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
} // Conexión a la base de datos
require_once '../../core/verificar-sesion.php'; // Verificar Session

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'PADM001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
        
    exit(); 
}

// Validar permisos específicos para cada módulo
$permiso_dashboard = validarPermiso($conn, 'PADM002', $id_empleado);
$permiso_usuarios = validarPermiso($conn, 'USU001', $id_empleado);
$permiso_empleados = validarPermiso($conn, 'EMP001', $id_empleado);
$permiso_info_factura = validarPermiso($conn, 'FAC003', $id_empleado);
$permiso_cuadres = validarPermiso($conn, 'CUA001', $id_empleado);
$permiso_cotizaciones = validarPermiso($conn, 'COT002', $id_empleado);
$permiso_transferencias = validarPermiso($conn, 'ALM002', $id_empleado);
$entrada_inventario = validarPermiso($conn, 'ALM004', $id_empleado);
$salida_inventario = validarPermiso($conn, 'ALM005', $id_empleado);
$permiso_bancos_destinos = validarPermiso($conn, 'PADM003', $id_empleado);

// Tabla Bancos
if ($permiso_bancos_destinos) {
    $stmtb = $conn->prepare("SELECT id AS idBank, nombreBanco AS namebanks FROM bancos WHERE id <> 1 AND enable = 1");
    $stmtb->execute();
    $resultsb = $stmtb->get_result();
}

// Tabla Destinos
if ($permiso_bancos_destinos) {
    $stmtd = $conn->prepare("SELECT id AS idDestination, descripcion AS namedestinations FROM destinocuentas WHERE id <> 1 AND enable = 1");
    $stmtd->execute();
    $resultsd = $stmtd->get_result();
}

