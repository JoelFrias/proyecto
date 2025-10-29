<?php
session_start();

require '../../models/conexion.php';

// Verificar sesión
if (!isset($_SESSION['username']) || !isset($_SESSION['idEmpleado'])) {
    echo json_encode(['error' => 'Sesión no válida']);
    exit();
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

// Validar que se recibió el número de cotización
if (!$data || !isset($data['noCotizacion']) || empty($data['noCotizacion'])) {
    echo json_encode(['error' => 'Número de cotización no proporcionado']);
    exit();
}

$noCotizacion = $conn->real_escape_string($data['noCotizacion']);

// Iniciar transacción
$conn->begin_transaction();

try {
    // Verificar que la cotización existe y pertenece al empleado de la sesión
    $sqlVerificar = "SELECT no, id_empleado FROM cotizaciones_inf WHERE no = ?";
    $stmtVerificar = $conn->prepare($sqlVerificar);
    $stmtVerificar->bind_param('s', $noCotizacion);
    $stmtVerificar->execute();
    $resultVerificar = $stmtVerificar->get_result();
    
    if ($resultVerificar->num_rows === 0) {
        throw new Exception('Cotización no encontrada');
    }
    
    $cotizacion = $resultVerificar->fetch_assoc();
    
    $stmtVerificar->close();
    
    // Eliminar productos de la cotización (cotizaciones_det)
    $sqlDetalle = "DELETE FROM cotizaciones_det WHERE no = ?";
    $stmtDetalle = $conn->prepare($sqlDetalle);
    $stmtDetalle->bind_param('s', $noCotizacion);
    
    if (!$stmtDetalle->execute()) {
        throw new Exception('Error al eliminar los productos de la cotización');
    }
    
    $stmtDetalle->close();
    
    // Eliminar la cotización (cotizaciones_inf)
    $sqlInf = "DELETE FROM cotizaciones_inf WHERE no = ?";
    $stmtInf = $conn->prepare($sqlInf);
    $stmtInf->bind_param('s', $noCotizacion);
    
    if (!$stmtInf->execute()) {
        throw new Exception('Error al eliminar la cotización');
    }
    
    $stmtInf->close();
    
    // Confirmar transacción
    $conn->commit();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'mensaje' => 'Cotización eliminada exitosamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>