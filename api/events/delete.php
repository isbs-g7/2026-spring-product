<?php
/**
 * api/events/delete.php
 * イベント削除エンドポイント（ソフトデリート）
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

// POSTリクエストのみを許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Method Not Allowed', 405);
}

// CSRF保護
validate_csrf($_SESSION);

// 入力を取得
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['id'])) {
    error_response('イベントIDが指定されていません', 400);
}

$eventId = $input['id'];

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
        error_response('このイベントを削除する権限がありません', 403);
    }

    // ソフトデリート：deleted_atを現在時刻に設定
    $stmt = $pdo->prepare(
        'UPDATE events SET deleted_at = NOW() WHERE id = ? AND organizer_id = ?'
    );
    $stmt->execute([$eventId, $currentUserId]);

    json_response([
        'message' => 'イベントを削除しました',
        'event_id' => $eventId
    ], 200);

} catch (Throwable $e) {
    error_response('イベント削除に失敗しました: ' . $e->getMessage(), 500);
}
