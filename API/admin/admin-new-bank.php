<?php
require_once '../../core/conexion.php';  // Conexión a la base de datos
require_once '../../core/verificar-sesion.php';    // Verificar sesión activa

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'PADM003';
$id_empleado = $_SESSION['idEmpleado'];
if (!validarPermiso($conn, $permiso_necesario, $id_empleado)) {
    http_response_code(403);
    die(json_encode([
        "success" => false, 
        "error" => "No tiene permisos para realizar esta acción",
        "error_code" => "INSUFFICIENT_PERMISSIONS",
        "solution" => "Contacte al administrador del sistema para obtener los permisos necesarios"
    ]));
}

// Validar metodo de entrada
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    die(json_encode([
        "success" => false, 
        "error" => "Método no permitido"
    ]));
}

// Obtener datos JSON
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Verificar si el JSON es válido
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'JSON inválido',
        'details' => json_last_error_msg()
    ]));
}

try {
    // Validaciones esenciales
    $requiredFields = ['nameBank'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception("Campos obligatorios faltantes: " . implode(', ', $missingFields));
    }

    // Sanitización y asignación de variables
    $nameBank = $conn->real_escape_string($data['nameBank']);

    // Crear una transacción
    $conn->begin_transaction();

    // Agregar el nuevo banco a la base de datos
    $stmt = $conn->prepare("INSERT INTO bancos (nombreBanco, enable) VALUES (?, TRUE)");

    if (!$stmt) {
        throw new Exception("Error en la preparación de la consulta: " . $conn->error);
    }

    $stmt->bind_param("s", $nameBank);

    if (!$stmt->execute()) {
        throw new Exception("Error al agregar el banco: " . $stmt->error);
    }

    
    $result = $conn->query("SELECT LAST_INSERT_ID() as last_id");

    if (!$result) {
        throw new Exception("Error al obtener el ID del último banco insertado: " . $conn->error);
    }
    
    $row = $result->fetch_assoc();
    $newId = $row['last_id'];
    
    $conn->commit();
    
    // Asegurar que no haya salida antes de este punto
    echo json_encode([
        'success' => true, 
        'message' => 'El banco ha sido agregado exitosamente',
        'data' => [
            'id' => $newId,
            'nameBank' => $nameBank
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'errorCode' => $e->getCode()
    ]);
    
} finally {
    if (isset($conn) && $conn->ping()) {
        // $conn->close();
    }
}
?>