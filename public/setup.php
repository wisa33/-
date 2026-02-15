<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

$tables = [];
$error = null;

try {
    if (db_driver() === 'sqlite') {
        $st = db()->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tables = $st->fetchAll(PDO::FETCH_NUM);
    } else {
        $st = db()->query('SHOW TABLES');
        $tables = $st->fetchAll(PDO::FETCH_NUM);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>セットアップ確認</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container single">
  <header>
    <div class="brand">セットアップ確認</div>
    <a class="btn" href="index.php">戻る</a>
  </header>

  <main class="main single-col">
    <div class="card">
      <h2>1) 環境変数</h2>
      <p>`.env.example` を `.env` にコピーし、DB情報を設定してください。</p>
    </div>

    <div class="card">
      <h2>2) SQL 実行順</h2>
      <ol>
        <li>sql/schema_core.sql</li>
        <li>sql/seed.sql</li>
        <li>（任意）sql/triggers.sql</li>
        <li>（任意）sql/schema_optional.sql</li>
      </ol>
    </div>

    <div class="card">
      <h2>3) 接続テスト</h2>
      <?php if ($error): ?>
        <p class="red">接続失敗: <?= h($error) ?></p>
      <?php else: ?>
        <p class="green">接続OK</p>
        <ul>
          <?php foreach ($tables as $table): ?>
            <li><?= h((string)$table[0]) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="tiny">公開ディレクトリは `public/` を指定してください。</div>
  </main>
</div>
</body>
</html>
