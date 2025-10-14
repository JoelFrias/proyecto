<?php

/* Verificacion de sesion */

// Iniciar sesión
session_start();

// Configurar el tiempo de caducidad de la sesión
$inactivity_limit = 9000; // 15 minutos en segundos

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

require_once '../../models/conexion.php';

/* Fin de verificacion de sesion */

// Verificar si el usuario tiene permisos de administrador
if ($_SESSION['idPuesto'] > 2) {
    echo "<script>
            Swal.fire({
                    icon: 'error',
                    title: 'Acceso Prohibido',
                    text: 'Usted no cuenta con permisos de administrador para entrar a esta pagina.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    window.location.href = '../../index.php';
                });
          </script>";
    exit();
}

// Procesar la actualización si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $tipo_identificacion = trim($_POST['tipo_identificacion']);
    $identificacion = trim($_POST['identificacion']);
    $telefono = trim($_POST['telefono']);
    $idPuesto = intval($_POST['idPuesto']);
    $estado = trim($_POST['estado']);

    if($estado == "activo"){
        $estado = 1;  // Using 1 instead of TRUE
    } else if ($estado == "inactivo"){
        $estado = 0;  // Using 0 instead of FALSE
    } else {
        $_SESSION['error_message'] = "Estado invalido";
        header('Location: ../../views/empleados/empleados.php');
        exit();
    }

    // Actualizar el empleado en la base de datos
    $sql_update = "UPDATE empleados SET
                   nombre = ?,
                   apellido = ?,
                   tipo_identificacion = ?,
                   identificacion = ?,
                   telefono = ?,
                   idPuesto = ?,
                   activo = ?
                   WHERE id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("sssssiii", $nombre, $apellido, $tipo_identificacion, $identificacion, $telefono, $idPuesto, $estado, $id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Empleado actualizado con éxito.';
    } else {
        $_SESSION['error_message'] = 'Error al actualizar el empleado: ' . $stmt->error;
    }

    // Auditoria de Acciones de Usuario
    require_once '../../models/auditorias.php';
    $usuario_id = $_SESSION['idEmpleado'];
    $accion = 'Actualizacion Empleado';
    $detalle = 'IdEmpleado: ' . $id . ', Nombre: ' . $nombre . ', Apellido: ' . $apellido . ', tipo Identificacion: ' . $tipo_identificacion . ', identificacion: ' . $identificacion . ', telefono: ' . $telefono . ', id puesto: ' . $idPuesto . ', estado: ' . $estado;
    $ip = $_SERVER['REMOTE_ADDR']; // Obtener la dirección IP del cliente
    registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

    // Redirigir para evitar reenvío del formulario
    header('Location: ../../views/empleados/empleados.php');
    exit();
}

// Inicializar la variable de búsqueda
$busqueda = '';
if (isset($_GET['busqueda'])) {
    $busqueda = trim($_GET['busqueda']);
}

// Obtener la lista de empleados con filtro de búsqueda si existe
$sql = "SELECT e.id, e.nombre, e.apellido, e.tipo_identificacion, e.identificacion, e.telefono, p.descripcion AS puesto , e.activo
        FROM empleados e 
        JOIN empleados_puestos p ON e.idPuesto = p.id 
        WHERE 1=1";

// Agregar condición de búsqueda si se proporcionó un término
if (!empty($busqueda)) {
    $busqueda = '%' . $busqueda . '%';
    $sql .= " AND (e.nombre LIKE ? OR e.apellido LIKE ? OR e.identificacion LIKE ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    $resultado = $conn->query($sql);
}

