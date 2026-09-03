CREATE TABLE IF NOT EXISTS `PREFIX_pulse_setting` (
  `id_pulse_setting` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(64) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `value` TEXT,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_setting`),
  UNIQUE KEY `mod_name` (`module`,`name`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_audit` (
  `id_pulse_audit` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(64) NOT NULL,
  `event` VARCHAR(64) NOT NULL,
  `id_employee` INT UNSIGNED DEFAULT NULL,
  `entity` VARCHAR(64),
  `id_entity` INT UNSIGNED,
  `payload` TEXT,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_audit`),
  KEY `mod_ev` (`module`,`event`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_api_token` (
  `id_pulse_api_token` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(128),
  `token` CHAR(64) NOT NULL,
  `scopes` VARCHAR(255),
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_api_token`),
  UNIQUE KEY `token` (`token`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;
