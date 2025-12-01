<?php 

require_once '../../core/conexion.php';		// Conexión a la base de datos

// Verificar conexión a la base de datos
if (!$conn || !$conn->connect_errno === 0) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos",
        "error_code" => "DATABASE_CONNECTION_ERROR"
    ]));
}
require_once '../../core/verificar-sesion.php';

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
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">  <!-- Tom Select CSS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script> <!-- Tom Select JS -->
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
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .info-badge {
            display: block;
            background: #cdfdd1ff;
            color: #616161ff;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin: 15px 0;
            line-height: 1.6;
        }
        
        .info-badge i {
            margin-right: 5px;
        }
        
        .info-badge strong {
            display: block;
            margin-bottom: 5px;
        }
        
        /* Contenedor con scroll para tabla */

        .table-container {
            overflow-x: auto;
            overflow-y: visible;
            margin-top: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
            position: relative;
        }
        
        .productos-table {
            width: 100%;
            min-width: 700px;
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
            background: #b8fcbcff;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border-left: 4px solid #129e1bff;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 15px;
            padding: 8px 0;
        }
        
        .summary-row strong {
            font-weight: 600;
        }
        
        .summary-row.total {
            font-size: 20px;
            font-weight: bold;
            border-top: 2px solid rgba(255,255,255,0.3);
            padding-top: 15px;
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
        
        /* RESPONSIVE DESIGN */
        @media (max-width: 768px) {
            
            .container {
                padding: 15px;
                border-radius: 8px;
            }
            
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header h1 {
                font-size: 20px;
                text-align: center;
                justify-content: center;
            }
            
            .header .btn {
                width: 100%;
                justify-content: center;
            }
            
            .info-badge {
                font-size: 12px;
                padding: 10px;
            }
            
            .info-badge strong {
                font-size: 13px;
            }
            
            .form-section h3 {
                font-size: 16px;
            }
            
            .btn-primary {
                width: 100%;
                justify-content: center;
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
                flex-direction: column-reverse;
            }
            
            .actions .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .header h1 {
                font-size: 18px;
            }
            
            .btn {
                font-size: 13px;
                padding: 8px 15px;
            }
            
            .summary-row.total {
                font-size: 16px;
            }
            
            .productos-table {
                font-size: 12px;
                min-width: 650px;
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
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-box-open"></i> Nueva Entrada de Inventario</h1>
                    <button class="btn btn-secondary" onclick="window.location.href='inventario-entrada-lista.php'">
                        <i class="fas fa-list"></i> Ver Entradas
                    </button>
                </div>
                
                <div class="info-badge">
                    <strong><i class="fas fa-calculator"></i> Cálculo automático:</strong>
                    El costo se calcula con promedio ponderado: ∑ (cantidad×costo) / ∑ cantidad
                    <br><br>
                    <strong><i class="fas fa-sync-alt"></i> Ajuste de precios:</strong>
                    Si los precios de venta son menores al costo, se ajustarán automáticamente (Precio Venta 1: +25%, Precio Venta 2: +15% sobre el precio de compra)
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
        </div>
    </div>

    <script>
        let productosDisponibles = [];
        let contadorProductos = 0;
        
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
            if (productosDisponibles.length === 0) {
                Swal.fire('Advertencia', 'No hay productos disponibles', 'warning');
                return;
            }

            contadorProductos++;
            const id = contadorProductos;

            // Generar opciones
            let optionsHTML = '<option value="">Seleccionar producto...</option>';
            productosDisponibles.forEach(prod => {
                optionsHTML += `
                    <option 
                        value="${prod.id}"
                        data-descripcion="${prod.descripcion}"
                        data-costo="${prod.precioCompra}"
                        data-precio1="${prod.precioVenta1}"
                        data-precio2="${prod.precioVenta2}">
                        ${prod.descripcion} (ID: ${prod.id})
                    </option>
                `;
            });

            // Crear fila
            const row = document.createElement('tr');
            row.className = 'producto-row';
            row.id = `producto-${id}`;

            row.innerHTML = `
                <td>
                    <select id="producto-select-${id}" class="producto-select"
                            onchange="actualizarCosto(${id})">
                        ${optionsHTML}
                    </select>
                </td>

                <td>
                    <input type="number"
                        class="cantidad-input"
                        min="0.01"
                        step="0.01"
                        value="1"
                        onchange="calcularSubtotal(${id})"
                        onkeyup="calcularSubtotal(${id})">
                </td>

                <td>
                    <div style="display: flex; flex-direction: column; gap: 5px;">
                        <input type="number"
                            class="costo-input"
                            min="0"
                            step="0.01"
                            value="0"
                            onchange="calcularSubtotal(${id})"
                            onkeyup="calcularSubtotal(${id})"
                            id="costo-input-${id}">
                        <label style="font-size: 11px; display: flex; align-items: center; gap: 5px; cursor: pointer; width: 100%;">
                            <input type="checkbox" 
                                class="auto-costo-checkbox" 
                                id="auto-costo-${id}"
                                checked
                                onchange="toggleAutoCosto(${id})"
                                style="min-width: 15px; width: 15px; margin: 0;">
                            <span style="color: #666; flex: 1; word-wrap: break-word;">Calcular automático</span>
                        </label>
                    </div>
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

            // Agregar fila
            document.getElementById('productos-tbody').appendChild(row);
            document.getElementById('table-container').style.display = 'block';
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('summary').style.display = 'block';

            // Mostrar scroll en móvil
            if (window.innerWidth <= 768) {
                document.getElementById('scroll-indicator').style.display = 'block';
            }

            // Inicializar TomSelect
            new TomSelect(`#producto-select-${id}`, {
                placeholder: "Buscar producto...",
                maxOptions: 200,
                dropdownParent: 'body',
                onChange: function(value) {
                    if (value) {
                        actualizarCosto(id);
                    }
                }
            });
        }

        
        function toggleAutoCosto(id) {
            const checkbox = document.getElementById(`auto-costo-${id}`);
            const costoInput = document.getElementById(`costo-input-${id}`);
            
            if (checkbox.checked) {
                // Si está activado, aplicar el costo automático
                costoInput.style.backgroundColor = '#f0f0f0';
                actualizarCosto(id);
            } else {
                // Si está desactivado, permitir edición manual
                costoInput.style.backgroundColor = '#fff3cd';
                costoInput.focus();
            }
        }

        function actualizarCosto(id) {
            const row = document.getElementById(`producto-${id}`);
            if (!row) return;

            const select = row.querySelector('.producto-select');
            const costoInput = row.querySelector('.costo-input');
            const checkbox = document.getElementById(`auto-costo-${id}`);
            
            // Solo actualizar si el checkbox está activado
            if (!checkbox || !checkbox.checked) {
                return;
            }
            
            // Obtener el option seleccionado
            const selectedOption = select.options[select.selectedIndex];
            
            if (!selectedOption || !selectedOption.value) return;

            // Obtener el costo del data-attribute
            const costo = selectedOption.getAttribute('data-costo');

            if (costo) {
                costoInput.value = parseFloat(costo).toFixed(2);
                calcularSubtotal(id);
            }
        }
        
        function calcularSubtotal(id) {
            const row = document.getElementById(`producto-${id}`);
            if(!row) return;
            
            const cantidad = parseFloat(row.querySelector('.cantidad-input').value) || 0;
            const costo = parseFloat(row.querySelector('.costo-input').value) || 0;
            const subtotal = cantidad * costo;
            
            const subtotalFormatted = `RD$ ${subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            row.querySelector('.subtotal').textContent = subtotalFormatted;
            
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
                const checkbox = row.querySelector('.auto-costo-checkbox');
                
                if(!select.value || cantidad <= 0 || costo < 0) {
                    error = true;
                    return;
                }
                
                productos.push({
                    id_producto: select.value,
                    cantidad: cantidad,
                    costo: costo,
                    calcular_automatico: checkbox.checked
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
                    if (result.value.precios_ajustados && result.value.ajustes) {
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