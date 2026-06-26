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

$data = [
    'title' => $_POST['title'] ?? '',
    'description' => $_POST['description'] ?? '',
    'location' => $_POST['location'] ?? '',
    'event_date' => $_POST['event_date'] ?? '',
    'status' => $_POST['status'] ?? '',
];

try {
    $result = $eventObj->update($eventId, $currentUserId, $data);
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(403);
        echo json_encode(['error' => '編集権限がありません']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
