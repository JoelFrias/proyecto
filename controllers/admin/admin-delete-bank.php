<?php

/* Verificacion de sesion */

// Iniciar sesión
session_start();

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

require_once '../../models/conexion.php';

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
    $requiredFields = ['idBank'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missingFields[] = $field;
        }
    }
    if (!empty($missingFields)) {
        throw new Exception("Campos obligatorios faltantes: " . implode(', ', $missingFields), 1001);
    }

    // Sanitización y asignación de variables
    $idBank = (int) $data['idBank'];

    // Verificar que el id del banco no esté vacío
    if (empty($idBank)) {
        throw new Exception("El id del banco no puede estar vacío", 1001);
    }

    // Verificar que el id del banco sea un número entero
    if (!is_int($idBank) || $idBank <= 0) {
        throw new Exception("El id del banco debe ser un número entero positivo", 1003);
    }

    // verificar que el id del banco no sea igual a 1
    if ($idBank == 1) {
        throw new Exception("No se puede eliminar el banco con id 1", 1002);
    }

    // Verificar que el banco es válidos
    $stmt = $conn->prepare("SELECT id FROM bancos WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de banco: " . $conn->error, 2001);
    }
    $stmt->bind_param('i', $idBank);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Banco no encontrado: " . $banco, 2002);
    }

    /**
     *  0. Se inicia la transacción
     */

    $conn->begin_transaction();


    /**
     * * 1. Se deshabilita el bancos
     */

    $stmt = $conn->prepare("UPDATE `bancos` SET `enable`= FALSE WHERE id = ?");

    if (!$stmt) {
        throw new Exception("Error preparando actualización del banco: " . $conn->error, 3006);
    }

    $stmt->bind_param('i', $idBank);

    if (!$stmt->execute()) {
        throw new Exception("Error preparando actualización del banco: " . $stmt->error, 3007);
    }

    $stmt->close();

    /**
     *  2. Auditoria de acciones de usuario
     */

    require_once '../../models/auditorias.php';
    $usuario_id = $_SESSION['idEmpleado'];
    $accion = 'Eliminar banco';
    $detalle = 'Se eliminó el banco con id: ' . $idBank;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

    /**
     *  3. Se ejecuta la transacción
     */
    $conn->commit();

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'El banco fue eliminado correctamente'
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
        'error_code' => $errorCode
    ]));
}

?>