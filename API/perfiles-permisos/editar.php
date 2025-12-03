<?php
// api/perfiles-permisos/editar.php

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
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Método no permitido"
    ]);
    exit();
}

// Obtener datos del cuerpo de la solicitud
$input = json_decode(file_get_contents('php://input'), true);

// Validaciones
$errors = [];

if (!isset($input['id']) || empty($input['id'])) {
    $errors[] = "ID del perfil es obligatorio";
}

if (empty($input['nombre'])) {
    $errors[] = "El nombre del perfil es obligatorio";
}

if (empty($input['permisos']) || !is_array($input['permisos'])) {
    $errors[] = "Debe seleccionar al menos un permiso";
}

if (!isset($input['activo'])) {
    $errors[] = "El estado activo es obligatorio";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Datos de validación incompletos",
        "errors" => $errors
    ]);
    exit();
}

$id = intval($input['id']);
$nombre = trim($input['nombre']);
$descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : '';
$activo = $input['activo'] ? 1 : 0;
$permisos = $input['permisos'];

if (count($permisos) === 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Debe seleccionar al menos un permiso"
    ]);
    exit();
}

if (strlen($nombre) > 100) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "El nombre del perfil no debe exceder los 100 caracteres"
    ]);
    exit();
}

if (strlen($descripcion) > 255) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "La descripción no debe exceder los 255 caracteres"
    ]);
    exit();
}

// Iniciar transacción
$conn->begin_transaction();

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
        $stmt_verificar->close();
        $conn->rollback();
        exit();
    }
    $stmt_verificar->close();

    // Verificar si ya existe otro perfil con ese nombre
    $sql_nombre = "SELECT id FROM perfiles_permisos WHERE nombre = ? AND id != ?";
    $stmt_nombre = $conn->prepare($sql_nombre);
    $stmt_nombre->bind_param("si", $nombre, $id);
    $stmt_nombre->execute();
    $result_nombre = $stmt_nombre->get_result();

    if ($result_nombre->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Ya existe otro perfil con ese nombre"
        ]);
        $stmt_nombre->close();
        $conn->rollback();
        exit();
    }
    $stmt_nombre->close();

    // Actualizar el perfil
    $sql_update = "UPDATE perfiles_permisos SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssii", $nombre, $descripcion, $activo, $id);
    
    if (!$stmt_update->execute()) {
        throw new Exception("Error al actualizar el perfil");
    }
    $stmt_update->close();

    // Eliminar permisos antiguos
    $sql_delete_permisos = "DELETE FROM perfiles_permisos_detalle WHERE id_perfil = ?";
    $stmt_delete = $conn->prepare($sql_delete_permisos);
    $stmt_delete->bind_param("i", $id);
    
    if (!$stmt_delete->execute()) {
        throw new Exception("Error al eliminar permisos antiguos");
    }
    $stmt_delete->close();

    // Insertar los nuevos permisos
    $sql_insert_permiso = "INSERT INTO perfiles_permisos_detalle (id_perfil, id_permiso) VALUES (?, ?)";
    $stmt_insert = $conn->prepare($sql_insert_permiso);

    foreach ($permisos as $permiso) {
        $stmt_insert->bind_param("is", $id, $permiso);
        if (!$stmt_insert->execute()) {
            throw new Exception("Error al asignar permisos");
        }
    }
    $stmt_insert->close();

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Perfil actualizado exitosamente"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al actualizar el perfil: " . $e->getMessage()
    ]);
}

$conn->close();
?>