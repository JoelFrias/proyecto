<?php
// cancelar-factura.php - Procesa la cancelación de factura
session_start();
require_once('../../models/conexion.php');

// Verificar si hay una sesión de usuario activa
if (!isset($_SESSION['idEmpleado'])) {
    echo json_encode(['success' => false, 'message' => 'No hay sesión de usuario']);
    exit;
}

// Verificar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener los datos del formulario
$numFactura = isset($_POST['numFactura']) ? intval($_POST['numFactura']) : 0;
$motivo = isset($_POST['motivo']) ? $conn->real_escape_string($_POST['motivo']) : '';
$idEmpleado = $_SESSION['idEmpleado'];

// Validaciones iniciales
if (empty($numFactura) || empty($motivo)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Iniciar transacción
$conn->begin_transaction();

try {
    // Verificar si la factura existe y no está ya cancelada
    $sql = "SELECT
                f.estado AS estado,
                f.total AS total,
                f.idCliente AS idCliente,
                f.idEmpleado AS idEmpleado,
                f.fecha AS fecha_factura
            FROM
                facturas AS f
            WHERE
                f.numFactura = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $numFactura);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('La factura no existe');
    }
    
    $factura = $result->fetch_assoc();
    
    if ($factura['estado'] === 'Cancelada') {
        throw new Exception('Esta factura ya ha sido cancelada');
    }
    
    // Debug info para verificar el formato de fecha que viene de la base de datos
    error_log("Factura #$numFactura - Fecha original en BD: " . $factura['fecha_factura']);

    // Verificar si han pasado más de dos horas desde la facturación
    try {
        // Establecer zona horaria para República Dominicana
        $zonaHoraria = new DateTimeZone('America/Santo_Domingo');
        
        // Obtener la fecha de la factura y convertirla a timestamp
        $fechaFacturaObj = new DateTime($factura['fecha_factura'], $zonaHoraria);
        $fechaFacturaTimestamp = $fechaFacturaObj->getTimestamp();
        $fechaFacturaFormateada = $fechaFacturaObj->format('d/m/Y h:i A');
        
        // Obtener fecha actual con la misma zona horaria
        $fechaActualObj = new DateTime('now', $zonaHoraria);
        $fechaActualTimestamp = $fechaActualObj->getTimestamp();
        
        // Calcular diferencia en segundos y luego en horas
        $diferenciaSegundos = $fechaActualTimestamp - $fechaFacturaTimestamp;
        $horasPasadas = $diferenciaSegundos / 3600;
        $minutosPasados = $diferenciaSegundos / 60;
        
        // Calcular tiempo restante para cancelación
        $minutosRestantes = 4320 - $minutosPasados;  // 3 dias = 4320 minutos
        
        // Permitir cancelación solo si no han pasado más de 72 horas (3 Dias)
        if ($horasPasadas > 72) {
            $mensaje = sprintf(
                'No se puede cancelar la factura. Han pasado %d horas y %d minutos desde su emisión. ' .
                'La factura fue emitida el %s. El tiempo límite para cancelaciones es de 3 dias.',
                floor($horasPasadas),
                floor(($horasPasadas - floor($horasPasadas)) * 60),
                $fechaFacturaFormateada
            );
            throw new Exception($mensaje);
        }
        
        // Registrar información de depuración
        error_log("Factura #$numFactura - Fecha factura: " . $fechaFacturaObj->format('Y-m-d H:i:s') . 
                 " - Fecha actual: " . $fechaActualObj->format('Y-m-d H:i:s') . 
                 " - Tiempo transcurrido: " . number_format($horasPasadas, 2) . " horas" . 
                 " - Minutos restantes: " . number_format($minutosRestantes, 0));
                 
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'No se puede cancelar la factura') === 0) {
            throw $e;
        } else {
            // Si hay un error en el cálculo de fechas, permitir la cancelación y registrar el error
            error_log("Error al calcular tiempo para factura #$numFactura: " . $e->getMessage());
        }
    }

    $idEmpleadoi = $factura['idEmpleado'];

    $sqlCaja = "SELECT
                    CASE 
                        WHEN EXISTS (
                            SELECT 1 
                            FROM cajasabiertas ca 
                            WHERE ca.numCaja = fm.noCaja
                        ) THEN 1
                        ELSE 0
                    END AS caja_abierta,
                    fm.metodo,
                    fm.noCaja,
                    fm.monto
                FROM
                    facturas_metodopago AS fm
                WHERE
                    fm.numFactura = ?;";
    
    $stmtCaja = $conn->prepare($sqlCaja);
    $stmtCaja->bind_param("i", $numFactura);
    $stmtCaja->execute();
    $resultCaja = $stmtCaja->get_result();
    
    if ($resultCaja->num_rows === 0) {
        throw new Exception('Factura no encontrada en metodo de pago');
    }
    $cajaFactura = $resultCaja->fetch_assoc();
    
    // Verificar si la caja con la que se cobró sigue activa
    $cajaActiva = ($cajaFactura['caja_abierta'] == 1);
    
    // Registrar la cancelación en la tabla facturas_cancelaciones
    $sql = "INSERT INTO facturas_cancelaciones (numFactura, motivo, fecha, idEmpleado) 
            VALUES (?, ?, NOW(), ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $numFactura, $motivo, $idEmpleado);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al registrar la cancelación');
    }
    
    // Cambiar el estado de la factura a "Cancelada"
    $sql = "UPDATE facturas SET estado = 'Cancelada', balance = 0 WHERE numFactura = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $numFactura);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar el estado de la factura');
    }
    
    /**  Si la caja sigue activa y el metodo fue efectivo, registrar un egreso
    * if ($cajaActiva && $cajaFactura['metodo'] == 'efectivo') {
    *    $monto = $cajaFactura['monto'];
    *    $concepto = "Devolución por cancelación de factura #" . $numFactura;
        
    *    $sql = "INSERT INTO cajaegresos (metodo, monto, idEmpleado, numCaja, razon, fecha) 
    *            VALUES ('efectivo', ?, ?, ?, ?, NOW())";
        
    *    $stmt = $conn->prepare($sql);
    *    $stmt->bind_param("diss", $monto, $idEmpleado, $cajaFactura['noCaja'], $concepto);
        
    *    if (!$stmt->execute()) {
    *        throw new Exception('Error al registrar el egreso en caja');
    *    }
    *}

    */

    // Borrar el ingreso de la factura en la tabla ingresos
    $sql = "DELETE FROM cajaingresos WHERE razon = ?";
    $stmt = $conn->prepare($sql);
    $parametro = "Venta por factura #" . $numFactura;
    $stmt->bind_param("s", $parametro);
    if (!$stmt->execute()) {
        throw new Exception('Error al eliminar el ingreso de la factura');
    }
    $stmt->close();

    // Actualizar balance del cliente

    $idCliente = $factura['idCliente'];

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

    // Devolver productos al inventario del empleado
    $sqldevb = "SELECT
                    fd.idProducto as id,
                    fd.cantidad as cantidad
                FROM
                    facturas_detalles fd
                WHERE
                    fd.numFactura = ?";

    $stmtdevb = $conn->prepare($sqldevb);
    if (!$stmtdevb) {
        throw new Exception("Error preparando consulta de productos: " . $conn->error);
    }

    $stmtdevb->bind_param("i", $numFactura);
    if (!$stmtdevb->execute()) {
        throw new Exception("Error ejecutando consulta de productos: " . $stmtdevb->error);
    }

    $resultdevb = $stmtdevb->get_result();
    if ($resultdevb->num_rows === 0) {
        throw new Exception('Error buscando los productos a devolver');
    }

    // Requerir Auditoria
    require_once '../../models/auditorias.php';

    foreach ($resultdevb as $resultb) {
        // Actualizar inventario de empleados
        $sqlCheck = "SELECT idProducto FROM inventarioempleados WHERE idProducto = ? AND idEmpleado = ? LIMIT 1";
        $stmtCheck = $conn->prepare($sqlCheck);
        if (!$stmtCheck) {
            throw new Exception("Error preparando verificación de inventario: " . $conn->error);
        }

        $stmtCheck->bind_param("ii", $resultb['id'], $idEmpleadoi);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            // Existe, entonces actualizar
            $sqldeva = "UPDATE inventarioempleados SET cantidad = cantidad + ? WHERE idProducto = ? AND idEmpleado = ?";
            $stmtdeva = $conn->prepare($sqldeva);
            if (!$stmtdeva) {
                throw new Exception("Error preparando actualización de inventario de empleado: " . $conn->error);
            }
            
            $stmtdeva->bind_param("iii", $resultb['cantidad'], $resultb['id'], $idEmpleadoi);
            if (!$stmtdeva->execute()) {
                throw new Exception("Error actualizando inventario de empleado: " . $stmtdeva->error);
            }
        } else {
            // No existe, entonces insertar
            $sqlInsert = "INSERT INTO inventarioempleados (idProducto, idEmpleado, cantidad) VALUES (?, ?, ?)";
            $stmtInsert = $conn->prepare($sqlInsert);
            if (!$stmtInsert) {
                throw new Exception("Error preparando inserción en inventario: " . $conn->error);
            }
            
            $stmtInsert->bind_param("iii", $resultb['id'], $idEmpleadoi, $resultb['cantidad']);
            if (!$stmtInsert->execute()) {
                throw new Exception("Error insertando en inventario de empleado: " . $stmtInsert->error);
            }
        }

        // Actualizar existencia en productos
        $sqldevp = "UPDATE productos SET existencia = existencia + ? WHERE id = ?";
        $stmtdevp = $conn->prepare($sqldevp);
        if (!$stmtdevp) {
            throw new Exception("Error preparando actualización de existencia: " . $conn->error);
        }
        
        $stmtdevp->bind_param("ii", $resultb['cantidad'], $resultb['id']);
        if (!$stmtdevp->execute()) {
            throw new Exception("Error actualizando existencia del producto: " . $stmtdevp->error);
        }

        // Registrar transacción en inventario
        $stmtdevit = $conn->prepare("INSERT INTO inventariotransacciones (tipo, idProducto, cantidad, fecha, descripcion, idEmpleado) VALUES ('retorno', ?, ?, NOW(), 'Retorno por factura cancelada #".$numFactura."', ?)");
        if (!$stmtdevit) {
            throw new Exception("Error preparando registro de transacción de inventario: " . $conn->error);
        }

        $stmtdevit->bind_param('iii', $resultb['id'], $resultb['cantidad'], $_SESSION['idEmpleado']);
        if (!$stmtdevit->execute()) {
            throw new Exception("Error al registrar la transacción de inventario del producto ID: " . $resultb['id'] . " - " . $stmtdevit->error);
        }

        // Verificar si se realizó la inserción
        if ($stmtdevit->affected_rows === 0) {
            throw new Exception("No se registró la transacción de inventario para el producto ID: " . $resultb['id']);
        }
    }

    // Registrar auditoria de acciones de usuario

    $usuario = $_SESSION['idEmpleado'];
    $accion = 'Cancelacion de factura';
    $descripcion = "Motivos: " . $motivo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    registrarAuditoriaUsuarios($conn, $usuario, $accion, $descripcion, $ip);
    
    // Confirmar los cambios
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Factura cancelada correctamente',
        'caja_activa' => $cajaActiva
    ]);
    
} catch (Exception $e) {
    // Revertir los cambios en caso de error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Cerrar conexión
$conn->close();
?>