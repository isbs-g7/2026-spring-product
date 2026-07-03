<?php

require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/participation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Method Not Allowed', 405);
}

validate_csrf($_SESSION);

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$eventId = $body['event_id'] ?? '';

if ($eventId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $eventId)) {
    error_response('event_idが不正です', 400);
}

$pdo = getPdo();

try {
    join_event($pdo, $eventId, $currentUserId);
    json_response(['message' => '参加登録しました']);
} catch (Exception $e) {
    error_response($e->getMessage(), $e->getCode() ?: 400);
}
