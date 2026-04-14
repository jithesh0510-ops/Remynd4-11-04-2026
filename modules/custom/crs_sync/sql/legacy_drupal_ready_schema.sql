-- =============================================================================
-- legacy_drupal_ready — canonical MySQL schema aligned with crs_sync
-- =============================================================================
-- Purpose: A clean “staging” database whose table and column names match what
-- Drupal\custom\crs_sync\Sync\SyncManager reads, so imports are predictable.
--
-- Usage (example):
--   mysql -h 127.0.0.1 -P 3306 -u db -p < legacy_drupal_ready_schema.sql
--
-- DDEV single-DB setup: this script does CREATE DATABASE + USE legacy_drupal_ready,
-- so tables land in `legacy_drupal_ready`, not in `db`. Either set
-- CRS_LEGACY_DATABASE=legacy_drupal_ready, or create only the coach submission
-- tables inside `db` with:
--   ddev mysql db < modules/custom/crs_sync/sql/qs_coach_submitted_tables.sql
--
-- Point Drupal at this DB as the legacy connection (keep Drupal default on its
-- own database):
--   $databases['legacy']['default'] = [
--     'driver' => 'mysql',
--     'database' => 'legacy_drupal_ready',
--     'username' => '…',
--     'password' => '…',
--     'host' => '127.0.0.1',
--     'port' => '3306',
--     'prefix' => '',
--     'collation' => 'utf8mb4_unicode_ci',
--   ];
--
-- Recommended crs_sync order (matches /admin/tools/crs-sync):
--   1) qs_company_master
--   2) qs_coach_master + qs_company_coach_details
--   3) qs_employee_master (+ branch / job lookup tables)
--   4) qs_questionnaire_master (+ category / question / answer tables)
--   5) qs_company_questionnaire_details (+ qs_company_jobprofilerelation)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `legacy_drupal_ready`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `legacy_drupal_ready`;

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Companies → Drupal user (role company) + profile bundle company
-- SyncManager::syncCompanies()
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qs_company_master` (
  `company_id` INT UNSIGNED NOT NULL COMMENT 'Legacy PK → crs_sync_legacy_map legacy_id (type=company)',
  `id` INT UNSIGNED NOT NULL COMMENT 'Alias PK used by some PHP builds; keep equal to company_id',
  `email` VARCHAR(255) NULL COMMENT 'Drupal user mail',
  `company_name` VARCHAR(255) NOT NULL COMMENT 'Display name; profile field_company_name',
  `company_code` VARCHAR(128) NULL COMMENT 'Profile field_company_id',
  `no_of_coach` INT UNSIGNED NULL DEFAULT 0 COMMENT 'Profile field_no_of_coach',
  `no_of_employees` INT UNSIGNED NULL DEFAULT 0 COMMENT 'Profile field_no_of_employees',
  `no_of_password_generated` INT UNSIGNED NULL DEFAULT 0 COMMENT 'Profile field_no_of_password_generated',
  `first_name` VARCHAR(128) NULL COMMENT 'User field_first_name',
  `middle_name` VARCHAR(128) NULL COMMENT 'User field_middle_name',
  `last_name` VARCHAR(128) NULL COMMENT 'User field_last_name',
  `full_name` VARCHAR(255) NULL COMMENT 'User field_full_name',
  `phone` VARCHAR(64) NULL COMMENT 'User field_phone_no (also phone_no, mobile, …)',
  `website` VARCHAR(512) NULL COMMENT 'User field_website',
  `country` VARCHAR(128) NULL,
  `state` VARCHAR(128) NULL,
  `city` VARCHAR(128) NULL,
  `postal` VARCHAR(32) NULL,
  `address1` VARCHAR(255) NULL,
  `address2` VARCHAR(255) NULL,
  `is_delete` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'User field_is_delete when present',
  PRIMARY KEY (`company_id`),
  UNIQUE KEY `uk_company_legacy_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Drupal crs_sync: one row per company user';

