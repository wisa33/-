DELIMITER $$

DROP TRIGGER IF EXISTS bi_step_instance $$
CREATE TRIGGER bi_step_instance BEFORE INSERT ON step_instance FOR EACH ROW
BEGIN
  DECLARE s_status TINYINT; DECLARE s_form BIGINT; DECLARE d_form BIGINT;
  SELECT status, form_version_id INTO s_status, s_form FROM submission WHERE submission_id=NEW.submission_id;
  IF s_status = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission is locked'; END IF;
  SELECT form_version_id INTO d_form FROM step_definition WHERE step_def_id=NEW.step_def_id;
  IF d_form <> s_form THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='form_version mismatch'; END IF;
END $$

DROP TRIGGER IF EXISTS bu_step_instance $$
CREATE TRIGGER bu_step_instance BEFORE UPDATE ON step_instance FOR EACH ROW
BEGIN
  DECLARE s_status TINYINT; DECLARE s_form BIGINT; DECLARE d_form BIGINT;
  SELECT status, form_version_id INTO s_status, s_form FROM submission WHERE submission_id=NEW.submission_id;
  IF s_status = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission is locked'; END IF;
  SELECT form_version_id INTO d_form FROM step_definition WHERE step_def_id=NEW.step_def_id;
  IF d_form <> s_form THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='form_version mismatch'; END IF;
END $$

DROP TRIGGER IF EXISTS bd_step_instance $$
CREATE TRIGGER bd_step_instance BEFORE DELETE ON step_instance FOR EACH ROW
BEGIN
  DECLARE s_status TINYINT;
  SELECT status INTO s_status FROM submission WHERE submission_id=OLD.submission_id;
  IF s_status = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission is locked'; END IF;
END $$

DROP TRIGGER IF EXISTS bi_alternative_choice $$
CREATE TRIGGER bi_alternative_choice BEFORE INSERT ON alternative_choice FOR EACH ROW
BEGIN
  DECLARE s_status TINYINT; DECLARE s_form BIGINT; DECLARE c_form BIGINT;
  SELECT sub.status, sub.form_version_id INTO s_status, s_form FROM step_instance si JOIN submission sub ON sub.submission_id=si.submission_id WHERE si.step_inst_id=NEW.step_inst_id;
  IF s_status = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission is locked'; END IF;
  SELECT form_version_id INTO c_form FROM choice_definition WHERE choice_def_id=NEW.choice_def_id;
  IF c_form <> s_form THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='form_version mismatch'; END IF;
END $$

DROP TRIGGER IF EXISTS bu_alternative_choice $$
CREATE TRIGGER bu_alternative_choice BEFORE UPDATE ON alternative_choice FOR EACH ROW
BEGIN
  DECLARE s_status TINYINT;
  SELECT sub.status INTO s_status FROM step_instance si JOIN submission sub ON sub.submission_id=si.submission_id WHERE si.step_inst_id=OLD.step_inst_id;
  IF s_status = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission is locked'; END IF;
END $$

DROP TRIGGER IF EXISTS bd_alternative_choice $$
CREATE TRIGGER bd_alternative_choice BEFORE DELETE ON alternative_choice FOR EACH ROW
BEGIN
  DECLARE s_status TINYINT;
  SELECT sub.status INTO s_status FROM step_instance si JOIN submission sub ON sub.submission_id=si.submission_id WHERE si.step_inst_id=OLD.step_inst_id;
  IF s_status = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission is locked'; END IF;
END $$

DROP TRIGGER IF EXISTS bi_hypothetical_step_instance $$
CREATE TRIGGER bi_hypothetical_step_instance BEFORE INSERT ON hypothetical_step_instance FOR EACH ROW
BEGIN
  DECLARE s_status TINYINT; DECLARE s_form BIGINT; DECLARE d_form BIGINT;
  SELECT sub.status, sub.form_version_id INTO s_status, s_form
  FROM alternative_choice ac JOIN step_instance si ON si.step_inst_id=ac.step_inst_id JOIN submission sub ON sub.submission_id=si.submission_id
  WHERE ac.alt_choice_id=NEW.alt_choice_id;
  IF s_status = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission is locked'; END IF;
  SELECT form_version_id INTO d_form FROM step_definition WHERE step_def_id=NEW.step_def_id;
  IF d_form <> s_form THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='form_version mismatch'; END IF;
END $$

DROP TRIGGER IF EXISTS bu_hypothetical_step_instance $$
CREATE TRIGGER bu_hypothetical_step_instance BEFORE UPDATE ON hypothetical_step_instance FOR EACH ROW
BEGIN
  DECLARE s_status TINYINT; DECLARE s_form BIGINT; DECLARE d_form BIGINT;
  SELECT sub.status, sub.form_version_id INTO s_status, s_form
  FROM alternative_choice ac JOIN step_instance si ON si.step_inst_id=ac.step_inst_id JOIN submission sub ON sub.submission_id=si.submission_id
  WHERE ac.alt_choice_id=NEW.alt_choice_id;
  IF s_status = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission is locked'; END IF;
  SELECT form_version_id INTO d_form FROM step_definition WHERE step_def_id=NEW.step_def_id;
  IF d_form <> s_form THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='form_version mismatch'; END IF;
END $$

DROP TRIGGER IF EXISTS bd_hypothetical_step_instance $$
CREATE TRIGGER bd_hypothetical_step_instance BEFORE DELETE ON hypothetical_step_instance FOR EACH ROW
BEGIN
  DECLARE s_status TINYINT;
  SELECT sub.status INTO s_status
  FROM alternative_choice ac JOIN step_instance si ON si.step_inst_id=ac.step_inst_id JOIN submission sub ON sub.submission_id=si.submission_id
  WHERE ac.alt_choice_id=OLD.alt_choice_id;
  IF s_status = 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission is locked'; END IF;
END $$

DELIMITER ;
