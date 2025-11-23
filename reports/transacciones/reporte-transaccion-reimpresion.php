<?php

// Activar reporte de errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../libs/fpdf/fpdf.php';
require_once '../../core/conexion.php';

// Verificar que se recibió el número de transacción
if (!isset($_GET['no']) || empty($_GET['no'])) {
    die('Error: Número de transacción no especificado');
}

$no_transaccion = $_GET['no'];

// ============================================
// CONSULTAR INFORMACIÓN DE LA TRANSACCIÓN
// ============================================

$sql_transaccion = "SELECT
                        ti.no AS NO,
                        DATE_FORMAT(ti.fecha, '%d/%m/%Y %l:%i %p') AS fecha,
                        CONCAT(e1.nombre, ' ', e1.apellido) AS emisor,
                        CONCAT(e2.nombre, ' ', e2.apellido) AS destinatario,
                        ti.tipo_mov AS tipo
                    FROM
                        transacciones_inv AS ti
                    INNER JOIN empleados AS e1 ON e1.id = ti.id_emp_reg
                    INNER JOIN empleados AS e2 ON e2.id = ti.id_emp_des
                    WHERE
                        ti.no = ?";

$stmt_trans = $conn->prepare($sql_transaccion);
$stmt_trans->bind_param("s", $no_transaccion);
$stmt_trans->execute();
$result_trans = $stmt_trans->get_result();

if ($result_trans->num_rows === 0) {
    die('Error: Transacción no encontrada');
}

$transaccion = $result_trans->fetch_assoc();

// ============================================
// CONSULTAR PRODUCTOS DE LA TRANSACCIÓN
// ============================================

$sql_productos = "SELECT
    p.id AS pid,
    p.descripcion AS pdescripcion,
    pt.descripcion AS tproducto,
    td.cantidad
FROM
    transacciones_det AS td
INNER JOIN productos AS p ON p.id = td.id_producto
INNER JOIN productos_tipo AS pt ON pt.id = p.idTipo
WHERE
    td.no = ?";

$stmt_prod = $conn->prepare($sql_productos);
$stmt_prod->bind_param("s", $no_transaccion);
$stmt_prod->execute();
$result_prod = $stmt_prod->get_result();

$productos = [];
while ($row = $result_prod->fetch_assoc()) {
    $productos[] = $row;
}

// ============================================
// CLASE PERSONALIZADA DE PDF CON MARCA DE AGUA
// ============================================

class PDF_Reimpresion extends FPDF
{
    private $transaccion;
    
    public function setTransaccion($data) {
        $this->transaccion = $data;
    }
    
