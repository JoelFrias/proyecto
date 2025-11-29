<?php
// Incluir archivo de configuración de la base de datos
require_once '../../core/conexion.php';		// Conexión a la base de datos

// Verificar conexión a la base de datos
if (!$conn || !$conn->connect_errno === 0) {
    http_response_code(500);
    die(json_encode([
        "success" => false,
        "error" => "Error de conexión a la base de datos",
        "error_code" => "DATABASE_CONNECTION_ERROR"
    ]));
}
require_once '../../core/verificar-sesion.php';

// Validar permisos de usuario
require_once '../../core/validar-permisos.php';
$permiso_necesario = 'PRO001';
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

// Verificar la acción solicitada
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Procesamiento según la acción
switch ($action) {
    case 'getAll':
        // Obtener todos los tipos de producto
        getTiposProducto();
        break;
    case 'create':
        // Crear un nuevo tipo de producto
        if (isset($_POST['descripcion'])) {
            crearTipoProducto($_POST['descripcion']);
        } else {
            responder('error', 'Datos incompletos');
        }
        break;
    case 'update':
        // Actualizar un tipo de producto existente
        if (isset($_POST['id']) && isset($_POST['descripcion'])) {
            actualizarTipoProducto($_POST['id'], $_POST['descripcion']);
        } else {
            responder('error', 'Datos incompletos');
        }
        break;
    case 'delete':
        // Eliminar un tipo de producto
        if (isset($_POST['id'])) {
            eliminarTipoProducto($_POST['id']);
        } else {
            responder('error', 'ID no proporcionado');
        }
        break;
    default:
        responder('error', 'Acción no válida');
        break;
}

/**
 * Obtiene todos los tipos de producto
 */
function getTiposProducto() {
    global $conn;
    
    try {
        // Preparar consulta
        $sql = "SELECT id, descripcion FROM productos_tipo ORDER BY descripcion ASC";
        $result = $conn->query($sql);
        
        // Verificar si hay resultados
        if ($result !== false && $result !== null) {
            $tipos = [];
            while ($row = $result->fetch_assoc()) {
                $tipos[] = $row;
            }
            responder('success', 'Tipos de producto obtenidos correctamente', ['tipos' => $tipos]);
        } else {
            responder('error', 'Error al obtener los tipos de producto: ' . $conn->error);
        }
    } catch (Exception $e) {
        responder('error', 'Excepción: ' . $e->getMessage());
    }
}

/**
 * Crea un nuevo tipo de producto
 */

function crearTipoProducto($descripcion) {
    
    global $conn;
    
    // Guard: asegura que $conn es un objeto mysqli antes de usarlo
    if (!($conn instanceof mysqli)) {
        responder('error', 'Conexión a la base de datos no inicializada');
        return;
    }
    
    // Validación básica
    $descripcion = trim($descripcion);
    if (empty($descripcion)) {
        responder('error', 'La descripción no puede estar vacía');
        return;
    }
    
    try {
        // Verificar si ya existe un tipo con la misma descripción
        $stmt = $conn->prepare("SELECT id FROM productos_tipo WHERE descripcion = ?");
        $stmt->bind_param("s", $descripcion);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            responder('error', 'Ya existe un tipo de producto con esa descripción');
            return;
        }
        
        // Insertar nuevo tipo
        $stmt = $conn->prepare("INSERT INTO productos_tipo (descripcion) VALUES (?)");
        $stmt->bind_param("s", $descripcion);
        
        if ($stmt->execute()) {
            responder('success', 'Tipo de producto creado correctamente', ['id' => $conn->insert_id]);
        } else {
            responder('error', 'Error al crear el tipo de producto: ' . $stmt->error);
        }
    } catch (Exception $e) {
        responder('error', 'Excepción: ' . $e->getMessage());
    }
}

/**
 * Actualiza un tipo de producto existente
 */
function actualizarTipoProducto($id, $descripcion) {
    global $conn;
    
    // Validación básica
    $id = intval($id);
    $descripcion = trim($descripcion);
    
    if ($id <= 0) {
        responder('error', 'ID de tipo de producto no válido');
        return;
    }
    
    if (empty($descripcion)) {
        responder('error', 'La descripción no puede estar vacía');
        return;
    }
    
    try {
        // Verificar si ya existe otro tipo con la misma descripción
        $stmt = $conn->prepare("SELECT id FROM productos_tipo WHERE descripcion = ? AND id != ?");
        $stmt->bind_param("si", $descripcion, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            responder('error', 'Ya existe otro tipo de producto con esa descripción');
            return;
        }
        
        // Actualizar el tipo
        $stmt = $conn->prepare("UPDATE productos_tipo SET descripcion = ? WHERE id = ?");
        $stmt->bind_param("si", $descripcion, $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                responder('success', 'Tipo de producto actualizado correctamente');
            } else {
                // No se encontró el ID o no hubo cambios
                responder('error', 'No se encontró el tipo de producto o no hubo cambios');
            }
        } else {
            responder('error', 'Error al actualizar el tipo de producto: ' . $stmt->error);
        }
    } catch (Exception $e) {
        responder('error', 'Excepción: ' . $e->getMessage());
    }
}

/**
 * Elimina un tipo de producto
 */
function eliminarTipoProducto($id) {
    global $conn;
    
    // Validación básica
    $id = intval($id);
    if ($id <= 0) {
        responder('error', 'ID de tipo de producto no válido');
        return;
    }
    
    try {
        // Verificar primero si hay productos que utilizan este tipo
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM productos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['total'] > 0) {
            responder('error', 'No se puede eliminar porque hay productos asociados a este tipo');
            return;
        }
        
        // Eliminar el tipo
        $stmt = $conn->prepare("DELETE FROM productos_tipo WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                responder('success', 'Tipo de producto eliminado correctamente');
            } else {
                responder('error', 'No se encontró el tipo de producto');
            }
        } else {
            responder('error', 'Error al eliminar el tipo de producto: ' . $stmt->error);
        }
    } catch (Exception $e) {
        responder('error', 'Excepción: ' . $e->getMessage());
    }
}

/**
 * Función para enviar respuesta en formato JSON
 */
function responder($status, $message, $data = []) {
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>