<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../src/event.php';

use App\Event;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POSTメソッドのみ許可されています']);
    exit;
}

$eventId = $_POST['id'] ?? '';
if (!$eventId) {
    http_response_code(400);
    echo json_encode(['error' => 'イベントIDが必要です']);
    exit;
}

$pdo = getPdo();
$eventObj = new Event($pdo);

$result = $eventObj->delete($eventId, $currentUserId);
if ($result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(403);
    echo json_encode(['error' => '削除権限がありません、または既に削除済みです']);
}
