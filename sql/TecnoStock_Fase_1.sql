-- TecnoStock | Fase 1 | MySQL 8.0.16 o posterior
-- Importar una sola vez en una base nueva o vacía llamada tecnostock.
-- Ejecutar el archivo completo y detener la importación ante el primer error.
-- El archivo crea el modelo, carga un inventario de ejemplo y muestra qué reponer.
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS tecnostock
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tecnostock;

-- 1. Usuarios. El correo identifica la cuenta al ingresar al sistema.
-- clave_hash guarda la huella bcrypt que PHP podrá comprobar con password_verify().
CREATE TABLE usuario (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    correo     VARCHAR(150) NOT NULL,
    clave_hash VARCHAR(255) NOT NULL,
    rol        VARCHAR(20)  NOT NULL DEFAULT 'ENCARGADO',
    estado     TINYINT      NOT NULL DEFAULT 1,
    CONSTRAINT uq_usuario_correo UNIQUE (correo),
    CONSTRAINT chk_usuario_rol CHECK (rol IN ('ADMINISTRADOR', 'ENCARGADO')),
    CONSTRAINT chk_usuario_estado CHECK (estado IN (0, 1))
) ENGINE=InnoDB;

-- 2. Categorías. Varios productos comparten una categoría a través de su ID.
-- Al cambiar el nombre de la categoría, basta con editar una fila.
CREATE TABLE categoria (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(80)  NOT NULL,
    descripcion  VARCHAR(255),
    estado       TINYINT      NOT NULL DEFAULT 1,
    CONSTRAINT uq_categoria_nombre UNIQUE (nombre),
    CONSTRAINT chk_categoria_estado CHECK (estado IN (0, 1))
) ENGINE=InnoDB;

-- 3. Productos. El código comercial es único, incluso si el producto está inactivo.
-- DECIMAL almacena precios exactos. El stock se cuenta en unidades enteras.
CREATE TABLE producto (
    id_producto  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo       VARCHAR(30)   NOT NULL,
    nombre       VARCHAR(120)  NOT NULL,
    id_categoria INT UNSIGNED  NOT NULL,
    descripcion  VARCHAR(255),
    precio       DECIMAL(12,2) NOT NULL,
    stock_actual INT           NOT NULL DEFAULT 0,
    stock_minimo INT           NOT NULL DEFAULT 0,
    estado       TINYINT       NOT NULL DEFAULT 1,
    CONSTRAINT uq_producto_codigo UNIQUE (codigo),
    CONSTRAINT chk_producto_codigo CHECK (CHAR_LENGTH(TRIM(codigo)) > 0),
    CONSTRAINT chk_producto_precio CHECK (precio >= 0),
    CONSTRAINT chk_producto_stock_actual CHECK (stock_actual >= 0),
    CONSTRAINT chk_producto_stock_minimo CHECK (stock_minimo >= 0),
    CONSTRAINT chk_producto_estado CHECK (estado IN (0, 1)),
    CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria)
        REFERENCES categoria (id_categoria) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- La baja se registra cambiando estado a 0. Este disparador bloquea DELETE
-- incluso para un producto recién creado que todavía carece de movimientos.
CREATE TRIGGER impedir_borrado_producto
BEFORE DELETE ON producto FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Para desactivar el producto, cambie su estado a 0.';

-- 4. Movimientos. Cada fila identifica qué producto se movió, cuándo y quién
-- lo registró. La cantidad es positiva y el tipo indica si entra o sale.
CREATE TABLE movimiento (
    id_movimiento BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha_hora    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo          VARCHAR(7)    NOT NULL,
    cantidad      INT           NOT NULL,
    observacion   VARCHAR(255),
    id_producto   INT UNSIGNED  NOT NULL,
    id_usuario    INT UNSIGNED  NOT NULL,
    CONSTRAINT chk_movimiento_tipo CHECK (tipo IN ('ENTRADA', 'SALIDA')),
    CONSTRAINT chk_movimiento_cantidad CHECK (cantidad > 0),
    CONSTRAINT fk_movimiento_producto FOREIGN KEY (id_producto)
        REFERENCES producto (id_producto) ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT,
    INDEX ix_movimiento_producto_fecha (id_producto, fecha_hora)
) ENGINE=InnoDB;

-- 5. Datos de prueba para una instalación nueva. Los ID explícitos permiten
-- seguir las relaciones al leer el ejemplo. Los futuros ID se autogeneran.
-- Las cuatro inserciones forman una única transacción de carga inicial.
START TRANSACTION;

INSERT INTO usuario (id_usuario, nombre, correo, clave_hash, rol, estado) VALUES
    (1, 'Administración de prueba', 'admin@tecnostock.test',
     '$2y$12$jRAMqHpYaRg1iyzezvzboOcCx4KAx2zRrbIUo0x9BwJRS4fkVvHLu', 'ADMINISTRADOR', 1);

INSERT INTO categoria (id_categoria, nombre, descripcion) VALUES
    (1, 'Periféricos', 'Dispositivos de entrada para computador'),
    (2, 'Cables',      'Cables de conexión y carga');

-- Se carga una fotografía de los saldos al cierre del día 20 de noviembre.
-- Cada saldo coincide con las entradas menos las salidas del historial inferior.
INSERT INTO producto
    (id_producto, codigo, nombre, id_categoria, descripcion, precio, stock_actual, stock_minimo, estado)
VALUES
    (1, 'PER-001', 'Mouse inalámbrico', 1, 'Mouse con receptor USB',          8990.00,  2,  5, 1),
    (2, 'CAB-001', 'Cable USB-C',       2, 'Cable de carga de un metro',      3990.00, 10, 10, 1),
    (3, 'PER-002', 'Teclado compacto',  1, 'Teclado con distribución latina', 15990.00, 12,  4, 1),
    (4, 'CAB-002', 'Cable micro-USB',   2, 'Modelo descontinuado',            2490.00,  0,  3, 0);

INSERT INTO movimiento (fecha_hora, tipo, cantidad, observacion, id_producto, id_usuario) VALUES
    ('2026-11-20 09:00:00', 'ENTRADA',  8, 'Inventario inicial', 1, 1),
    ('2026-11-20 09:05:00', 'ENTRADA', 10, 'Inventario inicial', 2, 1),
    ('2026-11-20 09:10:00', 'ENTRADA', 12, 'Inventario inicial', 3, 1),
    ('2026-11-20 09:15:00', 'ENTRADA',  2, 'Inventario inicial', 4, 1),
    ('2026-11-20 10:00:00', 'SALIDA',   6, 'Venta de seis mouse', 1, 1),
    ('2026-11-20 10:05:00', 'SALIDA',   2, 'Última venta antes de la baja', 4, 1);

COMMIT;

-- 6. Comprobación para el video. Debe aparecer solo PER-001 (2 < 5).
-- CAB-001 está justo en el mínimo; CAB-002 está desactivado y conserva su historial.
SELECT codigo, nombre, stock_actual, stock_minimo
FROM producto
WHERE estado = 1 AND stock_actual < stock_minimo
ORDER BY codigo;

-- Alcance de esta fase: los movimientos anteriores documentan la carga inicial.
-- En la fase 4, cada registro nuevo y su actualización del saldo deberán formar
-- una sola transacción, que rechazará la salida cuando falten unidades.
