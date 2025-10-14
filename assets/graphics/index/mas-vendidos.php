<?php
// Iniciar sesión para acceder a las variables de sesión
session_start();

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

$sql = "SELECT
            p.descripcion,
            SUM(df.cantidad) AS cantidad_vendida
        FROM
            facturas_detalles df
        JOIN productos p ON
            df.idProducto = p.id
        JOIN facturas f ON
            df.numFactura = f.numFactura
        WHERE
            $whereClause AND f.idEmpleado = ?
        GROUP BY
            p.descripcion
        ORDER BY
            cantidad_vendida DESC
        LIMIT
            10";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['idEmpleado']);
$stmt->execute();
$result = $stmt->get_result();

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