-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 27, 2025 at 04:33 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `easypos`
--

-- --------------------------------------------------------

--
-- Table structure for table `auditoria_caja`
--

CREATE TABLE `auditoria_caja` (
  `id` int(11) NOT NULL,
  `empleado_id` int(11) DEFAULT NULL,
  `accion` varchar(100) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `ip` varchar(45) DEFAULT 'DESCONOCIDA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auditoria_caja`
--

INSERT INTO `auditoria_caja` (`id`, `empleado_id`, `accion`, `detalles`, `fecha`, `ip`) VALUES
(1, 1, 'APERTURA_CAJA', 'Caja #00001 abierta con saldo inicial: 1500', '2025-10-14 18:33:04', '127.0.0.1'),
(2, 1, 'Registro de venta por factura #100001', 'Método: efectivo, Monto: 3500, Razón: Venta por factura #100001', '2025-10-14 18:35:50', '127.0.0.1'),
(3, 1, 'Registro de venta por factura #100002', 'Método: efectivo, Monto: 0, Razón: Venta por factura #100002', '2025-10-14 18:37:04', '127.0.0.1'),
(4, 1, 'Registro de venta por factura #100003', 'Método: efectivo, Monto: 1250, Razón: Venta por factura #100003', '2025-10-15 17:33:04', '127.0.0.1');

-- --------------------------------------------------------

--
-- Table structure for table `auditoria_usuarios`
--

CREATE TABLE `auditoria_usuarios` (
  `id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `accion` varchar(100) NOT NULL,
  `detalles` text DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auditoria_usuarios`
--

INSERT INTO `auditoria_usuarios` (`id`, `empleado_id`, `accion`, `detalles`, `fecha`, `ip`) VALUES
(1, 1, 'Cierre de sesión', 'El usuario fjoelfrias ha cerrado sesión.', '2025-10-13 23:13:49', '127.0.0.1'),
(2, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-13 23:13:54', '127.0.0.1'),
(3, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-13 23:45:46', '127.0.0.1'),
(4, 1, 'Nuevo cliente', 'ID del cliente: 1', '2025-10-13 23:51:33', '127.0.0.1'),
(5, 1, 'Nuevo cliente', 'ID del cliente: 2', '2025-10-13 23:53:43', '127.0.0.1'),
(6, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 1): Nombre: Juan - Apellido: Perez - Identificacion: 4026956569 - Límite: 50000 - Activo: Sí', '2025-10-14 00:28:58', '127.0.0.1'),
(7, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 1): Nombre: Jua - Apellido: Perez - Identificacion: 4026956569 - Límite: 50000 - Activo: Sí', '2025-10-14 00:29:16', '127.0.0.1'),
(8, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 1): Nombre: Juan - Apellido: Pere - Identificacion: 4026956569 - Límite: 50000 - Activo: Sí', '2025-10-14 00:29:26', '127.0.0.1'),
(9, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 1): Nombre: Juan - Apellido: Perez - Identificacion: 4026956569 - Límite: 50000 - Activo: Sí', '2025-10-14 00:31:39', '127.0.0.1'),
(10, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 1): Nombre: Juan - Apellido: Perez - Identificacion: 4026956569 - Límite: 50000 - Activo: Sí', '2025-10-14 00:31:53', '127.0.0.1'),
(11, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 1): Nombre: Juan - Apellido: Perez - Identificacion: 4026932343 - Límite: 50000 - Activo: Sí', '2025-10-14 00:32:09', '127.0.0.1'),
(12, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 1): Nombre: Juan - Apellido: Perez - Identificacion: 4026932343 - Límite: 5000 - Activo: No', '2025-10-14 00:32:59', '127.0.0.1'),
(13, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 1): Nombre: Juan - Apellido: Perez - Identificacion: 4026932343 - Límite: 5000 - Activo: Sí', '2025-10-14 00:33:07', '127.0.0.1'),
(14, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 1): Nombre: Juan - Apellido: Perez - Identificacion: 4026932343 - Límite: 5000 - Activo: Sí', '2025-10-14 00:33:29', '127.0.0.1'),
(15, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 1): Nombre: Juan - Apellido: Perez - Identificacion: 4026932343 - Límite: 50000 - Activo: Sí', '2025-10-14 00:33:52', '127.0.0.1'),
(16, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 2): Nombre: prueba - Apellido: prueba - Identificacion: 4026932342 - Límite: 15000 - Activo: No', '2025-10-14 00:34:02', '127.0.0.1'),
(17, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-14 00:55:13', '127.0.0.1'),
(18, 1, 'Cierre de sesión', 'El usuario fjoelfrias ha cerrado sesión.', '2025-10-14 01:08:56', '127.0.0.1'),
(19, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-14 01:11:28', '127.0.0.1'),
(20, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-14 17:27:05', '127.0.0.1'),
(21, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-14 17:47:16', '127.0.0.1'),
(22, 1, 'Nuevo Producto', 'Se ha registrado un nuevo producto: 1 - prueba', '2025-10-14 18:23:44', '127.0.0.1'),
(23, 1, 'Actualizar producto', 'Producto actualizado: prueba, ID: 1', '2025-10-14 18:24:04', '127.0.0.1'),
(24, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":15,\"idElimination\":0}]', '2025-10-14 18:24:21', '127.0.0.1'),
(25, 1, 'APERTURA_CAJA', 'Caja abierta con saldo inicial: 1500 y número de caja: 00001', '2025-10-14 18:33:04', '127.0.0.1'),
(26, 1, 'Registro de venta por factura #100001', 'Método: efectivo, Monto: 3500, Razón: Venta por factura #100001', '2025-10-14 18:35:50', '127.0.0.1'),
(27, 1, 'Registro de venta por factura #100002', 'Método: efectivo, Monto: 0, Razón: Venta por factura #100002', '2025-10-14 18:37:04', '127.0.0.1'),
(28, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 2): Nombre: prueba - Apellido: prueba - Identificacion: 4026932342 - Límite: 15000 - Activo: Sí', '2025-10-14 18:41:09', '127.0.0.1'),
(29, 1, 'Cancelacion de factura', 'Motivos: Prueba', '2025-10-14 18:43:07', '127.0.0.1'),
(30, 1, 'Actualizar cliente', 'Cliente actualizado (ID: 2): Nombre: prueba - Apellido: prueba - Identificacion: 4026932342 - Límite: 15000 - Activo: No', '2025-10-14 18:47:03', '127.0.0.1'),
(31, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-15 17:32:36', '127.0.0.1'),
(32, 1, 'Registro de venta por factura #100003', 'Método: efectivo, Monto: 1250, Razón: Venta por factura #100003', '2025-10-15 17:33:04', '127.0.0.1'),
(33, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-15 18:03:41', '127.0.0.1'),
(34, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-15 18:37:31', '127.0.0.1'),
(35, 1, 'Nuevo cliente', 'ID del cliente: 3', '2025-10-15 19:37:53', '127.0.0.1'),
(36, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-26 18:30:32', '127.0.0.1'),
(37, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 20:47:50', '127.0.0.1'),
(38, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 20:48:07', '127.0.0.1'),
(39, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 20:49:58', '127.0.0.1'),
(40, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-26 21:36:22', '127.0.0.1'),
(41, 1, 'Nueva sesión iniciada', 'El usuario fjoelfrias ha iniciado sesión.', '2025-10-26 22:21:50', '127.0.0.1'),
(42, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:39:10', '127.0.0.1'),
(43, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:39:29', '127.0.0.1'),
(44, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:41:09', '127.0.0.1'),
(45, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:41:19', '127.0.0.1'),
(46, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:42:13', '127.0.0.1'),
(47, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:42:24', '127.0.0.1'),
(48, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:43:24', '127.0.0.1'),
(49, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:43:32', '127.0.0.1'),
(50, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":1}]', '2025-10-26 22:45:01', '127.0.0.1'),
(51, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:45:23', '127.0.0.1'),
(52, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:47:59', '127.0.0.1'),
(53, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:48:12', '127.0.0.1'),
(54, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 22:52:34', '127.0.0.1'),
(55, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":10,\"idElimination\":0}]', '2025-10-26 22:53:33', '127.0.0.1'),
(56, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":2,\"idElimination\":0}]', '2025-10-26 22:55:14', '127.0.0.1'),
(57, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":10,\"idElimination\":0}]', '2025-10-26 22:55:48', '127.0.0.1'),
(58, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":10,\"idElimination\":0}]', '2025-10-26 22:56:37', '127.0.0.1'),
(59, 1, 'Transacción de inventario', 'Se han transferido productos al inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 23:04:06', '127.0.0.1'),
(60, 1, 'Transacción a Almacen', 'Se han transferido productos al almacen principal desde inventario del empleado con ID: 1 - Productos: [{\"id\":1,\"cantidad\":12,\"idElimination\":0}]', '2025-10-26 23:26:59', '127.0.0.1');

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
(2, 'N/A', 1);

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
(2);

-- --------------------------------------------------------

--
-- Table structure for table `cajaegresos`
--

CREATE TABLE `cajaegresos` (
  `id` int(6) NOT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `monto` float DEFAULT NULL,
  `IdEmpleado` int(6) DEFAULT NULL,
  `numCaja` varchar(5) DEFAULT NULL,
  `razon` varchar(50) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cajaingresos`
--

CREATE TABLE `cajaingresos` (
  `id` int(6) NOT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `monto` float DEFAULT NULL,
  `IdEmpleado` int(6) DEFAULT NULL,
  `numCaja` varchar(5) DEFAULT NULL,
  `razon` varchar(50) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cajaingresos`
--

INSERT INTO `cajaingresos` (`id`, `metodo`, `monto`, `IdEmpleado`, `numCaja`, `razon`, `fecha`) VALUES
(1, 'efectivo', 3500, 1, '00001', 'Venta por factura #100001', '2025-10-14 18:35:50'),
(3, 'efectivo', 1250, 1, '00001', 'Venta por factura #100003', '2025-10-15 17:33:04');

-- --------------------------------------------------------

--
-- Table structure for table `cajasabiertas`
--

CREATE TABLE `cajasabiertas` (
  `numCaja` varchar(5) DEFAULT NULL,
  `idEmpleado` int(6) DEFAULT NULL,
  `fechaApertura` datetime DEFAULT NULL,
  `saldoApertura` float DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cajasabiertas`
--

INSERT INTO `cajasabiertas` (`numCaja`, `idEmpleado`, `fechaApertura`, `saldoApertura`, `registro`) VALUES
('00001', 1, '2025-10-14 18:33:04', 1500, 1);

-- --------------------------------------------------------

--
-- Table structure for table `cajascerradas`
--

CREATE TABLE `cajascerradas` (
  `numCaja` varchar(5) DEFAULT NULL,
  `idEmpleado` int(6) DEFAULT NULL,
  `fechaApertura` datetime DEFAULT NULL,
  `fechaCierre` datetime DEFAULT NULL,
  `saldoInicial` float DEFAULT NULL,
  `saldoFinal` float DEFAULT NULL,
  `diferencia` float DEFAULT NULL,
  `registro` int(6) NOT NULL
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
-- Dumping data for table `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `apellido`, `empresa`, `tipo_identificacion`, `identificacion`, `telefono`, `notas`, `fechaRegistro`, `activo`) VALUES
(1, 'Juan', 'Perez', 'Ysapelli', 'cedula', '4026932343', '8496253610', 'Cliente Preferencia', '2025-10-13 23:51:33', 1),
(2, 'prueba', 'prueba', 'prueba', 'cedula', '4026932342', '8095632329', 'prueba', '2025-10-13 23:53:43', 0),
(3, 'prueba', 'prueba', 'prueba', 'cedula', '40240967626', '8097276431', 'prueba', '2025-10-15 19:37:53', 1);

-- --------------------------------------------------------

--
-- Table structure for table `clientes_cuenta`
--

CREATE TABLE `clientes_cuenta` (
  `idCliente` int(6) DEFAULT NULL,
  `limite_credito` float DEFAULT NULL,
  `balance` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clientes_cuenta`
--

INSERT INTO `clientes_cuenta` (`idCliente`, `limite_credito`, `balance`) VALUES
(1, 50000, 50000),
(2, 15000, 15000),
(3, 50000, 50000);

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

--
-- Dumping data for table `clientes_direcciones`
--

INSERT INTO `clientes_direcciones` (`idCliente`, `no`, `calle`, `sector`, `ciudad`, `referencia`, `registro`) VALUES
(1, '50', 'del Sol', 'Centro Historico', 'Santiago', 'Al lado de la farmacia', 1),
(2, 'prueba', 'prueba', 'prueba', 'prueba', 'prueba', 2),
(3, 'prueba', 'prueba', 'prueba', 'prueba', 'prueba', 3);

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
  `monto` float NOT NULL,
  `numAutorizacion` varchar(30) NOT NULL,
  `referencia` varchar(30) NOT NULL,
  `idBanco` int(6) NOT NULL,
  `idDestino` int(6) NOT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'N/A', 1);

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
(1, 'Desarrollador'),
(2, 'Administrador'),
(4, 'Vendedor');

-- --------------------------------------------------------

--
-- Table structure for table `facturas`
--

CREATE TABLE `facturas` (
  `numFactura` varchar(10) DEFAULT NULL,
  `tipoFactura` varchar(10) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `importe` float DEFAULT NULL,
  `descuento` float DEFAULT NULL,
  `total` float DEFAULT NULL,
  `total_ajuste` float DEFAULT NULL,
  `balance` float DEFAULT NULL,
  `idCliente` int(6) DEFAULT NULL,
  `idEmpleado` int(6) NOT NULL,
  `estado` varchar(20) DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facturas`
--

INSERT INTO `facturas` (`numFactura`, `tipoFactura`, `fecha`, `importe`, `descuento`, `total`, `total_ajuste`, `balance`, `idCliente`, `idEmpleado`, `estado`, `registro`) VALUES
('100001', 'contado', '2025-10-14 18:35:50', 4000, 500, 4000, 3500, 0, 1, 1, 'Pagada', 1),
('100002', 'credito', '2025-10-14 18:37:04', 26000, 0, 26000, 26000, 0, 1, 1, 'Cancelada', 2),
('100003', 'contado', '2025-10-15 17:33:04', 2000, 750, 2000, 1250, 0, 1, 1, 'Pagada', 3);

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

--
-- Dumping data for table `facturas_cancelaciones`
--

INSERT INTO `facturas_cancelaciones` (`numFactura`, `motivo`, `fecha`, `idEmpleado`, `registro`) VALUES
('100002', 'Prueba', '2025-10-14 18:43:07', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `facturas_detalles`
--

CREATE TABLE `facturas_detalles` (
  `numFactura` varchar(10) DEFAULT NULL,
  `idProducto` int(6) DEFAULT NULL,
  `cantidad` float DEFAULT NULL,
  `precioCompra` float DEFAULT NULL,
  `precioVenta` float DEFAULT NULL,
  `importe` float DEFAULT NULL,
  `ganancias` float NOT NULL,
  `fecha` datetime DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facturas_detalles`
--

INSERT INTO `facturas_detalles` (`numFactura`, `idProducto`, `cantidad`, `precioCompra`, `precioVenta`, `importe`, `ganancias`, `fecha`, `registro`) VALUES
('100001', 1, 2, 1500, 2000, 4000, 1000, '2025-10-14 18:35:50', 1),
('100002', 1, 13, 1500, 2000, 26000, 6500, '2025-10-14 18:37:04', 2),
('100003', 1, 1, 1500, 2000, 2000, 500, '2025-10-15 17:33:04', 3);

-- --------------------------------------------------------

--
-- Table structure for table `facturas_metodopago`
--

CREATE TABLE `facturas_metodopago` (
  `numFactura` varchar(10) DEFAULT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `monto` float DEFAULT NULL,
  `numAutorizacion` varchar(30) DEFAULT NULL,
  `referencia` varchar(30) DEFAULT NULL,
  `idBanco` int(5) DEFAULT NULL,
  `idDestino` int(5) DEFAULT NULL,
  `noCaja` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facturas_metodopago`
--

INSERT INTO `facturas_metodopago` (`numFactura`, `metodo`, `monto`, `numAutorizacion`, `referencia`, `idBanco`, `idDestino`, `noCaja`) VALUES
('100001', 'efectivo', 3500, 'N/A', 'N/A', 1, 1, '00001'),
('100002', 'efectivo', 0, 'N/A', 'N/A', 1, 1, '00001'),
('100003', 'efectivo', 1250, 'N/A', 'N/A', 1, 1, '00001');

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
  `existencia` float DEFAULT NULL,
  `ultima_actualizacion` datetime DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventario`
--

INSERT INTO `inventario` (`idProducto`, `existencia`, `ultima_actualizacion`, `registro`) VALUES
(1, 12, '2025-10-26 23:26:59', 1);

-- --------------------------------------------------------

--
-- Table structure for table `inventarioempleados`
--

CREATE TABLE `inventarioempleados` (
  `idProducto` int(6) DEFAULT NULL,
  `cantidad` float DEFAULT NULL,
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
  `cantidad` float DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `idEmpleado` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventariotransacciones`
--

INSERT INTO `inventariotransacciones` (`id`, `tipo`, `idProducto`, `cantidad`, `fecha`, `descripcion`, `idEmpleado`) VALUES
(1, 'ingreso', 1, 15, '2025-10-14 18:23:44', 'Ingreso por nuevo producto: ', 1),
(2, 'transferencia', 1, 15, '2025-10-14 18:24:21', 'Movimiento a inventario de empleado id:1', 1),
(3, 'venta', 1, 2, '2025-10-14 18:35:50', 'Venta por factura #100001', 1),
(4, 'venta', 1, 13, '2025-10-14 18:37:04', 'Venta por factura #100002', 1),
(5, 'retorno', 1, 13, '2025-10-14 18:43:07', 'Retorno por factura cancelada #100002', 1),
(6, 'venta', 1, 1, '2025-10-15 17:33:04', 'Venta por factura #100003', 1),
(7, 'transferencia', 1, 12, '2025-10-26 20:47:50', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(8, 'transferencia', 1, 12, '2025-10-26 20:48:07', 'Movimiento a inventario de empleado id:1', 1),
(9, 'transferencia', 1, 12, '2025-10-26 20:49:58', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(10, 'transferencia', 1, 12, '2025-10-26 22:39:10', 'Movimiento a inventario de empleado id:1', 1),
(11, 'transferencia', 1, 12, '2025-10-26 22:39:29', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(12, 'transferencia', 1, 12, '2025-10-26 22:41:09', 'Movimiento a inventario de empleado id:1', 1),
(13, 'transferencia', 1, 12, '2025-10-26 22:41:19', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(14, 'transferencia', 1, 12, '2025-10-26 22:42:13', 'Movimiento a inventario de empleado id:1', 1),
(15, 'transferencia', 1, 12, '2025-10-26 22:42:24', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(16, 'transferencia', 1, 12, '2025-10-26 22:43:24', 'Movimiento a inventario de empleado id:1', 1),
(17, 'transferencia', 1, 12, '2025-10-26 22:43:32', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(18, 'transferencia', 1, 12, '2025-10-26 22:45:01', 'Movimiento a inventario de empleado id:1', 1),
(19, 'transferencia', 1, 12, '2025-10-26 22:45:23', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(20, 'transferencia', 1, 12, '2025-10-26 22:47:59', 'Movimiento a inventario de empleado id:1', 1),
(21, 'transferencia', 1, 12, '2025-10-26 22:48:12', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(22, 'transferencia', 1, 12, '2025-10-26 22:52:34', 'Movimiento a inventario de empleado id:1', 1),
(23, 'transferencia', 1, 10, '2025-10-26 22:53:33', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(24, 'transferencia', 1, 2, '2025-10-26 22:55:14', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(25, 'transferencia', 1, 10, '2025-10-26 22:55:48', 'Movimiento a inventario de empleado id:1', 1),
(26, 'transferencia', 1, 10, '2025-10-26 22:56:37', 'Movimiento a inventario general desde inventario del empleado id:1', 1),
(27, 'transferencia', 1, 12, '2025-10-26 23:04:06', 'Movimiento a inventario de empleado id:1', 1),
(28, 'transferencia', 1, 12, '2025-10-26 23:26:59', 'Movimiento a inventario general desde inventario del empleado id:1', 1);

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
('00004');

-- --------------------------------------------------------

--
-- Table structure for table `productos`
--

CREATE TABLE `productos` (
  `id` int(6) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `idTipo` int(5) DEFAULT NULL,
  `existencia` float DEFAULT NULL,
  `precioCompra` float DEFAULT NULL,
  `precioVenta1` float DEFAULT NULL,
  `precioVenta2` float DEFAULT NULL,
  `reorden` float DEFAULT NULL,
  `fechaRegistro` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `productos`
--

INSERT INTO `productos` (`id`, `descripcion`, `idTipo`, `existencia`, `precioCompra`, `precioVenta1`, `precioVenta2`, `reorden`, `fechaRegistro`, `activo`) VALUES
(1, 'prueba', 1, 12, 1500, 2000, 2500, 5, '2025-10-14 18:23:44', 1);

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
(1, 'Prueba tipo');

-- --------------------------------------------------------

--
-- Table structure for table `transacciones_det`
--

CREATE TABLE `transacciones_det` (
  `no` int(5) NOT NULL,
  `id_producto` float NOT NULL,
  `cantidad` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transacciones_det`
--

INSERT INTO `transacciones_det` (`no`, `id_producto`, `cantidad`) VALUES
(1, 1, 10),
(2, 1, 10),
(3, 1, 12),
(4, 1, 12);

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

--
-- Dumping data for table `transacciones_inv`
--

INSERT INTO `transacciones_inv` (`no`, `fecha`, `id_emp_reg`, `id_emp_des`, `tipo_mov`) VALUES
(1, '2025-10-26 22:55:48', 1, 1, 'entrega'),
(2, '2025-10-26 22:56:37', 1, 1, 'retorno'),
(3, '2025-10-26 23:04:06', 1, 1, 'entrega'),
(4, '2025-10-26 23:26:59', 1, 1, 'retorno');

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
(1, 'fjoelfrias', '$2y$10$AmLhmrSvcXwZRWJXarWlAuXux44ghmArHi6Z.7aUkWkiWGMjzLbwe', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `auditoria_caja`
--
ALTER TABLE `auditoria_caja`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `auditoria_usuarios`
--
ALTER TABLE `auditoria_usuarios`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `auditoria_caja`
--
ALTER TABLE `auditoria_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `auditoria_usuarios`
--
ALTER TABLE `auditoria_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `bancos`
--
ALTER TABLE `bancos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cajaegresos`
--
ALTER TABLE `cajaegresos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cajaingresos`
--
ALTER TABLE `cajaingresos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cajasabiertas`
--
ALTER TABLE `cajasabiertas`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cajascerradas`
--
ALTER TABLE `cajascerradas`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `clientes_direcciones`
--
ALTER TABLE `clientes_direcciones`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `clientes_historialpagos`
--
ALTER TABLE `clientes_historialpagos`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `destinocuentas`
--
ALTER TABLE `destinocuentas`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `empleados_puestos`
--
ALTER TABLE `empleados_puestos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `facturas`
--
ALTER TABLE `facturas`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `facturas_cancelaciones`
--
ALTER TABLE `facturas_cancelaciones`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `facturas_detalles`
--
ALTER TABLE `facturas_detalles`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventario`
--
ALTER TABLE `inventario`
  MODIFY `registro` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventariotransacciones`
--
ALTER TABLE `inventariotransacciones`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `productos_tipo`
--
ALTER TABLE `productos_tipo`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transacciones_inv`
--
ALTER TABLE `transacciones_inv`
  MODIFY `no` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
