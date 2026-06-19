<?php

require_once __DIR__ . '/../../includes/response.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

json_response(['csrf_token' => $_SESSION['csrf_token']]);
