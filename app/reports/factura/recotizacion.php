<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require('../../../libs/fpdf/fpdf.php');
require('../../../core/conexion.php');

// Ensure database connection is UTF-8
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8");
}

// Verificar que se recibió el número de cotización
if (!isset($_GET['cotizacion']) || empty($_GET['cotizacion'])) {
    header('Content-Type: text/html');
    echo "<h2>Error: Número de cotización no proporcionado</h2>";
    echo "<p><a href='javascript:history.back()'>Volver</a></p>";
    exit;
}

$noCotizacion = intval($_GET['cotizacion']);

try {
    // Obtener información de la cotización - Using prepared statement
    $sqlCotizacion = "SELECT
                        ci.no,
                        ci.fecha,
                        DATE_FORMAT(ci.fecha, '%d/%m/%Y') AS fecha_formato,
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
    
    if (!$stmtCotizacion) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }
    
    $stmtCotizacion->bind_param('i', $noCotizacion);
    $stmtCotizacion->execute();
    $resultCotizacion = $stmtCotizacion->get_result();

    if ($resultCotizacion->num_rows === 0) {
        header('Content-Type: text/html');
        echo "<h2>Error: Cotización no encontrada</h2>";
        echo "<p>La cotización #$noCotizacion no existe en la base de datos.</p>";
        echo "<p><a href='javascript:history.back()'>Volver</a></p>";
        exit;
    }

    $cotizacion = $resultCotizacion->fetch_assoc();
    $stmtCotizacion->close();

    // Obtener productos de la cotización - Using prepared statement
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
    
    if (!$stmtProductos) {
        throw new Exception("Error preparing products statement: " . $conn->error);
    }
    
    $stmtProductos->bind_param('i', $noCotizacion);
    $stmtProductos->execute();
    $resultProductos = $stmtProductos->get_result();

    $productos = [];
    while ($row = $resultProductos->fetch_assoc()) {
        $productos[] = $row;
    }
    
    $stmtProductos->close();

    // Crear PDF con marca de agua
    class PDF extends FPDF
    {
        function Header()
        {
            // Logo
            if (file_exists('../assets/img/logo.png')) {
                $this->Image('../assets/img/logo.png', 10, 10, 40);
            }
            
            // Información de la empresa
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(33, 37, 41);
            $this->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1', getenv('APP_NAME')), 0, 1, 'R');
            
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(108, 117, 125);
            $this->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1', 'Ave. 27 de febrero, Edif #32, Los Jardines'), 0, 1, 'R');
            $this->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1', 'Teléfono: (809) 727-6431'), 0, 1, 'R');
            $this->Cell(0, 5, iconv('UTF-8', 'ISO-8859-1', 'Email: fjoelfrias@gmail.com'), 0, 1, 'R');
            
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
            $this->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1', 'Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'C');
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
                $this->Cell($w[1], 6, iconv('UTF-8', 'ISO-8859-1', htmlspecialchars($row[1])), 'LR', 0, 'L', $fill);
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

    // Set PDF document properties
    $pdf->SetTitle("EasyPOS Cotizacion #" . $cotizacion['no']);
    $pdf->SetAuthor('EasyPOS');
    $pdf->SetCreator('EasyPOS Sistema de Cotizaciones');

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

    // Información de la cotización y cliente
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(33, 37, 41);

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
    $pdf->Cell(60, 5, $cotizacion['fecha_formato'] . ' - ' . $cotizacion['hora'], 0, 1);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(35, 5, iconv('UTF-8', 'ISO-8859-1', 'Atendido por:'), 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    
    // Manejar nombre de empleado largo
    $nombreEmpleado = iconv('UTF-8', 'ISO-8859-1', htmlspecialchars($cotizacion['nombreEmpleado']));
    $anchoEmpleado = $pdf->GetStringWidth($nombreEmpleado);
    if ($anchoEmpleado > 60) {
        $pdf->Ln();
        $pdf->SetX(10);
        $pdf->MultiCell(95, 5, $nombreEmpleado, 0, 'L');
    } else {
        $pdf->Cell(60, 5, $nombreEmpleado, 0, 1);
    }

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(35, 5, iconv('UTF-8', 'ISO-8859-1', 'Estado:'), 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(60, 5, iconv('UTF-8', 'ISO-8859-1', htmlspecialchars($cotizacion['estado'])), 0, 1);

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
    
    // Manejar nombre de cliente largo
    $nombreCliente = iconv('UTF-8', 'ISO-8859-1', htmlspecialchars($cotizacion['nombreCliente']));
    $anchoCliente = $pdf->GetStringWidth($nombreCliente);
    if ($anchoCliente > 60) {
        $pdf->Ln();
        $pdf->SetX(110);
        $pdf->MultiCell(90, 5, $nombreCliente, 0, 'L');
    } else {
        $pdf->Cell(60, 5, $nombreCliente, 0, 1);
    }

    $pdf->SetX(110);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 5, iconv('UTF-8', 'ISO-8859-1', 'Empresa:'), 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    
    // Manejar empresa larga
    $empresaCliente = iconv('UTF-8', 'ISO-8859-1', htmlspecialchars($cotizacion['empresaCliente']));
    $anchoEmpresa = $pdf->GetStringWidth($empresaCliente);
    if ($anchoEmpresa > 60) {
        $pdf->Ln();
        $pdf->SetX(110);
        $pdf->MultiCell(90, 5, $empresaCliente, 0, 'L');
    } else {
        $pdf->Cell(60, 5, $empresaCliente, 0, 1);
    }

    $pdf->SetX(110);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 5, iconv('UTF-8', 'ISO-8859-1', 'Teléfono:'), 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(60, 5, htmlspecialchars($cotizacion['telefonoCliente']), 0, 1);

    $pdf->SetX(110);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 5, iconv('UTF-8', 'ISO-8859-1', 'Dirección:'), 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->MultiCell(60, 5, iconv('UTF-8', 'ISO-8859-1', htmlspecialchars($cotizacion['direccionCliente'])), 0, 'L');

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
        $pdf->MultiCell(0, 5, iconv('UTF-8', 'ISO-8859-1', htmlspecialchars($cotizacion['notas'])), 1, 'L', true);
    }

    // Información adicional
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->MultiCell(0, 4, iconv('UTF-8', 'ISO-8859-1', 'Esta cotización tiene una validez de 30 días a partir de la fecha de emisión. Los precios están sujetos a cambios sin previo aviso. Para hacer efectiva esta cotización, favor contactar con nosotros.'), 0, 'C');

    // Salida del PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Cotizacion_' . $cotizacion['no'] . '.pdf"');
    $pdf->Output('I', 'Cotizacion_' . $cotizacion['no'] . '.pdf');

} catch (Exception $e) {
    // Handle errors gracefully
    header('Content-Type: text/html');
    echo "<h2>Error al procesar la cotización</h2>";
    echo "<p>Ha ocurrido un error al procesar su solicitud.</p>";
    echo "<p><a href='javascript:history.back()'>Volver</a></p>";
} finally {
    // Always close the connection
    if (isset($conn)) {
        $conn->close();
    }
}
?>