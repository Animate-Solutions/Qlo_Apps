CREATE TABLE IF NOT EXISTS `PREFIX_pulse_laundry_item` (
  `id_pulse_laundry_item` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(64) NOT NULL, `category` ENUM('mens','womens','kids','household','uniform') NOT NULL DEFAULT 'mens',
  `price_wash` DECIMAL(20,6) NOT NULL DEFAULT 0, `price_dryclean` DECIMAL(20,6) NOT NULL DEFAULT 0, `price_press` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0, PRIMARY KEY (`id_pulse_laundry_item`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_laundry_order` (
  `id_pulse_laundry_order` INT UNSIGNED NOT NULL AUTO_INCREMENT, `order_no` VARCHAR(16) NOT NULL,
  `type` ENUM('guest','house','uniform','outside') NOT NULL DEFAULT 'guest',
  `id_room` INT UNSIGNED, `id_htl_booking` INT UNSIGNED, `id_customer` INT UNSIGNED, `guest_name` VARCHAR(128), `department` VARCHAR(32),
  `service` ENUM('normal','express','same_day') NOT NULL DEFAULT 'normal', `surcharge_pct` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `status` ENUM('requested','collected','washing','ready','delivered','cancelled') NOT NULL DEFAULT 'requested',
  `id_vendor` INT UNSIGNED DEFAULT NULL, `vendor_ref` VARCHAR(64),
  `pieces` SMALLINT NOT NULL DEFAULT 0, `subtotal` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `posted_line` BIGINT UNSIGNED DEFAULT NULL, `complimentary` TINYINT(1) NOT NULL DEFAULT 0,
  `promised_at` DATETIME, `collected_at` DATETIME, `ready_at` DATETIME, `delivered_at` DATETIME,
  `collected_by` INT UNSIGNED, `delivered_by` INT UNSIGNED, `note` VARCHAR(255), `business_date` DATE NOT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_laundry_order`), UNIQUE KEY `no` (`order_no`), KEY `status` (`status`), KEY `booking` (`id_htl_booking`), KEY `bdate` (`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_laundry_order_line` (
  `id_pulse_laundry_order_line` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_laundry_order` INT UNSIGNED NOT NULL, `id_pulse_laundry_item` INT UNSIGNED,
  `item_name` VARCHAR(64) NOT NULL, `process` ENUM('wash','dryclean','press') NOT NULL DEFAULT 'wash', `qty` SMALLINT NOT NULL DEFAULT 1, `unit_price` DECIMAL(20,6) NOT NULL DEFAULT 0, `line_total` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `condition_note` VARCHAR(128), `damaged` TINYINT(1) NOT NULL DEFAULT 0, `missing` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_pulse_laundry_order_line`), KEY `o` (`id_pulse_laundry_order`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_laundry_claim` (
  `id_pulse_laundry_claim` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_laundry_order` INT UNSIGNED NOT NULL, `id_pulse_laundry_order_line` BIGINT UNSIGNED,
  `type` ENUM('damage','loss','delay','quality') NOT NULL, `description` VARCHAR(255), `amount_claimed` DECIMAL(20,6) NOT NULL DEFAULT 0, `amount_settled` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `status` ENUM('open','approved','rejected','settled') NOT NULL DEFAULT 'open', `settled_how` VARCHAR(64), `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_laundry_claim`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_laundry_vendor` (
  `id_pulse_laundry_vendor` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(128) NOT NULL, `contact` VARCHAR(128), `phone` VARCHAR(32), `rate_per_kg` DECIMAL(20,6), `turnaround_hours` SMALLINT NOT NULL DEFAULT 24, `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_laundry_vendor`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_linen_type` (
  `id_pulse_linen_type` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(64) NOT NULL, `unit_cost` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `par_per_room` DECIMAL(6,2) NOT NULL DEFAULT 3 COMMENT 'par level multiple (in room / in laundry / on shelf)', `expected_washes` SMALLINT NOT NULL DEFAULT 150,
  `qty_clean` INT NOT NULL DEFAULT 0, `qty_in_rooms` INT NOT NULL DEFAULT 0, `qty_soiled` INT NOT NULL DEFAULT 0, `qty_in_wash` INT NOT NULL DEFAULT 0, `qty_discarded` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (`id_pulse_linen_type`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_linen_movement` (
  `id_pulse_linen_movement` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_linen_type` INT UNSIGNED NOT NULL,
  `type` ENUM('issue','return','to_wash','from_wash','discard','purchase','count_adjust') NOT NULL, `qty` INT NOT NULL, `id_room` INT UNSIGNED, `floor` VARCHAR(8), `reason` VARCHAR(128),
  `id_employee` INT UNSIGNED, `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_linen_movement`), KEY `t` (`id_pulse_linen_type`,`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_laundry_batch` (
  `id_pulse_laundry_batch` INT UNSIGNED NOT NULL AUTO_INCREMENT, `batch_no` VARCHAR(16) NOT NULL, `machine` VARCHAR(32), `program` VARCHAR(32), `kg` DECIMAL(8,2),
  `started_at` DATETIME, `finished_at` DATETIME, `chemicals_cost` DECIMAL(20,6) NOT NULL DEFAULT 0, `note` VARCHAR(255), `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_laundry_batch`), UNIQUE KEY `b` (`batch_no`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_laundry_item` (`name`,`category`,`price_wash`,`price_dryclean`,`price_press`,`sort`) VALUES
('Shirt','mens',1500,3000,800,1),('Trousers','mens',1500,3500,800,2),('Suit (2 pc)','mens',0,9000,2500,3),('Native (2 pc)','mens',2500,5000,1500,4),('T-shirt','mens',1000,0,500,5),('Underwear','mens',500,0,0,6),('Socks (pair)','mens',400,0,0,7),
('Blouse','womens',1500,3000,800,10),('Dress','womens',2500,5000,1500,11),('Skirt','womens',1500,3000,800,12),('Abaya / Gown','womens',3000,6000,1500,13),
('Bedsheet','household',2000,0,0,20),('Towel','household',1000,0,0,21),('Duvet','household',5000,8000,0,22);
INSERT IGNORE INTO `PREFIX_pulse_linen_type` (`name`,`unit_cost`,`par_per_room`) VALUES ('Bedsheet (king)',12000,3),('Bedsheet (queen)',10000,3),('Pillow case',2500,6),('Duvet cover',15000,3),('Bath towel',6000,4),('Hand towel',2500,4),('Face towel',1200,4),('Bath mat',4000,2);
