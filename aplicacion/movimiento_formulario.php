<?php
declare(strict_types=1);

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/movimientos_datos.php';
require_once __DIR__ . '/vista.php';

$usuario = requerirSesion();
$productos = [];
$datos = ['id_producto' => '', 'tipo' => '', 'cantidad' => '', 'observacion' => ''];
$errores = [];
$error = '';

// Cada guardado exitoso consume este código de formulario para evitar un envío repetido.
$operacion = $_SESSION['operacion_movimiento'] ??= bin2hex(random_bytes(32));

try {
    $conexion = conectarBaseDeDatos();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (array_keys($datos) as $campo) {
            $valor = $_POST[$campo] ?? '';
            $datos[$campo] = is_string($valor) ? trim($valor) : '';
        }

        $operacionEnviada = $_POST['operacion'] ?? '';
        if (!tokenFormularioValido($_POST['token'] ?? null)) {
            http_response_code(403);
            $error = 'La sesión del formulario venció. Vuelve a abrir esta pantalla.';
        } elseif (!is_string($operacionEnviada) || !hash_equals($operacion, $operacionEnviada)) {
            http_response_code(409);
            $error = 'El formulario ya se usó o venció. Revisa el historial antes de repetir el movimiento.';
        } else {
            $idProducto = leerIdCatalogo($datos['id_producto']);
            $cantidad = leerUnidadesStock($datos['cantidad']);

            if ($idProducto === null) {
                $errores['id_producto'] = 'Selecciona un producto.';
            }
            if (!in_array($datos['tipo'], ['ENTRADA', 'SALIDA'], true)) {
                $errores['tipo'] = 'Selecciona Entrada o Salida.';
            }
            if ($cantidad === null || $cantidad === 0) {
                $errores['cantidad'] = 'La cantidad debe ser un entero entre 1 y 2.147.483.647.';
            }
            if (!mb_check_encoding($datos['observacion'], 'UTF-8') || mb_strlen($datos['observacion'], 'UTF-8') > 255) {
                $errores['observacion'] = 'La observación debe tener hasta 255 caracteres.';
            }

            if ($errores !== []) {
                http_response_code(422);
            } else {
                try {
                    // La transacción comprueba el saldo real aunque el formulario lleve tiempo abierto.
                    $resultado = registrarMovimiento(
                        $conexion,
                        $idProducto,
                        (int) $usuario['id'],
                        $datos['tipo'],
                        $cantidad,
                        $datos['observacion'] === '' ? null : $datos['observacion']
                    );
                    unset($_SESSION['operacion_movimiento']);

                    $accion = $datos['tipo'] === 'ENTRADA' ? 'Entrada registrada.' : 'Salida registrada.';
                    $_SESSION['aviso_movimientos'] = "$accion El stock de {$resultado['codigo']} quedó en {$resultado['stock']}.";
                    header('Location: movimientos.php', true, 303);
                    exit;
                } catch (DomainException $excepcion) {
                    http_response_code($excepcion->getCode() === 403 ? 403 : 422);
                    $error = $excepcion->getMessage();
                }
            }
        }
    }

    $productos = listarProductosActivos($conexion);
} catch (PDOException $excepcion) {
    error_log('TecnoStock: error de registro de movimiento ' . $excepcion->getCode());
    http_response_code(503);
    $error = 'No se pudo completar la operación. Revisa MySQL en XAMPP y consulta el historial.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrar movimiento | TecnoStock</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/estilos.css">
    <script src="assets/movimientos.js" defer></script>
</head>
<body>
    <?php mostrarNavegacion('movimientos'); ?>

    <main class="container-xl py-4 py-md-5">
        <h1 class="h2 mb-4">Registrar movimiento</h1>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= escaparHtml($error) ?></div>
        <?php endif; ?>
        <?php if ($errores !== []): ?>
            <ul class="alert alert-danger ps-5" role="alert">
                <?php foreach ($errores as $mensaje): ?>
                    <li><?= escaparHtml($mensaje) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($productos === []): ?>
            <?php if ($error === ''): ?>
                <p class="alert alert-warning">Registra un producto activo para poder agregar movimientos.</p>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="movimientos.php">Volver a movimientos</a>
        <?php else: ?>
            <form method="post" action="movimiento_formulario.php" id="formulario-movimiento"
                  class="superficie-catalogo formulario-movimiento p-3 p-md-4">
                <input type="hidden" name="token" value="<?= tokenFormulario() ?>">
                <input type="hidden" name="operacion" value="<?= escaparHtml($operacion) ?>">

                <div class="row g-3">
                    <div class="col-12">
                        <label for="movimiento-producto" class="form-label">Producto</label>
                        <select class="form-select<?= isset($errores['id_producto']) ? ' is-invalid' : '' ?>"
                                id="movimiento-producto" name="id_producto" aria-describedby="stock-disponible" required>
                            <option value="">Selecciona un producto</option>
                            <?php $stockMostrado = null; ?>
                            <?php foreach ($productos as $producto): ?>
                                <?php
                                $seleccionado = $datos['id_producto'] === (string) $producto['id_producto'];
                                if ($seleccionado) {
                                    $stockMostrado = (int) $producto['stock_actual'];
                                }
                                ?>
                                <option value="<?= (int) $producto['id_producto'] ?>"
                                        data-stock="<?= (int) $producto['stock_actual'] ?>" <?= $seleccionado ? 'selected' : '' ?>>
                                    <?= escaparHtml($producto['nombre'] . ' · ' . $producto['codigo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-text mb-0" id="stock-disponible" aria-live="polite">
                            <?= $stockMostrado === null ? '' : "Cantidad disponible $stockMostrado" ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label for="movimiento-tipo" class="form-label">Tipo</label>
                        <select class="form-select<?= isset($errores['tipo']) ? ' is-invalid' : '' ?>"
                                id="movimiento-tipo" name="tipo" required>
                            <option value="">Selecciona un tipo</option>
                            <option value="ENTRADA" <?= $datos['tipo'] === 'ENTRADA' ? 'selected' : '' ?>>Entrada</option>
                            <option value="SALIDA" <?= $datos['tipo'] === 'SALIDA' ? 'selected' : '' ?>>Salida</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="movimiento-cantidad" class="form-label">Cantidad</label>
                        <input class="form-control<?= isset($errores['cantidad']) ? ' is-invalid' : '' ?>"
                               id="movimiento-cantidad" name="cantidad" inputmode="numeric" required
                               maxlength="10" pattern="[0-9]{1,10}" data-maximo="<?= STOCK_MAXIMO ?>"
                               value="<?= escaparHtml($datos['cantidad']) ?>">
                    </div>
                    <div class="col-12">
                        <label for="movimiento-observacion" class="form-label">Observación <span class="text-secondary">(opcional)</span></label>
                        <textarea class="form-control<?= isset($errores['observacion']) ? ' is-invalid' : '' ?>"
                                  id="movimiento-observacion" name="observacion" rows="3"
                                  maxlength="255"><?= escaparHtml($datos['observacion']) ?></textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button class="btn btn-primary" type="submit">Guardar movimiento</button>
                    <a class="btn btn-outline-secondary" href="movimientos.php">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
