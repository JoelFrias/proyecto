<?php

require_once '../../../core/conexion.php';		// Conexión a la base de datos

// Verificar conexión a la base de datos
if (!$conn || !$conn->connect_errno === 0) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos",
        "error_code" => "DATABASE_CONNECTION_ERROR"
    ]));
} // Conexión a la base de datos
require_once '../../../core/verificar-sesion.php'; // Verificar Session


// Obtener el número de factura y el estado desde el formulario (si existen)
$numFactura = isset($_GET['numFactura']) && !empty($_GET['numFactura']) ? intval($_GET['numFactura']) : null;
$estado = isset($_GET['estado']) ? $_GET['estado'] : 'todas'; // Estado por defecto: todas

if ($numFactura === null) {
    header('Location: ../../../app/factura/factura-registro.php?error=missing_numFactura');
    exit();
}

// Construir la consulta SQL según los filtros
$sql = "SELECT 
            f.numFactura, 
            f.tipoFactura, 
            DATE_FORMAT(f.fecha, '%d/%m/%Y %l:%i %p') AS fecha, 
            f.importe, 
            f.descuento, 
            f.total, 
            f.total_ajuste, 
            f.balance, 
            f.estado,
            CONCAT(e.nombre, ' ', e.apellido) AS NombreEmpleado,
            c.id AS idCliente, 
            CONCAT(c.nombre, ' ', c.apellido) AS NombreCliente, 
            c.telefono,
            fm.metodo AS metodofm,
            fm.monto AS montofm,
            fm.numAutorizacion AS noautofm,
            fm.referencia AS refm,
            b.nombreBanco AS bancofm,
            dc.descripcion AS destinofm
        FROM facturas AS f
        LEFT JOIN empleados AS e ON f.idEmpleado = e.id
        LEFT JOIN clientes AS c ON f.idCliente = c.id
        LEFT JOIN facturas_metodopago AS fm ON f.numFactura = fm.numFactura
        LEFT JOIN bancos AS b ON b.id = fm.idBanco
        LEFT JOIN destinocuentas AS dc ON dc.id = fm.idDestino
        WHERE 1=1";

$params = [];
$types = "";

// Filtrar por número de factura si se ha ingresado
if (!empty($numFactura)) {
    $sql .= " AND f.numFactura = ?";
    $params[] = $numFactura;
    $types .= "i"; // 'i' para enteros
}

