<?php

/* Verificacion de sesion */

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configurar el tiempo de caducidad de la sesión
$inactivity_limit = 900; // 15 minutos en segundos

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    die(json_encode([
        "success" => false, 
        "error" => "No se ha encontrado una sesión activa",
        "error_code" => "SESSION_NOT_FOUND",
        "solution" => "Por favor inicie sesión nuevamente"
    ]));
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    die(json_encode([
        "success" => false, 
        "error" => "La sesión ha expirado por inactividad",
        "error_code" => "SESSION_EXPIRED",
        "solution" => "Por favor inicie sesión nuevamente"
    ]));
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

require_once '../../core/conexion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'CLI003';
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

// Funcion para guardar los logs de depuracion
function logDebug($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $logMessage .= " - Data: " . print_r($data, true);
    }
    error_log($logMessage);
}

// Validar metodo de entrada
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        "success" => false, 
        "error" => "Método no permitido",
        "error_code" => "INVALID_METHOD",
        "allowed_methods" => ["POST"]
    ]));
}

// Obtener datos JSON
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

logDebug("Datos recibidos", $data);

// Verificar si el JSON es válido
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'JSON inválido',
        'error_code' => 'INVALID_JSON',
        'details' => json_last_error_msg(),
        'solution' => 'Verifique el formato del JSON enviado'
    ]));
}

header('Content-Type: application/json');

