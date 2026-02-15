CREATE TABLE IF NOT EXISTS submission_summary (
  submission_id BIGINT UNSIGNED PRIMARY KEY,
  keep_tags_json JSON NULL,
  fix_tags_json JSON NULL,
  next_action TEXT NULL,
  evaluation ENUM('success','issue','failure') NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_summary_submission FOREIGN KEY (submission_id) REFERENCES submission(submission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS moment_feature (
  moment_feature_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  step_inst_id BIGINT UNSIGNED NOT NULL,
  feature_key VARCHAR(120) NOT NULL,
  feature_value TEXT NULL,
  UNIQUE KEY uq_moment_feature (step_inst_id, feature_key),
  CONSTRAINT fk_moment_step FOREIGN KEY (step_inst_id) REFERENCES step_instance(step_inst_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
