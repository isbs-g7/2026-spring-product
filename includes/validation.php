<?php

function validate_email_domain(string $email): bool {
    $atPos = strrpos($email, '@');
    if ($atPos === false) {
        return false;
    }
    return substr($email, $atPos + 1) === ALLOWED_EMAIL_DOMAIN;
}

// パスワードポリシーを満たさない場合はエラーメッセージを返す。満たす場合は null。
function validate_password(string $password): ?string {
    if (strlen($password) < 8) {
        return 'パスワードは8文字以上で入力してください';
    }
    return null;
}

function validate_csrf(array $session): void {
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($session['csrf_token']) || !hash_equals($session['csrf_token'], $header)) {
        // response.php が先にロードされていること前提
        error_response('Invalid CSRF token', 403);
    }
}
