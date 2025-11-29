<?php

require_once '../../core/conexion.php'; // Conexión a la base de datos
require_once '../../core/verificar-sesion.php'; // Verificar Session

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'CUA001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
        
    exit(); 
}

// Verifica si la conexión se estableció correctamente
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Error crítico: No se pudo establecer conexión con la base de datos");
}

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Procesar filtros si existen
$where_clausulas = [];
$params = [];
$tipos = "";

// Busqueda general
if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
    $busqueda = $_GET['busqueda'];
    $where_clausulas[] = "(cc.registro LIKE ? OR 
                            cc.fechaApertura LIKE ? OR 
                            cc.saldoInicial LIKE ? OR 
                            cc.fechaCierre LIKE ? OR 
                            cc.saldoFinal LIKE ? OR 
                            CONCAT(e.nombre, ' ', e.apellido) LIKE ? OR 
                            cc.diferencia LIKE ? OR
                            cc.estado LIKE ? OR
                            cc.numCaja LIKE ?)";
    // Añadimos el parámetro 9 veces para cada campo de búsqueda
    for ($i = 0; $i < 9; $i++) {
        $params[] = "%$busqueda%";
        $tipos .= "s";
    }
}

// Filtro de fecha inicio
if (isset($_GET['fecha_inicio']) && !empty($_GET['fecha_inicio'])) {
    $where_clausulas[] = "cc.fechaApertura >= ?";
    $params[] = $_GET['fecha_inicio'] . " 00:00:00";
    $tipos .= "s";
}

// Filtro de fecha fin
if (isset($_GET['fecha_fin']) && !empty($_GET['fecha_fin'])) {
    $where_clausulas[] = "cc.fechaApertura <= ?";
    $params[] = $_GET['fecha_fin'] . " 23:59:59";
    $tipos .= "s";
}

// Filtro de empleado
if (isset($_GET['empleado']) && !empty($_GET['empleado'])) {
    $where_clausulas[] = "cc.idEmpleado = ?";
    $params[] = $_GET['empleado'];
    $tipos .= "i";
}

// Filtro de estado
if (isset($_GET['estado']) && !empty($_GET['estado'])) {
    $where_clausulas[] = "cc.estado = ?";
    $params[] = $_GET['estado'];
    $tipos .= "s";
}

// Construir la cláusula WHERE
$where = "";
if (!empty($where_clausulas)) {
    $where = "WHERE " . implode(" AND ", $where_clausulas);
}

// Consulta para contar registros totales (para paginación)
$sql_count = "SELECT COUNT(*) as total FROM cajascerradas cc 
                LEFT JOIN empleados e ON cc.idEmpleado = e.id 
                $where";

if (!empty($params)) {
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param($tipos, ...$params);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $row_count = $result_count->fetch_assoc();
} else {
    $result_count = $conn->query($sql_count);
    $row_count = $result_count->fetch_assoc();
}

$total_registros = $row_count['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta SQL principal con paginación
$sql = "SELECT 
            cc.registro AS id,
            DATE_FORMAT(cc.fechaApertura, '%d/%m/%Y %l:%i %p') AS fecha_inicio,
            FORMAT(cc.saldoInicial, 2) AS monto_inicial,
            IFNULL(DATE_FORMAT(cc.fechaCierre, '%d/%m/%Y %l:%i %p'), 'No cerrado') AS fecha_cierre,
            IFNULL(FORMAT(cc.saldoFinal, 2), 'N/A') AS monto_cierre,
            CONCAT(e.nombre, ' ', e.apellido) AS empleado_nombre,
            IFNULL(FORMAT(cc.diferencia, 2), 'N/A') AS diferencia_formateada,
            cc.diferencia AS diferencia_raw,
            cc.numCaja AS numCaja,
            cc.estado AS estado
        FROM cajascerradas cc
        LEFT JOIN empleados e ON cc.idEmpleado = e.id
        $where
        ORDER BY cc.fechaCierre DESC
        LIMIT ?, ?";

// Preparar y ejecutar consulta principal
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $tipos .= "ii"; // Añadir tipos para los parámetros de LIMIT
    $params[] = $inicio;
    $params[] = $registros_por_pagina;
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $inicio, $registros_por_pagina);
    $stmt->execute();
    $resultado = $stmt->get_result();
}

