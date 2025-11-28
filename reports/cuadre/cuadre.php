<?php
require_once '../../core/verificar-sesion.php';
require_once '../../core/conexion.php';
require_once '../../libs/fpdf/fpdf.php';

$numCaja = '';
if (isset($_GET['numCaja'])) {
    if (preg_match('/^[a-zA-Z0-9]{5}$/', $_GET['numCaja'])) {
        $numCaja = $_GET['numCaja'];
    } else {
        die("Formato de número de caja inválido");
    }
}

// Obtener información de la caja
$sql_caja = "SELECT
                ce.numCaja AS numCaja, e.id AS idEmpleado,
                CONCAT(e.nombre,' ',e.apellido) AS nombreEmpleado,
                DATE_FORMAT(ce.fechaApertura, '%d/%m/%Y %l:%i %p') AS fechaApertura,
                DATE_FORMAT(ce.fechaCierre, '%d/%m/%Y %l:%i %p') AS fechaCierre,
                ce.saldoInicial AS saldoInicial, ce.saldoFinal AS saldoFinal,
                ce.diferencia AS diferencia, ce.estado AS estado
            FROM cajascerradas ce
            JOIN empleados e ON e.id = ce.idEmpleado
            WHERE ce.numCaja = ?;";
$stmt = $conn->prepare($sql_caja);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_caja = $stmt->get_result();
if ($result_caja->num_rows > 0) {
    $row_caja = $result_caja->fetch_assoc();
} else {
    die("No se encontró información para la caja especificada.");
}

// Obtener información de estado detalle
$row_estado_detalle = null;
$estado_lower = strtolower($row_caja['estado']);
if ($estado_lower === 'cerrada' || $estado_lower === 'cancelada') {
    $sql_estado_detalle = "SELECT
                            CONCAT(e.nombre, ' ', e.apellido) AS nombreEmpleadoAccion,
                            ced.nota AS notasAccion,
                            DATE_FORMAT(ced.fecha, '%d/%m/%Y %l:%i %p') AS fechaAccion
                        FROM caja_estado_detalle AS ced
                        LEFT JOIN empleados AS e ON e.id = ced.id_empleado
                        WHERE ced.numCaja = ?
                        ORDER BY ced.fecha DESC LIMIT 1;";
    $stmt_detalle = $conn->prepare($sql_estado_detalle);
    $stmt_detalle->bind_param("s", $numCaja);
    $stmt_detalle->execute();
    $result_detalle = $stmt_detalle->get_result();
    if ($result_detalle->num_rows > 0) {
        $row_estado_detalle = $result_detalle->fetch_assoc();
    }
}

// Obtener resumen de ingresos
$sql_ingresos = "WITH
                    fm AS (
                        SELECT metodo, monto FROM facturas_metodopago JOIN facturas ON facturas.numFactura = facturas_metodopago.numFactura WHERE facturas_metodopago.noCaja = ? AND facturas.estado != 'Cancelada'
                    ),
                    ch AS (
                        SELECT metodo, monto FROM clientes_historialpagos WHERE numCaja = ?
                    )
                    SELECT
                        COALESCE((SELECT SUM(monto) FROM fm WHERE metodo = 'efectivo'), 0) AS Fefectivo,
                        COALESCE((SELECT SUM(monto) FROM fm WHERE metodo = 'transferencia'), 0) AS Ftransferencia,
                        COALESCE((SELECT SUM(monto) FROM fm WHERE metodo = 'tarjeta'), 0) AS Ftarjeta,
                        COALESCE((SELECT SUM(monto) FROM ch WHERE metodo = 'efectivo'), 0) AS CPefectivo,
                        COALESCE((SELECT SUM(monto) FROM ch WHERE metodo = 'transferencia'), 0) AS CPtransferencia,
                        COALESCE((SELECT SUM(monto) FROM ch WHERE metodo = 'tarjeta'), 0) AS CPtarjeta;";
$stmt = $conn->prepare($sql_ingresos);
$stmt->bind_param("ss", $numCaja, $numCaja);
$stmt->execute();
$result_ingresos = $stmt->get_result();
$row_ingresos = $result_ingresos->fetch_assoc();

$ItotalE = $row_ingresos['Fefectivo'] + $row_ingresos['CPefectivo'];
$ItotalT = $row_ingresos['Ftransferencia'] + $row_ingresos['CPtransferencia'];
$ItotalC = $row_ingresos['Ftarjeta'] + $row_ingresos['CPtarjeta'];

