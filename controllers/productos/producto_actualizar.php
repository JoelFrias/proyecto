<?php
session_start();
require '../../models/conexion.php';

// Validar si el formulario fue enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Obtener y sanitizar los datos del formulario
    $idProducto = isset($_POST['idProducto']) ? intval($_POST['idProducto']) : 0;
    $descripcion = isset($_POST['descripcion']) ? htmlspecialchars(trim($_POST['descripcion'])) : "";
    $precioCompra = isset($_POST['precioCompra']) ? floatval($_POST['precioCompra']) : 0.0;
    $precioVenta1 = isset($_POST['precioVenta1']) ? floatval($_POST['precioVenta1']) : 0.0;
    $precioVenta2 = isset($_POST['precioVenta2']) ? floatval($_POST['precioVenta2']) : 0.0;
    $reorden = isset($_POST['reorden']) ? floatval($_POST['reorden']) : 0.0;
    $activo = isset($_POST['activo']) ? intval($_POST['activo']) : 0;
    $tipo = isset($_POST['tipo']) ? intval($_POST['tipo']) : 0;

    // Validar que el ID del producto sea válido
    if ($idProducto <= 0) {
        $_SESSION['errors'][] = "ID de producto no válido.";
        header("Location: ../../views/productos/productos.php");
        exit;
    }

    // Validar que el tipo de producto sea válido
    if ($tipo <= 0) {
        $_SESSION['errors'][] = "Tipo de producto no válido.";
        header("Location: ../../views/productos/productos.php");
        exit;
    }

    // Validar que los campos obligatorios no estén vacíos
    if (empty($descripcion) || $precioCompra < 0 || $precioVenta1 < 0 || $precioVenta2 < 0 || $reorden < 0) {
        $_SESSION['errors'][] = "Por favor, complete todos los campos correctamente.";
        header("Location: ../../views/productos/productos.php");
        exit;
    }

    // Manejo de errores con consultas preparadas
    try {
        // Iniciar la transacción
        $conn->begin_transaction();

        // Actualizar la tabla 'productos'
        $stmt = $conn->prepare("UPDATE productos SET descripcion = ?, precioCompra = ?, precioVenta1 = ?, precioVenta2 = ?, reorden = ?, activo = ?, idTipo = ? WHERE id = ?");

        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }

        $stmt->bind_param("sddddiii", $descripcion, $precioCompra, $precioVenta1, $precioVenta2, $reorden, $activo, $tipo, $idProducto);
        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }

        /**
         *  2. Auditoria de acciones de usuario
         */

        require_once '../../models/auditorias.php';
        $usuario_id = $_SESSION['idEmpleado'];
        $accion = 'Actualizar producto';
        $detalle = 'Producto actualizado: ' . $descripcion . ', ID: ' . $idProducto;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'DESCONOCIDA';
        registrarAuditoriaUsuarios($conn, $usuario_id, $accion, $detalle, $ip);

        // Confirmar la transacción
        $conn->commit();

        // Almacenar mensaje de éxito en sesión y redirigir
        $_SESSION['status'] = 'success';
        $_SESSION['message'] = 'Producto actualizado correctamente.';
        header("Location: ../../views/productos/productos.php");
        exit;

    } catch (Exception $e) {
        // En caso de error, revertir la transacción
        if ($conn) {
            $conn->rollback();
        }
        $_SESSION['errors'][] = "Error al actualizar producto: " . $e->getMessage();
        header("Location: ../../views/productos/productos.php");
        exit;
    } finally {
        // Cerrar las declaraciones preparadas
        if (isset($stmt)) {
            $stmt->close();
        }
        // Cerrar la conexión a la base de datos
        if ($conn) {
            $conn->close();
        }
    }
}
?>