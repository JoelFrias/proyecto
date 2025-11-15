<?php

session_start();
require_once '../../models/conexion.php';

// 1. Configuración de la Respuesta
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Ajustar esto en producción
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


// Inicializar la respuesta
$response = ['success' => false, 'message' => ''];
$errors = [];

// Validar permisos de usuario
require_once '../../models/validar-permisos.php';
$permiso_necesario = 'PRO001';
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Manejar la solicitud OPTIONS (preflight CORS)
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 2. Obtener y decodificar los datos JSON
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if (empty($data)) {
        $response['message'] = 'No se recibieron datos JSON válidos.';
        http_response_code(400); // Bad Request
        echo json_encode($response);
        exit;
    }

    // 3. Extracción y Sanitización Inicial
    $descripcion = htmlspecialchars(trim($data['descripcion'] ?? ""));
    $idTipo = htmlspecialchars(trim($data['idTipo'] ?? ""));
    $cantidad = htmlspecialchars(trim($data['cantidad'] ?? ""));
    $precioCompra = htmlspecialchars(trim($data['precioCompra'] ?? ""));
    $precio1 = htmlspecialchars(trim($data['precio1'] ?? ""));
    $precio2 = htmlspecialchars(trim($data['precio2'] ?? ""));
    $reorden = htmlspecialchars(trim($data['reorden'] ?? ""));

    // 4. Validaciones y Sanitización Avanzada

    // **Validaciones Básicas Obligatorias**
    if (empty($descripcion)) {
        $errors[] = "La descripcion es obligatorio.";
    }
    
    if (empty($idTipo) | $idTipo <= 0) {
        $errors[] = "El tipo de producto es invalido";
    }

    if (empty($cantidad) | $cantidad <= 0) {
        $errors[] = "La cantidad no puede ser igual o menor a cero";
    }

    if (empty($precioCompra) | $precioCompra <= 0) {
        $errors[] = "El precio de compra no puede ser igual o menor a cero";
    }

    if (empty($precio1) | $precio1 <= 0) {
        $errors[] = "El precio 1 no puede ser igual o menor a cero";
    }
    
    if (empty($precio2) | $precio2 <= 0) {
        $errors[] = "El precio 2 no puede ser igual o menor a cero";
    }
    
    if (empty($reorden) | $reorden <= 0) {
        $errors[] = "El reorden no puede ser igual o menor a cero" . $reorden . "-";
    }

    if ($precio1 <= $precioCompra) {
        $errors[] = "El precio 1 debe ser mayor que el precio de compra.";
    }

    if ($precio2 <= $precioCompra) {
        $errors[] = "El precio 2 debe ser mayor que el precio de compra.";
    }

    if ($precio2 <= $precio1) {
        $errors[] = "El precio 1 y el precio 2 no pueden ser iguales";
    }

    // **5. Manejo de Errores de Validación**
    if (!empty($errors)) {
        $response['message'] = "Errores de validación.";
        $response['errors'] = $errors;
        http_response_code(400); // Bad Request
        echo json_encode($response);
        exit;
    }

    // El ID del empleado para auditoría. Usar 0 si no hay sesión activa.
    $usuario_id = $_SESSION['idEmpleado'] ?? 0; 
    
    // 6. Verificación de Duplicados
    try {
        // Iniciar transacción para la validación y la inserción
        $conn->begin_transaction(); 

        $stmt = $conn->prepare("INSERT INTO productos (descripcion, idTipo, existencia, precioCompra, precioVenta1, precioVenta2, reorden, fechaRegistro, activo) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), TRUE)");
        $stmt->bind_param('siidddd', $descripcion, $idTipo, $cantidad, $precioCompra, $precio1, $precio2, $reorden); 
        $stmt->execute();

        // Obtener el ID del producto recién insertado
        $idProducto = $stmt->insert_id;

        // Insertar en la tabla 'inventario'
        $stmt = $conn->prepare("INSERT INTO inventario (idProducto, existencia, ultima_actualizacion) 
                                VALUES (?, ?, NOW())");
        $stmt->bind_param("id", $idProducto, $cantidad);
        $stmt->execute();

        // Insertar en la tabla 'inventariotransacciones'
        $stmt = $conn->prepare("INSERT INTO `inventariotransacciones`(`tipo`, `idProducto`, `cantidad`, `fecha`, `descripcion`,`idEmpleado`) VALUES (?,?,?,NOW(),?,?)");
        $tipo = "ingreso";
        $descripcionTransaccion = "Ingreso por nuevo producto: ";
        $stmt->bind_param("siisi", $tipo, $idProducto, $cantidad, $descripcionTransaccion, $_SESSION['idEmpleado']);
        $stmt->execute();


        /**
         *  2. Auditoria de acciones de usuario
         */

        require_once '../../models/auditorias.php';
        $usuario_id = $_SESSION['idEmpleado'];
        $accion = 'Nuevo Producto';
        $detalle = 'Se ha registrado un nuevo producto: ' . $idProducto . ' - ' . $descripcion;
        $ip = $_SERVER['REMOTE_ADDR']; // Obtener la dirección IP del cliente
        registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);
    
        // Confirmar la transacción
        $conn->commit();

        // 8. Respuesta de éxito JSON
        $response['success'] = true;
        $response['message'] = 'Producto registrado con éxito.';
        $response['idProducto'] = $idProducto;
        http_response_code(201); // Created

    } catch (Exception $e) {
        // En caso de cualquier error de DB, revertir la transacción
        $conn->rollback();
        
        // 9. Respuesta de error JSON
        $response['message'] = "Error interno del servidor: " . $e->getMessage();
        http_response_code(500); // Internal Server Error
    } finally {
        // 10. Cerrar las declaraciones preparadas
        if (isset($stmt_cliente)) $stmt_cliente->close();
        if (isset($stmt_cuenta)) $stmt_cuenta->close();
        if (isset($stmt_direccion)) $stmt_direccion->close();
        $conn->close();
    }
} else {
    // Si no es un POST
    $response['message'] = 'Método de solicitud no permitido.';
    http_response_code(405); // Method Not Allowed
}

// 11. Enviar la respuesta JSON final
echo json_encode($response);
?>