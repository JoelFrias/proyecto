<?php

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
$permiso_necesario = 'CLI001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
        
    exit(); 
}

// Obtener el ID del cliente desde la URL y validarlo
$cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Consulta SQL para obtener los datos del cliente, su cuenta y dirección
$query = "SELECT c.id, c.nombre, c.apellido, c.empresa, c.tipo_identificacion, c.identificacion, c.telefono, c.notas, cc.limite_credito, cd.no, cd.calle, cd.sector, cd.ciudad, cd.referencia, c.activo 
          FROM clientes c
          JOIN clientes_cuenta cc ON c.id = cc.idCliente
          JOIN clientes_direcciones cd ON c.id = cd.idCliente
          WHERE c.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$result = $stmt->get_result();
$cliente = $result->fetch_assoc();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Actualizar Cliente</title>
    <link rel="icon" href="../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/css/actualizar_cliente.css">
    <link rel="stylesheet" href="../assets/css/menu.css"> <!-- CSS menu -->
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
<?php 
// Mostrar mensaje de éxito si existe
if (isset($_SESSION['status']) && $_SESSION['status'] === 'update_success') {
    echo "
        <script>
            Swal.fire({
                title: '¡Éxito!',
                text: 'El cliente ha sido actualizado exitosamente.',
                icon: 'success',
                confirmButtonText: 'Aceptar'
            });
        </script>
    ";
    unset($_SESSION['status']); // Limpiar el estado después de mostrar el mensaje
}

