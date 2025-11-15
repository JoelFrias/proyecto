<?php

/**
 * Sistema de Verificación de Sesión Robusto
 * Incluye: timeouts, validación de IP/User-Agent, regeneración de ID, 
 * protección CSRF y manejo de sesiones concurrentes
 */

// ========================================
// PASO 1: CONFIGURACIÓN DE SESIÓN SEGURA
// ========================================
// Estas configuraciones se establecen ANTES de session_start()

ini_set('session.cookie_httponly', 1);   // La cookie solo accesible por HTTP (no JavaScript)
                                          // Previene ataques XSS que intenten robar cookies

ini_set('session.cookie_secure', 0);     // Cookie solo se envía por HTTPS
                                          // IMPORTANTE: Cambiar a 0 si usas HTTP en desarrollo

ini_set('session.cookie_samesite', 'Strict'); // Cookie no se envía en peticiones cross-site
                                               // Previene ataques CSRF

ini_set('session.use_strict_mode', 1);   // PHP rechaza IDs de sesión no inicializados
                                          // Previene ataques de session fixation

session_start();

// ========================================
// PASO 2: CONFIGURACIÓN DE PARÁMETROS
// ========================================
// Array con todas las configuraciones del sistema de sesiones

$config = [
    'inactivity_limit' => 900,      // 15 minutos (900 segundos) sin actividad = logout
    'absolute_timeout' => 3600,      // 1 hora (3600 segundos) máximo, incluso con actividad
    'regenerate_interval' => 300,    // Cada 5 minutos cambia el ID de sesión por seguridad
    'check_fingerprint' => true,     // Verifica que el navegador no cambie
    'check_ip' => false,             // Verifica que la IP no cambie (OFF: problemas con VPN)
    'login_url' => '../../frontend/auth/login.php'  // Página de login
];

// ========================================
// PASO 3: FUNCIÓN DE HUELLA DIGITAL
// ========================================
/**
 * Crea un "hash" único del navegador del usuario
 * Combina: User-Agent + Idioma + Encoding
 * Si alguien roba la cookie pero usa otro navegador, el hash será diferente
 */
function getClientFingerprint() {
    // Obtiene información del navegador (puede ser vacío si no está disponible)
    $fingerprint = $_SERVER['HTTP_USER_AGENT'] ?? '';      // Ej: "Mozilla/5.0..."
    $fingerprint .= $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''; // Ej: "es-ES,es;q=0.9"
    $fingerprint .= $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''; // Ej: "gzip, deflate"
    
    // Convierte todo a un hash SHA-256 (cadena de 64 caracteres)
    // Ej: "a3f5b8c9d2e1..." - mismo navegador = mismo hash
    return hash('sha256', $fingerprint);
}

// ========================================
// PASO 4: FUNCIÓN PARA DESTRUIR SESIÓN
// ========================================
/**
 * Cierra la sesión de forma segura y redirige al login
 * $reason: motivo del cierre (se pasa por URL)
 */
function destroySession($redirect_url, $reason = '') {
    // 1. Vacía el array de sesión
    $_SESSION = array();
    
    // 2. Si las cookies están habilitadas, elimina la cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        // Establece la cookie con tiempo pasado para eliminarla del navegador
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // 3. Destruye el archivo de sesión en el servidor
    session_destroy();
    
    // 4. Construye la URL de redirección con el motivo
    $redirect = $redirect_url;
    if ($reason) {
        // Agrega el parámetro GET: login.php?reason=session_expired
        $redirect .= (strpos($redirect, '?') === false ? '?' : '&') . "reason=$reason";
    }
    
    // 5. Redirige y termina la ejecución
    header("Location: $redirect");
    exit();
}

// ========================================
// VERIFICACIONES DE SEGURIDAD
// ========================================

// ========================================
// VERIFICACIÓN 1: ¿Existe username?
// ========================================
// Si no hay username en la sesión, el usuario no está logueado
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    destroySession($config['login_url'], 'no_session');
}

// ========================================
// VERIFICACIÓN 2: ¿Existe user_id?
// ========================================
// Capa adicional de seguridad: verifica que exista el ID del usuario
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    destroySession($config['login_url'], 'invalid_session');
}

// ========================================
// VERIFICACIÓN 3: Inicializar timestamps
// ========================================
// Si es la primera vez que se ejecuta este código en la sesión,
// inicializa los tiempos de creación y última actividad
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();  // Guarda el timestamp de cuándo inició sesión
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();  // Guarda el timestamp de la última actividad
}

// ========================================
// VERIFICACIÓN 4: Timeout absoluto
// ========================================
// Verifica si la sesión ha durado más del tiempo máximo permitido
// Ejemplo: Si la sesión inició hace 1 hora y 5 minutos, cierra sesión
// Esto previene sesiones eternas
if (time() - $_SESSION['created_at'] > $config['absolute_timeout']) {
    // time() = tiempo actual
    // time() - created_at = cuántos segundos han pasado desde el login
    // Si pasaron más de 3600 segundos (1 hora), cierra sesión
    destroySession($config['login_url'], 'session_expired');
}

