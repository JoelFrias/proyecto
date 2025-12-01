// ====================================
// VARIABLES GLOBALES
// ====================================
let chartsInstances = {};
let currentPeriod = 'mes';
let customDateStart = null;
let customDateEnd = null;

// ====================================
// INICIALIZACIÓN
// ====================================
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    setupEventListeners();
});

function initializeDashboard() {
    updateDashboard();
}

function setupEventListeners() {
    // Cambio de período
    document.getElementById('periodo').addEventListener('change', function() {
        const periodo = this.value;
        const customDateRange = document.getElementById('customDateRange');
        
        if (periodo === 'personalizado') {
            customDateRange.style.display = 'flex';
            // No actualizar hasta que se seleccionen las fechas
        } else {
            customDateRange.style.display = 'none';
            currentPeriod = periodo;
            updateDashboard();
        }
    });
}

// ====================================
// ACTUALIZACIÓN DE DASHBOARD
// ====================================
function updateDashboard() {
    const periodo = document.getElementById('periodo').value;
    let fechaInicio = null;
    let fechaFin = null;
    
    if (periodo === 'personalizado') {
        fechaInicio = document.getElementById('fechaInicio').value;
        fechaFin = document.getElementById('fechaFin').value;
        
        if (!fechaInicio || !fechaFin) {
            Swal.fire({
                position: "top-end",
                icon: "warning",
                text: "Selecciona las fechas",
                showConfirmButton: false,
                timer: 1500
                });
            return;
        }
    }
    
    // Mostrar indicador de carga
    showLoadingIndicators();
    
    // Cargar todos los datos
    Promise.all([
        loadKPIs(periodo, fechaInicio, fechaFin),
        loadCharts(periodo, fechaInicio, fechaFin),
        loadTables(periodo, fechaInicio, fechaFin),
        loadAlerts()
    ]).then(() => {
        console.log('Dashboard actualizado completamente');
    }).catch(error => {
        console.error('Error actualizando dashboard:', error);
        Swal.fire({
            position: "top-end",
            icon: "error",
            text: "Error al actualizar el dashboard",
            showConfirmButton: false,
            timer: 1500
        });
    });
}

function refreshDashboard() {
    // Animación del botón
    const btn = document.querySelector('.btn-refresh i');
    btn.classList.add('fa-spin');
    
    updateDashboard();
    
    setTimeout(() => {
        btn.classList.remove('fa-spin');
    }, 1000);
}

// ====================================
// CARGAR KPIs
// ====================================
async function loadKPIs(periodo, fechaInicio = null, fechaFin = null) {
    try {
        const params = new URLSearchParams({
            action: 'getKPIs',
            periodo: periodo
        });
        
        if (fechaInicio && fechaFin) {
            params.append('fechaInicio', fechaInicio);
            params.append('fechaFin', fechaFin);
        }
        
        const response = await fetch(`../../api/admin/dashboard.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            updateKPIsUI(data.data);
        } else {
            console.error('Error en KPIs:', data.message);
        }
    } catch (error) {
        console.error('Error cargando KPIs:', error);
    }
}

function updateKPIsUI(kpis) {
    // Ventas Totales
    document.getElementById('kpi-ventas-total').textContent = formatCurrency(kpis.ventas.total);
    const ventasTrend = document.getElementById('kpi-ventas-trend');
    ventasTrend.innerHTML = `<i class="fas fa-arrow-${kpis.ventas.tendencia >= 0 ? 'up' : 'down'}"></i> ${Math.abs(kpis.ventas.tendencia)}%`;
    ventasTrend.className = `kpi-trend ${kpis.ventas.tendencia >= 0 ? 'positive' : 'negative'}`;
    
    // Ganancias
    document.getElementById('kpi-ganancias').textContent = formatCurrency(kpis.ganancias.total);
    const gananciasTrend = document.getElementById('kpi-ganancias-trend');
    gananciasTrend.innerHTML = `<i class="fas fa-arrow-${kpis.ganancias.tendencia >= 0 ? 'up' : 'down'}"></i> ${Math.abs(kpis.ganancias.tendencia)}%`;
    gananciasTrend.className = `kpi-trend ${kpis.ganancias.tendencia >= 0 ? 'positive' : 'negative'}`;
    
    // Facturas
    document.getElementById('kpi-facturas').textContent = kpis.facturas.total;
    document.getElementById('kpi-facturas-pendientes').textContent = `${kpis.facturas.pendientes} pendientes`;
    
    // Productos
    document.getElementById('kpi-productos').textContent = kpis.productos.vendidos;
    document.getElementById('kpi-productos-bajo').textContent = `${kpis.productos.bajo_stock} bajo stock`;
    
    // Clientes
    document.getElementById('kpi-clientes').textContent = kpis.clientes.total;
    document.getElementById('kpi-clientes-nuevos').textContent = `${kpis.clientes.nuevos} nuevos`;
    
    // Por Cobrar
    document.getElementById('kpi-por-cobrar').textContent = formatCurrency(kpis.cuentas.por_cobrar);
    document.getElementById('kpi-clientes-deuda').textContent = `${kpis.cuentas.clientes_deuda} clientes`;
}

// ====================================
// CARGAR GRÁFICOS
// ====================================
async function loadCharts(periodo, fechaInicio = null, fechaFin = null) {
    try {
        const params = new URLSearchParams({
            action: 'getCharts',
            periodo: periodo
        });
        
        if (fechaInicio && fechaFin) {
            params.append('fechaInicio', fechaInicio);
            params.append('fechaFin', fechaFin);
        }
        
        const response = await fetch(`../../api/admin/dashboard.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            createCharts(data.data);
        } else {
            console.error('Error en gráficos:', data.message);
        }
    } catch (error) {
        console.error('Error cargando gráficos:', error);
    }
}