// Mostrar errores si existen
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

    <div class="navegator-nav">

        <!-- Menu-->
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR DEBAJO DE ESTA LINEA -->

            <!-- Contenedor del formulario -->
            <div class="form-container">
                <h1 class="form-title">Actualizar Datos</h1>
                    <!-- Sección de Datos Personales -->
                    <fieldset>
                        <legend>Datos Personales</legend>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nombre">Nombre:</label>
                                <input type="text" id="nombre" name="nombre" value="<?php echo $cliente['nombre']; ?>" placeholder="Ingrese el nombre" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="apellido">Apellido:</label>
                                <input type="text" id="apellido" name="apellido" value="<?php echo $cliente['apellido']; ?>" placeholder="Ingrese el apellido" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="empresa">Empresa:</label>
                                <input type="text" id="empresa" name="empresa" value="<?php echo $cliente['empresa']; ?>" placeholder="Ingrese la empresa" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="tipo_identificacion">Tipo Identificación:</label>
                                <select id="tipo_identificacion" name="tipo_identificacion" required>
                                    <option value="cedula" <?php echo $cliente['tipo_identificacion'] === 'cedula' ? 'selected' : ''; ?>>Cédula</option>
                                    <option value="rnc" <?php echo $cliente['tipo_identificacion'] === 'rnc' ? 'selected' : ''; ?>>RNC</option>
                                    <option value="pasaporte" <?php echo $cliente['tipo_identificacion'] === 'pasaporte' ? 'selected' : ''; ?>>Pasaporte</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="identificacion">Identificación:</label>
                                <input type="text" id="identificacion" name="identificacion" value="<?php echo $cliente['identificacion']; ?>" placeholder="Ingrese la identificación" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="telefono">Teléfono:</label>
                                <input type="text" id="telefono" name="telefono" value="<?php echo $cliente['telefono']; ?>" placeholder="000-000-0000" autocomplete="off" >
                            </div>
                            <div class="form-group">
                                <label for="notas">Notas:</label>
                                <textarea id="notas" name="notas" placeholder="Indique notas del cliente"><?php echo $cliente['notas']; ?></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Sección de Crédito -->
                    <fieldset>
                        <legend>Crédito</legend>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="limite_credito">Límite Crédito:</label>
                                <input type="number" id="limite_credito" name="limite_credito" min="0" value="<?php echo $cliente['limite_credito']; ?>" step="0.01" placeholder="Ingrese el límite de crédito" autocomplete="off">
                            </div>
                        </div>
                    </fieldset>

                    <!-- Sección de Dirección -->
                    <fieldset>
                        <legend>Dirección</legend>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="no">No:</label>
                                <input type="text" id="no" name="no" minlength="1" value="<?php echo $cliente['no']; ?>" placeholder="Ingrese el número de casa" required>
                            </div>
                            <div class="form-group">
                                <label for="calle">Calle:</label>
                                <input type="text" id="calle" name="calle" minlength="1" value="<?php echo $cliente['calle']; ?>" placeholder="Ingrese la calle" required>
                            </div>
                            <div class="form-group">
                                <label for="sector">Sector:</label>
                                <input type="text" id="sector" name="sector" minlength="1" value="<?php echo $cliente['sector']; ?>" placeholder="Ingrese el sector" required>
                            </div>
                            <div class="form-group">
                                <label for="ciudad">Ciudad:</label>
                                <input type="text" id="ciudad" name="ciudad" minlength="1" value="<?php echo $cliente['ciudad']; ?>" placeholder="Ingrese la ciudad" required>
                            </div>
                            <div class="form-group">
                                <label for="referencia">Referencia:</label>
                                <textarea id="referencia" name="referencia" minlength="1" placeholder="Indique referencia de direccion (Ej: Al lado de una farmacia)" required><?php echo $cliente['referencia']; ?></textarea>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Sección de Estado -->
                    <fieldset>
                        <legend>Estado</legend>
                        <div class="form-group">
                            <label for="inactividad">Estado:</label>
                            <select id="inactividad" name="inactividad" required>
                                <option value="TRUE" <?php echo $cliente['activo'] ? 'selected' : ''; ?>>Activo</option>
                                <option value="FALSE" <?php echo !$cliente['activo'] ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </fieldset>

                    <!-- Botón para enviar el formulario -->
                    <!-- <button class="btn-volver" onclick="history.back()">← Volver atrás</button> -->
                    <button class="btn-submit" onclick="actualizarCliente()">Actualizar</button>
            </div>

        <!-- TODO EL CONTENIDO DE LA PAGINA DEBE DE ESTAR POR ENCIMA DE ESTA LINEA -->
        </div>
    </div>

    <!-- Script para manejar el envío del formulario -->
    <script>

        const CLIENTE_ID_ACTUAL = parseInt(<?php echo json_encode($cliente_id ?? 0); ?>);
        
        function actualizarCliente() {

            // Obtener el ID del cliente de la constante inyectada.
            const cliente_id = CLIENTE_ID_ACTUAL; 

            if (cliente_id === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Parámetro',
                    text: 'El ID del cliente para actualizar es inválido o no se encontró.',
                    confirmButtonText: 'Cerrar'
                });
                return; // Detener la ejecución si no hay ID válido
            }

            // 1. Obtener la cadena seleccionada ("TRUE" o "FALSE")
            const estadoSeleccionado = document.getElementById('inactividad').value;

            // 2. CONVERSIÓN CRÍTICA: Convertir la cadena "TRUE"/"FALSE" a un booleano (true/false) de JavaScript.
            // Esto asegura que se envíe un booleano en el JSON, que es mejor práctica de API.
            const activoEstado = estadoSeleccionado === 'TRUE';
            
            // Recopilar informacion
            const clienteData = {
                // ID del cliente
                id: cliente_id, 
                
                // CAMPOS PRINCIPALES
                nombre: document.getElementById('nombre').value.trim(),
                apellido: document.getElementById('apellido').value.trim(),
                empresa: document.getElementById('empresa').value.trim(),
                tipo_identificacion: document.getElementById('tipo_identificacion').value,
                identificacion: document.getElementById('identificacion').value.trim(),
                telefono: document.getElementById('telefono').value.trim(),
                notas: document.getElementById('notas').value.trim(),
                
                // NUMÉRICOS
                limite_credito: parseFloat(document.getElementById('limite_credito').value) || 0.00, 
                
                // DIRECCIÓN
                no: document.getElementById('no').value.trim(),
                calle: document.getElementById('calle').value.trim(),
                sector: document.getElementById('sector').value.trim(),
                ciudad: document.getElementById('ciudad').value.trim(),
                referencia: document.getElementById('referencia').value.trim(),
                
                // ESTADO DE ACTIVIDAD: Ahora es un booleano (true/false)
                activo: activoEstado 
            };

            // Apuntar al archivo PHP de actualización
            const url = '../../api/clientes/actualizar-cliente.php';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json' 
                },
                body: JSON.stringify(clienteData) 
            })
            .then(async response => {
                const data = await response.json().catch(() => ({})); 

                if (!response.ok) {
                    const errorMessage = data.message || `Error en la comunicación con el servidor (Código HTTP: ${response.status}).`;
                    
                    if (response.status === 400 && data.errors) {
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
                            title: 'Error de Procesamiento en el Servidor',
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
                        html: `Los datos del cliente **${data.cliente_id}** han sido modificados satisfactoriamente: ${data.message}`,
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
            });
        }
    </script>

</body>
</html>