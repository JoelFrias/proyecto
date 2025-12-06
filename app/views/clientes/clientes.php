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

// Inicializar variables de búsqueda y filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : "";
$filtros = array();

// Configuración de paginación
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Inicializar variables para evitar errores
$types = "";
$params = [];

// Construir la consulta SQL con filtros de búsqueda
$sql_base = "SELECT
                c.id,
                CONCAT(c.nombre, ' ', c.apellido) AS nombreCompleto,
                c.nombre,
                c.apellido,
                c.empresa,
                c.tipo_identificacion,
                c.identificacion,
                c.telefono,
                c.notas,
                cc.limite_credito,
                cc.balance,
                CONCAT(
                    '#',
                    IFNULL(cd.no, ''),
                    ', ',
                    IFNULL(cd.calle, ''),
                    ', ',
                    IFNULL(cd.sector, ''),
                    ', ',
                    IFNULL(cd.ciudad, ''),
                    ', (Referencia: ',
                    IFNULL(cd.referencia, 'Sin referencia'),
                    ')'
                ) AS direccion,
                cd.no,
                cd.calle,
                cd.sector,
                cd.ciudad,
                cd.referencia,
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

// Filtro de búsqueda general (nombre, apellido, identificación, teléfono, dirección)
if (!empty($search)) {
    $sql_base .= " AND (
        c.nombre LIKE ? OR 
        c.apellido LIKE ? OR 
        CONCAT(c.nombre, ' ', c.apellido) LIKE ? OR
        c.empresa LIKE ? OR
        c.identificacion LIKE ? OR 
        c.telefono LIKE ? OR
        cd.no LIKE ? OR
        cd.calle LIKE ? OR
        cd.sector LIKE ? OR
        cd.ciudad LIKE ? OR
        cd.referencia LIKE ?
    )";
    $search_param = "%$search%";
    for ($i = 0; $i < 11; $i++) {
        $params[] = $search_param;
        $types .= "s";
    }
    $filtros['search'] = $search;
}

