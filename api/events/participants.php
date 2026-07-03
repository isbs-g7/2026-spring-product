<?php

require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/participation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error_response('Method Not Allowed', 405);
}

$eventId = $_GET['id'] ?? '';

if ($eventId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $eventId)) {
    error_response('idパラメータが不正です', 400);
}

$pdo = getPdo();

try {
    $names = list_participants($pdo, $eventId, $currentUserId);
    json_response(['participants' => $names]);
} catch (Exception $e) {
    error_response($e->getMessage(), $e->getCode() ?: 400);
}
