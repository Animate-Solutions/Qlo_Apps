CREATE TABLE IF NOT EXISTS `PREFIX_pulse_expense_category` (
  `id_pulse_expense_category` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(16) NOT NULL, `name` VARCHAR(64) NOT NULL,
  `group_name` ENUM('cost_of_sales','payroll','utilities','repairs','admin','marketing','property','other') NOT NULL DEFAULT 'other', `active` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_pulse_expense_category`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_expense` (
  `id_pulse_expense` INT UNSIGNED NOT NULL AUTO_INCREMENT, `expense_no` VARCHAR(16) NOT NULL, `id_pulse_expense_category` INT UNSIGNED NOT NULL,
  `department` ENUM('rooms','fnb','housekeeping','laundry','maintenance','admin','sales','security','general') NOT NULL DEFAULT 'general',
  `description` VARCHAR(255) NOT NULL, `payee` VARCHAR(128), `amount` DECIMAL(20,6) NOT NULL, `tax_amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `payment_method` ENUM('cash','transfer','card','petty_cash','credit') NOT NULL DEFAULT 'cash', `reference` VARCHAR(64), `receipt_path` VARCHAR(255),
  `status` ENUM('draft','submitted','approved','rejected','paid') NOT NULL DEFAULT 'submitted', `source` VARCHAR(32) NOT NULL DEFAULT 'manual' COMMENT 'manual|maintenance|laundry|cashier|meter',
  `source_ref` VARCHAR(64), `business_date` DATE NOT NULL, `id_employee` INT UNSIGNED, `approved_by` INT UNSIGNED, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_expense`), UNIQUE KEY `no` (`expense_no`), KEY `bdate` (`business_date`,`status`), KEY `cat` (`id_pulse_expense_category`), KEY `src` (`source`,`source_ref`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_budget` (
  `id_pulse_budget` INT UNSIGNED NOT NULL AUTO_INCREMENT, `year` SMALLINT NOT NULL, `month` TINYINT NOT NULL,
  `line` VARCHAR(32) NOT NULL COMMENT 'room_revenue|fnb_revenue|other_revenue|occupancy_pct|adr|expense:<category code>', `amount` DECIMAL(20,6) NOT NULL,
  PRIMARY KEY (`id_pulse_budget`), UNIQUE KEY `ym_line` (`year`,`month`,`line`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_report_schedule` (
  `id_pulse_report_schedule` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(64) NOT NULL,
  `report` ENUM('owner_daily','manager_daily','weekly','monthly','alerts') NOT NULL DEFAULT 'owner_daily',
  `recipients_email` VARCHAR(512), `recipients_sms` VARCHAR(255), `send_time` TIME NOT NULL DEFAULT '06:30:00', `send_after_audit` TINYINT(1) NOT NULL DEFAULT 1,
  `weekday` TINYINT DEFAULT NULL COMMENT 'weekly: 1=Mon', `month_day` TINYINT DEFAULT NULL, `include_pdf` TINYINT(1) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1, `last_sent` DATETIME, `last_business_date` DATE, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_report_schedule`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_report_log` (
  `id_pulse_report_log` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_report_schedule` INT UNSIGNED, `report` VARCHAR(32), `business_date` DATE, `recipients` VARCHAR(512), `status` ENUM('sent','failed') NOT NULL, `error` VARCHAR(255), `html` MEDIUMTEXT, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_report_log`), KEY `bd` (`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_expense_category` (`code`,`name`,`group_name`,`sort`) VALUES
('FOOD','Food purchases','cost_of_sales',1),('BEV','Beverage purchases','cost_of_sales',2),('GSUP','Guest supplies & amenities','cost_of_sales',3),('CLEAN','Cleaning & laundry chemicals','cost_of_sales',4),
('SAL','Salaries & wages','payroll',10),('CAS','Casual staff','payroll',11),('STAFFM','Staff meals & welfare','payroll',12),
('DIESEL','Diesel / generator fuel','utilities',20),('POWER','Electricity (grid)','utilities',21),('WATER','Water','utilities',22),('GAS','Cooking gas','utilities',23),('INET','Internet & telephone','utilities',24),('TV','DStv / TV subscriptions','utilities',25),
('RM','Repairs & maintenance','repairs',30),('PARTS','Spare parts','repairs',31),('VENDOR','Contractors','repairs',32),
('ADMIN','Office & admin','admin',40),('BANK','Bank charges','admin',41),('LIC','Licences, permits & levies','admin',42),('INS','Insurance','admin',43),('PROF','Professional fees','admin',44),
('MKT','Marketing & OTA commissions','marketing',50),('SEC','Security','property',60),('RENT','Rent / lease','property',61),('TAX','Taxes remitted','other',70),('MISC','Miscellaneous','other',99);

INSERT IGNORE INTO `PREFIX_pulse_report_schedule` (`id_pulse_report_schedule`,`name`,`report`,`send_time`,`send_after_audit`,`active`,`date_add`) VALUES
(1,'Owners daily snapshot','owner_daily','06:30:00',1,1,NOW()),(2,'Manager daily operations','manager_daily','06:45:00',1,1,NOW()),(3,'Weekly owners summary','weekly','07:00:00',0,1,NOW()),(4,'Monthly owners report','monthly','07:00:00',0,1,NOW());
UPDATE `PREFIX_pulse_report_schedule` SET weekday=1 WHERE id_pulse_report_schedule=3;
UPDATE `PREFIX_pulse_report_schedule` SET month_day=1 WHERE id_pulse_report_schedule=4;
