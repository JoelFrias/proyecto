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

require_once '../../models/conexion.php';

$sql = "SELECT
            p.id AS id,
            p.descripcion AS descripcion,
            p.existencia AS existenciaGeneral,
            i.existencia AS existenciaInventario
        FROM
            productos AS p
        INNER JOIN inventario AS i
        ON
            p.id = i.idProducto
        WHERE
            p.activo = TRUE
        AND
            i.existencia > 0
        ORDER BY
            p.descripcion ASC
        ";
$result = $conn->query($sql);

if (!$result) {
    die("Error en la consulta: " . $conn->error); // Muestra el error de la consulta
}

if ($result->num_rows > 0) {
    // echo "Número de filas: " . $result->num_rows; // Muestra el número de filas obtenidas
} else {
    // echo "0 resultados";
}
?>
<!--NO BORRAR ESTO:> PORQUE ESTO ES COMO MUESTRA LOS PRODUCTOS EN SU RESPECTIVAS POSISCIONES -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Transacciones Inventario</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/facturacion.css">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
    <style>
        
        #regresar {
            background-color:rgb(62, 153, 250);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: auto;
            display: inline-block;
        }

        #regresar:hover {
            background-color: #0056b3;
            transform: scale(1.02);
        }

        /* Responsive para pantallas pequeñas */
        @media (max-width: 768px) {
            #regresar {
            width: 100%;
            display: block;
            margin-top: 10px;
            }
        }
        /* Contenedor de botones */
        .buttons-top {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        /* Estilo base del botón */
        .btntop {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            flex: 1;
            min-width: 180px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .btntop::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btntop:hover::before {
            width: 300px;
            height: 300px;
        }

        .btntop i {
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }

        .btntop span {
            position: relative;
            z-index: 1;
        }

        /* Botón de Entrega (Azul) */
        .btntop-entrega {
            background: linear-gradient(135deg, #4de664ff 0%, #28bd2aff 100%);
            color: white;
        }

        .btntop-entrega:hover {
            background: linear-gradient(135deg, #4de664ff 0%, #28bd2aff 100%);
            box-shadow: 0 4px 12px rgba(37, 235, 83, 0.4);
            transform: translateY(-2px);
        }

        .btntop-entrega:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }

        /* Botón de Retorno (Rojo) */
        .btntop-retorno {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btntop-retorno:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }

        .btntop-retorno:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
        }   

        /* Responsive Design */
        @media (max-width: 768px) {
            .buttons-top {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .btntop {
                width: 100%;
                min-width: unset;
                padding: 0.875rem 1.25rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .btntop {
                padding: 0.75rem 1rem;
                font-size: 0.85rem;
            }
            
            .btntop i {
                font-size: 1rem;
            }
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

    
            <button class="toggle-menu" id="toggleMenuFacturacion">☰</button>

            <div class="facturacion-container">

                <div class="buttons-top">
                    <button class="btntop btntop-entrega" onclick="window.location.href='registro-transacciones.php'">
                        <i class="fa-solid fa-house"></i>
                        <span>Regresar a Registro</span>
                    </button>
                    <button class="btntop btntop-retorno" onclick="window.location.href='inventario-devAlmacen.php'">
                        <i class="fas fa-box-open"></i>
                        <span>Realizar Retorno</span>
                    </button>
                </div>

                <h2>Realizar Entrega de Productos</h2><br>

                <h3>Seleccione los productos</h3><br>
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Buscar productos...">
                </div>
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo '<div class="products-grid" id="productsGrid">';
                            echo '  <div class="product-card">';
                            echo '       <div class="product-info">';
                            echo '         <div>';
                            echo '            <div class="product-name">' . $row["id"] .'   '. $row["descripcion"] . '</div>';
                            echo '            <div class="product-quantity">Existencia General: ' . $row["existenciaGeneral"] . '</div>';
                            echo '            <div class="product-quantity">Existencia en Almacén: ' . $row["existenciaInventario"] . '</div>';   
                            echo '        </div>';
                            echo '        <div class="product-total"></div>';
                            echo '    </div>';
                            echo '    <input type="number" class="quantity-input" id="quantity-' . $row["id"] . '" placeholder="Cantidad a llevar" min="1">';
                            echo '    <button class="quantity-button" onclick="addToCart(' . $row["id"] . ', \'' . addslashes($row["descripcion"]) . '\', ' . $row["existenciaGeneral"] . ', ' . $row["existenciaInventario"] . ')">Agregar Producto</button>';
                            echo '  </div>';
                            echo '</div>';
                        }
                    } else {
                        echo "No existe ningún producto disponible en el amacén principal";
                    }
                    ?>
                </div>
            </div>

            <!-- Modal Selección empleado -->
            <div id="modal-seleccionar-cliente" class="modal">
                <div class="modal-content">
                    <span class="close-btn-cliente">&times;</span>
                    <h2 class="titulo-centrado" >Buscar Empleado</h2>
                    <input type="text" id="search-input-cliente" placeholder="Buscar por id o nombre" autocomplete="off">
                    <table id="table-buscar-cliente">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="table-body-cliente">
                            <!-- Clientes añadidos dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Campos del cliente en el menú desplegable -->
            <div class="order-menu" id="orderMenu">
                <div class="menu-header">
                    <h2 class="menu-title"><span>Procesar Transacción</span></h2>
                </div>
                <div class="menu-content">
                    <input type="text" class="menu-input" id="id-cliente" placeholder="ID del Empleado" readonly>
                    <input type="text" class="menu-input" id="nombre-cliente" placeholder="Nombre del Empleado" readonly>
                    <div class="menu-footer">
                        <button class="footer-button secundary" id="buscar-cliente">Buscar Empleado</button>
                        <button class="footer-button primary" id="btn-generar" onclick="guardarFactura()">Procesar Transaccion</button>
                    </div>

                    <div class="order-list" id="orderList">
                        <h3 class="order-list-title">Productos Agregados</h3>
                        <!-- Los productos se agregarán aquí dinámicamente -->
                        <div class="order-list-empty" id="orderListEmpty">
                            <span>No hay productos agregados.</span>
                        </div>
                    </div>
                </div>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA DEBAJO DE ESTA LINEA -->
        </div>
    </div>

    <!-- Cliente -->
    <script>

        // Script para abrir y cerrar el modal de selección de cliente
        const modalCliente = document.getElementById("modal-seleccionar-cliente");
        const openModalButtonCliente = document.getElementById("buscar-cliente");
        const closeModalButtonCliente = document.querySelector(".close-btn-cliente");

        openModalButtonCliente.addEventListener("click", () => {
            modalCliente.style.display = "block";
            getDataClientes(); // Cargar datos al abrir el modal
        });

        closeModalButtonCliente.addEventListener("click", () => {
            modalCliente.style.display = "none";
        });

        window.addEventListener("click", (event) => {
            if (event.target === modalCliente) {
                modalCliente.style.display = "none";
            }
        });

        getDataClientes();

        // Script para llenar tabla y buscar clientes en tiempo real
        document.getElementById("search-input-cliente").addEventListener("keyup", getDataClientes);

        function getDataClientes() {
            const input = document.getElementById('search-input-cliente').value;
            const content = document.getElementById('table-body-cliente');
            const url = '../../controllers/inventario/inventario_buscadorEmpleados.php';
            const formData = new FormData();
            formData.append('campo', input);

            fetch(url, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    content.innerHTML = data;
                })
                .catch(error => console.error("Error al buscar empleados:", error));
        }

        // Script para seleccionar cliente
        function selectCliente(id) {
            if (!id) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al seleccionar cliente.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            fetch("../../controllers/inventario/inventario_seleccionarEmpleado.php?id=" + id)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al seleccionar cliente.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });
                        console.error(data.error);
                    } else {
                        document.getElementById("id-cliente").value = data.id;
                        document.getElementById("nombre-cliente").value = data.nombre;
                    }
                })
                .catch(error => console.error("Error en fetch:", error));

            modalCliente.style.display = "none";
        }
    </script>

    <!-- Codigo de pasas los productos -->
    <script>

        let productos = []; // Array para almacenar los productos seleccionados
        let idElimination = 0; // Variable para almacenar el ID del producto a eliminar

        // Función para agregar productos al carrito
        function addToCart(productId, productName, existenciaGeneral, existenciaInventario) {

            const quantityInput = document.getElementById(`quantity-${productId}`);
            const quantity = parseInt(quantityInput.value);

            if (quantity <= 0 || isNaN(quantity)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Valor inválido',
                    text: 'La cantidad debe ser mayor que 0.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            if(quantity > existenciaInventario){
                Swal.fire({
                    icon: 'warning',
                    title: 'Valor inválido',
                    text: 'La cantidad no puede ser mayor a la existencia en almacén.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            // Crear un objeto con los datos del producto
            productos.push({
                id: productId,
                cantidad: quantity,
                idElimination: idElimination
            });

            console.log(productos);

            // Crear el elemento del producto en el carrito
            const orderList = document.getElementById('orderList');
            const orderItem = document.createElement('div');
            orderItem.classList.add('order-item');

            orderItem.innerHTML = `
                <div class="item-info">
                    <span class="item-name">${productName}</span>
                </div>
                <div class="item-total">
                    <span class="item-quantity">Cantidad: ${quantity}</span>
                </div>
                <button class="delete-item" id-producto="${productId}" id-elimination="${idElimination}" onclick="removeFromCart(this)">&times;</button>
            `;

            // Ocultar el mensaje de carrito vacío
            document.getElementById('orderListEmpty').style.display = 'none';

            // Agregar el producto al carrito
            orderList.appendChild(orderItem);

            // Limpiar el campo de cantidad
            quantityInput.value = '';

            // aumentar el contador de idElimination
            idElimination++;
        }

        // Función para eliminar un producto del carrito
        function removeFromCart(button) {

            // Obtener el ID del producto a eliminar
            const idElimination = button.getAttribute('id-elimination');

            // Eliminar el producto del array
            productos = productos.filter(producto => producto.idElimination !== parseInt(idElimination));

            console.log(productos);

            // Eliminar el elemento del DOM
            button.parentElement.remove();

            // Mostrar el mensaje de carrito vacío si no hay productos
            if (productos.length === 0) {
                document.getElementById('orderListEmpty').style.display = 'block';
            }
        }

    </script>

    <!-- PARA ABRIR EL MENU DESPEJABLE DE FACTURA -->
    <script>
        // Toggle del menú
        const toggleButton = document.getElementById('toggleMenuFacturacion');
        const orderMenu = document.getElementById('orderMenu');

        toggleButton.addEventListener('click', () => {
            orderMenu.classList.toggle('active');
        });
    </script>

    <script>

        function guardarFactura() {

            let idEmpleado = document.getElementById("id-cliente").value.trim();

            // Convertir valores numéricos y validar
            idEmpleado = idEmpleado ? parseInt(idEmpleado) : null;

            // Validacion de seleccion de cliente
            if (!idEmpleado) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Empleado no seleccionado',
                    text: 'Por favor, seleccione un Empleado.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            if (!productos || productos.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Carrito vacío',
                    text: 'No se han agregado productos al carrito.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            const datos = {
                idEmpleado,
                productos
            };

            // console.log("Enviando datos:", datos);

            fetch("../../controllers/inventario/inventario_guardarTransaccion.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            })
            .then(response => response.text())
            .then(text => {
                console.log("Respuesta completa del servidor:", text);
                try {
                    let data = JSON.parse(text);
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Transacción exitosa',
                            text: 'La transacción se ha realizado exitósamente.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            window.open('../../pdf/transacciones/reporte-transaccion.php?no=' + data.response.idtransaccion, '_blank');
                            location.reload();
                        });
                        
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.error,
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });
                        console.error("Error: " + data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error inesperado en el servidor.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.error("Error: Respuesta no es JSON válido:", text);
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo conectar con el servidor.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                console.error("Error de red o servidor:", error);
            });
        }

    </script>

    <!-- buscador en tiempo real -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById("searchInput");
            const searchButton = document.getElementById("searchButton");
            const productsGrid = document.getElementById("productsGrid");
            const productCards = document.querySelectorAll(".product-card");
            
            // Función para filtrar productos
            function filterProducts() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                
                // Si no hay término de búsqueda, mostrar todos los productos
                if (searchTerm === "") {
                    productCards.forEach(card => {
                        card.style.display = "block";
                    });
                    return;
                }
                
                // Recorrer todas las tarjetas de producto
                productCards.forEach(card => {
                    const productInfo = card.querySelector(".product-name").textContent.toLowerCase();
                    
                    // Mostrar u ocultar según si coincide con la búsqueda
                    if (productInfo.includes(searchTerm)) {
                        card.style.display = "block";
                    } else {
                        card.style.display = "none";
                    }
                });
            }
            
            // Filtrar al escribir en el campo (búsqueda en tiempo real)
            searchInput.addEventListener("keyup", filterProducts);
            
            // También filtrar si se presiona Enter en el campo de búsqueda
            searchInput.addEventListener("keypress", function(event) {
                if (event.key === "Enter") {
                    filterProducts();
                }
            });
        });
    </script>

</body>
</html>