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
require_once '../../core/verificar-sesion.php'; // Verificar Session

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'CAJ001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
    exit(); 
}

// Configuración de la zona horaria
date_default_timezone_set('America/Santo_Domingo');

// Denominaciones de moneda dominicana
$denominaciones = [
    'monedas' => [
        1 => 'RD$1',
        5 => 'RD$5',
        10 => 'RD$10',
        25 => 'RD$25'
    ],
    'billetes' => [
        50 => 'RD$50',
        100 => 'RD$100',
        200 => 'RD$200',
        500 => 'RD$500',
        1000 => 'RD$1,000',
        2000 => 'RD$2,000'
    ]
];

// Variables
$mensaje = null;
$id_empleado = $_SESSION['idEmpleado'];
$nombre_empleado = $_SESSION['nombre'];

// Verificar si el empleado tiene una caja abierta 
$sql_verificar = "SELECT
                    numCaja,
                    idEmpleado,
                    fechaApertura AS fechaApertura,
                    saldoApertura,
                    registro
                FROM
                    cajasabiertas
                WHERE
                    idEmpleado = ?";

$stmt = $conn->prepare($sql_verificar);
$stmt->bind_param("i", $id_empleado);
$stmt->execute();
$resultado = $stmt->get_result();
$caja_abierta = false;
$datos_caja = null;

if ($resultado->num_rows > 0) {
    $caja_abierta = true;
    $datos_caja = $resultado->fetch_assoc();

    // Almacenar datos de la caja abierta
    $_SESSION['numCaja'] = $datos_caja['numCaja'];
    $_SESSION['fechaApertura'] = $datos_caja['fechaApertura'];
    $_SESSION['saldoApertura'] = $datos_caja['saldoApertura'];
    $_SESSION['registro'] = $datos_caja['registro'];
}
$stmt->close();

// Consultar totales para caja actual (si está abierta)
$total_ingresos = 0;
$total_egresos = 0;

