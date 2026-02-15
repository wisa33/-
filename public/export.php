<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repo.php';

$submissionId = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
$submission = get_submission($submissionId);
if (!$submission) {
    http_response_code(404);
    echo 'Submission not found';
    exit;
}

$play = get_play((int)$submission['play_id']);
$steps = list_steps($submissionId);
$optionsByStep = list_options_for_submission($submissionId);

$selectedOptions = [];
foreach ($optionsByStep as $opts) {
    foreach ($opts as $opt) {
        if ((int)$opt['is_selected'] === 1) {
            $selectedOptions[] = $opt;
        }
    }
}
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>分析レポート #<?= (int)$submissionId ?></title>
<style>
body { font-family: Arial, sans-serif; color: #111; background: #fff; }
table { width: 100%; border-collapse: collapse; margin: 8px 0 14px; }
th, td { border: 1px solid #999; padding: 4px 6px; font-size: 12px; }
h1, h2, h3 { margin: .4em 0; }
a { color: #333; }
@media print {
  a { display: none; }
  @page { margin: 10mm; }
}
</style>
</head>
<body>
<h1>ラグビー分析レポート</h1>
<p>Play #<?= (int)$play['play_id'] ?> / Submission #<?= (int)$submissionId ?> / 分析者 <?= (int)$submission['author_id'] ?> / フォーム <?= h($submission['form_name']) ?> / status <?= (int)$submission['status'] ?></p>

<h2>Facts（事実）</h2>
<table>
  <thead><tr><th>seq</th><th>step</th><th>category</th><th>timestamp</th><th>actor</th><th>note</th></tr></thead>
  <tbody>
    <?php foreach ($steps as $s): ?>
      <tr>
        <td><?= (int)$s['seq'] ?></td>
        <td><?= h($s['step_label']) ?></td>
        <td><?= h($s['step_category']) ?></td>
        <td><?= h((string)$s['timestamp_sec']) ?></td>
        <td><?= h((string)$s['actor_role']) ?></td>
        <td><?= nl2br(h((string)$s['note'])) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Options（選択肢）</h2>
<?php foreach ($steps as $s): ?>
  <?php $opts = $optionsByStep[(int)$s['step_inst_id']] ?? []; ?>
  <h3>Step <?= (int)$s['seq'] ?>: <?= h($s['step_label']) ?></h3>
  <table>
    <thead><tr><th>choice</th><th>selected</th><th>confidence</th><th>reason</th></tr></thead>
    <tbody>
      <?php foreach ($opts as $o): ?>
      <tr>
        <td><?= h($o['choice_label']) ?></td>
        <td><?= (int)$o['is_selected'] === 1 ? 'yes' : 'no' ?></td>
        <td><?= h((string)$o['confidence']) ?></td>
        <td><?= nl2br(h((string)$o['reason'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endforeach; ?>

<h2>Hypothetical（もしも）</h2>
<?php if ($selectedOptions === []): ?>
  <p>Option を「採用」にすると、If ルートが整理しやすくなります。</p>
<?php endif; ?>

<?php foreach ($selectedOptions as $opt): ?>
  <?php $hypoRows = list_hypo_steps((int)$opt['alt_choice_id']); ?>
  <h3>Option #<?= (int)$opt['alt_choice_id'] ?>（<?= h($opt['choice_label']) ?>）からの分岐</h3>
  <table>
    <thead><tr><th>seq</th><th>step</th><th>category</th><th>timestamp</th><th>note</th></tr></thead>
    <tbody>
      <?php foreach ($hypoRows as $h): ?>
      <tr>
        <td><?= (int)$h['seq'] ?></td>
        <td><?= h($h['step_label']) ?></td>
        <td><?= h($h['step_category']) ?></td>
        <td><?= h((string)$h['timestamp_sec']) ?></td>
        <td><?= nl2br(h((string)$h['note'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endforeach; ?>
</body>
</html>
