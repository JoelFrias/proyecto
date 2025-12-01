<?php

require_once '../../core/conexion.php';
require_once '../../core/verificar-sesion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'PADM002';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
        
    exit(); 
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Dashboard - Estadisticas</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>
<body>
        
    <div class="navegator-nav">

        <!-- Menu-->
        <?php include_once '../layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <div class="header-left">
                        <h1><i class="fas fa-chart-line"></i> Dashboard de Estadisticas</h1>
                    </div>
                    <div class="header-right">
                        <button class="btn-refresh" onclick="refreshDashboard()">
                            <i class="fas fa-sync-alt"></i> Actualizar
                        </button>
                        <button class="btn-download" onclick="generarReportePDF()">
                            <i class="fas fa-file-pdf"></i> Descargar PDF
                        </button>
                    </div>
                </div>
            </header>

            <!-- Filtros de Período -->
            <section class="filters-section">
                <div class="container">
                    <div class="filters-wrapper">
                        <div class="filter-group">
                            <label><i class="fas fa-calendar"></i> Período:</label>
                            <select id="periodo" onchange="updateDashboard()">
                                <option value="hoy">Hoy</option>
                                <option value="semana">Esta Semana</option>
                                <option value="mes" selected>Este Mes</option>
                                <option value="ano">Este Año</option>
                                <option value="personalizado">Personalizado</option>
                            </select>
                        </div>
                        
                        <div class="filter-group" id="customDateRange" style="display: none;">
                            <label>Desde:</label>
                            <input type="date" id="fechaInicio">
                            <label>Hasta:</label>
                            <input type="date" id="fechaFin">
                            <button class="btn-apply" onclick="updateDashboard()">
                                <i class="fas fa-check"></i> Aplicar
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- KPIs Principales -->
            <section class="kpis-section">
                <div class="container">
                    <div class="kpis-grid">
                        
                        <!-- KPI: Ventas Totales -->
                        <div class="kpi-card" data-color="blue">
                            <div class="kpi-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="kpi-content">
                                <h3>Ventas Totales</h3>
                                <p class="kpi-value" id="kpi-ventas-total">$0.00</p>
                                <span class="kpi-trend" id="kpi-ventas-trend">
                                    <i class="fas fa-arrow-up"></i> 0%
                                </span>
                            </div>
                        </div>

                        <!-- KPI: Ganancias -->
                        <div class="kpi-card" data-color="green">
                            <div class="kpi-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="kpi-content">
                                <h3>Ganancias Netas</h3>
                                <p class="kpi-value" id="kpi-ganancias">$0.00</p>
                                <span class="kpi-trend" id="kpi-ganancias-trend">
                                    <i class="fas fa-arrow-up"></i> 0%
                                </span>
                            </div>
                        </div>

                        <!-- KPI: Facturas -->
                        <div class="kpi-card" data-color="orange">
                            <div class="kpi-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="kpi-content">
                                <h3>Facturas Generadas</h3>
                                <p class="kpi-value" id="kpi-facturas">0</p>
                                <span class="kpi-subtitle" id="kpi-facturas-pendientes">0 pendientes</span>
                            </div>
                        </div>

                        <!-- KPI: Productos Vendidos -->
                        <div class="kpi-card" data-color="purple">
                            <div class="kpi-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="kpi-content">
                                <h3>Productos Vendidos</h3>
                                <p class="kpi-value" id="kpi-productos">0</p>
                                <span class="kpi-subtitle" id="kpi-productos-bajo">0 bajo stock</span>
                            </div>
                        </div>

                        <!-- KPI: Clientes -->
                        <div class="kpi-card" data-color="cyan">
                            <div class="kpi-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="kpi-content">
                                <h3>Clientes Activos</h3>
                                <p class="kpi-value" id="kpi-clientes">0</p>
                                <span class="kpi-subtitle" id="kpi-clientes-nuevos">0 nuevos</span>
                            </div>
                        </div>

                        <!-- KPI: Cuentas por Cobrar -->
                        <div class="kpi-card" data-color="red">
                            <div class="kpi-icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <div class="kpi-content">
                                <h3>Por Cobrar</h3>
                                <p class="kpi-value" id="kpi-por-cobrar">$0.00</p>
                                <span class="kpi-subtitle" id="kpi-clientes-deuda">0 clientes</span>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

            <!-- Panel de Alertas -->
            <section class="alerts-section">
                <div class="container">
                    <h3><i class="fas fa-bell"></i> Alertas y Notificaciones</h3>
                    <div id="alertsContainer" class="alerts-container">
                        <div class="alert-item loading">
                            <i class="fas fa-spinner fa-spin"></i> Cargando alertas...
                        </div>
                    </div>
                </div>
            </section>

            <!-- Gráficos Principales -->
            <section class="charts-section">
                <div class="container">
                    
                    <div class="charts-row">
                        <!-- Gráfico: Ventas por Período -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3><i class="fas fa-chart-area"></i> Ventas por Día</h3>
                                <div class="chart-actions">
                                    <button class="btn-chart-action" onclick="toggleChartType('chartVentas', 'line')">
                                        <i class="fas fa-chart-line"></i>
                                    </button>
                                    <button class="btn-chart-action" onclick="toggleChartType('chartVentas', 'bar')">
                                        <i class="fas fa-chart-bar"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="chart-body">
                                <canvas id="chartVentas"></canvas>
                            </div>
                        </div>

                        <!-- Gráfico: Top Productos -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3><i class="fas fa-box-open"></i> Top 10 Productos</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="chartProductos"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="charts-row">
                        <!-- Gráfico: Estado de Facturas -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3><i class="fas fa-file-invoice-dollar"></i> Estado de Facturas</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="chartFacturas"></canvas>
                            </div>
                        </div>

                        <!-- Gráfico: Ventas por Empleado -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3><i class="fas fa-user-tie"></i> Ventas por Empleado</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="chartEmpleados"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="charts-row">
                        <!-- Gráfico: Flujo de Caja -->
                        <div class="chart-card chart-card-wide">
                            <div class="chart-header">
                                <h3><i class="fas fa-cash-register"></i> Flujo de Caja</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="chartFlujoCaja"></canvas>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Tablas y Datos Detallados -->
            <section class="tables-section">
                <div class="container">
                    
                    <div class="tables-row">
                        <!-- Tabla: Top Clientes -->
                        <div class="table-card">
                            <div class="table-header">
                                <h3><i class="fas fa-star"></i> Top 10 Clientes</h3>
                            </div>
                            <div class="table-body">
                                <table id="tableClientes">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Cliente</th>
                                            <th>Total Compras</th>
                                            <th>Facturas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="4" class="loading">
                                                <i class="fas fa-spinner fa-spin"></i> Cargando...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tabla: Productos con Stock Bajo -->
                        <div class="table-card">
                            <div class="table-header">
                                <h3><i class="fas fa-exclamation-triangle"></i> Stock Bajo</h3>
                            </div>
                            <div class="table-body">
                                <table id="tableStockBajo">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Existencia</th>
                                            <th>Reorden</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="4" class="loading">
                                                <i class="fas fa-spinner fa-spin"></i> Cargando...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tables-row">
                        <!-- Tabla: Facturas Pendientes -->
                        <div class="table-card table-card-wide">
                            <div class="table-header">
                                <h3><i class="fas fa-clock"></i> Facturas Pendientes de Cobro</h3>
                            </div>
                            <div class="table-body">
                                <table id="tableFacturasPendientes">
                                    <thead>
                                        <tr>
                                            <th>Factura</th>
                                            <th>Cliente</th>
                                            <th>Fecha</th>
                                            <th>Total</th>
                                            <th>Balance</th>
                                            <th>Días Vencido</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="6" class="loading">
                                                <i class="fas fa-spinner fa-spin"></i> Cargando...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <!-- Scripts -->
    <script src="../../assets/js/dashboard.js"></script>
    
</body>
</html>