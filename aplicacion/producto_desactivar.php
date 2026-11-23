<?php
declare(strict_types=1);

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/catalogo.php';

requerirSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

if (!tokenFormularioValido($_POST['token'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('La solicitud venció. Vuelve al listado e inténtalo de nuevo.');
}

$id = leerIdCatalogo($_POST['id'] ?? null);
if ($id === null) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    exit('El identificador del producto es inválido.');
}

try {
    $conexion = conectarBaseDeDatos();
    if (desactivarProducto($conexion, $id)) {
        $_SESSION['aviso_productos'] = 'Producto desactivado. Su ficha y su historial se conservaron.';
    } elseif (buscarProducto($conexion, $id) === false) {
        $_SESSION['error_productos'] = 'El producto no está disponible.';
    } else {
        $_SESSION['aviso_productos'] = 'El producto ya estaba inactivo.';
    }
} catch (PDOException $excepcion) {
    error_log('TecnoStock: error de desactivación ' . $excepcion->getCode());
    $_SESSION['error_productos'] = 'No se pudo desactivar el producto. Revisa MySQL en XAMPP.';
}

header('Location: productos.php', true, 303);
exit;
