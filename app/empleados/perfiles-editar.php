<?php
// perfil-editar.php

require_once '../../core/conexion.php';
require_once '../../core/verificar-sesion.php';

// Verificar conexión a la base de datos
if (!$conn || $conn->connect_errno !== 0) {
    http_response_code(500);
    die("Error de conexión a la base de datos");
}

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'PADM001';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    header('location: ../errors/403.html');
    exit(); 
}

// Obtener el ID del perfil desde la URL
$idPerfil = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idPerfil === 0) {
    header('Location: perfiles-permisos.php');
    exit();
}

// Obtener datos del perfil
$sql_perfil = "SELECT id, nombre, descripcion, activo FROM perfiles_permisos WHERE id = ?";
$stmt_perfil = $conn->prepare($sql_perfil);
$stmt_perfil->bind_param("i", $idPerfil);
$stmt_perfil->execute();
$result_perfil = $stmt_perfil->get_result();

if ($result_perfil->num_rows === 0) {
    header('Location: perfiles-permisos.php');
    exit();
}

$perfil = $result_perfil->fetch_assoc();
$stmt_perfil->close();

// Obtener permisos actuales del perfil
$sql_permisos = "SELECT id_permiso FROM perfiles_permisos_detalle WHERE id_perfil = ?";
$stmt_permisos = $conn->prepare($sql_permisos);
$stmt_permisos->bind_param("i", $idPerfil);
$stmt_permisos->execute();
$result_permisos = $stmt_permisos->get_result();

