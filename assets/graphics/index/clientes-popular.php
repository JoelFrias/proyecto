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
            CONCAT(c.nombre,' ',c.apellido) AS nombre_cliente,
            COUNT(f.numFactura) AS ventas,
            SUM(f.total_ajuste) AS total_comprado
        FROM
            facturas f
        JOIN clientes c ON
            f.idCliente = c.id
        WHERE
            $whereClause AND f.idEmpleado = ?
        GROUP BY
            c.nombre, c.apellido
        ORDER BY
            total_comprado DESC
        LIMIT 5;";

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