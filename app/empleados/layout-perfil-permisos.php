
<div class="perfil-permisos">
    <label for="perfil-permisos-select">Elegir Perfil de Permiso:</label>
    <select id="perfil-permisos-select">
        <option value="" disabled selected>Seleccionar</option>

        <?php
        $sql_puestos = "SELECT id, descripcion FROM empleados_puestos ORDER BY descripcion ASC";
        $resultado_puestos = $conn->query($sql_puestos);

        if ($resultado_puestos->num_rows > 0) {
            while ($fila = $resultado_puestos->fetch_assoc()) {
                echo "<option value='" . $fila['id'] . "'>" . $fila['descripcion'] . "</option>";
            }
        } else {
            echo "<option value='' disabled selected>No hay opciones</option>";
        }
        ?>

    </select>
</div>