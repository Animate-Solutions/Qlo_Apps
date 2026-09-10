CREATE TABLE IF NOT EXISTS `PREFIX_pulse_kc_encoder` (
  `id_pulse_kc_encoder` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(64) NOT NULL COMMENT 'workstation name printed on the encoder',
  `adapter` VARCHAR(64) NOT NULL DEFAULT 'PulseKcAdapterSimulator', `location` ENUM('front_desk','back_office','housekeeping','security','engineering','mobile') NOT NULL DEFAULT 'front_desk',
  `protocol` ENUM('http','https','tcp','local') NOT NULL DEFAULT 'http', `host` VARCHAR(128) NOT NULL DEFAULT '127.0.0.1', `port` SMALLINT UNSIGNED NOT NULL DEFAULT 8080, `endpoint` VARCHAR(128) NOT NULL DEFAULT '/',
  `encoder_ref` VARCHAR(64) COMMENT 'vendor-side encoder / terminal id', `credentials_enc` TEXT COMMENT 'PulseCoreService::encrypt of {user,password,api_key}', `options_json` TEXT,
  `local_only` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'reachable only from the clerk workstation, not from the web server',
  `test_mode` TINYINT(1) NOT NULL DEFAULT 0, `timeout_sec` SMALLINT NOT NULL DEFAULT 8,
  `status` ENUM('unknown','online','offline','disabled') NOT NULL DEFAULT 'unknown', `last_seen` DATETIME DEFAULT NULL, `last_error` VARCHAR(255), `keys_encoded` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_kc_encoder`), UNIQUE KEY `name` (`name`), KEY `status` (`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_kc_door` (
  `id_pulse_kc_door` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `type` ENUM('room','common','lift','gate','back_of_house','safe','wall_reader') NOT NULL DEFAULT 'common',
  `lock_id` VARCHAR(64) COMMENT 'vendor lock address used for audit pulls', `id_room` INT UNSIGNED DEFAULT NULL, `floor` VARCHAR(8), `zone` VARCHAR(32),
  `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'added to every guest key', `battery_pct` SMALLINT DEFAULT NULL, `battery_checked_at` DATETIME DEFAULT NULL, `battery_ticket_at` DATETIME DEFAULT NULL,
  `last_audit_at` DATETIME DEFAULT NULL, `id_pulse_kc_encoder` INT UNSIGNED DEFAULT NULL, `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_kc_door`), UNIQUE KEY `code` (`code`), KEY `room` (`id_room`), KEY `type` (`type`,`active`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_kc_staff_group` (
  `id_pulse_kc_staff_group` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(64) NOT NULL, `department` VARCHAR(32) NOT NULL DEFAULT 'frontdesk',
  `doors` VARCHAR(255) COMMENT 'csv id_pulse_kc_door', `all_rooms` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'floor/grand master over every guest room',
  `shift_start` TIME NOT NULL DEFAULT '00:00:00', `shift_end` TIME NOT NULL DEFAULT '23:59:00', `days_mask` TINYINT UNSIGNED NOT NULL DEFAULT 127 COMMENT 'bit 1=Mon .. 64=Sun',
  `card_days` SMALLINT NOT NULL DEFAULT 90 COMMENT 'card expiry in days', `override_deadbolt` TINYINT(1) NOT NULL DEFAULT 0, `override_dnd` TINYINT(1) NOT NULL DEFAULT 0,
  `is_master` TINYINT(1) NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_kc_staff_group`), UNIQUE KEY `name` (`name`), KEY `dept` (`department`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_kc_key` (
  `id_pulse_kc_key` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `key_no` VARCHAR(16) NOT NULL,
  `type` ENUM('guest','duplicate','one_shot','staff','master','common','emergency') NOT NULL DEFAULT 'guest',
  `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_customer` INT UNSIGNED DEFAULT NULL, `guest_name` VARCHAR(128),
  `id_employee_holder` INT UNSIGNED DEFAULT NULL, `id_pulse_kc_staff_group` INT UNSIGNED DEFAULT NULL,
  `id_room` INT UNSIGNED DEFAULT NULL, `id_rooms` VARCHAR(255) COMMENT 'csv id_room - a family key opens several rooms', `room_nums` VARCHAR(255), `doors` VARCHAR(255) COMMENT 'csv id_pulse_kc_door',
  `valid_from` DATETIME NOT NULL, `valid_to` DATETIME NOT NULL, `override_deadbolt` TINYINT(1) NOT NULL DEFAULT 0, `override_dnd` TINYINT(1) NOT NULL DEFAULT 0,
  `id_pulse_kc_encoder` INT UNSIGNED DEFAULT NULL, `adapter` VARCHAR(64) NOT NULL DEFAULT 'PulseKcAdapterSimulator',
  `key_ref` VARCHAR(64) COMMENT 'vendor reference used to cancel', `card_serial` VARCHAR(64), `sequence` INT UNSIGNED NOT NULL DEFAULT 0,
  `payload_enc` TEXT COMMENT 'encrypted key payload - never written to the audit trail in clear', `payload_hash` CHAR(64),
  `mobile` TINYINT(1) NOT NULL DEFAULT 0, `mechanical` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'fallback: metal key handed over while the encoder was down',
  `status` ENUM('pending','issued','cancelled','expired','failed','lost') NOT NULL DEFAULT 'pending', `id_parent_key` BIGINT UNSIGNED DEFAULT NULL,
  `issued_by` INT UNSIGNED, `issued_at` DATETIME, `cancelled_by` INT UNSIGNED, `cancelled_at` DATETIME, `cancel_reason` VARCHAR(128), `last_error` VARCHAR(255), `note` VARCHAR(255),
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_kc_key`), UNIQUE KEY `key_no` (`key_no`), KEY `booking` (`id_htl_booking`), KEY `room` (`id_room`,`status`),
  KEY `st` (`status`,`valid_to`), KEY `serial` (`card_serial`), KEY `holder` (`id_employee_holder`), KEY `bdate` (`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_kc_mobile_key` (
  `id_pulse_kc_mobile_key` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_kc_key` BIGINT UNSIGNED NOT NULL,
  `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_customer` INT UNSIGNED DEFAULT NULL, `token` CHAR(64) NOT NULL COMMENT 'bearer handle in the delivery link',
  `credential_enc` TEXT COMMENT 'encrypted signed BLE/QR credential', `credential_exp` DATETIME NOT NULL COMMENT 'short-lived - the app refreshes',
  `channel` ENUM('ble','qr','both') NOT NULL DEFAULT 'both', `device_fingerprint` CHAR(64) DEFAULT NULL, `device_label` VARCHAR(96), `device_bound_at` DATETIME DEFAULT NULL,
  `valid_from` DATETIME NOT NULL, `valid_to` DATETIME NOT NULL, `refresh_count` INT UNSIGNED NOT NULL DEFAULT 0, `last_refresh_at` DATETIME DEFAULT NULL, `last_ip` VARCHAR(45),
  `delivered_via` VARCHAR(32), `delivered_at` DATETIME DEFAULT NULL, `status` ENUM('issued','active','revoked','expired') NOT NULL DEFAULT 'issued',
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_kc_mobile_key`), UNIQUE KEY `token` (`token`), KEY `k` (`id_pulse_kc_key`), KEY `booking` (`id_htl_booking`), KEY `st` (`status`,`valid_to`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_kc_lock_audit` (
  `id_pulse_kc_lock_audit` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_kc_door` INT UNSIGNED DEFAULT NULL, `lock_id` VARCHAR(64) NOT NULL, `id_room` INT UNSIGNED DEFAULT NULL,
  `card_serial` VARCHAR(64), `id_pulse_kc_key` BIGINT UNSIGNED DEFAULT NULL, `holder` VARCHAR(128) COMMENT 'resolved guest or staff name at ingest time',
  `event` ENUM('open','denied','deadbolt','dnd_blocked','expired_card','battery_low','door_ajar','pass_used','emergency','staff_open','unknown') NOT NULL DEFAULT 'open',
  `result` ENUM('granted','denied','error','info') NOT NULL DEFAULT 'granted', `battery_pct` SMALLINT DEFAULT NULL,
  `source` ENUM('lock','encoder','gateway','mobile','manual') NOT NULL DEFAULT 'lock', `opened_at` DATETIME NOT NULL, `business_date` DATE NOT NULL,
  `raw` VARCHAR(255), `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_kc_lock_audit`), UNIQUE KEY `dedupe` (`lock_id`,`opened_at`,`card_serial`,`event`), KEY `room` (`id_room`,`opened_at`), KEY `bdate` (`business_date`), KEY `k` (`id_pulse_kc_key`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_kc_job` (
  `id_pulse_kc_job` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `type` ENUM('encode','cancel','audit_pull','mobile_revoke','blacklist') NOT NULL,
  `id_pulse_kc_key` BIGINT UNSIGNED DEFAULT NULL, `id_pulse_kc_encoder` INT UNSIGNED DEFAULT NULL, `id_pulse_kc_door` INT UNSIGNED DEFAULT NULL,
  `payload_enc` TEXT, `attempts` SMALLINT NOT NULL DEFAULT 0, `last_error` VARCHAR(255), `next_try_at` DATETIME NOT NULL,
  `status` ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued', `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_kc_job`), KEY `q` (`status`,`next_try_at`), KEY `k` (`id_pulse_kc_key`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_kc_encoder` (`name`,`adapter`,`location`,`protocol`,`host`,`port`,`endpoint`,`encoder_ref`,`local_only`,`test_mode`,`timeout_sec`,`status`,`active`,`date_add`,`date_upd`) VALUES
('Front Desk 1','PulseKcAdapterSimulator','front_desk','local','127.0.0.1',0,'','SIM-FD1',0,0,8,'online',1,NOW(),NOW());

INSERT IGNORE INTO `PREFIX_pulse_kc_door` (`code`,`name`,`type`,`lock_id`,`is_default`,`active`,`date_add`,`date_upd`) VALUES
('MAIN','Main entrance','common','LK-MAIN',1,1,NOW(),NOW()),
('LIFT','Guest lift','lift','LK-LIFT',1,1,NOW(),NOW()),
('POOL','Pool & gym','common','LK-POOL',0,1,NOW(),NOW());

INSERT IGNORE INTO `PREFIX_pulse_kc_staff_group` (`name`,`department`,`doors`,`all_rooms`,`shift_start`,`shift_end`,`card_days`,`override_deadbolt`,`override_dnd`,`is_master`,`active`,`date_add`,`date_upd`) VALUES
('Duty Manager Master','frontdesk','',1,'00:00:00','23:59:00',180,1,1,1,1,NOW(),NOW());
