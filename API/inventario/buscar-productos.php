<?php

// Iniciar sesión para verificación
session_start();

// Verificar que el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Sesión no iniciada']);
    exit();
}

// Verificar que se recibió el parámetro de búsqueda
$searchTerm = isset($_POST['search']) ? $_POST['search'] : '';

// Configuración de la conexión a la base de datos
require_once '../../core/conexion.php';

// Verificar conexión
if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error de conexión a la base de datos']);
    exit();
}

// Preparar la consulta SQL con parámetros preparados para evitar SQL injection
$sql = "SELECT
            p.id,
            p.descripcion,
            i.existencia,
            p.precioCompra,
            p.precioVenta1,
            p.precioVenta2,
            p.reorden
        FROM
            productos AS p
        JOIN inventario AS i ON
            i.idProducto = p.id
        WHERE
            (id LIKE ? OR descripcion LIKE ?) AND
            i.existencia > 0
        ORDER BY
            descripcion
        LIMIT 10";

// Preparar la declaración
$stmt = $conn->prepare($sql);
if (!$stmt) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error al preparar la consulta']);
    exit();
}

// Vincular parámetros y ejecutar
$searchParam = "%$searchTerm%";
$stmt->bind_param("ss", $searchParam, $searchParam);
$stmt->execute();

// Obtener resultados
$result = $stmt->get_result();
$productos = [];

while ($row = $result->fetch_assoc()) {
    $productos[] = [
        'id' => $row['id'],
        'descripcion' => $row['descripcion'],
        'existencia' => $row['existencia'],
        'precio_compra' => $row['precioCompra'],
        'precio_venta1' => $row['precioVenta1'],
        'precio_venta2' => $row['precioVenta2'],
        'punto_reorden' => $row['reorden']
    ];
}

// Cerrar conexiones
$stmt->close();
$conn->close();

// Devolver resultados como JSON
header('Content-Type: application/json');
echo json_encode($productos);
?>