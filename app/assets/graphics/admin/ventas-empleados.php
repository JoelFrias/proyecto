<?php

require_once '../../../core/conexion.php';

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
            CONCAT(e.nombre,' ',e.apellido) AS empleado,
            SUM(f.total_ajuste) AS ventas
        FROM
            facturas AS f
        JOIN empleados AS e
        ON
            f.idEmpleado = e.id
        WHERE
            $whereClause
        GROUP BY
            e.id,
            e.nombre,
            e.apellido
        ORDER BY
            ventas DESC";

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