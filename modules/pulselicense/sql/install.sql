CREATE TABLE IF NOT EXISTS `PREFIX_pulse_license_log` (
  `id_pulse_license_log` INT UNSIGNED NOT NULL AUTO_INCREMENT, `event` VARCHAR(32) NOT NULL, `lid` VARCHAR(64), `detail` VARCHAR(255), `fingerprint` CHAR(40), `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_license_log`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;
