# 大学生活イベント掲示板 — 設計書

> このファイルはAIエージェント共通の設計書です。  
> 開発ルールは `AGENTS.md` を参照してください。

---

## 技術スタック

| レイヤー        | 採用技術                                 | 備考                              |
| --------------- | ---------------------------------------- | --------------------------------- |
| フロントエンド  | HTML5 + Tailwind CSS (CDN) + Vanilla JS  | SPAなし。ページ遷移は通常のHTTP   |
| バックエンド    | PHP 8.1+                                 | フォーム処理・API・セッション管理 |
| DB              | PostgreSQL 15                            | ゼミサーバーのPostgreSQL を使用   |
| 認証            | PHPセッション（`session_start()`）       | サードパーティ不使用              |
| バリデーション  | PHP側: 手動チェック / JS側: HTML5 + JS   |                                   |
| APIフォーマット | JSON（`Content-Type: application/json`） | フロントのFetch APIと通信         |

> **Tailwind CSS**: CDNスクリプト1行でOK。ビルド不要。
>
> ```html
> <script src="https://cdn.tailwindcss.com"></script>
> ```

---

## 機能仕様

### 1. ユーザー登録・認証

| ID      | 機能           | 詳細                                                                                                        |
| ------- | -------------- | ----------------------------------------------------------------------------------------------------------- |
| AUTH-01 | 新規登録       | **大学メールアドレスのみ**・パスワード・表示名で登録。`ALLOWED_EMAIL_DOMAIN` ドメインチェック＋重複チェック |
| AUTH-02 | ログイン       | メール+パスワードで認証、セッション発行                                                                     |
| AUTH-03 | ログアウト     | セッション破棄→トップにリダイレクト                                                                         |
| AUTH-04 | マイページ     | 自分の登録情報表示・参加イベント一覧                                                                        |
| AUTH-05 | パスワード変更 | 現在パスワード確認後に更新                                                                                  |

### 2. イベント掲示板

| ID     | 機能           | 詳細                                                           |
| ------ | -------------- | -------------------------------------------------------------- |
| EVT-01 | イベント作成   | タイトル・説明・日時・場所・定員・カテゴリを登録（要ログイン） |
| EVT-02 | イベント一覧   | 公開中イベントをカード形式で表示（20件/ページ）                |
| EVT-03 | イベント詳細   | 全情報・参加者数・参加ボタンを表示                             |
| EVT-04 | イベント編集   | 主催者のみ編集可                                               |
| EVT-05 | イベント削除   | 主催者のみ削除可（論理削除）                                   |
| EVT-06 | フィルタ・検索 | カテゴリ・日付範囲・キーワードで絞り込み                       |
| EVT-07 | 自分のイベント | 主催・参加をタブ切替で表示（マイページ内）                     |

### 3. 参加機能

| ID      | 機能           | 詳細                                   |
| ------- | -------------- | -------------------------------------- |
| PART-01 | 参加登録       | 定員未満の場合のみ参加登録（重複不可） |
| PART-02 | 参加キャンセル | イベント開始前のみキャンセル可         |
| PART-03 | 参加者一覧     | 主催者のみ参加者の表示名一覧を閲覧可   |
| PART-04 | 定員管理       | 定員に達したら参加ボタンをグレーアウト |

---

## DB設計（PostgreSQL）

### ER概念図

```
users ──< events (organizer)
users ──< event_participations >── events
categories ──< events
```

### DDL (`sql/schema.sql`)

