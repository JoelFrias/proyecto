<?php
// api/perfiles-permisos/eliminar.php

header('Content-Type: application/json');

require_once '../../core/conexion.php';
require_once '../../core/verificar-sesion.php';

// Verificar conexión a la base de datos
if (!$conn || $conn->connect_errno !== 0) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de conexión a la base de datos"
    ]);
    exit();
}

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Método no permitido"
    ]);
    exit();
}

// Obtener datos del cuerpo de la solicitud
$input = json_decode(file_get_contents('php://input'), true);

// Validar datos recibidos
if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID de perfil no proporcionado"
    ]);
    exit();
}

$id = intval($input['id']);

try {
    // Verificar que el perfil existe
    $sql_verificar = "SELECT id FROM perfiles_permisos WHERE id = ?";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bind_param("i", $id);
    $stmt_verificar->execute();
    $result_verificar = $stmt_verificar->get_result();

    if ($result_verificar->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Perfil no encontrado"
        ]);
        exit();
    }
    $stmt_verificar->close();

    // Eliminar el perfil (los detalles se eliminan automáticamente por ON DELETE CASCADE)
    $sql_delete = "DELETE FROM perfiles_permisos WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id);
    
    if ($stmt_delete->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Perfil eliminado correctamente"
        ]);
    } else {
        throw new Exception("Error al eliminar el perfil");
    }

    $stmt_delete->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al eliminar perfil: " . $e->getMessage()
    ]);
}

$conn->close();
?>