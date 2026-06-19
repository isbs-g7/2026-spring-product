<?php

require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Method Not Allowed', 405);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

validate_csrf($_SESSION);

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$email    = trim($body['email']    ?? '');
$password = $body['password']       ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // 存在有無を推測させないため意図的にあいまいなメッセージ
    error_response('メールアドレスまたはパスワードが間違っています', 401);
}

$pdo  = getPdo();
$stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    error_response('メールアドレスまたはパスワードが間違っています', 401);
}

session_regenerate_id(true);
$_SESSION['user_id']    = $user['id'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

json_response(['message' => 'ログインしました', 'user_id' => $user['id']]);
