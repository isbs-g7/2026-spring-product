<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

class ParticipationTest extends TestCase
{
    private static PDO $pdo;
    private string $organizerId;
    private string $participantId;
    private string $categoryId;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = new PDO(
            (string) getenv('DB_DSN'),
            (string) getenv('DB_USER'),
            (string) getenv('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        require_once __DIR__ . '/../../includes/participation.php';
    }

    protected function setUp(): void
    {
        $this->categoryId   = (string) self::$pdo->query('SELECT id FROM categories LIMIT 1')->fetchColumn();
        $this->organizerId  = $this->createUser('organizer');
        $this->participantId = $this->createUser('participant');
    }

    protected function tearDown(): void
    {
        self::$pdo->exec("DELETE FROM users WHERE email LIKE 'participation-test-%'");
    }

    private function createUser(string $label): string
    {
        $email = 'participation-test-' . $label . '-' . uniqid() . '@test.ac.jp';
        $stmt = self::$pdo->prepare(
            "INSERT INTO users (email, password_hash, display_name)
             VALUES (?, 'dummy-hash', ?) RETURNING id"
        );
        $stmt->execute([$email, $label]);
        return (string) $stmt->fetchColumn();
    }

    private function createEvent(?int $maxParticipants, string $eventDateOffset = '+1 day'): string
    {
        $stmt = self::$pdo->prepare(
            "INSERT INTO events (title, description, location, event_date, organizer_id, category_id, max_participants)
             VALUES ('テストイベント', '説明', '場所', ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([
            date('Y-m-d H:i:sO', strtotime($eventDateOffset)),
            $this->organizerId,
            $this->categoryId,
            $maxParticipants,
        ]);
        return (string) $stmt->fetchColumn();
    }

    public function test_join_succeeds_when_capacity_available(): void
    {
        $eventId = $this->createEvent(null);
        join_event(self::$pdo, $eventId, $this->participantId);

        $stmt = self::$pdo->prepare(
            "SELECT COUNT(*) FROM event_participations WHERE event_id = ? AND status = 'confirmed'"
        );
        $stmt->execute([$eventId]);
        $this->assertEquals(1, $stmt->fetchColumn());
    }

    public function test_join_fails_when_event_is_full(): void
    {
        $eventId = $this->createEvent(1);
        $otherUserId = $this->createUser('other');
        join_event(self::$pdo, $eventId, $otherUserId);

        $this->expectExceptionMessage('定員に達しています');
        join_event(self::$pdo, $eventId, $this->participantId);
    }

    public function test_join_fails_on_duplicate_participation(): void
    {
        $eventId = $this->createEvent(null);
        join_event(self::$pdo, $eventId, $this->participantId);

        $this->expectExceptionMessage('既に参加登録済みです');
        join_event(self::$pdo, $eventId, $this->participantId);
    }

    public function test_join_fails_for_past_event(): void
    {
        $eventId = $this->createEvent(null, '-1 day');

        $this->expectExceptionMessage('開催済みのイベントには参加できません');
        join_event(self::$pdo, $eventId, $this->participantId);
    }

    public function test_rejoin_after_cancel_succeeds(): void
    {
        $eventId = $this->createEvent(null);
        join_event(self::$pdo, $eventId, $this->participantId);
        cancel_participation(self::$pdo, $eventId, $this->participantId);
        join_event(self::$pdo, $eventId, $this->participantId);

        $stmt = self::$pdo->prepare(
            'SELECT status FROM event_participations WHERE event_id = ? AND user_id = ?'
        );
        $stmt->execute([$eventId, $this->participantId]);
        $this->assertEquals('confirmed', $stmt->fetchColumn());
    }

    public function test_cancel_fails_for_past_event(): void
    {
        $eventId = $this->createEvent(null, '+1 day');
        join_event(self::$pdo, $eventId, $this->participantId);

        self::$pdo->prepare('UPDATE events SET event_date = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:sO', strtotime('-1 day')), $eventId]);

        $this->expectExceptionMessage('開催済みのイベントは参加をキャンセルできません');
        cancel_participation(self::$pdo, $eventId, $this->participantId);
    }

    public function test_cancel_fails_for_soft_deleted_event(): void
    {
        $eventId = $this->createEvent(null);
        join_event(self::$pdo, $eventId, $this->participantId);

        self::$pdo->prepare('UPDATE events SET deleted_at = NOW() WHERE id = ?')
            ->execute([$eventId]);

        $this->expectExceptionMessage('イベントが見つかりません');
        cancel_participation(self::$pdo, $eventId, $this->participantId);
    }

    public function test_list_participants_forbidden_for_non_organizer(): void
    {
        $eventId = $this->createEvent(null);
        join_event(self::$pdo, $eventId, $this->participantId);

        $this->expectExceptionMessage('主催者のみ閲覧できます');
        list_participants(self::$pdo, $eventId, $this->participantId);
    }

    public function test_list_participants_returns_display_names_for_organizer(): void
    {
        $eventId = $this->createEvent(null);
        join_event(self::$pdo, $eventId, $this->participantId);

        $names = list_participants(self::$pdo, $eventId, $this->organizerId);
        $this->assertEquals(['participant'], $names);
    }
}
