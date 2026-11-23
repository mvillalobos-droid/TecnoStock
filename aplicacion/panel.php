<?php
declare(strict_types=1);

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/vista.php';

$usuario = requerirSesion();
$rol = $usuario['rol'] === 'ADMINISTRADOR' ? 'Administrador' : 'Encargado de inventario';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inicio | TecnoStock</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/estilos.css">
</head>
<body>
    <?php mostrarNavegacion('inicio'); ?>

    <main class="container-xl py-5">
        <section class="bienvenida-panel p-4 p-md-5" aria-labelledby="titulo-inicio">
            <h1 id="titulo-inicio" class="h2 mb-2">
                Hola, <?= escaparHtml($usuario['nombre']) ?>
            </h1>
            <p class="text-secondary mb-0">Ingresaste como <?= $rol ?>.</p>
        </section>
    </main>
</body>
</html>
