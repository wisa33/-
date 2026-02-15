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
    <div class="canvas-toolbar tiny">Space+ドラッグでパン / Ctrl+ホイールでズーム / 事実をクリックで±操作表示 / 事実ドラッグで順番入替</div>
    <div id="canvas-viewport" class="canvas-viewport">
      <div id="world" class="world">
        <svg id="arrow-overlay"></svg>

        <div class="lane-heads" aria-hidden="true">
          <div class="lane-head if">もしも</div>
          <div class="lane-head fact">事実</div>
          <div class="lane-head reason">判断理由</div>
        </div>

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
                  >
                    <div class="card-summary row between">
                      <strong><?= h($opt['choice_label']) ?></strong>
                      <span class="tiny"><?= $selected ? '採用中' : '候補' ?></span>
                    </div>
                    <div class="card-detail">
                      <div class="row">
                        <button class="btn" data-action="select-option" data-submission-id="<?= $submissionId ?>" data-alt-choice-id="<?= $altChoiceId ?>" <?= $locked ? 'disabled' : '' ?>>採用</button>
                        <button class="btn" data-action="delete-option" data-submission-id="<?= $submissionId ?>" data-alt-choice-id="<?= $altChoiceId ?>" <?= $locked ? 'disabled' : '' ?>>削除</button>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </section>

              <section class="lane-cell lane-fact" data-lane="fact">
                <article class="card fact interactive-card fact-node" id="step-<?= $stepInstId ?>" draggable="true" data-step-inst-id="<?= $stepInstId ?>" data-step-def-id="<?= (int)$step['step_def_id'] ?>">
                  <button class="hotspot hot-up" data-hot-add="up" data-step-inst-id="<?= $stepInstId ?>" <?= $locked ? 'disabled' : '' ?>>＋</button>
                  <button class="hotspot hot-down" data-hot-add="down" data-step-inst-id="<?= $stepInstId ?>" <?= $locked ? 'disabled' : '' ?>>＋</button>
                  <button class="hotspot hot-left" data-hot-add="left" data-step-inst-id="<?= $stepInstId ?>" <?= $locked ? 'disabled' : '' ?>>＋</button>
                  <button class="hotspot hot-right" data-hot-add="right" data-step-inst-id="<?= $stepInstId ?>" <?= $locked ? 'disabled' : '' ?>>＋</button>

                  <div class="card-summary row between">
                    <div class="row"><span class="idbubble"><?= $turn ?></span><strong><?= h($step['step_label']) ?></strong></div>
                    <span class="tiny relation-arrow">事実<?= $turn ?></span>
                  </div>
                  <div class="card-detail">
                    <div class="row between">
                      <span class="tiny mono">step_inst_id: <?= $stepInstId ?></span>
                      <button class="btn" data-action="delete-step" data-submission-id="<?= $submissionId ?>" data-step-inst-id="<?= $stepInstId ?>" <?= $locked ? 'disabled' : '' ?>>削除</button>
                    </div>
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

              <section class="lane-cell lane-reason" data-lane="reason">
                <article class="card reason-card interactive-card" id="reason-<?= $stepInstId ?>" data-reason-target-step="<?= $nextStepInstId ?>">
                  <div class="card-summary row between">
                    <strong>判断理由 <?= $turn ?>-<?= $turn + 1 ?></strong>
                    <span class="tiny relation-arrow">理由<?= $turn ?> → 事実<?= $turn + 1 ?></span>
                  </div>
                  <div class="card-detail">
                    <?php if ($reasonOption): ?>
                      <?php $reasonAltId = (int)$reasonOption['alt_choice_id']; ?>
                      <div class="tiny">基準: <?= h($reasonOption['choice_label']) ?></div>
                      <label class="tiny">理由
                        <textarea class="textarea" data-autosave="1" data-type="opt" data-id="<?= $reasonAltId ?>" data-field="reason" data-submission-id="<?= $submissionId ?>" <?= $locked ? 'disabled' : '' ?>><?= h((string)$reasonOption['reason']) ?></textarea>
                      </label>
                    <?php else: ?>
                      <div class="tiny red">このターンの判断理由元がありません</div>
                    <?php endif; ?>
                  </div>
                </article>
              </section>
            </div>
          <?php endforeach; ?>
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
  defaultChoiceDefId: <?= $defaultChoiceDefId ?>
};
</script>
<script src="assets/app.js"></script>
</body>
</html>
