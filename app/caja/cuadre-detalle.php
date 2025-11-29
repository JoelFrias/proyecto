<?php

require_once '../../core/conexion.php';		// Conexión a la base de datos

// Verificar conexión a la base de datos
if (!$conn || !$conn->connect_errno === 0) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos",
        "error_code" => "DATABASE_CONNECTION_ERROR"
    ]));
}
require_once '../../core/verificar-sesion.php';

require_once '../../core/validar-permisos.php';
$permiso_necesario = 'CUA001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
    exit(); 
}

$numCaja = '';
if (isset($_GET['numCaja'])) {
    if (preg_match('/^[a-zA-Z0-9]{5}$/', $_GET['numCaja'])) {
        $numCaja = $_GET['numCaja'];
    } else {
        die("Formato de número de caja inválido");
    }
}

$sql_caja = "SELECT
                ce.numCaja AS numCaja, e.id AS idEmpleado,
                CONCAT(e.nombre,' ',e.apellido) AS nombreEmpleado,
                DATE_FORMAT(ce.fechaApertura, '%d/%m/%Y %l:%i %p') AS fechaApertura,
                DATE_FORMAT(ce.fechaCierre, '%d/%m/%Y %l:%i %p') AS fechaCierre,
                ce.saldoInicial AS saldoInicial, ce.saldoFinal AS saldoFinal,
                ce.diferencia AS diferencia, ce.estado AS estado
            FROM cajascerradas ce
            JOIN empleados e ON e.id = ce.idEmpleado
            WHERE ce.numCaja = ?;";
$stmt = $conn->prepare($sql_caja);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_caja = $stmt->get_result();
if ($result_caja->num_rows > 0) {
    $row_caja = $result_caja->fetch_assoc();
} else {
    die("No se encontró información para la caja especificada.");
}

// Obtener información de cierre/cancelación si aplica
$row_estado_detalle = null;
$estado_lower = strtolower($row_caja['estado']);
if ($estado_lower === 'cerrada' || $estado_lower === 'cancelada') {
    $sql_estado_detalle = "SELECT
                            CONCAT(e.nombre, ' ', e.apellido) AS nombreEmpleadoAccion,
                            ced.nota AS notasAccion,
                            DATE_FORMAT(ced.fecha, '%d/%m/%Y %l:%i %p') AS fechaAccion
                        FROM caja_estado_detalle AS ced
                        LEFT JOIN empleados AS e ON e.id = ced.id_empleado
                        WHERE ced.numCaja = ?
                        ORDER BY ced.fecha DESC LIMIT 1;";
    $stmt_detalle = $conn->prepare($sql_estado_detalle);
    $stmt_detalle->bind_param("s", $numCaja);
    $stmt_detalle->execute();
    $result_detalle = $stmt_detalle->get_result();
    if ($result_detalle->num_rows > 0) {
        $row_estado_detalle = $result_detalle->fetch_assoc();
    }
}

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

$ItotalE = $row['Fefectivo'] + $row['CPefectivo'];
$ItotalT = $row['Ftransferencia'] + $row['CPtransferencia'];
$ItotalC = $row['Ftarjeta'] + $row['CPtarjeta'];

$sql_FacturasContado = "SELECT f.numFactura AS noFac, DATE_FORMAT(f.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                            CONCAT(c.nombre,' ',c.apellido) AS nombrec, fm.metodo, fm.monto
                        FROM facturas f
                        JOIN clientes c ON c.id = f.idCliente
                        JOIN facturas_metodopago fm ON fm.numFactura = f.numFactura AND fm.noCaja = ?
                        WHERE f.tipoFactura = 'contado' AND f.estado != 'Cancelada';";
$stmt = $conn->prepare($sql_FacturasContado);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_FacturasContado = $stmt->get_result();

$sql_FacturasCredito = "SELECT f.numFactura AS noFac, DATE_FORMAT(f.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                            CONCAT(c.nombre,' ',c.apellido) AS nombrec, fm.metodo, fm.monto
                        FROM facturas f
                        JOIN clientes c ON c.id = f.idCliente
                        JOIN facturas_metodopago fm ON fm.numFactura = f.numFactura AND fm.noCaja = ?
                        WHERE f.tipoFactura = 'credito' AND f.estado != 'Cancelada';";
$stmt = $conn->prepare($sql_FacturasCredito);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_FacturasCredito = $stmt->get_result();

$totalEfectivo = 0; $totalTransferencia = 0; $totalTarjeta = 0;
$totalEfectivoCredito = 0; $totalTransferenciaCredito = 0; $totalTarjetaCredito = 0;

