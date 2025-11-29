<?php

require_once '../../core/conexion.php';
require_once '../../core/verificar-sesion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'ALM005';
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
    case 'listar_razones':
        listarRazones($conn);
        break;
    case 'crear_salida':
        crearSalida($conn);
        break;
    case 'listar_salidas':
        listarSalidas($conn);
        break;
    case 'obtener_salida':
        obtenerSalida($conn);
        break;
    case 'cancelar_salida':
        cancelarSalida($conn);
        break;
    case 'obtener_cancelacion':
        obtenerCancelacion($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function listarProductos($conn) {
    try {
        // Ahora usa inventario.existencia en lugar de productos.existencia
        $query = "
            SELECT 
                p.id, 
                p.descripcion, 
                i.existencia,
                p.precioCompra, 
                p.precioVenta1, 
                p.precioVenta2, 
                pt.descripcion as tipo
            FROM productos p
            INNER JOIN inventario i ON p.id = i.idProducto
            LEFT JOIN productos_tipo pt ON p.idTipo = pt.id
            WHERE p.activo = 1 AND i.existencia > 0
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

function listarRazones($conn) {
    try {
        $query = "SELECT id, descripcion FROM inventario_salidas_razones WHERE activo = 1 ORDER BY descripcion";
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $razones = [];
        while ($row = $result->fetch_assoc()) {
            $razones[] = $row;
        }
        
        echo json_encode(['success' => true, 'razones' => $razones]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function crearSalida($conn) {
    try {
        $empleado = $_SESSION['idEmpleado']; 
        $productos = json_decode($_POST['productos'], true);
        $razon = (int)$_POST['razon'];
        $notas = $_POST['notas'] ?? '';
        
        if(empty($productos)) {
            echo json_encode(['success' => false, 'message' => 'Debe agregar al menos un producto']);
            return;
        }
        
        if(empty($razon)) {
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar una razón']);
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
            
            $mensaje = 'No se pueden agregar productos duplicados en la misma salida. Productos duplicados: ' . implode(', ', $nombres_duplicados);
            echo json_encode(['success' => false, 'message' => $mensaje]);
            return;
        }
        
        $conn->begin_transaction();
        
        // Verificar existencias en INVENTARIO (almacén general)
        foreach($productos as $prod) {
            $stmt = $conn->prepare("
                SELECT i.existencia, p.descripcion 
                FROM inventario i
                INNER JOIN productos p ON i.idProducto = p.id
                WHERE i.idProducto = ?
            ");
            $stmt->bind_param("i", $prod['id_producto']);
            $stmt->execute();
            $result = $stmt->get_result();
            $inventario = $result->fetch_assoc();
            $stmt->close();
            
            if(!$inventario) {
                throw new Exception("Producto ID {$prod['id_producto']} no encontrado en inventario");
            }
            
            if($inventario['existencia'] < $prod['cantidad']) {
                throw new Exception("Stock insuficiente en almacén para {$inventario['descripcion']}. Disponible: {$inventario['existencia']}, Solicitado: {$prod['cantidad']}");
            }
        }
        
        // Insertar salida principal
        $stmt = $conn->prepare("
            INSERT INTO inventario_salidas (fecha, empleado, razon, notas, estado)
            VALUES (NOW(), ?, ?, ?, 'activo')
        ");
        $stmt->bind_param("iis", $empleado, $razon, $notas);
        $stmt->execute();
        $id_salida = $conn->insert_id;
        $stmt->close();
        
        // Insertar detalle de productos y actualizar inventario
        $stmt_detalle = $conn->prepare("
            INSERT INTO inventario_salidas_detalle (id_salida, id_producto, cantidad, costo, fecha)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        // Actualizar inventario (almacén general)
        $stmt_update_inventario = $conn->prepare("
            UPDATE inventario 
            SET existencia = existencia - ?,
                ultima_actualizacion = NOW()
            WHERE idProducto = ?
        ");
        
        // Actualizar existencia total en productos
        $stmt_update_producto = $conn->prepare("
            UPDATE productos 
            SET existencia = existencia - ?
            WHERE id = ?
        ");
        
        foreach($productos as $prod) {
            // Obtener el costo actual del producto
            $stmt = $conn->prepare("SELECT precioCompra FROM productos WHERE id = ?");
            $stmt->bind_param("i", $prod['id_producto']);
            $stmt->execute();
            $result = $stmt->get_result();
            $producto = $result->fetch_assoc();
            $costo_actual = $producto['precioCompra'];
            $stmt->close();
            
            // Insertar detalle
            $stmt_detalle->bind_param("iidd", 
                $id_salida,
                $prod['id_producto'],
                $prod['cantidad'],
                $costo_actual
            );
            $stmt_detalle->execute();
            
            // Actualizar existencia en inventario (almacén general)
            $stmt_update_inventario->bind_param("di",
                $prod['cantidad'],
                $prod['id_producto']
            );
            $stmt_update_inventario->execute();
            
            // Actualizar existencia total en productos
            $stmt_update_producto->bind_param("di",
                $prod['cantidad'],
                $prod['id_producto']
            );
            $stmt_update_producto->execute();
        }
        
        $stmt_detalle->close();
        $stmt_update_inventario->close();
        $stmt_update_producto->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Salida registrada exitosamente',
            'id_salida' => $id_salida
        ]);
        
    } catch(Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function listarSalidas($conn) {
    try {
        $limite = (int)($_GET['limite'] ?? 10);
        $pagina = (int)($_GET['pagina'] ?? 1);
        $offset = ($pagina - 1) * $limite;
        
        $where = [];
        $params = [];
        $types = '';
        
        if(!empty($_GET['estado'])) {
            $where[] = "isa.estado = ?";
            $params[] = $_GET['estado'];
            $types .= 's';
        }
        
        if(!empty($_GET['fecha_desde'])) {
            $where[] = "DATE(isa.fecha) >= ?";
            $params[] = $_GET['fecha_desde'];
            $types .= 's';
        }
        
        if(!empty($_GET['fecha_hasta'])) {
            $where[] = "DATE(isa.fecha) <= ?";
            $params[] = $_GET['fecha_hasta'];
            $types .= 's';
        }
        
        if(!empty($_GET['razon'])) {
            $where[] = "isa.razon = ?";
            $params[] = $_GET['razon'];
            $types .= 'i';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "
            SELECT 
                isa.id,
                isa.fecha,
                CONCAT(e.nombre, ' ', e.apellido) AS empleado,
                isr.descripcion as razon_texto,
                isa.estado,
                COUNT(isd.id_producto) as total_productos,
                SUM(isd.cantidad) as total_cantidad,
                SUM(isd.cantidad * isd.costo) as total_costo
            FROM inventario_salidas isa
            LEFT JOIN inventario_salidas_detalle isd ON isa.id = isd.id_salida
            LEFT JOIN empleados e ON e.id = isa.empleado
            LEFT JOIN inventario_salidas_razones isr ON isr.id = isa.razon
            $whereClause
            GROUP BY isa.id
            ORDER BY isa.fecha DESC
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
        
        $salidas = [];
        while ($row = $result->fetch_assoc()) {
            $salidas[] = $row;
        }
        
        $countQuery = "SELECT COUNT(*) as total FROM inventario_salidas isa $whereClause";
        
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
            'salidas' => $salidas,
            'total' => $total,
            'pagina' => $pagina,
            'total_paginas' => ceil($total / $limite)
        ]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function obtenerSalida($conn) {
    try {
        $id_salida = (int)($_GET['id'] ?? 0);
        
        $stmt = $conn->prepare("
            SELECT
                isa.*,
                CONCAT(e.nombre, ' ', e.apellido) AS empleado_nombre,
                isr.descripcion as razon_texto
            FROM inventario_salidas isa
            LEFT JOIN empleados e ON e.id = isa.empleado
            LEFT JOIN inventario_salidas_razones isr ON isr.id = isa.razon
            WHERE isa.id = ?
        ");
        $stmt->bind_param("i", $id_salida);
        $stmt->execute();
        $result = $stmt->get_result();
        $salida = $result->fetch_assoc();
        $stmt->close();
        
        if(!$salida) {
            echo json_encode(['success' => false, 'message' => 'Salida no encontrada']);
            return;
        }
        
        $stmt = $conn->prepare("
            SELECT 
                isd.*,
                p.descripcion,
                pt.descripcion as tipo
            FROM inventario_salidas_detalle isd
            INNER JOIN productos p ON isd.id_producto = p.id
            LEFT JOIN productos_tipo pt ON p.idTipo = pt.id
            WHERE isd.id_salida = ?
        ");
        $stmt->bind_param("i", $id_salida);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $detalle = [];
        while ($row = $result->fetch_assoc()) {
            $detalle[] = $row;
        }
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'salida' => $salida,
            'detalle' => $detalle
        ]);
        
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function cancelarSalida($conn) {
    try {
        $id_salida = (int)($_POST['id_salida'] ?? 0);
        $notas = $_POST['notas'] ?? '';
        $empleado = (int)($_SESSION['idEmpleado'] ?? 1);
        
        $conn->begin_transaction();
        
        $stmt = $conn->prepare("SELECT estado FROM inventario_salidas WHERE id = ?");
        $stmt->bind_param("i", $id_salida);
        $stmt->execute();
        $result = $stmt->get_result();
        $salida = $result->fetch_assoc();
        $stmt->close();
        
        if(!$salida) {
            echo json_encode(['success' => false, 'message' => 'Salida no encontrada']);
            return;
        }
        
        if($salida['estado'] == 'cancelado') {
            echo json_encode(['success' => false, 'message' => 'La salida ya está cancelada']);
            return;
        }
        
        // Obtener detalle de productos para revertir inventario
        $stmt = $conn->prepare("
            SELECT id_producto, cantidad FROM inventario_salidas_detalle WHERE id_salida = ?
        ");
        $stmt->bind_param("i", $id_salida);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $productos = [];
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
        $stmt->close();
        
        // Revertir inventario (devolver las cantidades al almacén general)
        $stmt_update_inventario = $conn->prepare("
            UPDATE inventario 
            SET existencia = existencia + ?,
                ultima_actualizacion = NOW()
            WHERE idProducto = ?
        ");
        
        // Actualizar existencia total en productos
        $stmt_update_producto = $conn->prepare("
            UPDATE productos 
            SET existencia = existencia + ?
            WHERE id = ?
        ");
        
        foreach($productos as $prod) {
            // Devolver al inventario (almacén general)
            $stmt_update_inventario->bind_param("di", $prod['cantidad'], $prod['id_producto']);
            $stmt_update_inventario->execute();
            
            // Actualizar existencia total en productos
            $stmt_update_producto->bind_param("di", $prod['cantidad'], $prod['id_producto']);
            $stmt_update_producto->execute();
        }
        
        $stmt_update_inventario->close();
        $stmt_update_producto->close();
        
        // Marcar salida como cancelada
        $stmt = $conn->prepare("UPDATE inventario_salidas SET estado = 'cancelado' WHERE id = ?");
        $stmt->bind_param("i", $id_salida);
        $stmt->execute();
        $stmt->close();
        
        // Registrar cancelación
        $stmt = $conn->prepare("
            INSERT INTO inventario_salidas_canceladas (id_salida, fecha, empleado, notas)
            VALUES (?, NOW(), ?, ?)
        ");
        $stmt->bind_param("iis", $id_salida, $empleado, $notas);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Salida cancelada exitosamente']);
        
    } catch(Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function obtenerCancelacion($conn) {
    try {
        $id_salida = (int)($_GET['id'] ?? 0);
        
        $stmt = $conn->prepare("
            SELECT
                isc.*,
                CONCAT(e.nombre, ' ', e.apellido) AS nombre_empleado
            FROM inventario_salidas_canceladas isc
            LEFT JOIN empleados e ON e.id = isc.empleado
            WHERE isc.id_salida = ?
        ");
        $stmt->bind_param("i", $id_salida);
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