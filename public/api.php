<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/repo.php';

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    json_response(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$action = (string)($body['action'] ?? '');

function get_submission_status(int $submissionId): int
{
    $submission = get_submission($submissionId);
    if (!$submission) {
        throw new RuntimeException('Submission not found');
    }
    return (int)$submission['status'];
}

function assert_editable(int $submissionId): void
{
    if (get_submission_status($submissionId) === 3) {
        throw new RuntimeException('Submission is locked');
    }
}

function step_belongs_to_submission(int $stepInstId, int $submissionId): bool
{
    $st = db()->prepare('SELECT submission_id FROM step_instance WHERE step_inst_id = :id');
    $st->execute([':id' => $stepInstId]);
    $row = $st->fetch();
    return $row && (int)$row['submission_id'] === $submissionId;
}

function option_belongs_to_submission(int $altChoiceId, int $submissionId): bool
{
    $st = db()->prepare('SELECT si.submission_id FROM alternative_choice ac JOIN step_instance si ON si.step_inst_id = ac.step_inst_id WHERE ac.alt_choice_id = :id');
    $st->execute([':id' => $altChoiceId]);
    $row = $st->fetch();
    return $row && (int)$row['submission_id'] === $submissionId;
}

function hypo_belongs_to_submission(int $hypoStepId, int $submissionId): bool
{
    $st = db()->prepare('SELECT si.submission_id FROM hypothetical_step_instance hs JOIN alternative_choice ac ON ac.alt_choice_id = hs.alt_choice_id JOIN step_instance si ON si.step_inst_id = ac.step_inst_id WHERE hs.hypo_step_id = :id');
    $st->execute([':id' => $hypoStepId]);
    $row = $st->fetch();
    return $row && (int)$row['submission_id'] === $submissionId;
}

try {
    switch ($action) {
        case 'add_step': {
            $submissionId = require_int('submission_id', $body);
            $stepDefId = require_int('step_def_id', $body);
            assert_editable($submissionId);
            $id = add_step($submissionId, $stepDefId);
            json_response(['ok' => true, 'step_inst_id' => $id]);
        }

        case 'update_step': {
            $submissionId = require_int('submission_id', $body);
            $stepInstId = require_int('step_inst_id', $body);
            $fields = (array)($body['fields'] ?? []);

            assert_editable($submissionId);
            if (!step_belongs_to_submission($stepInstId, $submissionId)) {
                throw new RuntimeException('step does not belong');
            }

            update_step($stepInstId, $fields);
            json_response(['ok' => true]);
        }

        case 'delete_step': {
            $submissionId = require_int('submission_id', $body);
            $stepInstId = require_int('step_inst_id', $body);

            assert_editable($submissionId);
            if (!step_belongs_to_submission($stepInstId, $submissionId)) {
                throw new RuntimeException('step does not belong');
            }

            delete_step($stepInstId);
            json_response(['ok' => true]);
        }


        case 'reorder_steps': {
            $submissionId = require_int('submission_id', $body);
            $ordered = (array)($body['ordered_step_inst_ids'] ?? []);
            assert_editable($submissionId);

            $normalized = [];
            foreach ($ordered as $id) {
                $iid = (int)$id;
                if ($iid <= 0 || !step_belongs_to_submission($iid, $submissionId)) {
                    throw new RuntimeException('invalid step order payload');
                }
                $normalized[] = $iid;
            }

            reorder_steps($submissionId, $normalized);
            json_response(['ok' => true]);
        }

        case 'add_option': {
            $submissionId = require_int('submission_id', $body);
            $stepInstId = require_int('step_inst_id', $body);
            $choiceDefId = require_int('choice_def_id', $body);

            assert_editable($submissionId);
            if (!step_belongs_to_submission($stepInstId, $submissionId)) {
                throw new RuntimeException('step does not belong');
            }

            $submission = get_submission($submissionId);
            if (!$submission) {
                throw new RuntimeException('Submission not found');
            }

            $altChoiceId = add_option($stepInstId, $choiceDefId, (int)$submission['author_id']);
            json_response(['ok' => true, 'alt_choice_id' => $altChoiceId]);
        }

        case 'update_option': {
            $submissionId = require_int('submission_id', $body);
            $altChoiceId = require_int('alt_choice_id', $body);
            $fields = (array)($body['fields'] ?? []);

            assert_editable($submissionId);
            if (!option_belongs_to_submission($altChoiceId, $submissionId)) {
                throw new RuntimeException('option does not belong');
            }

            update_option($altChoiceId, $fields);
            json_response(['ok' => true]);
        }

        case 'select_option': {
            $submissionId = require_int('submission_id', $body);
            $altChoiceId = require_int('alt_choice_id', $body);

            assert_editable($submissionId);
            if (!option_belongs_to_submission($altChoiceId, $submissionId)) {
                throw new RuntimeException('option does not belong');
            }

            select_option_exclusive($altChoiceId);
            json_response(['ok' => true]);
        }

        case 'unselect_option': {
            $submissionId = require_int('submission_id', $body);
            $altChoiceId = require_int('alt_choice_id', $body);

            assert_editable($submissionId);
            if (!option_belongs_to_submission($altChoiceId, $submissionId)) {
                throw new RuntimeException('option does not belong');
            }

            unselect_option($altChoiceId);
            json_response(['ok' => true]);
        }

        case 'delete_option': {
            $submissionId = require_int('submission_id', $body);
            $altChoiceId = require_int('alt_choice_id', $body);

            assert_editable($submissionId);
            if (!option_belongs_to_submission($altChoiceId, $submissionId)) {
                throw new RuntimeException('option does not belong');
            }

            delete_option($altChoiceId);
            json_response(['ok' => true]);
        }

        case 'add_hypo_step': {
            $submissionId = require_int('submission_id', $body);
            $altChoiceId = require_int('alt_choice_id', $body);
            $stepDefId = require_int('step_def_id', $body);

            assert_editable($submissionId);
            if (!option_belongs_to_submission($altChoiceId, $submissionId)) {
                throw new RuntimeException('option does not belong');
            }

            $hypoId = add_hypo_step($altChoiceId, $stepDefId);
            json_response(['ok' => true, 'hypo_step_id' => $hypoId]);
        }

        case 'update_hypo_step': {
            $submissionId = require_int('submission_id', $body);
            $hypoStepId = require_int('hypo_step_id', $body);
            $fields = (array)($body['fields'] ?? []);

            assert_editable($submissionId);
            if (!hypo_belongs_to_submission($hypoStepId, $submissionId)) {
                throw new RuntimeException('hypo does not belong');
            }

            update_hypo_step($hypoStepId, $fields);
            json_response(['ok' => true]);
        }

        case 'delete_hypo_step': {
            $submissionId = require_int('submission_id', $body);
            $hypoStepId = require_int('hypo_step_id', $body);

            assert_editable($submissionId);
            if (!hypo_belongs_to_submission($hypoStepId, $submissionId)) {
                throw new RuntimeException('hypo does not belong');
            }

            delete_hypo_step($hypoStepId);
            json_response(['ok' => true]);
        }

        case 'set_submission_status': {
            $submissionId = require_int('submission_id', $body);
            $status = require_int('status', $body);

            if (!in_array($status, [1, 2, 3, 4], true)) {
                throw new RuntimeException('Invalid status');
            }

            set_submission_status($submissionId, $status);
            json_response(['ok' => true]);
        }

        default:
            throw new RuntimeException('Unknown action');
    }
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 400);
}
