CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pos_outlet` (
  `id_pulse_pos_outlet` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_hotel` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `type` ENUM('restaurant','bar','room_service','pool','other') NOT NULL,
  `service_charge_rate` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_pos_outlet`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pos_table` (
  `id_pulse_pos_table` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_pos_outlet` INT UNSIGNED NOT NULL,
  `code` VARCHAR(16) NOT NULL,
  `seats` TINYINT NOT NULL DEFAULT 4,
  `status` ENUM('free','occupied','reserved','billed') NOT NULL DEFAULT 'free',
  PRIMARY KEY (`id_pulse_pos_table`),
  UNIQUE KEY `outlet_code` (`id_pulse_pos_outlet`,`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pos_menu_item` (
  `id_pulse_pos_menu_item` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_pos_outlet` INT UNSIGNED NOT NULL,
  `category` VARCHAR(64) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `price_tax_excl` DECIMAL(20,6) NOT NULL,
  `id_tax_rules_group` INT UNSIGNED,
  `kitchen_station` VARCHAR(32) COMMENT 'kitchen|bar|grill',
  `available` TINYINT(1) NOT NULL DEFAULT 1,
  `sort` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_pulse_pos_menu_item`),
  KEY `outlet_cat` (`id_pulse_pos_outlet`,`category`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pos_bill` (
  `id_pulse_pos_bill` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bill_no` VARCHAR(32) NOT NULL,
  `id_pulse_pos_outlet` INT UNSIGNED NOT NULL,
  `id_pulse_pos_table` INT UNSIGNED,
  `id_room` INT UNSIGNED COMMENT 'set for room service / post-to-room',
  `id_pulse_folio` INT UNSIGNED,
  `covers` TINYINT NOT NULL DEFAULT 1,
  `status` ENUM('open','billed','settled','void') NOT NULL DEFAULT 'open',
  `subtotal` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `service_charge` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `tax` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `settle_method` VARCHAR(32),
  `id_employee` INT UNSIGNED NOT NULL,
  `business_date` DATE NOT NULL,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pos_bill`),
  UNIQUE KEY `bill_no` (`bill_no`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pos_bill_line` (
  `id_pulse_pos_bill_line` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_pos_bill` INT UNSIGNED NOT NULL,
  `id_pulse_pos_menu_item` INT UNSIGNED NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `qty` DECIMAL(10,3) NOT NULL DEFAULT 1,
  `unit_price_tax_excl` DECIMAL(20,6) NOT NULL,
  `modifiers` VARCHAR(255),
  `kot_no` VARCHAR(32),
  `kot_status` ENUM('pending','fired','ready','served','void') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id_pulse_pos_bill_line`),
  KEY `bill` (`id_pulse_pos_bill`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;
