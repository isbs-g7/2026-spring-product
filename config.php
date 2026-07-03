<?php
// 環境ごとの設定をここに記述します。
// config.php.example をコピーして作成し、必要に応じて値を編集してください。

defined('DB_DSN')  || define('DB_DSN',  getenv('DB_DSN')  ?: 'pgsql:host=localhost;dbname=event_board;port=5432');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: 'hatakeyamaitsuki');
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: '');

defined('ALLOWED_EMAIL_DOMAIN') || define('ALLOWED_EMAIL_DOMAIN',

    getenv('ALLOWED_EMAIL_DOMAIN') ?: 'stu.musashino-u.ac.jp');

defined('APP_NAME') || define('APP_NAME', '大学イベント掲示板');
