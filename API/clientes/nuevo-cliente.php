<?php

session_start();
require_once '../../core/conexion.php';

// 1. Configuración de la Respuesta
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Ajustar esto en producción
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Inicializar la respuesta
$response = ['success' => false, 'message' => ''];
$errors = [];

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'CLI001';
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
    $nombre = htmlspecialchars(trim($data['nombre'] ?? ""));
    $apellido = htmlspecialchars(trim($data['apellido'] ?? ""));
    $empresa = htmlspecialchars(trim($data['empresa'] ?? ""));
    $tipo_identificacion = htmlspecialchars(trim($data['tipo_identificacion'] ?? ""));
    $identificacion = htmlspecialchars(trim($data['identificacion'] ?? ""));
    $telefono = htmlspecialchars(trim($data['telefono'] ?? ""));
    $notas = htmlspecialchars(trim($data['notas'] ?? ""));
    
    $limite_credito_raw = $data['limite_credito'] ?? 0.0;
    
    $no = htmlspecialchars(trim($data['no'] ?? ""));
    $calle = htmlspecialchars(trim($data['calle'] ?? ""));
    $sector = htmlspecialchars(trim($data['sector'] ?? ""));
    $ciudad = htmlspecialchars(trim($data['ciudad'] ?? ""));
    $referencia = htmlspecialchars(trim($data['referencia'] ?? ""));

    // 4. Validaciones y Sanitización Avanzada

    // **Validaciones Básicas Obligatorias**
    if (empty($nombre)) {
        $errors[] = "El nombre es obligatorio.";
    }
    
    if (empty($apellido)) {
        $errors[] = "El apellido es obligatorio.";
    }
    
    if (empty($identificacion)) {
        $errors[] = "La identificación es obligatoria.";
    }
    
    if (empty($telefono)) {
        $errors[] = "El teléfono es obligatorio.";
    }

    // **Validaciones de Formato**
    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $nombre)) {
        $errors[] = "El nombre solo puede contener letras y espacios.";
    }
    
    if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $apellido)) {
        $errors[] = "El apellido solo puede contener letras y espacios.";
    }
    
    // Validación de Límite de Crédito
    $limite_credito = filter_var($limite_credito_raw, FILTER_VALIDATE_FLOAT);
    if ($limite_credito === false || $limite_credito < 0) {
        $errors[] = "El límite de crédito debe ser un número positivo.";
        $limite_credito = 0.0; // Establecer un valor seguro en caso de error
    }

    // **Sanitización Final**
    // Sanitizar teléfono para que solo queden números
    $telefono_sanitizado = preg_replace('/\D/', '', $telefono);
    // Sanitizar identificación para que solo queden números
    $identificacion_sanitizada = preg_replace('/\D/', '', $identificacion);

    // Reemplazar valores originales con los sanitizados
    $telefono = $telefono_sanitizado;
    $identificacion = $identificacion_sanitizada;

    // Validar longitudes mínimas/máximas (Ejemplos)
    if (strlen($identificacion) < 7 || strlen($identificacion) > 15) {
        $errors[] = "La identificación debe tener entre 7 y 15 dígitos.";
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

        $stmt_check = $conn->prepare("SELECT id FROM clientes WHERE identificacion = ?");
        $stmt_check->bind_param('s', $identificacion); 
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $response['message'] = "Ya existe un cliente registrado con esta identificación ({$identificacion}).";
            http_response_code(409); // Conflict
            $conn->rollback();
            echo json_encode($response);
            $stmt_check->close();
            exit;
        }
        $stmt_check->close();


        // **7. Inserción de Datos**
        
        // Insertar en la tabla 'clientes'
        $stmt_cliente = $conn->prepare("INSERT INTO clientes (nombre, apellido, empresa, tipo_identificacion, identificacion, telefono, notas, fechaRegistro, activo) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), TRUE)");
        $stmt_cliente->bind_param("sssssss", $nombre, $apellido, $empresa, $tipo_identificacion, $identificacion, $telefono, $notas);
        $stmt_cliente->execute();

        // Obtener el ID del cliente recién insertado
        $cliente_id = $conn->insert_id;

        // Insertar en la tabla 'clientes_cuenta'
        $stmt_cuenta = $conn->prepare("INSERT INTO clientes_cuenta (idCliente, limite_credito, balance) VALUES (?, ?, ?)");
        $stmt_cuenta->bind_param("idd", $cliente_id, $limite_credito, $limite_credito);
        $stmt_cuenta->execute();

        // Insertar en la tabla 'clientes_direcciones'
        $stmt_direccion = $conn->prepare("INSERT INTO clientes_direcciones (idCliente, no, calle, sector, ciudad, referencia) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_direccion->bind_param("isssss", $cliente_id, $no, $calle, $sector, $ciudad, $referencia);
        $stmt_direccion->execute();

        // Auditoría
        require_once '../../core/auditorias.php';
        $accion = 'Nuevo cliente';
        $detalle = 'ID del cliente: ' . $cliente_id;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
        registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

        // Confirmar la transacción
        $conn->commit();

        // 8. Respuesta de éxito JSON
        $response['success'] = true;
        $response['message'] = 'Cliente registrado con éxito.';
        $response['cliente_id'] = $cliente_id;
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