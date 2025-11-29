<?php

require_once '../../core/conexion.php';
require_once '../../core/verificar-sesion.php';

// Configurar cabecera JSON antes de cualquier salida
header('Content-Type: application/json; charset=utf-8');

// Verificar si hay output buffer y limpiarlo si existe
if (ob_get_level()) {
    ob_clean();
}

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
    $requiredFields = ['user', 'password'];
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
    $user = $conn->real_escape_string($data['user']);
    $password = $conn->real_escape_string($data['password']);

    // Validar seguridad mínima de la contraseña
    if (strlen($password) < 4) {
        throw new Exception("La contraseña debe tener al menos 4 caracteres", 400);
    }

    // Verificar que el nuevo nombre de usuario no esté ya en uso (si cambió)
    if ($user !== $_SESSION['username']) {
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE username = ? AND id != ?");
        $checkStmt->bind_param("si", $user, $_SESSION['id']);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();

        if ($count > 0) {
            throw new Exception("El nombre de usuario ya está en uso por otro usuario", 409);
        }
    }

    // Crear una transacción
    $conn->begin_transaction();
    
    // Encriptar la nueva contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Actualizar el usuario en la base de datos
    $sql_update = "UPDATE usuarios SET username = ?, password = ? WHERE id = ?";
    $stmt = $conn->prepare($sql_update);

    if (!$stmt) {
        throw new Exception("Error en la preparación de la consulta: " . $conn->error);
    }

    $stmt->bind_param("ssi", $user, $hashed_password, $_SESSION['id']);

    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar el usuario: " . $stmt->error);
    }

    // Actualizar la sesión con el nuevo nombre de usuario
    $_SESSION['username'] = $user;
    
    $conn->commit();
    
    // Asegurar que no haya salida antes de este punto
    echo json_encode([
        'success' => true, 
        'message' => 'El perfil se ha actualizado correctamente'
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
        // $conn->close();
    }
}
?>