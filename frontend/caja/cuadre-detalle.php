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
    header('Location: frontend/auth/login.php'); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header("Location: frontend/auth/login.php?session_expired=session_expired"); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

// Configuración de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conexión a la base de datos
require_once '../../core/conexion.php';

    ////////////////////////////////////////////////////////////////////
    ///////////////////// VALIDACION DE PERMISOS ///////////////////////
    ////////////////////////////////////////////////////////////////////

    require_once '../../core/validar-permisos.php';
    $permiso_necesario = 'CUA001';
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

// 1. Validación segura del parámetro numCaja
$numCaja = '';
if (isset($_GET['numCaja'])) {
    // Validar que sea exactamente 5 caracteres alfanuméricos (ej: 00003)
    if (preg_match('/^[a-zA-Z0-9]{5}$/', $_GET['numCaja'])) {
        $numCaja = $_GET['numCaja'];
    } else {
        die("Formato de número de caja inválido");
    }
}

// Obtener información de la caja
$sql_caja = "SELECT
                ce.numCaja AS numCaja,
                e.id AS idEmpleado,
                CONCAT(e.nombre,' ',e.apellido) AS nombreEmpleado,
                DATE_FORMAT(ce.fechaApertura, '%d/%m/%Y %l:%i %p') AS fechaApertura,
                DATE_FORMAT(ce.fechaCierre, '%d/%m/%Y %l:%i %p') AS fechaCierre,
                ce.saldoInicial AS saldoInicial,
                ce.saldoFinal AS saldoFinal,
                ce.diferencia AS diferencia
            FROM
                cajascerradas ce
            JOIN empleados e
            ON
                e.id = ce.idEmpleado
            WHERE
                ce.numCaja = ?;";
$stmt = $conn->prepare($sql_caja);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_caja = $stmt->get_result();
if ($result_caja->num_rows > 0) {
    $row_caja = $result_caja->fetch_assoc();
} else {
    die("No se encontró información para la caja especificada.");
}

// Obtener total de ingresos y egresos
$sql_ingresos = "WITH
                    fm AS (
                        SELECT metodo, monto FROM facturas_metodopago JOIN facturas ON facturas.numFactura = facturas_metodopago.numFactura WHERE facturas_metodopago.noCaja = ? AND facturas.estado != 'Cancelada'
                    ),
                    ch AS (
                        SELECT metodo, monto FROM clientes_historialpagos WHERE numCaja = ?
                    )
                    SELECT
                        COALESCE((SELECT SUM(monto) FROM fm WHERE metodo = 'efectivo'), 0) AS Fefectivo,
                        COALESCE((SELECT SUM(monto) FROM fm WHERE metodo = 'transferencia'), 0) AS Ftransferencia,
                        COALESCE((SELECT SUM(monto) FROM fm WHERE metodo = 'tarjeta'), 0) AS Ftarjeta,

                        COALESCE((SELECT SUM(monto) FROM ch WHERE metodo = 'efectivo'), 0) AS CPefectivo,
                        COALESCE((SELECT SUM(monto) FROM ch WHERE metodo = 'transferencia'), 0) AS CPtransferencia,
                        COALESCE((SELECT SUM(monto) FROM ch WHERE metodo = 'tarjeta'), 0) AS CPtarjeta;";
$stmt = $conn->prepare($sql_ingresos);
$stmt->bind_param("ss", $numCaja, $numCaja);
$stmt->execute();
$result_ingresos = $stmt->get_result();
$row = $result_ingresos->fetch_assoc();

// Obtener total de ingresos
$ItotalE = $row['Fefectivo'] + $row['CPefectivo'];
$ItotalT = $row['Ftransferencia'] + $row['CPtransferencia'];
$ItotalC = $row['Ftarjeta'] + $row['CPtarjeta'];

