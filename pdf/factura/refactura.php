<?php
// Set proper header for PDF output
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="factura_EasyPOS.pdf"');

require('../../libs/fpdf/fpdf.php');

// Custom PDF class for narrow receipts (3 inches = 76.2mm)
class ReceiptPDF extends FPDF {
    // Add title to the PDF document properties
    function SetDocumentTitle($title) {
        $this->SetTitle($title);
    }
    
    function Header() {
        // Empty header
    }
    
    function Footer() {
        // Empty footer
    }
    
    // Función para calcular altura del contenido
    function GetContentHeight($items_count, $has_customer_info=true, $has_payment_info=true) {
        // Altura de componentes fijos
        $fixed_height = 0;
        
        // Cabecera (logo, info tienda)
        $fixed_height += 20;
        
        // Info de cliente (si existe)
        if ($has_customer_info) {
            $fixed_height += 20;
        }
        
        // Cabecera de productos
        $fixed_height += 10;
        
        // Sección de totales
        $fixed_height += 20;
        
        // Sección de pago
        if ($has_payment_info) {
            $fixed_height += 20;
        }
        
        // Pie de página
        $fixed_height += 20;
        
        // Altura por producto (cada producto ocupa ~7mm)
        $items_height = $items_count * 7;
        
        // Margen de seguridad
        $safety_margin = 10;
        
        return $fixed_height + $items_height + $safety_margin;
    }
}

// Database connection
require('../../models/conexion.php');

// Ensure database connection is UTF-8
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset("utf8");
}

// Validate and sanitize input - Use prepared statements for all queries
$invoice_id = isset($_GET['factura']) ? intval($_GET['factura']) : 0;

// Early validation to prevent invalid requests
if ($invoice_id <= 0) {
    // Reset header to HTML and show error
    header('Content-Type: text/html');
    echo "<h2>Error: Número de factura inválido</h2>";
    echo "<p>Por favor especifique un número de factura válido.</p>";
    echo "<p><a href='javascript:history.back()'>Volver</a></p>";
    exit;
}

