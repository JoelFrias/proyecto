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
    <title>Detalles de Salida</title>
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
            margin-bottom: 30px;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 200px;
        }
        
        .header-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
            text-decoration: none;
            white-space: nowrap;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .info-card {
            background: #fff5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #dc3545;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item label {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
            font-weight: 600;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .info-item .value {
            color: #333;
            font-size: 15px;
            font-weight: 500;
            word-break: break-word;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-top: 5px;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-reason {
            background: #fff3cd;
            color: #856404;
        }
        
        .section-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        
        /* TABLA PARA DESKTOP */
        .table-container {
            overflow-x: auto;
            margin-top: 15px;
        }
        
        .productos-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .productos-table th,
        .productos-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .productos-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }
        
        .productos-table tr:hover {
            background: #f8f9fa;
        }
        
        .productos-table tfoot {
            font-weight: bold;
        }
        
        .productos-table tfoot td {
            background: #fff5f5;
            border-top: 2px solid #dc3545;
            font-size: 18px;
            color: #dc3545;
        }
        
        /* CARDS PARA MÓVILES */
        .productos-cards {
            display: none;
            margin-top: 15px;
        }
        
        .producto-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
            gap: 10px;
        }
        
        .card-id {
            font-size: 16px;
            font-weight: bold;
            color: #dc3545;
        }
        
        .card-title {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        
        .card-body {
            display: grid;
            gap: 12px;
        }
        
        .card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }
        
        .card-label {
            font-weight: 500;
            color: #666;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .card-value {
            color: #333;
            font-size: 14px;
            font-weight: 600;
            text-align: right;
        }
        
        .card-total {
            background: #fff5f5;
            padding: 12px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            border-left: 3px solid #dc3545;
        }
        
        .card-total-label {
            font-weight: 600;
            color: #dc3545;
            font-size: 14px;
        }
        
        .card-total-value {
            font-weight: bold;
            color: #dc3545;
            font-size: 16px;
        }
        
        .total-general-mobile {
            display: none;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.2);
        }
        
        .total-general-mobile .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .total-general-mobile .value {
            font-size: 24px;
            font-weight: bold;
        }
        
        .actions-bar {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            flex-wrap: wrap;
        }
        
        .loading {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .loading i {
            font-size: 48px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: start;
            gap: 12px;
            line-height: 1.5;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert i {
            font-size: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .cancelacion-info {
            background: #f8d7da;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #dc3545;
            margin-top: 20px;
        }
        
        .cancelacion-info h4 {
            color: #721c24;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .cancelacion-info p {
            color: #721c24;
            margin: 5px 0;
            font-size: 14px;
        }
        
        .notas-box {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 3px solid #6c757d;
        }
        
        .notas-box h4 {
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .notas-box p {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.6;
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
                font-size: 18px;
                text-align: center;
                justify-content: center;
            }
            
            .header-buttons {
                width: 100%;
                flex-direction: column;
            }
            
            .header-buttons .btn {
                width: 100%;
                justify-content: center;
            }
            
            .info-card {
                padding: 15px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .info-item label {
                font-size: 11px;
            }
            
            .info-item .value {
                font-size: 14px;
            }
            
            .section-title {
                font-size: 16px;
            }
            
            /* Ocultar tabla y mostrar cards en móvil */
            .table-container {
                display: none !important;
            }
            
            .productos-cards {
                display: block;
            }
            
            .total-general-mobile {
                display: block;
            }
            
            .actions-bar {
                flex-direction: column-reverse;
            }
            
            .actions-bar .btn {
                width: 100%;
                justify-content: center;
            }
            
            .alert {
                font-size: 13px;
            }
            
            .cancelacion-info,
            .notas-box {
                padding: 12px;
            }
            
            .cancelacion-info h4,
            .notas-box h4 {
                font-size: 14px;
            }
            
            .cancelacion-info p,
            .notas-box p {
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .header h1 {
                font-size: 16px;
            }
            
            .btn {
                font-size: 13px;
                padding: 8px 15px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: start;
            }
            
            .total-general-mobile .value {
                font-size: 20px;
            }
        }
        
        @media (min-width: 769px) {
            .productos-cards {
                display: none !important;
            }
            
            .total-general-mobile {
                display: none !important;
            }
        }
        
        @media print {
            .btn, .actions-bar, .header button, .alert, .header-buttons {
                display: none !important;
            }
            
            body {
                background: white;
            }
            
            .container {
                box-shadow: none;
                padding: 20px;
            }
            
            .header {
                border-bottom: 2px solid #000;
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
                    <h1><i class="fas fa-file-alt"></i> Detalle de Salida #<span id="salida-id">...</span></h1>
                    <div class="header-buttons">
                        <button class="btn btn-success" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                        <a href="inventario-salida-lista.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                
                <div class="loading" id="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando información...</p>
                </div>
                
                <div id="content" style="display: none;">
                    <div class="info-card">
                        <div class="info-grid">
                            <div class="info-item">
                                <label><i class="fas fa-calendar"></i> Fecha de Salida</label>
                                <div class="value" id="salida-fecha">-</div>
                            </div>
                            <div class="info-item">
                                <label><i class="fas fa-user"></i> Empleado</label>
                                <div class="value" id="salida-empleado">-</div>
                            </div>
                            <div class="info-item">
                                <label><i class="fas fa-clipboard-list"></i> Razón</label>
                                <div class="value" id="salida-razon">-</div>
                            </div>
                            <div class="info-item">
                                <label><i class="fas fa-info-circle"></i> Estado</label>
                                <div class="value" id="salida-estado-container">-</div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="alert-cancelado" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Salida Cancelada</strong>
                            <p>Esta salida ha sido cancelada y los productos han sido devueltos al inventario.</p>
                        </div>
                    </div>
                    
                    <div id="notas-box" class="notas-box" style="display: none;">
                        <h4><i class="fas fa-sticky-note"></i> Notas Adicionales</h4>
                        <p id="salida-notas"></p>
                    </div>
                    
                    <div class="section-title" style="margin-top: 30px;">
                        <i class="fas fa-boxes"></i> Productos Retirados
                    </div>
                    
                    <!-- TABLA PARA DESKTOP -->
                    <div class="table-container">
                        <table class="productos-table">
                            <thead>
                                <tr>
                                    <th style="width: 10%">ID</th>
                                    <th style="width: 40%">Producto</th>
                                    <th style="width: 15%">Cantidad</th>
                                    <th style="width: 17%">Costo Unit.</th>
                                    <th style="width: 18%">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="productos-tbody"></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" style="text-align: right;">COSTO TOTAL:</td>
                                    <td id="total-general">RD$ 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- CARDS PARA MÓVILES -->
                    <div class="productos-cards" id="productos-cards"></div>
                    
                    <!-- TOTAL PARA MÓVILES -->
                    <div class="total-general-mobile" id="total-general-mobile">
                        <div class="label">COSTO TOTAL</div>
                        <div class="value" id="total-general-mobile-value">RD$ 0.00</div>
                    </div>
                    
                    <div id="cancelacion-info" style="display: none;"></div>
                    
                    <div class="actions-bar" id="actions-bar">
                        <button class="btn btn-danger" onclick="cancelarSalida()" id="btn-cancelar">
                            <i class="fas fa-ban"></i> Cancelar Salida
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let salidaId = 0;
        let salidaData = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            salidaId = urlParams.get('id');
            
            if(!salidaId) {
                Swal.fire('Error', 'ID de salida no especificado', 'error').then(() => {
                    window.location.href = 'inventario-salida-lista.php';
                });
                return;
            }
            
            document.getElementById('salida-id').textContent = salidaId;
            cargarDetalle();
        });
        
        function cargarDetalle() {
            fetch(`../../api/inventario/inventario-salida.php?accion=obtener_salida&id=${salidaId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').style.display = 'none';
                    
                    if(data.success) {
                        salidaData = data.salida;
                        mostrarDetalle(data.salida, data.detalle);
                    } else {
                        Swal.fire('Error', data.message, 'error').then(() => {
                            window.location.href = 'inventario-salida-lista.php';
                        });
                    }
                })
                .catch(error => {
                    document.getElementById('loading').style.display = 'none';
                    Swal.fire('Error', 'Error al cargar el detalle', 'error');
                });
        }
        
        function mostrarDetalle(salida, detalle) {
            const fecha = new Date(salida.fecha).toLocaleString('es-DO', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            document.getElementById('salida-fecha').textContent = fecha;
            document.getElementById('salida-empleado').textContent = salida.empleado_nombre || 'Sin información';
            document.getElementById('salida-razon').innerHTML = `<span class="badge badge-reason"><i class="fas fa-tag"></i> ${salida.razon_texto || 'Sin especificar'}</span>`;
            
            const estadoBadge = salida.estado === 'activo' 
                ? '<span class="badge badge-success"><i class="fas fa-check"></i> Activo</span>'
                : '<span class="badge badge-danger"><i class="fas fa-times"></i> Cancelado</span>';
            
            document.getElementById('salida-estado-container').innerHTML = estadoBadge;
            
            // Mostrar notas si existen
            if(salida.notas && salida.notas.trim() !== '') {
                document.getElementById('salida-notas').textContent = salida.notas;
                document.getElementById('notas-box').style.display = 'block';
            }
            
            // Mostrar alerta si está cancelado
            if(salida.estado === 'cancelado') {
                document.getElementById('alert-cancelado').style.display = 'flex';
                document.getElementById('btn-cancelar').style.display = 'none';
                cargarInfoCancelacion();
            }
            
            // Mostrar productos en tabla (desktop)
            const tbody = document.getElementById('productos-tbody');
            tbody.innerHTML = '';
            
            // Mostrar productos en cards (móvil)
            const cardsContainer = document.getElementById('productos-cards');
            cardsContainer.innerHTML = '';
            
            let totalGeneral = 0;
            
            detalle.forEach(prod => {
                const subtotal = prod.cantidad * prod.costo;
                totalGeneral += subtotal;
                
                // Fila para tabla (desktop)
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${prod.id_producto}</strong></td>
                    <td>
                        <strong>${prod.descripcion}</strong>
                        ${prod.tipo ? `<br><small style="color: #666;"><i class="fas fa-tag"></i> ${prod.tipo}</small>` : ''}
                    </td>
                    <td><i class="fas fa-cubes"></i> ${parseFloat(prod.cantidad).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td>RD$ ${parseFloat(prod.costo).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td><strong>RD$ ${subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong></td>
                `;
                tbody.appendChild(row);
                
                // Card para móvil
                const card = document.createElement('div');
                card.className = 'producto-card';
                card.innerHTML = `
                    <div class="card-header">
                        <div>
                            <div class="card-id">#${prod.id_producto}</div>
                            <div class="card-title">${prod.descripcion}</div>
                            ${prod.tipo ? `<small style="color: #666; font-size: 12px;"><i class="fas fa-tag"></i> ${prod.tipo}</small>` : ''}
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="card-row">
                            <span class="card-label"><i class="fas fa-cubes"></i> Cantidad</span>
                            <span class="card-value">${parseFloat(prod.cantidad).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                        </div>
                        <div class="card-row">
                            <span class="card-label"><i class="fas fa-dollar-sign"></i> Costo Unitario</span>
                            <span class="card-value">RD$ ${parseFloat(prod.costo).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                        </div>
                    </div>
                    <div class="card-total">
                        <span class="card-total-label">Subtotal</span>
                        <span class="card-total-value">RD$ ${subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </div>
                `;
                cardsContainer.appendChild(card);
            });
            
            const totalFormatted = `RD$ ${totalGeneral.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            document.getElementById('total-general').textContent = totalFormatted;
            document.getElementById('total-general-mobile-value').textContent = totalFormatted;
            
            document.getElementById('content').style.display = 'block';
        }
        
        function cargarInfoCancelacion() {
            fetch(`../../api/inventario/inventario-salida.php?accion=obtener_cancelacion&id=${salidaId}`)
                .then(response => response.json())
                .then(data => {
                    if(data.success && data.cancelacion) {
                        const cancelacion = data.cancelacion;
                        const fecha = new Date(cancelacion.fecha).toLocaleString('es-DO', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        const html = `
                            <div class="cancelacion-info">
                                <h4><i class="fas fa-info-circle"></i> Información de Cancelación</h4>
                                <p><strong>Fecha:</strong> ${fecha}</p>
                                <p><strong>Usuario:</strong> ${cancelacion.nombre_empleado}</p>
                                <p><strong>Motivo:</strong> ${cancelacion.notas || 'No especificado'}</p>
                            </div>
                        `;
                        document.getElementById('cancelacion-info').innerHTML = html;
                        document.getElementById('cancelacion-info').style.display = 'block';
                    }
                });
        }
        
        function cancelarSalida() {
            Swal.fire({
                title: '¿Cancelar esta salida?',
                html: `
                    <p>Esta acción devolverá los productos al inventario.</p>
                    <textarea id="notas-cancelacion" class="swal2-textarea" placeholder="Motivo de la cancelación (opcional)"></textarea>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, cancelar salida',
                cancelButtonText: 'No',
                confirmButtonColor: '#dc3545',
                preConfirm: () => {
                    return document.getElementById('notas-cancelacion').value;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('accion', 'cancelar_salida');
                    formData.append('id_salida', salidaId);
                    formData.append('notas', result.value || '');
                    
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Cancelando salida',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    fetch('../../api/inventario/inventario-salida.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire({
                                title: 'Cancelado',
                                text: data.message,
                                icon: 'success'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Error al cancelar la salida', 'error');
                    });
                }
            });
        }
    </script>
</body>
</html>