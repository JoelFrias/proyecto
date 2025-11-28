<?php 

require_once '../../core/verificar-sesion.php'; // Verificar Session
require_once '../../core/conexion.php'; // Conexión a la base de datos

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'ALM004';
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
    <title>Entrada de Inventario</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
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
            border-bottom: 2px solid #4CAF50;
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
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
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
            border-color: #4CAF50;
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
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
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
            color: #4CAF50;
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
        
        .info-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 13px;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        
        .info-badge i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-box-open"></i> Nueva Entrada de Inventario</h1>
                    <button class="btn btn-secondary" onclick="window.location.href='inventario-entrada-lista.php'">
                        <i class="fas fa-list"></i> Ver Entradas
                    </button>
                </div>
                
                <div class="info-badge">
                    <i class="fas fa-info-circle"></i>
                    <strong>Cálculo automático:</strong> El costo se calcula con promedio ponderado: ∑ (cantidad×costo) / ∑ cantidad
                    <br>
                    <i class="fas fa-sync-alt"></i>
                    <strong>Ajuste de precios:</strong> Si los precios de venta son menores al costo, se ajustarán automáticamente (Precio Venta 1: +25%, Precio Venta 2: +15% sobre el precio de compra)
                </div>
                
                <div class="form-section">
                    <div class="form-group">
                        <label for="referencia">Referencia / Documento (opcional)</label>
                        <input type="text" id="referencia" class="form-control" placeholder="Ej: Factura #12345, Orden de compra #678" autocomplete="off">
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
                        <span>TOTAL:</span>
                        <span id="total-costo">RD$ 0.00</span>
                    </div>
                </div>
                
                <div class="actions">
                    <button class="btn btn-secondary" onclick="cancelar()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button class="btn btn-primary" onclick="guardarEntrada()">
                        <i class="fas fa-save"></i> Guardar Entrada
                    </button>
                </div>
            </div>
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBAJO DE ESTA LINEA -->
        </div>
    </div>

    <script>
        let productosDisponibles = [];
        let productosAgregados = [];
        let contadorProductos = 0;
        
        // Cargar productos al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            cargarProductos();
        });
        
        function cargarProductos() {
            fetch('../../api/inventario/inventario-entrada.php?accion=listar_productos')
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
                    data-precio1="${prod.precioVenta1}"
                    data-precio2="${prod.precioVenta2}">
                    ${prod.descripcion} (Existencia: ${prod.existencia})
                </option>`;
            });
            
            row.innerHTML = `
                <td>
                    <select class="producto-select" onchange="actualizarCosto(${contadorProductos})">
                        ${optionsHTML}
                    </select>
                </td>
                <td>
                    <input type="number" class="cantidad-input" min="0.01" step="0.01" value="1" 
                           onchange="calcularSubtotal(${contadorProductos})">
                </td>
                <td>
                    <input type="number" class="costo-input" min="0" step="0.01" value="0" 
                           onchange="calcularSubtotal(${contadorProductos})">
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
        
        function actualizarCosto(id) {
            const row = document.getElementById(`producto-${id}`);
            const select = row.querySelector('.producto-select');
            const costoInput = row.querySelector('.costo-input');
            
            const selectedOption = select.options[select.selectedIndex];
            const costo = selectedOption.getAttribute('data-costo');
            
            if(costo) {
                costoInput.value = costo;
                calcularSubtotal(id);
            }
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
        
        function guardarEntrada() {
            const rows = document.querySelectorAll('.producto-row');
            
            if(rows.length === 0) {
                Swal.fire('Advertencia', 'Debe agregar al menos un producto', 'warning');
                return;
            }
            
            const productos = [];
            let error = false;
            
            rows.forEach(row => {
                const select = row.querySelector('.producto-select');
                const cantidad = parseFloat(row.querySelector('.cantidad-input').value);
                const costo = parseFloat(row.querySelector('.costo-input').value);
                
                if(!select.value || cantidad <= 0 || costo < 0) {
                    error = true;
                    return;
                }
                
                productos.push({
                    id_producto: select.value,
                    cantidad: cantidad,
                    costo: costo
                });
            });
            
            if(error) {
                Swal.fire('Error', 'Verifique que todos los productos tengan cantidad y costo válidos', 'error');
                return;
            }
            
            const referencia = document.getElementById('referencia').value;
            
            Swal.fire({
                title: '¿Confirmar entrada?',
                html: `
                    <p>Se agregarán ${productos.length} productos al inventario</p>
                    <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-top: 10px; font-size: 13px;">
                        <i class="fas fa-calculator"></i> El costo promedio ponderado será recalculado automáticamente
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Cancelar',
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    const formData = new FormData();
                    formData.append('accion', 'crear_entrada');
                    formData.append('productos', JSON.stringify(productos));
                    formData.append('referencia', referencia);
                    
                    return fetch('../../api/inventario/inventario-entrada.php', {
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
                    // Verificar si hubo ajustes de precios
                    if (result.value.precios_ajustados && result.value.ajustes) {
                        // Construir mensaje con los ajustes realizados
                        let ajustesHTML = '<div style="text-align: left; max-height: 300px; overflow-y: auto;">';
                        ajustesHTML += '<p style="margin-bottom: 15px;"><strong>Se realizaron los siguientes ajustes automáticos:</strong></p>';
                        
                        result.value.ajustes.forEach(ajuste => {
                            ajustesHTML += `
                                <div style="background: #fff3cd; padding: 12px; border-radius: 5px; margin-bottom: 10px; border-left: 4px solid #ffc107;">
                                    <div style="font-weight: bold; color: #856404; margin-bottom: 5px;">
                                        <i class="fas fa-box"></i> ${ajuste.producto}
                                    </div>
                                    <div style="font-size: 13px; color: #856404;">
                                        <i class="fas fa-calculator"></i> Nuevo Costo Promedio: <strong>RD$ ${ajuste.costo_promedio.toFixed(2)}</strong>
                                    </div>
                            `;
                            
                            ajuste.ajustes.forEach(cambio => {
                                ajustesHTML += `
                                    <div style="font-size: 13px; color: #856404; margin-top: 5px;">
                                        <i class="fas fa-arrow-right"></i> ${cambio}
                                    </div>
                                `;
                            });
                            
                            ajustesHTML += '</div>';
                        });
                        
                        ajustesHTML += '</div>';
                        ajustesHTML += `
                            <div style="background: #e3f2fd; padding: 10px; border-radius: 5px; margin-top: 15px; font-size: 13px;">
                                <i class="fas fa-info-circle"></i> Los precios se ajustaron automáticamente para mantener:
                                <strong>Precio Venta 2 > Precio Venta 1 > Costo</strong>
                            </div>
                        `;
                        
                        Swal.fire({
                            title: '¡Entrada registrada con ajustes!',
                            html: ajustesHTML,
                            icon: 'success',
                            confirmButtonText: 'Ver detalles',
                            width: '600px'
                        }).then(() => {
                            window.location.href = `inventario-entrada-detalle.php?id=${result.value.id_entrada}`;
                        });
                    } else {
                        // Sin ajustes, mensaje normal
                        Swal.fire({
                            title: '¡Entrada registrada!',
                            html: `
                                <p>La entrada se ha registrado exitosamente</p>
                                <div style="background: #e8f5e9; padding: 10px; border-radius: 5px; margin-top: 10px; font-size: 13px;">
                                    <i class="fas fa-check-circle" style="color: #4caf50;"></i> El costo promedio ponderado ha sido actualizado
                                </div>
                            `,
                            icon: 'success',
                            confirmButtonText: 'Ver detalles'
                        }).then(() => {
                            window.location.href = `inventario-entrada-detalle.php?id=${result.value.id_entrada}`;
                        });
                    }
                }
            });
        }
        
        function cancelar() {
            Swal.fire({
                title: '¿Cancelar entrada?',
                text: 'Se perderá toda la información ingresada',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, cancelar',
                cancelButtonText: 'No'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'inventario-entrada-lista.php';
                }
            });
        }
    </script>
</body>
</html>