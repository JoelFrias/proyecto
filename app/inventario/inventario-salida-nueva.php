<?php 

require_once '../../core/verificar-sesion.php';
require_once '../../core/conexion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
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
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
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
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
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
        
        .productos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
        }
        
        .productos-table tr:hover {
            background: #f8f9fa;
        }
        
        .producto-row input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .producto-row select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #dc3545;
            transition: all 0.3s;
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
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
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
        
        .stock-info {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
        
        .stock-warning {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="navegator-nav">
        <?php include '../../app/layouts/menu.php'; ?>

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
                        <table class="productos-table" id="productos-table" style="display: none;">
                            <thead>
                                <tr>
                                    <th style="width: 40%">Producto</th>
                                    <th style="width: 15%">Cantidad</th>
                                    <th style="width: 20%">Costo Unitario</th>
                                    <th style="width: 20%">Subtotal</th>
                                    <th style="width: 5%"></th>
                                </tr>
                            </thead>
                            <tbody id="productos-tbody">
                            </tbody>
                        </table>
                        
                        <div class="empty-state" id="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No hay productos agregados. Haz clic en "Agregar Producto" para comenzar.</p>
                        </div>
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

    <script>
        let productosDisponibles = [];
        let razones = [];
        let contadorProductos = 0;
        
        document.addEventListener('DOMContentLoaded', function() {
            cargarProductos();
            cargarRazones();
        });
        
        function cargarProductos() {
            fetch('../../api/inventario/inventario-salida.php?accion=listar_productos')
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
            fetch('../../api/inventario/inventario-salida.php?accion=listar_razones')
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
            const row = document.createElement('tr');
            row.className = 'producto-row';
            row.id = `producto-${contadorProductos}`;
            
            let optionsHTML = '<option value="">Seleccionar producto...</option>';
            productosDisponibles.forEach(prod => {
                optionsHTML += `<option value="${prod.id}" 
                    data-costo="${prod.precioCompra}"
                    data-existencia="${prod.existencia}">
                    ${prod.descripcion} (Stock: ${parseFloat(prod.existencia).toFixed(2)})
                </option>`;
            });
            
            row.innerHTML = `
                <td>
                    <select class="producto-select" onchange="actualizarInfo(${contadorProductos})">
                        ${optionsHTML}
                    </select>
                    <div class="stock-info" id="stock-info-${contadorProductos}"></div>
                </td>
                <td>
                    <input type="number" class="cantidad-input" min="0.01" step="0.01" value="1" 
                           onchange="validarCantidad(${contadorProductos})" onkeyup="validarCantidad(${contadorProductos})">
                </td>
                <td>
                    <input type="number" class="costo-input" min="0" step="0.01" value="0" readonly
                           style="background: #f8f9fa; cursor: not-allowed !important;">
                </td>
                <td>
                    <span class="subtotal" id="subtotal-${contadorProductos}">RD$ 0.00</span>
                </td>
                <td>
                    <button class="btn-icon" onclick="eliminarProducto(${contadorProductos})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            document.getElementById('productos-tbody').appendChild(row);
            document.getElementById('productos-table').style.display = 'table';
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('summary').style.display = 'block';
        }
        
        function actualizarInfo(id) {
            const row = document.getElementById(`producto-${id}`);
            const select = row.querySelector('.producto-select');
            const costoInput = row.querySelector('.costo-input');
            const stockInfo = document.getElementById(`stock-info-${id}`);
            
            const selectedOption = select.options[select.selectedIndex];
            const costo = selectedOption.getAttribute('data-costo');
            const existencia = selectedOption.getAttribute('data-existencia');
            
            if(costo && existencia) {
                costoInput.value = costo;
                stockInfo.innerHTML = `<i class="fas fa-warehouse"></i> Stock disponible: <strong>${parseFloat(existencia).toFixed(2)}</strong> unidades`;
                validarCantidad(id);
            } else {
                stockInfo.innerHTML = '';
            }
        }
        
        function validarCantidad(id) {
            const row = document.getElementById(`producto-${id}`);
            const select = row.querySelector('.producto-select');
            const cantidadInput = row.querySelector('.cantidad-input');
            const stockInfo = document.getElementById(`stock-info-${id}`);
            
            const selectedOption = select.options[select.selectedIndex];
            const existencia = parseFloat(selectedOption.getAttribute('data-existencia')) || 0;
            const cantidad = parseFloat(cantidadInput.value) || 0;
            
            if(cantidad > existencia) {
                stockInfo.innerHTML = `<span class="stock-warning"><i class="fas fa-exclamation-triangle"></i> ¡Cantidad excede stock disponible! (${existencia.toFixed(2)})</span>`;
                cantidadInput.style.borderColor = '#dc3545';
            } else {
                stockInfo.innerHTML = `<i class="fas fa-warehouse"></i> Stock disponible: <strong>${existencia.toFixed(2)}</strong> unidades`;
                cantidadInput.style.borderColor = '#ddd';
            }
            
            calcularSubtotal(id);
        }
        
        function calcularSubtotal(id) {
            const row = document.getElementById(`producto-${id}`);
            const cantidad = parseFloat(row.querySelector('.cantidad-input').value) || 0;
            const costo = parseFloat(row.querySelector('.costo-input').value) || 0;
            const subtotal = cantidad * costo;
            
            row.querySelector('.subtotal').textContent = `RD$ ${subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
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
                    document.getElementById(`producto-${id}`).remove();
                    
                    const tbody = document.getElementById('productos-tbody');
                    if(tbody.children.length === 0) {
                        document.getElementById('productos-table').style.display = 'none';
                        document.getElementById('empty-state').style.display = 'block';
                        document.getElementById('summary').style.display = 'none';
                    }
                    
                    actualizarResumen();
                }
            });
        }
        
        function actualizarResumen() {
            const rows = document.querySelectorAll('.producto-row');
            let totalProductos = rows.length;
            let totalUnidades = 0;
            let totalCosto = 0;
            
            rows.forEach(row => {
                const cantidad = parseFloat(row.querySelector('.cantidad-input').value) || 0;
                const costo = parseFloat(row.querySelector('.costo-input').value) || 0;
                totalUnidades += cantidad;
                totalCosto += cantidad * costo;
            });
            
            document.getElementById('total-productos').textContent = totalProductos;
            document.getElementById('total-unidades').textContent = totalUnidades.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('total-costo').textContent = `RD$ ${totalCosto.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
        
        function guardarSalida() {
            const razon = document.getElementById('razon').value;
            const rows = document.querySelectorAll('.producto-row');
            
            if(!razon) {
                Swal.fire('Advertencia', 'Debe seleccionar una razón para la salida', 'warning');
                return;
            }
            
            if(rows.length === 0) {
                Swal.fire('Advertencia', 'Debe agregar al menos un producto', 'warning');
                return;
            }
            
            const productos = [];
            let error = false;
            let errorMsg = '';
            
            rows.forEach(row => {
                const select = row.querySelector('.producto-select');
                const cantidad = parseFloat(row.querySelector('.cantidad-input').value);
                const costo = parseFloat(row.querySelector('.costo-input').value);
                const existencia = parseFloat(select.options[select.selectedIndex].getAttribute('data-existencia'));
                
                if(!select.value || cantidad <= 0) {
                    error = true;
                    errorMsg = 'Verifique que todos los productos tengan cantidad válida';
                    return;
                }
                
                if(cantidad > existencia) {
                    error = true;
                    errorMsg = `La cantidad de "${select.options[select.selectedIndex].text}" excede el stock disponible (${existencia.toFixed(2)})`;
                    return;
                }
                
                productos.push({
                    id_producto: select.value,
                    cantidad: cantidad
                });
            });
            
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
                    
                    return fetch('../../api/inventario/inventario-salida.php', {
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