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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Gestión de Inventario</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Librería de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
    <style>
        :root {
            --primary-color-inventario: #2c3e50;
            --secondary-color-inventario: #2c3e50;
            --text-color-inventario: #333333;
            --bg-color-inventario: #f5f6fa;
            --input-bg-inventario: #ffffff;
            --border-color-inventario: #ced4da;
            --success-color-inventario: #28a745;
            --shadow-inventario: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .page-content {
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color-inventario);
            color: var(--text-color-inventario);
        }
        
        .page-content h1 {
            color: var(--secondary-color-inventario);
            text-align: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--secondary-color-inventario);
            font-size: 1.6rem;
        }
        
        .page-content h2 {
            color: var(--primary-color-inventario);
            margin: 0.8rem 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
        }
        
        .page-content .container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .page-content .card {
            background: var(--input-bg-inventario);
            border-radius: 6px;
            padding: 1.2rem;
            box-shadow: var(--shadow-inventario);
        }
        
        .page-content .buscador {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 0.5rem;
        }
        
        .page-content .buscador button {
            font-size: 0.85rem;
            padding: 0.5rem 0.8rem;
        }
        
        .page-content .header-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.8rem;
        }
        
        .page-content .input-group {
            margin-bottom: 0.8rem;
            display: flex;
            flex-direction: column;
        }
        
        .page-content label {
            margin-bottom: 0.3rem;
            font-weight: 500;
            color: var(--secondary-color-inventario);
            font-size: 0.85rem;
        }
        
        .page-content input, .page-content select, .page-content textarea {
            padding: 0.6rem;
            border: 1px solid var(--border-color-inventario);
            border-radius: 4px;
            background-color: var(--input-bg-inventario);
            font-size: 0.85rem;
            outline: none;
            transition: border-color-inventario 0.3s;
        }
        
        .page-content input:focus, .page-content select:focus, .page-content textarea:focus {
            border-color: var(--primary-color-inventario);
            box-shadow: 0 0 0 2px rgba(58, 110, 165, 0.2);
        }
        
        .page-content input[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        
        .page-content textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .page-content button {
            padding: 0.5rem 1rem;
            background-color: var(--primary-color-inventario);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .page-content button:hover {
            background-color: var(--secondary-color-inventario);
        }
        
        .page-content .botones {
            display: flex;
            justify-content: flex-end;
            margin-top: 0.8rem;
        }
        
        .page-content .nota {
            background-color: rgba(58, 110, 165, 0.1);
            padding: 0.6rem;
            border-radius: 4px;
            margin-bottom: 0.8rem;
            font-size: 0.8rem;
        }

        /* Estilo para los campos de búsqueda según la imagen */
        .page-content .productos {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-content .productos .input-group {
            display: flex;
            flex-direction: column;
        }

        .page-content .productos label {
            display: block;
            margin-bottom: 0.3rem;
        }

        .page-content #id-producto {
            width: 200px;
            border-radius: 5px;
        }

        .page-content #descripcion-producto {
            flex-grow: 1;
            min-width: 300px;
            border-radius: 5px;
        }
        
        /* Responsive design */
        @media (min-width: 768px) {
            .page-content .transacciones {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1.5rem;
            }
        }
        
        @media (max-width: 767px) {
            .page-content .transacciones {
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .page-content input, .page-content select, .page-content textarea {
                width: 100%;
            }
        }

        /* Estilos para el modal de búsqueda */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 900px;
            position: relative;
            animation: modalFadeIn 0.3s;
        }

        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .close-edit-banks {
            position: absolute;
            right: 20px;
            top: 15px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-edit-banks:hover {
            color: var(--primary-color-inventario);
        }

        .modal h2 {
            color: var(--primary-color-inventario);
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 1.5rem;
        }

        .modal label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary-color-inventario);
            font-size: 0.9rem;
        }

        .modal input[type="search"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color-inventario);
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            box-sizing: border-box;
        }

        .modal input[type="search"]:focus {
            border-color: var(--primary-color-inventario);
            box-shadow: 0 0 0 2px rgba(58, 110, 165, 0.2);
            outline: none;
        }

        .modal table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.9rem;
        }

        .modal table thead {
            background-color: #f8f9fa;
        }

        .modal table th,
        .modal table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .modal table th {
            font-weight: 600;
            color: var(--primary-color-inventario);
        }

        .modal table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .modal table button {
            background-color: var(--primary-color-inventario);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background-color 0.3s;
        }

        .modal table button:hover {
            background-color: var(--secondary-color-inventario);
        }

        /* Estilos responsivos */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 15px;
            }
            
            .modal table {
                font-size: 0.8rem;
            }
            
            .modal th:nth-child(3),
            .modal td:nth-child(3) {
                display: none; /* Ocultar columna de existencia en dispositivos pequeños */
            }
        }

        /* Loader para indicación de carga */
        .loader {
            border: 3px solid #f3f3f3;
            border-radius: 50%;
            border-top: 3px solid var(--primary-color-inventario);
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../views/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

            <h1>Gestión de Inventario</h1>
    
            <div class="container">
                <div class="card">
                    <div class="header-group">
                        <h2>Detalles del Producto Seleccionado:</h2>
                        <div class="buscador">
                            <button id="buscar-producto">Buscar Producto</button>
                        </div>
                    </div>
                    
                    <div class="productos">
                        <div class="input-group">
                            <label for="id-producto">ID del producto:</label>
                            <input type="text" name="id-producto" id="id-producto" placeholder="ID del Producto" readonly>
                        </div>
                        
                        <div class="input-group">
                            <label for="descripcion-producto">Descripción del producto:</label>
                            <input type="text" name="descripcion-producto" id="descripcion-producto" placeholder="Descripción del Producto" readonly>
                        </div>
                        
                    </div>
                </div>
                
                <div class="transacciones">
                    <div class="entrada card">
                        <h2>Entrada de Productos</h2>
                        
                        <div class="input-group">
                            <label for="cantidad-entrante">Cantidad Entrante:</label>
                            <input type="number" name="cantidad-entrante" id="cantidad-entrante" placeholder="Cantidad Entrante">
                        </div>
                        
                        <div class="input-group">
                            <label for="descripcion-nueva">Nueva Descripción:</label>
                            <input type="text" name="descripcion-nueva" id="descripcion-nueva" placeholder="Nueva Descripción">
                        </div>
                        
                        <div class="input-group">
                            <label for="compra-nueva">Nuevo Precio de Compra:</label>
                            <input type="number" name="compra-nueva" id="compra-nueva" placeholder="Nuevo Precio de Compra">
                        </div>
                        
                        <div class="input-group">
                            <label for="venta1-nuevo">Nuevo Precio de Venta 1:</label>
                            <input type="number" name="venta1-nuevo" id="venta1-nuevo" placeholder="Precio Venta 1">
                        </div>
                        
                        <div class="input-group">
                            <label for="venta2-nuevo">Nuevo Precio de Venta 2:</label>
                            <input type="number" name="venta2-nuevo" id="venta2-nuevo" placeholder="Precio Venta 2">
                        </div>
                        
                        <div class="input-group">
                            <label for="reorden-nuevo">Nuevo Reorden:</label>
                            <input type="number" name="reorden-nuevo" id="reorden-nuevo" placeholder="Precio Reorden">
                        </div>
                        
                        <div class="botones">
                            <button id="enviar-entrada">Efectuar Entrada</button>
                        </div>
                    </div>
                    
                    <div class="salida card">
                        <h2>Salida de Productos</h2>
                        
                        <div class="nota">
                            <p>Nota: Para efectuar el retiro de productos del inventario, estos deben encontrarse previamente en el almacén principal.</p>
                        </div>
                        
                        <div class="input-group">
                            <label for="cantidad-saliente">Cantidad Saliente:</label>
                            <input type="number" name="cantidad-saliente" id="cantidad-saliente" placeholder="Cantidad Saliente">
                        </div>
                        
                        <div class="input-group">
                            <label for="motivo-retiro">Motivo de Retiro:</label>
                            <select name="motivo-retiro" id="motivo-retiro">
                                <option value="" disabled selected>Seleccionar</option>
                                <option value="Consumo interno">Consumo interno</option>
                                <option value="Muestras o demostraciones">Muestras o demostraciones</option>
                                <option value="Pérdidas por caducidad o deterioro">Pérdidas por caducidad o deterioro</option>
                                <option value="Ajustes de inventario">Ajustes de inventario</option>
                                <option value="Robos o extravíos">Robos o extravíos</option>
                                <option value="Devoluciones a proveedores">Devoluciones a proveedores</option>
                                <option value="Donaciones">Donaciones</option>
                            </select>
                        </div>
                        
                        <div class="input-group">
                            <label for="detalles-salida">Detalle de la salida:</label>
                            <textarea name="detalles-salida" id="detalles-salida" placeholder="Detalles o Motivos del Retiro"></textarea>
                        </div>
                        
                        <div class="botones">
                            <button id="enviar-salida">Efectuar Retiro</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal">
                <div class="modal-content">
                    <span class="close-edit-banks">&times;</span>
                    
                    <h2>Buscar Productos</h2>
                    
                    <label for="buscador-productos">Buscador:</label>
                    <input type="search" name="buscador-productos" id="buscador-productos" placeholder="Buscar producto por ID o Descripción">
                    
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Descripción</th>
                                <th>Existencia</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-buscador-productos">
                            <!-- Los resultados se cargarán aquí dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>


        <!-- TODO EL CONTENIDO POR ENCIMA DE ESTA LINEA -->
        </div>
    </div>
    

    <script>
        // Script para gestión de inventario
        document.addEventListener('DOMContentLoaded', function() {
            // Referencias a elementos DOM
            const buscarProductoBtn = document.getElementById('buscar-producto');
            const modal = document.querySelector('.modal');
            const closeModalBtn = document.querySelector('.close-edit-banks');
            const buscadorInput = document.getElementById('buscador-productos');
            const tbodyBuscador = document.getElementById('tbody-buscador-productos');
            const idProductoInput = document.getElementById('id-producto');
            const descripcionProductoInput = document.getElementById('descripcion-producto');
            
            // Botones para operaciones de inventario
            const enviarEntradaBtn = document.getElementById('enviar-entrada');
            const enviarSalidaBtn = document.getElementById('enviar-salida');
            
            // Campos para entrada de productos
            const cantidadEntranteInput = document.getElementById('cantidad-entrante');
            const descripcionNuevaInput = document.getElementById('descripcion-nueva');
            const compraNuevaInput = document.getElementById('compra-nueva');
            const venta1NuevoInput = document.getElementById('venta1-nuevo');
            const venta2NuevoInput = document.getElementById('venta2-nuevo');
            const reordenNuevoInput = document.getElementById('reorden-nuevo');
            
            // Campos para salida de productos
            const cantidadSalienteInput = document.getElementById('cantidad-saliente');
            const motivoRetiroSelect = document.getElementById('motivo-retiro');
            const detallesSalidaTextarea = document.getElementById('detalles-salida');

            // Mostrar modal de búsqueda
            buscarProductoBtn.addEventListener('click', function() {
                modal.style.display = 'block';
                buscadorInput.focus();
                buscarProductos(''); // Cargar todos los productos inicialmente
            });

            // Cerrar modal
            closeModalBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            // Cerrar modal al hacer clic fuera de él
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Búsqueda en tiempo real
            buscadorInput.addEventListener('input', function() {
                const searchTerm = this.value.trim();
                buscarProductos(searchTerm);
            });

            // Función para buscar productos con AJAX
            function buscarProductos(searchTerm) {
                // Mostrar indicador de carga
                tbodyBuscador.innerHTML = '<tr><td colspan="4" style="text-align: center;">Cargando...</td></tr>';
                
                // Realizar petición AJAX con fetch
                fetch('../../controllers/inventario/buscar-productos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'search=' + encodeURIComponent(searchTerm)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {

                    // Limpiar tabla
                    tbodyBuscador.innerHTML = '';
                    
                    // Verificar si hay resultados
                    if (data.length === 0) {
                        tbodyBuscador.innerHTML = '<tr><td colspan="4" style="text-align: center;">No se encontraron productos</td></tr>';
                        return;
                    }
                    
                    // Llenar tabla con resultados
                    data.forEach(producto => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${producto.id}</td>
                            <td>${producto.descripcion}</td>
                            <td>${producto.existencia}</td>
                            <td><button class="seleccionar-btn" data-id="${producto.id}" 
                                data-descripcion="${producto.descripcion}"
                                data-compra="${producto.precio_compra}"
                                data-venta1="${producto.precio_venta1}"
                                data-venta2="${producto.precio_venta2}"
                                data-reorden="${producto.punto_reorden}">Seleccionar</button></td>
                        `;
                        tbodyBuscador.appendChild(row);
                    });
                    
                    // Agregar event listeners a los botones de selección
                    document.querySelectorAll('.seleccionar-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            seleccionarProducto(this.dataset);
                        });
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    tbodyBuscador.innerHTML = `<tr><td colspan="4" style="text-align: center;">Error al cargar productos: ${error.message}</td></tr>`;
                });
            }

            // Función para seleccionar un producto
            function seleccionarProducto(dataset) {
                // Rellenar campos de producto
                idProductoInput.value = dataset.id;
                descripcionProductoInput.value = dataset.descripcion;
                
                // Rellenar campos de entrada con datos actuales del producto
                descripcionNuevaInput.value = dataset.descripcion;
                compraNuevaInput.value = dataset.compra || '';
                venta1NuevoInput.value = dataset.venta1 || '';
                venta2NuevoInput.value = dataset.venta2 || '';
                reordenNuevoInput.value = dataset.reorden || '';
                
                // Cerrar modal
                modal.style.display = 'none';
            }

            // Gestionar entrada de productos
            enviarEntradaBtn.addEventListener('click', function() {
                // Validar que hay un producto seleccionado
                if (!idProductoInput.value) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Debe seleccionar un producto primero'
                    });
                    return;
                }
                
                // Validar cantidad
                if (!cantidadEntranteInput.value || cantidadEntranteInput.value <= 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'La cantidad debe ser un número positivo'
                    });
                    return;
                }
                
                // Crear objeto con datos de entrada
                const entradaData = {
                    id_producto: idProductoInput.value,
                    cantidad: cantidadEntranteInput.value,
                    descripcion: descripcionNuevaInput.value,
                    precio_compra: compraNuevaInput.value,
                    precio_venta1: venta1NuevoInput.value,
                    precio_venta2: venta2NuevoInput.value,
                    punto_reorden: reordenNuevoInput.value
                };
                
                // Enviar datos al servidor
                fetch('../../controllers/inventario/registrar-entrada.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(entradaData)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: 'Entrada de productos registrada correctamente'
                        });
                        
                        // Limpiar formulario
                        cantidadEntranteInput.value = '';
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Error al registrar la entrada'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al procesar la solicitud: ' + error.message
                    });
                });
            });

            // Gestionar salida de productos
            enviarSalidaBtn.addEventListener('click', function() {
                // Validar que hay un producto seleccionado
                if (!idProductoInput.value) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Debe seleccionar un producto primero'
                    });
                    return;
                }
                
                // Validar cantidad
                if (!cantidadSalienteInput.value || cantidadSalienteInput.value <= 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'La cantidad debe ser un número positivo'
                    });
                    return;
                }
                
                // Validar motivo
                if (!motivoRetiroSelect.value) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Debe seleccionar un motivo de retiro'
                    });
                    return;
                }
                
                // Crear objeto con datos de salida
                const salidaData = {
                    id_producto: idProductoInput.value,
                    cantidad: cantidadSalienteInput.value,
                    motivo: motivoRetiroSelect.value,
                    detalles: detallesSalidaTextarea.value
                };
                
                // Enviar datos al servidor
                fetch('../../controllers/inventario/registrar-salida.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(salidaData)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: 'Salida de productos registrada correctamente'
                        });
                        
                        // Limpiar formulario
                        cantidadSalienteInput.value = '';
                        motivoRetiroSelect.selectedIndex = 0;
                        detallesSalidaTextarea.value = '';
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Error al registrar la salida'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al procesar la solicitud: ' + error.message
                    });
                });
            });
        });
    </script>


</body>
</html>