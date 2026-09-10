CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_device` (
  `id_pulse_gp_device` INT UNSIGNED NOT NULL AUTO_INCREMENT, `uid` VARCHAR(64) NOT NULL COMMENT 'normalised MAC or serial — the identity the launcher boots with',
  `mac` VARCHAR(32), `serial` VARCHAR(64), `type` ENUM('tv','tablet','phone','cast','kiosk') NOT NULL DEFAULT 'tv',
  `model` VARCHAR(64), `firmware` VARCHAR(64), `app_version` VARCHAR(32), `ip` VARCHAR(45), `label` VARCHAR(64),
  `id_room` INT UNSIGNED DEFAULT NULL, `room_num` VARCHAR(16), `floor` VARCHAR(8), `token` CHAR(64) NOT NULL,
  `locale` VARCHAR(5) NOT NULL DEFAULT 'en', `status` ENUM('pending','active','blocked','retired') NOT NULL DEFAULT 'pending',
  `last_seen` DATETIME DEFAULT NULL, `last_wipe` DATETIME DEFAULT NULL, `paired_at` DATETIME DEFAULT NULL, `paired_by` INT UNSIGNED DEFAULT NULL,
  `token_claimed_at` DATETIME DEFAULT NULL COMMENT 'a token is handed out once; re-issue from the desk if a set loses it',
  `boots` INT UNSIGNED NOT NULL DEFAULT 0, `note` VARCHAR(255), `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_device`), UNIQUE KEY `uid` (`uid`), UNIQUE KEY `token` (`token`), KEY `room` (`id_room`), KEY `st` (`status`,`last_seen`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_session` (
  `id_pulse_gp_session` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `sid` CHAR(32) NOT NULL, `id_pulse_gp_device` INT UNSIGNED NOT NULL,
  `id_room` INT UNSIGNED DEFAULT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_customer` INT UNSIGNED DEFAULT NULL,
  `guest_name` VARCHAR(128), `locale` VARCHAR(5) NOT NULL DEFAULT 'en', `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL, `revoked` TINYINT(1) NOT NULL DEFAULT 0, `revoke_reason` VARCHAR(32),
  `ip` VARCHAR(45), `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_session`), UNIQUE KEY `sid` (`sid`), KEY `dev` (`id_pulse_gp_device`,`revoked`), KEY `booking` (`id_htl_booking`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_command` (
  `id_pulse_gp_command` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_gp_device` INT UNSIGNED NOT NULL,
  `type` ENUM('reload','message','wipe','lock','unlock','notify','order_ready','folio_refresh','channel','volume','screenshot') NOT NULL,
  `payload` TEXT, `status` ENUM('queued','sent','acked','expired') NOT NULL DEFAULT 'queued', `id_employee` INT UNSIGNED DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_sent` DATETIME DEFAULT NULL, `date_ack` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_pulse_gp_command`), KEY `dev` (`id_pulse_gp_device`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_message` (
  `id_pulse_gp_message` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_gp_device` INT UNSIGNED DEFAULT NULL,
  `id_room` INT UNSIGNED DEFAULT NULL, `room_num` VARCHAR(16), `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_customer` INT UNSIGNED DEFAULT NULL,
  `direction` ENUM('guest','desk') NOT NULL DEFAULT 'guest', `body` TEXT NOT NULL, `id_employee` INT UNSIGNED DEFAULT NULL,
  `read_by_guest` TINYINT(1) NOT NULL DEFAULT 0, `read_by_desk` TINYINT(1) NOT NULL DEFAULT 0, `locale` VARCHAR(5) NOT NULL DEFAULT 'en',
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_message`), KEY `booking` (`id_htl_booking`,`date_add`), KEY `room` (`id_room`,`date_add`), KEY `unread` (`direction`,`read_by_desk`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_request` (
  `id_pulse_gp_request` INT UNSIGNED NOT NULL AUTO_INCREMENT, `request_no` VARCHAR(16) NOT NULL,
  `type` ENUM('housekeeping','turndown','towels','amenities','maintenance','laundry','dnd_on','dnd_off','mur','wakeup','late_checkout','express_checkout','transport','other') NOT NULL,
  `id_pulse_gp_device` INT UNSIGNED DEFAULT NULL, `id_room` INT UNSIGNED DEFAULT NULL, `room_num` VARCHAR(16),
  `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_customer` INT UNSIGNED DEFAULT NULL, `guest_name` VARCHAR(128),
  `detail` VARCHAR(255), `qty` SMALLINT NOT NULL DEFAULT 1, `scheduled_for` DATETIME DEFAULT NULL,
  `status` ENUM('new','ack','in_progress','done','cancelled','failed') NOT NULL DEFAULT 'new',
  `id_pulse_ticket` INT UNSIGNED DEFAULT NULL, `id_hk_task` INT UNSIGNED DEFAULT NULL, `ext_ref` VARCHAR(64), `fail_reason` VARCHAR(255),
  `source` VARCHAR(16) NOT NULL DEFAULT 'portal', `locale` VARCHAR(5) NOT NULL DEFAULT 'en', `business_date` DATE NOT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_request`), UNIQUE KEY `no` (`request_no`), KEY `st` (`status`,`business_date`), KEY `room` (`id_room`), KEY `sched` (`type`,`scheduled_for`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_order` (
  `id_pulse_gp_order` INT UNSIGNED NOT NULL AUTO_INCREMENT, `client_id` VARCHAR(40) NOT NULL COMMENT 'client-generated, makes offline replay idempotent',
  `id_pulse_gp_device` INT UNSIGNED DEFAULT NULL, `id_room` INT UNSIGNED DEFAULT NULL, `room_num` VARCHAR(16),
  `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_customer` INT UNSIGNED DEFAULT NULL,
  `id_pulse_pos_check` INT UNSIGNED DEFAULT NULL, `check_no` VARCHAR(16),
  `status` ENUM('placed','preparing','ready','delivered','cancelled','failed') NOT NULL DEFAULT 'placed',
  `items_json` TEXT, `items_count` SMALLINT NOT NULL DEFAULT 0, `total` DECIMAL(20,6) NOT NULL DEFAULT 0, `note` VARCHAR(255), `fail_reason` VARCHAR(255),
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL, `date_ready` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_pulse_gp_order`), UNIQUE KEY `cid` (`client_id`), KEY `room` (`id_room`,`status`), KEY `chk` (`id_pulse_pos_check`), KEY `bd` (`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_page` (
  `id_pulse_gp_page` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL,
  `category` ENUM('service','dining','spa','facility','transport','emergency','rules','wifi','attraction','info') NOT NULL DEFAULT 'info',
  `icon` VARCHAR(32), `image` VARCHAR(255), `phone` VARCHAR(32), `extension` VARCHAR(8), `opens` VARCHAR(64), `location` VARCHAR(64),
  `room_types` VARCHAR(255) DEFAULT NULL COMMENT 'csv id_product of room types, NULL=all', `sort` SMALLINT NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_page`), UNIQUE KEY `code` (`code`), KEY `cat` (`category`,`sort`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_page_lang` (
  `id_pulse_gp_page` INT UNSIGNED NOT NULL, `lang` VARCHAR(5) NOT NULL DEFAULT 'en',
  `title` VARCHAR(128) NOT NULL, `summary` VARCHAR(255), `body` TEXT,
  PRIMARY KEY (`id_pulse_gp_page`,`lang`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_promo` (
  `id_pulse_gp_promo` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `image` VARCHAR(255),
  `placement` ENUM('home','dining','entertainment','directory','checkout') NOT NULL DEFAULT 'home', `target` VARCHAR(32) COMMENT 'portal section the banner opens',
  `day_start` TIME NOT NULL DEFAULT '00:00:00', `day_end` TIME NOT NULL DEFAULT '23:59:59', `date_from` DATE DEFAULT NULL, `date_to` DATE DEFAULT NULL,
  `room_types` VARCHAR(255) DEFAULT NULL, `sort` SMALLINT NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_promo`), UNIQUE KEY `code` (`code`), KEY `pl` (`placement`,`active`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_promo_lang` (
  `id_pulse_gp_promo` INT UNSIGNED NOT NULL, `lang` VARCHAR(5) NOT NULL DEFAULT 'en',
  `title` VARCHAR(128) NOT NULL, `body` VARCHAR(255), `cta` VARCHAR(48),
  PRIMARY KEY (`id_pulse_gp_promo`,`lang`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_channel` (
  `id_pulse_gp_channel` INT UNSIGNED NOT NULL AUTO_INCREMENT, `number` SMALLINT UNSIGNED NOT NULL, `name` VARCHAR(64) NOT NULL, `logo` VARCHAR(255),
  `url` VARCHAR(255) NOT NULL COMMENT 'udp://@239.x.x.x:port or http(s) HLS from the headend',
  `category` ENUM('general','news','sport','movies','series','kids','music','documentary','religious','local','adult') NOT NULL DEFAULT 'general',
  `source` VARCHAR(32) NOT NULL DEFAULT 'dstv', `adult` TINYINT(1) NOT NULL DEFAULT 0, `hd` TINYINT(1) NOT NULL DEFAULT 0,
  `sort` SMALLINT NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_channel`), UNIQUE KEY `num` (`number`), KEY `cat` (`category`,`sort`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_vod` (
  `id_pulse_gp_vod` INT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(128) NOT NULL, `poster` VARCHAR(255), `synopsis` TEXT,
  `stream_url` VARCHAR(255) NOT NULL, `category` VARCHAR(32) NOT NULL DEFAULT 'movie', `rating` VARCHAR(8) NOT NULL DEFAULT 'PG',
  `year` SMALLINT UNSIGNED DEFAULT NULL, `duration_min` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `language` VARCHAR(32) NOT NULL DEFAULT 'English',
  `price` DECIMAL(20,6) NOT NULL DEFAULT 0, `free` TINYINT(1) NOT NULL DEFAULT 1, `adult` TINYINT(1) NOT NULL DEFAULT 0,
  `sort` SMALLINT NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_vod`), KEY `cat` (`category`,`sort`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_vod_play` (
  `id_pulse_gp_vod_play` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_gp_vod` INT UNSIGNED NOT NULL, `title` VARCHAR(128) NOT NULL,
  `id_pulse_gp_device` INT UNSIGNED DEFAULT NULL, `id_room` INT UNSIGNED DEFAULT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_customer` INT UNSIGNED DEFAULT NULL,
  `price` DECIMAL(20,6) NOT NULL DEFAULT 0, `posted_line` BIGINT UNSIGNED DEFAULT NULL,
  `status` ENUM('started','charged','free','cancelled','refunded') NOT NULL DEFAULT 'started', `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_vod_play`), KEY `booking` (`id_htl_booking`), KEY `bd` (`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_feedback` (
  `id_pulse_gp_feedback` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_room` INT UNSIGNED DEFAULT NULL, `room_num` VARCHAR(16),
  `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_customer` INT UNSIGNED DEFAULT NULL, `guest_name` VARCHAR(128), `email` VARCHAR(128),
  `rating_overall` TINYINT UNSIGNED NOT NULL DEFAULT 0, `rating_room` TINYINT UNSIGNED NOT NULL DEFAULT 0, `rating_service` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `rating_fnb` TINYINT UNSIGNED NOT NULL DEFAULT 0, `rating_cleanliness` TINYINT UNSIGNED NOT NULL DEFAULT 0, `nps` TINYINT DEFAULT NULL, `would_return` TINYINT(1) NOT NULL DEFAULT 1,
  `comment` TEXT, `locale` VARCHAR(5) NOT NULL DEFAULT 'en', `source` VARCHAR(16) NOT NULL DEFAULT 'portal',
  `crm_synced` TINYINT(1) NOT NULL DEFAULT 0, `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_feedback`), KEY `booking` (`id_htl_booking`), KEY `bd` (`business_date`), KEY `crm` (`crm_synced`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_cast` (
  `id_pulse_gp_cast` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_gp_device` INT UNSIGNED NOT NULL, `id_room` INT UNSIGNED DEFAULT NULL,
  `code` VARCHAR(8) NOT NULL, `pin` VARCHAR(8) NOT NULL, `protocol` ENUM('chromecast','airplay','miracast','dlna') NOT NULL DEFAULT 'chromecast',
  `guest_device` VARCHAR(64), `status` ENUM('waiting','paired','expired','ended') NOT NULL DEFAULT 'waiting',
  `expires_at` DATETIME NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_cast`), KEY `dev` (`id_pulse_gp_device`,`status`), KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_control_point` (
  `id_pulse_gp_control_point` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_room` INT UNSIGNED NOT NULL, `code` VARCHAR(32) NOT NULL,
  `type` ENUM('light','ac','curtain','scene','tv','socket') NOT NULL DEFAULT 'light', `label` VARCHAR(64) NOT NULL,
  `endpoint` VARCHAR(255) COMMENT 'adapter-specific address (relay id, KNX group, HTTP path)', `state` VARCHAR(32) NOT NULL DEFAULT 'off',
  `value` DECIMAL(6,2) NOT NULL DEFAULT 0, `min_value` DECIMAL(6,2) NOT NULL DEFAULT 16, `max_value` DECIMAL(6,2) NOT NULL DEFAULT 30,
  `online` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_control_point`), UNIQUE KEY `room_code` (`id_room`,`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_control_log` (
  `id_pulse_gp_control_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_room` INT UNSIGNED NOT NULL, `code` VARCHAR(32) NOT NULL,
  `action` VARCHAR(32) NOT NULL, `value` VARCHAR(32), `adapter` VARCHAR(64), `result` ENUM('ok','failed','queued') NOT NULL DEFAULT 'ok',
  `message` VARCHAR(255), `source` VARCHAR(16) NOT NULL DEFAULT 'portal', `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_gp_control_log`), KEY `room` (`id_room`,`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_gp_rate` (
  `bucket` VARCHAR(64) NOT NULL, `window_start` INT UNSIGNED NOT NULL COMMENT 'unix minute', `hits` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket`,`window_start`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_gp_page` (`id_pulse_gp_page`,`code`,`category`,`icon`,`phone`,`extension`,`opens`,`location`,`sort`,`active`,`date_add`,`date_upd`) VALUES
(1,'wifi','wifi','wifi','','0','24 hours','Whole property',1,1,NOW(),NOW()),
(2,'emergency','emergency','phone','','0','24 hours','Front desk',2,1,NOW(),NOW()),
(3,'house_rules','rules','book','','0','','',3,1,NOW(),NOW());

INSERT IGNORE INTO `PREFIX_pulse_gp_page_lang` (`id_pulse_gp_page`,`lang`,`title`,`summary`,`body`) VALUES
(1,'en','WiFi','Free high-speed internet in every room','Select the hotel network on your device and enter the password shown on this screen. One password covers the whole property, including the pool deck and the meeting rooms. If a device will not connect, dial 0 for the front desk.'),
(2,'en','Emergency & Safety','Dial 0 from the room phone at any hour','Dial 0 from the room telephone for the front desk at any hour. In a fire, leave by the nearest staircase — never the lift — and gather at the car park assembly point. A first-aid kit and a defibrillator are held at the front desk, and the duty manager is on the property overnight.'),
(3,'en','House Rules','The short version','Check-out is at noon and check-in from 2 p.m. Smoking is not allowed in the rooms or corridors; the terrace is the place for it. Visitors must sign in at reception and leave by 10 p.m. Quiet hours run from 10 p.m. to 7 a.m.');
