<?php
// layout-perfil-permisos.php

require_once '../../core/conexion.php';

// Verificar conexión a la base de datos
if (!$conn || $conn->connect_errno !== 0) {
    echo '<div class="perfil-permisos-error">Error de conexión a la base de datos</div>';
    return;
}
?>

<style>
    .perfil-permisos-section {
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px solid #e9ecef;
    }

    .perfil-permisos-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .perfil-permisos-header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .perfil-permisos-header h4 i {
        color: #0066cc;
    }

    .btn-administrar-perfiles {
        background-color: #0066cc;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn-administrar-perfiles:hover {
        background-color: #0052a3;
    }

    .perfil-select-container {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: center;
    }

    #perfil-permisos-select {
        padding: 10px;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
        background-color: white;
        cursor: pointer;
        transition: border-color 0.2s;
    }

    #perfil-permisos-select:hover {
        border-color: #0066cc;
    }

    #perfil-permisos-select:focus {
        outline: none;
        border-color: #0066cc;
        box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
    }

    .btn-limpiar-perfil {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn-limpiar-perfil:hover {
        background-color: #5a6268;
    }

    .perfil-info {
        margin-top: 10px;
        padding: 10px;
        background-color: #e7f3ff;
        border-left: 4px solid #0066cc;
        border-radius: 4px;
        font-size: 13px;
        color: #2c3e50;
        display: none;
    }

    .perfil-info.active {
        display: block;
    }

    @media (max-width: 768px) {
        .perfil-permisos-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .perfil-select-container {
            grid-template-columns: 1fr;
        }

        .btn-limpiar-perfil {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="perfil-permisos-section">
    <div class="perfil-permisos-header">
        <h4>
            <i class="fas fa-user-tag"></i>
            Perfil de Permisos
        </h4>
        <button type="button" class="btn-administrar-perfiles" onclick="window.open('perfiles-permisos.php', '_blank')">
            <i class="fas fa-cog"></i>
            Administrar Perfiles
        </button>
    </div>

    <div class="perfil-select-container">
        <select id="perfil-permisos-select">
            <option value="" disabled selected>Seleccionar un perfil (opcional)</option>
            <?php
            $sql_perfiles = "SELECT id, nombre, descripcion FROM perfiles_permisos WHERE activo = 1 ORDER BY nombre ASC";
            $resultado_perfiles = $conn->query($sql_perfiles);

            if ($resultado_perfiles && $resultado_perfiles->num_rows > 0) {
                while ($fila = $resultado_perfiles->fetch_assoc()) {
                    echo "<option value='" . $fila['id'] . "' data-descripcion='" . htmlspecialchars($fila['descripcion']) . "'>" 
                         . htmlspecialchars($fila['nombre']) . "</option>";
                }
            } else {
                echo "<option value='' disabled>No hay perfiles disponibles</option>";
            }
            ?>
        </select>
        
        <button type="button" class="btn-limpiar-perfil" onclick="limpiarPerfilSeleccionado()">
            <i class="fas fa-eraser"></i>
            Limpiar
        </button>
    </div>

    <div class="perfil-info" id="perfil-info">
        <i class="fas fa-info-circle"></i>
        <span id="perfil-descripcion"></span>
    </div>
</div>

<script>
    // Mapeo de códigos de permisos a IDs de checkboxes
    const mapaPermisosCheckboxes = {
        'CLI001': 'clientes',
        'CLI002': 'clientes-reporte',
        'CLI003': 'avance-cuenta',
        'CLI004': 'cancel-avance',
        'PRO001': 'productos',
        'PRO002': 'productos-reporte',
        'ALM001': 'almacen',
        'ALM002': 'tran-inventario',
        'ALM003': 'inv-empleados',
        'ALM004': 'entrada-inventario',
        'ALM005': 'salida-inventario',
        'FAC001': 'facturacion',
        'FAC002': 'cancel-facturas',
        'FAC003': 'inf-factura',
        'COT001': 'cot-accion',
        'COT002': 'cot-registro',
        'COT003': 'cot-cancelar',
        'CAJ001': 'caja',
        'CUA001': 'cuadres',
        'CUA002': 'cuadres-accion',
        'PADM001': 'pan-adm',
        'PADM002': 'estadisticas',
        'PADM003': 'bancos-destinos',
        'EMP001': 'empleados'
    };

    // Cargar permisos del perfil seleccionado
    document.getElementById('perfil-permisos-select').addEventListener('change', function() {
        const perfilId = this.value;
        const descripcion = this.options[this.selectedIndex].dataset.descripcion;

        if (!perfilId) return;

        // Mostrar descripción del perfil
        const perfilInfo = document.getElementById('perfil-info');
        const perfilDescripcion = document.getElementById('perfil-descripcion');
        
        if (descripcion) {
            perfilDescripcion.textContent = descripcion;
            perfilInfo.classList.add('active');
        }

        // Obtener permisos del perfil
        fetch(`../../api/perfiles-permisos/obtener-permisos.php?id=${perfilId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.permisos) {
                    // Primero desmarcar todos los checkboxes
                    Object.values(mapaPermisosCheckboxes).forEach(checkboxId => {
                        const checkbox = document.getElementById(checkboxId);
                        if (checkbox) {
                            checkbox.checked = false;
                        }
                    });

                    // Luego marcar los permisos del perfil
                    data.permisos.forEach(codigoPermiso => {
                        const checkboxId = mapaPermisosCheckboxes[codigoPermiso];
                        if (checkboxId) {
                            const checkbox = document.getElementById(checkboxId);
                            if (checkbox) {
                                checkbox.checked = true;
                                // Disparar evento change para activar dependencias
                                checkbox.dispatchEvent(new Event('change'));
                            }
                        }
                    });

                    // Notificación
                    Swal.fire({
                        icon: 'success',
                        title: 'Perfil Cargado',
                        text: 'Los permisos del perfil se han aplicado correctamente',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'No se pudieron cargar los permisos del perfil',
                        confirmButtonText: 'Aceptar'
                    });
                }
            })
            .catch(error => {
                console.error('Error al cargar permisos:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Conexión',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonText: 'Aceptar'
                });
            });
    });

    // Función para limpiar el perfil seleccionado
    function limpiarPerfilSeleccionado() {
        // Resetear el select
        document.getElementById('perfil-permisos-select').value = '';
        
        // Ocultar la descripción
        document.getElementById('perfil-info').classList.remove('active');
        
        // Desmarcar todos los checkboxes
        Object.values(mapaPermisosCheckboxes).forEach(checkboxId => {
            const checkbox = document.getElementById(checkboxId);
            if (checkbox) {
                checkbox.checked = false;
                // Disparar evento change para desactivar dependencias
                checkbox.dispatchEvent(new Event('change'));
            }
        });

        Swal.fire({
            icon: 'info',
            title: 'Permisos Limpiados',
            text: 'Todos los permisos han sido desmarcados',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
</script>