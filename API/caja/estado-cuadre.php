<?php
// cambiar-estado-cuadre.php

require_once '../../core/verificar-sesion.php';
require_once '../../core/conexion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'CUA002';
$id_empleado = $_SESSION['idEmpleado'];

// Configurar respuesta JSON
header('Content-Type: application/json');

// Validar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método de petición no válido'
    ]);
    exit();
}

// Validar permisos
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    echo json_encode([
        'success' => false,
        'message' => 'No tiene permisos para realizar esta acción'
    ]);
    exit();
}

// Validar parámetros recibidos
if (!isset($_POST['numCaja']) || !isset($_POST['nuevoEstado']) || !isset($_POST['notas'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Faltan parámetros requeridos'
    ]);
    exit();
}

// Variables
$numCaja = $_POST['numCaja'];
$nuevoEstado = $_POST['nuevoEstado'];
$notas = $_POST['notas'];
$idEmpleado = $_SESSION['idEmpleado']; // ← CORREGIDO: era 'id_empleado'

// Validar que la nota no esté vacía y tenga mínimo 10 caracteres
if (empty(trim($notas)) || strlen(trim($notas)) < 10) {
    echo json_encode([
        'success' => false,
        'message' => 'La nota es obligatoria y debe tener al menos 10 caracteres'
    ]);
    exit();
}

// Validar formato del número de caja
if (!preg_match('/^[a-zA-Z0-9]{5}$/', $numCaja)) {
    echo json_encode([
        'success' => false,
        'message' => 'Formato de número de caja inválido'
    ]);
    exit();
}

// Validar que el estado sea válido
$estados_permitidos = ['cerrada', 'cancelada'];
if (!in_array($nuevoEstado, $estados_permitidos)) {
    echo json_encode([
        'success' => false,
        'message' => 'Estado no válido'
    ]);
    exit();
}

try {
    // Iniciar transacción
    $conn->begin_transaction();

    // Verificar que la caja existe y obtener su estado actual
    $sql_verificar = "SELECT estado FROM cajascerradas WHERE numCaja = ?";
    $stmt_verificar = $conn->prepare($sql_verificar);
    $stmt_verificar->bind_param("s", $numCaja);
    $stmt_verificar->execute();
    $result_verificar = $stmt_verificar->get_result();

    if ($result_verificar->num_rows === 0) {
        throw new Exception('No se encontró el cuadre de caja especificado');
    }

    $row = $result_verificar->fetch_assoc();
    $estadoActual = strtolower($row['estado']);

    // Validar que el cuadre no esté ya cerrado o cancelado
    if ($estadoActual === 'cerrada' || $estadoActual === 'cancelada') {
        throw new Exception('Este cuadre ya ha sido ' . $estadoActual);
    }

    // Actualizar el estado del cuadre
    $sql_actualizar = "UPDATE cajascerradas SET estado = ? WHERE numCaja = ?";
    $stmt_actualizar = $conn->prepare($sql_actualizar);
    $stmt_actualizar->bind_param("ss", $nuevoEstado, $numCaja);
    
    if (!$stmt_actualizar->execute()) {
        throw new Exception('Error al actualizar el estado del cuadre');
    }

    // Insertar en caja_estado_detalle
    $sql_detalle = "INSERT INTO `caja_estado_detalle` (`numCaja`, `id_empleado`, `nota`, `fecha`) VALUES (?, ?, ?, NOW())";
    $stmt_estado = $conn->prepare($sql_detalle);
    
    if (!$stmt_estado) {
        throw new Exception('Error al preparar la consulta de detalle: ' . $conn->error);
    }
    
    $stmt_estado->bind_param("sis", $numCaja, $idEmpleado, $notas); // ← CORREGIDO: "sis" porque id_empleado es INT
    
    if (!$stmt_estado->execute()) {
        throw new Exception('Error al guardar el detalle del estado: ' . $stmt_estado->error);
    }

    // Confirmar transacción
    $conn->commit();

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'El cuadre de caja ha sido ' . ($nuevoEstado === 'cerrada' ? 'cerrado' : 'cancelado') . ' exitosamente'
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Cerrar conexión
$conn->close();
?>