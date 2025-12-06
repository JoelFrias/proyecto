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
$permiso_necesario = 'EMP001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
    exit(); 
}

// Inicializar variables de búsqueda y filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$filtro_puesto = isset($_GET['puesto']) ? trim($_GET['puesto']) : "";
$filtro_estado = isset($_GET['estado']) ? trim($_GET['estado']) : "";
$filtros = array();

// Construir consulta SQL base
$sql = "SELECT e.id, e.nombre, e.apellido, e.tipo_identificacion, e.identificacion, 
        e.telefono, p.descripcion AS puesto, e.idPuesto, e.activo
        FROM empleados e 
        LEFT JOIN empleados_puestos p ON e.idPuesto = p.id 
        WHERE 1=1";

$params = [];
$types = "";

// Filtro de búsqueda general (nombre, apellido, identificación, teléfono)
if (!empty($search)) {
    $sql .= " AND (e.nombre LIKE ? OR e.apellido LIKE ? OR CONCAT(e.nombre, ' ', e.apellido) LIKE ? OR e.identificacion LIKE ? OR e.telefono LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sssss";
    $filtros['search'] = $search;
}

// Filtro por puesto
if (!empty($filtro_puesto)) {
    $sql .= " AND e.idPuesto = ?";
    $params[] = $filtro_puesto;
    $types .= "i";
    $filtros['puesto'] = $filtro_puesto;
}

// Filtro por estado
if ($filtro_estado !== "") {
    $sql .= " AND e.activo = ?";
    $params[] = $filtro_estado;
    $types .= "i";
    $filtros['estado'] = $filtro_estado;
}

$sql .= " ORDER BY e.nombre ASC";

// Ejecutar consulta
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    // Para vista móvil
    $stmt_mobile = $conn->prepare($sql);
    $stmt_mobile->bind_param($types, ...$params);
    $stmt_mobile->execute();
    $resultado_mobile = $stmt_mobile->get_result();
} else {
    $resultado = $conn->query($sql);
    $resultado_mobile = $conn->query($sql);
}

$total_registros = $resultado->num_rows;

// Función para construir URL con filtros
function construirQueryFiltros($filtros) {
    $query = '';
    foreach ($filtros as $key => $value) {
        if (!empty($value) || $value === '0') {
            $query .= "&{$key}=" . urlencode($value);
        }
    }
    return $query;
}

// Obtener puestos de empleados
$query_puestos = "SELECT id, descripcion FROM empleados_puestos ORDER BY descripcion ASC";
$result_puestos = $conn->query($query_puestos);

if (!$result_puestos) {
    die("Error en consulta de puestos: " . $conn->error);
}

