<?php
/**
 * api/events/update.php
 * イベント編集エンドポイント
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validation.php';

// POSTリクエストのみを許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Method Not Allowed', 405);
}

// CSRF保護
validate_csrf($_SESSION);

// 入力を取得
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    error_response('Invalid JSON parameters', 400);
}

$eventId = $input['id'] ?? null;
if (!$eventId) {
    error_response('イベントIDが指定されていません', 400);
}

// 更新フィールドの抽出
$title = isset($input['title']) ? trim($input['title']) : null;
$description = isset($input['description']) ? trim($input['description']) : null;
$location = isset($input['location']) ? trim($input['location']) : null;
$event_date_raw = isset($input['event_date']) ? trim($input['event_date']) : null;
$end_date_raw = isset($input['end_date']) ? trim($input['end_date']) : null;
$category_id = isset($input['category_id']) ? (int)$input['category_id'] : null;
$max_participants = isset($input['max_participants']) && $input['max_participants'] !== '' ? (int)$input['max_participants'] : null;
$is_online = isset($input['is_online']) ? !empty($input['is_online']) : null;
$image_url = isset($input['image_url']) ? trim($input['image_url']) : null;

try {
    $pdo = getPdo();

    // イベントが存在し、自分が主催者であることを確認
    $stmt = $pdo->prepare(
        'SELECT organizer_id FROM events WHERE id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(\PDO::FETCH_ASSOC);

    if (!$event) {
        error_response('イベントが見つかりません', 404);
    }

    if ($event['organizer_id'] !== $currentUserId) {
        error_response('このイベントを編集する権限がありません', 403);
    }

    // バリデーション
    if ($title !== null && $title === '') {
        error_response('タイトルは必須です', 400);
    }
    if ($title !== null && mb_strlen($title) > 100) {
        error_response('タイトルは100文字以内で入力してください', 400);
    }

    if ($description !== null && $description === '') {
        error_response('説明は必須です', 400);
    }

    if ($location !== null && $location === '') {
        error_response('開催場所は必須です', 400);
    }
    if ($location !== null && mb_strlen($location) > 200) {
        error_response('開催場所は200文字以内で入力してください', 400);
    }

    // 日付のバリデーション
    $event_date = null;
    if ($event_date_raw !== null) {
        $event_date = date_create($event_date_raw);
        if (!$event_date) {
            error_response('開催日時の形式が正しくありません', 400);
        }
    }

    $end_date = null;
    if ($end_date_raw !== null && $end_date_raw !== '') {
        $end_date = date_create($end_date_raw);
        if (!$end_date) {
            error_response('終了日時の形式が正しくありません', 400);
        }
        if ($event_date && $end_date <= $event_date) {
            error_response('終了日時は開催日時より後の時刻を指定してください', 400);
        }
    }

    // カテゴリの確認（指定されている場合）
    if ($category_id !== null) {
        $catStmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $catStmt->execute([$category_id]);
        if (!$catStmt->fetch()) {
            error_response('指定されたカテゴリは存在しません', 400);
        }
    }

    // 更新クエリを動的に構築
    $updates = [];
    $values = [];

    if ($title !== null) {
        $updates[] = 'title = ?';
        $values[] = $title;
    }
    if ($description !== null) {
        $updates[] = 'description = ?';
        $values[] = $description;
    }
    if ($location !== null) {
        $updates[] = 'location = ?';
        $values[] = $location;
    }
    if ($event_date !== null) {
        $updates[] = 'event_date = ?';
        $values[] = $event_date->format('Y-m-d H:i:sO');
    }
    if ($end_date_raw !== null) {
        $updates[] = 'end_date = ?';
        $values[] = $end_date ? $end_date->format('Y-m-d H:i:sO') : null;
    }
    if ($category_id !== null) {
        $updates[] = 'category_id = ?';
        $values[] = $category_id;
    }
    if ($is_online !== null) {
        $updates[] = 'is_online = ?';
        $values[] = $is_online ? 1 : 0;
    }
    if ($max_participants !== null || isset($input['max_participants'])) {
        $updates[] = 'max_participants = ?';
        $values[] = $max_participants;
    }
    if ($image_url !== null) {
        $updates[] = 'image_url = ?';
        $values[] = $image_url !== '' ? $image_url : null;
    }

    if (empty($updates)) {
        error_response('更新するフィールドがありません', 400);
    }

    // 更新実行
    $values[] = $eventId;
    $values[] = $currentUserId;

    $sql = 'UPDATE events SET ' . implode(', ', $updates)
         . ' WHERE id = ? AND organizer_id = ? AND deleted_at IS NULL';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    if ($stmt->rowCount() === 0) {
        error_response('イベントの更新に失敗しました', 500);
    }

    json_response([
        'message' => 'イベントを更新しました',
        'event_id' => $eventId
    ], 200);

} catch (Throwable $e) {
    error_response('イベント更新に失敗しました: ' . $e->getMessage(), 500);
}
