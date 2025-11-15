<?php
session_start();

require '../../models/conexion.php';

// Verificar sesión
if (!isset($_SESSION['username']) || !isset($_SESSION['idEmpleado'])) {
    echo json_encode(['error' => 'Sesión no válida']);
    exit();
}

// Validar permisos de usuario
require_once '../../models/validar-permisos.php';
$permiso_necesario = 'COT001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    http_response_code(403);
    die(json_encode([
        "success" => false, 
        "error" => "No tiene permisos para realizar esta acción",
        "error_code" => "INSUFFICIENT_PERMISSIONS",
        "solution" => "Contacte al administrador del sistema para obtener los permisos necesarios"
    ]));
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

// Validar datos recibidos
if (!$data || !isset($data['idCliente']) || !isset($data['productos']) || empty($data['productos'])) {
    echo json_encode(['error' => 'Datos incompletos']);
    exit();
}

$idCliente = intval($data['idCliente']);
$descuento = isset($data['descuento']) && $data['descuento'] > 0 ? floatval($data['descuento']) : 0;
$total = floatval($data['total']);
$subtotal = $total + $descuento; // El subtotal es antes del descuento
$productos = $data['productos'];
$notas = isset($data['notas']) ? $conn->real_escape_string($data['notas']) : '';
$idEmpleado = intval($_SESSION['idEmpleado']);

// Iniciar transacción
$conn->begin_transaction();

try {
    // Obtener el contador actual
    $sqlContador = "SELECT contador FROM cotizaciones_contador LIMIT 1";
    $resultContador = $conn->query($sqlContador);
    
    if ($resultContador->num_rows === 0) {
        throw new Exception('No existe el contador de cotizaciones');
    }
    
    $rowContador = $resultContador->fetch_assoc();
    $contadorActual = intval($rowContador['contador']);
    
    // Incrementar el contador
    $nuevoContador = $contadorActual + 1;
    
    // Actualizar el contador en la base de datos
    $sqlActualizar = "UPDATE cotizaciones_contador SET contador = ?, ultima_actualizacion = NOW()";
    $stmtActualizar = $conn->prepare($sqlActualizar);
    $stmtActualizar->bind_param('i', $nuevoContador);
    
    if (!$stmtActualizar->execute()) {
        throw new Exception('Error al actualizar el contador');
    }
    $stmtActualizar->close();
    
    // Generar número de cotización: IDEMPLEADO + CONTADOR (5 dígitos)
    $noCotizacion = $idEmpleado . str_pad($nuevoContador, 5, '0', STR_PAD_LEFT);

    // Insertar en cotizaciones_inf
    $sqlInf = "INSERT INTO cotizaciones_inf (no, fecha, id_cliente, id_empleado, subtotal, descuento, total, notas, estado) 
               VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, 'pendiente')";
    
    $stmtInf = $conn->prepare($sqlInf);
    $stmtInf->bind_param('siiddds', $noCotizacion, $idCliente, $idEmpleado, $subtotal, $descuento, $total, $notas);
    
    if (!$stmtInf->execute()) {
        throw new Exception('Error al guardar la cotización: ' . $stmtInf->error);
    }

    // Insertar productos en cotizaciones_det
    $sqlDet = "INSERT INTO cotizaciones_det (no, id_producto, cantidad, precio_s) VALUES (?, ?, ?, ?)";
    $stmtDet = $conn->prepare($sqlDet);

    foreach ($productos as $producto) {
        $idProducto = intval($producto['id']);
        $cantidad = floatval($producto['cantidad']);
        $precioVenta = floatval($producto['venta']);

        $stmtDet->bind_param('sidd', $noCotizacion, $idProducto, $cantidad, $precioVenta);
        
        if (!$stmtDet->execute()) {
            throw new Exception('Error al guardar los productos: ' . $stmtDet->error);
        }
    }

    // Confirmar transacción
    $conn->commit();

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'noCotizacion' => $noCotizacion,
        'contador' => $nuevoContador,
        'mensaje' => 'Pre-factura guardada exitosamente'
    ], JSON_UNESCAPED_UNICODE);

    $stmtInf->close();
    $stmtDet->close();

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>