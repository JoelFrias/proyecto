<?php
/**
 * Script de cierre de sesión
 * Limpia sesiones y tokens de "recordar sesión"
 */

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
}

// Variable para almacenar datos antes de limpiar sesión
$usuario_id = $_SESSION['idEmpleado'] ?? null;
$username = $_SESSION['username'] ?? null;

// Eliminar token de "recordar sesión" si existe
if (isset($_COOKIE['remember_token'])) {
    try {
        require_once '../../core/conexion.php';		// Conexión a la base de datos

// Verificar conexión a la base de datos
if (!$conn || !$conn->connect_errno === 0) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos",
        "error_code" => "DATABASE_CONNECTION_ERROR"
    ]));
}
        
        $token = $_COOKIE['remember_token'];
        $token_hash = hash('sha256', $token);
        
        // Eliminar de la base de datos
        $stmt = $conn->prepare("DELETE FROM session_tokens WHERE token_hash = ?");
        if ($stmt) {
            $stmt->bind_param("s", $token_hash);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        error_log("Error en logout: " . $e->getMessage());
    }
}

// Eliminar cookie de remember_token
setcookie(
    'remember_token',
    '',
    [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Cambiar a true en producción con HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]
);

// Limpiar variables de sesión
$_SESSION = array();

// Eliminar cookie de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destruir sesión
session_destroy();

// Redirigir al login
header('Location: ../../app/auth/login.php?logout=1');
exit();
?>