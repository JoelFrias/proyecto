<?php
session_start();
require('../../libs/fpdf/fpdf.php');
require('../../core/conexion.php');

// Verificar que se recibió el número de cotización
if (!isset($_GET['cotizacion']) || empty($_GET['cotizacion'])) {
    die('Número de cotización no proporcionado');
}

$noCotizacion = $conn->real_escape_string($_GET['cotizacion']);

// Obtener información de la cotización
$sqlCotizacion = "SELECT
                    ci.no,
                    DATE_FORMAT(ci.fecha, '%d/%m/%Y') AS fecha,
                    DATE_FORMAT(ci.fecha, '%h:%i %p') AS hora,
                    ci.id_cliente,
                    CONCAT(c.nombre, ' ', c.apellido) AS nombreCliente,
                    c.telefono AS telefonoCliente,
                    c.empresa AS empresaCliente,
                    CONCAT(dc.no,', ', dc.calle, ', ', dc.sector, ', ', dc.ciudad, ', (Referencia: ',dc.referencia, ')') AS direccionCliente,
                    ci.subtotal,
                    ci.descuento,
                    ci.total,
                    ci.notas,
                    CONCAT(e.nombre, ' ', e.apellido) AS nombreEmpleado,
                    ci.estado AS estado
                FROM
                    cotizaciones_inf AS ci
                INNER JOIN clientes AS c
                ON
                    c.id = ci.id_cliente
                INNER JOIN empleados AS e
                ON
                    e.id = ci.id_empleado
                INNER JOIN clientes_direcciones AS dc
                ON
                    ci.id_cliente = dc.idCliente
                WHERE
                    ci.no = ?";

$stmtCotizacion = $conn->prepare($sqlCotizacion);
$stmtCotizacion->bind_param('s', $noCotizacion);
$stmtCotizacion->execute();
$resultCotizacion = $stmtCotizacion->get_result();

if ($resultCotizacion->num_rows === 0) {
    die('Cotización no encontrada');
}

$cotizacion = $resultCotizacion->fetch_assoc();

// Obtener productos de la cotización
$sqlProductos = "SELECT 
                    cd.id_producto,
                    p.descripcion,
                    cd.cantidad,
                    cd.precio_s,
                    (cd.cantidad * cd.precio_s) AS subtotal
                FROM cotizaciones_det AS cd
                INNER JOIN productos AS p ON p.id = cd.id_producto
                WHERE cd.no = ?
                ORDER BY cd.registro ASC";

$stmtProductos = $conn->prepare($sqlProductos);
$stmtProductos->bind_param('s', $noCotizacion);
$stmtProductos->execute();
$resultProductos = $stmtProductos->get_result();

$productos = [];
while ($row = $resultProductos->fetch_assoc()) {
    $productos[] = $row;
}

// Crear PDF con marca de agua
class PDF extends FPDF
{
    function Header()
    {
        // Logo
        if (file_exists('../../assets/img/logo.png')) {
            $this->Image('../../assets/img/logo.png', 10, 10, 40);
        }
        
        // Información de la empresa
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(33, 37, 41);
        $this->Cell(0, 8, utf8_decode('EasyPOS'), 0, 1, 'R');
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(108, 117, 125);
        $this->Cell(0, 5, utf8_decode(''), 0, 1, 'R');
        $this->Cell(0, 5, utf8_decode('Teléfono: (809) 727-6431'), 0, 1, 'R');
        $this->Cell(0, 5, utf8_decode('Email: fjoelfrias@gmail.com'), 0, 1, 'R');
        
        $this->Ln(5);
        
        // Agregar marca de agua
        $this->Watermark();
    }
    
    function Watermark()
    {
        $this->SetFont('Arial', 'B', 60);
        $this->SetTextColor(128, 128, 128);
        $this->RotatedText(70, 190, 'DUPLICADO', 45);
    }
    
    function RotatedText($x, $y, $txt, $angle)
    {
        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }
    
    var $angle = 0;
    
