-- ============================================
-- CREAR BASE DE DATOS DE AUDITORÍA
-- ============================================
CREATE DATABASE IF NOT EXISTS easypos_auditoria 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_general_ci;

USE easypos_auditoria;

-- ============================================
-- TABLA PRINCIPAL DE AUDITORÍA
-- ============================================
CREATE TABLE auditoria_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    base_datos VARCHAR(100) NOT NULL,
    tabla VARCHAR(100) NOT NULL,
    operacion ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    id_registro VARCHAR(50) NULL COMMENT 'ID del registro afectado',
    usuario_db VARCHAR(100) DEFAULT NULL COMMENT 'Usuario de MySQL',
    usuario_app INT(11) DEFAULT NULL COMMENT 'ID del empleado/usuario de la aplicación',
    ip_address VARCHAR(45) DEFAULT NULL,
    datos_anteriores JSON NULL COMMENT 'Datos antes del cambio (UPDATE/DELETE)',
    datos_nuevos JSON NULL COMMENT 'Datos después del cambio (INSERT/UPDATE)',
    cambios_detectados JSON NULL COMMENT 'Solo los campos que cambiaron',
    fecha_operacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tabla (tabla),
    INDEX idx_operacion (operacion),
    INDEX idx_fecha (fecha_operacion),
    INDEX idx_usuario_app (usuario_app),
    INDEX idx_id_registro (id_registro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
COMMENT='Registro completo de todas las operaciones en la BD de producción';

-- ============================================
-- TABLA DE RESUMEN DE AUDITORÍA POR TABLA
-- ============================================
CREATE TABLE auditoria_resumen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabla VARCHAR(100) NOT NULL,
    total_inserts INT DEFAULT 0,
    total_updates INT DEFAULT 0,
    total_deletes INT DEFAULT 0,
    ultima_operacion DATETIME DEFAULT NULL,
    UNIQUE KEY uk_tabla (tabla)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Resumen estadístico de operaciones por tabla';

-- ============================================
-- TABLA DE SESIONES DE AUDITORÍA
-- ============================================
CREATE TABLE auditoria_sesiones (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_app INT(11) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    fecha_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME DEFAULT NULL,
    total_operaciones INT DEFAULT 0,
    INDEX idx_usuario (usuario_app),
    INDEX idx_fecha_inicio (fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Registro de sesiones de usuarios para contexto de auditoría';

-- ============================================
-- TABLA DE CONFIGURACIÓN DE AUDITORÍA
-- ============================================
CREATE TABLE auditoria_configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabla VARCHAR(100) NOT NULL,
    auditar_insert BOOLEAN DEFAULT TRUE,
    auditar_update BOOLEAN DEFAULT TRUE,
    auditar_delete BOOLEAN DEFAULT TRUE,
    columnas_excluidas TEXT NULL COMMENT 'Columnas a excluir de auditoría, separadas por coma',
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tabla (tabla)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Configuración de qué tablas y operaciones auditar';

-- ============================================
-- VISTA: AUDITORÍA CON INFORMACIÓN DE USUARIO
-- ============================================
CREATE VIEW v_auditoria_completa AS
SELECT 
    al.id,
    al.base_datos,
    al.tabla,
    al.operacion,
    al.id_registro,
    al.usuario_db,
    al.usuario_app,
    CONCAT(e.nombre, ' ', e.apellido) AS nombre_empleado,
    u.username,
    al.ip_address,
    al.datos_anteriores,
    al.datos_nuevos,
    al.cambios_detectados,
    al.fecha_operacion
FROM auditoria_log al
LEFT JOIN easypos.empleados e ON al.usuario_app = e.id
LEFT JOIN easypos.usuarios u ON u.idEmpleado = al.usuario_app
ORDER BY al.fecha_operacion DESC;

-- ============================================
-- VISTA: RESUMEN DE ACTIVIDAD POR USUARIO
-- ============================================
CREATE VIEW v_auditoria_por_usuario AS
SELECT 
    al.usuario_app,
    CONCAT(e.nombre, ' ', e.apellido) AS nombre_empleado,
    u.username,
    COUNT(*) AS total_operaciones,
    SUM(CASE WHEN al.operacion = 'INSERT' THEN 1 ELSE 0 END) AS total_inserts,
    SUM(CASE WHEN al.operacion = 'UPDATE' THEN 1 ELSE 0 END) AS total_updates,
    SUM(CASE WHEN al.operacion = 'DELETE' THEN 1 ELSE 0 END) AS total_deletes,
    MIN(al.fecha_operacion) AS primera_operacion,
    MAX(al.fecha_operacion) AS ultima_operacion
FROM auditoria_log al
LEFT JOIN easypos.empleados e ON al.usuario_app = e.id
LEFT JOIN easypos.usuarios u ON u.idEmpleado = al.usuario_app
GROUP BY al.usuario_app, nombre_empleado, u.username;

-- ============================================
-- VISTA: ACTIVIDAD RECIENTE (ÚLTIMAS 24 HORAS)
-- ============================================
CREATE VIEW v_auditoria_reciente AS
SELECT 
    al.id,
    al.tabla,
    al.operacion,
    al.id_registro,
    CONCAT(e.nombre, ' ', e.apellido) AS usuario,
    al.fecha_operacion,
    TIMESTAMPDIFF(MINUTE, al.fecha_operacion, NOW()) AS minutos_transcurridos
FROM auditoria_log al
LEFT JOIN easypos.empleados e ON al.usuario_app = e.id
WHERE al.fecha_operacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY al.fecha_operacion DESC;

-- ============================================
-- INSERTAR CONFIGURACIÓN INICIAL
-- ============================================
INSERT INTO auditoria_configuracion (tabla, auditar_insert, auditar_update, auditar_delete, activo) VALUES
('productos', TRUE, TRUE, TRUE, TRUE),
('clientes', TRUE, TRUE, TRUE, TRUE),
('empleados', TRUE, TRUE, TRUE, TRUE),
('usuarios', TRUE, TRUE, TRUE, TRUE),
('facturas', TRUE, TRUE, TRUE, TRUE),
('inventario', TRUE, TRUE, TRUE, TRUE),
('inventario_entradas', TRUE, TRUE, TRUE, TRUE),
('inventario_salidas', TRUE, TRUE, TRUE, TRUE),
('cajasabiertas', TRUE, TRUE, TRUE, TRUE),
('cajascerradas', TRUE, TRUE, TRUE, TRUE);