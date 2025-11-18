<?php

require_once '../../core/verificar-sesion.php'; // Verificar Session
require_once '../../core/conexion.php'; // Conexión a la base de datos

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'PRO001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
        
    exit(); 
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

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

            <!-- Contenedor del formulario de registro de productos -->
            <div class="form-container">
                <h1 class="form-title">Registro de Productos</h1>
                
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
                <!-- <button class="btn-volver" onclick="history.back()">← Volver atrás</button> -->

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

    <!-- Script para manejar el formulario de registro de productos -->
    <script>

        document.querySelector('.btn-submit1').addEventListener('click', function() {
            event.preventDefault(); // Evitar el envío del formulario por defecto

            // Obtener los valores de los campos
            const descripcion = document.getElementById('descripcion').value.trim();
            const tipo = document.getElementById('tipo').value;
            const precioCompra = parseFloat(document.getElementById('precioCompra').value);
            const precio1 = parseFloat(document.getElementById('precio1').value);
            const precio2 = parseFloat(document.getElementById('precio2').value);
            const cantidad = parseFloat(document.getElementById('cantidad').value);
            const reorden = parseFloat(document.getElementById('reorden').value);
            let errors = [];

            const productoData = {
                descripcion: descripcion,
                idTipo: tipo,
                cantidad: cantidad,
                precioCompra: precioCompra,
                precio1: precio1,
                precio2: precio2,
                reorden: reorden
            };

            // Validaciones
            if (descripcion === '') {
                errors.push('La descripción no puede estar vacía.');
            }
            if (!tipo) {
                errors.push('Debe seleccionar un tipo de producto.');
            }
            if (isNaN(precioCompra) || precioCompra <= 0) {
                errors.push('El precio de compra debe ser un número positivo.');
            }
            if (isNaN(precio1) || precio1 <= 0) {
                errors.push('El precio de venta 1 debe ser un número positivo.');
            }
            if (isNaN(precio2) || precio2 <= 0) {
                errors.push('El precio de venta 2 debe ser un número positivo.');
            }
            if (isNaN(cantidad) || cantidad <= 0) {
                errors.push('La cantidad existente debe ser un número no negativo.');
            }
            if (isNaN(reorden) || reorden < 0) {
                errors.push('El reorden debe ser un número no negativo.');
            }
            if (precio1 <= precioCompra) {
                errors.push('El precio de venta 1 no puede ser menor o igual que el precio de compra.');
            }
            if (precio2 <= precioCompra) {
                errors.push('El precio de venta 2 no puede ser menor o igual que el precio de compra.');
            }

            // Mostrar errores o enviar el formulario
            if (errors.length > 0) {
                Swal.fire({
                    title: '¡Error!',
                    html: errors.join('<br>'),
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
            } else {
                // enviar los datos al servidor usando fetch

                const url = '../../api/productos/producto-nuevo.php';

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json' 
                    },
                    body: JSON.stringify(productoData) 
                })
                .then(async response => {
                    // Intentar leer la respuesta como JSON, manejando el caso donde el body esté vacío o no sea JSON
                    const data = await response.json().catch(() => ({})); 

                    // Verificación de la respuesta HTTP (código fuera del rango 200-299)
                    if (!response.ok) {
                        
                        const errorMessage = data.message || `Error en la comunicación con el servidor (Código HTTP: ${response.status}).`;
                        
                        // Manejo específico de errores basados en el código de estado HTTP
                        if (response.status === 400 && data.errors) {
                            // Error 400: Solicitud Incorrecta (Errores de Validación)
                            let errorList = '<ul>' + data.errors.map(err => `<li>${err}</li>`).join('') + '</ul>';
                            Swal.fire({
                                icon: 'warning',
                                title: 'Validación de Datos Incompleta',
                                html: 'Se identificaron los siguientes requerimientos pendientes:<br>' + errorList,
                                confirmButtonText: 'Revisar'
                            });
                        } else if (response.status === 409) {
                            // Error 409: Conflicto (Ej. Identificación duplicada)
                            Swal.fire({
                                icon: 'error',
                                title: 'Conflicto de Integridad de Datos',
                                text: errorMessage,
                                confirmButtonText: 'Aceptar'
                            });
                        } else {
                            // Otros Errores del Servidor (500, 405, etc.)
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de Procesamiento en el Servidor',
                                text: errorMessage,
                                confirmButtonText: 'Cerrar'
                            });
                        }

                        // Se lanza un error para detener la ejecución de las promesas 'then' subsiguientes
                        throw new Error(`Fallo en la Solicitud (HTTP ${response.status}): ${errorMessage}`);
                    }

                    // Si la respuesta es OK (ej. 201 Created), se retorna el objeto de datos
                    return data; 
                })
                .then(data => {
                    // Manejo de la respuesta exitosa (success: true)
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Registro del Producto Exitoso',
                            html: `El producto con ID **${data.idProducto}** ha sido registrado satisfactoriamente: ${data.message}`,
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            window.location.reload(); // Recargar la página para limpiar el formulario
                        });
                    } else {
                        // En caso de que response.ok sea true, pero el cuerpo JSON indique un fallo lógico (success: false)
                        Swal.fire({
                            icon: 'error',
                            title: 'Fallo Lógico en la Aplicación',
                            text: data.message,
                            confirmButtonText: 'Cerrar'
                        });
                    }
                })
                .catch(error => {
                    // Captura errores de red (e.g., servidor inactivo, problemas de CORS) que no tienen código HTTP
                    if (!error.message.includes('HTTP')) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Interrupción de Conexión de Red',
                            text: 'No fue posible establecer comunicación con el servidor. Por favor, verifique su conexión e intente nuevamente.',
                            footer: `Detalle: ${error.message}`
                        });
                    }
                    // Opcional: Registrar el error completo en la consola para depuración.
                    // console.error('Error total en la solicitud fetch:', error);
                });

            }
        });

    </script>

</body>
</html>