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
    header('Location: ../../frontend/auth/login.php'); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header("Location: ../../frontend/auth/login.php?session_expired=session_expired"); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

require_once '../../core/conexion.php';

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

$sql_base = "SELECT
            f.numFactura AS numf,
            f.tipoFactura AS tipof,
            DATE_FORMAT(f.fecha, '%d/%m/%Y %l:%i %p') AS fechaf,
            f.total_ajuste AS totalf,
            CONCAT(c.nombre, ' ', c.apellido) AS nombrec,
            f.balance AS balancef,
            CONCAT(e.nombre, ' ', e.apellido) AS nombree,
            f.estado AS estadof
        FROM
            facturas AS f
        JOIN clientes AS c ON c.id = f.idCliente
        JOIN empleados AS e ON e.id = f.idEmpleado
        WHERE 1=1";

$params = [];
$types = "";

// Procesar filtros
$filtros = [];

// Si hay POST, usamos los valores de POST; si no, verificamos GET para mantener los filtros en la paginación
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Usar valores de POST
    $filtro_tipo = !empty($_POST['tipo']) ? $_POST['tipo'] : '';
    $filtro_estado = !empty($_POST['estado']) ? $_POST['estado'] : '';
    $filtro_desde = !empty($_POST['desde']) ? $_POST['desde'] : '';
    $filtro_hasta = !empty($_POST['hasta']) ? $_POST['hasta'] : '';
    $filtro_buscador = !empty($_POST['buscador']) ? $_POST['buscador'] : '';
} else {
    // Usar valores de GET si existen
    $filtro_tipo = !empty($_GET['tipo']) ? $_GET['tipo'] : '';
    $filtro_estado = !empty($_GET['estado']) ? $_GET['estado'] : '';
    $filtro_desde = !empty($_GET['desde']) ? $_GET['desde'] : '';
    $filtro_hasta = !empty($_GET['hasta']) ? $_GET['hasta'] : '';
    $filtro_buscador = !empty($_GET['buscador']) ? $_GET['buscador'] : '';
}

// Aplicar filtros a la consulta SQL
if (!empty($filtro_tipo)) {
    $sql_base .= " AND f.tipoFactura = ?";
    $params[] = $filtro_tipo;
    $types .= "s";
    $filtros['tipo'] = $filtro_tipo;
}
if (!empty($filtro_estado)) {
    $sql_base .= " AND f.estado = ?";
    $params[] = $filtro_estado;
    $types .= "s";
    $filtros['estado'] = $filtro_estado;
}
if (!empty($filtro_desde)) {
    $sql_base .= " AND f.fecha >= ?";
    $params[] = $filtro_desde;
    $types .= "s";
    $filtros['desde'] = $filtro_desde;
}

if (!empty($filtro_hasta)) {
    $filtro_hasta_full = $filtro_hasta . ' 23:59:59'; 
    
    $sql_base .= " AND f.fecha <= ?";
    $params[] = $filtro_hasta_full;
    $types .= "s";
    
    // 3. Mantener el valor original de la fecha para la URL/el formulario
    $filtros['hasta'] = $filtro_hasta; 
}

if (!empty($filtro_buscador)) {
    $sql_base .= " AND (f.numFactura LIKE ? OR CONCAT(c.nombre, ' ', c.apellido) LIKE ? OR CONCAT(e.nombre, ' ', e.apellido) LIKE ?)";
    $searchTerm = "%" . $filtro_buscador . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
    $filtros['buscador'] = $filtro_buscador;
}

// Consulta para el total de registros (para paginación)
$sql_count = "SELECT COUNT(*) as total FROM ($sql_base GROUP BY f.numFactura) AS subquery";

// Preparar y ejecutar consulta para conteo
if (!empty($params)) {
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $row_count = $result_count->fetch_assoc();
} else {
    $result_count = $conn->query($sql_count);
    $row_count = $result_count->fetch_assoc();
}

