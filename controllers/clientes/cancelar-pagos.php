<?php
// Prevenir acceso directo a este script
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Acceso denegado');
}

// Verificar que se haya recibido el ID del pago a cancelar
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['registro_pago']) || empty($data['registro_pago'])) {
    echo json_encode([
        'success' => false,
        'message' => 'El ID del pago es requerido'
    ]);
    exit;
}

session_start();
// Verificar que el usuario esté autenticado
if (!isset($_SESSION['idEmpleado'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Acceso denegado'
    ]);
    exit;
}

$registro_pago = intval($data['registro_pago']);
$empleado_id = $_SESSION['idEmpleado'] ?? null;
$ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';

// Incluir el archivo de conexión a la base de datos
require_once '../../models/conexion.php';

try {
    // Iniciar transacción
    $conn->autocommit(false);
    
    // 1. Obtener información del pago antes de eliminarlo
    $stmt = $conn->prepare("SELECT idCliente, monto, metodo, numCaja, fecha FROM clientes_historialpagos WHERE registro = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta del historial de pagos: " . $conn->error);
    }
    
    $stmt->bind_param('i', $registro_pago);
    $stmt->execute();
    $result = $stmt->get_result();
    $pago = $result->fetch_assoc();
    $stmt->close();
    
    if (!$pago) {
        throw new Exception("No se encontró el pago con ID: " . $registro_pago);
    }
    
    $idCliente = $pago['idCliente'];
    $monto_pago = $pago['monto'];
    $metodo_pago = $pago['metodo'];
    $numCaja = $pago['numCaja'];
    $fecha_pago = $pago['fecha'];
    
    // NUEVO: Verificar que el pago no tenga más de 24 horas (usando zona horaria de Santo Domingo, RD)
    // Establecer zona horaria de Santo Domingo
    date_default_timezone_set('America/Santo_Domingo');
    
    $fecha_pago_timestamp = strtotime($fecha_pago);
    $fecha_actual_timestamp = time();
    $diferencia_horas = ($fecha_actual_timestamp - $fecha_pago_timestamp) / 3600;
    
    if ($diferencia_horas > 24) {
        throw new Exception("No se puede cancelar el pago porque han pasado más de 24 horas desde que se realizó");
    }
    
    // 2. Borrar el registro en historial de pago
    $stmt = $conn->prepare("DELETE FROM clientes_historialpagos WHERE registro = ?");
    if (!$stmt) {
        throw new Exception("Error preparando eliminación de historial de pagos: " . $conn->error);
    }
    
    $stmt->bind_param('i', $registro_pago);
    if (!$stmt->execute()) {
        throw new Exception("Error eliminando el registro de historial de pagos: " . $stmt->error);
    }
    $stmt->close();
    
    // 3. Borrar el registro de ingreso en la caja
    // Buscar el registro correspondiente en cajaingresos basado en información del pago
    $stmt = $conn->prepare("DELETE FROM cajaingresos WHERE IdEmpleado = ? AND numCaja = ? AND monto = ? AND razon LIKE ? AND fecha = ?");
    if (!$stmt) {
        throw new Exception("Error preparando eliminación de ingreso en caja: " . $conn->error);
    }
    
    $razon_like = "%Pago a cuenta del cliente: $idCliente%";
    $stmt->bind_param('issss', $empleado_id, $numCaja, $monto_pago, $razon_like, $fecha_pago);
    $stmt->execute();
    $stmt->close();
    
    // 4. Obtener todas las facturas del cliente ordenadas por fecha (más reciente primero)
    $stmt = $conn->prepare("SELECT registro, balance, total_ajuste AS total, estado, fecha FROM facturas WHERE idCliente = ? AND tipoFactura = 'credito' ORDER BY fecha DESC");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de facturas: " . $conn->error);
    }
    
    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $result = $stmt->get_result();
    $facturas = [];
    
    while ($row = $result->fetch_assoc()) {
        $facturas[] = $row;
    }
    $stmt->close();
    
    if (empty($facturas)) {
        throw new Exception("No se encontraron facturas para el cliente ID: " . $idCliente);
    }
    
    // 5. Distribuir el monto del pago cancelado entre las facturas
    $monto_restante = $monto_pago;
    
    foreach ($facturas as $factura) {
        if ($monto_restante <= 0) {
            break; // Ya se distribuyó todo el monto
        }
        
        $registro_factura = $factura['registro'];
        $balance_actual = $factura['balance'];
        $total_factura = $factura['total'];
        
        // Calcular cuánto se puede aplicar a esta factura
        $monto_a_aplicar = min($monto_restante, $total_factura - $balance_actual);
        
        if ($monto_a_aplicar > 0) {
            // Actualizar el balance de la factura
            $nuevo_balance = $balance_actual + $monto_a_aplicar;
            $estado_factura = "Pendiente";
            
            $stmt = $conn->prepare("UPDATE facturas SET balance = ?, estado = ? WHERE registro = ?");
            if (!$stmt) {
                throw new Exception("Error preparando actualización de factura: " . $conn->error);
            }
            
            $stmt->bind_param('dsi', $nuevo_balance, $estado_factura, $registro_factura);
            
            if (!$stmt->execute()) {
                throw new Exception("Error actualizando la factura: " . $stmt->error);
            }
            
            $stmt->close();
            
            // Reducir el monto restante
            $monto_restante -= $monto_a_aplicar;
        }
    }
    
    // 7. Actualizar el balance del cliente
    actualizarBalanceCliente($conn, $idCliente);
    
    // 8. Registrar la acción en la auditoría
    $detalles = "Cancelación de pago de cliente ID: $idCliente por un monto de $monto_pago";
    
    $stmt = $conn->prepare("INSERT INTO auditoria_usuarios (empleado_id, accion, detalles, ip) VALUES (?, 'Cancelar pago', ?, ?)");
    if (!$stmt) {
        throw new Exception("Error preparando registro de auditoría: " . $conn->error);
    }
    
    $stmt->bind_param('iss', $empleado_id, $detalles, $ip);
    
    if (!$stmt->execute()) {
        throw new Exception("Error registrando auditoría: " . $stmt->error);
    }
    $stmt->close();
    
    // Confirmar la transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pago cancelado correctamente'
    ]);
    
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al cancelar el pago: ' . $e->getMessage()
    ]);
}

// Función para actualizar el balance del cliente
function actualizarBalanceCliente($conn, $idCliente) {
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

    // Obtener la suma de todos los balances pendientes de facturas
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

    // Calcular el nuevo balance disponible
    $balanceDisponible = $limiteCredito - $balancePendiente;

    // Actualizar el balance disponible en clientes_cuenta
    $stmt = $conn->prepare("UPDATE clientes_cuenta SET balance = ? WHERE idCliente = ?");

    if (!$stmt) {
        throw new Exception("Error preparando actualización de balance: " . $conn->error);
    }

    $stmt->bind_param('di', $balanceDisponible, $idCliente);

    if (!$stmt->execute()) {
        throw new Exception("Error actualizando balance del cliente: " . $stmt->error);
    }
    
    $stmt->close();
}
?>