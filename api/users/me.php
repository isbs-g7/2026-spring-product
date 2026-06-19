<?php

require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    json_response(null, 401);
}

// CSRFトークンがなければここで生成（ログイン後の初回アクセス時など）
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pdo  = getPdo();
$stmt = $pdo->prepare(
    'SELECT id, email, display_name, avatar_url, created_at FROM users WHERE id = ?'
);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    // DBにユーザーが存在しない場合はセッションを破棄
    session_destroy();
    json_response(null, 401);
}

json_response([
    'user'       => $user,
    'csrf_token' => $_SESSION['csrf_token'],
]);
