CREATE TABLE IF NOT EXISTS `PREFIX_pulse_asset` (
  `id_pulse_asset` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(128) NOT NULL,
  `category` ENUM('hvac','electrical','plumbing','generator','kitchen','laundry','it','elevator','fire_safety','furniture','pool','vehicle','building','other') NOT NULL DEFAULT 'other',
  `location_type` ENUM('room','public_area','plant','external') NOT NULL DEFAULT 'room', `id_room` INT UNSIGNED DEFAULT NULL, `location` VARCHAR(128),
  `make_model` VARCHAR(128), `serial_no` VARCHAR(64), `installed_on` DATE, `warranty_until` DATE, `purchase_cost` DECIMAL(20,6) NOT NULL DEFAULT 0, `vendor` VARCHAR(128), `vendor_phone` VARCHAR(32),
  `status` ENUM('in_service','out_of_service','retired') NOT NULL DEFAULT 'in_service', `criticality` TINYINT NOT NULL DEFAULT 3 COMMENT '1 critical … 5 low', `note` TEXT, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_asset`), UNIQUE KEY `code` (`code`), KEY `room` (`id_room`), KEY `cat` (`category`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_work_order` (
  `id_pulse_work_order` INT UNSIGNED NOT NULL AUTO_INCREMENT, `wo_no` VARCHAR(16) NOT NULL,
  `type` ENUM('corrective','preventive','inspection','project','safety') NOT NULL DEFAULT 'corrective',
  `category` VARCHAR(32) NOT NULL DEFAULT 'other', `id_pulse_asset` INT UNSIGNED, `id_room` INT UNSIGNED, `location` VARCHAR(128),
  `priority` ENUM('emergency','high','normal','low') NOT NULL DEFAULT 'normal', `sla_hours` SMALLINT NOT NULL DEFAULT 24, `due_at` DATETIME,
  `subject` VARCHAR(128) NOT NULL, `description` TEXT, `status` ENUM('open','assigned','in_progress','on_hold','completed','verified','cancelled') NOT NULL DEFAULT 'open',
  `hold_reason` VARCHAR(128), `assigned_to` INT UNSIGNED, `vendor` VARCHAR(128), `room_ooo` TINYINT(1) NOT NULL DEFAULT 0,
  `source` VARCHAR(32) NOT NULL DEFAULT 'manual' COMMENT 'manual|ticket|housekeeping|pm|portal|inspection', `source_ref` VARCHAR(64), `id_pulse_ticket` INT UNSIGNED, `id_pulse_pm_schedule` INT UNSIGNED,
  `labour_minutes` INT NOT NULL DEFAULT 0, `labour_cost` DECIMAL(20,6) NOT NULL DEFAULT 0, `parts_cost` DECIMAL(20,6) NOT NULL DEFAULT 0, `vendor_cost` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `resolution` TEXT, `root_cause` VARCHAR(128), `reported_by` INT UNSIGNED, `verified_by` INT UNSIGNED,
  `date_add` DATETIME NOT NULL, `date_started` DATETIME, `date_completed` DATETIME, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_work_order`), UNIQUE KEY `no` (`wo_no`), KEY `status` (`status`,`due_at`), KEY `asset` (`id_pulse_asset`), KEY `room` (`id_room`), KEY `tech` (`assigned_to`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_work_order_note` (
  `id_pulse_work_order_note` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_work_order` INT UNSIGNED NOT NULL, `note` TEXT, `photo_path` VARCHAR(255), `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_work_order_note`), KEY `wo` (`id_pulse_work_order`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_part` (
  `id_pulse_part` INT UNSIGNED NOT NULL AUTO_INCREMENT, `sku` VARCHAR(32) NOT NULL, `name` VARCHAR(128) NOT NULL, `category` VARCHAR(32), `unit` VARCHAR(16) NOT NULL DEFAULT 'pc',
  `qty_on_hand` DECIMAL(10,2) NOT NULL DEFAULT 0, `reorder_level` DECIMAL(10,2) NOT NULL DEFAULT 0, `unit_cost` DECIMAL(20,6) NOT NULL DEFAULT 0, `supplier` VARCHAR(128), `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_part`), UNIQUE KEY `sku` (`sku`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_part_movement` (
  `id_pulse_part_movement` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_part` INT UNSIGNED NOT NULL, `type` ENUM('issue','receive','adjust','return') NOT NULL, `qty` DECIMAL(10,2) NOT NULL, `id_pulse_work_order` INT UNSIGNED, `unit_cost` DECIMAL(20,6), `note` VARCHAR(128), `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_part_movement`), KEY `p` (`id_pulse_part`), KEY `wo` (`id_pulse_work_order`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pm_schedule` (
  `id_pulse_pm_schedule` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(128) NOT NULL, `category` VARCHAR(32) NOT NULL DEFAULT 'other',
  `scope` ENUM('asset','all_rooms','room_type','location') NOT NULL DEFAULT 'asset', `id_pulse_asset` INT UNSIGNED, `id_product` INT UNSIGNED, `location` VARCHAR(128),
  `interval_days` SMALLINT NOT NULL DEFAULT 90, `checklist` TEXT COMMENT 'one item per line', `est_minutes` SMALLINT NOT NULL DEFAULT 60, `assigned_to` INT UNSIGNED, `priority` ENUM('emergency','high','normal','low') NOT NULL DEFAULT 'normal',
  `rooms_per_run` TINYINT NOT NULL DEFAULT 4 COMMENT 'all_rooms: how many rooms to generate per day', `next_due` DATE NOT NULL, `last_run` DATE, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_pm_schedule`), KEY `due` (`active`,`next_due`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pm_room_cursor` (
  `id_pulse_pm_schedule` INT UNSIGNED NOT NULL, `id_room` INT UNSIGNED NOT NULL, `last_done` DATE, PRIMARY KEY (`id_pulse_pm_schedule`,`id_room`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_meter_reading` (
  `id_pulse_meter_reading` INT UNSIGNED NOT NULL AUTO_INCREMENT, `meter` ENUM('generator_hours','diesel_litres','electricity_kwh','water_m3','gas_kg') NOT NULL, `id_pulse_asset` INT UNSIGNED, `reading` DECIMAL(14,2) NOT NULL, `cost` DECIMAL(20,6), `read_at` DATETIME NOT NULL, `id_employee` INT UNSIGNED,
  PRIMARY KEY (`id_pulse_meter_reading`), KEY `m` (`meter`,`read_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_pm_schedule` (`name`,`category`,`scope`,`interval_days`,`checklist`,`est_minutes`,`priority`,`rooms_per_run`,`next_due`) VALUES
('Room AC service (filter clean, gas check, drain)','hvac','all_rooms',90,'Clean filters\nCheck gas pressure\nClear condensate drain\nTest remote & thermostat\nCheck for leaks/noise',45,'normal',4,CURDATE()),
('Room electrical & plumbing check','electrical','all_rooms',180,'Test all sockets & switches\nCheck bulbs\nCheck tap/shower flow & leaks\nFlush & fill test WC\nCheck water heater',40,'normal',3,CURDATE()),
('Generator service','generator','location',30,'Oil & filter change per hours\nCoolant level\nBattery terminals\nFuel filter / water separator\nLoad test 30 min\nLog hours',120,'high',1,CURDATE()),
('Fire extinguisher & alarm inspection','fire_safety','location',30,'Extinguisher pressure & seals\nSmoke detectors test\nEmergency lights\nExit signs\nFire hose reels',60,'high',1,CURDATE()),
('Water tanks & pumps','plumbing','location',90,'Tank cleaning\nPump pressure\nFloat valves\nBorehole output\nTreatment dosing',180,'normal',1,CURDATE()),
('Lift/elevator safety check (vendor)','elevator','location',30,'Vendor visit logged\nEmergency phone\nDoor sensors\nLevelling',60,'high',1,CURDATE());
