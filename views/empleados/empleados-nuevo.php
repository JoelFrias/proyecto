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

/* Fin de verificacion de sesion */

require '../../models/conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $tipo_identificacion = trim($_POST['tipo_identificacion']);
    $identificacion = trim($_POST['identificacion']);
    $telefono = trim($_POST['telefono']);
    $idPuesto = intval($_POST['idPuesto']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Encriptar la contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Iniciar transacción
    $conn->begin_transaction();
    try {

        // Verificar si el nombre de usuario ya existe
        $queryVerificarUsuario = "SELECT COUNT(*) as count FROM usuarios WHERE username = ?";
        $stmt = $conn->prepare($queryVerificarUsuario);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count > 0) {
            throw new Exception('El nombre de usuario ya existe.'); // Lanzar excepción si el nombre de usuario ya existe
        }

        // Verificar si la identificación ya existe
        $queryVerificarIdentificacion = "SELECT COUNT(*) as count FROM empleados WHERE identificacion = ?";
        $stmt = $conn->prepare($queryVerificarIdentificacion);
        $stmt->bind_param("s", $identificacion);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        if ($count > 0) {
            throw new Exception('La identificación ya existe.'); // Lanzar excepción si la identificación ya existe
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
            throw new Exception('El teléfono ya existe.'); // Lanzar excepción si el teléfono ya existe
        }

        // Insertar empleado
        $queryEmpleado = "INSERT INTO empleados (nombre, apellido, tipo_identificacion, identificacion, telefono, idPuesto, fechaIngreso, activo) VALUES (?, ?, ?, ?, ?, ?, NOW(), TRUE)";
        $stmt = $conn->prepare($queryEmpleado);
        $stmt->bind_param("sssssi", $nombre, $apellido, $tipo_identificacion, $identificacion, $telefono, $idPuesto);
        $stmt->execute();
        $idEmpleado = $stmt->insert_id;
        $stmt->close();

        // Insertar usuario
        $queryUsuario = "INSERT INTO usuarios (username, password, idEmpleado) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($queryUsuario);
        $stmt->bind_param("ssi", $username, $hashed_password, $idEmpleado);
        $stmt->execute();
        $stmt->close();

        /**
         *  2. Auditoria de acciones de usuario
         */

        require_once '../../models/auditorias.php';
        $usuario_id = $_SESSION['idEmpleado'];
        $accion = 'Nuevo Empleado';
        $detalle = 'IdEmpleado: ' . $idEmpleado . ', Nombre: ' . $nombre . ', Apellido: ' . $apellido;
        $ip = $_SERVER['REMOTE_ADDR']; // Obtener la dirección IP del cliente
        registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

        // Confirmar transacción
        $conn->commit();
        $_SESSION['success_message'] = 'Registro exitoso.'; // Almacenar mensaje de éxito
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        $_SESSION['error_message'] = 'Error en el registro: ' . $e->getMessage(); // Almacenar mensaje de error
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Nuevo Empleado</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/registro_empleados.css">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
    <style>
        .btn-volver {
        background-color: #f5f5f5;
        border: 1px solid #ccc;
        color: #333;
        padding: 10px 20px;
        font-size: 16px;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.2s, box-shadow 0.2s;
        }

        .btn-volver:hover {
        background-color: #e0e0e0;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-volver:active {
        background-color: #d5d5d5;
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

            <div class="form-container">
                <h2 class="form-title">Registro de Empleado</h2>
                <form class="registration-form" action="" method="post">
                <legend>Datos del Empleado</legend>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre">Nombre:</label>
                            <input type="text" id="nombre" name="nombre" autocomplete="off" placeholder="Nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="apellido">Apellido:</label>
                            <input type="text" id="apellido" name="apellido" autocomplete="off" placeholder="Apellido" required>
                        </div>
                        <div class="form-group">
                            <label for="tipo_identificacion">Tipo de Identificación:</label>
                            <select id="tipo_identificacion" name="tipo_identificacion" required>
                                <option value="Cedula">Cédula</option>
                                <option value="Pasaporte">Pasaporte</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="identificacion">Identificación:</label>
                            <input type="number" id="identificacion" name="identificacion" autocomplete="off" placeholder="Identificacion" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono:</label>
                            <input type="text" id="telefono" name="telefono" autocomplete="off" placeholder="000-000-0000" minlength="12" maxlength="12" required>
                        </div>
                        <div class="form-group">
                            <label for="idPuesto">Puesto:</label>
                            <select id="idPuesto" name="idPuesto" required>
                                <?php
                                // Obtener el id y la descripción de los tipos de producto
                                $sql = "SELECT id, descripcion FROM empleados_puestos ORDER BY descripcion ASC";
                                $resultado = $conn->query($sql);

                                if ($resultado->num_rows > 0) {
                                    while ($fila = $resultado->fetch_assoc()) {
                                        echo "<option value='" . $fila['id'] . "'>" . $fila['descripcion'] . "</option>";
                                    }
                                } else {
                                    echo "<option value='' disabled>No hay opciones</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <legend>Datos de Usuario</legend>
                            <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Usuario:</label>
                            <input type="text" id="username" name="username" placeholder="Usuario" autocomplete="off" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Contraseña:</label>
                            <input type="password" id="password" name="password" placeholder="Contraseña" autocomplete="off" minlength="4" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">Guardar Cambios</button>
                    <button class="btn-volver" onclick="history.back()">← Volver atrás</button>
                </form>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <!--script de manejo de mensajes-->
    <script>
        // Verificar si hay un mensaje de éxito y mostrarlo con SweetAlert2
        <?php if (isset($_SESSION['success_message'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: '<?php echo $_SESSION['success_message']; ?>',
            });
            <?php unset($_SESSION['success_message']); ?> // Limpiar el mensaje de la sesión
        <?php endif; ?>

        // Verificar si hay un mensaje de error y mostrarlo con SweetAlert2
        <?php if (isset($_SESSION['error_message'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?php echo $_SESSION['error_message']; ?>',
            });
            <?php unset($_SESSION['error_message']); ?> // Limpiar el mensaje de la sesión
        <?php endif; ?>
    </script>

    <!-- Script para formatear el número de teléfono -->
    <script>
        const telefonoInput = document.getElementById('telefono');
        telefonoInput.addEventListener('input', function () {
            let value = this.value.replace(/[^0-9]/g, '');  // Eliminar cualquier carácter que no sea número

            // Agregar el primer guion después de los tres primeros números
            if (value.length > 3 && value.charAt(3) !== '-') {
                value = value.slice(0, 3) + '-' + value.slice(3);
            }

            // Agregar el segundo guion después de los seis primeros números (3+3)
            if (value.length > 6 && value.charAt(6) !== '-') {
                value = value.slice(0, 7) + '-' + value.slice(7);
            }

            // Asignar el valor al campo de entrada
            this.value = value;
        });
    </script>

</body>
</html>