<?php

/**
 *  NOTA:
 *      
 *  Este archivo esta fuera de uso desde el 11 de noviembre de 2025,
 *  debido a que se actualizo la forma de gestion de usuarios en el sistema.
 * 
 */

echo "Este archivo ya no está en uso.";

exit(); // Detener la ejecución del script para evitar su uso accidental


























/* Verificacion de sesion */

// Iniciar sesión
session_start();

// Configurar el tiempo de caducidad de la sesión
$inactivity_limit = 900; // 15 minutos en segundos

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header('Location: ../../app/auth/login.php'); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header("Location: ../../app/auth/login.php?session_expired=session_expired"); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

// Incluir la conexión a la base de datos
require '../../core/conexion.php';

// Procesar la actualización si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    $id = intval($_POST['id']);
    $new_username = trim($_POST['new_username']);
    $new_password = trim($_POST['new_password']);

    // Validar que el ID del usuario sea un número entero
    if (!filter_var($id, FILTER_VALIDATE_INT)) {
        $_SESSION['error_message'] = 'ID de usuario inválido.';
        header('Location: usuarios-editar.php');
        exit();
    }

    // Validar que el nuevo nombre de usuario no esté vacío
    if (empty($new_username)) {
        $_SESSION['error_message'] = 'El nombre de usuario no puede estar vacío.';
        header('Location: usuarios-editar.php');
        exit();
    }

    // Validar que la nueva contraseña tenga al menos 4 caracteres
    if (strlen($new_password) < 4) {
        $_SESSION['error_message'] = 'La contraseña debe tener al menos 4 caracteres.';
        header('Location: usuarios-editar.php');
        exit();
    }

    // Validar que el usuario no exista ya en la base de datos
    $sql_check = "SELECT id FROM usuarios WHERE username = ? AND id != ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("si", $new_username, $id);
    $stmt->execute();
    $resultado_check = $stmt->get_result();
    if ($resultado_check->num_rows > 0) {
        $_SESSION['error_message'] = 'El nombre de usuario ya está en uso.';
        $stmt->close();
        header('Location: usuarios-editar.php');
        exit();
    }
    $stmt->close();

    // Verificar si la identificación ya existe
    $queryVerificarIdentificacion = "SELECT COUNT(*) as count FROM empleados WHERE identificacion = ?";
    $stmt = $conn->prepare($queryVerificarIdentificacion);
    $stmt->bind_param("s", $identificacion);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    if ($count > 0) {
        $_SESSION['error_message'] = 'La identificación ya existe.';
        $stmt->close();
        header('Location: usuarios-editar.php');
        exit();
    }

    // Verificar si el teléfono ya existe
    $queryVerificarTelefono = "SELECT COUNT(*) as count FROM empleados WHERE telefono = ?";
    $stmt = $conn->prepare($queryVerificarTelefono);
    $stmt->bind_param("s", $telefono);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    if ($count > 0) {
        $_SESSION['error_message'] = 'El telefono ya existe.';
        $stmt->close();
        header('Location: usuarios-editar.php');
        exit();
    }

    // Validar que se ingresen ambos campos
    if (empty($new_username) || empty($new_password)) {
        $_SESSION['error_message'] = 'Debe completar ambos campos.';
    } else {
        // Encriptar la nueva contraseña
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Actualizar el usuario en la base de datos
        $sql_update = "UPDATE usuarios SET username = ?, password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ssi", $new_username, $hashed_password, $id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Usuario actualizado con éxito.';

        /**
         *  2. Auditoria de acciones de usuario
         */

        require_once '../../core/auditorias.php';
        $usuario_id = $_SESSION['idEmpleado'];
        $accion = 'Modificar usuario';
        $detalle = 'Se modificó el usuario con ID: ' . $id;
        $ip = $_SERVER['REMOTE_ADDR']; // Obtener la dirección IP del cliente
        registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

        } else {
            $_SESSION['error_message'] = 'Error al actualizar el usuario: ' . $stmt->error;
        }
        $stmt->close();
    }

    // Redirigir para evitar reenvío del formulario
    header('Location: ../../app/gestion/usuarios-editar.php');
    exit();
}

// Inicializar la variable de búsqueda
$busqueda = '';
if (isset($_GET['busqueda'])) {
    $busqueda = trim($_GET['busqueda']);
}

// Obtener la lista de usuarios con filtro de búsqueda si existe
$sql = "SELECT id, username FROM usuarios";

// Agregar condición de búsqueda si se proporcionó un término
if (!empty($busqueda)) {
    $busqueda = '%' . $busqueda . '%';
    $sql .= " WHERE username LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $busqueda);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    $resultado = $conn->query($sql);
}

