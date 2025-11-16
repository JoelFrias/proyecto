<?php

require_once '../../core/verificar-sesion.php'; // Verificar Session
require_once '../../core/conexion.php'; // Conexión a la base de datos

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'ALM002';
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

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

$sql_base = "SELECT
                ti.no AS no,
                DATE_FORMAT(ti.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                CONCAT(e1.nombre, ' ', e1.apellido) AS emisor,
                CONCAT(e2.nombre, ' ', e2.apellido) AS destinatario,
                ti.tipo_mov AS tipo
            FROM
                transacciones_inv AS ti
            INNER JOIN empleados AS e1
                ON e1.id = ti.id_emp_reg
            INNER JOIN empleados AS e2
                ON e2.id = ti.id_emp_des 
            WHERE
                1=1";

$params = [];
$types = "";

// Procesar filtros
$filtros = [];

// Si hay POST, usamos los valores de POST; si no, verificamos GET para mantener los filtros en la paginación
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Usar valores de POST
    $filtro_tipo = !empty($_POST['tipo']) ? $_POST['tipo'] : '';
    $filtro_desde = !empty($_POST['desde']) ? $_POST['desde'] : '';
    $filtro_hasta = !empty($_POST['hasta']) ? $_POST['hasta'] : '';
    $filtro_buscador = !empty($_POST['buscador']) ? $_POST['buscador'] : '';
} else {
    // Usar valores de GET si existen
    $filtro_tipo = !empty($_GET['tipo']) ? $_GET['tipo'] : '';
    $filtro_desde = !empty($_GET['desde']) ? $_GET['desde'] : '';
    $filtro_hasta = !empty($_GET['hasta']) ? $_GET['hasta'] : '';
    $filtro_buscador = !empty($_GET['buscador']) ? $_GET['buscador'] : '';
}

// Aplicar filtros a la consulta SQL
if (!empty($filtro_tipo)) {
    $sql_base .= " AND ti.tipo_mov = ?";
    $params[] = $filtro_tipo;
    $types .= "s";
    $filtros['tipo'] = $filtro_tipo;
}

if (!empty($filtro_desde)) {
    $filtro_desde_full = $filtro_desde . ' 00:00:00';
    $sql_base .= " AND ti.fecha >= ?";
    $params[] = $filtro_desde_full;
    $types .= "s";
    $filtros['desde'] = $filtro_desde;
}

if (!empty($filtro_hasta)) {
    $filtro_hasta_full = $filtro_hasta . ' 23:59:59'; 
    $sql_base .= " AND ti.fecha <= ?";
    $params[] = $filtro_hasta_full;
    $types .= "s";
    $filtros['hasta'] = $filtro_hasta; 
}

if (!empty($filtro_buscador)) {
    $sql_base .= " AND (ti.no LIKE ? OR CONCAT(e1.nombre, ' ', e1.apellido) LIKE ? OR CONCAT(e2.nombre, ' ', e2.apellido) LIKE ?)";
    $searchTerm = "%" . $filtro_buscador . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
    $filtros['buscador'] = $filtro_buscador;
}

// echo $sql_base;
// exit();

