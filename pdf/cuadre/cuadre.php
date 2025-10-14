<?php
require('../../libs/fpdf/fpdf.php');

class CajaReporte extends FPDF {
    // Page header
    function Header() {
        // Logo (if available)
        // $this->Image('logo.png', 10, 6, 30);
        
        // Company name
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'EasyPOS', 0, 1, 'L');
        
        // Title
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'Detalles de Cuadre', 0, 1, 'C');
        
        // Line break
        $this->Ln(5);
    }

    // Page footer
    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Database connection
require ('../../models/conexion.php');

// Ensure database connection is UTF-8
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8");
}

// Get cash register information
function obtenerInfoCaja($conn, $numCaja) {
    $cajaInfo = [];
    
    // Get basic cash register info
    $sql = "SELECT
                c.numCaja,
                c.fechaApertura,
                c.fechaCierre,
                c.saldoInicial,
                c.saldoFinal,
                c.idEmpleado AS empleadoID,
                c.diferencia,
                CONCAT(e.nombre,' ',e.apellido) AS nombreEmpleado
            FROM
                cajascerradas c
            JOIN empleados e ON
                c.idEmpleado = e.id
            WHERE 
                c.numCaja = ?";
                
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $numCaja);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $cajaInfo = $result->fetch_assoc();
    } else {
        die("No se encontró información para la caja #" . $numCaja);
    }
    
    // Get totals
    $sqlIngresos = "SELECT
                        SUM(monto) AS totalIngresos
                    FROM
                        cajaingresos
                    WHERE
                        numCaja = ?";
    $stmtIngresos = $conn->prepare($sqlIngresos);
    $stmtIngresos->bind_param("s", $numCaja);
    $stmtIngresos->execute();
    $resultIngresos = $stmtIngresos->get_result();
    $rowIngresos = $resultIngresos->fetch_assoc();
    $cajaInfo['totalIngresos'] = $rowIngresos['totalIngresos'] ?: 0;
    
    $sqlEgresos = "SELECT
                        SUM(monto) AS totalEgresos
                    FROM
                        cajaegresos
                    WHERE
                        numCaja = ?";
    $stmtEgresos = $conn->prepare($sqlEgresos);
    $stmtEgresos->bind_param("s", $numCaja);
    $stmtEgresos->execute();
    $resultEgresos = $stmtEgresos->get_result();
    $rowEgresos = $resultEgresos->fetch_assoc();
    $cajaInfo['totalEgresos'] = $rowEgresos['totalEgresos'] ?: 0;
    
    return $cajaInfo;
}

// Get income details
function obtenerIngresos($conn, $numCaja) {
    $ingresos = [];
    
    $sql = "SELECT
                c.monto,
                c.metodo,
                c.razon AS descripcion,
                DATE_FORMAT(c.fecha, '%d/%m/%Y %l:%i %p') AS fecha
            FROM
                cajaingresos c
            WHERE
                numCaja = ?
            ORDER BY
                fecha ASC";
                
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $numCaja);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $ingresos[] = $row;
    }
    
    return $ingresos;
}

// Get expense details
function obtenerEgresos($conn, $numCaja) {
    $egresos = [];
    
    $sql = "SELECT
                c.monto,
                c.metodo,
                c.razon AS descripcion,
                DATE_FORMAT(c.fecha, '%d/%m/%Y %l:%i %p') AS fecha
            FROM
                cajaegresos c
            WHERE
                numCaja = ?
            ORDER BY
                fecha ASC";
                
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $numCaja);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $egresos[] = $row;
    }
    
    return $egresos;
}

// Get the cash register number from URL parameter
$numCaja = isset($_GET['numCaja']) ? $_GET['numCaja'] : '';

if (empty($numCaja)) {
    die("Número de caja no especificado");
}

// Get data
$cajaInfo = obtenerInfoCaja($conn, $numCaja);
$ingresos = obtenerIngresos($conn, $numCaja);
$egresos = obtenerEgresos($conn, $numCaja);

// Close connection
$conn->close();

// Format values for display
$fechaActual = date('d/m/Y H:i:s');
$numIngresos = count($ingresos);
$numEgresos = count($egresos);

// Create new PDF document
$pdf = new CajaReporte('P', 'mm', 'Letter');

// Set document information
$pdf->SetTitle('Reporte de Caja #' . $numCaja);
$pdf->SetAuthor('Sistema EasyPOS');
$pdf->SetCreator('FPDF');

// Set default monospaced font
$pdf->SetFont('Arial', '', 10);

// Add a page
$pdf->AddPage();
$pdf->AliasNbPages();

// --------- Header information ---------
// Cash register number
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Caja: ' . $numCaja, 0, 1, 'R');

