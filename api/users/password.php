<?php

require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Method Not Allowed', 405);
}

validate_csrf($_SESSION);

$body            = json_decode(file_get_contents('php://input'), true) ?? [];
$currentPassword = $body['current_password'] ?? '';
$newPassword     = $body['new_password']      ?? '';

if ($currentPassword === '' || $newPassword === '') {
    error_response('現在のパスワードと新しいパスワードを入力してください');
}

$pwError = validate_password($newPassword);
if ($pwError !== null) {
    error_response($pwError);
}

$pdo  = getPdo();
$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$currentUserId]);
$user = $stmt->fetch();

if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
    error_response('現在のパスワードが間違っています', 401);
}

$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->execute([password_hash($newPassword, PASSWORD_BCRYPT), $currentUserId]);

json_response(['message' => 'パスワードを変更しました']);