// Obtener los datos del empleado a editar (si se ha hecho clic en "Modificar")
$empleado_editar = null;
if (isset($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    $sql_editar = "SELECT e.*, p.descripcion AS puesto 
                   FROM empleados e 
                   JOIN empleados_puestos p ON e.idPuesto = p.id 
                   WHERE e.id = $id_editar";
    $resultado_editar = $conn->query($sql_editar);
    if ($resultado_editar->num_rows > 0) {
        $empleado_editar = $resultado_editar->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Empleados</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
    <style>

        body{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f6fa;
        }

        /* Estilos para el formulario de búsqueda */
        .emp_search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .emp_search-input-container {
        display: flex;
        flex: 1;
        min-width: 0; /* Permite que el contenedor se encoja si es necesario */
        }

        .emp_search-input {
            flex-grow: 1;
        padding: 0.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 0.375rem 0 0 0.375rem;
        font-size: 0.875rem;
        border-right: none;
        }

        .emp_search-button {
            padding: 0.5rem 1rem;
            background-color: #3b82f6;
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.2s;
            white-space: nowrap;
        }

        .emp_search-button:hover {
            background-color: #2563eb;
        }

        /* Estilos específicos para la tabla de empleados - con prefijo emp_ para evitar conflictos */
        .emp_general-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
            flex: 1;
            overflow: auto;
        }

        /* Header Styles */
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

        .emp_header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .emp_header h1 {
            margin-bottom: 0; /* Anulamos el margen inferior existente */
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


        /* Estilos responsivos */
        /*manipulacion para que se quede siempre de lado al input*/
        @media (max-width: 768px) {
            .emp_search-container {
                flex-direction: column;
                align-items: stretch;
            }

            .emp_search-form {
                width: 100%;
                flex-direction: row;
                gap: 10px;
            }

            .emp_search-button,
            .emp_new-button {
                width: auto;
                text-align: center;
            }
        }
        /*manipulacion para que se quede siempre de lado al input*/
        @media (max-width: 480px) {
            .emp_search-container {
                flex-direction: column;
                align-items: stretch;
            }

            .emp_search-form {
                width: 100%;
                flex-direction: row;
                gap: 10px;
            }

            .emp_search-button,
            .emp_new-button {
                width: auto;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .emp_header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .emp_new-button {
                align-self: flex-end;
            }
        }

        /* Modal Styles - More compact and responsive */
        .emp_modal {
            display: <?php echo ($empleado_editar ? 'flex' : 'none'); ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 1rem;
        }

        .emp_modal-content {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 95%;
            max-width: 600px; /* Reduced from 800px for a more compact look */
            padding: 1.25rem; /* Reduced padding */
            animation: emp_modalFadeIn 0.3s ease;
            max-height: 85vh; /* Slightly reduced height */
            overflow-y: auto;
        }

        @keyframes emp_modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .emp_modal h2 {
            font-size: 1.125rem; /* Slightly smaller font */
            font-weight: 600;
            margin-bottom: 1rem; /* Reduced margin */
            color: #333;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.5rem; /* Reduced padding */
        }

        .emp_form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem; /* Reduced gap */
        }

        @media (min-width: 768px) {
            .emp_form-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem; /* Reduced from 1.5rem */
            }
        }

        .emp_form-group {
            margin-bottom: 0.75rem; /* Reduced margin */
        }

        .emp_form-group label {
            display: block;
            font-size: 0.8125rem; /* Slightly smaller */
            color: #64748b;
            margin-bottom: 0.375rem; /* Reduced margin */
        }

        .emp_form-group input,
        .emp_form-group select {
            width: 100%;
            padding: 0.5rem; /* Reduced padding */
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            font-size: 0.8125rem; /* Slightly smaller */
            background-color: white;
        }

        .emp_form-group input:focus,
        .emp_form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        .emp_form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem; /* Reduced gap */
            margin-top: 1rem; /* Reduced margin */
            grid-column: 1 / -1;
        }

        .emp_form-actions button {
            padding: 0.5rem 0.875rem; /* Reduced padding */
            border-radius: 0.375rem;
            font-size: 0.8125rem; /* Slightly smaller */
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .emp_form-actions button[type="submit"] {
            background-color: #3b82f6;
            color: white;
            border: none;
        }

        .emp_form-actions button[type="submit"]:hover {
            background-color: #2563eb;
        }

        .emp_form-actions button[type="button"] {
            background-color: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .emp_form-actions button[type="button"]:hover {
            background-color: #e2e8f0;
        }

        /* Enhanced Mobile Responsiveness */
        @media (max-width: 640px) {
            .emp_modal-content {
                width: 95%;
                padding: 1rem;
                max-width: none;
            }
            
            .emp_form-group input,
            .emp_form-group select {
                padding: 0.5rem;
                font-size: 16px; /* Prevent zoom on mobile */
            }
            
            .emp_form-actions {
                flex-direction: column-reverse;
                width: 100%;
            }
            
            .emp_form-actions button {
                width: 100%;
                padding: 0.625rem;
                margin-bottom: 0.5rem;
            }
        }

        /* Improved table responsiveness */
        @media (max-width: 768px) {
            .emp_desktop-view {
                display: none;
            }

            .emp_mobile-view {
                display: block;
            }
        }

        /* Estilos para el overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
        }

        .overlay.active {
            display: block;
        }

        /* Estilos para el botón de limpiar búsqueda */
        .emp_clear-search {
            padding: 0.5rem 1rem;
            background-color: #64748b;
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .emp_clear-search:hover {
            background-color: #475569;
        }

        /* Estilos para el contador de resultados */
        .emp_results-count {
            margin-top: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #64748b;
        }
    </style>
</head>
<body>

    <?php

        if ($_SESSION['idPuesto'] > 2) {
            echo "<script>
                    Swal.fire({
                            icon: 'error',
                            title: 'Acceso Prohibido',
                            text: 'Usted no cuenta con permisos de administrador para entrar a esta pagina.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            window.location.href = '../../index.php';
                        });
                </script>";
            exit();
        }

    ?>

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../views/layouts/menu.php'; ?>

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
                
                    <!-- Formulario de búsqueda -->
                    <form action="" method="GET" class="emp_search-form">
                        
                        <input type="text" name="busqueda" placeholder="Buscar por nombre o identificación..." class="emp_search-input" value="<?php echo htmlspecialchars($_GET['busqueda'] ?? ''); ?>">
                        
                        <button type="submit" class="emp_search-button"><i class="fas fa-search"></i> Buscar</button>
                
                    </form>
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
                                            
                                            <?php 
                                                if($fila['activo'] == 1){
                                                    $var = "Activo";
                                                } else {
                                                    $var = "Inactivo";
                                                }
                                            ?>

                                            <td><?php echo htmlspecialchars($var) ?></td>

                                            <td>
                                                <a href="empleados.php?editar=<?php echo $fila['id']; ?><?php echo !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : ''; ?>" class="emp_btn-edit">Modificar</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">No hay empleados registrados<?php echo !empty($busqueda) ? ' que coincidan con la búsqueda.' : '.'; ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            
                <!-- Vista Móvil -->
                <div class="emp_mobile-view">
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php 
                        // Reiniciar el puntero del resultado para la vista móvil
                        $resultado->data_seek(0);
                        while ($fila = $resultado->fetch_assoc()): 
                        ?>
                            <div class="emp_mobile-card">
                                <div class="emp_mobile-card-header">
                                    <div class="emp_mobile-card-title-section">
                                        <h3 class="emp_mobile-card-title"><?php echo htmlspecialchars($fila['nombre'] . ' ' . $fila['apellido']); ?></h3>
                                        <p class="emp_mobile-card-subtitle"><?php echo htmlspecialchars($fila['puesto']); ?></p>
                                    </div>
                                    <a href="empleados.php?editar=<?php echo $fila['id']; ?><?php echo !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : ''; ?>" class="emp_btn-edit">Modificar</a>
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
                                        <?php 
                                            if($fila['activo'] == 1){
                                                $var = "Activo";
                                            } else {
                                                $var = "Inactivo";
                                            }
                                        ?>
                                        <span class="emp_mobile-card-value"><?php echo htmlspecialchars($var); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="emp_mobile-card">
                            <p>No hay empleados registrados<?php echo !empty($busqueda) ? ' que coincidan con la búsqueda.' : '.'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal de edición mejorado con diseño de dos columnas -->
            <?php if ($empleado_editar): ?>
                <div class="emp_modal">
                    <div class="emp_modal-content">
                        <h2>Modificar Empleado</h2>
                        <form action="" method="post">
                            <input type="hidden" name="id" value="<?php echo $empleado_editar['id']; ?>">
                            <?php if (!empty($busqueda)): ?>
                            <input type="hidden" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>">
                            <?php endif; ?>
                            
                            <div class="emp_form-grid">
                                <div class="emp_form-group">
                                    <label for="nombre">Nombre:</label>
                                    <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($empleado_editar['nombre']); ?>" required>
                                </div>
                                
                                <div class="emp_form-group">
                                    <label for="apellido">Apellido:</label>
                                    <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($empleado_editar['apellido']); ?>" required>
                                </div>
                                
                                <div class="emp_form-group">
                                    <label for="tipo_identificacion">Tipo de Identificación:</label>
                                    <select id="tipo_identificacion" name="tipo_identificacion" required>
                                        <option value="Cedula" <?php echo ($empleado_editar['tipo_identificacion'] === 'Cedula' ? 'selected' : ''); ?>>Cédula</option>
                                        <option value="Pasaporte" <?php echo ($empleado_editar['tipo_identificacion'] === 'Pasaporte' ? 'selected' : ''); ?>>Pasaporte</option>
                                    </select>
                                </div>
                                
                                <div class="emp_form-group">
                                    <label for="identificacion">Identificación:</label>
                                    <input type="text" id="identificacion" name="identificacion" value="<?php echo htmlspecialchars($empleado_editar['identificacion']); ?>" required>
                                </div>
                                
                                <div class="emp_form-group">
                                    <label for="telefono">Teléfono:</label>
                                    <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($empleado_editar['telefono']); ?>" required>
                                </div>
                                
                                <div class="emp_form-group">
                                    <label for="idPuesto">Puesto:</label>
                                    <select id="idPuesto" name="idPuesto" required>
                                        <?php
                                        $sql_puestos = "SELECT id, descripcion FROM empleados_puestos ORDER BY descripcion ASC";
                                        $resultado_puestos = $conn->query($sql_puestos);
                                        if ($resultado_puestos && $resultado_puestos->num_rows > 0) {
                                            while ($fila = $resultado_puestos->fetch_assoc()) {
                                                $selected = ($fila['id'] == $empleado_editar['idPuesto']) ? 'selected' : '';
                                                echo "<option value='" . $fila['id'] . "' $selected>" . $fila['descripcion'] . "</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="emp_form-group">
                                    <label for="estado">Estado:</label>
                                    <select id="estado" name="estado" required>
                                        <option value="activo" <?php echo ($empleado_editar['activo'] == 1 ? 'selected' : ''); ?>>Activo</option>
                                        <option value="inactivo" <?php echo ($empleado_editar['activo'] == 0 ? 'selected' : ''); ?>>Inactivo</option>
                                    </select>
                                </div>
                                
                                <div class="emp_form-actions">
                                    <button type="button" onclick="window.location.href='empleados.php<?php echo !empty($busqueda) ? '?busqueda=' . urlencode($busqueda) : ''; ?>'">Cancelar</button>
                                    <button type="submit">Actualizar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

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

</body>
</html>