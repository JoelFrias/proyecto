<?php
/* Verificacion de sesion */

// Iniciar sesión
session_start();

// Configurar el tiempo de caducidad de la sesión
$inactivity_limit = 900; // 15 minutos en segundos

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['username'])) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header('Location: ../../views/auth/login.php'); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Verificar si la sesión ha expirado por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_limit)) {
    session_unset(); // Eliminar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header("Location: ../../views/auth/login.php?session_expired=session_expired"); // Redirigir al login
    exit(); // Detener la ejecución del script
}

// Actualizar el tiempo de la última actividad
$_SESSION['last_activity'] = time();

/* Fin de verificacion de sesion */

// Conexión a la base de datos
require_once '../../models/conexion.php';

////////////////////////////////////////////////////////////////////
///////////////////// VALIDACION DE PERMISOS ///////////////////////
////////////////////////////////////////////////////////////////////

require_once '../../models/validar-permisos.php';
$permiso_necesario = 'EMP001';
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

        .permisos-container {
            margin-top: 15px;
            margin-bottom: 25px;
        }

        .permisos-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        @media (min-width: 640px) {
            .permisos-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .permisos-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            }
        }

        .permisos-grid label {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            gap: 10px;
        }

        .permisos-grid label:hover {
            background: #e7f3ff;
            border-color: #0066cc;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.15);
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

        @media (max-width: 400px) {
            .permisos-grid {
                gap: 10px;
            }

            .permisos-grid label {
                padding: 10px;
                font-size: 13px;
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
        <?php include '../../views/layouts/menu.php'; ?>

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

                            require_once '../../models/conexion.php';

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
                            <input type="checkbox" name="permisos[productos]" id="productos">
                            <span>Crear/Editar Productos</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[productos-reporte]" id="productos-reporte">
                            <span>Imprimir Reporte de Productos</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[avance-cuenta]" id="avance-cuenta">
                            <span>Avance de Cuenta</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[cancel-avance]" id="cancel-avance">
                            <span>Cancelar Avance a Cuenta</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[cancel-facturas]" id="cancel-facturas">
                            <span>Cancelar Facturas</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[almacen]" id="almacen">
                            <span>Almacen Principal</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[inv-empleados]" id="inv-empleados">
                            <span>Visualizar Inventario de Empleados</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[facturacion]" id="facturacion">
                            <span>Facturación</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[cot-accion]" id="cot-accion">
                            <span>Crear/Vender Cotizaciones</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[caja]" id="caja">
                            <span>Abrir/Cerrar Caja</span>
                        </label>
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
                        <label>
                            <input type="checkbox" name="permisos[cuadres]" id="cuadres">
                            <span>Cuadres de Caja</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[cot-registro]" id="cot-registro">
                            <span>Ver Registro de Cotizaciones</span>
                        </label>
                        <label>
                            <input type="checkbox" name="permisos[cot-cancelar]" id="cot-cancelar">
                            <span>Cancelar Cotizaciones</span>
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
            const url = '../../controllers/empleados/nuevo.php';

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

</body>
</html>