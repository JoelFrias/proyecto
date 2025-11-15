<?php

    /* Verificacion de sesion */

    // Iniciar sesión
    session_start();

    // Configurar el tiempo de caducidad de la sesión
    $inactivity_limit = 9000; // 15 minutos en segundos

    // Verificar si el usuario ha iniciado sesión
    if (!isset($_SESSION['username'])) {
        session_unset(); // Eliminar todas las variables de sesión
        session_destroy(); // Destruir la sesión
        header('Location: ../../frontend/auth/login.php'); // Redirigir al login
        exit(); // Detener la ejecución del script
    }

    // Verificar si la sesión ha expirado por inactividad
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
        session_unset(); // Eliminar todas las variables de sesión
        session_destroy(); // Destruir la sesión
        header("Location: ../../frontend/auth/login.php?session_expired=session_expired"); // Redirigir al login
        exit(); // Detener la ejecución del script
    }

    // Actualizar el tiempo de la última actividad
    $_SESSION['last_activity'] = time();

    /* Fin de verificacion de sesion */

    include_once '../../core/conexion.php';

    ////////////////////////////////////////////////////////////////////
    ///////////////////// VALIDACION DE PERMISOS ///////////////////////
    ////////////////////////////////////////////////////////////////////

    require_once '../../core/validar-permisos.php';
    $permiso_necesario = 'CLI003';
    $id_empleado = $_SESSION['idEmpleado'];
    if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
        echo "
            <html>
                <head>
                    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
                </head>
                <body>
                    <script>
                        Swal.fire({
                            icon: 'error',
                            title: 'ACCESO DENEGADO',
                            text: 'No tienes permiso para acceder a esta sección.',
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            window.history.back();
                        });
                    </script>
                </body>
            </html>";
            
        exit(); 
    }

    ////////////////////////////////////////////////////////////////////

    // Variables
    $idCliente = $_GET["idCliente"];

    // Tabla Payment History
    $sqlph = "SELECT
                DATE_FORMAT(chp.fecha, '%d/%m/%Y %l:%i %p') AS fechaph,
                chp.metodo AS metodoph,
                chp.monto AS montoph
            FROM
                clientes_historialpagos AS chp
            WHERE
                chp.idCliente = ?
            ORDER BY
                chp.fecha
            DESC
            LIMIT 5";
    $stmtph = $conn->prepare($sqlph);
    $stmtph->bind_param("i", $idCliente);
    $stmtph->execute();
    $resultsph = $stmtph->get_result();

    // Tabla Facturas Pendientes
    $sqlf = "SELECT
                f.numFactura AS nf,
                DATE_FORMAT(f.fecha, '%d/%m/%Y %l:%i %p') AS fechaf,
                f.total_ajuste AS totalf,
                f.balance AS balancef
            FROM
                facturas AS f
            WHERE
                f.balance > 0 AND f.idCliente = ?
            ORDER BY
                f.fecha
            DESC";
    $stmtf = $conn->prepare($sqlf);
    $stmtf->bind_param("i", $idCliente);
    $stmtf->execute();
    $resultsf = $stmtf->get_result();

    
    // Informacion del cliente
    $sqlc = "SELECT
                c.id AS idc,
                CONCAT(c.nombre, ' ', c.apellido) AS nombrec,
                c.empresa AS empresac,
                c.telefono AS telefonoc,
                cc.limite_credito AS limitec,
                cc.balance AS balancec,
                COALESCE(SUM(f.balance),
                0) AS adeudadoc
            FROM
                clientes AS c
            LEFT JOIN clientes_cuenta AS cc
            ON
                cc.idCliente = c.id
            LEFT JOIN facturas AS f
            ON
                f.idCliente = c.id
            WHERE
                c.id = ?";
    $stmtc = $conn->prepare($sqlc);
    $stmtc->bind_param("i", $idCliente);
    $stmtc->execute();
    $resultsc = $stmtc->get_result();
    $rowc = $resultsc->fetch_assoc();

    $montodeuda = $rowc['adeudadoc']; // Variable almacenada para calculos

    // FORMATO DE MONEDA
    $limitec = number_format($rowc['limitec'], 2, '.', ',');
    $balancec = number_format($rowc['balancec'], 2, '.', ',');
    $adeudadoc = number_format($rowc['adeudadoc'], 2, '.', ',');


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Avance de Cuenta</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/avance-cuenta.css">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->

    <style>

        /* Base table styles */
        .table-container {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 1rem;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
            min-width: 100%;
        }

        .table-container th, 
        .table-container td {
            padding: 0.75rem;
            vertical-align: top;
            white-space: nowrap;
        }

        /* Estilos para el Modal de Historial de Pagos */

        /* Fondo del modal */
        .modal-history-payment {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s;
        }

        /* Contenido del modal */
        .modal-content-history-payments {
            background-color: #fff;
            margin: 5% auto;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 85%;
            max-width: 1200px;
            position: relative;
            animation: slideDown 0.4s;
            overflow: hidden;
        }

        /* Botón de cerrar */
        .close-modal-history-payments {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            color: #888;
            cursor: pointer;
            transition: color 0.2s;
        }

        .close-modal-history-payments:hover {
            color: #333;
        }

        /* Estilos para la tabla de pagos */
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .payment-table thead {
            background-color: #f8f9fa;
        }

        .payment-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e3e6f0;
        }

        .payment-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #e3e6f0;
            color: #555;
        }

        .payment-table tbody tr:hover {
            background-color: #f8f9fb;
        }

        /* Botón de cancelar pago */
        .btn-cancel {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background-color 0.2s;
        }

        .btn-cancel:hover {
            background-color: #c82333;
        }

        /* Estilos para la paginación */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 25px;
            gap: 8px;
        }

        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 14px;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .pagination a {
            background-color: #f8f9fa;
            color: #007bff;
            border: 1px solid #dee2e6;
        }

        .pagination a:hover {
            background-color: #e9ecef;
            border-color: #ced4da;
        }

        .pagination span.current {
            background-color: #007bff;
            color: white;
            border: 1px solid #007bff;
        }

        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Responsive design */
        @media screen and (max-width: 992px) {
            .modal-content-history-payments {
                width: 95%;
                margin: 5% auto;
                padding: 15px;
            }
            
            .payment-table th, .payment-table td {
                padding: 8px 10px;
                font-size: 14px;
            }
        }

        @media screen and (max-width: 768px) {
            .modal-content-history-payments {
                padding: 12px;
                margin: 0;
                width: 100%;
                height: 100%;
                max-height: 100vh;
                border-radius: 0;
                overflow-y: auto;
            }
            
            .modal-history-payment {
                padding: 0;
            }
            
            .payment-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                margin-top: 10px;
            }
            
            .btn-cancel {
                padding: 5px 10px;
                font-size: 12px;
            }
            
            .pagination a, .pagination span {
                padding: 6px 10px;
                font-size: 14px;
            }
            
            .close-modal-history-payments {
                top: 10px;
                right: 15px;
            }
        }
        
    </style>

