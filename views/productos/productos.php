<?php

/* Verificacion de sesion */

session_start();

$inactivity_limit = 900; // 15 minutos

if (!isset($_SESSION['username'])) {
    session_unset();
    session_destroy();
    header('Location: ../../views/auth/login.php');
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset();
    session_destroy();
    header("Location: ../../views/auth/login.php?session_expired=session_expired");
    exit();
}

$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

require '../../models/conexion.php';

// Inicializar variables de búsqueda y filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$filtros = array(); // Inicializar array de filtros

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Construir consulta SQL base
$sql_base = "SELECT
            p.id AS idProducto,
            p.descripcion AS descripcion,
            pt.descripcion AS tipo,
            p.existencia,
            p.idTipo,
            p.precioCompra,
            p.precioVenta1,
            p.precioVenta2,
            p.reorden,
            p.activo
        FROM
            productos AS p
        LEFT JOIN productos_tipo AS pt
        ON
            p.idTipo = pt.id
        WHERE
            1 = 1";

$params = [];
$types = "";

if (!empty($search)) {
    $sql_base .= " AND (p.descripcion LIKE ? OR pt.descripcion LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
    $filtros['search'] = $search;
}

// Consulta para total de registros
$sql_count = "SELECT COUNT(*) as total FROM ($sql_base) AS subquery";

// Preparar y ejecutar conteo
if (!empty($params)) {
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
} else {
    $result_count = $conn->query($sql_count);
}
$row_count = $result_count->fetch_assoc();
$total_registros = $row_count['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta principal con paginación
$sql = "$sql_base ORDER BY descripcion ASC LIMIT ?, ?";

// Preparar y ejecutar consulta principal
if (!empty($params)) {
    $types .= "ii";
    $all_params = array_merge($params, [$inicio, $registros_por_pagina]);
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$all_params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Vista móvil (misma consulta)
    $stmt_mobile = $conn->prepare($sql);
    $stmt_mobile->bind_param($types, ...$all_params);
    $stmt_mobile->execute();
    $result_mobile = $stmt_mobile->get_result();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $inicio, $registros_por_pagina);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Vista móvil (misma consulta)
    $stmt_mobile = $conn->prepare($sql);
    $stmt_mobile->bind_param("ii", $inicio, $registros_por_pagina);
    $stmt_mobile->execute();
    $result_mobile = $stmt_mobile->get_result();
}

// Función para construir URL con filtros
function construirQueryFiltros($filtros) {
    $query = '';
    foreach ($filtros as $key => $value) {
        if (!empty($value)) {
            $query .= "&{$key}=" . urlencode($value);
        }
    }
    return $query;
}

// Obtener tipos de producto
$query_tipos = "SELECT id, descripcion FROM productos_tipo";
$result_tipos = $conn->query($query_tipos);

if (!$result_tipos) {
    die("Error en consulta de tipos: " . $conn->error);
}

