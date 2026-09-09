CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_gateway` (
  `id_pulse_pay_gateway` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(64) NOT NULL, `adapter` VARCHAR(64) NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 0, `test_mode` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0, `currency` CHAR(3) NOT NULL DEFAULT 'NGN',
  `endpoint` VARCHAR(255) DEFAULT NULL COMMENT 'API base, overridable for sandbox or a local aggregator',
  `public_key` VARCHAR(255) DEFAULT NULL, `secret_key` TEXT COMMENT 'encrypted with PulseCoreService', `merchant_id` VARCHAR(64) DEFAULT NULL,
  `webhook_secret` TEXT COMMENT 'encrypted', `extra` TEXT COMMENT 'encrypted JSON of adapter specific settings',
  `fee_percent` DECIMAL(6,3) NOT NULL DEFAULT 0, `fee_flat` DECIMAL(20,6) NOT NULL DEFAULT 0, `fee_cap` DECIMAL(20,6) NOT NULL DEFAULT 0, `fee_flat_waive_below` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `capabilities` VARCHAR(255) NOT NULL DEFAULT '', `channels` VARCHAR(128) NOT NULL DEFAULT 'web,desk,pos,portal,link',
  `last_error` VARCHAR(255) DEFAULT NULL, `last_ok_at` DATETIME DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_gateway`), UNIQUE KEY `code` (`code`), KEY `act` (`active`,`sort`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_transaction` (
  `id_pulse_pay_transaction` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(48) NOT NULL COMMENT 'our reference, sent to the gateway', `idempotency_key` VARCHAR(64) NOT NULL,
  `gateway` VARCHAR(32) NOT NULL DEFAULT 'manual', `gateway_ref` VARCHAR(96) DEFAULT NULL,
  `type` ENUM('charge','preauth','capture','refund','void') NOT NULL DEFAULT 'charge',
  `purpose` ENUM('deposit','folio','pos','invoice','preauth','other') NOT NULL DEFAULT 'folio',
  `channel` ENUM('web','desk','pos','portal','link','terminal') NOT NULL DEFAULT 'desk',
  `method` ENUM('card','transfer','mobile_money','online','ussd','cash','other') NOT NULL DEFAULT 'card',
  `state` ENUM('intent','awaiting_confirmation','authorized','captured','partially_captured','settled','refunded','partially_refunded','voided','failed','expired') NOT NULL DEFAULT 'intent',
  `amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `amount_captured` DECIMAL(20,6) NOT NULL DEFAULT 0, `amount_refunded` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `surcharge` DECIMAL(20,6) NOT NULL DEFAULT 0, `surcharge_tax` DECIMAL(20,6) NOT NULL DEFAULT 0, `currency` CHAR(3) NOT NULL DEFAULT 'NGN',
  `fee` DECIMAL(20,6) NOT NULL DEFAULT 0, `net` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `auth_code` VARCHAR(32) DEFAULT NULL, `rrn` VARCHAR(32) DEFAULT NULL, `card_last4` VARCHAR(4) DEFAULT NULL, `card_brand` VARCHAR(24) DEFAULT NULL, `bank` VARCHAR(64) DEFAULT NULL,
  `auth_token` TEXT COMMENT 'encrypted reusable authorization / card token',
  `id_customer` INT UNSIGNED DEFAULT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_pulse_folio` INT UNSIGNED DEFAULT NULL,
  `id_pulse_pos_check` INT UNSIGNED DEFAULT NULL, `id_pulse_pay_link` INT UNSIGNED DEFAULT NULL, `id_order` INT UNSIGNED DEFAULT NULL, `id_parent` BIGINT UNSIGNED DEFAULT NULL,
  `customer_name` VARCHAR(128) DEFAULT NULL, `customer_email` VARCHAR(128) DEFAULT NULL, `customer_phone` VARCHAR(32) DEFAULT NULL, `description` VARCHAR(255) DEFAULT NULL,
  `hold_type` ENUM('none','gateway','deferred','manual') NOT NULL DEFAULT 'none' COMMENT 'how a preauth is really held',
  `expires_at` DATETIME DEFAULT NULL, `authorized_at` DATETIME DEFAULT NULL, `captured_at` DATETIME DEFAULT NULL, `settled_at` DATETIME DEFAULT NULL,
  `verify_attempts` SMALLINT NOT NULL DEFAULT 0, `next_verify_at` DATETIME DEFAULT NULL, `failed_reason` VARCHAR(255) DEFAULT NULL,
  `id_employee` INT UNSIGNED DEFAULT NULL, `raw` MEDIUMTEXT, `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_transaction`), UNIQUE KEY `ref` (`reference`), UNIQUE KEY `idem` (`idempotency_key`),
  KEY `gwref` (`gateway`,`gateway_ref`), KEY `st` (`state`), KEY `bdate` (`business_date`,`gateway`), KEY `booking` (`id_htl_booking`), KEY `folio` (`id_pulse_folio`), KEY `poscheck` (`id_pulse_pos_check`), KEY `sweep` (`state`,`next_verify_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_posting` (
  `id_pulse_pay_posting` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pay_transaction` BIGINT UNSIGNED NOT NULL,
  `gateway_ref` VARCHAR(96) NOT NULL COMMENT 'gateway ref, or our reference when the gateway has none',
  `purpose` VARCHAR(32) NOT NULL COMMENT 'capture|refund|surcharge|fee|pos_settle', `target` ENUM('folio','pos','expense') NOT NULL DEFAULT 'folio',
  `id_target` INT UNSIGNED DEFAULT NULL, `id_line` BIGINT UNSIGNED DEFAULT NULL, `amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_posting`), UNIQUE KEY `once` (`gateway_ref`,`purpose`), KEY `tx` (`id_pulse_pay_transaction`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_log` (
  `id_pulse_pay_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `gateway` VARCHAR(32) NOT NULL, `operation` VARCHAR(32) NOT NULL,
  `reference` VARCHAR(48) DEFAULT NULL, `url` VARCHAR(255) DEFAULT NULL, `method` VARCHAR(8) NOT NULL DEFAULT 'POST',
  `request` MEDIUMTEXT COMMENT 'secrets redacted', `response` MEDIUMTEXT, `http_code` SMALLINT NOT NULL DEFAULT 0, `attempt` TINYINT NOT NULL DEFAULT 1,
  `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0, `ok` TINYINT(1) NOT NULL DEFAULT 0, `error` VARCHAR(255) DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_log`), KEY `ref` (`reference`), KEY `gw` (`gateway`,`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_link` (
  `id_pulse_pay_link` INT UNSIGNED NOT NULL AUTO_INCREMENT, `token` VARCHAR(40) NOT NULL, `short_code` VARCHAR(12) NOT NULL,
  `purpose` ENUM('deposit','folio','pos','invoice','other') NOT NULL DEFAULT 'folio', `gateway` VARCHAR(32) DEFAULT NULL,
  `amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `amount_locked` TINYINT(1) NOT NULL DEFAULT 1, `min_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `currency` CHAR(3) NOT NULL DEFAULT 'NGN',
  `max_uses` SMALLINT NOT NULL DEFAULT 1, `uses` SMALLINT NOT NULL DEFAULT 0, `amount_paid` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `status` ENUM('open','paid','partly_paid','expired','cancelled') NOT NULL DEFAULT 'open',
  `id_customer` INT UNSIGNED DEFAULT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_pulse_folio` INT UNSIGNED DEFAULT NULL, `id_pulse_pos_check` INT UNSIGNED DEFAULT NULL, `id_pulse_company` INT UNSIGNED DEFAULT NULL,
  `customer_name` VARCHAR(128) DEFAULT NULL, `customer_email` VARCHAR(128) DEFAULT NULL, `customer_phone` VARCHAR(32) DEFAULT NULL,
  `title` VARCHAR(128) DEFAULT NULL, `note` VARCHAR(255) DEFAULT NULL, `expires_at` DATETIME DEFAULT NULL, `sent_at` DATETIME DEFAULT NULL, `paid_at` DATETIME DEFAULT NULL,
  `id_employee` INT UNSIGNED DEFAULT NULL, `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_link`), UNIQUE KEY `tok` (`token`), UNIQUE KEY `short` (`short_code`), KEY `st` (`status`,`expires_at`), KEY `booking` (`id_htl_booking`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_event` (
  `id_pulse_pay_event` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `gateway` VARCHAR(32) NOT NULL, `event_id` VARCHAR(128) NOT NULL COMMENT 'gateway event id or hash of the body',
  `event_type` VARCHAR(64) DEFAULT NULL, `reference` VARCHAR(48) DEFAULT NULL, `signature_ok` TINYINT(1) NOT NULL DEFAULT 0,
  `handled` TINYINT(1) NOT NULL DEFAULT 0, `result` VARCHAR(255) DEFAULT NULL, `payload` MEDIUMTEXT, `remote_ip` VARCHAR(45) DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_event`), UNIQUE KEY `once` (`gateway`,`event_id`), KEY `ref` (`reference`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_terminal` (
  `id_pulse_pay_terminal` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `label` VARCHAR(64) NOT NULL,
  `bank` VARCHAR(64) DEFAULT NULL, `terminal_id` VARCHAR(32) DEFAULT NULL COMMENT 'TID printed on the POS slip', `merchant_id` VARCHAR(32) DEFAULT NULL,
  `station` VARCHAR(64) DEFAULT NULL COMMENT 'reception, restaurant, bar', `id_pulse_pos_outlet` INT UNSIGNED DEFAULT NULL,
  `mode` ENUM('manual','claim') NOT NULL DEFAULT 'claim' COMMENT 'manual = key the RRN in, claim = a terminal app polls the queue',
  `active` TINYINT(1) NOT NULL DEFAULT 1, `last_seen` DATETIME DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_terminal`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_terminal_request` (
  `id_pulse_pay_terminal_request` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pay_terminal` INT UNSIGNED DEFAULT NULL,
  `id_pulse_pay_transaction` BIGINT UNSIGNED DEFAULT NULL, `reference` VARCHAR(48) NOT NULL, `amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `station` VARCHAR(64) DEFAULT NULL, `requested_by` INT UNSIGNED DEFAULT NULL, `source` ENUM('pos','desk','api') NOT NULL DEFAULT 'desk',
  `id_pulse_pos_check` INT UNSIGNED DEFAULT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_pulse_folio` INT UNSIGNED DEFAULT NULL, `auto_settle` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('queued','claimed','approved','declined','cancelled','expired') NOT NULL DEFAULT 'queued',
  `claimed_at` DATETIME DEFAULT NULL, `answered_at` DATETIME DEFAULT NULL, `expires_at` DATETIME DEFAULT NULL,
  `rrn` VARCHAR(32) DEFAULT NULL, `auth_code` VARCHAR(32) DEFAULT NULL, `card_last4` VARCHAR(4) DEFAULT NULL, `card_brand` VARCHAR(24) DEFAULT NULL, `response_message` VARCHAR(128) DEFAULT NULL,
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_terminal_request`), UNIQUE KEY `ref` (`reference`), KEY `q` (`status`,`id_pulse_pay_terminal`), KEY `bdate` (`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_refund` (
  `id_pulse_pay_refund` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pay_transaction` BIGINT UNSIGNED NOT NULL, `reference` VARCHAR(48) NOT NULL,
  `gateway` VARCHAR(32) NOT NULL, `gateway_ref` VARCHAR(96) DEFAULT NULL, `amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) DEFAULT NULL, `status` ENUM('requested','processing','done','failed') NOT NULL DEFAULT 'requested',
  `requested_by` INT UNSIGNED DEFAULT NULL, `approved_by` INT UNSIGNED DEFAULT NULL, `failed_reason` VARCHAR(255) DEFAULT NULL, `raw` MEDIUMTEXT,
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_refund`), UNIQUE KEY `ref` (`reference`), KEY `tx` (`id_pulse_pay_transaction`), KEY `bdate` (`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_dispute` (
  `id_pulse_pay_dispute` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pay_transaction` BIGINT UNSIGNED DEFAULT NULL, `gateway` VARCHAR(32) NOT NULL,
  `gateway_ref` VARCHAR(96) DEFAULT NULL, `dispute_ref` VARCHAR(96) DEFAULT NULL, `amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `category` ENUM('chargeback','fraud','service','duplicate','other') NOT NULL DEFAULT 'chargeback',
  `status` ENUM('open','evidence_sent','won','lost','cancelled') NOT NULL DEFAULT 'open',
  `reason` VARCHAR(255) DEFAULT NULL, `evidence` TEXT, `due_at` DATETIME DEFAULT NULL, `id_employee` INT UNSIGNED DEFAULT NULL,
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_dispute`), KEY `tx` (`id_pulse_pay_transaction`), KEY `st` (`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_settlement` (
  `id_pulse_pay_settlement` INT UNSIGNED NOT NULL AUTO_INCREMENT, `gateway` VARCHAR(32) NOT NULL, `statement_ref` VARCHAR(64) DEFAULT NULL,
  `filename` VARCHAR(255) DEFAULT NULL, `period_from` DATE DEFAULT NULL, `period_to` DATE DEFAULT NULL,
  `rows_total` INT UNSIGNED NOT NULL DEFAULT 0, `rows_matched` INT UNSIGNED NOT NULL DEFAULT 0, `rows_unmatched` INT UNSIGNED NOT NULL DEFAULT 0, `rows_variance` INT UNSIGNED NOT NULL DEFAULT 0,
  `gross_total` DECIMAL(20,6) NOT NULL DEFAULT 0, `fee_total` DECIMAL(20,6) NOT NULL DEFAULT 0, `net_total` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `fee_expense_posted` TINYINT(1) NOT NULL DEFAULT 0, `status` ENUM('imported','reconciled','closed') NOT NULL DEFAULT 'imported',
  `id_employee` INT UNSIGNED DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_settlement`), KEY `gw` (`gateway`,`period_to`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_settlement_line` (
  `id_pulse_pay_settlement_line` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_pay_settlement` INT UNSIGNED NOT NULL,
  `gateway_ref` VARCHAR(96) DEFAULT NULL, `reference` VARCHAR(48) DEFAULT NULL, `paid_at` DATETIME DEFAULT NULL,
  `gross` DECIMAL(20,6) NOT NULL DEFAULT 0, `fee` DECIMAL(20,6) NOT NULL DEFAULT 0, `net` DECIMAL(20,6) NOT NULL DEFAULT 0, `currency` CHAR(3) NOT NULL DEFAULT 'NGN',
  `id_pulse_pay_transaction` BIGINT UNSIGNED DEFAULT NULL, `match_state` ENUM('matched','fee_variance','amount_variance','unmatched','duplicate','extra') NOT NULL DEFAULT 'unmatched',
  `variance` DECIMAL(20,6) NOT NULL DEFAULT 0, `note` VARCHAR(255) DEFAULT NULL, `raw` TEXT,
  PRIMARY KEY (`id_pulse_pay_settlement_line`), KEY `s` (`id_pulse_pay_settlement`,`match_state`), KEY `gwref` (`gateway_ref`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_pay_daily` (
  `id_pulse_pay_daily` INT UNSIGNED NOT NULL AUTO_INCREMENT, `business_date` DATE NOT NULL, `gateway` VARCHAR(32) NOT NULL, `channel` VARCHAR(16) NOT NULL,
  `txn_count` INT UNSIGNED NOT NULL DEFAULT 0, `gross` DECIMAL(20,6) NOT NULL DEFAULT 0, `refunds` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `fee` DECIMAL(20,6) NOT NULL DEFAULT 0, `net` DECIMAL(20,6) NOT NULL DEFAULT 0, `failures` INT UNSIGNED NOT NULL DEFAULT 0,
  `open_preauth` DECIMAL(20,6) NOT NULL DEFAULT 0, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_pay_daily`), UNIQUE KEY `d` (`business_date`,`gateway`,`channel`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_pay_gateway` (`code`,`name`,`adapter`,`active`,`test_mode`,`sort`,`currency`,`endpoint`,`fee_percent`,`fee_flat`,`fee_cap`,`fee_flat_waive_below`,`capabilities`,`channels`,`date_add`,`date_upd`) VALUES
('paystack','Paystack','PulsePayPaystack',0,1,10,'NGN','https://api.paystack.co',1.5,100,2000,2500,'charge,capture,refund,verify,link,webhook,token,deferred_preauth','web,desk,portal,link,pos',NOW(),NOW()),
('flutterwave','Flutterwave','PulsePayFlutterwave',0,1,20,'NGN','https://api.flutterwave.com',1.4,0,2000,0,'charge,capture,refund,verify,link,webhook,token,deferred_preauth','web,desk,portal,link,pos',NOW(),NOW()),
('interswitch','Interswitch WebPAY / Quickteller','PulsePayInterswitch',0,1,30,'NGN','https://webpay.interswitchng.com',1.5,0,2000,0,'charge,verify,link,webhook','web,portal,link',NOW(),NOW()),
('manual','Bank transfer & bank POS terminal','PulsePayManual',1,0,40,'NGN',NULL,0,0,0,0,'charge,capture,void,refund,manual_preauth,terminal','desk,pos,terminal',NOW(),NOW());

INSERT IGNORE INTO `PREFIX_pulse_pay_terminal` (`code`,`label`,`bank`,`terminal_id`,`station`,`mode`,`active`,`date_add`) VALUES
('RECEPTION','Reception POS terminal','GTBank','2033XXXX','reception','manual',1,NOW()),
('RESTAURANT','Restaurant POS terminal','Moniepoint','2P66XXXX','restaurant','manual',1,NOW());