$total_registros = $row_count['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta principal con paginación
$sql = "$sql_base GROUP BY f.numFactura ORDER BY f.fecha DESC LIMIT ?, ?";

// Preparar y ejecutar consulta principal
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $types .= "ii"; // Tipos para LIMIT
    $all_params = array_merge($params, [$inicio, $registros_por_pagina]);
    $stmt->bind_param($types, ...$all_params);
    $stmt->execute();
    $results = $stmt->get_result();
    
    // Para vista móvil (misma consulta)
    $stmt1 = $conn->prepare($sql);
    $stmt1->bind_param($types, ...$all_params);
    $stmt1->execute();
    $results1 = $stmt1->get_result();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $inicio, $registros_por_pagina);
    $stmt->execute();
    $results = $stmt->get_result();
    
    // Para vista móvil (misma consulta)
    $stmt1 = $conn->prepare($sql);
    $stmt1->bind_param("ii", $inicio, $registros_por_pagina);
    $stmt1->execute();
    $results1 = $stmt1->get_result();
}

// Función para construir la URL con los filtros actuales
function construirQueryFiltros($filtros) {
    $query = '';
    foreach ($filtros as $key => $value) {
        if (!empty($value)) {
            $query .= "&{$key}=" . urlencode($value);
        }
    }
    return $query;
}

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Facturas</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->

    <style>
        :root {
            --primary-blue: #4285f4;
            --hover-blue: #2b7de9;
            --background: #f5f6fa;
            --card-bg:rgb(252, 252, 252);
            --border: #e0e4ec;
            --text-secondary: #718096;
            --success: #48bb78;
            --warning: #ed8936;
            --shadow: 0 2px 4px rgba(0,0,0,0.08);
            --radius: 10px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: var(--background);
         
        }

        .contenedor {
            max-width: 1400px;
            margin: 1rem;
        }

        .cabeza {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .cabeza h1 {
            font-size: 24px;
            font-weight: 600;
            margin-left: 10px; /* Agrega margen a la izquierda */
           
   
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background-color: white;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        input, select {
            padding: 0.625rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.875rem;
            background: var(--card-bg);
            transition: all 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }

        .search-bar {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .search-input {
            flex: 1;
            position: relative;
        }

        .search-input i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .search-input input {
            width: 100%;
            padding-left: 2.5rem;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--hover-blue);
        }

        .btn-secondary {
            background: var(--card-bg);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--background);
        }

        .table-container {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: block;
            background-color: white;
        }

        .mobile-cards {
            display: none;
            gap: 1rem;
            margin-top: 1rem;
        }

        .mobile-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .invoice-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: var(--shadow);
        }

        .invoice-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .invoice-number {
            font-weight: 600;
        }

        .invoice-card-body {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .invoice-detail {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }

        .detail-value {
            font-size: 0.875rem;
            font-weight: 500;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--background);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem;
        }

        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-paid {
            background: #e6ffec;
            color: var(--success);
        }

        .status-pending {
            background: #fff5e6;
            color: var(--warning);
        }

        .status-cancel {
            background:rgb(252, 206, 206);
            color: rgb(252, 85, 85);
        }

        .note {
            color: var(--text-secondary);
            font-size: 0.75rem;
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            /* .cabeza {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            } */

            .filters {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .search-bar {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .table-container {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .invoice-detail {
                padding: 0.75rem;
                background: var(--background);
                border-radius: var(--radius);
                border: none;
            }
            .contenedor {
                margin: 0rem;
            }
        }

        @media (min-width: 390px) and (max-width: 768px) {
            .filters {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .mobile-cards-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Estilo específico para el botón en tarjetas móviles */
        @media (max-width: 768px) {
            .invoice-card-body > div:last-child {
                display: flex;
                justify-content: flex-end;
                grid-column: 1 / -1; /* Ocupa todo el ancho disponible */
                margin-top: 4px;
            }
            
            .invoice-card-body > div:last-child .btn {
                width: auto; /* Ancho automático en lugar de 100% */
            }
        }

        /* Ajuste para pantallas muy pequeñas */
        @media (max-width: 480px) {
            .invoice-card-body > div:last-child {
                justify-content: flex-end;
            }
        }

        /* Estilos para la paginación */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            list-style: none;
            padding: 0;
        }
        
        .pagination li {
            display: inline-block;
            margin: 0 2px;
        }
        
        .pagination a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            color: #555;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background-color: #f5f5f5;
        }
        
        .pagination a.active {
            background-color: #2c3e50;
            color: white;
            border-color: #2c3e50;
        }
        
        .pagination a.disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        
        /* Estilos para la información de paginación */
        .pagination-info {
            text-align: center;
            margin-top: 10px;
            color: #777;
            font-size: 0.9rem;
        }
        
        /* Ajustes responsivos */
        @media (max-width: 768px) {
            .pagination a {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }
        }
    </style>
    
</head>
<body>

    <?php 
        if (isset($_GET['error']) && $_GET['error'] == 'missing_numFactura') {
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se encontró la factura seleccionada.',
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    window.location.href = 'factura-registro.php';
                });
            </script>";
        }
    ?>


    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../frontend/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

            <!-- Contenedor principal -->
            <div class="contenedor">
                <div class="cabeza">
                    <h1>Registro de Facturas</h1>
                </div>
        
                <form action="" method="post">
                    <div class="card">
                        <div class="filters">
                            <div class="filter-group">
                                <label>Desde</label>
                                <input type="date" name="desde" value="<?php echo $filtro_desde; ?>">
                            </div>
                            <div class="filter-group">
                                <label>Hasta</label>
                                <input type="date" name="hasta" value="<?php echo $filtro_hasta; ?>">
                            </div>
                            <div class="filter-group">
                                <label>Tipo de Factura</label>
                                <select name="tipo" id="tipo">
                                    <option value="" disabled selected>Seleccionar</option>
                                    <option value="credito" <?php echo ($filtro_tipo == 'credito') ? 'selected' : ''; ?>>Crédito</option>
                                    <option value="contado" <?php echo ($filtro_tipo == 'contado') ? 'selected' : ''; ?>>Contado</option>
                                </select>

                            </div>
                            <div class="filter-group">
                                <label>Estado de Factura</label>
                                <select name="estado" id="estado">
                                    <option value="" disabled selected>Seleccionar</option>
                                    <option value="Pagada" <?php echo ($filtro_estado == 'Pagada') ? 'selected' : ''; ?>>Pagada</option>
                                    <option value="Pendiente" <?php echo ($filtro_estado == 'Pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="Cancelada" <?php echo ($filtro_estado == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                                </select>
                            </div>
                        </div>

                        <div class="search-bar">
                            <div class="search-input">
                                <i class="fas fa-search"></i>
                                <input type="text" id="buscador" name="buscador" value="<?php echo $filtro_buscador; ?>" placeholder="Buscar factura por número, cliente o vendedor">
                            </div>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                                Buscar
                            </button>
                            <button class="btn btn-secondary" type="reset" onclick="window.location.href='factura-registro.php'">
                                <i class="fas fa-redo"></i>
                                Limpiar
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Desktop Table View -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>No. Factura</th>
                                <th>Tipo</th>
                                <th>Fecha y Hora</th>
                                <th>Total</th>
                                <th>Cliente</th>
                                <th>Balance</th>
                                <th>Vendedor</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php
                                if ($results->num_rows > 0) {
                                    while ($row = $results->fetch_assoc()) {
                                        // FORMATO DE MONEDA
                                        $totalf = number_format($row['totalf'], 2, '.', ',');
                                        $balancef = number_format($row['balancef'], 2, '.', ',');

                                        // Determinar la clase CSS del estado
                                        $estadoClass = "";
                                        if ($row['estadof'] == "Pagada") {
                                            $estadoClass = "paid";
                                        } elseif ($row['estadof'] == "Pendiente") {
                                            $estadoClass = "pending";
                                        } elseif ($row['estadof'] == "Cancelada") {
                                            $estadoClass = "cancel";
                                        }

                                        echo "
                                            <tr>
                                                <td>{$row['numf']}</td>
                                                <td>{$row['tipof']}</td>
                                                <td>{$row['fechaf']}</td>
                                                <td>RD$ {$totalf}</td>
                                                <td>{$row['nombrec']}</td>
                                                <td>RD$ {$balancef}</td>
                                                <td>{$row['nombree']}</td>
                                                <td><span class='status status-{$estadoClass}'>{$row['estadof']}</span></td>
                                                <td><button class='btn btn-secondary' onclick=\"window.location.href='factura-detalle.php?numFactura={$row['numf']}'\">Ver Detalles</button></td>
                                            </tr>
                                        ";
                                    }
                                } else {
                                    echo "<tr>
                                            <td colspan='9'>No se encontraron resultados.</td>
                                        </tr>";
                                }
                            ?>

                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards View -->
                <div class="mobile-cards">
                    <div class="mobile-cards-grid">
                        <?php
                        if ($results1->num_rows > 0) {
                            while ($row1 = $results1->fetch_assoc()) {
                                // FORMATO DE MONEDA
                                $totalf1 = number_format($row1['totalf'], 2, '.', ',');
                                $balancef1 = number_format($row1['balancef'], 2, '.', ',');

                                // Determinar la clase CSS del estado
                                $estadoClass1 = "";
                                if ($row1['estadof'] == "Pagada") {
                                    $estadoClass1 = "paid";
                                } elseif ($row1['estadof'] == "Pendiente") {
                                    $estadoClass1 = "pending";
                                } elseif ($row1['estadof'] == "Cancelada") {
                                    $estadoClass1 = "cancel";
                                }
                        ?>
                                <!-- Factura individual -->
                                <div class="invoice-card">
                                    <div class="invoice-card-header">
                                        <span class="invoice-number">No. <?php echo $row1['numf']; ?></span>
                                        <span class="status status-<?php echo $estadoClass1; ?>"><?php echo $row1['estadof']; ?></span>
                                    </div>
                                    <div class="invoice-card-body">
                                        <div class="invoice-detail">
                                            <span class="detail-label">Cliente</span>
                                            <span class="detail-value"><?php echo $row1['nombrec']; ?></span>
                                        </div>
                                        <div class="invoice-detail">
                                            <span class="detail-label">Tipo</span>
                                            <span class="detail-value"><?php echo $row1['tipof']; ?></span>
                                        </div>
                                        <div class="invoice-detail">
                                            <span class="detail-label">Fecha y Hora</span>
                                            <span class="detail-value"><?php echo $row1['fechaf']; ?></span>
                                        </div>
                                        <div class="invoice-detail">
                                            <span class="detail-label">Total</span>
                                            <span class="detail-value">RD$ <?php echo $totalf1; ?></span>
                                        </div>
                                        <div class="invoice-detail">
                                            <span class="detail-label">Balance</span>
                                            <span class="detail-value">RD$ <?php echo $balancef1; ?></span>
                                        </div>
                                        <div class="invoice-detail">
                                            <span class="detail-label">Cajero</span>
                                            <span class="detail-value"><?php echo $row1['nombree']; ?></span>
                                        </div>
                                        <div class="">
                                            <button class='btn btn-secondary' onclick="window.location.href='factura-detalle.php?numFactura=<?php echo $row1['numf']; ?>'">Ver Detalles</button>
                                        </div>
                                    </div>
                                </div>
                        <?php
                            }
                        } else {
                            echo "<p class=\"note\">No se encontraron resultados.</p>";
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>

                <!-- Información adicional de paginación -->
                <div class="pagination-info">
                    Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                </div>

                <div class="pagination">
                    <!-- Botón primera página -->
                    <li>
                        <a href="?pagina=1<?php echo construirQueryFiltros($filtros); ?>" <?php echo ($pagina_actual == 1) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    
                    <!-- Botón página anterior -->
                    <li>
                        <a href="?pagina=<?php echo max(1, $pagina_actual - 1); ?><?php echo construirQueryFiltros($filtros); ?>" <?php echo ($pagina_actual == 1) ? 'class="disabled"' : ''; ?>>
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
                            <a href="?pagina=<?php echo $i; ?><?php echo construirQueryFiltros($filtros); ?>" <?php echo ($i == $pagina_actual) ? 'class="active"' : ''; ?>>
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Botón página siguiente -->
                    <li>
                        <a href="?pagina=<?php echo min($total_paginas, $pagina_actual + 1); ?><?php echo construirQueryFiltros($filtros); ?>" <?php echo ($pagina_actual == $total_paginas) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    
                    <!-- Botón última página -->
                    <li>
                        <a href="?pagina=<?php echo $total_paginas; ?><?php echo construirQueryFiltros($filtros); ?>" <?php echo ($pagina_actual == $total_paginas) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </div>
                <?php endif; ?>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA ARRIBA DE ESTA LINEA -->
        </div>
    </div>

</body>
</html>