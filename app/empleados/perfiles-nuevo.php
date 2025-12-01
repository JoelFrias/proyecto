<?php
// perfil-nuevo.php

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Nuevo Perfil de Permisos</title>
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
        }
    </style>
</head>
<body>

    <div class="navegator-nav">
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="form-container">
                <h2 class="form-title">Crear Perfil de Permisos</h2><br>

                <legend>Información del Perfil</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre del Perfil:</label>
                        <input type="text" id="nombre" name="nombre" autocomplete="off" placeholder="Ej: Encargado de Inventario" required>
                    </div>
                    <div class="form-group">
                        <label for="descripcion">Descripción:</label>
                        <textarea id="descripcion" name="descripcion" rows="3" placeholder="Describe las responsabilidades de este perfil"></textarea>
                    </div>
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
                                    <input type="checkbox" name="permisos[]" value="CLI001" id="clientes">
                                    <span>Crear/Editar Clientes</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="CLI002" id="clientes-reporte">
                                    <span>Imprimir Reporte de Clientes</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="CLI003" id="avance-cuenta">
                                    <span>Avance de Cuenta</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="CLI004" id="cancel-avance">
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
                                    <input type="checkbox" name="permisos[]" value="PRO001" id="productos">
                                    <span>Crear/Editar Productos</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="PRO002" id="productos-reporte">
                                    <span>Imprimir Reporte de Productos</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="ALM001" id="almacen">
                                    <span>Almacén Principal</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="ALM003" id="inv-empleados">
                                    <span>Visualizar Inventario de Empleados</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="ALM002" id="tran-inventario">
                                    <span>Transferencias de Inventario</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="ALM004" id="entrada-inventario">
                                    <span>Entrada de Inventario</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="ALM005" id="salida-inventario">
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
                                    <input type="checkbox" name="permisos[]" value="FAC001" id="facturacion">
                                    <span>Facturación</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="FAC002" id="cancel-facturas">
                                    <span>Cancelar Facturas</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="COT001" id="cot-accion">
                                    <span>Crear/Vender Cotizaciones</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="COT002" id="cot-registro">
                                    <span>Ver Registro de Cotizaciones</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="COT003" id="cot-cancelar">
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
                                    <input type="checkbox" name="permisos[]" value="CAJ001" id="caja">
                                    <span>Abrir/Cerrar Caja</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="CUA001" id="cuadres">
                                    <span>Cuadres de Caja</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="CUA002" id="cuadres-accion">
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
                                    <input type="checkbox" name="permisos[]" value="PADM001" id="pan-adm">
                                    <span>Panel Administrativo</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="PADM002" id="estadisticas">
                                    <span>Ver Estadísticas del Negocio</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="PADM003" id="bancos-destinos">
                                    <span>Administrar Bancos y Destinos</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="EMP001" id="empleados">
                                    <span>Administrar Empleados</span>
                                </label>
                                <label>
                                    <input type="checkbox" name="permisos[]" value="FAC003" id="inf-factura">
                                    <span>Editar Información en Factura</span>
                                </label>
                            </div>
                        </div>
                    </div>

                </div>
                
                <button class="btn-volver" onclick="window.location.href='perfiles-permisos.php'">
                    <i class="fa fa-arrow-left"></i> Volver
                </button>
                <button type="submit" class="btn-submit" id="submitBtn" onclick="guardarPerfil()">Guardar Perfil</button>

            </div>
        </div>
    </div>

    <script>
        function guardarPerfil() {
            const nombre = document.getElementById('nombre').value.trim();
            const descripcion = document.getElementById('descripcion').value.trim();
            
            if (!nombre) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo Requerido',
                    text: 'El nombre del perfil es obligatorio',
                    confirmButtonText: 'Aceptar'
                });
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
                return;
            }

            // Enviar al servidor
            fetch('../../api/perfiles-permisos/crear.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    nombre: nombre,
                    descripcion: descripcion,
                    permisos: permisos
                })
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                
                if (!response.ok) {
                    const errorMessage = data.message || 'Error al crear el perfil';
                    
                    if (response.status === 409) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Perfil Duplicado',
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
                        title: 'Perfil Creado',
                        text: 'El perfil de permisos ha sido creado exitosamente',
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        window.location.href = 'perfiles-permisos.php';
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>

</body>
</html>