<?php
declare(strict_types=1);

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/movimientos_datos.php';
require_once __DIR__ . '/vista.php';

requerirSesion();

$movimientos = [];
$error = '';
$aviso = $_SESSION['aviso_movimientos'] ?? '';
unset($_SESSION['aviso_movimientos']);

try {
    $movimientos = listarMovimientos(conectarBaseDeDatos());
} catch (PDOException $excepcion) {
    error_log('TecnoStock: error de historial de movimientos ' . $excepcion->getCode());
    http_response_code(503);
    $error = 'No se pudo cargar el historial. Revisa MySQL en XAMPP e inténtalo de nuevo.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Movimientos | TecnoStock</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/estilos.css">
</head>
<body>
    <?php mostrarNavegacion('movimientos'); ?>

    <main class="container-xl py-4 py-md-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <h1 class="h2 mb-0">Movimientos</h1>
            <a class="btn btn-primary" href="movimiento_formulario.php">Registrar movimiento</a>
        </div>

        <?php if ($aviso !== ''): ?>
            <div class="alert alert-success" role="status"><?= escaparHtml($aviso) ?></div>
        <?php endif; ?>

        <div class="superficie-catalogo">
            <?php if ($error !== ''): ?>
                <p class="m-0 p-4" role="alert"><?= escaparHtml($error) ?></p>
            <?php elseif ($movimientos === []): ?>
                <p class="m-0 p-4">Todavía no hay movimientos registrados.</p>
            <?php else: ?>
                <div class="table-responsive" tabindex="0" role="region" aria-label="Historial de movimientos">
                    <table class="table tabla-catalogo align-middle m-0" aria-label="Entradas y salidas de inventario">
                        <thead>
                            <tr>
                                <th scope="col">Fecha y hora</th>
                                <th scope="col">Producto</th>
                                <th scope="col">Tipo</th>
                                <th scope="col" class="text-end">Cantidad</th>
                                <th scope="col">Responsable</th>
                                <th scope="col">Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $movimiento): ?>
                                <?php
                                $fecha = new DateTimeImmutable($movimiento['fecha_hora']);
                                $entrada = $movimiento['tipo'] === 'ENTRADA';
                                ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <time datetime="<?= $fecha->format('Y-m-d\TH:i:s') ?>">
                                            <?= $fecha->format('d/m/Y H:i:s') ?>
                                        </time>
                                    </td>
                                    <th scope="row">
                                        <span class="d-block fw-semibold"><?= escaparHtml($movimiento['producto']) ?></span>
                                        <span class="text-secondary fw-normal"><?= escaparHtml($movimiento['codigo']) ?></span>
                                    </th>
                                    <td>
                                        <span class="estado-producto <?= $entrada ? 'estado-activo' : 'tipo-salida' ?>">
                                            <?= $entrada ? 'Entrada' : 'Salida' ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?= (int) $movimiento['cantidad'] ?></td>
                                    <td><?= escaparHtml($movimiento['usuario']) ?></td>
                                    <td class="observacion-movimiento">
                                        <?= escaparHtml($movimiento['observacion'] ?? '—') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
