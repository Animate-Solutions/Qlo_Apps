CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_device` (
  `id_pulse_ta_device` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(64) NOT NULL, `brand` VARCHAR(32) NOT NULL DEFAULT 'simulator',
  `adapter` VARCHAR(64) NOT NULL DEFAULT 'PulseTaSimulator', `location` VARCHAR(64) NOT NULL DEFAULT 'Staff entrance', `department` VARCHAR(32) NOT NULL DEFAULT '',
  `mode` ENUM('pull','push','file') NOT NULL DEFAULT 'pull' COMMENT 'pull=we poll it, push=it dials us (ADMS/event listener), file=csv drop',
  `protocol` ENUM('tcp','udp','http','https','local','file') NOT NULL DEFAULT 'tcp', `host` VARCHAR(128) NOT NULL DEFAULT '', `port` SMALLINT UNSIGNED NOT NULL DEFAULT 4370,
  `endpoint` VARCHAR(160) NOT NULL DEFAULT '/', `serial` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'device serial - the identity a push device presents',
  `credentials_enc` TEXT COMMENT 'PulseCoreService::encrypt of {user,password,comm_key,api_key,push_key}', `options_json` TEXT,
  `timezone` VARCHAR(48) NOT NULL DEFAULT 'Africa/Lagos', `direction_mode` ENUM('in','out','auto','both') NOT NULL DEFAULT 'both' COMMENT 'both=trust the device state byte, auto=alternate, in/out=fixed reader',
  `poll_interval_min` SMALLINT NOT NULL DEFAULT 10, `timeout_sec` SMALLINT NOT NULL DEFAULT 8, `retries` TINYINT NOT NULL DEFAULT 2,
  `clear_after_pull` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'never default this on - the device log is the only copy until we have it',
  `test_mode` TINYINT(1) NOT NULL DEFAULT 0, `firmware` VARCHAR(64) NOT NULL DEFAULT '', `device_users` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_poll_at` DATETIME DEFAULT NULL, `last_punch_at` DATETIME DEFAULT NULL, `last_seen_at` DATETIME DEFAULT NULL, `last_cursor` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'vendor paging stamp (ADMS Stamp, ISAPI position)',
  `punch_count` INT UNSIGNED NOT NULL DEFAULT 0, `error_count` INT UNSIGNED NOT NULL DEFAULT 0, `last_error` VARCHAR(255) NOT NULL DEFAULT '',
  `health` ENUM('unknown','online','degraded','offline') NOT NULL DEFAULT 'unknown', `status` ENUM('pending','active','blocked') NOT NULL DEFAULT 'pending',
  `claimed_by` INT UNSIGNED DEFAULT NULL, `claimed_at` DATETIME DEFAULT NULL, `note` VARCHAR(255) NOT NULL DEFAULT '',
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_device`), UNIQUE KEY `name` (`name`), KEY `sn` (`serial`), KEY `st` (`status`,`health`), KEY `mode` (`mode`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_device_cmd` (
  `id_pulse_ta_device_cmd` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_ta_device` INT UNSIGNED NOT NULL,
  `cmd` TEXT NOT NULL COMMENT 'ADMS command body, e.g. DATA UPDATE USERINFO PIN=12 Name=...', `kind` VARCHAR(32) NOT NULL DEFAULT 'other',
  `status` ENUM('queued','sent','done','failed','expired') NOT NULL DEFAULT 'queued', `sent_at` DATETIME DEFAULT NULL, `replied_at` DATETIME DEFAULT NULL,
  `return_code` VARCHAR(16) NOT NULL DEFAULT '', `reply` VARCHAR(255) NOT NULL DEFAULT '', `attempts` SMALLINT NOT NULL DEFAULT 0,
  `expires_at` DATETIME DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_device_cmd`), KEY `q` (`id_pulse_ta_device`,`status`,`id_pulse_ta_device_cmd`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_device_log` (
  `id_pulse_ta_device_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_ta_device` INT UNSIGNED DEFAULT NULL, `serial` VARCHAR(64) NOT NULL DEFAULT '',
  `direction` ENUM('in','out') NOT NULL DEFAULT 'in', `path` VARCHAR(64) NOT NULL DEFAULT '', `table_name` VARCHAR(32) NOT NULL DEFAULT '',
  `ip` VARCHAR(45) NOT NULL DEFAULT '', `bytes` INT UNSIGNED NOT NULL DEFAULT 0, `rows_in` INT UNSIGNED NOT NULL DEFAULT 0, `rows_kept` INT UNSIGNED NOT NULL DEFAULT 0,
  `result` ENUM('ok','rejected','pending_device','rate_limited','too_large','error') NOT NULL DEFAULT 'ok', `message` VARCHAR(255) NOT NULL DEFAULT '',
  `sample` VARCHAR(512) NOT NULL DEFAULT '' COMMENT 'first line of the body, stored escaped for the admin screen', `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_device_log`), KEY `sn` (`serial`,`date_add`), KEY `d` (`id_pulse_ta_device`,`date_add`), KEY `t` (`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_staff` (
  `id_pulse_ta_staff` INT UNSIGNED NOT NULL AUTO_INCREMENT, `staff_no` VARCHAR(24) NOT NULL, `firstname` VARCHAR(64) NOT NULL DEFAULT '', `lastname` VARCHAR(64) NOT NULL DEFAULT '',
  `department` VARCHAR(32) NOT NULL DEFAULT 'admin', `section` VARCHAR(48) NOT NULL DEFAULT '', `position` VARCHAR(64) NOT NULL DEFAULT '',
  `id_hr_employee` INT UNSIGNED DEFAULT NULL COMMENT 'pulse_hr_employee when Pulse HR is installed', `id_employee` INT UNSIGNED DEFAULT NULL COMMENT 'back-office user', `id_pos_staff` INT UNSIGNED DEFAULT NULL,
  `id_pulse_ta_shift` INT UNSIGNED DEFAULT NULL COMMENT 'default shift when no roster row exists', `pay_basis` ENUM('monthly','daily','hourly','shift') NOT NULL DEFAULT 'monthly',
  `hourly_rate` DECIMAL(20,6) NOT NULL DEFAULT 0, `daily_rate` DECIMAL(20,6) NOT NULL DEFAULT 0, `ot_eligible` TINYINT(1) NOT NULL DEFAULT 1,
  `source` ENUM('local','hr') NOT NULL DEFAULT 'local', `status` ENUM('active','suspended','exited') NOT NULL DEFAULT 'active', `exit_date` DATE DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_staff`), UNIQUE KEY `staff_no` (`staff_no`), KEY `hr` (`id_hr_employee`), KEY `dept` (`department`,`status`), KEY `emp` (`id_employee`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_enrolment` (
  `id_pulse_ta_enrolment` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_ta_staff` INT UNSIGNED DEFAULT NULL COMMENT 'NULL = a device user id the reader told us about that nobody has mapped yet',
  `id_pulse_ta_device` INT UNSIGNED NOT NULL,
  `device_user_id` VARCHAR(32) NOT NULL COMMENT 'the PIN/employeeNo/userID as that device knows it - it differs per brand and per device',
  `device_name` VARCHAR(64) NOT NULL DEFAULT '', `card_no` VARCHAR(32) NOT NULL DEFAULT '', `privilege` SMALLINT NOT NULL DEFAULT 0,
  `has_finger` TINYINT(1) NOT NULL DEFAULT 0, `has_face` TINYINT(1) NOT NULL DEFAULT 0, `has_palm` TINYINT(1) NOT NULL DEFAULT 0, `has_card` TINYINT(1) NOT NULL DEFAULT 0, `has_password` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('queued','pushed','failed','removed') NOT NULL DEFAULT 'queued', `attempts` SMALLINT NOT NULL DEFAULT 0, `last_error` VARCHAR(255) NOT NULL DEFAULT '',
  `pushed_at` DATETIME DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_enrolment`), UNIQUE KEY `dev_user` (`id_pulse_ta_device`,`device_user_id`), KEY `staff` (`id_pulse_ta_staff`), KEY `st` (`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_punch` (
  `id_pulse_ta_punch` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_ta_device` INT UNSIGNED DEFAULT NULL, `device_serial` VARCHAR(64) NOT NULL DEFAULT '',
  `employee_ref` VARCHAR(32) NOT NULL COMMENT 'device user id exactly as the device sent it', `id_pulse_ta_staff` INT UNSIGNED DEFAULT NULL COMMENT 'resolved through pulse_ta_enrolment - NULL means unmatched',
  `punched_at` DATETIME NOT NULL COMMENT 'device local time normalised to the shop timezone', `device_time` DATETIME DEFAULT NULL COMMENT 'as the device stated it, before timezone normalisation',
  `direction` ENUM('in','out','break_out','break_in','ot_in','ot_out','unknown') NOT NULL DEFAULT 'unknown',
  `verify_mode` ENUM('finger','face','card','password','palm','iris','vein','mobile','manual','other') NOT NULL DEFAULT 'other',
  `work_code` VARCHAR(16) NOT NULL DEFAULT '', `source` ENUM('device','mobile','manual','import','pos','adjustment') NOT NULL DEFAULT 'device',
  `latitude` DECIMAL(10,7) DEFAULT NULL, `longitude` DECIMAL(10,7) DEFAULT NULL, `accuracy_m` SMALLINT DEFAULT NULL,
  `business_date` DATE NOT NULL COMMENT 'attribution date at ingest - the engine may move the punch to the shift date',
  `raw` TEXT COMMENT 'the exact vendor row this punch came from - evidence for a dispute',
  `dedupe_hash` CHAR(40) NOT NULL COMMENT 'sha1(device|employee_ref|punched_at) - the append-only guard',
  `id_employee_entered` INT UNSIGNED DEFAULT NULL COMMENT 'who keyed a manual punch', `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_punch`), UNIQUE KEY `dedupe` (`dedupe_hash`), KEY `staff_time` (`id_pulse_ta_staff`,`punched_at`),
  KEY `ref` (`device_serial`,`employee_ref`,`punched_at`), KEY `bdate` (`business_date`), KEY `dev` (`id_pulse_ta_device`,`punched_at`), KEY `unmatched` (`id_pulse_ta_staff`,`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_adjustment` (
  `id_pulse_ta_adjustment` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_ta_staff` INT UNSIGNED NOT NULL, `business_date` DATE NOT NULL,
  `type` ENUM('add_punch','ignore_punch','set_minutes','add_overtime','paid_absence','unpaid_absence','waive_late') NOT NULL DEFAULT 'add_punch',
  `punched_at` DATETIME DEFAULT NULL, `direction` ENUM('in','out','break_out','break_in','ot_in','ot_out','unknown') NOT NULL DEFAULT 'unknown',
  `id_pulse_ta_punch` BIGINT UNSIGNED DEFAULT NULL COMMENT 'the evidence row an ignore_punch refers to - the punch itself is never edited',
  `minutes` INT NOT NULL DEFAULT 0, `reason` VARCHAR(255) NOT NULL DEFAULT '', `id_pulse_ta_exception` BIGINT UNSIGNED DEFAULT NULL,
  `id_employee_requested` INT UNSIGNED DEFAULT NULL, `id_employee_approver` INT UNSIGNED DEFAULT NULL, `approved_at` DATETIME DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','void') NOT NULL DEFAULT 'pending', `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_adjustment`), KEY `sd` (`id_pulse_ta_staff`,`business_date`,`status`), KEY `exc` (`id_pulse_ta_exception`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_shift` (
  `id_pulse_ta_shift` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(16) NOT NULL, `name` VARCHAR(64) NOT NULL, `department` VARCHAR(32) NOT NULL DEFAULT '',
  `start_time` TIME NOT NULL DEFAULT '08:00:00', `end_time` TIME NOT NULL DEFAULT '17:00:00',
  `crosses_midnight` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'end_time is on the following calendar day', `is_night` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'attracts the night allowance Payroll reads',
  `break_minutes` SMALLINT NOT NULL DEFAULT 60, `break_paid` TINYINT(1) NOT NULL DEFAULT 0, `break_punched` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'staff punch out for break instead of a flat deduction',
  `grace_in_min` SMALLINT NOT NULL DEFAULT 10, `grace_out_min` SMALLINT NOT NULL DEFAULT 10,
  `window_before_min` SMALLINT NOT NULL DEFAULT 120 COMMENT 'how early a punch may be and still belong to this shift', `window_after_min` SMALLINT NOT NULL DEFAULT 180,
  `min_shift_min` SMALLINT NOT NULL DEFAULT 240, `max_shift_min` SMALLINT NOT NULL DEFAULT 900 COMMENT 'above this the pairing is suspect and raises an exception',
  `is_split` TINYINT(1) NOT NULL DEFAULT 0, `split2_start` TIME DEFAULT NULL, `split2_end` TIME DEFAULT NULL,
  `paid_minutes` SMALLINT NOT NULL DEFAULT 480 COMMENT 'nominal paid minutes used for absence and proration', `colour` VARCHAR(7) NOT NULL DEFAULT '#2e86c1',
  `active` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_shift`), UNIQUE KEY `code` (`code`), KEY `dept` (`department`,`active`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_roster` (
  `id_pulse_ta_roster` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_ta_staff` INT UNSIGNED NOT NULL, `roster_date` DATE NOT NULL,
  `id_pulse_ta_shift` INT UNSIGNED DEFAULT NULL COMMENT 'NULL with day_type=rest means a rostered day off',
  `day_type` ENUM('work','rest','leave','holiday','training','off_site') NOT NULL DEFAULT 'work', `source` ENUM('local','hr','auto') NOT NULL DEFAULT 'local',
  `note` VARCHAR(128) NOT NULL DEFAULT '', `published` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_roster`), UNIQUE KEY `sd` (`id_pulse_ta_staff`,`roster_date`), KEY `d` (`roster_date`,`id_pulse_ta_shift`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_timesheet` (
  `id_pulse_ta_timesheet` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_ta_staff` INT UNSIGNED NOT NULL, `business_date` DATE NOT NULL,
  `department` VARCHAR(32) NOT NULL DEFAULT '', `id_pulse_ta_shift` INT UNSIGNED DEFAULT NULL, `shift_code` VARCHAR(16) NOT NULL DEFAULT '',
  `shift_start` DATETIME DEFAULT NULL, `shift_end` DATETIME DEFAULT NULL, `crosses_midnight` TINYINT(1) NOT NULL DEFAULT 0,
  `first_in` DATETIME DEFAULT NULL, `last_out` DATETIME DEFAULT NULL, `pairs` SMALLINT NOT NULL DEFAULT 0,
  `raw_minutes` INT NOT NULL DEFAULT 0 COMMENT 'sum of paired intervals before rounding and breaks', `break_minutes` INT NOT NULL DEFAULT 0,
  `worked_minutes` INT NOT NULL DEFAULT 0 COMMENT 'after break deduction and rounding - the payable base', `rounded_minutes` INT NOT NULL DEFAULT 0 COMMENT 'delta applied by the rounding policy',
  `scheduled_minutes` INT NOT NULL DEFAULT 0, `late_minutes` INT NOT NULL DEFAULT 0, `early_out_minutes` INT NOT NULL DEFAULT 0, `short_minutes` INT NOT NULL DEFAULT 0,
  `ot_minutes` INT NOT NULL DEFAULT 0, `ot_daily_minutes` INT NOT NULL DEFAULT 0, `ot_weekly_minutes` INT NOT NULL DEFAULT 0, `ot_restday_minutes` INT NOT NULL DEFAULT 0, `ot_holiday_minutes` INT NOT NULL DEFAULT 0,
  `ot_weighted_minutes` INT NOT NULL DEFAULT 0 COMMENT 'sum of minutes x multiplier - what Payroll pays', `night_minutes` INT NOT NULL DEFAULT 0,
  `day_type` ENUM('work','rest','leave','holiday','training','off_site') NOT NULL DEFAULT 'work',
  `status` ENUM('present','late','absent','incomplete','rest','leave','holiday','off_site') NOT NULL DEFAULT 'present',
  `has_exception` TINYINT(1) NOT NULL DEFAULT 0, `adjustments` SMALLINT NOT NULL DEFAULT 0, `sources` VARCHAR(64) NOT NULL DEFAULT '',
  `id_pulse_ta_period` INT UNSIGNED DEFAULT NULL, `locked` TINYINT(1) NOT NULL DEFAULT 0, `note` VARCHAR(255) NOT NULL DEFAULT '',
  `built_at` DATETIME NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_timesheet`), UNIQUE KEY `sd` (`id_pulse_ta_staff`,`business_date`), KEY `bd` (`business_date`,`department`), KEY `per` (`id_pulse_ta_period`), KEY `exc` (`has_exception`,`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_timesheet_punch` (
  `id_pulse_ta_timesheet` BIGINT UNSIGNED NOT NULL, `id_pulse_ta_punch` BIGINT UNSIGNED NOT NULL, `seq` SMALLINT NOT NULL DEFAULT 0,
  `role` ENUM('in','out','break_out','break_in','ignored','extra') NOT NULL DEFAULT 'in', `virtual` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'came from an approved adjustment, not a device',
  PRIMARY KEY (`id_pulse_ta_timesheet`,`id_pulse_ta_punch`), KEY `p` (`id_pulse_ta_punch`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_exception` (
  `id_pulse_ta_exception` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_ta_staff` INT UNSIGNED DEFAULT NULL, `business_date` DATE NOT NULL,
  `department` VARCHAR(32) NOT NULL DEFAULT '', `id_pulse_ta_timesheet` BIGINT UNSIGNED DEFAULT NULL, `id_pulse_ta_punch` BIGINT UNSIGNED DEFAULT NULL,
  `type` ENUM('missing_in','missing_out','no_punches','late','early_out','short_shift','overlong_shift','unmatched_ref','duplicate_punch','pos_mismatch','device_offline','future_punch') NOT NULL,
  `severity` ENUM('info','warn','block') NOT NULL DEFAULT 'warn' COMMENT 'block stops the period being approved until it is cleared',
  `detail` VARCHAR(255) NOT NULL DEFAULT '', `minutes` INT NOT NULL DEFAULT 0,
  `status` ENUM('open','resolved','waived','auto_closed') NOT NULL DEFAULT 'open',
  `id_pulse_ta_adjustment` BIGINT UNSIGNED DEFAULT NULL, `resolution` VARCHAR(255) NOT NULL DEFAULT '',
  `id_employee_resolved` INT UNSIGNED DEFAULT NULL, `resolved_at` DATETIME DEFAULT NULL,
  `dedupe_hash` CHAR(40) NOT NULL COMMENT 'sha1(staff|date|type) so a rebuild reuses the row instead of piling duplicates on a supervisor',
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_exception`), UNIQUE KEY `dedupe` (`dedupe_hash`), KEY `q` (`status`,`business_date`,`department`), KEY `ts` (`id_pulse_ta_timesheet`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_period` (
  `id_pulse_ta_period` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(64) NOT NULL, `department` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'empty = every department',
  `date_from` DATE NOT NULL, `date_to` DATE NOT NULL,
  `status` ENUM('open','submitted','approved','locked','reopened') NOT NULL DEFAULT 'open',
  `staff_count` INT UNSIGNED NOT NULL DEFAULT 0, `worked_minutes` INT UNSIGNED NOT NULL DEFAULT 0, `ot_weighted_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `open_exceptions` INT UNSIGNED NOT NULL DEFAULT 0, `id_employee_submitted` INT UNSIGNED DEFAULT NULL, `submitted_at` DATETIME DEFAULT NULL,
  `id_employee_approved` INT UNSIGNED DEFAULT NULL, `approved_at` DATETIME DEFAULT NULL, `locked_at` DATETIME DEFAULT NULL,
  `reopen_reason` VARCHAR(255) NOT NULL DEFAULT '', `note` VARCHAR(255) NOT NULL DEFAULT '', `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_period`), UNIQUE KEY `span` (`department`,`date_from`,`date_to`), KEY `st` (`status`,`date_from`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_ot_rule` (
  `id_pulse_ta_ot_rule` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(24) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `scope` ENUM('daily','weekly','rest_day','holiday','night') NOT NULL DEFAULT 'daily', `department` VARCHAR(32) NOT NULL DEFAULT '',
  `threshold_minutes` INT NOT NULL DEFAULT 480 COMMENT 'minutes worked before this rule starts paying', `multiplier` DECIMAL(6,3) NOT NULL DEFAULT 1.500,
  `cap_minutes` INT NOT NULL DEFAULT 0 COMMENT '0 = uncapped', `requires_approval` TINYINT(1) NOT NULL DEFAULT 1,
  `effective_from` DATE NOT NULL DEFAULT '2000-01-01', `effective_to` DATE DEFAULT NULL, `sort` SMALLINT NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_ta_ot_rule`), UNIQUE KEY `code` (`code`), KEY `sc` (`scope`,`active`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_holiday` (
  `id_pulse_ta_holiday` INT UNSIGNED NOT NULL AUTO_INCREMENT, `holiday_date` DATE NOT NULL, `name` VARCHAR(96) NOT NULL, `country` CHAR(2) NOT NULL DEFAULT 'NG',
  `type` ENUM('public','religious','state','company') NOT NULL DEFAULT 'public', `multiplier` DECIMAL(6,3) NOT NULL DEFAULT 2.000,
  `confirmed` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = date depends on a moon sighting or a federal declaration and must be confirmed',
  `note` VARCHAR(160) NOT NULL DEFAULT '', `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_ta_holiday`), UNIQUE KEY `d` (`holiday_date`,`country`,`name`), KEY `dt` (`holiday_date`,`active`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ta_job` (
  `id_pulse_ta_job` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `type` ENUM('push_user','delete_user','pull','sync_time','clear_log','build','import') NOT NULL,
  `id_pulse_ta_device` INT UNSIGNED DEFAULT NULL, `id_pulse_ta_staff` INT UNSIGNED DEFAULT NULL, `payload` TEXT,
  `attempts` SMALLINT NOT NULL DEFAULT 0, `last_error` VARCHAR(255) NOT NULL DEFAULT '', `next_try_at` DATETIME NOT NULL,
  `status` ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued', `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ta_job`), KEY `q` (`status`,`next_try_at`), KEY `d` (`id_pulse_ta_device`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_ta_shift` (`code`,`name`,`department`,`start_time`,`end_time`,`crosses_midnight`,`is_night`,`break_minutes`,`break_paid`,`grace_in_min`,`grace_out_min`,`window_before_min`,`window_after_min`,`min_shift_min`,`max_shift_min`,`paid_minutes`,`colour`,`sort`,`date_add`,`date_upd`) VALUES
('EARLY','Early (06:00-14:00)','','06:00:00','14:00:00',0,0,30,0,10,10,120,180,240,780,480,'#f39c12',1,NOW(),NOW()),
('LATE','Late (14:00-22:00)','','14:00:00','22:00:00',0,0,30,0,10,10,120,180,240,780,480,'#2e86c1',2,NOW(),NOW()),
('NIGHT','Night (22:00-06:00)','','22:00:00','06:00:00',1,1,30,1,10,10,120,180,240,780,480,'#34495e',3,NOW(),NOW()),
('GEN','General office (08:00-17:00)','','08:00:00','17:00:00',0,0,60,0,15,10,120,180,300,840,480,'#27ae60',4,NOW(),NOW()),
('SPLIT','Split (07:00-11:00 / 17:00-21:00)','fnb','07:00:00','11:00:00',0,0,0,0,10,10,90,150,180,900,480,'#8e44ad',5,NOW(),NOW()),
('ONCALL','On call (24h)','maintenance','00:00:00','23:59:00',0,0,0,1,60,60,0,0,60,1440,480,'#7f8c8d',6,NOW(),NOW());

INSERT IGNORE INTO `PREFIX_pulse_ta_ot_rule` (`code`,`name`,`scope`,`department`,`threshold_minutes`,`multiplier`,`cap_minutes`,`requires_approval`,`effective_from`,`sort`,`active`) VALUES
('OT_DAILY','Daily overtime beyond 8 hours','daily','',480,1.500,240,1,'2000-01-01',1,1),
('OT_WEEKLY','Weekly overtime beyond 48 hours','weekly','',2880,1.500,720,1,'2000-01-01',2,1),
('OT_REST','Work on a rostered rest day','rest_day','',0,2.000,0,1,'2000-01-01',3,1),
('OT_HOL','Work on a public holiday','holiday','',0,2.000,0,1,'2000-01-01',4,1),
('OT_NIGHT','Night shift premium (22:00-06:00)','night','',0,1.250,0,0,'2000-01-01',5,1);

INSERT IGNORE INTO `PREFIX_pulse_ta_holiday` (`holiday_date`,`name`,`country`,`type`,`multiplier`,`confirmed`,`note`,`active`) VALUES
('2026-01-01','New Year Day','NG','public',2.000,1,'',1),
('2026-03-20','Eid-el-Fitr (day 1)','NG','religious',2.000,0,'Moon-dependent - confirm against the federal declaration',1),
('2026-03-23','Eid-el-Fitr (day 2)','NG','religious',2.000,0,'Moon-dependent - confirm against the federal declaration',1),
('2026-04-03','Good Friday','NG','public',2.000,1,'',1),
('2026-04-06','Easter Monday','NG','public',2.000,1,'',1),
('2026-05-01','Workers Day','NG','public',2.000,1,'',1),
('2026-05-27','Eid-el-Kabir (day 1)','NG','religious',2.000,0,'Moon-dependent - confirm against the federal declaration',1),
('2026-05-28','Eid-el-Kabir (day 2)','NG','religious',2.000,0,'Moon-dependent - confirm against the federal declaration',1),
('2026-06-12','Democracy Day','NG','public',2.000,1,'',1),
('2026-08-26','Eid-el-Maulud','NG','religious',2.000,0,'Moon-dependent - confirm against the federal declaration',1),
('2026-10-01','Independence Day','NG','public',2.000,1,'',1),
('2026-12-25','Christmas Day','NG','public',2.000,1,'',1),
('2026-12-26','Boxing Day','NG','public',2.000,1,'',1),
('2027-01-01','New Year Day','NG','public',2.000,1,'',1),
('2027-03-10','Eid-el-Fitr (day 1)','NG','religious',2.000,0,'Moon-dependent - confirm against the federal declaration',1),
('2027-03-11','Eid-el-Fitr (day 2)','NG','religious',2.000,0,'Moon-dependent - confirm against the federal declaration',1),
('2027-03-26','Good Friday','NG','public',2.000,1,'',1),
('2027-03-29','Easter Monday','NG','public',2.000,1,'',1),
('2027-05-01','Workers Day','NG','public',2.000,1,'',1),
('2027-05-17','Eid-el-Kabir (day 1)','NG','religious',2.000,0,'Moon-dependent - confirm against the federal declaration',1),
('2027-05-18','Eid-el-Kabir (day 2)','NG','religious',2.000,0,'Moon-dependent - confirm against the federal declaration',1),
('2027-06-12','Democracy Day','NG','public',2.000,1,'',1),
('2027-08-15','Eid-el-Maulud','NG','religious',2.000,0,'Moon-dependent - confirm against the federal declaration',1),
('2027-10-01','Independence Day','NG','public',2.000,1,'',1),
('2027-12-25','Christmas Day','NG','public',2.000,1,'',1),
('2027-12-26','Boxing Day','NG','public',2.000,1,'',1);

INSERT IGNORE INTO `PREFIX_pulse_ta_device` (`name`,`brand`,`adapter`,`location`,`mode`,`protocol`,`host`,`port`,`endpoint`,`serial`,`timezone`,`direction_mode`,`poll_interval_min`,`timeout_sec`,`health`,`status`,`note`,`date_add`,`date_upd`) VALUES
('Simulator','simulator','PulseTaSimulator','Staff entrance','pull','local','127.0.0.1',0,'','SIM-TA-1','Africa/Lagos','auto',10,8,'online','active','Default hardware-free device so the module works out of the box.',NOW(),NOW());
