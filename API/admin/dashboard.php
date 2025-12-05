<?php

require_once '../../core/conexion.php';

header("Content-Type: application/json");

// Obtener la acción solicitada
$action = $_GET["action"] ?? "";

// Función para obtener rango de fechas según período
function obtenerRangoFechas($periodo, $fechaInicio = null, $fechaFin = null)
{
    if ($periodo === "personalizado" && $fechaInicio && $fechaFin) {
        return [
            "inicio" => $fechaInicio . " 00:00:00",
            "fin" => $fechaFin . " 23:59:59",
        ];
    }

    $hoy = date("Y-m-d");

    switch ($periodo) {
        case "hoy":
            return [
                "inicio" => $hoy . " 00:00:00",
                "fin" => $hoy . " 23:59:59",
            ];

        case "semana":
            $inicioSemana = date("Y-m-d", strtotime("monday this week"));
            return [
                "inicio" => $inicioSemana . " 00:00:00",
                "fin" => $hoy . " 23:59:59",
            ];

        case "mes":
            $inicioMes = date("Y-m-01");
            return [
                "inicio" => $inicioMes . " 00:00:00",
                "fin" => $hoy . " 23:59:59",
            ];

        case "ano":
            $inicioAno = date("Y-01-01");
            return [
                "inicio" => $inicioAno . " 00:00:00",
                "fin" => $hoy . " 23:59:59",
            ];

        default:
            return [
                "inicio" => date("Y-m-01") . " 00:00:00",
                "fin" => $hoy . " 23:59:59",
            ];
    }
}

