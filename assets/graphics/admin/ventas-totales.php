<?php

require_once '../../../models/conexion.php';

header('Content-Type: application/json');

// Obtener el parámetro de fecha del request
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'current';

// Definir la cláusula WHERE según el periodo solicitado
if ($periodo === 'previous') {
    // Mes anterior
    $whereClause = "MONTH(f.fecha) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(f.fecha) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
} else {
    // Mes actual (por defecto)
    $whereClause = "MONTH(f.fecha) = MONTH(CURDATE()) AND YEAR(f.fecha) = YEAR(CURDATE())";
}

$whereClause = $whereClause . " AND f.estado != 'Cancelada'";

$sql = "SELECT
            DAY(f.fecha) AS dia,
            SUM(f.total_ajuste) AS ventas
        FROM
            facturas AS f
        WHERE
            $whereClause
        GROUP BY
            dia
        ORDER BY
            dia";

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