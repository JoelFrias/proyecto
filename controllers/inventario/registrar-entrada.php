<?php
// registrar_entrada.php - Archivo para registrar entradas de inventario

// Iniciar sesión para verificación
session_start();

// Verificar que el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit();
}

// Validar permisos de usuario
require_once '../../models/validar-permisos.php';
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

// Recibir los datos JSON y decodificarlos
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Validar datos recibidos
if (!isset($data['id_producto']) || !isset($data['cantidad']) || $data['cantidad'] <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos']);
    exit();
}

// Limpiar y preparar los datos
$id_producto = filter_var($data['id_producto'], FILTER_SANITIZE_NUMBER_INT);
$cantidad = filter_var($data['cantidad'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$descripcion = isset($data['descripcion']) ? filter_var($data['descripcion'], FILTER_SANITIZE_STRING) : null;
$precio_compra = isset($data['precio_compra']) ? filter_var($data['precio_compra'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
$precio_venta1 = isset($data['precio_venta1']) ? filter_var($data['precio_venta1'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
$precio_venta2 = isset($data['precio_venta2']) ? filter_var($data['precio_venta2'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;
$punto_reorden = isset($data['punto_reorden']) ? filter_var($data['punto_reorden'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : null;

// Configuración de la conexión a la base de datos
require_once '../../models/conexion.php';

// Verificar conexión
if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit();
}

// Iniciar transacción
$conn->begin_transaction();

try {

    // 1. Actualizar el inventario (tabla productos)
    $sqlUpdateInventario = "UPDATE productos SET 
                          existencia = existencia + ?,
                          descripcion = COALESCE(?, descripcion),
                          precioCompra = COALESCE(?, precioCompra),
                          precioVenta1 = COALESCE(?, precioVenta1),
                          precioVenta2 = COALESCE(?, precioVenta2),
                          reorden = COALESCE(?, reorden)
                          WHERE id = ?";
    
    $stmtInventario = $conn->prepare($sqlUpdateInventario);
    if (!$stmtInventario) {
        throw new Exception("Error al preparar la actualización del inventario: " . $conn->error);
    }
    
    $stmtInventario->bind_param(
        "dsssssi",
        $cantidad,
        $descripcion,
        $precio_compra,
        $precio_venta1,
        $precio_venta2,
        $punto_reorden,
        $id_producto
    );
    
    if (!$stmtInventario->execute()) {
        throw new Exception("Error al actualizar el producto: " . $stmtInventario->error);
    }
    
    // Verificar si se actualizó algún registro
    if ($stmtInventario->affected_rows === 0) {
        throw new Exception("No se encontró el producto con ID: $id_producto en productos");
    }

    // 2. Actualizar existencia en inventario

    $sqlito = "UPDATE inventario SET existencia = existencia + ?, ultima_actualizacion = NOW() WHERE idProducto = ?";
    
    $statement = $conn->prepare($sqlito);
    if (!$statement) {
        throw new Exception("Error al preparar la actualización del inventario: " . $conn->error);
    }
    
    $statement->bind_param("di", $cantidad, $id_producto);
    
    if (!$statement->execute()) {
        throw new Exception("Error al actualizar el inventario: " . $statement->error);
    }
    
    // 3. Registrar la transaccion de inventario (tabla inventariotransacciones)
    $sqlTransacciones = "INSERT INTO inventariotransacciones
                        (tipo, idProducto, cantidad, fecha, descripcion, idEmpleado)
                        VALUES ('entrada', ?, ?, NOW(), ?, ?)";

    $stmtTransacciones = $conn->prepare($sqlTransacciones);
    if (!$stmtTransacciones){
        throw new Exception("Error al preparar el registro en transacciones: " . $conn->error);
    }

    $usuario = $_SESSION['idEmpleado'];
    $detalles = json_encode([
        'descripcion_nueva' => $descripcion,
        'precio_compra_nuevo' => $precio_compra,
        'precio_venta1_nuevo' => $precio_venta1,
        'precio_venta2_nuevo' => $precio_venta2,
        'punto_reorden_nuevo' => $punto_reorden
    ]);

    $stmtTransacciones->bind_param("idss", $id_producto, $cantidad, $detalles, $usuario);

    if(!$stmtTransacciones->execute()){
        throw new Exception("Error al registrar en transacciones: " . $stmtTransacciones->error);
    }

    // 4. Registrar auditoria de acciones de usuario
    require_once '../../models/auditorias.php';

    $accion = 'Entrada de producto a inventario';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    registrarAuditoriaUsuarios($conn, $usuario, $accion, $detalles, $ip);
    
    // Confirmar la transacción
    $conn->commit();
    
    // Éxito
    echo json_encode(['success' => true, 'message' => 'Entrada registrada correctamente']);
    
} catch (Exception $e) {
    // Revertir cambios en caso de error
    $conn->rollback();
    
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Cerrar conexiones
if (isset($stmtInventario)) $stmtInventario->close();
if (isset($stmtTransacciones)) $stmtTransacciones->close();
$conn->close();
?>