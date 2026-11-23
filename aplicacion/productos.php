<?php
declare(strict_types=1);

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/catalogo.php';
require_once __DIR__ . '/vista.php';

requerirSesion();

$entrada = $_GET['q'] ?? '';
$busqueda = is_string($entrada) && mb_check_encoding($entrada, 'UTF-8')
    ? mb_substr(trim($entrada), 0, 120, 'UTF-8')
    : '';
$productos = [];
$error = '';
$aviso = $_SESSION['aviso_productos'] ?? '';
$errorAccion = $_SESSION['error_productos'] ?? '';
unset($_SESSION['aviso_productos'], $_SESSION['error_productos']);

try {
    $productos = listarProductos(conectarBaseDeDatos(), $busqueda);
} catch (PDOException $excepcion) {
    error_log('TecnoStock: error de listado ' . $excepcion->getCode());
    http_response_code(503);
    $error = 'No se pudo cargar el catálogo. Revisa MySQL en XAMPP e inténtalo de nuevo.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Productos | TecnoStock</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/estilos.css">
    <script src="assets/productos.js" defer></script>
</head>
<body>
    <?php mostrarNavegacion('productos'); ?>

    <main class="container-xl py-4 py-md-5">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <h1 class="h2 mb-0">Productos</h1>
            <a class="btn btn-primary" href="producto_formulario.php">Nuevo producto</a>
        </div>

        <?php if ($aviso !== ''): ?>
            <div class="alert alert-success" role="status"><?= escaparHtml($aviso) ?></div>
        <?php endif; ?>
        <?php if ($errorAccion !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= escaparHtml($errorAccion) ?></div>
        <?php endif; ?>

        <div class="superficie-catalogo">
            <form class="d-flex flex-wrap gap-2 p-3 p-md-4" method="get" action="productos.php" role="search">
                <label class="visually-hidden" for="buscar-producto">Buscar por nombre, código o categoría</label>
                <input class="form-control campo-busqueda" id="buscar-producto" name="q" type="search"
                       maxlength="120" placeholder="Nombre, código o categoría" value="<?= escaparHtml($busqueda) ?>">
                <button class="btn btn-outline-primary" type="submit">Buscar</button>
                <?php if ($busqueda !== ''): ?>
                    <a class="btn btn-outline-secondary" href="productos.php">Limpiar</a>
                <?php endif; ?>
            </form>

            <?php if ($error !== ''): ?>
                <p class="border-top m-0 p-4" role="alert"><?= escaparHtml($error) ?></p>
            <?php elseif ($productos === []): ?>
                <p class="border-top m-0 p-4">
                    <?= $busqueda === '' ? 'Todavía no hay productos.' : 'No encontramos productos con esa búsqueda.' ?>
                </p>
            <?php else: ?>
                <div class="table-responsive" tabindex="0" role="region" aria-label="Listado de productos">
                    <table class="table tabla-catalogo align-middle m-0" aria-label="Productos del inventario">
                        <thead>
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Producto</th>
                                <th scope="col" class="text-end">Precio</th>
                                <th scope="col" class="text-nowrap">Stock / mínimo</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $producto): ?>
                                <?php
                                // La alerta usa una comparación estricta y solo se muestra en activos.
                                $idProducto = (int) $producto['id_producto'];
                                $activo = (int) $producto['estado'] === 1;
                                $stockActual = (int) $producto['stock_actual'];
                                $stockMinimo = (int) $producto['stock_minimo'];
                                $stockBajo = $activo && $stockActual < $stockMinimo;
                                ?>
                                <tr>
                                    <th scope="row" class="text-nowrap"><?= escaparHtml($producto['codigo']) ?></th>
                                    <td>
                                        <span class="d-block fw-semibold"><?= escaparHtml($producto['nombre']) ?></span>
                                        <span class="text-secondary"><?= escaparHtml($producto['categoria']) ?></span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?= number_format((float) $producto['precio'], 2, ',', '.') ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <strong><?= $stockActual ?></strong>
                                        <span class="text-secondary"> / <?= $stockMinimo ?></span>
                                        <?php if ($stockBajo): ?>
                                            <span class="alerta-stock d-table mt-1">Stock bajo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="estado-producto <?= $activo ? 'estado-activo' : 'estado-inactivo' ?>">
                                            <?= $activo ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-end gap-2">
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="producto_formulario.php?id=<?= $idProducto ?>"
                                               aria-label="Editar <?= escaparHtml($producto['codigo']) ?>">Editar</a>
                                            <?php if ($activo): ?>
                                                <form method="post" action="producto_desactivar.php" data-desactivar>
                                                    <input type="hidden" name="token" value="<?= tokenFormulario() ?>">
                                                    <input type="hidden" name="id" value="<?= $idProducto ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit"
                                                        aria-label="Desactivar <?= escaparHtml($producto['codigo']) ?>">
                                                        Desactivar
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
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
