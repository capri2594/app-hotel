-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS habitapp;
USE habitapp;

-- Crear la tabla de roles
CREATE TABLE IF NOT EXISTS rol (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(150)
);

-- Crear la tabla de funcionarios (Personal del hotel)
CREATE TABLE IF NOT EXISTS funcionario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    ci VARCHAR(20) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    rol_id INT NOT NULL,
    salario DECIMAL(10,2) NOT NULL,
    fecha_contratacion DATE NOT NULL,
    estado ENUM('ACTIVO', 'INACTIVO') DEFAULT 'ACTIVO',
    FOREIGN KEY (rol_id) REFERENCES rol(id)
);

-- Crear la tabla de usuarios vinculada al funcionario
CREATE TABLE IF NOT EXISTS usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL UNIQUE,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    FOREIGN KEY (funcionario_id) REFERENCES funcionario(id) ON DELETE CASCADE
);

-- Crear la tabla de tipos de habitacion
CREATE TABLE IF NOT EXISTS tipo_habitacion (
    id_tipo INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL,
    nombre VARCHAR(100) NOT NULL
);

-- Crear la tabla de habitaciones
CREATE TABLE IF NOT EXISTS habitacion (
    id_habitacion INT AUTO_INCREMENT PRIMARY KEY,
    numero INT NOT NULL UNIQUE,
    id_tipo INT NOT NULL,
    piso INT NOT NULL,
    estado ENUM('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'MANTENIMIENTO') DEFAULT 'DISPONIBLE',
    FOREIGN KEY (id_tipo) REFERENCES tipo_habitacion(id_tipo)
);

-- Crear la tabla de reservas
CREATE TABLE IF NOT EXISTS reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    habitacion_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    ci VARCHAR(20) NOT NULL,
    telefono VARCHAR(20),
    fecha_ingreso DATE NOT NULL,
    fecha_salida DATE NOT NULL,
    estado ENUM('PENDIENTE', 'CONFIRMADA', 'FINALIZADA', 'CANCELADA') DEFAULT 'PENDIENTE',
    FOREIGN KEY (habitacion_id) REFERENCES habitacion(id_habitacion)
);

-- Crear la tabla de pagos
CREATE TABLE IF NOT EXISTS pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id INT NOT NULL,
    tipo_pago ENUM('EFECTIVO', 'DEPOSITO', 'QR') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reserva_id) REFERENCES reservas(id)
);

-- --------------------------------------------------------
-- Volcado de datos de prueba
-- --------------------------------------------------------

-- Insertar Roles
INSERT INTO rol (nombre, descripcion) VALUES 
('SuperAdmin', 'Acceso total y configuración del sistema'),
('Administrador', 'Gestión general de hotel, reportes y personal'),
('Recepcionista', 'Gestión de reservas, clientes y habitaciones');

-- Insertar datos de prueba para funcionarios
INSERT INTO funcionario (nombres, apellidos, ci, telefono, rol_id, salario, fecha_contratacion, estado) VALUES 
('Super', 'Admin', '0000000', '00000000', 1, 0.00, '2023-01-01', 'ACTIVO'),
('Carlos', 'Mendoza', '1234567', '77712345', 2, 4500.00, '2023-01-15', 'ACTIVO'),
('Ana', 'Gomez', '7654321', '77754321', 3, 2500.00, '2023-06-10', 'ACTIVO');

-- Insertar usuarios de prueba vinculados a los funcionarios
INSERT INTO usuario (funcionario_id, usuario, password) VALUES 
(1, 'admin', '21232f297a57a5a743894a0e4a801fc3'), -- Pass: admin (MD5)
(2, 'carlos', 'e10adc3949ba59abbe56e057f20f883e'), -- Pass: 123456 (MD5)
(3, 'recepcion', 'e10adc3949ba59abbe56e057f20f883e'); -- Pass: 123456 (MD5)

-- Insertar tipos de habitacion
INSERT INTO tipo_habitacion (codigo, nombre) VALUES 
('SGL', 'Habitación Simple'),
('DBL', 'Habitación Doble'),
('MAT', 'Habitación Matrimonial');

