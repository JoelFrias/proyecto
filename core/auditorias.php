<?php

// Función auxiliar para auditar acciones de caja
function registrarAuditoriaCaja($conn, $usuario_id, $accion, $detalles) {
    $sql = "INSERT INTO auditoria_caja (empleado_id, accion, detalles, fecha, ip) 
            VALUES (?, ?, ?, NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    $stmt->bind_param("isss", $usuario_id, $accion, $detalles, $ip);
    $stmt->execute();
}


// Funcion axuliar para auditar acciones de usuarios
function registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalles) {
    $sql = "INSERT INTO auditoria_usuarios (empleado_id, accion, detalles, fecha, ip) 
            VALUES (?, ?, ?, NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
    $stmt->bind_param("isss", $usuario_id, $accion, $detalles, $ip);
    $stmt->execute();
}


?>