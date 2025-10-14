<?php

/* Verificacion de sesion */

// Iniciar sesión
session_start();

// Configurar el tiempo de caducidad de la sesión
$inactivity_limit = 900; // 15 minutos en segundos

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header('Location: ../../views/auth/login.php'); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header("Location: ../../views/auth/login.php?session_expired=session_expired"); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

require_once '../../models/conexion.php';

$sql = "SELECT 
            p.id AS id, 
            p.descripcion AS descripcion, 
            ie.cantidad AS existencia, 
            p.precioVenta1 AS precioVenta1, 
            p.precioVenta2 AS precioVenta2, 
            p.precioCompra AS precioCompra 
        FROM productos AS p
        INNER JOIN inventarioempleados AS ie ON p.id = ie.idProducto
        WHERE ie.idempleado = ".$_SESSION["idEmpleado"]." 
        AND p.activo = TRUE 
        ORDER BY
            p.descripcion ASC
        ";
$result = $conn->query($sql);

if (!$result) {
    die("Error en la consulta: " . $conn->error); // Muestra el error de la consulta
}

if ($result->num_rows > 0) {
    // echo "Número de filas: " . $result->num_rows; // Muestra el número de filas obtenidas
} else {
    // echo "0 resultados";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Facturacion</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/facturacion.css">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
</head>
<body>

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../views/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

        <button class="toggle-menu" id="toggleMenuFacturacion">☰</button>

            <div class="facturacion-container">

                <h2>Facturación</h2><br>
                <h3>Seleccione los productos</h3><br>

                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Buscar productos...">
                </div>
                <div class="products-grid" id="productsGrid">
                    <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo '<div class="product-card">';
                                echo '    <div class="product-info">';
                                echo '        <div>';
                                echo '            <div class="product-name">'  . $row["id"] .'   '. $row["descripcion"] . '</div>';
                                echo '            <div class="product-quantity">Existencia: ' . $row["existencia"] . '</div>';
                                echo '        </div>';
                                echo '        <div class="product-total"></div>';
                                echo '    </div>';
                                echo '    <div class="product-inputs">';
                                echo '        <input type="number" class="product-input" id="input1-' . $row["id"] . '" value="' . $row["precioVenta2"] . '" readonly>';
                                echo '        <input type="number" class="product-input" id="input2-' . $row["id"] . '" value="' . $row["precioVenta1"] . '" readonly>';
                                echo '        <button class="product-button" id="button1-' . $row["id"] . '" onclick="handleButton2(' . $row["id"] . ', ' . $row["precioVenta2"] . ')">Precio 2</button>';
                                echo '        <button class="product-button" id="button2-' . $row["id"] . '" onclick="handleButton1(' . $row["id"] . ', ' . $row["precioVenta1"] . ')">Precio 1</button>';
                                echo '    </div>';
                                echo '    <input type="number" class="quantity-input" id="quantity-' . $row["id"] . '" placeholder="Cantidad a llevar" min="1">';
                                echo '    <button class="quantity-button" onclick="addToCart(' . $row["id"] . ', \'' . addslashes($row["descripcion"]) . '\', ' . $row["precioVenta1"] . ', ' . $row["precioCompra"] . ', ' . $row["existencia"] . ')">Agregar Producto</button>';
                                echo '</div>';
                            }
                        } else {
                            echo "No existe ningun producto en tu inventario personal";
                        }
                    ?>
                </div>
            </div>

            <!-- Modal Selección Cliente -->
            <div id="modal-seleccionar-cliente" class="modal">
                <div class="modal-content">
                    <span class="close-btn-cliente">&times;</span>
                    <h2 class="titulo-centrado" >Buscar Cliente</h2>
                    <input type="text" id="search-input-cliente" placeholder="Buscar por id, nombre o empresa" autocomplete="off">
                    <table id="table-buscar-cliente">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Empresa</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="table-body-cliente">
                            <!-- Clientes añadidos dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Campos del cliente en el menú desplegable -->
            <div class="order-menu" id="orderMenu">
                <div class="menu-header">
                    <h2 class="menu-title">Procesar Factura</h2>
                </div>
                <div class="menu-content">
                    <div class="input-group">
                        <input type="text" class="menu-input" id="id-cliente" placeholder="ID del cliente" readonly>
                    </div>
                    <div class="input-group">
                        <input type="text" class="menu-input" id="nombre-cliente" placeholder="Nombre del cliente" readonly>
                    </div>
                    <div class="input-group">
                        <input type="text" class="menu-input" id="empresa" placeholder="Empresa" readonly>
                    </div>

                    <div class="menu-footer">
                        <button class="footer-button secundary" id="buscar-cliente">Buscar Cliente</button>
                        <button class="footer-button primary" id="btn-generar">Procesar Factura</button>
                    </div>

                    <!-- Lista de productos agregados -->
                    <div class="order-list" id="orderList">
                        <h3 class="order-list-title">Productos Agregados</h3>
                        <!-- Los productos se agregarán aquí dinámicamente -->
                        <div class="order-list-empty" id="orderListEmpty">
                            <span>No hay productos agregados.</span>
                        </div>
                    </div>

                    <!-- Total de la compra -->
                    <div class="order-total">
                        <div class="total-label">Total:</div>
                        <div class="total-amount">RD$ <span id="totalAmount">0.00</span></div>
                    </div>
                </div>
            </div>

            <!-- Modal para procesar la factura -->
            <div id="modal-procesar-factura" class="modal">
                <div class="modal-content">
                    <span class="close-btn-factura">&times;</span>
                    <h2>Procesar Factura</h2>

                    <div class="body">

                        <label for="tipo-factura">Tipo de factura:</label>
                        <select id="tipo-factura">
                            <option value="contado">Contado</option>
                            <option value="credito">Crédito</option>
                        </select>

                        <label for="forma-pago">Forma de Pago:</label>
                        <select id="forma-pago">
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="transferencia">Transferencia</option>
                        </select>

                        <div id="div-numero-tarjeta" style="display: none;">
                            <label for="numero-tarjeta">Número de Tarjeta:</label>
                            <input type="text" name="numero-tarjeta" id="numero-tarjeta" placeholder="Ingrese los últimos 4 dígitos de la tarjeta" maxlength="4">
                        </div>

                        <div id="div-numero-autorizacion" style="display: none;">
                            <label for="numero-autorizacion">Número de autorización:</label>
                            <input type="text" name="numero-autorizacion" id="numero-autorizacion" placeholder="Ingrese los 4 últimos dígitos de autorización" maxlength="4">
                        </div>

                        <div id="div-banco" style="display: none;">
                            <label for="banco">Seleccione el banco:</label>
                            <select name="banco" id="banco">
                                <option value="1" disabled selected>Seleccionar</option>
                                <?php
                                    $sql = "SELECT * FROM bancos WHERE id <> 1 AND enable = TRUE ORDER BY id ASC";
                                    $resultado = $conn->query($sql);
                                    if ($resultado->num_rows > 0) {
                                        while ($fila = $resultado->fetch_assoc()) {
                                        echo "<option value='" . $fila['id'] . "'>" . $fila['nombreBanco'] . "</option>";
                                        }
                                    } else {
                                        echo "<option value='' disabled>No hay opciones</option>";
                                    }
                                ?>
                            </select>
                        </div>

                        <div id="div-destino" style="display: none;">
                            <label for="destino-cuenta">Seleccione el destino:</label>
                            <select name="destino-cuenta" id="destino-cuenta">
                                <option value="1" disabled selected>Seleccionar</option>
                                <?php
                                    $sql = "SELECT * FROM destinocuentas WHERE id <> 1 AND enable = TRUE ORDER BY id ASC";
                                    $resultado = $conn->query($sql);
                                    if ($resultado->num_rows > 0) {
                                        while ($fila = $resultado->fetch_assoc()) {
                                        echo "<option value='" . $fila['id'] . "'>" . $fila['descripcion'] . "</option>";
                                        }
                                    } else {
                                        echo "<option value='' disabled>No hay opciones</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        
                        <?php if ($_SESSION['idPuesto'] <= 2) : ?>

                        <div id="div-descuento">
                            <label for="input-descuento">Descuento:</label>
                            <input type="number" id="input-descuento" name="input-descuento" step="0.01" min="0" placeholder="Ingrese el descuento (Si aplica)">
                        </div>
                        
                        <?php endif ?>

                        <div id="div-monto">
                            <label for="monto-pagado">Monto Pagado:</label>
                            <input type="number" name="monto-pagado" id="monto-pagado" placeholder="Ingrese la cantidad pagada" step="0.01" min="0">
                        </div>

                        <div id="div-devuelta" style="margin-top: 10px; font-weight: bold;">
                            <span>Devuelta: RD$ <span id="devuelta-monto">0.00</span></span>
                        </div>

                        <div>
                            <div class="order-total">
                                <div class="total-label">Total a Pagar:</div>
                                <div class="total-amount">RD$ <span id="totalAmount2">0.00</span></div>
                            </div>
                        </div>

                        <div id="botones-facturas">
                            <button id="guardar-factura" class="footer-button" onclick="guardarFactura(false)">Guardar Factura</button>
                            <button id="guardar-imprimir-factura" class="footer-button" onclick="guardarFactura(true)">Guardar e Imprimir</button>
                        </div>
                        
                    </div>

                </div>
            </div>
        
        <!-- TODO EL CONTENIDO DE LA PAGINA VA AQUI ARRIBA -->
        </div>
    </div>

    <script src="../../assets/js/facturacion.js"></script>
    
</body>
</html>