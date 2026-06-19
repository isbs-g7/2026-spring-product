<?php
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/db.php';

// GETのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error_response('Method Not Allowed', 405);
}

$pdo = getPdo();

// --- パラメータ取得・バリデーション ---
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 20;
$offset      = ($page - 1) * $perPage;
$categoryId  = isset($_GET['category_id']) && ctype_digit($_GET['category_id'])
                ? (int)$_GET['category_id']
                : null;
$keyword     = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$from        = $_GET['from'] ?? '';
$to          = $_GET['to']   ?? '';

// 日付フォーマット検証（Y-m-d のみ許可）
$datePattern = '/^\d{4}-\d{2}-\d{2}$/';
if ($from && !preg_match($datePattern, $from)) {
    error_response('fromの日付形式が不正です（YYYY-MM-DD）');
}
if ($to && !preg_match($datePattern, $to)) {
    error_response('toの日付形式が不正です（YYYY-MM-DD）');
}

// --- WHERE句の組み立て ---
// 論理削除・公開済みは必須条件
$conditions = [
    'e.deleted_at IS NULL',
    "e.status = 'published'",
];
$bindings = [];

if ($categoryId !== null) {
    $conditions[] = 'e.category_id = :category_id';
    $bindings[':category_id'] = $categoryId;
}

if ($keyword !== '') {
    // タイトルと説明文をLIKE検索（SQLインジェクション対策: プリペアドステートメント使用）
    $conditions[] = '(e.title ILIKE :keyword OR e.description ILIKE :keyword)';
    $bindings[':keyword'] = '%' . $keyword . '%';
}

if ($from !== '') {
    $conditions[] = 'e.event_date >= :from';
    $bindings[':from'] = $from . ' 00:00:00+00';
}

if ($to !== '') {
    $conditions[] = 'e.event_date <= :to';
    $bindings[':to'] = $to . ' 23:59:59+00';
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// --- 件数取得 ---
$countSql = "
    SELECT COUNT(*) AS total
    FROM events e
    {$whereClause}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($bindings);
$total = (int)$countStmt->fetchColumn();

// --- イベント取得 ---
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
    {$whereClause}
    GROUP BY e.id, c.id, u.display_name
    ORDER BY e.event_date ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($bindings as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();

$events = $stmt->fetchAll();

// booleanとintegerの型変換（PDOはすべて文字列で返すため）
foreach ($events as &$event) {
    $event['is_online']         = (bool)$event['is_online'];
    $event['max_participants']  = $event['max_participants'] !== null
                                    ? (int)$event['max_participants']
                                    : null;
    $event['participant_count'] = (int)$event['participant_count'];
}
unset($event);

json_response([
    'events'   => $events,
    'total'    => $total,
    'page'     => $page,
    'per_page' => $perPage,
]);