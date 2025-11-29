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
    <title>Detalles de Entrada</title>
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
            margin-bottom: 30px;
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
            text-decoration: none;
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
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #4CAF50;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item label {
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .info-item .value {
            color: #333;
            font-size: 16px;
            font-weight: 500;
        }
        
        .badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
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
        
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .productos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
        }
        
        .productos-table tr:hover {
            background: #f8f9fa;
        }
        
        .productos-table tfoot {
            font-weight: bold;
        }
        
        .productos-table tfoot td {
            background: #f8f9fa;
            border-top: 2px solid #4CAF50;
            font-size: 18px;
            color: #4CAF50;
        }
        
        .actions-bar {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .loading {
            text-align: center;
            padding: 60px;
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
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert i {
            font-size: 20px;
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
        }
        
        .cancelacion-info p {
            color: #721c24;
            margin: 5px 0;
        }
        
        @media print {
            .btn, .actions-bar, .header button, .alert {
                display: none !important;
            }
            
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
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
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-file-alt"></i> Detalle de Entrada #<span id="entrada-id">...</span></h1>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-success" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                        <a href="inventario-entrada-lista.php" class="btn btn-secondary">
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
                                <label><i class="fas fa-calendar"></i> Fecha de Entrada</label>
                                <div class="value" id="entrada-fecha">-</div>
                            </div>
                            <div class="info-item">
                                <label><i class="fas fa-user"></i> Empleado</label>
                                <div class="value" id="entrada-empleado">-</div>
                            </div>
                            <div class="info-item">
                                <label><i class="fas fa-file"></i> Referencia</label>
                                <div class="value" id="entrada-referencia">-</div>
                            </div>
                            <div class="info-item">
                                <label><i class="fas fa-info-circle"></i> Estado</label>
                                <div class="value" id="entrada-estado-container">-</div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="alert-cancelado" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Entrada Cancelada</strong>
                            <p>Esta entrada ha sido cancelada y el inventario ha sido revertido.</p>
                        </div>
                    </div>
                    
                    <div class="section-title">
                        <i class="fas fa-boxes"></i> Productos Ingresados
                    </div>
                    
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
                        <tbody id="productos-tbody">
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="text-align: right;">TOTAL:</td>
                                <td id="total-general">RD$ 0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div id="cancelacion-info" style="display: none;"></div>
                    
                    <div class="actions-bar" id="actions-bar">
                        <button class="btn btn-danger" onclick="cancelarEntrada()" id="btn-cancelar">
                            <i class="fas fa-ban"></i> Cancelar Entrada
                        </button>
                    </div>
                </div>
            </div>
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBAJO DE ESTA LINEA -->
        </div>
    </div>

    <script>
        let entradaId = 0;
        let entradaData = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Obtener ID de la URL
            const urlParams = new URLSearchParams(window.location.search);
            entradaId = urlParams.get('id');
            
            if(!entradaId) {
                Swal.fire('Error', 'ID de entrada no especificado', 'error').then(() => {
                    window.location.href = 'inventario-entradas-lista.php';
                });
                return;
            }
            
            document.getElementById('entrada-id').textContent = entradaId;
            cargarDetalle();
        });
        
        function cargarDetalle() {
            fetch(`../../api/inventario/inventario-entrada.php?accion=obtener_entrada&id=${entradaId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').style.display = 'none';
                    
                    if(data.success) {
                        entradaData = data.entrada;
                        mostrarDetalle(data.entrada, data.detalle);
                    } else {
                        Swal.fire('Error', data.message, 'error').then(() => {
                            window.location.href = 'inventario-entradas-lista.php';
                        });
                    }
                })
                .catch(error => {
                    document.getElementById('loading').style.display = 'none';
                    Swal.fire('Error', 'Error al cargar el detalle', 'error');
                });
        }
        
        function mostrarDetalle(entrada, detalle) {
            // Mostrar información general
            const fecha = new Date(entrada.fecha).toLocaleString('es-DO', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            document.getElementById('entrada-fecha').textContent = fecha;
            document.getElementById('entrada-empleado').textContent = `${entrada.empleado_nombre}`;
            document.getElementById('entrada-referencia').textContent = entrada.referencia || 'Sin referencia';
            
            const estadoBadge = entrada.estado === 'activo' 
                ? '<span class="badge badge-success"><i class="fas fa-check"></i> Activo</span>'
                : '<span class="badge badge-danger"><i class="fas fa-times"></i> Cancelado</span>';
            
            document.getElementById('entrada-estado-container').innerHTML = estadoBadge;
            
            // Mostrar alerta si está cancelado
            if(entrada.estado === 'cancelado') {
                document.getElementById('alert-cancelado').style.display = 'flex';
                document.getElementById('btn-cancelar').style.display = 'none';
                cargarInfoCancelacion();
            }
            
            // Mostrar productos
            const tbody = document.getElementById('productos-tbody');
            tbody.innerHTML = '';
            
            let totalGeneral = 0;
            
            detalle.forEach(prod => {
                const subtotal = prod.cantidad * prod.costo;
                totalGeneral += subtotal;
                
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
            });
            
            document.getElementById('total-general').textContent = `RD$ ${totalGeneral.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            document.getElementById('content').style.display = 'block';
        }
        
        function cargarInfoCancelacion() {
            fetch(`../../api/inventario/inventario-entrada.php?accion=obtener_cancelacion&id=${entradaId}`)
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
        
        function cancelarEntrada() {
            Swal.fire({
                title: '¿Cancelar esta entrada?',
                html: `
                    <p>Esta acción revertirá el inventario agregado en esta entrada.</p>
                    <textarea id="notas-cancelacion" class="swal2-textarea" placeholder="Motivo de la cancelación (opcional)"></textarea>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, cancelar entrada',
                cancelButtonText: 'No',
                confirmButtonColor: '#dc3545',
                preConfirm: () => {
                    return document.getElementById('notas-cancelacion').value;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('accion', 'cancelar_entrada');
                    formData.append('id_entrada', entradaId);
                    formData.append('notas', result.value || '');
                    
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Cancelando entrada',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    fetch('../../api/inventario/inventario-entrada.php', {
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
                        Swal.fire('Error', 'Error al cancelar la entrada', 'error');
                    });
                }
            });
        }
    </script>
</body>
</html>