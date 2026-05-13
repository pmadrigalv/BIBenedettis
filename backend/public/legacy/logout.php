<?php

require_once __DIR__ . '/config/app.php';

if (session_id() === '') {
    session_start();
}

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        isset($params['path']) ? $params['path'] : '/',
        isset($params['domain']) ? $params['domain'] : '',
        isset($params['secure']) ? $params['secure'] : false,
        isset($params['httponly']) ? $params['httponly'] : false
    );
}

session_destroy();

header('Location: ' . appUrl('/login.php'));
exit;
