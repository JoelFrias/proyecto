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
    header('Location: ../../views/auth/login.php'); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header("Location: ../../views/auth/login.php?session_expired=session_expired"); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

// Incluir el archivo de conexión a la base de datos
require '../../models/conexion.php';

// Inicializar la variable de búsqueda
$search = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : "";

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Construir la consulta SQL con filtros de búsqueda
$sql_base = "SELECT
                c.id,
                CONCAT(c.nombre, ' ', c.apellido) AS nombreCompleto,
                c.empresa,
                c.tipo_identificacion,
                c.identificacion,
                c.telefono,
                c.notas,
                cc.limite_credito,
                cc.balance,
                CONCAT(
                    '#',
                    cd.no,
                    ', ',
                    cd.calle,
                    ', ',
                    cd.sector,
                    ', ',
                    cd.ciudad,
                    ', (Referencia: ',
                    IFNULL(cd.referencia, 'Sin referencia'),
                    ')'
                ) AS direccion,
                c.activo
            FROM
                clientes AS c
            LEFT JOIN clientes_cuenta AS cc
            ON
                c.id = cc.idCliente
            LEFT JOIN clientes_direcciones AS cd
            ON
                c.id = cd.idCliente
            WHERE
                1=1
            ";

// Agregar filtro de búsqueda si se proporciona un término de búsqueda
if (!empty($search)) {
    $sql_base .= " AND CONCAT(c.nombre,' ', c.apellido, c.empresa, c.identificacion, c.telefono) LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
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
$sql = "$sql_base ORDER BY nombreCompleto ASC LIMIT ?, ?";

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Clientes</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/cliente_tabla.css">
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
        
            <main class="main-content">
                <!-- Sección del encabezado -->
                <div class="header-section">
                    <div class="title-container">
                        <h1>Lista de Clientes</h1>
                        
                        <div class="botones">
                            
                            <!-- Botón para imprimir reporte -->
                            <a href="../../pdf/cliente/registro.php"
                            class="btn btn-print" 
                            target="_blank">
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9V2h12v7"></path>
                                    <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"></path>
                                    <path d="M6 14h12v8H6z"></path>
                                </svg>
                                <span>Imprimir</span>
                            </a>

                            <!-- Botón para agregar un nuevo cliente -->
                            <?php if ($_SESSION['idPuesto'] <= 2): ?>
                                <a href="clientes-nuevo.php" class="btn btn-new">
                                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 5v14m-7-7h14"></path>
                                    </svg>
                                    <span>Nuevo</span>
                                </a>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- Sección de búsqueda -->
                    <div class="search-section">
                        <form method="GET" action="clientes.php" class="search-form">
                            <div class="search-input-container">
                                <div class="search-input-wrapper">
                                    <!-- Icono de búsqueda -->
                                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <path d="m21 21-4.3-4.3"></path>
                                    </svg>
                                    <!-- Campo de búsqueda -->
                                    <input 
                                        type="text" 
                                        id="search" 
                                        name="search" 
                                        value="<?php echo htmlspecialchars($search ?? ''); ?>" 
                                        placeholder="Buscardor general..."
                                        autocomplete="off"
                                    >
                                </div>
                                <!-- Botón de búsqueda -->
                                <button type="submit" class="btn btn-primary">
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
                    
                    <!-- Sección de la tabla -->
                    <div class="table-section">
                        <div class="table-container">
                            <!-- Tabla de clientes -->
                            <table class="client-table">
                                <thead>
                                    <tr>
                                        <th>Cuenta</th>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Empresa</th>
                                        <th>Tipo ID</th>
                                        <th>Identificación</th>
                                        <th>Teléfono</th>
                                        <th>Notas</th>
                                        <th>Límite Crédito</th>
                                        <th>Balance</th>
                                        <th>Dirección</th>
                                        <th>Estado</th>
                                        <?php 
                                            // Verificar si el usuario tiene permisos de administrador
                                            if ($_SESSION['idPuesto'] <= 2) {
                                                echo '<th>Acciones</th>';
                                            }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php 
                                    
                                    if ($total_registros > 0){

                                    while ($row = $result->fetch_assoc()): 
                                        
                                        // pasar numeros a formato de moneda
                                        $row['limite_credito'] = number_format($row['limite_credito'], 2, '.', ',');
                                        $row['balance'] = number_format($row['balance'], 2, '.', ',');
                                        
                                    ?>
                                        
                                    <tr>
                                        
                                        <td>
                                            <a href="cuenta-avance.php?idCliente=<?php echo urlencode($row['id']); ?>" class="btn btn-update">
                                                <i class="fa-regular fa-user"></i>
                                                <span>Avance a Cuenta</span>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nombreCompleto']); ?></td>
                                        <td><?php echo htmlspecialchars($row['empresa']); ?></td>
                                        <td><?php echo htmlspecialchars($row['tipo_identificacion']); ?></td>
                                        <td><?php echo htmlspecialchars($row['identificacion']); ?></td>
                                        <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($row['notas']); ?></td>
                                        <td><?php echo htmlspecialchars("RD$ " . $row['limite_credito']); ?></td>
                                        <td><?php echo htmlspecialchars("RD$ " . $row['balance']); ?></td>
                                        <td><?php echo htmlspecialchars($row['direccion']); ?></td>
                                        <td>
                                            <!-- Estado del cliente -->
                                            <span class="status <?php echo $row['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <?php 
                                            // Verificar si el usuario tiene permisos de administrador
                                            if ($_SESSION['idPuesto'] <= 2):
                                        ?>
                                        <td>
                                            <!-- Botón para actualizar el cliente -->
                                            <a href="clientes-actualizar.php?id=<?php echo urlencode($row['id']); ?>" class="btn btn-update">
                                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M21 2v6h-6M3 22v-6h6"></path>
                                                    <path d="M21 8c0 9.941-8.059 18-18 18"></path>
                                                    <path d="M3 16c0-9.941 8.059-18 18-18"></path>
                                                </svg>
                                                <span>Editar Datos</span>
                                            </a>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endwhile; 
                                    
                                    } else {

                                        echo '<td colspan="3">No se encontraron resultados</td>';

                                    }
                                    
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tabla móvil -->
                <div class="mobile-table">
                    
                    <?php

                        if ($total_registros > 0){

                        while ($row = $result_mobile->fetch_assoc()): 

                        // pasar numeros a formato de moneda
                        $row['limite_credito'] = number_format($row['limite_credito'], 2, '.', ',');
                        $row['balance'] = number_format($row['balance'], 2, '.', ',');
                    ?>
                    <div class="mobile-record">
                        <div class="mobile-record-header">
                            <div class="mobile-header-info">
                                <h3><?php echo htmlspecialchars($row['nombreCompleto']); ?></h3>
                                <p class="mobile-subtitle"><?php echo htmlspecialchars($row['empresa']); ?></p>
                            </div>
                            <!-- Estado del cliente -->
                            <span class="status <?php echo $row['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                        <div class="mobile-record-content">
                            <div class="mobile-grid">
                                <!-- Información del cliente -->
                                <div class="mobile-info-item">
                                    <div class="mobile-label">ID:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['id']); ?></div>
                                </div>
                                <div class="mobile-info-item">
                                    <div class="mobile-label">Tipo ID:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['tipo_identificacion']); ?></div>
                                </div>
                                <div class="mobile-info-item">
                                    <div class="mobile-label">Identificación:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['identificacion']); ?></div>
                                </div>
                                <div class="mobile-info-item">
                                    <div class="mobile-label">Teléfono:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['telefono']); ?></div>
                                </div>
                                <div class="mobile-info-item">
                                    <div class="mobile-label">Límite Crédito:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars("RD$ " . $row['limite_credito']); ?></div>
                                </div>
                                <div class="mobile-info-item">
                                    <div class="mobile-label">Balance:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars("RD$ " . $row['balance']); ?></div>
                                </div>
                                <div class="mobile-info-item notes-field">
                                    <div class="mobile-label">Notas:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['notas']); ?></div>
                                </div>
                                <div class="mobile-info-item address-field">
                                    <div class="mobile-label">Dirección:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['direccion']); ?></div>
                                </div>

                                

                                <div class="mobile-actions">
                                    
                                    <!-- Boton para avance a cuenta -->
                                    <a href="cuenta-avance.php?idCliente=<?php echo urlencode($row['id']); ?>" class="btn btn-update">
                                        <i class="fa-regular fa-user"></i>
                                        <span>Avance a Cuenta</span>
                                    </a>

                                    <?php 
                                        // Verificar si el usuario tiene permisos de administrador
                                        if ($_SESSION['idPuesto'] <= 2):
                                    ?>
                                    
                                    <!-- Botón para actualizar el cliente -->
                                    <a href="clientes-actualizar.php?id=<?php echo urlencode($row['id']); ?>" class="btn btn-update">
                                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 2v6h-6M3 22v-6h6"></path>
                                            <path d="M21 8c0 9.941-8.059 18-18 18"></path>
                                            <path d="M3 16c0-9.941 8.059-18 18-18"></path>
                                        </svg>
                                        <span>Editar</span>
                                    </a>

                                    <?php endif ?>

                                </div>

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

        <!-- TODO EL CONTENIDO DE LA PAGINA ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <!-- Scripts adicionales -->
    <script src="../../assets/js/deslizar.js"></script>

</body>
</html>