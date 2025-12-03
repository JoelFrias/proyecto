<?php
// empleados-editar.php

require_once '../../core/conexion.php';		// Conexión a la base de datos

// Verificar conexión a la base de datos
if (!$conn || !$conn->connect_errno === 0) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos",
        "error_code" => "DATABASE_CONNECTION_ERROR"
    ]));
} // Conexión a la base de datos
require_once '../../core/verificar-sesion.php'; // Verificar Session

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'EMP001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
        
    exit(); 
}

// Obtener el ID del empleado desde la URL
$idEmpleado = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idEmpleado === 0) {
    header('Location: ../../app/empleados/empleados.php');
    exit();
}

// Obtener datos del empleado
require_once '../../core/conexion.php';		// Conexión a la base de datos

// Verificar conexión a la base de datos
if (!$conn || !$conn->connect_errno === 0) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos",
        "error_code" => "DATABASE_CONNECTION_ERROR"
    ]));
}

$sql = "SELECT e.*, u.username 
        FROM empleados e 
        INNER JOIN usuarios u ON e.id = u.idEmpleado 
        WHERE e.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idEmpleado);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ../../app/empleados/empleados.php');
    exit();
}

$empleado = $result->fetch_assoc();
$stmt->close();

// Obtener permisos actuales del empleado
$sql_permisos = "SELECT id_permiso FROM usuarios_permisos WHERE id_empleado = ?";
$stmt_permisos = $conn->prepare($sql_permisos);
$stmt_permisos->bind_param("i", $idEmpleado);
$stmt_permisos->execute();
$result_permisos = $stmt_permisos->get_result();

$permisos_activos = [];
while ($row = $result_permisos->fetch_assoc()) {
    $permisos_activos[] = $row['id_permiso'];
}
$stmt_permisos->close();

// Mapeo de códigos a nombres de permisos
$mapeoPermisos = [
    'CLI001' => 'clientes',
    'CLI002' => 'clientes-reporte',
    'PRO001' => 'productos',
    'PRO002' => 'productos-reporte',
    'CLI003' => 'avance-cuenta',
    'CLI004' => 'cancel-avance',
    'FAC002' => 'cancel-facturas',
    'ALM001' => 'almacen',
    'ALM003' => 'inv-empleados',
    'FAC001' => 'facturacion',
    'COT001' => 'cot-accion',
    'CAJ001' => 'caja',
    'PADM001' => 'pan-adm',
    'PADM002' => 'estadisticas',
    'PADM003' => 'bancos-destinos',
    'EMP001' => 'empleados',
    'FAC003' => 'inf-factura',
    'CUA001' => 'cuadres',
    'CUA002' => 'cuadres-accion',
    'COT002' => 'cot-registro',
    'COT003' => 'cot-cancelar',
    'ALM002' => 'tran-inventario',
    'ALM004' => 'entrada-inventario',
    'ALM005' => 'salida-inventario',
];

