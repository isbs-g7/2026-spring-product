<?php
/**
 * api/events/detail.php
 * イベント詳細取得エンドポイント
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

try {
    $eventId = $_GET['id'] ?? null;
    if (!$eventId) {
        error_response('イベントIDが指定されていません', 400);
    }

    $pdo = getPdo();

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
            e.organizer_id,
            e.status,
            e.created_at,
            e.updated_at,
            c.id AS category_id,
            c.name AS category_name,
            c.color AS category_color,
            u.id AS organizer_id,
            u.display_name AS organizer_name,
            u.email AS organizer_email
        FROM events e
        INNER JOIN categories c ON e.category_id = c.id
        INNER JOIN users u ON e.organizer_id = u.id
        WHERE e.id = ? AND e.deleted_at IS NULL
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$event) {
        error_response('イベントが見つかりません', 404);
    }

    // is_online を整数から論理値に変換
    $event['is_online'] = (bool)$event['is_online'];

    json_response($event, 200);

} catch (Throwable $e) {
    error_response('イベント詳細の取得に失敗しました', 500);
}
