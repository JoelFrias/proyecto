<?php
// registrar_salida.php - Archivo para registrar salidas de inventario

// Iniciar sesión para verificación
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

// Recibir los datos JSON y decodificarlos
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Validar datos recibidos
if (!isset($data['id_producto']) || !isset($data['cantidad']) || $data['cantidad'] <= 0 || !isset($data['motivo'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']);
    exit();
}

// Configuración de la conexión a la base de datos
require_once '../../core/conexion.php';

// Verificar conexión
if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit();
}

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'ALM004';
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

// Iniciar transacción
$conn->begin_transaction();

try {
    // Validaciones esenciales
    $requiredFields = ['id_producto','cantidad','motivo', 'detalles'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missingFields[] = $field;
        }
    }
    if (!empty($missingFields)) {
        throw new Exception("Campos obligatorios faltantes: " . implode(', ', $missingFields), 1001);
    }

    // Limpiar y preparar los datos
    $id_producto = filter_var($data['id_producto'], FILTER_SANITIZE_NUMBER_INT);
    $cantidad = filter_var($data['cantidad'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $motivo = filter_var($data['motivo'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $detalles = isset($data['detalles']) ? filter_var($data['detalles'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';

    // 1. Verificar si hay suficiente existencia - CORREGIDO el método de obtener resultado
    $sqlVerificar = "SELECT existencia FROM inventario WHERE idProducto = ?";
    $stmtVerificar = $conn->prepare($sqlVerificar);
    if (!$stmtVerificar) {
        throw new Exception("Error al preparar la verificación: " . $conn->error);
    }
    
    $stmtVerificar->bind_param("i", $id_producto);
    if (!$stmtVerificar->execute()) {
        throw new Exception("Error al verificar existencia: " . $stmtVerificar->error);
    }
    
    // Cambio en la forma de obtener el resultado - usando get_result() en lugar de bind_result()
    $resultVerificar = $stmtVerificar->get_result();
    if ($resultVerificar->num_rows === 0) {
        throw new Exception("No se encontró el producto con ID: $id_producto");
    }
    
    $row = $resultVerificar->fetch_assoc();
    $existencia = $row['existencia'];
    $stmtVerificar->close();
    
    // Verificar si hay suficiente stock
    if ($existencia < $cantidad) {
        throw new Exception("Stock insuficiente. Existencia actual: $existencia");
    }
    
    // 2. Actualizar el inventario (tabla productos)
    $sqlUpdateInventario = "UPDATE productos SET 
                          existencia = existencia - ?
                          WHERE id = ?";
    
    $stmtInventario = $conn->prepare($sqlUpdateInventario);
    if (!$stmtInventario) {
        throw new Exception("Error al preparar la actualización del inventario de productos: " . $conn->error);
    }
    
    $stmtInventario->bind_param("di", $cantidad, $id_producto);
    
    if (!$stmtInventario->execute()) {
        throw new Exception("Error al actualizar el inventario de productos: " . $stmtInventario->error);
    }
    
    // 3. Actualizar existencia en tabla inventario
    $sqlito = "UPDATE inventario SET existencia = existencia - ?, ultima_actualizacion = NOW() WHERE idProducto = ?";
    
    $statement = $conn->prepare($sqlito);
    if (!$statement) {
        throw new Exception("Error al preparar la actualización del inventario: " . $conn->error);
    }
    
    $statement->bind_param("di", $cantidad, $id_producto);
    
    if (!$statement->execute()) {
        throw new Exception("Error al actualizar el inventario: " . $statement->error);
    }
    
    // 4. Registrar la salida en el historial
    $sqlHistorial = "INSERT INTO inventariotransacciones 
                    (idProducto, tipo, cantidad, idEmpleado, fecha, descripcion)
                    VALUES (?, 'salida', ?, ?, NOW(), ?)";
    
    $stmtHistorial = $conn->prepare($sqlHistorial);
    if (!$stmtHistorial) {
        throw new Exception("Error al preparar el registro en historial: " . $conn->error);
    }
    
    $usuario = $_SESSION['idEmpleado'];
    $descripcion = "Motivo: " . $motivo . " Detalles: " . $detalles;
    $stmtHistorial->bind_param("idis", $id_producto, $cantidad, $usuario, $descripcion);
    
    if (!$stmtHistorial->execute()) {
        throw new Exception("Error al registrar en historial: " . $stmtHistorial->error);
    }

    // 5. Registrar auditoria de acciones de usuario
    require_once '../../core/auditorias.php';

    $accion = 'Salida de productos de Inventario';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    registrarAuditoriaUsuarios($conn, $usuario, $accion, $descripcion, $ip);
    
    // Confirmar la transacción
    $conn->commit();
    
    // Éxito
    echo json_encode(['success' => true, 'message' => 'Salida registrada correctamente']);
    
} catch (Exception $e) {
    // Revertir cambios en caso de error
    $conn->rollback();
    
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Cerrar conexiones
if (isset($stmtInventario)) $stmtInventario->close();
if (isset($statement)) $statement->close();
if (isset($stmtHistorial)) $stmtHistorial->close();
$conn->close();
?>