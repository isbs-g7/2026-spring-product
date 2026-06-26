<?php
// 認証チェックと共通処理の読み込み（本番稼働）
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/validation.php';

// // --- テスト用ダミー設定（合流後に上の require が有効化されたら自動で無効になります） ---
// if (!function_exists('json_response')) {
//     $currentUserId = '00000000-0000-0000-0000-000000000000'; // 仮のユーザーID
//     function json_response($data, $status = 200) {
//         http_response_code($status);
//         header('Content-Type: application/json; charset=utf-8');
//         echo json_encode($data, JSON_UNESCAPED_UNICODE);
//         exit;
//     }
//     function error_response($msg, $status = 400) {
//         json_response(['error' => $msg], $status);
//     }
//     // データベース接続（getPdo）がない環境用のダミー関数
//     function getPdo() {
//         throw new PDOException("ローカル環境用のダミーエラー（合流後に本番DBへ繋がります）");
//     }
// }

// POSTリクエストのみを許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Method Not Allowed', 405);
}

validate_csrf($_SESSION);

// フロントエンドからのJSON入力を取得
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    error_response('Invalid JSON parameters', 400);
}

// 項目の抽出
$title            = trim($input['title'] ?? '');
$description      = trim($input['description'] ?? '');
$location         = trim($input['location'] ?? '');
$event_date_raw   = trim($input['event_date'] ?? '');
$end_date_raw     = trim($input['end_date'] ?? '');
$category_id      = isset($input['category_id']) ? (int)$input['category_id'] : null;
$max_participants = isset($input['max_participants']) && $input['max_participants'] !== '' ? (int)$input['max_participants'] : null;
$is_online        = !empty($input['is_online']);
$image_url        = trim($input['image_url'] ?? '');

// --- バリデーションチェック ---
if ($title === '' || $description === '' || $location === '' || !$event_date_raw || !$category_id) {
    error_response('必須項目が不足しています。', 400);
}

if (mb_strlen($title) > 100) {
    error_response('タイトルは100文字以内で入力してください。', 400);
}

if (mb_strlen($location) > 200) {
    error_response('場所は200文字以内で入力してください。', 400);
}

$event_date = date_create($event_date_raw);
if (!$event_date) {
    error_response('開催日時の形式が正しくありません。', 400);
}

$end_date = null;
if ($end_date_raw !== '') {
    $end_date = date_create($end_date_raw);
    if (!$end_date) {
        error_response('終了日時の形式が正しくありません。', 400);
    }
    if ($end_date <= $event_date) {
        error_response('終了日時は開催日時より後の時刻を指定してください。', 400);
    }
}

try {
    // 💥 本番用のデータベース保存ロジック（チーム合流後に動きます）
    $pdo = getPdo();

    // カテゴリ整合性チェック
    $catStmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
    $catStmt->execute([$category_id]);
    if (!$catStmt->fetch()) {
        error_response('指定されたカテゴリは存在しません。', 400);
    }

    // SQLインジェクション対策を施したインサート処理（クォーテーションのバグ修正済み）
    $sql = "INSERT INTO events (
                title, description, location, is_online, event_date, end_date, 
                organizer_id, category_id, max_participants, image_url, status
            ) VALUES (
                :title, :description, :location, :is_online, :event_date, :end_date, 
                :organizer_id, :category_id, :max_participants, :image_url, 'published'
            ) RETURNING id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title'            => $title,
        ':description'      => $description,
        ':location'         => $location,
        ':is_online'        => $is_online ? 1 : 0,
        ':event_date'       => $event_date->format('Y-m-d H:i:sO'),
        ':end_date'         => $end_date?->format('Y-m-d H:i:sO'),
        ':organizer_id'     => $currentUserId,
        ':category_id'      => $category_id,
        ':max_participants' => $max_participants,
        ':image_url'        => $image_url !== '' ? $image_url : null,
    ]);

    $result = $stmt->fetch();
    
    json_response([
        'message'  => 'Event created successfully',
        'event_id' => $result['id']
    ], 201);

} catch (PDOException $e) {
    // 💡 データベースや共通処理が未完成のうちは、エラーをキャッチして「成功したフリ」をして画面遷移テストを阻害しないようにします
    if (str_contains($e->getMessage(), 'ダミーエラー')) {
        json_response([
            'message'  => 'Event created successfully (Dummy Mode)',
            'event_id' => 'dummy-event-id'
        ], 201);
    }
    error_response('Database error occurred: ' . $e->getMessage(), 500);
}