// Obtener lista de empleados para filtros
$sql_empleados = "SELECT id, CONCAT(nombre, ' ', apellido) AS nombre_completo FROM empleados ORDER BY nombre";
$empleados = $conn->query($sql_empleados);

// Obtener valores de filtros actuales para mantenerlos entre páginas
$filtro_busqueda = isset($_GET['busqueda']) ? htmlspecialchars($_GET['busqueda']) : '';
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$filtro_empleado = isset($_GET['empleado']) ? $_GET['empleado'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Cuadres de Caja</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Librería de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
    <style>
        /* Root Variables */
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
            --border: #dfe6e9;
            --background: #f5f6fa;
            --text: #2d3436;
            --gray: #636e72;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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

        /* Container */
        .contenedor {
            margin-inline: auto;
        }

        /* Header */
        .cabeza {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            align-items: center;
            margin-bottom: clamp(1rem, 3vw, 1.5rem);
            padding-bottom: clamp(0.75rem, 2vw, 1rem);
            border-bottom: 1px solid var(--border);
        }

        h1 {
            font-size: clamp(1.25rem, 2vw + 0.5rem, 1.5rem);
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Search Section */
        .search-container {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.5rem;
            width: auto;
            justify-content: flex-end;
        }

        .search-box {
            position: relative;
            width: 250px;
            min-width: auto;
        }

        .search-box i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            pointer-events: none;
        }

        .search-box input {
            width: 100%;
            padding: 0.5rem 0.75rem 0.5rem 2rem;
            border: 1px solid var(--border);
            border-radius: 0.25rem;
            font-size: 0.875rem;
            transition: border-color 0.2s ease;
            min-height: 38px;
            background: white;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(44, 62, 80, 0.1);
        }

        /* Filters Section */
        .filters {
            display: grid;
            grid-template-columns: repeat(3, minmax(150px, 1fr)) auto;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: clamp(0.25rem, 2vw, 0.5rem);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .filter-group label {
            font-size: 0.75rem;
            color: var(--gray);
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.25rem;
            font-size: 0.875rem;
            width: 100%;
            min-height: 38px;
            background: white;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: clamp(1rem, 3vw, 1.5rem);
            border-radius: clamp(0.25rem, 1vw, 0.5rem);
            background: white;
            border: 1px solid var(--border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        th {
            background: var(--primary);
            color: white;
            font-weight: 500;
            text-align: left;
            padding: 0.75rem;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            text-align: left;
        }

        td:last-child {
            text-align: right;
        }

        tr:nth-child(even) {
            background: var(--background);
        }

        /* Mobile Card Design */
        .mobile-cards {
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            padding: 1rem 0;
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: var(--card-shadow);
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }

        .card-subtitle {
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .card-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
        }

        .card-content {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .card-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .card-label {
            font-size: 0.75rem;
            color: var(--gray);
            font-weight: 500;
        }

        .card-value {
            font-size: 0.875rem;
            color: var(--primary);
            font-weight: 600;
        }

        .card-footer {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Status Badge - ESTILOS ACTUALIZADOS */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.85rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            justify-content: center;
            min-width: 90px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-cerrada {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .badge-auditoria {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .badge-pendiente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .badge-negada,
        .badge-cancelada {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Action Buttons */
        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .action-btn {
            width: 2rem;
            height: 2rem;
            padding: 0.4rem;
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            background: white;
        }
        
        /*vista  */
        .view-btn {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        /* imprimir */
        .print-btn {
            background: rgba(108, 117, 125, 0.1);
            color: var(--gray);
        }

        .edit-btn {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            min-height: 38px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-outline {
            background: white;
            border: 1px solid var(--border);
            color: var(--text);
        }

        /* Summary Section */
        .summary {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            margin-top: 1.5rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .pagination li {
            list-style: none;
        }

        .pagination li a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border: 1px solid var(--border);
            border-radius: 0.25rem;
            color: var(--text);
            text-decoration: none;
            font-size: 0.875rem;
            background: white;
        }

        .pagination li a.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Status Colors */
        .positive {
            color: var(--success);
        }

        .negative {
            color: var(--danger);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .container {
                margin: 1rem;
            }
            
            .mobile-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }

            .search-container {
                width: 100%;
            }

            .search-box {
                width: 100%;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .mobile-cards {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .filters {
                grid-template-columns: 1fr;
            }

            .table-container {
                display: none;
            }

            .mobile-cards {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .card {
                margin: 0;
            }

            .card-status {
                position: absolute;
                top: 1rem;
                right: 1rem;
            }

            .badge {
                margin: 0;
            }
        }

        @media (max-width: 480px) {
            .container {
                margin: 0;
                border-radius: 0;
                padding: 1rem;
                width: 100%;
            }

            .search-container {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .card {
                border-radius: 0.5rem;
                margin-bottom: 1rem;
            }

            .card-content {
                gap: 0.75rem;
            }

            .pagination li a {
                width: 2rem;
                height: 2rem;
            }
        }

        /* Print Styles */
        @media print {
            .container {
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .header button,
            .search-container,
            .filters,
            .actions,
            .pagination {
                display: none;
            }

            .table-container {
                display: table;
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .mobile-cards {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
            <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->
             
            <div class="contenedor">
                <div class="cabeza">
                    <h1><i class="fas fa-cash-register"></i> Reporte de Cuadres de Caja</h1>
                </div>
                
                <form id="filtros-form" method="GET" action="" class="filters">
                    <div class="filter-group">
                        <label for="fecha_inicio"><i class="far fa-calendar-alt"></i> Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $filtro_fecha_inicio; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="fecha_fin"><i class="far fa-calendar-alt"></i> Fecha Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?php echo $filtro_fecha_fin; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="empleado"><i class="fas fa-user-tie"></i> Empleado</label>
                        <select id="empleado" name="empleado">
                            <option value="">Todos los empleados</option>
                            <?php while($emp = $empleados->fetch_assoc()): ?>
                                <option value="<?= $emp['id'] ?>" <?= ($filtro_empleado == $emp['id'] ? 'selected' : '') ?>>
                                    <?= $emp['nombre_completo'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="estado"><i class="fa-solid fa-bars-staggered"></i> Estado</label>
                        <select id="estado" name="estado">
                            <option value="">Todos los estados</option>
                            <option value="cerrada" <?= ($filtro_estado == 'cerrada' ? 'selected' : '') ?>>Cerrada</option>
                            <option value="pendiente" <?= ($filtro_estado == 'pendiente' ? 'selected' : '') ?>>Pendiente</option>
                            <!-- <option value="auditoria" <?= ($filtro_estado == 'auditoria' ? 'selected' : '') ?>>Auditoría</option> -->
                            <option value="cancelada" <?= ($filtro_estado == 'cancelada' ? 'selected' : '') ?>>Cancelada</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="global-search"><i class="fa-solid fa-earth-americas"></i> Buscador General</label>
                        <input type="text" id="global-search" name="busqueda" placeholder="Buscar..." value="<?php echo $filtro_busqueda; ?>">
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Aplicar
                        </button>
                        <button type="button" class="btn btn-outline" onclick="limpiarFiltros()">
                            <i class="fas fa-eraser"></i> Limpiar
                        </button>
                    </div>

                </form>
                
                <!-- Vista de tabla para desktop -->
                <div class="table-container">
                    <table id="cuadres-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>N° Caja</th>
                                <th>Fecha Apertura</th>
                                <th>Monto Inicial</th>
                                <th>Fecha Cierre</th>
                                <th>Monto Cierre</th>
                                <th>Empleado</th>
                                <th>Diferencia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_inicial = 0;
                            $total_cierre = 0;
                            $total_diferencia = 0;
                            
                            if ($resultado->num_rows > 0) {
                                while($row = $resultado->fetch_assoc()) {
                                    $monto_inicial = str_replace(',', '', $row['monto_inicial']);
                                    $monto_cierre = ($row['monto_cierre'] != 'N/A') ? str_replace(',', '', $row['monto_cierre']) : 0;
                                    
                                    $total_inicial += $monto_inicial;
                                    $total_cierre += $monto_cierre;
                                    $total_diferencia += $row['diferencia_raw'] ?? 0;
                                    
                                    // Determinar clase de badge según el estado
                                    $estado_lower = strtolower($row['estado']);
                                    $badge_class = 'badge-' . $estado_lower;
                                    
                                    echo "<tr>
                                            <td>{$row['id']}</td>
                                            <td>{$row['numCaja']}</td>
                                            <td>{$row['fecha_inicio']}</td>
                                            <td>{$row['monto_inicial']}</td>
                                            <td>{$row['fecha_cierre']}</td>
                                            <td>{$row['monto_cierre']}</td>
                                            <td>{$row['empleado_nombre']}</td>
                                            <td class='".($row['diferencia_raw'] >= 0 ? 'positive' : 'negative')."'>
                                                {$row['diferencia_formateada']}
                                            </td>
                                            <td>
                                                <span class='badge {$badge_class}'>
                                                    {$row['estado']}
                                                </span>
                                            </td>
                                            <td class='actions'>
                                                <button class='action-btn view-btn' onclick=\"verDetalle('{$row['numCaja']}')\">
                                                    <i class=\"fa-regular fa-eye\"></i>
                                                </button>
                                                <button class='action-btn print-btn' onclick=\"imprimirReporte('{$row['numCaja']}')\">
                                                    <i class='fas fa-print'></i>
                                                </button>
                                            </td>
                                        </tr>";
                                }
                            } else {
                                echo "<tr>
                                        <td colspan='10' class='no-results'>
                                            <i class='far fa-folder-open'></i>
                                            <div>No se encontraron cuadres de caja</div>
                                        </td>
                                    </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Vista de tarjetas para móvil -->
                <div class="mobile-cards">
                    <?php
                    // Reiniciar el puntero del resultado
                    $resultado->data_seek(0);
                    
                    if ($resultado->num_rows > 0) {
                        while($row = $resultado->fetch_assoc()) {
                            // Determinar clase de badge según el estado
                            $estado_lower = strtolower($row['estado']);
                            $badge_class = 'badge-' . $estado_lower;
                            
                            echo "<div class='card'>
                                    <div class='card-header'>
                                        <div>
                                            <div class='card-title'>Caja N° {$row['numCaja']}</div>
                                            <span class='badge {$badge_class}'>
                                                {$row['estado']}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class='card-content'>
                                        <div class='card-item'>
                                            <span class='card-label'>Fecha Apertura</span>
                                            <span class='card-value'>{$row['fecha_inicio']}</span>
                                        </div>
                                        <div class='card-item'>
                                            <span class='card-label'>Fecha Cierre</span>
                                            <span class='card-value'>{$row['fecha_cierre']}</span>
                                        </div>
                                        <div class='card-item'>
                                            <span class='card-label'>Monto Inicial</span>
                                            <span class='card-value'>{$row['monto_inicial']}</span>
                                        </div>
                                        <div class='card-item'>
                                            <span class='card-label'>Monto Cierre</span>
                                            <span class='card-value'>{$row['monto_cierre']}</span>
                                        </div>
                                        <div class='card-item'>
                                            <span class='card-label'>Empleado</span>
                                            <span class='card-value'>{$row['empleado_nombre']}</span>
                                        </div>
                                        <div class='card-item'>
                                            <span class='card-label'>Diferencia</span>
                                            <span class='card-value ".($row['diferencia_raw'] >= 0 ? 'positive' : 'negative')."'>
                                                {$row['diferencia_formateada']}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class='card-footer'>
                                        <div class='actions'>
                                            <button class='action-btn view-btn' onclick=\"verDetalle('{$row['numCaja']}')\">
                                                <i class=\"fa-regular fa-eye\"></i>
                                            </button>
                                            <button class='action-btn print-btn' onclick=\"imprimirReporte('{$row['numCaja']}')\">
                                                <i class='fas fa-print'></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>";
                        }
                    } else {
                        echo "<div class='card'>
                                <div class='card-content'>
                                    <div class='no-results'>
                                        <i class='far fa-folder-open'></i>
                                        <div>No se encontraron cuadres de caja</div>
                                    </div>
                                </div>
                            </div>";
                    }
                    ?>
                </div>
                
                <!-- Paginación actualizada -->
                <?php if ($total_paginas > 0): ?>
                <div class="pagination">
                    <!-- Botón primera página -->
                    <li>
                        <a href="?pagina=1<?php echo construirQueryFiltros($filtro_busqueda, $filtro_fecha_inicio, $filtro_fecha_fin, $filtro_empleado, $filtro_estado); ?>" <?php echo ($pagina_actual == 1) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    
                    <!-- Botón página anterior -->
                    <li>
                        <a href="?pagina=<?php echo max(1, $pagina_actual - 1); ?><?php echo construirQueryFiltros($filtro_busqueda, $filtro_fecha_inicio, $filtro_fecha_fin, $filtro_empleado, $filtro_estado); ?>" <?php echo ($pagina_actual == 1) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                    
                    <!-- Páginas numeradas -->
                    <?php 
                    $start_page = max(1, min($pagina_actual - 2, $total_paginas - 4));
                    $end_page = min($total_paginas, max(5, $pagina_actual + 2));
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <li>
                            <a href="?pagina=<?php echo $i; ?><?php echo construirQueryFiltros($filtro_busqueda, $filtro_fecha_inicio, $filtro_fecha_fin, $filtro_empleado, $filtro_estado); ?>" <?php echo ($i == $pagina_actual) ? 'class="active"' : ''; ?>>
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Botón página siguiente -->
                    <li>
                        <a href="?pagina=<?php echo min($total_paginas, $pagina_actual + 1); ?><?php echo construirQueryFiltros($filtro_busqueda, $filtro_fecha_inicio, $filtro_fecha_fin, $filtro_empleado, $filtro_estado); ?>" <?php echo ($pagina_actual == $total_paginas) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    
                    <!-- Botón última página -->
                    <li>
                        <a href="?pagina=<?php echo $total_paginas; ?><?php echo construirQueryFiltros($filtro_busqueda, $filtro_fecha_inicio, $filtro_fecha_fin, $filtro_empleado, $filtro_estado); ?>" <?php echo ($pagina_actual == $total_paginas) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </div>
                <?php endif; ?>
            </div>
        <!-- TODO EL CONTENIDO POR ENCIMA DE ESTA LINEA -->
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Limpiar búsqueda
            window.limpiarBusqueda = function() {
                $('#global-search').val('');
                // Mantener otros filtros, quitar solo la búsqueda
                const form = $('#filtros-form');
                $('input[name="busqueda"]').remove();
                form.submit();
            };
            
            // Limpiar filtros
            window.limpiarFiltros = function() {
                window.location.href = '<?php echo strtok($_SERVER["PHP_SELF"], '?'); ?>';
            };
        });

        // Funciones para interacción
        function verDetalle(id) {
            window.location.href = `cuadre-detalle.php?numCaja=${id}`;
        }

        function imprimirReporte(id) {
            const invoiceUrl = `../../reports/cuadre/cuadre.php?numCaja=${id}`;
            window.open(invoiceUrl, '_blank');
        }

    </script>

<?php
// Función para construir la parte de query string con los filtros actuales
function construirQueryFiltros($busqueda, $fecha_inicio, $fecha_fin, $empleado, $estado) {
    $params = [];
    
    if (!empty($busqueda)) {
        $params[] = "busqueda=" . urlencode($busqueda);
    }
    
    if (!empty($fecha_inicio)) {
        $params[] = "fecha_inicio=" . urlencode($fecha_inicio);
    }
    
    if (!empty($fecha_fin)) {
        $params[] = "fecha_fin=" . urlencode($fecha_fin);
    }
    
    if (!empty($empleado)) {
        $params[] = "empleado=" . urlencode($empleado);
    }
    
    if (!empty($estado)) {
        $params[] = "estado=" . urlencode($estado);
    }

    return !empty($params) ? '&' . implode('&', $params) : '';
}
?>
</body>
</html>

<?php
$conn->close();
?>