// Current date and employee
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Fecha Actual: ' . $fechaActual, 0, 1, 'R');
$pdf->Cell(0, 6, 'Empleado: ' . utf8_decode($cajaInfo['nombreEmpleado']), 0, 1, 'R');
$pdf->Ln(5);

// --------- Cash register information ---------
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('Información de Caja'), 0, 1, 'L');

$pdf->SetFont('Arial', '', 10);
// Opening and closing dates
$pdf->Cell(90, 8, 'Fecha Apertura: ' . date('d/m/Y h:i A', strtotime($cajaInfo['fechaApertura'])), 0, 0);
$pdf->Cell(90, 8, 'Fecha Cierre: ' . date('d/m/Y h:i A', strtotime($cajaInfo['fechaCierre'])), 0, 1);

// Initial and final balance
$pdf->Cell(90, 8, 'Saldo Inicial: $' . number_format($cajaInfo['saldoInicial'], 2), 0, 0);
$pdf->Cell(90, 8, 'Saldo Final: $' . number_format($cajaInfo['saldoFinal'], 2), 0, 1);
$pdf->Ln(5);

// --------- Financial summary ---------
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Resumen Financiero', 0, 1, 'L');

$pdf->SetFont('Arial', '', 10);
// Total income and expenses
$pdf->Cell(90, 8, 'Total Ingresos: $' . number_format($cajaInfo['totalIngresos'], 2), 0, 0);
$pdf->Cell(90, 8, 'Total Egresos: $' . number_format($cajaInfo['totalEgresos'], 2), 0, 1);

// Difference
$pdf->SetFont('Arial', 'B', 10);
$diferencia = $cajaInfo['diferencia'];
$diferenciaTexto = '$' . number_format(abs($diferencia), 2);
if ($diferencia < 0) {
    $diferenciaTexto = '-' . $diferenciaTexto . ' (En contra)';
} else {
    $diferenciaTexto = '+' . $diferenciaTexto . ' (A favor)';
}
$pdf->Cell(0, 8, 'Diferencia: ' . $diferenciaTexto, 0, 1, 'R');
$pdf->Ln(5);

// --------- Income details ---------
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Ingresos (' . $numIngresos . ' registros)', 0, 1, 'L');

// Table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(40, 8, 'Monto', 1, 0, 'C', true);
$pdf->Cell(40, 8, utf8_decode('Método'), 1, 0, 'C', true);
$pdf->Cell(60, 8, utf8_decode('Descripción'), 1, 0, 'C', true);
$pdf->Cell(50, 8, 'Fecha', 1, 1, 'C', true);

// Table data
$pdf->SetFont('Arial', '', 10);
if ($numIngresos > 0) {
    foreach ($ingresos as $ingreso) {
        $pdf->Cell(40, 8, '$' . number_format($ingreso['monto'], 2), 1, 0, 'R');
        $pdf->Cell(40, 8, utf8_decode($ingreso['metodo']), 1, 0, 'L');
        $pdf->Cell(60, 8, utf8_decode($ingreso['descripcion']), 1, 0, 'L');
        $pdf->Cell(50, 8, $ingreso['fecha'], 1, 1, 'L');
    }
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(190, 8, 'No hay registros de ingresos', 1, 1, 'C');
}
$pdf->Ln(5);

// --------- Expense details ---------
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Egresos (' . $numEgresos . ' registros)', 0, 1, 'L');

// Table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(40, 8, 'Monto', 1, 0, 'C', true);
$pdf->Cell(40, 8, utf8_decode('Método'), 1, 0, 'C', true);
$pdf->Cell(60, 8, utf8_decode('Descripción'), 1, 0, 'C', true);
$pdf->Cell(50, 8, 'Fecha', 1, 1, 'C', true);

// Table data
$pdf->SetFont('Arial', '', 10);
if ($numEgresos > 0) {
    foreach ($egresos as $egreso) {
        $pdf->Cell(40, 8, '$' . number_format($egreso['monto'], 2), 1, 0, 'R');
        $pdf->Cell(40, 8, utf8_decode($egreso['metodo']), 1, 0, 'L');
        $pdf->Cell(60, 8, utf8_decode($egreso['descripcion']), 1, 0, 'L');
        $pdf->Cell(50, 8, $egreso['fecha'], 1, 1, 'L');
    }
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(190, 8, 'No hay registros de egresos', 1, 1, 'C');
}
$pdf->Ln(10);

// --------- Signatures ---------
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 10, '_______________________', 0, 0, 'C');
$pdf->Cell(95, 10, '_______________________', 0, 1, 'C');
$pdf->Cell(95, 6, 'Firma del Cajero', 0, 0, 'C');
$pdf->Cell(95, 6, 'Firma del Supervisor', 0, 1, 'C');

// Output the PDF to browser
$pdf->Output('I', 'reporte_caja_' . $numCaja . '.pdf');
?>