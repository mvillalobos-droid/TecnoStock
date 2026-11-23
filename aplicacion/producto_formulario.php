<?php
declare(strict_types=1);

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/catalogo.php';
require_once __DIR__ . '/vista.php';

requerirSesion();

// Sin id se crea un producto. Con id se carga y edita la misma fila.
$editar = array_key_exists('id', $_GET);
$idProducto = $editar ? leerIdCatalogo($_GET['id']) : null;
$titulo = $editar ? 'Editar producto' : 'Nuevo producto';
$accion = 'producto_formulario.php' . ($idProducto === null ? '' : '?id=' . $idProducto);
$datos = [
    'codigo' => '',
    'nombre' => '',
    'id_categoria' => '',
    'descripcion' => '',
    'precio' => '',
    'stock_actual' => '0',
    'stock_minimo' => '0',
];
$categorias = [];
$errores = [];
$errorPagina = '';
$errorFormulario = '';
$revision = '';

if ($editar && $idProducto === null) {
    http_response_code(400);
    $errorPagina = 'El identificador del producto es inválido.';
} else {
    try {
        $conexion = conectarBaseDeDatos();
        $categorias = array_column(listarCategorias($conexion), 'nombre', 'id_categoria');

        if ($idProducto !== null) {
            $producto = buscarProducto($conexion, $idProducto);

            if ($producto === false) {
                http_response_code(404);
                $errorPagina = 'El producto no está disponible.';
            } else {
                foreach (array_keys($datos) as $campo) {
                    $datos[$campo] = (string) $producto[$campo];
                }
                $revision = revisionProducto($producto);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errorPagina === '') {
            // Volvemos a validar en PHP y conservamos los datos si hay un error.
            foreach (array_keys($datos) as $campo) {
                $valor = $_POST[$campo] ?? '';
                $datos[$campo] = is_string($valor) ? trim($valor) : '';
            }
            $revisionEnviada = $_POST['revision'] ?? '';
            $revision = is_string($revisionEnviada) && preg_match('/\A[a-f0-9]{64}\z/', $revisionEnviada)
                ? $revisionEnviada : '';

            if (!tokenFormularioValido($_POST['token'] ?? null)) {
                http_response_code(403);
                $errorFormulario = 'La sesión del formulario venció. Vuelve a abrir esta pantalla.';
            } else {
                $errores = validarProducto($datos, $categorias);

                if ($errores !== []) {
                    http_response_code(422);
                } else {
                    // El precio viaja como texto decimal para conservar exactamente la cantidad ingresada.
                    $valores = $datos;
                    $valores['precio'] = str_replace(',', '.', $datos['precio']);
                    $valores['descripcion'] = $datos['descripcion'] === '' ? null : $datos['descripcion'];
                    $valores['id_categoria'] = (int) $datos['id_categoria'];
                    $valores['stock_actual'] = (int) $datos['stock_actual'];
                    $valores['stock_minimo'] = (int) $datos['stock_minimo'];

                    if ($idProducto === null) {
                        crearProducto($conexion, $valores);
                    } elseif (!actualizarProducto($conexion, $idProducto, $valores, $revision)) {
                        http_response_code(409);
                        $errorFormulario = 'El producto cambió desde que lo abriste. Vuelve a abrirlo desde el listado.';
                    }

                    if ($errorFormulario === '') {
                        $_SESSION['aviso_productos'] = $editar ? 'Producto actualizado.' : 'Producto creado.';
                        header('Location: productos.php', true, 303);
                        exit;
                    }
                }
            }
        }
    } catch (PDOException $excepcion) {
        $codigoMysql = (int) ($excepcion->errorInfo[1] ?? 0);

        if ($codigoMysql === 1062) {
            http_response_code(409);
            $errores['codigo'] = 'El código ya está en uso. Escribe un código único.';
        } elseif ($codigoMysql === 1452) {
            http_response_code(422);
            $errores['id_categoria'] = 'Selecciona una categoría que esté disponible.';
        } else {
            error_log('TecnoStock: error de formulario de producto ' . $excepcion->getCode());
            http_response_code(503);
            $errorFormulario = 'No se pudo consultar o guardar el producto. Revisa MySQL en XAMPP.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $titulo ?> | TecnoStock</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/estilos.css">
    <script src="assets/productos.js" defer></script>
</head>
<body>
    <?php mostrarNavegacion('productos'); ?>

    <main class="container-xl py-4 py-md-5">
        <h1 class="h2 mb-4"><?= $titulo ?></h1>

        <?php if ($errorPagina !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= escaparHtml($errorPagina) ?></div>
        <?php endif; ?>
        <?php if ($errorFormulario !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= escaparHtml($errorFormulario) ?></div>
        <?php endif; ?>
        <?php if ($errores !== []): ?>
            <ul class="alert alert-danger ps-5" role="alert">
                <?php foreach ($errores as $error): ?>
                    <li><?= escaparHtml($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($errorPagina !== '' || $categorias === []): ?>
            <?php if ($errorPagina === '' && $errorFormulario === ''): ?>
                <p class="alert alert-warning" role="alert">Agrega una categoría a la base antes de registrar productos.</p>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="productos.php">Volver a productos</a>
        <?php else: ?>
            <form method="post" action="<?= escaparHtml($accion) ?>" id="formulario-producto"
                  class="superficie-catalogo formulario-producto p-3 p-md-4">
                <input type="hidden" name="token" value="<?= tokenFormulario() ?>">
                <?php if ($editar): ?>
                    <input type="hidden" name="revision" value="<?= escaparHtml($revision) ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="codigo">Código</label>
                        <input class="form-control<?= isset($errores['codigo']) ? ' is-invalid' : '' ?>"
                               id="codigo" name="codigo" required maxlength="30" data-texto-obligatorio
                               value="<?= escaparHtml($datos['codigo']) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="nombre">Nombre</label>
                        <input class="form-control<?= isset($errores['nombre']) ? ' is-invalid' : '' ?>"
                               id="nombre" name="nombre" required maxlength="120" data-texto-obligatorio
                               value="<?= escaparHtml($datos['nombre']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="id-categoria">Categoría</label>
                        <select class="form-select<?= isset($errores['id_categoria']) ? ' is-invalid' : '' ?>"
                                id="id-categoria" name="id_categoria" required>
                            <option value="">Selecciona una categoría</option>
                            <?php foreach ($categorias as $idCategoria => $nombreCategoria): ?>
                                <option value="<?= (int) $idCategoria ?>"
                                    <?= $datos['id_categoria'] === (string) $idCategoria ? 'selected' : '' ?>>
                                    <?= escaparHtml($nombreCategoria) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="precio">Precio</label>
                        <input class="form-control<?= isset($errores['precio']) ? ' is-invalid' : '' ?>"
                               id="precio" name="precio" inputmode="decimal" maxlength="13" required
                               aria-describedby="ayuda-precio" pattern="[0-9]{1,10}([.,][0-9]{1,2})?"
                               value="<?= escaparHtml($datos['precio']) ?>">
                        <div class="form-text" id="ayuda-precio">Hasta dos decimales, con coma o punto.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="stock-actual">Stock actual</label>
                        <input class="form-control<?= isset($errores['stock_actual']) ? ' is-invalid' : '' ?>"
                               id="stock-actual" name="stock_actual" inputmode="numeric" required
                               maxlength="10" pattern="[0-9]{1,10}" data-stock
                               value="<?= escaparHtml($datos['stock_actual']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="stock-minimo">Stock mínimo</label>
                        <input class="form-control<?= isset($errores['stock_minimo']) ? ' is-invalid' : '' ?>"
                               id="stock-minimo" name="stock_minimo" inputmode="numeric" required
                               maxlength="10" pattern="[0-9]{1,10}" data-stock
                               value="<?= escaparHtml($datos['stock_minimo']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="descripcion">Descripción <span class="text-secondary">(opcional)</span></label>
                        <textarea class="form-control<?= isset($errores['descripcion']) ? ' is-invalid' : '' ?>"
                                  id="descripcion" name="descripcion" rows="3" maxlength="255"><?= escaparHtml($datos['descripcion']) ?></textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-primary" type="submit">Guardar producto</button>
                    <a class="btn btn-outline-secondary" href="productos.php">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
