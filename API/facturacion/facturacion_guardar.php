<?php

/* Verificacion de sesion */

// Iniciar sesión
session_start();

// Configurar el tiempo de caducidad de la sesión
$inactivity_limit = 900; // 15 minutos en segundos

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    die(json_encode([
        "success" => false, 
        "error" => "No se ha encontrado una sesión activa",
        "error_code" => "SESSION_NOT_FOUND",
        "solution" => "Por favor inicie sesión nuevamente"
    ]));
}

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'FAC001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    http_response_code(403);
    die(json_encode([
        "success" => false, 
        "error" => "No tiene permisos para realizar esta acción",
        "error_code" => "INSUFFICIENT_PERMISSIONS",
        "solution" => "Contacte al administrador del sistema para obtener los permisos necesarios"
    ]));
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    die(json_encode([
        "success" => false, 
        "error" => "La sesión ha expirado por inactividad",
        "error_code" => "SESSION_EXPIRED",
        "solution" => "Por favor inicie sesión nuevamente"
    ]));
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

require_once '../../core/conexion.php';

// Funcion para guardar los logs de facturacion
function logDebug($message, $data = null) {
    $logMessage = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $logMessage .= " - Data: " . print_r($data, true);
    }
    error_log($logMessage);
}

// Validar metodo de entrada
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["success" => false, "error" => "Método no permitido"]));
}

// Obtener datos JSON
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

logDebug("Datos recibidos", $data);

// Verificar si el JSON es válido
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'JSON inválido',
        'details' => json_last_error_msg()
    ]));
}

header('Content-Type: application/json');