// ====================================
// KPIS
// ====================================
if ($action === "getKPIs") {
    $periodo = $_GET["periodo"] ?? "mes";
    $fechaInicio = $_GET["fechaInicio"] ?? null;
    $fechaFin = $_GET["fechaFin"] ?? null;

    $rango = obtenerRangoFechas($periodo, $fechaInicio, $fechaFin);

    try {
        // KPI: Ventas Totales
        $queryVentas = "
            SELECT 
                IFNULL(SUM(total), 0) AS total_ventas,
                COUNT(*) AS num_facturas
            FROM facturas
            WHERE fecha BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
            AND estado != 'Cancelada'
        ";
        $resultVentas = mysqli_query($conn, $queryVentas);
        $ventas = mysqli_fetch_assoc($resultVentas);

        // Calcular tendencia (comparar con período anterior)
        $duracion =
            (strtotime($rango["fin"]) - strtotime($rango["inicio"])) / 86400;
        $inicioAnterior = date(
            "Y-m-d H:i:s",
            strtotime($rango["inicio"] . " -$duracion days")
        );
        $finAnterior = date(
            "Y-m-d H:i:s",
            strtotime($rango["fin"] . " -$duracion days")
        );

        $queryVentasAnterior = "
            SELECT SUM(total) as total_ventas_anterior
            FROM facturas
            WHERE fecha BETWEEN '$inicioAnterior' AND '$finAnterior'
            AND estado != 'Cancelada'
        ";
        $resultVentasAnterior = mysqli_query($conn, $queryVentasAnterior);
        $ventasAnterior = mysqli_fetch_assoc($resultVentasAnterior);

        $tendenciaVentas = 0;
        if ($ventasAnterior["total_ventas_anterior"] > 0) {
            $tendenciaVentas =
                (($ventas["total_ventas"] -
                    $ventasAnterior["total_ventas_anterior"]) /
                    $ventasAnterior["total_ventas_anterior"]) *
                100;
        }

        // KPI: Ganancias
        $queryGanancias = "
            SELECT SUM(ganancias) as total_ganancias
            FROM facturas_detalles fd
            INNER JOIN facturas f ON fd.numFactura = f.numFactura
            WHERE f.fecha BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
            AND f.estado != 'Cancelada'
        ";
        $resultGanancias = mysqli_query($conn, $queryGanancias);
        $ganancias = mysqli_fetch_assoc($resultGanancias);

        // Tendencia ganancias
        $queryGananciasAnterior = "
            SELECT SUM(ganancias) as total_ganancias_anterior
            FROM facturas_detalles fd
            INNER JOIN facturas f ON fd.numFactura = f.numFactura
            WHERE f.fecha BETWEEN '$inicioAnterior' AND '$finAnterior'
            AND f.estado != 'Cancelada'
        ";
        $resultGananciasAnterior = mysqli_query($conn, $queryGananciasAnterior);
        $gananciasAnterior = mysqli_fetch_assoc($resultGananciasAnterior);

        $tendenciaGanancias = 0;
        if ($gananciasAnterior["total_ganancias_anterior"] > 0) {
            $tendenciaGanancias =
                (($ganancias["total_ganancias"] -
                    $gananciasAnterior["total_ganancias_anterior"]) /
                    $gananciasAnterior["total_ganancias_anterior"]) *
                100;
        }

        // KPI: Facturas
        $queryFacturasPendientes = "
            SELECT COUNT(*) as facturas_pendientes
            FROM facturas
            WHERE estado = 'Pendiente'
        ";
        $resultFacturasPendientes = mysqli_query(
            $conn,
            $queryFacturasPendientes
        );
        $facturasPendientes = mysqli_fetch_assoc($resultFacturasPendientes);

        // KPI: Productos
        $queryProductosVendidos = "
            SELECT SUM(cantidad) as total_vendidos
            FROM facturas_detalles fd
            INNER JOIN facturas f ON fd.numFactura = f.numFactura
            WHERE f.fecha BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
            AND f.estado != 'Cancelada'
        ";
        $resultProductosVendidos = mysqli_query($conn, $queryProductosVendidos);
        $productosVendidos = mysqli_fetch_assoc($resultProductosVendidos);

        $queryProductosBajoStock = "
            SELECT COUNT(*) as bajo_stock
            FROM inventario
            WHERE (SELECT existencia FROM productos WHERE id = inventario.idProducto) <= (SELECT reorden FROM productos WHERE id = inventario.idProducto)
        ";
        $resultProductosBajoStock = mysqli_query(
            $conn,
            $queryProductosBajoStock
        );
        $productosBajoStock = mysqli_fetch_assoc($resultProductosBajoStock);

        // KPI: Clientes
        $queryClientesActivos = "
            SELECT COUNT(*) as total_clientes
            FROM clientes
            WHERE activo = 1
        ";
        $resultClientesActivos = mysqli_query($conn, $queryClientesActivos);
        $clientesActivos = mysqli_fetch_assoc($resultClientesActivos);

        $queryClientesNuevos = "
            SELECT COUNT(*) as clientes_nuevos
            FROM clientes
            WHERE fechaRegistro BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
        ";
        $resultClientesNuevos = mysqli_query($conn, $queryClientesNuevos);
        $clientesNuevos = mysqli_fetch_assoc($resultClientesNuevos);

        // KPI: Cuentas por Cobrar
        $queryPorCobrar = "
            SELECT 
                SUM(balance) as total_por_cobrar,
                COUNT(DISTINCT idCliente) as clientes_con_deuda
            FROM facturas
            WHERE balance > 0
            AND estado = 'Pendiente'
        ";
        $resultPorCobrar = mysqli_query($conn, $queryPorCobrar);
        $porCobrar = mysqli_fetch_assoc($resultPorCobrar);

        // Respuesta
        $response = [
            "success" => true,
            "data" => [
                "ventas" => [
                    "total" => floatval($ventas["total_ventas"] ?? 0),
                    "tendencia" => round($tendenciaVentas, 1),
                ],
                "ganancias" => [
                    "total" => floatval($ganancias["total_ganancias"] ?? 0),
                    "tendencia" => round($tendenciaGanancias, 1),
                ],
                "facturas" => [
                    "total" => intval($ventas["num_facturas"] ?? 0),
                    "pendientes" => intval(
                        $facturasPendientes["facturas_pendientes"] ?? 0
                    ),
                ],
                "productos" => [
                    "vendidos" => intval(
                        $productosVendidos["total_vendidos"] ?? 0
                    ),
                    "bajo_stock" => intval(
                        $productosBajoStock["bajo_stock"] ?? 0
                    ),
                ],
                "clientes" => [
                    "total" => intval($clientesActivos["total_clientes"] ?? 0),
                    "nuevos" => intval($clientesNuevos["clientes_nuevos"] ?? 0),
                ],
                "cuentas" => [
                    "por_cobrar" => floatval(
                        $porCobrar["total_por_cobrar"] ?? 0
                    ),
                    "clientes_deuda" => intval(
                        $porCobrar["clientes_con_deuda"] ?? 0
                    ),
                ],
            ],
        ];

        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "message" => "Error al obtener KPIs: " . $e->getMessage(),
        ]);
    }
}

