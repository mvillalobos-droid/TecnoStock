<?php
declare(strict_types=1);

// PHP conserva los datos de la sesión en el servidor y envía un identificador al navegador.
session_name('TecnoStockSession');
session_start([
    'use_strict_mode' => true,
    'use_only_cookies' => true,
    'cookie_httponly' => true,
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_samesite' => 'Lax',
]);

// Protege las páginas del panel incluso cuando alguien escribe su URL directamente.
function requerirSesion(): array
{
    if (!isset($_SESSION['usuario'])) {
        header('Location: index.php', true, 303);
        exit;
    }

    return $_SESSION['usuario'];
}

// Cada sesión tiene un token para distinguir sus formularios de peticiones ajenas.
function tokenFormulario(): string
{
    return $_SESSION['token_formulario'] ??= bin2hex(random_bytes(32));
}

function tokenFormularioValido(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['token_formulario'])
        && hash_equals($_SESSION['token_formulario'], $token);
}
