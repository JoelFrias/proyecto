<?php
require('../../libs/fpdf/fpdf.php');

// Clase personalizada para el recibo con altura automática
class PDF_Receipt extends FPDF {
    // Función para calcular altura del contenido
    function GetContentHeight($has_payment_info=true) {
        // Altura de componentes fijos
        $fixed_height = 0;
        
        // Cabecera (logo, título)
        $fixed_height += 20;
        
        // Info básica (fecha, cliente)
        $fixed_height += 15;
        
        // Sección de totales
        $fixed_height += 20;
        
        // Método de pago e info empleado
        if ($has_payment_info) {
            $fixed_height += 20;
        }
        
        // Pie de página con agradecimiento
        $fixed_height += 10;
        
        // Margen de seguridad
        $safety_margin = 10;
        
        return $fixed_height + $safety_margin;
    }
    
    // Función para el encabezado del recibo
    function Header() {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, 'EasyPOS', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, 'COMPROBANTE DE PAGO', 0, 1, 'C');
        $this->Ln(3);
    }
    
    // Función para agregar una línea de información
    function addInfoLine($label, $value) {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(30, 5, $label, 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->Cell(40, 5, $value, 0, 1);
    }
    
    // Función para agregar una línea separadora
    function addDashedLine() {
        $this->Cell(0, 0, '', 'T', 1);
        $this->Ln(2);
    }
}

// Database connection
require('../../models/conexion.php');

// Validate and sanitize input
$registro = isset($_GET['registro']) ? $_GET['registro'] : '';

// Early validation to prevent invalid requests
if (empty($registro)) {
    // Reset header to HTML and show error
    header('Content-Type: text/html');
    echo "<h2>Error: Registro inválido</h2>";
    echo "<p>Por favor especifique un registro válido.</p>";
    echo "<p><a href='javascript:history.back()'>Volver</a></p>";
    exit;
}

try {
    $sql = "SELECT
                DATE_FORMAT(ch.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                CONCAT(c.nombre, ' ', c.apellido) AS cliente,
                ch.monto AS total_pagado,
                ch.metodo AS metodo_pago,
                IFNULL(SUM(f.balance), 0) AS nuevo_balance,
                CONCAT(e.nombre, ' ', e.apellido) AS empleado
            FROM
                clientes_historialpagos ch
            JOIN clientes c ON
                ch.idCliente = c.id
            JOIN empleados e ON
                e.id = ch.idEmpleado
            LEFT JOIN facturas f ON
                f.idCliente = c.id AND f.estado = 'Pendiente'
            WHERE
                ch.registro = ?
            GROUP BY
                ch.registro";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }
    
    $stmt->bind_param("s", $registro);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $datos = $result->fetch_assoc();
        
        // Calcular la altura necesaria para el contenido
        $pdf_calculator = new PDF_Receipt();
        $contentHeight = $pdf_calculator->GetContentHeight();
        
        // Crear PDF con altura calculada
        $pdf = new FPDF('P', 'mm', array(76.2, $contentHeight));
        $pdf->SetAutoPageBreak(true, 5);
        $pdf->SetMargins(5, 10, 5);
        $pdf->AddPage();
        
        // Título del documento
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(66, 6, 'EasyPOS', 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(66, 5, 'COMPROBANTE DE PAGO', 0, 1, 'C');
        $pdf->Ln(3);
        
        // Información del recibo
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(33, 5, 'Fecha:', 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(33, 5, $datos['fecha'], 0, 1);
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(33, 5, 'Cliente:', 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(33, 5, utf8_decode(htmlspecialchars($datos['cliente'])), 0, 1);
        $pdf->Ln(2);
        
        // Línea separadora
        $pdf->Line(5, $pdf->GetY(), 71.2, $pdf->GetY());
        $pdf->Ln(2);
        
        // Información de pago
        $balance_anterior = $datos['nuevo_balance'] + $datos['total_pagado'];
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(33, 5, 'Total Pagado:', 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(33, 5, '$ ' . number_format($datos['total_pagado'], 2), 0, 1);
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(33, 5, 'Balance Anterior:', 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(33, 5, '$ ' . number_format($balance_anterior, 2), 0, 1);
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(33, 5, 'Balance Actual:', 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(33, 5, '$ ' . number_format($datos['nuevo_balance'], 2), 0, 1);
        
        // Línea separadora
        $pdf->Line(5, $pdf->GetY(), 71.2, $pdf->GetY());
        $pdf->Ln(2);
        
        // Método de pago y empleado
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(33, 5, 'Metodo de Pago:', 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(33, 5, utf8_decode(htmlspecialchars($datos['metodo_pago'])), 0, 1);
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(33, 5, 'Atendido por:', 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(33, 5, utf8_decode(htmlspecialchars($datos['empleado'])), 0, 1);
        $pdf->Ln(2);
        
        // Mensaje de agradecimiento
        $pdf->SetFont('Arial', '', 7);
        $pdf->MultiCell(66, 4, 'Gracias por su pago. Este comprobante es evidencia de su transaccion con EasyPOS.', 0, 'C');
        
        // Salida del PDF
        $pdf->Output('I', 'Comprobante_EasyPOS_' . $registro . '.pdf');
    } else {
        // Si no se encuentra el registro
        header('Content-Type: text/html'); // Reset header to HTML
        echo "<h2>Error: Registro no encontrado</h2>";
        echo "<p>El comprobante con registro #$registro no existe en la base de datos.</p>";
        echo "<p><a href='javascript:history.back()'>Volver</a></p>";
    }
} catch (Exception $e) {
    // Manejo de errores
    header('Content-Type: text/html');
    echo "<h2>Error al procesar el comprobante</h2>";
    echo "<p>Ha ocurrido un error al procesar su solicitud.</p>";
    // Para desarrollo - eliminar en producción:
    // echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p><a href='javascript:history.back()'>Volver</a></p>";
} finally {
    // Siempre cerrar la conexión
    if (isset($conn)) {
        $conn->close();
    }
}
?>