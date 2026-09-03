-- Pulse Front Desk — schema (all tables prefixed at install time)

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_room_status` (
  `id_room` INT UNSIGNED NOT NULL,
  `hk_status` ENUM('vacant_clean','vacant_dirty','vacant_inspected','occupied_clean','occupied_dirty','out_of_order','out_of_service') NOT NULL DEFAULT 'vacant_clean',
  `fo_status` ENUM('vacant','occupied','due_out','due_in','stayover') NOT NULL DEFAULT 'vacant',
  `id_htl_booking` INT UNSIGNED DEFAULT NULL,
  `ooo_reason` VARCHAR(128) DEFAULT NULL,
  `ooo_until` DATE DEFAULT NULL,
  `id_employee` INT UNSIGNED DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_room`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_room_status_log` (
  `id_pulse_room_status_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_room` INT UNSIGNED NOT NULL,
  `from_status` VARCHAR(32), `to_status` VARCHAR(32) NOT NULL,
  `id_employee` INT UNSIGNED, `source` VARCHAR(32) NOT NULL DEFAULT 'manual',
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_room_status_log`), KEY `room` (`id_room`,`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_housekeeping_task` (
  `id_pulse_housekeeping_task` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_room` INT UNSIGNED NOT NULL,
  `type` ENUM('clean','turndown','inspect','maintenance','minibar','linen','deep_clean') NOT NULL DEFAULT 'clean',
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('open','in_progress','done','skipped') NOT NULL DEFAULT 'open',
  `priority` TINYINT NOT NULL DEFAULT 5,
  `note` TEXT, `business_date` DATE NOT NULL,
  `date_add` DATETIME NOT NULL, `date_done` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_pulse_housekeeping_task`), KEY `room_status` (`id_room`,`status`), KEY `bdate` (`business_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_guest_profile` (
  `id_customer` INT UNSIGNED NOT NULL,
  `vip_level` TINYINT NOT NULL DEFAULT 0,
  `id_pulse_company` INT UNSIGNED DEFAULT NULL,
  `preferences` TEXT COMMENT 'JSON: pillow, floor, smoking, newspaper, etc.',
  `blacklisted` TINYINT(1) NOT NULL DEFAULT 0, `blacklist_reason` VARCHAR(255) DEFAULT NULL,
  `nationality` VARCHAR(3) DEFAULT NULL, `phone` VARCHAR(32) DEFAULT NULL, `address` VARCHAR(255) DEFAULT NULL,
  `stays` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `nights` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `lifetime_revenue` DECIMAL(20,6) NOT NULL DEFAULT 0, `last_stay` DATE DEFAULT NULL,
  `notes` TEXT, `special_dates` VARCHAR(255) DEFAULT NULL, `merged_into` INT UNSIGNED DEFAULT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_customer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_guest_identity` (
  `id_pulse_guest_identity` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_customer` INT UNSIGNED NOT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL,
  `id_type` ENUM('nin','passport','drivers_licence','voters_card','intl_passport','other') NOT NULL,
  `id_number` VARCHAR(64) NOT NULL, `issuing_country` VARCHAR(3) DEFAULT NULL, `expiry` DATE DEFAULT NULL,
  `scan_path` VARCHAR(255) DEFAULT NULL, `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_guest_identity`), KEY `cust` (`id_customer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_company` (
  `id_pulse_company` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(128) NOT NULL, `type` ENUM('corporate','travel_agent','government','group','other') NOT NULL DEFAULT 'corporate',
  `contact_name` VARCHAR(128), `email` VARCHAR(128), `phone` VARCHAR(32), `address` VARCHAR(255), `tin` VARCHAR(32),
  `credit_limit` DECIMAL(20,6) NOT NULL DEFAULT 0, `ledger_balance` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `discount_pct` DECIMAL(6,3) NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1, `payment_terms_days` SMALLINT NOT NULL DEFAULT 30, `auto_route_departments` VARCHAR(255) DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_company`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_charge_code` (
  `id_pulse_charge_code` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(16) NOT NULL, `name` VARCHAR(64) NOT NULL,
  `department` ENUM('rooms','fnb','minibar','spa','laundry','telephone','business_centre','misc','tax','payment','adjustment') NOT NULL,
  `default_price` DECIMAL(20,6) NOT NULL DEFAULT 0, `tax_rate` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `is_payment` TINYINT(1) NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_charge_code`), UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_folio` (
  `id_pulse_folio` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folio_no` VARCHAR(32) NOT NULL,
  `id_order` INT UNSIGNED DEFAULT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL,
  `id_customer` INT UNSIGNED DEFAULT NULL, `id_pulse_company` INT UNSIGNED DEFAULT NULL, `id_room` INT UNSIGNED DEFAULT NULL,
  `type` ENUM('guest','company','group','master','house') NOT NULL DEFAULT 'guest',
  `status` ENUM('open','closed','void') NOT NULL DEFAULT 'open',
  `total_charges` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_payments` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `balance` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `closed_by` INT UNSIGNED DEFAULT NULL, `date_closed` DATETIME DEFAULT NULL, `invoice_no` VARCHAR(32) DEFAULT NULL, `date_invoiced` DATETIME DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_folio`), UNIQUE KEY `folio_no` (`folio_no`), KEY `booking` (`id_htl_booking`), KEY `order` (`id_order`), KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_folio_line` (
  `id_pulse_folio_line` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_folio` INT UNSIGNED NOT NULL,
  `id_pulse_charge_code` INT UNSIGNED DEFAULT NULL,
  `department` VARCHAR(32) NOT NULL,
  `source` VARCHAR(32) NOT NULL COMMENT 'frontdesk|night_audit|pos|portal|payments|transfer|adjustment',
  `source_ref` VARCHAR(64) DEFAULT NULL,
  `description` VARCHAR(255) NOT NULL,
  `qty` DECIMAL(10,3) NOT NULL DEFAULT 1,
  `unit_price_tax_excl` DECIMAL(20,6) NOT NULL, `tax_rate` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `amount_tax_incl` DECIMAL(20,6) NOT NULL,
  `is_payment` TINYINT(1) NOT NULL DEFAULT 0, `payment_method` VARCHAR(32) DEFAULT NULL,
  `voided` TINYINT(1) NOT NULL DEFAULT 0, `void_reason` VARCHAR(128) DEFAULT NULL,
  `transferred_to` INT UNSIGNED DEFAULT NULL,
  `currency_iso` CHAR(3) DEFAULT NULL, `foreign_amount` DECIMAL(20,6) DEFAULT NULL, `exchange_rate` DECIMAL(13,6) DEFAULT NULL,
  `id_employee` INT UNSIGNED DEFAULT NULL, `id_cashier_session` INT UNSIGNED DEFAULT NULL,
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_folio_line`), KEY `folio` (`id_pulse_folio`), KEY `bdate_dept` (`business_date`,`department`), KEY `session` (`id_cashier_session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_cashier_session` (
  `id_pulse_cashier_session` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_employee` INT UNSIGNED NOT NULL,
  `opening_float` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `expected_cash` DECIMAL(20,6) DEFAULT NULL, `counted_cash` DECIMAL(20,6) DEFAULT NULL, `variance` DECIMAL(20,6) DEFAULT NULL,
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open', `blind_close` TINYINT(1) NOT NULL DEFAULT 0,
  `business_date` DATE NOT NULL, `note` VARCHAR(255),
  `date_open` DATETIME NOT NULL, `date_close` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_pulse_cashier_session`), KEY `emp_status` (`id_employee`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_room_move` (
  `id_pulse_room_move` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_htl_booking` INT UNSIGNED NOT NULL, `from_room` INT UNSIGNED NOT NULL, `to_room` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(128), `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_room_move`), KEY `booking` (`id_htl_booking`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_trace` (
  `id_pulse_trace` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('trace','message','wake_up','alert') NOT NULL DEFAULT 'trace',
  `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_room` INT UNSIGNED DEFAULT NULL, `id_customer` INT UNSIGNED DEFAULT NULL,
  `department` VARCHAR(32) NOT NULL DEFAULT 'frontdesk',
  `due_at` DATETIME NOT NULL, `text` VARCHAR(255) NOT NULL,
  `status` ENUM('open','done','cancelled') NOT NULL DEFAULT 'open',
  `id_employee` INT UNSIGNED, `resolved_by` INT UNSIGNED, `date_add` DATETIME NOT NULL, `date_resolved` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_pulse_trace`), KEY `due` (`status`,`due_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_night_audit` (
  `id_pulse_night_audit` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `business_date` DATE NOT NULL, `id_employee` INT UNSIGNED NOT NULL,
  `rooms_total` SMALLINT, `rooms_ooo` SMALLINT, `rooms_occupied` SMALLINT, `arrivals` SMALLINT, `departures` SMALLINT, `no_shows` SMALLINT,
  `room_revenue` DECIMAL(20,6), `fnb_revenue` DECIMAL(20,6), `other_revenue` DECIMAL(20,6), `tax` DECIMAL(20,6), `payments` DECIMAL(20,6),
  `guest_ledger` DECIMAL(20,6), `city_ledger` DECIMAL(20,6),
  `status` ENUM('running','closed','failed') NOT NULL DEFAULT 'running',
  `log` TEXT, `date_add` DATETIME NOT NULL, `date_end` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_pulse_night_audit`), UNIQUE KEY `bdate` (`business_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Seed charge codes (Nigerian defaults: 7.5% VAT, 5% consumption tax on F&B — adjust in Settings)
INSERT IGNORE INTO `PREFIX_pulse_charge_code` (`code`,`name`,`department`,`default_price`,`tax_rate`,`is_payment`) VALUES
('ROOM','Room Charge','rooms',0,7.5,0),
('EXTB','Extra Bed','rooms',0,7.5,0),
('LATE','Late Checkout','rooms',0,7.5,0),
('RSVC','Room Service','fnb',0,12.5,0),
('REST','Restaurant','fnb',0,12.5,0),
('BAR','Bar','fnb',0,12.5,0),
('MINI','Minibar','minibar',0,12.5,0),
('LNDY','Laundry','laundry',0,7.5,0),
('SPA','Spa','spa',0,7.5,0),
('MISC','Miscellaneous','misc',0,7.5,0),
('DMG','Damage / Loss','misc',0,0,0),
('ADJ','Adjustment','adjustment',0,0,0),
('CASH','Cash','payment',0,0,1),
('POS','Card (POS terminal)','payment',0,0,1),
('TRF','Bank Transfer','payment',0,0,1),
('ONL','Online Gateway','payment',0,0,1),
('CL','City Ledger (Company)','payment',0,0,1),
('DEP','Deposit Applied','payment',0,0,1);

-- ===================== v1.1 additions =====================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_group_block_allot` (
  `id_pulse_group_block_allot` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_group_block` INT UNSIGNED NOT NULL, `id_product` INT UNSIGNED NOT NULL COMMENT 'room type',
  `blocked` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `picked_up` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_pulse_group_block_allot`), UNIQUE KEY `blk_type` (`id_pulse_group_block`,`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_booking_ext` (
  `id_htl_booking` INT UNSIGNED NOT NULL,
  `id_pulse_group_block` INT UNSIGNED DEFAULT NULL,
  `day_use` TINYINT(1) NOT NULL DEFAULT 0, `day_use_until` TIME DEFAULT NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'web' COMMENT 'web|walkin|phone|group|ota|waitlist',
  `precheckin_token` CHAR(40) DEFAULT NULL, `precheckin_done` TINYINT(1) NOT NULL DEFAULT 0,
  `checkout_token` CHAR(40) DEFAULT NULL, `card_auth_ref` VARCHAR(64) DEFAULT NULL, `card_auth_amount` DECIMAL(20,6) DEFAULT NULL,
  `late_fee_posted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_htl_booking`), KEY `block` (`id_pulse_group_block`), KEY `pct` (`precheckin_token`), KEY `cot` (`checkout_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_waitlist` (
  `id_pulse_waitlist` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_customer` INT UNSIGNED DEFAULT NULL, `guest_name` VARCHAR(128) NOT NULL, `phone` VARCHAR(32), `email` VARCHAR(128),
  `id_product` INT UNSIGNED NOT NULL, `date_from` DATE NOT NULL, `date_to` DATE NOT NULL, `rooms` TINYINT NOT NULL DEFAULT 1,
  `priority` TINYINT NOT NULL DEFAULT 5, `status` ENUM('waiting','offered','booked','expired','cancelled') NOT NULL DEFAULT 'waiting',
  `offered_at` DATETIME DEFAULT NULL, `note` VARCHAR(255), `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_waitlist`), KEY `st` (`status`,`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_overbooking` (
  `id_product` INT UNSIGNED NOT NULL, `max_over` TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_registration_card` (
  `id_pulse_registration_card` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_htl_booking` INT UNSIGNED NOT NULL, `id_customer` INT UNSIGNED NOT NULL,
  `terms_version` VARCHAR(16) NOT NULL, `terms_accepted` TINYINT(1) NOT NULL DEFAULT 0,
  `signature` MEDIUMTEXT COMMENT 'PNG data URL', `signed_name` VARCHAR(128), `ip` VARCHAR(45), `channel` ENUM('desk','precheckin') NOT NULL DEFAULT 'desk',
  `snapshot` TEXT COMMENT 'JSON of guest/stay details at signing', `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_registration_card`), KEY `bk` (`id_htl_booking`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_upsell_offer` (
  `id_pulse_upsell_offer` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('room_upgrade','early_checkin','late_checkout','breakfast','package','other') NOT NULL,
  `name` VARCHAR(128) NOT NULL, `charge_code` VARCHAR(16) NOT NULL, `price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `per` ENUM('stay','night','person') NOT NULL DEFAULT 'stay', `min_avail_pct` TINYINT NOT NULL DEFAULT 0 COMMENT 'only offer when availability >= this %',
  `active` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_pulse_upsell_offer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_upsell_sale` (
  `id_pulse_upsell_sale` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_upsell_offer` INT UNSIGNED, `id_htl_booking` INT UNSIGNED NOT NULL, `amount_tax_incl` DECIMAL(20,6) NOT NULL,
  `id_employee` INT UNSIGNED, `stage` ENUM('checkin','instay','precheckin') NOT NULL DEFAULT 'checkin', `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_upsell_sale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_routing_rule` (
  `id_pulse_routing_rule` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` ENUM('booking','company','group') NOT NULL, `id_scope` INT UNSIGNED NOT NULL,
  `department` VARCHAR(32) NOT NULL COMMENT 'rooms|fnb|minibar|...|*',
  `target` ENUM('guest','company','master') NOT NULL, `id_target_folio` INT UNSIGNED DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_routing_rule`), KEY `sc` (`scope`,`id_scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_drawer_movement` (
  `id_pulse_drawer_movement` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pulse_cashier_session` INT UNSIGNED NOT NULL,
  `type` ENUM('blind_drop','paid_out','float_in','float_out','correction') NOT NULL,
  `amount` DECIMAL(20,6) NOT NULL, `note` VARCHAR(255), `id_employee` INT UNSIGNED, `witness` INT UNSIGNED DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_drawer_movement`), KEY `sess` (`id_pulse_cashier_session`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_ticket_note` (
  `id_pulse_ticket_note` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_ticket` INT UNSIGNED NOT NULL,
  `note` TEXT NOT NULL, `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_ticket_note`), KEY `t` (`id_pulse_ticket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_comms_log` (
  `id_pulse_comms_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel` ENUM('email','sms','whatsapp') NOT NULL, `template` VARCHAR(32) NOT NULL, `to_addr` VARCHAR(128) NOT NULL,
  `id_htl_booking` INT UNSIGNED, `id_customer` INT UNSIGNED, `status` ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `provider_ref` VARCHAR(64), `error` VARCHAR(255), `date_add` DATETIME NOT NULL, `date_sent` DATETIME,
  PRIMARY KEY (`id_pulse_comms_log`), KEY `bk` (`id_htl_booking`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pabx_log` (
  `id_pulse_pabx_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `extension` VARCHAR(16) NOT NULL, `id_room` INT UNSIGNED, `event` ENUM('call','status_code','wakeup_result') NOT NULL,
  `payload` VARCHAR(255), `duration_sec` INT UNSIGNED, `cost` DECIMAL(20,6), `posted` TINYINT(1) NOT NULL DEFAULT 0, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pabx_log`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

