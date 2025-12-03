<?php

require_once '../../core/conexion.php';		// Conexión a la base de datos

// Verificar conexión a la base de datos
if (!$conn || !$conn->connect_errno === 0) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos",
        "error_code" => "DATABASE_CONNECTION_ERROR"
    ]));
}  // Conexión a la base de datos
require_once '../../core/verificar-sesion.php';    // Verificar sesión activa

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
    $requiredFields = ['idDestination'];
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
    $idDestination = (int) $data['idDestination'];

    // Verificar que el id del destino no esté vacío
    if (empty($idDestination)) {
        throw new Exception("El id del destino no puede estar vacío", 1001);
    }

    // Verificar que el id del destino sea un número entero
    if (!is_int($idDestination) || $idDestination <= 0) {
        throw new Exception("El id del destino debe ser un número entero positivo", 1003);
    }

    // verificar que el id del destino no sea igual a 1
    if ($idDestination == 1) {
        throw new Exception("No se puede eliminar el destino con id 1", 1002);
    }

    // Verificar que el destino es válidos
    $stmt = $conn->prepare("SELECT id FROM destinocuentas WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de destino: " . $conn->error, 2001);
    }
    $stmt->bind_param('i', $idDestination);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("destino no encontrado: " . $idDestination, 2002);
    }

    /**
     *  0. Se inicia la transacción
     */

    $conn->begin_transaction();


    /**
     * * 1. Se deshabilita el destinos
     */

    $stmt = $conn->prepare("UPDATE `destinocuentas` SET `enable`= FALSE WHERE id = ?");

    if (!$stmt) {
        throw new Exception("Error preparando actualización del destino: " . $conn->error, 3006);
    }

    $stmt->bind_param('i', $idDestination);

    if (!$stmt->execute()) {
        throw new Exception("Error preparando actualización del destino: " . $stmt->error, 3007);
    }

    $stmt->close();

    /**
     *  3. Se ejecuta la transacción
     */


    $conn->commit();

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'El destino fue eliminado correctamente'
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