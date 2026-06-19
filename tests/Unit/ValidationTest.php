<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // validate_csrf() は error_response() (exit) を呼ぶため、
        // ここでは validate_email_domain / validate_password のみテストする
        if (!defined('ALLOWED_EMAIL_DOMAIN')) {
            define('ALLOWED_EMAIL_DOMAIN', 'test.ac.jp');
        }
        require_once __DIR__ . '/../../includes/validation.php';
    }

    // --- validate_email_domain ---

    public function test_valid_university_email(): void
    {
        $this->assertTrue(validate_email_domain('taro@test.ac.jp'));
    }

    public function test_invalid_domain_is_rejected(): void
    {
        $this->assertFalse(validate_email_domain('taro@gmail.com'));
    }

    public function test_subdomain_is_rejected(): void
    {
        // ドメインの部分一致ではなく完全一致を要求する
        $this->assertFalse(validate_email_domain('taro@sub.test.ac.jp'));
    }

    public function test_missing_at_sign_is_rejected(): void
    {
        $this->assertFalse(validate_email_domain('notanemail'));
    }

    // --- validate_password ---

    public function test_password_8_chars_is_valid(): void
    {
        $this->assertNull(validate_password('12345678'));
    }

    public function test_password_shorter_than_8_is_invalid(): void
    {
        $this->assertNotNull(validate_password('1234567'));
    }

    public function test_empty_password_is_invalid(): void
    {
        $this->assertNotNull(validate_password(''));
    }
}
