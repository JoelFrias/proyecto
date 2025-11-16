<?php
/**
 * Verificar-sesion.php (Middleware de Autenticación — Versión resuelta)
 *
 * Uso: require_once __DIR__ . '/../../core/Verificar-sesion.php';
 *
 * Notas:
 * - Diseñado para "A" (permitir funcionamiento en HTTP local / celular).
 * - Ajusta paths si los tuyos son distintos.
 */

// Iniciar sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración
define('INACTIVE_TIMEOUT', 7200); // 2 horas
define('EMPLOYEE_CHECK_INTERVAL', 300); // 5 minutos
define('ERROR_LOG_FILE', __DIR__ . '/../logs/auth_errors.log');

/**
 * Calcula la ruta dinámica al login desde cualquier ubicación
 */
function getLoginPath() {
    $current_path = $_SERVER['PHP_SELF'] ?? '/';
    $current_path = str_replace('//', '/', $current_path);

    $appPos = strpos($current_path, '/app');
    if ($appPos !== false) {
        $from_app = substr($current_path, $appPos + 4);
        $from_app = ltrim($from_app, '/');

        if ($from_app === '' || $from_app === false) {
            return 'auth/login.php';
        }

        $parts = explode('/', $from_app);
        if (count($parts) > 0) {
            array_pop($parts);
        }
        $depth = count($parts);

        return str_repeat('../', $depth) . 'auth/login.php';
    }

    return '/app/auth/login.php';
}

/**
 * Registra errores en log personalizado
 */
function logAuthError($message, $context = []) {
    $log_dir = dirname(ERROR_LOG_FILE);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $context_str = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

    $log_message = sprintf("[%s] IP: %s | %s %s\n", $timestamp, $ip, $message, $context_str);
    @error_log($log_message, 3, ERROR_LOG_FILE);
}

/**
 * Maneja errores de base de datos de forma segura
 */
function handleDatabaseError($conn = null, $error_message = '', $redirect = true) {
    logAuthError('Database Error: ' . $error_message, [
        'user_id' => $_SESSION['id'] ?? 'guest',
        'page' => $_SERVER['PHP_SELF'] ?? ''
    ]);

    if ($redirect) {
        $login_path = getLoginPath();
        header("Location: {$login_path}?error=system");
        exit();
    }

    return false;
}

/**
 * Devuelve opciones de cookie respetando si hay HTTPS.
 * Para "A" (local), permitimos secure=false si no hay HTTPS.
 */
