<?php
// Evitar cualquier output antes del PDF
ob_start();

require_once '../../../core/conexion.php';
require_once '../../../core/verificar-sesion.php';

// Validar permisos
require_once '../../../core/validar-permisos.php';
$permiso_necesario = 'PADM002';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    ob_end_clean();
    header('location: ../errors/403.html');
    exit(); 
}

// Limpiar cualquier output acumulado
ob_end_clean();

require_once '../../../libs/fpdf/fpdf.php';

// Obtener parámetros del período
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';
$fechaInicio = isset($_GET['fechaInicio']) ? $_GET['fechaInicio'] : date('Y-m-01');
$fechaFin = isset($_GET['fechaFin']) ? $_GET['fechaFin'] : date('Y-m-d');

// Función para convertir texto UTF-8 a ISO-8859-1
function convertirTexto($texto) {
    if (empty($texto)) return '';
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $texto);
}

// Función para obtener rango de fechas
function obtenerRangoFechas($periodo, $fechaInicio = null, $fechaFin = null) {
    if ($periodo === "personalizado" && $fechaInicio && $fechaFin) {
        return [
            "inicio" => $fechaInicio . " 00:00:00",
            "fin" => $fechaFin . " 23:59:59",
        ];
    }

    $hoy = date("Y-m-d");

    switch ($periodo) {
        case "hoy":
            return [
                "inicio" => $hoy . " 00:00:00",
                "fin" => $hoy . " 23:59:59",
            ];
        case "semana":
            $inicioSemana = date("Y-m-d", strtotime("monday this week"));
            return [
                "inicio" => $inicioSemana . " 00:00:00",
                "fin" => $hoy . " 23:59:59",
            ];
        case "mes":
            $inicioMes = date("Y-m-01");
            return [
                "inicio" => $inicioMes . " 00:00:00",
                "fin" => $hoy . " 23:59:59",
            ];
        case "ano":
            $inicioAno = date("Y-01-01");
            return [
                "inicio" => $inicioAno . " 00:00:00",
                "fin" => $hoy . " 23:59:59",
            ];
        default:
            return [
                "inicio" => date("Y-m-01") . " 00:00:00",
                "fin" => $hoy . " 23:59:59",
            ];
    }
}

$rango = obtenerRangoFechas($periodo, $fechaInicio, $fechaFin);

// Calcular título del período
switch($periodo) {
    case 'hoy':
        $tituloPeriodo = 'Hoy - ' . date('d/m/Y');
        break;
    case 'semana':
        $tituloPeriodo = 'Esta Semana';
        break;
    case 'mes':
        $tituloPeriodo = 'Este Mes - ' . date('F Y');
        break;
    case 'ano':
        $tituloPeriodo = 'Este Año - ' . date('Y');
        break;
    default:
        $tituloPeriodo = 'Del ' . date('d/m/Y', strtotime($rango['inicio'])) . ' al ' . date('d/m/Y', strtotime($rango['fin']));
}

// Clase PDF personalizada
class DashboardPDF extends FPDF {
    private $tituloPeriodo;
    
    function __construct($periodo) {
        parent::__construct();
        $this->tituloPeriodo = $periodo;
    }
    
    function Header() {
        // Logo (ajusta la ruta según tu estructura)
        if(file_exists('../assets/img/logo.png')) {
            $this->Image('../assets/img/logo.png', 10, 6, 30);
        }
        
        // Título
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(33, 37, 41);
        $this->Cell(80);
        $this->Cell(30, 10, 'REPORTE DE ESTADISTICAS', 0, 0, 'C');
        $this->Ln(7);
        
        // Período
        $this->SetFont('Arial', '', 12);
        $this->SetTextColor(108, 117, 125);
        $this->Cell(80);
        $this->Cell(30, 10, convertirTexto($this->tituloPeriodo), 0, 0, 'C');
        $this->Ln(10);
        
        // Fecha de generación
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, convertirTexto('Generado: ' . date('d/m/Y H:i:s')), 0, 0, 'R');
        $this->Ln(10);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, convertirTexto('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'C');
    }
    
    function SectionTitle($title, $icon = '') {
        $this->SetFont('Arial', 'B', 14);
        $this->SetFillColor(52, 58, 64);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, convertirTexto($icon . ' ' . $title), 0, 1, 'L', true);
        $this->Ln(3);
    }
    
    function KPICard($titulo, $valor, $subtitulo = '', $color = array(0, 123, 255)) {
        $this->SetFillColor($color[0], $color[1], $color[2]);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        
        // Título del KPI
        $this->Cell(90, 7, convertirTexto($titulo), 1, 0, 'L', true);
        $this->Ln();
        
        // Valor principal
        $this->SetFont('Arial', 'B', 14);
        $this->SetFillColor(248, 249, 250);
        $this->SetTextColor(33, 37, 41);
        $this->Cell(90, 10, convertirTexto($valor), 1, 0, 'C', true);
        $this->Ln();
        
        // Subtítulo
        if($subtitulo) {
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(108, 117, 125);
            $this->Cell(90, 5, convertirTexto($subtitulo), 1, 0, 'C', true);
            $this->Ln();
        }
    }
}

