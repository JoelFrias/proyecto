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

require "../../core/conexion.php";

    ////////////////////////////////////////////////////////////////////
    ///////////////////// VALIDACION DE PERMISOS ///////////////////////
    ////////////////////////////////////////////////////////////////////

    require_once '../../core/validar-permisos.php';
    $permiso_necesario = 'ALM001';
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

// Inicializar la variable de búsqueda
$search = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : "";

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Construir la consulta SQL base
$sql_base = "SELECT
            p.id,
            p.descripcion AS producto,
            pt.descripcion AS tipo_producto,
            p.existencia AS existencia,
            i.existencia AS existencia_inventario,
            p.precioCompra,
            p.precioVenta1,
            p.precioVenta2,
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

// Agregar filtro de búsqueda si se proporciona un término de búsqueda
$params = [];
$types = "";

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
        if (!empty($value)) {
            $query .= "&{$key}=" . urlencode($value);
        }
    }
    return $query;
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
    </style>
</head>
<body>
 
    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../frontend/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

            <div class="general-container">
                <div class="header">
                    <h1>Almacén Principal de Productos</h1>
                    <div class="search-container">
                        <form method="GET" action="" class="search-form">
                            <i class="lucide-search"></i>
                            <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Buscar productos...">
                            <button type="submit" class="search-button">Buscar</button>
                        </form>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="icon-container orange">
                                <i class="lucide-package"></i>
                            </div>
                        </div>
                        <div class="stat-info">
                            <p>Total Productos</p>
                            <h2><?php echo htmlspecialchars($totalProductos, ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <div class="stat-footer">
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="icon-container green">
                                <i class="lucide-list"></i>
                            </div>
                        </div>
                        <div class="stat-info">
                            <p>Total Categorías</p>
                            <h2><?php echo htmlspecialchars($totalCategorias, ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <div class="stat-footer">
                        </div>
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
                            <h2><?php echo htmlspecialchars($casiAgotados, ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <!--
                        <div class="stat-footer">
                            <button class="view-more-button">Ver más</button>
                        </div>
                        -->
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="icon-container red">
                                <i class="lucide-x-circle"></i>
                            </div>
                            <button class="filter-button"><i class="lucide-filter"></i></button>
                        </div>
                        <div class="stat-info">
                            <p>Agotados</p>
                            <h2><?php echo htmlspecialchars($noDisponibles, ENT_QUOTES, 'UTF-8'); ?></h2>
                        </div>
                        <!--
                        <div class="stat-footer">
                            <button class="view-more-button">Ver más</button>
                        </div>
                        -->
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
                                            <td>" . htmlspecialchars($row["id"], ENT_QUOTES, 'UTF-8') . "</td>
                                            <td>" . htmlspecialchars($row["producto"], ENT_QUOTES, 'UTF-8') . "</td>
                                            <td>" . htmlspecialchars($row["tipo_producto"], ENT_QUOTES, 'UTF-8') . "</td>
                                            <td>" . htmlspecialchars($row["existencia"], ENT_QUOTES, 'UTF-8') . "</td>
                                            <td>" . htmlspecialchars($row["existencia_inventario"], ENT_QUOTES, 'UTF-8') . "</td>
                                            <td>" . htmlspecialchars($row["PreciosVentas"], ENT_QUOTES, 'UTF-8') . "</td>
                                            <td><span class='status " . htmlspecialchars($row["disponiblidad_inventario"], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row["disponiblidad_inventario"], ENT_QUOTES, 'UTF-8') . "</span></td>
                                        </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8'>No se encontraron resultados</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Vista móvil -->
                <div class="mobile-view">
                    <?php
                    if ($result_mobile->num_rows > 0) {
                        while ($row = $result_mobile->fetch_assoc()) {

                            $hola = '';
                            if($_SESSION['idPuesto'] <= 2){
                                $hola = '<div class="mobile-card-item">
                                        <span class="mobile-card-label">Precio Compra:</span>
                                        <span class="mobile-card-value">' . htmlspecialchars($row["Costo"], ENT_QUOTES, 'UTF-8') . '</span>
                                    </div>';
                            }

                            echo '<div class="mobile-card" data-product="' . htmlspecialchars(strtoupper($row["producto"]), ENT_QUOTES, 'UTF-8') . '">
                                <div class="mobile-card-header">
                                    <div class="mobile-card-title-section">
                                        <h3 class="mobile-card-title">' .htmlspecialchars($row["id"], ENT_QUOTES, 'UTF-8') .' '. htmlspecialchars($row["producto"], ENT_QUOTES, 'UTF-8') . '</h3>
                                        <p class="mobile-card-subtitle">' . htmlspecialchars($row["tipo_producto"], ENT_QUOTES, 'UTF-8') . '</p>
                                    </div>
                                    <span class="status ' . htmlspecialchars($row["disponiblidad_inventario"], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($row["disponiblidad_inventario"], ENT_QUOTES, 'UTF-8') . '</span>
                                </div>
                                <div class="mobile-card-content">
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label">Existencia:</span>
                                        <span class="mobile-card-value">' . htmlspecialchars($row["existencia"], ENT_QUOTES, 'UTF-8') . '</span>
                                    </div>
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label">Almacen:</span>
                                        <span class="mobile-card-value">' . htmlspecialchars($row["existencia_inventario"], ENT_QUOTES, 'UTF-8') . '</span>
                                    </div> 
                                    '. $hola . '
                                    <div class="mobile-card-item">
                                        <span class="mobile-card-label">Precio Venta:</span>
                                        <span class="mobile-card-value">' . htmlspecialchars($row["PreciosVentas"], ENT_QUOTES, 'UTF-8') . '</span>
                                    </div>
                                </div>
                            </div>';
                        }
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
                    <!-- Botón primera página -->
                    <li>
                        <a href="?pagina=1<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" <?php echo ($pagina_actual == 1) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    
                    <!-- Botón página anterior -->
                    <li>
                        <a href="?pagina=<?php echo max(1, $pagina_actual - 1); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" <?php echo ($pagina_actual == 1) ? 'class="disabled"' : ''; ?>>
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
                            <a href="?pagina=<?php echo $i; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" <?php echo ($i == $pagina_actual) ? 'class="active"' : ''; ?>>
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- Botón página siguiente -->
                    <li>
                        <a href="?pagina=<?php echo min($total_paginas, $pagina_actual + 1); ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" <?php echo ($pagina_actual == $total_paginas) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    
                    <!-- Botón última página -->
                    <li>
                        <a href="?pagina=<?php echo $total_paginas; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" <?php echo ($pagina_actual == $total_paginas) ? 'class="disabled"' : ''; ?>>
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </div>
                <?php endif; ?>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA ENCIMA DE ESTA LINEA -->
        </div>
    </div>
    
</body>
</html>