function getCookieOptions() {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // PHP 7.3+ array options for setcookie
    return [
        'expires'  => time() + (86400 * 30), // 30 días por defecto (al crear)
        'path'     => '/',
        'domain'   => '',    // dejar por defecto
        'secure'   => $isHttps, // si hay HTTPS -> true, si no -> false (para local)
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

/**
 * Elimina cookie usando mismas opciones de cookie para concordancia
 */
function eliminarCookieRemember() {
    $opts = getCookieOptions();
    // Forzar expiración
    $opts['expires'] = time() - 3600;
    // Si la función de setcookie acepta array de opciones (PHP 7.3+)
    if (PHP_VERSION_ID >= 70300) {
        setcookie('remember_token', '', $opts);
    } else {
        // Fallback clásico
        setcookie('remember_token', '', $opts['expires'], $opts['path'], $opts['domain'], $opts['secure'], $opts['httponly']);
    }
}

/**
 * Elimina un token de forma segura en BD y elimina cookie
 */
function eliminarToken($conn, $token_hash) {
    try {
        if ($conn) {
            $stmt = $conn->prepare("DELETE FROM session_tokens WHERE token_hash = ?");
            if ($stmt) {
                $stmt->bind_param("s", $token_hash);
                $stmt->execute();
                $stmt->close();
            } else {
                logAuthError('Prepare failed in eliminarToken: ' . $conn->error);
            }
        }
    } catch (Exception $e) {
        logAuthError('Error eliminating token: ' . $e->getMessage());
        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
    }

    // Siempre eliminar cookie en cliente (usar la misma política de secure que al crearla)
    eliminarCookieRemember();
}

/**
 * Valida la sesión mediante cookie de "recordar"
 */
function validarSessionToken($conn) {
    if (empty($_COOKIE['remember_token'])) {
        return false;
    }

    if (!$conn) {
        logAuthError('No DB connection in validarSessionToken');
        return false;
    }

    try {
        $token = $_COOKIE['remember_token'];
        // Por si acaso viene con algún encoding
        $token = rawurldecode($token);
        $token_hash = hash('sha256', $token);

        $sql = "
            SELECT 
                st.id_usuario,
                st.expiry,
                st.user_agent,
                u.id,
                e.id AS idEmpleado,
                u.username,
                CONCAT(e.nombre, ' ', e.apellido) AS nombre,
                e.idPuesto,
                e.activo
            FROM 
                session_tokens AS st
            INNER JOIN usuarios AS u ON st.id_usuario = u.id
            INNER JOIN empleados AS e ON u.idEmpleado = e.id
            WHERE 
                st.token_hash = ?
                AND st.expiry > NOW()
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("s", $token_hash);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // USER-AGENT: comparar solo parte significativa
            $storedUA = $row['user_agent'] ?? '';
            $actualUA = $_SERVER['HTTP_USER_AGENT'] ?? '';

            // Tomar primeros 40 caracteres (o menor si no hay), esto previene mismatches por pequeñas variaciones.
            $storedPrefix = substr($storedUA, 0, 40);
            $actualPrefix = substr($actualUA, 0, 40);

            // Opcional: si storedUA tiene valores mínimos (ej: "Chrome", "Firefox"), comparar por contains.
            $ua_matches = false;
            if (!empty($storedUA)) {
                // Si storedUA es corto y contiene una palabra clave, usar stripos
                if (strlen($storedUA) < 50) {
                    if (stripos($actualUA, $storedUA) !== false) {
                        $ua_matches = true;
                    }
                }
                // También aceptar si los prefijos coinciden
                if ($storedPrefix === $actualPrefix) {
                    $ua_matches = true;
                }
                // O si actual contiene el primer token del stored
                $first_token = strtok($storedUA, " ");
                if ($first_token && stripos($actualUA, $first_token) !== false) {
                    $ua_matches = true;
                }
            } else {
                // Si no hay ua almacenado, no forzar rechazo (compatibilidad)
                $ua_matches = true;
            }

            if (!$ua_matches) {
                logAuthError('Token security violation: User-Agent mismatch', [
                    'user_id' => $row['id'],
                    'expected_ua_prefix' => substr($storedUA, 0, 100),
                    'actual_ua_prefix'   => substr($actualUA, 0, 100)
                ]);
                $stmt->close();
                eliminarToken($conn, $token_hash);
                return false;
            }

            // Verificar empleado activo
            if ($row['activo'] == 0) {
                logAuthError('Inactive employee login attempt', ['user_id' => $row['id']]);
                $stmt->close();
                eliminarToken($conn, $token_hash);
                return false;
            }

            // Restaurar sesión
            session_regenerate_id(true);
            $_SESSION['id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['idEmpleado'] = $row['idEmpleado'];
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['idPuesto'] = $row['idPuesto'];
            $_SESSION['last_activity'] = time();
            $_SESSION['persistent_session'] = true;

            // Verificar caja abierta (no crítico)
            verificarCajaAbierta($conn, $row['idEmpleado']);

            $stmt->close();
            return true;
        }

        $stmt->close();
        // Token no encontrado o expirado
        eliminarToken($conn, $token_hash);
        return false;

    } catch (Exception $e) {
        logAuthError('Exception in validarSessionToken: ' . $e->getMessage());
        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
        return false;
    }
}

/**
 * Verifica caja abierta con manejo de errores
 */
function verificarCajaAbierta($conn, $idEmpleado) {
    try {
        if (!$conn) {
            return;
        }
        $stmt = $conn->prepare("
            SELECT
                numCaja,
                idEmpleado,
                DATE_FORMAT(fechaApertura, '%d/%m/%Y %l:%i %p') AS fechaApertura,
                saldoApertura,
                registro
            FROM
                cajasabiertas
            WHERE
                idEmpleado = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $idEmpleado);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows > 0) {
            $datos_caja = $resultado->fetch_assoc();
            $_SESSION['numCaja'] = strval($datos_caja['numCaja']);
            $_SESSION['fechaApertura'] = $datos_caja['fechaApertura'];
            $_SESSION['saldoApertura'] = $datos_caja['saldoApertura'];
            $_SESSION['registro'] = $datos_caja['registro'];
        }

        $stmt->close();

    } catch (Exception $e) {
        logAuthError('Error checking open cash register: ' . $e->getMessage(), [
            'employee_id' => $idEmpleado
        ]);
        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
    }
}

/**
 * Cierra sesión limpiando todos los recursos
 */
function cerrarSesion($conn = null) {
    try {
        // Eliminar token de BD si existe
        if (!empty($_COOKIE['remember_token']) && $conn) {
            $token = rawurldecode($_COOKIE['remember_token']);
            $token_hash = hash('sha256', $token);
            eliminarToken($conn, $token_hash);
        } else {
            // Aun si no hay conn, eliminar la cookie localmente
            eliminarCookieRemember();
        }

        // Destruir sesión
        $_SESSION = array();

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        session_destroy();

    } catch (Exception $e) {
        logAuthError('Error during logout: ' . $e->getMessage());
    }
}

/**
 * Verifica si empleado sigue activo
 */
function verificarEmpleadoActivo($conn) {
    try {
        if (!isset($_SESSION['idEmpleado'])) {
            return false;
        }

        $stmt = $conn->prepare("SELECT activo FROM empleados WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $_SESSION['idEmpleado']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $is_active = ($row['activo'] == 1);
            $stmt->close();
            return $is_active;
        }

        $stmt->close();
        return false;

    } catch (Exception $e) {
        logAuthError('Error checking employee status: ' . $e->getMessage());
        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
        // En caso de error, asumir activo para no bloquear al usuario
        return true;
    }
}

// ====== LÓGICA PRINCIPAL DE VALIDACIÓN ======

try {
    // Si no hay sesión activa, intentar restaurar desde token
    if (!isset($_SESSION['username'])) {
        require_once __DIR__ . '/../core/conexion.php';

        if (!isset($conn) || $conn->connect_error) {
            handleDatabaseError($conn ?? null, 'Connection failed: ' . ($conn->connect_error ?? 'unknown'));
        }

        if (!validarSessionToken($conn)) {
            $login_path = getLoginPath();
            header("Location: {$login_path}");
            exit();
        }
    }
    // Si sí hay sesión activa
    else {
        // Controlar inactividad en sesiones NO persistentes
        if (!isset($_SESSION['persistent_session']) || $_SESSION['persistent_session'] !== true) {
            if (isset($_SESSION['last_activity'])) {
                $elapsed_time = time() - $_SESSION['last_activity'];
                if ($elapsed_time > INACTIVE_TIMEOUT) {
                    require_once __DIR__ . '/../core/conexion.php';
                    if (isset($conn) && !$conn->connect_error) {
                        cerrarSesion($conn);
                    } else {
                        // Aun si no hay conn, cerrar localmente
                        cerrarSesion(null);
                    }
                    $login_path = getLoginPath();
                    header("Location: {$login_path}?timeout=1");
                    exit();
                }
            }
            $_SESSION['last_activity'] = time();
        }

        // Verificación periódica de empleado activo
        if (!isset($_SESSION['last_employee_check']) ||
            (time() - $_SESSION['last_employee_check']) > EMPLOYEE_CHECK_INTERVAL) {

            require_once __DIR__ . '/../core/conexion.php';

            if (!isset($conn) || $conn->connect_error) {
                logAuthError('DB connection failed during employee check');
            } else {
                if (!verificarEmpleadoActivo($conn)) {
                    cerrarSesion($conn);
                    $login_path = getLoginPath();
                    header("Location: {$login_path}?disabled=1");
                    exit();
                }
                $_SESSION['last_employee_check'] = time();
            }
        }
    }

} catch (Exception $e) {
    logAuthError('Critical error in auth middleware: ' . $e->getMessage());
    $login_path = getLoginPath();
    header("Location: {$login_path}?error=critical");
    exit();
}

// Si se llegó hasta aquí, todo ok y se continúa con la página
return;
?>
