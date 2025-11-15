// Script para abrir y cerrar el modal de prcesar factura
const modalfactura = document.getElementById("modal-procesar-factura");
const openModalButtonfactura = document.getElementById("btn-generar");
const closeModalButtonfactura = document.querySelector(".close-btn-factura");

openModalButtonfactura.addEventListener("click", () => {
    modalfactura.style.display = "block";
    getDataClientes(); // Cargar datos al abrir el modal
});

closeModalButtonfactura.addEventListener("click", () => {
    modalfactura.style.display = "none";
});

window.addEventListener("click", (event) => {
    if (event.target === modalfactura) {
        modalfactura.style.display = "none";
    }
});

// Script para mostrar u ocultar campos de información de pagos
const metodo = document.getElementById("forma-pago");
const tarjeta = document.getElementById("div-numero-tarjeta");
const autorizacion = document.getElementById("div-numero-autorizacion");
const banco = document.getElementById("div-banco");
const destino = document.getElementById("div-destino");

metodo.addEventListener("change", () => {
    if (metodo.value === "tarjeta") {
        tarjeta.style.display = "block";
        autorizacion.style.display = "block";
        banco.style.display = "block";
        destino.style.display = "block";

        document.getElementById("monto-pagado").value = "";
        document.getElementById("banco").value = "1";
        document.getElementById("destino-cuenta").value = "1";
        document.getElementById("numero-tarjeta").value = "";
        document.getElementById("numero-autorizacion").value = "";

    } else if (metodo.value === "transferencia") {
        tarjeta.style.display = "none";
        autorizacion.style.display = "block";
        banco.style.display = "block";
        destino.style.display = "block";

        document.getElementById("monto-pagado").value = "";
        document.getElementById("banco").value = "1";
        document.getElementById("destino-cuenta").value = "1";
        document.getElementById("numero-tarjeta").value = "";
        document.getElementById("numero-autorizacion").value = "";

    } else {
        tarjeta.style.display = "none";
        autorizacion.style.display = "none";
        banco.style.display = "none";
        destino.style.display = "none";

        document.getElementById("monto-pagado").value = "";
        document.getElementById("banco").value = "1";
        document.getElementById("destino-cuenta").value = "1";
        document.getElementById("numero-tarjeta").value = "";
        document.getElementById("numero-autorizacion").value = "";

    }
});

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
    const url = '../../api/facturacion/facturacion_buscadorClientes.php';
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
        .catch(error => console.error("Error al buscar clientes:", error));
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
        console.error("Error: Respuesta no es JSON válido:", text);
        return;
    }

    fetch("../../api/facturacion/facturacion_seleccionarCliente.php?id=" + id)
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
                document.getElementById("empresa").value = data.empresa;
            }
        })
        .catch(error => console.error("Error en fetch:", error));

    modalCliente.style.display = "none"; // Cerrar el modal después de seleccionar
}

// Variables globales

let selectedPrices = {}; // Variable global para almacenar el precio seleccionado
let total = 0; // Variable global para almacenar el total de la compra
let productos = []; // Array para almacenar los productos seleccionados
let counter = 0; // Contador para los productos eliminados
let noCotizacion = 0; // Variable para almacenar el número de cotización activa

function handleButton1(productId, price1) {
    selectedPrices[productId] = price1; // Almacenar precio1
    document.getElementById(`button2-${productId}`).classList.add("selected");
    document.getElementById(`button1-${productId}`).classList.remove("selected");
}

function handleButton2(productId, price2) {
    selectedPrices[productId] = price2; // Almacenar precio2
    document.getElementById(`button1-${productId}`).classList.add("selected");
    document.getElementById(`button2-${productId}`).classList.remove("selected");
}

