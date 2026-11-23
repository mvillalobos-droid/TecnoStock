<?php
declare(strict_types=1);

require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/db.php';

if (isset($_SESSION['usuario'])) {
    header('Location: panel.php', true, 303);
    exit;
}

$correo = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correoIngresado = $_POST['correo'] ?? '';
    $correo = is_string($correoIngresado) ? trim($correoIngresado) : '';
    $clave = $_POST['clave'] ?? '';

    if (!tokenFormularioValido($_POST['token'] ?? null)) {
        http_response_code(400);
        $error = 'Vuelve a enviar el formulario desde esta página.';
    } elseif (
        strlen($correo) > 150
        || filter_var($correo, FILTER_VALIDATE_EMAIL) === false
        || !is_string($clave)
        || $clave === ''
        || strlen($clave) > 72
        || str_contains($clave, "\0")
    ) {
        // La validación del servidor se ejecuta incluso si JavaScript está deshabilitado.
        http_response_code(422);
        $error = 'Escribe un correo válido y una contraseña de hasta 72 bytes.';
    } else {
        try {
            // PDO recibe el correo como un dato separado de la instrucción SQL.
            $consulta = conectarBaseDeDatos()->prepare(
                'SELECT id_usuario, nombre, rol, clave_hash
                 FROM usuario
                 WHERE correo = :correo AND estado = 1
                 LIMIT 1'
            );
            $consulta->execute(['correo' => $correo]);
            $usuario = $consulta->fetch();

            if ($usuario !== false && password_verify($clave, $usuario['clave_hash'])) {
                // Cambiamos el identificador al pasar de visitante a usuario autenticado.
                session_regenerate_id(true);
                $_SESSION['usuario'] = [
                    'id' => (int) $usuario['id_usuario'],
                    'nombre' => $usuario['nombre'],
                    'rol' => $usuario['rol'],
                ];
                unset($_SESSION['token_formulario']);
                header('Location: panel.php', true, 303);
                exit;
            }

            http_response_code(401);
            $error = 'Correo o contraseña incorrectos.';
        } catch (PDOException $excepcion) {
            // El registro del servidor conserva el código técnico para diagnóstico.
            error_log('TecnoStock: error de base de datos ' . $excepcion->getCode());
            http_response_code(503);
            $error = 'No se pudo consultar la base de datos. Revisa MySQL en XAMPP.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | TecnoStock</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/estilos.css">
    <script src="assets/acceso.js" defer></script>
</head>
<body>
    <main class="pagina-acceso container min-vh-100 d-flex align-items-center justify-content-center">
        <div class="tarjeta-acceso row g-0">
            <div class="marca-acceso col-md-5 d-flex flex-column justify-content-md-end">
                <p class="nombre-marca mb-2">TecnoStock</p>
                <p class="descripcion-marca mb-0">Inventario de accesorios tecnológicos</p>
            </div>

            <section class="formulario-acceso col-md-7" aria-labelledby="titulo-acceso">
                <h1 id="titulo-acceso" class="h2 mb-4">Iniciar sesión</h1>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="index.php" id="formulario-acceso">
                    <input type="hidden" name="token" value="<?= tokenFormulario() ?>">

                    <div class="mb-3">
                        <label for="correo" class="form-label">Correo electrónico</label>
                        <input class="form-control" id="correo" name="correo" type="email"
                               maxlength="150" autocomplete="username" required
                               aria-describedby="correo-error"
                               value="<?= htmlspecialchars($correo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <div class="invalid-feedback" id="correo-error">Escribe un correo válido.</div>
                    </div>

                    <div class="mb-4">
                        <label for="clave" class="form-label">Contraseña</label>
                        <input class="form-control" id="clave" name="clave" type="password"
                               autocomplete="current-password" required aria-describedby="clave-error">
                        <div class="invalid-feedback" id="clave-error">Escribe tu contraseña.</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
