<?php

require_once('../../core/conexion.php');
require_once '../../core/verificar-sesion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'FAC002';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    http_response_code(403);
    die(json_encode([
        "success" => false, 
        "error" => "No tiene permisos para realizar esta acción",
        "error_code" => "INSUFFICIENT_PERMISSIONS",
        "solution" => "Contacte al administrador del sistema para obtener los permisos necesarios"
    ]));
}

// Verificar si hay una sesión de usuario activa
if (!isset($_SESSION['idEmpleado'])) {
    echo json_encode(['success' => false, 'message' => 'No hay sesión de usuario']);
    exit;
}

// Verificar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener los datos del formulario
$numFactura = isset($_POST['numFactura']) ? intval($_POST['numFactura']) : 0;
$motivo = isset($_POST['motivo']) ? $conn->real_escape_string($_POST['motivo']) : '';
$cajaAlternativa = isset($_POST['caja_alternativa']) ? $conn->real_escape_string($_POST['caja_alternativa']) : null;
$idEmpleadoCancela = $_SESSION['idEmpleado'];

// Validaciones iniciales
if (empty($numFactura) || empty($motivo)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Iniciar transacción
$conn->begin_transaction();

try {
    // 1. Verificar si la factura existe y no está ya cancelada
    $sql = "SELECT
                f.estado AS estado,
                f.total AS total,
                f.idCliente AS idCliente,
                f.idEmpleado AS idEmpleadoVendedor,
                f.fecha AS fecha_factura,
                f.tipoFactura AS tipoFactura
            FROM
                facturas AS f
            WHERE
                f.numFactura = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $numFactura);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('La factura no existe');
    }
    
    $factura = $result->fetch_assoc();
    
    if ($factura['estado'] === 'Cancelada') {
        throw new Exception('Esta factura ya ha sido cancelada');
    }
    
    error_log("Factura #$numFactura - Fecha original en BD: " . $factura['fecha_factura']);

    // 2. Verificar si han pasado más de 72 horas (3 días) desde la facturación
    try {
        date_default_timezone_set('America/Santo_Domingo');
        
        $fechaFacturaObj = new DateTime($factura['fecha_factura']);
        $fechaFacturaTimestamp = $fechaFacturaObj->getTimestamp();
        $fechaFacturaFormateada = $fechaFacturaObj->format('d/m/Y h:i A');
        
        $fechaActualTimestamp = time();
        
        $diferenciaSegundos = $fechaActualTimestamp - $fechaFacturaTimestamp;
        $horasPasadas = $diferenciaSegundos / 3600;
        
        if ($horasPasadas > 72) {
            $mensaje = sprintf(
                'No se puede cancelar la factura. Han pasado %d horas y %d minutos desde su emisión. ' .
                'La factura fue emitida el %s. El tiempo límite para cancelaciones es de 3 días.',
                floor($horasPasadas),
                floor(($horasPasadas - floor($horasPasadas)) * 60),
                $fechaFacturaFormateada
            );
            throw new Exception($mensaje);
        }
                 
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'No se puede cancelar la factura') === 0) {
            throw $e;
        } else {
            error_log("Error al calcular tiempo para factura #$numFactura: " . $e->getMessage());
        }
    }

    $idEmpleadoVendedor = $factura['idEmpleadoVendedor'];

    // 3. Obtener información del método de pago
    $sqlCaja = "SELECT
                    fm.metodo,
                    fm.noCaja,
                    fm.monto
                FROM
                    facturas_metodopago AS fm
                WHERE
                    fm.numFactura = ?";
    
    $stmtCaja = $conn->prepare($sqlCaja);
    $stmtCaja->bind_param("i", $numFactura);
    $stmtCaja->execute();
    $resultCaja = $stmtCaja->get_result();
    
    if ($resultCaja->num_rows === 0) {
        throw new Exception('Factura no encontrada en método de pago');
    }
    $metodoPago = $resultCaja->fetch_assoc();
    
    // 4. NUEVA LÓGICA: Verificar si requiere devolución y manejar la caja
    $requiereDevolucion = ($metodoPago['metodo'] == 'efectivo' && $metodoPago['monto'] > 0);
    $numCaja_original = $metodoPago['noCaja'];
    $numCaja_egreso = null;
    
    if ($requiereDevolucion) {
        // Verificar si la caja del vendedor está abierta
        $sqlVerificarCaja = "SELECT COUNT(*) as caja_abierta 
                            FROM cajasabiertas 
                            WHERE numCaja = ? AND idEmpleado = ?";
        
        $stmtVerificarCaja = $conn->prepare($sqlVerificarCaja);
        $stmtVerificarCaja->bind_param("si", $numCaja_original, $idEmpleadoVendedor);
        $stmtVerificarCaja->execute();
        $resultVerificarCaja = $stmtVerificarCaja->get_result();
        $cajaAbierta = $resultVerificarCaja->fetch_assoc()['caja_abierta'];
        
        if ($cajaAbierta > 0) {
            // La caja original está abierta, usarla
            $numCaja_egreso = $numCaja_original;
        } else {
            // La caja original está cerrada
            if ($cajaAlternativa === null) {
                // No se proporcionó caja alternativa, retornar lista de cajas abiertas
                $stmt = $conn->prepare("
                    SELECT DISTINCT ca.numCaja, CONCAT(e.nombre, ' ', e.apellido) as empleado
                    FROM cajasabiertas ca
                    JOIN empleados e ON ca.idEmpleado = e.id
                    ORDER BY ca.numCaja
                ");
                
                if (!$stmt) {
                    throw new Exception("Error preparando consulta de cajas abiertas: " . $conn->error);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                $cajas_abiertas = [];
                
                while ($row = $result->fetch_assoc()) {
                    $cajas_abiertas[] = $row;
                }
                $stmt->close();
                
                if (empty($cajas_abiertas)) {
                    throw new Exception("No hay cajas abiertas disponibles para realizar el egreso de devolución");
                }
                
                // Retornar que se necesita seleccionar una caja
                echo json_encode([
                    'success' => false,
                    'requires_cash_selection' => true,
                    'cajas_disponibles' => $cajas_abiertas,
                    'message' => 'La caja original está cerrada. Seleccione una caja abierta para realizar el egreso de devolución.'
                ]);
                exit;
            } else {
                // Validar que la caja alternativa proporcionada esté abierta
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as esta_abierta 
                    FROM cajasabiertas 
                    WHERE numCaja = ?
                ");
                
                if (!$stmt) {
                    throw new Exception("Error preparando consulta de caja alternativa: " . $conn->error);
                }
                
                $stmt->bind_param('s', $cajaAlternativa);
                $stmt->execute();
                $result = $stmt->get_result();
                $caja_alt_estado = $result->fetch_assoc();
                $stmt->close();
                
                if ($caja_alt_estado['esta_abierta'] == 0) {
                    throw new Exception("La caja seleccionada no está abierta");
                }
                
                $numCaja_egreso = $cajaAlternativa;
            }
        }
    }
    
    // 5. Registrar la cancelación en la tabla facturas_cancelaciones
    $fechaCancelacion = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO facturas_cancelaciones (numFactura, motivo, fecha, idEmpleado) 
            VALUES (?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issi", $numFactura, $motivo, $fechaCancelacion, $idEmpleadoCancela);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al registrar la cancelación');
    }
    
    // 6. Cambiar el estado de la factura a "Cancelada" (NO eliminarla)
    $sql = "UPDATE facturas SET estado = 'Cancelada', balance = 0 WHERE numFactura = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $numFactura);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar el estado de la factura');
    }
    
    // 7. Si requiere devolución (efectivo > 0), registrar EGRESO en la caja seleccionada
    if ($requiereDevolucion && $numCaja_egreso !== null) {
        // Limitar la razón a 50 caracteres
        if ($numCaja_egreso !== $numCaja_original) {
            $razonEgreso = "Dev. cancel. fact. #$numFactura (Caja alt.)";
        } else {
            $razonEgreso = "Devolución cancel. fact. #$numFactura";
        }
        
        $sql = "INSERT INTO cajaegresos (metodo, monto, IdEmpleado, numCaja, razon, fecha) 
                VALUES ('efectivo', ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("disis", 
            $metodoPago['monto'], 
            $idEmpleadoCancela, 
            $numCaja_egreso, 
            $razonEgreso,
            $fechaCancelacion
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Error al registrar el egreso en caja');
        }
    }

    // 8. Actualizar balance del cliente (solo si es a crédito)
    if ($factura['tipoFactura'] == 'credito') {
        $idCliente = $factura['idCliente'];

        $stmt = $conn->prepare("SELECT limite_credito FROM clientes_cuenta WHERE idCliente = ?");

        if (!$stmt) {
            throw new Exception("Error preparando consulta de límite de crédito: " . $conn->error);
        }

        $stmt->bind_param('i', $idCliente);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception("Cliente no encontrado: " . $idCliente);
        }

        $limiteCredito = $row['limite_credito'];

        $stmt = $conn->prepare("SELECT IFNULL(SUM(balance), 0) as balance_pendiente FROM facturas WHERE idCliente = ?");
        if (!$stmt) {
            throw new Exception("Error preparando consulta de balance pendiente: " . $conn->error);
        }

        $stmt->bind_param('i', $idCliente);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $balancePendiente = $row['balance_pendiente'];
        $balanceDisponible = $limiteCredito - $balancePendiente;

        $stmt = $conn->prepare("UPDATE clientes_cuenta SET balance = ? WHERE idCliente = ?");

        if (!$stmt) {
            throw new Exception("Error preparando actualización de balance: " . $conn->error);
        }

        $stmt->bind_param('di', $balanceDisponible, $idCliente);

        if (!$stmt->execute()) {
            throw new Exception("Error actualizando balance del cliente: " . $stmt->error);
        }
    }

    // 9. Devolver productos al inventario del empleado
    $sqldevb = "SELECT
                    fd.idProducto as id,
                    fd.cantidad as cantidad
                FROM
                    facturas_detalles fd
                WHERE
                    fd.numFactura = ?";

    $stmtdevb = $conn->prepare($sqldevb);
    if (!$stmtdevb) {
        throw new Exception("Error preparando consulta de productos: " . $conn->error);
    }

    $stmtdevb->bind_param("i", $numFactura);
    if (!$stmtdevb->execute()) {
        throw new Exception("Error ejecutando consulta de productos: " . $stmtdevb->error);
    }

    $resultdevb = $stmtdevb->get_result();
    if ($resultdevb->num_rows === 0) {
        throw new Exception('Error buscando los productos a devolver');
    }

    foreach ($resultdevb as $resultb) {
        // Actualizar inventario de empleados
        $sqlCheck = "SELECT idProducto FROM inventarioempleados WHERE idProducto = ? AND idEmpleado = ? LIMIT 1";
        $stmtCheck = $conn->prepare($sqlCheck);
        if (!$stmtCheck) {
            throw new Exception("Error preparando verificación de inventario: " . $conn->error);
        }

        $stmtCheck->bind_param("ii", $resultb['id'], $idEmpleadoVendedor);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            $sqldeva = "UPDATE inventarioempleados SET cantidad = cantidad + ? WHERE idProducto = ? AND idEmpleado = ?";
            $stmtdeva = $conn->prepare($sqldeva);
            if (!$stmtdeva) {
                throw new Exception("Error preparando actualización de inventario de empleado: " . $conn->error);
            }
            
            $stmtdeva->bind_param("iii", $resultb['cantidad'], $resultb['id'], $idEmpleadoVendedor);
            if (!$stmtdeva->execute()) {
                throw new Exception("Error actualizando inventario de empleado: " . $stmtdeva->error);
            }
        } else {
            $sqlInsert = "INSERT INTO inventarioempleados (idProducto, idEmpleado, cantidad) VALUES (?, ?, ?)";
            $stmtInsert = $conn->prepare($sqlInsert);
            if (!$stmtInsert) {
                throw new Exception("Error preparando inserción en inventario: " . $conn->error);
            }
            
            $stmtInsert->bind_param("iii", $resultb['id'], $idEmpleadoVendedor, $resultb['cantidad']);
            if (!$stmtInsert->execute()) {
                throw new Exception("Error insertando en inventario de empleado: " . $stmtInsert->error);
            }
        }

        $sqldevp = "UPDATE productos SET existencia = existencia + ? WHERE id = ?";
        $stmtdevp = $conn->prepare($sqldevp);
        if (!$stmtdevp) {
            throw new Exception("Error preparando actualización de existencia: " . $conn->error);
        }
        
        $stmtdevp->bind_param("ii", $resultb['cantidad'], $resultb['id']);
        if (!$stmtdevp->execute()) {
            throw new Exception("Error actualizando existencia del producto: " . $stmtdevp->error);
        }

        $stmtdevit = $conn->prepare("INSERT INTO inventariotransacciones (tipo, idProducto, cantidad, fecha, descripcion, idEmpleado) VALUES ('retorno', ?, ?, ?, 'Retorno por factura cancelada #".$numFactura."', ?)");
        if (!$stmtdevit) {
            throw new Exception("Error preparando registro de transacción de inventario: " . $conn->error);
        }

        $stmtdevit->bind_param('iisi', $resultb['id'], $resultb['cantidad'], $fechaCancelacion, $idEmpleadoCancela);
        if (!$stmtdevit->execute()) {
            throw new Exception("Error al registrar la transacción de inventario del producto ID: " . $resultb['id'] . " - " . $stmtdevit->error);
        }

        if ($stmtdevit->affected_rows === 0) {
            throw new Exception("No se registró la transacción de inventario para el producto ID: " . $resultb['id']);
        }
    }
    
    // 10. Confirmar los cambios
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Factura cancelada correctamente',
        'data' => [
            'numFactura' => $numFactura,
            'requirio_devolucion' => $requiereDevolucion,
            'metodo_pago' => $metodoPago['metodo'],
            'monto' => $metodoPago['monto'],
            'caja_original' => $numCaja_original,
            'caja_utilizada' => $numCaja_egreso,
            'caja_alternativa_usada' => $numCaja_egreso !== $numCaja_original
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>