```sql
-- UUID生成関数を有効化（PostgreSQL 13未満の場合のみ必要）
-- CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- updated_at 自動更新トリガー関数（全テーブル共用）
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ユーザー
CREATE TABLE users (
  id            UUID         NOT NULL PRIMARY KEY DEFAULT gen_random_uuid(),
  email         VARCHAR(255) NOT NULL UNIQUE,    -- 大学ドメインのみ許可
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(50)  NOT NULL,
  avatar_url    VARCHAR(500)     NULL,
  created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE TRIGGER trg_users_updated_at
  BEFORE UPDATE ON users
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- カテゴリ（マスタ）
CREATE TABLE categories (
  id    SERIAL       PRIMARY KEY,
  name  VARCHAR(50)  NOT NULL UNIQUE,
  color VARCHAR(7)   NOT NULL DEFAULT '#6366f1'
);

INSERT INTO categories (name, color) VALUES
  ('講演・セミナー', '#3b82f6'),
  ('サークル・部活', '#22c55e'),
  ('交流会・飲み会', '#f59e0b'),
  ('スポーツ',       '#ef4444'),
  ('ボランティア',   '#8b5cf6'),
  ('その他',         '#6b7280');

-- イベントステータス型
CREATE TYPE event_status AS ENUM ('draft', 'published', 'cancelled', 'completed');

-- イベント
CREATE TABLE events (
  id               UUID         NOT NULL PRIMARY KEY DEFAULT gen_random_uuid(),
  title            VARCHAR(100) NOT NULL,
  description      TEXT         NOT NULL,
  location         VARCHAR(200) NOT NULL,
  is_online        BOOLEAN      NOT NULL DEFAULT FALSE,
  event_date       TIMESTAMPTZ  NOT NULL,
  end_date         TIMESTAMPTZ      NULL,
  organizer_id     UUID         NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  category_id      INT          NOT NULL REFERENCES categories(id),
  max_participants INT              NULL,    -- NULL = 定員なし
  image_url        VARCHAR(500)     NULL,
  status           event_status NOT NULL DEFAULT 'published',
  deleted_at       TIMESTAMPTZ      NULL,   -- 論理削除
  created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE TRIGGER trg_events_updated_at
  BEFORE UPDATE ON events
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX idx_events_event_date   ON events(event_date)   WHERE deleted_at IS NULL;
CREATE INDEX idx_events_organizer_id ON events(organizer_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_events_status       ON events(status)       WHERE deleted_at IS NULL;
CREATE INDEX idx_events_category_id  ON events(category_id)  WHERE deleted_at IS NULL;

-- 参加ステータス型
CREATE TYPE participation_status AS ENUM ('confirmed', 'cancelled');

-- 参加
CREATE TABLE event_participations (
  id            UUID                  NOT NULL PRIMARY KEY DEFAULT gen_random_uuid(),
  event_id      UUID                  NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  user_id       UUID                  NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
  status        participation_status  NOT NULL DEFAULT 'confirmed',
  registered_at TIMESTAMPTZ           NOT NULL DEFAULT NOW(),
  UNIQUE (event_id, user_id)
);
CREATE INDEX idx_part_user_id  ON event_participations(user_id);
CREATE INDEX idx_part_event_id ON event_participations(event_id);
```

---

## API設計（PHP）

全APIは `api/` ディレクトリ以下に配置。リクエスト/レスポンスは JSON。  
認証が必要なエンドポイントはPHPセッションで `$_SESSION['user_id']` を確認。

### 認証

| メソッド | ファイル                | 説明                       |
| -------- | ----------------------- | -------------------------- |
| POST     | `api/auth/register.php` | 新規登録                   |
| POST     | `api/auth/login.php`    | ログイン（セッション発行） |
| POST     | `api/auth/logout.php`   | ログアウト                 |

### ユーザー

| メソッド | ファイル                 | 認証 | 説明                   |
| -------- | ------------------------ | ---- | ---------------------- |
| GET      | `api/users/me.php`       | ✅   | 自分のプロフィール取得 |
| POST     | `api/users/update.php`   | ✅   | 表示名更新             |
| POST     | `api/users/password.php` | ✅   | パスワード変更         |

### イベント

| メソッド | ファイル                          | 認証      | 説明                                        |
| -------- | --------------------------------- | --------- | ------------------------------------------- |
| GET      | `api/events/index.php`            | ❌        | 一覧（`?page&category_id&keyword&from&to`） |
| POST     | `api/events/create.php`           | ✅        | 新規作成                                    |
| GET      | `api/events/show.php?id=`         | ❌        | 詳細取得                                    |
| POST     | `api/events/update.php`           | ✅ 主催者 | 更新                                        |
| POST     | `api/events/delete.php`           | ✅ 主催者 | 論理削除                                    |
| GET      | `api/events/participants.php?id=` | ✅ 主催者 | 参加者一覧                                  |

### 参加

| メソッド | ファイル                        | 認証 | 説明           |
| -------- | ------------------------------- | ---- | -------------- |
| POST     | `api/participations/join.php`   | ✅   | 参加登録       |
| POST     | `api/participations/cancel.php` | ✅   | 参加キャンセル |

### カテゴリ

| メソッド | ファイル                   | 認証 | 説明         |
| -------- | -------------------------- | ---- | ------------ |
| GET      | `api/categories/index.php` | ❌   | カテゴリ一覧 |

---

## ファイル・ディレクトリ構成

