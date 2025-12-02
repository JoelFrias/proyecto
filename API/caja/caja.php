<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../core/conexion.php';
require_once '../../core/verificar-sesion.php';
require_once '../../core/validar-permisos.php';

// Función para enviar respuesta JSON
function sendResponse($success, $data = null, $message = '', $error_code = null) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data) $response['data'] = $data;
    if ($error_code) $response['error_code'] = $error_code;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// Verificar conexión a la base de datos
if (!$conn || $conn->connect_errno !== 0) {
    sendResponse(false, null, 'Error de conexión a la base de datos', 'DATABASE_CONNECTION_ERROR');
}

// Validar permisos de usuario
$permiso_necesario = 'CAJ001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    http_response_code(403);
    sendResponse(false, null, 'No tiene permisos para acceder a este recurso', 'FORBIDDEN');
}

// Configuración de la zona horaria
date_default_timezone_set('America/Santo_Domingo');

// Obtener método y acción
$metodo = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? $_GET['accion'] ?? '';

// Denominaciones de moneda dominicana
$denominaciones = [
    'monedas' => [1 => 'RD$1', 5 => 'RD$5', 10 => 'RD$10', 25 => 'RD$25'],
    'billetes' => [50 => 'RD$50', 100 => 'RD$100', 200 => 'RD$200', 500 => 'RD$500', 1000 => 'RD$1,000', 2000 => 'RD$2,000']
];

// ROUTER - Manejar diferentes acciones
switch ($accion) {
    
    case 'obtener_datos_iniciales':
        obtenerDatosIniciales($conn, $id_empleado, $denominaciones);
        break;
    
    case 'abrir_caja':
        abrirCaja($conn, $id_empleado, $input);
        break;
    
    case 'cerrar_caja':
        cerrarCaja($conn, $id_empleado, $input);
        break;
    
    case 'registrar_ingreso':
        registrarIngreso($conn, $id_empleado, $input);
        break;
    
    case 'registrar_egreso':
        registrarEgreso($conn, $id_empleado, $input);
        break;
    
    default:
        sendResponse(false, null, 'Acción no válida', 'INVALID_ACTION');
}

// ==================== FUNCIONES ====================

function obtenerDatosIniciales($conn, $id_empleado, $denominaciones) {
    // Verificar si el empleado tiene una caja abierta
    $sql_verificar = "SELECT numCaja, idEmpleado, fechaApertura, saldoApertura, registro 
                      FROM cajasabiertas WHERE idEmpleado = ?";
    $stmt = $conn->prepare($sql_verificar);
    $stmt->bind_param("i", $id_empleado);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $caja_abierta = false;
    $datos_caja = null;
    $total_ingresos = 0;
    $total_egresos = 0;
    $total_facturas = 0;
    $total_pagos = 0;
    
    if ($resultado->num_rows > 0) {
        $caja_abierta = true;
        $datos_caja = $resultado->fetch_assoc();
        $num_caja = $datos_caja['numCaja'];
        
        // Calcular total de ingresos EN EFECTIVO
        $sql_ingresos = "SELECT SUM(monto) as total FROM cajaingresos 
                        WHERE metodo = 'efectivo' AND numCaja = ?";
        $stmt = $conn->prepare($sql_ingresos);
        $stmt->bind_param("s", $num_caja);
        $stmt->execute();
        $result_ingresos = $stmt->get_result();
        if ($result_ingresos->num_rows > 0) {
            $row_ingresos = $result_ingresos->fetch_assoc();
            $total_ingresos = $row_ingresos['total'] ?? 0;
        }
        
        // Calcular total de egresos EN EFECTIVO
        $sql_egresos = "SELECT SUM(monto) as total FROM cajaegresos 
                       WHERE metodo = 'efectivo' AND numCaja = ?";
        $stmt = $conn->prepare($sql_egresos);
        $stmt->bind_param("s", $num_caja);
        $stmt->execute();
        $result_egresos = $stmt->get_result();
        if ($result_egresos->num_rows > 0) {
            $row_egresos = $result_egresos->fetch_assoc();
            $total_egresos = $row_egresos['total'] ?? 0;
        }
        
        // Contar facturas
        $sqlFacturas = "SELECT COUNT(*) AS totalFacturas FROM facturas_metodopago WHERE noCaja = ?";
        $stmt = $conn->prepare($sqlFacturas);
        $stmt->bind_param("s", $num_caja);
        $stmt->execute();
        $resultFacturas = $stmt->get_result();
        $rowFacturas = $resultFacturas->fetch_assoc();
        $total_facturas = $rowFacturas['totalFacturas'] ?? 0;
        
        // Contar pagos
        $sqlPagos = "SELECT COUNT(*) AS totalPagos FROM clientes_historialpagos WHERE numCaja = ?";
        $stmt = $conn->prepare($sqlPagos);
        $stmt->bind_param("s", $num_caja);
        $stmt->execute();
        $resultPagos = $stmt->get_result();
        $rowPagos = $resultPagos->fetch_assoc();
        $total_pagos = $rowPagos['totalPagos'] ?? 0;
    }
    $stmt->close();
    
    sendResponse(true, [
        'empleado' => [
            'id' => $id_empleado,
            'nombre' => $_SESSION['nombre'],
            'fecha' => date('j/n/Y h:i A')
        ],
        'caja_abierta' => $caja_abierta,
        'datos_caja' => $datos_caja,
        'total_ingresos' => $total_ingresos,
        'total_egresos' => $total_egresos,
        'total_facturas' => $total_facturas,
        'total_pagos' => $total_pagos,
        'saldo_esperado' => $caja_abierta ? ($datos_caja['saldoApertura'] + $total_ingresos - $total_egresos) : 0,
        'denominaciones' => $denominaciones
    ]);
}

