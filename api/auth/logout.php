<?php

require_once __DIR__ . '/../../includes/response.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

json_response(['message' => 'ログアウトしました']);
