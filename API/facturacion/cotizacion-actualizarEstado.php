<?php
session_start();

require '../../core/conexion.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (!isset($_SESSION['username']) || !isset($_SESSION['idEmpleado'])) {
    echo json_encode(['error' => 'Sesión no válida'], JSON_UNESCAPED_UNICODE);
    exit();
}

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
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

// Validar que se recibió el número de cotización
if (!$data || !isset($data['noCotizacion']) || empty($data['noCotizacion'])) {
    echo json_encode([
        'error' => 'Número de cotización no proporcionado',
        'debug' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$noCotizacion = $conn->real_escape_string($data['noCotizacion']);

// Iniciar transacción
$conn->begin_transaction();

try {

    // Verificar que la cotización existe
    $sqlVerificar = "SELECT no, estado FROM cotizaciones_inf WHERE no = ?";
    $stmtVerificar = $conn->prepare($sqlVerificar);
    
    if (!$stmtVerificar) {
        throw new Exception('Error al preparar consulta de verificación: ' . $conn->error);
    }
    
    $stmtVerificar->bind_param('s', $noCotizacion);
    
    if (!$stmtVerificar->execute()) {
        throw new Exception('Error al ejecutar verificación: ' . $stmtVerificar->error);
    }
    
    $resultVerificar = $stmtVerificar->get_result();
    
    if ($resultVerificar->num_rows === 0) {
        throw new Exception('Cotización no encontrada: ' . $noCotizacion);
    }
    
    $cotizacionActual = $resultVerificar->fetch_assoc();
    $stmtVerificar->close();
    
    // Cambiar estado de la cotización a 'vendida'
    $sqlUpdate = "UPDATE cotizaciones_inf SET estado = 'vendida' WHERE no = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    
    if (!$stmtUpdate) {
        throw new Exception('Error al preparar consulta de actualización: ' . $conn->error);
    }
    
    $stmtUpdate->bind_param('s', $noCotizacion);
    
    if (!$stmtUpdate->execute()) {
        throw new Exception('Error al ejecutar actualización: ' . $stmtUpdate->error);
    }
    
    // Verificar que se actualizó al menos una fila
    if ($stmtUpdate->affected_rows === 0) {
        throw new Exception('No se pudo actualizar la cotización (0 filas afectadas)');
    }
    
    $stmtUpdate->close();
    
    // Confirmar transacción
    $conn->commit();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'mensaje' => 'Se ha cambiado el estado de la cotización a vendida exitosamente',
        'noCotizacion' => $noCotizacion,
        'estadoAnterior' => $cotizacionActual['estado'],
        'estadoNuevo' => 'vendida'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    echo json_encode([
        'error' => $e->getMessage(),
        'noCotizacion' => $noCotizacion ?? 'No definido'
    ], JSON_UNESCAPED_UNICODE);
}

// $conn->close();
?>