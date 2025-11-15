<?php

require_once '../../core/conexion.php';

header('Content-Type: application/json'); // Asegura que la respuesta sea JSON

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $sql = "SELECT id, CONCAT(nombre, ' ', apellido) AS nombre, empresa FROM clientes WHERE id = ? AND activo = TRUE";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(['error' => 'Error en la preparación de la consulta']);
        exit;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['error' => 'No se encontró el cliente']);
    }

    $stmt->close();
} else {
    echo json_encode(['error' => 'ID no proporcionado']);
}

?>
