-- -----------------------------------------------------
-- Schema revistas_db
-- -----------------------------------------------------
DROP TABLE IF EXISTS envio CASCADE;
DROP TABLE IF EXISTS agencia_transporte CASCADE;
DROP TABLE IF EXISTS usuario CASCADE;
DROP TABLE IF EXISTS persona CASCADE;
DROP TABLE IF EXISTS ejemplar CASCADE;
DROP TABLE IF EXISTS revista CASCADE;

-- 1. Tabla revista
CREATE TABLE revista (
    id_revista SERIAL PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    periodicidad VARCHAR(50) NOT NULL
);

-- 2. Tabla ejemplar
CREATE TABLE ejemplar (
    id_ejemplar SERIAL PRIMARY KEY,
    id_revista INT NOT NULL,
    numero_edicion INT NOT NULL,
    fecha_publicacion DATE NOT NULL,
    CONSTRAINT fk_ejemplar_revista FOREIGN KEY (id_revista) REFERENCES revista (id_revista) ON DELETE RESTRICT
);

-- 3. Tabla persona (Clientes)
CREATE TABLE persona (
    id_persona SERIAL PRIMARY KEY,
    nombre_completo VARCHAR(150) NOT NULL,
    direccion_envio TEXT NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    telefono VARCHAR(50) NOT NULL
);

-- 4. Tabla usuario (Roles y Autenticación)
CREATE TABLE usuario (
    id_usuario SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL CHECK (rol IN ('admin', 'cliente')),
    id_persona INT UNIQUE, -- Puede ser nulo si es admin puro, obligatorio si es cliente
    CONSTRAINT fk_usuario_persona FOREIGN KEY (id_persona) REFERENCES persona (id_persona) ON DELETE RESTRICT
);

-- 5. Tabla agencia_transporte
CREATE TABLE agencia_transporte (
    id_agencia SERIAL PRIMARY KEY,
    nombre_agencia VARCHAR(150) NOT NULL,
    contacto VARCHAR(100) NOT NULL
);

-- 6. Tabla envio
CREATE TABLE envio (
    id_envio SERIAL PRIMARY KEY,
    id_ejemplar INT NOT NULL,
    id_persona INT NOT NULL,
    id_agencia INT,
    fecha_despacho DATE,
    estado VARCHAR(20) NOT NULL CHECK (estado IN ('Pendiente', 'En tránsito', 'Entregado')),
    numero_guia VARCHAR(100) UNIQUE,
    CONSTRAINT fk_envio_ejemplar FOREIGN KEY (id_ejemplar) REFERENCES ejemplar (id_ejemplar) ON DELETE RESTRICT,
    CONSTRAINT fk_envio_persona FOREIGN KEY (id_persona) REFERENCES persona (id_persona) ON DELETE RESTRICT,
    CONSTRAINT fk_envio_agencia FOREIGN KEY (id_agencia) REFERENCES agencia_transporte (id_agencia) ON DELETE RESTRICT
);

-- -----------------------------------------------------
-- Datos de Prueba (Dummy Data)
-- -----------------------------------------------------

-- 10 Revistas
INSERT INTO revista (titulo, categoria, periodicidad) VALUES 
('Tecnología Global', 'Tecnología', 'Mensual'),
('Cocina de Autor', 'Gastronomía', 'Quincenal'),
('Viajes Extraordinarios', 'Turismo', 'Bimestral'),
('Finanzas Clave', 'Economía', 'Mensual'),
('Salud y Vida', 'Medicina', 'Mensual'),
('Deporte Extremo', 'Deportes', 'Quincenal'),
('Diseño y Arte', 'Cultura', 'Trimestral'),
('Moda Urbana', 'Moda', 'Mensual'),
('Ciencia Hoy', 'Ciencia', 'Bimestral'),
('Mundo Motor', 'Automovilismo', 'Mensual');

-- 2 Ejemplares por Revista (Total 20)
INSERT INTO ejemplar (id_revista, numero_edicion, fecha_publicacion) VALUES 
(1, 101, '2026-06-01'), (1, 102, '2026-07-01'),
(2, 45, '2026-07-15'), (2, 46, '2026-08-01'),
(3, 12, '2026-05-01'), (3, 13, '2026-07-01'),
(4, 201, '2026-06-15'), (4, 202, '2026-07-15'),
(5, 88, '2026-06-01'), (5, 89, '2026-07-01'),
(6, 15, '2026-07-10'), (6, 16, '2026-07-25'),
(7, 4, '2026-04-01'), (7, 5, '2026-07-01'),
(8, 52, '2026-06-20'), (8, 53, '2026-07-20'),
(9, 30, '2026-05-10'), (9, 31, '2026-07-10'),
(10, 110, '2026-06-05'), (10, 111, '2026-07-05');

