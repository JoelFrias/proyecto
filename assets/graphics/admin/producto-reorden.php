<?php

require_once '../../../models/conexion.php';

header('Content-Type: application/json');

$sql = "SELECT
            p.descripcion AS producto,
            p.existencia AS stock,
            p.reorden AS stock_minimo
        FROM
            productos AS p
        WHERE
            p.existencia <= p.reorden
        AND
            p.activo = TRUE
        ORDER BY
            p.existencia ASC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["error" => $conn->error]);
    exit;
}

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);

$conn->close();

?>