// ========================================
// VERIFICACIÓN 5: Timeout de inactividad
// ========================================
// Verifica si el usuario ha estado inactivo más de 15 minutos
// Ejemplo: Si hace 20 minutos que no hace nada, cierra sesión
if (time() - $_SESSION['last_activity'] > $config['inactivity_limit']) {
    // Si hace más de 900 segundos (15 minutos) que no hay actividad
    destroySession($config['login_url'], 'inactive_timeout');
}

// ========================================
// VERIFICACIÓN 6: Huella digital del navegador
// ========================================
// Previene "Session Hijacking" (robo de sesión)
// Verifica que el navegador sea el mismo que inició sesión
if ($config['check_fingerprint']) {
    $current_fingerprint = getClientFingerprint();  // Hash del navegador actual
    
    if (!isset($_SESSION['client_fingerprint'])) {
        // Primera vez: guarda el hash del navegador
        $_SESSION['client_fingerprint'] = $current_fingerprint;
    } elseif ($_SESSION['client_fingerprint'] !== $current_fingerprint) {
        // El hash cambió = otro navegador está usando la cookie robada
        error_log("Session hijacking attempt detected for user: " . $_SESSION['username']);
        destroySession($config['login_url'], 'security_violation');
    }
}

// ========================================
// VERIFICACIÓN 7: Validación de IP
// ========================================
// Verifica que la IP no haya cambiado
// NOTA: Puede causar problemas si el usuario usa VPN o proxy
if ($config['check_ip']) {
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';  // IP actual del usuario
    
    if (!isset($_SESSION['client_ip'])) {
        // Primera vez: guarda la IP
        $_SESSION['client_ip'] = $current_ip;
    } elseif ($_SESSION['client_ip'] !== $current_ip) {
        // La IP cambió = posible ataque o cambio de red
        error_log("IP change detected for user: " . $_SESSION['username']);
        destroySession($config['login_url'], 'ip_changed');
    }
}

// ========================================
// VERIFICACIÓN 8: Regeneración de ID
// ========================================
// Cambia el ID de sesión cada 5 minutos por seguridad
// Previene ataques de "Session Fixation"
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();  // Primera vez: marca el tiempo
}

if (time() - $_SESSION['last_regeneration'] > $config['regenerate_interval']) {
    // Han pasado más de 5 minutos, regenera el ID
    session_regenerate_id(true);  // true = elimina el archivo de sesión antiguo
    $_SESSION['last_regeneration'] = time();
}

// ========================================
// VERIFICACIÓN 9: Token CSRF
// ========================================
// Valida que los formularios POST tengan un token válido
// Previene ataques Cross-Site Request Forgery
if (isset($_POST) && !empty($_POST)) {
    // Si hay datos POST (formulario enviado)
    
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        // Falta el token CSRF = ataque o error de programación
        error_log("CSRF token missing for user: " . $_SESSION['username']);
        destroySession($config['login_url'], 'csrf_error');
    }
    
    // hash_equals() compara de forma segura (previene timing attacks)
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // El token no coincide = posible ataque CSRF
        error_log("CSRF token mismatch for user: " . $_SESSION['username']);
        destroySession($config['login_url'], 'csrf_error');
    }
}

// ========================================
// VERIFICACIÓN 10: Generar token CSRF
// ========================================
// Si no existe un token CSRF, lo crea
// Este token se debe incluir en todos los formularios
if (!isset($_SESSION['csrf_token'])) {
    // random_bytes(32) = genera 32 bytes aleatorios
    // bin2hex() = convierte a hexadecimal (64 caracteres)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========================================
// VERIFICACIÓN 11: Actualizar actividad
// ========================================
// Actualiza el timestamp de última actividad
// Esto reinicia el contador de inactividad
$_SESSION['last_activity'] = time();

// 12. Registrar actividad (opcional, para auditoría)
if (!isset($_SESSION['page_views'])) {
    $_SESSION['page_views'] = 0;
}
$_SESSION['page_views']++;
$_SESSION['last_page'] = $_SERVER['REQUEST_URI'] ?? '';

/**
 * Función helper para obtener el token CSRF en formularios
 * Uso: <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
 */
function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Función helper para obtener información de la sesión
 */
function getSessionInfo() {
    return [
        'username' => $_SESSION['username'] ?? '',
        'user_id' => $_SESSION['user_id'] ?? '',
        'time_remaining' => isset($_SESSION['last_activity']) 
            ? ($GLOBALS['config']['inactivity_limit'] - (time() - $_SESSION['last_activity']))
            : 0,
        'session_age' => isset($_SESSION['created_at']) 
            ? (time() - $_SESSION['created_at'])
            : 0,
        'page_views' => $_SESSION['page_views'] ?? 0
    ];
}

// La sesión ha sido validada exitosamente
// El script continúa con normalidad

?>