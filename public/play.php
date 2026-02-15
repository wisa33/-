<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repo.php';

$playId = isset($_GET['play_id']) ? (int)$_GET['play_id'] : 0;
$play = get_play($playId);

if (!$play) {
    http_response_code(404);
    echo 'Play not found';
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_submission') {
    try {
        $authorId = (int)($_POST['author_id'] ?? 0);
        $formVersionId = (int)($_POST['form_version_id'] ?? 0);

        if ($authorId <= 0 || $formVersionId <= 0) {
            throw new InvalidArgumentException('author_id と form_version_id は必須です。');
        }

        $submissionId = create_submission($playId, $authorId, $formVersionId);
        header('Location: analyzer.php?submission_id=' . $submissionId);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$formVersions = list_form_versions();
$submissions = list_submissions_for_play($playId);
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Play #<?= (int)$playId ?> | Submission</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container single">
  <header>
    <div class="brand">Play #<?= (int)$playId ?> の分析セッション</div>
    <a class="btn" href="index.php">← Play一覧へ戻る</a>
  </header>

  <main class="main two-col">
    <section class="card">
      <h2>新規 Submission 作成</h2>
      <p class="tiny">誰が・どのフォーム定義で分析するかを選びます。</p>

      <?php if ($error): ?>
        <div class="card error tiny"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="action" value="create_submission">

        <label>分析者ID (author_id)
          <input class="input" name="author_id" required>
        </label>

        <label>フォームバージョン
          <select class="select" name="form_version_id" required>
            <option value="">-- 選択してください --</option>
            <?php foreach ($formVersions as $fv): ?>
              <option value="<?= (int)$fv['form_version_id'] ?>">
                <?= h($fv['name']) ?><?= (int)$fv['is_active'] ? '' : '（非アクティブ）' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <?php if (!$formVersions): ?>
          <p class="red tiny">フォーム定義がありません。`seed.sql` を投入してください。</p>
        <?php endif; ?>

        <button class="btn primary" type="submit">Submissionを作成して分析開始</button>
      </form>
    </section>

    <section class="card">
      <h2>既存 Submission</h2>
      <?php foreach ($submissions as $s): ?>
        <div class="card compact">
          <div class="row between">
            <strong>Submission #<?= (int)$s['submission_id'] ?></strong>
            <span class="tiny">status: <?= (int)$s['status'] ?></span>
          </div>
          <div class="tiny">分析者: <?= (int)$s['author_id'] ?> / フォーム: <?= h($s['form_name']) ?></div>
          <div class="tiny">作成: <?= h((string)$s['created_at']) ?></div>
          <a class="btn" href="analyzer.php?submission_id=<?= (int)$s['submission_id'] ?>">開く</a>
        </div>
      <?php endforeach; ?>

      <p class="tiny">status の意味: 1=draft / 2=submitted / 3=locked / 4=exempt</p>
    </section>
  </main>
</div>
</body>
</html>