if ($caja_abierta) {
    $num_caja = $datos_caja['numCaja'];
    
    // Calcular total de ingresos EN EFECTIVO
    $sql_ingresos = "SELECT SUM(monto) as total FROM cajaingresos WHERE metodo = 'efectivo' AND numCaja = ?";
    $stmt = $conn->prepare($sql_ingresos);
    $stmt->bind_param("s", $num_caja);
    $stmt->execute();
    $result_ingresos = $stmt->get_result();
    if ($result_ingresos->num_rows > 0) {
        $row_ingresos = $result_ingresos->fetch_assoc();
        $total_ingresos = $row_ingresos['total'] ? $row_ingresos['total'] : 0;
    }
    $stmt->close();
    
    // Calcular total de egresos EN EFECTIVO
    $sql_egresos = "SELECT SUM(monto) as total FROM cajaegresos WHERE metodo = 'efectivo' AND numCaja = ?";
    $stmt = $conn->prepare($sql_egresos);
    $stmt->bind_param("s", $num_caja);
    $stmt->execute();
    $result_egresos = $stmt->get_result();
    if ($result_egresos->num_rows > 0) {
        $row_egresos = $result_egresos->fetch_assoc();
        $total_egresos = $row_egresos['total'] ? $row_egresos['total'] : 0;
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Abrir caja con transacción
    if (isset($_POST['abrir_caja']) && !$caja_abierta) {
        $metodo_apertura = $_POST['metodo_apertura'] ?? 'manual';
        
        if ($metodo_apertura === 'conteo') {
            // Calcular saldo desde el conteo de denominaciones
            $saldo_apertura = 0;
            
            // Sumar monedas
            foreach ([1, 5, 10, 25] as $valor) {
                $cantidad = intval($_POST["moneda_$valor"] ?? 0);
                $saldo_apertura += $cantidad * $valor;
            }
            
            // Sumar billetes
            foreach ([50, 100, 200, 500, 1000, 2000] as $valor) {
                $cantidad = intval($_POST["billete_$valor"] ?? 0);
                $saldo_apertura += $cantidad * $valor;
            }
        } else {
            $saldo_apertura = filter_input(INPUT_POST, 'saldo_apertura', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }
        
        if ($saldo_apertura === false || $saldo_apertura < 0) {
            $mensaje = "Error: Saldo inicial no válido";
        } else {
            $conn->autocommit(FALSE);
            $error = false;
            
            try {
                // Obtener y bloquear el contador
                $sql_contador = "SELECT contador FROM cajacontador LIMIT 1 FOR UPDATE";
                $stmt = $conn->prepare($sql_contador);
                if (!$stmt->execute()) {
                    throw new Exception("Error al obtener número de caja");
                }
                
                $result_contador = $stmt->get_result();
                $contador_row = $result_contador->fetch_assoc();
                $contador_num = intval($contador_row['contador']);
                $num_caja = str_pad($contador_num, 5, '0', STR_PAD_LEFT);
                $nuevo_contador = $contador_num + 1;
                
                // Actualizar el contador
                $sql_update_contador = "UPDATE cajacontador SET contador = ?";
                $stmt = $conn->prepare($sql_update_contador);
                $stmt->bind_param("i", $nuevo_contador);
                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar contador");
                }
                
                // Insertar caja abierta
                $sql = "INSERT INTO cajasabiertas (numCaja, idEmpleado, fechaApertura, saldoApertura) 
                        VALUES (?, ?, NOW(), ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sid", $num_caja, $id_empleado, $saldo_apertura);
                if (!$stmt->execute()) {
                    throw new Exception("Error al abrir caja en sistema");
                }
                
                $conn->commit();
                $mensaje = "Caja abierta exitosamente con saldo inicial de RD$" . number_format($saldo_apertura, 2);
                $caja_abierta = true;
                
                // Refrescar datos
                $sql_verificar = "SELECT * FROM cajasabiertas WHERE idEmpleado = ?";
                $stmt = $conn->prepare($sql_verificar);
                $stmt->bind_param("i", $id_empleado);
                $stmt->execute();
                $resultado = $stmt->get_result();
                $datos_caja = $resultado->fetch_assoc();

                $_SESSION['numCaja'] = $num_caja;
                $_SESSION['fechaApertura'] = $datos_caja['fechaApertura'];
                $_SESSION['saldoApertura'] = $datos_caja['saldoApertura'];
                $_SESSION['registro'] = $datos_caja['registro'];
                
            } catch (Exception $e) {
                $conn->rollback();
                $mensaje = "Error en transacción: " . $e->getMessage();
                $error = true;
            }
            
            $conn->autocommit(TRUE);
        }
    }
    
    // Cerrar caja con transacción
    if (isset($_POST['cerrar_caja']) && $caja_abierta) {
        $metodo_cierre = $_POST['metodo_cierre'] ?? 'manual';
        $num_caja = filter_input(INPUT_POST, 'num_caja', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $registro = filter_input(INPUT_POST, 'registro', FILTER_SANITIZE_NUMBER_INT);
        $fecha_apertura = filter_input(INPUT_POST, 'fecha_apertura', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $saldo_inicial = filter_input(INPUT_POST, 'saldo_inicial', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        
        if ($metodo_cierre === 'conteo') {
            // Calcular saldo final desde el conteo
            $saldo_final = 0;
            
            // Sumar monedas
            foreach ([1, 5, 10, 25] as $valor) {
                $cantidad = intval($_POST["moneda_cierre_$valor"] ?? 0);
                $saldo_final += $cantidad * $valor;
            }
            
            // Sumar billetes
            foreach ([50, 100, 200, 500, 1000, 2000] as $valor) {
                $cantidad = intval($_POST["billete_cierre_$valor"] ?? 0);
                $saldo_final += $cantidad * $valor;
            }
        } else {
            $saldo_final = filter_input(INPUT_POST, 'saldo_final', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }
        
        if ($saldo_final === false || $saldo_final < 0) {
            $mensaje = "Error: Saldo final no válido";
        } else {
            $conn->autocommit(FALSE);
            $error = false;
            
            try {
                // Calcular saldo esperado: Saldo Inicial + Ingresos - Egresos
                $saldo_esperado = $saldo_inicial + $total_ingresos - $total_egresos;
                
                // Calcular diferencia: Saldo Real - Saldo Esperado
                $diferencia = $saldo_final - $saldo_esperado;
                
                // Insertar en cajas cerradas
                $sql = "INSERT INTO cajascerradas (numCaja, idEmpleado, fechaApertura, fechaCierre, 
                        saldoInicial, saldoFinal, estado, diferencia) 
                        VALUES (?, ?, ?, NOW(), ?, ?, 'pendiente', ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sisddd", $num_caja, $id_empleado, $fecha_apertura, $saldo_inicial, $saldo_final, $diferencia);
                if (!$stmt->execute()) {
                    throw new Exception("Error al registrar cierre de caja");
                }
                
                // Eliminar de cajas abiertas
                $sql_delete = "DELETE FROM cajasabiertas WHERE registro = ?";
                $stmt = $conn->prepare($sql_delete);
                $stmt->bind_param("i", $registro);
                if (!$stmt->execute()) {
                    throw new Exception("Error al eliminar caja abierta");
                }
                
                $conn->commit();
                
                // Mensaje detallado
                $mensaje = "Caja cerrada exitosamente.<br>";
                $mensaje .= "Saldo Esperado: RD$" . number_format($saldo_esperado, 2) . "<br>";
                $mensaje .= "Saldo Final: RD$" . number_format($saldo_final, 2) . "<br>";
                $mensaje .= "Diferencia: RD$" . number_format($diferencia, 2);
                
                if ($diferencia > 0) {
                    $mensaje .= " (Sobrante)";
                } elseif ($diferencia < 0) {
                    $mensaje .= " (Faltante)";
                }
                
                $caja_abierta = false;
                
                // Limpiar variables de caja
                unset($_SESSION['numCaja']);
                unset($_SESSION['fechaApertura']);
                unset($_SESSION['saldoApertura']);
                unset($_SESSION['registro']);
                
            } catch (Exception $e) {
                $conn->rollback();
                $mensaje = "Error en transacción de cierre: " . $e->getMessage();
                $error = true;
            }
            
            $conn->autocommit(TRUE);
        }
    }
    
    // Registrar ingreso con transacción
    if (isset($_POST['registrar_ingreso']) && $caja_abierta) {
        $monto = filter_input(INPUT_POST, 'monto_ingreso', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $metodo = $_POST['metodo_ingreso'];
        $metodo = filter_var($metodo, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $razon = filter_input(INPUT_POST, 'razon_ingreso', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $num_caja = filter_input(INPUT_POST, 'num_caja', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        if ($monto === false || $monto <= 0) {
            $mensaje = "Error: Monto no válido";
        } elseif (empty($razon)) {
            $mensaje = "Error: Razón no puede estar vacía";
        } else {
            $conn->autocommit(FALSE);
            $error = false;
            
            try {
                // Insertar ingreso
                $sql = "INSERT INTO cajaingresos (metodo, monto, IdEmpleado, numCaja, razon, fecha) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sdiss", $metodo, $monto, $id_empleado, $num_caja, $razon);
                if (!$stmt->execute()) {
                    throw new Exception("Error al registrar ingreso");
                }
                
                // Actualizar total de ingresos
                $sql_ingresos = "SELECT SUM(monto) as total FROM cajaingresos WHERE metodo = 'efectivo' AND numCaja = ?";
                $stmt = $conn->prepare($sql_ingresos);
                $stmt->bind_param("s", $num_caja);
                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar total de ingresos");
                }
                
                $result_ingresos = $stmt->get_result();
                if ($result_ingresos->num_rows > 0) {
                    $row_ingresos = $result_ingresos->fetch_assoc();
                    $total_ingresos = $row_ingresos['total'] ? $row_ingresos['total'] : 0;
                }
                
                $conn->commit();
                $mensaje = "Ingreso registrado exitosamente";
                
            } catch (Exception $e) {
                $conn->rollback();
                $mensaje = "Error en transacción de ingreso: " . $e->getMessage();
                $error = true;
            }
            
            $conn->autocommit(TRUE);
        }
    }
    
    // Registrar egreso con transacción
    if (isset($_POST['registrar_egreso']) && $caja_abierta) {
        $monto = filter_input(INPUT_POST, 'monto_egreso', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $metodo = $_POST['metodo_egreso'];
        $razon = filter_input(INPUT_POST, 'razon_egreso', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $num_caja = filter_input(INPUT_POST, 'num_caja', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        if ($monto === false || $monto <= 0) {
            $mensaje = "Error: Monto no válido";
        } elseif (empty($razon)) {
            $mensaje = "Error: Razón no puede estar vacía";
        } else {
            $conn->autocommit(FALSE);
            $error = false;
            
            try {
                // Insertar egreso
                $sql = "INSERT INTO cajaegresos (metodo, monto, IdEmpleado, numCaja, razon, fecha) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sdiss", $metodo, $monto, $id_empleado, $num_caja, $razon);
                if (!$stmt->execute()) {
                    throw new Exception("Error al registrar egreso");
                }
                
                // Actualizar total de egresos
                $sql_egresos = "SELECT SUM(monto) as total FROM cajaegresos WHERE metodo = 'efectivo' AND numCaja = ?";
                $stmt = $conn->prepare($sql_egresos);
                $stmt->bind_param("s", $num_caja);
                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar total de egresos");
                }
                
                $result_egresos = $stmt->get_result();
                if ($result_egresos->num_rows > 0) {
                    $row_egresos = $result_egresos->fetch_assoc();
                    $total_egresos = $row_egresos['total'] ? $row_egresos['total'] : 0;
                }

                $conn->commit();
                $mensaje = "Egreso registrado exitosamente";
                
            } catch (Exception $e) {
                $conn->rollback();
                $mensaje = "Error en transacción de egreso: " . $e->getMessage();
                $error = true;
            }
            
            $conn->autocommit(TRUE);
        }
    }
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

        .page-content .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
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
            margin-top: 10px;
            font-weight: bold;
            color: #2c3e50;
            font-size: 16px;
            background-color: #e9f7ef;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 0;
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

        @media (max-width: 768px) {
            .page-content .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-content .empleado-info {
                margin-top: 15px;
                width: 100%;
            }
            
            .page-content .grid {
                grid-template-columns: 1fr;
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
        
        /* Estilos para SweetAlert más ancho */
        .swal-wide {
            width: 600px !important;
            max-width: 90% !important;
        }

        .swal-wide .swal2-html-container {
            text-align: left;
        }
    </style>
</head>
<body>

    <?php
        if (isset($mensaje)) {
            $icon = strpos($mensaje, 'Error') !== false ? 'error' : 'success';
            $title = strpos($mensaje, 'Error') !== false ? 'ERROR' : 'Éxito';

            echo "
            <script>
                Swal.fire({
                    icon: '$icon',
                    title: '$title',
                    html: '$mensaje',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    window.location.href = 'caja.php';
                });
            </script>
            ";
        }
    ?>

    <div class="navegator-nav">
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="container">
                <div class="header">
                    <h1>Sistema de Caja</h1>
                    <div class="empleado-info">
                        <p>ID Empleado: <?php echo $id_empleado; ?></p>
                        <p>Empleado: <?php echo $nombre_empleado; ?></p>
                        <p>Fecha: <?php echo date('j/n/Y h:i A'); ?></p>
                    </div>
                </div>
                
                <?php if(!$caja_abierta): ?>
                    <!-- Panel para abrir caja -->
                    <div class="panel">
                        <h2>Abrir Caja</h2>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="formAbrirCaja">
                            
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
                            <div class="denominaciones-grid">
                                <?php foreach ($denominaciones['monedas'] as $valor => $label): ?>
                                    <div class="denominacion-item">
                                        <label><?php echo $label; ?></label>
                                        <input type="number" name="moneda_<?php echo $valor; ?>" 
                                               value="0" min="0" step="1" 
                                               onchange="calcularTotalApertura()">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <h3 class="section-title">Billetes</h3>
                            <div class="denominaciones-grid">
                                <?php foreach ($denominaciones['billetes'] as $valor => $label): ?>
                                    <div class="denominacion-item">
                                        <label><?php echo $label; ?></label>
                                        <input type="number" name="billete_<?php echo $valor; ?>" 
                                               value="0" min="0" step="1" 
                                               onchange="calcularTotalApertura()">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="denominacion-total">
                                Total: RD$ <span id="total-apertura">0.00</span>
                            </div>
                        </div>

                        <button type="submit" name="abrir_caja">Abrir Caja</button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Información de caja abierta -->
                <div class="info-caja">
                    <h2>Usted presenta una caja abierta</h2>
                    <p>Caja #: <?php echo $datos_caja['numCaja']; ?></p>
                    <p>Fecha de apertura: <?php echo date('j/n/Y h:i A', strtotime($datos_caja['fechaApertura'])); ?></p>
                    <p>Saldo inicial: RD$<?php echo number_format($datos_caja['saldoApertura'], 2); ?></p>
                </div>

                <?php
                // Calcular saldo esperado
                $saldo_esperado = $datos_caja['saldoApertura'] + $total_ingresos - $total_egresos;

                $sqlFacturas = "SELECT COUNT(*) AS totalFacturas FROM facturas_metodopago WHERE noCaja = ?";
                $stmt = $conn->prepare($sqlFacturas);
                $stmt->bind_param("s", $_SESSION['numCaja']);
                $stmt->execute();
                $resultFacturas = $stmt->get_result();
                $rowFacturas = $resultFacturas->fetch_assoc();
                $totalFacturas = $rowFacturas['totalFacturas'] ? $rowFacturas['totalFacturas'] : 0;
                $stmt->close();

                $sqlPagos = "SELECT COUNT(*) AS totalPagos FROM clientes_historialpagos WHERE numCaja = ?";
                $stmt = $conn->prepare($sqlPagos);
                $stmt->bind_param("s", $_SESSION['numCaja']);
                $stmt->execute();
                $resultPagos = $stmt->get_result();
                $rowPagos = $resultPagos->fetch_assoc();
                $totalPagos = $rowPagos['totalPagos'] ? $rowPagos['totalPagos'] : 0;
                $stmt->close();

                $totalTransacciones = $totalFacturas + $totalPagos;
                ?>
                
                <!-- Resumen de caja -->
                <div class="panel">
                    <h2>Resumen de Caja</h2>
                    <div class="resumen">
                        <h3>Movimientos de Caja #<?php echo $datos_caja['numCaja']; ?></h3>
                        
                        <div class="resumen-item">
                            <span class="etiqueta">Saldo Inicial:</span>
                            <span class="valor">RD$<?php echo number_format($datos_caja['saldoApertura'], 2) ?></span>
                        </div>

                        <div class="resumen-item ingreso">
                            <span class="etiqueta">Total Ingresos (Efectivo):</span>
                            <span class="valor">+ RD$<?php echo number_format($total_ingresos, 2) ?></span>
                        </div>

                        <div class="resumen-item egreso">
                            <span class="etiqueta">Total Egresos (Efectivo):</span>
                            <span class="valor">- RD$<?php echo number_format($total_egresos, 2) ?></span>
                        </div>

                        <div class="resumen-item destacado">
                            <span class="etiqueta">Saldo Esperado en Efectivo:</span>
                            <span class="valor">RD$<?php echo number_format($saldo_esperado, 2) ?></span>
                        </div>

                        <div class="resumen-item">
                            <span class="etiqueta">Número de Facturas:</span>
                            <span class="valor"><?php echo number_format($totalFacturas) ?></span>
                        </div>
                        
                        <div class="resumen-item">
                            <span class="etiqueta">Número de Pagos:</span>
                            <span class="valor"><?php echo number_format($totalPagos) ?></span>
                        </div>

                        <div class="resumen-item">
                            <span class="etiqueta">Total de Transacciones:</span>
                            <span class="valor"><?php echo $totalTransacciones ?></span>
                        </div>
                    </div>
                
                    <h2>Cerrar Caja</h2>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="formCerrarCaja">
                        
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
                            <div class="denominaciones-grid">
                                <?php foreach ($denominaciones['monedas'] as $valor => $label): ?>
                                    <div class="denominacion-item">
                                        <label><?php echo $label; ?></label>
                                        <input type="number" name="moneda_cierre_<?php echo $valor; ?>" 
                                               value="0" min="0" step="1" 
                                               onchange="calcularTotalCierre()">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <h3 class="section-title">Billetes</h3>
                            <div class="denominaciones-grid">
                                <?php foreach ($denominaciones['billetes'] as $valor => $label): ?>
                                    <div class="denominacion-item">
                                        <label><?php echo $label; ?></label>
                                        <input type="number" name="billete_cierre_<?php echo $valor; ?>" 
                                               value="0" min="0" step="1" 
                                               onchange="calcularTotalCierre()">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="denominacion-total">
                                Total Contado: RD$ <span id="total-cierre">0.00</span>
                            </div>

                            <div class="denominacion-total" style="background-color: #d4edda; color: #155724; margin-top: 10px;">
                                Diferencia: RD$ <span id="diferencia-cierre">0.00</span>
                            </div>
                        </div>
                        
                        <input type="hidden" name="num_caja" value="<?php echo $datos_caja['numCaja']; ?>">
                        <input type="hidden" name="registro" value="<?php echo $datos_caja['registro']; ?>">
                        <input type="hidden" name="fecha_apertura" value="<?php echo $_SESSION['fechaApertura']; ?>">
                        <input type="hidden" name="saldo_inicial" value="<?php echo $_SESSION['saldoApertura']; ?>">
                        
                        <button type="submit" name="cerrar_caja">Cerrar Caja</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const saldoEsperado = <?php echo $caja_abierta ? $saldo_esperado : 0; ?>;

    function toggleAperturaMetodo() {
        const metodo = document.querySelector('input[name="metodo_apertura"]:checked').value;
        const manual = document.getElementById('apertura-manual');
        const conteo = document.getElementById('apertura-conteo');
        
        if (metodo === 'manual') {
            manual.style.display = 'block';
            conteo.classList.remove('active');
            document.getElementById('saldo_apertura').required = true;
        } else {
            manual.style.display = 'none';
            conteo.classList.add('active');
            document.getElementById('saldo_apertura').required = false;
            calcularTotalApertura();
        }
    }

    // Toggle método de cierre
    function toggleCierreMetodo() {
        const metodo = document.querySelector('input[name="metodo_cierre"]:checked').value;
        const manual = document.getElementById('cierre-manual');
        const conteo = document.getElementById('cierre-conteo');
        
        if (metodo === 'manual') {
            manual.style.display = 'block';
            conteo.classList.remove('active');
            document.getElementById('saldo_final').required = true;
        } else {
            manual.style.display = 'none';
            conteo.classList.add('active');
            document.getElementById('saldo_final').required = false;
            calcularTotalCierre();
        }
    }

    // Confirmación para abrir caja
    document.getElementById('formAbrirCaja')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const metodo = document.querySelector('input[name="metodo_apertura"]:checked').value;
        let saldoInicial = 0;
        
        if (metodo === 'manual') {
            saldoInicial = parseFloat(document.getElementById('saldo_apertura').value) || 0;
        } else {
            saldoInicial = parseFloat(document.getElementById('total-apertura').textContent) || 0;
        }
        
        if (saldoInicial < 0) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El saldo inicial no puede ser negativo'
            });
            return;
        }
        
        Swal.fire({
            title: '¿Abrir Caja?',
            html: `
                <div style="text-align: left; padding: 10px;">
                    <p><strong>Saldo Inicial:</strong> RD$${saldoInicial.toFixed(2)}</p>
                    <p style="margin-top: 10px;">¿Está seguro de que desea abrir la caja con este monto?</p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2c3e50',
            cancelButtonColor: '#95a5a6',
            confirmButtonText: 'Sí, abrir caja',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });

    // Confirmación para cerrar caja
    document.getElementById('formCerrarCaja')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const metodo = document.querySelector('input[name="metodo_cierre"]:checked').value;
        let saldoFinal = 0;
        
        if (metodo === 'manual') {
            saldoFinal = parseFloat(document.getElementById('saldo_final').value) || 0;
        } else {
            saldoFinal = parseFloat(document.getElementById('total-cierre').textContent) || 0;
        }
        
        if (saldoFinal < 0) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El saldo final no puede ser negativo'
            });
            return;
        }
        
        const diferencia = saldoFinal - saldoEsperado;
        let estadoDiferencia = '';
        let iconColor = '';
        
        if (diferencia > 0) {
            estadoDiferencia = `<span style="color: #27ae60; font-weight: bold;">+RD$${diferencia.toFixed(2)} (Sobrante)</span>`;
            iconColor = 'warning';
        } else if (diferencia < 0) {
            estadoDiferencia = `<span style="color: #e74c3c; font-weight: bold;">RD$${diferencia.toFixed(2)} (Faltante)</span>`;
            iconColor = 'warning';
        } else {
            estadoDiferencia = `<span style="color: #3498db; font-weight: bold;">RD$0.00 (Cuadrado)</span>`;
            iconColor = 'success';
        }
        
        Swal.fire({
            title: '¿Cerrar Caja?',
            html: `
                <div style="text-align: left; padding: 10px;">
                    <p><strong>Saldo Esperado:</strong> RD$${saldoEsperado.toFixed(2)}</p>
                    <p><strong>Saldo Final Contado:</strong> RD$${saldoFinal.toFixed(2)}</p>
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
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loading mientras se procesa
                Swal.fire({
                    title: 'Cerrando caja...',
                    html: 'Por favor espere',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                this.submit();
            }
        });
    });

    // Calcular total de apertura
    function calcularTotalApertura() {
        let total = 0;
        
        // Monedas
        [1, 5, 10, 25].forEach(valor => {
            const cantidad = parseInt(document.querySelector(`input[name="moneda_${valor}"]`).value) || 0;
            total += cantidad * valor;
        });
        
        // Billetes
        [50, 100, 200, 500, 1000, 2000].forEach(valor => {
            const cantidad = parseInt(document.querySelector(`input[name="billete_${valor}"]`).value) || 0;
            total += cantidad * valor;
        });
        
        document.getElementById('total-apertura').textContent = total.toFixed(2);
    }

    // Calcular total de cierre
    function calcularTotalCierre() {
        let total = 0;
        
        // Monedas
        [1, 5, 10, 25].forEach(valor => {
            const cantidad = parseInt(document.querySelector(`input[name="moneda_cierre_${valor}"]`).value) || 0;
            total += cantidad * valor;
        });
        
        // Billetes
        [50, 100, 200, 500, 1000, 2000].forEach(valor => {
            const cantidad = parseInt(document.querySelector(`input[name="billete_cierre_${valor}"]`).value) || 0;
            total += cantidad * valor;
        });
        
        document.getElementById('total-cierre').textContent = total.toFixed(2);
        
        // Calcular diferencia
        const diferencia = total - saldoEsperado;
        const difElement = document.getElementById('diferencia-cierre');
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

    // Inicializar estado
    document.addEventListener('DOMContentLoaded', function() {
        toggleAperturaMetodo();
        if (document.getElementById('formCerrarCaja')) {
            toggleCierreMetodo();
        }
    });
</script>
</body>
</html>