// info factura
if ($permiso_info_factura) {
    $stmtif = $conn->prepare("SELECT * FROM infofactura");
    $stmtif->execute();
    $resultsif = $stmtif->get_result();
    $rowif = $resultsif->fetch_assoc();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Panel Administrativo</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/menu.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Libreria de alertas -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Libreria de graficos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilos generales */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
        }

        .tittle h1 {
            text-align: center;
            margin: 5px 0 15px;
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
            padding-bottom: 10px;
            letter-spacing: 1px;
            position: relative;
        }

        .tittle h1:after {
            content: '';
            position: absolute;
            width: 30%;
            height: 3px;
            bottom: -3px;
            left: 35%;
        }

        .conteiner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Botón para mostrar/ocultar */
        .toggle-menu-btn {
            width: 100%;
            max-width: 700px;
            margin: 0 auto 30px auto;
            display: block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #475bb5 0%, #877d97 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .toggle-menu-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .toggle-menu-btn i {
            margin-left: 10px;
            transition: transform 0.3s;
        }

        .toggle-menu-btn.active i {
            transform: rotate(180deg);
        }

        /* Contenedor de secciones - OCULTO por defecto */
        .admin-sections {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: max-height 0.5s ease, opacity 0.3s ease;
            max-width: 1500px;
            margin: 0 auto;
        }

        .admin-sections.show {
            max-height: 5000px;
            opacity: 1;
        }

        /* Grid simple de botones */
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .admin-btn {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .admin-btn:hover {
            border-color: #3498db;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .admin-btn i {
            font-size: 24px;
            color: #3498db;
            margin-bottom: 10px;
            display: block;
        }

        .admin-btn h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .admin-btn p {
            font-size: 13px;
            color: #7f8c8d;
            margin: 0;
        }

        /* Secciones */
        .section-title {
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
            margin: 30px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #3498db;
        }

        /* Colores por categoría */
        .admin-btn.config i { color: #3498db; }
        .admin-btn.gestion i { color: #e74c3c; }
        .admin-btn.inventario i { color: #f39c12; }
        .admin-btn.reportes i { color: #27ae60; }

        /* Estilos para el contenedor de filtros */
        #filters {
            display: flex;
            justify-content: left;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        /* Estilos para el select */
        #filters select {
            padding: 10px 15px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fff;
            color: #333;
            transition: border-color 0.3s ease;
            font-weight: 600;
        }

        #filters select:focus {
            border-color: #3498db;
            outline: none;
        }

        /* Estilos para el botón de aplicar */
        #btn-filters {
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        #btn-filters:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        #btn-filters:active {
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 768px) {
            #filters {
                flex-direction: column;
                gap: 10px;
            }

            #filters select, #btn-filters {
                width: 100%;
            }

            .admin-grid {
                grid-template-columns: 1fr;
            }
            
            .toggle-menu-btn {
                font-size: 14px;
                padding: 12px 20px;
            }
        }

        /* Estilos para modales */
        #modal-banks, #modal-destinations, #edit-banks, #edit-destinations {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            z-index: 1000;
            max-height: 90vh;
            overflow-y: auto;
        }
        #modal-banks, #modal-destinations {
            max-width: 600px;
        }

        #edit-banks, #edit-destinations {
            max-width: 400px;
        }

        /* Fondo oscuro para modales */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            z-index: 999;
        }

        /* Estilos para los botones de cerrar */
        .close-modal-banks, .close-modal-destinations, .close-edit-banks, .close-edit-destinations {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            font-weight: bold;
            color: #7f8c8d;
            cursor: pointer;
            transition: color 0.3s;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-modal-banks:hover, .close-modal-destinations:hover, .close-edit-banks:hover, .close-edit-destinations:hover {
            color: #e74c3c;
            background-color: #f7f7f7;
        }

        /* Estilos mejorados para los formularios de destinos y bancos */
        #new-destination, #new-bank {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        #new-destination:hover, #new-bank:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        #new-destination label, #new-bank label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 15px;
        }

        #new-destination input[type="text"], #new-bank input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            font-size: 15px;
        }

        #new-destination input[type="text"]:focus, #new-bank input[type="text"]:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.15);
            outline: none;
        }

        #new-destination button[type="submit"], #new-bank button[type="submit"] {
            width: 100%;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 12px 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            text-align: center;
        }

        #new-destination button[type="submit"]:hover, #new-bank button[type="submit"]:hover {
            background-color: #2ecc71;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        #new-destination button[type="submit"]:active, #new-bank button[type="submit"]:active {
            transform: translateY(0);
        }

        /* Responsive para formularios */
        @media (max-width: 768px) {
            #new-destination, #new-bank {
                padding: 15px;
            }
            
            #new-destination input[type="text"], #new-bank input[type="text"],
            #new-destination button[type="submit"], #new-bank button[type="submit"] {
                padding: 10px 12px;
            }
        }

        /* Estilos para tablas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        table tr:hover {
            background-color: #f1f1f1;
        }

        table button {
            padding: 5px 10px;
            background-color: transparent;
            border: none;
            cursor: pointer;
            margin-right: 5px;
        }

        table button i.fa-trash {
            color: #e74c3c;
        }

        table button i.fa-pen-to-square {
            color: #3498db;
        }

        table button:hover i {
            opacity: 0.8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            table th, table td {
                padding: 8px 10px;
                font-size: 14px;
            }
            
            #modal-banks, #modal-destinations, #edit-banks, #edit-destinations {
                width: 95%;
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .conteiner {
                padding: 10px;
            }
            
            table {
                font-size: 12px;
            }
            
            table th, table td {
                padding: 6px 8px;
            }
            
            h3 {
                font-size: 18px;
            }
        }

        /* Estilos mejorados para los botones de acción en tablas */
        table button.delete-bank, 
        table button.edit-bank,
        table button.delete-destination, 
        table button.edit-destination {
            padding: 8px 12px;
            background-color: transparent;
            border: none;
            cursor: pointer;
            margin-right: 8px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        /* Añadir un ligero fondo al hacer hover */
        table button.delete-bank:hover, 
        table button.delete-destination:hover {
            background-color: rgba(231, 76, 60, 0.1);
        }

        table button.edit-bank:hover,
        table button.edit-destination:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }

        /* Estilos para los iconos */
        table button i.fa-trash {
            color: #e74c3c;
            font-size: 16px;
        }

        table button i.fa-pen-to-square {
            color: #3498db;
            font-size: 16px;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        button[type="submit"], #update-edit-bank, #update-edit-destination {
            padding: 10px 15px;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 47%;
            transition: background-color 0.3s;
            margin-right: 10px;
        }

        button[type="submit"]:hover, #update-edit-bank:hover, #update-edit-destination:hover {
            background-color: #2ecc71;
        }

        #cancel-edit-bank, #cancel-edit-destination {
            padding: 10px 15px;
            color: white;
            border: none;
            border-radius: 4px;
            width: 47%;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        #cancel-edit-bank:hover, #cancel-edit-destination:hover {
            background-color: #c0392b;
        }

        /* Estilos específicos para móviles */
        @media (max-width: 480px) {
            /* Hacer que los botones sean más grandes y tengan mejor espacio entre ellos */
            table button.delete-bank, 
            table button.edit-bank,
            table button.delete-destination, 
            table button.edit-destination {
                padding: 10px 12px;
                margin: 3px;
                display: inline-block;
                min-width: 40px;
                text-align: center;
            }
            
            /* Aumentar tamaño de iconos en móvil */
            table button i.fa-trash,
            table button i.fa-pen-to-square {
                font-size: 18px;
            }
            
            /* Ajustar el contenedor de los botones */
            table td:last-child {
                display: flex;
                justify-content: flex-start;
                align-items: center;
                padding: 10px 8px;
                flex-wrap: nowrap;
            }
        }

        /* Para pantallas muy pequeñas */
        @media (max-width: 360px) {
            table button.delete-bank, 
            table button.edit-bank,
            table button.delete-destination, 
            table button.edit-destination {
                min-width: 36px;
                padding: 8px 10px;
            }
        }

        /* Estilos para el dashboard de gráficos */
        .dashboard-header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 15px;
        }

        .dashboard-header h2 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .dashboard-subtitle {
            color: #6c757d;
            font-size: 16px;
            margin-top: 0;
        }

        .filters-container {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .filter-group label {
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .select-styled {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ced4da;
            background-color: #fff;
            font-size: 14px;
            min-width: 150px;
            cursor: pointer;
            transition: border-color 0.15s ease-in-out;
        }

        .select-styled:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .filter-button {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.2s;
        }

        .filter-button:hover {
            background-color: #2980b9;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
            gap: 25px;
        }

        .chart-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .chart-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .chart-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }

        .chart-header i {
            font-size: 20px;
            color: #3498db;
        }

        .chart-title {
            margin: 0;
            color: #2c3e50;
            font-size: 20px;
            font-weight: 600;
        }

        .chart-description {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .chart-wrapper {
            height: 280px;
            position: relative;
        }

        /* Ajustes para pantallas pequeñas */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-group {
                align-items: stretch;
            }
        }

        .open-modal-btn-infoInvoice {
            padding: 12px 24px;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 50px auto;
            display: block;
            transition: background-color 0.3s;
        }
        
        .open-modal-btn-infoInvoice:hover {
            background-color: #3a56d4;
        }
        
        /* Fondo del modal */
        .modal-overlay-infoInvoice {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        /* Contenedor del modal */
        .modal-container-infoInvoice {
            background-color: white;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 30px;
            position: relative;
            transform: translateY(-20px);
            opacity: 0;
            transition: transform 0.3s, opacity 0.3s;
        }
        
        .modal-active-infoInvoice .modal-container-infoInvoice {
            transform: translateY(0);
            opacity: 1;
        }
        
        /* Encabezado del modal */
        .modal-header-infoInvoice {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title-infoInvoice {
            font-size: 22px;
            font-weight: 600;
            color: #333;
        }
        
        .close-modal-btn-infoInvoice {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        /* Cuerpo del modal */
        .modal-body-infoInvoice {
            margin-bottom: 20px;
        }
        
        .input-group-infoInvoice {
            margin-bottom: 18px;
        }
        
        .input-group-infoInvoice label {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
            color: #555;
        }
        
        .input-group-infoInvoice input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        
        .input-group-infoInvoice input:focus {
            outline: none;
            border-color: #4361ee;
        }
        
        /* Pie del modal */
        .modal-footer-infoInvoice {
            display: flex;
            justify-content: flex-end;
        }
        
        .submit-btn-infoInvoice {
            padding: 12px 24px;
            background-color: #4361ee;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .submit-btn-infoInvoice:hover {
            background-color: #3a56d4;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .modal-container-infoInvoice {
                padding: 20px;
            }
            
            .modal-title-infoInvoice {
                font-size: 20px;
            }
            
            .input-group-infoInvoice input, 
            .submit-btn-infoInvoice {
                padding: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .modal-container-infoInvoice {
                padding: 16px;
            }
            
            .modal-title-infoInvoice {
                font-size: 18px;
            }
            
            .modal-footer-infoInvoice {
                justify-content: center;
            }
            
            .submit-btn-infoInvoice {
                width: 100%;
            }
        }
        
        /* Clases para mostrar el modal */
        .modal-active-infoInvoice {
            display: flex;
        }

        /* Mejoras para la impresión del dashboard */
        @media print {
            .dashboard-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .chart-wrapper {
                height: 200px;
            }
            
            .filters-container,
            .toggle-menu-btn,
            .admin-sections {
                display: none !important;
            }
            
            .chart-container {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
        
        .admin-header {
            display: flex;
            align-items: center;
            justify-content: left;
            gap: 12px;
            margin: 0 auto 30px auto;
        }

        .admin-icon {
            font-size: 28px;
            color: #2c3e50;
        }

        .admin-header h1 {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .admin-header h1:after {
            display: none;
        }

        @media (max-width: 768px) {
            .admin-icon {
                font-size: 24px;
            }
            
            .admin-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">

            <!-- TODO EL CONTENIDO DE LA PAGINA VA AQUI DEBAJO -->

            

            <!--

            Boton para mostrar/ocultar el menú administrativo
            
            <button class="toggle-menu-btn" id="toggleMenuBtn">
                Mostrar Menú Administrativo
                <i class="fas fa-chevron-down"></i>
            </button>

            -->

            <!-- Secciones administrativas (OCULTAS por defecto) -->
        <div class="admin-sections show" id="adminSections">

            <div class="tittle">
                <div class="admin-header">
                    <i class="fas fa-cog admin-icon"></i>
                    <h1>Panel Administrativo</h1>
                </div>
            </div>
            
            <?php if($permiso_bancos_destinos || $permiso_info_factura || $permiso_empleados): ?>
            <!-- Configuración -->
            <h2 class="section-title">Gestión</h2>
            <div class="admin-grid">
                
                <?php if($permiso_bancos_destinos): ?>
                <div class="admin-btn config" id="manager-banks">
                    <i class="fas fa-university"></i>
                    <h3>Bancos</h3>
                    <p>Administrar cuentas bancos</p>
                </div>

                <div class="admin-btn config" id="manager-destinations">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Destinos de Cuenta</h3>
                    <p>Administrar destinos</p>
                </div>
                <?php endif; ?>

                <?php if($permiso_info_factura): ?>
                <div class="admin-btn config manager-infoInvoice">
                    <i class="fas fa-file-invoice"></i>
                    <h3>Información Factura</h3>
                    <p>Personalizar datos en las facturas</p>
                </div>
                <?php endif; ?>

                <?php if($permiso_empleados): ?>
                <div class="admin-btn config" onclick="window.location.href='../../app/empleados/empleados.php'">
                    <i class="fas fa-user-tie"></i>
                    <h3>Empleados</h3>
                    <p>Gestionar personal y permisos</p>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

            <?php if($entrada_inventario || $salida_inventario || $permiso_transferencias): ?>
            <!-- Inventario -->
            <h2 class="section-title">Inventario</h2>
            <div class="admin-grid">
                
                <?php if($entrada_inventario): ?>
                <div class="admin-btn inventario" onclick="window.location.href='../../app/inventario/inventario-entrada-lista.php'">
                    <i class="fas fa-arrow-down"></i>
                    <h3>Entrada Inventario</h3>
                    <p>Registrar nuevos productos y existencias</p>
                </div>
                <?php endif; ?>

                <?php if($salida_inventario): ?>
                <div class="admin-btn inventario" onclick="window.location.href='../../app/inventario/inventario-salida-lista.php'">
                    <i class="fas fa-arrow-up"></i>
                    <h3>Salida Inventario</h3>
                    <p>Registrar bajas y salidas de productos</p>
                </div>
                <?php endif; ?>

                <?php if($permiso_transferencias): ?>
                <div class="admin-btn inventario" onclick="window.location.href='../../app/inventario/registro-transacciones.php'">
                    <i class="fas fa-exchange-alt"></i>
                    <h3>Transferencias</h3>
                    <p>Movimientos entre almacenes</p>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

            <?php if($permiso_cuadres || $permiso_cotizaciones || $permiso_dashboard): ?>
            <!-- Reportes -->
            <h2 class="section-title">Reportes</h2>
            <div class="admin-grid">
                
                <?php if($permiso_cuadres): ?>
                <div class="admin-btn reportes" onclick="window.location.href='../../app/caja/cuadre-caja.php'">
                    <i class="fas fa-cash-register"></i>
                    <h3>Cuadres de Caja</h3>
                    <p>Consultar cierres y arqueos de caja</p>
                </div>
                <?php endif; ?>

                <?php if($permiso_cotizaciones): ?>
                <div class="admin-btn reportes" onclick="window.location.href='../../app/factura/cotizacion-registro.php'">
                    <i class="fas fa-file-alt"></i>
                    <h3>Cotizaciones</h3>
                    <p>Historial de cotizaciones realizadas</p>
                </div>
                <?php endif; ?>

                <?php if($permiso_dashboard): ?>
                <div class="admin-btn reportes" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-chart-line"></i>
                    <h3>Dashboard del Negocio</h3>
                    <p>Visualizar métricas y estadísticas clave</p>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

        </div>

        <?php if($permiso_bancos_destinos): ?>
            <!-- Modal para bancos -->
            <div id="modal-banks" style="display: none;">

                <span class="close-modal-banks">&times;</span>
            
                <h3>Bancos</h3>

                <div id="new-bank">
                    <label for="bank-name">Agregar Nuevo Banco:</label>
                    <input type="text" id="bank-name" name="bank-name" autocomplete="off">
                    <button type="submit" onclick="addBank()">Agregar</button>
                </div>

                <div id="bank-list">

                    <h4>Lista de Bancos</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre del Banco</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="bank-table-body">
                            <?php
                                if($resultsb->num_rows > 0){
                                    while ($rowb = $resultsb->fetch_assoc()) {
                                        echo "
                                            <tr data-id='{$rowb['idBank']}' data-name='{$rowb['namebanks']}'>
                                                <td>{$rowb['namebanks']}</td>
                                                <td>
                                                    <button class='delete-bank' onclick=\"deleteBank({$rowb['idBank']})\"><i class=\"fa-solid fa-trash\"></i></button>
                                                    <button class='edit-bank'><i class=\"fa-regular fa-pen-to-square\"></i></button>
                                                </td>
                                            </tr>
                                        ";
                                    }
                                } else {
                                    echo "<tr><td colspan='3'>No se encontraron resultados.</td></tr>";
                                }
                            ?>
                        </tbody>
                    </table>

                </div>
            </div>
            
            <!-- Modal para editar bancos -->
            <div id="edit-banks" style="display: none;">
                
                <span class="close-edit-banks">&times;</span>

                <h3>Editar Banco</h3>
                <label for="edit-bank-name">Nombre del Banco:</label>
                <input type="hidden" id="edit-bank-id" name="edit-bank-id"> <!-- ID oculto para el banco -->
                <input type="text" id="edit-bank-name" name="edit-bank-name"  autocomplete="off">
                <button id="update-edit-bank" onclick="updateBank()">Actualizar</button>
                <button id="cancel-edit-bank">Cancelar</button>

            </div>

            <!-- Modal para destinos -->
            <div id="modal-destinations" style="display: none;">
                
                <span class="close-modal-destinations">&times;</span>

                <h3>Destinos</h3>

                <div id="new-destination">
                    <label for="destination-name">Agregar Nuevo Destino:</label>
                    <input type="text" id="destination-name" name="destination-name" autocomplete="off">
                    <button type="submit" onclick="addDestination()">Agregar</button>
                </div>
                
                <div id="destination-list">

                    <h4>Lista de Destinos</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre del Destino</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="destination-table-body">
                            <?php
                                if($resultsd->num_rows > 0){
                                    while ($rowd = $resultsd->fetch_assoc()) {
                                        echo "
                                            <tr data-id='{$rowd['idDestination']}' data-name='{$rowd['namedestinations']}'>
                                                <td>{$rowd['namedestinations']}</td>
                                                <td>
                                                    <button class='delete-destination' onclick=\"deleteDestination({$rowd['idDestination']})\"><i class=\"fa-solid fa-trash\"></i></button>
                                                    <button class='edit-destination'><i class=\"fa-regular fa-pen-to-square\"></i></button>
                                                </td>
                                            </tr>
                                        ";
                                    }
                                } else {
                                    echo "<tr><td colspan='3'>No se encontraron resultados.</td></tr>";
                                }
                            ?>
                        </tbody>
                    </table>

                </div>
            </div>

            <!-- Modal para editar destinos -->
            <div id="edit-destinations" style="display: none;">

                <span class="close-edit-destinations">&times;</span>

                <h3>Editar Destino</h3>
                <label for="edit-destination-name">Nombre del Destino:</label>
                <input type="hidden" id="edit-destination-id" name="edit-destination-id"> <!-- ID oculto para el destino -->
                <input type="text" id="edit-destination-name" name="edit-destination-name" autocomplete="off" autocomplete="off">
                <button id="update-edit-destination" onclick="updateDestination()">Actualizar</button>
                <button id="cancel-edit-destination">Cancelar</button>

            </div>
        <?php endif; ?>

        <?php if($permiso_info_factura): ?>
            <!-- Modal para info Factura -->
            <div class="modal-overlay-infoInvoice" id="modal-overlay-infoInvoice">
                <div class="modal-container-infoInvoice">
                    <div class="modal-header-infoInvoice">
                        <h2 class="modal-title-infoInvoice">Informacion en Factura</h2>
                        <button class="close-modal-btn-infoInvoice" id="closeModalBtn-infoInvoice">&times;</button>
                    </div>
                    <div class="modal-body-infoInvoice">
                        <div class="input-group-infoInvoice">
                            <label for="texto1-infoInvoice">Texto 1</label>
                            <input type="text" id="texto1-infoInvoice" placeholder="Ingrese el primer texto" value="<?= $rowif['text1'] ?>">
                        </div>
                        <div class="input-group-infoInvoice">
                            <label for="texto2-infoInvoice">Texto 2</label>
                            <input type="text" id="texto2-infoInvoice" placeholder="Ingrese el segundo texto" value="<?= $rowif['text2'] ?>">
                        </div>
                        <div class="input-group-infoInvoice">
                            <label for="texto3-infoInvoice">Texto 3</label>
                            <input type="text" id="texto3-infoInvoice" placeholder="Ingrese el tercer texto" value="<?= $rowif['text3'] ?>">
                        </div>
                    </div>
                    <div class="modal-footer-infoInvoice">
                        <button class="submit-btn-infoInvoice" id="submitBtn-infoInvoice">Enviar</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR POR ENCIMA DE ESTA LINEA -->
    </div>
</div>

<!-- Script para manipular los bancos y destinos -->
<script>
    <?php if($permiso_bancos_destinos): ?>
        function deleteBank(id){

            const datos = {
                idBank: id
            };

            fetch("../../api/admin/admin-delete-bank.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            })
            .then(response => response.text())
            .then(text => {
                try {
                    let data = JSON.parse(text);
                    if (data.success) {

                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: 'Banco eliminado correctamente.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });

                        const row = document.querySelector(`tr[data-id='${id}']`);
                        if (row) {
                            row.remove();
                        }

                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.error,
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });
                        console.log("Error al borrar el banco:", data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Se produjo un error inesperado en el servidor.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.error("Error: Respuesta no es JSON válido:", text);
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Se produjo un error de red o en el servidor. Por favor, inténtelo de nuevo.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                console.error("Error de red o servidor:", error);
            });
        }

        function updateBank(){

            const datos = {
                idBank: document.getElementById('edit-bank-id').value,
                nombre: document.getElementById('edit-bank-name').value
            };

            fetch("../../api/admin/admin-update-bank.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            })
            .then(response => response.text())
            .then(text => {
                try {
                    let data = JSON.parse(text);
                    if (data.success) {

                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: 'Banco actualizado correctamente.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });

                        const row = document.querySelector(`tr[data-id='${datos.idBank}']`);
                        if (row) {
                            row.dataset.name = datos.nombre;
                            row.querySelector('td').textContent = datos.nombre;
                        }

                        const editModal = document.getElementById('edit-banks');
                        editModal.style.display = 'none';
                        const overlay = document.querySelector('.modal-overlay');
                        if (overlay) {
                            overlay.remove();
                        }

                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.error,
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });
                        console.log("Error al actualizar el banco:", data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Se produjo un error inesperado en el servidor.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.error("Error: Respuesta no es JSON válido:", text);
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Se produjo un error de red o en el servidor. Por favor, inténtelo de nuevo.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                console.error("Error de red o servidor:", error);
            });
        }

        function deleteDestination(id){
            const datos = {
                idDestination: id
            };

            fetch("../../api/admin/admin-delete-destination.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            })
            .then(response => response.text())
            .then(text => {
                try {
                    let data = JSON.parse(text);
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: 'Destino eliminado correctamente.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });

                        const row = document.querySelector(`#destination-table-body tr[data-id='${id}']`);
                        if (row) {
                            row.remove();
                        } else {
                            console.log("No se encontró la fila a eliminar con id:", id);
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.error,
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });
                        console.log("Error al borrar el destino:", data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Se produjo un error inesperado en el servidor.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.error("Error: Respuesta no es JSON válido:", text);
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Se produjo un error de red o en el servidor. Por favor, inténtelo de nuevo.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                console.error("Error de red o servidor:", error);
            });
        }

        function updateDestination(){
            const idDestino = document.getElementById('edit-destination-id').value;
            const nombreDestino = document.getElementById('edit-destination-name').value;
            
            const datos = {
                idDestino: idDestino,
                nombre: nombreDestino
            };

            fetch("../../api/admin/admin-update-destination.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            })
            .then(response => response.text())
            .then(text => {
                try {
                    let data = JSON.parse(text);
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: 'Destino actualizado correctamente.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });

                        const row = document.querySelector(`#destination-table-body tr[data-id='${idDestino}']`);
                        if (row) {
                            row.dataset.name = nombreDestino;
                            row.querySelector('td').textContent = nombreDestino;
                        } else {
                            console.log("No se encontró la fila a actualizar con id:", idDestino);
                        }

                        const editModal = document.getElementById('edit-destinations');
                        editModal.style.display = 'none';
                        const overlay = document.querySelector('.modal-overlay');
                        if (overlay) {
                            overlay.remove();
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.error,
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });
                        console.log("Error al actualizar destino:", data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Se produjo un error inesperado en el servidor.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.error("Error: Respuesta no es JSON válido:", text);
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Se produjo un error de red o en el servidor. Por favor, inténtelo de nuevo.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                console.error("Error de red o servidor:", error);
            });
        }
    <?php endif; ?>

    <?php if($permiso_info_factura): ?>
        function updateInfoInvoice(text1, text2, text3){
        
            const datos = {
                text1: text1,
                text2: text2,
                text3: text3
            };

            fetch("../../api/admin/info-factura.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            })
            .then(response => response.text())
            .then(text => {
                try {
                    let data = JSON.parse(text);
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: 'Informacion Actualizada Correctamente.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.error,
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });
                        console.log("Error al borrar el destino:", data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Se produjo un error inesperado en el servidor.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.error("Error: Respuesta no es JSON válido:", text);
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Se produjo un error de red o en el servidor. Por favor, inténtelo de nuevo.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                console.error("Error de red o servidor:", error);
            });
        }
    <?php endif; ?>
</script>

<!-- Script para manipular los modales -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if($permiso_bancos_destinos): ?>
        const modalBanks = document.getElementById('modal-banks');
        const modalDestinations = document.getElementById('modal-destinations');
        const editBanks = document.getElementById('edit-banks');
        const editDestinations = document.getElementById('edit-destinations');
        
        const btnManagerBanks = document.getElementById('manager-banks');
        const btnManagerDestinations = document.getElementById('manager-destinations');
        
        const closeModalBanks = document.querySelector('.close-modal-banks');
        const closeModalDestinations = document.querySelector('.close-modal-destinations');
        const closeEditBanks = document.querySelector('.close-edit-banks');
        const closeEditDestinations = document.querySelector('.close-edit-destinations');
        
        const cancelEditBank = document.getElementById('cancel-edit-bank');
        const cancelEditDestination = document.getElementById('cancel-edit-destination');
        
        function createOverlay() {
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            document.body.appendChild(overlay);
            return overlay;
        }
        
        function removeOverlay() {
            const overlay = document.querySelector('.modal-overlay');
            if (overlay) {
                overlay.remove();
            }
        }
        
        if (btnManagerBanks) {
            btnManagerBanks.addEventListener('click', function() {
                createOverlay();
                modalBanks.style.display = 'block';
            });
        }
        
        if (btnManagerDestinations) {
            btnManagerDestinations.addEventListener('click', function() {
                createOverlay();
                modalDestinations.style.display = 'block';
            });
        }
        
        if (closeModalBanks) {
            closeModalBanks.addEventListener('click', function() {
                modalBanks.style.display = 'none';
                removeOverlay();
            });
        }
        
        if (closeModalDestinations) {
            closeModalDestinations.addEventListener('click', function() {
                modalDestinations.style.display = 'none';
                removeOverlay();
            });
        }
        
        if (closeEditBanks) {
            closeEditBanks.addEventListener('click', function() {
                editBanks.style.display = 'none';
            });
        }
        
        if (closeEditDestinations) {
            closeEditDestinations.addEventListener('click', function() {
                editDestinations.style.display = 'none';
            });
        }
        
        if (cancelEditBank) {
            cancelEditBank.addEventListener('click', function() {
                editBanks.style.display = 'none';
            });
        }
        
        if (cancelEditDestination) {
            cancelEditDestination.addEventListener('click', function() {
                editDestinations.style.display = 'none';
            });
        }
        
        const bankTableBody = document.getElementById('bank-table-body');
        if (bankTableBody) {
            bankTableBody.addEventListener('click', function(e) {
                if (e.target.closest('.edit-bank')) {
                    const row = e.target.closest('tr');
                    const bankId = row.dataset.id;
                    const bankName = row.dataset.name;
                    
                    document.getElementById('edit-bank-id').value = bankId;
                    document.getElementById('edit-bank-name').value = bankName;
                    
                    editBanks.style.display = 'block';
                }
            });
        }
        
        const destinationTableBody = document.getElementById('destination-table-body');
        if (destinationTableBody) {
            destinationTableBody.addEventListener('click', function(e) {
                if (e.target.closest('.edit-destination')) {
                    const row = e.target.closest('tr');
                    const destId = row.dataset.id;
                    const destName = row.dataset.name;
                    
                    document.getElementById('edit-destination-id').value = destId;
                    document.getElementById('edit-destination-name').value = destName;
                    
                    editDestinations.style.display = 'block';
                }
            });
        }
        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                if (modalBanks) modalBanks.style.display = 'none';
                if (modalDestinations) modalDestinations.style.display = 'none';
                if (editBanks) editBanks.style.display = 'none';
                if (editDestinations) editDestinations.style.display = 'none';
                removeOverlay();
            }
        });
        <?php endif; ?>

        <?php if($permiso_info_factura): ?>
        const openModalBtn = document.querySelector('.manager-infoInvoice');
        const modalOverlay = document.getElementById('modal-overlay-infoInvoice');
        const closeModalBtn = document.getElementById('closeModalBtn-infoInvoice');
        const submitBtn = document.getElementById('submitBtn-infoInvoice');
        
        if (openModalBtn && modalOverlay && closeModalBtn && submitBtn) {
            function openModal() {
                modalOverlay.classList.add('modal-active-infoInvoice');
            }
            
            function closeModal() {
                modalOverlay.classList.remove('modal-active-infoInvoice');
            }
            
            function handleSubmit() {
                const texto1 = document.getElementById('texto1-infoInvoice').value;
                const texto2 = document.getElementById('texto2-infoInvoice').value;
                const texto3 = document.getElementById('texto3-infoInvoice').value;
                
                closeModal();
                
                updateInfoInvoice(texto1, texto2, texto3);
            }
            
            openModalBtn.addEventListener('click', openModal);
            closeModalBtn.addEventListener('click', closeModal);
            submitBtn.addEventListener('click', handleSubmit);
            
            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) {
                    closeModal();
                }
            });
            
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modalOverlay.classList.contains('modal-active-infoInvoice')) {
                    closeModal();
                }
            });
        }
        <?php endif; ?>
    });
</script>

<!-- Script para agregar bancos y destinos -->
<script>
    <?php if($permiso_bancos_destinos): ?>
    function addBank() {
        const bankNameInput = document.getElementById('bank-name');
        
        const datos = {
            nameBank: bankNameInput.value.trim()
        };
        
        if (!datos.nameBank) {
            Swal.fire({
                icon: 'warning',
                title: 'Campo vacío',
                text: 'Por favor ingrese un nombre para el banco',
                showConfirmButton: true,
                confirmButtonText: 'Aceptar'
            });
            return;
        }

        fetch("../../api/admin/admin-new-bank.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(datos)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.text();
        })
        .then(text => {
            try {
                console.log("Respuesta del servidor:", text);
                let data = JSON.parse(text);
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: data.message || 'Banco agregado correctamente.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });

                    const newRow = `<tr data-id="${data.data.id}" data-name="${datos.nameBank}">
                        <td>${datos.nameBank}</td>
                        <td>
                            <button class="delete-bank" onclick="deleteBank(${data.data.id})">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            <button class="edit-bank">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </button>
                        </td>
                    </tr>`;
                    
                    document.getElementById('bank-table-body').insertAdjacentHTML('beforeend', newRow);

                    bankNameInput.value = '';

                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Ocurrió un error al agregar el banco.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.log("Error al agregar el banco:", data.error);
                }
            } catch (error) {
                console.error("Error al procesar respuesta JSON:", error);
                console.error("Texto recibido:", text);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Se produjo un error inesperado en el servidor.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
            }
        })
        .catch(error => {
            console.error("Error de red o servidor:", error);
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Se produjo un error de red o en el servidor. Por favor, inténtelo de nuevo.',
                showConfirmButton: true,
                confirmButtonText: 'Aceptar'
            });
        });
    }

    function addDestination() {
        const destinationNameInput = document.getElementById('destination-name');
        
        const datos = {
            nameDestination: destinationNameInput.value.trim()
        };
        
        if (!datos.nameDestination) {
            Swal.fire({
                icon: 'warning',
                title: 'Campo vacío',
                text: 'Por favor ingrese un nombre para el destino',
                showConfirmButton: true,
                confirmButtonText: 'Aceptar'
            });
            return;
        }

        fetch("../../api/admin/admin-new-destination.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(datos)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.text();
        })
        .then(text => {
            try {
                console.log("Respuesta del servidor:", text);
                let data = JSON.parse(text);
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: data.message || 'Destino agregado correctamente.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });

                    const newRow = `<tr data-id="${data.data.id}" data-name="${datos.nameDestination}">
                                        <td>${datos.nameDestination}</td>
                                        <td>
                                            <button class="delete-destination" onclick="deleteDestination(${data.data.id})">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                            <button class="edit-destination">
                                                <i class="fa-regular fa-pen-to-square"></i>
                                            </button>
                                        </td>
                                    </tr>`;
                    
                    document.getElementById('destination-table-body').insertAdjacentHTML('beforeend', newRow);

                    destinationNameInput.value = '';

                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Ocurrió un error al agregar el destino.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.log("Error al agregar el destino:", data.error);
                }
            } catch (error) {
                console.error("Error al procesar respuesta JSON:", error);
                console.error("Texto recibido:", text);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Se produjo un error inesperado en el servidor.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
            }
        })
        .catch(error => {
            console.error("Error de red o servidor:", error);
            
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Se produjo un error de red o en el servidor. Por favor, inténtelo de nuevo.',
                showConfirmButton: true,
                confirmButtonText: 'Aceptar'
            });
        });
    }
    
    <?php endif; ?>
</script>

</body>
</html>