try {
    // Validaciones esenciales
    $requiredFields = ['idCliente', 'tipoFactura', 'formaPago', 'total', 'productos'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception("Campos obligatorios faltantes: " . implode(', ', $missingFields));
    }

    // Sanitización y asignación de variables
    $idCliente = (int)$data['idCliente'];
    $tipoFactura = $conn->real_escape_string($data['tipoFactura']);
    $formaPago = $conn->real_escape_string($data['formaPago']);
    $total = (float)$data['total'];
    $productos = $data['productos'];
    $descuento = isset($data['descuento']) ? (float) $data['descuento'] : 0;
    $montoPagado = (float)$data['montoPagado'];
    $numeroAutorizacion = $data['numeroAutorizacion'] ?? 'N/A';
    $numeroTarjeta = $data['numeroTarjeta'] ?? 'N/A';
    $banco = isset($data['banco']) ? (int)$data['banco'] : 1;
    $destino = isset($data['destino']) ? (int)$data['destino'] : 1;

    logDebug("Variables procesadas", [
        'idCliente' => $idCliente,
        'tipoFactura' => $tipoFactura,
        'formaPago' => $formaPago,
        'total' => $total,
        'montoPagado' => $montoPagado
    ]);

    // Validar que el empleado tenga una caja asignada
    if (!isset($_SESSION['numCaja'])) {
        throw new Exception("No se ha encontrado ninguna caja asignada al vendedor", 1001);
    }

    // Validar que el cliente existe
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de cliente: " . $conn->error);
    }
    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Cliente no encontrado: " . $idCliente);
    }

    // Verificar que el tipo de factura es válido
    $validTypes = ['credito', 'contado'];
    if (!in_array($tipoFactura, $validTypes)) {
        throw new Exception("Tipo de factura inválido: " . $tipoFactura);
    }

    // Verificar que la forma de pago es válida
    $validPayments = ['efectivo', 'tarjeta', 'transferencia'];
    if (!in_array($formaPago, $validPayments)) {
        throw new Exception("Forma de pago inválida: " . $formaPago);
    }

    // Validar que el total es un número positivo
    if ($total <= 0 && $tipoFactura === 'contado') {
        throw new Exception("El total debe ser un número positivo: " . $total);
    }

    // Validar que los productos son válidos
    if (empty($productos) || !is_array($productos)) {
        throw new Exception("Productos inválidos o vacíos");
    }
    foreach ($productos as $producto) {
        if (empty($producto['id']) || empty($producto['cantidad']) || empty($producto['precio']) || empty($producto['venta'])) {
            throw new Exception("Producto inválido: " . print_r($producto, true));
        }
        if ($producto['cantidad'] <= 0 || $producto['precio'] <= 0 || $producto['venta'] <= 0) {
            throw new Exception("Cantidad, precio o venta inválidos para el producto ID: " . $producto['id']);
        }
    }

    // verificar que el descuento es un número positivo y valido
    if ($descuento < 0) {
        throw new Exception("El descuento (" . $descuento . ") no es valido");
    }
    
    // Verificar que el monto pagado es mayor o igual al total
    $montoValido = $montoPagado + $descuento;
    if ($montoValido < $total && $tipoFactura === 'contado') {
        throw new Exception("El monto pagado es menor que el total: " . $montoValido . " < " . $total);
    }

    // Verificar que el número de autorización tenga un formato válido
    if ($formaPago === 'tarjeta' && !preg_match('/^[0-9]{4}$/', $numeroAutorizacion)) {
        throw new Exception("El número de autorización debe ser un número de 4 dígitos", 1007);
    }

    // Verificar que el número de tarjeta tenga un formato válido
    if ($formaPago === 'tarjeta' && !preg_match('/^[0-9]{4}$/', $numeroTarjeta)) {
        throw new Exception("El número de tarjeta debe ser un número de 4 dígitos", 1008);
    }

    // Verificar longitud de la tarjeta
    if ($formaPago === 'tarjeta' && strlen($numeroTarjeta) > 4) {
        throw new Exception("Solo se aceptan los últimos 4 dígitos de la tarjeta", 1005);
    }

    // Verificar longitud del número de autorización en tarjeta
    if ($formaPago === 'tarjeta' && strlen($numeroAutorizacion) > 4) {
        throw new Exception("Solo se aceptan los últimos 4 dígitos del número de autorización", 1009);
    }

    // Verificar longitud del número de autorización en transferencia
    if ($formaPago === 'transferencia' && strlen($numeroAutorizacion) > 4) {
        throw new Exception("Solo se aceptan los últimos 4 dígitos del número de autorización", 1010);
    }

    // Verificar que el banco es válidos
    $stmt = $conn->prepare("SELECT id FROM bancos WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de banco: " . $conn->error, 2001);
    }
    $stmt->bind_param('i', $banco);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Banco no encontrado: " . $banco, 2002);
    }
    
    // Verificar que el destino es válidos
    $stmt = $conn->prepare("SELECT id FROM destinocuentas WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de destino: " . $conn->error, 2003);
    }
    $stmt->bind_param('i', $destino);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Destino no encontrado: " . $destino, 2004);
    }

    /**
     *      0. Iniciamos la transaccion
     */


    $conn->begin_transaction();
    logDebug("Transacción iniciada");


    /**
     *      1. Verificar numero de facturas pendientes
     */


    /* $stmt = $conn->prepare("SELECT COUNT(*) AS pendientes FROM facturas WHERE balance > 0 AND idCliente = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de facturas pendientes: " . $conn->error);
    }
    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    logDebug("Facturas pendientes: ", $result);
    
    if ($result['pendientes'] >= 2 && $tipoFactura === 'credito') {
        throw new Exception("Cliente con el ID $idCliente tiene dos facturas pendientes, el crédito está bloqueado. Para desbloquear el crédito debe de pagar al menos una factura.");
    } */
 
 
    /**
     *      2. Verificar balance del cliente disponible
     */


    $stmt = $conn->prepare("SELECT balance FROM clientes_cuenta WHERE idCliente = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de varificar limite de credito: " . $conn->error);
    }
    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    logDebug("Balance adeudado: ", $result);
    
    if ($result['balance'] < ($total - $montoPagado) && $tipoFactura === 'credito') {
        throw new Exception("Cliente ID $idCliente excede el limite de credito disponible");
    }


    /**
     *      3. Obtener número de factura y actualizar el numero de factura
     */


    $stmt = $conn->prepare("SELECT num FROM numfactura LIMIT 1 FOR UPDATE");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de número de factura: " . $conn->error);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if (!$fila = $resultado->fetch_assoc()) {
        throw new Exception('Error al obtener el número de factura');
    }

    // Variable que almacena el numero de factura a utilizar
    $numFactura = $_SESSION['idEmpleado'] . $fila['num'];

    $nuevoNumero = str_pad((int)$fila['num'] + 1, strlen($fila['num']), '0', STR_PAD_LEFT);
    logDebug("Número de factura generado", ['numfactura' => $numFactura, 'nuevoNumero' => $nuevoNumero]);
    
    $stmtUpdate = $conn->prepare("UPDATE numfactura SET num = ?");
    if (!$stmtUpdate) {
        throw new Exception("Error preparando actualización de número de factura: " . $conn->error);
    }
    $stmtUpdate->bind_param("s", $nuevoNumero);
    if (!$stmtUpdate->execute()) {
        throw new Exception("Error actualizando número de factura: " . $stmtUpdate->error);
    }
    logDebug("Número de factura actualizado");


    /**
     *      4. Insertar factura principal
     */
    

    if ($tipoFactura == "credito") {
        $balance = $total - ($montoPagado + $descuento);
        if ($balance < 0) {
            $balance = 0;
        }
    } else {
        $balance = 0;
    }

    $totalAjuste = $total - $descuento;

    $estado = ($balance > 0) ? 'Pendiente' : 'Pagada';

    $query = "INSERT INTO facturas (numFactura, tipoFactura, fecha, importe, descuento, total, total_ajuste, balance, idCliente, idEmpleado, estado) 
              VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error preparando inserción de factura: " . $conn->error);
    }
    $stmt->bind_param('ssdddddiis', $numFactura, $tipoFactura, $total, $descuento ,$total, $totalAjuste, $balance, $idCliente, $_SESSION['idEmpleado'], $estado);
    if (!$stmt->execute()) {
        throw new Exception("Error insertando factura: " . $stmt->error);
    }
    logDebug("Factura principal insertada", [
        'numFactura' => $numFactura,
        'total' => $total,
        'balance' => $balance,
        'estado' => $estado
    ]);

    
    /**
     *      5. Insertar detalles de productos
     */


    $stmt = $conn->prepare("INSERT INTO facturas_detalles (numFactura, idProducto, cantidad, precioCompra, precioVenta, importe, ganancias, fecha) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        throw new Exception("Error preparando inserción de detalles: " . $conn->error);
    }

    foreach ($productos as $producto) {
        $ganancia = ($producto['venta'] - $producto['precio']) * $producto['cantidad'];
        $stmt->bind_param('siidddd', $numFactura, $producto['id'], $producto['cantidad'], $producto['precio'], $producto['venta'], $producto['subtotal'], $ganancia);
        if (!$stmt->execute()) {
            throw new Exception("Error insertando detalle de producto {$producto['id']}: " . $stmt->error);
        }
        logDebug("Detalle de producto insertado", $producto);
    }


    /**
     *      6. Actualizar inventario del empleado
     */


    $stmt = $conn->prepare("UPDATE inventarioempleados SET cantidad = cantidad - ? WHERE idProducto = ? AND idEmpleado = ? AND cantidad >= ?");
    if (!$stmt) {
        throw new Exception("Error preparando actualización de inventario: " . $conn->error);
    }

    foreach ($productos as $producto) {
        
        $idProducto = $producto['id'];
        
        $stmt->bind_param('diii', $producto['cantidad'], $idProducto, $_SESSION['idEmpleado'], $producto['cantidad']);

        if (!$stmt->execute()) {
            throw new Exception("Error actualizando inventario del empleado para producto {$idProducto} del empleado {$_SESSION['idEmpleado']}: " . $stmt->error);
        }

        // Verificar si el stock llegó a 0 y eliminar la fila
        $stmtCheck = $conn->prepare("SELECT cantidad FROM inventarioempleados WHERE idProducto = ? AND idEmpleado = ?");
        $stmtCheck->bind_param('ii', $idProducto, $_SESSION['idEmpleado']);
        $stmtCheck->execute();
        $result = $stmtCheck->get_result();
        $row = $result->fetch_assoc();

        if ($row && $row['cantidad'] == 0) {
            $stmtDelete = $conn->prepare("DELETE FROM inventarioempleados WHERE idProducto = ? AND idEmpleado = ?");
            $stmtDelete->bind_param('ii', $idProducto, $_SESSION['idEmpleado']);
            $stmtDelete->execute();
            $stmtDelete->close();
            logDebug("Fila eliminada para producto ID {$idProducto} del empleado ID {$_SESSION['idEmpleado']}");
        }

        $stmtCheck->close();

        logDebug("Inventario personal actualizado", [
            'id' => $idProducto,
            'cantidad' => $producto['cantidad'],
            'idEmpleado' => $_SESSION['idEmpleado']
        ]);
    }

  
    
    /**
     *      7. Actualizar existencia en productos
     */


    $stmt = $conn->prepare("UPDATE productos SET existencia = existencia - ? WHERE id = ? AND existencia >= ?");
    if (!$stmt) {
        throw new Exception("Error preparando actualización de inventario: " . $conn->error);
    }
    
    foreach ($productos as $producto) {
        $stmt->bind_param('dii', $producto['cantidad'], $producto['id'], $producto['cantidad']);
        if (!$stmt->execute()) {
            throw new Exception("Error actualizando inventario para producto {$producto['id']}: " . $stmt->error);
        }
        if ($stmt->affected_rows === 0) {
            throw new Exception("Stock insuficiente para producto ID: " . $producto['id']);
        }
        logDebug("Inventario actualizado", $producto);
    }


    /**
     *      8. Registrar trasacciones de inventario
     */

    $stmt = $conn->prepare("INSERT INTO inventariotransacciones (tipo, idProducto, cantidad, fecha, descripcion, idEmpleado) VALUES ( 'venta', ?, ?, NOW(), 'Venta por factura #".$numFactura."', ?)");
    if (!$stmt) {
        throw new Exception("Error registrando las transacciones de inventario: " . $conn->error);
    }
    
    foreach ($productos as $producto) {
        $stmt->bind_param('iii', $producto['id'], $producto['cantidad'], $_SESSION['idEmpleado']);
        if (!$stmt->execute()) {
            throw new Exception("Error registrar las transacciones de inventario del producto -> {$producto['id']}: " . $stmt->error);
        }
        logDebug("Transaccion de invatario realizada", $producto);
    }

    
    /**
     *      9. Registrar método de pago
     */

    // Calcular el total después de descuento
    $totalConDescuento = $total - $descuento;

    // Determinar el monto neto basado en el pago y el descuento
    if ($montoPagado >= $totalConDescuento) {
        // Si pagó completo o más, el monto neto es el total con descuento
        $montoNeto = $totalConDescuento;
    } else {
        // Si es crédito o pago parcial, el monto neto es lo que pagó
        $montoNeto = $montoPagado;
    }

    // Calcular la devolución correctamente
    $devuelta = $montoPagado - $totalConDescuento;
    $devuelta = ($devuelta > 0) ? $devuelta : 0;

    $stmt = $conn->prepare("INSERT INTO facturas_metodopago (numFactura, metodo, monto, numAutorizacion, referencia, idBanco, idDestino, noCaja) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Error preparando inserción de método de pago: " . $conn->error);
    }
    $stmt->bind_param('ssdssiis', $numFactura, $formaPago, $montoNeto, $numeroAutorizacion, $numeroTarjeta, $banco, $destino, $_SESSION['numCaja']);
    if (!$stmt->execute()) {
        throw new Exception("Error insertando método de pago: " . $stmt->error);
    }
    logDebug("Método de pago registrado");



    /**
     *      10. Registrar ingreso en caja
     */


    $stmt = $conn->prepare("INSERT INTO cajaingresos (metodo, monto, IdEmpleado, numCaja, razon, fecha) VALUES (?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        throw new Exception("Error preparando inserción de ingresos: " . $conn->error);
    }
    $razon = "Venta por factura #".$numFactura;
    $stmt->bind_param("sdiss", $formaPago, $montoNeto, $_SESSION['idEmpleado'], $_SESSION['numCaja'], $razon);
    if (!$stmt->execute()) {
        throw new Exception("Error insertando el ingreso: " . $stmt->error);
    }

    logDebug("Ingresos en caja registrado");


    /**
     *      10. Registrar auditorias
     */

    require_once '../../core/auditorias.php';
    $usuario_id = $_SESSION['idEmpleado'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';

    // auditoria de caja
    $accion = "Registro de venta por factura #".$numFactura;
    $detalles = "Método: ".$formaPago.", Monto: ".$montoNeto.", Razón: ".$razon;
    registrarAuditoriaCaja($conn, $usuario_id, $accion, $detalles);
    
    // auditoria de usuarios
    $accion = "Registro de venta por factura #".$numFactura;
    $detalles = "Método: ".$formaPago.", Monto: ".$montoNeto.", Razón: ".$razon;
    registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalles);


    /**
     *      11. Actualizar balance del cliente
     */

    $stmt = $conn->prepare("SELECT limite_credito FROM clientes_cuenta WHERE idCliente = ?");

    if (!$stmt) {
        throw new Exception("Error preparando consulta de límite de crédito: " . $conn->error);
    }

    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        throw new Exception("Cliente no encontrado: " . $idCliente);
    }

    $limiteCredito = $row['limite_credito'];

    // Obtener la suma de todos los balances pendientes de facturas
    $stmt = $conn->prepare("SELECT IFNULL(SUM(balance), 0) as balance_pendiente FROM facturas WHERE idCliente = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta de balance pendiente: " . $conn->error);
    }

    $stmt->bind_param('i', $idCliente);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $balancePendiente = $row['balance_pendiente'];

    // Calcular el nuevo balance disponible
    $balanceDisponible = $limiteCredito - $balancePendiente;

    // Actualizar el balance disponible en clientes_cuenta
    $stmt = $conn->prepare("UPDATE clientes_cuenta SET balance = ? WHERE idCliente = ?");

    if (!$stmt) {
        throw new Exception("Error preparando actualización de balance: " . $conn->error);
    }

    $stmt->bind_param('di', $balanceDisponible, $idCliente);

    if (!$stmt->execute()) {
        throw new Exception("Error actualizando balance del cliente: " . $stmt->error);
    }

    logDebug("Balance del cliente actualizado", [
        'idCliente' => $idCliente,
        'limiteCredito' => $limiteCredito,
        'balancePendiente' => $balancePendiente,
        'balanceDisponible' => $balanceDisponible
    ]);


    /**
     *      12. Confirmar la transacción
     */
    
    $conn->commit();
    logDebug("Transacción completada exitosamente");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Factura procesada correctamente',
        'numFactura' => $numFactura,
        'detalles' => [
            'total' => $total,
            'balance' => $balance,
            'estado' => $estado
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    logDebug("ERROR: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'errorCode' => $e->getCode()
    ]);
} finally {
    if ($conn) {
        // $conn->close();
    }
    exit;
}