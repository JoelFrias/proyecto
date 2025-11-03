<?php

/* Verificacion de sesion */

// Iniciar sesión
session_start();

// Configurar el tiempo de caducidad de la sesión
$inactivity_limit = 900; // 15 minutos en segundos

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    session_unset();
    session_destroy();
    header('Location: ../../views/auth/login.php');
    exit();
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset();
    session_destroy();
    header("Location: ../../views/auth/login.php?session_expired=session_expired");
    exit();
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

require_once '../../models/conexion.php';

// Obtener el número de cotización desde el formulario
$noCotizacion = isset($_GET['no']) && !empty($_GET['no']) ? intval($_GET['no']) : null;

if ($noCotizacion === null) {
    header('Location: ../../views/cotizacion/cotizacion-registro.php?error=missing_no');
    exit();
}

// Consulta para obtener información general de la cotización
$sqlInfo = "SELECT
                ci.no AS no,
                DATE_FORMAT(ci.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                CONCAT(c.nombre, ' ', c.apellido) AS nombreCliente,
                c.id AS idCliente,
                c.telefono AS telefono,
                CONCAT(e.nombre, ' ', e.apellido) AS nombreEmpleado,
                ci.notas AS notas,
                ci.estado AS estado
            FROM
                cotizaciones_inf AS ci
            INNER JOIN empleados AS e ON e.id = ci.id_empleado
            INNER JOIN clientes AS c ON c.id = ci.id_cliente
            WHERE ci.no = ?";

$stmtInfo = $conn->prepare($sqlInfo);
$stmtInfo->bind_param("i", $noCotizacion);
$stmtInfo->execute();
$resultInfo = $stmtInfo->get_result();

if ($resultInfo->num_rows === 0) {
    header('Location: ../../views/cotizacion/cotizacion-registro.php?error=not_found');
    exit();
}

$cotizacionInfo = $resultInfo->fetch_assoc();

// Consulta para obtener los productos de la cotización
$sqlProductos = "SELECT
                    p.id AS idProducto,
                    p.descripcion AS descripcionP,
                    cd.cantidad AS cantidad,
                    cd.precio_s AS precio
                FROM
                    cotizaciones_det AS cd
                INNER JOIN productos AS p ON p.id = cd.id_producto
                WHERE cd.no = ?";

$stmtProductos = $conn->prepare($sqlProductos);
$stmtProductos->bind_param("i", $noCotizacion);
$stmtProductos->execute();
$resultProductos = $stmtProductos->get_result();

$productos = [];
$subtotal = 0;

while ($producto = $resultProductos->fetch_assoc()) {
    $importe = $producto['cantidad'] * $producto['precio'];
    $producto['importe'] = $importe;
    $subtotal += $importe;
    $productos[] = $producto;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Detalle Cotización</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
            min-height: 100vh;
            position: relative;
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed ~ .container {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        /* Contenedor de cotización */
        .invoice-container {
            flex: 1;
            max-width: 1024px;
            margin: 0 auto;
            padding: 1rem;
        }

        .invoice-card {
            margin-bottom: 2rem;
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

        /* Tarjeta de cotización */
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

        .status.vencida {
            background: #e6f7ff;
            color: #1890ff;
        }

        .status.paid {
            background: #e6ffec;
            color: #48bb78;
        }

        .status.pending {
            background: #fff5e6;
            color: #ed8936;
        }

        .status.cancel {
            background: rgb(252, 206, 206);
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
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
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
            background: #f3f4f6;
            border-radius: 0.375rem;
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

        /* Resumen de cotización */
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

        .final-total {
            font-weight: 600;
            color: #111827;
        }

        .final-total span {
            font-size: 1rem;
        }

        /* Notas */
        .notes-section {
            background-color: #f9fafb;
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .notes-section label {
            display: block;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .notes-section span {
            display: block;
            color: #444;
            line-height: 1.6;
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

        .btn-cancel {
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

        /* Responsive */
        @media (max-width: 768px) {
            .invoice-container {
                padding: 0.5rem;
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
            .btn-secondary {
                width: 100%;
                min-width: 0;
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
                justify-content: space-between;
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
    </style>

</head>
<body>
    <div class="navegator-nav">
        <?php include '../../views/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="invoice-container">
                    <div class="search-section">
                        <h2>Detalle de Cotización</h2>
                    </div>

                    <div class="invoice-card">
                        <div class="invoice-header">
                            <h1>Cotización #<?php echo $cotizacionInfo['no']; ?></h1>
                            <?php

                                $estado = "";
                                if ($cotizacionInfo['estado'] == 'pendiente') {
                                    $estado = "pending";
                                } elseif ($cotizacionInfo['estado'] == 'vendida') {
                                    $estado = "paid";
                                } elseif ($cotizacionInfo['estado'] == 'cancelada') {
                                    $estado = "cancel";
                                }

                            ?>
                            <span class="status <?php echo $estado; ?>">
                                <?php echo $cotizacionInfo['estado']; ?>
                            </span>
                        </div>

                        <div class="client-info">
                            <div class="info-column">
                                <div class="info-row">
                                    <label>ID Cliente:</label>
                                    <span><?php echo $cotizacionInfo['idCliente']; ?></span>
                                </div>
                                <div class="info-row">
                                    <label>Nombre del Cliente:</label>
                                    <span><?php echo $cotizacionInfo['nombreCliente']; ?></span>
                                </div>
                                <div class="info-row">
                                    <label>Teléfono:</label>
                                    <span><?php echo $cotizacionInfo['telefono']; ?></span>
                                </div>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Fecha y Hora:</label>
                                    <span><?php echo $cotizacionInfo['fecha']; ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Vendedor:</label>
                                    <span><?php echo $cotizacionInfo['nombreEmpleado']; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="search-section">
                            <h2>Productos Cotizados:</h2>
                        </div>

                        <div class="products-section">
                            <?php foreach ($productos as $producto): ?>
                                <div class="product-card">
                                    <div class="product-header">
                                        <span class="product-id">ID - <?php echo $producto['idProducto']; ?></span>
                                        <span class="product-name"><?php echo $producto['descripcionP']; ?></span>
                                    </div>
                                    <div class="product-details">
                                        <div class="detail-item">
                                            <label>Cantidad</label>
                                            <span><?php echo number_format($producto['cantidad'], 0); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Precio</label>
                                            <span>RD$ <?php echo number_format($producto['precio'], 2); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Total</label>
                                            <span>RD$ <?php echo number_format($producto['importe'], 2); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="invoice-summary">
                            <?php if (!empty($cotizacionInfo['notas'])): ?>
                            <div class="notes-section">
                                <label><i class="fa-solid fa-note-sticky"></i> Notas:</label>
                                <span><?php echo nl2br(htmlspecialchars($cotizacionInfo['notas'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="totals">
                                <div class="total-row final-total">
                                    <span>Total:</span>
                                    <span>RD$ <?php echo number_format($subtotal, 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <?php if ($cotizacionInfo['estado'] == 'pendiente'): ?>

                                <button class="btn-secondary" onclick="imprimir()">
                                    <span class="printer-icon">🖨️</span>
                                    Re-Imprimir Reporte
                                </button>
                                <button class="btn-cancel" onclick="cancelCoti()">
                                    Cancelar Cotización
                                </button>

                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button class="btn-volver" onclick="history.back()">← Volver atrás</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php $conn->close(); ?>

    <script>
        function imprimir() {
            const cotizacionUrl = `../../pdf/factura/recotizacion.php?cotizacion=<?php echo $noCotizacion; ?>`;
            window.open(cotizacionUrl, '_blank');
        }

        function navigateTo(url) {
            window.location.href = url;
        }

        // Funcion para cancelar cotizacion
        function cancelCoti() {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, cancelar',
                cancelButtonText: 'No, mantener'
            }).then((result) => {
                if (result.isConfirmed) {

                    // Datos a enviar
                    const datos = {
                        noCotizacion: <?php echo $noCotizacion ?>
                    };

                    fetch("../../controllers/facturacion/cotizacion-cancelar.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(datos)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Cotización cancelada exitosamente:', datos.noCotizacion);
                            Swal.fire({
                                icon: 'success',
                                title: 'Éxito',
                                text: 'Cotización cancelada exitosamente',
                                showConfirmButton: true,
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'No se pudo cancelar la cotización. Por favor, contacte al administrador del sistema.',
                                showConfirmButton: true,
                                confirmButtonText: 'Aceptar'
                            });
                            console.error("Error al cancelar cotización:", data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error en la solicitud de cancelación:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'Ocurrió un error al intentar cancelar la cotización.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });
                    });
                }
            });
        }
    </script>
</body>
</html>