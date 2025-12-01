<?php
// api/perfiles-permisos/obtener-permisos.php

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

// Validar que se recibió el ID del perfil
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID de perfil no proporcionado"
    ]);
    exit();
}

$idPerfil = intval($_GET['id']);

try {
    // Verificar que el perfil existe y está activo
    $sql_verificar = "SELECT id, nombre FROM perfiles_permisos WHERE id = ? AND activo = 1";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bind_param("i", $idPerfil);
    $stmt_verificar->execute();
    $result_verificar = $stmt_verificar->get_result();

    if ($result_verificar->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Perfil no encontrado o inactivo"
        ]);
        exit();
    }

    $stmt_verificar->close();

    // Obtener los permisos del perfil
    $sql_permisos = "SELECT id_permiso FROM perfiles_permisos_detalle WHERE id_perfil = ?";
    $stmt_permisos = $conn->prepare($sql_permisos);
    $stmt_permisos->bind_param("i", $idPerfil);
    $stmt_permisos->execute();
    $result_permisos = $stmt_permisos->get_result();

    $permisos = [];
    while ($row = $result_permisos->fetch_assoc()) {
        $permisos[] = $row['id_permiso'];
    }

    $stmt_permisos->close();

    echo json_encode([
        "success" => true,
        "permisos" => $permisos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener permisos: " . $e->getMessage()
    ]);
}

$conn->close();
?>