$permisos_activos = [];
while ($row = $result_permisos->fetch_assoc()) {
    $permisos_activos[] = $row['id_permiso'];
}
$stmt_permisos->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Editar Perfil de Permisos</title>
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
            margin-right: 10px;
        }

        .btn-volver:hover {
            background-color: #e0e0e0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
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
    </style>
</head>
<body>

    <div class="navegator-nav">
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="form-container">
                <h2 class="form-title">Editar Perfil de Permisos</h2><br>

                <legend>Información del Perfil</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre del Perfil:</label>
                        <input type="text" id="nombre" name="nombre" autocomplete="off" placeholder="Ej: Encargado de Inventario" value="<?php echo htmlspecialchars($perfil['nombre']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="descripcion">Descripción:</label>
                        <textarea id="descripcion" name="descripcion" rows="3" placeholder="Describe las responsabilidades de este perfil"><?php echo htmlspecialchars($perfil['descripcion']); ?></textarea>
                    </div>
                </div>

                <div class="form-group-estado">
                    <label>
                        <input type="checkbox" id="activo" name="activo" <?php echo $perfil['activo'] ? 'checked' : ''; ?>>
                        <span>Perfil Activo</span>
                    </label>
                </div>

                <legend>Permisos del Perfil</legend>
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
                                    <input type="checkbox" name="permisos[]" value="CLI001" id="CLI001" <?php echo in_array('CLI001', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Crear/Editar Clientes</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="CLI002" id="CLI002" <?php echo in_array('CLI002', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Imprimir Reporte de Clientes</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="CLI003" id="CLI003" <?php echo in_array('CLI003', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Avance de Cuenta</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="CLI004" id="CLI004" <?php echo in_array('CLI004', $permisos_activos) ? 'checked' : ''; ?>>
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
                                    <input type="checkbox" name="permisos[]" value="PRO001" id="PRO001" <?php echo in_array('PRO001', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Crear/Editar Productos</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="PRO002" id="PRO002" <?php echo in_array('PRO002', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Imprimir Reporte de Productos</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="ALM001" id="ALM001" <?php echo in_array('ALM001', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Almacén Principal</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="ALM003" id="ALM003" <?php echo in_array('ALM003', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Visualizar Inventario de Empleados</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="ALM002" id="ALM002" <?php echo in_array('ALM002', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Transferencias de Inventario</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="ALM004" id="ALM004" <?php echo in_array('ALM004', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Entrada de Inventario</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="ALM005" id="ALM005" <?php echo in_array('ALM005', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Salida de Inventario</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Facturación y Cotizaciones -->
                    <div class="permisos-seccion">
                        <div class="seccion-header">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <h3>Facturación y Cotizaciones</h3>
                        </div>
                        <div class="seccion-body">
                            <div class="permisos-grid">
                                <label>
                                    <input type="checkbox" name="permisos[]" value="FAC001" id="FAC001" <?php echo in_array('FAC001', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Facturación</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="FAC002" id="FAC002" <?php echo in_array('FAC002', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Cancelar Facturas</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="COT001" id="COT001" <?php echo in_array('COT001', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Crear/Vender Cotizaciones</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="COT002" id="COT002" <?php echo in_array('COT002', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Ver Registro de Cotizaciones</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="COT003" id="COT003" <?php echo in_array('COT003', $permisos_activos) ? 'checked' : ''; ?>>
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
                                    <input type="checkbox" name="permisos[]" value="CAJ001" id="CAJ001" <?php echo in_array('CAJ001', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Abrir/Cerrar Caja</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="CUA001" id="CUA001" <?php echo in_array('CUA001', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Cuadres de Caja</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="CUA002" id="CUA002" <?php echo in_array('CUA002', $permisos_activos) ? 'checked' : ''; ?>>
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
                                    <input type="checkbox" name="permisos[]" value="PADM001" id="PADM001" <?php echo in_array('PADM001', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Panel Administrativo</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="PADM002" id="PADM002" <?php echo in_array('PADM002', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Ver Estadísticas del Negocio</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="PADM003" id="PADM003" <?php echo in_array('PADM003', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Administrar Bancos y Destinos</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="EMP001" id="EMP001" <?php echo in_array('EMP001', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Administrar Empleados</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="FAC003" id="FAC003" <?php echo in_array('FAC003', $permisos_activos) ? 'checked' : ''; ?>>
                                    <span>Editar Información en Factura</span>
                                </label>
                            </div>
                        </div>
                    </div>

                </div>
                
                <div class="btn-group">
                    <button type="button" class="btn-volver" onclick="window.location.href='perfiles-permisos.php'">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>

                    <button type="submit" class="btn-submit" id="submitBtn" onclick="actualizarPerfil()">
                        <i class="fas fa-save"></i> Actualizar Perfil
                    </button>
                </div>

            </div>
        </div>
    </div>

    <script>
        const idPerfil = <?php echo $idPerfil; ?>;

        function actualizarPerfil() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');

            const nombre = document.getElementById('nombre').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            const activo = document.getElementById('activo').checked;
            
            if (!nombre) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo Requerido',
                    text: 'El nombre del perfil es obligatorio',
                    confirmButtonText: 'Aceptar'
                });
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                return;
            }

            // Obtener permisos seleccionados
            const checkboxes = document.querySelectorAll('input[name="permisos[]"]:checked');
            const permisos = Array.from(checkboxes).map(cb => cb.value);

            if (permisos.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin Permisos',
                    text: 'Debe seleccionar al menos un permiso',
                    confirmButtonText: 'Aceptar'
                });
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                return;
            }

            // Enviar al servidor
            fetch('../../api/perfiles-permisos/editar.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: idPerfil,
                    nombre: nombre,
                    descripcion: descripcion,
                    activo: activo,
                    permisos: permisos
                })
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                
                if (!response.ok) {
                    const errorMessage = data.message || 'Error al actualizar el perfil';
                    
                    if (response.status === 409) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Perfil Duplicado',
                            text: errorMessage,
                            confirmButtonText: 'Aceptar'
                        });
                    } else if (response.status === 404) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Perfil No Encontrado',
                            text: errorMessage,
                            confirmButtonText: 'Aceptar'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: errorMessage,
                            confirmButtonText: 'Aceptar'
                        });
                    }
                    throw new Error(errorMessage);
                }
                
                return data;
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Perfil Actualizado',
                        text: 'El perfil de permisos ha sido actualizado exitosamente',
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        window.location.href = 'perfiles-permisos.php';
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            });
        }
    </script>

</body>
</html>