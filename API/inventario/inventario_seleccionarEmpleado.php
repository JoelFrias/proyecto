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
}

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID no proporcionado o no válido']);
    exit;
}

$id = intval($_GET['id']);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la conexión a la base de datos']);
    exit;
}

$sql = "SELECT id, CONCAT(nombre, ' ', apellido) AS nombre FROM empleados WHERE id = ? AND activo = TRUE";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la preparación de la consulta']);
    exit;
}

$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'No se encontró el empleado']);
}

$stmt->close();
$conn->close();

?>