-- Insertar las habitaciones del piso 4 al 8 para que el mapa renderice dinámicamente
INSERT INTO habitacion (numero, id_tipo, piso, estado) VALUES
(401, 1, 4, 'DISPONIBLE'), (402, 2, 4, 'OCUPADA'), (403, 3, 4, 'RESERVADA'),
(404, 1, 4, 'MANTENIMIENTO'), (405, 2, 4, 'DISPONIBLE'), (406, 1, 4, 'DISPONIBLE'),
(407, 3, 4, 'OCUPADA'), (408, 1, 4, 'DISPONIBLE'), (409, 2, 4, 'DISPONIBLE'),
(410, 1, 4, 'DISPONIBLE'), (411, 3, 4, 'DISPONIBLE'), (412, 1, 4, 'DISPONIBLE'),
(413, 2, 4, 'DISPONIBLE'), (414, 1, 4, 'DISPONIBLE'), (415, 3, 4, 'DISPONIBLE'),
(416, 1, 4, 'DISPONIBLE'), (417, 2, 4, 'DISPONIBLE'), (418, 1, 4, 'DISPONIBLE'),
(501, 1, 5, 'DISPONIBLE'), (502, 2, 5, 'DISPONIBLE'), (503, 3, 5, 'DISPONIBLE'), (504, 1, 5, 'DISPONIBLE'), (505, 2, 5, 'DISPONIBLE'), (506, 1, 5, 'DISPONIBLE'), (507, 3, 5, 'DISPONIBLE'), (508, 1, 5, 'DISPONIBLE'), (509, 2, 5, 'DISPONIBLE'), (510, 1, 5, 'DISPONIBLE'), (511, 3, 5, 'DISPONIBLE'), (512, 1, 5, 'DISPONIBLE'), (513, 2, 5, 'DISPONIBLE'), (514, 1, 5, 'DISPONIBLE'), (515, 3, 5, 'DISPONIBLE'), (516, 1, 5, 'DISPONIBLE'), (517, 2, 5, 'DISPONIBLE'), (518, 1, 5, 'DISPONIBLE'),
(601, 1, 6, 'DISPONIBLE'), (602, 2, 6, 'DISPONIBLE'), (603, 3, 6, 'DISPONIBLE'), (604, 1, 6, 'DISPONIBLE'), (605, 2, 6, 'DISPONIBLE'), (606, 1, 6, 'DISPONIBLE'), (607, 3, 6, 'DISPONIBLE'), (608, 1, 6, 'DISPONIBLE'), (609, 2, 6, 'DISPONIBLE'), (610, 1, 6, 'DISPONIBLE'), (611, 3, 6, 'DISPONIBLE'), (612, 1, 6, 'DISPONIBLE'), (613, 2, 6, 'DISPONIBLE'), (614, 1, 6, 'DISPONIBLE'), (615, 3, 6, 'DISPONIBLE'), (616, 1, 6, 'DISPONIBLE'), (617, 2, 6, 'DISPONIBLE'), (618, 1, 6, 'DISPONIBLE'),
(701, 1, 7, 'DISPONIBLE'), (702, 2, 7, 'DISPONIBLE'), (703, 3, 7, 'DISPONIBLE'), (704, 1, 7, 'DISPONIBLE'), (705, 2, 7, 'DISPONIBLE'), (706, 1, 7, 'DISPONIBLE'), (707, 3, 7, 'DISPONIBLE'), (708, 1, 7, 'DISPONIBLE'), (709, 2, 7, 'DISPONIBLE'), (710, 1, 7, 'DISPONIBLE'), (711, 3, 7, 'DISPONIBLE'), (712, 1, 7, 'DISPONIBLE'), (713, 2, 7, 'DISPONIBLE'), (714, 1, 7, 'DISPONIBLE'), (715, 3, 7, 'DISPONIBLE'), (716, 1, 7, 'DISPONIBLE'), (717, 2, 7, 'DISPONIBLE'), (718, 1, 7, 'DISPONIBLE'),
(801, 1, 8, 'DISPONIBLE'), (802, 2, 8, 'DISPONIBLE'), (803, 3, 8, 'DISPONIBLE'), (804, 1, 8, 'DISPONIBLE'), (805, 2, 8, 'DISPONIBLE'), (806, 1, 8, 'DISPONIBLE'), (807, 3, 8, 'DISPONIBLE'), (808, 1, 8, 'DISPONIBLE'), (809, 2, 8, 'DISPONIBLE'), (810, 1, 8, 'DISPONIBLE'), (811, 3, 8, 'DISPONIBLE'), (812, 1, 8, 'DISPONIBLE'), (813, 2, 8, 'DISPONIBLE'), (814, 1, 8, 'DISPONIBLE'), (815, 3, 8, 'DISPONIBLE'), (816, 1, 8, 'DISPONIBLE'), (817, 2, 8, 'DISPONIBLE'), (818, 1, 8, 'DISPONIBLE');