<?php
// api/perfiles-permisos/crear.php

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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

if (empty($input['nombre'])) {
    $errors[] = "El nombre del perfil es obligatorio";
}

if (empty($input['permisos']) || !is_array($input['permisos'])) {
    $errors[] = "Debe seleccionar al menos un permiso";
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

$nombre = trim($input['nombre']);
$descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : '';
$permisos = $input['permisos'];
$creado_por = $_SESSION['idEmpleado'];

// Iniciar transacción
$conn->begin_transaction();

try {
    // Verificar si ya existe un perfil con ese nombre
    $sql_verificar = "SELECT id FROM perfiles_permisos WHERE nombre = ?";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bind_param("s", $nombre);
    $stmt_verificar->execute();
    $result_verificar = $stmt_verificar->get_result();

    if ($result_verificar->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Ya existe un perfil con ese nombre"
        ]);
        $stmt_verificar->close();
        $conn->rollback();
        exit();
    }
    $stmt_verificar->close();

    // Insertar el perfil
    $sql_perfil = "INSERT INTO perfiles_permisos (nombre, descripcion, creado_por) VALUES (?, ?, ?)";
    $stmt_perfil = $conn->prepare($sql_perfil);
    $stmt_perfil->bind_param("ssi", $nombre, $descripcion, $creado_por);
    
    if (!$stmt_perfil->execute()) {
        throw new Exception("Error al crear el perfil");
    }

    $id_perfil = $conn->insert_id;
    $stmt_perfil->close();

    // Insertar los permisos
    $sql_permiso = "INSERT INTO perfiles_permisos_detalle (id_perfil, id_permiso) VALUES (?, ?)";
    $stmt_permiso = $conn->prepare($sql_permiso);

    foreach ($permisos as $permiso) {
        $stmt_permiso->bind_param("is", $id_perfil, $permiso);
        if (!$stmt_permiso->execute()) {
            throw new Exception("Error al asignar permisos");
        }
    }

    $stmt_permiso->close();

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Perfil creado exitosamente",
        "id" => $id_perfil
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al crear el perfil: " . $e->getMessage()
    ]);
}

$conn->close();
?>