    // Función para calcular el número de líneas necesarias
    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 and $s[$nb - 1] == "\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ')
                $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j)
                        $i++;
                } else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else
                $i++;
        }
        return $nl;
    }
    
    // Marca de agua de reimpresión (versión simple y confiable)
    function MarcaAgua()
    {
        // Marca de agua diagonal en el centro
        $this->SetFont('Arial', 'B', 50);
        $this->SetTextColor(260, 200, 200); // Rosa claro para simular transparencia
        
        // Rotar y posicionar texto
        $this->Rotate(45, 105, 148.5);
        $this->Text(45, 155, 'REIMPRESION');
        $this->Rotate(0);
        
        // Resetear color
        $this->SetTextColor(0, 0, 0);
    }
    
    // Función auxiliar para rotación
    function Rotate($angle, $x = -1, $y = -1){

        if ($x == -1)
            $x = $this->x;
        if ($y == -1)
            $y = $this->y;
        if ($this->angle != 0)
            $this->_out('Q');
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }
    
    // Encabezado
    function Header(){

        // Marca de agua en cada página
        $this->MarcaAgua();
        
        // Logo
        $logo_path = '../../assets/img/logo.png';
        if (file_exists($logo_path)) {
            $this->Image($logo_path, 15, 10, 20);
        }
        
        // Indicador de REIMPRESIÓN en esquina superior derecha
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(220, 53, 69); // Rojo
        $this->SetXY(140, 10);
        $this->Cell(55, 37, 'REIMPRESION FECHA', 0, 1, 'C');
        $this->SetX(140);
        $this->SetFont('Arial', '', 8);
        $this->Cell(55, -30, date('d/m/Y H:i:s'), 0, 1, 'C');
        
        // Espacio después del logo
        $this->SetY(15);
        $this->SetX(45);
        
        // Título principal
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 8, 'REPORTE DE TRANSACCION DE INVENTARIO', 0, 1, 'C');
        
        // Fecha actual
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        $dia_semana = $dias[date('w')];
        $dia = date('d');
        $mes = $meses[date('n')];
        $anio = date('Y');

        $fecha_actual = "$dia_semana $dia de $mes de $anio";
        
        $this->SetFont('Arial', 'I', 10);
        $this->SetTextColor(127, 140, 141);
        $this->Cell(0, 6, '', 0, 1, 'C');
        
        // Línea decorativa
        $this->SetDrawColor(52, 152, 219);
        $this->SetLineWidth(0.8);
        $this->Line(15.5, 38, 194.55, 38);
        
        $this->Ln(9.5);
    }
    
    // Pie de página
    function Footer()
    {
        $this->SetY(-20);
        
        // Nota de reimpresión
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(220, 53, 69);
        $this->Cell(0, 4, 'DOCUMENTO REIMPRESO - NO ES EL ORIGINAL', 0, 1, 'C');
        
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(149, 165, 166);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . ' de {nb}', 0, 0, 'C');
    }
    
    // Inicializar propiedades
    var $angle = 0;
}

// ============================================
// CREAR DOCUMENTO PDF
// ============================================

$pdf = new PDF_Reimpresion('P', 'mm', 'Letter');

// Metadatos del documento
$pdf->SetTitle('REIMPRESION - Transaccion ' . $transaccion['NO']);
$pdf->SetAuthor('JFSystems');
$pdf->SetSubject('Reimpresion de Transaccion de Inventario No. ' . $transaccion['NO']);
$pdf->SetKeywords('Reimpresion, Transaccion, Inventario, ' . $transaccion['tipo']);
$pdf->SetCreator('Sistema de Gestion de Inventario - JFSystems');

$pdf->AliasNbPages();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 40);
$pdf->AddPage();
$pdf->setTransaccion($transaccion);

// ============================================
// INFORMACIÓN DE LA TRANSACCIÓN
// ============================================

// Fondo para información principal
$pdf->SetFillColor(236, 240, 241);
$pdf->Rect(15, $pdf->GetY(), 180, 35, 'F');

$y_start = $pdf->GetY() + 5;

