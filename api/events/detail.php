<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../src/event.php';

use App\Event;

header('Content-Type: application/json; charset=utf-8');

$eventId = $_GET['id'] ?? '';
if (!$eventId) {
    http_response_code(400);
    echo json_encode(['error' => 'イベントIDが必要です']);
    exit;
}

$pdo = getPdo();
$eventObj = new Event($pdo);
$event = $eventObj->getById($eventId);

if ($event) {
    echo json_encode($event, JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'イベントが見つかりません']);
}