$sql_pagos = "SELECT ch.registro AS id, DATE_FORMAT(ch.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                CONCAT(c.nombre,' ',c.apellido) AS nombre, ch.metodo AS metodo, ch.monto AS monto
            FROM clientes_historialpagos ch
            JOIN clientes c ON c.id = ch.idCliente
            WHERE ch.numCaja = ?;";
$stmt = $conn->prepare($sql_pagos);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_pagos = $stmt->get_result();

$totalEfectivoPagos = 0; $totalTransferenciaPagos = 0; $totalTarjetaPagos = 0;
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
            --warning: #f39c12;
            --info: #3498db;
            --border: #eaeaea;
            --background: #f5f6fa;
            --text: #2d3436;
            --gray: #636e72;
            --light-gray: #f1f2f6;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
            margin: 0 auto;
        }

        .cabecera {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .cabecera-titulo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cabecera h1 {
            font-size: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }

        .badge-estado {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-cerrada {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .badge-auditoria {
            background: #cce5ff;
            color: #004085;
            border: 2px solid #b8daff;
        }

        .badge-pendiente {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffeaa7;
        }

        .badge-cancelada {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .acciones {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
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
            white-space: nowrap;
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

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

        .info-general {
            display: grid;
            gap: 1rem;
        }

        .info-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .info-row div {
            padding: 0.75rem;
            background: var(--light-gray);
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }

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

        table tfoot {
            border-top: 2px solid var(--border);
        }

        table tfoot td {
            background-color: var(--light-gray);
            font-weight: 600;
            padding: 0.75rem 1rem;
            color: var(--primary);
        }

        table tfoot .table-footer-row td:first-child {
            text-align: left;
        }


        /* Estilos para la sección de estado */

        .estado-accion-card {
            border-left: 4px solid var(--success);
        }

        .estado-accion-card.cancelada {
            border-left-color: var(--danger);
        }

        .estado-accion-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .estado-accion-header i {
            font-size: 1.1rem;
        }

        .estado-accion-header.cerrada i {
            color: var(--success);
        }

        .estado-accion-header.cancelada i {
            color: var(--danger);
        }

        .notas-contenido {
            background: var(--light-gray);
            padding: 1rem;
            border-radius: 0.25rem;
            font-size: 0.9rem;
            line-height: 1.6;
            color: var(--text);
            margin-top: 0.5rem;
            border-left: 3px solid var(--gray);
            white-space: pre-wrap;
            word-break: break-word;
        }

        @media (max-width: 991px) {
            .info-resumen {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .cabecera {
                flex-direction: column;
                align-items: flex-start;
            }
            .cabecera-titulo {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }
            .acciones {
                width: 100%;
                justify-content: flex-start;
            }
        }

        @media (max-width: 576px) {
            .info-resumen {
                grid-template-columns: 1fr;
            }
            .info-row {
                grid-template-columns: 1fr;
            }
            .acciones {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
        }

        .grid-container {
            column-count: 2;
            column-gap: 1.5rem;
        }

        .grid-container .card {
            break-inside: avoid;
            margin-bottom: 1.5rem;
            display: inline-block;
            width: 100%;
        }

        @media (max-width: 768px) {
            .grid-container {
                column-count: 1;
            }
        }
    </style>
</head>
<body>
    <div class="navegator-nav">
        <?php include '../../app/layouts/menu.php'; ?>
        <div class="page-content">
            <div class="contenedor">
                <div class="cabecera">
                    <div class="cabecera-titulo">
                        <h1><i class="fas fa-cash-register"></i> Detalle de Cuadre de Caja</h1>
                        <?php $badge_class = 'badge-' . $estado_lower; ?>
                        <span class="badge-estado <?= $badge_class ?>">
                            <i class="fas fa-circle"></i> <?= strtoupper($row_caja['estado']) ?>
                        </span>
                    </div>
                    <div class="acciones">
                        <button class="btn btn-outline" onclick="history.back()">
                            <i class="fas fa-arrow-left"></i> Volver
                        </button>
                        <?php
                        $permiso_necesario = 'CUA002';
                        if (validarPermiso($conn, $permiso_necesario, $id_empleado) && ($estado_lower !== 'cerrada' && $estado_lower !== 'cancelada')):
                        ?>
                            <button class="btn btn-success" onclick="cambiarEstado('cerrada')">
                                <i class="fas fa-check-circle"></i> Cerrar Cuadre
                            </button>
                            <button class="btn btn-danger" onclick="cambiarEstado('cancelada')">
                                <i class="fas fa-times-circle"></i> Cancelar Cuadre
                            </button>
                        <?php endif; ?>
                        <button class="btn" onclick="imprimirReporte()">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-info-circle"></i> Información General</h2>
                        <span style="font-size: 1.125rem; font-weight: 600; color: var(--primary);">Caja #<?= $row_caja['numCaja'] ?></span>
                    </div>
                    <div class="card-body">
                        <div class="info-general">
                            <div class="info-row">
                                <div><strong>ID Empleado:</strong> <?= $row_caja['idEmpleado'] ?></div>
                                <div><strong>Empleado:</strong> <?= $row_caja['nombreEmpleado'] ?></div>
                                <div><strong>Fecha Apertura:</strong> <?= $row_caja['fechaApertura'] ?></div>
                                <div><strong>Fecha Cierre:</strong> <?= $row_caja['fechaCierre'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($row_estado_detalle): ?>
                <!-- Información de Cierre/Cancelación -->
                <div class="card estado-accion-card <?= $estado_lower ?>">
                    <div class="card-header">
                        <h2 class="estado-accion-header <?= $estado_lower ?>">
                            <?php if ($estado_lower === 'cerrada'): ?>
                                <i class="fas fa-check-circle"></i> Información de Cierre
                            <?php else: ?>
                                <i class="fas fa-times-circle"></i> Información de Cancelación
                            <?php endif; ?>
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="info-general">
                            <div class="info-row">
                                <div>
                                    <strong><?= $estado_lower === 'cerrada' ? 'Cerrado por:' : 'Cancelado por:' ?></strong> 
                                    <?= htmlspecialchars($row_estado_detalle['nombreEmpleadoAccion'] ?? 'No disponible') ?>
                                </div>
                                <div>
                                    <strong>Fecha de <?= $estado_lower === 'cerrada' ? 'cierre' : 'cancelación' ?>:</strong> 
                                    <?= htmlspecialchars($row_estado_detalle['fechaAccion'] ?? 'No disponible') ?>
                                </div>
                            </div>
                            <div>
                                <strong>Nota / Observación:</strong>
                                <div class="notas-contenido"><?= htmlspecialchars($row_estado_detalle['notasAccion'] ?? 'Sin notas') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-resumen">
                    <div class="info-item">
                        <div class="info-label">Saldo Inicial</div>
                        <div class="info-value <?= ($row_caja['saldoInicial'] >= 0) ? '' : 'negative' ?>">$<?= number_format($row_caja['saldoInicial']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Saldo Final</div>
                        <div class="info-value <?= ($row_caja['saldoFinal'] >= 0) ? '' : 'negative' ?>">$<?= number_format($row_caja['saldoFinal']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Diferencia</div>
                        <div class="info-value <?= ($row_caja['diferencia'] >= 0) ? 'positive' : 'negative' ?>">$<?= number_format($row_caja['diferencia']) ?></div>
                    </div>
                </div>

                <div class="grid-container">
                    <!-- RESUMEN DE INGRESOS -->
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
                                    <tfoot>
                                        <tr>
                                            <td>Total</td>
                                            <td>$<?= number_format($ItotalE) ?></td>
                                            <td>$<?= number_format($ItotalT) ?></td>
                                            <td>$<?= number_format($ItotalC) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- FACTURAS A CONTADO -->
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
                                        if ($result_FacturasContado->num_rows > 0) {
                                            while ($row = $result_FacturasContado->fetch_assoc()) {
                                                echo "<tr><td>{$row['noFac']}</td><td>{$row['fecha']}</td><td>{$row['nombrec']}</td><td>{$row['metodo']}</td><td>$" . number_format($row['monto']) . "</td></tr>";
                                                if($row['metodo'] == 'efectivo') $totalEfectivo += $row['monto'];
                                                elseif($row['metodo'] == 'transferencia') $totalTransferencia += $row['monto'];
                                                elseif($row['metodo'] == 'tarjeta') $totalTarjeta += $row['monto'];
                                            }
                                        } else {
                                            echo "<tr><td colspan='5'>No hay facturas a contado</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4">Total Efectivo:</strong></td>
                                            <td>$<?= number_format($totalEfectivo) ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">Total Transferencia:</strong></td>
                                            <td>$<?= number_format($totalTransferencia) ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">Total Tarjeta:</strong></td>
                                            <td>$<?= number_format($totalTarjeta) ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- FACTURAS A CRÉDITO -->
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
                                        if ($result_FacturasCredito->num_rows > 0) {
                                            while ($row = $result_FacturasCredito->fetch_assoc()) {
                                                echo "<tr><td>{$row['noFac']}</td><td>{$row['fecha']}</td><td>{$row['nombrec']}</td><td>{$row['metodo']}</td><td>$" . number_format($row['monto']) . "</td></tr>";
                                                if($row['metodo'] == 'efectivo') $totalEfectivoCredito += $row['monto'];
                                                elseif($row['metodo'] == 'transferencia') $totalTransferenciaCredito += $row['monto'];
                                                elseif($row['metodo'] == 'tarjeta') $totalTarjetaCredito += $row['monto'];
                                            }
                                        } else {
                                            echo "<tr><td colspan='5'>No hay facturas a crédito</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4">Total Efectivo:</td>
                                            <td>$<?= number_format($totalEfectivoCredito) ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">Total Transferencia:</td>
                                            <td>$<?= number_format($totalTransferenciaCredito) ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">Total Tarjeta:</td>
                                            <td>$<?= number_format($totalTarjetaCredito) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- PAGOS DE CLIENTES -->
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
                                        if ($result_pagos->num_rows > 0) {
                                            while ($row = $result_pagos->fetch_assoc()) {
                                                echo "<tr><td>{$row['id']}</td><td>{$row['fecha']}</td><td>{$row['nombre']}</td><td>{$row['metodo']}</td><td>$" . number_format($row['monto']) . "</td></tr>";
                                                if($row['metodo'] == 'efectivo') $totalEfectivoPagos += $row['monto'];
                                                elseif($row['metodo'] == 'transferencia') $totalTransferenciaPagos += $row['monto'];
                                                elseif($row['metodo'] == 'tarjeta') $totalTarjetaPagos += $row['monto'];
                                            }
                                        } else {
                                            echo "<tr><td colspan='5'>No hay pagos de clientes</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4">Total Efectivo:</td>
                                            <td>$<?= number_format($totalEfectivoPagos) ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">Total Transferencia:</td>
                                            <td>$<?= number_format($totalTransferenciaPagos) ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">Total Tarjeta:</td>
                                            <td>$<?= number_format($totalTarjetaPagos) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
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
            window.open(`../../reports/cuadre/cuadre.php?numCaja=${numCaja}`, '_blank');
        }

        function cambiarEstado(nuevoEstado) {
            const estadoTexto = nuevoEstado === 'cerrada' ? 'cerrar' : 'cancelar';
            const estadoCapital = nuevoEstado === 'cerrada' ? 'Cerrar' : 'Cancelar';
            
            Swal.fire({
                title: `¿${estadoCapital} Cuadre de Caja?`,
                html: `
                    <p style="margin-bottom: 1rem;">¿Está seguro que desea ${estadoTexto} este cuadre de caja? Esta acción no se puede deshacer.</p>
                    <div style="text-align: left;">
                        <label for="swal-nota" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2c3e50;">
                            Nota / Observación <span style="color: #e74c3c;">*</span>
                        </label>
                        <textarea id="swal-nota" class="swal2-textarea" placeholder="Escriba una nota obligatoria para ${estadoTexto} el cuadre..." 
                            style="width: 100%; min-height: 100px; resize: vertical; margin: 0; font-size: 0.95rem;"></textarea>
                        <small style="color: #636e72; font-size: 0.8rem;">Mínimo 10 caracteres</small>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: nuevoEstado === 'cerrada' ? '#27ae60' : '#e74c3c',
                cancelButtonColor: '#636e72',
                confirmButtonText: `Sí, ${estadoTexto}`,
                cancelButtonText: 'Cancelar',
                focusConfirm: false,
                preConfirm: () => {
                    const nota = document.getElementById('swal-nota').value.trim();
                    if (!nota) {
                        Swal.showValidationMessage('La nota es obligatoria');
                        return false;
                    }
                    if (nota.length < 10) {
                        Swal.showValidationMessage('La nota debe tener al menos 10 caracteres');
                        return false;
                    }
                    if (nota.length > 500) {
                        Swal.showValidationMessage('La nota no puede exceder 500 caracteres');
                        return false;
                    }
                    return nota;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    $.ajax({
                        url: '../../api/caja/estado-cuadre.php',
                        type: 'POST',
                        data: {
                            numCaja: '<?php echo $numCaja; ?>',
                            nuevoEstado: nuevoEstado,
                            notas: result.value
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: '¡Éxito!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonColor: '#27ae60'
                                }).then(() => { location.reload(); });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: response.message || 'No se pudo cambiar el estado del cuadre',
                                    icon: 'error',
                                    confirmButtonColor: '#e74c3c'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error',
                                text: 'Hubo un problema al cambiar el estado. Por favor intente nuevamente.',
                                icon: 'error',
                                confirmButtonColor: '#e74c3c'
                            });
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>