CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hotel_access` (
  `id_pulse_hotel_access` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_employee` INT UNSIGNED NOT NULL,
  `id_hotel` INT UNSIGNED NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'the hotel the picker pre-selects for this person',
  `can_switch` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 pins the person to this hotel for the whole session',
  `granted_by` INT UNSIGNED DEFAULT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hotel_access`),
  UNIQUE KEY `emp_hotel` (`id_employee`,`id_hotel`),
  KEY `emp` (`id_employee`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_hotel_session` (
  `id_pulse_hotel_session` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_employee` INT UNSIGNED NOT NULL,
  `id_hotel` INT UNSIGNED DEFAULT NULL,
  `event` ENUM('selected','switched','refused','signed_out','no_access') NOT NULL,
  `from_hotel` INT UNSIGNED DEFAULT NULL,
  `controller` VARCHAR(64) NOT NULL DEFAULT '',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `detail` VARCHAR(255) NOT NULL DEFAULT '',
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_hotel_session`),
  KEY `emp` (`id_employee`,`date_add`),
  KEY `ev` (`event`,`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;
