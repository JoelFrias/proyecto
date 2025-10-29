<?php

require '../../models/conexion.php';

$campo = isset($_POST['campo']) ? '%' . $conn->real_escape_string($_POST['campo']) . '%' : '%';

$sql = "SELECT
            ci.no AS no,
            CONCAT(c.nombre, ' ', c.apellido) AS nombreCliente,
            DATE_FORMAT(ci.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
            ci.total AS total,
            CONCAT(e.nombre, ' ', e.apellido) AS nombreEmpleado,
            ci.notas AS notas
        FROM
            cotizaciones_inf AS ci
        INNER JOIN clientes AS c
        ON
            c.id = ci.id_cliente
        INNER JOIN empleados AS e
        ON
            e.id = ci.id_empleado
        WHERE
            ci.no LIKE ?
            OR CONCAT(c.nombre, ' ', c.apellido) LIKE ?
            OR ci.fecha LIKE ?
            OR c.id LIKE ?
            OR e.id LIKE ?
            OR CONCAT(e.nombre, ' ', e.apellido) LIKE ?
        ORDER BY
            fecha
        DESC
        LIMIT 10";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssssss', $campo, $campo, $campo, $campo, $campo, $campo);
$stmt->execute();
$result = $stmt->get_result();

$html = '';

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $html .= "<tr>";
        $html .= "<td><button onclick='seleccionarprefactura(\"" . $row["no"] . "\")' class='btn-seleccionar'>Seleccionar</button></td>";
        $html .= "<td>" . $row["no"] . "</td>";
        $html .= "<td>" . $row["nombreCliente"] . "</td>";
        $html .= "<td>" . $row["fecha"] . "</td>";
        $html .= "<td>" . $row["total"] . "</td>";
        $html .= "<td>" . $row["nombreEmpleado"] . "</td>";
        $html .= "<td>" . $row["notas"] . "</td>";
        $html .= "</tr>";
    }
} else {
    $html .= "<tr><td colspan='7'>No se encontraron resultados.</td></tr>";
}

echo json_encode($html, JSON_UNESCAPED_UNICODE);

$stmt->close();

?>