function abrirCaja($conn, $id_empleado, $input) {
    $metodo_apertura = $input['metodo_apertura'] ?? 'manual';
    $saldo_apertura = 0;
    
    if ($metodo_apertura === 'conteo') {
        // Calcular desde conteo de denominaciones
        foreach ([1, 5, 10, 25] as $valor) {
            $cantidad = intval($input["moneda_$valor"] ?? 0);
            $saldo_apertura += $cantidad * $valor;
        }
        foreach ([50, 100, 200, 500, 1000, 2000] as $valor) {
            $cantidad = intval($input["billete_$valor"] ?? 0);
            $saldo_apertura += $cantidad * $valor;
        }
    } else {
        $saldo_apertura = floatval($input['saldo_apertura'] ?? 0);
    }
    
    if ($saldo_apertura < 0) {
        sendResponse(false, null, 'Saldo inicial no válido', 'INVALID_AMOUNT');
    }
    
    $conn->autocommit(FALSE);
    
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
        
        // Obtener datos de la caja recién abierta
        $sql_verificar = "SELECT * FROM cajasabiertas WHERE idEmpleado = ?";
        $stmt = $conn->prepare($sql_verificar);
        $stmt->bind_param("i", $id_empleado);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $datos_caja = $resultado->fetch_assoc();
        
        // Actualizar sesión
        $_SESSION['numCaja'] = $num_caja;
        $_SESSION['fechaApertura'] = $datos_caja['fechaApertura'];
        $_SESSION['saldoApertura'] = $datos_caja['saldoApertura'];
        $_SESSION['registro'] = $datos_caja['registro'];
        
        $conn->commit();
        
        sendResponse(true, [
            'num_caja' => $num_caja,
            'saldo_apertura' => $saldo_apertura,
            'datos_caja' => $datos_caja
        ], "Caja abierta exitosamente con saldo inicial de RD$" . number_format($saldo_apertura, 2));
        
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, null, "Error en transacción: " . $e->getMessage(), 'TRANSACTION_ERROR');
    } finally {
        $conn->autocommit(TRUE);
    }
}

