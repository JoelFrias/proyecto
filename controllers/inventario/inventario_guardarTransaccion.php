<?php

/* Verificacion de sesion */

// Iniciar sesión
session_start();

// Configurar el tiempo de caducidad de la sesión
$inactivity_limit = 900; // 15 minutos en segundos

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    die(json_encode(["success" => false, "error" => "No se ha encontrado una sesion activa"]));
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    die(json_encode(["success" => false, "error" => "La sesion ha caducado"]));
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

/* Validacion de rango */

if ($_SESSION['idPuesto'] > 2) {
    die(json_encode(["success" => false, "error" => "Acceso no Autorizado"]));
}

require_once '../../models/conexion.php';

// Funcion para guardar los logs de facturacion
function logDebug($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $logMessage .= " - Data: " . print_r($data, true);
    }
    error_log($logMessage);
}

// Validar metodo de entrada
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["success" => false, "error" => "Método no permitido"]));
}

// Obtener datos JSON
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

logDebug("Datos recibidos", $data);

// Verificar si el JSON es válido
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'JSON inválido',
        'details' => json_last_error_msg()
    ]));
}

header('Content-Type: application/json');

try {
    // Validaciones esenciales
    $requiredFields = ['idEmpleado', 'productos'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception("Campos obligatorios faltantes: " . implode(', ', $missingFields));
    }

    // Sanitización y asignación de variables
    $idEmpleado = $data['idEmpleado'];
    $productos = $data['productos'];

    logDebug("Variables procesadas", [
        'idEmpleado' => $idEmpleado,
        'Productos' => $productos
    ]);

    // Validar que el idEmpleado sea un número entero
    if (!is_int($idEmpleado) || $idEmpleado <= 0) {
        throw new Exception("El idEmpleado debe ser un número entero positivo.");
    }

    // Validar que productos sea un array y contenga al menos un producto
    if (!is_array($productos) || count($productos) === 0) {
        throw new Exception("El campo productos debe ser un array no vacío.");
    }

    // Validar que cada producto tenga id y cantidad
    foreach ($productos as $producto) {
        if (!isset($producto['id']) || !isset($producto['cantidad'])) {
            throw new Exception("Cada producto debe tener un id y una cantidad.");
        }
        if (!is_int($producto['id']) || !is_int($producto['cantidad'])) {
            throw new Exception("El id y la cantidad de cada producto deben ser números enteros.");
        }
    }

    // Validar que la cantidad de cada producto sea mayor a 0
    foreach ($productos as $producto) {
        if ($producto['cantidad'] <= 0) {
            throw new Exception("La cantidad de cada producto debe ser mayor a 0.");
        }
    }

    // Validar que el idEmpleado exista en la base de datos
    $stmt = $conn->prepare("SELECT id FROM empleados WHERE id = ? AND activo = TRUE");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de empleados: " . $conn->error);
    }
    $stmt->bind_param("i", $idEmpleado);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("El idEmpleado no existe en la base de datos o esta desactivado.");
    }

    /**
     *      0. Iniciamos la transaccion
     */


    $conn->begin_transaction();
    logDebug("Transacción iniciada");


    /**
     *      1. Ingresamos los productos en el inventario del empleado
     */

    foreach ($productos as $producto) {

        $idProducto = $producto['id'];
        $cantidad = $producto['cantidad'];
        
        // Verificar existencia en inventario
        $stmt = $conn->prepare("SELECT existencia FROM inventario WHERE idProducto = ? FOR UPDATE");
        if (!$stmt) {
            throw new Exception("Error preparando consulta de inventario: " . $conn->error);
        }
        $stmt->bind_param("i", $idProducto);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("El producto no existe en inventario.");
        }
        
        $row = $result->fetch_assoc();
        $existencia = $row['existencia'];
        
        if ($existencia < $cantidad) {
            throw new Exception("Stock insuficiente en inventario.");
        }
        
        // Verificar si el producto ya existe en inventarioempleados
        $stmt = $conn->prepare("SELECT cantidad FROM inventarioempleados WHERE idProducto = ? AND idEmpleado = ?");
        if (!$stmt) {
            throw new Exception("Error preparando consulta de inventarioempleados: " . $conn->error);
        }

        $stmt->bind_param("ii", $idProducto, $idEmpleado);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Si ya existe, actualizar cantidad
            $row = $result->fetch_assoc();
            $nuevaCantidad = $row['cantidad'] + $cantidad;
            $stmt = $conn->prepare("UPDATE inventarioempleados SET cantidad = ? WHERE idProducto = ? AND idEmpleado = ?");
            if (!$stmt) {
                throw new Exception("Error preparando actualización de inventarioempleados: " . $conn->error);
            }
            $stmt->bind_param("dii", $nuevaCantidad, $idProducto, $idEmpleado);
        } else {
            // Si no existe, insertar nuevo registro
            $stmt = $conn->prepare("INSERT INTO inventarioempleados (idProducto, cantidad, idEmpleado) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Error preparando inserción en inventarioempleados: " . $conn->error);
            }
            $stmt->bind_param("idi", $idProducto, $cantidad, $idEmpleado);
        }
        if (!$stmt->execute()) {
            throw new Exception("Error ejecutando operación en inventarioempleados: " . $stmt->error);
        }
        
        // Restar cantidad en inventario
        $nuevaExistencia = $existencia - $cantidad;
        $stmt = $conn->prepare("UPDATE inventario SET existencia = ?, ultima_actualizacion = NOW() WHERE idProducto = ?");
        if (!$stmt) {
            throw new Exception("Error preparando actualización de inventario: " . $conn->error);
        }
        $stmt->bind_param("di", $nuevaExistencia, $idProducto);
        if (!$stmt->execute()) {
            throw new Exception("Error ejecutando actualización de inventario: " . $stmt->error);
        }
    
        logDebug("Trasaccion realiada", [
            'idProducto' => $idProducto
        ]);

    }
    
    /**
     *      2. Registrar trasacciones de inventario
     */

    $stmt = $conn->prepare("INSERT INTO inventariotransacciones (tipo, idProducto, cantidad, fecha, descripcion, idEmpleado) VALUES ( 'transferencia', ?, ?, NOW(), 'Movimiento a inventario de empleado id:" . $idEmpleado . "' , ?)");
    if (!$stmt) {
        throw new Exception("Error registrando las transacciones de inventario: " . $conn->error);
    }
    
    foreach ($productos as $producto) {
        $stmt->bind_param('iii', $producto['id'], $producto['cantidad'], $_SESSION['idEmpleado']);
        if (!$stmt->execute()) {
            throw new Exception("Error registrar las transacciones de inventario del producto -> {$producto['id']}: " . $stmt->error);
        }
        logDebug("Transaccion de invatario realizada", $producto);
    }

    /**
     *      2. Auditoria de acciones de usuario
     */

    require_once '../../models/auditorias.php';
    $usuario_id = $_SESSION['idEmpleado'];
    $accion = 'Transacción de inventario';
    $detalle = 'Se han transferido productos al inventario del empleado con ID: ' . $idEmpleado . ' - Productos: ' . json_encode($productos);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

    /**
     *      3. Confirmar la transacción
     */

    
    $conn->commit();
    logDebug("Transacción completada exitosamente");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Transaccion realizada correctamente'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    logDebug("ERROR: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'errorCode' => $e->getCode()
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
    exit;
}