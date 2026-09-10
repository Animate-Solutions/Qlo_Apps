CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_account` (
  `id_pulse_acc_account` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(16) NOT NULL, `name` VARCHAR(128) NOT NULL,
  `type` ENUM('asset','liability','equity','revenue','expense') NOT NULL,
  `subtype` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'cash|receivable|inventory|fixed_asset|accum_depreciation|payable|tax|deposit|capital|room_revenue|fnb_revenue|cost_of_sales|payroll|utilities|depreciation…',
  `id_parent` INT UNSIGNED DEFAULT NULL, `parent_code` VARCHAR(16) DEFAULT NULL, `depth` TINYINT NOT NULL DEFAULT 0,
  `usali_dept` ENUM('rooms','fnb','other_operated','undistributed','fixed_charges','non_operating','balance_sheet') NOT NULL DEFAULT 'balance_sheet',
  `normal_balance` ENUM('debit','credit') NOT NULL DEFAULT 'debit',
  `is_header` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'header/roll-up account: nothing may post to it',
  `is_control` TINYINT(1) NOT NULL DEFAULT 0, `control_of` VARCHAR(24) DEFAULT NULL COMMENT 'guest_ledger|city_ledger|ap|grn_accrual|vat_output|vat_input|wht_payable|wht_receivable|bank|inventory|fixed_asset|deposit',
  `cashflow` ENUM('none','cash','operating','investing','financing') NOT NULL DEFAULT 'operating',
  `is_contra` TINYINT(1) NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0, `note` VARCHAR(255),
  `date_add` DATETIME NOT NULL DEFAULT '2024-01-01 00:00:00', `date_upd` DATETIME NOT NULL DEFAULT '2024-01-01 00:00:00',
  PRIMARY KEY (`id_pulse_acc_account`), UNIQUE KEY `code` (`code`), KEY `type` (`type`,`code`), KEY `parent` (`id_parent`), KEY `dept` (`usali_dept`), KEY `ctrl` (`control_of`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_period` (
  `id_pulse_acc_period` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` CHAR(7) NOT NULL COMMENT 'YYYY-MM', `year` SMALLINT NOT NULL, `month` TINYINT NOT NULL,
  `date_from` DATE NOT NULL, `date_to` DATE NOT NULL, `status` ENUM('open','closed','locked') NOT NULL DEFAULT 'open',
  `is_year_end` TINYINT(1) NOT NULL DEFAULT 0, `closing_journal` INT UNSIGNED DEFAULT NULL, `closed_by` INT UNSIGNED, `date_closed` DATETIME DEFAULT NULL, `note` VARCHAR(255), `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_period`), UNIQUE KEY `code` (`code`), KEY `st` (`status`,`date_from`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_journal` (
  `id_pulse_acc_journal` INT UNSIGNED NOT NULL AUTO_INCREMENT, `journal_no` VARCHAR(24) NOT NULL,
  `type` ENUM('general','sales','receipt','purchase','payment','depreciation','closing','opening','fx','reversal','adjustment') NOT NULL DEFAULT 'general',
  `source` ENUM('folio','pos','expense','grn','bill','ap_payment','ar_receipt','inventory','payroll','depreciation','asset','manual','fx','opening','closing','bank','tax') NOT NULL DEFAULT 'manual',
  `source_ref` VARCHAR(96) DEFAULT NULL COMMENT 'NULL for manual entries; unique with source so nothing posts twice',
  `business_date` DATE NOT NULL, `period` CHAR(7) NOT NULL, `reference` VARCHAR(64), `memo` VARCHAR(255),
  `status` ENUM('draft','posted','reversed','void') NOT NULL DEFAULT 'posted',
  `total_debit` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_credit` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `reverses` INT UNSIGNED DEFAULT NULL, `reversed_by` INT UNSIGNED DEFAULT NULL, `reverse_reason` VARCHAR(255),
  `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL, `date_posted` DATETIME DEFAULT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_journal`), UNIQUE KEY `no` (`journal_no`), UNIQUE KEY `src` (`source`,`source_ref`),
  KEY `bd` (`business_date`,`status`), KEY `per` (`period`,`status`), KEY `st` (`status`,`id_pulse_acc_journal`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_journal_line` (
  `id_pulse_acc_journal_line` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_acc_journal` INT UNSIGNED NOT NULL, `line_no` SMALLINT NOT NULL DEFAULT 1,
  `id_pulse_acc_account` INT UNSIGNED NOT NULL, `account_code` VARCHAR(16) NOT NULL, `account_name` VARCHAR(128) NOT NULL,
  `debit` DECIMAL(20,6) NOT NULL DEFAULT 0, `credit` DECIMAL(20,6) NOT NULL DEFAULT 0, `memo` VARCHAR(255),
  `department` VARCHAR(32) DEFAULT NULL, `cost_centre` VARCHAR(32) DEFAULT NULL, `usali_dept` VARCHAR(20) NOT NULL DEFAULT 'balance_sheet',
  `entity` VARCHAR(32) DEFAULT NULL COMMENT 'source document table for drill-down', `id_entity` BIGINT UNSIGNED DEFAULT NULL,
  `id_pulse_company` INT UNSIGNED DEFAULT NULL, `id_supplier` INT UNSIGNED DEFAULT NULL, `tax_code` VARCHAR(16) DEFAULT NULL,
  `business_date` DATE NOT NULL, `period` CHAR(7) NOT NULL, `posted` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'denormalised journal status so report sums never join',
  PRIMARY KEY (`id_pulse_acc_journal_line`), KEY `j` (`id_pulse_acc_journal`),
  KEY `acct_date` (`id_pulse_acc_account`,`business_date`,`posted`), KEY `per_acct` (`period`,`id_pulse_acc_account`,`posted`),
  KEY `dept` (`usali_dept`,`period`,`posted`), KEY `ent` (`entity`,`id_entity`), KEY `co` (`id_pulse_company`,`posted`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_map` (
  `id_pulse_acc_map` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `map_type` ENUM('charge_code','expense_category','payment_method','department','payroll_department','inv_category','pos_major_group','folio_type','wht_category','asset_class') NOT NULL,
  `key_value` VARCHAR(64) NOT NULL, `label` VARCHAR(128), `account_code` VARCHAR(16) DEFAULT NULL, `tax_account_code` VARCHAR(16) DEFAULT NULL,
  `contra_account_code` VARCHAR(16) DEFAULT NULL COMMENT 'inventory account for stock, accumulated depreciation for asset classes',
  `cost_centre` VARCHAR(32) DEFAULT NULL, `wht_rate_pct` DECIMAL(6,3) NOT NULL DEFAULT 0, `note` VARCHAR(255), `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pulse_acc_map`), UNIQUE KEY `k` (`map_type`,`key_value`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_queue` (
  `id_pulse_acc_queue` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `source` VARCHAR(24) NOT NULL, `source_ref` VARCHAR(96) NOT NULL,
  `business_date` DATE NOT NULL, `payload` TEXT, `status` ENUM('pending','posted','skipped','failed') NOT NULL DEFAULT 'pending',
  `attempts` TINYINT NOT NULL DEFAULT 0, `last_error` VARCHAR(255), `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_queue`), UNIQUE KEY `src` (`source`,`source_ref`), KEY `st` (`status`,`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_invoice` (
  `id_pulse_acc_invoice` INT UNSIGNED NOT NULL AUTO_INCREMENT, `invoice_no` VARCHAR(24) NOT NULL, `type` ENUM('invoice','credit_note') NOT NULL DEFAULT 'invoice',
  `id_pulse_company` INT UNSIGNED DEFAULT NULL, `company_name` VARCHAR(128) NOT NULL, `tin` VARCHAR(32), `email` VARCHAR(128), `address` VARCHAR(255),
  `id_pulse_folio` INT UNSIGNED DEFAULT NULL, `id_credited_invoice` INT UNSIGNED DEFAULT NULL,
  `invoice_date` DATE NOT NULL, `due_date` DATE NOT NULL, `terms_days` SMALLINT NOT NULL DEFAULT 30, `currency` CHAR(3) NOT NULL DEFAULT 'NGN',
  `subtotal` DECIMAL(20,6) NOT NULL DEFAULT 0, `tax_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `total` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `allocated` DECIMAL(20,6) NOT NULL DEFAULT 0, `wht_deducted` DECIMAL(20,6) NOT NULL DEFAULT 0, `balance` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `status` ENUM('draft','issued','part_paid','paid','credited','written_off','cancelled') NOT NULL DEFAULT 'issued',
  `period` CHAR(7) NOT NULL, `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL, `note` VARCHAR(255), `id_employee` INT UNSIGNED,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_invoice`), UNIQUE KEY `no` (`invoice_no`), KEY `co` (`id_pulse_company`,`status`), KEY `due` (`status`,`due_date`), KEY `f` (`id_pulse_folio`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_invoice_line` (
  `id_pulse_acc_invoice_line` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_acc_invoice` INT UNSIGNED NOT NULL, `description` VARCHAR(255) NOT NULL,
  `department` VARCHAR(32), `account_code` VARCHAR(16), `qty` DECIMAL(10,3) NOT NULL DEFAULT 1, `unit_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `tax_rate` DECIMAL(6,3) NOT NULL DEFAULT 0, `tax_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `line_total` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `id_pulse_folio_line` BIGINT UNSIGNED DEFAULT NULL, `business_date` DATE,
  PRIMARY KEY (`id_pulse_acc_invoice_line`), KEY `inv` (`id_pulse_acc_invoice`), KEY `fl` (`id_pulse_folio_line`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_receipt` (
  `id_pulse_acc_receipt` INT UNSIGNED NOT NULL AUTO_INCREMENT, `receipt_no` VARCHAR(24) NOT NULL, `id_pulse_company` INT UNSIGNED DEFAULT NULL, `company_name` VARCHAR(128) NOT NULL,
  `receipt_date` DATE NOT NULL, `method` ENUM('cash','transfer','card','cheque','pos','online','contra') NOT NULL DEFAULT 'transfer',
  `id_pulse_acc_bank_account` INT UNSIGNED DEFAULT NULL, `amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `wht_amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `wht_cert_no` VARCHAR(32), `allocated` DECIMAL(20,6) NOT NULL DEFAULT 0, `unallocated` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `reference` VARCHAR(64), `note` VARCHAR(255), `status` ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'posted',
  `period` CHAR(7) NOT NULL, `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL, `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_receipt`), UNIQUE KEY `no` (`receipt_no`), KEY `co` (`id_pulse_company`,`receipt_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_allocation` (
  `id_pulse_acc_allocation` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `kind` ENUM('ar','ap') NOT NULL DEFAULT 'ar',
  `id_source` INT UNSIGNED NOT NULL COMMENT 'AR: receipt or credit note; AP: payment', `source_type` ENUM('receipt','credit_note','payment') NOT NULL DEFAULT 'receipt',
  `id_target` INT UNSIGNED NOT NULL COMMENT 'AR: invoice; AP: bill', `amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_allocation`), KEY `src` (`kind`,`source_type`,`id_source`), KEY `tgt` (`kind`,`id_target`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_dunning` (
  `id_pulse_acc_dunning` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_company` INT UNSIGNED NOT NULL, `company_name` VARCHAR(128) NOT NULL,
  `level` TINYINT NOT NULL DEFAULT 1 COMMENT '1 reminder, 2 second notice, 3 final demand', `as_of` DATE NOT NULL,
  `balance` DECIMAL(20,6) NOT NULL DEFAULT 0, `overdue` DECIMAL(20,6) NOT NULL DEFAULT 0, `invoices` VARCHAR(255), `body` MEDIUMTEXT,
  `status` ENUM('draft','sent','settled','cancelled') NOT NULL DEFAULT 'draft', `sent_at` DATETIME DEFAULT NULL, `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_dunning`), KEY `co` (`id_pulse_company`,`as_of`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_bill` (
  `id_pulse_acc_bill` INT UNSIGNED NOT NULL AUTO_INCREMENT, `bill_no` VARCHAR(24) NOT NULL, `supplier_invoice_no` VARCHAR(64),
  `id_pulse_inv_supplier` INT UNSIGNED DEFAULT NULL, `supplier_name` VARCHAR(128) NOT NULL, `tin` VARCHAR(32),
  `id_pulse_inv_grn` INT UNSIGNED DEFAULT NULL, `grn_no` VARCHAR(16), `id_pulse_expense` INT UNSIGNED DEFAULT NULL,
  `bill_date` DATE NOT NULL, `due_date` DATE NOT NULL, `terms_days` SMALLINT NOT NULL DEFAULT 30,
  `subtotal` DECIMAL(20,6) NOT NULL DEFAULT 0, `vat_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `total` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `wht_rate_pct` DECIMAL(6,3) NOT NULL DEFAULT 0, `wht_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `paid` DECIMAL(20,6) NOT NULL DEFAULT 0, `balance` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `status` ENUM('draft','approved','part_paid','paid','disputed','cancelled') NOT NULL DEFAULT 'approved',
  `period` CHAR(7) NOT NULL, `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL, `note` VARCHAR(255), `id_employee` INT UNSIGNED,
  `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_bill`), UNIQUE KEY `no` (`bill_no`), KEY `sup` (`id_pulse_inv_supplier`,`status`), KEY `due` (`status`,`due_date`), KEY `grn` (`id_pulse_inv_grn`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_bill_line` (
  `id_pulse_acc_bill_line` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_acc_bill` INT UNSIGNED NOT NULL, `description` VARCHAR(255) NOT NULL,
  `account_code` VARCHAR(16), `department` VARCHAR(32), `qty` DECIMAL(14,3) NOT NULL DEFAULT 1, `unit_price` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `tax_rate` DECIMAL(6,3) NOT NULL DEFAULT 0, `tax_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `line_total` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `id_pulse_inv_item` INT UNSIGNED DEFAULT NULL, `is_accrual_clear` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = clears the GRN accrual rather than hitting an expense',
  PRIMARY KEY (`id_pulse_acc_bill_line`), KEY `b` (`id_pulse_acc_bill`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_payment` (
  `id_pulse_acc_payment` INT UNSIGNED NOT NULL AUTO_INCREMENT, `payment_no` VARCHAR(24) NOT NULL, `run_no` VARCHAR(24) DEFAULT NULL,
  `id_pulse_inv_supplier` INT UNSIGNED DEFAULT NULL, `supplier_name` VARCHAR(128) NOT NULL,
  `payment_date` DATE NOT NULL, `method` ENUM('cash','transfer','cheque','card','petty_cash') NOT NULL DEFAULT 'transfer',
  `id_pulse_acc_bank_account` INT UNSIGNED DEFAULT NULL, `gross_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `wht_amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `amount` DECIMAL(20,6) NOT NULL DEFAULT 0 COMMENT 'net paid to the supplier', `reference` VARCHAR(64), `note` VARCHAR(255),
  `status` ENUM('draft','approved','paid','cancelled') NOT NULL DEFAULT 'paid', `period` CHAR(7) NOT NULL,
  `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL, `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_payment`), UNIQUE KEY `no` (`payment_no`), KEY `sup` (`id_pulse_inv_supplier`,`payment_date`), KEY `run` (`run_no`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_vat` (
  `id_pulse_acc_vat` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `direction` ENUM('output','input') NOT NULL,
  `source` VARCHAR(24) NOT NULL, `source_ref` VARCHAR(96) NOT NULL, `doc_no` VARCHAR(64), `party_name` VARCHAR(128), `tin` VARCHAR(32),
  `department` VARCHAR(32), `net_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `vat_rate` DECIMAL(6,3) NOT NULL DEFAULT 7.5,
  `vat_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `consumption_tax` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `business_date` DATE NOT NULL, `period` CHAR(7) NOT NULL, `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL,
  `returned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'included in a filed return', `return_ref` VARCHAR(32), `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_vat`), UNIQUE KEY `src` (`direction`,`source`,`source_ref`), KEY `per` (`period`,`direction`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_wht` (
  `id_pulse_acc_wht` INT UNSIGNED NOT NULL AUTO_INCREMENT, `cert_no` VARCHAR(32) NOT NULL,
  `direction` ENUM('deducted','suffered') NOT NULL DEFAULT 'deducted' COMMENT 'deducted = we withheld from a supplier; suffered = a customer withheld from us',
  `party_type` ENUM('supplier','company','employee','other') NOT NULL DEFAULT 'supplier', `party_name` VARCHAR(128) NOT NULL, `tin` VARCHAR(32), `id_party` INT UNSIGNED DEFAULT NULL,
  `wht_type` ENUM('services','contracts','rent','dividends','royalties','commission','other') NOT NULL DEFAULT 'services',
  `base_amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `rate_pct` DECIMAL(6,3) NOT NULL DEFAULT 5, `amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `source` VARCHAR(24) NOT NULL DEFAULT 'manual', `source_ref` VARCHAR(96), `doc_no` VARCHAR(64),
  `business_date` DATE NOT NULL, `period` CHAR(7) NOT NULL, `remitted` TINYINT(1) NOT NULL DEFAULT 0, `remit_ref` VARCHAR(64), `remit_date` DATE DEFAULT NULL,
  `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL, `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_wht`), UNIQUE KEY `cert` (`cert_no`), KEY `per` (`period`,`direction`), KEY `src` (`source`,`source_ref`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_einvoice` (
  `id_pulse_acc_einvoice` INT UNSIGNED NOT NULL AUTO_INCREMENT, `doc_type` ENUM('invoice','credit_note') NOT NULL DEFAULT 'invoice',
  `id_pulse_acc_invoice` INT UNSIGNED NOT NULL, `invoice_no` VARCHAR(24) NOT NULL, `irn` VARCHAR(96) DEFAULT NULL COMMENT 'Invoice Reference Number sent to FIRS/NRS',
  `payload` MEDIUMTEXT, `status` ENUM('queued','sending','accepted','rejected','failed','cancelled') NOT NULL DEFAULT 'queued',
  `attempts` TINYINT NOT NULL DEFAULT 0, `http_code` SMALLINT DEFAULT NULL, `last_error` VARCHAR(255), `response` MEDIUMTEXT,
  `qr_data` VARCHAR(512), `csid` VARCHAR(255) COMMENT 'cryptographic stamp / signature returned by the service',
  `submitted_at` DATETIME DEFAULT NULL, `accepted_at` DATETIME DEFAULT NULL, `next_retry_at` DATETIME DEFAULT NULL,
  `business_date` DATE NOT NULL, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_einvoice`), UNIQUE KEY `doc` (`doc_type`,`id_pulse_acc_invoice`), KEY `st` (`status`,`next_retry_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_bank_account` (
  `id_pulse_acc_bank_account` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(16) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `type` ENUM('bank','cash','petty_cash','mobile_money','card_settlement') NOT NULL DEFAULT 'bank',
  `bank_name` VARCHAR(96), `account_no` VARCHAR(32), `account_name` VARCHAR(128), `branch` VARCHAR(96), `currency` CHAR(3) NOT NULL DEFAULT 'NGN',
  `account_code` VARCHAR(16) NOT NULL COMMENT 'GL account in pulse_acc_account', `opening_balance` DECIMAL(20,6) NOT NULL DEFAULT 0, `opening_date` DATE DEFAULT NULL,
  `imprest_float` DECIMAL(20,6) NOT NULL DEFAULT 0, `active` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0, `note` VARCHAR(255),
  PRIMARY KEY (`id_pulse_acc_bank_account`), UNIQUE KEY `code` (`code`), KEY `acct` (`account_code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_bank_statement` (
  `id_pulse_acc_bank_statement` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_acc_bank_account` INT UNSIGNED NOT NULL, `filename` VARCHAR(255),
  `period_from` DATE DEFAULT NULL, `period_to` DATE DEFAULT NULL, `opening_balance` DECIMAL(20,6) DEFAULT NULL, `closing_balance` DECIMAL(20,6) DEFAULT NULL,
  `rows_total` INT NOT NULL DEFAULT 0, `rows_matched` INT NOT NULL DEFAULT 0, `rows_unmatched` INT NOT NULL DEFAULT 0,
  `total_in` DECIMAL(20,6) NOT NULL DEFAULT 0, `total_out` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `status` ENUM('imported','reconciled','closed') NOT NULL DEFAULT 'imported', `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_bank_statement`), KEY `ba` (`id_pulse_acc_bank_account`,`status`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_bank_line` (
  `id_pulse_acc_bank_line` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_acc_bank_statement` INT UNSIGNED NOT NULL, `id_pulse_acc_bank_account` INT UNSIGNED NOT NULL,
  `txn_date` DATE NOT NULL, `value_date` DATE DEFAULT NULL, `description` VARCHAR(255), `reference` VARCHAR(96),
  `money_in` DECIMAL(20,6) NOT NULL DEFAULT 0, `money_out` DECIMAL(20,6) NOT NULL DEFAULT 0, `balance` DECIMAL(20,6) DEFAULT NULL,
  `match_state` ENUM('unmatched','auto','manual','ignored','created') NOT NULL DEFAULT 'unmatched',
  `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL, `id_pulse_acc_journal_line` BIGINT UNSIGNED DEFAULT NULL, `match_note` VARCHAR(255), `raw` VARCHAR(1000),
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_bank_line`), KEY `st` (`id_pulse_acc_bank_statement`,`match_state`), KEY `ba` (`id_pulse_acc_bank_account`,`txn_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_petty_cash` (
  `id_pulse_acc_petty_cash` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_acc_bank_account` INT UNSIGNED NOT NULL,
  `type` ENUM('float_in','expense','reimburse','return','variance','adjust') NOT NULL DEFAULT 'expense',
  `business_date` DATE NOT NULL, `description` VARCHAR(255) NOT NULL, `amount` DECIMAL(20,6) NOT NULL DEFAULT 0 COMMENT 'signed: + into the box, - out',
  `balance_after` DECIMAL(20,6) NOT NULL DEFAULT 0, `reference` VARCHAR(64), `id_pulse_cashier_session` INT UNSIGNED DEFAULT NULL, `id_pulse_expense` INT UNSIGNED DEFAULT NULL,
  `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL, `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_petty_cash`), KEY `ba` (`id_pulse_acc_bank_account`,`business_date`), KEY `sess` (`id_pulse_cashier_session`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_asset_class` (
  `id_pulse_acc_asset_class` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(16) NOT NULL, `name` VARCHAR(96) NOT NULL,
  `method` ENUM('straight_line','reducing_balance','units_of_production','none') NOT NULL DEFAULT 'straight_line',
  `life_months` SMALLINT NOT NULL DEFAULT 60, `rate_pct` DECIMAL(6,3) NOT NULL DEFAULT 0 COMMENT 'reducing balance annual rate', `residual_pct` DECIMAL(6,3) NOT NULL DEFAULT 0,
  `asset_account` VARCHAR(16) NOT NULL, `accum_account` VARCHAR(16) NOT NULL, `expense_account` VARCHAR(16) NOT NULL,
  `capital_allowance_note` VARCHAR(255) COMMENT 'CITA Second Schedule initial/annual allowance for the class', `active` TINYINT(1) NOT NULL DEFAULT 1, `sort` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_pulse_acc_asset_class`), UNIQUE KEY `code` (`code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_asset` (
  `id_pulse_acc_asset` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(32) NOT NULL, `name` VARCHAR(128) NOT NULL,
  `id_pulse_acc_asset_class` INT UNSIGNED NOT NULL, `class_code` VARCHAR(16) NOT NULL,
  `id_pulse_asset` INT UNSIGNED DEFAULT NULL COMMENT 'link to the engineering register in pulsemaintenance — never a copy of it',
  `id_parent` INT UNSIGNED DEFAULT NULL COMMENT 'componentised assets: chiller compressor under chiller',
  `id_room` INT UNSIGNED DEFAULT NULL, `location` VARCHAR(128), `cost_centre` VARCHAR(32) NOT NULL DEFAULT 'general', `department` VARCHAR(32) NOT NULL DEFAULT 'general',
  `supplier` VARCHAR(128), `invoice_ref` VARCHAR(64), `serial_no` VARCHAR(64),
  `acquisition_date` DATE NOT NULL, `in_service_date` DATE NOT NULL, `cost` DECIMAL(20,6) NOT NULL DEFAULT 0, `residual_value` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `method` ENUM('straight_line','reducing_balance','units_of_production','none') NOT NULL DEFAULT 'straight_line',
  `life_months` SMALLINT NOT NULL DEFAULT 60, `rate_pct` DECIMAL(6,3) NOT NULL DEFAULT 0, `units_total` DECIMAL(14,2) DEFAULT NULL, `units_used` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `revaluation` DECIMAL(20,6) NOT NULL DEFAULT 0, `impairment` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `accum_depreciation` DECIMAL(20,6) NOT NULL DEFAULT 0, `nbv` DECIMAL(20,6) NOT NULL DEFAULT 0, `last_period` CHAR(7) DEFAULT NULL,
  `status` ENUM('in_service','idle','under_repair','held_for_sale','disposed','written_off') NOT NULL DEFAULT 'in_service',
  `disposal_date` DATE DEFAULT NULL, `disposal_proceeds` DECIMAL(20,6) NOT NULL DEFAULT 0, `disposal_gain_loss` DECIMAL(20,6) NOT NULL DEFAULT 0, `disposal_note` VARCHAR(255),
  `capex_budget_line` VARCHAR(32) DEFAULT NULL, `note` VARCHAR(255), `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL, `date_upd` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_asset`), UNIQUE KEY `code` (`code`), KEY `cls` (`id_pulse_acc_asset_class`,`status`), KEY `eng` (`id_pulse_asset`), KEY `room` (`id_room`), KEY `par` (`id_parent`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_depreciation` (
  `id_pulse_acc_depreciation` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_acc_asset` INT UNSIGNED NOT NULL, `class_code` VARCHAR(16) NOT NULL,
  `period` CHAR(7) NOT NULL, `business_date` DATE NOT NULL, `method` VARCHAR(24) NOT NULL DEFAULT 'straight_line',
  `opening_nbv` DECIMAL(20,6) NOT NULL DEFAULT 0, `amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `accum_after` DECIMAL(20,6) NOT NULL DEFAULT 0, `closing_nbv` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_depreciation`), UNIQUE KEY `asset_period` (`id_pulse_acc_asset`,`period`), KEY `per` (`period`,`class_code`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_pulse_acc_asset_event` (
  `id_pulse_acc_asset_event` INT UNSIGNED NOT NULL AUTO_INCREMENT, `id_pulse_acc_asset` INT UNSIGNED NOT NULL,
  `type` ENUM('addition','component','transfer','revaluation','impairment','disposal','write_off','reinstate') NOT NULL,
  `business_date` DATE NOT NULL, `amount` DECIMAL(20,6) NOT NULL DEFAULT 0, `from_value` VARCHAR(128), `to_value` VARCHAR(128),
  `note` VARCHAR(255), `id_pulse_acc_journal` INT UNSIGNED DEFAULT NULL, `id_employee` INT UNSIGNED, `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_pulse_acc_asset_event`), KEY `a` (`id_pulse_acc_asset`,`business_date`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `PREFIX_pulse_acc_account` (`code`,`name`,`type`,`subtype`,`parent_code`,`usali_dept`,`normal_balance`,`is_header`,`is_control`,`control_of`,`cashflow`,`is_contra`,`sort`) VALUES
('1000','ASSETS','asset','header',NULL,'balance_sheet','debit',1,0,NULL,'none',0,1000),
('1100','Cash and bank','asset','header','1000','balance_sheet','debit',1,0,NULL,'cash',0,1100),
('1110','Cash on hand — front office','asset','cash','1100','balance_sheet','debit',0,1,'bank','cash',0,1110),
('1111','Cash on hand — F&B outlets','asset','cash','1100','balance_sheet','debit',0,1,'bank','cash',0,1111),
('1115','Petty cash / imprest','asset','cash','1100','balance_sheet','debit',0,1,'bank','cash',0,1115),
('1120','Bank — Zenith Bank current','asset','cash','1100','balance_sheet','debit',0,1,'bank','cash',0,1120),
('1121','Bank — GTBank current','asset','cash','1100','balance_sheet','debit',0,1,'bank','cash',0,1121),
('1122','Bank — Access Bank collections','asset','cash','1100','balance_sheet','debit',0,1,'bank','cash',0,1122),
('1125','Bank — domiciliary (USD)','asset','cash','1100','balance_sheet','debit',0,1,'bank','cash',0,1125),
('1130','Card & gateway settlement in transit','asset','cash','1100','balance_sheet','debit',0,1,'bank','cash',0,1130),
('1200','Receivables','asset','header','1000','balance_sheet','debit',1,0,NULL,'operating',0,1200),
('1210','Guest ledger (in-house)','asset','receivable','1200','balance_sheet','debit',0,1,'guest_ledger','operating',0,1210),
('1220','City ledger — trade receivables','asset','receivable','1200','balance_sheet','debit',0,1,'city_ledger','operating',0,1220),
('1230','OTA and channel receivables','asset','receivable','1200','balance_sheet','debit',0,1,'city_ledger','operating',0,1230),
('1240','Staff advances and loans','asset','receivable','1200','balance_sheet','debit',0,0,NULL,'operating',0,1240),
('1250','Other receivables','asset','receivable','1200','balance_sheet','debit',0,0,NULL,'operating',0,1250),
('1255','Prepaid rent','asset','prepayment','1200','balance_sheet','debit',0,0,NULL,'operating',0,1255),
('1256','Prepaid insurance','asset','prepayment','1200','balance_sheet','debit',0,0,NULL,'operating',0,1256),
('1260','WHT receivable (credits suffered)','asset','tax','1200','balance_sheet','debit',0,1,'wht_receivable','operating',0,1260),
('1270','VAT input (recoverable)','asset','tax','1200','balance_sheet','debit',0,1,'vat_input','operating',0,1270),
('1300','Inventories','asset','header','1000','balance_sheet','debit',1,0,NULL,'operating',0,1300),
('1310','Inventory — food','asset','inventory','1300','balance_sheet','debit',0,1,'inventory','operating',0,1310),
('1320','Inventory — beverage and liquor','asset','inventory','1300','balance_sheet','debit',0,1,'inventory','operating',0,1320),
('1330','Inventory — guest supplies and amenities','asset','inventory','1300','balance_sheet','debit',0,1,'inventory','operating',0,1330),
('1340','Inventory — cleaning and chemicals','asset','inventory','1300','balance_sheet','debit',0,1,'inventory','operating',0,1340),
('1350','Inventory — engineering spares','asset','inventory','1300','balance_sheet','debit',0,1,'inventory','operating',0,1350),
('1360','Inventory — printing and stationery','asset','inventory','1300','balance_sheet','debit',0,1,'inventory','operating',0,1360),
('1370','Inventory — linen and uniforms','asset','inventory','1300','balance_sheet','debit',0,1,'inventory','operating',0,1370),
('1400','Property, plant and equipment — cost','asset','header','1000','balance_sheet','debit',1,0,NULL,'investing',0,1400),
('1410','Land','asset','fixed_asset','1400','balance_sheet','debit',0,1,'fixed_asset','investing',0,1410),
('1420','Buildings and structures','asset','fixed_asset','1400','balance_sheet','debit',0,1,'fixed_asset','investing',0,1420),
('1430','Building improvements and renovations','asset','fixed_asset','1400','balance_sheet','debit',0,1,'fixed_asset','investing',0,1430),
('1440','Furniture, fittings and guest room equipment','asset','fixed_asset','1400','balance_sheet','debit',0,1,'fixed_asset','investing',0,1440),
('1450','Kitchen and laundry equipment','asset','fixed_asset','1400','balance_sheet','debit',0,1,'fixed_asset','investing',0,1450),
('1460','Generators and power plant','asset','fixed_asset','1400','balance_sheet','debit',0,1,'fixed_asset','investing',0,1460),
('1470','HVAC, chillers and lifts','asset','fixed_asset','1400','balance_sheet','debit',0,1,'fixed_asset','investing',0,1470),
('1480','IT and office equipment','asset','fixed_asset','1400','balance_sheet','debit',0,1,'fixed_asset','investing',0,1480),
('1490','Motor vehicles','asset','fixed_asset','1400','balance_sheet','debit',0,1,'fixed_asset','investing',0,1490),
('1500','Accumulated depreciation','asset','header','1000','balance_sheet','credit',1,0,NULL,'none',1,1500),
('1520','Accumulated depreciation — buildings','asset','accum_depreciation','1500','balance_sheet','credit',0,0,NULL,'none',1,1520),
('1530','Accumulated depreciation — improvements','asset','accum_depreciation','1500','balance_sheet','credit',0,0,NULL,'none',1,1530),
('1540','Accumulated depreciation — FF&E','asset','accum_depreciation','1500','balance_sheet','credit',0,0,NULL,'none',1,1540),
('1550','Accumulated depreciation — kitchen and laundry','asset','accum_depreciation','1500','balance_sheet','credit',0,0,NULL,'none',1,1550),
('1560','Accumulated depreciation — generators','asset','accum_depreciation','1500','balance_sheet','credit',0,0,NULL,'none',1,1560),
('1570','Accumulated depreciation — HVAC and lifts','asset','accum_depreciation','1500','balance_sheet','credit',0,0,NULL,'none',1,1570),
('1580','Accumulated depreciation — IT and office','asset','accum_depreciation','1500','balance_sheet','credit',0,0,NULL,'none',1,1580),
('1590','Accumulated depreciation — motor vehicles','asset','accum_depreciation','1500','balance_sheet','credit',0,0,NULL,'none',1,1590);

INSERT IGNORE INTO `PREFIX_pulse_acc_account` (`code`,`name`,`type`,`subtype`,`parent_code`,`usali_dept`,`normal_balance`,`is_header`,`is_control`,`control_of`,`cashflow`,`is_contra`,`sort`) VALUES
('2000','LIABILITIES','liability','header',NULL,'balance_sheet','credit',1,0,NULL,'none',0,2000),
('2100','Payables','liability','header','2000','balance_sheet','credit',1,0,NULL,'operating',0,2100),
('2110','Trade payables (suppliers)','liability','payable','2100','balance_sheet','credit',0,1,'ap','operating',0,2110),
('2120','GRN accrual — goods received not invoiced','liability','payable','2100','balance_sheet','credit',0,1,'grn_accrual','operating',0,2120),
('2130','Accrued expenses','liability','payable','2100','balance_sheet','credit',0,0,NULL,'operating',0,2130),
('2140','Salaries and wages payable','liability','payable','2100','balance_sheet','credit',0,0,NULL,'operating',0,2140),
('2150','Pension payable (PenCom)','liability','payable','2100','balance_sheet','credit',0,0,NULL,'operating',0,2150),
('2155','PAYE payable','liability','payable','2100','balance_sheet','credit',0,0,NULL,'operating',0,2155),
('2160','NSITF / ITF / NHF payable','liability','payable','2100','balance_sheet','credit',0,0,NULL,'operating',0,2160),
('2200','Tax liabilities','liability','header','2000','balance_sheet','credit',1,0,NULL,'operating',0,2200),
('2210','VAT output payable','liability','tax','2200','balance_sheet','credit',0,1,'vat_output','operating',0,2210),
('2220','VAT payable to FIRS (net of return)','liability','tax','2200','balance_sheet','credit',0,0,NULL,'operating',0,2220),
('2230','WHT payable (deducted from suppliers)','liability','tax','2200','balance_sheet','credit',0,1,'wht_payable','operating',0,2230),
('2240','Consumption tax payable (Rivers State)','liability','tax','2200','balance_sheet','credit',0,1,'consumption_tax','operating',0,2240),
('2250','Company income tax payable','liability','tax','2200','balance_sheet','credit',0,0,NULL,'operating',0,2250),
('2300','Guest and deposit liabilities','liability','header','2000','balance_sheet','credit',1,0,NULL,'operating',0,2300),
('2310','Advance deposits and prepaid bookings','liability','deposit','2300','balance_sheet','credit',0,1,'deposit','operating',0,2310),
('2320','Guest ledger credit balances','liability','deposit','2300','balance_sheet','credit',0,0,NULL,'operating',0,2320),
('2330','Gift vouchers and credits outstanding','liability','deposit','2300','balance_sheet','credit',0,0,NULL,'operating',0,2330),
('2340','Tips and gratuities payable','liability','payable','2300','balance_sheet','credit',0,0,NULL,'operating',0,2340),
('2400','Borrowings and other liabilities','liability','header','2000','balance_sheet','credit',1,0,NULL,'financing',0,2400),
('2410','Bank overdraft','liability','borrowing','2400','balance_sheet','credit',0,0,NULL,'financing',0,2410),
('2420','Loans payable — current','liability','borrowing','2400','balance_sheet','credit',0,0,NULL,'financing',0,2420),
('2430','Loans payable — non-current','liability','borrowing','2400','balance_sheet','credit',0,0,NULL,'financing',0,2430),
('2440','Directors current account','liability','borrowing','2400','balance_sheet','credit',0,0,NULL,'financing',0,2440),
('3000','EQUITY','equity','header',NULL,'balance_sheet','credit',1,0,NULL,'financing',0,3000),
('3100','Share capital','equity','capital','3000','balance_sheet','credit',0,0,NULL,'financing',0,3100),
('3200','Share premium','equity','capital','3000','balance_sheet','credit',0,0,NULL,'financing',0,3200),
('3300','Revaluation reserve','equity','reserve','3000','balance_sheet','credit',0,0,NULL,'none',0,3300),
('3400','Retained earnings','equity','retained','3000','balance_sheet','credit',0,0,NULL,'financing',0,3400),
('3500','Current year result','equity','pl_clearing','3000','balance_sheet','credit',0,0,NULL,'none',0,3500),
('3600','Owner drawings and dividends','equity','drawings','3000','balance_sheet','debit',0,0,NULL,'financing',1,3600);

INSERT IGNORE INTO `PREFIX_pulse_acc_account` (`code`,`name`,`type`,`subtype`,`parent_code`,`usali_dept`,`normal_balance`,`is_header`,`is_control`,`control_of`,`cashflow`,`is_contra`,`sort`) VALUES
('4000','REVENUE','revenue','header',NULL,'balance_sheet','credit',1,0,NULL,'operating',0,4000),
('4100','Rooms revenue','revenue','header','4000','rooms','credit',1,0,NULL,'operating',0,4100),
('4110','Room revenue — transient','revenue','room_revenue','4100','rooms','credit',0,0,NULL,'operating',0,4110),
('4120','Room revenue — corporate and contract','revenue','room_revenue','4100','rooms','credit',0,0,NULL,'operating',0,4120),
('4130','Room revenue — groups and conferences','revenue','room_revenue','4100','rooms','credit',0,0,NULL,'operating',0,4130),
('4140','Room revenue — OTA and channels','revenue','room_revenue','4100','rooms','credit',0,0,NULL,'operating',0,4140),
('4150','Day use, early check-in and late checkout','revenue','room_revenue','4100','rooms','credit',0,0,NULL,'operating',0,4150),
('4160','Extra bed and upgrades','revenue','room_revenue','4100','rooms','credit',0,0,NULL,'operating',0,4160),
('4170','Package revenue allocated — rooms','revenue','room_revenue','4100','rooms','credit',0,0,NULL,'operating',0,4170),
('4180','No-show and cancellation revenue','revenue','room_revenue','4100','rooms','credit',0,0,NULL,'operating',0,4180),
('4190','Rooms allowances and rebates','revenue','allowance','4100','rooms','debit',0,0,NULL,'operating',1,4190),
('4200','Food and beverage revenue','revenue','header','4000','fnb','credit',1,0,NULL,'operating',0,4200),
('4210','Food revenue — restaurant','revenue','fnb_revenue','4200','fnb','credit',0,0,NULL,'operating',0,4210),
('4215','Food revenue — room service','revenue','fnb_revenue','4200','fnb','credit',0,0,NULL,'operating',0,4215),
('4220','Food revenue — banqueting and events','revenue','fnb_revenue','4200','fnb','credit',0,0,NULL,'operating',0,4220),
('4230','Beverage revenue — soft drinks and water','revenue','fnb_revenue','4200','fnb','credit',0,0,NULL,'operating',0,4230),
('4235','Beverage revenue — beer, wine and spirits','revenue','fnb_revenue','4200','fnb','credit',0,0,NULL,'operating',0,4235),
('4240','Bar revenue','revenue','fnb_revenue','4200','fnb','credit',0,0,NULL,'operating',0,4240),
('4250','Service charge income','revenue','fnb_revenue','4200','fnb','credit',0,0,NULL,'operating',0,4250),
('4260','F&B allowances and rebates','revenue','allowance','4200','fnb','debit',0,0,NULL,'operating',1,4260),
('4300','Other operated departments','revenue','header','4000','other_operated','credit',1,0,NULL,'operating',0,4300),
('4310','Laundry and valet revenue','revenue','other_revenue','4300','other_operated','credit',0,0,NULL,'operating',0,4310),
('4320','Spa and wellness revenue','revenue','other_revenue','4300','other_operated','credit',0,0,NULL,'operating',0,4320),
('4330','Telephone and internet revenue','revenue','other_revenue','4300','other_operated','credit',0,0,NULL,'operating',0,4330),
('4340','Minibar revenue','revenue','other_revenue','4300','other_operated','credit',0,0,NULL,'operating',0,4340),
('4350','Business centre revenue','revenue','other_revenue','4300','other_operated','credit',0,0,NULL,'operating',0,4350),
('4360','Hall and venue hire','revenue','other_revenue','4300','other_operated','credit',0,0,NULL,'operating',0,4360),
('4370','Airport transfer and transport','revenue','other_revenue','4300','other_operated','credit',0,0,NULL,'operating',0,4370),
('4380','Other operated allowances','revenue','allowance','4300','other_operated','debit',0,0,NULL,'operating',1,4380),
('4400','Miscellaneous income','revenue','header','4000','other_operated','credit',1,0,NULL,'operating',0,4400),
('4410','Shop and concession rental income','revenue','other_revenue','4400','other_operated','credit',0,0,NULL,'operating',0,4410),
('4420','Commission income','revenue','other_revenue','4400','other_operated','credit',0,0,NULL,'operating',0,4420),
('4430','Damage and loss recovery','revenue','other_revenue','4400','other_operated','credit',0,0,NULL,'operating',0,4430),
('4900','Non-operating income','revenue','header','4000','non_operating','credit',1,0,NULL,'operating',0,4900),
('4910','Interest income','revenue','non_operating','4900','non_operating','credit',0,0,NULL,'investing',0,4910),
('4920','Gain on disposal of fixed assets','revenue','non_operating','4900','non_operating','credit',0,0,NULL,'investing',0,4920),
('4930','Foreign exchange gain','revenue','non_operating','4900','non_operating','credit',0,0,NULL,'operating',0,4930);

INSERT IGNORE INTO `PREFIX_pulse_acc_account` (`code`,`name`,`type`,`subtype`,`parent_code`,`usali_dept`,`normal_balance`,`is_header`,`is_control`,`control_of`,`cashflow`,`is_contra`,`sort`) VALUES
('5000','COST OF SALES','expense','header',NULL,'balance_sheet','debit',1,0,NULL,'operating',0,5000),
('5100','Cost of sales — food','expense','cost_of_sales','5000','fnb','debit',0,0,NULL,'operating',0,5100),
('5200','Cost of sales — beverage','expense','cost_of_sales','5000','fnb','debit',0,0,NULL,'operating',0,5200),
('5300','Cost of sales — other operated departments','expense','cost_of_sales','5000','other_operated','debit',0,0,NULL,'operating',0,5300),
('5400','Guest supplies and amenities consumed','expense','cost_of_sales','5000','rooms','debit',0,0,NULL,'operating',0,5400),
('5500','Stock variance and wastage','expense','cost_of_sales','5000','fnb','debit',0,0,NULL,'operating',0,5500),
('6000','DEPARTMENTAL EXPENSES','expense','header',NULL,'balance_sheet','debit',1,0,NULL,'operating',0,6000),
('6100','Rooms department','expense','header','6000','rooms','debit',1,0,NULL,'operating',0,6100),
('6110','Rooms payroll and related','expense','payroll','6100','rooms','debit',0,0,NULL,'operating',0,6110),
('6120','Rooms — casual labour','expense','payroll','6100','rooms','debit',0,0,NULL,'operating',0,6120),
('6130','Rooms — cleaning supplies and chemicals','expense','dept_expense','6100','rooms','debit',0,0,NULL,'operating',0,6130),
('6140','Rooms — laundry, linen and uniforms','expense','dept_expense','6100','rooms','debit',0,0,NULL,'operating',0,6140),
('6150','Rooms — guest transport and other','expense','dept_expense','6100','rooms','debit',0,0,NULL,'operating',0,6150),
('6160','Rooms — reservation and OTA commissions','expense','dept_expense','6100','rooms','debit',0,0,NULL,'operating',0,6160),
('6170','Rooms — contract cleaning','expense','dept_expense','6100','rooms','debit',0,0,NULL,'operating',0,6170),
('6200','Food and beverage department','expense','header','6000','fnb','debit',1,0,NULL,'operating',0,6200),
('6210','F&B payroll and related','expense','payroll','6200','fnb','debit',0,0,NULL,'operating',0,6210),
('6220','F&B — casual labour','expense','payroll','6200','fnb','debit',0,0,NULL,'operating',0,6220),
('6230','F&B — kitchen fuel (LPG)','expense','dept_expense','6200','fnb','debit',0,0,NULL,'operating',0,6230),
('6240','F&B — smallwares, crockery and glassware','expense','dept_expense','6200','fnb','debit',0,0,NULL,'operating',0,6240),
('6250','F&B — music and entertainment','expense','dept_expense','6200','fnb','debit',0,0,NULL,'operating',0,6250),
('6260','F&B — other operating expenses','expense','dept_expense','6200','fnb','debit',0,0,NULL,'operating',0,6260),
('6300','Other operated departments','expense','header','6000','other_operated','debit',1,0,NULL,'operating',0,6300),
('6310','Laundry department costs','expense','dept_expense','6300','other_operated','debit',0,0,NULL,'operating',0,6310),
('6320','Spa department costs','expense','dept_expense','6300','other_operated','debit',0,0,NULL,'operating',0,6320),
('6330','Telephone department costs','expense','dept_expense','6300','other_operated','debit',0,0,NULL,'operating',0,6330);

INSERT IGNORE INTO `PREFIX_pulse_acc_account` (`code`,`name`,`type`,`subtype`,`parent_code`,`usali_dept`,`normal_balance`,`is_header`,`is_control`,`control_of`,`cashflow`,`is_contra`,`sort`) VALUES
('7000','UNDISTRIBUTED OPERATING EXPENSES','expense','header',NULL,'balance_sheet','debit',1,0,NULL,'operating',0,7000),
('7100','Administrative and general','expense','header','7000','undistributed','debit',1,0,NULL,'operating',0,7100),
('7110','A&G payroll (management and accounts)','expense','payroll','7100','undistributed','debit',0,0,NULL,'operating',0,7110),
('7120','Office supplies, printing and stationery','expense','admin','7100','undistributed','debit',0,0,NULL,'operating',0,7120),
('7130','Bank charges and merchant fees','expense','admin','7100','undistributed','debit',0,0,NULL,'operating',0,7130),
('7140','Professional fees (audit, legal, consultancy)','expense','admin','7100','undistributed','debit',0,0,NULL,'operating',0,7140),
('7150','Licences, permits and government levies','expense','admin','7100','undistributed','debit',0,0,NULL,'operating',0,7150),
('7160','Communication, internet and subscriptions','expense','admin','7100','undistributed','debit',0,0,NULL,'operating',0,7160),
('7170','Staff welfare, training and uniforms','expense','admin','7100','undistributed','debit',0,0,NULL,'operating',0,7170),
('7180','Travel and transport','expense','admin','7100','undistributed','debit',0,0,NULL,'operating',0,7180),
('7190','Bad debts written off','expense','admin','7100','undistributed','debit',0,0,NULL,'operating',0,7190),
('7195','Security services','expense','admin','7100','undistributed','debit',0,0,NULL,'operating',0,7195),
('7200','Sales and marketing','expense','header','7000','undistributed','debit',1,0,NULL,'operating',0,7200),
('7210','Marketing payroll','expense','payroll','7200','undistributed','debit',0,0,NULL,'operating',0,7210),
('7220','Advertising and promotions','expense','marketing','7200','undistributed','debit',0,0,NULL,'operating',0,7220),
('7230','OTA and travel agent commissions','expense','marketing','7200','undistributed','debit',0,0,NULL,'operating',0,7230),
('7240','Loyalty and guest relations','expense','marketing','7200','undistributed','debit',0,0,NULL,'operating',0,7240),
('7300','Property operations and maintenance','expense','header','7000','undistributed','debit',1,0,NULL,'operating',0,7300),
('7310','Maintenance payroll','expense','payroll','7300','undistributed','debit',0,0,NULL,'operating',0,7310),
('7320','Repairs and maintenance — building','expense','repairs','7300','undistributed','debit',0,0,NULL,'operating',0,7320),
('7330','Repairs and maintenance — plant and equipment','expense','repairs','7300','undistributed','debit',0,0,NULL,'operating',0,7330),
('7340','Engineering spares and consumables','expense','repairs','7300','undistributed','debit',0,0,NULL,'operating',0,7340),
('7350','Contract maintenance (lift, AC, fire)','expense','repairs','7300','undistributed','debit',0,0,NULL,'operating',0,7350),
('7360','Grounds and landscaping','expense','repairs','7300','undistributed','debit',0,0,NULL,'operating',0,7360),
('7400','Utilities','expense','header','7000','undistributed','debit',1,0,NULL,'operating',0,7400),
('7410','Electricity — grid','expense','utilities','7400','undistributed','debit',0,0,NULL,'operating',0,7410),
('7420','Diesel — generator fuel','expense','utilities','7400','undistributed','debit',0,0,NULL,'operating',0,7420),
('7430','Generator maintenance and lubricants','expense','utilities','7400','undistributed','debit',0,0,NULL,'operating',0,7430),
('7440','Water and borehole','expense','utilities','7400','undistributed','debit',0,0,NULL,'operating',0,7440),
('7450','Cooking gas','expense','utilities','7400','undistributed','debit',0,0,NULL,'operating',0,7450),
('7460','Waste disposal and sanitation','expense','utilities','7400','undistributed','debit',0,0,NULL,'operating',0,7460),
('8000','FIXED CHARGES','expense','header',NULL,'balance_sheet','debit',1,0,NULL,'operating',0,8000),
('8100','Rent and lease','expense','fixed_charge','8000','fixed_charges','debit',0,0,NULL,'operating',0,8100),
('8200','Property rates and land use charge','expense','fixed_charge','8000','fixed_charges','debit',0,0,NULL,'operating',0,8200),
('8300','Insurance','expense','fixed_charge','8000','fixed_charges','debit',0,0,NULL,'operating',0,8300),
('8400','Depreciation','expense','header','8000','fixed_charges','debit',1,0,NULL,'none',0,8400),
('8410','Depreciation — buildings','expense','depreciation','8400','fixed_charges','debit',0,0,NULL,'none',0,8410),
('8420','Depreciation — improvements','expense','depreciation','8400','fixed_charges','debit',0,0,NULL,'none',0,8420),
('8430','Depreciation — FF&E','expense','depreciation','8400','fixed_charges','debit',0,0,NULL,'none',0,8430),
('8440','Depreciation — kitchen and laundry equipment','expense','depreciation','8400','fixed_charges','debit',0,0,NULL,'none',0,8440),
('8450','Depreciation — generators and power plant','expense','depreciation','8400','fixed_charges','debit',0,0,NULL,'none',0,8450),
('8460','Depreciation — HVAC and lifts','expense','depreciation','8400','fixed_charges','debit',0,0,NULL,'none',0,8460),
('8470','Depreciation — IT and office equipment','expense','depreciation','8400','fixed_charges','debit',0,0,NULL,'none',0,8470),
('8480','Depreciation — motor vehicles','expense','depreciation','8400','fixed_charges','debit',0,0,NULL,'none',0,8480),
('8500','Amortisation of intangibles','expense','depreciation','8000','fixed_charges','debit',0,0,NULL,'none',0,8500),
('8600','Interest and finance charges','expense','fixed_charge','8000','non_operating','debit',0,0,NULL,'financing',0,8600),
('8700','Loss on disposal of fixed assets','expense','non_operating','8000','non_operating','debit',0,0,NULL,'investing',0,8700),
('8800','Foreign exchange loss','expense','non_operating','8000','non_operating','debit',0,0,NULL,'operating',0,8800),
('8900','Impairment of assets','expense','non_operating','8000','non_operating','debit',0,0,NULL,'none',0,8900),
('9000','Income tax expense','expense','header',NULL,'non_operating','debit',1,0,NULL,'operating',0,9000),
('9100','Company income tax','expense','income_tax','9000','non_operating','debit',0,0,NULL,'operating',0,9100),
('9200','Tertiary education tax','expense','income_tax','9000','non_operating','debit',0,0,NULL,'operating',0,9200);

UPDATE `PREFIX_pulse_acc_account` a INNER JOIN `PREFIX_pulse_acc_account` p ON p.code=a.parent_code SET a.id_parent=p.id_pulse_acc_account, a.depth=IF(p.parent_code IS NULL,1,2) WHERE a.parent_code IS NOT NULL;

INSERT IGNORE INTO `PREFIX_pulse_acc_map` (`map_type`,`key_value`,`label`,`account_code`,`tax_account_code`,`cost_centre`) VALUES
('charge_code','ROOM','Room Charge','4110','2210','rooms'),
('charge_code','EXTB','Extra Bed','4160','2210','rooms'),
('charge_code','LATE','Late Checkout','4150','2210','rooms'),
('charge_code','ECI','Early Check-in','4150','2210','rooms'),
('charge_code','DAYUSE','Day Use','4150','2210','rooms'),
('charge_code','UPG','Room Upgrade','4160','2210','rooms'),
('charge_code','PKG','Package','4170','2210','rooms'),
('charge_code','RSVC','Room Service','4215','2210','fnb'),
('charge_code','REST','Restaurant','4210','2210','fnb'),
('charge_code','BAR','Bar','4240','2210','fnb'),
('charge_code','BRK','Breakfast','4210','2210','fnb'),
('charge_code','MINI','Minibar','4340','2210','rooms'),
('charge_code','LNDY','Laundry','4310','2210','laundry'),
('charge_code','SPA','Spa','4320','2210','spa'),
('charge_code','TELE','Telephone','4330','2210','telephone'),
('charge_code','MISC','Miscellaneous','4400','2210','general'),
('charge_code','SURCH','Card processing surcharge','4400','2210','general'),
('charge_code','DMG','Damage / Loss','4430',NULL,'general'),
('charge_code','ADJ','Adjustment / rebate','4190',NULL,'rooms'),
('charge_code','CASH','Cash','1110',NULL,'general'),
('charge_code','POS','Card (POS terminal)','1130',NULL,'general'),
('charge_code','CARD','Card (online)','1130',NULL,'general'),
('charge_code','TRF','Bank Transfer','1121',NULL,'general'),
('charge_code','MOMO','Mobile money','1130',NULL,'general'),
('charge_code','ONL','Online Gateway','1130',NULL,'general'),
('charge_code','CL','City Ledger (Company)','1220',NULL,'general'),
('charge_code','DEP','Deposit Applied','2310',NULL,'general');

INSERT IGNORE INTO `PREFIX_pulse_acc_map` (`map_type`,`key_value`,`label`,`account_code`,`tax_account_code`,`cost_centre`,`wht_rate_pct`) VALUES
('expense_category','FOOD','Food purchases','5100','1270','fnb',0),
('expense_category','BEV','Beverage purchases','5200','1270','fnb',0),
('expense_category','GSUP','Guest supplies and amenities','5400','1270','rooms',0),
('expense_category','CLEAN','Cleaning and laundry chemicals','6130','1270','housekeeping',0),
('expense_category','SAL','Salaries and wages','7110',NULL,'admin',0),
('expense_category','CAS','Casual staff','6120',NULL,'general',0),
('expense_category','STAFFM','Staff meals and welfare','7170',NULL,'admin',0),
('expense_category','DIESEL','Diesel / generator fuel','7420','1270','maintenance',0),
('expense_category','POWER','Electricity (grid)','7410','1270','maintenance',0),
('expense_category','WATER','Water','7440','1270','maintenance',0),
('expense_category','GAS','Cooking gas','7450','1270','fnb',0),
('expense_category','INET','Internet and telephone','7160','1270','admin',5),
('expense_category','TV','DStv / TV subscriptions','7160','1270','admin',5),
('expense_category','RM','Repairs and maintenance','7320','1270','maintenance',5),
('expense_category','PARTS','Spare parts','7340','1270','maintenance',0),
('expense_category','VENDOR','Contractors','7350','1270','maintenance',5),
('expense_category','ADMIN','Office and admin','7120','1270','admin',0),
('expense_category','BANK','Bank charges','7130',NULL,'admin',0),
('expense_category','LIC','Licences, permits and levies','7150',NULL,'admin',0),
('expense_category','INS','Insurance','8300','1270','admin',5),
('expense_category','PROF','Professional fees','7140','1270','admin',5),
('expense_category','MKT','Marketing and OTA commissions','7220','1270','sales',5),
('expense_category','SEC','Security','7195','1270','security',5),
('expense_category','RENT','Rent / lease','8100','1270','admin',10),
('expense_category','TAX','Taxes remitted','7150',NULL,'admin',0),
('expense_category','MISC','Miscellaneous','7120','1270','general',0);

INSERT IGNORE INTO `PREFIX_pulse_acc_map` (`map_type`,`key_value`,`label`,`account_code`,`cost_centre`) VALUES
('payment_method','cash','Cash','1110','general'),
('payment_method','petty_cash','Petty cash','1115','general'),
('payment_method','transfer','Bank transfer','1121','general'),
('payment_method','card','Card','1130','general'),
('payment_method','pos','Bank POS terminal','1130','general'),
('payment_method','cheque','Cheque','1121','general'),
('payment_method','online','Online gateway','1130','general'),
('payment_method','mobile_money','Mobile money','1130','general'),
('payment_method','credit','On credit (supplier)','2110','general'),
('payment_method','city_ledger','City ledger','1220','general'),
('payment_method','room','Charge to room','1210','general'),
('payment_method','voucher','Voucher redeemed','2330','general'),
('payment_method','comp','Complimentary','4260','general'),
('payment_method','contra','Contra / offset','1220','general'),
('payroll_department','rooms','Rooms payroll','6110','rooms'),
('payroll_department','housekeeping','Housekeeping payroll','6110','housekeeping'),
('payroll_department','fnb','F&B payroll','6210','fnb'),
('payroll_department','laundry','Laundry payroll','6310','laundry'),
('payroll_department','maintenance','Maintenance payroll','7310','maintenance'),
('payroll_department','sales','Marketing payroll','7210','sales'),
('payroll_department','security','Security','7195','security'),
('payroll_department','admin','A&G payroll','7110','admin'),
('payroll_department','general','A&G payroll','7110','general'),
('department','rooms','Rooms','','rooms'),
('department','fnb','Food & Beverage','','fnb'),
('department','minibar','Minibar','','rooms'),
('department','laundry','Laundry','','laundry'),
('department','spa','Spa','','spa'),
('department','telephone','Telephone','','telephone'),
('department','business_centre','Business centre','','business_centre'),
('department','housekeeping','Housekeeping','','housekeeping'),
('department','maintenance','Maintenance','','maintenance'),
('department','admin','Administration','','admin'),
('department','sales','Sales & marketing','','sales'),
('department','security','Security','','security'),
('department','general','General','','general'),
('department','misc','Miscellaneous','','general'),
('department','adjustment','Adjustment','','general'),
('folio_type','guest','Guest folio','1210','rooms'),
('folio_type','company','Company folio','1220','general'),
('folio_type','group','Group folio','1210','rooms'),
('folio_type','master','Master folio','1210','rooms'),
('folio_type','house','House / outlet folio','1210','general');

INSERT IGNORE INTO `PREFIX_pulse_acc_map` (`map_type`,`key_value`,`label`,`account_code`,`contra_account_code`,`cost_centre`) VALUES
('inv_category','FOOD','Food stock','5100','1310','fnb'),
('inv_category','BEV','Soft drinks and water','5200','1320','fnb'),
('inv_category','LIQ','Beer, wine and spirits','5200','1320','fnb'),
('inv_category','AMEN','Guest amenities','5400','1330','rooms'),
('inv_category','CLEAN','Cleaning and chemicals','6130','1340','housekeeping'),
('inv_category','MINI','Minibar stock','5300','1320','rooms'),
('inv_category','STAT','Stationery and printing','7120','1360','admin'),
('inv_category','KEY','Key cards and FO consumables','5400','1330','rooms'),
('inv_category','ENG','Engineering consumables','7340','1350','maintenance'),
('inv_category','UNIF','Uniforms','7170','1370','admin'),
('pos_major_group','food','Food','4210',NULL,'fnb'),
('pos_major_group','beverage','Soft drinks and water','4230',NULL,'fnb'),
('pos_major_group','liquor','Beer, wine and spirits','4235',NULL,'fnb'),
('pos_major_group','tobacco','Tobacco','4240',NULL,'fnb'),
('pos_major_group','other','Other F&B','4400',NULL,'fnb');

INSERT IGNORE INTO `PREFIX_pulse_acc_map` (`map_type`,`key_value`,`label`,`account_code`,`wht_rate_pct`,`note`) VALUES
('wht_category','services','Professional and technical services','2230',5,'CITA/VAT Act: 5% on services rendered by a resident company or individual'),
('wht_category','contracts','Construction and supply contracts','2230',5,'5% on contracts of supply and construction (all-inclusive contracts)'),
('wht_category','rent','Rent of property and equipment','2230',10,'10% on rent, including equipment hire'),
('wht_category','dividends','Dividends, interest and royalties','2230',10,'10% on dividends, interest, rent and royalties to companies'),
('wht_category','commission','Commission and consultancy','2230',5,'5% commission, consultancy, management and technical fees'),
('wht_category','royalties','Royalties','2230',10,'10% royalties'),
('wht_category','other','Other','2230',5,'Default 5% where the payee type is unclear — confirm before remitting');

INSERT IGNORE INTO `PREFIX_pulse_acc_asset_class` (`code`,`name`,`method`,`life_months`,`rate_pct`,`residual_pct`,`asset_account`,`accum_account`,`expense_account`,`capital_allowance_note`,`sort`) VALUES
('LAND','Land','none',0,0,0,'1410','1520','8410','No capital allowance on land; only the building element qualifies.',1),
('BLDG','Buildings and structures','straight_line',600,0,5,'1420','1520','8410','CITA Second Schedule: industrial building 15% initial, 10% annual.',2),
('IMPR','Building improvements and renovations','straight_line',120,0,0,'1430','1530','8420','Treated with the building; 15% initial, 10% annual.',3),
('FFE','Furniture, fittings and guest room equipment','straight_line',60,0,5,'1440','1540','8430','Furniture and fittings: 25% initial, 20% annual.',4),
('KITCH','Kitchen and laundry equipment','straight_line',96,0,5,'1450','1550','8440','Plant and machinery: 50% initial, 25% annual.',5),
('GEN','Generators and power plant','reducing_balance',0,20,5,'1460','1560','8450','Plant and machinery: 50% initial, 25% annual. Reducing balance mirrors real generator wear.',6),
('HVAC','HVAC, chillers and lifts','straight_line',120,0,5,'1470','1570','8460','Plant and machinery: 50% initial, 25% annual.',7),
('IT','IT and office equipment','straight_line',36,0,0,'1480','1580','8470','Plant: 50% initial, 25% annual. Short book life reflects real replacement.',8),
('VEH','Motor vehicles','reducing_balance',0,25,10,'1490','1590','8480','Motor vehicles: 50% initial, 25% annual.',9);

INSERT IGNORE INTO `PREFIX_pulse_acc_bank_account` (`code`,`name`,`type`,`bank_name`,`currency`,`account_code`,`sort`) VALUES
('CASH-FO','Front office cash drawer','cash','','NGN','1110',1),
('CASH-FB','F&B outlet cash','cash','','NGN','1111',2),
('PETTY','Petty cash / imprest','petty_cash','','NGN','1115',3),
('CARD','Card and gateway settlement','card_settlement','','NGN','1130',9);
