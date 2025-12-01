<?php
// api/perfiles-permisos/listar.php

header('Content-Type: application/json');

require_once '../../core/conexion.php';
require_once '../../core/verificar-sesion.php';

// Verificar conexión a la base de datos
if (!$conn || $conn->connect_errno !== 0) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error de conexión a la base de datos"
    ]);
    exit();
}

try {
    // Obtener todos los perfiles con el conteo de permisos
    $sql = "SELECT 
                pp.id,
                pp.nombre,
                pp.descripcion,
                pp.activo,
                pp.fecha_creacion,
                COUNT(ppd.id) as total_permisos
            FROM perfiles_permisos pp
            LEFT JOIN perfiles_permisos_detalle ppd ON pp.id = ppd.id_perfil
            GROUP BY pp.id
            ORDER BY pp.nombre ASC";
    
    $result = $conn->query($sql);
    
    $perfiles = [];
    while ($row = $result->fetch_assoc()) {
        $perfiles[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'activo' => (bool)$row['activo'],
            'fecha_creacion' => $row['fecha_creacion'],
            'total_permisos' => (int)$row['total_permisos']
        ];
    }
    
    echo json_encode([
        "success" => true,
        "perfiles" => $perfiles
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener perfiles: " . $e->getMessage()
    ]);
}

$conn->close();
?>