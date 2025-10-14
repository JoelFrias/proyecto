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
    header('Location: views/auth/login.php'); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header("Location: views/auth/login.php?session_expired=session_expired"); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

require_once 'models/conexion.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>EasyPOS</title>
    <link rel="icon" href="assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* General styles */
        :root {
            --primary-color-i: #4a6fa5;
            --secondary-color-i: #6c757d;
            --accent-color-i: #3498db;
            --success-color-i: #2ecc71;
            --warning-color-i: #f39c12;
            --danger-color-i: #e74c3c;
            --dark-color-i: #2c3e50;
            --light-color-i: #f9fafb;
            --border-radius-i: 12px;
            --box-shadow-i: 0 8px 15px rgba(0, 0, 0, 0.1);
            --transition-i: all 0.3s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9fafb;
            color: var(--dark-color-i);
        }

        /* Welcome section con botón */
        .welcome {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eaeaea;
        }

        .welcome h1 {
            font-size: 1.8rem;
            font-weight: 500;
            color: var(--primary-color-i);
            margin: 0;
        }

        #btn-edit-profile {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: var(--border-radius-i);
            padding: 8px 16px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition-i);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            color: var(--primary-color-i);
            font-weight: 500;
        }

        #btn-edit-profile:hover {
            background-color: #f5f5f5;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        #btn-edit-profile:active {
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        /* Dashboard title */
        .dashboard-title {
            font-size: 1.8rem;
            color: var(--dark-color-i);
            margin: 20px 0;
            text-align: center;
            position: relative;
            font-weight: 600;
        }

        .dashboard-title:after {
            content: '';
            display: block;
            width: 70px;
            height: 3px;
            background: var(--primary-color-i);
            margin: 10px auto;
            border-radius: 2px;
        }

        /* Filters section */
        #filters {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-radius: var(--border-radius-i);
            margin-bottom: 25px;
            background: white;
            box-shadow: var(--box-shadow-i);
        }

        #filters label {
            margin-right: 10px;
            font-weight: 600;
            color: var(--dark-color-i);
        }

        #filters select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius-i);
            background-color: white;
            margin-right: 15px;
            font-size: 0.9rem;
            min-width: 150px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: var(--transition-i);
        }

        #filters select:focus {
            border-color: var(--primary-color-i);
            box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.2);
            outline: none;
        }

        #btn-filters {
            background-color: var(--primary-color-i);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius-i);
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            transition: var(--transition-i);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }

        #btn-filters:hover {
            background-color: #3a5885;
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.15);
        }

        #btn-filters i {
            margin-right: 8px;
        }

        /* Graphics section */
        .graphics {
            margin-top: 30px;
        }

        .containers {
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }

        /* Tarjetas de gráficos */
        .chart-card {
            flex: 0 0 calc(50% - 30px);
            max-width: calc(50% - 30px);
            margin: 0 15px 30px;
            background-color: white;
            border-radius: var(--border-radius-i);
            box-shadow: var(--box-shadow-i);
            overflow: hidden;
            transition: var(--transition-i);
        }

        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
        }

        .chart-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            align-items: center;
        }

        .chart-icon {
            margin-right: 12px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(74, 111, 165, 0.12);
            color: var(--primary-color-i);
            border-radius: 8px;
            font-size: 16px;
        }

        .chart-title {
            margin: 0;
            color: var(--dark-color-i);
            font-size: 1.25rem;
            font-weight: 600;
        }

        .chart-body {
            padding: 15px;
            position: relative;
            height: 300px;
        }

        .no-data {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: var(--secondary-color-i);
        }

        /* Canvas para gráficos */
        canvas {
            width: 100% !important;
            height: 100% !important;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .chart-card {
                flex: 0 0 100%;
                max-width: 100%;
                margin: 0 0 25px;
            }
            
            #filters {
                flex-direction: column;
                align-items: flex-start;
            }
            
            #filters select {
                margin-bottom: 15px;
                width: 100%;
                margin-right: 0;
            }
            
            #btn-filters {
                width: 100%;
            }
        }

        /* Modal styles (mantuvimos los mismos) */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: block;
            opacity: 1;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 10% auto;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            border-radius: var(--border-radius-i);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
            animation: modal-appear 0.3s forwards;
        }

        @keyframes modal-appear {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: 300;
            color: #aaa;
            cursor: pointer;
            transition: var(--transition-i);
        }

        .close:hover {
            color: var(--primary-color-i);
        }

        .modal h2 {
            margin-top: 0;
            margin-bottom: 25px;
            font-size: 1.6rem;
            font-weight: 500;
            color: var(--primary-color-i);
            padding-bottom: 15px;
            border-bottom: 1px solid #eaeaea;
        }

        .modal form {
            display: flex;
            flex-direction: column;
        }

        .modal label {
            margin-bottom: 8px;
            color: #555;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .modal input {
            padding: 12px 15px;
            margin-bottom: 20px;
            border: 1px solid #e1e1e1;
            border-radius: var(--border-radius-i);
            font-size: 1rem;
            transition: var(--transition-i);
        }

        .modal input:focus {
            outline: none;
            border-color: var(--primary-color-i);
            box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.15);
        }

        .modal button[type="submit"] {
            margin-top: 10px;
            padding: 12px;
            background-color: var(--primary-color-i);
            color: white;
            border: none;
            border-radius: var(--border-radius-i);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition-i);
        }

        .modal button[type="submit"]:hover {
            background-color: #3a5885;
        }

        .modal button[type="submit"]:active {
            transform: translateY(1px);
        }

        .modal input.error {
            border-color: var(--danger-color-i);
        }

        .error-message {
            color: var(--danger-color-i);
            font-size: 0.85rem;
            margin-top: -15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    
    <div class="navegator-nav">

        <!-- Menu-->
        <?php include 'views/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

            <!-- Mensaje de bienvenida -->
            <div class="welcome">
                <h1 id="mensaje"></h1>
                <button id="btn-edit-profile"><i class="fas fa-user-edit"></i> Editar Usuario</button>
            </div>

            <!-- modal para editar Usuario -->
            <div id="modal-edit-profile" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Editar Usuario</h2>
                    <form id="edit-profile-form">
                        <label for="user">Usuario:</label>
                        <input type="text" id="user" name="user" required>

                        <label for="password">Contraseña:</label>
                        <input type="password" id="password" name="password" required>

                        <button type="submit">Guardar Cambios</button>
                    </form>
                </div>
            </div>

            <!-- Título principal del Dashboard -->
            <h1 class="dashboard-title">Dashboard de Estadísticas Personal</h1>
            
            <!-- filters -->
            <div id="filters">
                <label for="months"><i class="fas fa-calendar-alt"></i> Periodo:</label>
                <select name="months" id="months">
                    <option value="current" <?php echo (isset($_GET['periodo']) && $_GET['periodo'] == 'current') ? 'selected' : ''; ?>>Mes Actual</option>
                    <option value="previous" <?php echo (isset($_GET['periodo']) && $_GET['periodo'] == 'previous') ? 'selected' : ''; ?>>Mes Anterior</option>
                </select>

                <button id="btn-filters" name="btn-filters" onclick="recargar()"><i class="fa-solid fa-magnifying-glass"></i> Aplicar Filtros</button>
            </div>

            <!-- graphics -->
            <div class="graphics">
                <div class="containers">
                    <div class="row">
                        <!-- Gráfico 1: Número de Ventas -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-icon">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <h3 class="chart-title">Número de Ventas por Día</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="ventas"></canvas>
                                <div class="no-data" id="no-data-ventas" style="display:none;">
                                    <i class="fas fa-exclamation-circle fa-3x"></i>
                                    <p>No hay datos disponibles</p>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico 2: Total de Ventas -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h3 class="chart-title">Total de Ventas ($) por Día</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="no-ventas"></canvas>
                                <div class="no-data" id="no-data-total-ventas" style="display:none;">
                                    <i class="fas fa-exclamation-circle fa-3x"></i>
                                    <p>No hay datos disponibles</p>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico 3: Clientes Populares -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="chart-title">Clientes Más Frecuentes</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="clientes-populares"></canvas>
                                <div class="no-data" id="no-data-clientes" style="display:none;">
                                    <i class="fas fa-exclamation-circle fa-3x"></i>
                                    <p>No hay datos disponibles</p>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico 4: Productos Más Vendidos -->
                        <div class="chart-card">
                            <div class="chart-header">
                                <div class="chart-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <h3 class="chart-title">Productos Más Vendidos</h3>
                            </div>
                            <div class="chart-body">
                                <canvas id="mas-vendidos"></canvas>
                                <div class="no-data" id="no-data-productos" style="display:none;">
                                    <i class="fas fa-exclamation-circle fa-3x"></i>
                                    <p>No hay datos disponibles</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <!-- scripts de gráficos -->
    <script>
        function cargarVentasPorDia(periodo) {
            fetch(`assets/graphics/index/no-ventas.php?periodo=${periodo}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        document.getElementById('no-data-ventas').style.display = 'none';
                        
                        const dias = data.map(item => item.dia);
                        const ventas = data.map(item => item.cantidad_ventas);

                        const ctx = document.getElementById('ventas').getContext('2d');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: dias,
                                datasets: [{
                                    label: 'Número de Ventas',
                                    data: ventas,
                                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 1,
                                    borderRadius: 5,
                                    maxBarThickness: 40
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.05)'
                                        },
                                        ticks: {
                                            font: {
                                                size: 12
                                            }
                                        }
                                    },
                                    x: {
                                        grid: {
                                            display: false
                                        },
                                        ticks: {
                                            font: {
                                                size: 12
                                            }
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                        padding: 10,
                                        titleFont: {
                                            size: 14
                                        },
                                        bodyFont: {
                                            size: 13
                                        },
                                        displayColors: false,
                                        callbacks: {
                                            title: function(tooltipItems) {
                                                return 'Día: ' + tooltipItems[0].label;
                                            },
                                            label: function(context) {
                                                return 'Ventas: ' + context.raw;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        document.getElementById('no-data-ventas').style.display = 'flex';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('no-data-ventas').style.display = 'flex';
                });
        }

        // Función para obtener los datos del total de ventas por día
        function cargarTotalVentasPorDia(periodo) {
            fetch(`assets/graphics/index/total-ventas.php?periodo=${periodo}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        document.getElementById('no-data-total-ventas').style.display = 'none';
                        
                        const dias = data.map(item => item.dia);
                        const ventas = data.map(item => item.total_ventas);

                        const ctx = document.getElementById('no-ventas').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: dias,
                                datasets: [{
                                    label: 'Total de Ventas ($)',
                                    data: ventas,
                                    backgroundColor: 'rgba(46, 204, 113, 0.2)',
                                    borderColor: 'rgba(39, 174, 96, 1)',
                                    borderWidth: 2.5,
                                    fill: true,
                                    tension: 0.3,
                                    pointBackgroundColor: 'white',
                                    pointBorderColor: 'rgba(39, 174, 96, 1)',
                                    pointBorderWidth: 2,
                                    pointRadius: 5,
                                    pointHoverRadius: 7
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.05)'
                                        },
                                        ticks: {
                                            callback: function(value) {
                                                return '$' + value;
                                            }
                                        }
                                    },
                                    x: {
                                        grid: {
                                            display: false
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                        padding: 10,
                                        titleFont: {
                                            size: 14
                                        },
                                        bodyFont: {
                                            size: 13
                                        },
                                        callbacks: {
                                            title: function(tooltipItems) {
                                                return 'Día: ' + tooltipItems[0].label;
                                            },
                                            label: function(context) {
                                                return 'Total: $' + context.raw.toFixed(2);
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        document.getElementById('no-data-total-ventas').style.display = 'flex';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('no-data-total-ventas').style.display = 'flex';
                });
        }

        // Función para obtener los datos de clientes más populares
        function cargarClientesPopulares(periodo) {
            fetch(`assets/graphics/index/clientes-popular.php?periodo=${periodo}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        document.getElementById('no-data-clientes').style.display = 'none';
                        
                        const clientes = data.map(item => item.nombre_cliente);
                        const compras = data.map(item => item.ventas);

                        const ctx = document.getElementById('clientes-populares').getContext('2d');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: clientes,
                                datasets: [{
                                    label: 'Compras por Cliente',
                                    data: compras,
                                    backgroundColor: [
                                        'rgba(255, 99, 132, 0.8)',
                                        'rgba(255, 159, 64, 0.8)',
                                        'rgba(255, 205, 86, 0.8)',
                                        'rgba(75, 192, 192, 0.8)',
                                        'rgba(54, 162, 235, 0.8)',
                                        'rgba(153, 102, 255, 0.8)'
                                    ],
                                    borderColor: [
                                        'rgb(255, 99, 132)',
                                        'rgb(255, 159, 64)',
                                        'rgb(255, 205, 86)',
                                        'rgb(75, 192, 192)',
                                        'rgb(54, 162, 235)',
                                        'rgb(153, 102, 255)'
                                    ],
                                    borderWidth: 1,
                                    borderRadius: 5,
                                    maxBarThickness: 40
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                indexAxis: 'y',  // Barras horizontales para mejor visualización
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        grid: {
                                            color: 'rgba(0, 0, 0, 0.05)'
                                        }
                                    },
                                    y: {
                                        grid: {
                                            display: false
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                        padding: 10,
                                        displayColors: false,
                                        callbacks: {
                                            title: function(tooltipItems) {
                                                return tooltipItems[0].label;
                                            },
                                            label: function(context) {
                                                let label = context.raw === 1 ? ' compra' : ' compras';
                                                return context.raw + label;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        document.getElementById('no-data-clientes').style.display = 'flex';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('no-data-clientes').style.display = 'flex';
                });
        }

        // Función para obtener los datos de productos más vendidos
        function cargarProductosMasVendidos(periodo) {
            fetch(`assets/graphics/index/mas-vendidos.php?periodo=${periodo}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        document.getElementById('no-data-productos').style.display = 'none';
                        
                        const productos = data.map(item => item.descripcion);
                        const cantidades = data.map(item => item.cantidad_vendida);

                        const ctx = document.getElementById('mas-vendidos').getContext('2d');
                        new Chart(ctx, {
                            type: 'doughnut',  // Cambiado a doughnut para un look más moderno
                            data: {
                                labels: productos,
                                datasets: [{
                                    label: 'Cantidad Vendida',
                                    data: cantidades,
                                    backgroundColor: [
                                        'rgba(255, 183, 77, 0.8)',
                                        'rgba(129, 199, 132, 0.8)',
                                        'rgba(100, 181, 246, 0.8)',
                                        'rgba(244, 143, 177, 0.8)',
                                        'rgba(77, 182, 172, 0.8)',
                                        'rgba(171, 71, 188, 0.8)'
                                    ],
                                    borderColor: 'white',
                                    borderWidth: 2,
                                    hoverOffset: 15
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '65%',
                                plugins: {
                                    legend: {
                                        position: 'right',
                                        labels: {
                                            font: {
                                                size: 12
                                            },
                                            padding: 15,
                                            usePointStyle: true,
                                            pointStyle: 'circle'
                                        }
                                    },
                                    tooltip: {
                                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                        padding: 10,
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.label || '';
                                                let value = context.raw || 0;
                                                let total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                                let percentage = Math.round((value / total) * 100);
                                                return `${label}: ${value} (${percentage}%)`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        document.getElementById('no-data-productos').style.display = 'flex';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('no-data-productos').style.display = 'flex';
                });
        }

        // Función para inicializar los gráficos con el periodo seleccionado
        function cargarGraficos(periodo = 'current') {
            // Limpiar los canvas existentes para evitar superposiciones
            document.getElementById('ventas').remove();
            document.getElementById('no-ventas').remove();
            document.getElementById('clientes-populares').remove();
            document.getElementById('mas-vendidos').remove();
            
            // Crear nuevos canvas
            const contenedoresGraficos = document.querySelectorAll('.chart-body');
            
            contenedoresGraficos[0].innerHTML = '<canvas id="ventas"></canvas><div class="no-data" id="no-data-ventas" style="display:none;"><i class="fas fa-exclamation-circle fa-3x"></i><p>No hay datos disponibles</p></div>';
            contenedoresGraficos[1].innerHTML = '<canvas id="no-ventas"></canvas><div class="no-data" id="no-data-total-ventas" style="display:none;"><i class="fas fa-exclamation-circle fa-3x"></i><p>No hay datos disponibles</p></div>';
            contenedoresGraficos[2].innerHTML = '<canvas id="clientes-populares"></canvas><div class="no-data" id="no-data-clientes" style="display:none;"><i class="fas fa-exclamation-circle fa-3x"></i><p>No hay datos disponibles</p></div>';
            contenedoresGraficos[3].innerHTML = '<canvas id="mas-vendidos"></canvas><div class="no-data" id="no-data-productos" style="display:none;"><i class="fas fa-exclamation-circle fa-3x"></i><p>No hay datos disponibles</p></div>';
            
            // Cargar los datos en los nuevos gráficos
            cargarVentasPorDia(periodo);
            cargarTotalVentasPorDia(periodo);
            cargarClientesPopulares(periodo);
            cargarProductosMasVendidos(periodo);
            
            // Actualizar el texto del periodo en la UI
            const periodoTexto = periodo === 'current' ? 'Mes Actual' : 'Mes Anterior';
            document.querySelector('.dashboard-title').innerHTML = `Dashboard de Estadísticas Personal <span style="font-size: 1rem; color: var(--primary-color-i); font-weight: normal; display: block; margin-top: 5px;">${periodoTexto}</span>`;
        }

        // Función para recargar la página con el filtro seleccionado
        function recargar() {
            const periodo = document.getElementById('months').value;
            
            // Mostrar indicador de carga
            Swal.fire({
                title: 'Cargando...',
                html: 'Actualizando los datos del dashboard',
                timer: 1000,
                timerProgressBar: true,
                didOpen: () => {
                    Swal.showLoading();
                }
            }).then(() => {
                window.location.href = `index.php?periodo=${periodo}`;
            });
        }

        // Llamar la función de inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const periodo = urlParams.get('periodo') || 'current';
            document.getElementById('months').value = periodo;
            
            // Pequeña animación de carga para los gráficos
            const chartCards = document.querySelectorAll('.chart-card');
            chartCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
            
            cargarGraficos(periodo);
        });
    </script>

    <!-- Script de mensaje de bienvenida -->
    <script>
        const mensaje = document.getElementById('mensaje');
        const hora = new Date().getHours();
        let saludo;

        if (hora >= 5 && hora < 12) {
            saludo = "<i class='fas fa-sun' style='color: #f39c12; margin-right: 10px;'></i>Buenos días";
        } else if (hora >= 12 && hora < 18) {
            saludo = "<i class='fas fa-cloud-sun' style='color: #e67e22; margin-right: 10px;'></i>Buenas tardes";
        } else {
            saludo = "<i class='fas fa-moon' style='color: #3498db; margin-right: 10px;'></i>Buenas noches";
        }

        mensaje.innerHTML = `${saludo} <span style="color: #2c3e50; font-weight: 600;"><?php echo $_SESSION['nombre']; ?></span>`;
    </script>

    <!-- Script para controlar el modal -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
        // Elementos DOM
        const modal = document.getElementById('modal-edit-profile');
        const openBtn = document.getElementById('btn-edit-profile');
        const closeBtn = document.querySelector('.close');
        const form = document.getElementById('edit-profile-form');
        
        // Obtener valores actuales del usuario
        const username = "<?php echo isset($_SESSION['username']) ? $_SESSION['username'] : ''; ?>";
        document.getElementById('user').value = username;
        
        // Función para abrir el modal
        function openModal() {
            modal.style.display = 'block';
            setTimeout(function() {
                modal.classList.add('show');
            }, 10);
            document.body.style.overflow = 'hidden'; // Prevenir scroll en el fondo
        }
        
        // Función para cerrar el modal
        function closeModal() {
            modal.classList.remove('show');
            setTimeout(function() {
                modal.style.display = 'none';
                document.body.style.overflow = ''; // Restaurar scroll
            }, 300);
        }
        
        // Event listeners
        openBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        
        // Cerrar modal cuando se hace clic fuera del contenido
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        
        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.classList.contains('show')) {
                closeModal();
            }
        });
        
        // Manejar envío del formulario
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            
            // Validación básica
            const userInput = document.getElementById('user');
            const passwordInput = document.getElementById('password');
            let isValid = true;
            
            // Eliminar mensajes de error previos
            const errorMessages = document.querySelectorAll('.error-message');
            for (let i = 0; i < errorMessages.length; i++) {
                errorMessages[i].remove();
            }
            userInput.classList.remove('error');
            passwordInput.classList.remove('error');
            
            // Validar usuario
            if (userInput.value.trim() === '') {
                isValid = false;
                userInput.classList.add('error');
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.textContent = 'Por favor ingresa un nombre de usuario';
                userInput.insertAdjacentElement('afterend', errorMsg);
            }
            
            // Validar contraseña
            if (passwordInput.value.trim() === '') {
                isValid = false;
                passwordInput.classList.add('error');
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.textContent = 'Por favor ingresa una contraseña';
                passwordInput.insertAdjacentElement('afterend', errorMsg);
            }
            
            if (isValid) {
                // datos del formulario
                const datos = {
                    user: userInput.value,
                    password: passwordInput.value
                };

                // Mostrar indicador de carga
                const loadingSwal = Swal.fire({
                    title: 'Actualizando...',
                    html: 'Guardando los cambios en tu perfil',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Enviar datos mediante AJAX
                fetch("controllers/gestion/update-profile.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(datos)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.text();
                })
                .then(text => {
                    // Cerrar indicador de carga
                    loadingSwal.close();
                    
                    try {
                        // Intentar analizar el texto como JSON
                        let data = JSON.parse(text);
                        
                        if (data.success) {
                            // Mostrar mensaje de éxito
                            Swal.fire({
                                icon: 'success',
                                title: '¡Perfil actualizado!',
                                text: data.message || 'El perfil se ha actualizado correctamente.',
                                showConfirmButton: true,
                                confirmButtonText: 'Aceptar',
                                confirmButtonColor: '#4a6fa5'
                            }).then(function() {
                                closeModal();
                                // Limpiar el formulario
                                passwordInput.value = '';
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.error || 'Ocurrió un error al actualizar el perfil.',
                                confirmButtonColor: '#4a6fa5'
                            });
                            console.log("Error al actualizar el perfil:", data.error);
                        }
                    } catch (error) {
                        console.error("Error: Respuesta no es JSON válido:", text);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Se produjo un error inesperado en el servidor.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });
                    }
                })
                .catch(error => {
                    // Cerrar indicador de carga
                    loadingSwal.close();
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Se produjo un error de red o en el servidor.\nPor favor, inténtelo de nuevo.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.error("Error de red o servidor:", error);
                });
            }
        });
        });
    </script>
</body>
</html>