<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentUserId = $_SESSION['user_id'] ?? null;

try {
    $pdo = getPdo();

    $page = max((int)($_GET['page'] ?? 1), 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $conditions = [
        'e.deleted_at IS NULL',
        "e.status = 'published'"
    ];

    $params = [];

    if (!empty($_GET['category_id'])) {
        $conditions[] = 'e.category_id = :category_id';
        $params['category_id'] = (int)$_GET['category_id'];
    }

    if (!empty($_GET['keyword'])) {
        $conditions[] = '(e.title ILIKE :keyword OR e.description ILIKE :keyword)';
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $_GET['keyword']);
        $params['keyword'] = '%' . $escaped . '%';
    }

    if (!empty($_GET['from'])) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) || !strtotime($_GET['from'])) {
            error_response('fromの日付形式が不正です', 400);
        }
        $conditions[] = 'e.event_date >= :from';
        $params['from'] = $_GET['from'];
    }

    if (!empty($_GET['to'])) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) || !strtotime($_GET['to'])) {
            error_response('toの日付形式が不正です', 400);
        }
        $conditions[] = 'e.event_date <= :to';
        $params['to'] = $_GET['to'] . ' 23:59:59';
    }

    $whereClause = implode(' AND ', $conditions);

    $countSql = "
        SELECT COUNT(*)
        FROM events e
        WHERE {$whereClause}
    ";

    $countStmt = $pdo->prepare($countSql);

    foreach ($params as $key => $value) {
        $countStmt->bindValue(":{$key}", $value);
    }

    $countStmt->execute();

    $total = (int)$countStmt->fetchColumn();

    $sql = "
        SELECT
            e.id,
            e.title,
            e.description,
            e.location,
            e.event_date,
            e.max_participants,
            c.name AS category_name,
            c.color AS category_color,
            COUNT(p.id) FILTER (WHERE p.status = 'confirmed') AS participant_count,
            EXISTS (
                SELECT 1 FROM event_participations pu
                WHERE pu.event_id = e.id AND pu.user_id = :current_user_id AND pu.status = 'confirmed'
            ) AS is_participating,
            (e.organizer_id = :current_user_id_2) AS is_organizer
        FROM events e
        INNER JOIN categories c
            ON e.category_id = c.id
        LEFT JOIN event_participations p
            ON p.event_id = e.id
        WHERE {$whereClause}
        GROUP BY e.id, c.name, c.color
        ORDER BY e.event_date ASC
        LIMIT :limit
        OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value);
    }

    $stmt->bindValue(':current_user_id', $currentUserId, $currentUserId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':current_user_id_2', $currentUserId, $currentUserId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $events = $stmt->fetchAll();
    foreach ($events as &$event) {
        $event['max_participants']  = $event['max_participants'] !== null ? (int)$event['max_participants'] : null;
        $event['participant_count'] = (int)$event['participant_count'];
        $event['is_participating']  = (bool)$event['is_participating'];
        $event['is_organizer']      = (bool)$event['is_organizer'];
    }
    unset($event);

    json_response([
        'events' => $events,
        'page' => $page,
        'total' => $total,
        'total_pages' => ceil($total / $limit)
    ]);

} catch (Throwable $e) {
    error_response('Failed to fetch events', 500);
}
