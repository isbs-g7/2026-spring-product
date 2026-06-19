<?php
// 認証が必要なAPIファイルの先頭で require する。
// require後、$currentUserId にログイン中ユーザーのUUIDがセットされる。
require_once __DIR__ . '/response.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    error_response('Unauthorized', 401);
}

$currentUserId = $_SESSION['user_id'];
