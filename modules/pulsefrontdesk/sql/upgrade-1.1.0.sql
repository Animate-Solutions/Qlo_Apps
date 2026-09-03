
CREATE TABLE IF NOT EXISTS `PREFIX_pulse_group_block` (
  `id_pulse_group_block` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(16) NOT NULL, `name` VARCHAR(128) NOT NULL,
  `id_pulse_company` INT UNSIGNED DEFAULT NULL, `id_pulse_folio` INT UNSIGNED DEFAULT NULL COMMENT 'master folio',
  `date_from` DATE NOT NULL, `date_to` DATE NOT NULL, `cutoff_date` DATE NOT NULL,
  `rate_per_night` DECIMAL(20,6) DEFAULT NULL COMMENT 'contracted nightly rate tax incl; NULL = rack',
  `billing` ENUM('individual','master','room_to_master') NOT NULL DEFAULT 'individual',
  `status` ENUM('tentative','definite','cancelled','released') NOT NULL DEFAULT 'tentative',
  `contact_name` VARCHAR(128), `contact_phone` VARCHAR(32), `contact_email` VARCHAR(128), `notes` TEXT,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_group_block`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_group_block_allot` (
  `id_pulse_group_block_allot` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_group_block` INT UNSIGNED NOT NULL, `id_product` INT UNSIGNED NOT NULL COMMENT 'room type',
  `blocked` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `picked_up` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_pulse_group_block_allot`), UNIQUE KEY `blk_type` (`id_pulse_group_block`,`id_product`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_booking_ext` (
  `id_htl_booking` INT UNSIGNED NOT NULL,
  `id_pulse_group_block` INT UNSIGNED DEFAULT NULL,
  `day_use` TINYINT(1) NOT NULL DEFAULT 0, `day_use_until` TIME DEFAULT NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'web' COMMENT 'web|walkin|phone|group|ota|waitlist',
  `precheckin_token` CHAR(40) DEFAULT NULL, `precheckin_done` TINYINT(1) NOT NULL DEFAULT 0,
  `checkout_token` CHAR(40) DEFAULT NULL, `card_auth_ref` VARCHAR(64) DEFAULT NULL, `card_auth_amount` DECIMAL(20,6) DEFAULT NULL,
  `late_fee_posted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_htl_booking`), KEY `block` (`id_pulse_group_block`), KEY `pct` (`precheckin_token`), KEY `cot` (`checkout_token`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_waitlist` (
  `id_pulse_waitlist` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_customer` INT UNSIGNED DEFAULT NULL, `guest_name` VARCHAR(128) NOT NULL, `phone` VARCHAR(32), `email` VARCHAR(128),
  `id_product` INT UNSIGNED NOT NULL, `date_from` DATE NOT NULL, `date_to` DATE NOT NULL, `rooms` TINYINT NOT NULL DEFAULT 1,
  `priority` TINYINT NOT NULL DEFAULT 5, `status` ENUM('waiting','offered','booked','expired','cancelled') NOT NULL DEFAULT 'waiting',
  `offered_at` DATETIME DEFAULT NULL, `note` VARCHAR(255), `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_waitlist`), KEY `st` (`status`,`priority`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_overbooking` (
  `id_product` INT UNSIGNED NOT NULL, `max_over` TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_product`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_registration_card` (
  `id_pulse_registration_card` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_htl_booking` INT UNSIGNED NOT NULL, `id_customer` INT UNSIGNED NOT NULL,
  `terms_version` VARCHAR(16) NOT NULL, `terms_accepted` TINYINT(1) NOT NULL DEFAULT 0,
  `signature` MEDIUMTEXT COMMENT 'PNG data URL', `signed_name` VARCHAR(128), `ip` VARCHAR(45), `channel` ENUM('desk','precheckin') NOT NULL DEFAULT 'desk',
  `snapshot` TEXT COMMENT 'JSON of guest/stay details at signing', `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_registration_card`), KEY `bk` (`id_htl_booking`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_upsell_offer` (
  `id_pulse_upsell_offer` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('room_upgrade','early_checkin','late_checkout','breakfast','package','other') NOT NULL,
  `name` VARCHAR(128) NOT NULL, `charge_code` VARCHAR(16) NOT NULL, `price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `per` ENUM('stay','night','person') NOT NULL DEFAULT 'stay', `min_avail_pct` TINYINT NOT NULL DEFAULT 0 COMMENT 'only offer when availability >= this %',
  `active` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_pulse_upsell_offer`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_upsell_sale` (
  `id_pulse_upsell_sale` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_upsell_offer` INT UNSIGNED, `id_htl_booking` INT UNSIGNED NOT NULL, `amount_tax_incl` DECIMAL(20,6) NOT NULL,
  `id_employee` INT UNSIGNED, `stage` ENUM('checkin','instay','precheckin') NOT NULL DEFAULT 'checkin', `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_upsell_sale`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_routing_rule` (
  `id_pulse_routing_rule` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` ENUM('booking','company','group') NOT NULL, `id_scope` INT UNSIGNED NOT NULL,
  `department` VARCHAR(32) NOT NULL COMMENT 'rooms|fnb|minibar|...|*',
  `target` ENUM('guest','company','master') NOT NULL, `id_target_folio` INT UNSIGNED DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_routing_rule`), KEY `sc` (`scope`,`id_scope`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_drawer_movement` (
  `id_pulse_drawer_movement` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_cashier_session` INT UNSIGNED NOT NULL,
  `type` ENUM('blind_drop','paid_out','float_in','float_out','correction') NOT NULL,
  `amount` DECIMAL(20,6) NOT NULL, `note` VARCHAR(255), `id_employee` INT UNSIGNED, `witness` INT UNSIGNED DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_drawer_movement`), KEY `sess` (`id_pulse_cashier_session`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ticket` (
  `id_pulse_ticket` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_no` VARCHAR(16) NOT NULL,
  `category` ENUM('complaint','maintenance','housekeeping','amenity','concierge','it','other') NOT NULL,
  `department` ENUM('frontdesk','housekeeping','engineering','fnb','security','management') NOT NULL,
  `id_room` INT UNSIGNED DEFAULT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_customer` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(128) NOT NULL, `description` TEXT, `priority` ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` ENUM('open','assigned','in_progress','resolved','closed','reopened') NOT NULL DEFAULT 'open',
  `assigned_to` INT UNSIGNED DEFAULT NULL, `sla_due` DATETIME DEFAULT NULL, `resolution` TEXT, `source` VARCHAR(16) NOT NULL DEFAULT 'desk',
  `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL, `date_resolved` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_pulse_ticket`), UNIQUE KEY `no` (`ticket_no`), KEY `st` (`status`,`department`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ticket_note` (
  `id_pulse_ticket_note` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_ticket` INT UNSIGNED NOT NULL,
  `note` TEXT NOT NULL, `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ticket_note`), KEY `t` (`id_pulse_ticket`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_comms_log` (
  `id_pulse_comms_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel` ENUM('email','sms','whatsapp') NOT NULL, `template` VARCHAR(32) NOT NULL, `to_addr` VARCHAR(128) NOT NULL,
  `id_htl_booking` INT UNSIGNED, `id_customer` INT UNSIGNED, `status` ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `provider_ref` VARCHAR(64), `error` VARCHAR(255), `date_add` DATETIME NOT NULL, `date_sent` DATETIME,
  PRIMARY KEY (`id_pulse_comms_log`), KEY `bk` (`id_htl_booking`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pabx_log` (
  `id_pulse_pabx_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `extension` VARCHAR(16) NOT NULL, `id_room` INT UNSIGNED, `event` ENUM('call','status_code','wakeup_result') NOT NULL,
  `payload` VARCHAR(255), `duration_sec` INT UNSIGNED, `cost` DECIMAL(20,6), `posted` TINYINT(1) NOT NULL DEFAULT 0, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pabx_log`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

-- multi-currency on folios (settlement currency captured per line; folio kept in shop currency)
ALTER TABLE `PREFIX_pulse_folio_line` ADD COLUMN `currency_iso` CHAR(3) DEFAULT NULL, ADD COLUMN `amount_foreign` DECIMAL(20,6) DEFAULT NULL, ADD COLUMN `conversion_rate` DECIMAL(13,6) DEFAULT NULL;

INSERT IGNORE INTO `PREFIX_pulse_charge_code` (`code`,`name`,`department`,`default_price`,`tax_rate`,`is_payment`) VALUES
('UPG','Room Upgrade','rooms',0,7.5,0),('ECI','Early Check-in','rooms',0,7.5,0),('DAYUSE','Day Use','rooms',0,7.5,0),
('BRK','Breakfast','fnb',0,12.5,0),('TELE','Telephone','telephone',0,7.5,0),('PKG','Package','rooms',0,7.5,0),('CARD','Card (online / pre-auth capture)','payment',0,0,1);

INSERT IGNORE INTO `PREFIX_pulse_upsell_offer` (`id_pulse_upsell_offer`,`type`,`name`,`charge_code`,`price_tax_excl`,`per`,`min_avail_pct`,`sort`) VALUES
(1,'room_upgrade','Upgrade to next room category','UPG',0,'night',20,1),
(2,'early_checkin','Early check-in (from 10:00)','ECI',0,'stay',0,2),
(3,'late_checkout','Late check-out (until 16:00)','LATE',0,'stay',0,3),
(4,'breakfast','Add breakfast','BRK',0,'person',0,4);

