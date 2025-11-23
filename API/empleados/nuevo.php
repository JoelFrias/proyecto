<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
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
$permiso_necesario = 'EMP001';
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
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 2. Obtener y decodificar los datos JSON
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if (empty($data)) {
        $response['message'] = 'No se recibieron datos JSON válidos.';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    // 3. Extracción y Sanitización Inicial
    $nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
    $apellido = isset($data['apellido']) ? trim($data['apellido']) : '';
    $tipo_identificacion = isset($data['tipo_identificacion']) ? trim($data['tipo_identificacion']) : '';
    $identificacion = isset($data['identificacion']) ? trim($data['identificacion']) : '';
    $telefono = isset($data['telefono']) ? trim($data['telefono']) : '';
    $idPuesto = isset($data['idPuesto']) ? intval($data['idPuesto']) : 0;
    $username = isset($data['username']) ? trim($data['username']) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';
    $permisos = isset($data['permisos']) ? $data['permisos'] : [];

    // 4. Validaciones y Sanitización Avanzada

    if (empty($nombre)) {
        $errors[] = "El nombre es obligatorio.";
    }
    
    if (empty($apellido)) {
        $errors[] = "El apellido es obligatorio.";
    }

    if (empty($tipo_identificacion)) {
        $errors[] = "El tipo identificación es obligatoria.";
    }
    
    if (empty($identificacion)) {
        $errors[] = "La identificación es obligatoria.";
    }
    
    if (empty($telefono)) {
        $errors[] = "El teléfono es obligatorio.";
    }

    if (empty($idPuesto)) {
        $errors[] = "El puesto es obligatorio.";
    }

    if (empty($username)) {
        $errors[] = "El nombre de usuario es obligatorio.";
    }

    if (empty($password)) {
        $errors[] = "La contraseña es obligatoria.";
    } elseif (strlen($password) < 4) {
        $errors[] = "La contraseña debe tener al menos 4 caracteres.";
    }

    // Validaciones de Formato
    if (!empty($nombre) && !preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $nombre)) {
        $errors[] = "El nombre solo puede contener letras y espacios.";
    }
    
    if (!empty($apellido) && !preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $apellido)) {
        $errors[] = "El apellido solo puede contener letras y espacios.";
    }

    // Sanitizar teléfono e identificación
    $telefono = preg_replace('/\D/', '', $telefono);
    $identificacion = preg_replace('/\D/', '', $identificacion);

    // Validar longitudes
    if (!empty($identificacion) && (strlen($identificacion) < 7 || strlen($identificacion) > 15)) {
        $errors[] = "La identificación debe tener entre 7 y 15 dígitos.";
    }

    // 5. Manejo de Errores de Validación
    if (!empty($errors)) {
        $response['message'] = "Errores de validación.";
        $response['errors'] = $errors;
        http_response_code(400);
        echo json_encode($response);
        exit;
    }

    $usuario_id = $_SESSION['idEmpleado'] ?? 0; 

    // Lógica de permisos optimizada
    $mapeoPermisos = [
        'clientes' => 'CLI001',
        'clientes-reporte' => 'CLI002',
        'productos' => 'PRO001',
        'productos-reporte' => 'PRO002',
        'avance-cuenta' => 'CLI003',
        'cancel-avance' => 'CLI004',
        'cancel-facturas' => 'FAC002',
        'almacen' => 'ALM001',
        'inv-empleados' => 'ALM003',
        'facturacion' => 'FAC001',
        'cot-accion' => 'COT001',
        'caja' => 'CAJ001',
        'pan-adm' => 'PADM001',
        'estadisticas' => 'PADM002',
        'bancos-destinos' => 'PADM003',
        'usuarios' => 'USU001',
        'empleados' => 'EMP001',
        'inf-factura' => 'FAC003',
        'cuadres' => 'CUA001',
        'cuadres-accion' => 'CUA002',
        'cot-registro' => 'COT002',
        'cot-cancelar' => 'COT003',
        'tran-inventario' => 'ALM002',
        'admi-inventario' => 'ALM004',
    ];

    $permisosActivos = [];
    foreach ($mapeoPermisos as $clave => $codigo) {
        if (isset($permisos[$clave]) && $permisos[$clave] == true) {
            $permisosActivos[] = $codigo;
        }
    }
    
    // Hashear la contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {

        // 6. Verificación de Duplicados

        // Verificar puesto
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM empleados_puestos WHERE id = ?");
        $stmt->bind_param("i", $idPuesto);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        
        if ($count == 0) {
            throw new Exception("El puesto seleccionado no es válido.");
        }

        // Verificar username
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM usuarios WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            throw new Exception("El nombre de usuario ya existe.");
        }

        // Verificar identificación
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM empleados WHERE identificacion = ?");
        $stmt->bind_param("s", $identificacion);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            throw new Exception("La identificación ya existe.");
        }

        // Verificar teléfono
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM empleados WHERE telefono = ?");
        $stmt->bind_param("s", $telefono);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        
        if ($count > 0) {
            throw new Exception("El teléfono ya existe.");
        }

        // 7. Inserción de Datos
        
        // Insertar empleado
        $stmt = $conn->prepare("INSERT INTO empleados (nombre, apellido, tipo_identificacion, identificacion, telefono, idPuesto, fechaIngreso, activo) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW(), TRUE)");
        $stmt->bind_param("sssssi", $nombre, $apellido, $tipo_identificacion, $identificacion, $telefono, $idPuesto);
        $stmt->execute();
        $idEmpleado = $stmt->insert_id;
        $stmt->close();

        // Insertar usuario
        $stmt = $conn->prepare("INSERT INTO usuarios (username, password, idEmpleado) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $username, $hashed_password, $idEmpleado);
        $stmt->execute();
        $stmt->close();

        // Insertar permisos
        if (!empty($permisosActivos)) {
            $stmt = $conn->prepare("INSERT INTO usuarios_permisos (id_permiso, id_empleado) VALUES (?, ?)");
            foreach ($permisosActivos as $permisoCodigo) {
                $stmt->bind_param("si", $permisoCodigo, $idEmpleado);
                $stmt->execute();
            }
            $stmt->close();
        }

        // Auditoría
        require_once '../../core/auditorias.php';
        $accion = 'Nuevo usuario/empleado';
        $detalle = 'ID del empleado: ' . $idEmpleado . ', Username: ' . $username;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
        registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

        // Confirmar la transacción
        $conn->commit();

        // 8. Respuesta de éxito
        $response['success'] = true;
        $response['message'] = 'Usuario y empleado registrados con éxito.';
        $response['empleado_id'] = $idEmpleado;
        http_response_code(201);

    } catch (Exception $e) {
        $conn->rollback();
        
        $response['message'] = $e->getMessage();
        http_response_code(500);
    } finally {
        // $conn->close();
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
    http_response_code(405);
}

echo json_encode($response);
?>