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

require_once '../../../core/verificar-sesion.php'; // Verificar Session

// Validar permisos de usuario
require_once '../../../core/validar-permisos.php';
$permiso_necesario = 'CAJ001';
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
    <title>Sistema de Caja</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilos generales */
        .page-content .container {
            margin: 0 auto;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page-content .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eaeaea;
        }

        .page-content .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 24px;
        }

        .page-content .empleado-info {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .page-content .empleado-info p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }

        .page-content .panel {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 25px;
        }

        .page-content .panel h2 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .page-content form {
            display: flex;
            flex-direction: column;
        }

        .page-content label {
            margin-bottom: 6px;
            color: #555;
            font-size: 14px;
            font-weight: 500;
        }

        .page-content input[type="number"],
        .page-content input[type="text"],
        .page-content select {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
            width: 100%;
        }

        .page-content input[type="number"]:focus,
        .page-content input[type="text"]:focus,
        .page-content select:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .page-content button {
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 5px;
        }

        .page-content button:hover {
            background-color: rgb(57, 79, 102);
        }

        .page-content .info-caja {
            background-color: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #3498db;
        }

        .page-content .info-caja h2 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .page-content .info-caja p {
            margin: 5px 0;
            color: #444;
        }

        .page-content .resumen {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
        }

        .page-content .resumen h3 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
        }

        .page-content .resumen-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 15px;
            color: #495057;
        }

        .page-content .resumen-item:last-child {
            border-bottom: none;
        }

        .page-content .resumen-item .etiqueta {
            font-weight: 500;
        }

        .page-content .resumen-item .valor {
            font-weight: 600;
        }

        .page-content .resumen-item.ingreso .valor {
            color: #27ae60;
        }

        .page-content .resumen-item.egreso .valor {
            color: #e74c3c;
        }

        .page-content .resumen-item.destacado {
            background-color: #e9f7ef;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            border: 1px solid #d5f5e3;
        }

        /* Estilos para conteo de denominaciones */
        .denominaciones-container {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .denominaciones-container.active {
            display: block;
        }

        .denominaciones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .denominacion-item {
            display: flex;
            flex-direction: column;
        }

        .denominacion-item label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #495057;
        }

        .denominacion-item input {
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        .denominacion-total {
            text-align: right;
            margin-top: 15px;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-weight: bold;
            color: #2c3e50;
        }

        .metodo-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }

        .metodo-selector label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
        }

        .metodo-selector input[type="radio"] {
            margin-right: 8px;
            margin-bottom: 0;
        }

        .section-title {
            font-weight: 600;
            color: #2c3e50;
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #3498db;
        }

        .swal-wide {
            width: 600px !important;
            max-width: 90% !important;
        }

        .swal-wide .swal2-html-container {
            text-align: left;
        }

        @media (max-width: 768px) {
            .page-content .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-content .empleado-info {
                margin-top: 15px;
                width: 100%;
            }

            .denominaciones-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .page-content .panel {
                padding: 15px;
            }
            
            .page-content .header h1 {
                font-size: 20px;
            }

            .denominaciones-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navegator-nav">
        <?php include '../../../app/views/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <!-- Header -->
                <div class="header">
                    <h1>Sistema de Caja</h1>
                    <div class="empleado-info">
                        <p>ID Empleado: <span id="empleado-id"></span></p>
                        <p>Empleado: <span id="empleado-nombre"></span></p>
                        <p>Fecha: <span id="empleado-fecha"></span></p>
                    </div>
                </div>

                <!-- Panel Abrir Caja -->
                <div id="panel-abrir-caja" class="panel" style="display: none;">
                    <h2>Abrir Caja</h2>
                    <form id="formAbrirCaja">
                        <div class="metodo-selector">
                            <label>
                                <input type="radio" name="metodo_apertura" value="manual" checked onchange="toggleAperturaMetodo()">
                                Monto Manual
                            </label>
                            <label>
                                <input type="radio" name="metodo_apertura" value="conteo" onchange="toggleAperturaMetodo()">
                                Conteo de Denominaciones
                            </label>
                        </div>

                        <!-- Monto manual -->
                        <div id="apertura-manual">
                            <label for="saldo_apertura">Saldo Inicial:</label>
                            <input type="number" id="saldo_apertura" name="saldo_apertura" step="0.01" min="0" placeholder="0.00">
                        </div>

                        <!-- Conteo de denominaciones -->
                        <div id="apertura-conteo" class="denominaciones-container">
                            <h3 class="section-title">Monedas</h3>
                            <div class="denominaciones-grid" id="monedas-apertura"></div>

                            <h3 class="section-title">Billetes</h3>
                            <div class="denominaciones-grid" id="billetes-apertura"></div>

                            <div class="denominacion-total">
                                Total: RD$ <span id="total-apertura">0.00</span>
                            </div>
                        </div>

                        <button type="submit">Abrir Caja</button>
                    </form>
                </div>

                <!-- Info Caja Abierta -->
                <div id="info-caja-abierta" class="info-caja" style="display: none;">
                    <h2>Usted presenta una caja abierta</h2>
                    <p>Caja #: <span id="caja-numero"></span></p>
                    <p>Fecha de apertura: <span id="caja-fecha-apertura"></span></p>
                    <p>Saldo inicial: <span id="caja-saldo-inicial"></span></p>
                </div>

                <!-- Panel Resumen y Cierre -->
                <div id="panel-caja-abierta" class="panel" style="display: none;">
                    <h2>Resumen de Caja</h2>
                    <div class="resumen">
                        <h3>Movimientos de Caja #<span id="resumen-num-caja"></span></h3>
                        
                        <div class="resumen-item">
                            <span class="etiqueta">Saldo Inicial:</span>
                            <span class="valor" id="resumen-saldo-inicial"></span>
                        </div>

                        <div class="resumen-item ingreso">
                            <span class="etiqueta">Total Ingresos (Efectivo):</span>
                            <span class="valor" id="resumen-ingresos"></span>
                        </div>

                        <div class="resumen-item egreso">
                            <span class="etiqueta">Total Egresos (Efectivo):</span>
                            <span class="valor" id="resumen-egresos"></span>
                        </div>

                        <div class="resumen-item destacado">
                            <span class="etiqueta">Saldo Esperado en Efectivo:</span>
                            <span class="valor" id="resumen-saldo-esperado"></span>
                        </div>

                        <div class="resumen-item">
                            <span class="etiqueta">Número de Facturas:</span>
                            <span class="valor" id="resumen-facturas"></span>
                        </div>
                        
                        <div class="resumen-item">
                            <span class="etiqueta">Número de Pagos:</span>
                            <span class="valor" id="resumen-pagos"></span>
                        </div>

                        <div class="resumen-item">
                            <span class="etiqueta">Total de Transacciones:</span>
                            <span class="valor" id="resumen-transacciones"></span>
                        </div>
                    </div>

                    <h2>Cerrar Caja</h2>
                    <form id="formCerrarCaja">
                        <div class="metodo-selector">
                            <label>
                                <input type="radio" name="metodo_cierre" value="manual" checked onchange="toggleCierreMetodo()">
                                Monto Manual
                            </label>
                            <label>
                                <input type="radio" name="metodo_cierre" value="conteo" onchange="toggleCierreMetodo()">
                                Conteo de Denominaciones
                            </label>
                        </div>

                        <!-- Monto manual -->
                        <div id="cierre-manual">
                            <label for="saldo_final">Saldo Final (conteo físico de efectivo):</label>
                            <input type="number" id="saldo_final" name="saldo_final" step="0.01" min="0" 
                                   placeholder="Ingrese el monto total contado en efectivo">
                        </div>

                        <!-- Conteo de denominaciones -->
                        <div id="cierre-conteo" class="denominaciones-container">
                            <h3 class="section-title">Monedas</h3>
                            <div class="denominaciones-grid" id="monedas-cierre"></div>

                            <h3 class="section-title">Billetes</h3>
                            <div class="denominaciones-grid" id="billetes-cierre"></div>

                            <div class="denominacion-total">
                                Total Contado: RD$ <span id="total-cierre">0.00</span>
                            </div>

                            <div class="denominacion-total" style="background-color: #d4edda; color: #155724; margin-top: 10px;">
                                Diferencia: RD$ <span id="diferencia-cierre">0.00</span>
                            </div>
                        </div>

                        <button type="submit">Cerrar Caja</button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let datosGlobales = null;
        const API_URL = '../../../api/caja/caja.php';

        // Inicializar la aplicación
        document.addEventListener('DOMContentLoaded', function() {
            cargarDatosIniciales();
            configurarEventListeners();
        });

        // Cargar datos iniciales desde la API
        async function cargarDatosIniciales() {
            try {
                mostrarCargando();
                
                const response = await fetch(`${API_URL}?accion=obtener_datos_iniciales`);
                const result = await response.json();
                
                if (result.success) {
                    datosGlobales = result.data;
                    actualizarInterfaz();
                    Swal.close();
                } else {
                    Swal.close();
                    mostrarError(result.message);
                }
            } catch (error) {
                Swal.close();
                mostrarError('Error al cargar datos: ' + error.message);
            }
        }

        // Actualizar la interfaz con los datos cargados
        function actualizarInterfaz() {
            const datos = datosGlobales;
            
            // Actualizar info del empleado
            document.getElementById('empleado-id').textContent = datos.empleado.id;
            document.getElementById('empleado-nombre').textContent = datos.empleado.nombre;
            document.getElementById('empleado-fecha').textContent = datos.empleado.fecha;
            
            if (datos.caja_abierta) {
                // Mostrar info de caja abierta
                document.getElementById('panel-abrir-caja').style.display = 'none';
                document.getElementById('info-caja-abierta').style.display = 'block';
                document.getElementById('panel-caja-abierta').style.display = 'block';
                
                // Llenar datos de la caja
                const caja = datos.datos_caja;
                document.getElementById('caja-numero').textContent = caja.numCaja;
                document.getElementById('caja-fecha-apertura').textContent = formatearFecha(caja.fechaApertura);
                document.getElementById('caja-saldo-inicial').textContent = formatearMoneda(caja.saldoApertura);
                
                // Llenar resumen
                document.getElementById('resumen-num-caja').textContent = caja.numCaja;
                document.getElementById('resumen-saldo-inicial').textContent = formatearMoneda(caja.saldoApertura);
                document.getElementById('resumen-ingresos').textContent = '+ ' + formatearMoneda(datos.total_ingresos);
                document.getElementById('resumen-egresos').textContent = '- ' + formatearMoneda(datos.total_egresos);
                document.getElementById('resumen-saldo-esperado').textContent = formatearMoneda(datos.saldo_esperado);
                document.getElementById('resumen-facturas').textContent = formatearNumero(datos.total_facturas);
                document.getElementById('resumen-pagos').textContent = formatearNumero(datos.total_pagos);
                document.getElementById('resumen-transacciones').textContent = datos.total_facturas + datos.total_pagos;
                
                // Generar campos de denominaciones para cierre
                generarCamposDenominaciones('cierre', datos.denominaciones);
            } else {
                // Mostrar panel para abrir caja
                document.getElementById('panel-abrir-caja').style.display = 'block';
                document.getElementById('info-caja-abierta').style.display = 'none';
                document.getElementById('panel-caja-abierta').style.display = 'none';
                
                // Generar campos de denominaciones para apertura
                generarCamposDenominaciones('apertura', datos.denominaciones);
            }
            
            toggleAperturaMetodo();
            toggleCierreMetodo();
        }

        // Generar campos de denominaciones dinámicamente
        function generarCamposDenominaciones(tipo, denominaciones) {
            const monedasContainer = document.getElementById(`monedas-${tipo}`);
            const billetesContainer = document.getElementById(`billetes-${tipo}`);
            
            if (!monedasContainer || !billetesContainer) return;
            
            monedasContainer.innerHTML = '';
            billetesContainer.innerHTML = '';
            
            // Generar monedas
            Object.entries(denominaciones.monedas).forEach(([valor, label]) => {
                const item = crearCampoDenominacion(valor, label, `moneda_${tipo}_${valor}`, tipo);
                monedasContainer.appendChild(item);
            });
            
            // Generar billetes
            Object.entries(denominaciones.billetes).forEach(([valor, label]) => {
                const item = crearCampoDenominacion(valor, label, `billete_${tipo}_${valor}`, tipo);
                billetesContainer.appendChild(item);
            });
        }

        // Crear un campo de denominación individual
        function crearCampoDenominacion(valor, label, name, tipo) {
            const div = document.createElement('div');
            div.className = 'denominacion-item';
            
            const labelEl = document.createElement('label');
            labelEl.textContent = label;
            
            const input = document.createElement('input');
            input.type = 'number';
            input.name = name;
            input.value = '0';
            input.min = '0';
            input.step = '1';
            input.onchange = () => tipo === 'apertura' ? calcularTotalApertura() : calcularTotalCierre();
            
            div.appendChild(labelEl);
            div.appendChild(input);
            
            return div;
        }

        // Configurar event listeners
        function configurarEventListeners() {
            // Formulario abrir caja
            document.getElementById('formAbrirCaja')?.addEventListener('submit', manejarAbrirCaja);
            
            // Formulario cerrar caja
            document.getElementById('formCerrarCaja')?.addEventListener('submit', manejarCerrarCaja);
        }

        // Manejar apertura de caja
        async function manejarAbrirCaja(e) {
            e.preventDefault();
            
            const metodo = document.querySelector('input[name="metodo_apertura"]:checked').value;
            let saldoInicial = 0;
            const formData = {};
            
            if (metodo === 'manual') {
                saldoInicial = parseFloat(document.getElementById('saldo_apertura').value) || 0;
                formData.saldo_apertura = saldoInicial;
            } else {
                saldoInicial = parseFloat(document.getElementById('total-apertura').textContent) || 0;
                // Obtener todos los valores de denominaciones
                document.querySelectorAll('#apertura-conteo input[type="number"]').forEach(input => {
                    formData[input.name] = input.value;
                });
            }
            
            if (saldoInicial < 0) {
                mostrarError('El saldo inicial no puede ser negativo');
                return;
            }
            
            formData.metodo_apertura = metodo;
            formData.accion = 'abrir_caja';
            
            // Confirmar con el usuario
            const result = await Swal.fire({
                title: '¿Abrir Caja?',
                html: `
                    <div style="text-align: left; padding: 10px;">
                        <p><strong>Saldo Inicial:</strong> ${formatearMoneda(saldoInicial)}</p>
                        <p style="margin-top: 10px;">¿Está seguro de que desea abrir la caja con este monto?</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2c3e50',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Sí, abrir caja',
                cancelButtonText: 'Cancelar'
            });
            
            if (!result.isConfirmed) return;
            
            try {
                mostrarCargando();
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const resultado = await response.json();
                
                if (resultado.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: resultado.message,
                        confirmButtonText: 'Aceptar'
                    });
                    cargarDatosIniciales();
                } else {
                    mostrarError(resultado.message);
                }
            } catch (error) {
                mostrarError('Error al abrir caja: ' + error.message);
            }
        }

        // Manejar cierre de caja
        async function manejarCerrarCaja(e) {
            e.preventDefault();
            
            const metodo = document.querySelector('input[name="metodo_cierre"]:checked').value;
            let saldoFinal = 0;
            const formData = {};
            
            if (metodo === 'manual') {
                saldoFinal = parseFloat(document.getElementById('saldo_final').value) || 0;
                formData.saldo_final = saldoFinal;
            } else {
                saldoFinal = parseFloat(document.getElementById('total-cierre').textContent) || 0;
                // Obtener todos los valores de denominaciones
                document.querySelectorAll('#cierre-conteo input[type="number"]').forEach(input => {
                    formData[input.name] = input.value;
                });
            }
            
            if (saldoFinal < 0) {
                mostrarError('El saldo final no puede ser negativo');
                return;
            }
            
            const saldoEsperado = datosGlobales.saldo_esperado;
            const diferencia = saldoFinal - saldoEsperado;
            
            let estadoDiferencia = '';
            let iconColor = '';
            
            if (diferencia > 0) {
                estadoDiferencia = `<span style="color: #27ae60; font-weight: bold;">+${formatearMoneda(diferencia)} (Sobrante)</span>`;
                iconColor = 'warning';
            } else if (diferencia < 0) {
                estadoDiferencia = `<span style="color: #e74c3c; font-weight: bold;">${formatearMoneda(diferencia)} (Faltante)</span>`;
                iconColor = 'warning';
            } else {
                estadoDiferencia = `<span style="color: #3498db; font-weight: bold;">${formatearMoneda(0)} (Cuadrado)</span>`;
                iconColor = 'success';
            }
            
            formData.metodo_cierre = metodo;
            formData.accion = 'cerrar_caja';
            formData.num_caja = datosGlobales.datos_caja.numCaja;
            formData.registro = datosGlobales.datos_caja.registro;
            formData.fecha_apertura = datosGlobales.datos_caja.fechaApertura;
            formData.saldo_inicial = datosGlobales.datos_caja.saldoApertura;
            
            // Confirmar con el usuario
            const result = await Swal.fire({
                title: '¿Cerrar Caja?',
                html: `
                    <div style="text-align: left; padding: 10px;">
                        <p><strong>Saldo Esperado:</strong> ${formatearMoneda(saldoEsperado)}</p>
                        <p><strong>Saldo Final Contado:</strong> ${formatearMoneda(saldoFinal)}</p>
                        <p style="margin-top: 10px;"><strong>Diferencia:</strong> ${estadoDiferencia}</p>
                        <hr style="margin: 15px 0;">
                        <p style="color: #e74c3c; font-weight: bold;">⚠️ Esta acción no se puede deshacer</p>
                    </div>
                `,
                icon: iconColor,
                showCancelButton: true,
                confirmButtonColor: '#2c3e50',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Sí, cerrar caja',
                cancelButtonText: 'Cancelar',
                customClass: {
                    popup: 'swal-wide'
                }
            });
            
            if (!result.isConfirmed) return;
            
            try {
                Swal.fire({
                    title: 'Cerrando caja...',
                    html: 'Por favor espere',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const resultado = await response.json();
                
                if (resultado.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        html: `
                            <div style="text-align: left;">
                                <p>${resultado.message}</p>
                                <hr>
                                <p><strong>Saldo Esperado:</strong> ${formatearMoneda(resultado.data.saldo_esperado)}</p>
                                <p><strong>Saldo Final:</strong> ${formatearMoneda(resultado.data.saldo_final)}</p>
                                <p><strong>Diferencia:</strong> ${formatearMoneda(resultado.data.diferencia)}</p>
                            </div>
                        `,
                        confirmButtonText: 'Aceptar'
                    });
                    cargarDatosIniciales();
                } else {
                    mostrarError(resultado.message);
                }
            } catch (error) {
                mostrarError('Error al cerrar caja: ' + error.message);
            }
        }

        // Toggle método de apertura
        function toggleAperturaMetodo() {
            const metodo = document.querySelector('input[name="metodo_apertura"]:checked')?.value;
            const manual = document.getElementById('apertura-manual');
            const conteo = document.getElementById('apertura-conteo');
            
            if (!manual || !conteo) return;
            
            if (metodo === 'manual') {
                manual.style.display = 'block';
                conteo.classList.remove('active');
                const input = document.getElementById('saldo_apertura');

                if (input) input.required = true;
            } else {
                manual.style.display = 'none';
                conteo.classList.add('active');
                const input = document.getElementById('saldo_apertura');
            if (input) input.required = false;
                calcularTotalApertura();
            }
        }
        
        // Toggle método de cierre
        function toggleCierreMetodo() {
            const metodo = document.querySelector('input[name="metodo_cierre"]:checked')?.value;
            const manual = document.getElementById('cierre-manual');
            const conteo = document.getElementById('cierre-conteo');
            
            if (!manual || !conteo) return;

            if (metodo === 'manual') {
                manual.style.display = 'block';
                conteo.classList.remove('active');
                const input = document.getElementById('saldo_final');
                if (input) input.required = true;
            } else {
                manual.style.display = 'none';
                conteo.classList.add('active');
                const input = document.getElementById('saldo_final');
                if (input) input.required = false;
                calcularTotalCierre();
            }
        }

        // Calcular total de apertura
        function calcularTotalApertura() {
            let total = 0;
            document.querySelectorAll('#apertura-conteo input[type="number"]').forEach(input => {
                const name = input.name;
                const cantidad = parseInt(input.value) || 0;
                
                // Extraer valor de la denominación del nombre
                const valor = parseInt(name.split('_').pop());
                total += cantidad * valor;
            });

            const totalEl = document.getElementById('total-apertura');
            
            if (totalEl) totalEl.textContent = total.toFixed(2);
        }

        // Calcular total de cierre
        function calcularTotalCierre() {
            let total = 0;
            document.querySelectorAll('#cierre-conteo input[type="number"]').forEach(input => {
                const name = input.name;
                const cantidad = parseInt(input.value) || 0;
                
                // Extraer valor de la denominación del nombre
                const valor = parseInt(name.split('_').pop());
                total += cantidad * valor;
            });

            const totalEl = document.getElementById('total-cierre');
            if (totalEl) totalEl.textContent = total.toFixed(2);

            // Calcular diferencia
            const saldoEsperado = datosGlobales?.saldo_esperado || 0;
            const diferencia = total - saldoEsperado;
            const difElement = document.getElementById('diferencia-cierre');

            if (difElement) {
                difElement.textContent = diferencia.toFixed(2);
                
                // Cambiar color según diferencia
                const container = difElement.parentElement;
                if (diferencia > 0) {
                    container.style.backgroundColor = '#d4edda';
                    container.style.color = '#155724';
                } else if (diferencia < 0) {
                    container.style.backgroundColor = '#f8d7da';
                    container.style.color = '#721c24';
                } else {
                    container.style.backgroundColor = '#d1ecf1';
                    container.style.color = '#0c5460';
                }
            }

            }

        // Utilidades
        function formatearMoneda(valor) {
            let numero = parseFloat(valor);

            if (isNaN(numero)) return "RD$ 0.00";

            return "RD$ " + numero.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function formatearNumero(valor) {
            return parseInt(valor).toLocaleString();
        }

        function formatearFecha(fecha) {
            const d = new Date(fecha);
            return d.toLocaleString('es-DO', {
                day: 'numeric',
                month: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function mostrarCargando() {
            Swal.fire({
                title: 'Cargando...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }

        function mostrarError(mensaje) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: mensaje,
                confirmButtonText: 'Aceptar'
            });
        }
    </script>
</body>
</html>