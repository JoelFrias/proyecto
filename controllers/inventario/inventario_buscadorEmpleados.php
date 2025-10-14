<?php

require '../../models/conexion.php';

$campo = isset($_POST['campo']) ? '%' . $conn->real_escape_string($_POST['campo']) . '%' : '%';

$sql = "SELECT 
            id, CONCAT(nombre, ' ', apellido) AS nombreCompleto 
        FROM 
            empleados 
        WHERE 
            (id LIKE ? OR CONCAT(nombre, ' ', apellido) LIKE ? OR nombre LIKE ? OR apellido LIKE ?)
            AND activo = TRUE 
        ORDER BY id ASC
        LIMIT 5;";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $campo, $campo, $campo, $campo);
$stmt->execute();
$result = $stmt->get_result();

$html = '';

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $html .= "<tr>";
        $html .= "<td>" . $row["id"] . "</td>";
        $html .= "<td>" . $row["nombreCompleto"] . "</td>";
        $html .= "<td><button onclick='selectCliente(" . $row["id"] . ")' class='btn-seleccionar' >Seleccionar</button></td>";
        $html .= "</tr>";
    }
} else {
    $html .= "<tr><td colspan='3'>No se encontraron resultados.</td></tr>";
}

echo json_encode($html, JSON_UNESCAPED_UNICODE);

$stmt->close();

?>
