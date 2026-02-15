<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function list_plays(int $limit = 50): array
{
    $st = db()->prepare('SELECT play_id, match_id, video_id, possession, timecode_start_sec, timecode_end_sec, phase_no, field_zone_key, created_at FROM play ORDER BY play_id DESC LIMIT :lim');
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function create_play(array $data): int
{
    $st = db()->prepare('INSERT INTO play (match_id, video_id, possession, timecode_start_sec, timecode_end_sec, phase_no, field_zone_key) VALUES (:match_id, :video_id, :possession, :ts1, :ts2, :phase_no, :zone)');
    $st->execute([
        ':match_id' => ($data['match_id'] ?? '') === '' ? null : (int)$data['match_id'],
        ':video_id' => ($data['video_id'] ?? '') === '' ? null : (int)$data['video_id'],
        ':possession' => (int)($data['possession'] ?? 1),
        ':ts1' => ($data['timecode_start_sec'] ?? '') === '' ? null : (float)$data['timecode_start_sec'],
        ':ts2' => ($data['timecode_end_sec'] ?? '') === '' ? null : (float)$data['timecode_end_sec'],
        ':phase_no' => ($data['phase_no'] ?? '') === '' ? null : (int)$data['phase_no'],
        ':zone' => ($data['field_zone_key'] ?? '') === '' ? null : (string)$data['field_zone_key'],
    ]);
    return (int)db()->lastInsertId();
}

function get_play(int $play_id): ?array
{
    $st = db()->prepare('SELECT * FROM play WHERE play_id = :id');
    $st->execute([':id' => $play_id]);
    return $st->fetch() ?: null;
}

function list_form_versions(): array
{
    return db()->query('SELECT form_version_id, name, is_active FROM form_version ORDER BY form_version_id DESC')->fetchAll();
}

function get_form_version(int $form_version_id): ?array
{
    $st = db()->prepare('SELECT * FROM form_version WHERE form_version_id=:id');
    $st->execute([':id' => $form_version_id]);
    return $st->fetch() ?: null;
}

function list_submissions_for_play(int $play_id): array
{
    $st = db()->prepare('SELECT s.*, f.name AS form_name FROM submission s JOIN form_version f ON f.form_version_id=s.form_version_id WHERE s.play_id=:id ORDER BY s.submission_id DESC');
    $st->execute([':id' => $play_id]);
    return $st->fetchAll();
}

function get_submission(int $submission_id): ?array
{
    $st = db()->prepare('SELECT s.*, f.name AS form_name FROM submission s JOIN form_version f ON f.form_version_id=s.form_version_id WHERE s.submission_id=:id');
    $st->execute([':id' => $submission_id]);
    return $st->fetch() ?: null;
}

function create_submission(int $play_id, int $author_id, int $form_version_id): int
{
    $st = db()->prepare('INSERT INTO submission (play_id, author_id, form_version_id, status) VALUES (:play_id, :author_id, :form_version_id, 1)');
    $st->execute([':play_id' => $play_id, ':author_id' => $author_id, ':form_version_id' => $form_version_id]);
    return (int)db()->lastInsertId();
}

function set_submission_status(int $submission_id, int $status): void
{
    $st = db()->prepare('UPDATE submission SET status=:status, submitted_at=CASE WHEN :status=2 THEN NOW() ELSE submitted_at END, locked_at=CASE WHEN :status=3 THEN NOW() ELSE locked_at END WHERE submission_id=:id');
    $st->execute([':status' => $status, ':id' => $submission_id]);
}

function list_step_definitions(int $form_version_id): array
{
    $st = db()->prepare('SELECT * FROM step_definition WHERE form_version_id=:id AND is_active=1 ORDER BY category, sort_order, step_def_id');
    $st->execute([':id' => $form_version_id]);
    return $st->fetchAll();
}

function list_choice_definitions(int $form_version_id): array
{
    $st = db()->prepare('SELECT * FROM choice_definition WHERE form_version_id=:id AND is_active=1 ORDER BY category, sort_order, choice_def_id');
    $st->execute([':id' => $form_version_id]);
    return $st->fetchAll();
}

function list_steps(int $submission_id): array
{
    $st = db()->prepare('SELECT si.*, sd.label AS step_label, sd.category AS step_category FROM step_instance si JOIN step_definition sd ON sd.step_def_id=si.step_def_id WHERE si.submission_id=:id ORDER BY si.seq ASC');
    $st->execute([':id' => $submission_id]);
    return $st->fetchAll();
}

function add_step(int $submission_id, int $step_def_id): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT COALESCE(MAX(seq),0)+1 AS next_seq FROM step_instance WHERE submission_id=:id');
        $st->execute([':id' => $submission_id]);
        $next = (int)$st->fetch()['next_seq'];

        $in = $pdo->prepare('INSERT INTO step_instance (submission_id, step_def_id, seq) VALUES (:sid,:def,:seq)');
        $in->execute([':sid' => $submission_id, ':def' => $step_def_id, ':seq' => $next]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function update_step(int $step_inst_id, array $fields): void
{
    $allowed = ['step_def_id', 'timestamp_sec', 'actor_role', 'note'];
    $set = [];
    $params = [':id' => $step_inst_id];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $fields)) continue;
        $set[] = "{$k}=:{$k}";
        $params[":{$k}"] = $fields[$k] === '' ? null : $fields[$k];
    }
    if (!$set) return;
    $sql = 'UPDATE step_instance SET ' . implode(', ', $set) . ' WHERE step_inst_id=:id';
    db()->prepare($sql)->execute($params);
}

