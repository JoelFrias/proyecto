<?php
session_start();

require '../../models/conexion.php';

// Verificar que se recibió el número de cotización
if (!isset($_GET['no']) || empty($_GET['no'])) {
    echo json_encode(['error' => 'Número de cotización no proporcionado']);
    exit();
}

$no = $conn->real_escape_string($_GET['no']);

// Obtener datos del cliente de la cotización
$sqlCliente = "SELECT 
                ci.id_cliente,
                CONCAT(c.nombre, ' ', c.apellido) AS nombreCliente,
                c.empresa
            FROM cotizaciones_inf AS ci
            INNER JOIN clientes AS c ON c.id = ci.id_cliente
            WHERE ci.no = ?";

$stmtCliente = $conn->prepare($sqlCliente);
$stmtCliente->bind_param('s', $no);
$stmtCliente->execute();
$resultCliente = $stmtCliente->get_result();

if ($resultCliente->num_rows === 0) {
    echo json_encode(['error' => 'Cotización no encontrada']);
    exit();
}

$cliente = $resultCliente->fetch_assoc();

// Obtener productos de la cotización con datos adicionales del inventario del empleado
// Cambiado a LEFT JOIN para incluir productos aunque no estén en el inventario
$sqlProductos = "SELECT 
                    cd.id_producto,
                    cd.cantidad,
                    cd.precio_s,
                    p.descripcion,
                    p.precioCompra,
                    IFNULL(ie.cantidad, 0) AS existencia
                FROM cotizaciones_det AS cd
                INNER JOIN productos AS p ON p.id = cd.id_producto
                LEFT JOIN inventarioempleados AS ie ON ie.idProducto = cd.id_producto AND ie.idempleado = ?
                WHERE cd.no = ?";

$stmtProductos = $conn->prepare($sqlProductos);
$stmtProductos->bind_param('is', $_SESSION['idEmpleado'], $no);
$stmtProductos->execute();
$resultProductos = $stmtProductos->get_result();

$productos = [];
while ($row = $resultProductos->fetch_assoc()) {
    $productos[] = [
        'id' => $row['id_producto'],
        'descripcion' => $row['descripcion'],
        'cantidad' => $row['cantidad'],
        'precio' => $row['precio_s'],
        'precioCompra' => $row['precioCompra'],
        'existencia' => $row['existencia'],
        'subtotal' => $row['cantidad'] * $row['precio_s']
    ];
}

// Preparar respuesta
$response = [
    'success' => true,
    'cliente' => [
        'id' => $cliente['id_cliente'],
        'nombre' => $cliente['nombreCliente'],
        'empresa' => $cliente['empresa']
    ],
    'productos' => $productos
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);

$stmtCliente->close();
$stmtProductos->close();
$conn->close();
?>