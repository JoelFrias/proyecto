<?php

require_once '../../core/verificar-sesion.php';
require_once '../../core/conexion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'ALM004';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error: INSUFFICIENT_PERMISSIONS']);
    exit();
}

header('Content-Type: application/json');

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch($accion) {
    case 'listar_productos':
        listarProductos($conn);
        break;
    case 'crear_entrada':
        crearEntrada($conn);
        break;
    case 'listar_entradas':
        listarEntradas($conn);
        break;
    case 'obtener_entrada':
        obtenerEntrada($conn);
        break;
    case 'cancelar_entrada':
        cancelarEntrada($conn);
        break;
    case 'obtener_cancelacion':
        obtenerCancelacion($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function listarProductos($conn) {
    try {
        $query = "
            SELECT p.id, p.descripcion, p.existencia, p.precioCompra, 
                   p.precioVenta1, p.precioVenta2, pt.descripcion as tipo
            FROM productos p
            LEFT JOIN productos_tipo pt ON p.idTipo = pt.id
            WHERE p.activo = 1
            ORDER BY p.descripcion
        ";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $productos = [];
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
        
        echo json_encode(['success' => true, 'productos' => $productos]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Calcula el costo promedio ponderado de un producto
 * Formula: ∑(cantidad×costo) / ∑cantidad
 */
function calcularCostoPromedioPonderado($conn, $id_producto, $nueva_cantidad, $nuevo_costo) {
    try {
        // Obtener la existencia actual y el costo actual del producto
        $stmt = $conn->prepare("SELECT existencia, precioCompra FROM productos WHERE id = ?");
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $result = $stmt->get_result();
        $producto = $result->fetch_assoc();
        $stmt->close();
        
        if (!$producto) {
            throw new Exception("Producto no encontrado");
        }
        
        $existencia_actual = floatval($producto['existencia']);
        $costo_actual = floatval($producto['precioCompra']);
        
        // Si no hay existencia actual, el costo promedio es el nuevo costo
        if ($existencia_actual <= 0) {
            return $nuevo_costo;
        }
        
        // Calcular el costo promedio ponderado
        // Formula: [(existencia_actual × costo_actual) + (nueva_cantidad × nuevo_costo)] / (existencia_actual + nueva_cantidad)
        $suma_costos = ($existencia_actual * $costo_actual) + ($nueva_cantidad * $nuevo_costo);
        $suma_cantidades = $existencia_actual + $nueva_cantidad;
        
        $costo_promedio = $suma_costos / $suma_cantidades;
        
        return round($costo_promedio, 2);
        
    } catch(Exception $e) {
        throw new Exception("Error al calcular costo promedio: " . $e->getMessage());
    }
}

/**
 * Ajusta automáticamente los precios de venta si son menores o iguales al costo
 * Regla: precioVenta2 > precioVenta1 > costo
 * Si precioVenta1 <= costo: precioVenta1 = costo * 1.25 (25% margen mínimo)
 * Si precioVenta2 <= precioVenta1: precioVenta2 = precioVenta1 * 1.15 (15% diferencia mínima)
 */
function ajustarPreciosVenta($conn, $id_producto, $nuevo_costo_promedio) {
    try {
        $stmt = $conn->prepare("SELECT descripcion, precioVenta1, precioVenta2 FROM productos WHERE id = ?");
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $result = $stmt->get_result();
        $producto = $result->fetch_assoc();
        $stmt->close();
        
        if (!$producto) {
            return [
                'ajustado' => false, 
                'mensaje' => 'Producto no encontrado',
                'precioVenta1' => 0,
                'precioVenta2' => 0
            ];
        }
        
        $precioVenta1_original = floatval($producto['precioVenta1']);
        $precioVenta2_original = floatval($producto['precioVenta2']);
        $descripcion = $producto['descripcion'];
        
        $precioVenta1_nuevo = $precioVenta1_original;
        $precioVenta2_nuevo = $precioVenta2_original;
        $ajustes_realizados = [];
        
        // Ajustar precioVenta1 si es menor o igual al costo
        if ($precioVenta1_nuevo <= $nuevo_costo_promedio) {
            $precioVenta1_nuevo = round($nuevo_costo_promedio * 1.25, 2); // 25% de margen mínimo
            $ajustes_realizados[] = "Precio Venta 1: RD$ $precioVenta1_original → RD$ $precioVenta1_nuevo";
        }
        
        // Ajustar precioVenta2 si es menor o igual a precioVenta1
        if ($precioVenta2_nuevo <= $precioVenta1_nuevo) {
            $precioVenta2_nuevo = round($precioVenta1_nuevo * 1.15, 2); // 15% más que precioVenta1
            $ajustes_realizados[] = "Precio Venta 2: RD$ $precioVenta2_original → RD$ $precioVenta2_nuevo";
        }
        
        // Si se realizaron ajustes, actualizar en la base de datos
        if (!empty($ajustes_realizados)) {
            $stmt_update = $conn->prepare("UPDATE productos SET precioVenta1 = ?, precioVenta2 = ? WHERE id = ?");
            $stmt_update->bind_param("ddi", $precioVenta1_nuevo, $precioVenta2_nuevo, $id_producto);
            $stmt_update->execute();
            $stmt_update->close();
            
            return [
                'ajustado' => true,
                'producto' => $descripcion,
                'ajustes' => $ajustes_realizados,
                'precioVenta1' => $precioVenta1_nuevo,
                'precioVenta2' => $precioVenta2_nuevo,
                'costo_promedio' => $nuevo_costo_promedio
            ];
        }
        
        return [
            'ajustado' => false,
            'precioVenta1' => $precioVenta1_nuevo,
            'precioVenta2' => $precioVenta2_nuevo
        ];
        
    } catch(Exception $e) {
        return [
            'ajustado' => false, 
            'mensaje' => 'Error al ajustar precios: ' . $e->getMessage(),
            'precioVenta1' => 0,
            'precioVenta2' => 0
        ];
    }
}

function crearEntrada($conn) {
    try {
        $empleado = $_SESSION['idEmpleado']; 
        $productos = json_decode($_POST['productos'], true);
        $referencia = $_POST['referencia'] ?? '';
        
        if(empty($productos)) {
            echo json_encode(['success' => false, 'message' => 'Debe agregar al menos un producto']);
            return;
        }
        
        // Validar productos duplicados
        $productos_ids = array_column($productos, 'id_producto');
        $productos_unicos = array_unique($productos_ids);
        
        if (count($productos_ids) !== count($productos_unicos)) {
            // Encontrar cuáles son los duplicados para mostrar en el mensaje
            $duplicados = array_diff_assoc($productos_ids, $productos_unicos);
            $ids_duplicados = array_unique($duplicados);
            
            // Obtener nombres de productos duplicados
            $placeholders = implode(',', array_fill(0, count($ids_duplicados), '?'));
            $stmt = $conn->prepare("SELECT descripcion FROM productos WHERE id IN ($placeholders)");
            $types = str_repeat('i', count($ids_duplicados));
            $stmt->bind_param($types, ...$ids_duplicados);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $nombres_duplicados = [];
            while($row = $result->fetch_assoc()) {
                $nombres_duplicados[] = $row['descripcion'];
            }
            $stmt->close();
            
            $mensaje = 'No se pueden agregar productos duplicados en la misma orden de entrada. Productos duplicados: ' . implode(', ', $nombres_duplicados);
            echo json_encode(['success' => false, 'message' => $mensaje]);
            return;
        }
        
        $conn->begin_transaction();
        
        // Insertar entrada principal
        $stmt = $conn->prepare("
            INSERT INTO inventario_entradas (fecha, empleado, referencia, estado)
            VALUES (NOW(), ?, ?, 'activo')
        ");
        $stmt->bind_param("is", $empleado, $referencia);
        $stmt->execute();
        $id_entrada = $conn->insert_id;
        $stmt->close();
        
        // Preparar statements
        $stmt_detalle = $conn->prepare("
            INSERT INTO inventario_entradas_detalle (id_entrada, id_producto, cantidad, costo, fecha)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        // CRÍTICO: Actualizar inventario (almacén general)
        $stmt_update_inventario = $conn->prepare("
            UPDATE inventario 
            SET existencia = existencia + ?,
                ultima_actualizacion = NOW()
            WHERE idProducto = ?
        ");
        
        // Actualizar existencia total y costo en productos
        $stmt_update_producto = $conn->prepare("
            UPDATE productos 
            SET existencia = existencia + ?, 
                precioCompra = ?
            WHERE id = ?
        ");
        
        $productos_ajustados = [];
        
        foreach($productos as $prod) {
            // Calcular costo promedio ponderado
            $costo_promedio = calcularCostoPromedioPonderado(
                $conn, 
                $prod['id_producto'], 
                $prod['cantidad'], 
                $prod['costo']
            );
            
            // Ajustar precios de venta si es necesario
            $ajuste = ajustarPreciosVenta($conn, $prod['id_producto'], $costo_promedio);
            
            if ($ajuste['ajustado']) {
                $productos_ajustados[] = [
                    'producto' => $ajuste['producto'],
                    'ajustes' => $ajuste['ajustes'],
                    'costo_promedio' => $ajuste['costo_promedio'],
                    'precioVenta1' => $ajuste['precioVenta1'],
                    'precioVenta2' => $ajuste['precioVenta2']
                ];
            }
            
            // Insertar detalle
            $stmt_detalle->bind_param("iidd", 
                $id_entrada,
                $prod['id_producto'],
                $prod['cantidad'],
                $prod['costo']
            );
            $stmt_detalle->execute();
            
            // CRÍTICO: Actualizar existencia en inventario (almacén general)
            $stmt_update_inventario->bind_param("di",
                $prod['cantidad'],
                $prod['id_producto']
            );
            $stmt_update_inventario->execute();
            
            // Actualizar existencia total y costo promedio en productos
            $stmt_update_producto->bind_param("ddi",
                $prod['cantidad'],
                $costo_promedio,
                $prod['id_producto']
            );
            $stmt_update_producto->execute();
        }
        
        $stmt_detalle->close();
        $stmt_update_inventario->close();
        $stmt_update_producto->close();
        
        $conn->commit();
        
        $response = [
            'success' => true, 
            'message' => 'Entrada registrada exitosamente',
            'id_entrada' => $id_entrada
        ];
        
        // Agregar información sobre ajustes si los hubo
        if (!empty($productos_ajustados)) {
            $response['precios_ajustados'] = true;
            $response['ajustes'] = $productos_ajustados;
        }
        
        echo json_encode($response);
        
    } catch(Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function listarEntradas($conn) {
    try {
        $limite = (int)($_GET['limite'] ?? 10);
        $pagina = (int)($_GET['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;
        
        $where = [];
        $params = [];
        $types = '';
        
        if(!empty($_GET['estado'])) {
            $where[] = "ie.estado = ?";
            $params[] = $_GET['estado'];
            $types .= 's';
        }
        
        if(!empty($_GET['fecha_desde'])) {
            $where[] = "DATE(ie.fecha) >= ?";
            $params[] = $_GET['fecha_desde'];
            $types .= 's';
        }
        
        if(!empty($_GET['fecha_hasta'])) {
            $where[] = "DATE(ie.fecha) <= ?";
            $params[] = $_GET['fecha_hasta'];
            $types .= 's';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "
            SELECT 
                ie.id,
                ie.fecha,
                CONCAT(e.nombre, ' ', e.apellido) AS empleado,
                ie.estado,
                COUNT(ied.id_producto) as total_productos,
                SUM(ied.cantidad) as total_cantidad,
                SUM(ied.cantidad * ied.costo) as total_costo
            FROM inventario_entradas ie
            LEFT JOIN inventario_entradas_detalle ied ON ie.id = ied.id_entrada
            LEFT JOIN empleados e ON e.id = ie.empleado
            $whereClause
            GROUP BY ie.id
            ORDER BY ie.fecha DESC
            LIMIT $limite OFFSET $offset
        ";
        
        if(!empty($params)) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($query);
        }
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $entradas = [];
        while ($row = $result->fetch_assoc()) {
            $entradas[] = $row;
        }
        
        $countQuery = "SELECT COUNT(*) as total FROM inventario_entradas ie $whereClause";
        
        if(!empty($params)) {
            $stmt_count = $conn->prepare($countQuery);
            $stmt_count->bind_param($types, ...$params);
            $stmt_count->execute();
            $result_total = $stmt_count->get_result();
        } else {
            $result_total = $conn->query($countQuery);
        }
        
        $total = $result_total->fetch_assoc()['total'];
        
        echo json_encode([
            'success' => true, 
            'entradas' => $entradas,
            'total' => $total,
            'pagina' => $pagina,
            'total_paginas' => ceil($total / $limite)
        ]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function obtenerEntrada($conn) {
    try {
        $id_entrada = (int)($_GET['id'] ?? 0);
        
        $stmt = $conn->prepare("SELECT
                                    ie.*,
                                    CONCAT(e.nombre, ' ', e.apellido) AS empleado_nombre
                                FROM
                                    inventario_entradas ie
                                LEFT JOIN empleados e ON e.id = ie.empleado
                                WHERE ie.id = ?");

        $stmt->bind_param("i", $id_entrada);
        $stmt->execute();
        $result = $stmt->get_result();
        $entrada = $result->fetch_assoc();
        $stmt->close();
        
        if(!$entrada) {
            echo json_encode(['success' => false, 'message' => 'Entrada no encontrada']);
            return;
        }
        
        $stmt = $conn->prepare("
            SELECT 
                ied.*,
                p.descripcion,
                pt.descripcion as tipo
            FROM inventario_entradas_detalle ied
            INNER JOIN productos p ON ied.id_producto = p.id
            LEFT JOIN productos_tipo pt ON p.idTipo = pt.id
            WHERE ied.id_entrada = ?
        ");
        $stmt->bind_param("i", $id_entrada);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $detalle = [];
        while ($row = $result->fetch_assoc()) {
            $detalle[] = $row;
        }
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'entrada' => $entrada,
            'detalle' => $detalle
        ]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function cancelarEntrada($conn) {
    try {
        $id_entrada = (int)($_POST['id_entrada'] ?? 0);
        $notas = $_POST['notas'] ?? '';
        $empleado = (int)($_SESSION['idEmpleado'] ?? 1);
        
        $conn->begin_transaction();
        
        $stmt = $conn->prepare("SELECT estado FROM inventario_entradas WHERE id = ?");
        $stmt->bind_param("i", $id_entrada);
        $stmt->execute();
        $result = $stmt->get_result();
        $entrada = $result->fetch_assoc();
        $stmt->close();
        
        if(!$entrada) {
            echo json_encode(['success' => false, 'message' => 'Entrada no encontrada']);
            return;
        }
        
        if($entrada['estado'] == 'cancelado') {
            echo json_encode(['success' => false, 'message' => 'La entrada ya está cancelada']);
            return;
        }
        
        // Obtener detalle de productos para recalcular costos
        $stmt = $conn->prepare("
            SELECT id_producto, cantidad, costo FROM inventario_entradas_detalle WHERE id_entrada = ?
        ");
        $stmt->bind_param("i", $id_entrada);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $productos = [];
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
        $stmt->close();
        
        // Recalcular costo promedio para cada producto
        $stmt_update_producto = $conn->prepare("
            UPDATE productos SET existencia = existencia - ?, precioCompra = ? WHERE id = ?
        ");
        
        foreach($productos as $prod) {
            // Obtener datos actuales del producto
            $stmt = $conn->prepare("SELECT existencia, precioCompra FROM productos WHERE id = ?");
            $stmt->bind_param("i", $prod['id_producto']);
            $stmt->execute();
            $result = $stmt->get_result();
            $producto_actual = $result->fetch_assoc();
            $stmt->close();
            
            $existencia_actual = floatval($producto_actual['existencia']);
            $costo_actual = floatval($producto_actual['precioCompra']);
            
            // Calcular el nuevo costo promedio sin esta entrada
            // Formula: [(existencia_actual × costo_actual) - (cantidad_cancelada × costo_cancelado)] / (existencia_actual - cantidad_cancelada)
            $nueva_existencia = $existencia_actual - $prod['cantidad'];
            
            if ($nueva_existencia > 0) {
                $suma_costos_actual = $existencia_actual * $costo_actual;
                $suma_costos_cancelados = $prod['cantidad'] * $prod['costo'];
                $nuevo_costo_promedio = ($suma_costos_actual - $suma_costos_cancelados) / $nueva_existencia;
                $nuevo_costo_promedio = round($nuevo_costo_promedio, 2);
            } else {
                $nuevo_costo_promedio = 0;
            }
            
            $stmt_update_producto->bind_param("ddi", $prod['cantidad'], $nuevo_costo_promedio, $prod['id_producto']);
            $stmt_update_producto->execute();
        }
        
        $stmt_update_producto->close();
        
        // Marcar entrada como cancelada
        $stmt = $conn->prepare("UPDATE inventario_entradas SET estado = 'cancelado' WHERE id = ?");
        $stmt->bind_param("i", $id_entrada);
        $stmt->execute();
        $stmt->close();
        
        // Registrar cancelación
        $stmt = $conn->prepare("
            INSERT INTO inventario_entradas_canceladas (id_entrada, fecha, empleado, notas)
            VALUES (?, NOW(), ?, ?)
        ");
        $stmt->bind_param("iis", $id_entrada, $empleado, $notas);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Entrada cancelada exitosamente']);
        
    } catch(Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function obtenerCancelacion($conn) {
    try {
        $id_entrada = (int)($_GET['id'] ?? 0);
        
        $stmt = $conn->prepare("SELECT
                                    iec.*,
                                    CONCAT(e.nombre, ' ', e.apellido) AS nombre_empleado
                                FROM
                                    inventario_entradas_canceladas iec
                                LEFT JOIN empleados e ON e.id = iec.empleado
                                WHERE iec.id_entrada = ?");
        $stmt->bind_param("i", $id_entrada);
        $stmt->execute();
        $result = $stmt->get_result();
        $cancelacion = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'cancelacion' => $cancelacion
        ]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

$conn->close();
?>