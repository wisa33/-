<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repo.php';

function status_label(int $status): string
{
    return match ($status) {
        1 => 'draft',
        2 => 'submitted',
        3 => 'locked',
        4 => 'exempt',
        default => 'unknown',
    };
}

function pick_reason_option(array $options): ?array
{
    foreach ($options as $opt) {
        if ((int)$opt['is_selected'] === 1) {
            return $opt;
        }
    }

  foreach ($options as $opt) {
    if (trim((string)($opt['reason'] ?? '')) !== '') {
      return $opt;
    }
  }

    return $options[0] ?? null;
}

$submissionId = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
$submission = get_submission($submissionId);
if (!$submission) {
    http_response_code(404);
    echo 'Submission not found';
    exit;
}

$play = get_play((int)$submission['play_id']);
$formVersionId = (int)$submission['form_version_id'];
$locked = (int)$submission['status'] === 3;

$stepDefs = list_step_definitions($formVersionId);
$choiceDefs = list_choice_definitions($formVersionId);
$steps = list_steps($submissionId);
$optionsByStep = list_options_for_submission($submissionId);
$defaultStepDefId = (int)($stepDefs[0]['step_def_id'] ?? 0);
$defaultChoiceDefId = (int)($choiceDefs[0]['choice_def_id'] ?? 0);
$youtubeVideoId = extract_youtube_video_id((string)($play['video_id'] ?? ''));
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>分析キャンバス</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
  <header>
    <div>
      <div class="brand">Play #<?= (int)$play['play_id'] ?> / Submission #<?= (int)$submissionId ?></div>
      <div class="tiny">分析者: <?= (int)$submission['author_id'] ?> / フォーム: <?= h($submission['form_name']) ?></div>
    </div>
    <div class="row">
      <span class="kpi">状態: <strong class="<?= $locked ? 'red' : 'green' ?>"><?= h(status_label((int)$submission['status'])) ?></strong></span>
      <span class="pill">Phase <?= h((string)$play['phase_no']) ?></span>
      <span class="pill">Poss <?= (int)$play['possession'] ?></span>
      <span class="pill">Zone <?= h((string)$play['field_zone_key']) ?></span>
      <a class="btn" href="play.php?play_id=<?= (int)$play['play_id'] ?>">戻る</a>
      <a class="btn" target="_blank" href="export.php?submission_id=<?= (int)$submissionId ?>">エクスポート</a>
    </div>
  </header>

  <main class="main canvas-main">
    <div class="canvas-toolbar tiny row between">
      <span>Space+ドラッグでパン / Ctrl+ホイールでズーム / 事実をクリックで±操作表示</span>
      <span class="row">
        <button id="input-mode-toggle" type="button" class="btn">入力モード</button>
        <span id="yt-state" class="tiny mono">動画: 未接続</span>
        <button id="yt-rw" type="button" class="btn">⏪ 5s</button>
        <button id="yt-play" type="button" class="btn">再生</button>
        <button id="yt-pause" type="button" class="btn">停止</button>
        <button id="yt-ff" type="button" class="btn">5s ⏩</button>
        <button id="yt-slow" type="button" class="btn">0.5x ▶</button>
        <button id="yt-slow-back" type="button" class="btn">0.5x ◀</button>
      </span>
    </div>
    <div id="canvas-viewport" class="canvas-viewport">
      <div id="video-bg" class="video-bg" aria-hidden="true">
        <div id="yt-player"></div>
      </div>

      <div class="lane-heads" aria-hidden="true">
        <div class="lane-head fact">事実</div>
        <div class="lane-head if">もしも</div>
      </div>

      <div id="world" class="world">
        <svg id="arrow-overlay"></svg>

        <div id="turn-stack">
          <?php foreach ($steps as $idx => $step): ?>
            <?php
              $turn = $idx + 1;
              $stepInstId = (int)$step['step_inst_id'];
              $prevStepInstId = $idx > 0 ? (int)$steps[$idx - 1]['step_inst_id'] : 0;
              $nextStepInstId = isset($steps[$idx + 1]) ? (int)$steps[$idx + 1]['step_inst_id'] : 0;
              $opts = $optionsByStep[$stepInstId] ?? [];
              $reasonOption = pick_reason_option($opts);
            ?>
            <div class="turn-row" data-turn="<?= $turn ?>">
              <div class="turn-label tiny mono">n=<?= $turn ?></div>

              <section class="lane-cell lane-if" data-lane="if">
                <?php foreach ($opts as $opt): ?>
                  <?php $altChoiceId = (int)$opt['alt_choice_id']; $selected = (int)$opt['is_selected'] === 1; ?>
                  <article
                    class="card option interactive-card if-node <?= $selected ? 'selected' : '' ?>"
                    id="if-<?= $altChoiceId ?>"
                    data-prev-step="<?= $prevStepInstId ?>"
                    data-if-step="<?= $stepInstId ?>"
                    data-choice-def-id="<?= (int)$opt['choice_def_id'] ?>"
                  >
                    <button class="delete-icon" aria-label="もしもを削除" title="削除" data-action="delete-option" data-submission-id="<?= $submissionId ?>" data-alt-choice-id="<?= $altChoiceId ?>" <?= $locked ? 'disabled' : '' ?>>✕</button>
                    <div class="card-summary row between">
                      <strong><?= h($opt['choice_label']) ?></strong>
                      <span class="tiny"><?= $selected ? '採用中' : '候補' ?></span>
                    </div>
                    <div class="card-detail">
                      <div class="row">
                        <?php if ($selected): ?>
                          <button class="btn" data-action="unselect-option" data-submission-id="<?= $submissionId ?>" data-alt-choice-id="<?= $altChoiceId ?>" <?= $locked ? 'disabled' : '' ?>>採用解除</button>
                        <?php else: ?>
                          <button class="btn" data-action="select-option" data-submission-id="<?= $submissionId ?>" data-alt-choice-id="<?= $altChoiceId ?>" <?= $locked ? 'disabled' : '' ?>>採用</button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </section>

              <section class="lane-cell lane-fact" data-lane="fact">
                <article class="card fact interactive-card fact-node" id="step-<?= $stepInstId ?>" data-step-inst-id="<?= $stepInstId ?>" data-step-def-id="<?= (int)$step['step_def_id'] ?>">
                  <button class="delete-icon" aria-label="事実を削除" title="削除" data-action="delete-step" data-submission-id="<?= $submissionId ?>" data-step-inst-id="<?= $stepInstId ?>" <?= $locked ? 'disabled' : '' ?>>✕</button>
                  <button class="hotspot hot-up" data-hot-add="up" data-step-inst-id="<?= $stepInstId ?>" <?= $locked ? 'disabled' : '' ?>>＋</button>
                  <button class="hotspot hot-down" data-hot-add="down" data-step-inst-id="<?= $stepInstId ?>" <?= $locked ? 'disabled' : '' ?>>＋</button>
                  <button class="hotspot hot-left" data-hot-add="left" data-step-inst-id="<?= $stepInstId ?>" <?= $locked ? 'disabled' : '' ?>>＋</button>
                  <button class="hotspot hot-right" data-hot-add="right" data-step-inst-id="<?= $stepInstId ?>" <?= $locked ? 'disabled' : '' ?>>＋</button>

                  <div class="card-summary row between">
                    <div class="row"><span class="idbubble"><?= $turn ?></span><strong><?= h($step['step_label']) ?></strong></div>
                  </div>
                  <div class="card-detail">
                    <div class="grid2">
                      <label class="tiny">step定義
                        <select class="select" data-autosave="1" data-type="step" data-id="<?= $stepInstId ?>" data-field="step_def_id" data-submission-id="<?= $submissionId ?>" <?= $locked ? 'disabled' : '' ?>>
                          <?php foreach ($stepDefs as $def): ?>
                            <option value="<?= (int)$def['step_def_id'] ?>" <?= (int)$step['step_def_id'] === (int)$def['step_def_id'] ? 'selected' : '' ?>><?= h($def['label']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label class="tiny">timestamp(秒)
                        <input class="input" data-autosave="1" data-type="step" data-id="<?= $stepInstId ?>" data-field="timestamp_sec" data-submission-id="<?= $submissionId ?>" value="<?= h((string)$step['timestamp_sec']) ?>" <?= $locked ? 'disabled' : '' ?>>
                      </label>
                      <label class="tiny">actor_role
                        <input class="input" data-autosave="1" data-type="step" data-id="<?= $stepInstId ?>" data-field="actor_role" data-submission-id="<?= $submissionId ?>" value="<?= h((string)$step['actor_role']) ?>" <?= $locked ? 'disabled' : '' ?>>
                      </label>
                    </div>
                    <label class="tiny">メモ
                      <textarea class="textarea" data-autosave="1" data-type="step" data-id="<?= $stepInstId ?>" data-field="note" data-submission-id="<?= $submissionId ?>" <?= $locked ? 'disabled' : '' ?>><?= h((string)$step['note']) ?></textarea>
                    </label>
                  </div>
                </article>
              </section>
            </div>

            <?php if ($nextStepInstId > 0): ?>
              <div class="reason-bridge-row" data-turn-reason="<?= $turn ?>">
                <?php if ($reasonOption): ?>
                  <?php
                    $reasonAltId = (int)$reasonOption['alt_choice_id'];
                    $reasonText = trim((string)$reasonOption['reason']);
                    $reasonEmpty = $reasonText === '';
                  ?>
                  <article class="card reason-card interactive-card <?= $reasonEmpty ? 'reason-empty' : '' ?>" id="reason-<?= $stepInstId ?>" data-reason-target-step="<?= $nextStepInstId ?>">
                    <?php if (!$reasonEmpty): ?>
                      <button class="delete-icon" aria-label="判断理由を削除" title="削除" data-action="delete-reason" data-submission-id="<?= $submissionId ?>" data-alt-choice-id="<?= $reasonAltId ?>" <?= $locked ? 'disabled' : '' ?>>✕</button>
                    <?php endif; ?>
                    <div class="card-summary row between">
                      <span class="tiny reason-preview"><?= $reasonEmpty ? '理由未入力' : h((string)$reasonOption['reason']) ?></span>
                    </div>
                    <div class="card-detail">
                      <label class="tiny">理由
                        <textarea class="textarea" data-autosave="1" data-type="opt" data-id="<?= $reasonAltId ?>" data-field="reason" data-submission-id="<?= $submissionId ?>" <?= $locked ? 'disabled' : '' ?>><?= h((string)$reasonOption['reason']) ?></textarea>
                      </label>
                    </div>
                  </article>
                <?php else: ?>
                  <article class="card reason-card reason-empty" id="reason-<?= $stepInstId ?>" data-reason-target-step="<?= $nextStepInstId ?>">
                    <div class="tiny red">このターンの判断理由元がありません</div>
                  </article>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>

          <div class="turn-row turn-row-add" data-turn="add">
            <section class="lane-cell lane-if"></section>
            <section class="lane-cell lane-fact lane-fact-add">
              <button class="btn primary" data-action="add-step" data-submission-id="<?= $submissionId ?>" <?= $locked ? 'disabled' : '' ?>>＋ 事実ブロックを追加</button>
            </section>
          </div>
        </div>
      </div>
    </div>
  </main>

  <footer>
    <div class="tiny">更新: <?= h((string)$submission['updated_at']) ?></div>
    <div class="row">
      <select class="select" data-autosave="1" data-type="sub" data-id="<?= $submissionId ?>" data-field="status" data-submission-id="<?= $submissionId ?>" <?= $locked ? 'disabled' : '' ?>>
        <?php foreach ([1 => 'draft', 2 => 'submitted', 3 => 'locked', 4 => 'exempt'] as $key => $label): ?>
          <option value="<?= $key ?>" <?= (int)$submission['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <a class="btn" href="analyzer.php?submission_id=<?= $submissionId ?>">更新</a>
    </div>
  </footer>
</div>

<div id="toast"></div>
<script>
window.__APP__ = {
  submissionId: <?= $submissionId ?>,
  locked: <?= $locked ? 'true' : 'false' ?>,
  defaultStepDefId: <?= $defaultStepDefId ?>,
  defaultChoiceDefId: <?= $defaultChoiceDefId ?>,
  choiceDefIds: <?= json_encode(array_values(array_map(static fn($def) => (int)$def['choice_def_id'], $choiceDefs)), JSON_UNESCAPED_UNICODE) ?>,
  youtubeVideoId: <?= json_encode($youtubeVideoId, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="assets/app.js"></script>
</body>
</html>