    function Rotate($angle, $x = -1, $y = -1)
    {
        if($x == -1)
            $x = $this->x;
        if($y == -1)
            $y = $this->y;
        if($this->angle != 0)
            $this->_out('Q');
        $this->angle = $angle;
        if($angle != 0)
        {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }
    
    function _endpage()
    {
        if($this->angle != 0)
        {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'C');
    }
    
    function TablaProductos($header, $data)
    {
        $this->SetFillColor(37, 99, 235);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(.3);
        $this->SetFont('Arial', 'B', 10);
        
        $w = array(15, 95, 25, 30, 30);
        
        for($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        
        $this->SetFillColor(248, 249, 250);
        $this->SetTextColor(33, 37, 41);
        $this->SetFont('Arial', '', 9);
        
        $fill = false;
        foreach($data as $row) {
            $this->Cell($w[0], 6, $row[0], 'LR', 0, 'C', $fill);
            $this->Cell($w[1], 6, utf8_decode($row[1]), 'LR', 0, 'L', $fill);
            $this->Cell($w[2], 6, number_format($row[2], 2), 'LR', 0, 'C', $fill);
            $this->Cell($w[3], 6, 'RD$ ' . number_format($row[3], 2), 'LR', 0, 'R', $fill);
            $this->Cell($w[4], 6, 'RD$ ' . number_format($row[4], 2), 'LR', 0, 'R', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

// Instanciar PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// Título principal
$pdf->SetFont('Arial', 'B', 20);
$pdf->SetTextColor(37, 99, 235);
$pdf->Cell(0, 10, utf8_decode('PRE-FACTURA / COTIZACIÓN'), 0, 1, 'C');
$pdf->Ln(3);

// Línea divisora
$pdf->SetDrawColor(37, 99, 235);
$pdf->SetLineWidth(0.5);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(5);

// Información de la cotización y cliente
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(33, 37, 41);

$y_start = $pdf->GetY();
$pdf->Cell(95, 6, utf8_decode('INFORMACIÓN DE COTIZACIÓN'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(73, 80, 87);

$pdf->Cell(35, 5, utf8_decode('Número:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, $cotizacion['no'], 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(35, 5, utf8_decode('Fecha:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, $cotizacion['fecha'] . ' - ' . $cotizacion['hora'], 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(35, 5, utf8_decode('Atendido por:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, utf8_decode($cotizacion['nombreEmpleado']), 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(35, 5, utf8_decode('Estado:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, utf8_decode($cotizacion['estado']), 0, 1);

$pdf->SetY($y_start);
$pdf->SetX(110);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(33, 37, 41);
$pdf->Cell(95, 6, utf8_decode('INFORMACIÓN DEL CLIENTE'), 0, 1);

$pdf->SetX(110);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(73, 80, 87);
$pdf->Cell(30, 5, utf8_decode('Cliente:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, utf8_decode($cotizacion['nombreCliente']), 0, 1);

$pdf->SetX(110);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(30, 5, utf8_decode('Empresa:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, utf8_decode($cotizacion['empresaCliente']), 0, 1);

$pdf->SetX(110);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(30, 5, utf8_decode('Teléfono:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, $cotizacion['telefonoCliente'], 0, 1);

$pdf->SetX(110);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(30, 5, utf8_decode('Dirección:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->MultiCell(60, 5, utf8_decode($cotizacion['direccionCliente']), 0, 'L');

$pdf->Ln(5);

// Tabla de productos
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(33, 37, 41);
$pdf->Cell(0, 6, utf8_decode('DETALLE DE PRODUCTOS'), 0, 1);
$pdf->Ln(2);

$header = array('#', utf8_decode('Descripción'), 'Cant.', 'Precio Unit.', 'Subtotal');
$data = array();
$contador = 1;

foreach ($productos as $producto) {
    $data[] = array(
        $contador++,
        $producto['descripcion'],
        $producto['cantidad'],
        $producto['precio_s'],
        $producto['subtotal']
    );
}

$pdf->TablaProductos($header, $data);
$pdf->Ln(5);

// Totales
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(33, 37, 41);

$pdf->Cell(135, 6, '', 0, 0);
$pdf->Cell(30, 6, 'Subtotal:', 1, 0, 'R');
$pdf->Cell(30, 6, 'RD$ ' . number_format($cotizacion['subtotal'], 2), 1, 1, 'R');

if ($cotizacion['descuento'] > 0) {
    $pdf->Cell(135, 6, '', 0, 0);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->Cell(30, 6, 'Descuento:', 1, 0, 'R');
    $pdf->Cell(30, 6, '- RD$ ' . number_format($cotizacion['descuento'], 2), 1, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(37, 99, 235);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(135, 8, '', 0, 0);
$pdf->Cell(30, 8, 'TOTAL:', 1, 0, 'R', true);
$pdf->Cell(30, 8, 'RD$ ' . number_format($cotizacion['total'], 2), 1, 1, 'R', true);

// Notas
if (!empty($cotizacion['notas'])) {
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->Cell(0, 6, 'NOTAS:', 0, 1);
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(73, 80, 87);
    $pdf->SetFillColor(248, 249, 250);
    $pdf->MultiCell(0, 5, utf8_decode($cotizacion['notas']), 1, 'L', true);
}

// Información adicional
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(150, 150, 150);
$pdf->MultiCell(0, 4, utf8_decode('Esta cotización tiene una validez de 30 días a partir de la fecha de emisión. Los precios están sujetos a cambios sin previo aviso. Para hacer efectiva esta cotización, favor contactar con nosotros.'), 0, 'C');

// Salida del PDF
$pdf->Output('I', 'Cotizacion_' . $noCotizacion . '.pdf');

$stmtCotizacion->close();
$stmtProductos->close();
$conn->close();
?>