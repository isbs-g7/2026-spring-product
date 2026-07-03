<?php
declare(strict_types=1);

namespace App;

use PDO;
use Exception;

class Event
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * イベント詳細取得
     */
    public function getById(string $eventId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM events WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$eventId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * イベント編集（主催者のみ）
     *
     * @throws Exception 更新フィールドが空のとき
     */
    public function update(string $eventId, string $currentUserId, array $data): bool
    {
        $allowedFields = ['title', 'description', 'location', 'event_date', 'status'];
        $updates = [];
        $values = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($updates)) {
            throw new Exception('更新するフィールドがありません');
        }

        $values[] = $eventId;
        $values[] = $currentUserId;
        $sql = 'UPDATE events SET ' . implode(', ', $updates)
             . ' WHERE id = ? AND organizer_id = ? AND deleted_at IS NULL';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    /**
     * イベント削除（ソフトデリート、主催者のみ）
     */
    public function delete(string $eventId, string $currentUserId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE events SET deleted_at = NOW() WHERE id = ? AND organizer_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$eventId, $currentUserId]);
        return $stmt->rowCount() > 0;
    }
}