try {
    // Validaciones esenciales
    $requiredFields = ['idCliente', 'formaPago', 'montoPagado'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception("Campos obligatorios faltantes: " . implode(', ', $missingFields), 1001);
    }

    // Validar tipos de datos
    if (!is_numeric($data['idCliente'])) {
        throw new Exception("El ID de cliente debe ser numérico", 1002);
    }
    
    // Validar monto pagado
    if (!is_numeric($data['montoPagado']) || $data['montoPagado'] <= 0) {
        throw new Exception("El monto pagado debe ser un número positivo", 1003);
    }

    // Validar forma de pago
    if (!in_array($data['formaPago'], ['efectivo', 'tarjeta', 'transferencia'])) {
        throw new Exception("Forma de pago no válida", 1004);
    }

    // Sanitización y asignación de variables
    $idEmpleado = (int) $_SESSION['idEmpleado'];
    $idCliente = (int) $data['idCliente'];
    $formaPago = $conn->real_escape_string($data['formaPago']);
    $montoPagado = (float) $data['montoPagado'];
    $montoPagado1 = (float) $data['montoPagado'];  //  Variable para guardar el monto pagado en payment history
    $numeroAutorizacion = $data['numeroAutorizacion'] ?? 'N/A';
    $numeroTarjeta = $data['numeroTarjeta'] ?? 'N/A';
    $banco = isset($data['banco']) ? (int)$data['banco'] : 1;
    $destino = isset($data['destino']) ? (int)$data['destino'] : 1;

    // Validar que el empleado tenga una caja asignada
    if (!isset($_SESSION['numCaja'])) {
        throw new Exception("No se ha encontrado ninguna caja asignada al vendedor", 1001);
    }

    // Validar que el cliente existe en la base de datos
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de cliente: " . $conn->error);
    }
    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Cliente no encontrado: " . $idCliente);
    }

    // Verificar que el monto pagado es un número positivo
    if ($montoPagado <= 0) {
        throw new Exception("El monto pagado debe ser un número positivo: " . $montoPagado);
    }

    // Verificar que la forma de pago es válida
    $validPayments = ['efectivo', 'tarjeta', 'transferencia'];
    if (!in_array($formaPago, $validPayments)) {
        throw new Exception("Forma de pago inválida: " . $formaPago);
    }

    logDebug("Variables procesadas", [
        'idCliente' => $idCliente,
        'formaPago' => $formaPago,
        'montoPagado' => $montoPagado,
        'numeroAutorizacion' => $numeroAutorizacion,
        'numeroTarjeta' => $numeroTarjeta,
        'banco' => $banco,
        'destino' => $destino
    ]);

    // Verificar que el número de tarjeta no esté vacío
    if ($formaPago === 'tarjeta' && empty($numeroTarjeta)) {
        throw new Exception("El número de tarjeta no puede estar vacío para pagos con tarjeta", 1005);
    }

    // Verificar que el número de autorización no esté vacío
    if ($formaPago === 'tarjeta' && empty($numeroAutorizacion)) {
        throw new Exception("El número de autorización no puede estar vacío para pagos con tarjeta", 1006);
    }

    // Verificar que el número de autorización tenga un formato válido
    if ($formaPago === 'tarjeta' && !preg_match('/^[0-9]{4}$/', $numeroAutorizacion)) {
        throw new Exception("El número de autorización debe ser un número de 4 dígitos", 1007);
    }

    // Verificar que el número de tarjeta tenga un formato válido
    if ($formaPago === 'tarjeta' && !preg_match('/^[0-9]{4}$/', $numeroTarjeta)) {
        throw new Exception("El número de tarjeta debe ser un número de 4 dígitos", 1008);
    }

    // Verificar longitud de la tarjeta
    if ($formaPago === 'tarjeta' && strlen($numeroTarjeta) > 4) {
        throw new Exception("Solo se aceptan los últimos 4 dígitos de la tarjeta", 1005);
    }

    // Verificar longitud del número de autorización en tarjeta
    if ($formaPago === 'tarjeta' && strlen($numeroAutorizacion) > 4) {
        throw new Exception("Solo se aceptan los últimos 4 dígitos del número de autorización", 1009);
    }

    // Verificar longitud del número de autorización en transferencia
    if ($formaPago === 'transferencia' && strlen($numeroAutorizacion) > 4) {
        throw new Exception("Solo se aceptan los últimos 4 dígitos del número de autorización", 1010);
    }

    // Verificar que el banco es válidos
    $stmt = $conn->prepare("SELECT id FROM bancos WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de banco: " . $conn->error, 2001);
    }
    $stmt->bind_param('i', $banco);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Banco no encontrado: " . $banco, 2002);
    }
    
    // Verificar que el destino es válidos
    $stmt = $conn->prepare("SELECT id FROM destinocuentas WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de destino: " . $conn->error, 2003);
    }
    $stmt->bind_param('i', $destino);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Destino no encontrado: " . $destino, 2004);
    }


    /**
     *  0. Se inicia la transacción
     */
    $conn->begin_transaction();

    /**
     *  1. Restar el monto pagado del balance de la cuenta del cliente
     */

    $sqlFactura = "SELECT numFactura, balance FROM facturas WHERE idCliente = ? AND balance > 0 ORDER BY fecha ASC LIMIT 1";
    $sqlActualizarFactura = "UPDATE facturas SET balance = ?, estado = CASE WHEN balance <= 0 THEN 'Pagada' ELSE estado END WHERE numFactura = ?";
    
    while ($montoPagado > 0) {
        // Buscar la factura pendiente más vieja
        $pstFactura = $conn->prepare($sqlFactura);
        if (!$pstFactura) {
            throw new Exception("Error al preparar consulta de facturas: " . $conn->error, 2001);
        }
        
        $pstFactura->bind_param("s", $idCliente);
        if (!$pstFactura->execute()) {
            throw new Exception("Error al ejecutar consulta de facturas: " . $pstFactura->error, 2002);
        }
        
        $rsFactura = $pstFactura->get_result();
    
        if ($rsFactura->num_rows > 0) {
            $factura = $rsFactura->fetch_assoc();
            $idFactura = $factura['numFactura'];
            $balanceFactura = floatval($factura['balance']);
    
            // Restar el monto del balance de la factura
            $nuevoBalance = $balanceFactura - $montoPagado;
    
            if ($nuevoBalance >= 0) {
                // Actualizar el balance de la factura
                $pstActualizar = $conn->prepare($sqlActualizarFactura);
                if (!$pstActualizar) {
                    throw new Exception("Error al preparar actualización de factura: " . $conn->error, 2003);
                }
                
                $pstActualizar->bind_param("ds", $nuevoBalance, $idFactura);
                if (!$pstActualizar->execute()) {
                    throw new Exception("Error al actualizar factura: " . $pstActualizar->error, 2004);
                }
                
                $montoPagado = 0; // Todo el pago ha sido aplicado
            } else {
                // El pago excede el balance actual de la factura
                $montoPagado = abs($nuevoBalance);
    
                // Actualizar el estado de la factura a Pagada y balance a 0
                $pstActualizar = $conn->prepare($sqlActualizarFactura);
                if (!$pstActualizar) {
                    throw new Exception("Error al preparar actualización de factura: " . $conn->error, 2005);
                }
    
                $nuevoBalance = 0.0;
                $pstActualizar->bind_param("ds", $nuevoBalance, $idFactura);
                
                if (!$pstActualizar->execute()) {
                    throw new Exception("Error al actualizar factura: " . $pstActualizar->error, 2006);
                }
            }
        } else {
            // No hay más facturas pendientes
            if ($montoPagado > 0) {
                throw new Exception("El cliente no tiene facturas pendientes o el monto pagado excede el total adeudado", 2007);
            }
            break;
        }
    }

    /**
     *  2. Actualizar el balance de la cuenta del cliente
     */

    $stmt = $conn->prepare("SELECT limite_credito FROM clientes_cuenta WHERE idCliente = ?");

    if (!$stmt) {
        throw new Exception("Error preparando consulta de límite de crédito: " . $conn->error, 3001);
    }

    $stmt->bind_param('i', $idCliente);
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar consulta de límite de crédito: " . $stmt->error, 3002);
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new Exception("Cliente no encontrado en la tabla de cuentas", 3003);
    }

    $limiteCredito = $row['limite_credito'];

    // Obtener la suma de todos los balances pendientes de facturas
    $stmt = $conn->prepare("SELECT IFNULL(SUM(balance), 0) as balance_pendiente FROM facturas WHERE idCliente = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de balance pendiente: " . $conn->error, 3004);
    }

    $stmt->bind_param('i', $idCliente);
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar consulta de balance pendiente: " . $stmt->error, 3005);
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $balancePendiente = $row['balance_pendiente'];

    // Calcular el nuevo balance disponible
    $balanceDisponible = $limiteCredito - $balancePendiente;

    // Actualizar el balance disponible en clientes_cuenta
    $stmt = $conn->prepare("UPDATE clientes_cuenta SET balance = ? WHERE idCliente = ?");

    if (!$stmt) {
        throw new Exception("Error preparando actualización de balance: " . $conn->error, 3006);
    }

    $stmt->bind_param('di', $balanceDisponible, $idCliente);

    if (!$stmt->execute()) {
        throw new Exception("Error actualizando balance del cliente: " . $stmt->error, 3007);
    }

    /**
     *  3. Registrar el pago en la tabla de pagos
     */

    $sqlPago = "INSERT INTO `clientes_historialpagos`(`idCliente`, `fecha`, `numCaja`, `idEmpleado`, `metodo`, `monto`, `numAutorizacion`, `referencia`, `idBanco`, `idDestino`) VALUES (?,NOW(),?,?,?,?,?,?,?,?)";
    $pstPago = $conn->prepare($sqlPago);
    if (!$pstPago) {
        throw new Exception("Error al preparar inserción de pago: " . $conn->error, 4001);
    }
    
    $pstPago->bind_param("isisdssss", $idCliente, $_SESSION['numCaja'], $idEmpleado, $formaPago, $montoPagado1, $numeroAutorizacion, $numeroTarjeta, $banco, $destino);
    if (!$pstPago->execute()) {
        throw new Exception("Error al registrar el pago: " . $pstPago->error, 4002);
    }
    
    if ($pstPago->affected_rows === 0) {
        throw new Exception("No se pudo registrar el pago en el historial", 4003);
    }
    
    // Obtener el ID autoincremental del campo 'registro'
    $idRegistro = $conn->insert_id;
    

    /**
     *      4. Registrar ingreso en caja
     */

    $stmt = $conn->prepare("INSERT INTO cajaingresos (metodo, monto, IdEmpleado, numCaja, razon, fecha) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        throw new Exception("Error preparando inserción de ingresos: " . $conn->error);
    }
    $razon = "Pago a cuenta del cliente: " . $idCliente;
    $stmt->bind_param("sdiss", $formaPago, $montoPagado1, $idEmpleado, $_SESSION['numCaja'], $razon);
    if (!$stmt->execute()) {
        throw new Exception("Error insertando el ingreso: " . $stmt->error);
    }
    logDebug("Ingresos en caja registrado");


    /**
     * *  5. Registrar auditoría de caja
     */

    $usuario_id = $_SESSION['idEmpleado'];
    $accion = "Registro de pago a cuenta del cliente: " . $idCliente;
    $detalles = "Método: " . $formaPago . ", Monto: " . $montoPagado1 . ", Autorización: " . $numeroAutorizacion . ", Tarjeta: " . $numeroTarjeta . ", Banco: " . $banco . ", Destino: " . $destino;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';

    require_once '../../core/auditorias.php';
    registrarAuditoriaCaja($conn, $usuario_id, $accion, $detalles);


    /**
     *  6. Auditoria de acciones de usuario
     */

    $usuario_id = $_SESSION['idEmpleado'];
    $accion = 'Pago a cuenta del cliente: ' . $idCliente;
    $detalle = 'Método: ' . $formaPago . ', Monto: ' . $montoPagado1 . ', Autorización: ' . $numeroAutorizacion . ', Tarjeta: ' . $numeroTarjeta . ', Banco: ' . $banco . ', Destino: ' . $destino;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

    /**
     *  7. Confirmar la transacción
     */

    $conn->commit();

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Pago registrado correctamente',
        'data' => [
            'idCliente' => $idCliente,
            'balance_disponible' => $balanceDisponible,
            'monto_aplicado' => $data['montoPagado'],
            'idRegistro' => $idRegistro
        ]
    ]);

} catch (Exception $e) {
    // Revertir la transacción en caso de error
    if ($conn) {
        $conn->rollback();
    }
    
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();
    $httpCode = 400;
    
    // Determinar código HTTP basado en el código de error
    if ($errorCode >= 2000 && $errorCode < 3000) {
        $httpCode = 422; // Unprocessable Entity
    } elseif ($errorCode >= 3000 && $errorCode < 4000) {
        $httpCode = 404; // Not Found (para cliente no encontrado)
    }
    
    http_response_code($httpCode);
    
    die(json_encode([
        'success' => false,
        'error' => $errorMessage,
        'error_code' => $errorCode,
        'solution' => 'Verifique los datos e intente nuevamente'
    ]));
}

?>