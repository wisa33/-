PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS form_version (
  form_version_id INTEGER PRIMARY KEY,
  name TEXT NOT NULL UNIQUE,
  effective_from TEXT NULL,
  effective_to TEXT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS play (
  play_id INTEGER PRIMARY KEY,
  match_id INTEGER NULL,
  video_id INTEGER NULL,
  possession INTEGER NOT NULL,
  timecode_start_sec REAL NULL,
  timecode_end_sec REAL NULL,
  phase_no INTEGER NULL,
  field_zone_key TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS submission (
  submission_id INTEGER PRIMARY KEY,
  play_id INTEGER NOT NULL,
  author_id INTEGER NOT NULL,
  form_version_id INTEGER NOT NULL,
  status INTEGER NOT NULL DEFAULT 1,
  submitted_at TEXT NULL,
  locked_at TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(play_id, author_id, form_version_id),
  FOREIGN KEY (play_id) REFERENCES play(play_id),
  FOREIGN KEY (form_version_id) REFERENCES form_version(form_version_id)
);

CREATE TABLE IF NOT EXISTS step_definition (
  step_def_id INTEGER PRIMARY KEY,
  form_version_id INTEGER NOT NULL,
  step_key TEXT NOT NULL,
  label TEXT NOT NULL,
  category TEXT NOT NULL,
  description TEXT NULL,
  sort_order INTEGER NOT NULL DEFAULT 100,
  is_active INTEGER NOT NULL DEFAULT 1,
  UNIQUE(form_version_id, step_key),
  FOREIGN KEY (form_version_id) REFERENCES form_version(form_version_id)
);

CREATE TABLE IF NOT EXISTS step_instance (
  step_inst_id INTEGER PRIMARY KEY,
  submission_id INTEGER NOT NULL,
  step_def_id INTEGER NOT NULL,
  seq INTEGER NOT NULL,
  timestamp_sec REAL NULL,
  actor_role TEXT NULL,
  note TEXT NULL,
  UNIQUE(submission_id, seq),
  FOREIGN KEY (submission_id) REFERENCES submission(submission_id) ON DELETE CASCADE,
  FOREIGN KEY (step_def_id) REFERENCES step_definition(step_def_id)
);

CREATE TABLE IF NOT EXISTS choice_definition (
  choice_def_id INTEGER PRIMARY KEY,
  form_version_id INTEGER NOT NULL,
  choice_key TEXT NOT NULL,
  label TEXT NOT NULL,
  description TEXT NULL,
  category TEXT NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 100,
  UNIQUE(form_version_id, choice_key),
  FOREIGN KEY (form_version_id) REFERENCES form_version(form_version_id)
);

CREATE TABLE IF NOT EXISTS alternative_choice (
  alt_choice_id INTEGER PRIMARY KEY,
  step_inst_id INTEGER NOT NULL,
  choice_def_id INTEGER NOT NULL,
  is_selected INTEGER NOT NULL DEFAULT 0,
  confidence TEXT NULL,
  reason TEXT NULL,
  created_by INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(step_inst_id, choice_def_id, created_by),
  FOREIGN KEY (step_inst_id) REFERENCES step_instance(step_inst_id) ON DELETE CASCADE,
  FOREIGN KEY (choice_def_id) REFERENCES choice_definition(choice_def_id)
);

CREATE TABLE IF NOT EXISTS hypothetical_step_instance (
  hypo_step_id INTEGER PRIMARY KEY,
  alt_choice_id INTEGER NOT NULL,
  seq INTEGER NOT NULL,
  step_def_id INTEGER NOT NULL,
  timestamp_sec REAL NULL,
  note TEXT NULL,
  UNIQUE(alt_choice_id, seq),
  FOREIGN KEY (alt_choice_id) REFERENCES alternative_choice(alt_choice_id) ON DELETE CASCADE,
  FOREIGN KEY (step_def_id) REFERENCES step_definition(step_def_id)
);