// Columna izquierda
$pdf->SetXY(20, $y_start);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(52, 73, 94);
$pdf->Cell(40, 6, 'No. Transaccion:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(44, 62, 80);
$pdf->Cell(0, 6, $transaccion['NO'], 0, 1);

$pdf->SetX(20);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(52, 73, 94);
$pdf->Cell(40, 6, 'Fecha:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(44, 62, 80);
$pdf->Cell(0, 6, $transaccion['fecha'], 0, 1);

$pdf->SetX(20);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(52, 73, 94);
$pdf->Cell(40, 6, 'Tipo de Movimiento:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(44, 62, 80);
$tipo_display = strtoupper($transaccion['tipo']);
$pdf->Cell(0, 6, $tipo_display, 0, 1);

// Columna derecha
$pdf->SetXY(110, $y_start);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(52, 73, 94);
$pdf->Cell(30, 6, 'Emisor:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(44, 62, 80);
$pdf->MultiCell(0, 6, $transaccion['emisor'], 0, 'L');

$pdf->SetX(110);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(52, 73, 94);
$pdf->Cell(30, 6, 'Destinatario:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(44, 62, 80);
$pdf->MultiCell(0, 6, $transaccion['destinatario'], 0, 'L');

$pdf->Ln(10);

// ============================================
// TABLA DE PRODUCTOS
// ============================================

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(44, 62, 80);
$pdf->Cell(0, 8, 'DETALLE DE PRODUCTOS', 0, 1, 'L');

// Encabezado de tabla
$pdf->SetFillColor(52, 152, 219);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetDrawColor(52, 152, 219);
$pdf->SetLineWidth(0.3);
$pdf->SetFont('Arial', 'B', 9);

$pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
$pdf->Cell(80, 8, 'DESCRIPCION', 1, 0, 'C', true);
$pdf->Cell(55, 8, 'TIPO', 1, 0, 'C', true);
$pdf->Cell(30, 8, 'CANTIDAD', 1, 1, 'C', true);

// Contenido de tabla
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(44, 62, 80);
$pdf->SetDrawColor(189, 195, 199);

$fill = false;
foreach ($productos as $producto) {
    $pdf->SetFillColor(236, 240, 241);
    
    // Calcular altura necesaria para descripción larga
    $nb_lines = $pdf->NbLines(80, $producto['pdescripcion']);
    $height = 6 * $nb_lines;
    if ($height < 6) $height = 6;
    
    // Verificar si necesita nueva página
    if ($pdf->GetY() + $height > 240) {
        $pdf->AddPage();
        // Re-dibujar encabezado de tabla
        $pdf->SetFillColor(52, 152, 219);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $pdf->Cell(80, 8, 'DESCRIPCION', 1, 0, 'C', true);
        $pdf->Cell(55, 8, 'TIPO', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'CANTIDAD', 1, 1, 'C', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(44, 62, 80);
    }
    
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    
    $pdf->Cell(15, $height, $producto['pid'], 1, 0, 'C', $fill);
    
    // MultiCell para descripción
    $pdf->MultiCell(80, 6, $producto['pdescripcion'], 1, 'L', $fill);
    
    $pdf->SetXY($x + 95, $y);
    $pdf->Cell(55, $height, $producto['tproducto'], 1, 0, 'C', $fill);
    $pdf->Cell(30, $height, $producto['cantidad'], 1, 1, 'C', $fill);
    
    $fill = !$fill;
}

$pdf->Ln(5);

// ============================================
// SECCIÓN DE FIRMAS
// ============================================

// Verificar espacio disponible
if ($pdf->GetY() > 220) {
    $pdf->AddPage();
}

$pdf->Ln(15);

$pdf->SetDrawColor(52, 73, 94);
$pdf->SetLineWidth(0.5);

$y_firmas = $pdf->GetY();

// Firma Emisor
$pdf->SetXY(25, $y_firmas);
$pdf->Line(25, $y_firmas + 20, 85, $y_firmas + 20);
$pdf->SetXY(25, $y_firmas + 22);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(52, 73, 94);
$pdf->Cell(60, 5, 'EMISOR', 0, 1, 'C');
$pdf->SetX(25);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(127, 140, 141);
$pdf->Cell(60, 5, $transaccion['emisor'], 0, 1, 'C');
$pdf->SetX(25);
$pdf->Cell(60, 4, 'Firma y Sello', 0, 0, 'C');

// Firma Destinatario
$pdf->SetXY(115, $y_firmas);
$pdf->Line(115, $y_firmas + 20, 175, $y_firmas + 20);
$pdf->SetXY(115, $y_firmas + 22);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(52, 73, 94);
$pdf->Cell(60, 5, 'DESTINATARIO', 0, 1, 'C');
$pdf->SetX(115);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(127, 140, 141);
$pdf->Cell(60, 5, $transaccion['destinatario'], 0, 1, 'C');
$pdf->SetX(115);
$pdf->Cell(60, 4, 'Firma y Sello', 0, 0, 'C');

// Nota al pie
$pdf->Ln(20);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(149, 165, 166);
$pdf->Cell(0, 5, 'Este documento es valido como comprobante de transaccion de inventario', 0, 1, 'C');

// ============================================
// GENERAR PDF
// ============================================

$pdf->Output('I', 'REIMPRESION_Transaccion_' . $transaccion['NO'] . '.pdf');

// Cerrar conexiones
$stmt_trans->close();
$stmt_prod->close();
$conn->close();
?>