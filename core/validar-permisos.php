<?php
function validarPermiso($conn, $permiso_necesario, $id_empleado) {
    if (empty($id_empleado) || empty($permiso_necesario)) {
        return false;
    }
    
    $sql = "SELECT COUNT(*) as tiene_permiso 
            FROM usuarios_permisos 
            WHERE id_permiso = ? AND id_empleado = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Error preparando consulta: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("si", $permiso_necesario, $id_empleado);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return ($row['tiene_permiso'] > 0);
}
?>