// Obtener datos KPIs
function obtenerKPIs($conn, $rango) {
    $kpis = array();
    
    try {
        // Ventas Totales
        $sql = "SELECT IFNULL(SUM(total), 0) AS total_ventas, COUNT(*) AS num_facturas
                FROM facturas
                WHERE fecha BETWEEN ? AND ?
                AND estado != 'Cancelada'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $rango['inicio'], $rango['fin']);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $kpis['ventas_total'] = $result['total_ventas'];
            $kpis['facturas_total'] = $result['num_facturas'];
            $stmt->close();
        }
    } catch (Exception $e) {
        $kpis['ventas_total'] = 0;
        $kpis['facturas_total'] = 0;
    }
    
    try {
        // Ganancias
        $sql = "SELECT IFNULL(SUM(ganancias), 0) as total_ganancias
                FROM facturas_detalles fd
                INNER JOIN facturas f ON fd.numFactura = f.numFactura
                WHERE f.fecha BETWEEN ? AND ?
                AND f.estado != 'Cancelada'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $rango['inicio'], $rango['fin']);
            $stmt->execute();
            $kpis['ganancias'] = $stmt->get_result()->fetch_assoc()['total_ganancias'];
            $stmt->close();
        }
    } catch (Exception $e) {
        $kpis['ganancias'] = 0;
    }
    
    try {
        // Facturas Pendientes
        $sql = "SELECT COUNT(*) as facturas_pendientes
                FROM facturas
                WHERE estado = 'Pendiente'";
        $result = $conn->query($sql);
        if ($result) {
            $kpis['facturas_pendientes'] = $result->fetch_assoc()['facturas_pendientes'];
        }
    } catch (Exception $e) {
        $kpis['facturas_pendientes'] = 0;
    }
    
    try {
        // Productos vendidos
        $sql = "SELECT IFNULL(SUM(cantidad), 0) as total_vendidos
                FROM facturas_detalles fd
                INNER JOIN facturas f ON fd.numFactura = f.numFactura
                WHERE f.fecha BETWEEN ? AND ?
                AND f.estado != 'Cancelada'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $rango['inicio'], $rango['fin']);
            $stmt->execute();
            $kpis['productos_vendidos'] = $stmt->get_result()->fetch_assoc()['total_vendidos'];
            $stmt->close();
        }
    } catch (Exception $e) {
        $kpis['productos_vendidos'] = 0;
    }
    
    try {
        // Productos bajo stock
        $sql = "SELECT COUNT(*) as bajo_stock
                FROM inventario i
                INNER JOIN productos p ON i.idProducto = p.id
                WHERE i.existencia <= p.reorden";
        $result = $conn->query($sql);
        if ($result) {
            $kpis['productos_bajo_stock'] = $result->fetch_assoc()['bajo_stock'];
        }
    } catch (Exception $e) {
        $kpis['productos_bajo_stock'] = 0;
    }
    
    try {
        // Clientes activos
        $sql = "SELECT COUNT(*) as total_clientes FROM clientes WHERE activo = 1";
        $result = $conn->query($sql);
        if ($result) {
            $kpis['clientes_activos'] = $result->fetch_assoc()['total_clientes'];
        }
    } catch (Exception $e) {
        $kpis['clientes_activos'] = 0;
    }
    
    try {
        // Clientes nuevos
        $sql = "SELECT COUNT(*) as clientes_nuevos
                FROM clientes
                WHERE fechaRegistro BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $rango['inicio'], $rango['fin']);
            $stmt->execute();
            $kpis['clientes_nuevos'] = $stmt->get_result()->fetch_assoc()['clientes_nuevos'];
            $stmt->close();
        }
    } catch (Exception $e) {
        $kpis['clientes_nuevos'] = 0;
    }
    
    try {
        // Por cobrar
        $sql = "SELECT IFNULL(SUM(balance), 0) as total_por_cobrar,
                COUNT(DISTINCT idCliente) as clientes_deuda
                FROM facturas 
                WHERE balance > 0 AND estado = 'Pendiente'";
        $result = $conn->query($sql);
        if ($result) {
            $data = $result->fetch_assoc();
            $kpis['por_cobrar'] = $data['total_por_cobrar'];
            $kpis['clientes_deuda'] = $data['clientes_deuda'];
        }
    } catch (Exception $e) {
        $kpis['por_cobrar'] = 0;
        $kpis['clientes_deuda'] = 0;
    }
    
    return $kpis;
}

