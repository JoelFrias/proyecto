<?php

require_once '../../core/verificar-sesion.php'; // Verificar Session
require_once '../../core/conexion.php'; // Conexión a la base de datos

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'EMP001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
        
    exit(); 
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Nuevo Empleado</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/registro_empleados.css">
    <link rel="stylesheet" href="../../assets/css/menu.css">
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
        }

        .btn-volver:hover {
            background-color: #e0e0e0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-volver:active {
            background-color: #d5d5d5;
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
    </style>
</head>
<body>

    <div class="navegator-nav">
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="form-container">
                <h2 class="form-title">Registro de Empleado</h2><br>

                <legend>Datos del Empleado</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" autocomplete="off" placeholder="Nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="apellido">Apellido:</label>
                        <input type="text" id="apellido" name="apellido" autocomplete="off" placeholder="Apellido" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_identificacion">Tipo de Identificación:</label>
                        <select id="tipo_identificacion" name="tipo_identificacion" required>
                            <option value="Cedula">Cédula</option>
                            <option value="Pasaporte">Pasaporte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="identificacion">Identificación:</label>
                        <input type="number" id="identificacion" name="identificacion" autocomplete="off" placeholder="Identificacion" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono:</label>
                        <input type="text" id="telefono" name="telefono" autocomplete="off" placeholder="0000000000" minlength="12" maxlength="12" required>
                    </div>
                    <div class="form-group">
                        <label for="idPuesto">Puesto:</label>
                        <select id="idPuesto" name="idPuesto" required>

                            <option value="" disabled selected>Seleccione un puesto</option>

                            <?php

                            require_once '../../core/conexion.php';

                            // Obtener el id y la descripción de los tipos de producto
                            $sql = "SELECT id, descripcion FROM empleados_puestos ORDER BY descripcion ASC";
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
                </div>

                <legend>Datos de Usuario</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Usuario:</label>
                        <input type="text" id="username" name="username" placeholder="Usuario" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña:</label>
                        <input type="password" id="password" name="password" placeholder="Contraseña" autocomplete="off" minlength="4" required>
                    </div>
                </div>

                <legend>Permisos de Usuario</legend>
                <div class="permisos-container">
                    
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
                                    <input type="checkbox" name="permisos[admi-inventario]" id="admi-inventario">
                                    <span>Administrar Inventario</span>
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
                                    <input type="checkbox" name="permisos[usuarios]" id="usuarios">
                                    <span>Administrar Usuarios</span>
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
                
                <button class="btn-volver" onclick="window.location.href='empleados.php'">
                    <i class="fa fa-arrow-left"></i> Volver
                </button>
                <button type="submit" class="btn-submit" id="submitBtn" onclick="guardarEmp()">Guardar Cambios</button>

            </div>
        </div>
    </div>

    <script>

        function guardarEmp(){

            // Datos
            const nombre = document.getElementById('nombre').value;
            const apellido = document.getElementById('apellido').value;
            const tipo_identificacion = document.getElementById('tipo_identificacion').value;
            const identificacion = document.getElementById('identificacion').value;
            const telefono = document.getElementById('telefono').value;
            const idPuesto = document.getElementById('idPuesto').value;
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const listPermisos = [
                                    'clientes', 'clientes-reporte', 'productos', 'productos-reporte',
                                    'avance-cuenta', 'cancel-avance', 'cancel-facturas', 'almacen', 'inv-empleados',
                                    'facturacion', 'cot-accion', 'caja', 'pan-adm', 'estadisticas',
                                    'bancos-destinos', 'usuarios', 'empleados', 'inf-factura',
                                    'cuadres', 'cot-registro', 'cot-cancelar', 'tran-inventario',
                                    'admi-inventario'
                                ];

            const permisos = {};

            listPermisos.forEach(permiso => {
                const checkbox = document.getElementById(permiso);
                permisos[permiso] = checkbox ? checkbox.checked : false;
            });

            // Enviar datos al servidor
            const url = '../../api/empleados/nuevo.php';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json' 
                },
                body: JSON.stringify({
                    nombre: nombre,
                    apellido: apellido,
                    tipo_identificacion: tipo_identificacion,
                    identificacion: identificacion,
                    telefono: telefono,
                    idPuesto: idPuesto,
                    username: username,
                    password: password,
                    permisos: permisos
                })
            })
            .then(async response => {
                // Intentar leer la respuesta como JSON, manejando el caso donde el body esté vacío o no sea JSON
                const data = await response.json().catch(() => ({})); 

                // Verificación de la respuesta HTTP (código fuera del rango 200-299)
                if (!response.ok) {
                    
                    const errorMessage = data.message || `Error en la comunicación con el servidor (Código HTTP: ${response.status}).`;
                    
                    // Manejo específico de errores basados en el código de estado HTTP
                    if (response.status === 400) {
                        // Error 400: Solicitud Incorrecta (Errores de Validación)
                        let errorList = '<ul>' + data.errors.map(err => `<li>${err}</li>`).join('') + '</ul>';
                        Swal.fire({
                            icon: 'warning',
                            title: 'Validación de Datos Incompleta',
                            html: 'Se identificaron los siguientes requerimientos pendientes:<br>' + errorList,
                            confirmButtonText: 'Revisar'
                        });
                    } else if (response.status === 409) {
                        // Error 409: Conflicto (Ej. Identificación duplicada)
                        Swal.fire({
                            icon: 'error',
                            title: 'Conflicto de Integridad de Datos',
                            text: errorMessage,
                            confirmButtonText: 'Aceptar'
                        });
                    } else {
                        // Otros Errores del Servidor (500, 405, etc.)
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de Procesamiento',
                            text: errorMessage,
                            confirmButtonText: 'Cerrar'
                        });
                    }

                    // Se lanza un error para detener la ejecución de las promesas 'then' subsiguientes
                    throw new Error(`Fallo en la Solicitud (HTTP ${response.status}): ${errorMessage}`);
                }

                // Si la respuesta es OK (ej. 201 Created), se retorna el objeto de datos
                return data; 
            })
            .then(data => {
                // Manejo de la respuesta exitosa (success: true)
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registro de Empleado Exitoso',
                        html: `El empleado ha sido registrado satisfactoriamente: ${response.message}`,
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        window.location.reload(); // Recargar la página para limpiar el formulario
                    });
                } else {
                    // En caso de que response.ok sea true, pero el cuerpo JSON indique un fallo lógico (success: false)
                    Swal.fire({
                        icon: 'error',
                        title: 'Fallo Lógico en la Aplicación',
                        text: data.message,
                        confirmButtonText: 'Cerrar'
                    });
                }
            })
            .catch(error => {
                // Captura errores de red (e.g., servidor inactivo, problemas de CORS) que no tienen código HTTP
                if (!error.message.includes('HTTP')) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Interrupción de Conexión de Red',
                        text: 'No fue posible establecer comunicación con el servidor. Por favor, verifique su conexión e intente nuevamente.',
                        footer: `Detalle: ${error.message}`
                    });
                }
                // Opcional: Registrar el error completo en la consola para depuración.
                // console.error('Error total en la solicitud fetch:', error);
            });
        }

    </script>

    <script>
        // ---------- Configuración de dependencias de permisos ----------
        const dependenciasPermisos = {
            'avance-cuenta': ['cancel-avance'],
            'facturacion': ['cot-accion'],
            'cot-registro': ['cot-cancelar'],
            'pan-adm': [
                'estadisticas',
                'bancos-destinos',
                'usuarios',
                'empleados',
                'inf-factura',
                'cot-registro',
                'tran-inventario',
                'admi-inventario',
                'cuadres'
            ]
        };

        // ---------- Permisos activos desde PHP (asegurar un objeto por defecto) ----------
        // Si $permisos_checked no está definido en PHP, esto inyectará {}
        const permisosActivos = <?php echo isset($permisos_checked) ? json_encode($permisos_checked) : json_encode((object)[]); ?> || {};

        /**
         * Deshabilita y desmarca los permisos dependientes (recursivo)
         */
        function deshabilitarDependientes(permisoPrincipal) {
            const dependientes = dependenciasPermisos[permisoPrincipal] || [];

            dependientes.forEach(permisoId => {
                const checkbox = document.getElementById(permisoId);
                if (checkbox) {
                    checkbox.checked = false;
                    checkbox.disabled = true;
                    if (checkbox.parentElement) {
                        checkbox.parentElement.style.opacity = '0.5';
                        checkbox.parentElement.style.cursor = 'not-allowed';
                    }
                    // Recursividad si hay dependientes del dependiente
                    if (dependenciasPermisos[permisoId]) {
                        deshabilitarDependientes(permisoId);
                    }
                }
            });
        }

        /**
         * Habilita los permisos dependientes
         */
        function habilitarDependientes(permisoPrincipal) {
            const dependientes = dependenciasPermisos[permisoPrincipal] || [];

            dependientes.forEach(permisoId => {
                const checkbox = document.getElementById(permisoId);
                if (checkbox) {
                    checkbox.disabled = false;
                    if (checkbox.parentElement) {
                        checkbox.parentElement.style.opacity = '1';
                        checkbox.parentElement.style.cursor = 'pointer';
                    }
                }
            });
        }

        /**
         * Marca los checkboxes según los permisos activos del usuario (si vienen)
         */
        function cargarPermisosActivos() {
            // Seguridad: si no es objeto, salir
            if (!permisosActivos || typeof permisosActivos !== 'object') return;

            for (const [permiso, activo] of Object.entries(permisosActivos)) {
                const checkbox = document.getElementById(permiso);
                if (checkbox && activo) {
                    checkbox.checked = true;
                }
            }
        }

        /**
         * Inicializa listeners y estado
         */
        function inicializarLogicaPermisos() {
            // Cargar permisos que vengan desde PHP
            cargarPermisosActivos();

            // Event listeners para los permisos principales (los keys de dependencias)
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

            // Estado inicial: si un permiso principal está desmarcado, deshabilitar sus dependientes
            Object.keys(dependenciasPermisos).forEach(permisoId => {
                const checkbox = document.getElementById(permisoId);
                if (checkbox && !checkbox.checked) {
                    deshabilitarDependientes(permisoId);
                }
            });
        }

        // Ejecutar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            inicializarLogicaPermisos();
        });

        // ---------- Función guardarEmp (corrección mínima de display de mensajes) ----------
        function guardarEmp(){

            // Datos
            const nombre = document.getElementById('nombre').value;
            const apellido = document.getElementById('apellido').value;
            const tipo_identificacion = document.getElementById('tipo_identificacion').value;
            const identificacion = document.getElementById('identificacion').value;
            const telefono = document.getElementById('telefono').value;
            const idPuesto = document.getElementById('idPuesto').value;
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const listPermisos = [
                'clientes', 'clientes-reporte', 'productos', 'productos-reporte',
                'avance-cuenta', 'cancel-avance', 'cancel-facturas', 'almacen', 'inv-empleados',
                'facturacion', 'cot-accion', 'caja', 'pan-adm', 'estadisticas',
                'bancos-destinos', 'usuarios', 'empleados', 'inf-factura',
                'cuadres', 'cot-registro', 'cot-cancelar', 'tran-inventario',
                'admi-inventario'
            ];

            const permisos = {};

            listPermisos.forEach(permiso => {
                const checkbox = document.getElementById(permiso);
                permisos[permiso] = checkbox ? checkbox.checked : false;
            });

            if (!validarPermisosAntesDeProcesar()) return;

            // Enviar datos al servidor
            const url = '../../api/empleados/nuevo.php';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    nombre: nombre,
                    apellido: apellido,
                    tipo_identificacion: tipo_identificacion,
                    identificacion: identificacion,
                    telefono: telefono,
                    idPuesto: idPuesto,
                    username: username,
                    password: password,
                    permisos: permisos
                })
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const errorMessage = data.message || `Error en la comunicación con el servidor (Código HTTP: ${response.status}).`;
                    if (response.status === 400 && Array.isArray(data.errors)) {
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
                        title: 'Registro de Empleado Exitoso',
                        html: `El empleado ha sido registrado satisfactoriamente: ${data.message || ''}`,
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Fallo Lógico en la Aplicación',
                        text: data.message || 'Ocurrió un error inesperado.',
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
                console.error('Error en la solicitud fetch:', error);
            });
        }

        /**
         * Validar permisos antes de enviar el formulario
         */
        function validarPermisosAntesDeProcesar() {
            let errores = [];

            for (const [padre, dependientes] of Object.entries(dependenciasPermisos)) {
                const checkboxPadre = document.getElementById(padre);

                if (checkboxPadre && !checkboxPadre.checked) {
                    dependientes.forEach(permisoId => {
                        const checkbox = document.getElementById(permisoId);
                        if (checkbox && checkbox.checked) {
                            const textoPermiso = checkbox.parentElement ? checkbox.parentElement.querySelector('span').textContent : permisoId;
                            const textoPadre = checkboxPadre.parentElement ? checkboxPadre.parentElement.querySelector('span').textContent : padre;
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