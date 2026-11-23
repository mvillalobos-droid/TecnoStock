<?php
declare(strict_types=1);

require_once __DIR__ . '/sesion.php';

// El cierre se envía desde el formulario del panel mediante POST y su token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

if (!tokenFormularioValido($_POST['token'] ?? null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('La solicitud de cierre de sesión venció. Vuelve al panel e inténtalo nuevamente.');
}

$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $cookie['path'],
    'domain' => $cookie['domain'],
    'secure' => $cookie['secure'],
    'httponly' => $cookie['httponly'],
    'samesite' => $cookie['samesite'],
]);
session_destroy();

header('Location: index.php', true, 303);
exit;
