<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Actualizar Producto</title>
    <link rel="stylesheet" href="../../assets/css/producto_modal.css">
    <link rel="icon" href="../../assets/img/logo-ico.ico" type="image/x-icon">
</head>
<body>
    <!-- Modal de actualización de producto -->
    <div id="modalActualizar" class="modal-actualizar-producto">
        <div class="modal-actualizar-contenedor">
            <span class="cerrar-actualizar" onclick="cerrarModal()">&times;</span>
            <h3 class="form-title">Actualizar Producto</h3>
            <form class="registration-form" action="../../api/productos/producto_actualizar.php" method="POST">
                <fieldset>
                    <legend>Datos del Producto</legend>
                
                    <!-- Campo oculto para el ID del producto -->
                    <input type="hidden" id="idProducto" name="idProducto" value="<?php echo $idProducto; ?>">

                    <div class="form-grid">
                        <!-- Descripción ocupa 2 columnas -->
                        <div class="modal-input1-form-group">
                            <label for="descripcion">Descripción:</label>
                            <input type="text" id="descripcion" name="descripcion" class="modal-input" required>
                        </div>

                        <!-- Precio de compra -->
                        <div class="form-group">
                            <label for="precioCompra">Precio Compra:</label>
                            <input type="number" id="precioCompra" name="precioCompra" min="0" step="0.01" class="modal-input" readonly style="cursor: not-allowed !important;">
                        </div>

                        <!-- Precio Venta 1 -->
                        <div class="form-group">
                            <label for="precioVenta1">Precio Venta 1:</label>
                            <input type="number" id="precioVenta1" name="precioVenta1" min="0" step="0.01" class="modal-input" required>
                        </div>

                        <!-- Precio Venta 2 -->
                        <div class="form-group">
                            <label for="precioVenta2">Precio Venta 2:</label>
                            <input type="number" id="precioVenta2" name="precioVenta2" min="0" step="0.01" class="modal-input" required>
                        </div>

                        <!-- Reorden -->
                        <div class="form-group">
                            <label for="reorden">Reorden:</label>
                            <input type="number" id="reorden" name="reorden" min="0" class="modal-input" required>
                        </div>

                        <!-- Tipo de Producto -->
                        <div class="form-group">
                            <label for="tipo">Tipo de Producto:</label>
                            <select id="tipo" name="tipo" class="modal-input" required>
                                <?php foreach ($tipos_producto as $tipo): ?>
                                    <option value="<?php echo $tipo['id']; ?>"><?php echo htmlspecialchars($tipo['descripcion']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Estado -->
                        <div class="form-group">
                            <label for="activo">Estado:</label>
                            <select id="activo" name="activo" class="modal-input" required>
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <!-- Botón de actualización -->
                    <button type="submit" class="button-actualizar-modal">Actualizar</button>
                </fieldset>
            </form>
        </div>
    </div>
</body>
</html>