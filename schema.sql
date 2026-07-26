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

INSERT INTO revista (titulo, categoria, periodicidad) VALUES 
('Tecnología Global', 'Tecnología', 'Mensual'),
('Cocina de Autor', 'Gastronomía', 'Quincenal'),
('Viajes Extraordinarios', 'Turismo', 'Bimestral');

INSERT INTO ejemplar (id_revista, numero_edicion, fecha_publicacion) VALUES 
(1, 101, '2026-06-01'),
(1, 102, '2026-07-01'),
(2, 45, '2026-07-15'),
(3, 12, '2026-05-01');

INSERT INTO persona (nombre_completo, direccion_envio, ciudad, telefono) VALUES 
('Juan Pérez', 'Av. Siempre Viva 123', 'Ciudad de México', '555-1234'),
('María García', 'Calle Falsa 456', 'Guadalajara', '555-5678');

-- Contraseñas en texto plano para facilidad de prueba. El backend soporta texto plano para estos datos iniciales.
INSERT INTO usuario (username, password, rol, id_persona) VALUES 
('admin', 'admin', 'admin', NULL),
('cliente1', 'cliente1', 'cliente', 1),
('cliente2', 'cliente2', 'cliente', 2);

INSERT INTO agencia_transporte (nombre_agencia, contacto) VALUES 
('FedEx Express', 'contacto@fedex.com'),
('DHL Global', 'soporte@dhl.com'),
('Estafeta Local', 'ayuda@estafeta.com');

INSERT INTO envio (id_ejemplar, id_persona, id_agencia, fecha_despacho, estado, numero_guia) VALUES 
(1, 1, 1, '2026-06-05', 'Entregado', 'FDX-99887766'),
(2, 1, 2, '2026-07-02', 'En tránsito', 'DHL-55443322'),
(3, 2, NULL, NULL, 'Pendiente', NULL);
