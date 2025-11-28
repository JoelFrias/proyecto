<?php

require_once '../../core/verificar-sesion.php'; // Verificar Session
require_once '../../core/conexion.php'; // Conexión a la base de datos

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'ALM003';
$id_empleado = $_SESSION['idEmpleado'];
$tiene_permiso = validarPermiso($conn, $permiso_necesario, $id_empleado);

// Inicializar variables
$result = false;
$result_mobile = false;
$totalProductos = $totalCategorias = $casiAgotados = "N/A";
$idEmpleado = null;
$search = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : "";

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;
$total_registros = 0;
$total_paginas = 0;

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

if (($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['seleccionar-empleado'])) || 
    (isset($_GET['empleado']))) {
    
    // Determinar el ID del empleado ya sea de POST o GET
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['seleccionar-empleado'])) {
        $idEmpleado = intval($_POST['seleccionar-empleado']);
    } else if (isset($_GET['empleado'])) {
        $idEmpleado = intval($_GET['empleado']);
    }

    // Si NO tiene permiso, solo puede ver su propio inventario
    if (!$tiene_permiso) {
        $idEmpleado = intval($_SESSION['idEmpleado']);
    }
    
    // Construir la consulta SQL base
    $sql_base = "SELECT
        p.id,
        p.descripcion AS producto,
        pt.descripcion AS tipo_producto,
        p.existencia AS existencia,
        ie.cantidad AS existencia_inventario,
        p.precioCompra AS costo,
        p.precioVenta1,
        p.precioVenta2,
        CASE 
            WHEN ie.cantidad = 0 THEN 'Agotado' 
            WHEN ie.cantidad <= p.reorden THEN 'Casi Agotado' 
            ELSE 'Disponible'
        END AS disponiblidad_inventario
        FROM productos AS p
        INNER JOIN inventarioempleados AS ie ON p.id = ie.idProducto
        LEFT JOIN productos_tipo AS pt ON p.idTipo = pt.id
        WHERE p.activo = TRUE AND ie.idEmpleado = ?";
    
    // Parámetros para la consulta
    $params = [$idEmpleado];
    $types = "i";
    
    // Agregar filtro de búsqueda si se proporciona
    if (!empty($search)) {
        $sql_base .= " AND (p.descripcion LIKE ? OR pt.descripcion LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= "ss";
        $filtros['search'] = $search;
    }
    
    // Consulta para el total de registros (para paginación)
    $sql_count = "SELECT COUNT(*) as total FROM ($sql_base) AS subquery";
    
    // Preparar y ejecutar consulta para conteo
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $row_count = $result_count->fetch_assoc();
    $total_registros = $row_count['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);
    
    // Consulta principal con paginación
    $sql = "$sql_base ORDER BY p.descripcion ASC LIMIT ?, ?";
    
    // Preparar y ejecutar consulta principal
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
    
    // Consultas para estadísticas - usando consultas preparadas
    $stmtTotal = $conn->prepare("SELECT COUNT(*) as total FROM inventarioempleados 
                                JOIN productos ON inventarioempleados.idProducto = productos.id 
                                WHERE productos.activo = TRUE AND inventarioempleados.idEmpleado = ?");
    $stmtTotal->bind_param("i", $idEmpleado);
    $stmtTotal->execute();
    $totalProductos = $stmtTotal->get_result()->fetch_assoc()['total'];
    
    $stmtCat = $conn->prepare("SELECT COUNT(DISTINCT idTipo) as total FROM inventarioempleados 
                              JOIN productos ON inventarioempleados.idProducto = productos.id 
                              WHERE productos.activo = TRUE AND inventarioempleados.idEmpleado = ?");
    $stmtCat->bind_param("i", $idEmpleado);
    $stmtCat->execute();
    $totalCategorias = $stmtCat->get_result()->fetch_assoc()['total'];
    
    $stmtAgot = $conn->prepare("SELECT COUNT(*) as total FROM inventarioempleados 
                               JOIN productos ON inventarioempleados.idProducto = productos.id 
                               WHERE productos.activo = TRUE AND inventarioempleados.cantidad <= productos.reorden 
                               AND inventarioempleados.cantidad > 0 AND inventarioempleados.idEmpleado = ?");
    $stmtAgot->bind_param("i", $idEmpleado);
    $stmtAgot->execute();
    $casiAgotados = $stmtAgot->get_result()->fetch_assoc()['total'];
} else {
    // Si no tiene permiso y no ha seleccionado empleado, cargar automáticamente su inventario
    if (!$tiene_permiso) {
        $idEmpleado = intval($_SESSION['idEmpleado']);
        // Redirigir para cargar el inventario del empleado actual
        header("Location: ?empleado=" . $idEmpleado);
        exit();
    }
}

// Consulta para el dropdown de empleados
if ($tiene_permiso) {
    // Si tiene permiso, mostrar todos los empleados activos
    $stmtEmp = $conn->prepare("SELECT id, CONCAT(id,' ',nombre,' ',apellido) AS nombre 
                             FROM empleados WHERE activo = TRUE ORDER BY nombre, apellido");
} else {
    // Si NO tiene permiso, solo mostrar su propio usuario
    $stmtEmp = $conn->prepare("SELECT id, CONCAT(id,' ',nombre,' ',apellido) AS nombre 
                             FROM empleados WHERE activo = TRUE AND id = ?");
    $stmtEmp->bind_param("i", $_SESSION['idEmpleado']);
}
$stmtEmp->execute();
$resultEmpleados = $stmtEmp->get_result();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Inventario Personal</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/inventario.css">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
    <style>
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
        
        /* Estilo para la búsqueda con formulario */
        .search-container form {
            display: flex;
            align-items: center;
            width: 100%;
        }
        
        .search-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            transition: background-color 0.3s;
        }
        
        .search-button:hover {
            background-color: #2980b9;
        }
        
        input[type="text"] {
            flex-grow: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        /* Estilo para mensaje de no empleado seleccionado */
        .no-employee-message {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .no-employee-message i {
            font-size: 30px;
            color: #6c757d;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

            <div class="general-container">
                <div class="header">
                    <h1>Inventario Personal de Productos</h1>
                    <?php if ($tiene_permiso): ?>
                    <!-- Mostrar selector solo si tiene permiso -->
                    <div class="header">
                        <form action="" method="post" class="employee-selector-form">
                            <span class="employee-selector-label">Selecciona el Empleado:</span>
                            <div class="employee-selector-controls">
                                <div class="select-container">
                                    <select name="seleccionar-empleado" id="seleccionar-empleado" class="employee-select">
                                        <option disabled selected>---</option>
                                        <?php
                                        if ($resultEmpleados && $resultEmpleados->num_rows > 0) {
                                            while ($fila = $resultEmpleados->fetch_assoc()) {
                                                $selected = (($idEmpleado == $fila['id']) ? " selected" : "");
                                                echo "<option value='" . $fila['id'] . "'" . $selected . ">" . htmlspecialchars($fila['nombre'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</option>";
                                            }
                                        } else {
                                            echo "<option value='' disabled>No hay opciones</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <button type="submit" class="employee-submit-button">Seleccionar</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(isset($idEmpleado) && $idEmpleado !== null): ?>
                    <div class="search-container">
                        <form method="GET" action="" class="search-form">
                            <i class="lucide-search"></i>
                            <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Buscar productos...">
                            <?php if($idEmpleado): ?>
                            <input type="hidden" name="empleado" value="<?php echo $idEmpleado; ?>">
                            <?php endif; ?>
                            <button type="submit" class="search-button">Buscar</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if(isset($idEmpleado) && $idEmpleado !== null): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="icon-container orange">
                                <i class="lucide-package"></i>
                            </div>
                        </div>
                        <div class="stat-info">
                            <p>Total Productos</p>
                            <h2><?php echo htmlspecialchars($totalProductos, FILTER_SANITIZE_FULL_SPECIAL_CHARS); ?></h2>
                        </div>
                        <div class="stat-footer"></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="icon-container green">
                                <i class="lucide-list"></i>
                            </div>
                        </div>
                        <div class="stat-info">
                            <p>Total Categorías</p>
                            <h2><?php echo htmlspecialchars($totalCategorias, FILTER_SANITIZE_FULL_SPECIAL_CHARS); ?></h2>
                        </div>
                        <div class="stat-footer"></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="icon-container blue">
                                <i class="lucide-alert-triangle"></i>
                            </div>
                            <button class="filter-button"><i class="lucide-filter"></i></button>
                        </div>
                        <div class="stat-info">
                            <p>Casi Agotados</p>
                            <h2><?php echo htmlspecialchars($casiAgotados, FILTER_SANITIZE_FULL_SPECIAL_CHARS); ?></h2>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if(!isset($idEmpleado) || $idEmpleado === null): ?>
                <div class="no-employee-message">
                    <p><i class="fas fa-user-slash"></i></p>
                    <p>Por favor seleccione un empleado para ver sus productos disponibles.</p>
                </div>
                <?php else: ?>

                <!-- Vista de escritorio -->
                <div class="table-card desktop-view">
                    <table id="inventarioTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Tipo de Producto</th>
                                <th>Existencia</th>
                                <th>Precios de Venta</th>
                                <th>Disponibilidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>
                                            <td>" . htmlspecialchars($row["id"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td>" . htmlspecialchars($row["producto"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td>" . htmlspecialchars($row["tipo_producto"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td>" . htmlspecialchars($row["existencia_inventario"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td>$" . htmlspecialchars($row["precioVenta1"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . ", $" . 
                                            htmlspecialchars($row["precioVenta2"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</td>
                                            <td><span class='status " . htmlspecialchars($row["disponiblidad_inventario"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "'>" . htmlspecialchars($row["disponiblidad_inventario"], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</span></td>
                                        </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>No se encontraron resultados</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Vista móvil -->
                <div class="mobile-view">
                    <?php
                    if ($result_mobile && $result_mobile->num_rows > 0) {
                        while ($row = $result_mobile->fetch_assoc()) {
                            $productName = htmlspecialchars($row["producto"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                            $productNameUpper = htmlspecialchars(strtoupper($row["producto"]), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                            $statusClass = htmlspecialchars(str_replace(' ', '-', $row["disponiblidad_inventario"]), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                            
                            echo <<<HTML
                            <div class="mobile-card" data-product="{$productNameUpper}">
                                <div class="mobile-card-header">
                                    <div class="mobile-card-title-section">
                                        <h3 class="mobile-card-title">{$productName}</h3>
                                        <p class="mobile-card-subtitle">{$row["tipo_producto"]}</p>
                                    </div>
                                    <span class="status $statusClass">{$row["disponiblidad_inventario"]}</span>
                                </div>
                                <div class="mobile-card-content">
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label">ID:</span>
                                        <span class="mobile-card-value">{$row["id"]}</span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label">Existencia:</span>
                                        <span class="mobile-card-value">{$row["existencia_inventario"]}</span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label">Precio Venta:</span>
                                        <span class="mobile-card-value">\${$row["precioVenta1"]}, \${$row["precioVenta2"]}</span>
                                    </div>
                                </div>
                            </div>
                    HTML;
                        }
                    } else {
                        echo '<div class="no-results-message">No se encontraron productos</div>';
                    }
                    ?>
                </div>
                
                <!-- Paginación -->
                <?php if ($idEmpleado && $total_paginas > 1): ?>
                <!-- Información adicional de paginación -->
                <div class="pagination-info">
                    Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
                </div>

                <div class="pagination">
                    <!-- Botón primera página -->
                    <li>
                        <a href="?pagina=1<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>&empleado=<?php echo $idEmpleado; ?>" <?php echo ($pagina_actual == 1) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    
                    <!-- Botón página anterior -->
                    <li>
                        <a href="?pagina=<?php echo max(1, $pagina_actual - 1); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>&empleado=<?php echo $idEmpleado; ?>" <?php echo ($pagina_actual == 1) ? 'class="disabled"' : ''; ?>>
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
                            <a href="?pagina=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>&empleado=<?php echo $idEmpleado; ?>" <?php echo ($i == $pagina_actual) ? 'class="active"' : ''; ?>>
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Botón página siguiente -->
                    <li>
                        <a href="?pagina=<?php echo min($total_paginas, $pagina_actual + 1); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>&empleado=<?php echo $idEmpleado; ?>" <?php echo ($pagina_actual == $total_paginas) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    
                    <!-- Botón última página -->
                    <li>
                        <a href="?pagina=<?php echo $total_paginas; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>&empleado=<?php echo $idEmpleado; ?>" <?php echo ($pagina_actual == $total_paginas) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA ENCIMA DE ESTA LINEA -->
        </div>
    </div>
    
    <script>
        // Esperar a que se cargue el DOM completamente
        document.addEventListener('DOMContentLoaded', function() {
            // Manejador del overlay y menú móvil
            const mobileToggle = document.getElementById('mobileToggle');
            const overlay = document.getElementById('overlay');
            
            if (mobileToggle && overlay) {
                mobileToggle.addEventListener('click', toggleMenu);
                overlay.addEventListener('click', closeMenu);
            }
            
            function toggleMenu() {
                document.body.classList.toggle('menu-open');
            }
            
            function closeMenu() {
                document.body.classList.remove('menu-open');
            }
        });
    </script>

    

</body>
</html>