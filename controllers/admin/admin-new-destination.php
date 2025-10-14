<?php
// Iniciar sesión al principio del archivo
session_start();

// Configurar cabecera JSON antes de cualquier salida
header('Content-Type: application/json; charset=utf-8');

// Verificar si hay output buffer y limpiarlo si existe
if (ob_get_level()) {
    ob_clean();
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

require_once '../../models/conexion.php';

// Validar metodo de entrada
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die(json_encode([
        "success" => false, 
        "error" => "Método no permitido"
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
        'details' => json_last_error_msg()
    ]));
}

try {
    // Validaciones esenciales
    $requiredFields = ['nameDestination'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception("Campos obligatorios faltantes: " . implode(', ', $missingFields));
    }

    // Sanitización y asignación de variables
    $nameDestination = $conn->real_escape_string($data['nameDestination']);

    // Crear una transacción
    $conn->begin_transaction();

    // Agregar el nuevo destino a la base de datos
    $stmt = $conn->prepare("INSERT INTO destinocuentas (descripcion, enable) VALUES (?, TRUE)");

    if (!$stmt) {
        throw new Exception("Error en la preparación de la consulta: " . $conn->error);
    }

    $stmt->bind_param("s", $nameDestination);

    if (!$stmt->execute()) {
        throw new Exception("Error al agregar el destino: " . $stmt->error);
    }

    $result = $conn->query("SELECT LAST_INSERT_ID() as last_id");

    if (!$result) {
        throw new Exception("Error al obtener el ID del último banco insertado: " . $conn->error);
    }
    
    $row = $result->fetch_assoc();
    $newId = $row['last_id'];

    // Auditoria de acciones de usuario

    require_once '../../models/auditorias.php';
    $usuario_id = $_SESSION['idEmpleado'];
    $accion = 'Agregar destino';
    $detalle = 'Se ha agregado un nuevo destino: ' . $nameDestination;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

    
    $conn->commit();
    
    // Asegurar que no haya salida antes de este punto
    echo json_encode([
        'success' => true, 
        'message' => 'El destino ha sido agregado exitosamente',
        'data' => [
            'id' => $newId,
            'nameBank' => $nameDestination
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'errorCode' => $e->getCode()
    ]);
    
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>