try {
    // Get data from database for invoice info - Using prepared statement
    $sqlito = "SELECT * FROM infofactura";
    $stmt_info = $conn->prepare($sqlito);
    
    if (!$stmt_info) {
        throw new Exception("Error preparing info statement: " . $conn->error);
    }
    
    $stmt_info->execute();
    $information = $stmt_info->get_result();
    $info = $information->fetch_assoc();
    $stmt_info->close();
    
    // Get invoice data - Using prepared statement
    $sql = "SELECT
                f.fecha AS fecha,
                CONCAT(c.id, ' ', c.nombre, ' ', c.apellido) AS nombrec,
                f.numFactura AS numf,
                f.descuento AS descuentof,
                CONCAT(e.nombre, ' ', e.apellido) AS nombree,
                c.empresa AS empresac,
                f.tipoFactura AS tipof,
                fm.metodo AS metodof,
                fm.monto AS montof,
                f.balance AS balancef
            FROM
                facturas f
            LEFT JOIN clientes c ON
                c.id = f.idCliente
            LEFT JOIN empleados e ON
                e.id = f.idEmpleado
            LEFT JOIN facturas_metodopago fm ON
                fm.numFactura = f.numFactura
            WHERE
                f.numFactura = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error preparing invoice statement: " . $conn->error);
    }
    
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $invoice = $result->fetch_assoc();
        $stmt->close();
        
        // Get invoice items - Using prepared statement
        $sql_items = "SELECT
                            CONCAT(p.id,' ',p.descripcion) AS descripcionp,
                            fc.importe AS importep,
                            fc.cantidad AS cantidadp,
                            fc.precioVenta
                        FROM
                            facturas_detalles fc
                        JOIN productos p ON
                            p.id = fc.idProducto
                        WHERE
                            fc.numFactura = ?";
        
        $stmt_items = $conn->prepare($sql_items);
        
        if (!$stmt_items) {
            throw new Exception("Error preparing items statement: " . $conn->error);
        }
        
        $stmt_items->bind_param("i", $invoice_id);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        
        // Instance of PDF to calculate height
        $pdf_calculator = new ReceiptPDF();
        
        // Get exact content height based on data
        $itemCount = $result_items->num_rows;
        $contentHeight = $pdf_calculator->GetContentHeight($itemCount);
        
        // Create PDF with calculated height - sin espacio adicional
        $pdf = new ReceiptPDF('P', 'mm', array(76.2, $contentHeight));
        
        // Set PDF document properties
        $pdf->SetDocumentTitle("EasyPOS Factura #" . $invoice['numf']);
        $pdf->SetAuthor('EasyPOS');
        $pdf->SetCreator('EasyPOS Sistema de Facturación');
        
        // Auto page break only if absolutely necessary
        $pdf->SetAutoPageBreak(true, 5);
        
        $pdf->AddPage();
        $pdf->SetMargins(5, 10, 5);
        $pdf->SetFont('Arial', 'B', 12);
        
        // Store name and info
        $pdf->Cell(66, 6, '              ' . utf8_decode(htmlspecialchars($info['name'])), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell(66, 4, utf8_decode(htmlspecialchars($info['text1'])), 0, 1, 'C');
        $pdf->Cell(66, 4, utf8_decode(htmlspecialchars($info['text2'])), 0, 1, 'C');
        
        // Date and invoice number
        $pdf->Cell(66, 4, date('d/m/Y h:i A', strtotime($invoice['fecha'])), 0, 1, 'R');
        $pdf->Ln(3);
        
        // Customer info
        $pdf->Cell(33, 4, 'Nombre Cliente:', 0, 0);
        $pdf->Cell(33, 4, utf8_decode(htmlspecialchars($invoice['nombrec'])), 0, 1);
        $pdf->Cell(33, 4, 'Empresa:', 0, 0);
        $pdf->Cell(33, 4, utf8_decode(htmlspecialchars($invoice['empresac'])), 0, 1);
        $pdf->Cell(33, 4, 'NCF:', 0, 0);
        $pdf->Cell(33, 4, '0', 0, 1);
        $pdf->Cell(33, 4, 'Tipo de Factura:', 0, 0);
        $pdf->Cell(33, 4, utf8_decode(htmlspecialchars($invoice['tipof'])), 0, 1);
        $pdf->Ln(3);
    
        // Numero de Factura
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(66, 3, '                   Factura #' . $invoice['numf'], 0, 1, 'L');

        // Reimpresion Alert
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(66, 3, utf8_decode('                               Reimpresión'), 0, 1, 'L');
        $pdf->Ln(3);
        
        // Header for items
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(40, 4, utf8_decode('Productos Facturados:'), 0, 0);
        $pdf->Cell(13, 4, '', 0, 1, 'R');
        $pdf->Line(5, $pdf->GetY(), 71.2, $pdf->GetY());
        $pdf->Ln(1);
        
        // Items
        $pdf->SetFont('Arial', '', 8);
        $subtotal = 0;
        
        if ($result_items->num_rows > 0) {
            // Reset pointer to beginning
            $result_items->data_seek(0);
            
            while($item = $result_items->fetch_assoc()) {
                $pdf->Cell(40, 4, utf8_decode(htmlspecialchars($item['descripcionp'])), 0, 0);
                $pdf->Ln(3);
                $pdf->Cell(26, 4, $item['cantidadp'].' x '.number_format($item['precioVenta'], 2).' = '.number_format($item['importep'], 2), 0, 1, 'L');
                
                $subtotal += $item['importep'];
            }
        }

        $pdf->Ln(1);
        $pdf->Line(5, $pdf->GetY(), 71.2, $pdf->GetY());
        $pdf->Ln(1);
        
         // Totals and Payment Method
         $pdf->SetFont('Arial', 'B', 8);
         $pdf->Cell(66, 4, 'TOTALES', 0, 1, 'C');
         $pdf->SetFont('Arial', '', 8);
 
         // Subtotal
         $pdf->Cell(33, 4, 'Subtotal:', 0, 0, 'L');
         $pdf->Cell(33, 4, number_format($subtotal, 2), 0, 1, 'R');
 
         // Descuento
         $pdf->Cell(33, 4, 'Descuento:', 0, 0, 'L');
         $pdf->Cell(33, 4, number_format($invoice['descuentof'], 2), 0, 1, 'R');
 
         // Total en negrita
         $pdf->SetFont('Arial', 'B', 8);
         $pdf->Cell(33, 4, 'TOTAL:', 0, 0, 'L');
         $pdf->Cell(33, 4, number_format(($subtotal - $invoice['descuentof']), 2), 0, 1, 'R');
 
         // Separación
         $pdf->Ln(2);
         $pdf->Line(5, $pdf->GetY(), 71.2, $pdf->GetY());
         $pdf->Ln(2);
 
         // Método de pago
         $pdf->SetFont('Arial', 'B', 8);
         $pdf->Cell(66, 4, utf8_decode('MÉTODO DE PAGO'), 0, 1, 'C');
         $pdf->SetFont('Arial', '', 8);
 
         // Método
         $pdf->Cell(33, 4, utf8_decode('Método:'), 0, 0, 'L');
         $pdf->Cell(33, 4, utf8_decode(htmlspecialchars($invoice['metodof'])), 0, 1, 'R');
 
         // Monto
         $pdf->Cell(33, 4, 'Monto:', 0, 0, 'L');
         $pdf->Cell(33, 4, number_format($invoice['montof'], 2), 0, 1, 'R');
 
         // Pendiente
         $pdf->Cell(33, 4, 'Pendiente:', 0, 0, 'L');
         $pdf->Cell(33, 4, number_format($invoice['balancef'], 2), 0, 1, 'R');
        
        // Footer text
        $pdf->Ln(2);
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(66, 3, utf8_decode(htmlspecialchars($info['text3'])), 0, 'C');
        
        // Close statement
        $stmt_items->close();
        
        // Output PDF directly to browser
        $pdf->Output('I', 'Factura_EasyPOS_' . $invoice['numf'] . '.pdf');
    } else {
        // If no invoice found, don't try to output PDF
        header('Content-Type: text/html'); // Reset header to HTML
        echo "<h2>Error: Factura no encontrada</h2>";
        echo "<p>La factura #$invoice_id no existe en la base de datos.</p>";
        echo "<p><a href='javascript:history.back()'>Volver</a></p>";
    }
} catch (Exception $e) {
    // Handle errors gracefully
    header('Content-Type: text/html');
    echo "<h2>Error al procesar la factura</h2>";
    echo "<p>Ha ocurrido un error al procesar su solicitud.</p>";
    // For development only - remove in production:
    // echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p><a href='javascript:history.back()'>Volver</a></p>";
} finally {
    // Always close the connection
    $conn->close();
}
?>