function createCharts(chartsData) {
    // Destruir gráficos existentes
    Object.keys(chartsInstances).forEach(key => {
        if (chartsInstances[key]) {
            chartsInstances[key].destroy();
        }
    });
    
    // Gráfico: Ventas por Día
    const ctxVentas = document.getElementById('chartVentas').getContext('2d');
    chartsInstances.chartVentas = new Chart(ctxVentas, {
        type: 'line',
        data: {
            labels: chartsData.ventas_diarias.labels,
            datasets: [{
                label: 'Ventas',
                data: chartsData.ventas_diarias.data,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    callbacks: {
                        label: function(context) {
                            return 'Ventas: $' + context.parsed.y.toLocaleString('es-DO', {minimumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString('es-DO');
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico: Top 10 Productos
    const ctxProductos = document.getElementById('chartProductos').getContext('2d');
    chartsInstances.chartProductos = new Chart(ctxProductos, {
        type: 'bar',
        data: {
            labels: chartsData.top_productos.labels,
            datasets: [{
                label: 'Cantidad Vendida',
                data: chartsData.top_productos.data,
                backgroundColor: [
                    '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                    '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'
                ],
                borderRadius: 8,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Gráfico: Estado de Facturas
    const ctxFacturas = document.getElementById('chartFacturas').getContext('2d');
    chartsInstances.chartFacturas = new Chart(ctxFacturas, {
        type: 'doughnut',
        data: {
            labels: chartsData.estado_facturas.labels,
            datasets: [{
                data: chartsData.estado_facturas.data,
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        font: {
                            size: 12
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico: Ventas por Empleado
    const ctxEmpleados = document.getElementById('chartEmpleados').getContext('2d');
    chartsInstances.chartEmpleados = new Chart(ctxEmpleados, {
        type: 'bar',
        data: {
            labels: chartsData.ventas_empleado.labels,
            datasets: [{
                label: 'Ventas',
                data: chartsData.ventas_empleado.data,
                backgroundColor: '#8b5cf6',
                borderRadius: 8,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString('es-DO');
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico: Flujo de Caja
    const ctxFlujoCaja = document.getElementById('chartFlujoCaja').getContext('2d');
    chartsInstances.chartFlujoCaja = new Chart(ctxFlujoCaja, {
        type: 'bar',
        data: {
            labels: chartsData.flujo_caja.labels,
            datasets: [
                {
                    label: 'Ingresos',
                    data: chartsData.flujo_caja.ingresos,
                    backgroundColor: '#10b981',
                    borderRadius: 6
                },
                {
                    label: 'Egresos',
                    data: chartsData.flujo_caja.egresos,
                    backgroundColor: '#ef4444',
                    borderRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString('es-DO');
                        }
                    }
                }
            }
        }
    });
}

// ====================================
// CARGAR TABLAS
// ====================================
async function loadTables(periodo, fechaInicio = null, fechaFin = null) {
    try {
        const params = new URLSearchParams({
            action: 'getTables',
            periodo: periodo
        });
        
        if (fechaInicio && fechaFin) {
            params.append('fechaInicio', fechaInicio);
            params.append('fechaFin', fechaFin);
        }
        
        const response = await fetch(`../../api/admin/dashboard.php?${params}`);
        const data = await response.json();
        
        if (data.success) {
            updateTablesUI(data.data);
        } else {
            console.error('Error en tablas:', data.message);
        }
    } catch (error) {
        console.error('Error cargando tablas:', error);
    }
}

function updateTablesUI(tablesData) {
    // Tabla: Top Clientes
    const tableClientes = document.querySelector('#tableClientes tbody');
    tableClientes.innerHTML = '';
    
    if (tablesData.top_clientes.length === 0) {
        tableClientes.innerHTML = '<tr><td colspan="4" class="loading">No hay datos disponibles</td></tr>';
    } else {
        tablesData.top_clientes.forEach((cliente, index) => {
            const row = `
                <tr>
                    <td>${index + 1}</td>
                    <td>${cliente.nombre}</td>
                    <td>${formatCurrency(cliente.total_compras)}</td>
                    <td>${cliente.num_facturas}</td>
                </tr>
            `;
            tableClientes.innerHTML += row;
        });
    }
    
    // Tabla: Stock Bajo
    const tableStockBajo = document.querySelector('#tableStockBajo tbody');
    tableStockBajo.innerHTML = '';
    
    if (tablesData.stock_bajo.length === 0) {
        tableStockBajo.innerHTML = '<tr><td colspan="4" class="loading">Todos los productos tienen stock suficiente</td></tr>';
    } else {
        tablesData.stock_bajo.forEach(producto => {
            const estado = producto.existencia <= producto.reorden * 0.5 ? 'danger' : 'warning';
            const row = `
                <tr>
                    <td>${producto.descripcion}</td>
                    <td>${producto.existencia}</td>
                    <td>${producto.reorden}</td>
                    <td><span class="badge badge-${estado}">Crítico</span></td>
                </tr>
            `;
            tableStockBajo.innerHTML += row;
        });
    }
    
    // Tabla: Facturas Pendientes
    const tableFacturasPendientes = document.querySelector('#tableFacturasPendientes tbody');
    tableFacturasPendientes.innerHTML = '';
    
    if (tablesData.facturas_pendientes.length === 0) {
        tableFacturasPendientes.innerHTML = '<tr><td colspan="6" class="loading">No hay facturas pendientes</td></tr>';
    } else {
        tablesData.facturas_pendientes.forEach(factura => {
            const diasVencido = calcularDiasVencido(factura.fecha);
            const estadoClass = diasVencido > 30 ? 'danger' : diasVencido > 15 ? 'warning' : 'success';
            
            const row = `
                <tr>
                    <td><strong>${factura.numFactura}</strong></td>
                    <td>${factura.cliente}</td>
                    <td>${formatDate(factura.fecha)}</td>
                    <td>${formatCurrency(factura.total)}</td>
                    <td>${formatCurrency(factura.balance)}</td>
                    <td><span class="badge badge-${estadoClass}">${diasVencido} días</span></td>
                </tr>
            `;
            tableFacturasPendientes.innerHTML += row;
        });
    }
}

// ====================================
// CARGAR ALERTAS
// ====================================
async function loadAlerts() {
    try {
        const response = await fetch('../../api/admin/dashboard.php?action=getAlerts');
        const data = await response.json();
        
        if (data.success) {
            updateAlertsUI(data.data);
        } else {
            console.error('Error en alertas:', data.message);
        }
    } catch (error) {
        console.error('Error cargando alertas:', error);
    }
}

function updateAlertsUI(alerts) {
    const alertsContainer = document.getElementById('alertsContainer');
    alertsContainer.innerHTML = '';
    
    if (alerts.length === 0) {
        alertsContainer.innerHTML = `
            <div class="alert-item alert-info">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-content">
                    <strong>Todo en orden</strong>
                    <p>No hay alertas en este momento</p>
                </div>
            </div>
        `;
    } else {
        alerts.forEach(alert => {
            const alertHTML = `
                <div class="alert-item alert-${alert.tipo}">
                    <div class="alert-icon">
                        <i class="fas ${getAlertIcon(alert.tipo)}"></i>
                    </div>
                    <div class="alert-content">
                        <strong>${alert.titulo}</strong>
                        <p>${alert.mensaje}</p>
                    </div>
                </div>
            `;
            alertsContainer.innerHTML += alertHTML;
        });
    }
}

// ====================================
// FUNCIONES AUXILIARES
// ====================================
function formatCurrency(value) {
    return '$' + parseFloat(value).toLocaleString('es-DO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-DO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function calcularDiasVencido(fecha) {
    const fechaFactura = new Date(fecha);
    const hoy = new Date();
    const diferencia = hoy - fechaFactura;
    return Math.floor(diferencia / (1000 * 60 * 60 * 24));
}

function getAlertIcon(tipo) {
    const icons = {
        'danger': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle',
        'success': 'fa-check-circle'
    };
    return icons[tipo] || 'fa-info-circle';
}

function showLoadingIndicators() {
    // Mostrar indicadores de carga en KPIs
    document.querySelectorAll('.kpi-value').forEach(el => {
        el.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    });
}

function toggleChartType(chartId, type) {
    if (chartsInstances[chartId]) {
        chartsInstances[chartId].config.type = type;
        chartsInstances[chartId].update();
    }
}

// ====================================
// GENERAR REPORTE PDF
// ====================================

function generarReportePDF() {
    const periodo = document.getElementById('periodo').value;
    const fechaInicio = document.getElementById('fechaInicio').value;
    const fechaFin = document.getElementById('fechaFin').value;
    
    // Construir URL con parámetros
    let url = '../../reports/gestion/dashboard.php?periodo=' + periodo;
    
    if(periodo === 'personalizado') {
        if(!fechaInicio || !fechaFin) {
            Swal.fire({
                icon: 'warning',
                title: 'Fechas requeridas',
                text: 'Por favor selecciona las fechas de inicio y fin',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        url += '&fechaInicio=' + fechaInicio + '&fechaFin=' + fechaFin;
    }
    
    // Mostrar mensaje de carga
    Swal.fire({
        title: 'Generando reporte...',
        html: 'Por favor espera mientras se genera el PDF',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Abrir PDF en nueva ventana
    setTimeout(() => {
        window.open(url, '_blank');
        Swal.close();
    }, 500);
}