-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 07-04-2026 a las 00:44:59
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `habitapp`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `funcionario`
--

CREATE TABLE `funcionario` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `paterno` varchar(100) NOT NULL,
  `materno` varchar(100) DEFAULT NULL,
  `ci` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `rol_id` int(11) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') DEFAULT 'ACTIVO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `funcionario`
--

INSERT INTO `funcionario` (`id`, `nombre`, `paterno`, `materno`, `ci`, `telefono`, `rol_id`, `estado`) VALUES
(1, 'Super', 'Admin', '', '0000000', '00000000', 1, 'ACTIVO'),
(2, 'Carlos', 'Mendoza', '', '1234567', '77712345', 2, 'ACTIVO'),
(3, 'Ana', 'Gomez', '', '7654321', '77754321', 3, 'ACTIVO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `habitacion`
--

CREATE TABLE `habitacion` (
  `id_habitacion` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `id_tipo` int(11) NOT NULL,
  `piso` int(11) NOT NULL,
  `estado` enum('DISPONIBLE','OCUPADA','RESERVADA','MANTENIMIENTO') DEFAULT 'DISPONIBLE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `habitacion`
--

INSERT INTO `habitacion` (`id_habitacion`, `numero`, `id_tipo`, `piso`, `estado`) VALUES
(1, 401, 1, 4, 'DISPONIBLE'),
(2, 402, 2, 4, 'OCUPADA'),
(3, 403, 3, 4, 'RESERVADA'),
(4, 404, 1, 4, 'MANTENIMIENTO'),
(5, 405, 2, 4, 'DISPONIBLE'),
(6, 406, 1, 4, 'DISPONIBLE'),
(7, 407, 3, 4, 'OCUPADA'),
(8, 408, 1, 4, 'DISPONIBLE'),
(9, 409, 2, 4, 'DISPONIBLE'),
(10, 410, 1, 4, 'DISPONIBLE'),
(11, 411, 3, 4, 'DISPONIBLE'),
(12, 412, 1, 4, 'DISPONIBLE'),
(13, 413, 2, 4, 'DISPONIBLE'),
(14, 414, 1, 4, 'DISPONIBLE'),
(15, 415, 3, 4, 'DISPONIBLE'),
(16, 416, 1, 4, 'DISPONIBLE'),
(17, 417, 2, 4, 'DISPONIBLE'),
(18, 418, 1, 4, 'DISPONIBLE'),
(19, 501, 1, 5, 'DISPONIBLE'),
(20, 502, 2, 5, 'DISPONIBLE'),
(21, 503, 3, 5, 'DISPONIBLE'),
(22, 504, 1, 5, 'DISPONIBLE'),
(23, 505, 2, 5, 'DISPONIBLE'),
(24, 506, 1, 5, 'DISPONIBLE'),
(25, 507, 3, 5, 'DISPONIBLE'),
(26, 508, 1, 5, 'DISPONIBLE'),
(27, 509, 2, 5, 'DISPONIBLE'),
(28, 510, 1, 5, 'DISPONIBLE'),
(29, 511, 3, 5, 'DISPONIBLE'),
(30, 512, 1, 5, 'DISPONIBLE'),
(31, 513, 2, 5, 'DISPONIBLE'),
(32, 514, 1, 5, 'DISPONIBLE'),
(33, 515, 3, 5, 'DISPONIBLE'),
(34, 516, 1, 5, 'DISPONIBLE'),
(35, 517, 2, 5, 'DISPONIBLE'),
(36, 518, 1, 5, 'DISPONIBLE'),
(37, 601, 1, 6, 'DISPONIBLE'),
(38, 602, 2, 6, 'DISPONIBLE'),
(39, 603, 3, 6, 'DISPONIBLE'),
(40, 604, 1, 6, 'DISPONIBLE'),
(41, 605, 2, 6, 'DISPONIBLE'),
(42, 606, 1, 6, 'DISPONIBLE'),
(43, 607, 3, 6, 'DISPONIBLE'),
(44, 608, 1, 6, 'DISPONIBLE'),
(45, 609, 2, 6, 'DISPONIBLE'),
(46, 610, 1, 6, 'DISPONIBLE'),
(47, 611, 3, 6, 'DISPONIBLE'),
(48, 612, 1, 6, 'DISPONIBLE'),
(49, 613, 2, 6, 'DISPONIBLE'),
(50, 614, 1, 6, 'DISPONIBLE'),
(51, 615, 3, 6, 'DISPONIBLE'),
(52, 616, 1, 6, 'DISPONIBLE'),
(53, 617, 2, 6, 'DISPONIBLE'),
(54, 618, 1, 6, 'DISPONIBLE'),
(55, 701, 1, 7, 'DISPONIBLE'),
(56, 702, 2, 7, 'DISPONIBLE'),
(57, 703, 3, 7, 'DISPONIBLE'),
(58, 704, 1, 7, 'DISPONIBLE'),
(59, 705, 2, 7, 'DISPONIBLE'),
(60, 706, 1, 7, 'DISPONIBLE'),
(61, 707, 3, 7, 'DISPONIBLE'),
(62, 708, 1, 7, 'DISPONIBLE'),
(63, 709, 2, 7, 'DISPONIBLE'),
(64, 710, 1, 7, 'DISPONIBLE'),
(65, 711, 3, 7, 'DISPONIBLE'),
(66, 712, 1, 7, 'DISPONIBLE'),
(67, 713, 2, 7, 'DISPONIBLE'),
(68, 714, 1, 7, 'DISPONIBLE'),
(69, 715, 3, 7, 'DISPONIBLE'),
(70, 716, 1, 7, 'DISPONIBLE'),
(71, 717, 2, 7, 'DISPONIBLE'),
(72, 718, 1, 7, 'DISPONIBLE'),
(73, 801, 1, 8, 'DISPONIBLE'),
(74, 802, 2, 8, 'DISPONIBLE'),
(75, 803, 3, 8, 'DISPONIBLE'),
(76, 804, 1, 8, 'DISPONIBLE'),
(77, 805, 2, 8, 'DISPONIBLE'),
(78, 806, 1, 8, 'DISPONIBLE'),
(79, 807, 3, 8, 'DISPONIBLE'),
(80, 808, 1, 8, 'DISPONIBLE'),
(81, 809, 2, 8, 'DISPONIBLE'),
(82, 810, 1, 8, 'DISPONIBLE'),
(83, 811, 3, 8, 'DISPONIBLE'),
(84, 812, 1, 8, 'DISPONIBLE'),
(85, 813, 2, 8, 'DISPONIBLE'),
(86, 814, 1, 8, 'DISPONIBLE'),
(87, 815, 3, 8, 'DISPONIBLE'),
(88, 816, 1, 8, 'DISPONIBLE'),
(89, 817, 2, 8, 'DISPONIBLE'),
(90, 818, 1, 8, 'DISPONIBLE');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int(11) NOT NULL,
  `reserva_id` int(11) NOT NULL,
  `tipo_pago` enum('EFECTIVO','DEPOSITO','QR') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas`
--

CREATE TABLE `reservas` (
  `id` int(11) NOT NULL,
  `habitacion_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `ci` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `fecha_salida` date NOT NULL,
  `estado` enum('PENDIENTE','CONFIRMADA','FINALIZADA','CANCELADA') DEFAULT 'PENDIENTE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`id`, `nombre`, `descripcion`) VALUES
(1, 'SuperAdmin', 'Acceso total y configuración del sistema'),
(2, 'Administrador', 'Gestión general de hotel, reportes y personal'),
(3, 'Recepcionista', 'Gestión de reservas, clientes y habitaciones');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_habitacion`
--

CREATE TABLE `tipo_habitacion` (
  `id_tipo` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipo_habitacion`
--

INSERT INTO `tipo_habitacion` (`id_tipo`, `codigo`, `nombre`) VALUES
(1, 'SGL', 'Habitación Simple'),
(2, 'DBL', 'Habitación Doble'),
(3, 'MAT', 'Habitación Matrimonial');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id` int(11) NOT NULL,
  `funcionario_id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id`, `funcionario_id`, `usuario`, `password`) VALUES
(1, 1, 'admin', '21232f297a57a5a743894a0e4a801fc3'),
(2, 2, 'cmendoza', 'e10adc3949ba59abbe56e057f20f883e'),
(3, 3, 'anag', 'e10adc3949ba59abbe56e057f20f883e');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `funcionario`
--
ALTER TABLE `funcionario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ci` (`ci`),
  ADD KEY `rol_id` (`rol_id`);

--
-- Indices de la tabla `habitacion`
--
ALTER TABLE `habitacion`
  ADD PRIMARY KEY (`id_habitacion`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `id_tipo` (`id_tipo`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reserva_id` (`reserva_id`);

--
-- Indices de la tabla `reservas`
--
ALTER TABLE `reservas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `habitacion_id` (`habitacion_id`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `tipo_habitacion`
--
ALTER TABLE `tipo_habitacion`
  ADD PRIMARY KEY (`id_tipo`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `funcionario_id` (`funcionario_id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `funcionario`
--
ALTER TABLE `funcionario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `habitacion`
--
ALTER TABLE `habitacion`
  MODIFY `id_habitacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reservas`
--
ALTER TABLE `reservas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tipo_habitacion`
--
ALTER TABLE `tipo_habitacion`
  MODIFY `id_tipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `funcionario`
--
ALTER TABLE `funcionario`
  ADD CONSTRAINT `funcionario_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `rol` (`id`);

--
-- Filtros para la tabla `habitacion`
--
ALTER TABLE `habitacion`
  ADD CONSTRAINT `habitacion_ibfk_1` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_habitacion` (`id_tipo`);

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`);

--
-- Filtros para la tabla `reservas`
--
ALTER TABLE `reservas`
  ADD CONSTRAINT `reservas_ibfk_1` FOREIGN KEY (`habitacion_id`) REFERENCES `habitacion` (`id_habitacion`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionario` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