$sql_FacturasContado = "SELECT
                            f.numFactura AS noFac,
                            DATE_FORMAT(f.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                            CONCAT(c.nombre,' ',c.apellido) AS nombrec,
                            fm.metodo,
                            fm.monto
                        FROM
                            facturas f
                        JOIN clientes c
                        ON 
                            c.id = f.idCliente
                        JOIN facturas_metodopago fm
                        ON
                            fm.numFactura = f.numFactura AND fm.noCaja = ?
                        WHERE
                            f.tipoFactura = 'contado'
                        AND f.estado != 'Cancelada';";
$stmt = $conn->prepare($sql_FacturasContado);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_FacturasContado = $stmt->get_result();

// Obtener total de facturas a contado
$sql_FacturasCredito = "SELECT
                            f.numFactura AS noFac,
                            DATE_FORMAT(f.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                            CONCAT(c.nombre,' ',c.apellido) AS nombrec,
                            fm.metodo,
                            fm.monto
                        FROM
                            facturas f
                        JOIN clientes c
                        ON 
                            c.id = f.idCliente
                        JOIN facturas_metodopago fm
                        ON
                            fm.numFactura = f.numFactura AND fm.noCaja = ?
                        WHERE
                            f.tipoFactura = 'credito'
                        AND f.estado != 'Cancelada';";
$stmt = $conn->prepare($sql_FacturasCredito);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_FacturasCredito = $stmt->get_result();

// Variables para totales de facturas a contado
$totalEfectivo = 0;
$totalTransferencia = 0;
$totalTarjeta = 0;

// Variables para totales de facturas a credito
$totalEfectivoCredito = 0;
$totalTransferenciaCredito = 0;
$totalTarjetaCredito = 0;

// Obtener pagos de clientes
$sql_pagos = "SELECT
                ch.registro AS id, 
                DATE_FORMAT(ch.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                CONCAT(c.nombre,' ',c.apellido) AS nombre,
                ch.metodo AS metodo,
                ch.monto AS monto
            FROM
                clientes_historialpagos ch
            JOIN clientes c
            ON
                c.id = ch.idCliente
            WHERE
                ch.numCaja = ?;";
$stmt = $conn->prepare($sql_pagos);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_pagos = $stmt->get_result();

// Variables para totales de pagos
$totalEfectivoPagos = 0;
$totalTransferenciaPagos = 0;
$totalTarjetaPagos = 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Cuadre de Caja #<?= $row_caja['numCaja'] ?></title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --success: #27ae60;
            --danger: #e74c3c;
            --border: #eaeaea;
            --background: #f5f6fa;
            --text: #2d3436;
            --gray: #636e72;
            --light-gray: #f1f2f6;
            --shadow: 0 2px 4px rgba(0,0,0,0.05);
            --card-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--background);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .contenedor {
            padding: 0 1.5rem;
            max-width: 1280px;
            margin: 0 auto;
        }

        /* Header */
        .cabecera {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .cabecera h1 {
            font-size: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }

        .acciones {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn:hover {
            opacity: 0.9;
        }

        /* Card layout */
        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.25rem;
            background-color: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        /* Resumen info */
        .info-resumen {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            background: white;
            padding: 1.25rem;
            border-radius: 0.5rem;
            box-shadow: var(--card-shadow);
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .info-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }

        .positive {
            color: var(--success);
        }

        .negative {
            color: var(--danger);
        }

        /* Tablas */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        table thead th {
            background-color: var(--light-gray);
            color: var(--primary);
            font-weight: 600;
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        table tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        .table-footer {
            background-color: var(--light-gray);
            padding: 0.75rem 1rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid var(--border);
        }

        /* Responsive */
        @media (max-width: 991px) {
            .info-resumen {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .info-resumen {
                grid-template-columns: 1fr;
            }

            .cabecera {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .acciones {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="navegator-nav">
        <!-- Menu -->
        <?php include '../../frontend/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="contenedor">
                <!-- Cabecera -->
                <div class="cabecera">
                    <h1><i class="fas fa-cash-register"></i> Detalle de Cuadre de Caja</h1>
                    <div class="acciones">
                        <button class="btn btn-outline" onclick="history.back()">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                        <button class="btn" onclick="imprimirReporte()">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>

                <!-- Información General -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-info-circle"></i> Información General</h2>
                        <span class="badge-cuadre">Caja #<?= $row_caja['numCaja'] ?></span>
                    </div>
                    <div class="card-body">
                        <div class="info-general">
                            <div class="info-row">
                                <div><strong>ID Empleado: </strong><?= $row_caja['idEmpleado'] ?></div>
                                <div><strong>Empleado: </strong><?= $row_caja['nombreEmpleado'] ?></div>
                                <div><strong>Fecha Apertura: </strong><?= $row_caja['fechaApertura'] ?></div>
                                <div><strong>Fecha Cierre: </strong><?= $row_caja['fechaCierre'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen de Saldos -->
                <div class="info-resumen">
                    <div class="info-item">
                        <div class="info-label">Saldo Inicial</div>
                        <div class="info-value <?php echo ($row_caja['saldoInicial'] >= 0) ? '' : 'negative'; ?> ">$<?= number_format($row_caja['saldoInicial']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Saldo Final</div>
                        <div class="info-value <?php echo ($row_caja['saldoFinal'] >= 0) ? '' : 'negative'; ?> ">$<?= number_format($row_caja['saldoFinal']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Diferencia</div>
                        <div class="info-value <?php echo ($row_caja['diferencia'] >= 0) ? 'positive' : 'negative'; ?> ">$<?= number_format($row_caja['diferencia']) ?></div>
                    </div>
                </div>

                <!-- Secciones de detalle -->
                <div class="grid-container">

                    <!-- Resumen de Ingresos -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-chart-line"></i> Resumen de Ingresos</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Descripción</th>
                                            <th>Efectivo</th>
                                            <th>Transferencia</th>
                                            <th>Tarjeta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Facturas</strong></td>
                                            <td>$<?= number_format($row['Fefectivo']) ?></td>
                                            <td>$<?= number_format($row['Ftransferencia']) ?></td>
                                            <td>$<?= number_format($row['Ftarjeta']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Pagos de Clientes</strong></td>
                                            <td>$<?= number_format($row['CPefectivo']) ?></td>
                                            <td>$<?= number_format($row['CPtransferencia']) ?></td>
                                            <td>$<?= number_format($row['CPtarjeta']) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="table-footer">
                                    <div>Total</div>
                                    <div>$<?= number_format($ItotalE) ?></div>
                                    <div>$<?= number_format($ItotalT) ?></div>
                                    <div>$<?= number_format($ItotalC) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Facturas a Contado -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-receipt"></i> Facturas a Contado</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Método</th>
                                            <th>Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $totalEfectivo = 0;
                                        $totalTransferencia = 0;
                                        $totalTarjeta = 0;
                                        
                                        if ($result_FacturasContado->num_rows > 0) {
                                            while ($row = $result_FacturasContado->fetch_assoc()) {
                                                echo "<tr>
                                                    <td>{$row['noFac']}</td>
                                                    <td>{$row['fecha']}</td>
                                                    <td>{$row['nombrec']}</td>
                                                    <td>{$row['metodo']}</td>
                                                    <td>$" . number_format($row['monto']) . "</td>
                                                </tr>";

                                                if($row['metodo'] == 'efectivo') {
                                                    $totalEfectivo += $row['monto'];
                                                } elseif($row['metodo'] == 'transferencia') {
                                                    $totalTransferencia += $row['monto'];
                                                } elseif($row['metodo'] == 'tarjeta') {
                                                    $totalTarjeta += $row['monto'];
                                                }
                                            }
                                        } else {
                                            echo "<tr><td colspan='5'>No hay facturas a contado</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="table-footer">
                                <div>Total Efectivo:</div>
                                <div>$<?= number_format($totalEfectivo) ?></div>
                            </div>
                            <div class="table-footer">
                                <div>Total Transferencia:</div>
                                <div>$<?= number_format($totalTransferencia) ?></div>
                            </div>
                            <div class="table-footer">
                                <div>Total Tarjeta:</div>
                                <div>$<?= number_format($totalTarjeta) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Facturas a Crédito -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-credit-card"></i> Facturas a Crédito</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Método</th>
                                            <th>Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $totalEfectivoCredito = 0;
                                        $totalTransferenciaCredito = 0;
                                        $totalTarjetaCredito = 0;
                                        
                                        if ($result_FacturasCredito->num_rows > 0) {
                                            while ($row = $result_FacturasCredito->fetch_assoc()) {
                                                echo "<tr>
                                                    <td>{$row['noFac']}</td>
                                                    <td>{$row['fecha']}</td>
                                                    <td>{$row['nombrec']}</td>
                                                    <td>{$row['metodo']}</td>
                                                    <td>$" . number_format($row['monto']) . "</td>
                                                </tr>";

                                                if($row['metodo'] == 'efectivo') {
                                                    $totalEfectivoCredito += $row['monto'];
                                                } elseif ($row['metodo'] == 'transferencia') {
                                                    $totalTransferenciaCredito += $row['monto'];
                                                } elseif ($row['metodo'] == 'tarjeta') {
                                                    $totalTarjetaCredito += $row['monto'];
                                                }
                                            }
                                        } else {
                                            echo "<tr><td colspan='5'>No hay facturas a crédito</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="table-footer">
                                <div>Total Efectivo:</div>
                                <div>$<?= number_format($totalEfectivoCredito) ?></div>
                            </div>
                            <div class="table-footer">
                                <div>Total Transferencia:</div>
                                <div>$<?= number_format($totalTransferenciaCredito) ?></div>
                            </div>
                            <div class="table-footer">
                                <div>Total Tarjeta:</div>
                                <div>$<?= number_format($totalTarjetaCredito) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagos de Clientes -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-hand-holding-usd"></i> Pagos de Clientes</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Método</th>
                                            <th>Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $totalEfectivoPagos = 0;
                                        $totalTransferenciaPagos = 0;
                                        $totalTarjetaPagos = 0;
                                        
                                        if ($result_pagos->num_rows > 0) {
                                            while ($row = $result_pagos->fetch_assoc()) {
                                                echo "<tr>
                                                    <td>{$row['id']}</td>
                                                    <td>{$row['fecha']}</td>
                                                    <td>{$row['nombre']}</td>
                                                    <td>{$row['metodo']}</td>
                                                    <td>$" . number_format($row['monto']) . "</td>
                                                </tr>";

                                                if($row['metodo'] == 'efectivo') {
                                                    $totalEfectivoPagos += $row['monto'];
                                                } elseif ($row['metodo'] == 'transferencia') {
                                                    $totalTransferenciaPagos += $row['monto'];
                                                } elseif ($row['metodo'] == 'tarjeta') {
                                                    $totalTarjetaPagos += $row['monto'];
                                                }
                                            }
                                        } else {
                                            echo "<tr><td colspan='5'>No hay pagos de clientes</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="table-footer">
                                <div>Total Efectivo:</div>
                                <div>$<?= number_format($totalEfectivoPagos) ?></div>
                            </div>
                            <div class="table-footer">
                                <div>Total Transferencia:</div>
                                <div>$<?= number_format($totalTransferenciaPagos) ?></div>
                            </div>
                            <div class="table-footer">
                                <div>Total Tarjeta:</div>
                                <div>$<?= number_format($totalTarjetaPagos) ?></div>
                            </div>
                        </div>
                    </div>

                    
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function imprimirReporte() {
            const numCaja = '<?php echo $numCaja; ?>';
            const reportUrl = `../../reports/cuadre/cuadre.php?numCaja=${numCaja}`;
            window.open(reportUrl, '_blank');
        }
    </script>
</body>
</html>