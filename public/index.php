<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repo.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_play') {
    try {
        $playId = create_play($_POST);
        header('Location: play.php?play_id=' . $playId);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$plays = [];
try {
    $plays = list_plays(100);
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ラグビー分析ノート</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container single">
  <header>
    <div class="brand">🏉 ラグビー分析ノート</div>
    <div class="tiny">Play 一覧 / 作成</div>
    <div class="row">
      <span class="tiny mono">DB: <?= h(getenv('DB_NAME') ?: '(未設定)') ?></span>
      <a class="btn" href="setup.php">セットアップ確認</a>
    </div>
  </header>

  <main class="main single-col">
    <?php if ($error): ?>
      <div class="card error">エラー: <?= h($error) ?><br><span class="tiny">`schema_core.sql` と `seed.sql` の投入を確認してください。</span></div>
    <?php endif; ?>

    <div class="card">
      <h2>新しい Play を作成</h2>
      <form method="post" class="grid2">
        <input type="hidden" name="action" value="create_play">
        <label>試合ID (match_id)<input class="input" name="match_id"></label>
        <label>動画ID (video_id)<input class="input" name="video_id"></label>
        <label>ボール保持
          <select class="select" name="possession">
            <option value="1">1: 自チーム</option>
            <option value="2">2: 相手チーム</option>
          </select>
        </label>
        <label>開始秒 (timecode_start_sec)<input class="input" name="timecode_start_sec"></label>
        <label>終了秒 (timecode_end_sec)<input class="input" name="timecode_end_sec"></label>
        <label>フェーズ番号 (phase_no)<input class="input" name="phase_no"></label>
        <label>ゾーンキー (field_zone_key)<input class="input" name="field_zone_key"></label>
        <button class="btn primary" type="submit">Playを作成</button>
      </form>
    </div>

    <div class="card">
      <h2>既存の Play</h2>
      <?php foreach ($plays as $p): ?>
        <div class="card compact">
          <div class="row between">
            <strong>Play #<?= (int)$p['play_id'] ?></strong>
            <span class="tiny"><?= h((string)$p['created_at']) ?></span>
          </div>
          <div class="tiny">保持: <?= (int)$p['possession'] ?> / フェーズ: <?= h((string)$p['phase_no']) ?> / ゾーン: <?= h((string)$p['field_zone_key']) ?></div>
          <div class="tiny">時間: <?= h((string)$p['timecode_start_sec']) ?> 〜 <?= h((string)$p['timecode_end_sec']) ?></div>
          <a class="btn" href="play.php?play_id=<?= (int)$p['play_id'] ?>">このPlayを開く</a>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>
</body>
</html>
