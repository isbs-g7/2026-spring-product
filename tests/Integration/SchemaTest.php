<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = new PDO(
            (string) getenv('DB_DSN'),
            (string) getenv('DB_USER'),
            (string) getenv('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    /** @dataProvider tableProvider */
    public function test_table_exists(string $table): void
    {
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = 'public' AND table_name = '$table'"
        );
        $this->assertEquals(1, $stmt->fetchColumn(), "テーブル '$table' が存在しない");
    }

    public static function tableProvider(): array
    {
        return [
            ['users'],
            ['categories'],
            ['events'],
            ['event_participations'],
        ];
    }

    public function test_categories_seeded(): void
    {
        $stmt = self::$pdo->query('SELECT COUNT(*) FROM categories');
        $this->assertGreaterThan(0, $stmt->fetchColumn(), 'categoriesにシードデータがない');
    }
}
