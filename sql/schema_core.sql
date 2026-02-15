-- MySQL 8+
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS form_version (
  form_version_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL UNIQUE,
  effective_from DATE NULL,
  effective_to DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS play (
  play_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  match_id BIGINT UNSIGNED NULL,
  video_id BIGINT UNSIGNED NULL,
  possession TINYINT UNSIGNED NOT NULL,
  timecode_start_sec DECIMAL(10,2) NULL,
  timecode_end_sec DECIMAL(10,2) NULL,
  phase_no INT NULL,
  field_zone_key VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_play_match (match_id),
  INDEX idx_play_video (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS submission (
  submission_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  play_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NOT NULL,
  form_version_id BIGINT UNSIGNED NOT NULL,
  status TINYINT UNSIGNED NOT NULL DEFAULT 1,
  submitted_at TIMESTAMP NULL,
  locked_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_submission (play_id, author_id, form_version_id),
  CONSTRAINT fk_submission_play FOREIGN KEY (play_id) REFERENCES play(play_id),
  CONSTRAINT fk_submission_form FOREIGN KEY (form_version_id) REFERENCES form_version(form_version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS step_definition (
  step_def_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  form_version_id BIGINT UNSIGNED NOT NULL,
  step_key VARCHAR(80) NOT NULL,
  label VARCHAR(255) NOT NULL,
  category VARCHAR(80) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_step_key (form_version_id, step_key),
  INDEX idx_step_category (form_version_id, category),
  INDEX idx_step_active (form_version_id, is_active),
  CONSTRAINT fk_stepdef_form FOREIGN KEY (form_version_id) REFERENCES form_version(form_version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS step_instance (
  step_inst_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  submission_id BIGINT UNSIGNED NOT NULL,
  step_def_id BIGINT UNSIGNED NOT NULL,
  seq INT NOT NULL,
  timestamp_sec DECIMAL(10,2) NULL,
  actor_role VARCHAR(80) NULL,
  note TEXT NULL,
  UNIQUE KEY uq_step_seq (submission_id, seq),
  INDEX idx_step_submission (submission_id),
  INDEX idx_step_def (step_def_id),
  INDEX idx_step_submission_def (submission_id, step_def_id),
  INDEX idx_step_submission_seq (submission_id, seq),
  CONSTRAINT fk_stepinst_submission FOREIGN KEY (submission_id) REFERENCES submission(submission_id) ON DELETE CASCADE,
  CONSTRAINT fk_stepinst_def FOREIGN KEY (step_def_id) REFERENCES step_definition(step_def_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS choice_definition (
  choice_def_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  form_version_id BIGINT UNSIGNED NOT NULL,
  choice_key VARCHAR(80) NOT NULL,
  label VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  UNIQUE KEY uq_choice_key (form_version_id, choice_key),
  INDEX idx_choice_category (form_version_id, category),
  INDEX idx_choice_active (form_version_id, is_active),
  CONSTRAINT fk_choicedef_form FOREIGN KEY (form_version_id) REFERENCES form_version(form_version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alternative_choice (
  alt_choice_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  step_inst_id BIGINT UNSIGNED NOT NULL,
  choice_def_id BIGINT UNSIGNED NOT NULL,
  is_selected TINYINT(1) NOT NULL DEFAULT 0,
  confidence VARCHAR(120) NULL,
  reason TEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alt_unique (step_inst_id, choice_def_id, created_by),
  INDEX idx_alt_selected (step_inst_id, is_selected),
  CONSTRAINT fk_alt_step FOREIGN KEY (step_inst_id) REFERENCES step_instance(step_inst_id) ON DELETE CASCADE,
  CONSTRAINT fk_alt_choice FOREIGN KEY (choice_def_id) REFERENCES choice_definition(choice_def_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hypothetical_step_instance (
  hypo_step_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  alt_choice_id BIGINT UNSIGNED NOT NULL,
  seq INT NOT NULL,
  step_def_id BIGINT UNSIGNED NOT NULL,
  timestamp_sec DECIMAL(10,2) NULL,
  note TEXT NULL,
  UNIQUE KEY uq_hypo_seq (alt_choice_id, seq),
  CONSTRAINT fk_hypo_alt FOREIGN KEY (alt_choice_id) REFERENCES alternative_choice(alt_choice_id) ON DELETE CASCADE,
  CONSTRAINT fk_hypo_stepdef FOREIGN KEY (step_def_id) REFERENCES step_definition(step_def_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