// Consulta para el total de registros (para paginación)
$sql_count = "SELECT COUNT(*) as total FROM ($sql_base) AS subquery";

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
$sql = "$sql_base ORDER BY ti.fecha DESC LIMIT ?, ?";

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
            --success: #187caeff;
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

        /* Contenedor de botones */
        .buttons-top {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        /* Estilo base del botón */
        .btntop {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            flex: 1;
            min-width: 180px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .btntop::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btntop:hover::before {
            width: 300px;
            height: 300px;
        }

        .btntop i {
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }

        .btntop span {
            position: relative;
            z-index: 1;
        }

        /* Botón de Entrega (Azul) */
        .btntop-entrega {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .btntop-entrega:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            transform: translateY(-2px);
        }

        .btntop-entrega:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }

        /* Botón de Retorno (Rojo) */
        .btntop-retorno {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btntop-retorno:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }

        .btntop-retorno:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .buttons-top {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .btntop {
                width: 100%;
                min-width: unset;
                padding: 0.875rem 1.25rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .btntop {
                padding: 0.75rem 1rem;
                font-size: 0.85rem;
            }
            
            .btntop i {
                font-size: 1rem;
            }
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
            background: #9de6ffff;
            color: var(--success);
        }

        .status-pending {
            background: #f9e1bcff;
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
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

            <!-- Contenedor principal -->
            <div class="contenedor">

                <div class="buttons-top">
                    <button class="btntop btntop-entrega" onclick="window.location.href='inventario-transaccion.php'">
                        <i class="fas fa-box-open"></i>
                        <span>Realizar Entrega</span>
                    </button>
                    <button class="btntop btntop-retorno" onclick="window.location.href='inventario-devAlmacen.php'">
                        <i class="fas fa-undo-alt"></i>
                        <span>Realizar Retorno</span>
                    </button>
                </div>

                <div class="cabeza">
                    <h1>Registro de  Movimientos de Inventario</h1>
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
                                <label>Tipo de Movimiento</label>
                                <select name="tipo" id="tipo">
                                    <option value="" disabled selected>Seleccionar</option>
                                    <option value="retorno" <?php echo ($filtro_tipo == 'retorno') ? 'selected' : ''; ?>>Retorno</option>
                                    <option value="entrega" <?php echo ($filtro_tipo == 'entrega') ? 'selected' : ''; ?>>Entrega</option>
                                </select>

                            </div>
                        </div>

                        <div class="search-bar">
                            <div class="search-input">
                                <i class="fas fa-search"></i>
                                <input type="text" id="buscador" name="buscador" value="<?php echo $filtro_buscador; ?>" placeholder="Buscar movimiento por número o empleado">
                            </div>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                                Buscar
                            </button>
                            <button class="btn btn-secondary" type="reset" onclick="window.location.href='registro-transacciones.php'">
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
                                <th>No.</th>
                                <th>Fecha y Hora</th>
                                <th>Emisor</th>
                                <th>Destinatario</th>
                                <th>Tipo de Movimiento</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php
                                if ($results->num_rows > 0) {
                                    while ($row = $results->fetch_assoc()) {
                                        // FORMATO DE MONEDA
                                        // $valor_totalf = number_format($row['valor_total'], 2, '.', ',');

                                        // Determinar la clase CSS del estado
                                        $estadoClass = "";
                                        $tipotexto = "";
                                        if ($row['tipo'] == "entrega") {
                                            $tipotexto = "Entrega a Empleado";
                                            $estadoClass = "paid";
                                        } elseif ($row['tipo'] == "retorno") {
                                            $tipotexto = "Retorno al Almacén";
                                            $estadoClass = "pending";
                                        } elseif ($row['tipo'] == "Cancelada") {
                                            $estadoClass = "cancel";
                                        }

                                        echo "
                                            <tr>
                                                <td>{$row['no']}</td>
                                                <td>{$row['fecha']}</td>
                                                <td>{$row['emisor']}</td>
                                                <td>{$row['destinatario']}</td>
                                                <td><span class='status status-{$estadoClass}'>{$tipotexto}</span></td>
                                                <td>
                                                    <button class='btn btn-secondary' onclick=\"window.open('../../reports/transacciones/reporte-transaccion-reimpresion.php?no={$row['no']}', '_blank')\">
                                                        Ver Reporte
                                                    </button>
                                                </td>
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
                                // $totalf1 = number_format($row1['valor_total'], 2, '.', ',');

                                // Determinar la clase CSS del estado
                                $estadoClass1 = "";
                                $tipotexto1 = "";
                                if ($row1['tipo'] == "entrega") {
                                    $tipotexto1 = "Entrega a Empleado";
                                    $estadoClass1 = "paid";
                                } elseif ($row1['tipo'] == "retorno") {
                                    $tipotexto1 = "Retorno al Almacén";
                                    $estadoClass1 = "pending";
                                } elseif ($row1['tipo'] == "Cancelada") {
                                    $estadoClass1 = "cancel";
                                }
                        ?>
                                <!-- Factura individual -->
                                <div class="invoice-card">
                                    <div class="invoice-card-header">
                                        <span class="invoice-number">No. <?php echo $row1['no']; ?></span>
                                        <span class="status status-<?php echo $estadoClass1; ?>"><?php echo $tipotexto1; ?></span>
                                    </div>
                                    <div class="invoice-card-body">
                                        <div class="invoice-detail">
                                            <span class="detail-label">Fecha y Hora</span>
                                            <span class="detail-value"><?php echo $row1['fecha']; ?></span>
                                        </div>
                                        <div class="invoice-detail">
                                            <span class="detail-label">Emisor</span>
                                            <span class="detail-value"><?php echo $row1['emisor']; ?></span>
                                        </div>
                                        <div class="invoice-detail">
                                            <span class="detail-label">Destinatario</span>
                                            <span class="detail-value"><?php echo $row1['destinatario']; ?></span>
                                        </div>
                                        <div class="">
                                            <button class="btn btn-secondary" onclick="window.open('../../reports/transacciones/reporte-transaccion-reimpresion.php?no=<?php echo $row1['no']; ?>', '_blank')">Ver Reporte</button>
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