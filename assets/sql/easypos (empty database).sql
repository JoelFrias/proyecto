-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 04, 2025 at 12:10 AM
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
(1, 'N/A', 1);

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

-- --------------------------------------------------------

--
-- Table structure for table `clientes_cuenta`
--

CREATE TABLE `clientes_cuenta` (
  `idCliente` int(6) DEFAULT NULL,
  `limite_credito` float DEFAULT NULL,
  `balance` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `monto` float NOT NULL,
  `numAutorizacion` varchar(30) NOT NULL,
  `referencia` varchar(30) NOT NULL,
  `idBanco` int(6) NOT NULL,
  `idDestino` int(6) NOT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, '2025-11-03 19:06:04');

-- --------------------------------------------------------

--
-- Table structure for table `cotizaciones_det`
--

CREATE TABLE `cotizaciones_det` (
  `no` varchar(10) NOT NULL,
  `id_producto` int(5) NOT NULL,
  `cantidad` float NOT NULL,
  `precio_s` float NOT NULL,
  `registro` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cotizaciones_inf`
--

CREATE TABLE `cotizaciones_inf` (
  `no` varchar(10) NOT NULL,
  `fecha` datetime NOT NULL,
  `id_cliente` int(5) NOT NULL,
  `id_empleado` int(5) NOT NULL,
  `subtotal` float NOT NULL,
  `descuento` float NOT NULL,
  `total` float NOT NULL,
  `notas` text NOT NULL,
  `estado` varchar(15) NOT NULL,
  `registro` int(5) NOT NULL
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
  `cantidad` float DEFAULT NULL,
  `precioCompra` float DEFAULT NULL,
  `precioVenta` float DEFAULT NULL,
  `importe` float DEFAULT NULL,
  `ganancias` float NOT NULL,
  `fecha` datetime DEFAULT NULL,
  `registro` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `productos_tipo`
--

CREATE TABLE `productos_tipo` (
  `id` int(6) NOT NULL,
  `descripcion` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transacciones_det`
--

CREATE TABLE `transacciones_det` (
  `no` int(5) NOT NULL,
  `id_producto` float NOT NULL,
  `cantidad` float NOT NULL
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auditoria_usuarios`
--
ALTER TABLE `auditoria_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bancos`
--
ALTER TABLE `bancos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `empleados_puestos`
--
ALTER TABLE `empleados_puestos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- AUTO_INCREMENT for table `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `productos_tipo`
--
ALTER TABLE `productos_tipo`
  MODIFY `id` int(6) NOT NULL AUTO_INCREMENT;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