// Filtro por estado
if ($filtro_estado !== "") {
    $sql_base .= " AND c.activo = ?";
    $params[] = $filtro_estado;
    $types .= "i";
    $filtros['estado'] = $filtro_estado;
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
        if (!empty($value) || $value === '0') {
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
    <title>Clientes</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/cliente_tabla.css">
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .btn-print {
            background-color: #3b82f6;
            color: white;
            min-width: fit-content;
        }

        .btn-print:hover {
            background-color: #2563eb;
        }

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
        <?php include '../../../app/views/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->
        
            <main class="main-content">
                <!-- Sección del encabezado -->
                <div class="header-section">
                    <div class="title-container">
                        <h1>Lista de Clientes</h1>
                        
                        <div class="botones">

                            <?php
                            // Validar permisos para imprimir reporte
                            require_once '../../../core/validar-permisos.php';
                            $permiso_necesario = 'CLI002';
                            $id_empleado = $_SESSION['idEmpleado'];
                            if (validarPermiso($conn, $permiso_necesario, $id_empleado)):
                            ?>

                                <!-- Botón para imprimir reporte -->
                                <a href="../../reports/cliente/registro.php"
                                class="btn btn-print" 
                                target="_blank">
                                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M6 9V2h12v7"></path>
                                        <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"></path>
                                        <path d="M6 14h12v8H6z"></path>
                                    </svg>
                                    <span>Imprimir</span>
                                </a>

                            <?php endif; ?>

                            <?php
                            // Validar permisos para nuevo cliente
                            require_once '../../../core/validar-permisos.php';
                            $permiso_necesario = 'CLI001';
                            $id_empleado = $_SESSION['idEmpleado'];
                            if (validarPermiso($conn, $permiso_necesario, $id_empleado)):
                            ?>
                                <a href="clientes-nuevo.php" class="btn btn-new">
                                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 5v14m-7-7h14"></path>
                                    </svg>
                                    <span>Nuevo</span>
                                </a>
                            
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- Sección de filtros -->
                    <div class="filters-section">
                        <form method="GET" action="clientes.php" id="filterForm">
                            
                            <div class="filters-grid">
                                <!-- Filtro por búsqueda general -->
                                <div class="filter-group">
                                    <label for="search">Buscar Cliente</label>
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
                                            placeholder="Nombre, identificación, dirección..."
                                            autocomplete="off"
                                            style="padding-left: 35px;"
                                        >
                                    </div>
                                </div>

                                <!-- Filtro por estado -->
                                <div class="filter-group">
                                    <label for="estado">Estado</label>
                                    <select name="estado" id="estado">
                                        <option value="">Todos los estados</option>
                                        <option value="1" <?php echo ($filtro_estado === '1') ? 'selected' : ''; ?>>Activo</option>
                                        <option value="0" <?php echo ($filtro_estado === '0') ? 'selected' : ''; ?>>Inactivo</option>
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
                                
                                <?php if ($filtro_estado !== ""): ?>
                                    <span class="filter-tag">
                                        Estado: <?php echo $filtro_estado === '1' ? 'Activo' : 'Inactivo'; ?>
                                        <button type="button" onclick="removerFiltro('estado')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
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

                                        <?php
                                        // Validar permisos
                                        require_once '../../../core/validar-permisos.php';
                                        $permiso_necesario = 'CLI003';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):
                                        ?>

                                            <th>Cuenta</th>

                                        <?php endif; ?>

                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Empresa</th>
                                        <th>Tipo ID</th>
                                        <th>Identificación</th>
                                        <th>Teléfono</th>
                                        <th>Notas</th>
                                        
                                        <?php
                                        // Validar permisos
                                        require_once '../../../core/validar-permisos.php';
                                        $permiso_necesario = 'CLI001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):
                                        ?>

                                        <th>Límite Crédito</th>
                                        <th>Balance</th>

                                        <?php endif; ?>

                                        <th>Dirección</th>
                                        <th>Estado</th>

                                        <?php
                                        // Validar permisos para acciones
                                        require_once '../../../core/validar-permisos.php';
                                        $permiso_necesario = 'CLI001';
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
                                        
                                        // pasar numeros a formato de moneda
                                        $row['limite_credito'] = number_format($row['limite_credito'], 2, '.', ',');
                                        $row['balance'] = number_format($row['balance'], 2, '.', ',');
                                        
                                    ?>
                                        
                                    <tr>

                                        <?php
                                        // Validar permisos
                                        require_once '../../../core/validar-permisos.php';
                                        $permiso_necesario = 'CLI003';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):
                                        ?>
                                        
                                            <td>
                                                <a href="cuenta-avance.php?idCliente=<?php echo urlencode($row['id']); ?>" class="btn btn-update">
                                                    <i class="fa-regular fa-user"></i>
                                                    <span>Avance a Cuenta</span>
                                                </a>
                                            </td>
                                            
                                        <?php endif; ?>

                                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nombreCompleto']); ?></td>
                                        <td><?php echo htmlspecialchars($row['empresa']); ?></td>
                                        <td><?php echo htmlspecialchars($row['tipo_identificacion']); ?></td>
                                        <td><?php echo htmlspecialchars($row['identificacion']); ?></td>
                                        <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($row['notas']); ?></td>

                                        <?php
                                        // Validar permisos
                                        require_once '../../../core/validar-permisos.php';
                                        $permiso_necesario = 'CLI001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):
                                        ?>

                                        <td><?php echo htmlspecialchars("RD$ " . $row['limite_credito']); ?></td>
                                        <td><?php echo htmlspecialchars("RD$ " . $row['balance']); ?></td>

                                        <?php endif; ?>

                                        <td><?php echo htmlspecialchars($row['direccion']); ?></td>
                                        <td>
                                            <!-- Estado del cliente -->
                                            <span class="status <?php echo $row['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $row['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>

                                        <?php
                                        // Validar permisos para imprimir reporte
                                        require_once '../../../core/validar-permisos.php';
                                        $permiso_necesario = 'CLI001';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):
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

                                        echo '<tr><td colspan="13" style="text-align: center;">No se encontraron resultados</td></tr>';

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

                        $result_mobile->data_seek(0);
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

                                <?php
                                // Validar permisos
                                require_once '../../../core/validar-permisos.php';
                                $permiso_necesario = 'CLI001';
                                $id_empleado = $_SESSION['idEmpleado'];
                                if (validarPermiso($conn, $permiso_necesario, $id_empleado)):
                                ?>

                                <div class="mobile-info-item">
                                    <div class="mobile-label">Límite Crédito:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars("RD$ " . $row['limite_credito']); ?></div>
                                </div>
                                <div class="mobile-info-item">
                                    <div class="mobile-label">Balance:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars("RD$ " . $row['balance']); ?></div>
                                </div>
                                
                                <?php endif; ?> 

                                <div class="mobile-info-item notes-field">
                                    <div class="mobile-label">Notas:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['notas']); ?></div>
                                </div>
                                <div class="mobile-info-item address-field">
                                    <div class="mobile-label">Dirección:</div>
                                    <div class="mobile-value"><?php echo htmlspecialchars($row['direccion']); ?></div>
                                </div>

                                <div class="mobile-actions">

                                    <?php
                                    // Validar permisos
                                    require_once '../../../core/validar-permisos.php';
                                    $permiso_necesario = 'CLI003';
                                    $id_empleado = $_SESSION['idEmpleado'];
                                    if (validarPermiso($conn, $permiso_necesario, $id_empleado)):
                                    ?>
                                    
                                    <!-- Boton para avance a cuenta -->
                                    <a href="cuenta-avance.php?idCliente=<?php echo urlencode($row['id']); ?>" class="btn btn-update">
                                        <i class="fa-regular fa-user"></i>
                                        <span>Avance a Cuenta</span>
                                    </a>

                                    <?php endif; 

                                    // Validar permisos
                                    require_once '../../../core/validar-permisos.php';
                                    $permiso_necesario = 'CLI001';
                                    $id_empleado = $_SESSION['idEmpleado'];
                                    if (validarPermiso($conn, $permiso_necesario, $id_empleado)):
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
                
            </main>

        <!-- TODO EL CONTENIDO DE LA PAGINA ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <!-- Scripts adicionales -->
    <script>
        // Funciones para manejar filtros
        function limpiarFiltros() {
            window.location.href = 'clientes.php';
        }

        function removerFiltro(filtro) {
            const url = new URL(window.location);
            url.searchParams.delete(filtro);
            url.searchParams.delete('pagina'); // Reset a página 1
            window.location.href = url.toString();
        }
    </script>
    <script src="../../assets/js/deslizar.js"></script>

</body>
</html>