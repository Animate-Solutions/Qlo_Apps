CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_country` (
  `id_pulse_pr_country` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(2) NOT NULL, `name` VARCHAR(64) NOT NULL, `currency` VARCHAR(3) NOT NULL DEFAULT 'NGN',
  `tax_year_start` VARCHAR(5) NOT NULL DEFAULT '01-01' COMMENT 'MM-DD',
  `statutory_class` VARCHAR(64) NOT NULL DEFAULT 'PulsePrStatutoryGeneric' COMMENT 'implements PulsePrStatutoryInterface',
  `paye_mode` ENUM('cumulative','non_cumulative') NOT NULL DEFAULT 'cumulative',
  `paye_basis` ENUM('annual','monthly') NOT NULL DEFAULT 'annual' COMMENT 'annual = annualise then de-annualise; monthly = bands applied to the period directly',
  `rounding` ENUM('round','floor','ceil') NOT NULL DEFAULT 'round', `rounding_dp` TINYINT NOT NULL DEFAULT 2,
  `verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = starting point, requires local verification before use',
  `note` VARCHAR(255), `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_country`), UNIQUE KEY `c` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_tax_band` (
  `id_pulse_pr_tax_band` INT UNSIGNED NOT NULL AUTO_INCREMENT, `country` VARCHAR(2) NOT NULL, `regime` VARCHAR(32) NOT NULL DEFAULT 'paye',
  `seq` SMALLINT NOT NULL DEFAULT 0, `band_from` DECIMAL(20,6) NOT NULL DEFAULT 0, `band_to` DECIMAL(20,6) DEFAULT NULL COMMENT 'NULL = no ceiling',
  `rate_pct` DECIMAL(6,3) NOT NULL DEFAULT 0, `basis` ENUM('annual','monthly') NOT NULL DEFAULT 'annual',
  `effective_from` DATE NOT NULL, `effective_to` DATE DEFAULT NULL, `note` VARCHAR(160),
  PRIMARY KEY (`id_pulse_pr_tax_band`), KEY `look` (`country`,`regime`,`effective_from`), KEY `seq` (`seq`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_relief` (
  `id_pulse_pr_relief` INT UNSIGNED NOT NULL AUTO_INCREMENT, `country` VARCHAR(2) NOT NULL, `code` VARCHAR(24) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `type` ENUM('fixed','percent_of','capped_percent','greater_of') NOT NULL DEFAULT 'capped_percent',
  `value_pct` DECIMAL(6,3) NOT NULL DEFAULT 0, `value_fixed` DECIMAL(20,6) NOT NULL DEFAULT 0, `cap` DECIMAL(20,6) DEFAULT NULL,
  `base` VARCHAR(24) NOT NULL DEFAULT 'GROSS' COMMENT 'named base or DECLARED:<declaration code>',
  `basis` ENUM('annual','monthly') NOT NULL DEFAULT 'annual',
  `requires_evidence` TINYINT(1) NOT NULL DEFAULT 0, `declaration_code` VARCHAR(24) DEFAULT NULL,
  `conditions` VARCHAR(255), `sort` SMALLINT NOT NULL DEFAULT 0,
  `effective_from` DATE NOT NULL, `effective_to` DATE DEFAULT NULL, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_pr_relief`), KEY `look` (`country`,`effective_from`), KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_contribution` (
  `id_pulse_pr_contribution` INT UNSIGNED NOT NULL AUTO_INCREMENT, `country` VARCHAR(2) NOT NULL, `code` VARCHAR(24) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `employee_pct` DECIMAL(6,3) NOT NULL DEFAULT 0, `employer_pct` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `base` VARCHAR(24) NOT NULL DEFAULT 'BHT' COMMENT 'named base: BASIC, BHT, GROSS, TAXABLE_GROSS',
  `floor` DECIMAL(20,6) NOT NULL DEFAULT 0, `ceiling` DECIMAL(20,6) DEFAULT NULL,
  `frequency` ENUM('monthly','annual') NOT NULL DEFAULT 'monthly',
  `mode` ENUM('mandatory','opt_in','opt_out','disabled') NOT NULL DEFAULT 'mandatory',
  `consent_code` VARCHAR(24) DEFAULT NULL COMMENT 'declaration code that records the employee consent for opt_in schemes',
  `pre_tax` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'employee side deductible before PAYE',
  `employer_min_staff` SMALLINT NOT NULL DEFAULT 0, `employer_min_turnover` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `remit_within_days` SMALLINT NOT NULL DEFAULT 0, `remit_rule` VARCHAR(160), `penalty_pct_month` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `gl_liability` VARCHAR(16) DEFAULT NULL, `gl_expense` VARCHAR(16) DEFAULT NULL, `sort` SMALLINT NOT NULL DEFAULT 0,
  `effective_from` DATE NOT NULL, `effective_to` DATE DEFAULT NULL, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_pr_contribution`), KEY `look` (`country`,`effective_from`), KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_element` (
  `id_pulse_pr_element` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(24) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `type` ENUM('earning','deduction','employer','information') NOT NULL DEFAULT 'earning',
  `calc` ENUM('fixed','percent','rate_units','formula','statutory') NOT NULL DEFAULT 'fixed',
  `percent_of` VARCHAR(24) DEFAULT NULL COMMENT 'named base for calc=percent', `default_value` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `formula` VARCHAR(255) DEFAULT NULL COMMENT 'safe expression over named bases and element codes',
  `statutory_code` VARCHAR(24) DEFAULT NULL COMMENT 'PAYE or a pulse_pr_contribution code',
  `taxable` TINYINT(1) NOT NULL DEFAULT 1, `pensionable` TINYINT(1) NOT NULL DEFAULT 0, `nsitfable` TINYINT(1) NOT NULL DEFAULT 1,
  `in_basic` TINYINT(1) NOT NULL DEFAULT 0, `proratable` TINYINT(1) NOT NULL DEFAULT 1, `recurring` TINYINT(1) NOT NULL DEFAULT 1,
  `gl_account` VARCHAR(16) DEFAULT NULL, `department` VARCHAR(32) DEFAULT NULL, `sequence` SMALLINT NOT NULL DEFAULT 0,
  `show_on_payslip` TINYINT(1) NOT NULL DEFAULT 1, `active` TINYINT(1) NOT NULL DEFAULT 1, `note` VARCHAR(255),
  PRIMARY KEY (`id_pulse_pr_element`), UNIQUE KEY `code` (`code`), KEY `seq` (`sequence`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_employee` (
  `id_pulse_pr_employee` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_hr_employee` INT UNSIGNED DEFAULT NULL COMMENT 'pulse_hr_employee when pulsehr is installed',
  `id_employee` INT UNSIGNED DEFAULT NULL COMMENT 'PrestaShop back-office user, nullable',
  `staff_no` VARCHAR(24) NOT NULL, `firstname` VARCHAR(64) NOT NULL, `lastname` VARCHAR(64) NOT NULL,
  `department` VARCHAR(32) NOT NULL DEFAULT 'general', `section` VARCHAR(32) DEFAULT NULL, `position` VARCHAR(96) DEFAULT NULL,
  `grade` VARCHAR(24) DEFAULT NULL, `cost_centre` VARCHAR(32) DEFAULT NULL,
  `employment_type` ENUM('permanent','fixed_term','contract','casual','service','intern') NOT NULL DEFAULT 'permanent',
  `pay_basis` ENUM('monthly','daily','hourly','per_shift') NOT NULL DEFAULT 'monthly', `pay_rate` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `country` VARCHAR(2) NOT NULL DEFAULT 'NG', `currency` VARCHAR(3) NOT NULL DEFAULT 'NGN',
  `hire_date` DATE DEFAULT NULL, `exit_date` DATE DEFAULT NULL, `status` ENUM('active','probation','suspended','on_leave','exited') NOT NULL DEFAULT 'active',
  `tin` VARCHAR(32) DEFAULT NULL, `tax_state` VARCHAR(32) NOT NULL DEFAULT 'Rivers', `rsa_pin` VARCHAR(32) DEFAULT NULL, `pfa` VARCHAR(96) DEFAULT NULL,
  `nhf_no` VARCHAR(32) DEFAULT NULL, `nsitf_no` VARCHAR(32) DEFAULT NULL, `nin` VARCHAR(32) DEFAULT NULL,
  `bank_name` VARCHAR(96) DEFAULT NULL, `bank_code` VARCHAR(16) DEFAULT NULL, `account_no` VARCHAR(24) DEFAULT NULL, `account_name` VARCHAR(128) DEFAULT NULL,
  `email` VARCHAR(128) DEFAULT NULL, `phone` VARCHAR(32) DEFAULT NULL,
  `payslip_pin` VARCHAR(128) DEFAULT NULL COMMENT 'hashed with _COOKIE_KEY_, gates the tokenised payslip download',
  `pay_method` ENUM('bank','cash','cheque') NOT NULL DEFAULT 'bank', `on_hold` TINYINT(1) NOT NULL DEFAULT 0, `hold_reason` VARCHAR(160) DEFAULT NULL,
  `note` VARCHAR(255), `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_employee`), UNIQUE KEY `staff` (`staff_no`), KEY `hr` (`id_hr_employee`), KEY `dept` (`department`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_employee_element` (
  `id_pulse_pr_employee_element` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_pr_employee` INT UNSIGNED DEFAULT NULL COMMENT 'NULL with a grade set = the grade default structure',
  `grade` VARCHAR(24) DEFAULT NULL, `element_code` VARCHAR(24) NOT NULL,
  `amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `percent` DECIMAL(9,4) NOT NULL DEFAULT 0, `units` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `effective_from` DATE NOT NULL, `effective_to` DATE DEFAULT NULL, `note` VARCHAR(160), `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_employee_element`), KEY `emp` (`id_pulse_pr_employee`,`effective_from`), KEY `grade` (`grade`,`effective_from`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_declaration` (
  `id_pulse_pr_declaration` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_employee` INT UNSIGNED NOT NULL,
  `code` VARCHAR(24) NOT NULL COMMENT 'RENT, LIFE, MORTGAGE, NHF_CONSENT, NHIS_CONSENT, PENSION_VOL',
  `annual_value` DECIMAL(20,6) NOT NULL DEFAULT 0, `percent` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `evidence_ref` VARCHAR(160) DEFAULT NULL, `evidence_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `consented` TINYINT(1) NOT NULL DEFAULT 0, `consent_date` DATE DEFAULT NULL, `consent_channel` VARCHAR(32) DEFAULT NULL,
  `date_from` DATE NOT NULL, `date_to` DATE DEFAULT NULL, `id_employee` INT UNSIGNED, `note` VARCHAR(255),
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_declaration`), KEY `emp` (`id_pulse_pr_employee`,`code`,`date_from`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_opening` (
  `id_pulse_pr_opening` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_employee` INT UNSIGNED NOT NULL, `tax_year` SMALLINT NOT NULL,
  `periods` SMALLINT NOT NULL DEFAULT 0 COMMENT 'pay periods already run before Pulse took over',
  `gross` DECIMAL(20,6) NOT NULL DEFAULT 0, `taxable` DECIMAL(20,6) NOT NULL DEFAULT 0, `paye` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `pension_ee` DECIMAL(20,6) NOT NULL DEFAULT 0, `pension_er` DECIMAL(20,6) NOT NULL DEFAULT 0, `nhf` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `net` DECIMAL(20,6) NOT NULL DEFAULT 0, `note` VARCHAR(160), `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_opening`), UNIQUE KEY `ey` (`id_pulse_pr_employee`,`tax_year`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_timesheet` (
  `id_pulse_pr_timesheet` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_employee` INT UNSIGNED NOT NULL, `period` VARCHAR(10) NOT NULL,
  `source` ENUM('pulsetime','manual','import') NOT NULL DEFAULT 'manual',
  `days_worked` DECIMAL(8,3) NOT NULL DEFAULT 0, `hours_worked` DECIMAL(10,3) NOT NULL DEFAULT 0, `shifts` DECIMAL(8,3) NOT NULL DEFAULT 0,
  `ot_hours` DECIMAL(10,3) NOT NULL DEFAULT 0, `ot_rest_hours` DECIMAL(10,3) NOT NULL DEFAULT 0, `ot_holiday_hours` DECIMAL(10,3) NOT NULL DEFAULT 0,
  `night_shifts` DECIMAL(8,3) NOT NULL DEFAULT 0, `unpaid_days` DECIMAL(8,3) NOT NULL DEFAULT 0, `absent_days` DECIMAL(8,3) NOT NULL DEFAULT 0,
  `approved` TINYINT(1) NOT NULL DEFAULT 0, `approved_by` INT UNSIGNED, `date_approved` DATETIME DEFAULT NULL,
  `note` VARCHAR(160), `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_timesheet`), UNIQUE KEY `ep` (`id_pulse_pr_employee`,`period`), KEY `p` (`period`,`approved`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_run` (
  `id_pulse_pr_run` INT UNSIGNED NOT NULL AUTO_INCREMENT, `run_no` VARCHAR(20) NOT NULL, `period` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
  `run_type` ENUM('regular','supplementary','bonus','final_settlement','casual') NOT NULL DEFAULT 'regular',
  `country` VARCHAR(2) NOT NULL DEFAULT 'NG', `currency` VARCHAR(3) NOT NULL DEFAULT 'NGN',
  `period_from` DATE NOT NULL, `period_to` DATE NOT NULL, `pay_date` DATE NOT NULL,
  `status` ENUM('draft','calculated','approved','paid','posted','cancelled') NOT NULL DEFAULT 'draft',
  `department` VARCHAR(32) DEFAULT NULL COMMENT 'blank = whole property',
  `headcount` INT NOT NULL DEFAULT 0, `total_gross` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_taxable` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_paye` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_pension_ee` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_pension_er` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_nhf` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_nsitf` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_itf` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_other_ded` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_loan` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_arrears` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_net` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_employer_cost` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `result_hash` CHAR(40) DEFAULT NULL COMMENT 'sha1 of the calculated result — a re-run of an unchanged period must reproduce it',
  `calc_ms` INT NOT NULL DEFAULT 0, `errors` TEXT,
  `calculated_by` INT UNSIGNED, `date_calculated` DATETIME DEFAULT NULL, `approved_by` INT UNSIGNED, `date_approved` DATETIME DEFAULT NULL,
  `paid_by` INT UNSIGNED, `date_paid` DATETIME DEFAULT NULL, `id_acc_journal` INT UNSIGNED DEFAULT NULL, `date_posted` DATETIME DEFAULT NULL,
  `note` VARCHAR(255), `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_run`), UNIQUE KEY `no` (`run_no`), KEY `pd` (`period`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_payslip` (
  `id_pulse_pr_payslip` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_run` INT UNSIGNED NOT NULL, `id_pulse_pr_employee` INT UNSIGNED NOT NULL,
  `period` VARCHAR(7) NOT NULL, `staff_no` VARCHAR(24) NOT NULL, `employee_name` VARCHAR(128) NOT NULL, `department` VARCHAR(32) NOT NULL DEFAULT 'general',
  `position` VARCHAR(96) DEFAULT NULL, `grade` VARCHAR(24) DEFAULT NULL, `cost_centre` VARCHAR(32) DEFAULT NULL,
  `days_paid` DECIMAL(8,3) NOT NULL DEFAULT 0, `days_in_period` DECIMAL(8,3) NOT NULL DEFAULT 0, `proration` DECIMAL(9,6) NOT NULL DEFAULT 1,
  `basic` DECIMAL(20,6) NOT NULL DEFAULT 0, `bht` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `gross` DECIMAL(20,6) NOT NULL DEFAULT 0, `taxable_gross` DECIMAL(20,6) NOT NULL DEFAULT 0, `pensionable` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `paye` DECIMAL(20,6) NOT NULL DEFAULT 0, `paye_annual` DECIMAL(20,6) NOT NULL DEFAULT 0, `annualisation_periods` SMALLINT NOT NULL DEFAULT 12,
  `chargeable_income` DECIMAL(20,6) NOT NULL DEFAULT 0, `reliefs_total` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `pension_ee` DECIMAL(20,6) NOT NULL DEFAULT 0, `pension_er` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `nhf` DECIMAL(20,6) NOT NULL DEFAULT 0, `nhf_consent_date` DATE DEFAULT NULL, `nhis` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `nsitf_er` DECIMAL(20,6) NOT NULL DEFAULT 0, `itf_er` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `loan_recovered` DECIMAL(20,6) NOT NULL DEFAULT 0, `arrears_added` DECIMAL(20,6) NOT NULL DEFAULT 0, `arrears_recovered` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_earnings` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_deductions` DECIMAL(20,6) NOT NULL DEFAULT 0, `net_pay` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `employer_cost` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `ytd_gross` DECIMAL(20,6) NOT NULL DEFAULT 0, `ytd_taxable` DECIMAL(20,6) NOT NULL DEFAULT 0, `ytd_paye` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `ytd_pension_ee` DECIMAL(20,6) NOT NULL DEFAULT 0, `ytd_nhf` DECIMAL(20,6) NOT NULL DEFAULT 0, `ytd_net` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `bank_name` VARCHAR(96) DEFAULT NULL, `bank_code` VARCHAR(16) DEFAULT NULL, `account_no` VARCHAR(24) DEFAULT NULL,
  `pay_method` ENUM('bank','cash','cheque') NOT NULL DEFAULT 'bank', `token` CHAR(48) DEFAULT NULL,
  `emailed_at` DATETIME DEFAULT NULL, `viewed_at` DATETIME DEFAULT NULL, `note` VARCHAR(255), `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_payslip`), UNIQUE KEY `re` (`id_pulse_pr_run`,`id_pulse_pr_employee`), UNIQUE KEY `tok` (`token`),
  KEY `emp` (`id_pulse_pr_employee`,`period`), KEY `dept` (`period`,`department`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_payslip_line` (
  `id_pulse_pr_payslip_line` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_payslip` BIGINT UNSIGNED NOT NULL, `id_pulse_pr_run` INT UNSIGNED NOT NULL,
  `element_code` VARCHAR(24) NOT NULL, `element_name` VARCHAR(96) NOT NULL,
  `type` ENUM('earning','deduction','employer','information') NOT NULL DEFAULT 'earning',
  `amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `units` DECIMAL(12,3) NOT NULL DEFAULT 0, `rate` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `base_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `percent` DECIMAL(9,4) NOT NULL DEFAULT 0,
  `taxable` TINYINT(1) NOT NULL DEFAULT 1, `pensionable` TINYINT(1) NOT NULL DEFAULT 0, `prorated` TINYINT(1) NOT NULL DEFAULT 0,
  `gl_account` VARCHAR(16) DEFAULT NULL, `sequence` SMALLINT NOT NULL DEFAULT 0, `note` VARCHAR(160),
  PRIMARY KEY (`id_pulse_pr_payslip_line`), KEY `ps` (`id_pulse_pr_payslip`,`sequence`), KEY `run` (`id_pulse_pr_run`,`element_code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_loan` (
  `id_pulse_pr_loan` INT UNSIGNED NOT NULL AUTO_INCREMENT, `loan_no` VARCHAR(20) NOT NULL, `id_pulse_pr_employee` INT UNSIGNED NOT NULL,
  `type` ENUM('loan','salary_advance','asset','other') NOT NULL DEFAULT 'loan', `purpose` VARCHAR(160) DEFAULT NULL,
  `principal` DECIMAL(20,6) NOT NULL DEFAULT 0, `interest_pct` DECIMAL(6,3) NOT NULL DEFAULT 0, `interest_amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_repayable` DECIMAL(20,6) NOT NULL DEFAULT 0, `instalments` SMALLINT NOT NULL DEFAULT 1, `instalment_amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `first_period` VARCHAR(7) NOT NULL, `recovered` DECIMAL(20,6) NOT NULL DEFAULT 0, `balance` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `status` ENUM('applied','approved','disbursed','repaying','settled','written_off','rejected','cancelled') NOT NULL DEFAULT 'applied',
  `date_applied` DATE NOT NULL, `date_approved` DATE DEFAULT NULL, `approved_by` INT UNSIGNED, `date_disbursed` DATE DEFAULT NULL,
  `date_settled` DATE DEFAULT NULL, `note` VARCHAR(255), `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_loan`), UNIQUE KEY `no` (`loan_no`), KEY `emp` (`id_pulse_pr_employee`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_loan_schedule` (
  `id_pulse_pr_loan_schedule` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_loan` INT UNSIGNED NOT NULL, `seq` SMALLINT NOT NULL DEFAULT 1,
  `period` VARCHAR(7) NOT NULL, `due_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `paid_amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `status` ENUM('due','part','paid','deferred','waived') NOT NULL DEFAULT 'due', `id_pulse_pr_payslip` BIGINT UNSIGNED DEFAULT NULL, `note` VARCHAR(160),
  PRIMARY KEY (`id_pulse_pr_loan_schedule`), UNIQUE KEY `ls` (`id_pulse_pr_loan`,`seq`), KEY `p` (`period`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_arrears` (
  `id_pulse_pr_arrears` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_employee` INT UNSIGNED NOT NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'loan', `source_ref` VARCHAR(48) DEFAULT NULL, `description` VARCHAR(160) DEFAULT NULL,
  `amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `recovered` DECIMAL(20,6) NOT NULL DEFAULT 0, `balance` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `period_raised` VARCHAR(7) NOT NULL, `status` ENUM('open','part','cleared','waived') NOT NULL DEFAULT 'open',
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_arrears`), KEY `emp` (`id_pulse_pr_employee`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_tronc_pool` (
  `id_pulse_pr_tronc_pool` INT UNSIGNED NOT NULL AUTO_INCREMENT, `pool_no` VARCHAR(20) NOT NULL, `period` VARCHAR(7) NOT NULL,
  `period_from` DATE NOT NULL, `period_to` DATE NOT NULL,
  `basis` ENUM('points','hours','equal') NOT NULL DEFAULT 'points',
  `collected_fnb` DECIMAL(20,6) NOT NULL DEFAULT 0, `collected_rooms` DECIMAL(20,6) NOT NULL DEFAULT 0, `collected_manual` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `gross_pool` DECIMAL(20,6) NOT NULL DEFAULT 0, `admin_pct` DECIMAL(6,3) NOT NULL DEFAULT 0, `admin_amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `breakage_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `management_cap_pct` DECIMAL(6,3) NOT NULL DEFAULT 10,
  `management_capped_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `distributable` DECIMAL(20,6) NOT NULL DEFAULT 0, `distributed` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `rounding_residue` DECIMAL(20,6) NOT NULL DEFAULT 0, `participants` INT NOT NULL DEFAULT 0,
  `status` ENUM('draft','distributed','approved','paid','cancelled') NOT NULL DEFAULT 'draft',
  `source_note` VARCHAR(255), `approved_by` INT UNSIGNED, `date_approved` DATETIME DEFAULT NULL, `note` VARCHAR(255),
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_tronc_pool`), UNIQUE KEY `no` (`pool_no`), KEY `p` (`period`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_tronc_line` (
  `id_pulse_pr_tronc_line` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_tronc_pool` INT UNSIGNED NOT NULL, `id_pulse_pr_employee` INT UNSIGNED NOT NULL,
  `staff_no` VARCHAR(24) DEFAULT NULL, `employee_name` VARCHAR(128) DEFAULT NULL, `department` VARCHAR(32) NOT NULL DEFAULT 'general',
  `is_management` TINYINT(1) NOT NULL DEFAULT 0, `points` DECIMAL(10,3) NOT NULL DEFAULT 0, `hours` DECIMAL(10,3) NOT NULL DEFAULT 0,
  `dept_weight` DECIMAL(6,3) NOT NULL DEFAULT 1, `weighted_units` DECIMAL(14,4) NOT NULL DEFAULT 0, `share_pct` DECIMAL(9,5) NOT NULL DEFAULT 0,
  `amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `capped` TINYINT(1) NOT NULL DEFAULT 0, `paid_in_run` INT UNSIGNED DEFAULT NULL, `note` VARCHAR(160),
  PRIMARY KEY (`id_pulse_pr_tronc_line`), UNIQUE KEY `pe` (`id_pulse_pr_tronc_pool`,`id_pulse_pr_employee`), KEY `emp` (`id_pulse_pr_employee`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_tronc_weight` (
  `id_pulse_pr_tronc_weight` INT UNSIGNED NOT NULL AUTO_INCREMENT, `department` VARCHAR(32) NOT NULL, `weight` DECIMAL(6,3) NOT NULL DEFAULT 1,
  `default_points` DECIMAL(10,3) NOT NULL DEFAULT 10, `is_management` TINYINT(1) NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_pr_tronc_weight`), UNIQUE KEY `d` (`department`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_casual_batch` (
  `id_pulse_pr_casual_batch` INT UNSIGNED NOT NULL AUTO_INCREMENT, `batch_no` VARCHAR(20) NOT NULL,
  `week_start` DATE NOT NULL, `week_end` DATE NOT NULL, `period` VARCHAR(7) NOT NULL, `department` VARCHAR(32) DEFAULT NULL,
  `pay_method` ENUM('cash','bank','mixed') NOT NULL DEFAULT 'cash', `pay_date` DATE NOT NULL,
  `status` ENUM('draft','approved','paid','posted','cancelled') NOT NULL DEFAULT 'draft',
  `headcount` INT NOT NULL DEFAULT 0, `total_gross` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_tax` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_net` DECIMAL(20,6) NOT NULL DEFAULT 0, `id_acc_journal` INT UNSIGNED DEFAULT NULL,
  `approved_by` INT UNSIGNED, `date_approved` DATETIME DEFAULT NULL, `note` VARCHAR(255), `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_casual_batch`), UNIQUE KEY `no` (`batch_no`), KEY `w` (`week_start`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_casual_line` (
  `id_pulse_pr_casual_line` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_casual_batch` INT UNSIGNED NOT NULL,
  `id_pulse_pr_employee` INT UNSIGNED DEFAULT NULL, `staff_no` VARCHAR(24) DEFAULT NULL, `name` VARCHAR(128) NOT NULL,
  `phone` VARCHAR(32) DEFAULT NULL, `department` VARCHAR(32) NOT NULL DEFAULT 'general', `role` VARCHAR(96) DEFAULT NULL,
  `basis` ENUM('daily','hourly','per_shift') NOT NULL DEFAULT 'daily', `units` DECIMAL(10,3) NOT NULL DEFAULT 0, `rate` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `gross` DECIMAL(20,6) NOT NULL DEFAULT 0, `tax` DECIMAL(20,6) NOT NULL DEFAULT 0, `other_deduction` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `net` DECIMAL(20,6) NOT NULL DEFAULT 0, `source` ENUM('pulsetime','manual') NOT NULL DEFAULT 'manual',
  `bank_code` VARCHAR(16) DEFAULT NULL, `account_no` VARCHAR(24) DEFAULT NULL,
  `signed_off_by` INT UNSIGNED, `note` VARCHAR(160),
  PRIMARY KEY (`id_pulse_pr_casual_line`), KEY `b` (`id_pulse_pr_casual_batch`), KEY `emp` (`id_pulse_pr_employee`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_bank` (
  `id_pulse_pr_bank` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(96) NOT NULL, `nibss_code` VARCHAR(16) DEFAULT NULL,
  `sort_code` VARCHAR(16) DEFAULT NULL, `swift` VARCHAR(16) DEFAULT NULL, `country` VARCHAR(2) NOT NULL DEFAULT 'NG',
  `template` VARCHAR(32) NOT NULL DEFAULT 'nibss' COMMENT 'nibss | generic — generic uses the column map in settings',
  `active` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_pulse_pr_bank`), UNIQUE KEY `n` (`name`), KEY `c` (`nibss_code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_bank_file` (
  `id_pulse_pr_bank_file` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_run` INT UNSIGNED DEFAULT NULL, `id_pulse_pr_casual_batch` INT UNSIGNED DEFAULT NULL,
  `file_no` VARCHAR(24) NOT NULL, `bank_name` VARCHAR(96) DEFAULT NULL, `bank_code` VARCHAR(16) DEFAULT NULL, `template` VARCHAR(32) NOT NULL DEFAULT 'nibss',
  `filename` VARCHAR(128) NOT NULL, `record_count` INT NOT NULL DEFAULT 0, `control_total` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `checksum` CHAR(40) DEFAULT NULL, `body` MEDIUMTEXT, `value_date` DATE NOT NULL,
  `status` ENUM('generated','downloaded','sent','acknowledged','void') NOT NULL DEFAULT 'generated',
  `id_employee` INT UNSIGNED, `note` VARCHAR(255), `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_bank_file`), UNIQUE KEY `fn` (`file_no`), KEY `run` (`id_pulse_pr_run`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_remittance` (
  `id_pulse_pr_remittance` INT UNSIGNED NOT NULL AUTO_INCREMENT, `scheme` VARCHAR(24) NOT NULL COMMENT 'paye|pension|nsitf|itf|nhf|nhis',
  `period` VARCHAR(7) NOT NULL, `authority` VARCHAR(96) DEFAULT NULL, `amount_due` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `amount_paid` DECIMAL(20,6) NOT NULL DEFAULT 0, `due_date` DATE DEFAULT NULL, `date_paid` DATE DEFAULT NULL,
  `reference` VARCHAR(96) DEFAULT NULL, `status` ENUM('due','part','paid','overdue','waived') NOT NULL DEFAULT 'due',
  `penalty` DECIMAL(20,6) NOT NULL DEFAULT 0, `note` VARCHAR(255), `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_remittance`), UNIQUE KEY `sp` (`scheme`,`period`), KEY `st` (`status`,`due_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pr_audit` (
  `id_pulse_pr_audit` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pr_run` INT UNSIGNED DEFAULT NULL, `entity` VARCHAR(32) NOT NULL DEFAULT 'run',
  `id_entity` INT UNSIGNED DEFAULT NULL, `event` VARCHAR(48) NOT NULL, `detail` TEXT, `id_employee` INT UNSIGNED, `ip` VARCHAR(45) DEFAULT NULL,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pr_audit`), KEY `run` (`id_pulse_pr_run`), KEY `e` (`entity`,`id_entity`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_pr_country` (`code`,`name`,`currency`,`tax_year_start`,`statutory_class`,`paye_mode`,`paye_basis`,`verified`,`note`,`active`,`date_add`,`date_upd`) VALUES
('NG','Nigeria','NGN','01-01','PulsePrStatutoryNigeria','cumulative','annual',1,'Nigeria Tax Act 2025, effective 1 January 2026. CRA abolished; rent relief replaces it; NHF voluntary.',1,NOW(),NOW()),
('GH','Ghana','GHS','01-01','PulsePrStatutoryGeneric','non_cumulative','monthly',0,'STARTING POINT ONLY — monthly PAYE bands and SSNIT rates must be verified against the current GRA/SSNIT circulars before payroll is run on this pack.',1,NOW(),NOW());

INSERT IGNORE INTO `PREFIX_pulse_pr_tax_band` (`country`,`regime`,`seq`,`band_from`,`band_to`,`rate_pct`,`basis`,`effective_from`,`effective_to`,`note`) VALUES
('NG','paye',1,0,800000,0,'annual','2026-01-01',NULL,'Nigeria Tax Act 2025 — first N800,000 nil'),
('NG','paye',2,800000,3000000,15,'annual','2026-01-01',NULL,'next N2,200,000 at 15%'),
('NG','paye',3,3000000,12000000,18,'annual','2026-01-01',NULL,'next N9,000,000 at 18%'),
('NG','paye',4,12000000,25000000,21,'annual','2026-01-01',NULL,'next N13,000,000 at 21%'),
('NG','paye',5,25000000,50000000,23,'annual','2026-01-01',NULL,'next N25,000,000 at 23%'),
('NG','paye',6,50000000,NULL,25,'annual','2026-01-01',NULL,'above N50,000,000 at 25%'),
('NG','paye',1,0,300000,7,'annual','2011-06-14','2025-12-31','PITA 2011 historical set — kept so a back-dated 2025 recalculation still works'),
('NG','paye',2,300000,600000,11,'annual','2011-06-14','2025-12-31','PITA 2011 historical set'),
('NG','paye',3,600000,1100000,15,'annual','2011-06-14','2025-12-31','PITA 2011 historical set'),
('NG','paye',4,1100000,1600000,19,'annual','2011-06-14','2025-12-31','PITA 2011 historical set'),
('NG','paye',5,1600000,3200000,21,'annual','2011-06-14','2025-12-31','PITA 2011 historical set'),
('NG','paye',6,3200000,NULL,24,'annual','2011-06-14','2025-12-31','PITA 2011 historical set'),
('GH','paye',1,0,490,0,'monthly','2024-01-01',NULL,'UNVERIFIED starting point — first GHS 490 per month nil'),
('GH','paye',2,490,600,5,'monthly','2024-01-01',NULL,'UNVERIFIED starting point'),
('GH','paye',3,600,730,10,'monthly','2024-01-01',NULL,'UNVERIFIED starting point'),
('GH','paye',4,730,3896.67,17.5,'monthly','2024-01-01',NULL,'UNVERIFIED starting point'),
('GH','paye',5,3896.67,19896.67,25,'monthly','2024-01-01',NULL,'UNVERIFIED starting point'),
('GH','paye',6,19896.67,50416.67,30,'monthly','2024-01-01',NULL,'UNVERIFIED starting point'),
('GH','paye',7,50416.67,NULL,35,'monthly','2024-01-01',NULL,'UNVERIFIED starting point');

INSERT IGNORE INTO `PREFIX_pulse_pr_relief` (`country`,`code`,`name`,`type`,`value_pct`,`value_fixed`,`cap`,`base`,`basis`,`requires_evidence`,`declaration_code`,`conditions`,`sort`,`effective_from`,`effective_to`,`active`) VALUES
('NG','RENT','Rent relief','capped_percent',20,0,500000,'DECLARED','annual',1,'RENT','20% of annual rent actually paid, capped at N500,000. Only where the employee has declared the rent and the evidence is on file. Nigeria Tax Act 2025.',10,'2026-01-01',NULL,1),
('NG','PENSION','Pension contribution (employee)','percent_of',0,0,NULL,'CONTRIB:PENSION','annual',0,NULL,'Statutory employee pension contribution is deducted before tax.',20,'2026-01-01',NULL,1),
('NG','NHF','National Housing Fund contribution','percent_of',0,0,NULL,'CONTRIB:NHF','annual',0,'NHF_CONSENT','Deductible before tax only where the employee has actually contributed — NHF is voluntary for private-sector employees.',30,'2026-01-01',NULL,1),
('NG','LIFE','Life assurance premium','percent_of',100,0,NULL,'DECLARED','annual',1,'LIFE','Premium on the life of the employee or spouse, evidenced by the policy and receipts.',40,'2026-01-01',NULL,1),
('NG','MORTGAGE','Mortgage interest (owner-occupied)','percent_of',100,0,NULL,'DECLARED','annual',1,'MORTGAGE','Interest on a loan for an owner-occupied home, evidenced by the lender statement.',50,'2026-01-01',NULL,1),
('NG','CRA','Consolidated Relief Allowance','greater_of',20,200000,NULL,'GROSS','annual',0,NULL,'ABOLISHED from 1 January 2026. Retained with an effective-to date so a back-dated 2025 recalculation still works: higher of N200,000 or 1% of gross, plus 20% of gross.',10,'2011-06-14','2025-12-31',1),
('NG','PENSION','Pension contribution (employee)','percent_of',0,0,NULL,'CONTRIB:PENSION','annual',0,NULL,'Pre-2026 rule, unchanged.',20,'2011-06-14','2025-12-31',1),
('NG','NHF','National Housing Fund contribution','percent_of',0,0,NULL,'CONTRIB:NHF','annual',0,NULL,'Pre-2026: NHF was mandatory for employees earning above the threshold.',30,'2011-06-14','2025-12-31',1),
('GH','SSNIT','SSNIT employee contribution','percent_of',0,0,NULL,'CONTRIB:SSNIT','monthly',0,NULL,'UNVERIFIED starting point — employee SSNIT is deducted before PAYE in Ghana.',10,'2024-01-01',NULL,1);

INSERT IGNORE INTO `PREFIX_pulse_pr_contribution` (`country`,`code`,`name`,`employee_pct`,`employer_pct`,`base`,`floor`,`ceiling`,`frequency`,`mode`,`consent_code`,`pre_tax`,`employer_min_staff`,`employer_min_turnover`,`remit_within_days`,`remit_rule`,`penalty_pct_month`,`gl_liability`,`gl_expense`,`sort`,`effective_from`,`effective_to`,`active`) VALUES
('NG','PENSION','Pension (PenCom RSA)',8,10,'BHT',0,NULL,'monthly','mandatory',NULL,1,3,0,7,'Remit to the employee RSA within 7 working days of paying salary. Late remittance attracts a 2% per month penalty.',2,'2150','6110',10,'2026-01-01',NULL,1),
('NG','NSITF','NSITF employee compensation scheme',0,1,'GROSS',0,NULL,'monthly','mandatory',NULL,0,0,0,10,'Employer-borne, 1% of total monthly emolument, every employer regardless of size, remitted monthly by about the 10th.',0,'2160','6110',20,'2026-01-01',NULL,1),
('NG','ITF','Industrial Training Fund',0,1,'GROSS',0,NULL,'annual','mandatory',NULL,0,5,50000000,0,'Employer-borne, 1% of annual gross payroll where the employer has 5 or more staff or N50m+ turnover. Accrued monthly, remitted by about 1 April.',0,'2160','6110',30,'2026-01-01',NULL,1),
('NG','NHF','National Housing Fund',2.5,0,'BASIC',0,NULL,'monthly','opt_in','NHF_CONSENT',1,0,0,30,'VOLUNTARY for private-sector employees. Deduct only where a dated employee consent is on file.',0,'2160',NULL,40,'2026-01-01',NULL,1),
('NG','NHIS','NHIA health insurance',1.75,3.25,'BASIC',0,NULL,'monthly','opt_in','NHIS_CONSENT',1,0,0,30,'Scheme-dependent. Off unless the property has enrolled and the employee has opted in.',0,'2160','6110',50,'2026-01-01',NULL,0),
('NG','PENSION','Pension (PenCom RSA)',8,10,'BHT',0,NULL,'monthly','mandatory',NULL,1,3,0,7,'Pre-2026 rule, unchanged.',2,'2150','6110',10,'2004-06-25','2025-12-31',1),
('NG','NHF','National Housing Fund',2.5,0,'BASIC',0,NULL,'monthly','mandatory',NULL,1,0,0,30,'Pre-2026: mandatory 2.5% of basic for employees earning N3,000 or more per annum.',0,'2160',NULL,40,'2004-06-25','2025-12-31',1),
('GH','SSNIT','SSNIT Tier 1 and 2',5.5,13,'BASIC',0,NULL,'monthly','mandatory',NULL,1,0,0,14,'UNVERIFIED starting point — employee 5.5%, employer 13% of basic salary, remitted by the 14th of the following month.',0,'2160','6110',10,'2024-01-01',NULL,1);

INSERT IGNORE INTO `PREFIX_pulse_pr_element` (`code`,`name`,`type`,`calc`,`percent_of`,`default_value`,`formula`,`statutory_code`,`taxable`,`pensionable`,`nsitfable`,`in_basic`,`proratable`,`recurring`,`gl_account`,`department`,`sequence`,`show_on_payslip`,`active`,`note`) VALUES
('BASIC','Basic salary','earning','percent','PACKAGE',0,'','',1,1,1,1,1,1,'','',10,1,1,'Part of the BHT pension base'),
('HOUSING','Housing allowance','earning','percent','PACKAGE',0,'','',1,1,1,0,1,1,'','',20,1,1,'Part of the BHT pension base'),
('TRANSPORT','Transport allowance','earning','percent','PACKAGE',0,'','',1,1,1,0,1,1,'','',30,1,1,'Part of the BHT pension base'),
('MEAL','Meal allowance','earning','percent','PACKAGE',0,'','',1,0,1,0,1,1,'','',40,1,1,'Taxable, not pensionable'),
('UTILITY','Utility allowance','earning','percent','PACKAGE',0,'','',1,0,1,0,1,1,'','',50,1,1,'Taxable, not pensionable'),
('LEAVE','Leave allowance','earning','fixed','',0,'','',1,0,1,0,0,0,'','',60,1,1,'Paid once a year, not prorated, not annualised'),
('SHIFT','Shift / night allowance','earning','rate_units','',0,'','',1,0,1,0,0,1,'','',70,1,1,'Rate per night shift from the approved timesheet'),
('OT','Overtime','earning','rate_units','',0,'','',1,0,1,0,0,0,'','',75,1,1,'Hours from the approved timesheet at the overtime rate'),
('TRONC','Service charge distribution','earning','fixed','',0,'','',1,0,1,0,0,0,'','',80,1,1,'Taxable, not pensionable — excluded from the BHT base'),
('BONUS','Bonus','earning','fixed','',0,'','',1,0,1,0,0,0,'','',85,1,1,'One-off, not annualised into the projection'),
('ACTING','Acting allowance','earning','fixed','',0,'','',1,0,1,0,1,1,'','',88,1,1,''),
('BACKPAY','Back pay / arrears of salary','earning','fixed','',0,'','',1,0,1,0,0,0,'','',90,1,1,'One-off'),
('PAYE','PAYE income tax','deduction','statutory','',0,'','PAYE',0,0,0,0,0,1,'2155','',200,1,1,'Computed by the statutory pack for the employee country'),
('PENSION','Pension contribution (employee)','deduction','statutory','',0,'','PENSION',0,0,0,0,0,1,'2150','',210,1,1,'8% of BHT'),
('NHF','National Housing Fund','deduction','statutory','',0,'','NHF',0,0,0,0,0,1,'2160','',220,1,1,'Voluntary — deducted only with a recorded, dated consent'),
('NHIS','Health insurance (employee)','deduction','statutory','',0,'','NHIS',0,0,0,0,0,1,'2160','',230,1,1,'Off by default'),
('PENSVOL','Voluntary pension contribution','deduction','fixed','',0,'','',0,0,0,0,0,1,'2150','',240,1,1,'Additional voluntary contribution, pre-tax up to the PenCom cap'),
('SSNIT','SSNIT contribution (employee)','deduction','statutory','',0,'','SSNIT',0,0,0,0,0,1,'2160','',245,1,1,'Ghana pack — employee social security, pre-tax'),
('LOAN','Staff loan repayment','deduction','statutory','',0,'','LOAN',0,0,0,0,0,1,'1240','',250,1,1,'Automatic recovery from the loan schedule'),
('ADVANCE','Salary advance recovery','deduction','statutory','',0,'','LOAN',0,0,0,0,0,1,'1240','',255,1,1,''),
('ARREARS','Arrears recovery','deduction','statutory','',0,'','ARREARS',0,0,0,0,0,1,'1240','',258,1,1,'Recovery of a shortfall parked in an earlier period'),
('UNION','Union / welfare dues','deduction','fixed','',0,'','',0,0,0,0,0,1,'2130','',260,1,1,''),
('COOP','Cooperative deduction','deduction','fixed','',0,'','',0,0,0,0,0,1,'2130','',265,1,1,''),
('DAMAGE','Damage / shortage recovery','deduction','fixed','',0,'','',0,0,0,0,0,1,'2130','',270,1,1,''),
('ABSENCE','Unpaid absence','deduction','rate_units','',0,'','',0,0,0,0,0,1,'','',275,1,1,'Days of unpaid leave at the daily rate'),
('ER_PENSION','Pension contribution (employer)','employer','statutory','',0,'','PENSION_ER',0,0,0,0,0,1,'2150','',300,1,1,'10% of BHT'),
('ER_NSITF','NSITF (employer)','employer','statutory','',0,'','NSITF',0,0,0,0,0,1,'2160','',310,1,1,'1% of gross'),
('ER_ITF','ITF (employer, accrued)','employer','statutory','',0,'','ITF',0,0,0,0,0,1,'2160','',320,1,1,'1% of gross accrued monthly, remitted annually'),
('ER_NHIS','Health insurance (employer)','employer','statutory','',0,'','NHIS_ER',0,0,0,0,0,1,'2160','',330,1,1,''),
('ER_SSNIT','SSNIT contribution (employer)','employer','statutory','',0,'','SSNIT_ER',0,0,0,0,0,1,'2160','',340,1,1,'Ghana pack — employer social security'),
('PAYE_OVER','PAYE over-deducted (claim from the tax authority)','information','statutory','',0,'','',0,0,0,0,0,0,'','',400,1,1,'Raised on a final settlement when the year-to-date tax already paid exceeds the tax actually due'),
('ARREARS_NEW','Shortfall parked as arrears','information','statutory','',0,'','',0,0,0,0,0,0,'','',410,1,1,'A recovery that would have pushed net pay below the protected floor');

INSERT IGNORE INTO `PREFIX_pulse_pr_tronc_weight` (`department`,`weight`,`default_points`,`is_management`,`active`) VALUES
('fnb',1.200,10,0,1),('rooms',1.000,10,0,1),('housekeeping',1.000,10,0,1),('laundry',0.800,10,0,1),
('maintenance',0.700,10,0,1),('security',0.700,10,0,1),('accounts',0.600,10,0,1),('sales',0.600,10,0,1),
('admin',0.600,10,0,1),('management',1.000,10,1,1),('general',0.600,10,0,1);

INSERT IGNORE INTO `PREFIX_pulse_pr_bank` (`name`,`nibss_code`,`sort_code`,`swift`,`country`,`template`,`active`,`sort`) VALUES
('Access Bank','044','044150149','ABNGNGLA','NG','nibss',1,10),
('Citibank Nigeria','023','023150005','CITINGLA','NG','nibss',1,20),
('Ecobank Nigeria','050','050150010','ECOCNGLA','NG','nibss',1,30),
('Fidelity Bank','070','070150003','FIDTNGLA','NG','nibss',1,40),
('First Bank of Nigeria','011','011151003','FBNINGLA','NG','nibss',1,50),
('First City Monument Bank','214','214150018','FCMBNGLA','NG','nibss',1,60),
('Globus Bank','00103','000103001','GLBKNGLA','NG','nibss',1,65),
('Guaranty Trust Bank','058','058152036','GTBINGLA','NG','nibss',1,70),
('Heritage Bank','030','030159992','HBCLNGLA','NG','nibss',1,75),
('Keystone Bank','082','082150017','PLNINGLA','NG','nibss',1,80),
('Kuda Microfinance Bank','50211','090267001','','NG','nibss',1,85),
('Opay Digital Services','999992','100004001','','NG','nibss',1,88),
('Moniepoint MFB','50515','090405001','','NG','nibss',1,89),
('Polaris Bank','076','076151006','PRDTNGLA','NG','nibss',1,90),
('Providus Bank','101','101152001','','NG','nibss',1,95),
('Stanbic IBTC Bank','221','221159522','SBICNGLA','NG','nibss',1,100),
('Standard Chartered Bank','068','068150015','SCBLNGLA','NG','nibss',1,105),
('Sterling Bank','232','232150029','NAMENGLA','NG','nibss',1,110),
('SunTrust Bank','100','100150003','','NG','nibss',1,115),
('Union Bank of Nigeria','032','032080474','UBNINGLA','NG','nibss',1,120),
('United Bank for Africa','033','033153513','UNAFNGLA','NG','nibss',1,125),
('Unity Bank','215','215082334','ICITNGLA','NG','nibss',1,130),
('Wema Bank','035','035150103','WEMANGLA','NG','nibss',1,135),
('Zenith Bank','057','057150013','ZEIBNGLA','NG','nibss',1,140);

INSERT IGNORE INTO `PREFIX_pulse_pr_employee_element` (`id_pulse_pr_employee`,`grade`,`element_code`,`amount`,`percent`,`units`,`effective_from`,`effective_to`,`note`,`date_add`) VALUES
(NULL,'DEFAULT','BASIC',0,40,0,'2020-01-01',NULL,'40% of the contractual monthly package',NOW()),
(NULL,'DEFAULT','HOUSING',0,25,0,'2020-01-01',NULL,'25% of the contractual monthly package',NOW()),
(NULL,'DEFAULT','TRANSPORT',0,15,0,'2020-01-01',NULL,'15% of the contractual monthly package',NOW()),
(NULL,'DEFAULT','MEAL',0,10,0,'2020-01-01',NULL,'10% of the contractual monthly package',NOW()),
(NULL,'DEFAULT','UTILITY',0,10,0,'2020-01-01',NULL,'10% of the contractual monthly package',NOW());