function delete_step(int $step_inst_id): void
{
    db()->prepare('DELETE FROM step_instance WHERE step_inst_id=:id')->execute([':id' => $step_inst_id]);
}

function reorder_steps(int $submission_id, array $ordered_step_inst_ids): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $u = $pdo->prepare('UPDATE step_instance SET seq=:seq WHERE step_inst_id=:id AND submission_id=:sid');
        $seq = 1;
        foreach ($ordered_step_inst_ids as $id) {
            $u->execute([':seq' => $seq++, ':id' => (int)$id, ':sid' => $submission_id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function list_options_for_submission(int $submission_id): array
{
    $st = db()->prepare('SELECT ac.*, cd.label AS choice_label, cd.category AS choice_category FROM alternative_choice ac JOIN step_instance si ON si.step_inst_id=ac.step_inst_id JOIN choice_definition cd ON cd.choice_def_id=ac.choice_def_id WHERE si.submission_id=:sid ORDER BY ac.step_inst_id ASC, ac.is_selected DESC, ac.created_at ASC');
    $st->execute([':sid' => $submission_id]);
    $rows = $st->fetchAll();
    $group = [];
    foreach ($rows as $r) {
        $group[(int)$r['step_inst_id']][] = $r;
    }
    return $group;
}

function add_option(int $step_inst_id, int $choice_def_id, int $created_by): int
{
    $st = db()->prepare('INSERT INTO alternative_choice (step_inst_id, choice_def_id, is_selected, confidence, reason, created_by) VALUES (:step,:choice,0,NULL,NULL,:by)');
    $st->execute([':step' => $step_inst_id, ':choice' => $choice_def_id, ':by' => $created_by]);
    return (int)db()->lastInsertId();
}

function update_option(int $alt_choice_id, array $fields): void
{
    $allowed = ['is_selected', 'confidence', 'reason'];
    $set = [];
    $params = [':id' => $alt_choice_id];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $fields)) continue;
        $set[] = "{$k}=:{$k}";
        $params[":{$k}"] = $fields[$k] === '' ? null : $fields[$k];
    }
    if (!$set) return;
    db()->prepare('UPDATE alternative_choice SET ' . implode(', ', $set) . ' WHERE alt_choice_id=:id')->execute($params);
}

function select_option_exclusive(int $alt_choice_id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT step_inst_id FROM alternative_choice WHERE alt_choice_id=:id';
        if (db_driver() !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $alt_choice_id]);
        $row = $st->fetch();
        if (!$row) {
            $pdo->rollBack();
            return;
        }
        $stepId = (int)$row['step_inst_id'];
        $pdo->prepare('UPDATE alternative_choice SET is_selected=0 WHERE step_inst_id=:sid')->execute([':sid' => $stepId]);
        $pdo->prepare('UPDATE alternative_choice SET is_selected=1 WHERE alt_choice_id=:id')->execute([':id' => $alt_choice_id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function delete_option(int $alt_choice_id): void
{
    db()->prepare('DELETE FROM alternative_choice WHERE alt_choice_id=:id')->execute([':id' => $alt_choice_id]);
}

function list_hypo_steps(int $alt_choice_id): array
{
    $st = db()->prepare('SELECT hs.*, sd.label AS step_label, sd.category AS step_category FROM hypothetical_step_instance hs JOIN step_definition sd ON sd.step_def_id=hs.step_def_id WHERE hs.alt_choice_id=:id ORDER BY hs.seq ASC');
    $st->execute([':id' => $alt_choice_id]);
    return $st->fetchAll();
}

function add_hypo_step(int $alt_choice_id, int $step_def_id): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT COALESCE(MAX(seq),0)+1 AS next_seq FROM hypothetical_step_instance WHERE alt_choice_id=:id';
        if (db_driver() !== 'sqlite') {
            $sql .= ' FOR UPDATE';
        }
        $st = $pdo->prepare($sql);
        $st->execute([':id' => $alt_choice_id]);
        $next = (int)$st->fetch()['next_seq'];
        $in = $pdo->prepare('INSERT INTO hypothetical_step_instance (alt_choice_id, step_def_id, seq) VALUES (:alt,:def,:seq)');
        $in->execute([':alt' => $alt_choice_id, ':def' => $step_def_id, ':seq' => $next]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function update_hypo_step(int $hypo_step_id, array $fields): void
{
    $allowed = ['step_def_id', 'timestamp_sec', 'note'];
    $set = [];
    $params = [':id' => $hypo_step_id];
    foreach ($allowed as $k) {
        if (!array_key_exists($k, $fields)) continue;
        $set[] = "{$k}=:{$k}";
        $params[":{$k}"] = $fields[$k] === '' ? null : $fields[$k];
    }
    if (!$set) return;
    db()->prepare('UPDATE hypothetical_step_instance SET ' . implode(', ', $set) . ' WHERE hypo_step_id=:id')->execute($params);
}

function delete_hypo_step(int $hypo_step_id): void
{
    db()->prepare('DELETE FROM hypothetical_step_instance WHERE hypo_step_id=:id')->execute([':id' => $hypo_step_id]);
}

function reorder_hypo_steps(int $alt_choice_id, array $ordered_hypo_step_ids): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $u = $pdo->prepare('UPDATE hypothetical_step_instance SET seq=:seq WHERE hypo_step_id=:id AND alt_choice_id=:alt');
        $seq = 1;
        foreach ($ordered_hypo_step_ids as $id) {
            $u->execute([':seq' => $seq++, ':id' => (int)$id, ':alt' => $alt_choice_id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
