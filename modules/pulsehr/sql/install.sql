CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_department` (
  `id_pulse_hr_department` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(64) NOT NULL,
  `cost_centre` VARCHAR(32) DEFAULT NULL, `id_head` INT UNSIGNED DEFAULT NULL COMMENT 'pulse_hr_employee of the HOD',
  `credit_minutes_per_room` SMALLINT NOT NULL DEFAULT 0 COMMENT 'housekeeping-style coverage credit; 0 = not room-driven',
  `sort` SMALLINT NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_hr_department`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;
3
CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_section` (
  `id_pulse_hr_section` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_department` INT UNSIGNED NOT NULL, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(64) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (`id_pulse_hr_section`), UNIQUE KEY `code` (`code`), KEY `dept` (`id_pulse_hr_department`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_grade` (
  `id_pulse_hr_grade` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(16) NOT NULL, `name` VARCHAR(64) NOT NULL, `level` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `salary_min` DECIMAL(20,6) NOT NULL DEFAULT 0, `salary_max` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `annual_leave_days` DECIMAL(6,2) NOT NULL DEFAULT 21, `notice_days` SMALLINT NOT NULL DEFAULT 30, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_hr_grade`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_position` (
  `id_pulse_hr_position` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `title` VARCHAR(96) NOT NULL,
  `id_pulse_hr_department` INT UNSIGNED NOT NULL, `id_pulse_hr_section` INT UNSIGNED DEFAULT NULL, `id_pulse_hr_grade` INT UNSIGNED DEFAULT NULL,
  `establishment` SMALLINT NOT NULL DEFAULT 1 COMMENT 'budgeted headcount for this position', `night_shift` TINYINT(1) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (`id_pulse_hr_position`), UNIQUE KEY `code` (`code`), KEY `dept` (`id_pulse_hr_department`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_employee` (
  `id_pulse_hr_employee` INT UNSIGNED NOT NULL AUTO_INCREMENT, `staff_no` VARCHAR(32) NOT NULL,
  `firstname` VARCHAR(64) NOT NULL, `lastname` VARCHAR(64) NOT NULL, `othernames` VARCHAR(64) DEFAULT NULL, `photo` VARCHAR(255) DEFAULT NULL,
  `gender` ENUM('m','f','x') NOT NULL DEFAULT 'm', `dob` DATE DEFAULT NULL, `marital` ENUM('single','married','divorced','widowed','other') NOT NULL DEFAULT 'single',
  `nationality` VARCHAR(48) DEFAULT 'Nigerian', `state_of_origin` VARCHAR(48) DEFAULT NULL, `lga` VARCHAR(64) DEFAULT NULL,
  `national_id` VARCHAR(32) DEFAULT NULL COMMENT 'NIN', `tin` VARCHAR(32) DEFAULT NULL, `rsa_pin` VARCHAR(32) DEFAULT NULL, `pfa` VARCHAR(96) DEFAULT NULL,
  `nhf_no` VARCHAR(32) DEFAULT NULL, `nhf_consent` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'NHF is opt-in for private-sector staff; no consent, no deduction',
  `nhf_consent_date` DATE DEFAULT NULL, `nhf_consent_note` VARCHAR(255) DEFAULT NULL,
  `bank_name` VARCHAR(96) DEFAULT NULL, `bank_code` VARCHAR(16) DEFAULT NULL, `account_no` VARCHAR(20) DEFAULT NULL, `account_name` VARCHAR(96) DEFAULT NULL,
  `nok_name` VARCHAR(96) DEFAULT NULL, `nok_relationship` VARCHAR(32) DEFAULT NULL, `nok_phone` VARCHAR(32) DEFAULT NULL, `nok_address` VARCHAR(255) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL, `city` VARCHAR(64) DEFAULT NULL, `phone` VARCHAR(32) DEFAULT NULL, `phone_alt` VARCHAR(32) DEFAULT NULL, `email` VARCHAR(128) DEFAULT NULL,
  `id_employee` INT UNSIGNED DEFAULT NULL COMMENT 'PrestaShop employee row when the person is also a back-office / POS user — link, never duplicate',
  `id_pulse_hr_department` INT UNSIGNED DEFAULT NULL COMMENT 'mirror of the contract in force; pulse_hr_contract is authoritative',
  `id_pulse_hr_section` INT UNSIGNED DEFAULT NULL, `id_pulse_hr_position` INT UNSIGNED DEFAULT NULL, `id_pulse_hr_grade` INT UNSIGNED DEFAULT NULL,
  `id_manager` INT UNSIGNED DEFAULT NULL COMMENT 'pulse_hr_employee this person reports to',
  `hire_date` DATE DEFAULT NULL, `probation_end` DATE DEFAULT NULL, `confirmation_date` DATE DEFAULT NULL,
  `status` ENUM('probation','active','suspended','on_leave','exited') NOT NULL DEFAULT 'probation',
  `exit_date` DATE DEFAULT NULL, `exit_type` ENUM('none','resignation','termination','end_of_contract','retirement','redundancy','abscondment','death') NOT NULL DEFAULT 'none',
  `exit_reason` VARCHAR(255) DEFAULT NULL, `rehire_eligible` TINYINT(1) NOT NULL DEFAULT 1,
  `pin_hash` CHAR(64) DEFAULT NULL, `pin_set_at` DATETIME DEFAULT NULL, `ess_enabled` TINYINT(1) NOT NULL DEFAULT 1, `ess_locked_until` DATETIME DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_employee`), UNIQUE KEY `staff_no` (`staff_no`), KEY `status` (`status`), KEY `dept` (`id_pulse_hr_department`), KEY `pse` (`id_employee`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_contract` (
  `id_pulse_hr_contract` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_employee` INT UNSIGNED NOT NULL, `contract_no` VARCHAR(24) NOT NULL,
  `type` ENUM('permanent','fixed_term','contract','casual','service','intern') NOT NULL DEFAULT 'permanent',
  `id_pulse_hr_position` INT UNSIGNED DEFAULT NULL, `id_pulse_hr_department` INT UNSIGNED DEFAULT NULL, `id_pulse_hr_section` INT UNSIGNED DEFAULT NULL,
  `id_pulse_hr_grade` INT UNSIGNED DEFAULT NULL, `id_manager` INT UNSIGNED DEFAULT NULL, `cost_centre` VARCHAR(32) DEFAULT NULL,
  `effective_from` DATE NOT NULL COMMENT 'this version is the pay basis from this date', `effective_to` DATE DEFAULT NULL COMMENT 'NULL = still in force',
  `start_date` DATE DEFAULT NULL COMMENT 'engagement start', `end_date` DATE DEFAULT NULL COMMENT 'fixed-term expiry',
  `pay_basis` ENUM('monthly','daily','hourly','per_shift') NOT NULL DEFAULT 'monthly', `pay_rate` DECIMAL(20,6) NOT NULL DEFAULT 0, `currency` CHAR(3) NOT NULL DEFAULT 'NGN',
  `hours_per_week` DECIMAL(6,2) NOT NULL DEFAULT 48, `days_per_week` DECIMAL(4,2) NOT NULL DEFAULT 6, `working_pattern` VARCHAR(64) DEFAULT NULL,
  `night_shift` TINYINT(1) NOT NULL DEFAULT 0, `notice_days` SMALLINT NOT NULL DEFAULT 30, `probation_months` TINYINT UNSIGNED NOT NULL DEFAULT 6, `probation_end` DATE DEFAULT NULL,
  `reason` ENUM('hire','confirmation','promotion','salary_review','transfer','renewal','demotion','correction','exit') NOT NULL DEFAULT 'hire',
  `status` ENUM('draft','active','superseded','ended') NOT NULL DEFAULT 'active', `note` VARCHAR(255) DEFAULT NULL,
  `id_employee_created` INT UNSIGNED DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_contract`), UNIQUE KEY `emp_from` (`id_pulse_hr_employee`,`effective_from`), KEY `window` (`effective_from`,`effective_to`), KEY `no` (`contract_no`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_document` (
  `id_pulse_hr_document` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_employee` INT UNSIGNED NOT NULL,
  `type` VARCHAR(32) NOT NULL COMMENT 'employment_letter, id_card, nin, work_permit, food_handler, medical, driving_licence, certificate, contract, other',
  `name` VARCHAR(128) NOT NULL, `number` VARCHAR(64) DEFAULT NULL, `issuer` VARCHAR(96) DEFAULT NULL,
  `issued_on` DATE DEFAULT NULL, `expires_on` DATE DEFAULT NULL, `file_path` VARCHAR(255) DEFAULT NULL,
  `verified` TINYINT(1) NOT NULL DEFAULT 0, `verified_by` INT UNSIGNED DEFAULT NULL, `remind_days` SMALLINT NOT NULL DEFAULT 30, `last_reminded` DATE DEFAULT NULL,
  `status` ENUM('valid','expiring','expired','revoked') NOT NULL DEFAULT 'valid', `note` VARCHAR(255) DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_document`), KEY `emp` (`id_pulse_hr_employee`), KEY `exp` (`expires_on`), KEY `type` (`type`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_leave_type` (
  `id_pulse_hr_leave_type` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(16) NOT NULL, `name` VARCHAR(64) NOT NULL,
  `paid` TINYINT(1) NOT NULL DEFAULT 1, `accrual` ENUM('none','monthly','annual','on_event') NOT NULL DEFAULT 'none',
  `days_per_year` DECIMAL(6,2) NOT NULL DEFAULT 0, `carry_over_cap` DECIMAL(6,2) NOT NULL DEFAULT 0, `max_consecutive` SMALLINT NOT NULL DEFAULT 0,
  `min_service_months` SMALLINT NOT NULL DEFAULT 0, `gender` ENUM('any','m','f') NOT NULL DEFAULT 'any',
  `working_days_only` TINYINT(1) NOT NULL DEFAULT 1, `requires_document` TINYINT(1) NOT NULL DEFAULT 0, `encashable` TINYINT(1) NOT NULL DEFAULT 0,
  `colour` VARCHAR(7) NOT NULL DEFAULT '#2e86c1', `sort` SMALLINT NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_hr_leave_type`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_leave_entitlement` (
  `id_pulse_hr_leave_entitlement` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_leave_type` INT UNSIGNED NOT NULL, `id_pulse_hr_grade` INT UNSIGNED NOT NULL,
  `days` DECIMAL(6,2) NOT NULL DEFAULT 0, PRIMARY KEY (`id_pulse_hr_leave_entitlement`), UNIQUE KEY `tg` (`id_pulse_hr_leave_type`,`id_pulse_hr_grade`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_leave_balance` (
  `id_pulse_hr_leave_balance` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_employee` INT UNSIGNED NOT NULL, `id_pulse_hr_leave_type` INT UNSIGNED NOT NULL,
  `year` SMALLINT NOT NULL, `opening` DECIMAL(6,2) NOT NULL DEFAULT 0, `carried` DECIMAL(6,2) NOT NULL DEFAULT 0, `accrued` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `taken` DECIMAL(6,2) NOT NULL DEFAULT 0, `pending` DECIMAL(6,2) NOT NULL DEFAULT 0, `encashed` DECIMAL(6,2) NOT NULL DEFAULT 0, `adjustment` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `last_accrued` CHAR(7) DEFAULT NULL COMMENT 'YYYY-MM of the last monthly accrual, so a re-run cannot double-accrue', `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_leave_balance`), UNIQUE KEY `ety` (`id_pulse_hr_employee`,`id_pulse_hr_leave_type`,`year`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_leave_request` (
  `id_pulse_hr_leave_request` INT UNSIGNED NOT NULL AUTO_INCREMENT, `request_no` VARCHAR(16) NOT NULL, `id_pulse_hr_employee` INT UNSIGNED NOT NULL,
  `id_pulse_hr_leave_type` INT UNSIGNED NOT NULL, `date_from` DATE NOT NULL, `date_to` DATE NOT NULL, `days` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `half_day` ENUM('none','start','end') NOT NULL DEFAULT 'none', `reason` VARCHAR(255) DEFAULT NULL,
  `id_relief` INT UNSIGNED DEFAULT NULL COMMENT 'colleague covering the desk', `contact_phone` VARCHAR(32) DEFAULT NULL, `address_on_leave` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('draft','pending','approved','rejected','cancelled','taken') NOT NULL DEFAULT 'pending', `current_level` TINYINT NOT NULL DEFAULT 1,
  `id_pulse_hr_document` INT UNSIGNED DEFAULT NULL, `source` ENUM('admin','ess','api') NOT NULL DEFAULT 'admin',
  `encash_days` DECIMAL(6,2) NOT NULL DEFAULT 0, `decision_note` VARCHAR(255) DEFAULT NULL, `decided_by` INT UNSIGNED DEFAULT NULL, `decided_at` DATETIME DEFAULT NULL,
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_leave_request`), UNIQUE KEY `no` (`request_no`), KEY `emp` (`id_pulse_hr_employee`,`status`), KEY `win` (`date_from`,`date_to`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_leave_approval` (
  `id_pulse_hr_leave_approval` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_leave_request` INT UNSIGNED NOT NULL, `level` TINYINT NOT NULL DEFAULT 1,
  `role` VARCHAR(32) NOT NULL DEFAULT 'manager', `id_pulse_hr_employee` INT UNSIGNED DEFAULT NULL COMMENT 'nominated approver', `id_employee` INT UNSIGNED DEFAULT NULL COMMENT 'who actually clicked',
  `action` ENUM('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending', `comment` VARCHAR(255) DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_leave_approval`), KEY `req` (`id_pulse_hr_leave_request`,`level`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_blackout` (
  `id_pulse_hr_blackout` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(96) NOT NULL, `department` VARCHAR(32) DEFAULT NULL COMMENT 'NULL = every department',
  `date_from` DATE NOT NULL, `date_to` DATE NOT NULL, `max_off` SMALLINT NOT NULL DEFAULT 0 COMMENT '0 = nobody may be off',
  `min_occupancy_pct` DECIMAL(6,3) NOT NULL DEFAULT 0 COMMENT 'only bites when forecast occupancy is at or above this', `reason` VARCHAR(255) DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (`id_pulse_hr_blackout`), KEY `win` (`date_from`,`date_to`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_shift` (
  `id_pulse_hr_shift` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(16) NOT NULL, `name` VARCHAR(64) NOT NULL,
  `start_time` TIME NOT NULL DEFAULT '08:00:00', `end_time` TIME NOT NULL DEFAULT '16:00:00', `break_minutes` SMALLINT NOT NULL DEFAULT 60,
  `paid_hours` DECIMAL(6,2) NOT NULL DEFAULT 8, `night` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'payroll reads this for the night allowance',
  `split` TINYINT(1) NOT NULL DEFAULT 0, `on_call` TINYINT(1) NOT NULL DEFAULT 0, `department` VARCHAR(32) DEFAULT NULL,
  `colour` VARCHAR(7) NOT NULL DEFAULT '#5bc0de', `sort` SMALLINT NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_hr_shift`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_roster` (
  `id_pulse_hr_roster` INT UNSIGNED NOT NULL AUTO_INCREMENT, `roster_date` DATE NOT NULL, `id_pulse_hr_employee` INT UNSIGNED NOT NULL,
  `id_pulse_hr_shift` INT UNSIGNED DEFAULT NULL, `department` VARCHAR(32) DEFAULT NULL, `section` VARCHAR(32) DEFAULT NULL,
  `is_off` TINYINT(1) NOT NULL DEFAULT 0, `status` ENUM('planned','published','swapped','cancelled') NOT NULL DEFAULT 'planned',
  `note` VARCHAR(128) DEFAULT NULL, `published_at` DATETIME DEFAULT NULL, `published_by` INT UNSIGNED DEFAULT NULL,
  `id_employee_created` INT UNSIGNED DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_roster`), UNIQUE KEY `de` (`roster_date`,`id_pulse_hr_employee`), KEY `dept` (`roster_date`,`department`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_roster_swap` (
  `id_pulse_hr_roster_swap` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_roster` INT UNSIGNED NOT NULL,
  `id_requested_by` INT UNSIGNED NOT NULL COMMENT 'pulse_hr_employee asking', `id_pulse_hr_employee_to` INT UNSIGNED DEFAULT NULL COMMENT 'colleague asked to take it',
  `id_roster_to` INT UNSIGNED DEFAULT NULL COMMENT 'their shift, when it is a straight swap', `reason` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','accepted','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `decided_by` INT UNSIGNED DEFAULT NULL, `decided_at` DATETIME DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_roster_swap`), KEY `r` (`id_pulse_hr_roster`), KEY `st` (`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_checklist_template` (
  `id_pulse_hr_checklist_template` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `type` ENUM('onboarding','offboarding') NOT NULL DEFAULT 'onboarding', `department` VARCHAR(32) DEFAULT NULL COMMENT 'NULL = any department',
  `active` TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (`id_pulse_hr_checklist_template`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_checklist_task_template` (
  `id_pulse_hr_checklist_task_template` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_checklist_template` INT UNSIGNED NOT NULL,
  `sort` SMALLINT NOT NULL DEFAULT 0, `title` VARCHAR(128) NOT NULL, `owner_department` VARCHAR(32) DEFAULT 'admin', `due_offset_days` SMALLINT NOT NULL DEFAULT 0,
  `action` ENUM('none','keycard_issue','keycard_revoke','pos_pin','pos_disable','ess_pin','ess_disable','document','asset_issue','asset_return','induction','exit_interview','final_settlement') NOT NULL DEFAULT 'none',
  `mandatory` TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (`id_pulse_hr_checklist_task_template`), KEY `tpl` (`id_pulse_hr_checklist_template`,`sort`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_checklist` (
  `id_pulse_hr_checklist` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_employee` INT UNSIGNED NOT NULL,
  `type` ENUM('onboarding','offboarding') NOT NULL DEFAULT 'onboarding', `id_pulse_hr_checklist_template` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('open','completed','cancelled') NOT NULL DEFAULT 'open', `opened_on` DATE NOT NULL, `due_on` DATE DEFAULT NULL, `completed_on` DATE DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_checklist`), KEY `emp` (`id_pulse_hr_employee`,`type`), KEY `st` (`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_checklist_task` (
  `id_pulse_hr_checklist_task` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_checklist` INT UNSIGNED NOT NULL, `sort` SMALLINT NOT NULL DEFAULT 0,
  `title` VARCHAR(128) NOT NULL, `owner_department` VARCHAR(32) DEFAULT NULL, `id_owner_employee` INT UNSIGNED DEFAULT NULL COMMENT 'PrestaShop employee who owns the task',
  `due_on` DATE DEFAULT NULL, `status` ENUM('pending','done','na','failed') NOT NULL DEFAULT 'pending',
  `action` ENUM('none','keycard_issue','keycard_revoke','pos_pin','pos_disable','ess_pin','ess_disable','document','asset_issue','asset_return','induction','exit_interview','final_settlement') NOT NULL DEFAULT 'none',
  `action_ref` VARCHAR(64) DEFAULT NULL, `mandatory` TINYINT(1) NOT NULL DEFAULT 1, `note` VARCHAR(255) DEFAULT NULL,
  `done_by` INT UNSIGNED DEFAULT NULL, `done_at` DATETIME DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_checklist_task`), KEY `cl` (`id_pulse_hr_checklist`,`sort`), KEY `st` (`status`,`due_on`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_case` (
  `id_pulse_hr_case` INT UNSIGNED NOT NULL AUTO_INCREMENT, `case_no` VARCHAR(16) NOT NULL, `id_pulse_hr_employee` INT UNSIGNED NOT NULL,
  `type` ENUM('query','verbal_warning','written_warning','final_warning','suspension','commendation','grievance') NOT NULL DEFAULT 'query',
  `subject` VARCHAR(128) NOT NULL, `description` TEXT, `incident_date` DATE DEFAULT NULL, `issued_on` DATE NOT NULL, `issued_by` INT UNSIGNED DEFAULT NULL,
  `response` TEXT, `responded_at` DATETIME DEFAULT NULL, `acknowledged_at` DATETIME DEFAULT NULL, `ack_ip` VARCHAR(45) DEFAULT NULL,
  `status` ENUM('open','acknowledged','responded','closed','withdrawn') NOT NULL DEFAULT 'open', `outcome` VARCHAR(255) DEFAULT NULL,
  `expires_on` DATE DEFAULT NULL COMMENT 'a warning stops counting after this date', `suspension_from` DATE DEFAULT NULL, `suspension_to` DATE DEFAULT NULL,
  `unpaid` TINYINT(1) NOT NULL DEFAULT 0, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_case`), UNIQUE KEY `no` (`case_no`), KEY `emp` (`id_pulse_hr_employee`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_appraisal_cycle` (
  `id_pulse_hr_appraisal_cycle` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(24) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `period_from` DATE NOT NULL, `period_to` DATE NOT NULL, `due_on` DATE DEFAULT NULL, `status` ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `date_add` DATETIME NOT NULL, PRIMARY KEY (`id_pulse_hr_appraisal_cycle`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_appraisal` (
  `id_pulse_hr_appraisal` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_appraisal_cycle` INT UNSIGNED NOT NULL, `id_pulse_hr_employee` INT UNSIGNED NOT NULL,
  `id_reviewer` INT UNSIGNED DEFAULT NULL COMMENT 'pulse_hr_employee doing the review',
  `status` ENUM('draft','self_review','reviewer','signed','closed') NOT NULL DEFAULT 'draft', `overall_rating` DECIMAL(4,2) NOT NULL DEFAULT 0,
  `reviewer_comment` TEXT, `employee_comment` TEXT, `recommendation` ENUM('none','confirm','promote','increment','training','pip','exit') NOT NULL DEFAULT 'none',
  `employee_signed_at` DATETIME DEFAULT NULL, `reviewer_signed_at` DATETIME DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_appraisal`), UNIQUE KEY `ce` (`id_pulse_hr_appraisal_cycle`,`id_pulse_hr_employee`), KEY `emp` (`id_pulse_hr_employee`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_appraisal_objective` (
  `id_pulse_hr_appraisal_objective` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_appraisal` INT UNSIGNED NOT NULL, `sort` SMALLINT NOT NULL DEFAULT 0,
  `title` VARCHAR(128) NOT NULL, `description` VARCHAR(255) DEFAULT NULL, `weight` DECIMAL(6,3) NOT NULL DEFAULT 0, `target` VARCHAR(128) DEFAULT NULL,
  `result` VARCHAR(128) DEFAULT NULL, `rating` DECIMAL(4,2) NOT NULL DEFAULT 0, `comment` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id_pulse_hr_appraisal_objective`), KEY `ap` (`id_pulse_hr_appraisal`,`sort`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_training` (
  `id_pulse_hr_training` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_employee` INT UNSIGNED NOT NULL, `course` VARCHAR(128) NOT NULL,
  `provider` VARCHAR(96) DEFAULT NULL, `type` ENUM('induction','safety','food_hygiene','fire','first_aid','service','technical','compliance','other') NOT NULL DEFAULT 'other',
  `completed_on` DATE DEFAULT NULL, `expires_on` DATE DEFAULT NULL, `cost` DECIMAL(20,6) NOT NULL DEFAULT 0, `certificate_no` VARCHAR(64) DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_training`), KEY `emp` (`id_pulse_hr_employee`), KEY `exp` (`expires_on`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_ess_session` (
  `id_pulse_hr_ess_session` INT UNSIGNED NOT NULL AUTO_INCREMENT, `sid` CHAR(32) NOT NULL, `id_pulse_hr_employee` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL, `expires_at` DATETIME NOT NULL, `payslip_until` DATETIME DEFAULT NULL COMMENT 'salary is only served inside this short window, after a PIN re-entry',
  `ip` VARCHAR(45) DEFAULT NULL, `user_agent` VARCHAR(255) DEFAULT NULL, `revoked` TINYINT(1) NOT NULL DEFAULT 0, `revoke_reason` VARCHAR(32) DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_ess_session`), UNIQUE KEY `sid` (`sid`), KEY `emp` (`id_pulse_hr_employee`,`revoked`), KEY `exp` (`expires_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_ess_login` (
  `id_pulse_hr_ess_login` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `staff_no` VARCHAR(32) DEFAULT NULL, `ip` VARCHAR(45) DEFAULT NULL,
  `ok` TINYINT(1) NOT NULL DEFAULT 0, `reason` VARCHAR(48) DEFAULT NULL, `user_agent` VARCHAR(255) DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_ess_login`), KEY `sn` (`staff_no`,`date_add`), KEY `ip` (`ip`,`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_punch` (
  `id_pulse_hr_punch` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_employee` INT UNSIGNED NOT NULL, `punched_at` DATETIME NOT NULL,
  `direction` ENUM('in','out') NOT NULL DEFAULT 'in', `source` ENUM('mobile','qr','kiosk','manual') NOT NULL DEFAULT 'mobile',
  `lat` DECIMAL(10,7) DEFAULT NULL, `lng` DECIMAL(10,7) DEFAULT NULL, `accuracy_m` DECIMAL(8,2) DEFAULT NULL, `distance_m` DECIMAL(10,2) DEFAULT NULL,
  `inside_geofence` TINYINT(1) NOT NULL DEFAULT 0, `status` ENUM('accepted','flagged','rejected') NOT NULL DEFAULT 'accepted', `flag_reason` VARCHAR(64) DEFAULT NULL,
  `reviewed_by` INT UNSIGNED DEFAULT NULL, `reviewed_at` DATETIME DEFAULT NULL, `review_note` VARCHAR(255) DEFAULT NULL,
  `device` VARCHAR(64) DEFAULT NULL, `ip` VARCHAR(45) DEFAULT NULL, `id_pulse_hr_roster` INT UNSIGNED DEFAULT NULL,
  `synced` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'handed to Pulse Time when that module is installed', `note` VARCHAR(255) DEFAULT NULL,
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_punch`), UNIQUE KEY `dedupe` (`id_pulse_hr_employee`,`punched_at`,`direction`), KEY `bd` (`business_date`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_change_request` (
  `id_pulse_hr_change_request` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_hr_employee` INT UNSIGNED NOT NULL, `field` VARCHAR(32) NOT NULL,
  `old_value` VARCHAR(255) DEFAULT NULL, `new_value` VARCHAR(255) DEFAULT NULL, `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `note` VARCHAR(255) DEFAULT NULL, `decided_by` INT UNSIGNED DEFAULT NULL, `decided_at` DATETIME DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hr_change_request`), KEY `emp` (`id_pulse_hr_employee`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hr_rate` (
  `bucket` VARCHAR(64) NOT NULL, `window_start` INT UNSIGNED NOT NULL, `hits` INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (`bucket`,`window_start`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_hr_department` (`code`,`name`,`cost_centre`,`credit_minutes_per_room`,`sort`) VALUES
('rooms','Rooms / Front Office','CC-ROOMS',0,1),('housekeeping','Housekeeping','CC-HK',25,2),('fnb','Food & Beverage','CC-FNB',0,3),('laundry','Laundry','CC-LDY',6,4),
('maintenance','Maintenance','CC-MNT',0,5),('security','Security','CC-SEC',0,6),('sales','Sales & Marketing','CC-SAL',0,7),('accounts','Accounts','CC-ACC',0,8),('admin','Admin & HR','CC-ADM',0,9);

INSERT IGNORE INTO `PREFIX_pulse_hr_grade` (`code`,`name`,`level`,`salary_min`,`salary_max`,`annual_leave_days`,`notice_days`) VALUES
('G1','Entry / Casual',1,80000,140000,15,7),('G2','Attendant / Steward',2,120000,190000,18,30),('G3','Senior Attendant / Technician',3,170000,260000,20,30),
('G4','Supervisor',4,250000,400000,21,30),('G5','Assistant Manager',5,380000,600000,24,30),('G6','Head of Department',6,550000,900000,26,60),
('G7','Executive',7,850000,1600000,30,90),('G8','General Manager',8,1500000,3000000,30,90);

INSERT IGNORE INTO `PREFIX_pulse_hr_leave_type` (`code`,`name`,`paid`,`accrual`,`days_per_year`,`carry_over_cap`,`max_consecutive`,`min_service_months`,`gender`,`working_days_only`,`requires_document`,`encashable`,`colour`,`sort`) VALUES
('ANN','Annual leave',1,'monthly',21,5,21,6,'any',1,0,1,'#2e86c1',1),
('SICK','Sick leave',1,'annual',12,0,12,0,'any',1,1,0,'#e67e22',2),
('MAT','Maternity leave',1,'on_event',84,0,84,6,'f',0,1,0,'#c0392b',3),
('PAT','Paternity leave',1,'on_event',14,0,14,6,'m',0,0,0,'#8e44ad',4),
('COMP','Compassionate leave',1,'annual',5,0,5,0,'any',1,0,0,'#16a085',5),
('STUDY','Study leave',1,'none',0,0,30,24,'any',1,1,0,'#2c3e50',6),
('LWOP','Leave without pay',0,'none',0,0,90,0,'any',0,0,0,'#7f8c8d',7);

INSERT IGNORE INTO `PREFIX_pulse_hr_shift` (`code`,`name`,`start_time`,`end_time`,`break_minutes`,`paid_hours`,`night`,`split`,`on_call`,`colour`,`sort`) VALUES
('E','Early 06:00–14:00','06:00:00','14:00:00',30,7.5,0,0,0,'#5bc0de',1),
('L','Late 14:00–22:00','14:00:00','22:00:00',30,7.5,0,0,0,'#f0ad4e',2),
('N','Night 22:00–06:00','22:00:00','06:00:00',30,7.5,1,0,0,'#34495e',3),
('G','General 08:00–17:00','08:00:00','17:00:00',60,8,0,0,0,'#5cb85c',4),
('SP','Split 07:00–11:00 / 17:00–21:00','07:00:00','21:00:00',360,8,0,1,0,'#9b59b6',5),
('OC','On call','00:00:00','23:59:00',0,0,0,0,1,'#95a5a6',6);

INSERT IGNORE INTO `PREFIX_pulse_hr_checklist_template` (`code`,`name`,`type`,`department`) VALUES
('ONB-STD','Standard onboarding','onboarding',NULL),('OFF-STD','Standard offboarding / clearance','offboarding',NULL);

INSERT IGNORE INTO `PREFIX_pulse_hr_checklist_task_template` (`id_pulse_hr_checklist_template`,`sort`,`title`,`owner_department`,`due_offset_days`,`action`,`mandatory`) VALUES
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),1,'Signed offer and employment letter on file','admin',0,'document',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),2,'Collect NIN, TIN, bank details and next of kin','admin',1,'document',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),3,'Medical / health screening certificate','admin',3,'document',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),4,'Food handler certificate (kitchen and service staff)','fnb',3,'document',0),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),5,'Open RSA with a PFA and record the PIN','accounts',5,'none',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),6,'Issue staff key card','security',0,'keycard_issue',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),7,'Issue POS PIN where the role sells','fnb',0,'pos_pin',0),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),8,'Set the staff portal PIN and show them the portal','admin',0,'ess_pin',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),9,'Issue uniform and name badge','admin',1,'asset_issue',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),10,'Allocate locker and hand over the key','security',1,'asset_issue',0),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),11,'Induction: fire, first aid, guest service, grooming','admin',7,'induction',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='ONB-STD'),12,'Department orientation with the HOD','admin',7,'none',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='OFF-STD'),1,'Resignation / termination letter on file','admin',0,'document',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='OFF-STD'),2,'Revoke every staff key card','security',0,'keycard_revoke',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='OFF-STD'),3,'Disable POS login','fnb',0,'pos_disable',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='OFF-STD'),4,'Disable the staff portal account','admin',0,'ess_disable',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='OFF-STD'),5,'Return uniform, badge, locker key and tools','admin',0,'asset_return',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='OFF-STD'),6,'Handover of float, keys and pending work','accounts',0,'none',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='OFF-STD'),7,'Clearance signed by every department','accounts',3,'none',1),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='OFF-STD'),8,'Exit interview','admin',3,'exit_interview',0),
((SELECT `id_pulse_hr_checklist_template` FROM `PREFIX_pulse_hr_checklist_template` WHERE `code`='OFF-STD'),9,'Final entitlements: leave encashment, loans, final pay','accounts',7,'final_settlement',1);