// ====================================
// GRÁFICOS
// ====================================
elseif ($action === "getCharts") {
    $periodo = $_GET["periodo"] ?? "mes";
    $fechaInicio = $_GET["fechaInicio"] ?? null;
    $fechaFin = $_GET["fechaFin"] ?? null;
    $rango = obtenerRangoFechas($periodo, $fechaInicio, $fechaFin);

    try {
        // Gráfico: Ventas Diarias
        $queryVentasDiarias = "
        SELECT 
            DATE(fecha) as dia,
            SUM(total) as total_ventas
        FROM facturas
        WHERE fecha BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
        AND estado != 'Cancelada'
        GROUP BY DATE(fecha)
        ORDER BY dia ASC
    ";
        $resultVentasDiarias = mysqli_query($conn, $queryVentasDiarias);

        $ventasDiariasLabels = [];
        $ventasDiariasData = [];
        while ($row = mysqli_fetch_assoc($resultVentasDiarias)) {
            $ventasDiariasLabels[] = date("d/m", strtotime($row["dia"]));
            $ventasDiariasData[] = floatval($row["total_ventas"]);
        }

        // Gráfico: Top 10 Productos
        $queryTopProductos = "
        SELECT 
            p.descripcion,
            SUM(fd.cantidad) as total_cantidad
        FROM facturas_detalles fd
        INNER JOIN facturas f ON fd.numFactura = f.numFactura
        INNER JOIN productos p ON fd.idProducto = p.id
        WHERE f.fecha BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
        AND f.estado != 'Cancelada'
        GROUP BY fd.idProducto
        ORDER BY total_cantidad DESC
        LIMIT 10
    ";
        $resultTopProductos = mysqli_query($conn, $queryTopProductos);

        $topProductosLabels = [];
        $topProductosData = [];
        while ($row = mysqli_fetch_assoc($resultTopProductos)) {
            $topProductosLabels[] = $row["descripcion"];
            $topProductosData[] = floatval($row["total_cantidad"]);
        }

        // Gráfico: Estado de Facturas
        $queryEstadoFacturas = "
        SELECT 
            estado,
            COUNT(*) as cantidad
        FROM facturas
        WHERE fecha BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
        GROUP BY estado
    ";
        $resultEstadoFacturas = mysqli_query($conn, $queryEstadoFacturas);

        $estadoFacturasLabels = [];
        $estadoFacturasData = [];
        while ($row = mysqli_fetch_assoc($resultEstadoFacturas)) {
            $estadoFacturasLabels[] = $row["estado"];
            $estadoFacturasData[] = intval($row["cantidad"]);
        }

        // Gráfico: Ventas por Empleado
        $queryVentasEmpleado = "
        SELECT 
            CONCAT(e.nombre, ' ', e.apellido) as empleado,
            SUM(f.total) as total_ventas
        FROM facturas f
        INNER JOIN empleados e ON f.idEmpleado = e.id
        WHERE f.fecha BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
        AND f.estado != 'Cancelada'
        GROUP BY f.idEmpleado
        ORDER BY total_ventas DESC
    ";
        $resultVentasEmpleado = mysqli_query($conn, $queryVentasEmpleado);

        $ventasEmpleadoLabels = [];
        $ventasEmpleadoData = [];
        while ($row = mysqli_fetch_assoc($resultVentasEmpleado)) {
            $ventasEmpleadoLabels[] = $row["empleado"];
            $ventasEmpleadoData[] = floatval($row["total_ventas"]);
        }

        // Gráfico: Flujo de Caja
        $queryFlujoCaja = "
        SELECT 
            DATE(fecha) as dia,
            SUM(monto) as total_ingresos
        FROM cajaingresos
        WHERE fecha BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
        GROUP BY DATE(fecha)
        ORDER BY dia ASC
    ";
        $resultFlujoCaja = mysqli_query($conn, $queryFlujoCaja);

        $flujoCajaLabels = [];
        $flujoCajaIngresos = [];
        while ($row = mysqli_fetch_assoc($resultFlujoCaja)) {
            $flujoCajaLabels[] = date("d/m", strtotime($row["dia"]));
            $flujoCajaIngresos[] = floatval($row["total_ingresos"]);
        }

        $queryFlujoCajaEgresos = "
        SELECT 
            DATE(fecha) as dia,
            SUM(monto) as total_egresos
        FROM cajaegresos
        WHERE fecha BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
        GROUP BY DATE(fecha)
        ORDER BY dia ASC
    ";
        $resultFlujoCajaEgresos = mysqli_query($conn, $queryFlujoCajaEgresos);

        $flujoCajaEgresos = [];
        $egresosMap = [];
        while ($row = mysqli_fetch_assoc($resultFlujoCajaEgresos)) {
            $egresosMap[date("d/m", strtotime($row["dia"]))] = floatval(
                $row["total_egresos"]
            );
        }

        // Alinear egresos con ingresos
        foreach ($flujoCajaLabels as $label) {
            $flujoCajaEgresos[] = $egresosMap[$label] ?? 0;
        }

        // Respuesta
        $response = [
            "success" => true,
            "data" => [
                "ventas_diarias" => [
                    "labels" => $ventasDiariasLabels,
                    "data" => $ventasDiariasData,
                ],
                "top_productos" => [
                    "labels" => $topProductosLabels,
                    "data" => $topProductosData,
                ],
                "estado_facturas" => [
                    "labels" => $estadoFacturasLabels,
                    "data" => $estadoFacturasData,
                ],
                "ventas_empleado" => [
                    "labels" => $ventasEmpleadoLabels,
                    "data" => $ventasEmpleadoData,
                ],
                "flujo_caja" => [
                    "labels" => $flujoCajaLabels,
                    "ingresos" => $flujoCajaIngresos,
                    "egresos" => $flujoCajaEgresos,
                ],
            ],
        ];

        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "message" => "Error al obtener gráficos: " . $e->getMessage(),
        ]);
    }
}

