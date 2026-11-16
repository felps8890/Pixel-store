<?php
/** Encerra a sessão (somente via POST com token CSRF). */
require __DIR__ . '/includes/init.php';

if (eh_post()) {
    csrf_validar();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

redirecionar('index.php');
