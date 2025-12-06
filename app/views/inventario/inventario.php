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
}
require_once '../../../core/verificar-sesion.php'; // Verificar Session

// Validar permisos de usuario
require_once '../../../core/validar-permisos.php';
$permiso_necesario = 'ALM001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
    exit(); 
}

// Inicializar variables de búsqueda y filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$filtro_tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : "";
$filtro_disponibilidad = isset($_GET['disponibilidad']) ? trim($_GET['disponibilidad']) : "";
$filtros = array();

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Construir la consulta SQL base
$sql_base = "SELECT
            p.id,
            p.descripcion AS producto,
            pt.descripcion AS tipo_producto,
            p.idTipo,
            p.existencia AS existencia,
            i.existencia AS existencia_inventario,
            p.precioCompra,
            p.precioVenta1,
            p.precioVenta2,
            p.reorden,
            CONCAT('$',p.precioCompra) AS Costo,
            CONCAT('$',p.precioVenta1, ', $',p.precioVenta2) AS PreciosVentas,
            CASE
                WHEN i.existencia = 0 THEN 'Agotado'
                WHEN i.existencia <= p.reorden THEN 'Casi Agotado'
                ELSE 'Disponible'
            END AS disponiblidad_inventario
        FROM
            productos AS p
        INNER JOIN inventario AS i
            ON p.id = i.idProducto
        LEFT JOIN productos_tipo AS pt
            ON p.idTipo = pt.id
        WHERE
            p.activo = TRUE";

// Inicializar parámetros
$params = [];
$types = "";

// Filtro de búsqueda por descripción
if (!empty($search)) {
    $sql_base .= " AND (p.descripcion LIKE ? OR pt.descripcion LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
    $filtros['search'] = $search;
}

// Filtro por tipo de producto
if (!empty($filtro_tipo)) {
    $sql_base .= " AND p.idTipo = ?";
    $params[] = $filtro_tipo;
    $types .= "i";
    $filtros['tipo'] = $filtro_tipo;
}

// Filtro por disponibilidad
if (!empty($filtro_disponibilidad)) {
    switch ($filtro_disponibilidad) {
        case 'Disponible':
            $sql_base .= " AND i.existencia > p.reorden";
            break;
        case 'Casi Agotado':
            $sql_base .= " AND i.existencia <= p.reorden AND i.existencia > 0";
            break;
        case 'Agotado':
            $sql_base .= " AND i.existencia = 0";
            break;
    }
    $filtros['disponibilidad'] = $filtro_disponibilidad;
}

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
$sql = "$sql_base ORDER BY p.descripcion ASC LIMIT ?, ?";

// Preparar y ejecutar consulta principal
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $types .= "ii"; // Agregar tipos para LIMIT
    $all_params = array_merge($params, [$inicio, $registros_por_pagina]);
    $stmt->bind_param($types, ...$all_params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Para vista móvil (misma consulta)
    $stmt_mobile = $conn->prepare($sql);
    $stmt_mobile->bind_param($types, ...$all_params);
    $stmt_mobile->execute();
    $result_mobile = $stmt_mobile->get_result();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $inicio, $registros_por_pagina);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Para vista móvil (misma consulta)
    $stmt_mobile = $conn->prepare($sql);
    $stmt_mobile->bind_param("ii", $inicio, $registros_por_pagina);
    $stmt_mobile->execute();
    $result_mobile = $stmt_mobile->get_result();
}

// Función para construir la URL con los filtros actuales
function construirQueryFiltros($filtros) {
    $query = '';
    foreach ($filtros as $key => $value) {
        if (!empty($value) || $value === '0') {
            $query .= "&{$key}=" . urlencode($value);
        }
    }
    return $query;
}

// Obtener tipos de producto
$query_tipos = "SELECT id, descripcion FROM productos_tipo ORDER BY descripcion ASC";
$result_tipos = $conn->query($query_tipos);

if (!$result_tipos) {
    die("Error en consulta de tipos: " . $conn->error);
}

$tipos_producto = [];
while ($row_tipo = $result_tipos->fetch_assoc()) {
    $tipos_producto[] = $row_tipo;
}

// Consultas para estadísticas
$totalProductos = $conn->query("SELECT COUNT(*) as total FROM inventario")->fetch_assoc()['total'];

$totalCategorias = $conn->query("SELECT COUNT(DISTINCT idTipo) as total FROM inventario JOIN productos ON inventario.idProducto = productos.id WHERE productos.activo = TRUE")->fetch_assoc()['total'];

