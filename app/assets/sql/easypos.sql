-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 02, 2025 at 03:27 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

CREATE DATABASE IF NOT EXISTS easypos 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_general_ci;

USE easypos;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-04:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Table structure for table `bancos`
--

CREATE TABLE `bancos` (
  `id` int(6) NOT NULL,
  `nombreBanco` varchar(100) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bancos`
--

INSERT INTO `bancos` (`id`, `nombreBanco`, `enable`) VALUES
(1, 'N/A', 1),
(3, 'Banreservas', 1),
(4, 'BHD', 1),
(5, 'Popular', 1),
(6, 'Asociación Cibao', 1);

-- --------------------------------------------------------

--
-- Table structure for table `cajacontador`
--

CREATE TABLE `cajacontador` (
  `contador` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cajacontador`
--

INSERT INTO `cajacontador` (`contador`) VALUES
(1);

-- --------------------------------------------------------

--
-- Table structure for table `cajaegresos`
--

CREATE TABLE `cajaegresos` (
  `id` int(6) NOT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `monto` decimal(15,2) DEFAULT NULL,
  `IdEmpleado` int(6) DEFAULT NULL,
  `numCaja` varchar(5) DEFAULT NULL,
  `razon` varchar(50) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `cajaegresos`
--
DELIMITER $$
CREATE TRIGGER `trg_cajaegresos_after_delete` AFTER DELETE ON `cajaegresos` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'cajaegresos', 'DELETE', OLD.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'monto', OLD.monto, 'numCaja', OLD.numCaja, 'razon', OLD.razon),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('cajaegresos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cajaegresos_after_insert` AFTER INSERT ON `cajaegresos` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'cajaegresos', 'INSERT', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id', NEW.id,
            'metodo', NEW.metodo,
            'monto', NEW.monto,
            'IdEmpleado', NEW.IdEmpleado,
            'numCaja', NEW.numCaja,
            'razon', NEW.razon,
            'fecha', NEW.fecha
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('cajaegresos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cajaegresos_after_update` AFTER UPDATE ON `cajaegresos` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.monto != NEW.monto THEN
        SET cambios = JSON_SET(cambios, '$.monto', JSON_OBJECT('anterior', OLD.monto, 'nuevo', NEW.monto));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'cajaegresos', 'UPDATE', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'monto', OLD.monto, 'razon', OLD.razon),
        JSON_OBJECT('id', NEW.id, 'monto', NEW.monto, 'razon', NEW.razon),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('cajaegresos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `cajaingresos`
--

CREATE TABLE `cajaingresos` (
  `id` int(6) NOT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `monto` decimal(15,2) DEFAULT NULL,
  `IdEmpleado` int(6) DEFAULT NULL,
  `numCaja` varchar(5) DEFAULT NULL,
  `razon` varchar(50) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `cajaingresos`
--
DELIMITER $$
CREATE TRIGGER `trg_cajaingresos_after_delete` AFTER DELETE ON `cajaingresos` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'cajaingresos', 'DELETE', OLD.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'monto', OLD.monto, 'numCaja', OLD.numCaja, 'razon', OLD.razon),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('cajaingresos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cajaingresos_after_insert` AFTER INSERT ON `cajaingresos` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'cajaingresos', 'INSERT', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id', NEW.id,
            'metodo', NEW.metodo,
            'monto', NEW.monto,
            'IdEmpleado', NEW.IdEmpleado,
            'numCaja', NEW.numCaja,
            'razon', NEW.razon,
            'fecha', NEW.fecha
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('cajaingresos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cajaingresos_after_update` AFTER UPDATE ON `cajaingresos` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.monto != NEW.monto THEN
        SET cambios = JSON_SET(cambios, '$.monto', JSON_OBJECT('anterior', OLD.monto, 'nuevo', NEW.monto));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'cajaingresos', 'UPDATE', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'monto', OLD.monto, 'razon', OLD.razon),
        JSON_OBJECT('id', NEW.id, 'monto', NEW.monto, 'razon', NEW.razon),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('cajaingresos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `cajasabiertas`
--

CREATE TABLE `cajasabiertas` (
  `numCaja` varchar(5) DEFAULT NULL,
  `idEmpleado` int(6) DEFAULT NULL,
  `fechaApertura` datetime DEFAULT NULL,
  `saldoApertura` decimal(15,2) DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `cajasabiertas`
--
DELIMITER $$
CREATE TRIGGER `trg_cajasabiertas_after_delete` AFTER DELETE ON `cajasabiertas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'cajasabiertas', 'DELETE', OLD.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('numCaja', OLD.numCaja, 'idEmpleado', OLD.idEmpleado, 'saldoApertura', OLD.saldoApertura),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('cajasabiertas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cajasabiertas_after_insert` AFTER INSERT ON `cajasabiertas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'cajasabiertas', 'INSERT', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'registro', NEW.registro,
            'numCaja', NEW.numCaja,
            'idEmpleado', NEW.idEmpleado,
            'fechaApertura', NEW.fechaApertura,
            'saldoApertura', NEW.saldoApertura
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('cajasabiertas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cajasabiertas_after_update` AFTER UPDATE ON `cajasabiertas` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.saldoApertura != NEW.saldoApertura THEN
        SET cambios = JSON_SET(cambios, '$.saldoApertura', JSON_OBJECT('anterior', OLD.saldoApertura, 'nuevo', NEW.saldoApertura));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'cajasabiertas', 'UPDATE', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('numCaja', OLD.numCaja, 'idEmpleado', OLD.idEmpleado, 'saldoApertura', OLD.saldoApertura),
        JSON_OBJECT('numCaja', NEW.numCaja, 'idEmpleado', NEW.idEmpleado, 'saldoApertura', NEW.saldoApertura),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('cajasabiertas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `cajascerradas`
--

CREATE TABLE `cajascerradas` (
  `numCaja` varchar(5) DEFAULT NULL,
  `idEmpleado` int(6) DEFAULT NULL,
  `fechaApertura` datetime DEFAULT NULL,
  `fechaCierre` datetime DEFAULT NULL,
  `saldoInicial` decimal(15,2) DEFAULT NULL,
  `saldoFinal` decimal(15,2) DEFAULT NULL,
  `diferencia` decimal(15,2) DEFAULT NULL,
  `estado` varchar(15) DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `cajascerradas`
--
DELIMITER $$
CREATE TRIGGER `trg_cajascerradas_after_delete` AFTER DELETE ON `cajascerradas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'cajascerradas', 'DELETE', OLD.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('numCaja', OLD.numCaja, 'estado', OLD.estado, 'saldoFinal', OLD.saldoFinal),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('cajascerradas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cajascerradas_after_insert` AFTER INSERT ON `cajascerradas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'cajascerradas', 'INSERT', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'registro', NEW.registro,
            'numCaja', NEW.numCaja,
            'idEmpleado', NEW.idEmpleado,
            'fechaCierre', NEW.fechaCierre,
            'saldoInicial', NEW.saldoInicial,
            'saldoFinal', NEW.saldoFinal,
            'diferencia', NEW.diferencia,
            'estado', NEW.estado
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('cajascerradas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cajascerradas_after_update` AFTER UPDATE ON `cajascerradas` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.estado != NEW.estado THEN
        SET cambios = JSON_SET(cambios, '$.estado', JSON_OBJECT('anterior', OLD.estado, 'nuevo', NEW.estado));
    END IF;
    
    IF OLD.saldoFinal != NEW.saldoFinal THEN
        SET cambios = JSON_SET(cambios, '$.saldoFinal', JSON_OBJECT('anterior', OLD.saldoFinal, 'nuevo', NEW.saldoFinal));
    END IF;
    
    IF OLD.diferencia != NEW.diferencia THEN
        SET cambios = JSON_SET(cambios, '$.diferencia', JSON_OBJECT('anterior', OLD.diferencia, 'nuevo', NEW.diferencia));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'cajascerradas', 'UPDATE', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('numCaja', OLD.numCaja, 'estado', OLD.estado, 'saldoFinal', OLD.saldoFinal, 'diferencia', OLD.diferencia),
        JSON_OBJECT('numCaja', NEW.numCaja, 'estado', NEW.estado, 'saldoFinal', NEW.saldoFinal, 'diferencia', NEW.diferencia),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('cajascerradas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `caja_estado_detalle`
--

CREATE TABLE `caja_estado_detalle` (
  `numCaja` varchar(20) NOT NULL,
  `id_empleado` int(11) NOT NULL,
  `nota` text NOT NULL,
  `fecha` datetime NOT NULL,
  `registro` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int(6) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `empresa` varchar(100) DEFAULT NULL,
  `tipo_identificacion` varchar(15) DEFAULT NULL,
  `identificacion` varchar(20) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `notas` varchar(500) DEFAULT NULL,
  `fechaRegistro` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `clientes`
--
DELIMITER $$
CREATE TRIGGER `trg_clientes_after_delete` AFTER DELETE ON `clientes` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'clientes', 'DELETE', OLD.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'nombre', OLD.nombre, 'apellido', OLD.apellido, 'identificacion', OLD.identificacion),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('clientes', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_clientes_after_insert` AFTER INSERT ON `clientes` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'clientes', 'INSERT', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id', NEW.id,
            'nombre', NEW.nombre,
            'apellido', NEW.apellido,
            'empresa', NEW.empresa,
            'tipo_identificacion', NEW.tipo_identificacion,
            'identificacion', NEW.identificacion,
            'telefono', NEW.telefono,
            'activo', NEW.activo
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('clientes', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_clientes_after_update` AFTER UPDATE ON `clientes` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.nombre != NEW.nombre THEN
        SET cambios = JSON_SET(cambios, '$.nombre', JSON_OBJECT('anterior', OLD.nombre, 'nuevo', NEW.nombre));
    END IF;
    
    IF OLD.apellido != NEW.apellido THEN
        SET cambios = JSON_SET(cambios, '$.apellido', JSON_OBJECT('anterior', OLD.apellido, 'nuevo', NEW.apellido));
    END IF;
    
    IF OLD.telefono != NEW.telefono THEN
        SET cambios = JSON_SET(cambios, '$.telefono', JSON_OBJECT('anterior', OLD.telefono, 'nuevo', NEW.telefono));
    END IF;
    
    IF OLD.activo != NEW.activo THEN
        SET cambios = JSON_SET(cambios, '$.activo', JSON_OBJECT('anterior', OLD.activo, 'nuevo', NEW.activo));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'clientes', 'UPDATE', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'nombre', OLD.nombre, 'apellido', OLD.apellido, 'telefono', OLD.telefono, 'activo', OLD.activo),
        JSON_OBJECT('id', NEW.id, 'nombre', NEW.nombre, 'apellido', NEW.apellido, 'telefono', NEW.telefono, 'activo', NEW.activo),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('clientes', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `clientes_cuenta`
--

CREATE TABLE `clientes_cuenta` (
  `idCliente` int(6) DEFAULT NULL,
  `limite_credito` decimal(15,2) DEFAULT NULL,
  `balance` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `clientes_cuenta`
--
DELIMITER $$
CREATE TRIGGER `trg_clientes_cuenta_after_delete` AFTER DELETE ON `clientes_cuenta` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'clientes_cuenta', 'DELETE', OLD.idCliente,
        USER(), @usuario_app_id,
        JSON_OBJECT('idCliente', OLD.idCliente, 'limite_credito', OLD.limite_credito, 'balance', OLD.balance),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('clientes_cuenta', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_clientes_cuenta_after_insert` AFTER INSERT ON `clientes_cuenta` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'clientes_cuenta', 'INSERT', NEW.idCliente,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'idCliente', NEW.idCliente,
            'limite_credito', NEW.limite_credito,
            'balance', NEW.balance
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('clientes_cuenta', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_clientes_cuenta_after_update` AFTER UPDATE ON `clientes_cuenta` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.limite_credito != NEW.limite_credito THEN
        SET cambios = JSON_SET(cambios, '$.limite_credito', JSON_OBJECT('anterior', OLD.limite_credito, 'nuevo', NEW.limite_credito));
    END IF;
    
    IF OLD.balance != NEW.balance THEN
        SET cambios = JSON_SET(cambios, '$.balance', JSON_OBJECT('anterior', OLD.balance, 'nuevo', NEW.balance, 'diferencia', NEW.balance - OLD.balance));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'clientes_cuenta', 'UPDATE', NEW.idCliente,
        USER(), @usuario_app_id,
        JSON_OBJECT('idCliente', OLD.idCliente, 'limite_credito', OLD.limite_credito, 'balance', OLD.balance),
        JSON_OBJECT('idCliente', NEW.idCliente, 'limite_credito', NEW.limite_credito, 'balance', NEW.balance),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('clientes_cuenta', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `clientes_direcciones`
--

CREATE TABLE `clientes_direcciones` (
  `idCliente` int(6) DEFAULT NULL,
  `no` varchar(15) DEFAULT NULL,
  `calle` varchar(100) DEFAULT NULL,
  `sector` varchar(100) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `referencia` varchar(500) DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clientes_historialpagos`
--

CREATE TABLE `clientes_historialpagos` (
  `idCliente` int(6) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `numCaja` varchar(15) DEFAULT NULL,
  `idEmpleado` int(6) DEFAULT NULL,
  `metodo` varchar(20) NOT NULL,
  `monto` decimal(15,2) DEFAULT NULL,
  `numAutorizacion` varchar(30) NOT NULL,
  `referencia` varchar(30) NOT NULL,
  `idBanco` int(6) NOT NULL,
  `idDestino` int(6) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activo',
  `fecha_cancelacion` datetime DEFAULT NULL,
  `cancelado_por` int(6) DEFAULT NULL,
  `motivo_cancelacion` text DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `clientes_historialpagos`
--
DELIMITER $$
CREATE TRIGGER `trg_clientes_historialpagos_after_delete` AFTER DELETE ON `clientes_historialpagos` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'clientes_historialpagos', 'DELETE', OLD.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('registro', OLD.registro, 'idCliente', OLD.idCliente, 'monto', OLD.monto, 'metodo', OLD.metodo),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('clientes_historialpagos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_clientes_historialpagos_after_insert` AFTER INSERT ON `clientes_historialpagos` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'clientes_historialpagos', 'INSERT', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'registro', NEW.registro,
            'idCliente', NEW.idCliente,
            'fecha', NEW.fecha,
            'numCaja', NEW.numCaja,
            'idEmpleado', NEW.idEmpleado,
            'metodo', NEW.metodo,
            'monto', NEW.monto
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('clientes_historialpagos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_clientes_historialpagos_after_update` AFTER UPDATE ON `clientes_historialpagos` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.monto != NEW.monto THEN
        SET cambios = JSON_SET(cambios, '$.monto', JSON_OBJECT('anterior', OLD.monto, 'nuevo', NEW.monto));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'clientes_historialpagos', 'UPDATE', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('idCliente', OLD.idCliente, 'monto', OLD.monto, 'metodo', OLD.metodo),
        JSON_OBJECT('idCliente', NEW.idCliente, 'monto', NEW.monto, 'metodo', NEW.metodo),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('clientes_historialpagos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `cotizaciones_canceladas`
--

CREATE TABLE `cotizaciones_canceladas` (
  `id_cotizacion` varchar(6) NOT NULL,
  `empleado` int(11) NOT NULL,
  `notas` varchar(100) NOT NULL,
  `fecha` datetime NOT NULL,
  `registro` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `cotizaciones_canceladas`
--
DELIMITER $$
CREATE TRIGGER `trg_cotizaciones_canceladas_after_delete` AFTER DELETE ON `cotizaciones_canceladas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos',
        'cotizaciones_canceladas',
        'DELETE',
        OLD.registro,
        USER(),
        @usuario_app_id,
        JSON_OBJECT(
            'registro', OLD.registro,
            'id_cotizacion', OLD.id_cotizacion,
            'empleado', OLD.empleado,
            'notas', OLD.notas,
            'fecha', OLD.fecha
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('cotizaciones_canceladas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cotizaciones_canceladas_after_insert` AFTER INSERT ON `cotizaciones_canceladas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos',
        'cotizaciones_canceladas',
        'INSERT',
        NEW.registro,
        USER(),
        @usuario_app_id,
        JSON_OBJECT(
            'registro', NEW.registro,
            'id_cotizacion', NEW.id_cotizacion,
            'empleado', NEW.empleado,
            'notas', NEW.notas,
            'fecha', NEW.fecha
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('cotizaciones_canceladas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cotizaciones_canceladas_after_update` AFTER UPDATE ON `cotizaciones_canceladas` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.id_cotizacion != NEW.id_cotizacion THEN
        SET cambios = JSON_SET(cambios, '$.id_cotizacion', JSON_OBJECT('anterior', OLD.id_cotizacion, 'nuevo', NEW.id_cotizacion));
    END IF;
    
    IF OLD.empleado != NEW.empleado THEN
        SET cambios = JSON_SET(cambios, '$.empleado', JSON_OBJECT('anterior', OLD.empleado, 'nuevo', NEW.empleado));
    END IF;
    
    IF OLD.notas != NEW.notas THEN
        SET cambios = JSON_SET(cambios, '$.notas', JSON_OBJECT('anterior', OLD.notas, 'nuevo', NEW.notas));
    END IF;
    
    IF OLD.fecha != NEW.fecha THEN
        SET cambios = JSON_SET(cambios, '$.fecha', JSON_OBJECT('anterior', OLD.fecha, 'nuevo', NEW.fecha));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos',
        'cotizaciones_canceladas',
        'UPDATE',
        NEW.registro,
        USER(),
        @usuario_app_id,
        JSON_OBJECT(
            'registro', OLD.registro,
            'id_cotizacion', OLD.id_cotizacion,
            'empleado', OLD.empleado,
            'notas', OLD.notas,
            'fecha', OLD.fecha
        ),
        JSON_OBJECT(
            'registro', NEW.registro,
            'id_cotizacion', NEW.id_cotizacion,
            'empleado', NEW.empleado,
            'notas', NEW.notas,
            'fecha', NEW.fecha
        ),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('cotizaciones_canceladas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `cotizaciones_contador`
--

CREATE TABLE `cotizaciones_contador` (
  `contador` int(10) DEFAULT NULL,
  `ultima_actualizacion` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cotizaciones_contador`
--

INSERT INTO `cotizaciones_contador` (`contador`, `ultima_actualizacion`) VALUES
(1, NOW());

-- --------------------------------------------------------

--
-- Table structure for table `cotizaciones_det`
--

CREATE TABLE `cotizaciones_det` (
  `no` varchar(10) NOT NULL,
  `id_producto` int(5) NOT NULL,
  `cantidad` decimal(15,2) DEFAULT NULL,
  `precio_s` decimal(15,2) DEFAULT NULL,
  `registro` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `cotizaciones_det`
--
DELIMITER $$
CREATE TRIGGER `trg_cotizaciones_det_after_delete` AFTER DELETE ON `cotizaciones_det` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'cotizaciones_det', 'DELETE', OLD.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('no', OLD.no, 'id_producto', OLD.id_producto, 'cantidad', OLD.cantidad, 'precio_s', OLD.precio_s),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('cotizaciones_det', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cotizaciones_det_after_insert` AFTER INSERT ON `cotizaciones_det` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'cotizaciones_det', 'INSERT', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'registro', NEW.registro,
            'no', NEW.no,
            'id_producto', NEW.id_producto,
            'cantidad', NEW.cantidad,
            'precio_s', NEW.precio_s
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('cotizaciones_det', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cotizaciones_det_after_update` AFTER UPDATE ON `cotizaciones_det` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.cantidad != NEW.cantidad THEN
        SET cambios = JSON_SET(cambios, '$.cantidad', JSON_OBJECT('anterior', OLD.cantidad, 'nuevo', NEW.cantidad));
    END IF;
    
    IF OLD.precio_s != NEW.precio_s THEN
        SET cambios = JSON_SET(cambios, '$.precio_s', JSON_OBJECT('anterior', OLD.precio_s, 'nuevo', NEW.precio_s));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'cotizaciones_det', 'UPDATE', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('no', OLD.no, 'id_producto', OLD.id_producto, 'cantidad', OLD.cantidad, 'precio_s', OLD.precio_s),
        JSON_OBJECT('no', NEW.no, 'id_producto', NEW.id_producto, 'cantidad', NEW.cantidad, 'precio_s', NEW.precio_s),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('cotizaciones_det', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `cotizaciones_inf`
--

CREATE TABLE `cotizaciones_inf` (
  `no` varchar(10) NOT NULL,
  `fecha` datetime NOT NULL,
  `id_cliente` int(5) NOT NULL,
  `id_empleado` int(5) NOT NULL,
  `subtotal` decimal(15,2) DEFAULT NULL,
  `descuento` decimal(15,2) DEFAULT NULL,
  `total` decimal(15,2) DEFAULT NULL,
  `notas` text NOT NULL,
  `estado` varchar(15) NOT NULL,
  `registro` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `cotizaciones_inf`
--
DELIMITER $$
CREATE TRIGGER `trg_cotizaciones_inf_after_delete` AFTER DELETE ON `cotizaciones_inf` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'cotizaciones_inf', 'DELETE', OLD.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('no', OLD.no, 'total', OLD.total, 'estado', OLD.estado),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('cotizaciones_inf', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cotizaciones_inf_after_insert` AFTER INSERT ON `cotizaciones_inf` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'cotizaciones_inf', 'INSERT', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'registro', NEW.registro,
            'no', NEW.no,
            'fecha', NEW.fecha,
            'id_cliente', NEW.id_cliente,
            'id_empleado', NEW.id_empleado,
            'subtotal', NEW.subtotal,
            'descuento', NEW.descuento,
            'total', NEW.total,
            'estado', NEW.estado
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('cotizaciones_inf', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cotizaciones_inf_after_update` AFTER UPDATE ON `cotizaciones_inf` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.estado != NEW.estado THEN
        SET cambios = JSON_SET(cambios, '$.estado', JSON_OBJECT('anterior', OLD.estado, 'nuevo', NEW.estado));
    END IF;
    
    IF OLD.total != NEW.total THEN
        SET cambios = JSON_SET(cambios, '$.total', JSON_OBJECT('anterior', OLD.total, 'nuevo', NEW.total));
    END IF;
    
    IF OLD.descuento != NEW.descuento THEN
        SET cambios = JSON_SET(cambios, '$.descuento', JSON_OBJECT('anterior', OLD.descuento, 'nuevo', NEW.descuento));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'cotizaciones_inf', 'UPDATE', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('no', OLD.no, 'estado', OLD.estado, 'total', OLD.total, 'descuento', OLD.descuento),
        JSON_OBJECT('no', NEW.no, 'estado', NEW.estado, 'total', NEW.total, 'descuento', NEW.descuento),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('cotizaciones_inf', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `destinocuentas`
--

CREATE TABLE `destinocuentas` (
  `id` int(6) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL,
  `enable` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `destinocuentas`
--

INSERT INTO `destinocuentas` (`id`, `descripcion`, `enable`) VALUES
(1, 'N/A', 1),
(2, 'Franklin Frias - 4625 (Banreservas)', 1),
(3, 'Cuenta Corriente - 8986 (Asociación Cibao)', 1);

-- --------------------------------------------------------

--
-- Table structure for table `empleados`
--

CREATE TABLE `empleados` (
  `id` int(6) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `tipo_identificacion` varchar(15) DEFAULT NULL,
  `identificacion` varchar(50) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `idPuesto` int(5) DEFAULT NULL,
  `fechaIngreso` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `empleados`
--

INSERT INTO `empleados` (`id`, `nombre`, `apellido`, `tipo_identificacion`, `identificacion`, `telefono`, `idPuesto`, `fechaIngreso`, `activo`) VALUES
(1, 'Franklin Joel', 'Frias', 'Cedula', '00000000000', '0000000000', 1, '2025-02-18 23:22:40', 1);

--
-- Triggers `empleados`
--
DELIMITER $$
CREATE TRIGGER `trg_empleados_after_delete` AFTER DELETE ON `empleados` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'empleados', 'DELETE', OLD.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'nombre', OLD.nombre, 'apellido', OLD.apellido, 'identificacion', OLD.identificacion),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('empleados', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_empleados_after_insert` AFTER INSERT ON `empleados` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'empleados', 'INSERT', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id', NEW.id,
            'nombre', NEW.nombre,
            'apellido', NEW.apellido,
            'identificacion', NEW.identificacion,
            'telefono', NEW.telefono,
            'idPuesto', NEW.idPuesto,
            'activo', NEW.activo
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('empleados', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_empleados_after_update` AFTER UPDATE ON `empleados` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.nombre != NEW.nombre THEN
        SET cambios = JSON_SET(cambios, '$.nombre', JSON_OBJECT('anterior', OLD.nombre, 'nuevo', NEW.nombre));
    END IF;
    
    IF OLD.apellido != NEW.apellido THEN
        SET cambios = JSON_SET(cambios, '$.apellido', JSON_OBJECT('anterior', OLD.apellido, 'nuevo', NEW.apellido));
    END IF;
    
    IF OLD.telefono != NEW.telefono THEN
        SET cambios = JSON_SET(cambios, '$.telefono', JSON_OBJECT('anterior', OLD.telefono, 'nuevo', NEW.telefono));
    END IF;
    
    IF OLD.idPuesto != NEW.idPuesto THEN
        SET cambios = JSON_SET(cambios, '$.idPuesto', JSON_OBJECT('anterior', OLD.idPuesto, 'nuevo', NEW.idPuesto));
    END IF;
    
    IF OLD.activo != NEW.activo THEN
        SET cambios = JSON_SET(cambios, '$.activo', JSON_OBJECT('anterior', OLD.activo, 'nuevo', NEW.activo));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'empleados', 'UPDATE', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'nombre', OLD.nombre, 'apellido', OLD.apellido, 'idPuesto', OLD.idPuesto, 'activo', OLD.activo),
        JSON_OBJECT('id', NEW.id, 'nombre', NEW.nombre, 'apellido', NEW.apellido, 'idPuesto', NEW.idPuesto, 'activo', NEW.activo),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('empleados', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `empleados_puestos`
--

CREATE TABLE `empleados_puestos` (
  `id` int(6) NOT NULL,
  `descripcion` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `empleados_puestos`
--

INSERT INTO `empleados_puestos` (`id`, `descripcion`) VALUES
(1, 'Administrador'),
(2, 'Encargado de Inventario'),
(3, 'Recepcionista'),
(4, 'Vendedor');

-- --------------------------------------------------------

--
-- Table structure for table `facturas`
--

CREATE TABLE `facturas` (
  `numFactura` varchar(10) DEFAULT NULL,
  `tipoFactura` varchar(10) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `importe` decimal(15,2) DEFAULT NULL,
  `descuento` decimal(15,2) DEFAULT NULL,
  `total` decimal(15,2) DEFAULT NULL,
  `total_ajuste` decimal(15,2) DEFAULT NULL,
  `balance` decimal(15,2) DEFAULT NULL,
  `idCliente` int(6) DEFAULT NULL,
  `idEmpleado` int(6) NOT NULL,
  `estado` varchar(20) DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `facturas`
--
DELIMITER $$
CREATE TRIGGER `trg_facturas_after_delete` AFTER DELETE ON `facturas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'facturas', 'DELETE', OLD.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('numFactura', OLD.numFactura, 'total', OLD.total, 'estado', OLD.estado),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('facturas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_facturas_after_insert` AFTER INSERT ON `facturas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'facturas', 'INSERT', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'numFactura', NEW.numFactura,
            'tipoFactura', NEW.tipoFactura,
            'total', NEW.total,
            'balance', NEW.balance,
            'idCliente', NEW.idCliente,
            'idEmpleado', NEW.idEmpleado,
            'estado', NEW.estado
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('facturas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_facturas_after_update` AFTER UPDATE ON `facturas` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.estado != NEW.estado THEN
        SET cambios = JSON_SET(cambios, '$.estado', JSON_OBJECT('anterior', OLD.estado, 'nuevo', NEW.estado));
    END IF;
    
    IF OLD.balance != NEW.balance THEN
        SET cambios = JSON_SET(cambios, '$.balance', JSON_OBJECT('anterior', OLD.balance, 'nuevo', NEW.balance));
    END IF;
    
    IF OLD.total != NEW.total THEN
        SET cambios = JSON_SET(cambios, '$.total', JSON_OBJECT('anterior', OLD.total, 'nuevo', NEW.total));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'facturas', 'UPDATE', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('numFactura', OLD.numFactura, 'estado', OLD.estado, 'balance', OLD.balance, 'total', OLD.total),
        JSON_OBJECT('numFactura', NEW.numFactura, 'estado', NEW.estado, 'balance', NEW.balance, 'total', NEW.total),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('facturas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `facturas_cancelaciones`
--

CREATE TABLE `facturas_cancelaciones` (
  `numFactura` varchar(10) DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `idEmpleado` int(6) DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facturas_detalles`
--

CREATE TABLE `facturas_detalles` (
  `numFactura` varchar(10) DEFAULT NULL,
  `idProducto` int(6) DEFAULT NULL,
  `cantidad` decimal(15,2) DEFAULT NULL,
  `precioCompra` decimal(15,2) DEFAULT NULL,
  `precioVenta` decimal(15,2) DEFAULT NULL,
  `importe` decimal(15,2) DEFAULT NULL,
  `ganancias` decimal(15,2) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `facturas_detalles`
--
DELIMITER $$
CREATE TRIGGER `trg_facturas_detalles_after_delete` AFTER DELETE ON `facturas_detalles` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'facturas_detalles', 'DELETE', OLD.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('numFactura', OLD.numFactura, 'idProducto', OLD.idProducto, 'cantidad', OLD.cantidad, 'importe', OLD.importe),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('facturas_detalles', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_facturas_detalles_after_insert` AFTER INSERT ON `facturas_detalles` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'facturas_detalles', 'INSERT', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'registro', NEW.registro,
            'numFactura', NEW.numFactura,
            'idProducto', NEW.idProducto,
            'cantidad', NEW.cantidad,
            'precioVenta', NEW.precioVenta,
            'importe', NEW.importe,
            'ganancias', NEW.ganancias
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('facturas_detalles', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_facturas_detalles_after_update` AFTER UPDATE ON `facturas_detalles` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.cantidad != NEW.cantidad THEN
        SET cambios = JSON_SET(cambios, '$.cantidad', JSON_OBJECT('anterior', OLD.cantidad, 'nuevo', NEW.cantidad));
    END IF;
    
    IF OLD.precioVenta != NEW.precioVenta THEN
        SET cambios = JSON_SET(cambios, '$.precioVenta', JSON_OBJECT('anterior', OLD.precioVenta, 'nuevo', NEW.precioVenta));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'facturas_detalles', 'UPDATE', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('numFactura', OLD.numFactura, 'idProducto', OLD.idProducto, 'cantidad', OLD.cantidad, 'precioVenta', OLD.precioVenta),
        JSON_OBJECT('numFactura', NEW.numFactura, 'idProducto', NEW.idProducto, 'cantidad', NEW.cantidad, 'precioVenta', NEW.precioVenta),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('facturas_detalles', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `facturas_metodopago`
--

CREATE TABLE `facturas_metodopago` (
  `numFactura` varchar(10) DEFAULT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `monto` decimal(15,2) DEFAULT NULL,
  `numAutorizacion` varchar(30) DEFAULT NULL,
  `referencia` varchar(30) DEFAULT NULL,
  `idBanco` int(5) DEFAULT NULL,
  `idDestino` int(5) DEFAULT NULL,
  `noCaja` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `infofactura`
--

CREATE TABLE `infofactura` (
  `name` varchar(20) DEFAULT NULL,
  `text1` text DEFAULT NULL,
  `text2` text DEFAULT NULL,
  `text3` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `infofactura`
--

INSERT INTO `infofactura` (`name`, `text1`, `text2`, `text3`) VALUES
('EasyPOS', 'Esto es un texto demo', 'Esto es un texto demo', 'Esto es un texto demo');

-- --------------------------------------------------------

--
-- Table structure for table `inventario`
--

CREATE TABLE `inventario` (
  `idProducto` int(6) DEFAULT NULL,
  `existencia` decimal(15,2) DEFAULT NULL,
  `ultima_actualizacion` datetime DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `inventario`
--
DELIMITER $$
CREATE TRIGGER `trg_inventario_after_delete` AFTER DELETE ON `inventario` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'inventario', 'DELETE', OLD.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('registro', OLD.registro, 'idProducto', OLD.idProducto, 'existencia', OLD.existencia),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('inventario', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventario_after_insert` AFTER INSERT ON `inventario` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'inventario', 'INSERT', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'registro', NEW.registro,
            'idProducto', NEW.idProducto,
            'existencia', NEW.existencia,
            'ultima_actualizacion', NEW.ultima_actualizacion
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('inventario', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventario_after_update` AFTER UPDATE ON `inventario` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.existencia != NEW.existencia THEN
        SET cambios = JSON_SET(cambios, '$.existencia', JSON_OBJECT('anterior', OLD.existencia, 'nuevo', NEW.existencia, 'diferencia', NEW.existencia - OLD.existencia));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'inventario', 'UPDATE', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('idProducto', OLD.idProducto, 'existencia', OLD.existencia),
        JSON_OBJECT('idProducto', NEW.idProducto, 'existencia', NEW.existencia),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('inventario', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventarioempleados`
--

CREATE TABLE `inventarioempleados` (
  `idProducto` int(6) DEFAULT NULL,
  `cantidad` decimal(15,2) DEFAULT NULL,
  `idEmpleado` int(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventariotransacciones`
--

CREATE TABLE `inventariotransacciones` (
  `id` int(6) NOT NULL,
  `tipo` varchar(20) DEFAULT NULL,
  `idProducto` int(6) DEFAULT NULL,
  `cantidad` decimal(15,2) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `idEmpleado` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `inventariotransacciones`
--
DELIMITER $$
CREATE TRIGGER `trg_inventariotransacciones_after_delete` AFTER DELETE ON `inventariotransacciones` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'inventariotransacciones', 'DELETE', OLD.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'tipo', OLD.tipo, 'idProducto', OLD.idProducto, 'cantidad', OLD.cantidad),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('inventariotransacciones', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventariotransacciones_after_insert` AFTER INSERT ON `inventariotransacciones` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'inventariotransacciones', 'INSERT', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id', NEW.id,
            'tipo', NEW.tipo,
            'idProducto', NEW.idProducto,
            'cantidad', NEW.cantidad,
            'fecha', NEW.fecha,
            'descripcion', NEW.descripcion,
            'idEmpleado', NEW.idEmpleado
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('inventariotransacciones', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventariotransacciones_after_update` AFTER UPDATE ON `inventariotransacciones` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.tipo != NEW.tipo THEN
        SET cambios = JSON_SET(cambios, '$.tipo', JSON_OBJECT('anterior', OLD.tipo, 'nuevo', NEW.tipo));
    END IF;
    
    IF OLD.cantidad != NEW.cantidad THEN
        SET cambios = JSON_SET(cambios, '$.cantidad', JSON_OBJECT('anterior', OLD.cantidad, 'nuevo', NEW.cantidad));
    END IF;
    
    IF OLD.descripcion != NEW.descripcion THEN
        SET cambios = JSON_SET(cambios, '$.descripcion', JSON_OBJECT('anterior', OLD.descripcion, 'nuevo', NEW.descripcion));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'inventariotransacciones', 'UPDATE', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'tipo', OLD.tipo, 'cantidad', OLD.cantidad, 'descripcion', OLD.descripcion),
        JSON_OBJECT('id', NEW.id, 'tipo', NEW.tipo, 'cantidad', NEW.cantidad, 'descripcion', NEW.descripcion),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('inventariotransacciones', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventario_entradas`
--

CREATE TABLE `inventario_entradas` (
  `id` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `empleado` int(11) NOT NULL,
  `referencia` varchar(150) NOT NULL,
  `estado` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `inventario_entradas`
--
DELIMITER $$
CREATE TRIGGER `trg_inventario_entradas_after_delete` AFTER DELETE ON `inventario_entradas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_entradas', 'DELETE', OLD.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'empleado', OLD.empleado, 'referencia', OLD.referencia, 'estado', OLD.estado),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('inventario_entradas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventario_entradas_after_insert` AFTER INSERT ON `inventario_entradas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_entradas', 'INSERT', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id', NEW.id,
            'fecha', NEW.fecha,
            'empleado', NEW.empleado,
            'referencia', NEW.referencia,
            'estado', NEW.estado
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('inventario_entradas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventario_entradas_after_update` AFTER UPDATE ON `inventario_entradas` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.estado != NEW.estado THEN
        SET cambios = JSON_SET(cambios, '$.estado', JSON_OBJECT('anterior', OLD.estado, 'nuevo', NEW.estado));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_entradas', 'UPDATE', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'estado', OLD.estado, 'referencia', OLD.referencia),
        JSON_OBJECT('id', NEW.id, 'estado', NEW.estado, 'referencia', NEW.referencia),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('inventario_entradas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventario_entradas_canceladas`
--

CREATE TABLE `inventario_entradas_canceladas` (
  `id_entrada` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `empleado` int(11) NOT NULL,
  `notas` text NOT NULL,
  `registro` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventario_entradas_detalle`
--

CREATE TABLE `inventario_entradas_detalle` (
  `id_entrada` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` double NOT NULL,
  `costo` double NOT NULL,
  `fecha` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `inventario_entradas_detalle`
--
DELIMITER $$
CREATE TRIGGER `trg_inventario_entradas_detalle_after_delete` AFTER DELETE ON `inventario_entradas_detalle` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_entradas_detalle', 'DELETE', CONCAT(OLD.id_entrada, '-', OLD.id_producto),
        USER(), @usuario_app_id,
        JSON_OBJECT('id_entrada', OLD.id_entrada, 'id_producto', OLD.id_producto, 'cantidad', OLD.cantidad, 'costo', OLD.costo),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('inventario_entradas_detalle', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventario_entradas_detalle_after_insert` AFTER INSERT ON `inventario_entradas_detalle` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_entradas_detalle', 'INSERT', CONCAT(NEW.id_entrada, '-', NEW.id_producto),
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id_entrada', NEW.id_entrada,
            'id_producto', NEW.id_producto,
            'cantidad', NEW.cantidad,
            'costo', NEW.costo,
            'fecha', NEW.fecha
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('inventario_entradas_detalle', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventario_entradas_detalle_after_update` AFTER UPDATE ON `inventario_entradas_detalle` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.cantidad != NEW.cantidad THEN
        SET cambios = JSON_SET(cambios, '$.cantidad', JSON_OBJECT('anterior', OLD.cantidad, 'nuevo', NEW.cantidad));
    END IF;
    
    IF OLD.costo != NEW.costo THEN
        SET cambios = JSON_SET(cambios, '$.costo', JSON_OBJECT('anterior', OLD.costo, 'nuevo', NEW.costo));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_entradas_detalle', 'UPDATE', CONCAT(NEW.id_entrada, '-', NEW.id_producto),
        USER(), @usuario_app_id,
        JSON_OBJECT('id_entrada', OLD.id_entrada, 'id_producto', OLD.id_producto, 'cantidad', OLD.cantidad, 'costo', OLD.costo),
        JSON_OBJECT('id_entrada', NEW.id_entrada, 'id_producto', NEW.id_producto, 'cantidad', NEW.cantidad, 'costo', NEW.costo),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('inventario_entradas_detalle', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventario_salidas`
--

CREATE TABLE `inventario_salidas` (
  `id` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `empleado` int(11) NOT NULL,
  `razon` int(11) NOT NULL,
  `notas` text DEFAULT NULL,
  `estado` varchar(15) NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `inventario_salidas`
--
DELIMITER $$
CREATE TRIGGER `trg_inventario_salidas_after_delete` AFTER DELETE ON `inventario_salidas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_salidas', 'DELETE', OLD.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'empleado', OLD.empleado, 'razon', OLD.razon, 'estado', OLD.estado),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('inventario_salidas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventario_salidas_after_insert` AFTER INSERT ON `inventario_salidas` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_salidas', 'INSERT', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id', NEW.id,
            'fecha', NEW.fecha,
            'empleado', NEW.empleado,
            'razon', NEW.razon,
            'estado', NEW.estado
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('inventario_salidas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventario_salidas_after_update` AFTER UPDATE ON `inventario_salidas` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.estado != NEW.estado THEN
        SET cambios = JSON_SET(cambios, '$.estado', JSON_OBJECT('anterior', OLD.estado, 'nuevo', NEW.estado));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_salidas', 'UPDATE', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'estado', OLD.estado, 'razon', OLD.razon),
        JSON_OBJECT('id', NEW.id, 'estado', NEW.estado, 'razon', NEW.razon),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('inventario_salidas', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventario_salidas_canceladas`
--

CREATE TABLE `inventario_salidas_canceladas` (
  `id_salida` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `empleado` int(11) NOT NULL,
  `notas` text NOT NULL,
  `registro` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventario_salidas_detalle`
--

CREATE TABLE `inventario_salidas_detalle` (
  `id_salida` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` double NOT NULL,
  `costo` double NOT NULL,
  `fecha` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `inventario_salidas_detalle`
--
DELIMITER $$
CREATE TRIGGER `trg_inventario_salidas_detalle_after_delete` AFTER DELETE ON `inventario_salidas_detalle` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_salidas_detalle', 'DELETE', CONCAT(OLD.id_salida, '-', OLD.id_producto),
        USER(), @usuario_app_id,
        JSON_OBJECT('id_salida', OLD.id_salida, 'id_producto', OLD.id_producto, 'cantidad', OLD.cantidad, 'costo', OLD.costo),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('inventario_salidas_detalle', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventario_salidas_detalle_after_insert` AFTER INSERT ON `inventario_salidas_detalle` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_salidas_detalle', 'INSERT', CONCAT(NEW.id_salida, '-', NEW.id_producto),
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id_salida', NEW.id_salida,
            'id_producto', NEW.id_producto,
            'cantidad', NEW.cantidad,
            'costo', NEW.costo,
            'fecha', NEW.fecha
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('inventario_salidas_detalle', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_inventario_salidas_detalle_after_update` AFTER UPDATE ON `inventario_salidas_detalle` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.cantidad != NEW.cantidad THEN
        SET cambios = JSON_SET(cambios, '$.cantidad', JSON_OBJECT('anterior', OLD.cantidad, 'nuevo', NEW.cantidad));
    END IF;
    
    IF OLD.costo != NEW.costo THEN
        SET cambios = JSON_SET(cambios, '$.costo', JSON_OBJECT('anterior', OLD.costo, 'nuevo', NEW.costo));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'inventario_salidas_detalle', 'UPDATE', CONCAT(NEW.id_salida, '-', NEW.id_producto),
        USER(), @usuario_app_id,
        JSON_OBJECT('id_salida', OLD.id_salida, 'id_producto', OLD.id_producto, 'cantidad', OLD.cantidad, 'costo', OLD.costo),
        JSON_OBJECT('id_salida', NEW.id_salida, 'id_producto', NEW.id_producto, 'cantidad', NEW.cantidad, 'costo', NEW.costo),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('inventario_salidas_detalle', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventario_salidas_razones`
--

CREATE TABLE `inventario_salidas_razones` (
  `id` int(11) NOT NULL,
  `descripcion` varchar(100) NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventario_salidas_razones`
--

INSERT INTO `inventario_salidas_razones` (`id`, `descripcion`, `activo`) VALUES
(1, 'Producto dañado', 1),
(2, 'Vencido', 1),
(3, 'Uso interno', 1),
(4, 'Donación', 1),
(5, 'Ajuste de inventario', 1),
(6, 'Robo', 1),
(7, 'Otro', 1);

-- --------------------------------------------------------

--
-- Table structure for table `numfactura`
--

CREATE TABLE `numfactura` (
  `num` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `numfactura`
--

INSERT INTO `numfactura` (`num`) VALUES
('00001');

-- --------------------------------------------------------

--
-- Table structure for table `perfiles_permisos`
--

CREATE TABLE `perfiles_permisos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_modificacion` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `creado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `perfiles_permisos`
--

INSERT INTO `perfiles_permisos` (`id`, `nombre`, `descripcion`, `activo`, `fecha_creacion`, `fecha_modificacion`, `creado_por`) VALUES
(1, 'Encargado de Inventario', 'Permisos para gestión completa de inventario y productos', 1, '2025-11-30 20:35:28', '2025-11-30 20:35:28', NULL),
(2, 'Vendedor', 'Permisos básicos de ventas y cotizaciones', 1, '2025-11-30 20:35:28', '2025-11-30 20:35:28', NULL),
(3, 'Administrador', 'Acceso completo al sistema', 1, '2025-11-30 20:35:28', '2025-11-30 20:35:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `perfiles_permisos_detalle`
--

CREATE TABLE `perfiles_permisos_detalle` (
  `id` int(11) NOT NULL,
  `id_perfil` int(11) NOT NULL,
  `id_permiso` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `perfiles_permisos_detalle`
--

INSERT INTO `perfiles_permisos_detalle` (`id`, `id_perfil`, `id_permiso`) VALUES
(85, 1, 'ALM001'),
(87, 1, 'ALM002'),
(86, 1, 'ALM003'),
(88, 1, 'ALM004'),
(89, 1, 'ALM005'),
(90, 1, 'PADM001'),
(83, 1, 'PRO001'),
(84, 1, 'PRO002'),
(73, 3, 'CAJ001'),
(69, 3, 'CLI001'),
(70, 3, 'CLI003'),
(72, 3, 'COT001'),
(71, 3, 'FAC001'),
(23, 4, 'ALM001'),
(24, 4, 'ALM002'),
(25, 4, 'ALM003'),
(26, 4, 'ALM004'),
(27, 4, 'ALM005'),
(34, 4, 'CAJ001'),
(17, 4, 'CLI001'),
(18, 4, 'CLI002'),
(19, 4, 'CLI003'),
(20, 4, 'CLI004'),
(31, 4, 'COT001'),
(32, 4, 'COT002'),
(33, 4, 'COT003'),
(35, 4, 'CUA001'),
(36, 4, 'CUA002'),
(40, 4, 'EMP001'),
(28, 4, 'FAC001'),
(29, 4, 'FAC002'),
(30, 4, 'FAC003'),
(37, 4, 'PADM001'),
(38, 4, 'PADM002'),
(39, 4, 'PADM003'),
(21, 4, 'PRO001'),
(22, 4, 'PRO002');

-- --------------------------------------------------------

--
-- Table structure for table `productos`
--

CREATE TABLE `productos` (
  `id` int(6) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `idTipo` int(5) DEFAULT NULL,
  `existencia` decimal(15,2) DEFAULT NULL,
  `precioCompra` decimal(15,2) DEFAULT NULL,
  `precioVenta1` decimal(15,2) DEFAULT NULL,
  `precioVenta2` decimal(15,2) DEFAULT NULL,
  `reorden` decimal(15,2) DEFAULT NULL,
  `fechaRegistro` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `productos`
--
DELIMITER $$
CREATE TRIGGER `trg_productos_after_delete` AFTER DELETE ON `productos` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'productos', 'DELETE', OLD.id,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id', OLD.id, 'descripcion', OLD.descripcion, 'existencia', OLD.existencia,
            'precioCompra', OLD.precioCompra, 'precioVenta1', OLD.precioVenta1,
            'precioVenta2', OLD.precioVenta2, 'activo', OLD.activo
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('productos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_productos_after_insert` AFTER INSERT ON `productos` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos',
        'productos',
        'INSERT',
        NEW.id,
        USER(),
        @usuario_app_id,
        JSON_OBJECT(
            'id', NEW.id,
            'descripcion', NEW.descripcion,
            'idTipo', NEW.idTipo,
            'existencia', NEW.existencia,
            'precioCompra', NEW.precioCompra,
            'precioVenta1', NEW.precioVenta1,
            'precioVenta2', NEW.precioVenta2,
            'reorden', NEW.reorden,
            'fechaRegistro', NEW.fechaRegistro,
            'activo', NEW.activo
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('productos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_productos_after_update` AFTER UPDATE ON `productos` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    
    SET cambios = JSON_OBJECT();
    
    IF OLD.descripcion != NEW.descripcion THEN
        SET cambios = JSON_SET(cambios, '$.descripcion', JSON_OBJECT('anterior', OLD.descripcion, 'nuevo', NEW.descripcion));
    END IF;
    
    IF OLD.existencia != NEW.existencia THEN
        SET cambios = JSON_SET(cambios, '$.existencia', JSON_OBJECT('anterior', OLD.existencia, 'nuevo', NEW.existencia));
    END IF;
    
    IF OLD.precioCompra != NEW.precioCompra THEN
        SET cambios = JSON_SET(cambios, '$.precioCompra', JSON_OBJECT('anterior', OLD.precioCompra, 'nuevo', NEW.precioCompra));
    END IF;
    
    IF OLD.precioVenta1 != NEW.precioVenta1 THEN
        SET cambios = JSON_SET(cambios, '$.precioVenta1', JSON_OBJECT('anterior', OLD.precioVenta1, 'nuevo', NEW.precioVenta1));
    END IF;
    
    IF OLD.precioVenta2 != NEW.precioVenta2 THEN
        SET cambios = JSON_SET(cambios, '$.precioVenta2', JSON_OBJECT('anterior', OLD.precioVenta2, 'nuevo', NEW.precioVenta2));
    END IF;
    
    IF OLD.activo != NEW.activo THEN
        SET cambios = JSON_SET(cambios, '$.activo', JSON_OBJECT('anterior', OLD.activo, 'nuevo', NEW.activo));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados,
        fecha_operacion
    ) VALUES (
        'easypos', 'productos', 'UPDATE', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id', OLD.id, 'descripcion', OLD.descripcion, 'existencia', OLD.existencia,
            'precioCompra', OLD.precioCompra, 'precioVenta1', OLD.precioVenta1, 
            'precioVenta2', OLD.precioVenta2, 'activo', OLD.activo
        ),
        JSON_OBJECT(
            'id', NEW.id, 'descripcion', NEW.descripcion, 'existencia', NEW.existencia,
            'precioCompra', NEW.precioCompra, 'precioVenta1', NEW.precioVenta1,
            'precioVenta2', NEW.precioVenta2, 'activo', NEW.activo
        ),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('productos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `productos_tipo`
--

CREATE TABLE `productos_tipo` (
  `id` int(6) NOT NULL,
  `descripcion` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `productos_tipo`
--

INSERT INTO `productos_tipo` (`id`, `descripcion`) VALUES
(1, 'prueba tipo');

-- --------------------------------------------------------

--
-- Table structure for table `session_tokens`
--

CREATE TABLE `session_tokens` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expiry` datetime NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transacciones_det`
--

CREATE TABLE `transacciones_det` (
  `no` int(5) NOT NULL,
  `id_producto` int(11) DEFAULT NULL,
  `cantidad` decimal(15,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transacciones_inv`
--

CREATE TABLE `transacciones_inv` (
  `no` int(5) NOT NULL,
  `fecha` datetime NOT NULL,
  `id_emp_reg` int(5) NOT NULL,
  `id_emp_des` int(5) NOT NULL,
  `tipo_mov` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(6) NOT NULL,
  `username` varchar(30) DEFAULT NULL,
  `password` varchar(500) DEFAULT NULL,
  `idEmpleado` int(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `password`, `idEmpleado`) VALUES
(1, 'fjoelfrias', '$2y$10$sT7H/5n8HaRJ.hJ2CerHKe99.vt7SaVk0IjumFaDiT7M.q29uKufe', 1);

--
-- Triggers `usuarios`
--
DELIMITER $$
CREATE TRIGGER `trg_usuarios_after_delete` AFTER DELETE ON `usuarios` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'usuarios', 'DELETE', OLD.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'username', OLD.username, 'idEmpleado', OLD.idEmpleado),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('usuarios', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_usuarios_after_insert` AFTER INSERT ON `usuarios` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'usuarios', 'INSERT', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'id', NEW.id,
            'username', NEW.username,
            'idEmpleado', NEW.idEmpleado
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('usuarios', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_usuarios_after_update` AFTER UPDATE ON `usuarios` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.username != NEW.username THEN
        SET cambios = JSON_SET(cambios, '$.username', JSON_OBJECT('anterior', OLD.username, 'nuevo', NEW.username));
    END IF;
    
    IF OLD.password != NEW.password THEN
        SET cambios = JSON_SET(cambios, '$.password', JSON_OBJECT('anterior', 'CHANGED', 'nuevo', 'CHANGED'));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'usuarios', 'UPDATE', NEW.id,
        USER(), @usuario_app_id,
        JSON_OBJECT('id', OLD.id, 'username', OLD.username, 'password', 'HASH'),
        JSON_OBJECT('id', NEW.id, 'username', NEW.username, 'password', 'HASH'),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('usuarios', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios_permisos`
--

CREATE TABLE `usuarios_permisos` (
  `id_permiso` varchar(15) NOT NULL,
  `id_empleado` int(11) NOT NULL,
  `registro` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usuarios_permisos`
--

INSERT INTO `usuarios_permisos` (`id_permiso`, `id_empleado`, `registro`) VALUES
('CLI001', 1, 1995),
('CLI002', 1, 1996),
('PRO001', 1, 1997),
('PRO002', 1, 1998),
('CLI003', 1, 1999),
('CLI004', 1, 2000),
('FAC002', 1, 2001),
('ALM001', 1, 2002),
('ALM003', 1, 2003),
('FAC001', 1, 2004),
('COT001', 1, 2005),
('CAJ001', 1, 2006),
('PADM001', 1, 2007),
('PADM002', 1, 2008),
('PADM003', 1, 2009),
('EMP001', 1, 2010),
('FAC003', 1, 2011),
('CUA001', 1, 2012),
('CUA002', 1, 2013),
('COT002', 1, 2014),
('COT003', 1, 2015),
('ALM002', 1, 2016),
('ALM004', 1, 2017),
('ALM005', 1, 2018);

--
-- Triggers `usuarios_permisos`
--
DELIMITER $$
CREATE TRIGGER `trg_usuarios_permisos_after_delete` AFTER DELETE ON `usuarios_permisos` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, datos_anteriores, fecha_operacion
    ) VALUES (
        'easypos', 'usuarios_permisos', 'DELETE', OLD.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('registro', OLD.registro, 'id_permiso', OLD.id_permiso, 'id_empleado', OLD.id_empleado),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_deletes, ultima_operacion)
    VALUES ('usuarios_permisos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_deletes = total_deletes + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_usuarios_permisos_after_insert` AFTER INSERT ON `usuarios_permisos` FOR EACH ROW BEGIN
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro, 
        usuario_db, usuario_app, datos_nuevos, fecha_operacion
    ) VALUES (
        'easypos', 'usuarios_permisos', 'INSERT', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT(
            'registro', NEW.registro,
            'id_permiso', NEW.id_permiso,
            'id_empleado', NEW.id_empleado
        ),
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_inserts, ultima_operacion)
    VALUES ('usuarios_permisos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_inserts = total_inserts + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_usuarios_permisos_after_update` AFTER UPDATE ON `usuarios_permisos` FOR EACH ROW BEGIN
    DECLARE cambios JSON;
    SET cambios = JSON_OBJECT();
    
    IF OLD.id_permiso != NEW.id_permiso THEN
        SET cambios = JSON_SET(cambios, '$.id_permiso', JSON_OBJECT('anterior', OLD.id_permiso, 'nuevo', NEW.id_permiso));
    END IF;
    
    IF OLD.id_empleado != NEW.id_empleado THEN
        SET cambios = JSON_SET(cambios, '$.id_empleado', JSON_OBJECT('anterior', OLD.id_empleado, 'nuevo', NEW.id_empleado));
    END IF;
    
    INSERT INTO easypos_auditoria.auditoria_log (
        base_datos, tabla, operacion, id_registro,
        usuario_db, usuario_app, 
        datos_anteriores, datos_nuevos, cambios_detectados, fecha_operacion
    ) VALUES (
        'easypos', 'usuarios_permisos', 'UPDATE', NEW.registro,
        USER(), @usuario_app_id,
        JSON_OBJECT('registro', OLD.registro, 'id_permiso', OLD.id_permiso, 'id_empleado', OLD.id_empleado),
        JSON_OBJECT('registro', NEW.registro, 'id_permiso', NEW.id_permiso, 'id_empleado', NEW.id_empleado),
        cambios,
        NOW()
    );
    
    INSERT INTO easypos_auditoria.auditoria_resumen (tabla, total_updates, ultima_operacion)
    VALUES ('usuarios_permisos', 1, NOW())
    ON DUPLICATE KEY UPDATE 
        total_updates = total_updates + 1,
        ultima_operacion = NOW();
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bancos`
--
ALTER TABLE `bancos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cajaegresos`
--
ALTER TABLE `cajaegresos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cajaingresos`
--
ALTER TABLE `cajaingresos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cajasabiertas`
--
ALTER TABLE `cajasabiertas`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `cajascerradas`
--
ALTER TABLE `cajascerradas`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `caja_estado_detalle`
--
ALTER TABLE `caja_estado_detalle`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clientes_direcciones`
--
ALTER TABLE `clientes_direcciones`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `clientes_historialpagos`
--
ALTER TABLE `clientes_historialpagos`
  ADD PRIMARY KEY (`registro`),
  ADD KEY `idx_estado` (`estado`);

--
-- Indexes for table `cotizaciones_canceladas`
--
ALTER TABLE `cotizaciones_canceladas`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `cotizaciones_det`
--
ALTER TABLE `cotizaciones_det`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `cotizaciones_inf`
--
ALTER TABLE `cotizaciones_inf`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `destinocuentas`
--
ALTER TABLE `destinocuentas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `empleados_puestos`
--
ALTER TABLE `empleados_puestos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `facturas`
--
ALTER TABLE `facturas`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `facturas_cancelaciones`
--
ALTER TABLE `facturas_cancelaciones`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `facturas_detalles`
--
ALTER TABLE `facturas_detalles`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `inventariotransacciones`
--
ALTER TABLE `inventariotransacciones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventario_entradas`
--
ALTER TABLE `inventario_entradas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventario_entradas_canceladas`
--
ALTER TABLE `inventario_entradas_canceladas`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `inventario_entradas_detalle`
--
ALTER TABLE `inventario_entradas_detalle`
  ADD PRIMARY KEY (`id_entrada`,`id_producto`);

--
-- Indexes for table `inventario_salidas`
--
ALTER TABLE `inventario_salidas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventario_salidas_canceladas`
--
ALTER TABLE `inventario_salidas_canceladas`
  ADD PRIMARY KEY (`registro`);

--
-- Indexes for table `inventario_salidas_detalle`
--
ALTER TABLE `inventario_salidas_detalle`
  ADD PRIMARY KEY (`id_salida`,`id_producto`);

--
-- Indexes for table `inventario_salidas_razones`
--
ALTER TABLE `inventario_salidas_razones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `perfiles_permisos`
--
ALTER TABLE `perfiles_permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `creado_por` (`creado_por`);

--
-- Indexes for table `perfiles_permisos_detalle`
--
ALTER TABLE `perfiles_permisos_detalle`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_perfil_permiso` (`id_perfil`,`id_permiso`);

--
-- Indexes for table `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `productos_tipo`
--
ALTER TABLE `productos_tipo`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `session_tokens`
--
ALTER TABLE `session_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `expiry` (`expiry`);

--
-- Indexes for table `transacciones_inv`
--
ALTER TABLE `transacciones_inv`
  ADD PRIMARY KEY (`no`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `usuarios_permisos`
--
ALTER TABLE `usuarios_permisos`
  ADD PRIMARY KEY (`registro`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bancos`
--
ALTER TABLE `bancos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cajaegresos`
--
ALTER TABLE `cajaegresos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cajaingresos`
--
ALTER TABLE `cajaingresos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cajasabiertas`
--
ALTER TABLE `cajasabiertas`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cajascerradas`
--
ALTER TABLE `cajascerradas`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `caja_estado_detalle`
--
ALTER TABLE `caja_estado_detalle`
  MODIFY `registro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clientes_direcciones`
--
ALTER TABLE `clientes_direcciones`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clientes_historialpagos`
--
ALTER TABLE `clientes_historialpagos`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cotizaciones_canceladas`
--
ALTER TABLE `cotizaciones_canceladas`
  MODIFY `registro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cotizaciones_det`
--
ALTER TABLE `cotizaciones_det`
  MODIFY `registro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cotizaciones_inf`
--
ALTER TABLE `cotizaciones_inf`
  MODIFY `registro` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `destinocuentas`
--
ALTER TABLE `destinocuentas`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `empleados_puestos`
--
ALTER TABLE `empleados_puestos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `facturas`
--
ALTER TABLE `facturas`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facturas_cancelaciones`
--
ALTER TABLE `facturas_cancelaciones`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facturas_detalles`
--
ALTER TABLE `facturas_detalles`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventario`
--
ALTER TABLE `inventario`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventariotransacciones`
--
ALTER TABLE `inventariotransacciones`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventario_entradas`
--
ALTER TABLE `inventario_entradas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventario_entradas_canceladas`
--
ALTER TABLE `inventario_entradas_canceladas`
  MODIFY `registro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventario_entradas_detalle`
--
ALTER TABLE `inventario_entradas_detalle`
  MODIFY `id_entrada` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventario_salidas`
--
ALTER TABLE `inventario_salidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventario_salidas_canceladas`
--
ALTER TABLE `inventario_salidas_canceladas`
  MODIFY `registro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventario_salidas_razones`
--
ALTER TABLE `inventario_salidas_razones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `perfiles_permisos`
--
ALTER TABLE `perfiles_permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `perfiles_permisos_detalle`
--
ALTER TABLE `perfiles_permisos_detalle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `productos_tipo`
--
ALTER TABLE `productos_tipo`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `session_tokens`
--
ALTER TABLE `session_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transacciones_inv`
--
ALTER TABLE `transacciones_inv`
  MODIFY `no` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `usuarios_permisos`
--
ALTER TABLE `usuarios_permisos`
  MODIFY `registro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