// Obtener top clientes
function obtenerTopClientes($conn, $rango, $limit = 10) {
    try {
        $sql = "SELECT CONCAT(c.nombre, ' ', c.apellido) as nombre,
                IFNULL(SUM(f.total), 0) as total_compras,
                COUNT(f.registro) as num_facturas
                FROM facturas f
                INNER JOIN clientes c ON f.idCliente = c.id
                WHERE f.fecha BETWEEN ? AND ?
                AND f.estado != 'Cancelada'
                GROUP BY f.idCliente
                ORDER BY total_compras DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssi", $rango['inicio'], $rango['fin'], $limit);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $result;
        }
    } catch (Exception $e) {
        return array();
    }
    return array();
}

// Obtener productos con stock bajo
function obtenerStockBajo($conn) {
    try {
        $sql = "SELECT p.descripcion as nombre, i.existencia, p.reorden as punto_reorden
                FROM inventario i
                INNER JOIN productos p ON i.idProducto = p.id
                WHERE i.existencia <= p.reorden
                ORDER BY i.existencia ASC
                LIMIT 10";
        $result = $conn->query($sql);
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        return array();
    }
    return array();
}

// Obtener top productos
function obtenerTopProductos($conn, $rango, $limit = 10) {
    try {
        $sql = "SELECT p.descripcion as nombre,
                SUM(fd.cantidad) as cantidad_vendida,
                IFNULL(SUM(fd.total), 0) as total_ventas
                FROM facturas_detalles fd
                INNER JOIN facturas f ON fd.numFactura = f.numFactura
                INNER JOIN productos p ON fd.idProducto = p.id
                WHERE f.fecha BETWEEN ? AND ?
                AND f.estado != 'Cancelada'
                GROUP BY fd.idProducto
                ORDER BY cantidad_vendida DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssi", $rango['inicio'], $rango['fin'], $limit);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $result;
        }
    } catch (Exception $e) {
        return array();
    }
    return array();
}

// Obtener facturas pendientes
function obtenerFacturasPendientes($conn) {
    try {
        $sql = "SELECT f.numFactura,
                CONCAT(c.nombre, ' ', c.apellido) as cliente,
                f.fecha,
                f.total,
                f.balance,
                DATEDIFF(NOW(), f.fecha) as dias_vencido
                FROM facturas f
                INNER JOIN clientes c ON f.idCliente = c.id
                WHERE f.estado = 'Pendiente'
                AND f.balance > 0
                ORDER BY f.fecha ASC
                LIMIT 10";
        $result = $conn->query($sql);
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        return array();
    }
    return array();
}

// Crear PDF
$pdf = new DashboardPDF($tituloPeriodo);
$pdf->AliasNbPages();
$pdf->AddPage();

// Obtener datos
$kpis = obtenerKPIs($conn, $rango);

// Sección KPIs
$pdf->SectionTitle('INDICADORES PRINCIPALES', '>>');

$x = 10;
$y = $pdf->GetY();

// Primera fila de KPIs
$pdf->SetXY($x, $y);
$pdf->KPICard('Ventas Totales', '$' . number_format($kpis['ventas_total'], 2), '', array(0, 123, 255));

$pdf->SetXY($x + 100, $y);
$pdf->KPICard('Ganancias Netas', '$' . number_format($kpis['ganancias'], 2), '', array(40, 167, 69));

$y += 25;

// Segunda fila de KPIs
$pdf->SetXY($x, $y);
$pdf->KPICard('Facturas Generadas', $kpis['facturas_total'], $kpis['facturas_pendientes'] . ' pendientes', array(255, 193, 7));

$pdf->SetXY($x + 100, $y);
$pdf->KPICard('Productos Vendidos', $kpis['productos_vendidos'], $kpis['productos_bajo_stock'] . ' bajo stock', array(156, 39, 176));

$y += 28;

// Tercera fila de KPIs
$pdf->SetXY($x, $y);
$pdf->KPICard('Clientes Activos', $kpis['clientes_activos'], $kpis['clientes_nuevos'] . ' nuevos', array(23, 162, 184));

$pdf->SetXY($x + 100, $y);
$pdf->KPICard('Por Cobrar', '$' . number_format($kpis['por_cobrar'], 2), $kpis['clientes_deuda'] . ' clientes', array(220, 53, 69));