$puestos_empleados = [];
while ($row_puesto = $result_puestos->fetch_assoc()) {
    $puestos_empleados[] = $row_puesto;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Empleados</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f6fa;
        }

        .emp_general-container {
            margin: 0 auto;
            padding: 1rem;
            flex: 1;
            overflow: auto;
        }

        .emp_header {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .emp_header h1 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            text-align: left;
            color: #333;
        }

        .emp_header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .emp_header h1 {
            margin-bottom: 0;
        }

        .emp_new-button {
            padding: 0.5rem 1rem;
            background-color: #10b981;
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .emp_new-button:hover {
            background-color: #059669;
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

        /* Desktop Table Styles */
        .emp_desktop-view {
            display: block;
        }

        .emp_table-card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow-x: auto;
            margin-bottom: 1.5rem;
        }

        .emp_table {
            width: 100%;
            border-collapse: collapse;
        }

        .emp_table th, 
        .emp_table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .emp_table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .emp_table tr:hover {
            background-color: #f1f5f9;
        }

        /* Mobile View Styles */
        .emp_mobile-view {
            display: none;
        }

        .emp_mobile-card {
            background: white;
            border-radius: 0.5rem;
            padding: 0.875rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .emp_mobile-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .emp_mobile-card-title-section {
            flex: 1;
        }

        .emp_mobile-card-title {
            font-size: 0.9375rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .emp_mobile-card-subtitle {
            color: #64748b;
            font-size: 0.75rem;
        }

        .emp_mobile-card-content {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .emp_mobile-card-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .emp_mobile-card-label {
            color: #64748b;
            font-size: 0.75rem;
        }

        .emp_mobile-card-value {
            font-weight: 500;
            font-size: 0.8125rem;
        }

        /* Button Styles */
        .emp_btn-edit {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .emp_btn-edit:hover {
            background-color: #2563eb;
        }

        .emp_results-count {
            margin-top: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #64748b;
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

            .emp_header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .emp_new-button {
                align-self: flex-end;
            }

            .emp_desktop-view {
                display: none;
            }

            .emp_mobile-view {
                display: block;
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
    
            <div class="emp_general-container">
                <div class="emp_header">
                    <div class="emp_header-top">
                        <h1>Lista de Empleados</h1>
                        <a href="empleados-nuevo.php" class="emp_new-button">
                            <i class="fas fa-plus"></i> Nuevo Empleado
                        </a>
                    </div>

                    <!-- Sección de filtros -->
                    <div class="filters-section">
                        <form method="GET" action="" id="filterForm">
                            
                            <div class="filters-grid">
                                <!-- Filtro por búsqueda general -->
                                <div class="filter-group">
                                    <label for="search">Buscar Empleado</label>
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
                                            placeholder="Nombre, apellido, identificación..."
                                            autocomplete="off"
                                            style="padding-left: 35px;"
                                        >
                                    </div>
                                </div>

                                <!-- Filtro por puesto -->
                                <div class="filter-group">
                                    <label for="puesto">Puesto</label>
                                    <select name="puesto" id="puesto">
                                        <option value="">Todos los puestos</option>
                                        <?php foreach ($puestos_empleados as $puesto): ?>
                                            <option value="<?php echo $puesto['id']; ?>" 
                                                <?php echo ($filtro_puesto == $puesto['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($puesto['descripcion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                                
                                <?php if (!empty($filtro_puesto)): ?>
                                    <?php 
                                    $puesto_nombre = '';
                                    foreach ($puestos_empleados as $puesto) {
                                        if ($puesto['id'] == $filtro_puesto) {
                                            $puesto_nombre = $puesto['descripcion'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="filter-tag">
                                        Puesto: <?php echo htmlspecialchars($puesto_nombre); ?>
                                        <button type="button" onclick="removerFiltro('puesto')">
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
            
                <!-- Vista de Escritorio -->
                <div class="emp_desktop-view">
                    <div class="emp_table-card">
                        <table class="emp_table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Apellido</th>
                                    <th>Tipo de Identificación</th>
                                    <th>Identificación</th>
                                    <th>Teléfono</th>
                                    <th>Puesto</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($resultado && $resultado->num_rows > 0): ?>
                                    <?php while ($fila = $resultado->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($fila['apellido']); ?></td>
                                            <td><?php echo htmlspecialchars($fila['tipo_identificacion']); ?></td>
                                            <td><?php echo htmlspecialchars($fila['identificacion']); ?></td>
                                            <td><?php echo htmlspecialchars($fila['telefono']); ?></td>
                                            <td><?php echo htmlspecialchars($fila['puesto']); ?></td>
                                            <td><?php echo $fila['activo'] == 1 ? 'Activo' : 'Inactivo'; ?></td>
                                            <td>
                                                <a href="empleados-editar.php?id=<?php echo $fila['id']; ?>" class="emp_btn-edit">Modificar</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center;">No se encontraron empleados con los criterios seleccionados</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            
                <!-- Vista Móvil -->
                <div class="emp_mobile-view">
                    <?php if ($resultado_mobile && $resultado_mobile->num_rows > 0): ?>
                        <?php 
                        $resultado_mobile->data_seek(0);
                        while ($fila = $resultado_mobile->fetch_assoc()): 
                        ?>
                            <div class="emp_mobile-card">
                                <div class="emp_mobile-card-header">
                                    <div class="emp_mobile-card-title-section">
                                        <h3 class="emp_mobile-card-title"><?php echo htmlspecialchars($fila['nombre'] . ' ' . $fila['apellido']); ?></h3>
                                        <p class="emp_mobile-card-subtitle"><?php echo htmlspecialchars($fila['puesto']); ?></p>
                                    </div>
                                    <a href="empleados-editar.php?id=<?php echo $fila['id']; ?>" class="emp_btn-edit">Modificar</a>
                                </div>
                                <div class="emp_mobile-card-content">
                                    <div class="emp_mobile-card-item">
                                        <span class="emp_mobile-card-label">Tipo de Identificación</span>
                                        <span class="emp_mobile-card-value"><?php echo htmlspecialchars($fila['tipo_identificacion']); ?></span>
                                    </div>
                                    <div class="emp_mobile-card-item">
                                        <span class="emp_mobile-card-label">Identificación</span>
                                        <span class="emp_mobile-card-value"><?php echo htmlspecialchars($fila['identificacion']); ?></span>
                                    </div>
                                    <div class="emp_mobile-card-item">
                                        <span class="emp_mobile-card-label">Teléfono</span>
                                        <span class="emp_mobile-card-value"><?php echo htmlspecialchars($fila['telefono']); ?></span>
                                    </div>
                                    <div class="emp_mobile-card-item">
                                        <span class="emp_mobile-card-label">Estado</span>
                                        <span class="emp_mobile-card-value"><?php echo $fila['activo'] == 1 ? 'Activo' : 'Inactivo'; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="emp_mobile-card">
                            <p>No se encontraron empleados con los criterios seleccionados</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA POR ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <!-- Script para mostrar mensajes de éxito o error -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: '<?php echo $_SESSION['success_message']; ?>',
                confirmButtonColor: '#3b82f6'
            });
            <?php unset($_SESSION['success_message']); ?>
        </script>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?php echo $_SESSION['error_message']; ?>',
                confirmButtonColor: '#ef4444'
            });
            <?php unset($_SESSION['error_message']); ?>
        </script>
    <?php endif; ?>

    <script>
        // Funciones para manejar filtros
        function limpiarFiltros() {
            window.location.href = 'empleados.php';
        }

        function removerFiltro(filtro) {
            const url = new URL(window.location);
            url.searchParams.delete(filtro);
            window.location.href = url.toString();
        }
    </script>

</body>
</html>