function cerrarCaja($conn, $id_empleado, $input) {
    $metodo_cierre = $input['metodo_cierre'] ?? 'manual';
    $num_caja = $input['num_caja'] ?? '';
    $registro = intval($input['registro'] ?? 0);
    $fecha_apertura = $input['fecha_apertura'] ?? '';
    $saldo_inicial = floatval($input['saldo_inicial'] ?? 0);
    $saldo_final = 0;
    
    if ($metodo_cierre === 'conteo') {
        // Calcular desde conteo
        foreach ([1, 5, 10, 25] as $valor) {
            $cantidad = intval($input["moneda_cierre_$valor"] ?? 0);
            $saldo_final += $cantidad * $valor;
        }
        foreach ([50, 100, 200, 500, 1000, 2000] as $valor) {
            $cantidad = intval($input["billete_cierre_$valor"] ?? 0);
            $saldo_final += $cantidad * $valor;
        }
    } else {
        $saldo_final = floatval($input['saldo_final'] ?? 0);
    }
    
    if ($saldo_final < 0) {
        sendResponse(false, null, 'Saldo final no válido', 'INVALID_AMOUNT');
    }
    
    // Calcular totales
    $total_ingresos = 0;
    $total_egresos = 0;
    
    $sql_ingresos = "SELECT SUM(monto) as total FROM cajaingresos WHERE metodo = 'efectivo' AND numCaja = ?";
    $stmt = $conn->prepare($sql_ingresos);
    $stmt->bind_param("s", $num_caja);
    $stmt->execute();
    $result_ingresos = $stmt->get_result();
    if ($result_ingresos->num_rows > 0) {
        $row_ingresos = $result_ingresos->fetch_assoc();
        $total_ingresos = $row_ingresos['total'] ?? 0;
    }
    
    $sql_egresos = "SELECT SUM(monto) as total FROM cajaegresos WHERE metodo = 'efectivo' AND numCaja = ?";
    $stmt = $conn->prepare($sql_egresos);
    $stmt->bind_param("s", $num_caja);
    $stmt->execute();
    $result_egresos = $stmt->get_result();
    if ($result_egresos->num_rows > 0) {
        $row_egresos = $result_egresos->fetch_assoc();
        $total_egresos = $row_egresos['total'] ?? 0;
    }
    
    $conn->autocommit(FALSE);
    
    try {
        $saldo_esperado = $saldo_inicial + $total_ingresos - $total_egresos;
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
        
        // Limpiar sesión
        unset($_SESSION['numCaja']);
        unset($_SESSION['fechaApertura']);
        unset($_SESSION['saldoApertura']);
        unset($_SESSION['registro']);
        
        $conn->commit();
        
        sendResponse(true, [
            'saldo_esperado' => $saldo_esperado,
            'saldo_final' => $saldo_final,
            'diferencia' => $diferencia
        ], "Caja cerrada exitosamente");
        
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, null, "Error en transacción de cierre: " . $e->getMessage(), 'TRANSACTION_ERROR');
    } finally {
        $conn->autocommit(TRUE);
    }
}

function registrarIngreso($conn, $id_empleado, $input) {
    $monto = floatval($input['monto_ingreso'] ?? 0);
    $metodo = $input['metodo_ingreso'] ?? '';
    $razon = $input['razon_ingreso'] ?? '';
    $num_caja = $input['num_caja'] ?? '';
    
    if ($monto <= 0) {
        sendResponse(false, null, 'Monto no válido', 'INVALID_AMOUNT');
    }
    if (empty($razon)) {
        sendResponse(false, null, 'Razón no puede estar vacía', 'EMPTY_REASON');
    }
    
    $conn->autocommit(FALSE);
    
    try {
        $sql = "INSERT INTO cajaingresos (metodo, monto, IdEmpleado, numCaja, razon, fecha) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdiss", $metodo, $monto, $id_empleado, $num_caja, $razon);
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar ingreso");
        }
        
        $conn->commit();
        sendResponse(true, null, "Ingreso registrado exitosamente");
        
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, null, "Error en transacción de ingreso: " . $e->getMessage(), 'TRANSACTION_ERROR');
    } finally {
        $conn->autocommit(TRUE);
    }
}

function registrarEgreso($conn, $id_empleado, $input) {
    $monto = floatval($input['monto_egreso'] ?? 0);
    $metodo = $input['metodo_egreso'] ?? '';
    $razon = $input['razon_egreso'] ?? '';
    $num_caja = $input['num_caja'] ?? '';
    
    if ($monto <= 0) {
        sendResponse(false, null, 'Monto no válido', 'INVALID_AMOUNT');
    }
    if (empty($razon)) {
        sendResponse(false, null, 'Razón no puede estar vacía', 'EMPTY_REASON');
    }
    
    $conn->autocommit(FALSE);
    
    try {
        $sql = "INSERT INTO cajaegresos (metodo, monto, IdEmpleado, numCaja, razon, fecha) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdiss", $metodo, $monto, $id_empleado, $num_caja, $razon);
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar egreso");
        }
        
        $conn->commit();
        sendResponse(true, null, "Egreso registrado exitosamente");
        
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(false, null, "Error en transacción de egreso: " . $e->getMessage(), 'TRANSACTION_ERROR');
    } finally {
        $conn->autocommit(TRUE);
    }
}
?>