$casiAgotados = $conn->query("SELECT COUNT(*) as total FROM inventario JOIN productos ON inventario.idProducto = productos.id WHERE inventario.existencia <= productos.reorden AND inventario.existencia > 0 AND productos.activo = TRUE")->fetch_assoc()['total'];

$noDisponibles = $conn->query("SELECT COUNT(*) as total FROM inventario JOIN productos ON inventario.idProducto = productos.id WHERE inventario.existencia = 0 AND productos.activo = TRUE")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Inventario</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/inventario.css">
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Estilos para los filtros */
        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .filter-group select,
        .filter-group input[type="text"] {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            background-color: white;
            transition: border-color 0.2s;
            width: 100%;
        }

        .filter-group select {
            cursor: pointer;
        }

        .filter-group select:focus,
        .filter-group input[type="text"]:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-group .search-input-wrapper {
            position: relative;
            width: 100%;
        }

        .filters-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .btn-filter {
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-filter-apply {
            background-color: #3b82f6;
            color: white;
        }

        .btn-filter-apply:hover {
            background-color: #2563eb;
        }

        .btn-filter-clear {
            background-color: #f3f4f6;
            color: #374151;
        }

        .btn-filter-clear:hover {
            background-color: #e5e7eb;
        }

        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }

        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background-color: #dbeafe;
            color: #1e40af;
            border-radius: 9999px;
            font-size: 0.813rem;
        }

        .filter-tag button {
            background: none;
            border: none;
            color: #1e40af;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filters-actions {
                flex-direction: column;
            }

            .btn-filter {
                width: 100%;
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
 
    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../../app/views/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

            <div class="general-container">
                <div class="header">
                    <h1>Almacén Principal de Productos</h1>
                </div>

                <!-- Sección de filtros -->
                <div class="filters-section">
                    <form method="GET" action="" id="filterForm">
                        
                        <div class="filters-grid">
                            <!-- Filtro por búsqueda -->
                            <div class="filter-group">
                                <label for="search">Buscar Producto</label>
                                <div class="search-input-wrapper">
                                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px; position: absolute; left: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <path d="m21 21-4.3-4.3"></path>
                                    </svg>
                                    <input 
                                        type="text" 
                                        id="search" 
                                        name="search" 
                                        value="<?php echo htmlspecialchars($search ?? ''); ?>" 
                                        placeholder="Buscar por descripción o tipo..."
                                        autocomplete="off"
                                        style="padding-left: 35px;"
                                    >
                                </div>
                            </div>

                            <!-- Filtro por tipo de producto -->
                            <div class="filter-group">
                                <label for="tipo">Tipo de Producto</label>
                                <select name="tipo" id="tipo">
                                    <option value="">Todos los tipos</option>
                                    <?php foreach ($tipos_producto as $tipo): ?>
                                        <option value="<?php echo $tipo['id']; ?>" 
                                            <?php echo ($filtro_tipo == $tipo['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipo['descripcion']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Filtro por disponibilidad -->
                            <div class="filter-group">
                                <label for="disponibilidad">Disponibilidad</label>
                                <select name="disponibilidad" id="disponibilidad">
                                    <option value="">Todas las disponibilidades</option>
                                    <option value="Disponible" <?php echo ($filtro_disponibilidad === 'Disponible') ? 'selected' : ''; ?>>Disponible</option>
                                    <option value="Casi Agotado" <?php echo ($filtro_disponibilidad === 'Casi Agotado') ? 'selected' : ''; ?>>Casi Agotado</option>
                                    <option value="Agotado" <?php echo ($filtro_disponibilidad === 'Agotado') ? 'selected' : ''; ?>>Agotado</option>
                                </select>
                            </div>
                        </div>

                        <div class="filters-actions">
                            <button type="button" class="btn-filter btn-filter-clear" onclick="limpiarFiltros()">
                                <i class="fas fa-times"></i> Limpiar filtros
                            </button>
                            <button type="submit" class="btn-filter btn-filter-apply">
                                <i class="fas fa-filter"></i> Aplicar filtros
                            </button>
                        </div>

                        <!-- Mostrar filtros activos -->
                        <?php if (!empty($filtros)): ?>
                        <div class="active-filters">
                            <?php if (!empty($search)): ?>
                                <span class="filter-tag">
                                    Búsqueda: "<?php echo htmlspecialchars($search); ?>"
                                    <button type="button" onclick="removerFiltro('search')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($filtro_tipo)): ?>
                                <?php 
                                $tipo_nombre = '';
                                foreach ($tipos_producto as $tipo) {
                                    if ($tipo['id'] == $filtro_tipo) {
                                        $tipo_nombre = $tipo['descripcion'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="filter-tag">
                                    Tipo: <?php echo htmlspecialchars($tipo_nombre); ?>
                                    <button type="button" onclick="removerFiltro('tipo')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($filtro_disponibilidad)): ?>
                                <span class="filter-tag">
                                    Disponibilidad: <?php echo htmlspecialchars($filtro_disponibilidad); ?>
                                    <button type="button" onclick="removerFiltro('disponibilidad')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="icon-container orange">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                        </div>
                        <div class="stat-info">
                            <p>Total Productos</p>
                            <h2><?php echo htmlspecialchars($totalProductos, FILTER_SANITIZE_FULL_SPECIAL_CHARS); ?></h2>
                        </div>
                        <div class="stat-footer">
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="icon-container green">
                                <i class="fa-solid fa-box-tissue"></i>
                            </div>
                        </div>
                        <div class="stat-info">
                            <p>Total Categorías</p>
                            <h2><?php echo htmlspecialchars($totalCategorias, FILTER_SANITIZE_FULL_SPECIAL_CHARS); ?></h2>
                        </div>
                        <div class="stat-footer">
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="icon-container blue">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                        </div>
                        <div class="stat-info">
                            <p>Casi Agotados</p>
                            <h2><?php echo htmlspecialchars($casiAgotados, FILTER_SANITIZE_FULL_SPECIAL_CHARS); ?></h2>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="icon-container red">
                                <i class="fa-solid fa-skull"></i>
                            </div>
                        </div>
                        <div class="stat-info">
                            <p>Agotados</p>
                            <h2><?php echo htmlspecialchars($noDisponibles, FILTER_SANITIZE_FULL_SPECIAL_CHARS); ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Vista de escritorio -->
                <div class="table-card desktop-view">
                    <table id="inventarioTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Tipo de Producto</th>
                                <th>Existencia</th>
                                <th>Existencia en Inventario</th>
                                <th>Precios de Venta</th>
                                <th>Disponibilidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>
                                            <td>" . htmlspecialchars($row["id"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td>" . htmlspecialchars($row["producto"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td>" . htmlspecialchars($row["tipo_producto"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td>" . htmlspecialchars($row["existencia"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td>" . htmlspecialchars($row["existencia_inventario"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td>" . htmlspecialchars($row["PreciosVentas"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td><span class='status " . htmlspecialchars($row["disponiblidad_inventario"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "'>" . htmlspecialchars($row["disponiblidad_inventario"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</span></td>
                                        </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align: center;'>No se encontraron resultados</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Vista móvil -->
                <div class="mobile-view">
                    <?php
                    if ($result_mobile->num_rows > 0) {
                        $result_mobile->data_seek(0);
                        while ($row = $result_mobile->fetch_assoc()) {

                            $hola = '';
                            if($_SESSION['idPuesto'] <= 2){
                                $hola = '<div class="mobile-card-item">
                                        <span class="mobile-card-label">Precio Compra:</span>
                                        <span class="mobile-card-value">' . htmlspecialchars($row["Costo"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '</span>
                                    </div>';
                            }

                            echo '<div class="mobile-card" data-product="' . htmlspecialchars(strtoupper($row["producto"]), FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '">
                                <div class="mobile-card-header">
                                    <div class="mobile-card-title-section">
                                        <h3 class="mobile-card-title">' .htmlspecialchars($row["id"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) .' '. htmlspecialchars($row["producto"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '</h3>
                                        <p class="mobile-card-subtitle">' . htmlspecialchars($row["tipo_producto"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '</p>
                                    </div>
                                    <span class="status ' . htmlspecialchars($row["disponiblidad_inventario"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '">' . htmlspecialchars($row["disponiblidad_inventario"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '</span>
                                </div>
                                <div class="mobile-card-content">
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label">Existencia:</span>
                                        <span class="mobile-card-value">' . htmlspecialchars($row["existencia"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '</span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label">Almacen:</span>
                                        <span class="mobile-card-value">' . htmlspecialchars($row["existencia_inventario"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '</span>
                                    </div> 
                                    '. $hola . '
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label">Precio Venta:</span>
                                        <span class="mobile-card-value">' . htmlspecialchars($row["PreciosVentas"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . '</span>
                                    </div>
                                </div>
                            </div>';
                        }
                    } else {
                        echo '<p style="text-align: center; padding: 2rem;">No se encontraron resultados</p>';
                    }
                    ?>
                </div>
                
                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <!-- Información adicional de paginación -->
                <div class="pagination-info">
                    Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                </div>

                <div class="pagination">
                    <?php $query_string = construirQueryFiltros($filtros); ?>
                    <!-- Botón primera página -->
                    <li>
                        <a href="?pagina=1<?php echo $query_string; ?>" <?php echo ($pagina_actual == 1) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    
                    <!-- Botón página anterior -->
                    <li>
                        <a href="?pagina=<?php echo max(1, $pagina_actual - 1); ?><?php echo $query_string; ?>" <?php echo ($pagina_actual == 1) ? 'class="disabled"' : ''; ?>>
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
                            <a href="?pagina=<?php echo $i; ?><?php echo $query_string; ?>" <?php echo ($i == $pagina_actual) ? 'class="active"' : ''; ?>>
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Botón página siguiente -->
                    <li>
                        <a href="?pagina=<?php echo min($total_paginas, $pagina_actual + 1); ?><?php echo $query_string; ?>" <?php echo ($pagina_actual == $total_paginas) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    
                    <!-- Botón última página -->
                    <li>
                        <a href="?pagina=<?php echo $total_paginas; ?><?php echo $query_string; ?>" <?php echo ($pagina_actual == $total_paginas) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </div>
                <?php endif; ?>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <script>
        // Funciones para manejar filtros
        function limpiarFiltros() {
            window.location.href = window.location.pathname;
        }

        function removerFiltro(filtro) {
            const url = new URL(window.location);
            url.searchParams.delete(filtro);
            url.searchParams.delete('pagina'); // Reset página al remover filtro
            window.location.href = url.toString();
        }

        // Prevenir envío del formulario con Enter en el campo de búsqueda
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('filterForm').submit();
            }
        });

        // Auto-submit al cambiar filtros (opcional)
        document.getElementById('tipo').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        document.getElementById('disponibilidad').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        // Búsqueda en tiempo real para móvil (opcional)
        const searchInput = document.getElementById('search');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = this.value.toLowerCase();
            
            // Filtrar tarjetas móviles en tiempo real (sin hacer submit)
            const mobileCards = document.querySelectorAll('.mobile-card');
            mobileCards.forEach(card => {
                const productName = card.getAttribute('data-product').toLowerCase();
                if (productName.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Función para resaltar texto buscado
        function highlightSearchTerm() {
            const searchTerm = '<?php echo htmlspecialchars($search ?? '', ENT_QUOTES); ?>';
            if (searchTerm) {
                const cells = document.querySelectorAll('#inventarioTable tbody td');
                cells.forEach(cell => {
                    const text = cell.textContent;
                    const regex = new RegExp(`(${searchTerm})`, 'gi');
                    if (regex.test(text)) {
                        cell.innerHTML = text.replace(regex, '<mark>$1</mark>');
                    }
                });
            }
        }

        // Ejecutar al cargar la página
        window.addEventListener('DOMContentLoaded', function() {
            highlightSearchTerm();
            
            // Agregar animación de carga
            document.querySelector('.general-container').style.opacity = '0';
            setTimeout(() => {
                document.querySelector('.general-container').style.transition = 'opacity 0.3s';
                document.querySelector('.general-container').style.opacity = '1';
            }, 100);
        });

        // Confirmación antes de limpiar filtros si hay muchos aplicados
        const btnLimpiar = document.querySelector('.btn-filter-clear');
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', function(e) {
                const activeFiltros = document.querySelectorAll('.filter-tag').length;
                if (activeFiltros > 2) {
                    if (!confirm('¿Estás seguro de que deseas limpiar todos los filtros?')) {
                        e.preventDefault();
                    }
                }
            });
        }

        // Agregar estilo para el texto resaltado
        const style = document.createElement('style');
        style.textContent = `
            mark {
                background-color: #fef08a;
                padding: 0.1em 0.2em;
                border-radius: 2px;
                font-weight: 500;
            }
        `;
        document.head.appendChild(style);

        // Mejorar experiencia móvil - scroll suave
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Mensaje de confirmación al aplicar filtros
        const filterForm = document.getElementById('filterForm');
        filterForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('.btn-filter-apply');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aplicando...';
            submitBtn.disabled = true;
        });

        // Detectar si viene de aplicar filtros y mostrar mensaje
        <?php if (isset($_GET['search']) || isset($_GET['tipo']) || isset($_GET['disponibilidad'])): ?>
        window.addEventListener('DOMContentLoaded', function() {
            const totalResultados = <?php echo $total_registros; ?>;
            if (totalResultados === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin resultados',
                    text: 'No se encontraron productos con los filtros aplicados.',
                    confirmButtonColor: '#3b82f6'
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>