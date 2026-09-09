CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_profile_ext` (
  `id_customer` INT UNSIGNED NOT NULL,
  `source_of_business` VARCHAR(32) DEFAULT NULL, `market_segment` VARCHAR(32) DEFAULT NULL, `guest_type` VARCHAR(32) DEFAULT NULL,
  `preferred_language` VARCHAR(8) DEFAULT NULL, `preferred_channel` ENUM('email','sms','whatsapp','none') NOT NULL DEFAULT 'email',
  `nps_last` TINYINT DEFAULT NULL, `nps_band` ENUM('promoter','passive','detractor','unknown') NOT NULL DEFAULT 'unknown', `nps_date` DATE DEFAULT NULL,
  `gss_avg` DECIMAL(6,3) NOT NULL DEFAULT 0, `reviews_written` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `forgotten` TINYINT(1) NOT NULL DEFAULT 0, `date_forgotten` DATETIME DEFAULT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_customer`), KEY `sob` (`source_of_business`), KEY `band` (`nps_band`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_preference_option` (
  `id_pulse_crm_preference_option` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` ENUM('room_position','floor','pillow','bed','allergy','newspaper','transport','dietary','amenity','housekeeping','other') NOT NULL,
  `code` VARCHAR(32) NOT NULL, `label` VARCHAR(96) NOT NULL, `sort` SMALLINT NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_crm_preference_option`), UNIQUE KEY `cc` (`category`,`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_preference` (
  `id_pulse_crm_preference` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_customer` INT UNSIGNED NOT NULL,
  `category` ENUM('room_position','floor','pillow','bed','allergy','newspaper','transport','dietary','amenity','housekeeping','other') NOT NULL,
  `code` VARCHAR(32) DEFAULT NULL, `value` VARCHAR(255) NOT NULL, `is_managed` TINYINT(1) NOT NULL DEFAULT 0,
  `is_service_note` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'allergies and the like: shown on arrivals and kitchen dockets',
  `source` VARCHAR(32) NOT NULL DEFAULT 'desk', `id_employee` INT UNSIGNED DEFAULT NULL, `active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_preference`), KEY `cust` (`id_customer`,`category`), KEY `note` (`is_service_note`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_occasion` (
  `id_pulse_crm_occasion` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_customer` INT UNSIGNED NOT NULL,
  `type` ENUM('birthday','anniversary','graduation','promotion','religious','other') NOT NULL DEFAULT 'birthday',
  `occasion_date` DATE NOT NULL, `recurring` TINYINT(1) NOT NULL DEFAULT 1, `remind_days` SMALLINT NOT NULL DEFAULT 7,
  `note` VARCHAR(255) DEFAULT NULL, `last_reminded` DATE DEFAULT NULL, `active` TINYINT(1) NOT NULL DEFAULT 1,
  `source` VARCHAR(32) NOT NULL DEFAULT 'desk', `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_occasion`), KEY `cust` (`id_customer`), KEY `d` (`type`,`occasion_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_relationship` (
  `id_pulse_crm_relationship` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_customer` INT UNSIGNED NOT NULL, `id_related_customer` INT UNSIGNED NOT NULL,
  `type` ENUM('travels_with','spouse','partner','child','colleague','assistant_of','reports_to','same_company','other') NOT NULL DEFAULT 'travels_with',
  `note` VARCHAR(255) DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_relationship`), UNIQUE KEY `pair` (`id_customer`,`id_related_customer`,`type`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_consent` (
  `id_pulse_crm_consent` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_customer` INT UNSIGNED NOT NULL,
  `channel` ENUM('email','sms','whatsapp','phone','post','profiling') NOT NULL DEFAULT 'email',
  `state` ENUM('opt_in','opt_out','unknown') NOT NULL DEFAULT 'unknown',
  `source` VARCHAR(48) NOT NULL DEFAULT 'desk' COMMENT 'registration_card, portal, campaign_unsub, import, desk, api',
  `evidence` VARCHAR(255) DEFAULT NULL, `unsub_reason` VARCHAR(255) DEFAULT NULL, `ip` VARCHAR(45) DEFAULT NULL,
  `date_consent` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_consent`), UNIQUE KEY `cc` (`id_customer`,`channel`), KEY `st` (`channel`,`state`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_tag` (
  `id_pulse_crm_tag` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(64) NOT NULL, `colour` VARCHAR(16) NOT NULL DEFAULT 'default',
  PRIMARY KEY (`id_pulse_crm_tag`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_customer_tag` (
  `id_customer` INT UNSIGNED NOT NULL, `id_pulse_crm_tag` INT UNSIGNED NOT NULL, `source` VARCHAR(32) NOT NULL DEFAULT 'desk', `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_customer`,`id_pulse_crm_tag`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_segment` (
  `id_pulse_crm_segment` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(96) NOT NULL, `description` VARCHAR(255) DEFAULT NULL,
  `rules_json` TEXT COMMENT 'JSON {match:all|any, rules:[{field,op,value}]}', `is_system` TINYINT(1) NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  `member_count` INT UNSIGNED NOT NULL DEFAULT 0, `last_refresh` DATETIME DEFAULT NULL, `refresh_ms` INT UNSIGNED NOT NULL DEFAULT 0, `last_error` VARCHAR(255) DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_segment`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_segment_member` (
  `id_pulse_crm_segment` INT UNSIGNED NOT NULL, `id_customer` INT UNSIGNED NOT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_segment`,`id_customer`), KEY `cust` (`id_customer`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_campaign` (
  `id_pulse_crm_campaign` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(128) NOT NULL,
  `channel` ENUM('email','sms','whatsapp') NOT NULL DEFAULT 'email', `id_pulse_crm_segment` INT UNSIGNED DEFAULT NULL,
  `subject` VARCHAR(190) DEFAULT NULL, `body` TEXT, `subject_b` VARCHAR(190) DEFAULT NULL, `body_b` TEXT, `ab_split_pct` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `schedule_type` ENUM('manual','once','daily','weekly','monthly') NOT NULL DEFAULT 'manual', `send_at` DATETIME DEFAULT NULL, `recur_dom` TINYINT UNSIGNED NOT NULL DEFAULT 1, `recur_dow` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `throttle_per_run` SMALLINT UNSIGNED NOT NULL DEFAULT 100, `quiet_from` VARCHAR(5) NOT NULL DEFAULT '21:00', `quiet_to` VARCHAR(5) NOT NULL DEFAULT '08:00',
  `status` ENUM('draft','scheduled','sending','paused','sent','cancelled') NOT NULL DEFAULT 'draft',
  `count_queued` INT UNSIGNED NOT NULL DEFAULT 0, `count_sent` INT UNSIGNED NOT NULL DEFAULT 0, `count_failed` INT UNSIGNED NOT NULL DEFAULT 0,
  `count_opened` INT UNSIGNED NOT NULL DEFAULT 0, `count_clicked` INT UNSIGNED NOT NULL DEFAULT 0, `count_unsub` INT UNSIGNED NOT NULL DEFAULT 0, `count_skipped` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_run_at` DATETIME DEFAULT NULL, `id_employee` INT UNSIGNED DEFAULT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_campaign`), KEY `st` (`status`,`send_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_campaign_recipient` (
  `id_pulse_crm_campaign_recipient` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_campaign` INT UNSIGNED NOT NULL, `id_customer` INT UNSIGNED NOT NULL,
  `variant` ENUM('a','b') NOT NULL DEFAULT 'a', `to_addr` VARCHAR(190) DEFAULT NULL, `token` VARCHAR(40) NOT NULL,
  `status` ENUM('queued','sent','failed','opened','clicked','unsubscribed','skipped') NOT NULL DEFAULT 'queued',
  `skip_reason` VARCHAR(64) DEFAULT NULL, `error` VARCHAR(255) DEFAULT NULL, `open_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `click_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `date_queued` DATETIME NOT NULL, `date_sent` DATETIME DEFAULT NULL, `date_opened` DATETIME DEFAULT NULL, `date_clicked` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_pulse_crm_campaign_recipient`), UNIQUE KEY `tok` (`token`), UNIQUE KEY `cc` (`id_pulse_crm_campaign`,`id_customer`), KEY `st` (`id_pulse_crm_campaign`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_journey` (
  `id_pulse_crm_journey` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(96) NOT NULL, `description` VARCHAR(255) DEFAULT NULL,
  `trigger_event` ENUM('booking_confirmed','check_in','mid_stay','check_out','post_stay','lapsed','birthday','anniversary','manual') NOT NULL DEFAULT 'check_in',
  `active` TINYINT(1) NOT NULL DEFAULT 1, `quiet_from` VARCHAR(5) NOT NULL DEFAULT '21:00', `quiet_to` VARCHAR(5) NOT NULL DEFAULT '08:00',
  `suppress_days` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'no two automated sends to one guest inside this many days',
  `count_started` INT UNSIGNED NOT NULL DEFAULT 0, `count_done` INT UNSIGNED NOT NULL DEFAULT 0, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_journey`), UNIQUE KEY `code` (`code`), KEY `trg` (`trigger_event`,`active`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_journey_step` (
  `id_pulse_crm_journey_step` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_journey` INT UNSIGNED NOT NULL, `sort` SMALLINT NOT NULL DEFAULT 0,
  `name` VARCHAR(96) DEFAULT NULL, `delay_minutes` INT NOT NULL DEFAULT 0, `condition_json` TEXT,
  `action` ENUM('send_template','create_ticket','open_case','add_tag','add_points','notify_manager','stop') NOT NULL DEFAULT 'send_template',
  `channel` ENUM('email','sms','whatsapp','auto') NOT NULL DEFAULT 'auto', `template_code` VARCHAR(48) DEFAULT NULL,
  `subject` VARCHAR(190) DEFAULT NULL, `body` TEXT, `action_json` TEXT, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_crm_journey_step`), KEY `j` (`id_pulse_crm_journey`,`sort`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_journey_run` (
  `id_pulse_crm_journey_run` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_journey` INT UNSIGNED NOT NULL,
  `id_customer` INT UNSIGNED NOT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_room` INT UNSIGNED DEFAULT NULL,
  `step_index` SMALLINT NOT NULL DEFAULT 0, `status` ENUM('active','done','cancelled','failed') NOT NULL DEFAULT 'active',
  `next_run_at` DATETIME NOT NULL, `context_json` TEXT, `last_error` VARCHAR(255) DEFAULT NULL,
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_journey_run`), KEY `due` (`status`,`next_run_at`), KEY `jc` (`id_pulse_crm_journey`,`id_customer`,`id_htl_booking`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_journey_log` (
  `id_pulse_crm_journey_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_journey_run` BIGINT UNSIGNED NOT NULL, `id_pulse_crm_journey_step` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(32) NOT NULL, `result` ENUM('done','skipped','failed') NOT NULL DEFAULT 'done', `message` VARCHAR(255) DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_journey_log`), KEY `r` (`id_pulse_crm_journey_run`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_send_log` (
  `id_pulse_crm_send_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_customer` INT UNSIGNED NOT NULL,
  `channel` ENUM('email','sms','whatsapp') NOT NULL DEFAULT 'email', `kind` ENUM('campaign','journey','survey','occasion','transactional') NOT NULL DEFAULT 'campaign',
  `reference` VARCHAR(64) DEFAULT NULL, `send_date` DATE NOT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_send_log`), KEY `sup` (`id_customer`,`send_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_loyalty_program` (
  `id_pulse_crm_loyalty_program` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(16) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `earn_rate_json` TEXT COMMENT 'JSON points per 1000 naira by folio department', `point_value` DECIMAL(20,6) NOT NULL DEFAULT 1 COMMENT 'naira per point on redemption',
  `min_redeem_points` INT UNSIGNED NOT NULL DEFAULT 1000, `expiry_months` SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  `qualify_window_months` SMALLINT UNSIGNED NOT NULL DEFAULT 12, `enrol_bonus` INT UNSIGNED NOT NULL DEFAULT 0,
  `terms` TEXT, `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_loyalty_program`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_tier` (
  `id_pulse_crm_tier` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_loyalty_program` INT UNSIGNED NOT NULL,
  `code` VARCHAR(16) NOT NULL, `name` VARCHAR(64) NOT NULL, `sort` SMALLINT NOT NULL DEFAULT 0,
  `min_nights` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `min_stays` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `min_spend` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `earn_multiplier` DECIMAL(6,3) NOT NULL DEFAULT 1, `benefits` TEXT, `colour` VARCHAR(16) NOT NULL DEFAULT 'default',
  PRIMARY KEY (`id_pulse_crm_tier`), UNIQUE KEY `pc` (`id_pulse_crm_loyalty_program`,`code`), KEY `s` (`sort`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_member` (
  `id_pulse_crm_member` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_customer` INT UNSIGNED NOT NULL, `id_pulse_crm_loyalty_program` INT UNSIGNED NOT NULL,
  `member_no` VARCHAR(24) NOT NULL, `card_no` VARCHAR(32) DEFAULT NULL, `id_pulse_crm_tier` INT UNSIGNED DEFAULT NULL,
  `points_balance` INT NOT NULL DEFAULT 0, `points_earned_life` INT NOT NULL DEFAULT 0, `points_redeemed_life` INT NOT NULL DEFAULT 0, `points_expired_life` INT NOT NULL DEFAULT 0,
  `qualifying_nights` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `qualifying_stays` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `qualifying_spend` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `tier_since` DATE DEFAULT NULL, `tier_review_date` DATE DEFAULT NULL, `join_date` DATE NOT NULL, `enrol_source` VARCHAR(32) NOT NULL DEFAULT 'desk',
  `status` ENUM('active','suspended','closed') NOT NULL DEFAULT 'active', `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_member`), UNIQUE KEY `no` (`member_no`), UNIQUE KEY `cp` (`id_customer`,`id_pulse_crm_loyalty_program`), KEY `t` (`id_pulse_crm_tier`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_points_txn` (
  `id_pulse_crm_points_txn` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_member` INT UNSIGNED NOT NULL,
  `type` ENUM('earn','bonus','redeem','adjust','expire','transfer_in','transfer_out') NOT NULL DEFAULT 'earn',
  `points` INT NOT NULL DEFAULT 0, `points_remaining` INT NOT NULL DEFAULT 0 COMMENT 'unconsumed balance of an earn row (FIFO)', `balance_after` INT NOT NULL DEFAULT 0,
  `source` VARCHAR(32) NOT NULL DEFAULT 'folio', `reference` VARCHAR(64) DEFAULT NULL, `description` VARCHAR(190) DEFAULT NULL,
  `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_pulse_folio_line` BIGINT UNSIGNED DEFAULT NULL, `department` VARCHAR(24) DEFAULT NULL,
  `amount_basis` DECIMAL(20,6) NOT NULL DEFAULT 0, `expires_on` DATE DEFAULT NULL, `id_employee` INT UNSIGNED DEFAULT NULL,
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_points_txn`), KEY `m` (`id_pulse_crm_member`,`date_add`), KEY `exp` (`type`,`expires_on`,`points_remaining`), KEY `line` (`id_pulse_folio_line`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_survey` (
  `id_pulse_crm_survey` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `touchpoint` ENUM('in_stay','post_stay','fnb','event','spa','generic') NOT NULL DEFAULT 'post_stay',
  `intro` TEXT, `thanks` TEXT, `low_score_threshold` TINYINT UNSIGNED NOT NULL DEFAULT 6 COMMENT 'NPS at or below this opens a recovery case',
  `expiry_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30, `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_survey`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_survey_question` (
  `id_pulse_crm_survey_question` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_survey` INT UNSIGNED NOT NULL, `sort` SMALLINT NOT NULL DEFAULT 0,
  `code` VARCHAR(32) NOT NULL, `type` ENUM('nps','scale5','single','multi','text','bool') NOT NULL DEFAULT 'scale5', `label` VARCHAR(255) NOT NULL,
  `options_json` TEXT, `department` VARCHAR(24) DEFAULT NULL, `required` TINYINT(1) NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_crm_survey_question`), UNIQUE KEY `sc` (`id_pulse_crm_survey`,`code`), KEY `s` (`id_pulse_crm_survey`,`sort`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_survey_response` (
  `id_pulse_crm_survey_response` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_survey` INT UNSIGNED NOT NULL, `token` VARCHAR(40) NOT NULL,
  `id_customer` INT UNSIGNED DEFAULT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_room` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','partial','completed','expired') NOT NULL DEFAULT 'pending',
  `nps` TINYINT DEFAULT NULL, `nps_band` ENUM('promoter','passive','detractor','unknown') NOT NULL DEFAULT 'unknown',
  `gss` DECIMAL(6,3) DEFAULT NULL COMMENT 'guest satisfaction score, mean of 1-5 questions scaled to 100',
  `department_low` VARCHAR(24) DEFAULT NULL, `sentiment` ENUM('positive','neutral','negative','unknown') NOT NULL DEFAULT 'unknown',
  `comment` TEXT, `language` VARCHAR(8) NOT NULL DEFAULT 'en', `channel` VARCHAR(16) NOT NULL DEFAULT 'email',
  `expires_on` DATE DEFAULT NULL, `sent_at` DATETIME DEFAULT NULL, `completed_at` DATETIME DEFAULT NULL, `ip` VARCHAR(45) DEFAULT NULL,
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_survey_response`), UNIQUE KEY `tok` (`token`), KEY `s` (`id_pulse_crm_survey`,`status`), KEY `bd` (`business_date`), KEY `cust` (`id_customer`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_survey_answer` (
  `id_pulse_crm_survey_answer` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_survey_response` BIGINT UNSIGNED NOT NULL, `id_pulse_crm_survey_question` INT UNSIGNED NOT NULL,
  `code` VARCHAR(32) NOT NULL, `department` VARCHAR(24) DEFAULT NULL, `value_num` DECIMAL(10,3) DEFAULT NULL, `value_text` TEXT, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_survey_answer`), KEY `r` (`id_pulse_crm_survey_response`), KEY `dept` (`department`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_case` (
  `id_pulse_crm_case` INT UNSIGNED NOT NULL AUTO_INCREMENT, `case_no` VARCHAR(16) NOT NULL,
  `source` ENUM('survey','ticket','review','staff','guest','audit') NOT NULL DEFAULT 'survey',
  `severity` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `department` ENUM('frontdesk','housekeeping','engineering','fnb','security','management','other') NOT NULL DEFAULT 'frontdesk',
  `id_customer` INT UNSIGNED DEFAULT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL, `id_room` INT UNSIGNED DEFAULT NULL,
  `id_pulse_ticket` INT UNSIGNED DEFAULT NULL, `id_pulse_crm_survey_response` BIGINT UNSIGNED DEFAULT NULL, `id_pulse_crm_review` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(190) NOT NULL, `description` TEXT, `root_cause` VARCHAR(255) DEFAULT NULL,
  `recovery_action` ENUM('none','apology','comp','discount','upgrade','gift','letter','refund','points') NOT NULL DEFAULT 'none',
  `recovery_detail` VARCHAR(255) DEFAULT NULL, `recovery_cost` DECIMAL(20,6) NOT NULL DEFAULT 0, `recovery_posted_line` BIGINT UNSIGNED DEFAULT NULL,
  `owner` INT UNSIGNED DEFAULT NULL, `sla_due` DATETIME DEFAULT NULL,
  `status` ENUM('open','investigating','recovering','closed','escalated') NOT NULL DEFAULT 'open', `closing_note` TEXT,
  `opened_at` DATETIME NOT NULL, `closed_at` DATETIME DEFAULT NULL, `business_date` DATE NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_case`), UNIQUE KEY `no` (`case_no`), KEY `st` (`status`,`department`), KEY `bd` (`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_review` (
  `id_pulse_crm_review` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source` ENUM('tripadvisor','google','booking','expedia','agoda','hotels_ng','facebook','direct','other') NOT NULL DEFAULT 'google',
  `external_id` VARCHAR(96) DEFAULT NULL, `url` VARCHAR(255) DEFAULT NULL,
  `rating` DECIMAL(6,3) NOT NULL DEFAULT 0, `rating_scale` TINYINT UNSIGNED NOT NULL DEFAULT 5, `rating_pct` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `title` VARCHAR(190) DEFAULT NULL, `body` TEXT, `language` VARCHAR(8) NOT NULL DEFAULT 'en', `author` VARCHAR(128) DEFAULT NULL,
  `review_date` DATE NOT NULL, `trip_type` VARCHAR(32) DEFAULT NULL, `department` VARCHAR(24) DEFAULT NULL,
  `sentiment` ENUM('positive','neutral','negative','unknown') NOT NULL DEFAULT 'unknown',
  `responded` TINYINT(1) NOT NULL DEFAULT 0, `response_text` TEXT, `responded_at` DATETIME DEFAULT NULL, `responded_by` INT UNSIGNED DEFAULT NULL,
  `id_customer` INT UNSIGNED DEFAULT NULL, `id_htl_booking` INT UNSIGNED DEFAULT NULL, `imported_at` DATETIME DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_review`), UNIQUE KEY `ext` (`source`,`external_id`), KEY `d` (`review_date`), KEY `resp` (`responded`,`sentiment`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_account` (
  `id_pulse_crm_account` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_company` INT UNSIGNED DEFAULT NULL, `name` VARCHAR(128) NOT NULL,
  `industry` VARCHAR(64) DEFAULT NULL, `segment` ENUM('corporate','government','ngo','travel_agent','airline','embassy','other') NOT NULL DEFAULT 'corporate',
  `account_manager` INT UNSIGNED DEFAULT NULL, `status` ENUM('prospect','active','dormant','lost') NOT NULL DEFAULT 'prospect',
  `potential_nights` INT UNSIGNED NOT NULL DEFAULT 0, `potential_value` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `next_review` DATE DEFAULT NULL, `notes` TEXT, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_account`), KEY `co` (`id_pulse_company`), KEY `st` (`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_account_rate` (
  `id_pulse_crm_account_rate` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_account` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED DEFAULT NULL, `room_type_name` VARCHAR(128) DEFAULT NULL, `rate_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `includes_breakfast` TINYINT(1) NOT NULL DEFAULT 0, `valid_from` DATE NOT NULL, `valid_to` DATE NOT NULL, `note` VARCHAR(190) DEFAULT NULL,
  PRIMARY KEY (`id_pulse_crm_account_rate`), KEY `a` (`id_pulse_crm_account`,`valid_from`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_contact` (
  `id_pulse_crm_contact` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_account` INT UNSIGNED NOT NULL, `id_customer` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(128) NOT NULL, `title` VARCHAR(96) DEFAULT NULL, `email` VARCHAR(190) DEFAULT NULL, `phone` VARCHAR(32) DEFAULT NULL,
  `decision_role` ENUM('decision_maker','influencer','booker','finance','other') NOT NULL DEFAULT 'booker', `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `notes` VARCHAR(255) DEFAULT NULL, `active` TINYINT(1) NOT NULL DEFAULT 1, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_contact`), KEY `a` (`id_pulse_crm_account`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_activity` (
  `id_pulse_crm_activity` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_account` INT UNSIGNED NOT NULL, `id_pulse_crm_contact` INT UNSIGNED DEFAULT NULL,
  `type` ENUM('call','visit','email','meeting','proposal','entertainment','note') NOT NULL DEFAULT 'call',
  `subject` VARCHAR(190) NOT NULL, `notes` TEXT, `outcome` VARCHAR(190) DEFAULT NULL,
  `activity_date` DATETIME NOT NULL, `follow_up_at` DATETIME DEFAULT NULL, `follow_up_done` TINYINT(1) NOT NULL DEFAULT 0,
  `id_employee` INT UNSIGNED DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_activity`), KEY `a` (`id_pulse_crm_account`,`activity_date`), KEY `fu` (`follow_up_done`,`follow_up_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_crm_opportunity` (
  `id_pulse_crm_opportunity` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_crm_account` INT UNSIGNED NOT NULL, `name` VARCHAR(190) NOT NULL,
  `stage` ENUM('lead','qualified','proposal','negotiation','won','lost') NOT NULL DEFAULT 'lead',
  `expected_nights` INT UNSIGNED NOT NULL DEFAULT 0, `expected_value` DECIMAL(20,6) NOT NULL DEFAULT 0, `probability` TINYINT UNSIGNED NOT NULL DEFAULT 20,
  `close_date` DATE DEFAULT NULL, `owner` INT UNSIGNED DEFAULT NULL, `lost_reason` VARCHAR(190) DEFAULT NULL, `notes` TEXT,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_crm_opportunity`), KEY `a` (`id_pulse_crm_account`,`stage`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_charge_code` (`code`,`name`,`department`,`default_price`,`tax_rate`,`is_payment`) VALUES ('LOYR','Loyalty Redemption','adjustment',0,0,0);

INSERT IGNORE INTO `PREFIX_pulse_crm_preference_option` (`category`,`code`,`label`,`sort`) VALUES
('room_position','high_floor','High floor',1),('room_position','low_floor','Low floor',2),('room_position','away_lift','Away from the lift',3),('room_position','near_lift','Near the lift',4),('room_position','quiet','Quiet side, away from the generator',5),('room_position','pool_view','Pool view',6),('room_position','corner','Corner room',7),
('floor','ground','Ground floor',10),('floor','first','First floor',11),('floor','second','Second floor',12),('floor','third','Third floor',13),('floor','fourth','Fourth floor',14),
('pillow','soft','Soft pillow',20),('pillow','firm','Firm pillow',21),('pillow','extra','Extra pillows',22),('pillow','foam','Foam, no feather',23),
('bed','king','King bed',30),('bed','twin','Twin beds',31),('bed','extra_blanket','Extra blanket',32),('bed','hard_mattress','Firm mattress',33),
('allergy','nuts','Nut allergy',40),('allergy','seafood','Seafood allergy',41),('allergy','dust','Dust sensitive — no carpet freshener',42),('allergy','feather','Feather allergy',43),('allergy','penicillin','Penicillin allergy (medical file)',44),
('newspaper','punch','The Punch',50),('newspaper','guardian','The Guardian',51),('newspaper','thisday','ThisDay',52),('newspaper','vanguard','Vanguard',53),('newspaper','none','No newspaper',54),
('transport','airport_pickup','Airport pick-up (Port Harcourt Intl)',60),('transport','airport_dropoff','Airport drop-off',61),('transport','car_hire','Car with driver',62),('transport','own_car','Comes with own car — reserve parking',63),
('dietary','halal','Halal',70),('dietary','vegetarian','Vegetarian',71),('dietary','vegan','Vegan',72),('dietary','no_pepper','No pepper',73),('dietary','diabetic','Diabetic — sugar free',74),('dietary','gluten_free','Gluten free',75),
('amenity','extra_water','Extra bottled water',80),('amenity','kettle','Kettle in room',81),('amenity','iron','Iron and board',82),('amenity','fridge_empty','Empty the minibar fridge',83),('amenity','fan','Standing fan',84),
('housekeeping','turndown','Turndown service',90),('housekeeping','no_turndown','No turndown',91),('housekeeping','morning_clean','Clean before 10:00',92),('housekeeping','evening_clean','Clean after 16:00',93),('housekeeping','no_entry','Do not enter unless requested',94);

INSERT IGNORE INTO `PREFIX_pulse_crm_tag` (`code`,`name`,`colour`) VALUES
('vip','VIP','danger'),('repeat','Repeat guest','success'),('corporate','Corporate traveller','info'),('detractor','Detractor','danger'),('promoter','Promoter','success'),
('winback','Win-back target','warning'),('birthday','Birthday this month','info'),('long_stay','Long stay','default'),('do_not_upsell','Do not upsell','default'),('recovered','Service recovered','success');

INSERT IGNORE INTO `PREFIX_pulse_crm_segment` (`code`,`name`,`description`,`rules_json`,`is_system`,`date_add`,`date_upd`) VALUES
('first_timers','First-timers','Guests with exactly one completed stay','{"match":"all","rules":[{"field":"stays","op":"eq","value":1},{"field":"blacklisted","op":"eq","value":0}]}',1,NOW(),NOW()),
('repeat','Repeat guests','Three or more stays','{"match":"all","rules":[{"field":"stays","op":"gte","value":3},{"field":"blacklisted","op":"eq","value":0}]}',1,NOW(),NOW()),
('lapsed_12m','Lapsed 12 months','Stayed before but nothing in the last 365 days','{"match":"all","rules":[{"field":"stays","op":"gte","value":1},{"field":"last_stay_days","op":"gte","value":365},{"field":"blacklisted","op":"eq","value":0}]}',1,NOW(),NOW()),
('corporate','Corporate travellers','Attached to a company account','{"match":"all","rules":[{"field":"has_company","op":"eq","value":1},{"field":"blacklisted","op":"eq","value":0}]}',1,NOW(),NOW()),
('high_spenders','High spenders','Lifetime revenue above 1,500,000 naira','{"match":"all","rules":[{"field":"lifetime_revenue","op":"gte","value":1500000},{"field":"blacklisted","op":"eq","value":0}]}',1,NOW(),NOW()),
('detractors','Detractors needing follow-up','Last NPS 6 or below','{"match":"all","rules":[{"field":"nps_band","op":"eq","value":"detractor"}]}',1,NOW(),NOW()),
('birthdays_30d','Upcoming birthdays','Birthday inside the next 30 days','{"match":"all","rules":[{"field":"birthday_in_days","op":"lte","value":30},{"field":"opt_in_email","op":"eq","value":1}]}',1,NOW(),NOW()),
('loyalty_members','Loyalty members','Enrolled and active in the loyalty programme','{"match":"all","rules":[{"field":"member","op":"eq","value":1}]}',1,NOW(),NOW());

INSERT IGNORE INTO `PREFIX_pulse_crm_survey` (`code`,`name`,`touchpoint`,`intro`,`thanks`,`low_score_threshold`,`expiry_days`,`date_add`,`date_upd`) VALUES
('in_stay','In-stay check','in_stay','You are on night two with us. Thirty seconds, and we can still fix anything that is not right.','Thank you. If you flagged something, a duty manager will call your room shortly.',6,7,NOW(),NOW()),
('post_stay','Post-stay satisfaction','post_stay','Thank you for staying with us. Your answers go straight to the general manager.','Thank you — this is read every morning at our stand-up.',6,30,NOW(),NOW()),
('fnb','Restaurant and bar','fnb','How was your meal with us today?','Thank you. Chef reads every one of these.',6,14,NOW(),NOW());

INSERT IGNORE INTO `PREFIX_pulse_crm_survey_question` (`id_pulse_crm_survey`,`sort`,`code`,`type`,`label`,`options_json`,`department`,`required`) VALUES
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='in_stay'),1,'nps','nps','How likely are you to recommend us to a friend or colleague?','','management',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='in_stay'),2,'room','scale5','Your room — comfort and cleanliness','','housekeeping',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='in_stay'),3,'power','scale5','Power and water supply','','engineering',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='in_stay'),4,'fix','text','Is there anything we should fix right now?','','frontdesk',0),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='post_stay'),1,'nps','nps','How likely are you to recommend us to a friend or colleague?','','management',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='post_stay'),2,'checkin','scale5','Check-in and the front desk','','frontdesk',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='post_stay'),3,'room','scale5','Room comfort and cleanliness','','housekeeping',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='post_stay'),4,'food','scale5','Food and drink','','fnb',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='post_stay'),5,'power','scale5','Power, water and internet','','engineering',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='post_stay'),6,'value','scale5','Value for money','','management',0),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='post_stay'),7,'purpose','single','What brought you to Port Harcourt?','["Business","Leisure","Conference","Family","Transit"]','management',0),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='post_stay'),8,'comment','text','Anything else you would like the general manager to know?','','management',0),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='fnb'),1,'food','scale5','The food','','fnb',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='fnb'),2,'service','scale5','Speed and friendliness of service','','fnb',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='fnb'),3,'nps','nps','Would you recommend our restaurant?','','fnb',1),
((SELECT id_pulse_crm_survey FROM `PREFIX_pulse_crm_survey` WHERE code='fnb'),4,'comment','text','Tell us more','','fnb',0);