// Obtener facturas a contado
$sql_FacturasContado = "SELECT f.numFactura AS noFac, DATE_FORMAT(f.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                            CONCAT(c.nombre,' ',c.apellido) AS nombrec, fm.metodo, fm.monto
                        FROM facturas f
                        JOIN clientes c ON c.id = f.idCliente
                        JOIN facturas_metodopago fm ON fm.numFactura = f.numFactura AND fm.noCaja = ?
                        WHERE f.tipoFactura = 'contado' AND f.estado != 'Cancelada';";
$stmt = $conn->prepare($sql_FacturasContado);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_FacturasContado = $stmt->get_result();

// Obtener facturas a crédito
$sql_FacturasCredito = "SELECT f.numFactura AS noFac, DATE_FORMAT(f.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                            CONCAT(c.nombre,' ',c.apellido) AS nombrec, fm.metodo, fm.monto
                        FROM facturas f
                        JOIN clientes c ON c.id = f.idCliente
                        JOIN facturas_metodopago fm ON fm.numFactura = f.numFactura AND fm.noCaja = ?
                        WHERE f.tipoFactura = 'credito' AND f.estado != 'Cancelada';";
$stmt = $conn->prepare($sql_FacturasCredito);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_FacturasCredito = $stmt->get_result();

// Obtener pagos de clientes
$sql_pagos = "SELECT ch.registro AS id, DATE_FORMAT(ch.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                CONCAT(c.nombre,' ',c.apellido) AS nombre, ch.metodo AS metodo, ch.monto AS monto
            FROM clientes_historialpagos ch
            JOIN clientes c ON c.id = ch.idCliente
            WHERE ch.numCaja = ?;";
$stmt = $conn->prepare($sql_pagos);
$stmt->bind_param("s", $numCaja);
$stmt->execute();
$result_pagos = $stmt->get_result();

// Crear PDF
class PDF extends FPDF
{
    private $empresa = 'EasyPOS';
    private $numCaja;
    private $estado;
    
    function SetCajaInfo($numCaja, $estado) {
        $this->numCaja = $numCaja;
        $this->estado = $estado;
    }
    
    function Header()
    {
        // Logo (ajusta la ruta según tu estructura)
        // $this->Image('../../assets/img/logo.png', 10, 6, 30);
        
        // Título
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, $this->empresa, 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Reporte de Cuadre de Caja', 0, 1, 'C');
        $this->Cell(0, 5, 'Caja #' . $this->numCaja . ' - Estado: ' . strtoupper($this->estado), 0, 1, 'C');
        $this->Ln(5);
        
        // Línea
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . ' - Generado el ' . date('d/m/Y H:i:s'), 0, 0, 'C');
    }
    
    function SectionTitle($title)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(44, 62, 80);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 8, $title, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(3);
    }
    
    function InfoBox($label, $value, $width = 95)
    {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($width, 6, $label . ':', 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, $value, 0, 1);
    }
    
    function TableHeader($headers, $widths)
    {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(241, 242, 246);
        $this->SetTextColor(44, 62, 80);
        foreach ($headers as $i => $header) {
            $this->Cell($widths[$i], 7, $header, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
    }
    
    function TableRow($data, $widths, $aligns = [])
    {
        $this->SetFont('Arial', '', 8);
        foreach ($data as $i => $item) {
            $align = isset($aligns[$i]) ? $aligns[$i] : 'L';
            $this->Cell($widths[$i], 6, $item, 1, 0, $align);
        }
        $this->Ln();
    }
    
    function TableFooter($label, $value, $widths, $colspan)
    {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(241, 242, 246);
        $totalWidth = 0;
        for ($i = 0; $i < $colspan - 1; $i++) {
            $totalWidth += $widths[$i];
        }
        $this->Cell($totalWidth, 6, $label, 1, 0, 'R', true);
        $this->Cell($widths[$colspan - 1], 6, $value, 1, 1, 'R', true);
    }
}

$pdf = new PDF();
$pdf->SetCajaInfo($numCaja, $row_caja['estado']);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Información General
$pdf->SectionTitle('INFORMACION GENERAL');
$pdf->InfoBox('Numero de Caja', $row_caja['numCaja']);
$pdf->InfoBox('ID Empleado', $row_caja['idEmpleado']);
$pdf->InfoBox('Empleado', $row_caja['nombreEmpleado']);
$pdf->InfoBox('Fecha de Apertura', $row_caja['fechaApertura']);
$pdf->InfoBox('Fecha de Cierre', $row_caja['fechaCierre']);
$pdf->Ln(3);

// Información de Cierre/Cancelación si existe
if ($row_estado_detalle) {
    $titulo_estado = ($estado_lower === 'cerrada') ? 'INFORMACION DE CIERRE' : 'INFORMACION DE CANCELACION';
    $pdf->SectionTitle($titulo_estado);
    $pdf->InfoBox(($estado_lower === 'cerrada') ? 'Cerrado por' : 'Cancelado por', 
                  $row_estado_detalle['nombreEmpleadoAccion'] ?? 'No disponible');
    $pdf->InfoBox('Fecha de ' . $estado_lower, 
                  $row_estado_detalle['fechaAccion'] ?? 'No disponible');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'Nota / Observacion:', 0, 1);
    $pdf->SetFont('Arial', '', 8);
    $pdf->MultiCell(0, 5, $row_estado_detalle['notasAccion'] ?? 'Sin notas');
    $pdf->Ln(3);
}

