<?php
// Incluir librería FPDF y conexión a la base de datos
require('../../../libs/fpdf/fpdf.php');
require('../../../core/conexion.php');

// Verificar y validar la existencia de la variable de sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$idPuesto = isset($_SESSION['idPuesto']) ? intval($_SESSION['idPuesto']) : 0;
$ocultarIdentificacion = (1 > 2);

// Clase extendida de FPDF para soportar caracteres especiales
class PDF extends FPDF {
    // Cabecera de página
    function Header() {
        // Logo (opcional)
        $this->Image('../../assets/img/logo.png', 10, 8, 10);
        
        // Título
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'REPORTE DE CLIENTES', 0, 1, 'C');
        
        // Fecha del reporte
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
        
        // Salto de línea
        $this->Ln(5);
        
        // Encabezados de la tabla
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(10, 7, 'ID', 1, 0, 'C', true);
        $this->Cell(40, 7, 'Nombre', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Empresa', 1, 0, 'C', true);
        $this->Cell(15, 7, 'Tipo ID', 1, 0, 'C', true);
        $this->Cell(22, 7, 'Identificacion', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Telefono', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Limite Cred.', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Balance', 1, 0, 'C', true);
        $this->Cell(8, 7, 'Est.', 1, 1, 'C', true);
    }
    
    // Pie de página
    function Footer() {
        // Posición: a 1.5 cm del final
        $this->SetY(-15);
        // Arial italica 8
        $this->SetFont('Arial', 'I', 8);
        // Número de página
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    // Función para mostrar notas y dirección (texto largo)
    function ShowDetails($title, $text) {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 5, $title . ':', 0, 0);
        $this->SetFont('Arial', '', 9);
        
        // Dividimos el texto en múltiples líneas
        $this->MultiCell(0, 5, $text);
        $this->Ln(2);
    }
}

// Función para modificar la visualización de la identificación
function formatIdentificacion($identificacion, $ocultarIdentificacion) {
    if (!$ocultarIdentificacion) {
        return $identificacion;
    }
    
    // Obtener solo los últimos 4 dígitos
    $longitud = strlen($identificacion);
    if ($longitud <= 4) {
        return $identificacion; // Si tiene 4 o menos caracteres, mostrar completa
    }
    
    $ultimos4 = substr($identificacion, -4);
    $asteriscos = str_repeat('*', $longitud - 4);
    
    return $asteriscos . $ultimos4;
}

// Crear documento PDF
$pdf = new PDF('P', 'mm', 'Letter');
$pdf->AliasNbPages(); // Para mostrar el total de páginas
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

// Configurar la codificación para caracteres especiales
$pdf->SetAutoPageBreak(true, 25);

// Consultar los datos
$sql = "SELECT
            c.id,
            CONCAT(c.nombre, ' ', c.apellido) AS nombreCompleto,
            c.empresa,
            c.tipo_identificacion,
            c.identificacion,
            c.telefono,
            c.notas,
            cc.limite_credito,
            cc.balance,
            CONCAT(
                '#',
                cd.no,
                ', ',
                cd.calle,
                ', ',
                cd.sector,
                ', ',
                cd.ciudad,
                ', (Referencia: ',
                IFNULL(cd.referencia, 'Sin referencia'),
                ')'
            ) AS direccion,
            c.activo
        FROM
            clientes AS c
        LEFT JOIN clientes_cuenta AS cc
        ON
            c.id = cc.idCliente
        LEFT JOIN clientes_direcciones AS cd
        ON
            c.id = cd.idCliente";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Comprobar si hay suficiente espacio en la página para los datos
        if ($pdf->GetY() > 240) {
            $pdf->AddPage();
        }
        
        // Definir estado como texto
        $estado = ($row['activo'] == 1) ? 'Activo' : 'Inactivo';
        
        // Formatear números
        $limiteCredito = number_format($row['limite_credito'], 2);
        $balance = number_format($row['balance'], 2);
        
        // Formatear identificación según permisos
        $identificacionFormateada = formatIdentificacion($row['identificacion'], $ocultarIdentificacion);
        
        // Imprimir línea de datos principal
        $pdf->Cell(10, 6, $row['id'], 1, 0, 'C');
        $pdf->Cell(40, 6, iconv('UTF-8', 'ISO-8859-1', $row['nombreCompleto']), 1, 0, 'L');
        $pdf->Cell(30, 6, iconv('UTF-8', 'ISO-8859-1', $row['empresa']), 1, 0, 'L');
        $pdf->Cell(15, 6, $row['tipo_identificacion'], 1, 0, 'C');
        $pdf->Cell(22, 6, $identificacionFormateada, 1, 0, 'L');
        $pdf->Cell(20, 6, $row['telefono'], 1, 0, 'L');
        $pdf->Cell(20, 6, '$' . $limiteCredito, 1, 0, 'R');
        $pdf->Cell(20, 6, '$' . $balance, 1, 0, 'R');
        $pdf->Cell(8, 6, ($row['activo'] == 1) ? 'A' : 'I', 1, 1, 'C');
        
        // Detalles adicionales (dirección y notas)
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(25, 5, 'Direccion:', 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(0, 5, iconv('UTF-8', 'ISO-8859-1', $row['direccion']));
        
        if (!empty($row['notas'])) {
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(25, 5, 'Notas:', 0, 0);
            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell(0, 5, iconv('UTF-8', 'ISO-8859-1', $row['notas']));
        }
        
        // Línea separadora entre clientes
        $pdf->Ln(3);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);
    }
} else {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'No se encontraron registros de clientes', 0, 1, 'C');
}

// Cerrar la conexión a la base de datos
$conn->close();

// Nombre del archivo
$filename = 'Reporte_Clientes_' . date('Y-m-d_H-i-s') . '.pdf';

// Salida del PDF
$pdf->Output('I', $filename);
?>