<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configurar parámetros de sesión ANTES de iniciar sesión (para móviles)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', '0');
    ini_set('session.gc_maxlifetime', '2592000'); // 30 días
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario ya inició sesión
if (isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Ya existe una sesión activa, por favor actualiza la página',
        'redirect' => '../views/'
    ]);
    exit();
}

// Solo aceptar POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit();
}

// Leer datos JSON del body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Formato JSON inválido'
    ]);
    exit();
}

require '../../core/conexion.php';

$user = isset($data['username']) ? trim($data['username']) : '';
$pass = isset($data['password']) ? trim($data['password']) : '';
$remember_me = isset($data['remember_me']) ? (bool)$data['remember_me'] : false;

// Validar campos vacíos
if (empty($user) || empty($pass)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Usuario y contraseña son requeridos.'
    ]);
    exit();
}

// Consulta a la base de datos
$query = "SELECT
            u.id,
            e.id AS idEmpleado,
            u.username,
            u.password,
            CONCAT(e.nombre, ' ', e.apellido) AS nombre,
            e.idPuesto,
            e.activo
        FROM
            usuarios AS u
        INNER JOIN empleados AS e
        ON
            u.idEmpleado = e.id
        WHERE u.username = ?
        LIMIT 1";

if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Verificar contraseña
        if (password_verify($pass, $row['password'])) {
            
            // Verificar si el empleado está activo
            if ($row['activo'] == 0 || $row['activo'] === false) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'El empleado vinculado a estas credenciales ha sido deshabilitado. Si esto es un error, póngase en contacto con el administrador.',
                    'disabled' => true
                ]);
                $stmt->close();
                exit();
            }
            
            // Guardar datos en la sesión
            $_SESSION['id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['idEmpleado'] = $row['idEmpleado'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['idPuesto'] = $row['idPuesto'];
            $_SESSION['last_activity'] = time();

            // Si el usuario marcó "Mantener sesión abierta"
            if ($remember_me) {
                // Marcar como sesión persistente
                $_SESSION['persistent_session'] = true;
                
                // Generar token único y seguro
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expiry = time() + (30 * 24 * 60 * 60); // 30 días
                
                // Guardar el token en la base de datos
                $stmt_token = $conn->prepare("INSERT INTO session_tokens (id_usuario, token_hash, expiry, user_agent, ip_address) VALUES (?, ?, ?, ?, ?)");
                $expiry_date = date('Y-m-d H:i:s', $expiry);
                $user_agent = $_SERVER['HTTP_USER_AGENT'];
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $stmt_token->bind_param("issss", $row['id'], $token_hash, $expiry_date, $user_agent, $ip_address);
                
                if ($stmt_token->execute()) {
                    // Detectar si es móvil
                    $isMobile = preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $user_agent);
                    
                    // Configuración de cookie optimizada para móviles
                    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                               || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
                    
                    $cookieOptions = [
                        'expires' => $expiry,
                        'path' => '/',
                        'domain' => '',
                        'secure' => $isHttps,
                        'httponly' => true,
                        'samesite' => 'Lax' // Lax es mejor para móviles que Strict
                    ];
                    
                    if (PHP_VERSION_ID >= 70300) {
                        setcookie('remember_token', $token, $cookieOptions);
                    } else {
                        setcookie(
                            'remember_token',
                            $token,
                            $cookieOptions['expires'],
                            $cookieOptions['path'],
                            $cookieOptions['domain'],
                            $cookieOptions['secure'],
                            $cookieOptions['httponly']
                        );
                    }
                }
                $stmt_token->close();
            } else {
                // No marcar como persistente si no se seleccionó "recordar"
                $_SESSION['persistent_session'] = false;
            }

            // Verificar si el empleado tiene una caja abierta
            $datosCaja = verificarCajaAbierta($conn, $row['idEmpleado']);

            // Respuesta exitosa
            echo json_encode([
                'success' => true,
                'message' => 'Inicio de sesión exitoso',
                'user' => [
                    'username' => $row['username'],
                    'nombre' => $row['nombre'],
                    'idPuesto' => $row['idPuesto']
                ],
                'caja' => $datosCaja,
                'redirect' => '../'
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Credenciales incorrectas.'
            ]);
        }
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Credenciales incorrectas.'
        ]);
    }

    $stmt->close();
} else {
    http_response_code(500);
    error_log("Error en la consulta SQL: " . $conn->error);
    echo json_encode([
        'success' => false,
        'error' => 'Se ha producido un error interno en el servidor.'
    ]);
}

/**
 * Función para verificar si el empleado tiene una caja abierta
 */
function verificarCajaAbierta($conn, $idEmpleado) {
    $sql_verificar = "SELECT
                        numCaja,
                        idEmpleado,
                        DATE_FORMAT(fechaApertura, '%d/%m/%Y %l:%i %p') AS fechaApertura,
                        saldoApertura,
                        registro
                    FROM
                        cajasabiertas
                    WHERE
                        idEmpleado = ?";

    $stmt = $conn->prepare($sql_verificar);
    $stmt->bind_param("i", $idEmpleado);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $datos_caja = $resultado->fetch_assoc();

        // Almacenar datos de la caja abierta en la sesión
        $_SESSION['numCaja'] = strval($datos_caja['numCaja']);
        $_SESSION['fechaApertura'] = $datos_caja['fechaApertura'];
        $_SESSION['saldoApertura'] = $datos_caja['saldoApertura'];
        $_SESSION['registro'] = $datos_caja['registro'];

        return $datos_caja;
    }

    return null;
}
?>