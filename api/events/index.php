<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

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
        $params['keyword'] = '%' . $_GET['keyword'] . '%';
    }

    if (!empty($_GET['from'])) {
        $conditions[] = 'e.event_date >= :from';
        $params['from'] = $_GET['from'];
    }

    if (!empty($_GET['to'])) {
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
            c.color AS category_color
        FROM events e
        INNER JOIN categories c
            ON e.category_id = c.id
        WHERE {$whereClause}
        ORDER BY e.event_date ASC
        LIMIT :limit
        OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    json_response([
        'events' => $stmt->fetchAll(),
        'page' => $page,
        'total' => $total,
        'total_pages' => ceil($total / $limit)
    ]);

} catch (Throwable $e) {
    error_response('Failed to fetch events', 500);
}