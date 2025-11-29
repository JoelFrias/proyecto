<?php

require_once '../../core/verificar-sesion.php'; // Verificar Session
require_once '../../core/conexion.php'; // Conexión a la base de datos

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'ALM002';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
        
    exit(); 
}

// Inicializar variables
$empleado = isset($_POST['seleccionar-empleado']) ? $_POST['seleccionar-empleado'] : null;
$result = null;
$hasEmployee = false;

// Solo ejecutar la consulta de productos si se ha seleccionado un empleado
if ($empleado) {
    $hasEmployee = true;
    
    $stmt = $conn->prepare("SELECT
                p.id AS id,
                p.descripcion AS descripcion,
                p.existencia AS existenciaGeneral,
                ie.cantidad AS existenciaInventario
            FROM
                productos AS p
            INNER JOIN inventarioempleados AS ie
            ON
                p.id = ie.idProducto
            WHERE
                p.activo = TRUE AND ie.cantidad > 0 AND 
                ie.idEmpleado = ?
            ORDER BY
                p.descripcion ASC
            ");
            
    $stmt->bind_param("i", $empleado);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        die("Error en la consulta: " . $conn->error);
    }
}

// Obtener la lista de empleados activos para el selector
$stmtEmp = $conn->prepare("SELECT id, CONCAT(id,' ',nombre,' ',apellido) AS nombre FROM empleados WHERE activo = TRUE");
$stmtEmp->execute();
$resultEmpleados = $stmtEmp->get_result();

?>

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
        /* Estilos para el formulario de selección de empleados */
        .employee-selector-form {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* Etiqueta del selector */
        .employee-selector-label {
            font-weight: 500;
            margin-bottom: 5px;
        }

        /* Contenedor del select y el botón */
        .employee-selector-controls {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }

        /* Contenedor del select con flechita personalizada */
        .select-container {
            position: relative;
            width: 100%;
        }

        .employee-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #fff;
            font-size: 14px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
        }

        .employee-select:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
        }

        .select-container::after {
            content: '▼';
            font-size: 12px;
            color: #777;
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }

        /* Botón de envío */
        .employee-submit-button {
            padding: 8px 12px;
            background-color: #28a745; /* Verde */
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            flex-shrink: 0;
            width: 100%;
            text-align: center;
        }

        .employee-submit-button:hover {
            background-color: #218838;
        }

        /* Estilo para pantallas mayores a 600px (modo escritorio) */
        @media (min-width: 601px) {
            .employee-selector-controls {
                flex-direction: row;
                align-items: center;
            }

            .select-container {
                flex: 1;
            }

            .employee-submit-button {
                width: auto;
            }
        }

        #regresar {
            background-color:rgb(62, 153, 250);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: auto; /* por defecto */
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
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .btntop-entrega:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            transform: translateY(-2px);
        }

        .btntop-entrega:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }

        /* Botón de Retorno (Rojo) */
        .btntop-retorno {
            background: linear-gradient(135deg, #4de664ff 0%, #28bd2aff 100%);
            color: white;
        }

        .btntop-retorno:hover {
            background: linear-gradient(135deg, #4de664ff 0%, #28bd2aff 100%);
            box-shadow: 0 4px 12px rgba(139, 239, 68, 0.4);
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

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

        <button class="toggle-menu" id="toggleMenuFacturacion">☰</button>

            <div class="facturacion-container">

                <div class="buttons-top">
                    <button class="btntop btntop-retorno" onclick="window.location.href='registro-transacciones.php'">
                        <i class="fa-solid fa-house"></i>
                        <span>Regresar a Registro</span>
                    </button>
                    <button class="btntop btntop-entrega" onclick="window.location.href='inventario-transaccion.php'">
                        <i class="fas fa-box-open"></i>
                        <span>Realizar Entrega</span>
                    </button>
                </div>

                <h2>Devolver Productos a Almacén</h2><br>

                <div class="seleccionEmpleado">
                    <form action="" method="post" class="employee-selector-form">
                        <span class="employee-selector-label">Seleccione el Empleado:</span>
                        <div class="employee-selector-controls">
                            <div class="select-container">
                                <select name="seleccionar-empleado" id="seleccionar-empleado" class="employee-select">
                                    <option disabled selected>---</option>
                                    <?php
                                    if ($resultEmpleados && $resultEmpleados->num_rows > 0) {
                                        while ($fila = $resultEmpleados->fetch_assoc()) {
                                            $selected = (($empleado == $fila['id']) ? " selected" : "");
                                            echo "<option value='" . $fila['id'] . "'" . $selected . ">" . htmlspecialchars($fila['nombre'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) . "</option>";
                                        }
                                    } else {
                                        echo "<option value='' disabled>No hay opciones</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="employee-submit-button">Buscar Inventario de Empleado</button>
                        </div>
                    </form>
                </div>
                
                <?php if ($hasEmployee): ?>

                <h3>Buscardor de Productos:</h3><br>
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Buscar productos...">
                </div>
                <div class="products-grid" id="productsGrid"">
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo '  <div class="product-card">';
                            echo '      <div class="product-info">';
                            echo '          <div>';
                            echo '              <div class="product-name">' . $row["id"] .'   '. $row["descripcion"] . '</div>';
                            echo '                  <div class="product-quantity">Existencia General: ' . $row["existenciaGeneral"] . '</div>';
                            echo '                  <div class="product-quantity">Existencia en Empleado: ' . $row["existenciaInventario"] . '</div>';   
                            echo '              </div>';
                            echo '          <div class="product-total"></div>';
                            echo '      </div>';
                            echo '      <input type="number" class="quantity-input" id="quantity-' . $row["id"] . '" placeholder="Cantidad a llevar" min="1">';
                            echo '      <button class="quantity-button" onclick="addToCart(' . $row["id"] . ', \'' . addslashes($row["descripcion"]) . '\', ' . $row["existenciaGeneral"] . ', ' . $row["existenciaInventario"] . ')">Agregar Producto</button>';
                            echo '  </div>';
                        }
                    } else {
                        echo "<p style='text-align:center;width:100%;'>No existe ningún producto disponible en el almacén del empleado seleccionado.</p>";
                    }
                    ?>
                    <?php else: ?>
                        <div class="no-employee-message" style="text-align:center; margin-top:20px; padding:15px; background-color:#f8f9fa; border-radius:5px;">
                            <p><i class="fas fa-user-slash" style="font-size:30px; color:#6c757d; margin-bottom:10px;"></i></p>
                            <p>Por favor seleccione un empleado para ver sus productos disponibles.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-menu" id="orderMenu">
                <div class="menu-header">
                    <h2 class="menu-title"><span>Procesar Transacción</span></h2>
                </div>
                <div class="menu-content">
                    <div class="menu-footer">
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

            idEmpleado = <?php echo isset($_POST['seleccionar-empleado']) ? $_POST['seleccionar-empleado'] : null; ?>

            const datos = {
                idEmpleado,
                productos
            };

            // console.log("Enviando datos:", datos);

            fetch("../../api/inventario/inventario_revAlmacen.php", {
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
                            window.open('../../reports/transacciones/reporte-transaccion.php?no=' + data.response.idtransaccion, '_blank');
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