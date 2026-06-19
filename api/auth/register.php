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

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$email       = trim($body['email']        ?? '');
$password    = $body['password']           ?? '';
$displayName = trim($body['display_name'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_response('有効なメールアドレスを入力してください');
}
if (!validate_email_domain($email)) {
    error_response('大学のメールアドレス（' . ALLOWED_EMAIL_DOMAIN . '）のみ登録できます', 403);
}
$pwError = validate_password($password);
if ($pwError !== null) {
    error_response($pwError);
}
if (mb_strlen($displayName) < 1 || mb_strlen($displayName) > 50) {
    error_response('表示名は1〜50文字で入力してください');
}

$pdo = getPdo();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    error_response('このメールアドレスは既に登録されています', 409);
}

$stmt = $pdo->prepare(
    'INSERT INTO users (email, password_hash, display_name) VALUES (?, ?, ?) RETURNING id'
);
$stmt->execute([$email, password_hash($password, PASSWORD_BCRYPT), $displayName]);
$user = $stmt->fetch();

// 登録後は自動ログイン
session_regenerate_id(true);
$_SESSION['user_id']    = $user['id'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

json_response(['message' => '登録が完了しました', 'user_id' => $user['id']], 201);