$pdf->Ln(35);

// Top Clientes
$topClientes = obtenerTopClientes($conn, $rango);
if(count($topClientes) > 0) {
    $pdf->SectionTitle('TOP 10 CLIENTES', '>>');
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(52, 58, 64);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
    $pdf->Cell(100, 7, 'Cliente', 1, 0, 'L', true);
    $pdf->Cell(40, 7, 'Total Compras', 1, 0, 'R', true);
    $pdf->Cell(40, 7, 'Facturas', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(33, 37, 41);
    $fill = false;
    $pos = 1;
    
    foreach($topClientes as $cliente) {
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->Cell(10, 6, $pos++, 1, 0, 'C', true);
        $pdf->Cell(100, 6, convertirTexto(substr($cliente['nombre'], 0, 45)), 1, 0, 'L', true);
        $pdf->Cell(40, 6, '$' . number_format($cliente['total_compras'], 2), 1, 0, 'R', true);
        $pdf->Cell(40, 6, $cliente['num_facturas'], 1, 1, 'C', true);
        $fill = !$fill;
    }
    $pdf->Ln(5);
}

// Top Productos
$topProductos = obtenerTopProductos($conn, $rango);
if(count($topProductos) > 0) {
    $pdf->SectionTitle('TOP 10 PRODUCTOS MAS VENDIDOS', '>>');
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(52, 58, 64);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
    $pdf->Cell(100, 7, 'Producto', 1, 0, 'L', true);
    $pdf->Cell(40, 7, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Total Ventas', 1, 1, 'R', true);
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(33, 37, 41);
    $fill = false;
    $pos = 1;
    
    foreach($topProductos as $producto) {
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->Cell(10, 6, $pos++, 1, 0, 'C', true);
        $pdf->Cell(100, 6, convertirTexto(substr($producto['nombre'], 0, 45)), 1, 0, 'L', true);
        $pdf->Cell(40, 6, $producto['cantidad_vendida'], 1, 0, 'C', true);
        $pdf->Cell(40, 6, '$' . number_format($producto['total_ventas'], 2), 1, 1, 'R', true);
        $fill = !$fill;
    }
    $pdf->Ln(5);
}

// Stock Bajo (Nueva página si es necesario)
$stockBajo = obtenerStockBajo($conn);
if(count($stockBajo) > 0) {
    if($pdf->GetY() > 200) {
        $pdf->AddPage();
    }
    
    $pdf->SectionTitle('PRODUCTOS CON STOCK BAJO', '>>');
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(220, 53, 69);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(120, 7, 'Producto', 1, 0, 'L', true);
    $pdf->Cell(35, 7, 'Existencia', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Punto Reorden', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(33, 37, 41);
    
    foreach($stockBajo as $producto) {
        $pdf->SetFillColor(255, 243, 205);
        $pdf->Cell(120, 6, convertirTexto(substr($producto['nombre'], 0, 50)), 1, 0, 'L', true);
        $pdf->Cell(35, 6, $producto['existencia'], 1, 0, 'C', true);
        $pdf->Cell(35, 6, $producto['punto_reorden'], 1, 1, 'C', true);
    }
    $pdf->Ln(5);
}

// Facturas Pendientes
$facturasPendientes = obtenerFacturasPendientes($conn);
if(count($facturasPendientes) > 0) {    
    if($pdf->GetY() > 180) {
        $pdf->AddPage();
    }
    
    $pdf->SectionTitle('FACTURAS PENDIENTES DE COBRO', '>>');
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(52, 58, 64);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(25, 7, 'Factura', 1, 0, 'C', true);
    $pdf->Cell(60, 7, 'Cliente', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'Fecha', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Balance', 1, 0, 'R', true);
    $pdf->Cell(40, 7, 'Dias Vencido', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(33, 37, 41);
    $fill = false;
    
    foreach($facturasPendientes as $factura) {
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $pdf->Cell(25, 6, $factura['numFactura'], 1, 0, 'C', true);
        $pdf->Cell(60, 6, convertirTexto(substr($factura['cliente'], 0, 30)), 1, 0, 'L', true);
        $pdf->Cell(30, 6, date('d/m/Y', strtotime($factura['fecha'])), 1, 0, 'C', true);
        $pdf->Cell(35, 6, '$' . number_format($factura['balance'], 2), 1, 0, 'R', true);
        $pdf->Cell(40, 6, $factura['dias_vencido'] . ' dias', 1, 1, 'C', true);
        $fill = !$fill;
    }
}

// Salida del PDF
$pdf->Output('I', 'Reporte_Dashboard_' . date('Y-m-d') . '.pdf');
?>