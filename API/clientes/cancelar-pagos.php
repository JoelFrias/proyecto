<?php

require_once '../../core/conexion.php';

// Verificar conexión a la base de datos
if (!$conn || $conn->connect_errno !== 0) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos",
        "error_code" => "DATABASE_CONNECTION_ERROR"
    ]));
}

require_once '../../core/verificar-sesion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'CLI004';
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

$registro_pago = intval($data['registro_pago']);
$empleado_cancela = $_SESSION['idEmpleado'] ?? null;

// Obtener el motivo (OBLIGATORIO)
if (!isset($data['motivo']) || empty(trim($data['motivo']))) {
    echo json_encode([
        'success' => false,
        'message' => 'El motivo de cancelación es obligatorio'
    ]);
    exit;
}

$motivo_cancelacion = trim($data['motivo']);

// Validar longitud del motivo
if (strlen($motivo_cancelacion) < 10) {
    echo json_encode([
        'success' => false,
        'message' => 'El motivo debe tener al menos 10 caracteres'
    ]);
    exit;
}

if (strlen($motivo_cancelacion) > 500) {
    echo json_encode([
        'success' => false,
        'message' => 'El motivo no puede exceder 500 caracteres'
    ]);
    exit;
}

try {
    // Iniciar transacción
    $conn->autocommit(false);
    
    // 1. Obtener información del pago antes de cancelarlo
    $stmt = $conn->prepare("
        SELECT 
            idCliente, 
            monto, 
            metodo, 
            numCaja, 
            fecha,
            idEmpleado,
            estado
        FROM clientes_historialpagos 
        WHERE registro = ?
    ");
    
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
    
    // Verificar que el pago no esté ya cancelado
    if ($pago['estado'] === 'cancelado') {
        throw new Exception("Este pago ya fue cancelado anteriormente");
    }
    
    $idCliente = $pago['idCliente'];
    $monto_pago = $pago['monto'];
    $metodo_pago = $pago['metodo'];
    $numCaja = $pago['numCaja'];
    $fecha_pago = $pago['fecha'];
    $empleado_pago = $pago['idEmpleado'];
    
    // 2. VALIDACIÓN: Verificar que el pago no tenga más de 24 horas
    date_default_timezone_set('America/Santo_Domingo');
    
    $fecha_pago_timestamp = strtotime($fecha_pago);
    $fecha_actual_timestamp = time();
    $diferencia_horas = ($fecha_actual_timestamp - $fecha_pago_timestamp) / 3600;
    
    if ($diferencia_horas > 24) {
        throw new Exception("No se puede cancelar el pago porque han pasado más de 24 horas desde que se realizó");
    }
    
    // 3. VALIDACIÓN: Verificar que la caja donde se hizo el pago esté abierta
    $stmt = $conn->prepare("
        SELECT COUNT(*) as esta_abierta 
        FROM cajasabiertas 
        WHERE numCaja = ? AND idEmpleado = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparando consulta de caja abierta: " . $conn->error);
    }
    
    $stmt->bind_param('si', $numCaja, $empleado_pago);
    $stmt->execute();
    $result = $stmt->get_result();
    $caja_estado = $result->fetch_assoc();
    $stmt->close();
    
    if ($caja_estado['esta_abierta'] == 0) {
        throw new Exception("No se puede cancelar el pago porque la caja " . $numCaja . " ya fue cerrada");
    }
    
    // 4. Marcar el pago como cancelado (NO eliminarlo)
    $fecha_cancelacion = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("
        UPDATE clientes_historialpagos 
        SET 
            estado = 'cancelado',
            fecha_cancelacion = ?,
            cancelado_por = ?,
            motivo_cancelacion = ?
        WHERE registro = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparando actualización de pago: " . $conn->error);
    }
    
    $stmt->bind_param('sisi', $fecha_cancelacion, $empleado_cancela, $motivo_cancelacion, $registro_pago);
    
    if (!$stmt->execute()) {
        throw new Exception("Error marcando el pago como cancelado: " . $stmt->error);
    }
    
    $stmt->close();
    
    // 5. Registrar EGRESO en la caja (devolución del dinero)
    $razon_egreso = "Cancelación de pago #$registro_pago - Cliente: $idCliente";
    
    $stmt = $conn->prepare("
        INSERT INTO cajaegresos (metodo, monto, IdEmpleado, numCaja, razon, fecha)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparando inserción de egreso: " . $conn->error);
    }
    
    $stmt->bind_param('sdisss', $metodo_pago, $monto_pago, $empleado_cancela, $numCaja, $razon_egreso, $fecha_cancelacion);
    
    if (!$stmt->execute()) {
        throw new Exception("Error registrando egreso en caja: " . $stmt->error);
    }
    
    $stmt->close();
    
    // 6. Obtener todas las facturas del cliente ordenadas por fecha (más antigua primero para revertir)
    $stmt = $conn->prepare("
        SELECT 
            registro, 
            balance, 
            total_ajuste AS total, 
            estado, 
            fecha 
        FROM facturas 
        WHERE idCliente = ? AND tipoFactura = 'credito' 
        ORDER BY fecha ASC
    ");
    
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
    
    // 7. Revertir el pago: Aumentar el balance de las facturas
    // Empezamos desde la más antigua (donde primero se aplicó el pago)
    $monto_restante = $monto_pago;
    
    foreach ($facturas as $factura) {
        if ($monto_restante <= 0) {
            break;
        }
        
        $registro_factura = $factura['registro'];
        $balance_actual = $factura['balance'];
        $total_factura = $factura['total'];
        
        // Calcular cuánto podemos revertir en esta factura
        // No podemos aumentar el balance más allá del total de la factura
        $espacio_disponible = $total_factura - $balance_actual;
        $monto_a_revertir = min($monto_restante, $espacio_disponible);
        
        if ($monto_a_revertir > 0) {
            // Aumentar el balance de la factura
            $nuevo_balance = $balance_actual + $monto_a_revertir;
            
            // Determinar el nuevo estado de la factura
            $estado_factura = "Pendiente";
            if ($nuevo_balance >= $total_factura) {
                $estado_factura = "Pendiente"; // Totalmente pendiente de nuevo
            }
            
            $stmt = $conn->prepare("
                UPDATE facturas 
                SET balance = ?, estado = ? 
                WHERE registro = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Error preparando actualización de factura: " . $conn->error);
            }
            
            $stmt->bind_param('dsi', $nuevo_balance, $estado_factura, $registro_factura);
            
            if (!$stmt->execute()) {
                throw new Exception("Error actualizando la factura: " . $stmt->error);
            }
            
            $stmt->close();
            
            // Reducir el monto restante
            $monto_restante -= $monto_a_revertir;
        }
    }
    
    // 8. Actualizar el balance del cliente
    actualizarBalanceCliente($conn, $idCliente);
    
    // 9. Confirmar la transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pago cancelado correctamente',
        'data' => [
            'registro_pago' => $registro_pago,
            'monto_devuelto' => $monto_pago,
            'numCaja' => $numCaja,
            'fecha_cancelacion' => $fecha_cancelacion
        ]
    ]);
    
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
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