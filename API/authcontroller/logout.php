<?php
session_start();

// Auditoria de acciones de usuario

require_once '../../core/conexion.php';
require_once '../../core/auditorias.php';
$usuario_id = $_SESSION['idEmpleado'];
$accion = 'Cierre de sesión';
$detalle = 'El usuario ' . $_SESSION['username'] . ' ha cerrado sesión.';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

// Cierre de sesion

session_unset();
session_destroy();

header("Location: ../../frontend/auth/login.php");
exit();

?>