</head>
<body>

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../frontend/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

        

            <div class="contenedor">
                
                <div class="flex-container">
                    <!-- Client Data Section -->
                    <div class="client-data">
                        <!-- Your existing client data content -->
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                                <rect width="16" height="20" x="4" y="2" rx="2" ry="2"></rect>
                                <path d="M9 22v-4h6v4"></path>
                                <path d="M8 6h.01"></path>
                                <path d="M16 6h.01"></path>
                                <path d="M12 6h.01"></path>
                                <path d="M12 10h.01"></path>
                                <path d="M12 14h.01"></path>
                                <path d="M16 10h.01"></path>
                                <path d="M16 14h.01"></path>
                                <path d="M8 10h.01"></path>
                                <path d="M8 14h.01"></path>
                            </svg>
                            Datos del Cliente
                        </h3>
                        <div class="form-row">
                            <label>ID:</label>
                            <div><?php echo $rowc['idc'] ?></div>
                        </div>
                        <div class="form-row">
                            <label>Nombre:</label>
                            <div><?php echo $rowc['nombrec'] ?></div>
                        </div>
                        <div class="form-row">
                            <label>Empresa:</label>
                            <div><?php echo $rowc['empresac'] ?></div>
                        </div>
                        <div class="form-row">
                            <label>Teléfono:</label>
                            <div><?php echo $rowc['telefonoc'] ?></div>
                        </div>
                        <div class="form-row credit-limit">
                            <label>Límite de Crédito:</label>
                            <div><?php echo "RD$ " .$limitec ?></div>
                        </div>
                        <div class="form-row balance-available">
                            <label>Balance Disponible:</label>
                            <div><?php echo "RD$ " .$balancec ?></div>
                        </div>
                        <div class="form-row amount-due">
                            <label>Monto Total Adeudado:</label>
                            <div><?php echo "RD$ " .$adeudadoc ?></div>
                        </div>
                    </div>
                    
                    <!-- Payment Section -->
                    <div class="payment-section">
                        
                        <div class="payment-input">
                            <div class="column-left">
                                <!-- Elementos de la columna izquierda -->
                                <div class="form-field">
                                    <label>Método de Pago:</label>
                                    <select id="forma-pago" name="forma-pago">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="transferencia">Transferencia</option>
                                        <option value="tarjeta">Tarjeta</option>
                                    </select>
                                </div>

                                <div id="banco-div" class="form-field" style="display: none;">
                                    <label>Banco:</label>
                                    <select name="banco" id="banco">
                                        <option value="1" disabled selected>Seleccionar</option>
                                        <?php
                                            $sql = "SELECT * FROM bancos WHERE id <> 1 AND enable = TRUE ORDER BY id ASC";
                                            $resultado = $conn->query($sql);
                                            if ($resultado->num_rows > 0) {
                                                while ($fila = $resultado->fetch_assoc()) {
                                                echo "<option value='" . $fila['id'] . "'>" . $fila['nombreBanco'] . "</option>";
                                                }
                                            } else {
                                                echo "<option value='' disabled>No hay opciones</option>";
                                            }
                                        ?>
                                    </select>
                                </div>

                                <div id="num-auto-div" class="form-field" style="display: none;">
                                    <label>Número de autorización:</label>
                                    <input type="number" id="num-auto" name="num-auto" min="0" max="9999" minlength="4" maxlength="4" placeholder="Ultimos 4 números">
                                </div>
                                
                            </div>
                            
                            <div class="column-right">
                                <!-- Elementos de la columna derecha -->
                                
                                <div id="monto-div" class="form-field">
                                    <label>Monto Pagado:</label>
                                    <div class="input-group">
                                        <input type="number" id="monto-pagado" name="monto-pagado" min="0" placeholder="Monto Pagado" autocomplete="off">
                                    </div>
                                </div>

                                <div id="destino-div" class="form-field" style="display: none;">
                                    <label>Destino:</label>
                                    <select name="destino" id="destino">
                                        <option value="1" disabled selected>Seleccionar</option>
                                        <?php
                                            $sql = "SELECT * FROM destinocuentas WHERE id <> 1 AND enable = TRUE ORDER BY id ASC";
                                            $resultado = $conn->query($sql);
                                            if ($resultado->num_rows > 0) {
                                                while ($fila = $resultado->fetch_assoc()) {
                                                echo "<option value='" . $fila['id'] . "'>" . $fila['descripcion'] . "</option>";
                                                }
                                            } else {
                                                echo "<option value='' disabled>No hay opciones</option>";
                                            }
                                        ?>
                                    </select>
                                </div>

                                <div id="num-tarjeta-div" class="form-field" style="display: none">
                                    <label>Número de tarjeta:</label>
                                    <input type="number" id="num-tarjeta" name="num-tarjeta" min="0" max="9999" minlength="4" maxlength="4" placeholder="Ultimos 4 digitos">
                                </div>
                                
                            </div>
                            
                        </div>
                        <div id="devuelta-div">
                            <label for="devuelta">Devuelta:</label>
                            <span id="devuelta">RD$ .00</span>
                        </div>
                        <div class="button-group">
                            <button class="btn btn-primary" onclick="procesarPago(false)">Procesar Pago</button>
                            <button class="btn btn-secondary" onclick="procesarPago(true)">Procesar e Imprimir</button>
                        </div>
                    </div>
                </div>
                
                <!-- History Tables Section -->
                <div class="history-tables">
                    <div class="history-table">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            Historial de Pagos
                        </h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Fecha y Hora</th>
                                        <th>Método</th>
                                        <th>Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        if($resultsph->num_rows > 0){
                                            while ($rowph = $resultsph->fetch_assoc()) {
                                                $montoph = number_format($rowph['montoph'], 2, '.', ',');
                                                echo "
                                                    <tr>
                                                        <td>{$rowph['fechaph']}</td>
                                                        <td>{$rowph['metodoph']}</td>
                                                        <td>RD$ {$montoph}</td>
                                                    </tr>
                                                ";
                                            }
                                        } else {
                                            echo "<tr><td colspan='3'>No se encontraron resultados.</td></tr>";
                                        }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top: 1rem">
                            <button class="btn btn-secondary" id="show-more-modal">Ver más</button>
                        </div>
                    </div>

                    <!-- Modal for Payment History -->
                    <div class="modal-history-payment">
                        <div class="modal-content-history-payments">
                            <span class="close-modal-history-payments">&times;</span>

                            <?php 
                                // Your existing PHP code for pagination
                                $registrosPorPagina = 10;
                                $paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
                                $offset = ($paginaActual - 1) * $registrosPorPagina;

                                $sqlTotal = "SELECT COUNT(*) AS total FROM clientes_historialpagos WHERE idCliente = ?";
                                $stmtTotal = $conn->prepare($sqlTotal);
                                $stmtTotal->bind_param("i", $idCliente);
                                $stmtTotal->execute();
                                $resultTotal = $stmtTotal->get_result();
                                $totalRegistros = $resultTotal->fetch_assoc()['total'];

                                $totalPaginas = ceil($totalRegistros / $registrosPorPagina);

                                $sql = "SELECT
                                            chp.registro AS id,
                                            DATE_FORMAT(chp.fecha, '%d/%m/%Y %l:%i %p') AS fechachp,
                                            chp.metodo AS metodochp,
                                            chp.monto AS montochp,
                                            chp.numAutorizacion AS autorizacionchp,
                                            chp.referencia AS tarjetachp,
                                            b.nombreBanco AS bancochp,
                                            d.descripcion AS destinochp,
                                            CONCAT(e.nombre, ' ', e.apellido) AS nombree
                                        FROM
                                            clientes_historialpagos AS chp
                                        JOIN bancos AS b ON chp.idBanco = b.id
                                        JOIN destinocuentas AS d ON chp.idDestino = d.id
                                        JOIN empleados AS e ON e.id = chp.idEmpleado
                                        WHERE
                                            chp.idCliente = ?
                                        ORDER BY
                                            chp.fecha DESC
                                        LIMIT ? OFFSET ?";

                                $stmt = $conn->prepare($sql);
                                if (!$stmt) {
                                    die("Error en la preparación de la consulta: " . $conn->error);
                                }

                                $stmt->bind_param("iii", $idCliente, $registrosPorPagina, $offset);
                                if (!$stmt->execute()) {
                                    die("Error al ejecutar la consulta: " . $stmt->error);
                                }
                                $result = $stmt->get_result();
                            ?>

                            <table class="payment-table">
                                <thead>
                                    <tr>

                                        <?php 

                                        require_once '../../core/validar-permisos.php';
                                        $permiso_necesario = 'CLI004';
                                        $id_empleado = $_SESSION['idEmpleado'];
                                        if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                        ?>

                                        <th>Acción</th>

                                        <?php endif; ?>

                                        <th>Fecha</th>
                                        <th>Método</th>
                                        <th>Monto</th>
                                        <th>Empleado</th>
                                        <th>Autorización</th>
                                        <th>Referencia</th>
                                        <th>Banco</th>
                                        <th>Destino</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <?php 

                                            require_once '../../core/validar-permisos.php';
                                            $permiso_necesario = 'CLI004';
                                            $id_empleado = $_SESSION['idEmpleado'];
                                            if (validarPermiso($conn, $permiso_necesario, $id_empleado)):

                                            ?>

                                            <td><button onclick="cancelpayment(<?= $row['id'] ?>)" class="btn-cancel">Cancelar Pago</button></td>

                                            <?php endif; ?>
                                            
                                            <td><?php echo htmlspecialchars($row['fechachp']); ?></td>
                                            <td><?php echo htmlspecialchars($row['metodochp']); ?></td>
                                            <td><?php echo htmlspecialchars($row['montochp']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nombree']); ?></td>
                                            <td><?php echo htmlspecialchars($row['autorizacionchp']); ?></td>
                                            <td><?php echo htmlspecialchars($row['tarjetachp']); ?></td>
                                            <td><?php echo htmlspecialchars($row['bancochp']); ?></td>
                                            <td><?php echo htmlspecialchars($row['destinochp']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>

                            <div class="pagination">
                                <?php
                                // Botón Anterior
                                if ($paginaActual > 1) {
                                    echo "<a href='?idCliente=".($idCliente)."&pagina=" . ($paginaActual - 1) . "&modal=true" . "'>Anterior</a>";
                                }

                                // Números de página
                                for ($i = 1; $i <= $totalPaginas; $i++) {
                                    if ($i == $paginaActual) {
                                        echo "<span class='current'> $i</span>";
                                    } else {
                                        echo "<a href='?idCliente=".($idCliente)."&pagina=$i&modal=true'>$i</a>";
                                    }
                                }

                                // Botón Siguiente
                                if ($paginaActual < $totalPaginas) {
                                    echo "<a href='?idCliente=".($idCliente)."&pagina=" . ($paginaActual + 1) . "&modal=true" . "'> Siguiente</a>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Invoices Table -->
                    <div class="history-table">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon">
                                <rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect>
                                <line x1="16" x2="16" y1="2" y2="6"></line>
                                <line x1="8" x2="8" y1="2" y2="6"></line>
                                <line x1="3" x2="21" y1="10" y2="10"></line>
                            </svg>
                            Facturas Pendientes
                        </h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No. Fact</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                        <th>Pendiente</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        if ($resultsf->num_rows > 0) {
                                            while ($rowf = $resultsf->fetch_assoc()) {
                                                $totalf = number_format($rowf['totalf'], 2, '.', ',');
                                                $balancef = number_format($rowf['balancef'], 2, '.', ',');
                                                echo "
                                                    <tr>
                                                        <td>{$rowf['nf']}</td>
                                                        <td>{$rowf['fechaf']}</td>
                                                        <td>RD$ {$totalf}</td>
                                                        <td class='amount-due'>RD$ {$balancef}</td>
                                                    </tr>
                                                ";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4'>No se encontraron resultados.</td></tr>";
                                        }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBAJO DE ESTA LINEA -->
        </div>
    </div>


    <?php
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if(isset($_GET['modal']) && $_GET['modal'] == "true") {
                echo "<script>document.querySelector('.modal-history-payment').style.display = 'flex';</script>";
            } else {
                echo "<script>document.querySelector('.modal-history-payment').style.display = 'none';</script>";
            }
        }
    ?>

    <!-- Script para mostrar y ocultar el modal de historial de pagos -->
    <script>
        // 
        const modal = document.querySelector('.modal-history-payment');
        const showModalBtn = document.getElementById('show-more-modal');
        const closeModalBtn = document.querySelector('.close-modal-history-payments');

        showModalBtn.addEventListener('click', () => {
            modal.style.display = 'flex';
        });

        closeModalBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>


    <script>

        // Variables globales
        let totalpagado = 0;
        let deuda = <?php echo $montodeuda ?>;

        // Script para mostrar el modal de historial de pagos
        const openBtn = document.getElementById("show-more-modal");
        const openModal = document.querySelector('.modal-history-payment');

        openBtn.addEventListener('click', () => {
            openModal.style.display = 'flex';
        });

        // Script para cerrar el modal de historial de pagos
        const Closeodal = document.querySelector('.modal-history-payment');
        const closeBtn = document.querySelector('.close-modal-history-payments');

        closeBtn.addEventListener('click', () => {
            window.location.href = '?idCliente=<?php echo $idCliente ?>';
        });

        window.onclick = function(event) {
            if (event.target == Closeodal) {
                window.location.href = '?idCliente=<?php echo $idCliente ?>';
            }
        }

        function procesarPago(print) {
            let idCliente = <?php echo $idCliente ?>;
            let formaPago = document.getElementById("forma-pago").value;
            let numeroTarjeta = document.getElementById("num-tarjeta").value;
            let numeroAutorizacion = document.getElementById("num-auto").value;
            let banco = document.getElementById("banco").value;
            let destino = document.getElementById("destino").value;

            // Validacion de seleccion de cliente
            if (!idCliente) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación',
                    text: 'Ningun cliente fue encontrado seleccionado.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            // Validar campos con tarjeta
            if (formaPago == "tarjeta" && (!numeroTarjeta || !numeroAutorizacion || banco == "1" || destino  == "1")){
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación',
                    text: 'Por favor, complete todos los campos obligatorios.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            // Validar campos por transferencia
            if (formaPago == "transferencia" && (!numeroAutorizacion || banco == "1" || banco == "1")){
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación',
                    text: 'Por favor, complete todos los campos obligatorios.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            // Validar si el cliente tiene deuda
            if (deuda <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación',
                    text: 'El cliente no tiene deuda pendiente.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            
            // Validar que el total pagado sea un número válido
            if (Number.isNaN(totalpagado) || totalpagado <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pago no válido',
                    text: 'No se ha registrado ningún pago',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            const datos = {
                idCliente,
                formaPago,
                montoPagado: totalpagado,
                numeroTarjeta: numeroTarjeta || null, 
                numeroAutorizacion: numeroAutorizacion || null, 
                banco: banco || null,
                destino: destino || null
            };

            fetch("../../api/clientes/cuentas_avance.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(datos)
            })
            .then(response => response.text())
            .then(text => {
                console.log("Respuesta completa del servidor:", text);
                try {
                    let data = JSON.parse(text);
                    if (data.success) {

                        if(print) {
                            const invoiceUrl = `../../reports/cliente/avance.php?registro=${data.data.idRegistro}`;
                            window.open(invoiceUrl, '_blank');
                            
                            location.reload();
                        } else {
                            // Mostrar mensaje de éxito
                            Swal.fire({
                                icon: 'success',
                                title: 'Éxito',
                                text: 'Pago realizado exitosamente.',
                                showConfirmButton: true,
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                location.reload();
                            });
                        }

                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.error,
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar'
                        });
                        console.error("Error al guardar la factura:", data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Se produjo un error inesperado en el servidor. Pago no realizado.',
                        showConfirmButton: true,
                        confirmButtonText: 'Aceptar'
                    });
                    console.error("Error: Respuesta no es JSON válido:", text);
                }
            }).catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Se produjo un error de red o en el servidor.\nPor favor, inténtelo de nuevo.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
                console.error("Error de red o servidor:", error);
            });
        }

        // Script para calcular la devuelta y el total ingresado
        document.getElementById("monto-pagado").addEventListener("keyup", () => {

            // Variables
            const montoPagado = parseFloat(document.getElementById("monto-pagado").value);
            let deuda = <?php echo $montodeuda ?>;

            // Calcular total ingresado
            let totaldevuelta = montoPagado - deuda;

            // Mostrar total ingresado
            if (totaldevuelta < 0) {
                document.getElementById("devuelta").textContent = "RD$ .00";
                totalpagado = montoPagado;
            } else {
                if (Number.isNaN(totaldevuelta)){
                    document.getElementById("devuelta").textContent = "RD$ .00";
                } else {
                    document.getElementById("devuelta").textContent = "RD$ " + totaldevuelta.toFixed(2);
                    totalpagado = montoPagado - totaldevuelta;
                }
            }

        });

        // Script para mostrar u ocultar campos de información de pagos
        const metodo = document.getElementById("forma-pago");
        const tarjeta = document.getElementById("num-tarjeta-div");
        const autorizacion = document.getElementById("num-auto-div");
        const banco = document.getElementById("banco-div");
        const destino = document.getElementById("destino-div");
        
        metodo.addEventListener("change", () => {
            if (metodo.value === "tarjeta") {
                tarjeta.style.display = "flex";
                autorizacion.style.display = "flex";
                banco.style.display = "flex";
                destino.style.display = "flex";

                document.getElementById("monto-pagado").value = "";
                document.getElementById("banco").value = "1";
                document.getElementById("destino").value = "1";
                document.getElementById("num-tarjeta").value = "";
                document.getElementById("num-auto").value = "";

            } else if (metodo.value === "transferencia") {
                tarjeta.style.display = "none";
                autorizacion.style.display = "flex";
                banco.style.display = "flex";
                destino.style.display = "flex";

                document.getElementById("monto-pagado").value = "";
                document.getElementById("banco").value = "1";
                document.getElementById("destino").value = "1";
                document.getElementById("num-tarjeta").value = "";
                document.getElementById("num-auto").value = "";

            } else {
                tarjeta.style.display = "none";
                autorizacion.style.display = "none";
                banco.style.display = "none";
                destino.style.display = "none";

                document.getElementById("monto-pagado").value = "";
                document.getElementById("banco").value = "1";
                document.getElementById("destino").value = "1";
                document.getElementById("num-tarjeta").value = "";
                document.getElementById("num-auto").value = "";

            }
        });


        /**
         * Script para manejar la cancelación de pagos de clientes
         */

        /**
         * Función para cancelar un pago
         * @param {number} idPago - ID del registro de pago a cancelar
         */
        function cancelpayment(idPago) {
            // Usar SweetAlert para la confirmación
            Swal.fire({
                title: '¿Está seguro?',
                text: '¿Desea cancelar este pago? Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, cancelar pago',
                cancelButtonText: 'No'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar indicador de carga si existe
                    const loadingElement = document.getElementById('loading');
                    if (loadingElement) {
                        loadingElement.style.display = 'block';
                    }
                    
                    // Realizar la petición al servidor
                    fetch('../../api/clientes/cancelar-pagos.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            registro_pago: idPago
                            // No es necesario enviar el empleado_id ya que se obtiene de la sesión en el backend
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Ocultar indicador de carga
                        if (loadingElement) {
                            loadingElement.style.display = 'none';
                        }
                        
                        if (data.success) {
                            // Mostrar mensaje de éxito
                            Swal.fire({
                                title: 'Éxito',
                                text: data.message,
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                // Recargar la página después de hacer clic en Aceptar
                                location.reload();
                            });
                        } else {
                            // Mostrar mensaje de error
                            Swal.fire({
                                title: 'Error',
                                text: data.message,
                                icon: 'error',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    })
                    .catch(error => {
                        // Ocultar indicador de carga
                        if (loadingElement) {
                            loadingElement.style.display = 'none';
                        }
                        
                        // Mostrar mensaje de error
                        Swal.fire({
                            title: 'Error',
                            text: 'Error de conexión con el servidor',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                        console.error('Error:', error);
                    });
                }
            });
        }
    </script>
    
</body>
</html>