// ====================================
// TABLAS
// ====================================
elseif ($action === "getTables") {
    $periodo = $_GET["periodo"] ?? "mes";
    $fechaInicio = $_GET["fechaInicio"] ?? null;
    $fechaFin = $_GET["fechaFin"] ?? null;

    $rango = obtenerRangoFechas($periodo, $fechaInicio, $fechaFin);

    try {
        // Tabla: Top 10 Clientes
        $queryTopClientes = "
        SELECT 
            c.id,
            CONCAT(c.nombre, ' ', c.apellido) as nombre,
            SUM(f.total) as total_compras,
            COUNT(f.registro) as num_facturas
        FROM facturas f
        INNER JOIN clientes c ON f.idCliente = c.id
        WHERE f.fecha BETWEEN '{$rango["inicio"]}' AND '{$rango["fin"]}'
        AND f.estado != 'Cancelada'
        GROUP BY f.idCliente
        ORDER BY total_compras DESC
        LIMIT 10
    ";
        $resultTopClientes = mysqli_query($conn, $queryTopClientes);

        $topClientes = [];
        while ($row = mysqli_fetch_assoc($resultTopClientes)) {
            $topClientes[] = $row;
        }

        // Tabla: Stock Bajo
        $queryStockBajo = "
        SELECT 
            p.descripcion,
            p.existencia,
            p.reorden
        FROM inventario i
        INNER JOIN productos p ON i.idProducto = p.id
        WHERE p.existencia <= p.reorden
        ORDER BY i.existencia ASC
        LIMIT 10
    ";
        $resultStockBajo = mysqli_query($conn, $queryStockBajo);

        $stockBajo = [];
        while ($row = mysqli_fetch_assoc($resultStockBajo)) {
            $stockBajo[] = $row;
        }

        // Tabla: Facturas Pendientes
        $queryFacturasPendientes = "
        SELECT 
            f.numFactura,
            CONCAT(c.nombre, ' ', c.apellido) as cliente,
            f.fecha,
            f.total,
            f.balance
        FROM facturas f
        INNER JOIN clientes c ON f.idCliente = c.id
        WHERE f.estado = 'Pendiente'
        AND f.balance > 0
        ORDER BY f.fecha ASC
        LIMIT 20
    ";
        $resultFacturasPendientes = mysqli_query(
            $conn,
            $queryFacturasPendientes
        );

        $facturasPendientes = [];
        while ($row = mysqli_fetch_assoc($resultFacturasPendientes)) {
            $facturasPendientes[] = $row;
        }

        // Respuesta
        $response = [
            "success" => true,
            "data" => [
                "top_clientes" => $topClientes,
                "stock_bajo" => $stockBajo,
                "facturas_pendientes" => $facturasPendientes,
            ],
        ];

        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "message" => "Error al obtener tablas: " . $e->getMessage(),
        ]);
    }
}