// Resumen de Saldos
$pdf->SectionTitle('RESUMEN DE SALDOS');
$pdf->SetFont('Arial', 'B', 10);
$colorPositive = ($row_caja['saldoInicial'] >= 0) ? [39, 174, 96] : [231, 76, 60];
$pdf->SetTextColor($colorPositive[0], $colorPositive[1], $colorPositive[2]);
$pdf->Cell(63, 8, 'Saldo Inicial: $' . number_format($row_caja['saldoInicial'], 2), 1, 0, 'C');
$pdf->SetTextColor(0, 0, 0);

$colorFinal = ($row_caja['saldoFinal'] >= 0) ? [39, 174, 96] : [231, 76, 60];
$pdf->SetTextColor($colorFinal[0], $colorFinal[1], $colorFinal[2]);
$pdf->Cell(63, 8, 'Saldo Final: $' . number_format($row_caja['saldoFinal'], 2), 1, 0, 'C');
$pdf->SetTextColor(0, 0, 0);

$colorDif = ($row_caja['diferencia'] >= 0) ? [39, 174, 96] : [231, 76, 60];
$pdf->SetTextColor($colorDif[0], $colorDif[1], $colorDif[2]);
$pdf->Cell(64, 8, 'Diferencia: $' . number_format($row_caja['diferencia'], 2), 1, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(5);

// Resumen de Ingresos
$pdf->SectionTitle('RESUMEN DE INGRESOS');
$widths_ingresos = [70, 40, 40, 40];
$pdf->TableHeader(['Descripcion', 'Efectivo', 'Transferencia', 'Tarjeta'], $widths_ingresos);
$pdf->TableRow(['Facturas', '$'.number_format($row_ingresos['Fefectivo'], 2), 
                '$'.number_format($row_ingresos['Ftransferencia'], 2), 
                '$'.number_format($row_ingresos['Ftarjeta'], 2)], $widths_ingresos, ['L','R','R','R']);
$pdf->TableRow(['Pagos de Clientes', '$'.number_format($row_ingresos['CPefectivo'], 2), 
                '$'.number_format($row_ingresos['CPtransferencia'], 2), 
                '$'.number_format($row_ingresos['CPtarjeta'], 2)], $widths_ingresos, ['L','R','R','R']);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(241, 242, 246);
$pdf->Cell($widths_ingresos[0], 6, 'TOTAL', 1, 0, 'R', true);
$pdf->Cell($widths_ingresos[1], 6, '$'.number_format($ItotalE, 2), 1, 0, 'R', true);
$pdf->Cell($widths_ingresos[2], 6, '$'.number_format($ItotalT, 2), 1, 0, 'R', true);
$pdf->Cell($widths_ingresos[3], 6, '$'.number_format($ItotalC, 2), 1, 1, 'R', true);
$pdf->Ln(5);

// Facturas a Contado
$pdf->SectionTitle('FACTURAS A CONTADO');
$widths_facturas = [25, 40, 60, 30, 35];
$pdf->TableHeader(['No. Factura', 'Fecha', 'Cliente', 'Metodo', 'Monto'], $widths_facturas);

$totalEfectivo = 0; $totalTransferencia = 0; $totalTarjeta = 0;
if ($result_FacturasContado->num_rows > 0) {
    while ($row = $result_FacturasContado->fetch_assoc()) {
        $pdf->TableRow([
            $row['noFac'],
            $row['fecha'],
            substr($row['nombrec'], 0, 25),
            ucfirst($row['metodo']),
            '$'.number_format($row['monto'], 2)
        ], $widths_facturas, ['C','L','L','C','R']);
        
        if($row['metodo'] == 'efectivo') $totalEfectivo += $row['monto'];
        elseif($row['metodo'] == 'transferencia') $totalTransferencia += $row['monto'];
        elseif($row['metodo'] == 'tarjeta') $totalTarjeta += $row['monto'];
    }
    $pdf->TableFooter('Total Efectivo:', '$'.number_format($totalEfectivo, 2), $widths_facturas, 5);
    $pdf->TableFooter('Total Transferencia:', '$'.number_format($totalTransferencia, 2), $widths_facturas, 5);
    $pdf->TableFooter('Total Tarjeta:', '$'.number_format($totalTarjeta, 2), $widths_facturas, 5);
} else {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(array_sum($widths_facturas), 6, 'No hay facturas a contado', 1, 1, 'C');
}
$pdf->Ln(5);

// Facturas a Crédito
$pdf->SectionTitle('FACTURAS A CREDITO');
$pdf->TableHeader(['No. Factura', 'Fecha', 'Cliente', 'Metodo', 'Monto'], $widths_facturas);

$totalEfectivoCredito = 0; $totalTransferenciaCredito = 0; $totalTarjetaCredito = 0;
if ($result_FacturasCredito->num_rows > 0) {
    while ($row = $result_FacturasCredito->fetch_assoc()) {
        $pdf->TableRow([
            $row['noFac'],
            $row['fecha'],
            substr($row['nombrec'], 0, 25),
            ucfirst($row['metodo']),
            '$'.number_format($row['monto'], 2)
        ], $widths_facturas, ['C','L','L','C','R']);
        
        if($row['metodo'] == 'efectivo') $totalEfectivoCredito += $row['monto'];
        elseif($row['metodo'] == 'transferencia') $totalTransferenciaCredito += $row['monto'];
        elseif($row['metodo'] == 'tarjeta') $totalTarjetaCredito += $row['monto'];
    }
    $pdf->TableFooter('Total Efectivo:', '$'.number_format($totalEfectivoCredito, 2), $widths_facturas, 5);
    $pdf->TableFooter('Total Transferencia:', '$'.number_format($totalTransferenciaCredito, 2), $widths_facturas, 5);
    $pdf->TableFooter('Total Tarjeta:', '$'.number_format($totalTarjetaCredito, 2), $widths_facturas, 5);
} else {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(array_sum($widths_facturas), 6, 'No hay facturas a credito', 1, 1, 'C');
}
$pdf->Ln(5);

// Pagos de Clientes
$pdf->SectionTitle('PAGOS DE CLIENTES');
$pdf->TableHeader(['No. Pago', 'Fecha', 'Cliente', 'Metodo', 'Monto'], $widths_facturas);

$totalEfectivoPagos = 0; $totalTransferenciaPagos = 0; $totalTarjetaPagos = 0;
if ($result_pagos->num_rows > 0) {
    while ($row = $result_pagos->fetch_assoc()) {
        $pdf->TableRow([
            $row['id'],
            $row['fecha'],
            substr($row['nombre'], 0, 25),
            ucfirst($row['metodo']),
            '$'.number_format($row['monto'], 2)
        ], $widths_facturas, ['C','L','L','C','R']);
        
        if($row['metodo'] == 'efectivo') $totalEfectivoPagos += $row['monto'];
        elseif($row['metodo'] == 'transferencia') $totalTransferenciaPagos += $row['monto'];
        elseif($row['metodo'] == 'tarjeta') $totalTarjetaPagos += $row['monto'];
    }
    $pdf->TableFooter('Total Efectivo:', '$'.number_format($totalEfectivoPagos, 2), $widths_facturas, 5);
    $pdf->TableFooter('Total Transferencia:', '$'.number_format($totalTransferenciaPagos, 2), $widths_facturas, 5);
    $pdf->TableFooter('Total Tarjeta:', '$'.number_format($totalTarjetaPagos, 2), $widths_facturas, 5);
} else {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(array_sum($widths_facturas), 6, 'No hay pagos de clientes', 1, 1, 'C');
}

// Firmas

$pdf->SetFont('Arial', '', 9);

// Firma empleado que genera el reporte
$pdf->Ln(8);
$pdf->Cell(80, 6, '_______________________________', 0, 0, 'C');
$pdf->Cell(30, 6, '', 0, 0); 
$pdf->Cell(80, 6, '_______________________________', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(80, 6, $row_caja['nombreEmpleado'], 0, 0, 'C');  
$pdf->Cell(30, 6, '', 0, 0);
$pdf->Cell(80, 6, $_SESSION['nombre'], 0, 1, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(80, 5, 'Empleado', 0, 0, 'C');
$pdf->Cell(30, 5, '', 0, 0);
$pdf->Cell(80, 5, 'Encargado / Autorizador', 0, 1, 'C');


$pdf->Output('I', 'Cuadre_Caja_'.$numCaja.'_'.date('YmdHis').'.pdf');
?>