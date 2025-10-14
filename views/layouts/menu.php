<?php

if (!isset($_SESSION['idEmpleado'])) {
    echo 'No se ha iniciado sesion';
    exit();
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . "/proyecto"; // Eliminar ("/proyecto") en produccion

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
    $jsUrl = $baseUrl . '/assets/js/menu.js';
    $indexUrl = $baseUrl . '/';
    $clientesUrl = $baseUrl . '/views/clientes/clientes.php';
    $productosUrl = $baseUrl . '/views/productos/productos.php';
    $facturaUrl = $baseUrl . '/views/factura/factura-registro.php';
    $almacenUrl = $baseUrl . '/views/inventario/inventario.php';
    $inventarioPUrl = $baseUrl . '/views/inventario/inventario-empleados.php';
    $facturacionUrl = $baseUrl . '/views/factura/facturacion.php';
    $cajaUrl = $baseUrl . '/views/caja/caja.php';
    $panelUrl = $baseUrl . '/views/gestion/panel-administrativo.php';
    $logoutUrl = $baseUrl . '/controllers/authcontroller/logout.php';
    
    ?>

    <!-- Menú de navegación -->
    <ul class="menu">
        <li onclick="navigateTo('<?= $indexUrl ?>')"><i class="fas fa-home"></i><span>Inicio</span></li>
        <li onclick="navigateTo('<?= $clientesUrl ?>')"><i class="fa-solid fa-user-group"></i><span>Clientes</span></li>
        <li onclick="navigateTo('<?= $productosUrl ?>')"><i class="fa-solid fa-box-open"></i></i><span>Productos</span></li>
        <li onclick="navigateTo('<?= $facturaUrl ?>')"><i class="fa-solid fa-list-ul"></i></i><span>Registro de Facturas</span></li>
        <li onclick="navigateTo('<?= $almacenUrl ?>')"><i class="fa-solid fa-warehouse"></i><span>Almacén</span></li>
        <li onclick="navigateTo('<?= $inventarioPUrl ?>')"><i class="fa-solid fa-boxes-stacked"></i><span>Inventario Personal</span></li>
        <li onclick="navigateTo('<?= $facturacionUrl ?>')"><i class="fa-solid fa-shop"></i><span>Facturación</span></li>
        <li onclick="navigateTo('<?= $cajaUrl ?>')"><i class="fa-solid fa-cash-register"></i><span>Caja</span></li>

        <?php if ($_SESSION['idPuesto'] <= 2): ?>
            <li onclick="panelAdministrativo(<?php echo $_SESSION['idPuesto'] ?>)"><i class="fa-solid fa-screwdriver-wrench"></i><span>Panel Administrativo</span></li>
        <?php endif; ?>
        
        <li onclick="logout()"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span></li>
    </ul>

</nav>

<script src="<?= $jsUrl ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<!-- Funciones adicionales para validación de acceso -->
<script>
    function panelAdministrativo(idPuesto) {
        if (idPuesto > 2) {
            Swal.fire({
                icon: 'error',
                title: 'Acceso denegado',
                text: 'No tienes permisos para acceder a esta página.'
            });
        } else {
            navigateTo('<?= $panelUrl ?>');
        }
    }

    function logout() {
        Swal.fire({
            icon: 'question',
            title: 'Cierre de Sesión',
            text: '¿Desea cerrar la sesión?',
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