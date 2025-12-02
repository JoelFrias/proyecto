<?php
// perfiles-permisos.php

require_once '../../core/conexion.php';
require_once '../../core/verificar-sesion.php';

// Verificar conexión a la base de datos
if (!$conn || $conn->connect_errno !== 0) {
    http_response_code(500);
    die("Error de conexión a la base de datos");
}

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'PADM001'; // Panel administrativo
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
    <title>Administrar Perfiles de Permisos</title>
    <link rel="icon" href="../assets/img/logo-ico.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/css/menu.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
        }

        .page-content {
            padding: 20px;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .header-section h1 {
            color: #2c3e50;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-nuevo-perfil {
            background-color: #0066cc;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-nuevo-perfil:hover {
            background-color: #0052a3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
        }

        .perfiles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .perfil-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .perfil-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            border-color: #0066cc;
        }

        .perfil-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .perfil-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .perfil-descripcion {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .perfil-info {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-activo {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-inactivo {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-permisos {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .perfil-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .btn-action {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-editar {
            background-color: #218ce4;
            color: #ffffffff;
        }

        .btn-editar:hover {
            background-color: #1867a8ff;
            transform: scale(1.05);
        }

        .btn-eliminar {
            background-color: #dc3545;
            color: white;
        }

        .btn-eliminar:hover {
            background-color: #c82333;
            transform: scale(1.05);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .header-section {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .btn-nuevo-perfil {
                width: 100%;
                justify-content: center;
            }

            .perfiles-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="navegator-nav">
        <?php include '../../app/layouts/menu.php'; ?>

        <div class="page-content">
            <div class="header-section">
                <h1>
                    <i class="fas fa-user-shield"></i>
                    Perfiles de Permisos
                </h1>
                <button class="btn-nuevo-perfil" onclick="mostrarModalNuevoPerfil()">
                    <i class="fas fa-plus"></i>
                    Nuevo Perfil
                </button>
            </div>

            <div id="perfiles-container" class="perfiles-grid">
                <!-- Los perfiles se cargarán dinámicamente aquí -->
            </div>
        </div>
    </div>

    <script>
        // Cargar perfiles al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            cargarPerfiles();
        });

        function cargarPerfiles() {
            fetch('../../api/perfiles-permisos/listar.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('perfiles-container');
                    
                    if (data.success && data.perfiles.length > 0) {
                        container.innerHTML = data.perfiles.map(perfil => `
                            <div class="perfil-card">
                                <div class="perfil-card-header">
                                    <div>
                                        <div class="perfil-title">${perfil.nombre}</div>
                                        <div class="perfil-info">
                                            <span class="badge ${perfil.activo ? 'badge-activo' : 'badge-inactivo'}">
                                                <i class="fas fa-circle"></i> ${perfil.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                            <span class="badge badge-permisos">
                                                <i class="fas fa-key"></i> ${perfil.total_permisos} permisos
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="perfil-descripcion">
                                    ${perfil.descripcion || 'Sin descripción'}
                                </div>
                                <div class="perfil-actions">
                                    <button class="btn-action btn-editar" onclick="editarPerfil(${perfil.id})">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn-action btn-eliminar" onclick="eliminarPerfil(${perfil.id}, '${perfil.nombre}')">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-user-shield"></i>
                                <h3>No hay perfiles de permisos</h3>
                                <p>Crea tu primer perfil para organizar los permisos de tus empleados</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error al cargar perfiles:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Conexión',
                        text: 'No se pudieron cargar los perfiles',
                        confirmButtonText: 'Aceptar'
                    });
                });
        }

        function mostrarModalNuevoPerfil() {
            window.location.href = 'perfiles-nuevo.php';
        }

        function editarPerfil(id) {
            window.location.href = `perfiles-editar.php?id=${id}`;
        }

        function eliminarPerfil(id, nombre) {
            Swal.fire({
                title: '¿Eliminar perfil?',
                html: `Estás a punto de eliminar el perfil <strong>"${nombre}"</strong>.<br>Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`../../api/perfiles-permisos/eliminar.php`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id: id })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Perfil Eliminado',
                                text: 'El perfil ha sido eliminado correctamente',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            cargarPerfiles();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'No se pudo eliminar el perfil',
                                confirmButtonText: 'Aceptar'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de Conexión',
                            text: 'No se pudo conectar con el servidor',
                            confirmButtonText: 'Aceptar'
                        });
                    });
                }
            });
        }
    </script>

</body>
</html>