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

// Incluir conexion a la base de datos
require_once '../../core/conexion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'PADM003';
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
    $requiredFields = ['idDestino','nombre'];
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
    $idDestino = (int) $data['idDestino'];
    $destino = htmlspecialchars($data['nombre'], ENT_QUOTES, 'UTF-8');

    // Verificar que el nombre del destino no esté vacío
    if (empty($destino)) {
        throw new Exception("El nombre del destino no puede estar vacío", 1001);
    }

    // Verificar que el id del destino no esté vacío
    if (empty($idDestino)) {
        throw new Exception("El id del destino no puede estar vacío", 1001);
    }

    // Verificar que el id del destino sea un número entero
    if (!is_int($idDestino) || $idDestino <= 0) {
        throw new Exception("El id del destino debe ser un número entero positivo", 1003);
    }

    // verificar que el id del destino no sea igual a 1
    if ($idDestino == 1) {
        throw new Exception("No se puede eliminar el destino con id 1", 1002);
    }

    // Verificar que el destino es válidos
    $stmt = $conn->prepare("SELECT id FROM destinocuentas WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de destino: " . $conn->error, 2001);
    }
    $stmt->bind_param('i', $idDestino);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("destino no encontrado: " . $destino, 2002);
    }

    /**
     *  0. Se inicia la transacción
     */

    $conn->begin_transaction();


    /**
     * * 1. Se deshabilita el destinos
     */

    $stmt = $conn->prepare("UPDATE `destinocuentas` SET `descripcion`= ? WHERE id = ?");

    if (!$stmt) {
        throw new Exception("Error preparando actualización del destino: " . $conn->error, 3006);
    }

    $stmt->bind_param('si', $destino, $idDestino);

    if (!$stmt->execute()) {
        throw new Exception("Error preparando actualización del destino: " . $stmt->error, 3007);
    }

    /**
     *  2. Auditoria de acciones de usuario
     */

    require_once '../../core/auditorias.php';
    $usuario_id = $_SESSION['idEmpleado'];
    $accion = 'Actualizar destino';
    $detalle = 'Se actualizó el destino con id ' . $idDestino . ' a ' . $destino;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);


    /**
     *  2. Se ejecuta la transacción
     */
    $conn->commit();

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'El destino fue eliminado correctamente',
        'datos' => [
            'idDestino' => $idDestino,
            'nombre' => $destino
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
        'error_code' => $errorCode
    ]));
}

?>