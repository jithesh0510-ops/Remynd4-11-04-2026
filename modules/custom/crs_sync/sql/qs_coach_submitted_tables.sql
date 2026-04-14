-- =============================================================================
-- Legacy qs_* tables for CRS migrations — safe to run against your legacy DB
-- =============================================================================
-- Use when Drupal legacy connection points at the same database as the site
-- (e.g. DDEV `db`). The full legacy_drupal_ready_schema.sql uses
-- `USE legacy_drupal_ready`; importing that file alone does NOT create these
-- tables inside `db`.
--
-- Creates: qs_answer_master, qs_coach_submitted_*, qs_emp_questionnaire_filling_master
-- (coach submissions + employee filling sessions crs_emp_filling_session).
--
-- Example (DDEV):
--   ddev mysql db < modules/custom/crs_sync/sql/qs_coach_submitted_tables.sql
--
-- qs_answer_master: optional for coach answer migrate join; omit behavior if absent.
-- =============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `qs_answer_master` (
  `answer_text_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `answer_text` VARCHAR(512) NULL COMMENT 'Paragraph options field_option_title',
  `answer_text_value` VARCHAR(128) NULL COMMENT 'Paragraph options field_option_value',
  PRIMARY KEY (`answer_text_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_coach_submitted_session` (
  `session_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coach_id` INT UNSIGNED NOT NULL COMMENT 'qs_coach_master.coach_id → crs_sync_legacy_map type=coach',
  `employee_id` INT UNSIGNED NOT NULL COMMENT 'qs_employee_master.id → type=employee',
  `company_id` INT UNSIGNED NOT NULL COMMENT 'qs_company_master.company_id → type=company',
  `questionnaire_id` INT UNSIGNED NOT NULL COMMENT 'qs_questionnaire_master.questionnaire_id',
  `fill_date` VARCHAR(32) NULL COMMENT 'Y-m-d preferred',
  `submitted` INT UNSIGNED NOT NULL COMMENT 'Unix time when coach finished the questionnaire',
  `created` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Optional legacy created time',
  PRIMARY KEY (`session_id`),
  KEY `idx_css_coach` (`coach_id`),
  KEY `idx_css_emp` (`employee_id`),
  KEY `idx_css_company` (`company_id`),
  KEY `idx_css_q` (`questionnaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_coach_submitted_answer` (
  `answer_row_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL COMMENT 'qs_question_master.question_id → crs_sync_content_map type=crs_question',
  `answer_text_id` INT UNSIGNED NULL COMMENT 'Optional; joined to qs_answer_master for answer_text_value',
  `answer_value` VARCHAR(255) NULL COMMENT 'Override when answer is not in qs_answer_master',
  PRIMARY KEY (`answer_row_id`),
  KEY `idx_csa_session` (`session_id`),
  KEY `idx_csa_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_emp_questionnaire_filling_master` (
  `filling_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL COMMENT 'qs_employee_master.id or employee_id',
  `company_id` INT UNSIGNED NOT NULL COMMENT 'qs_company_master.company_id',
  `questionnaire_id` INT UNSIGNED NOT NULL COMMENT 'qs_questionnaire_master.questionnaire_id',
  `coach_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'qs_coach_master.coach_id; NULL/0 → qs_company_coach_details',
  `fill_date` VARCHAR(32) NULL,
  `submitted` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`filling_id`),
  KEY `idx_eqfm_company` (`company_id`),
  KEY `idx_eqfm_emp` (`employee_id`),
  KEY `idx_eqfm_q` (`questionnaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
