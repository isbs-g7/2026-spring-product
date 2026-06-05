-- updated_at 自動更新トリガー関数（全テーブル共用）
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ユーザー
CREATE TABLE IF NOT EXISTS users (
  id            UUID         NOT NULL PRIMARY KEY DEFAULT gen_random_uuid(),
  email         VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(50)  NOT NULL,
  avatar_url    VARCHAR(500)     NULL,
  created_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE OR REPLACE TRIGGER trg_users_updated_at
  BEFORE UPDATE ON users
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- カテゴリ（マスタ）
CREATE TABLE IF NOT EXISTS categories (
  id    SERIAL      PRIMARY KEY,
  name  VARCHAR(50) NOT NULL UNIQUE,
  color VARCHAR(7)  NOT NULL DEFAULT '#6366f1'
);

-- イベントステータス型
DO $$ BEGIN
  CREATE TYPE event_status AS ENUM ('draft', 'published', 'cancelled', 'completed');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- イベント
CREATE TABLE IF NOT EXISTS events (
  id               UUID         NOT NULL PRIMARY KEY DEFAULT gen_random_uuid(),
  title            VARCHAR(100) NOT NULL,
  description      TEXT         NOT NULL,
  location         VARCHAR(200) NOT NULL,
  is_online        BOOLEAN      NOT NULL DEFAULT FALSE,
  event_date       TIMESTAMPTZ  NOT NULL,
  end_date         TIMESTAMPTZ      NULL,
  organizer_id     UUID         NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  category_id      INT          NOT NULL REFERENCES categories(id),
  max_participants INT              NULL,
  image_url        VARCHAR(500)     NULL,
  status           event_status NOT NULL DEFAULT 'published',
  deleted_at       TIMESTAMPTZ      NULL,
  created_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
  updated_at       TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
CREATE OR REPLACE TRIGGER trg_events_updated_at
  BEFORE UPDATE ON events
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX IF NOT EXISTS idx_events_event_date   ON events(event_date)   WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_events_organizer_id ON events(organizer_id) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_events_status       ON events(status)       WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_events_category_id  ON events(category_id)  WHERE deleted_at IS NULL;

-- 参加ステータス型
DO $$ BEGIN
  CREATE TYPE participation_status AS ENUM ('confirmed', 'cancelled');
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- 参加
CREATE TABLE IF NOT EXISTS event_participations (
  id            UUID                 NOT NULL PRIMARY KEY DEFAULT gen_random_uuid(),
  event_id      UUID                 NOT NULL REFERENCES events(id)  ON DELETE CASCADE,
  user_id       UUID                 NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
  status        participation_status NOT NULL DEFAULT 'confirmed',
  registered_at TIMESTAMPTZ          NOT NULL DEFAULT NOW(),
  UNIQUE (event_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_part_user_id  ON event_participations(user_id);
CREATE INDEX IF NOT EXISTS idx_part_event_id ON event_participations(event_id);
