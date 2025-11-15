<?php
/**
 * Archivo de conexión a la base de datos MySQL
 * Preparado para entorno de producción
 * Con configuración de zona horaria para Santo Domingo (UTC-4)
 */

// Evitar mostrar errores en producción
error_reporting(0);
ini_set('display_errors', 0);

// Establecer zona horaria para PHP
date_default_timezone_set('America/Santo_Domingo');

// Datos de conexión
$servername = "localhost";
$username = "root";
$password = "Joelbless23";
$dbname = "easypos";

// Manejo de errores personalizado
function gestionarErrorConexion($mensaje) {
    
    // Mostrar página de error estilizada
    header("HTTP/1.1 503 Servicio no disponible");

    echo '
        <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Error de conexión</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background-color: #f8f9fa;
                        margin: 0;
                        padding: 0;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                    }
                    .error-container {
                        background-color: white;
                        border-radius: 8px;
                        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                        padding: 30px;
                        width: 80%;
                        max-width: 500px;
                        text-align: center;
                    }
                    .error-icon {
                        color: #dc3545;
                        font-size: 48px;
                        margin-bottom: 20px;
                    }
                    h1 {
                        color: #dc3545;
                        margin-bottom: 15px;
                    }
                    p {
                        color: #6c757d;
                        margin-bottom: 20px;
                        line-height: 1.5;
                    }
                    .btn {
                        display: inline-block;
                        background-color: #0d6efd;
                        color: white;
                        padding: 10px 20px;
                        border-radius: 5px;
                        text-decoration: none;
                        font-weight: bold;
                        transition: background-color 0.3s;
                    }
                    .btn:hover {
                        background-color: #0b5ed7;
                    }
                    .details {
                        margin-top: 20px;
                        padding: 15px;
                        background-color:rgb(229, 229, 229);
                        border-radius: 5px;
                        font-size: 14px;
                        color:rgb(52, 57, 60);
                        text-align: left;
                    }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <div class="error-icon">⚠️</div>
                    <h1>No se pudo conectar al servidor</h1>
                    <p>Lo sentimos, no se ha podido establecer conexión con el servidor de base de datos.</p>
                    <p>Por favor, inténtelo de nuevo más tarde.</p>
                    <!-- <a href="/" class="btn">Reportar error</a> -->
                    <div class="details">
                        <p>Error: No se puede establecer conexión con la base de datos en este momento.</p>
                        <!-- <p>Código de referencia: ERR-' . time() . '</p> -->
                    </div>
                </div>

                <script>
                    console.error("Error de conexión a la base de datos: ' . addslashes($mensaje) . '");
                </script>
            

            </body>
            </html>
    ';

    exit();
}

try {
    // Crear conexión
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Verificar conexión
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
    
    // Establecer conjunto de caracteres
    $conn->set_charset("utf8mb4");
    
    // Configurar el modo estricto de SQL
    $conn->query("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
    
    // Establecer zona horaria para la conexión MySQL (Santo Domingo = UTC-4)
    $conn->query("SET time_zone = '-04:00'");
    
} catch (Exception $e) {
    gestionarErrorConexion($e->getMessage());
}
?>