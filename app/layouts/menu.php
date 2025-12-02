<?php

if (!isset($_SESSION['idEmpleado'])) {
    echo 'No se ha iniciado sesion';
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . "/EasyPOS"; // Eliminar ("/EasyPOS") en produccion

//incluir el archivo para validar permisos
require_once __DIR__ . '/../../core/validar-permisos.php';
require_once __DIR__ . '/../../core/conexion.php';

// Obtener permisos del usuario
$id_empleado = $_SESSION['idEmpleado'];
$permisoAlmacen = validarPermiso($conn, 'ALM001', $id_empleado);
$permisoFacturacion = validarPermiso($conn, 'FAC001', $id_empleado);
$permisoCaja = validarPermiso($conn, 'CAJ001', $id_empleado);
$permisoPanelAdmin = validarPermiso($conn, 'PADM001', $id_empleado);

?>

<!-- Botón móvil -->
<button id="mobileToggle" class="toggle-btn">
    <i class="fas fa-bars"></i>
</button>

<!-- Barra lateral de navegación -->
<nav class="sidebar" id="sidebar">
    <div class="logo" style="cursor: pointer;" id="dassd">
        <h2>EasyPOS</h2>
        <!-- Botón para alternar el menú -->
        <button id="toggleMenu" class="toggle-btn">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <?php
    $jsUrl = $baseUrl . '/app/assets/js/menu.js';
    $indexUrl = $baseUrl . '/';
    $clientesUrl = $baseUrl . '/app/clientes/clientes.php';
    $productosUrl = $baseUrl . '/app/productos/productos.php';
    $facturaUrl = $baseUrl . '/app/factura/factura-registro.php';
    $almacenUrl = $baseUrl . '/app/inventario/inventario.php';
    $inventarioPUrl = $baseUrl . '/app/inventario/inventario-empleados.php';
    $facturacionUrl = $baseUrl . '/app/factura/facturacion.php';
    $cajaUrl = $baseUrl . '/app/caja/caja.php';
    $panelUrl = $baseUrl . '/app/gestion/panel-administrativo.php';
    $logoutUrl = $baseUrl . '/api/auth/logout.php';
    ?>

    <!-- Menú de navegación -->
    <ul class="menu">
        <li onclick="navigateTo('<?= $indexUrl ?>')"><i class="fas fa-home"></i><span>Inicio</span></li>
        <li onclick="navigateTo('<?= $clientesUrl ?>')"><i class="fa-solid fa-user-group"></i><span>Clientes</span></li>
        <li onclick="navigateTo('<?= $productosUrl ?>')"><i class="fa-solid fa-box-open"></i></i><span>Productos</span></li>
        <li onclick="navigateTo('<?= $facturaUrl ?>')"><i class="fa-solid fa-list-ul"></i></i><span>Registro de Facturas</span></li>
        
        <?php if ($permisoFacturacion): ?>
            <li onclick="navigateTo('<?= $facturacionUrl ?>')"><i class="fa-solid fa-shop"></i><span>Facturación</span></li>
        <?php endif; ?>
        
        <?php if ($permisoCaja): ?>
            <li onclick="navigateTo('<?= $cajaUrl ?>')"><i class="fa-solid fa-cash-register"></i><span>Caja</span></li>
        <?php endif; ?>
        
        <li onclick="navigateTo('<?= $inventarioPUrl ?>')"><i class="fa-solid fa-boxes-stacked"></i><span>Inventario Personal</span></li>
        
        <?php if ($permisoAlmacen): ?>
            <li onclick="navigateTo('<?= $almacenUrl ?>')"><i class="fa-solid fa-warehouse"></i><span>Almacén</span></li>
        <?php endif; ?>

        <?php if ($permisoPanelAdmin): ?>
            <li onclick="navigateTo('<?= $panelUrl ?>')"><i class="fa-solid fa-screwdriver-wrench"></i><span>Panel Administrativo</span></li>
        <?php endif; ?>
        
        <li onclick="logout()"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span></li>
    </ul>

</nav>

<script src="<?= $jsUrl ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function logout() {
        Swal.fire({
            title: 'Cierre de Sesión',
            text: 'Confirme el cierre de sesión',
            showCancelButton: true,
            confirmButtonText: 'Cerrar sesión',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '<?= $logoutUrl ?>';
            }
        });
    }
</script>