-- Cuenta de laboratorio para probar el formulario de la Fase 2 en XAMPP.
-- Si el correo ya está registrado, conserva el usuario y su contraseña.
USE tecnostock;

INSERT INTO usuario (nombre, correo, clave_hash, rol, estado)
VALUES (
    'Administración de demostración',
    'demo@tecnostock.test',
    '$2y$12$C2CuglqbHW.KhpvVEB5xiegtaGs.oJMmv6DUgpDCnVVdljSKgHA5C',
    'ADMINISTRADOR',
    1
)
ON DUPLICATE KEY UPDATE id_usuario = usuario.id_usuario;
