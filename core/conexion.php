<?php
/**
 * Conexión a Base de Datos - Lista para Producción
 * Sin patrón Singleton - Conexión directa tradicional
 */

// Configuración de errores según entorno
if (getenv('APP_ENV') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Establecer zona horaria
date_default_timezone_set('America/Santo_Domingo');

// ==================================================
// CARGAR VARIABLES DE ENTORNO
// ==================================================

if (!function_exists('loadEnv')) {
    /**
     * Cargar variables de entorno desde archivo .env
     * @param string $path Ruta al archivo .env
     * @return void
     * @throws Exception Si el archivo no existe
     */
    function loadEnv($path) {
        if (!file_exists($path)) {
            throw new Exception("Archivo .env no encontrado en: {$path}");
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            throw new Exception("No se pudo leer el archivo .env");
        }
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, "\"\' \t\n\r\0\x0B");
            
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

if (!function_exists('env')) {
    /**
     * Obtener variable de entorno
     * @param string $key Nombre de la variable
     * @param mixed $default Valor por defecto si no existe
     * @return mixed Valor de la variable o valor por defecto
     */
    function env($key, $default = null) {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
            case '':
                return null;
        }
        
        return $value;
    }
}

// Cargar .env
try {
    $envPath = __DIR__ . '/../.env';
    loadEnv($envPath);
} catch (Exception $e) {
    error_log("CRÍTICO: Error cargando .env - " . $e->getMessage());
    
    if (env('APP_ENV') !== 'production') {
        die("Error: No se pudo cargar la configuración del sistema: " . $e->getMessage());
    }
    
    die("Error del sistema. Contacte al administrador.");
}

// ==================================================
// FUNCIÓN DE MANEJO DE ERRORES
// ==================================================

function showDatabaseError($message) {
    $isProduction = (env('APP_ENV') === 'production');
    
    // Log del error
    error_log("CRÍTICO: Error de conexión BD: {$message}");
    
    // En producción, ocultar detalles
    $displayMessage = $isProduction 
        ? "Error de conexión a la base de datos. Por favor, contacte al administrador."
        : "Error de BD: {$message}";
    
    // Mostrar página de error solo si no hay redirecciones pendientes
    if (!headers_sent()) {
        header("HTTP/1.1 503 Servicio no disponible");
        header("Retry-After: 300"); // Reintentar en 5 minutos
        
        $debugInfo = '';
        if (!$isProduction) {
            $debugInfo = '<p>DB_HOST: ' . htmlspecialchars(env('DB_HOST', 'no configurado')) . '</p>
                <p>DB_DATABASE: ' . htmlspecialchars(env('DB_DATABASE', 'no configurado')) . '</p>
                <p>DB_PORT: ' . htmlspecialchars(env('DB_PORT', 'no configurado')) . '</p>
                <p>Error técnico: ' . htmlspecialchars($message) . '</p>';
        }
        
        echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de conexión</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .error-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 80%;
            max-width: 500px;
            text-align: center;
        }
        .error-icon {
            color: #dc3545;
            font-size: 48px;
            margin-bottom: 20px;
        }
        h1 {
            color: #dc3545;
            margin-bottom: 15px;
        }
        p {
            color: #6c757d;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            background-color: #0d6efd;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #0b5ed7;
        }
        .details {
            margin-top: 20px;
            padding: 15px;
            background-color: rgb(229, 229, 229);
            border-radius: 5px;
            font-size: 14px;
            color: rgb(52, 57, 60);
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>No se pudo conectar al servidor</h1>
        <p>Lo sentimos, no se ha podido establecer conexión con el servidor de base de datos.</p>
        <p>Por favor, inténtelo de nuevo más tarde.</p>
        <div class="details">
            <p>Error: ' . htmlspecialchars($displayMessage) . '</p>
            ' . $debugInfo . '
        </div>
    </div>
    <script>
        console.error("Error de conexión a la base de datos: ' . addslashes($displayMessage) . '");
    </script>
</body>
</html>';
        exit();
    }
    
    die("Error de conexión a la base de datos");
}

// ==================================================
// CONEXIÓN A LA BASE DE DATOS
// ==================================================

// Obtener datos de conexión desde .env
/** @var string $servername */
$servername = env('DB_HOST', 'localhost');
/** @var string $username */
$username = env('DB_USERNAME', 'root');
/** @var string $password */
$password = env('DB_PASSWORD', '');
/** @var string $dbname */
$dbname = env('DB_DATABASE', 'easypos');
/** @var int $port */
$port = (int)env('DB_PORT', 3306);
/** @var string $charset */
$charset = env('DB_CHARSET', 'utf8mb4');

// Validar configuración crítica
if (empty($dbname)) {
    error_log("CRÍTICO: DB_DATABASE no está configurado en .env");
    showDatabaseError("Configuración de base de datos incompleta");
}

// Variables para reintentos
$maxRetries = 3;
$retryDelay = 1; // segundos
$attempt = 0;
$conn = null;
$lastError = null;

// Intentar conexión con reintentos
while ($attempt < $maxRetries && $conn === null) {
    $attempt++;
    
    try {
        // Crear conexión
        $conn = new mysqli($servername, $username, $password, $dbname, $port);
        
        // Verificar error de conexión
        if ($conn->connect_error) {
            throw new Exception("Error de conexión: " . $conn->connect_error);
        }
        
        // Establecer conjunto de caracteres
        if (!$conn->set_charset($charset)) {
            throw new Exception("Error estableciendo charset: " . $conn->error);
        }
        
        // Configurar el modo estricto de SQL
        $conn->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
        
        // Establecer zona horaria para la conexión MySQL (Santo Domingo = UTC-4)
        $conn->query("SET time_zone = '-04:00'");
        
        // Log de éxito
        if (env('APP_DEBUG', false)) {
            error_log("✓ Conexión BD exitosa: {$dbname}@{$servername} (intento {$attempt})");
        }
        
    } catch (Exception $e) {
        $lastError = $e->getMessage();
        $conn = null;
        
        error_log("✗ Intento {$attempt}/{$maxRetries} falló: {$lastError}");
        
        // Si no es el último intento, esperar antes de reintentar
        if ($attempt < $maxRetries) {
            sleep($retryDelay);
        }
    }
}

// Si falló después de todos los reintentos
if ($conn === null || $conn->connect_error) {
    showDatabaseError($lastError ?? "No se pudo establecer conexión después de {$maxRetries} intentos");
}

// ==================================================
// FUNCIONES AUXILIARES OPCIONALES
// ==================================================

/**
 * Verificar si la conexión sigue activa
 * @return bool
 */
function checkConnection() {
    global $conn;
    
    if ($conn === null) {
        return false;
    }
    
    return $conn->ping();
}

/**
 * Reconectar si se perdió la conexión
 * @return bool
 */
function reconnectDatabase() {
    global $conn;
    
    // Obtener configuración nuevamente
    $servername = env('DB_HOST', 'localhost');
    $username = env('DB_USERNAME', 'root');
    $password = env('DB_PASSWORD', '');
    $dbname = env('DB_DATABASE', 'easypos');
    $port = (int)env('DB_PORT', 3306);
    $charset = env('DB_CHARSET', 'utf8mb4');
    
    if ($conn !== null) {
        $conn->close();
    }
    
    try {
        $conn = new mysqli($servername, $username, $password, $dbname, $port);
        
        if ($conn->connect_error) {
            throw new Exception($conn->connect_error);
        }
        
        $conn->set_charset($charset);
        $conn->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
        $conn->query("SET time_zone = '-04:00'");
        
        error_log("✓ Reconexión exitosa");
        return true;
        
    } catch (Exception $e) {
        error_log("✗ Error en reconexión: " . $e->getMessage());
        return false;
    }
}

?>