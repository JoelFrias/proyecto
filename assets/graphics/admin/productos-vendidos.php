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

// Cantidad de productos a mostrar (por defecto 10)
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;

$sql = "SELECT
            p.descripcion AS producto,
            SUM(d.cantidad) AS total_vendido
        FROM
            facturas_detalles AS d
        JOIN productos AS p
        ON
            d.idProducto = p.id
        JOIN facturas AS f
        ON
            d.numFactura = f.numFactura
        WHERE
            $whereClause
        GROUP BY
            p.id,
            p.descripcion
        ORDER BY
            total_vendido DESC
        LIMIT $limite"; // Obtener los productos más vendidos

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