-- -----------------------------------------------------------------------------
-- Coaches → user role coach + profile coach
-- SyncManager::syncCoaches() joins qs_company_coach_details for company links
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qs_coach_master` (
  `coach_id` INT UNSIGNED NOT NULL COMMENT 'Legacy PK → crs_sync_legacy_map (type=coach)',
  `id` INT UNSIGNED NOT NULL COMMENT 'Keep equal to coach_id if old code used id',
  `email` VARCHAR(255) NULL COMMENT 'Drupal user mail',
  `first_name` VARCHAR(128) NULL,
  `full_name` VARCHAR(255) NULL COMMENT 'Fallback display name',
  `see_actionreport_result` TINYINT(1) NULL DEFAULT 0 COMMENT 'Profile field_enable_the_coach_will_see',
  `lagard_to_stars` TINYINT(1) NULL DEFAULT 0 COMMENT 'Profile field_see_laggards_to_stars',
  `see_previous_date` TINYINT(1) NULL DEFAULT 0 COMMENT 'Profile field_see_previous_date',
  `see_questionnaire_result` TINYINT(1) NULL DEFAULT 0 COMMENT 'Profile field_see_questionnaire_result',
  `skills_assessment` TINYINT(1) NULL DEFAULT 0 COMMENT 'Profile field_see_skills_assessment',
  `phone` VARCHAR(64) NULL,
  `website` VARCHAR(512) NULL,
  `is_delete` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`coach_id`),
  UNIQUE KEY `uk_coach_legacy_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_company_coach_details` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coach_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL COMMENT 'Maps to company legacy id → profile field_company (multi)',
  PRIMARY KEY (`id`),
  KEY `idx_coach` (`coach_id`),
  KEY `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Branches / job titles (joined from qs_employee_master)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qs_branch_master` (
  `branch_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_name` VARCHAR(255) NOT NULL COMMENT 'Employee profile field_branch',
  PRIMARY KEY (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_job_position` (
  `job_position_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_position_name` VARCHAR(255) NOT NULL COMMENT 'Employee profile field_job_position; taxonomy job_position for assignments',
  PRIMARY KEY (`job_position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Employees → user role employee + profile employee
-- SyncManager::syncEmployees()
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qs_employee_master` (
  `employee_id` INT UNSIGNED NOT NULL COMMENT 'Business id; profile field_employee_number',
  `id` INT UNSIGNED NOT NULL COMMENT 'Legacy PK for map; often same as employee_id',
  `company_id` INT UNSIGNED NOT NULL COMMENT 'qs_company_master.company_id → profile field_company',
  `assigned_coachs_id` VARCHAR(255) NULL COMMENT 'Comma/space-separated coach_id list → profile field_coach',
  `branch_id` INT UNSIGNED NULL,
  `job_position_id` INT UNSIGNED NULL,
  `email` VARCHAR(255) NULL COMMENT 'Drupal user mail',
  `first_name` VARCHAR(128) NULL,
  `full_name` VARCHAR(255) NULL,
  `view_report` TINYINT(1) NULL DEFAULT 0 COMMENT 'Profile field_view_report',
  `phone` VARCHAR(64) NULL,
  `is_delete` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_business` (`employee_id`),
  KEY `idx_emp_company` (`company_id`),
  KEY `idx_emp_branch` (`branch_id`),
  KEY `idx_emp_job` (`job_position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Questionnaire shell → node type questionnaire + paragraph questionnaire
-- SyncManager::syncQuestionnaires()
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qs_questionnaire_master` (
  `questionnaire_id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Legacy Q id; node matched by title today',
  `questionnaire_name` VARCHAR(512) NOT NULL COMMENT 'Node title + paragraph field_title',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Only status=1 rows are imported',
  PRIMARY KEY (`questionnaire_id`),
  KEY `idx_q_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Answer options (scores) — attachScores()
CREATE TABLE IF NOT EXISTS `qs_answer_master` (
  `answer_text_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `answer_text` VARCHAR(512) NULL COMMENT 'Paragraph options field_option_title',
  `answer_text_value` VARCHAR(128) NULL COMMENT 'Paragraph options field_option_value',
  PRIMARY KEY (`answer_text_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_question_answer_details` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT UNSIGNED NOT NULL,
  `answer_text_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qad_question` (`question_id`),
  KEY `idx_qad_answer` (`answer_text_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_question_master` (
  `question_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `questionnaire_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `subcategory_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `subsubcategory_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `question_title` VARCHAR(512) NULL COMMENT 'Paragraph question field_title',
  `question_hint` VARCHAR(512) NULL COMMENT 'Paragraph question field_hint',
  `priority` INT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `is_delete` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`question_id`),
  KEY `idx_qm_q` (`questionnaire_id`, `category_id`, `subcategory_id`, `subsubcategory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_category_master` (
  `category_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `questionnaire_id` INT UNSIGNED NOT NULL,
  `category_name` VARCHAR(255) NOT NULL COMMENT 'Paragraph category field_title',
  `priority` INT NULL DEFAULT 0 COMMENT 'Paragraph category field_weight',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `is_delete` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`category_id`),
  KEY `idx_cat_q` (`questionnaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_subcategory_master` (
  `subcategory_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `questionnaire_id` INT UNSIGNED NOT NULL,
  `subcategory_name` VARCHAR(255) NOT NULL,
  `priority` INT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `is_delete` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`questionnaire_id`, `category_id`, `subcategory_id`),
  KEY `idx_scat_cat` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_subsubcategory_master` (
  `subsubcategory_id` INT UNSIGNED NOT NULL,
  `subcategory_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `questionnaire_id` INT UNSIGNED NOT NULL,
  `subsubcategory_name` VARCHAR(255) NOT NULL,
  `priority` INT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `is_delete` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`questionnaire_id`, `category_id`, `subcategory_id`, `subsubcategory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Company ↔ questionnaire assignments
-- SyncManager::syncCompanyQuestionnaireAssignments()
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qs_company_questionnaire_details` (
  `company_questionnaire_details_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `questionnaire_id` INT UNSIGNED NOT NULL,
  `number_of_meetings` INT UNSIGNED NULL COMMENT 'Paragraph field_number_of_meetings',
  `ip_address` VARCHAR(64) NULL,
  `user_id` INT UNSIGNED NULL,
  `created_date` DATETIME NULL,
  `hide` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Paragraph field_hide',
  `percentage` DECIMAL(6,2) NULL,
  `date_cron` DATE NULL,
  `time_cron` TIME NULL,
  `user_timezone` VARCHAR(64) NULL,
  `collect_name` TINYINT(1) NULL DEFAULT 0,
  PRIMARY KEY (`company_questionnaire_details_id`),
  KEY `idx_cqd_company` (`company_id`),
  KEY `idx_cqd_q` (`questionnaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qs_company_jobprofilerelation` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_questionnaire_details_id` INT UNSIGNED NOT NULL,
  `job_position_id` INT UNSIGNED NOT NULL COMMENT 'Joined to qs_job_position for taxonomy match',
  PRIMARY KEY (`id`),
  KEY `idx_cjpr_assign` (`company_questionnaire_details_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Coach questionnaire submissions (on behalf of employee)
-- Migrated by crs_migrate:crs_coach_submission_session + crs_coach_submission_answer
-- Requires: users in crs_sync_legacy_map; questionnaires synced (crs_sync_content_map).
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- Employee questionnaire fill sessions (legacy app)
-- Migrated by crs_migrate:crs_emp_filling_session → coach_reporting_session
-- Column names may differ on your DB; crs_emp_questionnaire_filling_session_source
-- auto-detects common aliases. coach_id optional (0 → first coach from qs_company_coach_details).
-- employee_id should match qs_employee_master.id or employee_id (join normalizes to id).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qs_emp_questionnaire_filling_master` (
  `filling_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL COMMENT 'qs_employee_master.id or employee_id',
  `company_id` INT UNSIGNED NOT NULL COMMENT 'qs_company_master.company_id',
  `questionnaire_id` INT UNSIGNED NOT NULL COMMENT 'qs_questionnaire_master.questionnaire_id',
  `coach_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'qs_coach_master.coach_id; NULL/0 → pick from qs_company_coach_details',
  `fill_date` VARCHAR(32) NULL,
  `submitted` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`filling_id`),
  KEY `idx_eqfm_company` (`company_id`),
  KEY `idx_eqfm_emp` (`employee_id`),
  KEY `idx_eqfm_q` (`questionnaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Optional smoke-test seed (commented). Uncomment to load tiny demo data.
-- =============================================================================
/*
INSERT INTO qs_company_master (company_id, id, email, company_name, company_code, no_of_coach, no_of_employees, no_of_password_generated)
VALUES (1, 1, 'company1@example.test', 'Demo Company', 'DEMO-001', 1, 5, 0);

INSERT INTO qs_coach_master (coach_id, id, email, first_name, full_name, see_questionnaire_result)
VALUES (10, 10, 'coach10@example.test', 'Sam', 'Sam Coach', 1);

INSERT INTO qs_company_coach_details (coach_id, company_id) VALUES (10, 1);

INSERT INTO qs_branch_master (branch_id, branch_name) VALUES (1, 'Head Office');
INSERT INTO qs_job_position (job_position_id, job_position_name) VALUES (100, 'Sales Associate');

INSERT INTO qs_employee_master (employee_id, id, company_id, assigned_coachs_id, branch_id, job_position_id, email, full_name, view_report)
VALUES (500, 500, 1, '10', 1, 100, 'employee500@example.test', 'Alex Worker', 1);

INSERT INTO qs_questionnaire_master (questionnaire_id, questionnaire_name, status)
VALUES (200, 'Demo Questionnaire', 1);

INSERT INTO qs_category_master (category_id, questionnaire_id, category_name, priority, status, is_delete)
VALUES (1, 200, 'General', 0, 1, 0);

INSERT INTO qs_answer_master (answer_text_id, answer_text, answer_text_value) VALUES
  (1, 'Strongly disagree', '1'),
  (2, 'Strongly agree', '5');

INSERT INTO qs_question_master (question_id, questionnaire_id, category_id, subcategory_id, subsubcategory_id, question_title, question_hint, status, is_delete)
VALUES (1, 200, 1, 0, 0, 'How satisfied are you?', NULL, 1, 0);

INSERT INTO qs_question_answer_details (question_id, answer_text_id) VALUES (1, 1), (1, 2);

INSERT INTO qs_company_questionnaire_details
  (company_questionnaire_details_id, company_id, questionnaire_id, number_of_meetings, hide)
VALUES (1, 1, 200, 3, 0);

INSERT INTO qs_company_jobprofilerelation (company_questionnaire_details_id, job_position_id)
VALUES (1, 100);
*/
