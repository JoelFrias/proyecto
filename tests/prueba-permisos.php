<?php 

require_once '../core/validar-permisos.php';
require_once '../core/conexion.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$id_empleado = $_SESSION['idEmpleado'];
$permisoNum = 'ALM004';
$permiso = validarPermiso($conn, $permisoNum, $id_empleado);

if ($permiso) {
    echo "El usuario tiene el permiso\n Usuario ID: " . $id_empleado . "\nPermiso: " . $permisoNum;
} else {
    echo "El usuario NO tiene el permiso\n Usuario ID: " . $id_empleado . "\nPermiso: " . $permisoNum;
}


?>
