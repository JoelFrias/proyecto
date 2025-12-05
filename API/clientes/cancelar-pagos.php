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
    $numCaja_original = $pago['numCaja'];
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
    
    // 3. NUEVA LÓGICA: Verificar si la caja original está abierta
    $stmt = $conn->prepare("
        SELECT COUNT(*) as esta_abierta 
        FROM cajasabiertas 
        WHERE numCaja = ? AND idEmpleado = ?
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparando consulta de caja abierta: " . $conn->error);
    }
    
    $stmt->bind_param('si', $numCaja_original, $empleado_pago);
    $stmt->execute();
    $result = $stmt->get_result();
    $caja_estado = $result->fetch_assoc();
    $stmt->close();
    
    $caja_original_abierta = $caja_estado['esta_abierta'] > 0;
    
    // Determinar qué caja usar para el egreso
    $numCaja_egreso = null;
    
    if ($caja_original_abierta) {
        // Si la caja original está abierta, usarla
        $numCaja_egreso = $numCaja_original;
    } else {
        // Si la caja original está cerrada, verificar si el usuario envió una caja alternativa
        if (!isset($data['caja_alternativa']) || empty($data['caja_alternativa'])) {
            // Si no se proporcionó caja alternativa, retornar lista de cajas abiertas
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
                throw new Exception("No hay cajas abiertas disponibles para realizar el egreso");
            }
            
            // Retornar que se necesita seleccionar una caja
            echo json_encode([
                'success' => false,
                'requires_cash_selection' => true,
                'cajas_disponibles' => $cajas_abiertas,
                'message' => 'La caja original está cerrada. Seleccione una caja abierta para realizar el egreso.'
            ]);
            exit;
        } else {
            // Validar que la caja alternativa proporcionada esté abierta
            $caja_alternativa = trim($data['caja_alternativa']);
            
            $stmt = $conn->prepare("
                SELECT COUNT(*) as esta_abierta 
                FROM cajasabiertas 
                WHERE numCaja = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Error preparando consulta de caja alternativa: " . $conn->error);
            }
            
            $stmt->bind_param('s', $caja_alternativa);
            $stmt->execute();
            $result = $stmt->get_result();
            $caja_alt_estado = $result->fetch_assoc();
            $stmt->close();
            
            if ($caja_alt_estado['esta_abierta'] == 0) {
                throw new Exception("La caja seleccionada no está abierta");
            }
            
            $numCaja_egreso = $caja_alternativa;
        }
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
    
    // 5. Registrar EGRESO en la caja seleccionada
    $razon_egreso = "Cancelación de pago #$registro_pago - Cliente: $idCliente";
    if ($numCaja_egreso !== $numCaja_original) {
        $razon_egreso .= " (Caja original: $numCaja_original)";
    }
    
    $stmt = $conn->prepare("
        INSERT INTO cajaegresos (metodo, monto, IdEmpleado, numCaja, razon, fecha)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparando inserción de egreso: " . $conn->error);
    }
    
    $stmt->bind_param('sdisss', $metodo_pago, $monto_pago, $empleado_cancela, $numCaja_egreso, $razon_egreso, $fecha_cancelacion);
    
    if (!$stmt->execute()) {
        throw new Exception("Error registrando egreso en caja: " . $stmt->error);
    }
    
    $stmt->close();
    
    // 6. Obtener todas las facturas del cliente ordenadas por fecha (más reciente primero para revertir)
    $stmt = $conn->prepare("
        SELECT 
            f.registro, 
            f.balance, 
            f.total_ajuste AS total,
            COALESCE(fm.monto, 0) AS pago_inicial,
            f.estado, 
            f.fecha,
            f.numFactura
        FROM facturas f
        LEFT JOIN facturas_metodopago fm ON f.numFactura = fm.numFactura
        WHERE f.idCliente = ? AND f.tipoFactura = 'credito' 
        ORDER BY f.fecha DESC
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

    // 7. Revertir el pago: AUMENTAR el balance (deuda) de las facturas
    $monto_restante = $monto_pago;

    foreach ($facturas as $factura) {
        if ($monto_restante <= 0) {
            break;
        }
        
        $registro_factura = $factura['registro'];
        $balance_actual = $factura['balance'];
        $total_factura = $factura['total'];
        $pago_inicial = $factura['pago_inicial'];
        
        $balance_maximo = $total_factura - $pago_inicial;
        $espacio_disponible = $balance_maximo - $balance_actual;
        $monto_a_revertir = min($monto_restante, $espacio_disponible);
        
        if ($monto_a_revertir > 0) {
            $nuevo_balance = $balance_actual + $monto_a_revertir;
            
            if ($nuevo_balance > 0) {
                $estado_factura = "Pendiente";
            } else {
                $estado_factura = "Pagada";
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
            
            $monto_restante -= $monto_a_revertir;
        }
    }
    
    if ($monto_restante > 0) {
        throw new Exception("No se pudo revertir completamente el pago. Monto restante: $" . number_format($monto_restante, 2));
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
            'numCaja' => $numCaja_egreso,
            'caja_original' => $numCaja_original,
            'caja_utilizada' => $numCaja_egreso !== $numCaja_original ? 'alternativa' : 'original',
            'fecha_cancelacion' => $fecha_cancelacion
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

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
    
    $stmt->close();
}
?>