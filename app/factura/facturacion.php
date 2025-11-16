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
    header('Location: ../../app/auth/login.php'); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header("Location: ../../app/auth/login.php?session_expired=session_expired"); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

require_once '../../core/conexion.php';

////////////////////////////////////////////////////////////////////
///////////////////// VALIDACION DE PERMISOS ///////////////////////
////////////////////////////////////////////////////////////////////

require_once '../../core/validar-permisos.php';
$permiso_necesario = 'FAC001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    echo "
        <html>
            <head>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'ACCESO DENEGADO',
                        text: 'No tienes permiso para acceder a esta sección.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        window.history.back();
                    });
                </script>
            </body>
        </html>";
        
    exit(); 
}

////////////////////////////////////////////////////////////////////

// Validar permisos para realizar o vender cotizaciones
$codigo_permiso = 'COT001';
$permiso_cotizaciones = validarPermiso($conn, $codigo_permiso, $id_empleado);  // Ya se realizó el request del archivo

// Consulta para obtener los productos del inventario del empleado
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
    <style>
        .btn-cotizaciones {
            background-color: #f5f5f5;
            border: 2px solid #a5a5a5ff;
            color: #333;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        .btn-cotizaciones:hover {
            background-color: #e0e0e0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-cotizaciones:active {
            background-color: #d5d5d5;
        }

        /* Modal Overlay */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Modal Container */
        .modal-overlay .modal-container {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @media (max-width: 768px) {
            .modal-overlay .modal-container {
                width: 95%;
                max-height: 95vh;
                border-radius: 8px;
            }
        }

        /* Modal Header */
        .modal-overlay .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-overlay .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
        }

        @media (max-width: 768px) {
            .modal-overlay .modal-header {
                padding: 16px 20px;
            }

            .modal-overlay .modal-header h3 {
                font-size: 18px;
            }
        }

        .modal-overlay .btn-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #666;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .modal-overlay .btn-close:hover {
            background: #f5f5f5;
            color: #1a1a1a;
        }

        /* Search Bar */
        .modal-overlay .search-container-modal {
            padding: 20px 28px;
            background: #fafafa;
            border-bottom: 1px solid #e5e5e5;
        }

        .modal-overlay .search-input-modal {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d4d4d4;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        @media (max-width: 768px) {
            .modal-overlay .search-container-modal {
                padding: 16px 20px;
            }

            .modal-overlay .search-input-modal {
                padding: 10px 14px;
                font-size: 16px; /* Evita zoom en iOS */
            }
        }

        .modal-overlay .search-input-modal:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Modal Body */
        .modal-overlay .modal-body {
            padding: 28px;
            max-height: calc(90vh - 200px);
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .modal-overlay .modal-body {
                padding: 20px;
                max-height: calc(95vh - 180px);
            }
        }

        /* Table Styles */
        .modal-overlay .table-container {
            overflow-x: auto;
        }

        .modal-overlay table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 700px;
        }

        @media (max-width: 768px) {
            .modal-overlay table {
                font-size: 13px;
                min-width: 650px;
            }

            .modal-overlay .table-container {
                -webkit-overflow-scrolling: touch;
            }
        }

        .modal-overlay thead {
            background: #fafafa;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-overlay th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e5e5e5;
            white-space: nowrap;
        }

        .modal-overlay td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            color: #1a1a1a;
        }

        @media (max-width: 768px) {
            .modal-overlay th {
                padding: 10px 12px;
                font-size: 11px;
            }

            .modal-overlay td {
                padding: 12px;
                font-size: 13px;
            }
        }

        .modal-overlay tbody tr {
            transition: background 0.2s;
        }

        .modal-overlay tbody tr:hover {
            background: #fafafa;
        }

        /* Button Styles */
        .modal-overlay .btn-seleccionar {
            padding: 8px 16px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .modal-overlay .btn-seleccionar:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .modal-overlay .btn-seleccionar:active {
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            .modal-overlay .btn-seleccionar {
                padding: 6px 12px;
                font-size: 12px;
            }
        }

        /* Empty State */
        .modal-overlay .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #999;
        }

        .modal-overlay .empty-state p {
            font-size: 15px;
        }

        /* Loading State */
        .modal-overlay .loading {
            text-align: center;
            padding: 48px 20px;
            color: #999;
        }

        .modal-overlay .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2563eb;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Scrollbar */
        .modal-overlay .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-overlay .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .modal-overlay .modal-body::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .modal-overlay .modal-body::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        .bloqueado:hover {
            cursor: not-allowed;
        }

    </style>
