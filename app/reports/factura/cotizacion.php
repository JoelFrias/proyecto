<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require('../../../libs/fpdf/fpdf.php');
require('../../../core/conexion.php');

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

// Crear PDF
class PDF extends FPDF
{
    function Header()
    {
        // Logo (ajusta la ruta según tu estructura)
        if (file_exists('../../assets/img/logo.png')) {
            $this->Image('../../assets/img/logo.png', 10, 10, 40);
        }
        
        // Información de la empresa (ajusta según tus datos)
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(33, 37, 41);
        $this->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1', 'EasyPOS'), 0, 1, 'R');  // Nombre de la empresa
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(108, 117, 125);
        $this->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1', ''), 0, 1, 'R'); // Dirección de la empresa
        $this->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1', 'Teléfono: (809) 727-6431'), 0, 1, 'R'); // Teléfono de la empresa
        $this->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1', 'Email: fjoelfrias@gmail.com'), 0, 1, 'R'); // Email de la empresa
        
        $this->Ln(5);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1', 'Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'C');
    }
    
    // Tabla mejorada
    function TablaProductos($header, $data)
    {
        // Colores y anchos
        $this->SetFillColor(37, 99, 235);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(.3);
        $this->SetFont('Arial', 'B', 10);
        
        // Anchos de columnas
        $w = array(15, 95, 25, 30, 30);
        
        // Cabecera
        for($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        
        // Restaurar colores
        $this->SetFillColor(248, 249, 250);
        $this->SetTextColor(33, 37, 41);
        $this->SetFont('Arial', '', 9);
        
        // Datos
        $fill = false;
        foreach($data as $row) {
            $this->Cell($w[0], 6, $row[0], 'LR', 0, 'C', $fill);
            $this->Cell($w[1], 6, iconv('UTF-8', 'ISO-8859-1', $row[1]), 'LR', 0, 'L', $fill);
            $this->Cell($w[2], 6, number_format($row[2], 2), 'LR', 0, 'C', $fill);
            $this->Cell($w[3], 6, 'RD$ ' . number_format($row[3], 2), 'LR', 0, 'R', $fill);
            $this->Cell($w[4], 6, 'RD$ ' . number_format($row[4], 2), 'LR', 0, 'R', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        
        // Línea de cierre
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
$pdf->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1', 'PRE-FACTURA / COTIZACIÓN'), 0, 1, 'C');
$pdf->Ln(3);

// Línea divisora
$pdf->SetDrawColor(37, 99, 235);
$pdf->SetLineWidth(0.5);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(5);

// Información de la cotización y cliente en dos columnas
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(33, 37, 41);

// Columna izquierda - Info de cotización
$y_start = $pdf->GetY();
$pdf->Cell(95, 6, iconv('UTF-8', 'ISO-8859-1', 'INFORMACIÓN DE COTIZACIÓN'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(73, 80, 87);

$pdf->Cell(35, 5, iconv('UTF-8', 'ISO-8859-1', 'Número:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, $cotizacion['no'], 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(35, 5, iconv('UTF-8', 'ISO-8859-1', 'Fecha:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, $cotizacion['fecha'] . ' - ' . $cotizacion['hora'], 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(35, 5, iconv('UTF-8', 'ISO-8859-1', 'Atendido por:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, iconv('UTF-8', 'ISO-8859-1', $cotizacion['nombreEmpleado']), 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(35, 5, iconv('UTF-8', 'ISO-8859-1', 'Estado:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, iconv('UTF-8', 'ISO-8859-1', $cotizacion['estado']), 0, 1);

// Columna derecha - Info del cliente
$pdf->SetY($y_start);
$pdf->SetX(110);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(33, 37, 41);
$pdf->Cell(95, 6, iconv('UTF-8', 'ISO-8859-1', 'INFORMACIÓN DEL CLIENTE'), 0, 1);

$pdf->SetX(110);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(73, 80, 87);
$pdf->Cell(30, 5, iconv('UTF-8', 'ISO-8859-1', 'Cliente:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, iconv('UTF-8', 'ISO-8859-1', $cotizacion['nombreCliente']), 0, 1);

$pdf->SetX(110);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(30, 5, iconv('UTF-8', 'ISO-8859-1', 'Empresa:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, iconv('UTF-8', 'ISO-8859-1', $cotizacion['empresaCliente']), 0, 1);

$pdf->SetX(110);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(30, 5, iconv('UTF-8', 'ISO-8859-1', 'Teléfono:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, $cotizacion['telefonoCliente'], 0, 1);

$pdf->SetX(110);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(30, 5, iconv('UTF-8', 'ISO-8859-1', 'Dirección:'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->MultiCell(60, 5, iconv('UTF-8', 'ISO-8859-1', $cotizacion['direccionCliente']), 0, 'L');

$pdf->Ln(5);

// Tabla de productos
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(33, 37, 41);
$pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1', 'DETALLE DE PRODUCTOS'), 0, 1);
$pdf->Ln(2);

$header = array('#', iconv('UTF-8', 'ISO-8859-1', 'Descripción'), 'Cant.', 'Precio Unit.', 'Subtotal');
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

// Subtotal
$pdf->Cell(135, 6, '', 0, 0);
$pdf->Cell(30, 6, 'Subtotal:', 1, 0, 'R');
$pdf->Cell(30, 6, 'RD$ ' . number_format($cotizacion['subtotal'], 2), 1, 1, 'R');

// Descuento
if ($cotizacion['descuento'] > 0) {
    $pdf->Cell(135, 6, '', 0, 0);
    $pdf->SetTextColor(220, 38, 38);
    $pdf->Cell(30, 6, 'Descuento:', 1, 0, 'R');
    $pdf->Cell(30, 6, '- RD$ ' . number_format($cotizacion['descuento'], 2), 1, 1, 'R');
}

// Total
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
    $pdf->MultiCell(0, 5, iconv('UTF-8', 'ISO-8859-1', $cotizacion['notas']), 1, 'L', true);
}

// Información adicional al final
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(150, 150, 150);
$pdf->MultiCell(0, 4, iconv('UTF-8', 'ISO-8859-1', 'Esta cotización tiene una validez de 30 días a partir de la fecha de emisión. Los precios están sujetos a cambios sin previo aviso. Para hacer efectiva esta cotización, favor contactar con nosotros.'), 0, 'C');

// Salida del PDF
$pdf->Output('I', 'Cotizacion_' . $noCotizacion . '.pdf');

$stmtCotizacion->close();
$stmtProductos->close();
$conn->close();
?>