$tipos_producto = [];
while ($row_tipo = $result_tipos->fetch_assoc()) {
    $tipos_producto[] = $row_tipo;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Productos</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/cliente_tabla.css">         <!--------tabla de cliente--------->
    <link rel="stylesheet" href="../../assets/css/producto_modal.css">      <!------actualizar modal de producto-->
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
    <style>

        .btn-print {
            background-color: #3b82f6;
            color: white;
            min-width: fit-content;
        }

        .btn-print:hover {
            background-color: #2563eb;
        }

        @media (max-width: 768px) {
            .title-container {
                flex-wrap: wrap;
            }
            
            .btn-print {
                padding: 0.375rem 0.75rem;
                font-size: 0.813rem;
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
        <?php include '../../views/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->
        
            <!-- Contenido principal -->
            <main class="main-content">
                <!-- Sección del encabezado -->
                <div class="header-section">
                    <div class="title-container">
                        <h1>Lista de Productos</h1>

                        <div class="botones">

                            <?php

                            // Validar permisos para imprimir reporte
                            require_once '../../models/validar-permisos.php';
                            $permiso_necesario = 'PRO002';
                            $id_empleado = $_SESSION['idEmpleado'];
                            if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                            ?>

                            <!-- Botón para imprimir reporte -->
                            <a href="../../pdf/producto/registro.php" class="btn btn-print" target="_blank">
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9V2h12v7"></path>
                                    <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"></path>
                                    <path d="M6 14h12v8H6z"></path>
                                </svg>
                                <span>Imprimir</span>
                            </a>

                            <?php endif; ?>

                            <?php

                            // Validar permisos para nuevo producto
                            require_once '../../models/validar-permisos.php';
                            $permiso_necesario = 'PRO001';
                            $id_empleado = $_SESSION['idEmpleado'];
                            if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                            ?>

                                <button class="btn btn-new" id="btnNew" onclick="window.location.href='productos-nuevo.php'">
                                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 5v14m-7-7h14"></path>
                                    </svg>
                                    <span>Nuevo</span>
                                </button>


                            <?php endif; ?>
                        </div>

                    </div>
                    
                    <!-- Sección de búsqueda -->
                    <div class="search-section">
                        <form method="GET" action="" class="search-form">
                            <div class="search-input-container">
                                <div class="search-input-wrapper">
                                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <path d="m21 21-4.3-4.3"></path>
                                    </svg>
                                    <input 
                                        type="text" 
                                        id="search" 
                                        name="search" 
                                        value="<?php echo htmlspecialchars($search ?? ''); ?>" 
                                        placeholder="Buscardor de productos"
                                        autocomplete="off"
                                    >
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <!-- boton de buscar -->
                                    Buscar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-wrapper">
                    <div class="swipe-hint">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 5l7 7-7 7"></path>
                            <path d="M3 5l7 7-7 7"></path>
                        </svg>
                        <span>Desliza</span>
                    </div>
                    <div class="table-section">
                        <div class="table-container">
                            <table class="client-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Descripción</th>
                                        <th>Tipo</th>

                                        <?php

                                        // Validar permisos
                                        require_once '../../models/validar-permisos.php';
                                        $permiso_necesario = 'ALM001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                        ?>

                                        <th>Existencia</th>

                                        <?php

                                        endif;

                                        // Validar permisos
                                        require_once '../../models/validar-permisos.php';
                                        $permiso_necesario = 'PRO001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                        ?>

                                            <th>Precio Compra</th>

                                        <?php endif; ?>

                                        <th>Precio Venta 1</th>
                                        <th>Precio Venta 2</th>
                                        <th>Reorden</th>

                                        <?php

                                        // Validar permisos
                                        require_once '../../models/validar-permisos.php';
                                        $permiso_necesario = 'ALM001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                        ?>

                                        <th>Estado</th>

                                        <?php
                                        
                                        endif;

                                        // Validar permisos
                                        require_once '../../models/validar-permisos.php';
                                        $permiso_necesario = 'PRO001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                        ?>

                                            <th>Acciones</th>

                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 

                                    if ($total_registros > 0){

                                    while ($row = $result->fetch_assoc()):                           

                                        // formatear existencia y reorden
                                        $row['existencia'] = number_format($row['existencia'], 0);
                                        $row['reorden'] = number_format($row['reorden'], 0);

                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['idProducto']); ?></td>
                                        <td><?php echo htmlspecialchars($row['descripcion']); ?></td>
                                        <td><?php echo htmlspecialchars($row['tipo']); ?></td>

                                        <?php

                                        // Validar permisos
                                        require_once '../../models/validar-permisos.php';
                                        $permiso_necesario = 'ALM001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                        ?>

                                        <td><?php echo htmlspecialchars($row['existencia']); ?></td>

                                        <?php

                                        endif;

                                        // Validar permisos
                                        require_once '../../models/validar-permisos.php';
                                        $permiso_necesario = 'PRO001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                        ?>

                                        <td><?php echo htmlspecialchars("RD$ " . number_format($row['precioCompra'], 2)); ?></td>

                                        <?php endif; ?>
                                        
                                        <td><?php echo htmlspecialchars("RD$ " . number_format($row['precioVenta1'], 2));?></td>
                                        <td><?php echo htmlspecialchars("RD$ " . number_format($row['precioVenta2'], 2)); ?></td>

                                        <?php

                                        // Validar permisos
                                        require_once '../../models/validar-permisos.php';
                                        $permiso_necesario = 'ALM001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                        ?>

                                        <td><?php echo htmlspecialchars($row['reorden']); ?></td>

                                        <?php endif; ?>

                                        <td>
                                            <span class="status <?php echo $row['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>

                                        <?php

                                        // Validar permisos
                                        require_once '../../models/validar-permisos.php';
                                        $permiso_necesario = 'PRO001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                        ?>

                                            <td>
                                                <button class="btn btn-update" 
                                                        data-id="<?php echo $row['idProducto']; ?>" 
                                                        data-descripcion="<?php echo $row['descripcion']; ?>"
                                                        data-tipo="<?php echo $row['idTipo']; ?>" 
                                                        data-existencia="<?php echo $row['existencia']; ?>"
                                                        data-preciocompra="<?php echo $row['precioCompra']; ?>"
                                                        data-precioventa1="<?php echo $row['precioVenta1']; ?>"
                                                        data-precioventa2="<?php echo $row['precioVenta2']; ?>"
                                                        data-reorden="<?php echo $row['reorden']; ?>"
                                                        data-activo="<?php echo $row['activo']; ?>"
                                                        onclick="mostrarModal(this)">
                                                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M21 2v6h-6M3 22v-6h6"></path>
                                                        <path d="M21 8c0 9.941-8.059 18-18 18"></path>
                                                        <path d="M3 16c0-9.941 8.059-18 18-18"></path>
                                                    </svg>
                                                    <span>Editar</span>
                                                </button>
                                            </td>

                                        <?php endif; ?>
                                    </tr>
                                    <?php endwhile; 
                                    
                                    } else {

                                        echo '<td colspan="10">No se encontraron resultados</td>';

                                    }
                                    
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
               
                <div class="mobile-table">
                    <?php 

                    if ($total_registros > 0){
                    
                    $result_mobile->data_seek(0);
                    while ($row = $result_mobile->fetch_assoc()): 

                    // formatear existencia y reorden
                    $row['existencia'] = number_format($row['existencia'], 0);
                    $row['reorden'] = number_format($row['reorden'], 0);

                    // fomateo de monedas se realizo directamente en la tabla

                    ?>
                    <div class="mobile-record">
                        <div class="mobile-record-header">
                            <div class="mobile-header-info">
                                <h3><?php echo htmlspecialchars($row['descripcion']); ?></h3>
                                <p class="mobile-subtitle"><?php echo htmlspecialchars($row['tipo']); ?></p>
                            </div>
                            <span class="status <?php echo $row['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                        <div class="mobile-record-content">
                            <div class="mobile-grid">
                                <div class="mobile-info-item">
                                    <div class="mobile-label">ID:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['idProducto']); ?></div>
                                </div>

                                <?php

                                // Validar permisos
                                require_once '../../models/validar-permisos.php';
                                $permiso_necesario = 'ALM001';
                                $id_empleado = $_SESSION['idEmpleado'];
                                if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                ?>

                                <div class="mobile-info-item">
                                    <div class="mobile-label">Existencia:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['existencia']); ?></div>
                                </div>

                               <?php

                                endif;

                                // Validar permisos
                                require_once '../../models/validar-permisos.php';
                                $permiso_necesario = 'PRO001';
                                $id_empleado = $_SESSION['idEmpleado'];
                                if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                ?>

                                <div class="mobile-info-item">
                                    <div class="mobile-label">Precio Compra:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars("RD$ " . number_format($row['precioCompra'], 2)); ?></div>
                                </div>
                                
                                <?php endif; ?>

                                <div class="mobile-info-item">
                                    <div class="mobile-label">Precio Venta 1:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars("RD$ " . number_format($row['precioVenta1'], 2)); ?></div>
                                </div>
                                <div class="mobile-info-item">
                                    <div class="mobile-label">Precio Venta 2:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars("RD$ " . number_format($row['precioVenta2'], 2)); ?></div>
                                </div>

                                <?php

                                // Validar permisos
                                require_once '../../models/validar-permisos.php';
                                $permiso_necesario = 'ALM001';
                                $id_empleado = $_SESSION['idEmpleado'];
                                if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                ?>
                                
                                <div class="mobile-info-item">
                                    <div class="mobile-label">Reorden:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['reorden']); ?></div>
                                </div>

                                <?php

                                endif;

                                // Validar permisos
                                require_once '../../models/validar-permisos.php';
                                $permiso_necesario = 'PRO001';
                                $id_empleado = $_SESSION['idEmpleado'];
                                if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                ?>
                                
                                <div class="mobile-actions">
                                    <button class="btn btn-update" 
                                            data-id="<?php echo $row['idProducto']; ?>" 
                                            data-descripcion="<?php echo $row['descripcion']; ?>"
                                            data-tipo="<?php echo $row['idTipo']; ?>" 
                                            data-existencia="<?php echo $row['existencia']; ?>"
                                            data-preciocompra="<?php echo $row['precioCompra']; ?>"
                                            data-precioventa1="<?php echo $row['precioVenta1']; ?>"
                                            data-precioventa2="<?php echo $row['precioVenta2']; ?>"
                                            data-reorden="<?php echo $row['reorden']; ?>"
                                            data-activo="<?php echo $row['activo']; ?>"
                                            onclick="mostrarModal(this)">
                                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 2v6h-6M3 22v-6h6"></path>
                                            <path d="M21 8c0 9.941-8.059 18-18 18"></path>
                                            <path d="M3 16c0-9.941 8.059-18 18-18"></path>
                                        </svg>
                                        <span>Editar</span>
                                    </button>
                                </div>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; 
                    
                    } else{
                        echo '<p>No se encontraron resultados</p>';
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

            </main>

            <?php require 'productos-actualizar.php'; ?>

        <!-- TODO EL CONTENIDO VA ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <!-- manejo de mensajes  -->
    <?php
        // Mostrar mensajes de éxito
        if (isset($_SESSION['status']) && $_SESSION['status'] === 'success') {
            echo "
                <script>
                    Swal.fire({
                        title: '¡Éxito!',
                        text: '{$_SESSION['message']}',
                        icon: 'success',
                        confirmButtonText: 'Aceptar'
                    }).then(function() {
                        window.location.href = 'productos.php'; 
                    });
                </script>
            ";
            unset($_SESSION['status'], $_SESSION['message']); // Limpiar el estado después de mostrar el mensaje
        }

        // Mostrar mensajes de error
        if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])) {
            $errors = json_encode($_SESSION['errors']); // Convertir el array de errores a JSON
            echo "
                <script>
                    Swal.fire({
                        title: '¡Error!',
                        html: `{$errors}`.split(',').join('<br>'), // Mostrar errores en líneas separadas
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                </script>
            ";
            unset($_SESSION['errors']); // Limpiar los errores después de mostrarlos
        }
    ?>

    <!-- Scripts -->
    <script>

        function mostrarModal(button) {
            if (!button) return;  // Evita ejecutar si no hay un botón específico

            // Obtener datos del producto desde los atributos data-*
            const idProducto = button.getAttribute("data-id");
            const descripcion = button.getAttribute("data-descripcion");
            const precioCompra = button.getAttribute("data-preciocompra");
            const precioVenta1 = button.getAttribute("data-precioventa1");
            const precioVenta2 = button.getAttribute("data-precioventa2");
            const reorden = button.getAttribute("data-reorden");
            const activo = button.getAttribute("data-activo");

            // Asignar valores a los campos del formulario
            if (idProducto) document.getElementById("idProducto").value = idProducto;
            if (descripcion) document.getElementById("descripcion").value = descripcion;
            if (precioCompra) document.getElementById("precioCompra").value = precioCompra;
            if (precioVenta1) document.getElementById("precioVenta1").value = precioVenta1;
            if (precioVenta2) document.getElementById("precioVenta2").value = precioVenta2;
            if (reorden) document.getElementById("reorden").value = reorden;
            if (activo) document.getElementById("activo").value = activo;

            // Establecer el valor seleccionado del tipo de producto
            const tipoActual = button.getAttribute("data-tipo");  // Obtener el idTipo
            const selectTipo = document.getElementById("tipo");
            selectTipo.value = tipoActual;  // Establecer el valor seleccionado

            // Mostrar el modal
            document.getElementById("modalActualizar").style.display = "flex";
            console.log("Tipo de producto (idTipo):", tipoActual);  // Verificar el valor del tipo
        }

        function cerrarModal() {
            document.getElementById("modalActualizar").style.display = "none";
        }

        // Cerrar el modal si el usuario hace clic fuera de él
        window.onclick = function(event) {
            let modal = document.getElementById("modalActualizar");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        };
    </script>

    <script src="../../assets/js/deslizar.js"></script>
       
</body>
</html>