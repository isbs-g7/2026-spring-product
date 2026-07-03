<?php
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/db.php';

// GETのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error_response('Method Not Allowed', 405);
}

$pdo = getPdo();

$eventId = $_GET['id'] ?? '';
if ($eventId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $eventId)) {
    error_response('idパラメータが不正です', 400);
}

$sql = "
    SELECT
        e.id,
        e.title,
        e.description,
        e.location,
        e.is_online,
        e.event_date,
        e.end_date,
        e.max_participants,
        e.image_url,
        e.status,
        e.created_at,
        c.id   AS category_id,
        c.name AS category_name,
        c.color AS category_color,
        u.display_name AS organizer_name,
        COUNT(p.id) FILTER (WHERE p.status = 'confirmed') AS participant_count
    FROM events e
    INNER JOIN categories c ON c.id = e.category_id
    INNER JOIN users u      ON u.id = e.organizer_id
    LEFT  JOIN event_participations p ON p.event_id = e.id
    WHERE e.id = :id AND e.deleted_at IS NULL
    GROUP BY e.id, c.id, u.display_name
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':id', $eventId);
$stmt->execute();

$event = $stmt->fetch();

if (!$event) {
    error_response('イベントが見つかりません', 404);
}

$event['is_online']         = (bool)$event['is_online'];
$event['max_participants']  = $event['max_participants'] !== null
                                ? (int)$event['max_participants']
                                : null;
$event['participant_count'] = (int)$event['participant_count'];

json_response(['event' => $event]);
