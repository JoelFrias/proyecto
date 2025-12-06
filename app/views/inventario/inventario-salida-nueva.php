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
require_once '../../../core/verificar-sesion.php';

// Validar permisos de usuario
require_once '../../../core/validar-permisos.php';
$permiso_necesario = 'ALM005';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
    exit(); 
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Salida de Inventario</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .container {
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
            flex: 1;
            min-width: 250px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: #dc3545;
            color: white;
        }
        
        .btn-primary:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            color: #555;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #dc3545;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        /* Contenedor con scroll para tabla */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .productos-table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
        }
        
        .productos-table th,
        .productos-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .productos-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .productos-table tr:hover {
            background: #f8f9fa;
        }
        
        .producto-row input,
        .producto-row select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .stock-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .stock-warning {
            color: #dc3545;
            font-weight: bold;
        }
        
        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #dc3545;
            transition: all 0.3s;
            padding: 5px;
        }
        
        .btn-icon:hover {
            color: #c82333;
            transform: scale(1.2);
        }
        
        .summary {
            background: #fff5f5;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border-left: 4px solid #dc3545;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .summary-row.total {
            font-size: 20px;
            font-weight: bold;
            color: #dc3545;
            border-top: 2px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .alert-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            margin-top: 20px;
            color: #856404;
        }
        
        .alert-warning i {
            margin-right: 8px;
        }
        
        /* Indicador de scroll en móvil */
        .scroll-indicator {
            display: none;
            background: #fff3cd;
            color: #856404;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 12px;
            text-align: center;
            margin-top: 10px;
            border-left: 4px solid #ffc107;
        }
        
        /* Responsive Design */
        @media screen and (max-width: 968px) {
            .container {
                padding: 20px;
                border-radius: 8px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        @media screen and (max-width: 768px) {
            .container {
                padding: 15px;
                border-radius: 0;
            }
            
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header h1 {
                font-size: 20px;
                min-width: auto;
                text-align: center;
            }
            
            .header .btn {
                width: 100%;
                justify-content: center;
            }
            
            .form-section h3 {
                font-size: 16px;
            }
            
            /* Mostrar indicador de scroll */
            .scroll-indicator {
                display: block;
            }
            
            .table-container {
                border-radius: 5px;
            }
            
            .productos-table {
                font-size: 13px;
                min-width: 750px;
            }
            
            .productos-table th,
            .productos-table td {
                padding: 8px;
            }
            
            .producto-row input,
            .producto-row select {
                padding: 6px;
                font-size: 13px;
            }
            
            .summary {
                padding: 15px;
            }
            
            .summary-row {
                font-size: 14px;
            }
            
            .summary-row.total {
                font-size: 18px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .alert-warning {
                font-size: 14px;
                padding: 12px;
            }
        }
        
        @media screen and (max-width: 480px) {
            .header h1 {
                font-size: 18px;
            }
            
            .btn {
                padding: 8px 15px;
                font-size: 13px;
            }
            
            .form-control {
                padding: 8px;
                font-size: 13px;
            }
            
            .summary {
                padding: 12px;
            }
            
            .productos-table {
                font-size: 12px;
                min-width: 700px;
            }
            
            .productos-table th,
            .productos-table td {
                padding: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="navegator-nav">
        <?php include '../../../app/views/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-box-open"></i> Nueva Salida de Inventario</h1>
                    <button class="btn btn-secondary" onclick="window.location.href='inventario-salida-lista.php'">
                        <i class="fas fa-list"></i> Ver Salidas
                    </button>
                </div>
                
                <div class="alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Importante:</strong> Esta acción reducirá el inventario. Asegúrate de verificar las existencias disponibles antes de proceder.
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Información General</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="razon"><i class="fas fa-clipboard-list"></i> Razón de Salida <span style="color: red;">*</span></label>
                            <select id="razon" class="form-control" required>
                                <option value="">Seleccionar razón...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notas"><i class="fas fa-sticky-note"></i> Notas Adicionales (opcional)</label>
                            <input type="text" id="notas" class="form-control" placeholder="Información adicional sobre esta salida" autocomplete="off">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-boxes"></i> Productos</h3>
                    <button class="btn btn-primary" onclick="agregarProducto()">
                        <i class="fas fa-plus"></i> Agregar Producto
                    </button>
                    
                    <div id="productos-container">
                        <div class="table-container" id="table-container" style="display: none;">
                            <table class="productos-table" id="productos-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40%">Producto</th>
                                        <th style="width: 15%">Cantidad</th>
                                        <th style="width: 20%">Costo Unitario</th>
                                        <th style="width: 20%">Subtotal</th>
                                        <th style="width: 5%"></th>
                                    </tr>
                                </thead>
                                <tbody id="productos-tbody"></tbody>
                            </table>
                        </div>
                        <div class="scroll-indicator" id="scroll-indicator">
                            <i class="fas fa-arrows-alt-h"></i> Desliza horizontalmente para ver toda la tabla
                        </div>
                    </div>
                    
                    <div class="empty-state" id="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No hay productos agregados. Haz clic en "Agregar Producto" para comenzar.</p>
                    </div>
                </div>
                
                <div class="summary" id="summary" style="display: none;">
                    <div class="summary-row">
                        <span><strong>Total de Productos:</strong></span>
                        <span id="total-productos">0</span>
                    </div>
                    <div class="summary-row">
                        <span><strong>Total de Unidades:</strong></span>
                        <span id="total-unidades">0</span>
                    </div>
                    <div class="summary-row total">
                        <span>COSTO TOTAL:</span>
                        <span id="total-costo">RD$ 0.00</span>
                    </div>
                </div>
                
                <div class="actions">
                    <button class="btn btn-secondary" onclick="cancelar()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button class="btn btn-primary" onclick="guardarSalida()">
                        <i class="fas fa-save"></i> Guardar Salida
                    </button>
                </div>
            </div>
        </div>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">  <!-- Tom Select CSS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script> <!-- Tom Select JS -->

    <script>
        let productosDisponibles = [];
        let razones = [];
        let contadorProductos = 0;
        let productosData = {};
        
        document.addEventListener('DOMContentLoaded', function() {
            cargarProductos();
            cargarRazones();
        });
        
        function cargarProductos() {
            fetch('../../../api/inventario/inventario-salida.php?accion=listar_productos')
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        productosDisponibles = data.productos;
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Error al cargar productos', 'error');
                });
        }
        
        function cargarRazones() {
            fetch('../../../api/inventario/inventario-salida.php?accion=listar_razones')
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        razones = data.razones;
                        const select = document.getElementById('razon');
                        razones.forEach(razon => {
                            const option = document.createElement('option');
                            option.value = razon.id;
                            option.textContent = razon.descripcion;
                            select.appendChild(option);
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Error al cargar razones', 'error');
                });
        }
        
        function agregarProducto() {
            if(productosDisponibles.length === 0) {
                Swal.fire('Advertencia', 'No hay productos disponibles', 'warning');
                return;
            }
            
            contadorProductos++;
            const id = contadorProductos;
            
            productosData[id] = {
                productoId: '',
                cantidad: 1,
                costo: 0,
                existencia: 0
            };
            
            let optionsHTML = '<option value="">Seleccionar producto...</option>';
            productosDisponibles.forEach(prod => {
                optionsHTML += `
                    <option value="${prod.id}" 
                        data-costo="${prod.precioCompra}"
                        data-existencia="${prod.existencia}">
                        ID: ${prod.id} ${prod.descripcion} (Stock: ${parseFloat(prod.existencia).toFixed(2)})
                    </option>`;
            });

            const row = document.createElement('tr');
            row.className = 'producto-row';
            row.id = `producto-${id}`;
            row.setAttribute('data-id', id);
            
            row.innerHTML = `
                <td>
                    <select id="producto-select-${id}" class="producto-select" 
                            onchange="actualizarProductoSeleccionado(${id}, this.value)">
                        ${optionsHTML}
                    </select>
                    <div class="stock-info" id="stock-info-${id}"></div>
                </td>

                <td>
                    <input type="number" class="cantidad-input" min="0.01" step="0.01" value="1" 
                        onchange="actualizarCantidad(${id}, this.value)"
                        onkeyup="actualizarCantidad(${id}, this.value)">
                </td>

                <td>
                    <input type="number" class="costo-input" min="0" step="0.01" value="0" readonly
                        style="background: #f8f9fa; cursor: not-allowed;">
                </td>

                <td>
                    <span class="subtotal" id="subtotal-${id}">RD$ 0.00</span>
                </td>

                <td>
                    <button class="btn-icon" onclick="eliminarProducto(${id})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;

            document.getElementById('productos-tbody').appendChild(row);
            document.getElementById('table-container').style.display = 'block';
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('summary').style.display = 'block';
            
            // Indicador de scroll para móvil
            if(window.innerWidth <= 768) {
                document.getElementById('scroll-indicator').style.display = 'block';
            }

            new TomSelect(`#producto-select-${id}`, {
                placeholder: "Buscar producto...",
                maxOptions: 200,
                dropdownParent: 'body',
                onChange: function(value) {
                    actualizarProductoSeleccionado(id, value);
                }
            });
        }

        
        function actualizarProductoSeleccionado(id, productoId) {
            if(!productosData[id]) return;
            
            productosData[id].productoId = productoId;
            
            const row = document.getElementById(`producto-${id}`);
            if(!row) return;
            
            const select = row.querySelector('.producto-select');
            const costoInput = row.querySelector('.costo-input');
            
            let costo = 0;
            let existencia = 0;
            
            if(productoId) {
                const option = select.options[select.selectedIndex];
                if(option) {
                    costo = parseFloat(option.getAttribute('data-costo')) || 0;
                    existencia = parseFloat(option.getAttribute('data-existencia')) || 0;
                }
            }
            
            productosData[id].costo = costo;
            productosData[id].existencia = existencia;
            
            costoInput.value = costo.toFixed(2);
            
            actualizarStockInfo(id);
            validarYCalcular(id);
        }
        
        function actualizarCantidad(id, cantidad) {
            if(!productosData[id]) return;
            
            cantidad = parseFloat(cantidad) || 0;
            productosData[id].cantidad = cantidad;
            
            validarYCalcular(id);
        }
        
        function actualizarStockInfo(id) {
            const data = productosData[id];
            const stockInfo = document.getElementById(`stock-info-${id}`);
            
            if(!data || !data.productoId || !stockInfo) {
                if(stockInfo) stockInfo.innerHTML = '';
                return;
            }
            
            const existencia = data.existencia;
            stockInfo.innerHTML = `<i class="fas fa-warehouse"></i> Stock disponible: <strong>${existencia.toFixed(2)}</strong> unidades`;
        }
        
        function validarYCalcular(id) {
            const data = productosData[id];
            if(!data) return;
            
            const row = document.getElementById(`producto-${id}`);
            if(!row) return;
            
            const cantidad = data.cantidad;
            const existencia = data.existencia;
            const costo = data.costo;
            
            const cantidadInput = row.querySelector('.cantidad-input');
            const stockInfo = document.getElementById(`stock-info-${id}`);
            
            // Validar cantidad vs existencia
            if(cantidad > existencia && data.productoId) {
                const warningHtml = `<span class="stock-warning"><i class="fas fa-exclamation-triangle"></i> ¡Cantidad excede stock disponible! (${existencia.toFixed(2)})</span>`;
                if(stockInfo) stockInfo.innerHTML = warningHtml;
                if(cantidadInput) cantidadInput.style.borderColor = '#dc3545';
            } else {
                actualizarStockInfo(id);
                if(cantidadInput) cantidadInput.style.borderColor = '#ddd';
            }
            
            // Calcular subtotal
            const subtotal = cantidad * costo;
            const subtotalFormatted = `RD$ ${subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            
            const subtotalElement = document.getElementById(`subtotal-${id}`);
            if(subtotalElement) subtotalElement.textContent = subtotalFormatted;
            
            actualizarResumen();
        }
        
        function eliminarProducto(id) {
            Swal.fire({
                title: '¿Eliminar producto?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const row = document.getElementById(`producto-${id}`);
                    if(row) row.remove();
                    
                    delete productosData[id];
                    
                    const tbody = document.getElementById('productos-tbody');
                    if(tbody.children.length === 0) {
                        document.getElementById('table-container').style.display = 'none';
                        document.getElementById('scroll-indicator').style.display = 'none';
                        document.getElementById('empty-state').style.display = 'block';
                        document.getElementById('summary').style.display = 'none';
                    }
                    
                    actualizarResumen();
                }
            });
        }
        
        function actualizarResumen() {
            let totalProductos = 0;
            let totalUnidades = 0;
            let totalCosto = 0;
            
            for(let id in productosData) {
                const data = productosData[id];
                if(data && data.productoId) {
                    totalProductos++;
                    totalUnidades += data.cantidad;
                    totalCosto += data.cantidad * data.costo;
                }
            }
            
            document.getElementById('total-productos').textContent = totalProductos;
            document.getElementById('total-unidades').textContent = totalUnidades.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('total-costo').textContent = `RD$ ${totalCosto.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
        
        function guardarSalida() {
            const razon = document.getElementById('razon').value;
            
            if(!razon) {
                Swal.fire('Advertencia', 'Debe seleccionar una razón para la salida', 'warning');
                return;
            }
            
            const productos = [];
            let error = false;
            let errorMsg = '';
            
            for(let id in productosData) {
                const data = productosData[id];
                
                if(!data.productoId || data.cantidad <= 0) {
                    error = true;
                    errorMsg = 'Verifique que todos los productos tengan cantidad válida';
                    break;
                }
                
                if(data.cantidad > data.existencia) {
                    const row = document.getElementById(`producto-${id}`);
                    const select = row ? row.querySelector('.producto-select') : null;
                    const nombreProducto = select ? select.options[select.selectedIndex].text : 'producto';
                    
                    error = true;
                    errorMsg = `La cantidad de "${nombreProducto}" excede el stock disponible (${data.existencia.toFixed(2)})`;
                    break;
                }
                
                productos.push({
                    id_producto: data.productoId,
                    cantidad: data.cantidad
                });
            }
            
            if(productos.length === 0) {
                Swal.fire('Advertencia', 'Debe agregar al menos un producto', 'warning');
                return;
            }
            
            if(error) {
                Swal.fire('Error', errorMsg, 'error');
                return;
            }
            
            const notas = document.getElementById('notas').value;
            const razonTexto = document.getElementById('razon').options[document.getElementById('razon').selectedIndex].text;
            
            Swal.fire({
                title: '¿Confirmar salida?',
                html: `
                    <p><strong>Razón:</strong> ${razonTexto}</p>
                    <p>Se retirarán <strong>${productos.length}</strong> productos del inventario</p>
                    <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin-top: 10px;">
                        <i class="fas fa-exclamation-triangle"></i> Esta acción reducirá las existencias
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const formData = new FormData();
                    formData.append('accion', 'crear_salida');
                    formData.append('productos', JSON.stringify(productos));
                    formData.append('razon', razon);
                    formData.append('notas', notas);
                    
                    return fetch('../../../api/inventario/inventario-salida.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(!data.success) {
                            throw new Error(data.message);
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(error.message);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: '¡Salida registrada!',
                        text: 'La salida se ha registrado exitosamente',
                        icon: 'success',
                        confirmButtonText: 'Ver detalles'
                    }).then(() => {
                        window.location.href = `inventario-salida-detalle.php?id=${result.value.id_salida}`;
                    });
                }
            });
        }
        
        function cancelar() {
            Swal.fire({
                title: '¿Cancelar salida?',
                text: 'Se perderá toda la información ingresada',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, cancelar',
                cancelButtonText: 'No'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'inventario-salida-lista.php';
                }
            });
        }
    </script>
</body>
</html>