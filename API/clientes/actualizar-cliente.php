<?php

require_once '../../core/conexion.php';
require_once '../../core/verificar-sesion.php';

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

// 1. Configuración de la Respuesta
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Inicializar la respuesta
$response = ['success' => false, 'message' => ''];
$errors = [];

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
    
    // 3. Obtener el ID del cliente y Validarlo
    // Asumimos que el ID del cliente viene en el cuerpo JSON para una API de actualización (PUT/POST)
    $cliente_id = isset($data['id']) ? intval($data['id']) : 0;

    if ($cliente_id <= 0) {
        $response['message'] = 'ID de cliente no proporcionado o inválido.';
        http_response_code(400); // Bad Request
        echo json_encode($response);
        exit;
    }

    // 4. Extracción y Sanitización Inicial
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
    
    // Obtener el estado de inactividad (activo/inactivo). TRUE si está activo, FALSE si está inactivo.
    // Asumimos que el cliente envía un booleano o un string que evaluamos. 
    // Si viene 'activo', lo usamos. Si no, asumimos activo por defecto.
    $activo_estado = boolval($data['activo'] ?? TRUE); 

    // 5. Validaciones y Sanitización Avanzada (Mismas que en nuevo-cliente.php)

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
        $limite_credito = 0.0; 
    }

    // **Sanitización Final**
    $telefono = preg_replace('/\D/', '', $telefono);
    $identificacion = preg_replace('/\D/', '', $identificacion);

    // Validar longitudes mínimas/máximas
    if (strlen($identificacion) < 7 || strlen($identificacion) > 15) {
        $errors[] = "La identificación debe tener entre 7 y 15 dígitos.";
    }

    // 6. Manejo de Errores de Validación
    if (!empty($errors)) {
        $response['message'] = "Errores de validación.";
        $response['errors'] = $errors;
        http_response_code(400); // Bad Request
        echo json_encode($response);
        exit;
    }
    
    // 7. Verificación de Duplicados (Excluyendo el cliente actual)
    try {
        $conn->begin_transaction(); 

        $stmt_check = $conn->prepare("SELECT id FROM clientes WHERE identificacion = ? AND id != ?");
        $stmt_check->bind_param('si', $identificacion, $cliente_id); 
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $response['message'] = "Ya existe otro cliente registrado con esta identificación ({$identificacion}).";
            http_response_code(409); // Conflict
            $conn->rollback();
            echo json_encode($response);
            $stmt_check->close();
            exit;
        }
        $stmt_check->close();


        // 8. Lógica de Actualización
        
        // a) Obtener el balance total de las facturas del cliente
        $query_balance_facturas = "SELECT SUM(f.balance) AS total_balance FROM facturas AS f WHERE idCliente = ?";
        $stmt_balance = $conn->prepare($query_balance_facturas);
        $stmt_balance->bind_param("i", $cliente_id);
        $stmt_balance->execute();
        $result_balance = $stmt_balance->get_result();
        $row_balance = $result_balance->fetch_assoc();
        $stmt_balance->close();

        // Calcular el nuevo balance: Límite de Crédito - Total de Deuda
        $total_balance_facturas = $row_balance['total_balance'] ?? 0;
        $nuevo_balance = $limite_credito - $total_balance_facturas;

        // b) Actualizar la tabla 'clientes'
        // NOTA: Se usa el estado $activo_estado (TRUE/FALSE)
        $stmt_cliente = $conn->prepare("UPDATE clientes SET nombre = ?, apellido = ?, empresa = ?, tipo_identificacion = ?, identificacion = ?, telefono = ?, notas = ?, activo = ? WHERE id = ?");
        $stmt_cliente->bind_param("sssssssii", $nombre, $apellido, $empresa, $tipo_identificacion, $identificacion, $telefono, $notas, $activo_estado, $cliente_id);
        $stmt_cliente->execute();
        $stmt_cliente->close();

        // c) Actualizar la tabla 'clientes_cuenta' con el nuevo balance
        $stmt_cuenta = $conn->prepare("UPDATE clientes_cuenta SET limite_credito = ?, balance = ? WHERE idCliente = ?");
        $stmt_cuenta->bind_param("ddi", $limite_credito, $nuevo_balance, $cliente_id);
        $stmt_cuenta->execute();
        $stmt_cuenta->close();

        // d) Actualizar la tabla 'clientes_direcciones'
        $stmt_direccion = $conn->prepare("UPDATE clientes_direcciones SET no = ?, calle = ?, sector = ?, ciudad = ?, referencia = ? WHERE idCliente = ?");
        $stmt_direccion->bind_param("sssssi", $no, $calle, $sector, $ciudad, $referencia, $cliente_id);
        $stmt_direccion->execute();
        $stmt_direccion->close();

        // Confirmar la transacción
        $conn->commit();

        // 9. Respuesta de éxito JSON
        $response['success'] = true;
        $response['message'] = 'Cliente actualizado con éxito.';
        $response['cliente_id'] = $cliente_id;
        http_response_code(200); // OK (para actualización)

    } catch (Exception $e) {
        // En caso de cualquier error de DB, revertir la transacción
        $conn->rollback();
        
        // 10. Respuesta de error JSON
        $response['message'] = "Error interno del servidor al actualizar: " . $e->getMessage();
        http_response_code(500); // Internal Server Error
    } finally {
        // 11. Cerrar la conexión
        // $conn->close();
    }
} else {
    // Si no es un POST
    $response['message'] = 'Método de solicitud no permitido.';
    http_response_code(405); // Method Not Allowed
}

// 12. Enviar la respuesta JSON final
echo json_encode($response);
?>