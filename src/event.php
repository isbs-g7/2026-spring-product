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
     * イベント編集
     */
    public function update(string $eventId, array $data): bool
    {
        $allowedFields = ['title', 'description', 'location', 'event_date', 'status'];
        $updates = [];
        $values = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $values[] = $value;
            }
        }

        if (empty($updates)) {
            throw new Exception('更新するフィールドがありません');
        }

        $values[] = $eventId;
        $sql = 'UPDATE events SET ' . implode(', ', $updates) 
             . ' WHERE id = ? AND deleted_at IS NULL';
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * イベント削除（ソフトデリート）
     */
    public function delete(string $eventId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE events SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$eventId]);
    }
}