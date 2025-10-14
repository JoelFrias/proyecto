<?php
// Incluir librería FPDF y conexión a la base de datos
require('../../libs/fpdf/fpdf.php');
require('../../models/conexion.php');

// Clase extendida de FPDF para soportar caracteres especiales
class PDF extends FPDF {
    // Cabecera de página
    function Header() {
        // Logo
        $this->Image('../../assets/img/logo.png', 10, 8, 10);
        
        // Título
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'REPORTE DE PRODUCTOS', 0, 1, 'C');
        
        // Fecha del reporte
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
        
        // Salto de línea
        $this->Ln(5);
        
        // Encabezados de la tabla
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(10, 7, 'ID', 1, 0, 'C', true);
        $this->Cell(70, 7, 'Descripcion', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Reorden', 1, 0, 'C', true);
        $this->Cell(15, 7, 'Existencia', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Compra', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Venta 1', 1, 0, 'C', true);
        $this->Cell(25, 7, 'Venta 2', 1, 0, 'C', true);
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
    
    // Función para mostrar detalles adicionales (texto largo)
    function ShowDetails($title, $text) {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 5, $title . ':', 0, 0);
        $this->SetFont('Arial', '', 9);
        
        // Dividimos el texto en múltiples líneas
        $this->MultiCell(0, 5, $text);
        $this->Ln(2);
    }
}

// Verificar y validar la existencia de la variable de sesión
session_start();
$idPuesto = isset($_SESSION['idPuesto']) ? intval($_SESSION['idPuesto']) : 0;
$ocultarPrecioCompra = ($idPuesto > 2);

// Crear documento PDF
$pdf = new PDF('P', 'mm', 'Letter');
$pdf->AliasNbPages(); // Para mostrar el total de páginas
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

// Configurar la codificación para caracteres especiales
$pdf->SetAutoPageBreak(true, 25);

// Consultar los datos
$sql = "SELECT
            p.id AS idProducto,
            p.descripcion AS descripcion,
            pt.descripcion AS tipo,
            p.existencia,
            p.idTipo,
            p.precioCompra,
            p.precioVenta1,
            p.precioVenta2,
            p.reorden,
            p.activo
        FROM
            productos AS p
        LEFT JOIN productos_tipo AS pt
        ON
            p.idTipo = pt.id";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Comprobar si hay suficiente espacio en la página para los datos
        if ($pdf->GetY() > 240) {
            $pdf->AddPage();
        }
        
        // Formatear números
        $precioCompra = number_format($row['precioCompra'], 2);
        $precioVenta1 = number_format($row['precioVenta1'], 2);
        $precioVenta2 = number_format($row['precioVenta2'], 2);
        $existencia = number_format($row['existencia'], 0);

        // Modificar la visualización del precio de compra según el idPuesto
        $mostrarPrecioCompra = $ocultarPrecioCompra ? '****' : '$' . $precioCompra;
        
        // Imprimir línea de datos principal
        $pdf->SetFont('Arial', '', 8);

        $pdf->Cell(10, 6, $row['idProducto'], 1, 0, 'C');
        $pdf->Cell(70, 6, utf8_decode($row['descripcion']), 1, 0, 'L');
        $pdf->Cell(20, 6, utf8_decode($row['reorden']), 1, 0, 'C');
        $pdf->Cell(15, 6, $existencia, 1, 0, 'C');
        $pdf->Cell(25, 6, $mostrarPrecioCompra, 1, 0, 'R');
        $pdf->Cell(25, 6, '$' . $precioVenta1, 1, 0, 'R');
        $pdf->Cell(25, 6, '$' . $precioVenta2, 1, 0, 'R');
        $pdf->Cell(8, 6, ($row['activo'] == 1) ? 'A' : 'I', 1, 1, 'C');
        
        // Información adicional (nivel de reorden)
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(40, 5, 'Tipo de Producto:', 0, 0);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(30, 5, utf8_decode($row['tipo']), 0, 0);
        
        // Indicador visual si el producto está por debajo del nivel de reorden
        if ($row['existencia'] < $row['reorden']) {
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor(255, 0, 0); // Texto en rojo
            $pdf->Cell(0, 5, '                                **ALERTA: Producto por debajo del nivel de reorden**', 0, 0);
            $pdf->SetTextColor(0, 0, 0); // Restablecer color del texto
        }
        
        $pdf->Ln(5);
        
        // Línea separadora entre productos
        $pdf->Line($pdf->GetX(), $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);
    }
    
    // Resumen al final del reporte
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'RESUMEN DE INVENTARIO', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Consultar estadísticas
    $statsQuery = "SELECT 
                      COUNT(*) as totalProductos,
                      SUM(existencia) as totalExistencia,
                      SUM(existencia * precioCompra) as valorInventario,
                      COUNT(CASE WHEN existencia < reorden THEN 1 END) as bajosStock
                  FROM productos";
    
    $statsResult = $conn->query($statsQuery);
    $stats = $statsResult->fetch_assoc();
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(100, 8, 'Total de Productos:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, number_format($stats['totalProductos'], 0), 0, 1);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(100, 8, 'Total de Unidades en Inventario:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, number_format($stats['totalExistencia'], 0), 0, 1);
    
    // Mostrar el valor total del inventario con asteriscos si el empleado no tiene permiso
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(100, 8, 'Valor Total del Inventario (Precio de Compra):', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    
    if ($ocultarPrecioCompra) {
        $pdf->Cell(0, 8, '****', 0, 1);
    } else {
        $pdf->Cell(0, 8, '$' . number_format($stats['valorInventario'], 2), 0, 1);
    }
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(100, 8, 'Productos por Debajo del Nivel de Reorden:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, number_format($stats['bajosStock'], 0), 0, 1);
    
    // Consultar productos por tipo
    $tipoQuery = "SELECT 
                    pt.descripcion as tipo, 
                    COUNT(*) as cantidad
                  FROM productos p
                  LEFT JOIN productos_tipo pt ON p.idTipo = pt.id
                  GROUP BY p.idTipo
                  ORDER BY cantidad DESC";
    
    $tipoResult = $conn->query($tipoQuery);
    
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, utf8_decode('DISTRIBUCIÓN DE PRODUCTOS POR TIPO'), 0, 1, 'C');
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(100, 8, 'Tipo de Producto', 1, 0, 'C');
    $pdf->Cell(80, 8, 'Cantidad', 1, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    while($tipoRow = $tipoResult->fetch_assoc()) {
        $pdf->Cell(100, 8, utf8_decode($tipoRow['tipo']), 1, 0, 'L');
        $pdf->Cell(80, 8, number_format($tipoRow['cantidad'], 0), 1, 1, 'C');
    }
    
} else {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'No se encontraron registros de productos', 0, 1, 'C');
}

// Cerrar la conexión a la base de datos
$conn->close();

// Nombre del archivo
$filename = 'Reporte_Productos_' . date('Y-m-d_H-i-s') . '.pdf';

// Salida del PDF
$pdf->Output('I', $filename);
?>