```
/                              # ドキュメントルート
├── .agents/
│   ├── AGENTS.md              # AIエージェント共通ルール（編集禁止）
│   └── DESIGN.md              # 本ファイル
├── api/
│   ├── auth/
│   │   ├── register.php
│   │   ├── login.php
│   │   └── logout.php
│   ├── users/
│   │   ├── me.php
│   │   ├── update.php
│   │   └── password.php
│   ├── events/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── show.php
│   │   ├── update.php
│   │   ├── delete.php
│   │   └── participants.php
│   ├── participations/
│   │   ├── join.php
│   │   └── cancel.php
│   └── categories/
│       └── index.php
├── includes/
│   ├── db.php                 # PDO接続シングルトン（getPdo()）
│   ├── auth_check.php         # セッション認証（requireで読み込み）
│   ├── response.php           # json_response() / error_response()
│   └── validation.php         # 共通バリデーション関数
├── pages/
│   ├── index.html             # イベント一覧
│   ├── login.html             # ログイン
│   ├── register.html          # 新規登録
│   ├── event-detail.html      # イベント詳細（?id=）
│   ├── event-new.html         # イベント作成
│   ├── event-edit.html        # イベント編集（?id=）
│   └── mypage.html            # マイページ
├── js/
│   ├── api.js                 # fetch wrapper（API呼び出し共通化）
│   ├── auth.js                # ログイン状態管理・ヘッダー制御
│   ├── events.js              # イベント一覧・詳細・作成・編集
│   ├── participations.js      # 参加・キャンセル
│   └── mypage.js              # マイページ
├── css/
│   └── custom.css             # Tailwindで対応できないスタイルのみ
├── sql/
│   ├── schema.sql             # DDL一式
│   └── seed.sql               # categoriesシードデータ
└── config.php                 # DB接続情報（.gitignore必須）
```

---

## 共通PHPヘルパー仕様

### `includes/db.php`

```php
<?php
require_once __DIR__ . '/../config.php';
function getPdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
```

### `includes/response.php`

```php
<?php
function json_response(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function error_response(string $message, int $status = 400): never {
    json_response(['error' => $message], $status);
}
```

### `includes/auth_check.php`

```php
<?php
// 認証が必要なAPIファイルの先頭で require する
session_start();
if (empty($_SESSION['user_id'])) {
    error_response('Unauthorized', 401);
}
$currentUserId = $_SESSION['user_id'];
```

---

## ビジネスロジック制約（実装上の必須ルール）

1. **定員チェックはトランザクションで実施**  
   `BEGIN` → `SELECT COUNT(*) FOR UPDATE` → 定員未満なら INSERT → `COMMIT`

2. **論理削除フィルタ**  
   全イベント取得クエリに `WHERE deleted_at IS NULL` を必須付与。

3. **主催者権限チェック**  
   編集・削除・参加者閲覧APIでは `events.organizer_id = $currentUserId` を必ず検証し、不一致なら 403 を返す。

4. **大学メールドメイン制限**  
   登録・ログインAPIで `substr(strrchr($email, '@'), 1) !== ALLOWED_EMAIL_DOMAIN` なら 403 を返す。

5. **パスワードハッシュ**  
   登録: `password_hash($pw, PASSWORD_BCRYPT)` / 認証: `password_verify($pw, $hash)`

6. **イベント開始後のキャンセル禁止**  
   `event_date <= NOW()` の場合は参加キャンセルAPIで 409 を返す。

7. **CSRF対策**  
   `$_SESSION['csrf_token']` をフォーム/リクエストヘッダーに含め、サーバー側で照合する。

8. **SQLインジェクション対策**  
   全DBクエリはPDOプリペアドステートメントを使用。動的なカラム名は使用禁止。

---

## config.php（テンプレート）

```php
<?php
// .gitignore に必ず追加すること
define('DB_DSN',  'pgsql:host=localhost;dbname=event_board;port=5432');
define('DB_USER', 'YOUR_DB_USER');
define('DB_PASS', 'YOUR_DB_PASS');

// 大学メールドメイン制限（例: 'example-u.ac.jp'）
// このドメイン以外のメールアドレスは登録・ログインを拒否する
define('ALLOWED_EMAIL_DOMAIN', 'YOUR_UNIVERSITY_DOMAIN');
```

## 検証方法

1. `psql -U postgres -d event_board -f sql/schema.sql` でテーブル作成確認
2. `pages/register.html` から大学メールで登録→ログイン→セッション確認
3. イベント作成→一覧に表示されるか確認
4. 別アカウントでログインして参加登録→定員到達後のボタン状態を確認
5. DevTools（Network > Fetch/XHR）でAPIレスポンスの JSON を確認