$permisos_checked = [];
foreach ($permisos_activos as $codigo) {
    if (isset($mapeoPermisos[$codigo])) {
        $permisos_checked[$mapeoPermisos[$codigo]] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Editar Empleado</title>
    <link rel="icon" href="../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/css/registro_empleados.css">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .btn-volver {
            background-color: #f5f5f5;
            border: 1px solid #ccc;
            color: #333;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s, box-shadow 0.2s;
            margin-right: 10px;
        }

        .btn-volver:hover {
            background-color: #e0e0e0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-volver:active {
            background-color: #d5d5d5;
        }

        .permisos-container {
            margin-top: 15px;
            margin-bottom: 25px;
        }

        .form-group-estado {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }

        .form-group-estado label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: 500;
        }

        .form-group-estado input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #28a745;
        }

        .permisos-container {
            margin-top: 15px;
            margin-bottom: 25px;
        }

        .permisos-seccion {
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .seccion-header {
            background-color: #f8f9fa;
            padding: 12px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .seccion-header i {
            color: #2c3e50;
            font-size: 18px;
        }

        .seccion-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .seccion-body {
            padding: 15px;
            background-color: #ffffff;
        }

        .permisos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
        }

        .permisos-grid label {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            gap: 10px;
        }

        .permisos-grid label:hover {
            background: #e7f3ff;
            border-color: #0066cc;
            transform: translateX(3px);
            box-shadow: 0 2px 8px rgba(0, 102, 204, 0.15);
        }

        .permisos-grid input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #0066cc;
            flex-shrink: 0;
        }

        .permisos-grid input[type="checkbox"]:checked + span {
            color: #0066cc;
            font-weight: 500;
        }

        .permisos-grid label span {
            flex: 1;
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .permisos-grid {
                grid-template-columns: 1fr;
            }

            .seccion-header {
                padding: 10px 15px;
            }

            .seccion-body {
                padding: 12px;
            }
        }

        @media (max-width: 480px) {
            .permisos-grid label {
                padding: 8px 10px;
                font-size: 13px;
            }

            .seccion-header h3 {
                font-size: 14px;
            }
        }

        .btn-submit:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-submit.loading {
            position: relative;
            color: transparent;
        }

        .btn-submit.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff40;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .password-note {
            font-size: 13px;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
    </style>
</head>
<body>

    <div class="navegator-nav">
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="form-container">
                <h2 class="form-title">Editar Empleado</h2><br>

                <legend>Datos del Empleado</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" autocomplete="off" placeholder="Nombre" value="<?php echo htmlspecialchars($empleado['nombre']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="apellido">Apellido:</label>
                        <input type="text" id="apellido" name="apellido" autocomplete="off" placeholder="Apellido" value="<?php echo htmlspecialchars($empleado['apellido']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_identificacion">Tipo de Identificación:</label>
                        <select id="tipo_identificacion" name="tipo_identificacion" required>
                            <option value="cedula" <?php echo $empleado['tipo_identificacion'] === 'Cedula' ? 'selected' : ''; ?>>Cédula</option>
                            <option value="pasaporte" <?php echo $empleado['tipo_identificacion'] === 'Pasaporte' ? 'selected' : ''; ?>>Pasaporte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="identificacion">Identificación:</label>
                        <input type="number" id="identificacion" name="identificacion" autocomplete="off" placeholder="Identificacion" min="0" value="<?php echo htmlspecialchars($empleado['identificacion']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono:</label>
                        <input type="text" id="telefono" name="telefono" autocomplete="off" placeholder="0000000000" minlength="10" maxlength="12" value="<?php echo htmlspecialchars($empleado['telefono']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="idPuesto">Puesto:</label>
                        <select id="idPuesto" name="idPuesto" required>
                            <option value="" disabled>Seleccione un puesto</option>
                            <?php
                            $sql_puestos = "SELECT id, descripcion FROM empleados_puestos ORDER BY descripcion ASC";
                            $resultado_puestos = $conn->query($sql_puestos);

                            if ($resultado_puestos->num_rows > 0) {
                                while ($fila = $resultado_puestos->fetch_assoc()) {
                                    $selected = ($fila['id'] == $empleado['idPuesto']) ? 'selected' : '';
                                    echo "<option value='" . $fila['id'] . "' $selected>" . $fila['descripcion'] . "</option>";
                                }
                            } else {
                                echo "<option value='' disabled>No hay opciones</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <legend>Datos de Usuario</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Usuario:</label>
                        <input type="text" id="username" name="username" placeholder="Usuario" autocomplete="off" value="<?php echo htmlspecialchars($empleado['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Nueva Contraseña (opcional):</label>
                        <input type="password" id="password" name="password" placeholder="Dejar vacío para no cambiar" autocomplete="off" minlength="4">
                        <p class="password-note">* Dejar en blanco si no desea cambiar la contraseña</p>
                    </div>
                </div>

                <div class="form-group-estado">
                    <label>
                        <input type="checkbox" id="activo" name="activo" <?php echo $empleado['activo'] ? 'checked' : ''; ?>>
                        <span>Empleado Activo</span>
                    </label>
                </div>

                <legend>Permisos de Usuario</legend>

                <div class="permisos-container">

                    <?php include 'layout-perfil-permisos.php'; ?>
                    
                    <!-- Sección: Clientes -->
                    <div class="permisos-seccion">
                        <div class="seccion-header">
                            <i class="fas fa-users"></i>
                            <h3>Gestión de Clientes</h3>
                        </div>
                        <div class="seccion-body">
                            <div class="permisos-grid">
                                <label>
                                    <input type="checkbox" name="permisos[clientes]" id="clientes">
                                    <span>Crear/Editar Clientes</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[clientes-reporte]" id="clientes-reporte">
                                    <span>Imprimir Reporte de Clientes</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[avance-cuenta]" id="avance-cuenta">
                                    <span>Avance de Cuenta</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[cancel-avance]" id="cancel-avance">
                                    <span>Cancelar Avance a Cuenta</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Productos e Inventario -->
                    <div class="permisos-seccion">
                        <div class="seccion-header">
                            <i class="fas fa-boxes"></i>
                            <h3>Productos e Inventario</h3>
                        </div>
                        <div class="seccion-body">
                            <div class="permisos-grid">
                                <label>
                                    <input type="checkbox" name="permisos[productos]" id="productos">
                                    <span>Crear/Editar Productos</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[productos-reporte]" id="productos-reporte">
                                    <span>Imprimir Reporte de Productos</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[almacen]" id="almacen">
                                    <span>Almacén Principal</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[inv-empleados]" id="inv-empleados">
                                    <span>Visualizar Inventario de Empleados</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[tran-inventario]" id="tran-inventario">
                                    <span>Transferencias de Inventario</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[entrada-inventario]" id="entrada-inventario">
                                    <span>Entrada de Inventario</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[salida-inventario]" id="salida-inventario">
                                    <span>Salida de Inventario</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Facturación y Ventas -->
                    <div class="permisos-seccion">
                        <div class="seccion-header">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <h3>Facturación y Cotizaciones</h3>
                        </div>
                        <div class="seccion-body">
                            <div class="permisos-grid">
                                <label>
                                    <input type="checkbox" name="permisos[facturacion]" id="facturacion">
                                    <span>Facturación</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[cancel-facturas]" id="cancel-facturas">
                                    <span>Cancelar Facturas</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[cot-accion]" id="cot-accion">
                                    <span>Crear/Vender Cotizaciones</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[cot-registro]" id="cot-registro">
                                    <span>Ver Registro de Cotizaciones</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[cot-cancelar]" id="cot-cancelar">
                                    <span>Cancelar Cotizaciones</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Caja -->
                    <div class="permisos-seccion">
                        <div class="seccion-header">
                            <i class="fas fa-cash-register"></i>
                            <h3>Gestión de Caja</h3>
                        </div>
                        <div class="seccion-body">
                            <div class="permisos-grid">
                                <label>
                                    <input type="checkbox" name="permisos[caja]" id="caja">
                                    <span>Abrir/Cerrar Caja</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[cuadres]" id="cuadres">
                                    <span>Cuadres de Caja</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[cuadres-accion]" id="cuadres-accion">
                                    <span>Cerrar/Cancelar Cuadres</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Administración -->
                    <div class="permisos-seccion">
                        <div class="seccion-header">
                            <i class="fas fa-cog"></i>
                            <h3>Administración del Sistema</h3>
                        </div>
                        <div class="seccion-body">
                            <div class="permisos-grid">
                                <label>
                                    <input type="checkbox" name="permisos[pan-adm]" id="pan-adm">
                                    <span>Panel Administrativo</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[estadisticas]" id="estadisticas">
                                    <span>Ver Estadísticas del Negocio</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[bancos-destinos]" id="bancos-destinos">
                                    <span>Administrar Bancos y Destinos</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[empleados]" id="empleados">
                                    <span>Administrar Empleados</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[inf-factura]" id="inf-factura">
                                    <span>Editar Información en Factura</span>
                                </label>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="btn-group">
                    <button type="button" class="btn-volver" onclick="window.location.href='empleados.php'">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>

                    <button type="submit" class="btn-submit" id="submitBtn" onclick="actualizarEmp()">
                        <i class="fas fa-save"></i> Actualizar Empleado
                    </button>
                </div>

            </div>
        </div>
    </div>

    <script>
        const idEmpleado = <?php echo $idEmpleado; ?>;

        function actualizarEmp(){
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');

            // Datos
            const nombre = document.getElementById('nombre').value;
            const apellido = document.getElementById('apellido').value;
            const tipo_identificacion = document.getElementById('tipo_identificacion').value;
            const identificacion = document.getElementById('identificacion').value;
            const telefono = document.getElementById('telefono').value;
            const idPuesto = document.getElementById('idPuesto').value;
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const activo = document.getElementById('activo').checked;
            
            const listPermisos = [
                'clientes', 'clientes-reporte', 'productos', 'productos-reporte',
                'avance-cuenta', 'cancel-avance', 'cancel-facturas', 'almacen',
                'inv-empleados', 'facturacion', 'cot-accion', 'caja', 'pan-adm', 
                'estadisticas', 'bancos-destinos', 'empleados', 
                'inf-factura', 'cuadres', 'cuadres-accion', 'cot-registro', 'cot-cancelar',
                'tran-inventario','entrada-inventario', 'salida-inventario'
            ];

            const permisos = {};
            listPermisos.forEach(permiso => {
                const checkbox = document.getElementById(permiso);
                permisos[permiso] = checkbox ? checkbox.checked : false;
            });

            // Enviar datos al servidor
            const url = '../../api/empleados/editar.php';

            fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json' 
                },
                body: JSON.stringify({
                    idEmpleado: idEmpleado,
                    nombre: nombre,
                    apellido: apellido,
                    tipo_identificacion: tipo_identificacion,
                    identificacion: identificacion,
                    telefono: telefono,
                    idPuesto: idPuesto,
                    username: username,
                    password: password,
                    activo: activo,
                    permisos: permisos
                })
            })
            .then(async response => {
                const data = await response.json().catch(() => ({})); 

                if (!response.ok) {
                    const errorMessage = data.message || `Error en la comunicación con el servidor (Código HTTP: ${response.status}).`;
                    
                    if (response.status === 400) {
                        let errorList = '<ul>' + data.errors.map(err => `<li>${err}</li>`).join('') + '</ul>';
                        Swal.fire({
                            icon: 'warning',
                            title: 'Validación de Datos Incompleta',
                            html: 'Se identificaron los siguientes requerimientos pendientes:<br>' + errorList,
                            confirmButtonText: 'Revisar'
                        });
                    } else if (response.status === 409) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Conflicto de Integridad de Datos',
                            text: errorMessage,
                            confirmButtonText: 'Aceptar'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de Procesamiento',
                            text: errorMessage,
                            confirmButtonText: 'Cerrar'
                        });
                    }

                    throw new Error(`Fallo en la Solicitud (HTTP ${response.status}): ${errorMessage}`);
                }

                return data; 
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Actualización Exitosa',
                        html: `El empleado ha sido actualizado satisfactoriamente.`,
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Fallo Lógico en la Aplicación',
                        text: data.message,
                        confirmButtonText: 'Cerrar'
                    });
                }
            })
            .catch(error => {
                if (!error.message.includes('HTTP')) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Interrupción de Conexión de Red',
                        text: 'No fue posible establecer comunicación con el servidor. Por favor, verifique su conexión e intente nuevamente.',
                        footer: `Detalle: ${error.message}`
                    });
                }
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            });
        }
    </script>

    <script>
        // Configuración de dependencias de permisos
        
        //const dependenciasPermisos = { 'avance-cuenta': ['cancel-avance'], 'facturacion': ['cot-accion'],'cot-registro': ['cot-cancelar'], 'pan-adm': ['estadisticas', 'bancos-destinos', 'empleados', 'inf-factura', 'cot-registro', 'tran-inventario', 'entrada-inventario', 'salida-inventario', 'cuadres' ], 'cuadres' : ['cuadres-accion'] };

        const dependenciasPermisos = {}; // Dependencias vacías por conflicto en perfiles de permisos

        // Permisos activos desde PHP
        const permisosActivos = <?php echo json_encode($permisos_checked); ?>;

        /**
         * Deshabilita y desmarca los permisos dependientes
         */
        function deshabilitarDependientes(permisoPrincipal) {
            const dependientes = dependenciasPermisos[permisoPrincipal];
            
            if (dependientes && dependientes.length > 0) {
                dependientes.forEach(permisoId => {
                    const checkbox = document.getElementById(permisoId);
                    if (checkbox) {
                        checkbox.checked = false;
                        checkbox.disabled = true;
                        checkbox.parentElement.style.opacity = '0.5';
                        checkbox.parentElement.style.cursor = 'not-allowed';
                        
                        // Si este permiso también tiene dependientes, deshabilitarlos recursivamente
                        if (dependenciasPermisos[permisoId]) {
                            deshabilitarDependientes(permisoId);
                        }
                    }
                });
            }
        }

        /**
         * Habilita los permisos dependientes
         */
        function habilitarDependientes(permisoPrincipal) {
            const dependientes = dependenciasPermisos[permisoPrincipal];
            
            if (dependientes && dependientes.length > 0) {
                dependientes.forEach(permisoId => {
                    const checkbox = document.getElementById(permisoId);
                    if (checkbox) {
                        checkbox.disabled = false;
                        checkbox.parentElement.style.opacity = '1';
                        checkbox.parentElement.style.cursor = 'pointer';
                    }
                });
            }
        }

        /**
         * Marca los checkboxes según los permisos activos del usuario
         */
        function cargarPermisosActivos() {
            for (const [permiso, activo] of Object.entries(permisosActivos)) {
                const checkbox = document.getElementById(permiso);
                if (checkbox && activo) {
                    checkbox.checked = true;
                }
            }
        }

        /**
         * Inicializa los listeners de todos los checkboxes de permisos
         */
        function inicializarLogicaPermisos() {
            // Primero cargar los permisos activos
            cargarPermisosActivos();

            // Agregar event listeners a todos los permisos principales
            Object.keys(dependenciasPermisos).forEach(permisoId => {
                const checkbox = document.getElementById(permisoId);
                if (checkbox) {
                    checkbox.addEventListener('change', function() {
                        if (this.checked) {
                            habilitarDependientes(permisoId);
                        } else {
                            deshabilitarDependientes(permisoId);
                        }
                    });
                }
            });

            // Verificar estado inicial después de cargar permisos
            Object.keys(dependenciasPermisos).forEach(permisoId => {
                const checkbox = document.getElementById(permisoId);
                if (checkbox && !checkbox.checked) {
                    deshabilitarDependientes(permisoId);
                }
            });
        }

        // Ejecutar cuando el DOM esté completamente cargado
        document.addEventListener('DOMContentLoaded', function() {
            inicializarLogicaPermisos();
        });

        /**
         * Validar permisos antes de enviar el formulario
         */
        function validarPermisosAntesDeProcesar() {
            let errores = [];

            // Verificar cada dependencia
            for (const [padre, dependientes] of Object.entries(dependenciasPermisos)) {
                const checkboxPadre = document.getElementById(padre);
                
                if (checkboxPadre && !checkboxPadre.checked) {
                    dependientes.forEach(permisoId => {
                        const checkbox = document.getElementById(permisoId);
                        if (checkbox && checkbox.checked) {
                            // Obtener el texto del permiso
                            const textoPermiso = checkbox.parentElement.querySelector('span').textContent;
                            const textoPadre = checkboxPadre.parentElement.querySelector('span').textContent;
                            errores.push(`"${textoPermiso}" requiere que "${textoPadre}" esté habilitado.`);
                        }
                    });
                }
            }

            if (errores.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Permisos Inconsistentes',
                    html: 'Se detectaron las siguientes inconsistencias en los permisos:<br><ul style="text-align: left; margin-top: 10px;">' + 
                        errores.map(err => `<li>${err}</li>`).join('') + '</ul>',
                    confirmButtonText: 'Revisar Permisos'
                });
                return false;
            }

            return true;
        }
    </script>

</body>
</html>