INSERT INTO categories (name, color) VALUES
  ('講演・セミナー', '#3b82f6'),
  ('サークル・部活', '#22c55e'),
  ('交流会・飲み会', '#f59e0b'),
  ('スポーツ',       '#ef4444'),
  ('ボランティア',   '#8b5cf6'),
  ('その他',         '#6b7280')
ON CONFLICT (name) DO NOTHING;
