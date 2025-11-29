<?php 

require_once '../../core/verificar-sesion.php';
require_once '../../core/conexion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'ALM005';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    http_response_code(403);
    header('location: ../errors/403.html');
    exit(); 
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Registro Salidas</title>
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
        
        .header h2 {
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
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: #dc3545;
            color: white;
        }
        
        .btn-primary:hover {
            background: #c82333;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
            padding: 8px 15px;
            font-size: 13px;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .btn-danger {
            background: #f95f5fff;
            color: white;
            padding: 8px 15px;
            font-size: 13px;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #598498ff 0%, #455b65ff 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card h3 {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
        }
        
        /* TABLA PARA DESKTOP */
        .table-container {
            overflow-x: auto;
        }
        
        .salidas-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .salidas-table th,
        .salidas-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .salidas-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            position: sticky;
            top: 0;
        }
        
        .salidas-table tr:hover {
            background: #f8f9fa;
        }
        
        /* CARDS PARA MÓVILES */
        .salidas-cards {
            display: none;
        }
        
        .salida-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .salida-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-id {
            font-size: 18px;
            font-weight: bold;
            color: #dc3545;
        }
        
        .card-body {
            display: grid;
            gap: 10px;
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
            font-weight: 500;
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .card-actions .btn {
            flex: 1;
            justify-content: center;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
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
            padding: 4px 10px;
            font-size: 11px;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .pagination button {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #dc3545;
            color: white;
            border-color: #dc3545;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination .page-info {
            padding: 8px 15px;
            color: #555;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .loading i {
            font-size: 48px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            
            .header h2 {
                font-size: 20px;
                text-align: center;
            }
            
            .header .btn {
                width: 100%;
                justify-content: center;
            }
            
            .filters {
                grid-template-columns: 1fr;
                padding: 15px;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-card h3 {
                font-size: 12px;
            }
            
            .stat-card .value {
                font-size: 22px;
            }
            
            /* Ocultar tabla y mostrar cards en móvil */
            .salidas-table {
                display: none !important;
            }
            
            .salidas-cards {
                display: block;
            }
            
            .pagination button {
                padding: 6px 12px;
                font-size: 13px;
            }
            
            .pagination .page-info {
                width: 100%;
                text-align: center;
                order: -1;
            }
        }
        
        @media (max-width: 480px) {
            .header h2 {
                font-size: 18px;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .btn {
                font-size: 13px;
                padding: 8px 15px;
            }
            
            .card-actions {
                flex-direction: column;
            }
            
            .card-actions .btn {
                width: 100%;
            }
        }
        
        @media (min-width: 769px) {
            .salidas-cards {
                display: none !important;
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
                    <h2><i class="fas fa-sign-out-alt"></i> Salidas de Inventario</h2>
                    <a href="inventario-salida-nueva.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva Salida
                    </a>
                </div>
                
                <div class="filters">
                    <div class="filter-group">
                        <label for="fecha-desde"><i class="fas fa-calendar"></i> Fecha Desde</label>
                        <input id="fecha-desde" type="date">
                    </div>
                    <div class="filter-group">
                        <label for="fecha-hasta"><i class="fas fa-calendar"></i> Fecha Hasta</label>
                        <input id="fecha-hasta" type="date">
                    </div>
                    <div class="filter-group">
                        <label for="filtro-razon"><i class="fas fa-clipboard-list"></i> Razón</label>
                        <select id="filtro-razon">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filtro-estado"><i class="fas fa-filter"></i> Estado</label>
                        <select id="filtro-estado">
                            <option value="" selected>Todos</option>
                            <option value="activo">Activo</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>ㅤ</label>
                        <button id="buscador" class="btn btn-primary" onclick="aplicarFiltros()" style="width: 100%;">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>

                <div class="stats" id="stats-container">
                    <div class="stat-card">
                        <h3><i class="fas fa-sign-out-alt"></i> Total Salidas</h3>
                        <div class="value" id="stat-total">0</div>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fas fa-cubes"></i> Productos Retirados</h3>
                        <div class="value" id="stat-productos">0</div>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fas fa-dollar-sign"></i> Valor Total</h3>
                        <div class="value" id="stat-valor">RD$ 0</div>
                    </div>
                    <div class="stat-card">
                        <h3><i class="fas fa-calendar-day"></i> Este Mes</h3>
                        <div class="value" id="stat-mes">0</div>
                    </div>
                </div>
                
                <div class="loading" id="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando salidas...</p>
                </div>
                
                <!-- TABLA PARA DESKTOP -->
                <div class="table-container">
                    <table class="salidas-table" id="salidas-table" style="display: none;">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha</th>
                                <th>Razón</th>
                                <th>Productos</th>
                                <th>Unidades</th>
                                <th>Costo Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="salidas-tbody"></tbody>
                    </table>
                </div>
                
                <!-- CARDS PARA MÓVILES -->
                <div class="salidas-cards" id="salidas-cards"></div>
                
                <div class="empty-state" id="empty-state" style="display: none;">
                    <i class="fas fa-inbox"></i>
                    <h3>No hay salidas registradas</h3>
                    <p>Comienza agregando una nueva salida de inventario</p>
                    <br>
                    <a href="inventario-salida-nueva.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva Salida
                    </a>
                </div>
                
                <div class="pagination" id="pagination" style="display: none;">
                    <button onclick="cambiarPagina('primera')">
                        <i class="fas fa-angle-double-left"></i>
                    </button>
                    <button onclick="cambiarPagina('anterior')">
                        <i class="fas fa-angle-left"></i> Anterior
                    </button>
                    <span class="page-info" id="page-info">Página 1 de 1</span>
                    <button onclick="cambiarPagina('siguiente')">
                        Siguiente <i class="fas fa-angle-right"></i>
                    </button>
                    <button onclick="cambiarPagina('ultima')">
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let paginaActual = 1;
        let totalPaginas = 1;
        let salidasData = [];
        
        document.addEventListener('DOMContentLoaded', function() {
            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('fecha-hasta').value = hoy;
            
            const hace30Dias = new Date();
            hace30Dias.setDate(hace30Dias.getDate() - 30);
            document.getElementById('fecha-desde').value = hace30Dias.toISOString().split('T')[0];
            
            cargarRazones();
            cargarSalidas();
        });
        
        function cargarRazones() {
            fetch('../../api/inventario/inventario-salida.php?accion=listar_razones')
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        const select = document.getElementById('filtro-razon');
                        data.razones.forEach(razon => {
                            const option = document.createElement('option');
                            option.value = razon.id;
                            option.textContent = razon.descripcion;
                            select.appendChild(option);
                        });
                    }
                });
        }
        
        function cargarSalidas() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('salidas-table').style.display = 'none';
            document.getElementById('salidas-cards').style.display = 'none';
            document.getElementById('empty-state').style.display = 'none';
            
            const estado = document.getElementById('filtro-estado').value;
            const fechaDesde = document.getElementById('fecha-desde').value;
            const fechaHasta = document.getElementById('fecha-hasta').value;
            const razon = document.getElementById('filtro-razon').value;
            
            let url = `../../api/inventario/inventario-salida.php?accion=listar_salidas&pagina=${paginaActual}`;
            
            if(estado) url += `&estado=${estado}`;
            if(fechaDesde) url += `&fecha_desde=${fechaDesde}`;
            if(fechaHasta) url += `&fecha_hasta=${fechaHasta}`;
            if(razon) url += `&razon=${razon}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').style.display = 'none';
                    
                    if(data.success) {
                        salidasData = data.salidas;
                        totalPaginas = data.total_paginas;
                        
                        if(salidasData.length > 0) {
                            mostrarSalidas();
                            actualizarEstadisticas();
                            document.getElementById('pagination').style.display = 'flex';
                        } else {
                            document.getElementById('empty-state').style.display = 'block';
                        }
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    document.getElementById('loading').style.display = 'none';
                    Swal.fire('Error', 'Error al cargar las salidas', 'error');
                });
        }
        
        function mostrarSalidas() {
            // Mostrar tabla (desktop)
            const tbody = document.getElementById('salidas-tbody');
            tbody.innerHTML = '';
            
            // Mostrar cards (móvil)
            const cardsContainer = document.getElementById('salidas-cards');
            cardsContainer.innerHTML = '';
            
            salidasData.forEach(salida => {
                const fecha = new Date(salida.fecha).toLocaleString('es-DO', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const estadoBadge = salida.estado === 'activo' 
                    ? '<span class="badge badge-success"><i class="fas fa-check"></i> Activo</span>'
                    : '<span class="badge badge-danger"><i class="fas fa-times"></i> Cancelado</span>';
                
                // Fila para tabla (desktop)
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>#${salida.id}</td>
                    <td>${fecha}</td>
                    <td><span class="badge badge-reason"><i class="fas fa-tag"></i> ${salida.razon_texto || 'Sin especificar'}</span></td>
                    <td><i class="fas fa-box"></i> ${salida.total_productos.toLocaleString('en-US') || 0}</td>
                    <td><i class="fas fa-cubes"></i> ${parseFloat(salida.total_cantidad || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td>RD$ ${parseFloat(salida.total_costo || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td>${estadoBadge}</td>
                    <td>
                        <div class="actions">
                            <a href="inventario-salida-detalle.php?id=${salida.id}" class="btn btn-info" title="Ver detalles">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                            ${salida.estado === 'activo' ? `
                                <button class="btn btn-danger" onclick="cancelarSalida(${salida.id})" title="Cancelar salida">
                                    <i class="fas fa-ban"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
                
                // Card para móviles
                const card = document.createElement('div');
                card.className = 'salida-card';
                card.innerHTML = `
                    <div class="card-header">
                        <div class="card-id">#${salida.id}</div>
                        ${estadoBadge}
                    </div>
                    <div class="card-body">
                        <div class="card-row">
                            <span class="card-label"><i class="fas fa-calendar"></i> Fecha</span>
                            <span class="card-value">${fecha}</span>
                        </div>
                        <div class="card-row">
                            <span class="card-label"><i class="fas fa-tag"></i> Razón</span>
                            <span class="card-value"><span class="badge badge-reason">${salida.razon_texto || 'Sin especificar'}</span></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label"><i class="fas fa-box"></i> Productos</span>
                            <span class="card-value">${salida.total_productos.toLocaleString('en-US') || 0}</span>
                        </div>
                        <div class="card-row">
                            <span class="card-label"><i class="fas fa-cubes"></i> Unidades</span>
                            <span class="card-value">${parseFloat(salida.total_cantidad || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                        </div>
                        <div class="card-row">
                            <span class="card-label"><i class="fas fa-dollar-sign"></i> Costo Total</span>
                            <span class="card-value">RD$ ${parseFloat(salida.total_costo || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                        </div>
                    </div>
                    <div class="card-actions">
                        <a href="inventario-salida-detalle.php?id=${salida.id}" class="btn btn-info">
                            <i class="fas fa-eye"></i> Ver Detalles
                        </a>
                        ${salida.estado === 'activo' ? `
                            <button class="btn btn-danger" onclick="cancelarSalida(${salida.id})">
                                <i class="fas fa-ban"></i> Cancelar
                            </button>
                        ` : ''}
                    </div>
                `;
                cardsContainer.appendChild(card);
            });
            
            document.getElementById('salidas-table').style.display = 'table';
            document.getElementById('salidas-cards').style.display = 'block';
            document.getElementById('page-info').textContent = `Página ${paginaActual} de ${totalPaginas}`;
        }
        
        function actualizarEstadisticas() {
            let totalSalidas = 0;
            let totalProductos = 0;
            let totalValor = 0;
            let totalMes = 0;
            
            const mesActual = new Date().getMonth();
            const añoActual = new Date().getFullYear();
            
            salidasData.forEach(salida => {
                if(salida.estado === 'activo') {
                    totalSalidas++;
                    totalProductos += parseInt(salida.total_productos || 0);
                    totalValor += parseFloat(salida.total_costo || 0);
                    
                    const fechaSalida = new Date(salida.fecha);
                    if(fechaSalida.getMonth() === mesActual && fechaSalida.getFullYear() === añoActual) {
                        totalMes++;
                    }
                }
            });
            
            document.getElementById('stat-total').textContent = totalSalidas.toLocaleString('en-US');
            document.getElementById('stat-productos').textContent = totalProductos.toLocaleString('en-US');
            document.getElementById('stat-valor').textContent = `RD$ ${totalValor.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            document.getElementById('stat-mes').textContent = totalMes.toLocaleString('en-US');
        }
        
        function cambiarPagina(accion) {
            switch(accion) {
                case 'primera':
                    paginaActual = 1;
                    break;
                case 'anterior':
                    if(paginaActual > 1) paginaActual--;
                    break;
                case 'siguiente':
                    if(paginaActual < totalPaginas) paginaActual++;
                    break;
                case 'ultima':
                    paginaActual = totalPaginas;
                    break;
            }
            cargarSalidas();
        }
        
        function aplicarFiltros() {
            paginaActual = 1;
            cargarSalidas();
        }
        
        function cancelarSalida(id) {
            Swal.fire({
                title: '¿Cancelar salida?',
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
                    formData.append('id_salida', id);
                    formData.append('notas', result.value || '');
                    
                    fetch('../../api/inventario/inventario-salida.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire('Cancelado', data.message, 'success');
                            cargarSalidas();
                        } else {
                        Swal.fire('Error', data.message, 'error');
                        }
                    }).catch(error => {
                        Swal.fire('Error', 'Error al cancelar la salida', 'error');
                    });
                }
            });
        }
    </script>
</body>
</html>