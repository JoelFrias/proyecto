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

require '../../models/conexion.php';

// Validar si el formulario fue enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Obtener y sanitizar los datos del formulario
    $descripcion = htmlspecialchars(trim($_POST['descripcion']));
    $idTipo = isset($_POST['tipo']) ? intval($_POST['tipo']) : 0; // Captura el idTipo aquí
    $cantidad = floatval($_POST['cantidad']);
    $precioCompra = floatval($_POST['precioCompra']);
    $precio1 = floatval($_POST['precio1']);
    $precio2 = floatval($_POST['precio2']);
    $reorden = floatval($_POST['reorden']);

    // Debug: Imprimir el idTipo para verificar
    error_log("ID Tipo: " . $idTipo); // Esto se registrará en el log de errores

    // Manejo de errores con consultas preparadas
    try {
        // Iniciar la transacción
        $conn->begin_transaction();
    
        // Insertar en la tabla 'productos'
        $stmt = $conn->prepare("INSERT INTO productos (descripcion, idTipo, existencia, precioCompra, precioVenta1, precioVenta2, reorden, fechaRegistro, activo) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), TRUE)");
        $stmt->bind_param("siidddd", $descripcion, $idTipo, $cantidad, $precioCompra, $precio1, $precio2, $reorden);
        $stmt->execute();
    
        // Obtener el ID del producto recién insertado
        $idProducto = $stmt->insert_id;
    
        // Insertar en la tabla 'inventario'
        $stmt = $conn->prepare("INSERT INTO inventario (idProducto, existencia, ultima_actualizacion) 
                                VALUES (?, ?, NOW())");
        $stmt->bind_param("id", $idProducto, $cantidad);
        $stmt->execute();

        // Insertar en la tabla 'inventariotransacciones'
        $stmt = $conn->prepare("INSERT INTO `inventariotransacciones`(`tipo`, `idProducto`, `cantidad`, `fecha`, `descripcion`,`idEmpleado`) VALUES (?,?,?,NOW(),?,?)");
        $tipo = "ingreso";
        $descripcionTransaccion = "Ingreso por nuevo producto: ";
        $stmt->bind_param("siisi", $tipo, $idProducto, $cantidad, $descripcionTransaccion, $_SESSION['idEmpleado']);
        $stmt->execute();

        /**
         *  2. Auditoria de acciones de usuario
         */

        require_once '../../models/auditorias.php';
        $usuario_id = $_SESSION['idEmpleado'];
        $accion = 'Nuevo Producto';
        $detalle = 'Se ha registrado un nuevo producto: ' . $idProducto . ' - ' . $descripcion;
        $ip = $_SERVER['REMOTE_ADDR']; // Obtener la dirección IP del cliente
        registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);
    
        // Confirmar la transacción
        $conn->commit();
    
        // Almacenar mensaje de éxito en sesión y redirigir
        $_SESSION['status'] = 'success';
        header("Location: productos-nuevo.php");
        exit;
    
    } catch (Exception $e) {
        // En caso de error, revertir la transacción
        $conn->rollback();
        $_SESSION['errors'][] = "Error al registrar producto: " . $e->getMessage();
        header("Location: productos-nuevo.php");
        exit;
    } finally {
        // Cerrar las declaraciones preparadas
        if (isset($stmt)) $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Nuevo Producto</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/mant_producto.css">
    <link rel="stylesheet" href="../../assets/css/producto_modal.css">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->

    <!-- Estilos para el modal -->
    <style>
        /* Modal Base */
        .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
        animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
        }

        .modal-content {
        background-color: #fff;
        margin: 5% auto;
        width: 90%;
        max-width: 800px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        animation: slideIn 0.3s;
        }

        @keyframes slideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
        }

        /* Modal Header */
        .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #e0e0e0;
        background-color: #f8f9fa;
        border-radius: 8px 8px 0 0;
        }

        .modal-header h2 {
        margin: 0;
        font-size: 1.4rem;
        color: #333;
        }

        .close {
        font-size: 28px;
        font-weight: bold;
        color: #666;
        cursor: pointer;
        transition: color 0.2s;
        }

        .close:hover {
        color: #000;
        }

        /* Modal Body */
        .modal-body {
        padding: 20px;
        }

        /* Add Form */
        .add-form {
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
        }

        .add-form h3 {
        margin-top: 0;
        font-size: 1.1rem;
        color: #444;
        }

        .input-group {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        }

        .input-group input {
        flex: 1;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        }

        .btn-add {
        background-color: #4CAF50;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
        }

        .btn-add:hover {
        background-color: #45a049;
        }

        /* Table Container */
        .table-container {
        margin-top: 10px;
        }

        .table-container h3 {
        margin-top: 0;
        font-size: 1.1rem;
        color: #444;
        }

        .search-input {
        width: 100%;
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        }

        .table-wrapper {
        max-height: 300px;
        overflow-y: auto;
        border-radius: 4px;
        border: 1px solid #e0e0e0;
        }

        /* Table Styles */
        table {
        width: 100%;
        border-collapse: collapse;
        }

        thead {
        background-color: #f8f9fa;
        position: sticky;
        top: 0;
        }

        th, td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
        }

        th {
        font-weight: 600;
        color: #333;
        }

        tbody tr:hover {
        background-color: #f5f5f5;
        }

        /* Action Buttons */
        .action-buttons {
        display: flex;
        gap: 5px;
        }

        .btn-edit, .btn-delete {
        padding: 6px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: background-color 0.2s;
        }

        .btn-edit {
        background-color: #2196F3;
        color: white;
        }

        .btn-edit:hover {
        background-color: #0b7dda;
        }

        .btn-delete {
        background-color: #f44336;
        color: white;
        }

        .btn-delete:hover {
        background-color: #d32f2f;
        }

        .btn-save {
        background-color: #4CAF50;
        color: white;
        }

        .btn-save:hover {
        background-color: #45a049;
        }

        .btn-cancel {
        background-color: #9e9e9e;
        color: white;
        }

        .btn-cancel:hover {
        background-color: #757575;
        }

        /* Edit Mode */
        .edit-mode input {
        width: 100%;
        padding: 6px;
        border: 1px solid #2196F3;
        border-radius: 4px;
        }

        /* Error Message */
        .error-message {
        color: #f44336;
        font-size: 14px;
        margin-top: 5px;
        min-height: 20px;
        }

        /* Loading Indicator */
        .loading {
        text-align: center;
        padding: 20px;
        color: #666;
        }

        /* Empty State */
        .empty-state {
        text-align: center;
        padding: 30px;
        color: #666;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .input-group {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            th, td {
                padding: 10px;
            }
        }

        /* boton volver */
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

            <!-- Contenedor del formulario de registro de productos -->
            <div class="form-container">
                <h1 class="form-title">Registro de Productos</h1>
                
                <form class="registration-form" action="" method="POST">
                    <fieldset>
                        <legend>Datos del Producto</legend>
                        <div class="form-grid-producto">
                            <div class="form-group">
                                <label for="descripcion">Descripción:</label>
                                <input type="text" id="descripcion" name="descripcion" autocomplete="off" placeholder="Nombre del producto" required>   
                            </div>

                            <div class="form-group">
                                <label for="tipo_identificacion">Tipo de Producto:</label>
                                <div class="input-button-container">
                                    <select id="tipo" name="tipo" required>
                                        <option value="" disabled selected>Seleccionar</option>
                                        <?php
                                        // Obtener el id y la descripción de los tipos de producto
                                        $sql = "SELECT id, descripcion FROM productos_tipo ORDER BY descripcion ASC";
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
                                    <!-- Botón para abrir el modal -->
                                    <button id="openModalBtn" onclick="" class="btn-abrir">Tipo Producto</button>
                                </div>
                            </div>    
                            <div class="form-group">
                                <label for="precioCompra">Precio de Compra:</label>
                                <input type="number" id="precioCompra" name="precioCompra" step="0.01" autocomplete="off" placeholder="Precio de Compra" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="precio1">Precio de Venta 1:</label>
                                <input type="number" id="precio1" name="precio1" step="0.01" autocomplete="off" placeholder="Precio de venta 1" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="precio2">Precio de Venta 2:</label>
                                <input type="number" id="precio2" name="precio2" step="0.01" autocomplete="off" placeholder="Precio de venta 2" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="cantidad">Cantidad Existente:</label>
                                <input type="number" id="cantidad" name="cantidad" step="0.01" autocomplete="off" placeholder="Cantidad existente" min="1" required>
                            </div>

                            <div class="form-group">
                                <label for="telefono">Reorden:</label>
                                <input type="number" id="reorden" name="reorden" step="0.01" autocomplete="off" placeholder="Reorden de producto" min="0" required>
                            </div>
                        </div>
                    </fieldset>

                    <button type="submit" class="btn-submit1">Registrar Producto</button>
                    <button class="btn-volver" onclick="history.back()">← Volver atrás</button>
                </form>
            </div>
        
        <!-- TODO EL CONTENIDO DE ESTA PAGINA ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <!-- Modal para Tipos de Producto -->
    <div id="tipoProductoModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
        <h2>Gestión de Tipos de Producto</h2>
        <span class="close">&times;</span>
        </div>
        <div class="modal-body">
        <!-- Formulario para agregar nuevo tipo -->
        <div class="add-form">
            <h3>Agregar Nuevo Tipo</h3>
            <div class="input-group">
            <input type="text" id="nuevoTipoNombre" placeholder="Nombre del tipo de producto" required>
            <button id="btnAgregarTipo" class="btn-add">Agregar</button>
            </div>
            <div id="mensajeError" class="error-message"></div>
        </div>
        
        <!-- Tabla de tipos existentes -->
        <div class="table-container">
            <h3>Tipos de Producto Existentes</h3>
            <input type="text" id="searchTipo" placeholder="Buscar tipo..." class="search-input">
            <div class="table-wrapper">
            <table id="tiposProductoTable">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Descripción</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody id="tiposProductoBody">
                <!-- Los datos se cargarán dinámicamente -->
                </tbody>
            </table>
            </div>
        </div>
        </div>
    </div>
    </div>

    <!-- Mostrar mensajes de éxito o error -->
    <?php 
        if (isset($_SESSION['status']) && $_SESSION['status'] === 'success') {
            echo "
                <script>
                    Swal.fire({
                        title: '¡Éxito!',
                        text: 'El producto ha sido registrado exitosamente.',
                        icon: 'success',
                        confirmButtonText: 'Aceptar'
                    });
                </script>
            ";
            unset($_SESSION['status']); // Limpiar el estado después de mostrar el mensaje
        }
        if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])) {
            foreach ($_SESSION['errors'] as $error) {
                echo "
                    <script>
                        Swal.fire({
                            title: '¡Error!',
                            text: '$error',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    </script>
                ";
            }
            unset($_SESSION['errors']); // Limpiar los errores después de mostrarlos
        }
    ?>

    <!-- Scripts adicionales -->
    <script src="../../assets/js/producto_modal.js"></script>

</body>
</html>