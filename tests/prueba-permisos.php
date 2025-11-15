<?php 

require_once 'models/validar-permisos.php';
require_once 'models/conexion.php';
session_start();

$id_empleado = $_SESSION['idEmpleado'];
$permiso = validarPermiso($conn, 'PADM001', $id_empleado);

if ($permiso) {
    echo "El usuario tiene el permiso \n Usuario ID: " . $id_empleado;
} else {
    echo "El usuario NO tiene el permiso";
}


?>