// ====================================
// ALERTAS
// ====================================
elseif ($action === "getAlerts") {
    try {
        $alerts = [];
        // Alerta: Productos con stock crítico
        $queryStockCritico = "
        SELECT COUNT(*) as cantidad
        FROM inventario i
        INNER JOIN productos p ON i.idProducto = p.id
        WHERE i.existencia <= (p.reorden * 0.5)
    ";
        $resultStockCritico = mysqli_query($conn, $queryStockCritico);
        $stockCritico = mysqli_fetch_assoc($resultStockCritico);

        if ($stockCritico["cantidad"] > 0) {
            $alerts[] = [
                "tipo" => "danger",
                "titulo" => "Stock Crítico",
                "mensaje" => "Hay {$stockCritico["cantidad"]} producto(s) con stock crítico que requieren atención inmediata.",
            ];
        }

        // Alerta: Facturas vencidas
        $queryFacturasVencidas = "
        SELECT COUNT(*) as cantidad
        FROM facturas
        WHERE estado = 'Pendiente'
        AND balance > 0
        AND DATEDIFF(NOW(), fecha) > 30
    ";
        $resultFacturasVencidas = mysqli_query($conn, $queryFacturasVencidas);
        $facturasVencidas = mysqli_fetch_assoc($resultFacturasVencidas);

        if ($facturasVencidas["cantidad"] > 0) {
            $alerts[] = [
                "tipo" => "warning",
                "titulo" => "Facturas Vencidas",
                "mensaje" => "Hay {$facturasVencidas["cantidad"]} factura(s) con más de 30 días pendientes de pago.",
            ];
        }

        // Alerta: Cajas con diferencias
        $queryCajasDiferencias = "
        SELECT COUNT(*) as cantidad
        FROM cajascerradas
        WHERE ABS(diferencia) > 100
        AND DATE(fechaCierre) = CURDATE()
    ";
        $resultCajasDiferencias = mysqli_query($conn, $queryCajasDiferencias);
        $cajasDiferencias = mysqli_fetch_assoc($resultCajasDiferencias);

        if ($cajasDiferencias["cantidad"] > 0) {
            $alerts[] = [
                "tipo" => "warning",
                "titulo" => "Diferencias en Cajas",
                "mensaje" => "Se detectaron {$cajasDiferencias["cantidad"]} caja(s) con diferencias significativas hoy.",
            ];
        }

        // Alerta: Cotizaciones pendientes
        $queryCotizacionesPendientes = "
        SELECT COUNT(*) as cantidad
        FROM cotizaciones_inf
        WHERE estado = 'pendiente'
        AND DATEDIFF(NOW(), fecha) <= 7
    ";
        $resultCotizacionesPendientes = mysqli_query(
            $conn,
            $queryCotizacionesPendientes
        );
        $cotizacionesPendientes = mysqli_fetch_assoc(
            $resultCotizacionesPendientes
        );

        if ($cotizacionesPendientes["cantidad"] > 0) {
            $alerts[] = [
                "tipo" => "info",
                "titulo" => "Cotizaciones Pendientes",
                "mensaje" => "Tienes {$cotizacionesPendientes["cantidad"]} cotización(es) reciente(s) pendiente(s) de conversión.",
            ];
        }

        $response = [
            "success" => true,
            "data" => $alerts,
        ];

        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "message" => "Error al obtener alertas: " . $e->getMessage(),
        ]);
    }
}
// Acción no válida
else {
    echo json_encode([
        "success" => false,
        "message" => "Acción no válida",
    ]);
}
?>
