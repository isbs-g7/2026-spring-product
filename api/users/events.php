<?php

require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    json_response(null, 401);
}

$pdo = getPdo();

// ログインユーザーが作成したイベント一覧を取得
$stmt = $pdo->prepare(
    'SELECT id, title, description, location, is_online, event_date, end_date, 
            category_id, max_participants, image_url, status, created_at, updated_at
     FROM events 
     WHERE organizer_id = ? AND deleted_at IS NULL
     ORDER BY event_date DESC'
);
$stmt->execute([$_SESSION['user_id']]);
$events = $stmt->fetchAll();

json_response([
    'events' => $events,
]);