</head>
<body>

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

        <button class="toggle-menu" id="toggleMenuFacturacion">☰</button>

            <div class="facturacion-container">

                <h2>Facturación</h2><br>

                <?php

                if (!$permiso_cotizaciones) {
                    echo '<p style="color: red; font-weight: bold;">No tienes permiso para gestionar cotizaciones.</p><br>';
                } else {

                ?>

                <button id="btn-cotizaciones" class="btn-cotizaciones">Abrir Lista cotización</button><br><br>

                <!-- Modal cotización -->
                <div id="modal-overlay" class="modal-overlay">
                    <div class="modal-container">
                        <!-- Header -->
                        <div class="modal-header">
                            <h3>Lista de cotización</h3>
                            <button class="btn-close" onclick="cerrarModal()">&times;</button>
                        </div>

                        <!-- Search Bar -->
                        <div class="search-container-modal">
                            <input 
                                type="text" 
                                id="search-input-modal" 
                                class="search-input-modal" 
                                placeholder="Buscar por número, cliente, fecha o emisor..."
                                autocomplete="off"
                            >
                        </div>

                        <!-- Body -->
                        <div class="modal-body">
                            <div class="table-container">
                                <table id="prefactura-table">
                                    <thead>
                                        <tr>
                                            <th>Acción</th>
                                            <th>NO</th>
                                            <th>Cliente</th>
                                            <th>Fecha</th>
                                            <th>Total</th>
                                            <th>Emisor</th>
                                            <th>Notas</th>
                                        </tr>
                                    </thead>
                                    <tbody id="prefactura-body">
                                        <tr>
                                            <td colspan="6" class="loading">
                                                <div class="spinner"></div>
                                                <p>Cargando datos...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <?php } ?>


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
                    <h2 class="menu-title" id="titulo-factura">Procesar Factura</h2>
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
                    
                    <button class="footer-button cliente" id="buscar-cliente">Buscar Cliente</button><br><br>

                    <div class="menu-footer">
                        <?php if ($permiso_cotizaciones) : ?> <!-- Verificar permiso para cotizaciones -->
                            <button class="footer-button secundary" id="guardar-prefactura">Guardar como cotización</button>
                        <?php endif; ?>
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
                        
                        <div id="div-descuento">
                            <label for="input-descuento">Descuento:</label>
                            <input type="number" id="input-descuento" name="input-descuento" step="0.01" min="0" placeholder="Ingrese el descuento (Si aplica)">
                        </div>

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
    
    <!-- Script para guardar como cotización -->
    <script>
        // Event listener para el botón de guardar cotización
        document.getElementById('guardar-prefactura').addEventListener('click', function() {
            guardarPrefactura();
        });

        function guardarPrefactura() {
            let idCliente = document.getElementById("id-cliente").value.trim();
            let descuento = document.getElementById("input-descuento") ? document.getElementById("input-descuento").value.trim() : '';
            let total = document.getElementById("totalAmount").textContent.replace(/,/g, "");

            // Convertir valores numéricos
            idCliente = idCliente ? parseInt(idCliente) : null;
            descuento = descuento ? parseFloat(descuento) : 0;
            total = total ? parseFloat(total) : null;

            // Validación de selección de cliente
            if (!idCliente) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación',
                    text: 'Por favor, seleccione un cliente.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            // Validar que hayan productos agregados
            if (productos.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación',
                    text: 'Ningún producto ha sido agregado a la cotización.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            // Validar que el total sea válido
            if (Number.isNaN(total) || total <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Error',
                    text: 'El total de la cotización no es válido.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            // Mensaje de confirmación con opción de agregar notas
            Swal.fire({
                title: '¿Confirmar cotización?',
                html: `
                    <div style="text-align: left; margin-bottom: 15px;">
                        <p style="margin: 5px 0;"><strong>Cliente:</strong> ${document.getElementById("nombre-cliente").value}</p>
                        <p style="margin: 5px 0;"><strong>Productos:</strong> ${productos.length}</p>
                        <p style="margin: 5px 0;"><strong>Total:</strong> RD$ ${total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                    </div>
                    <hr style="margin: 15px 0;">
                    <p style="margin-bottom: 10px; text-align: left;"><strong>Notas adicionales (opcional):</strong></p>
                    <textarea id="swal-notas" class="swal2-textarea" placeholder="Ejemplo: Cliente requiere entrega el día lunes..." style="width: 80%; min-height: 100px; font-size: 14px; padding: 10px; border: 1px solid #d4d4d4; border-radius: 6px; font-family: inherit;"></textarea>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, Guardar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
                width: '500px',
                preConfirm: () => {
                    return document.getElementById('swal-notas').value;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const notas = result.value || '';
                    
                    // Preparar datos para enviar
                    const datos = {
                        idCliente,
                        descuento,
                        total,
                        notas,
                        productos
                    };

                    // Mostrar loading
                    Swal.fire({
                        title: 'Guardando cotización...',
                        html: 'Por favor espere un momento',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Enviar datos al servidor
                    fetch("../../api/facturacion/cotizacion-guardar.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(datos)
                    })
                    .then(response => response.text())
                    .then(text => {
                        try {
                            let data = JSON.parse(text);
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡cotización Guardada!',
                                    html: `
                                        <div style="text-align: center; padding: 10px;">
                                            <p style="font-size: 16px; margin: 10px 0;">
                                                <strong>Número de Cotización:</strong>
                                            </p>
                                            <p style="font-size: 24px; font-weight: bold; color: #2563eb; margin: 10px 0;">
                                                ${data.noCotizacion}
                                            </p>
                                            <p style="font-size: 14px; color: #666; margin-top: 15px;">
                                                ${data.mensaje}
                                            </p>
                                        </div>
                                    `,
                                    showDenyButton: true,
                                    showCancelButton: false,
                                    confirmButtonText: 'Imprimir PDF',
                                    denyButtonText: 'Cerrar',
                                    confirmButtonColor: '#2563eb',
                                    denyButtonColor: '#6b7280'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        // Abrir PDF en nueva ventana
                                        window.open(`../../reports/factura/cotizacion.php?cotizacion=${data.noCotizacion}`, '_blank');
                                        setTimeout(() => {
                                            location.reload();
                                        }, 500);
                                    } else {
                                        location.reload();
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error al Guardar',
                                    text: data.error || 'Error desconocido al guardar la cotización',
                                    showConfirmButton: true,
                                    confirmButtonText: 'Aceptar',
                                    confirmButtonColor: '#dc2626'
                                });
                                console.error("Error al guardar:", data.error);
                            }
                        } catch (error) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error del Servidor',
                                html: `
                                    <p>Se produjo un error inesperado en el servidor.</p>
                                    <p style="font-size: 12px; color: #666; margin-top: 10px;">Por favor, contacte al administrador del sistema.</p>
                                `,
                                showConfirmButton: true,
                                confirmButtonText: 'Aceptar',
                                confirmButtonColor: '#dc2626'
                            });
                            console.error("Error: Respuesta no es JSON válido:", text);
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de Conexión',
                            html: `
                                <p>No se pudo conectar con el servidor.</p>
                                <p style="font-size: 12px; color: #666; margin-top: 10px;">Verifique su conexión a internet e intente nuevamente.</p>
                            `,
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar',
                            confirmButtonColor: '#dc2626'
                        });
                        console.error("Error de red:", error);
                    });
                }
            });
        }
    </script>

    <!-- Script para el modal de cotización -->
    <script>

        let searchTimeout;  // Variable para debounce
        let cotizacionactiva = false; // Variable para controlar la eliminacion

        // Abrir modal
        document.getElementById('btn-cotizaciones').addEventListener('click', function() {
            document.getElementById('modal-overlay').classList.add('active');
            document.body.style.overflow = 'hidden';
            cargarDatos(''); // Se envian datos vacios para cargar los primeros 10 registros
        });

        // Cerrar modal
        function cerrarModal() {
            document.getElementById('modal-overlay').classList.remove('active');
            document.body.style.overflow = 'auto';
            document.getElementById('search-input-modal').value = '';
        }

        // Cerrar al hacer clic fuera del modal
        document.getElementById('modal-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        // Cerrar con tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });

        // Búsqueda con debounce
        document.getElementById('search-input-modal').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                cargarDatos(e.target.value);
            }, 300);
        });

        // Función para cargar datos
        function cargarDatos(campo) {
            const tbody = document.getElementById('prefactura-body');
            
            // Mostrar loading
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="loading">
                        <div class="spinner"></div>
                        <p>Cargando datos...</p>
                    </td>
                </tr>
            `;

            // Realizar fetch
            const formData = new FormData();
            formData.append('campo', campo);

            fetch('../../api/facturacion/cotizacion-buscador.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                tbody.innerHTML = data;
            })
            .catch(error => {
                console.error('Error:', error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="empty-state">
                            <p>Error al cargar los datos. Por favor, intente nuevamente.</p>
                        </td>
                    </tr>
                `;
            });
        }

        // Función que se llama al seleccionar una cotización
        function seleccionarprefactura(no) {
            if (!no || no.trim() === '' || isNaN(no) || parseInt(no) <= 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Número de cotización no válido.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            // Mostrar loading
            Swal.fire({
                title: 'Cargando cotización...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Hacer fetch para obtener los datos de la cotización
            fetch(`../../api/facturacion/cotizacion-detalle.php?no=${no}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error,
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    return;
                }

                // Validar existencia de productos antes de cargar
                const productosConProblemas = [];

                // console.log('Validando productos...', data.productos);

                data.productos.forEach(producto => {
                    // console.log(`Producto: ${producto.descripcion}, Cantidad: ${producto.cantidad}, Existencia: ${producto.existencia}`);
                    
                    if (producto.cantidad > producto.existencia) {
                        console.log(`Problema detectado con: ${producto.descripcion}`);
                        productosConProblemas.push({
                            descripcion: producto.descripcion,
                            requerida: producto.cantidad,
                            disponible: producto.existencia
                        });
                    }
                });

                // console.log('Productos con problemas:', productosConProblemas);

                // Si hay productos con problemas de existencia, mostrar error y detener
                if (productosConProblemas.length > 0) {
                    const mensajesProductos = productosConProblemas.map(p => 
                        `• ${p.descripcion}: requiere ${p.requerida} pero hay ${p.disponible}`
                    ).join('\n');
                    
                    Swal.fire({
                        icon: 'warning',
                        title: 'Inventario Insuficiente',
                        html: `<div style="text-align: left;">No se puede cargar la cotización. Los siguientes productos no tienen suficiente inventario:<br><br><pre>${mensajesProductos}</pre></div>`,
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    
                    // console.log('Carga de cotización detenida por falta de inventario');
                    return; // ESTO DEBERÍA DETENER LA EJECUCIÓN
                }

                // console.log('Validación pasada, procediendo a cargar cotización...');

                // Cargar datos del cliente
                document.getElementById("id-cliente").value = data.cliente.id;
                document.getElementById("nombre-cliente").value = data.cliente.nombre;
                document.getElementById("empresa").value = data.cliente.empresa;

                // Limpiar carrito actual
                const orderList = document.getElementById('orderList');
                const orderItems = orderList.querySelectorAll('.order-item');
                orderItems.forEach(item => item.remove());
                
                // Reiniciar el array de productos y el total
                productos = [];
                total = 0;
                counter = 0;

                // Agregar productos al carrito
                data.productos.forEach(producto => {
                    const subtotal = producto.precio * producto.cantidad;

                    productos.push({
                        id: producto.id,
                        venta: producto.precio,
                        cantidad: producto.cantidad,
                        precio: producto.precioCompra,
                        subtotal: subtotal,
                        idElimination: counter
                    });

                    const orderItem = document.createElement('div');
                    orderItem.classList.add('order-item');

                    orderItem.innerHTML = `
                        <div class="item-info">
                            <span class="item-name">${producto.descripcion}</span>
                            <span class="item-base-price">RD$${producto.precio.toFixed(2)}</span>
                        </div>
                        <div class="item-total">
                            <span class="item-quantity">x${producto.cantidad}</span>
                            <span class="item-total-price">RD$${subtotal.toFixed(2)}</span>
                        </div>
                        <button class="delete-item" id-producto="${producto.id}" id-elimination="${counter}" onclick="removeFromCart(this, ${subtotal})">&times;</button>
                    `;

                    orderList.appendChild(orderItem);
                    total += subtotal;
                    counter++;
                });

                // Deshabilitar búsqueda de cliente
                document.getElementById('buscar-cliente').disabled = true;
                document.getElementById("buscar-cliente").style.backgroundColor = "#096849ff";
                document.getElementById("buscar-cliente").classList.add("bloqueado");

                // Desabilitar botón de guardar cotización
                document.getElementById('guardar-prefactura').disabled = true;
                document.getElementById("guardar-prefactura").style.backgroundColor = "#6b7280";
                document.getElementById("guardar-prefactura").style.color = "#8b929fff";
                document.getElementById("guardar-prefactura").classList.add("bloqueado");

                // Ocultar mensaje de carrito vacío
                document.getElementById('orderListEmpty').style.display = 'none';

                updateTotal();
                cerrarModal();

                Swal.fire({
                    icon: 'success',
                    title: 'Cotización cargada',
                    text: `Se han cargado ${data.productos.length} productos al carrito.`,
                    timer: 2000,
                    showConfirmButton: false
                });

                orderMenu.classList.add('active');  // Abrir el menú de la factura
                document.getElementById("titulo-factura").textContent = "Cotización N° " + no;  // Cambiar título del menú
                cotizacionactiva = true; // Activar la variable para eliminar luego
                noCotizacion = no; // Guardar el número de cotización para eliminar luego

            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al cargar la cotización. Por favor, intente nuevamente.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
            });
        }
    </script>

    <!-- Funsion para eliminar prefacturas -->
    <script>
        // Función para eliminar la cotización
        function actualizarCotizacion(noCotizacion) {

            const datos = {
                noCotizacion: noCotizacion
            };

            fetch("../../api/facturacion/cotizacion-actualizarEstado.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Cotización actualizada exitosamente:', noCotizacion);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Se guardo correctamente la factura pero no se pudo actualizar el estado de la cotización. Por favor, contacte al administrador del sistema.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.error("Error al actualizar cotización:", data.error);
                }
            })
            .catch(error => {
                console.error('Error en la solicitud de eliminación:', error);
            });
        }
    </script>

</body>
</html>