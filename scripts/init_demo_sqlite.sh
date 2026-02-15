#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p storage
DB_FILE="storage/demo.sqlite"
rm -f "$DB_FILE"
sqlite3 "$DB_FILE" < sql/schema_core_sqlite.sql
sqlite3 "$DB_FILE" < sql/seed_sqlite.sql

sqlite3 "$DB_FILE" <<'SQL'
INSERT INTO play (match_id, video_id, possession, timecode_start_sec, timecode_end_sec, phase_no, field_zone_key)
VALUES (101, 9001, 1, 122.3, 141.2, 7, 'R22-L');

INSERT INTO submission (play_id, author_id, form_version_id, status)
VALUES (1, 10, 1, 1);

INSERT INTO step_instance (submission_id, step_def_id, seq, timestamp_sec, actor_role, note) VALUES
(1, 1, 1, 123.1, 'FW', 'ラインアウトのセットアップ'),
(1, 3, 2, 127.0, 'FW', 'ラック形成して前進'),
(1, 4, 3, 132.8, 'BK', '外へ展開してゲイン');

INSERT INTO alternative_choice (step_inst_id, choice_def_id, is_selected, confidence, reason, created_by) VALUES
(1, 2, 0, 'medium', 'FWで近場を作ってから展開でも良い', 10),
(1, 1, 1, 'high', 'DFラインが狭く外が空いていた', 10),
(2, 3, 1, 'medium', 'キックで背後を突く選択肢', 10);

INSERT INTO hypothetical_step_instance (alt_choice_id, seq, step_def_id, timestamp_sec, note) VALUES
(2, 1, 4, 133.5, '外へ素早く展開して数的優位を作る'),
(2, 2, 6, 138.2, '最終的にTRYまで到達する想定');
SQL

echo "Initialized $DB_FILE"
