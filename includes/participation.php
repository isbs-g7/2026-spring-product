<?php

/**
 * イベント参加登録。
 * 定員超過・重複参加・開催後の参加は Exception（code=HTTPステータス）をthrowする。
 */
function join_event(PDO $pdo, string $eventId, string $userId): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT max_participants, event_date, status, deleted_at
             FROM events WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if (!$event || $event['deleted_at'] !== null || $event['status'] !== 'published') {
            throw new Exception('イベントが見つかりません', 404);
        }

        if (strtotime($event['event_date']) <= time()) {
            throw new Exception('開催済みのイベントには参加できません', 409);
        }

        $stmt = $pdo->prepare(
            'SELECT id, status FROM event_participations
             WHERE event_id = ? AND user_id = ? FOR UPDATE'
        );
        $stmt->execute([$eventId, $userId]);
        $participation = $stmt->fetch();

        if ($participation && $participation['status'] === 'confirmed') {
            throw new Exception('既に参加登録済みです', 409);
        }

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM event_participations
             WHERE event_id = ? AND status = 'confirmed'"
        );
        $countStmt->execute([$eventId]);
        $confirmedCount = (int) $countStmt->fetchColumn();

        if ($event['max_participants'] !== null && $confirmedCount >= (int) $event['max_participants']) {
            throw new Exception('定員に達しています', 409);
        }

        if ($participation) {
            $pdo->prepare(
                "UPDATE event_participations
                 SET status = 'confirmed', registered_at = NOW()
                 WHERE id = ?"
            )->execute([$participation['id']]);
        } else {
            $pdo->prepare(
                "INSERT INTO event_participations (event_id, user_id, status)
                 VALUES (?, ?, 'confirmed')"
            )->execute([$eventId, $userId]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * 参加キャンセル。開催後のキャンセルは Exception をthrowする。
 */
function cancel_participation(PDO $pdo, string $eventId, string $userId): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT event_date FROM events WHERE id = ? FOR UPDATE');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();

        if (!$event) {
            throw new Exception('イベントが見つかりません', 404);
        }

        $stmt = $pdo->prepare(
            "SELECT id FROM event_participations
             WHERE event_id = ? AND user_id = ? AND status = 'confirmed' FOR UPDATE"
        );
        $stmt->execute([$eventId, $userId]);
        $participation = $stmt->fetch();

        if (!$participation) {
            throw new Exception('参加登録が見つかりません', 404);
        }

        if (strtotime($event['event_date']) <= time()) {
            throw new Exception('開催済みのイベントは参加をキャンセルできません', 409);
        }

        $pdo->prepare(
            "UPDATE event_participations SET status = 'cancelled' WHERE id = ?"
        )->execute([$participation['id']]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * 参加者の表示名一覧を返す。主催者以外が呼ぶと Exception(403) をthrowする。
 * @return string[]
 */
function list_participants(PDO $pdo, string $eventId, string $currentUserId): array
{
    $stmt = $pdo->prepare(
        'SELECT organizer_id FROM events WHERE id = ? AND deleted_at IS NULL'
    );
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        throw new Exception('イベントが見つかりません', 404);
    }

    if ($event['organizer_id'] !== $currentUserId) {
        throw new Exception('主催者のみ閲覧できます', 403);
    }

    $stmt = $pdo->prepare(
        "SELECT u.display_name
         FROM event_participations p
         JOIN users u ON u.id = p.user_id
         WHERE p.event_id = ? AND p.status = 'confirmed'
         ORDER BY p.registered_at ASC"
    );
    $stmt->execute([$eventId]);

    return array_column($stmt->fetchAll(), 'display_name');
}
