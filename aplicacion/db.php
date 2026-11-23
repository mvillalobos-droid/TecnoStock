<?php
declare(strict_types=1);

/**
 * Abre la conexión con la base local. Las variables de entorno permiten usar
 * otra cuenta de MariaDB si XAMPP tiene credenciales distintas a las iniciales.
 */
function conectarBaseDeDatos(): PDO
{
    $usuario = getenv('TECNOSTOCK_DB_USER') ?: 'root';
    $clave = getenv('TECNOSTOCK_DB_PASSWORD');

    return new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=tecnostock;charset=utf8mb4',
        $usuario,
        $clave === false ? '' : $clave,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}
