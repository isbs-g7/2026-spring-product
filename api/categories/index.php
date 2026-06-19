<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

try {
    $pdo = getPdo();

    $stmt = $pdo->query(
        '
        SELECT
            id,
            name,
            color
        FROM categories
        ORDER BY id
        '
    );

    json_response($stmt->fetchAll());

} catch (Throwable $e) {
    error_response('Failed to fetch categories', 500);
}