$sql .= " ORDER BY f.numFactura DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Verificar si hay resultados
if ($result->num_rows > 0) {
    $facturas = $result->fetch_all(MYSQLI_ASSOC);

    // Pasar numeros de factura a moneda
    foreach ($facturas as &$factura) {
        $factura['importe'] = "RD$ " . number_format($factura['importe']);
        $factura['descuento'] = "RD$ " . number_format($factura['descuento']);
        $factura['total_ajuste'] = "RD$ " . number_format($factura['total_ajuste']);
        $factura['total'] = "RD$ " . number_format($factura['total']);
        $factura['balance'] = "RD$ " . number_format($factura['balance']);
        $factura['montofm'] = "RD$ " . number_format($factura['montofm']);
    }



} else {
    header('Location: ../../../app/factura/factura-registro.php?error=missing_numFactura');
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Detalle Factura</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
    
    <style>
        /* Variables globales */
        :root {
            --sidebar-collapsed-width: 60px;
            --header-height: 60px;
            --primary-color: #2c3e50;
            --secondary-color: #2c3e50;
            --text-color: #ecf0f1;
            --text-primary: #1f2937;
        }

        /* Estilos base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: #f5f6fa;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Contenedor principal */
        .container {
            display: flex;
            min-height: 100vh; /* para evitar el desborde hacia abajo*/
            position: relative;
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed ~ .container {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        /* Contenedor de factura */
        .invoice-container {
            flex: 1;
            margin: 0 auto;
            padding: 1rem;
        }

        .invoice-card {
            margin-bottom: 2rem; /* Espacio entre cada tarjeta de factura */
        }
        /* Sección de búsqueda */
        .search-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .search-section h2 {
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .search-box {
            display: flex;
            gap: 0.5rem;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            flex: 1;
            padding: 0.625rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            outline: none;
            font-size: 0.875rem;
        }

        .search-box button {
            padding: 0.625rem 1.25rem;
            background-color: #3b82f6;
            color: white;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Tarjeta de factura */
        .invoice-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .invoice-header h1 {
            font-size: 1.5rem;
            color: #1f2937;
            font-weight: 600;
        }

        .status {
            padding: 0.375rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-block;
        }

        /* Verde para facturas pagadas */
        .status.pagada {
            background: #e6ffec;
            color: #48bb78;
        }

        /* Rojo para facturas pendientes */
        .status.pendiente {
            background: #fff5e6;
            color: #ed8936;
        }

        .status.cancelada {
            background:rgb(252, 206, 206);
            color: rgb(252, 85, 85);
        }

        /* Información del cliente */
        .client-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .info-column {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }

        .info-row label {
            color: #4b5563;
            font-size: 0.875rem;
        }

        .info-row span {
            color: #111827;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        .info-item label {
            color: #4b5563;
            font-size: 0.875rem;
        }

        .info-item span {
            color: #111827;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .amount-due {
            color: #dc2626;
        }

        /* Sección de productos */
        .products-section {
            margin: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 0;
        }

        .product-card {
            background: #f6f7f7;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .product-header {
            display: flex;
            justify-content: flex-start; 
            align-items: center;
            margin-bottom: 1rem;
            gap: 1rem; 
        }

        .product-name {
            font-weight: 500;
            font-size: 1rem;
            color: #111827;
        }

        .product-id {
            color: #6b7280;
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            background: #e5e7eb;
            border-radius: 0.375rem;
            white-space: nowrap;
        }

        .product-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        .detail-item label {
            color: #4b5563;
            font-size: 0.75rem;
        }

        .detail-item span {
            color: #111827;
            font-size: 0.875rem;
            font-weight: 500;
        }

        /* Resumen de factura */
        .invoice-summary {
            display: grid;
            grid-template-columns: 2fr auto;
            align-items: start;
        }

        .totals {
            width: 300px;
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 0.5rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: #4b5563;
        }

        .total-row span:last-child {
            color: #111827;
            font-weight: 500;
            text-align: right;
        }

        .discount {
            color: #dc2626;
        }

        .final-total {
            border-top: 1px solid #e5e7eb;
            padding-top: 1rem;
            margin-top: 1rem;
            font-weight: 600;
            color: #111827;
        }

        .final-total span {
            font-size: 1rem;
        }

        /* Botones de acción */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-primary {
            padding: 0.75rem 1.5rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            min-width: 150px;
        }

        .btn-secondary {
            padding: 0.75rem 1.5rem;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            min-width: 150px;
        }

        .btn-secondary-cancel {
            padding: 0.75rem 1.5rem;
            background-color: rgb(211, 80, 80);
            border: 1px rgb(160, 33, 33);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: rgb(251, 251, 251);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            min-width: 150px;
        }

        /* Overlay para dispositivos móviles */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .overlay.active {
            opacity: 1;
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {

            .invoice-container {
                padding: 0.5rem;
            }

            .search-section {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }

            .invoice-card {
                padding: 1rem;
            }

            .client-info {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .products-section {
                margin: 1rem 0;
                padding: 1rem 0;
            }

            .invoice-summary {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .totals {
                width: 100%;
                order: 1;
            }

            .action-buttons {
                flex-direction: column;
                order: 2;
            }

            .btn-primary,
            .btn-secondary,
            .btn-secondary-cancel {
                width: 100%;
                min-width: 0;
            }

            #mobileToggle {
                display: flex;
            }
        }
        @media (max-width: 390px) {
            .products-section {
                width: 100%;
                padding: 1rem;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .product-card {
                width: 100%;
                background: #f8f9fa;
                border-radius: 8px;
                padding: 10px;
                box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .product-header {
                display: flex;
                justify-content: flex-start;
                align-items: center;
                font-size: 14px;
                font-weight: bold;
            }

            .product-details {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                width: 100%;
                text-align: left;
                gap: 5px;
            }

            .product-details div {
                font-size: 14px;
            }
        }

        /* Estilos específicos para el selector de filtros */
        .search-box select {
            padding: 8px 12px; /* Espaciado interno */
            border: 1px solid #ccc; /* Borde sutil */
            border-radius: 5px; /* Esquinas redondeadas */
            font-size: 14px; /* Tamaño de fuente */
            background-color: #f9f9f9; /* Fondo claro */
            color: #333; /* Color del texto */
            cursor: pointer; /* Cursor tipo pointer */
            transition: border-color 0.3s ease, box-shadow 0.3s ease; /* Transición suave */
        }

        /* Efecto al pasar el mouse sobre el selector */
        .search-box select:hover {
            border-color: #007bff; /* Borde azul al hacer hover */
        }

        /* Efecto al enfocar el selector */
        .search-box select:focus {
            border-color: #007bff; /* Borde azul al hacer focus */
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5); /* Sombra suave */
            outline: none; /* Eliminar el outline predeterminado */
        }

        /* Estilo para las opciones del selector */
        .search-box select option {
            padding: 8px 12px; /* Espaciado interno */
            background-color: #fff; /* Fondo blanco */
            color: #333; /* Color del texto */
        }

        /* Efecto al pasar el mouse sobre las opciones */
        .search-box select option:hover {
            background-color: #007bff; /* Fondo azul al hacer hover */
            color: #fff; /* Texto blanco al hacer hover */
        }

        /* Estilos del menu con swal */
        .swal-responsive-container {
            padding: 10px;
        }
        
        .swal-responsive-popup {
            max-width: 90% !important;
            width: auto !important;
            font-size: 14px !important;
        }
        
        .swal-responsive-content {
            padding: 10px !important;
        }

        #motivo-cancelacion{
            width: 450px;
            height: 80px;
            min-height: 80px;
            padding: 8px;
            border: 1px solid #d9d9d9;
            border-radius: 4px;
            resize: none;
            box-sizing: border-box; 
            margin: auto;
        }
        
        @media (max-width: 500px) {
            .swal2-popup {
                padding: 1em !important;
                font-size: 0.9em !important;
            }
            
            .swal2-title {
                font-size: 1.3em !important;
            }
            
            .swal2-content {
                font-size: 1em !important;
            }
            
            .swal2-textarea {
                font-size: 1em !important;
            }
            #motivo-cancelacion{
                width: 250px;
                padding: 0;
            }
        }

        .note-cancelation {
            background-color: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
        }

        .note-cancelation label {
            display: block;
            font-weight: 600;
            color: #991b1b;
            margin-top: 0.5rem;
        }

        .note-cancelation span {
            display: block;
            margin-left: 0.5rem;
            color: #444;
        }

        @media (max-width: 600px) {
            .note-cancelation {
                font-size: 0.9rem;
                padding: 0.75rem;
            }
        }

        /* boton volver */
        .btn-volver {
            background-color: #f5f5f5;
            border: 1px solid #ccc;
            color: #333;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s, box-shadow 0.2s;
        }

        .btn-volver:hover {
            background-color: #e0e0e0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-volver:active {
            background-color: #d5d5d5;
        }
            
    </style>

</head>
<body>

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../../app/views/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

            <div class="container">
                <div class="invoice-container">
                    <div class="search-section">
                        <h2>Detalle de Factura</h2>
                    </div>
                    <?php if (isset($mensaje)): ?>
                        <!-- Mostrar mensaje si no se encontraron facturas -->
                        <p><?php echo $mensaje; ?></p>
                    <?php elseif (!empty($facturas)): ?>
                        <!-- Mostrar todas las facturas que coinciden con el filtro -->
                        <?php foreach ($facturas as $facturaInfo): ?>
                            <div class="invoice-card">
                                <div class="invoice-header">
                                    <h1>Factura #<?php echo $facturaInfo['numFactura']; ?></h1>
                                    <span class="status <?php echo strtolower($facturaInfo['estado']); ?>">
                                        <?php echo $facturaInfo['estado']; ?>
                                    </span>
                                </div>
                                <div class="client-info">
                                    <div class="info-column">
                                        <div class="info-row">
                                            <label>ID Cliente</label>
                                            <span><?php echo $facturaInfo['idCliente']; ?></span>
                                        </div>
                                        <div class="info-row">
                                            <label>Nombre del Cliente:</label>
                                            <span><?php echo $facturaInfo['NombreCliente']; ?></span>
                                        </div>
                                        <div class="info-row">
                                            <label>Teléfono:</label>
                                            <span><?php echo $facturaInfo['telefono']; ?></span>
                                        </div>
                                    </div>
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <label>Fecha y Hora:</label>
                                            <span><?php echo $facturaInfo['fecha']; ?></span>
                                        </div>
                                        <div class="info-item">
                                            <label>Tipo de Factura:</label>
                                            <span><?php echo $facturaInfo['tipoFactura']; ?></span>
                                        </div>
                                        <div class="info-item">
                                            <label>Vendedor:</label>
                                            <span><?php echo $facturaInfo['NombreEmpleado']; ?></span>
                                        </div>
                                        <div class="info-item">
                                            <label>Monto Adeudado:</label>
                                            <span class="amount-due"><?php echo $facturaInfo['balance']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            
                                <div class="search-section">
                                    <h2>Productos Facturados:</h2>
                                </div>

                                <!-- Mostrar detalles de la factura -->
                                <?php
                                // Consulta para obtener los detalles de la factura actual
                                $sqlDetalles = "
                                SELECT 
                                    p.id AS idProducto, 
                                    p.descripcion AS Producto, 
                                    fd.cantidad, 
                                    fd.precioVenta, 
                                    fd.importe AS importeProducto
                                FROM facturas_detalles AS fd
                                LEFT JOIN productos AS p ON fd.idProducto = p.id
                                WHERE fd.numFactura = {$facturaInfo['numFactura']}";

                                $resultDetalles = $conn->query($sqlDetalles);

                                

                                if ($resultDetalles->num_rows > 0) {
                                    echo "<div class='products-section'>";
                                    while ($detalle = $resultDetalles->fetch_assoc()) {

                                        // Formatear los números a moneda
                                        $detalle['importeProducto'] = "RD$ " . number_format($detalle['importeProducto']);
                                        $detalle['precioVenta'] = "RD$ " . number_format($detalle['precioVenta']);
                                        $detalle['cantidad'] = number_format($detalle['cantidad'], 0, '.', '');

                                        echo "<div class='product-card'>
                                                <div class='product-header'>
                                                    <span class='product-id'>ID - {$detalle['idProducto']}</span>
                                                    <span class='product-name'>{$detalle['Producto']}</span>
                                                </div>
                                                <div class='product-details'>
                                                    <div class='detail-item'>
                                                        <label>Cantidad</label>
                                                        <span>{$detalle['cantidad']}</span>
                                                    </div>
                                                    <div class='detail-item'>
                                                        <label>Precio</label>
                                                        <span>{$detalle['precioVenta']}</span>
                                                    </div>
                                                    <div class='detail-item'>
                                                        <label>Total</label>
                                                        <span>{$detalle['importeProducto']}</span>
                                                    </div>
                                                </div>
                                            </div>";
                                    }
                                    echo "</div>";
                                }
                                ?>
                                <div class="invoice-summary">
                                    <div class="totals">
                                        <div class="total-row">
                                            <span>Método:</span>
                                            <span><?php echo $factura['metodofm']; ?></span>
                                        </div>
                                        <div class="total-row">
                                            <span>Monto:</span>
                                            <span><?php echo $factura['montofm']; ?></span>
                                        </div>
                                        <div class="total-row">
                                            <span>No. Autorización:</span>
                                            <span>#<?php echo $factura['noautofm']; ?></span>
                                        </div>
                                        <div class="total-row">
                                            <span>No. Tarjeta:</span>
                                            <span>#<?php echo $factura['refm']; ?></span>
                                        </div>
                                        <div class="total-row">
                                            <span>Banco:</span>
                                            <span><?php echo $factura['bancofm']; ?></span>
                                        </div>
                                        <div class="total-row">
                                            <span>Destino:</span>
                                            <span><?php echo $factura['destinofm']; ?></span>
                                        </div>
                                    </div>
                                    <div class="totals">
                                        <div class="total-row">
                                            <span>Subtotal</span>
                                            <span><?php echo $facturaInfo['importe']; ?></span>
                                        </div>
                                        <div class="total-row">
                                            <span>ITBIS Total</span>
                                            <span>RD$ 0</span> <!--no se cobra itbis-->
                                        </div>
                                        <div class="total-row">
                                            <span>Descuento</span>
                                            <span class="discount"><?php echo $facturaInfo['descuento']; ?></span>
                                        </div>
                                        <div class="total-row">
                                            <span>Total a Pagar:</span>
                                            <span><?php echo $facturaInfo['total_ajuste']; ?></span>
                                        </div>
                                        <div class="total-row final-total">
                                            <span>Total:</span>
                                            <span><?php echo $facturaInfo['total']; ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="action-buttons">

                                    <?php if ($factura['estado'] != "Cancelada"): ?>

                                    <button class="btn-secondary" onclick="reimprimir()">
                                        <span class="printer-icon">🖨️</span>
                                        Reimprimir
                                    </button>

                                    <?php endif ?>

                                    <?php

                                        // Validar permisos y estado de factura
                                        require_once '../../../core/validar-permisos.php';
                                        $permiso_necesario = 'FAC002';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado) && $factura['estado'] !== "Cancelada"):

                                    ?>

                                        <button class="btn-secondary-cancel" id="cancel-btn">
                                            <spa class="printer-icon"><i class="fa-solid fa-ban">  </i></span>
                                            Cancelar Factura
                                        </button>

                                    <?php endif ?>

                                    <?php

                                        // Validar permisos
                                        require_once '../../../core/validar-permisos.php';
                                        $permiso_necesario = 'CLI003';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                    ?>

                                        <button class="btn-primary" onclick="navigateTo('../../../app/clientes/cuenta-avance.php?idCliente=<?php echo $facturaInfo['idCliente']; ?>')"><i class="fa-solid fa-money-check-dollar"></i> Avance a cuenta del cliente</button>

                                    <?php endif; ?>
                                    
                                </div>

                                <div class="details-cancelation">

                                    <?php
                                    if ($factura['estado'] == "Cancelada" && $_SESSION['idPuesto'] <= 2):

                                        $sqlfc = "SELECT
                                                    fc.motivo AS motivofc,
                                                    DATE_FORMAT(fc.fecha, '%d/%m/%Y %l:%i %p') AS fechafc,
                                                    CONCAT(e.nombre, ' ', e.apellido) AS empleadofc
                                                FROM
                                                    facturas_cancelaciones AS fc
                                                JOIN empleados AS e
                                                    ON e.id = fc.idEmpleado
                                                WHERE
                                                    fc.numFactura = ?";

                                        $stmtfc = $conn->prepare($sqlfc);
                                        $stmtfc->bind_param("s", $factura['numFactura']);
                                        $stmtfc->execute();
                                        $resultfc = $stmtfc->get_result();

                                        if ($resultfc->num_rows > 0):
                                            $datosfc = $resultfc->fetch_assoc();
                                    ?>
                                            <div class="note-cancelation">
                                                <label for="motivo-cancelation"><i class="fa-solid fa-circle-info"></i> Motivo de Cancelación:</label>
                                                <span id="motivo-cancelation"><?php echo $datosfc['motivofc']; ?></span>

                                                <label for="fechafc">Fecha de Cancelación:</label>
                                                <span id="fechafc"><?php echo $datosfc['fechafc']; ?></span>

                                                <label for="empleadofc">Empleado:</label>
                                                <span id="empleadofc"><?php echo $datosfc['empleadofc']; ?></span>
                                            </div>
                                    <?php
                                        endif;

                                    endif;
                                    ?>

                                </div>
                            </div>
                            <button class="btn-volver" onclick="history.back()">← Volver atrás</button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA ENCIMA DE ESTA LINEA -->
        </div>
    </div>
    
    <?php
        // Cerrar conexión
        $conn->close();
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cancelBtn = document.getElementById('cancel-btn');
            
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    const numFactura = <?php echo $facturaInfo['numFactura']; ?>;
                    mostrarDialogoCancelacion(numFactura);
                });
            }
        });

        /**
         * Muestra el diálogo inicial de cancelación
         */
        function mostrarDialogoCancelacion(numFactura) {
            Swal.fire({
                title: '¿Cancelar Factura?',
                text: `¿Está seguro que desea cancelar la factura #${numFactura}? Esta acción no se puede deshacer.`,
                icon: 'warning',
                html: `
                    <div class="form-group" style="margin-top: 15px;">
                        <label for="motivo-cancelacion" style="display: block; text-align: left; margin-bottom: 8px; font-weight: 500;">Motivo de cancelación:</label>
                        <textarea id="motivo-cancelacion" class="swal2-textarea" placeholder="Ingrese el motivo de la cancelación"></textarea>
                    </div>
                    <div class="alert alert-info" style="margin-top: 10px; font-size: 0.9rem; text-align: center;">
                        <i class="fas fa-info-circle"></i> La cancelación está disponible hasta 3 días después de efectuada.
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Sí, cancelar',
                confirmButtonColor: '#d33',
                cancelButtonText: 'Volver',
                cancelButtonColor: '#3085d6',
                width: 'auto',
                customClass: {
                    container: 'swal-responsive-container',
                    popup: 'swal-responsive-popup',
                    content: 'swal-responsive-content'
                },
                preConfirm: () => {
                    const motivoCancelacion = document.getElementById('motivo-cancelacion').value.trim();
                    
                    if (!motivoCancelacion) {
                        Swal.showValidationMessage('Debe ingresar un motivo para cancelar la factura');
                        return false;
                    }
                    
                    return motivoCancelacion;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    procesarCancelacionFactura(numFactura, result.value);
                }
            });
        }

        /**
         * Procesa la cancelación de la factura
         */
        function procesarCancelacionFactura(numFactura, motivo, cajaAlternativa = null) {
            const cancelBtn = document.getElementById('cancel-btn');
            
            // Mostrar loader
            Swal.fire({
                title: 'Procesando cancelación...',
                html: '<i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: #007bff;"></i><br><br>Por favor espere',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Deshabilitar el botón
            if (cancelBtn) {
                cancelBtn.disabled = true;
                const btnTextoOriginal = cancelBtn.innerHTML;
                cancelBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
            }
            
            // Crear el objeto FormData
            const formData = new FormData();
            formData.append('numFactura', numFactura);
            formData.append('motivo', motivo);
            
            if (cajaAlternativa) {
                formData.append('caja_alternativa', cajaAlternativa);
            }
            
            // Llamada fetch al backend
            fetch('../../../api/facturacion/cancelar-factura.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // Restaurar el botón
                if (cancelBtn) {
                    cancelBtn.disabled = false;
                    cancelBtn.innerHTML = '<span class="printer-icon"><i class="fa-solid fa-ban"></i></span> Cancelar Factura';
                }
                
                if (data.success) {
                    // Mensaje de éxito
                    let mensajeAdicional = '';
                    if (data.data && data.data.caja_alternativa_usada) {
                        mensajeAdicional = `
                            <div style="background: #fff3cd; padding: 0.75rem; border-radius: 0.25rem; margin-top: 0.5rem; border-left: 4px solid #ffc107;">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Nota:</strong> La caja original (${data.data.caja_original}) estaba cerrada. 
                                El egreso se realizó en la caja ${data.data.caja_utilizada}
                            </div>
                        `;
                    }
                    
                    let mensaje = `Factura #${numFactura} cancelada correctamente.`;
                    if (data.data && data.data.requirio_devolucion) {
                        mensaje += ' Se ha registrado un egreso por el pago inicial de la factura.';
                    }
                    
                    Swal.fire({
                        title: '¡Completado!',
                        html: `
                            <div style="text-align: left; background: #d4edda; padding: 1rem; border-radius: 0.25rem; margin-top: 1rem;">
                                <p style="margin: 0.5rem 0;"><strong>✓ ${mensaje}</strong></p>
                                ${data.data && data.data.requirio_devolucion ? `
                                    <p style="margin: 0.5rem 0;"><i class="fas fa-money-bill-wave"></i> Monto devuelto: $${parseFloat(data.data.monto || 0).toFixed(2)}</p>
                                    <p style="margin: 0.5rem 0;"><i class="fas fa-cash-register"></i> Caja: ${data.data.caja_utilizada || 'N/A'}</p>
                                ` : ''}
                            </div>
                            ${mensajeAdicional}
                        `,
                        icon: 'success',
                        confirmButtonColor: '#28a745',
                        confirmButtonText: '<i class="fas fa-check"></i> Aceptar'
                    }).then(() => {
                        location.reload();
                    });
                } else if (data.requires_cash_selection) {
                    // La caja original está cerrada, mostrar selector
                    mostrarSelectorCajasFactura(numFactura, motivo, data.cajas_disponibles);
                } else {
                    // Error específico
                    if (data.message && data.message.includes('No se puede cancelar la factura')) {
                        Swal.fire({
                            title: 'Tiempo excedido',
                            html: `<div style="text-align: left;">${data.message}</div>`,
                            icon: 'error',
                            confirmButtonText: 'Entendido'
                        });
                    } else {
                        throw new Error(data.message || 'Error al cancelar la factura');
                    }
                }
            })
            .catch(error => {
                // Restaurar el botón en caso de error
                if (cancelBtn) {
                    cancelBtn.disabled = false;
                    cancelBtn.innerHTML = '<span class="printer-icon"><i class="fa-solid fa-ban"></i></span> Cancelar Factura';
                }
                
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'Ha ocurrido un error al procesar la solicitud.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            });
        }

        /**
         * Muestra un selector de cajas abiertas disponibles
         */
        function mostrarSelectorCajasFactura(numFactura, motivo, cajas) {
            let opcionesCajas = cajas.map(caja => 
                `<option value="${caja.numCaja}">Caja ${caja.numCaja} - ${caja.empleado}</option>`
            ).join('');
            
            Swal.fire({
                title: 'Seleccione una Caja',
                html: `
                    <p style="margin-bottom: 1rem; color: #856404; background: #fff3cd; padding: 0.75rem; border-radius: 0.25rem; border-left: 4px solid #ffc107;">
                        <i class="fas fa-info-circle"></i> 
                        La caja donde se realizó la factura original está cerrada. 
                        <br>Seleccione una caja abierta para registrar el egreso de devolución.
                    </p>
                    <div style="text-align: left; margin-top: 1rem;">
                        <label for="swal-caja" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">
                            Caja para el egreso <span style="color: #dc3545;">*</span>
                        </label>
                        <select 
                            id="swal-caja" 
                            class="swal2-select" 
                            style="width: 100%; padding: 0.5rem; font-size: 0.95rem; border: 2px solid #e3e6f0; border-radius: 0.25rem;"
                        >
                            <option value="" disabled selected>Seleccione una caja</option>
                            ${opcionesCajas}
                        </select>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#007bff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check"></i> Continuar',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                focusConfirm: false,
                width: '600px',
                customClass: {
                    popup: 'swal-custom-popup',
                    confirmButton: 'swal-custom-confirm',
                    cancelButton: 'swal-custom-cancel'
                },
                preConfirm: () => {
                    const cajaSeleccionada = document.getElementById('swal-caja').value;
                    
                    if (!cajaSeleccionada) {
                        Swal.showValidationMessage('Debe seleccionar una caja');
                        return false;
                    }
                    
                    return cajaSeleccionada;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    // Reintentar la cancelación con la caja seleccionada
                    procesarCancelacionFactura(numFactura, motivo, result.value);
                }
            });
        }

        function reimprimir(){
            const invoiceUrl = `../../reports/factura/refactura.php?factura=` + <?= $numFactura ?>;
            window.open(invoiceUrl, '_blank');
        }
    </script>
</body>
</html>
