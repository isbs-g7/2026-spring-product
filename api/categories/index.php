<?php
// 本来はデータベースから取得しますが、テスト用に設計書通りのデータをそのまま返します
header('Content-Type: application/json; charset=utf-8');

$categories = [
    ['id' => 1, 'name' => '講演・セミナー', 'color' => '#3b82f6'],
    ['id' => 2, 'name' => 'サークル・部活', 'color' => '#22c55e'],
    ['id' => 3, 'name' => '交流会・飲み会', 'color' => '#f59e0b'],
    ['id' => 4, 'name' => 'スポーツ',       'color' => '#ef4444'],
    ['id' => 5, 'name' => 'ボランティア',   'color' => '#8b5cf6'],
    ['id' => 6, 'name' => 'その他',         'color' => '#6b7280']
];

echo json_encode($categories, JSON_UNESCAPED_UNICODE);