-- 12 Personas (Clientes)
INSERT INTO persona (nombre_completo, direccion_envio, ciudad, telefono) VALUES 
('Juan Pérez', 'Av. Siempre Viva 123', 'Ciudad de México', '555-1234'),
('María García', 'Calle Falsa 456', 'Guadalajara', '555-5678'),
('Carlos López', 'Av. Constitución 789', 'Monterrey', '555-9012'),
('Ana Martínez', 'Calle 5 de Mayo 101', 'Puebla', '555-3456'),
('Luis Rodríguez', 'Blvd. Diaz Ordaz 202', 'Tijuana', '555-7890'),
('Carmen Gómez', 'Calle Hidalgo 303', 'León', '555-2345'),
('José Sánchez', 'Calle 60 #404', 'Mérida', '555-6789'),
('Laura Díaz', 'Av. Corregidora 505', 'Querétaro', '555-0123'),
('Alejandro Fernández', 'Calle Carranza 606', 'San Luis Potosí', '555-4567'),
('Patricia Torres', 'Av. Tulum 707', 'Cancún', '555-8901'),
('Roberto Ramírez', 'Paseo Tollocan 808', 'Toluca', '555-2345'),
('Sofia Flores', 'Calle Libertad 909', 'Chihuahua', '555-6789');

-- Cuentas de Usuario (Bcrypt hashes para admin, cliente1 y cliente2. Texto plano para el resto que auto-migrará al iniciar sesión)
INSERT INTO usuario (username, password, rol, id_persona) VALUES 
('admin', '$2y$10$HA5OxsZwqut4BNfvxrYSkOTRRSiwV6SIn4Wuk.RvE0ysHDaeVhqpq', 'admin', NULL),
('cliente1', '$2y$10$XLgtWHq/rWdF2lg/xvgivuWgkudtU45H2brAK/61jbXnhclwXzRge', 'cliente', 1),
('cliente2', '$2y$10$fDFEmCEnpDuiTtvbo9927OYupXaKT4C.a1gTiPEnsiNRQc5lkeFYO', 'cliente', 2),
('cliente3', 'cliente3', 'cliente', 3),
('cliente4', 'cliente4', 'cliente', 4),
('cliente5', 'cliente5', 'cliente', 5),
('cliente6', 'cliente6', 'cliente', 6),
('cliente7', 'cliente7', 'cliente', 7),
('cliente8', 'cliente8', 'cliente', 8),
('cliente9', 'cliente9', 'cliente', 9),
('cliente10', 'cliente10', 'cliente', 10),
('cliente11', 'cliente11', 'cliente', 11),
('cliente12', 'cliente12', 'cliente', 12);

-- 5 Agencias
INSERT INTO agencia_transporte (nombre_agencia, contacto) VALUES 
('FedEx Express', 'contacto@fedex.com'),
('DHL Global', 'soporte@dhl.com'),
('Estafeta Local', 'ayuda@estafeta.com'),
('Servientrega', 'servicio@servientrega.com'),
('Correos de México', 'contacto@correos.gob.mx');

-- 15 Envíos (Varios estados)
INSERT INTO envio (id_ejemplar, id_persona, id_agencia, fecha_despacho, estado, numero_guia) VALUES 
(1, 1, 1, '2026-06-05', 'Entregado', 'FDX-99887766'),
(2, 1, 2, '2026-07-02', 'En tránsito', 'DHL-55443322'),
(3, 2, 3, '2026-07-16', 'Pendiente', NULL),
(5, 3, 4, '2026-05-10', 'Entregado', 'SRV-11223344'),
(7, 4, 5, '2026-06-20', 'Entregado', 'COR-44332211'),
(9, 5, 1, '2026-06-08', 'Entregado', 'FDX-11112222'),
(11, 6, 2, '2026-07-12', 'En tránsito', 'DHL-99998888'),
(13, 7, NULL, NULL, 'Pendiente', NULL),
(15, 8, 3, '2026-07-22', 'En tránsito', 'EST-77665544'),
(17, 9, 4, '2026-05-15', 'Entregado', 'SRV-55667788'),
(19, 10, NULL, NULL, 'Pendiente', NULL),
(1, 11, 5, '2026-06-10', 'Entregado', 'COR-88776655'),
(2, 12, 1, '2026-07-08', 'En tránsito', 'FDX-55554444'),
(4, 2, NULL, NULL, 'Pendiente', NULL),
(6, 3, 2, '2026-07-26', 'En tránsito', 'DHL-33332222');