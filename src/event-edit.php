<?php
// 必要なファイルの読み込みと初期化
require_once __DIR__ . '/event.php';
use App\Event;

// PDOインスタンス生成（既存の接続方法に合わせてください）
$pdo = new PDO('mysql:host=localhost;dbname=your_db;charset=utf8', 'user', 'pass');
$eventObj = new Event($pdo);

// 認証済みユーザーID取得（例: セッションから）
$currentUserId = $_SESSION['user_id'] ?? '';

// イベントID取得
$eventId = $_GET['id'] ?? '';
$message = '';

// 編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    try {
        $data = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'location' => $_POST['location'] ?? '',
            'event_date' => $_POST['event_date'] ?? '',
            'status' => $_POST['status'] ?? '',
        ];
        $eventObj->update($eventId, $currentUserId, $data);
        $message = 'イベントを更新しました。';
    } catch (Exception $e) {
        $message = '更新エラー: ' . $e->getMessage();
    }
}

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    if ($eventObj->delete($eventId, $currentUserId)) {
        $message = 'イベントを削除しました。';
    } else {
        $message = '削除できませんでした。';
    }
}

// イベント詳細取得
$event = $eventObj->getById($eventId);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>イベント編集</title>
</head>
<body>
<h1>イベント編集</h1>
<?php if ($message): ?>
    <p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>
<?php if ($event): ?>
    <form method="post">
        <label>タイトル: <input type="text" name="title" value="<?= htmlspecialchars($event['title']) ?>"></label><br>
        <label>説明: <textarea name="description"><?= htmlspecialchars($event['description']) ?></textarea></label><br>
        <label>場所: <input type="text" name="location" value="<?= htmlspecialchars($event['location']) ?>"></label><br>
        <label>日付: <input type="date" name="event_date" value="<?= htmlspecialchars($event['event_date']) ?>"></label><br>
        <label>ステータス: <input type="text" name="status" value="<?= htmlspecialchars($event['status']) ?>"></label><br>
        <button type="submit" name="update">更新</button>
        <button type="submit" name="delete" onclick="return confirm('本当に削除しますか？');">削除</button>
    </form>
    <h2>イベント詳細</h2>
    <ul>
        <li>ID: <?= htmlspecialchars($event['id']) ?></li>
        <li>主催者ID: <?= htmlspecialchars($event['organizer_id']) ?></li>
        <li>作成日: <?= htmlspecialchars($event['created_at']) ?></li>
    </ul>
<?php else: ?>
    <p>イベントが見つかりません。</p>
<?php endif; ?>
</body>
</html>