// Obtener los datos del usuario a editar (si se ha hecho clic en "Modificar")
$usuario_editar = null;
if (isset($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    $sql_editar = "SELECT id, username FROM usuarios WHERE id = $id_editar";
    $resultado_editar = $conn->query($sql_editar);
    if ($resultado_editar->num_rows > 0) {
        $usuario_editar = $resultado_editar->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Editar Usuarios</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
    <style>

        *{
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body{
            background-color: #f5f6fa;
        }
        
        /* Estilos para el formulario de búsqueda */
        .emp_search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .emp_search-input {
            flex-grow: 1;
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            font-size: 0.875rem;
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
        }

        .emp_search-button:hover {
            background-color: #2563eb;
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

        @media (max-width: 768px) {
            .emp_search-form {
                flex-direction: column;
            }
        }
        /* Estilos específicos para la tabla de usuarios - con prefijo emp_ para evitar conflictos */
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
            padding: 1rem;
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
            font-size: 1rem;
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
            font-size: 0.875rem;
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

        /* Modal Styles */
        .emp_modal {
            display: <?php echo ($usuario_editar ? 'flex' : 'none'); ?>;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .emp_modal-content {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            padding: 1.5rem;
            animation: emp_modalFadeIn 0.3s ease;
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
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #333;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.75rem;
        }

        .emp_form-group {
            margin-bottom: 1rem;
        }

        .emp_form-group label {
            display: block;
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .emp_form-group input {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            background-color: white;
        }

        .emp_form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .emp_form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .emp_form-actions button {
            padding: 0.625rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
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

        @media (max-width: 768px) {
            .toggle-btn {
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

        /* Responsive Styles */
        @media (max-width: 768px) {
            .emp_desktop-view {
                display: none;
            }

            .emp_mobile-view {
                display: block;
            }

            .emp_modal-content {
                width: 95%;
                padding: 1rem;
            }
        }
    </style>

</head>
<body>
    
    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->
            <div class="emp_general-container">
                <div class="emp_header">
                    <h1>Lista de Usuarios</h1>
                    
                    <!-- Formulario de búsqueda -->
                    <form action="" method="GET" class="emp_search-form">
                        <input type="text" name="busqueda" placeholder="Buscar por nombre de usuario..." class="emp_search-input" value="<?php echo htmlspecialchars($busqueda ?? ''); ?>">
                        <button type="submit" class="emp_search-button">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        <?php if (!empty($busqueda)): ?>
                        <a href="usuarios-editar.php" class="emp_clear-search">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                        <?php endif; ?>
                    </form>
                    
                    <!-- Contador de resultados -->
                    <?php if ($resultado): ?>
                    <div class="emp_results-count">
                        <?php 
                        $num_resultados = $resultado->num_rows;
                        echo "Se encontraron $num_resultados " . ($num_resultados == 1 ? "usuario" : "usuarios");
                        if (!empty($busqueda)) {
                            echo " para la búsqueda: \"" . htmlspecialchars(trim($_GET['busqueda'])) . "\"";
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Vista de Escritorio -->
                <div class="emp_desktop-view">
                    <div class="emp_table-card">
                        <table class="emp_table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($resultado && $resultado->num_rows > 0): ?>
                                    <?php while ($fila = $resultado->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fila['username']); ?></td>
                                            <td>
                                                <a href="usuarios-editar.php?editar=<?php echo $fila['id']; ?><?php echo !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : ''; ?>" class="emp_btn-edit">Modificar</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2">No hay usuarios registrados<?php echo !empty($busqueda) ? ' que coincidan con la búsqueda.' : '.'; ?></td>
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
                                        <h3 class="emp_mobile-card-title"><?php echo htmlspecialchars($fila['username']); ?></h3>
                                    </div>
                                    <a href="usuarios-editar.php?editar=<?php echo $fila['id']; ?><?php echo !empty($busqueda) ? '&busqueda=' . urlencode($busqueda) : ''; ?>" class="emp_btn-edit">Modificar</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="emp_mobile-card">
                            <p>No hay usuarios registrados<?php echo !empty($busqueda) ? ' que coincidan con la búsqueda.' : '.'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal de edición -->
            <?php if ($usuario_editar): ?>
                <div class="emp_modal">
                    <div class="emp_modal-content">
                        <h2>Modificar Usuario</h2>
                        <form action="" method="post">
                            <input type="hidden" name="id" value="<?php echo $usuario_editar['id']; ?>">
                            <?php if (!empty($busqueda)): ?>
                            <input type="hidden" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>">
                            <?php endif; ?>
                            
                            <div class="emp_form-group">
                                <label for="new_username">Nuevo Usuario:</label>
                                <input type="text" id="new_username" name="new_username" value="<?php echo htmlspecialchars($usuario_editar['username']); ?>" required>
                            </div>
                            
                            <div class="emp_form-group">
                                <label for="new_password">Nueva Contraseña:</label>
                                <input type="password" id="new_password" name="new_password" minlength="4" required>
                            </div>
                            
                            <div class="emp_form-actions">
                                <button type="button" onclick="window.location.href='usuarios-editar.php<?php echo !empty($busqueda) ? '?busqueda=' . urlencode($busqueda) : ''; ?>'">Cancelar</button>
                                <button type="submit" name="update_user">Actualizar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        
        <!-- TODO EL CONTENIDO ANTES DE ESTA LINEA -->
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