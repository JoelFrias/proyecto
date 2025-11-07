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

// incluir la conexion a la base de datos
include '../../models/conexion.php';

////////////////////////////////////////////////////////////////////
///////////////////// VALIDACION DE PERMISOS ///////////////////////
////////////////////////////////////////////////////////////////////

require_once '../../models/validar-permisos.php';
$permiso_necesario = 'CLI001';
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Nuevo Cliente</title>
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/actualizar_cliente.css">
    <link rel="stylesheet" href="../../assets/css/menu.css"> <!-- CSS menu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Importación de iconos -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Librería para alertas -->
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
    </style>
</head>
<body>

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../views/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->
        
            <!-- Contenedor del formulario -->
            <div class="form-container">
                <h1 class="form-title">Registro de Cliente</h1>
                <!-- Sección de Datos del Cliente -->
                <fieldset>
                    <legend>Datos del Cliente</legend>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre">Nombre:</label>
                            <input type="text" id="nombre" name="nombre" autocomplete="off" placeholder="Ingrese el nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="apellido">Apellido:</label>
                            <input type="text" id="apellido" name="apellido" autocomplete="off" placeholder="Ingrese el apellido" required>
                        </div>
                        <div class="form-group">
                            <label for="empresa">Empresa:</label>
                            <input type="text" id="empresa" name="empresa" autocomplete="off" placeholder="Ingrese la empresa" required>
                        </div>
                        <div class="form-group">
                            <label for="tipo_identificacion">Tipo de Identificación:</label>
                            <select id="tipo_identificacion" name="tipo_identificacion" required>
                                <option value="" disabled selected>Seleccionar</option>
                                <option value="cedula">Cédula</option>
                                <option value="rnc">RNC</option>
                                <option value="pasaporte">Pasaporte</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="identificacion">Número de Identificación:</label>
                            <input type="text" id="identificacion" name="identificacion" autocomplete="off" placeholder="Ingrese la identificación" required>
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono:</label>
                            <input type="text" id="telefono" name="telefono" autocomplete="off" placeholder="000-000-0000" maxlength="12" minlength="12" required>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label for="notas">Notas:</label>
                        <textarea id="notas" name="notas" placeholder="Notas del cliente" required></textarea>
                    </div>
                </fieldset>

                <!-- Sección de Datos de la Cuenta -->
                <fieldset>
                    <legend>Datos de la Cuenta</legend>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="limite_credito">Límite de Crédito:</label>
                            <input type="number" id="limite_credito" name="limite_credito" min="0" step="0.01" autocomplete="off" placeholder="Ingrese un límite de crédito" required>
                        </div>
                    </div>
                </fieldset>

                <!-- Sección de Dirección -->
                <fieldset>
                    <legend>Dirección</legend>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="no">Número:</label>
                            <input type="text" id="no" name="no" autocomplete="off" placeholder="Ingrese el número de local" required>
                        </div>
                        <div class="form-group">
                            <label for="calle">Calle:</label>
                            <input type="text" id="calle" name="calle" autocomplete="off" placeholder="Ingrese la calle" required>
                        </div>
                        <div class="form-group">
                            <label for="sector">Sector:</label>
                            <input type="text" id="sector" name="sector" autocomplete="off" placeholder="Ingrese el sector" required>
                        </div>
                        <div class="form-group">
                            <label for="ciudad">Ciudad:</label>
                            <input type="text" id="ciudad" name="ciudad" autocomplete="off" placeholder="Ingrese la ciudad" required>
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label for="referencia">Referencia:</label>
                        <textarea id="referencia" name="referencia" placeholder="Indique referencias del local (Ej: Al lado de la farmacia)" required></textarea>
                    </div>
                </fieldset>

                <!-- Botón para enviar el formulario -->
                <!-- <button class="btn-volver" onclick="history.back()">← Volver atrás</button> -->
                <button class="btn-submit" onclick="registrarCliente()">Registrar Cliente</button>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR POR ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <!-- Mostrar mensajes de éxito o error -->
    <?php
        if (isset($_SESSION['status']) && $_SESSION['status'] === 'success') {
            echo "
                <script>
                    Swal.fire({
                        title: '¡Éxito!',
                        text: 'El cliente ha sido registrado exitosamente.',
                        icon: 'success',
                        confirmButtonText: 'Aceptar'
                    }).then(function() {
                        window.location.href = '../../views/clientes/clientes-nuevo.php'; 
                    });
                </script>
            ";
            unset($_SESSION['status']); // Limpiar el estado después de mostrar el mensaje
        }
        if (isset($_SESSION['errors']) && !empty($_SESSION['errors'])) {
            foreach ($_SESSION['errors'] as $error) {
                echo "
                    <script>
                        Swal.fire({
                            title: '¡Error!',
                            text: '$error',
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                    </script>
                ";
            }
            unset($_SESSION['errors']); // Limpiar los errores después de mostrarlos
        }
    ?>

    <!-- Script para manejar el envío del formulario -->
    <script> 

        // 1. **Función Principal** que maneja el envío
        function registrarCliente() {

            // Recopilar los datos del formulario
            let nombre = document.getElementById('nombre').value;
            let apellido = document.getElementById('apellido').value;
            let empresa = document.getElementById('empresa').value;
            let tipo_identificacion = document.getElementById('tipo_identificacion').value;
            let identificacion = document.getElementById('identificacion').value;
            let telefono = document.getElementById('telefono').value;
            let notas = document.getElementById('notas').value;
            let limite_credito = parseFloat(document.getElementById('limite_credito').value);
            let no = document.getElementById('no').value;
            let calle = document.getElementById('calle').value;
            let sector = document.getElementById('sector').value;
            let ciudad = document.getElementById('ciudad').value;
            let referencia = document.getElementById('referencia').value;

            const clienteData = {
                nombre: nombre,
                apellido: apellido,
                empresa: empresa,
                tipo_identificacion: tipo_identificacion,
                identificacion: identificacion,
                telefono: telefono,
                notas: notas,
                limite_credito: limite_credito,
                no: no,
                calle: calle,
                sector: sector,
                ciudad: ciudad,
                referencia: referencia
            };

            const url = '../../controllers/clientes/nuevo-cliente.php';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json' 
                },
                body: JSON.stringify(clienteData) 
            })
            .then(async response => {
                // Intentar leer la respuesta como JSON, manejando el caso donde el body esté vacío o no sea JSON
                const data = await response.json().catch(() => ({})); 

                // Verificación de la respuesta HTTP (código fuera del rango 200-299)
                if (!response.ok) {
                    
                    const errorMessage = data.message || `Error en la comunicación con el servidor (Código HTTP: ${response.status}).`;
                    
                    // Manejo específico de errores basados en el código de estado HTTP
                    if (response.status === 400 && data.errors) {
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
                            title: 'Error de Procesamiento en el Servidor',
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
                        title: 'Registro de Cliente Exitoso',
                        html: `El cliente con ID **${data.cliente_id}** ha sido registrado satisfactoriamente: ${data.message}`,
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