// Función para agregar productos al carrito
function addToCart(productId, productName, venta, precio, existencia) {
    const quantityInput = document.getElementById(`quantity-${productId}`);
    const quantity = quantityInput.value;

    if (quantity <= 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'La cantidad debe ser mayor que 0.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    if(quantity > existencia){
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'La cantidad requerida es mayor a la existencia.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    // Obtener el precio seleccionado
    const selectedPrice = selectedPrices[productId];
    if (!selectedPrice) {
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'Por favor, selecciona un precio antes de agregar a la factura.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    // Calcular el subtotal del producto
    const subtotal = selectedPrice * quantity;

    /*

    // Verificar si el producto ya está en el carrito
    const existingProduct = productos.find(producto => 
        producto.id === productId && producto.venta === selectedPrice
    );

    if (existingProduct) {
        // Si el producto ya existe, actualizar la cantidad y el subtotal
        const cantidadInt = parseInt(quantity);
        existingProduct.cantidad += cantidadInt;
        existingProduct.subtotal += precio * cantidadInt;
    } else {
        // Si el producto no existe, agregarlo al carrito
        const cantidadInt = parseInt(quantity);
        productos.push({
            id: productId,
            venta: selectedPrice,
            cantidad: cantidadInt,
            precio: precio,
            subtotal: precio * cantidadInt
        });
    }

    */


    productos.push({
        id: productId,
        venta: selectedPrice,
        cantidad: quantity,
        precio: precio,
        subtotal: subtotal,
        idElimination: counter
    });

    // console.log(productos);

    // Crear el elemento del producto en el carrito
    const orderList = document.getElementById('orderList');
    const orderItem = document.createElement('div');
    orderItem.classList.add('order-item');

    orderItem.innerHTML = `
        <div class="item-info">
            <span class="item-name">${productName}</span>
            <span class="item-base-price">RD$${selectedPrice.toFixed(2)}</span>
        </div>
        <div class="item-total">
            <span class="item-quantity">x${quantity}</span>
            <span class="item-total-price">RD$${subtotal.toFixed(2)}</span>
        </div>
        <button class="delete-item" id-producto="${productId}" id-elimination="${counter}" onclick="removeFromCart(this, ${subtotal})">&times;</button>
    `;

    // Ocultar el mensaje de carrito vacío
    document.getElementById('orderListEmpty').style.display = 'none';

    // Agregar el producto al carrito
    orderList.appendChild(orderItem);

    // Actualizar el total
    total += subtotal;
    updateTotal();

    // Limpiar el campo de cantidad
    quantityInput.value = '';

    // Desmarcar los botones de precio
    document.getElementById(`button1-${productId}`).classList.remove("selected");
    document.getElementById(`button2-${productId}`).classList.remove("selected");

    // Reiniciar el precio seleccionado
    selectedPrices[productId] = null;

    // Incrementar el contador de eliminacion para el siguiente producto
    counter++;
}

// Función para eliminar un producto del carrito
function removeFromCart(button, subtotal) {
    // Restar el subtotal del producto eliminado
    total -= subtotal;
    updateTotal();

    // Obtener el ID del producto a eliminar
    const idElimination = button.getAttribute('id-elimination');

    // Eliminar el producto del array
    productos = productos.filter(producto => producto.idElimination !== parseInt(idElimination));

    // console.log(productos);

    // Eliminar el elemento del DOM
    button.parentElement.remove();

    // Mostrar el mensaje de carrito vacío si no hay productos
    if (productos.length === 0) {
        document.getElementById('orderListEmpty').style.display = 'block';
    }
}

// Función para actualizar el total en el modal
function updateTotal() {
    document.getElementById('totalAmount').textContent = `${total.toFixed(2)}`;
    document.getElementById('totalAmount2').textContent = `${total.toFixed(2)}`;
}

// Función para uso de , ., donde esta minimunfraction y maximumfraction
function updateTotal() {
    document.getElementById('totalAmount').textContent = `${total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    document.getElementById('totalAmount2').textContent = `${total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// Toggle del menú
const toggleButton = document.getElementById('toggleMenuFacturacion');
const orderMenu = document.getElementById('orderMenu');

toggleButton.addEventListener('click', () => {
    orderMenu.classList.toggle('active');
});

function guardarFactura(print) {
    let idCliente = document.getElementById("id-cliente").value.trim();
    let tipoFactura = document.getElementById("tipo-factura").value.trim();
    let formaPago = document.getElementById("forma-pago").value.trim();
    let numeroTarjeta = document.getElementById("numero-tarjeta").value.trim();
    let numeroAutorizacion = document.getElementById("numero-autorizacion").value.trim();
    let banco = document.getElementById("banco").value.trim();
    let destino = document.getElementById("destino-cuenta").value.trim();
    let descuento = document.getElementById("input-descuento").value.trim();
    let montoPagado = document.getElementById("monto-pagado").value.trim();
    let total = document.getElementById("totalAmount").textContent.replace(/,/g, "");

    // Convertir valores numéricos y validar
    idCliente = idCliente ? parseInt(idCliente) : null;
    banco = banco ? parseInt(banco) : null;
    destino = destino ? parseInt(destino) : null;
    descuento = descuento ? parseInt(descuento) : null;
    montoPagado = montoPagado ? parseFloat(montoPagado) : null;
    total = total ? parseFloat(total) : null;

    // Validacion de seleccion de cliente
    if (!idCliente) {
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'Por favor, Seleccione un cliente.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    // Validar que hallan productos facturados
    if (productos.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'Ningún producto ha sido agregado a la factura.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    // Validación de campos obligatorios
    if (!idCliente || !tipoFactura || !formaPago || Number.isNaN(montoPagado)) {   
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'Por favor, complete todos los campos obligatorios.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    // Validar campos con tarjeta
    if (formaPago == "tarjeta" && (!numeroTarjeta || !numeroAutorizacion || banco == "1" || destino == "1")){
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'Por favor, complete todos los campos obligatorios.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    // Validar campos por transferencia
    if (formaPago == "transferencia" && (!numeroAutorizacion || banco == "1" || destino == "1")){
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'Por favor, complete todos los campos obligatorios.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    // Validar que el total sea un número válido
    if (Number.isNaN(total)) {
        Swal.fire({
            icon: 'warning',
            title: 'Error',
            text: 'El total de la factura no es válido.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    // Validar que el monto pagado sea un número válido
    if (Number.isNaN(montoPagado)) {
        Swal.fire({
            icon: 'warning',
            title: 'Error',
            text: 'El monto pagado no es válido.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }
    
    // Validar que el monto pagado sea mayor o igual al total
    montoValido = montoPagado + descuento;
    if (montoValido < total && tipoFactura == "contado") {
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'El monto pagado no puede ser menor que el total a pagar.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        return;
    }

    const datos = {
        idCliente,
        tipoFactura,
        formaPago,
        numeroTarjeta: numeroTarjeta || null, 
        numeroAutorizacion: numeroAutorizacion || null, 
        banco: banco || null,
        destino: destino || null,
        descuento,
        montoPagado,
        total,
        productos
    };

    // Deshabilitar botones para evitar doble envío
    document.getElementById("guardar-factura").disabled = true;
    document.getElementById("guardar-imprimir-factura").disabled = true;

    fetch("../../api/facturacion/facturacion_guardar.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(datos)
    })
    .then(response => response.text())
    .then(text => {
        try {
            let data = JSON.parse(text);
            if (data.success) {
                // Cerrar el modal de procesar factura
                document.getElementById("modal-procesar-factura").style.display = "none";
                
                if (!print) {
                    // Solo mostrar mensaje de éxito si no se imprime
                    Swal.fire({
                        icon: 'success',
                        title: 'Factura #' + data.numFactura,
                        text: 'Factura Guardada Exitosamente',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                } else {
                    // Abrir el reporte en una nueva ventana y recargar la página actual
                    const invoiceUrl = `../../reports/factura/factura.php?factura=${data.numFactura}`;
                    window.open(invoiceUrl, '_blank');
                }

                // Eliminar cotización si aplica
                if (cotizacionactiva) {
                    actualizarCotizacion(noCotizacion);
                }

                // Pequeña demora antes de recargar para asegurar que la ventana se abra
                setTimeout(() => {
                    location.reload();
                }, 500);

            } else {
                // Rehabilitar botones en caso de error
                document.getElementById("guardar-factura").disabled = false;
                document.getElementById("guardar-imprimir-factura").disabled = false;
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.error || 'Error al guardar la factura',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                console.log("Error al guardar la factura:", data.error);
            }
        } catch (error) {
            // Rehabilitar botones en caso de error
            document.getElementById("guardar-factura").disabled = false;
            document.getElementById("guardar-imprimir-factura").disabled = false;
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Se produjo un error inesperado en el servidor.\nFactura no guardada.',
                showConfirmButton: true,
                confirmButtonText: 'Aceptar'
            });
            console.error("Error: Respuesta no es JSON válido:", text);
        }
    })
    .catch(error => {
        // Rehabilitar botones en caso de error
        document.getElementById("guardar-factura").disabled = false;
        document.getElementById("guardar-imprimir-factura").disabled = false;
        
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Se produjo un error de red o en el servidor.\nPor favor, inténtelo de nuevo.',
            showConfirmButton: true,
            confirmButtonText: 'Aceptar'
        });
        console.error("Error de red o servidor:", error);
    });
}

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


// Calcular Devuelta - Ejecutar cuando cambia el monto pagado o el descuento
document.getElementById('monto-pagado').addEventListener('input', calcularDevuelta);
document.getElementById('input-descuento').addEventListener('input', calcularDevuelta);

function calcularDevuelta() {
    const montoPagado = parseFloat(document.getElementById('monto-pagado').value) || 0;
    const totalFacturaBruto = parseFloat(document.getElementById('totalAmount').textContent.replace(',', '').replace('RD$', '').trim()) || 0;
    const descuento = parseFloat(document.getElementById('input-descuento').value) || 0;
    
    // Aplicar el descuento al total de la factura
    const totalFacturaConDescuento = totalFacturaBruto - descuento;
    
    // Calcular la devuelta basado en el total con descuento
    let devuelta = montoPagado - totalFacturaConDescuento;

    const devueltaElement = document.getElementById('div-devuelta');
    
    if (devuelta >= 0) {
        document.getElementById('devuelta-monto').textContent = devuelta.toFixed(2);
        devueltaElement.style.color = 'green';
    } else {
        document.getElementById('devuelta-monto').textContent = '0.00';
        devueltaElement.style.color = 'red';
    }

    if(descuento > 0){
        calTotal = totalFacturaBruto - descuento;
        document.getElementById('totalAmount2').textContent = `${calTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

}