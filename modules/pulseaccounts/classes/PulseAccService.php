<?php
/**
 * Shared helpers for Pulse Accounts: chart of accounts lookups, posting-rule resolution, period control,
 * document numbering and the dashboard roll-up. Every other class in the module leans on this one.
 */
class PulseAccService
{
    protected static $accountCache = array();
    protected static $mapCache = array();

    /* ---------------- environment ---------------- */

    public static function fd() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFolio'); }
    public static function pos() { return Module::isEnabled('pulsepos') && self::tableExists('pulse_pos_check'); }
    public static function inv() { return Module::isEnabled('pulseinventory') && self::tableExists('pulse_inv_grn'); }
    public static function rpt() { return Module::isEnabled('pulsereports') && self::tableExists('pulse_expense'); }
    public static function mnt() { return Module::isEnabled('pulsemaintenance') && self::tableExists('pulse_asset'); }
    public static function bd() { return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    public static function emp() { $c = Context::getContext(); return isset($c->employee) && $c->employee ? (int) $c->employee->id : 0; }
    public static function tableExists($t) { return (bool) Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.pSQL($t).'"'); }
    public static function vatPct() { return (float) Configuration::get('PULSE_ACC_VAT_PCT'); }
    public static function consumptionPct() { return (float) Configuration::get('PULSE_ACC_CONSUMPTION_PCT'); }
    public static function currency() { $c = Configuration::get('PULSE_ACC_CURRENCY'); return $c ? $c : 'NGN'; }

    /** Next document number in a per-prefix sequence held in pulse_setting (PC00012 style). */
    public static function nextNo($prefix, $width = 5)
    {
        $n = (int) PulseCoreService::setting('pulseaccounts', 'seq_'.$prefix) + 1;
        PulseCoreService::setting('pulseaccounts', 'seq_'.$prefix, $n);
        return $prefix.date('y').str_pad($n, $width, '0', STR_PAD_LEFT);
    }

    /* ---------------- chart of accounts ---------------- */

    public static function account($code)
    {
        $code = trim((string) $code);
        if ($code === '') { return null; }
        if (!isset(self::$accountCache[$code])) { self::$accountCache[$code] = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_account` WHERE code="'.pSQL($code).'"'); }
        return self::$accountCache[$code] ? self::$accountCache[$code] : null;
    }

    public static function accountById($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_account` WHERE id_pulse_acc_account='.(int) $id); }

    public static function accounts($type = null, $activeOnly = true, $postableOnly = false)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_account` WHERE 1'.($type ? ' AND type="'.pSQL($type).'"' : '').($activeOnly ? ' AND active=1' : '').($postableOnly ? ' AND is_header=0' : '').' ORDER BY sort, code');
    }

    /** Chart of accounts as a tree (headers carrying their children) for the COA screen. */
    public static function tree()
    {
        $rows = self::accounts(null, false);
        $byCode = array(); $children = array(); $roots = array();
        foreach ($rows as $r) { $byCode[$r['code']] = $r; $children[$r['code']] = array(); }
        foreach ($rows as $r) { if ($r['parent_code'] !== null && $r['parent_code'] !== '' && isset($byCode[$r['parent_code']])) { $children[$r['parent_code']][] = $r['code']; } else { $roots[] = $r['code']; } }
        return self::treeBranch($roots, $byCode, $children);
    }

    protected static function treeBranch(array $codes, array $byCode, array $children)
    {
        $out = array();
        foreach ($codes as $c) { $n = $byCode[$c]; $n['children'] = $children[$c] ? self::treeBranch($children[$c], $byCode, $children) : array(); $out[] = $n; }
        return $out;
    }

    public static function saveAccount(array $d)
    {
        $code = trim(Tools::substr((string) $d['code'], 0, 16));
        if ($code === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $code)) { throw new PrestaShopException('Account code must be short and alphanumeric'); }
        if (empty($d['name'])) { throw new PrestaShopException('Account name is required'); }
        $types = array('asset', 'liability', 'equity', 'revenue', 'expense');
        $type = in_array(isset($d['type']) ? $d['type'] : '', $types) ? $d['type'] : 'expense';
        $parent = isset($d['parent_code']) ? trim($d['parent_code']) : '';
        if ($parent === $code) { throw new PrestaShopException('An account cannot be its own parent'); }
        $row = array(
            'code' => pSQL($code), 'name' => pSQL(Tools::substr($d['name'], 0, 128)), 'type' => pSQL($type),
            'subtype' => pSQL(isset($d['subtype']) ? Tools::substr($d['subtype'], 0, 32) : ''), 'parent_code' => $parent !== '' ? pSQL($parent) : null,
            'id_parent' => $parent !== '' && ($p = self::account($parent)) ? (int) $p['id_pulse_acc_account'] : null,
            'depth' => $parent !== '' ? 2 : 0,
            'usali_dept' => pSQL(isset($d['usali_dept']) ? $d['usali_dept'] : 'balance_sheet'),
            'normal_balance' => in_array($type, array('asset', 'expense')) ? 'debit' : 'credit',
            'is_header' => !empty($d['is_header']) ? 1 : 0, 'is_control' => !empty($d['is_control']) ? 1 : 0,
            'control_of' => !empty($d['control_of']) ? pSQL($d['control_of']) : null,
            'cashflow' => pSQL(isset($d['cashflow']) ? $d['cashflow'] : 'operating'), 'is_contra' => !empty($d['is_contra']) ? 1 : 0,
            'active' => isset($d['active']) ? (int) (bool) $d['active'] : 1, 'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0),
            'note' => pSQL(isset($d['note']) ? Tools::substr($d['note'], 0, 255) : ''), 'date_upd' => date('Y-m-d H:i:s'),
        );
        self::$accountCache = array();
        if (!empty($d['id_pulse_acc_account'])) { Db::getInstance()->update('pulse_acc_account', $row, 'id_pulse_acc_account='.(int) $d['id_pulse_acc_account'], 0, true); $id = (int) $d['id_pulse_acc_account']; }
        else {
            if (self::account($code)) { throw new PrestaShopException('Account '.$code.' already exists'); }
            $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_acc_account', $row, true); $id = (int) Db::getInstance()->Insert_ID();
        }
        PulseCoreService::audit('pulseaccounts', 'account_save', array('code' => $code), 'pulse_acc_account', $id);
        return $id;
    }

    /** An account may only be deactivated (never deleted) once it carries journal lines. */
    public static function deactivateAccount($id)
    {
        $used = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE id_pulse_acc_account='.(int) $id);
        if ($used) { Db::getInstance()->update('pulse_acc_account', array('active' => 0), 'id_pulse_acc_account='.(int) $id); return 'deactivated'; }
        Db::getInstance()->delete('pulse_acc_account', 'id_pulse_acc_account='.(int) $id);
        self::$accountCache = array();
        return 'deleted';
    }

    /* ---------------- posting rules ---------------- */

    public static function map($type, $key)
    {
        $k = $type.'|'.$key;
        if (!isset(self::$mapCache[$k])) { self::$mapCache[$k] = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_map` WHERE map_type="'.pSQL($type).'" AND key_value="'.pSQL($key).'" AND active=1'); }
        return self::$mapCache[$k] ? self::$mapCache[$k] : null;
    }

    public static function maps($type = null)
    {
        return Db::getInstance()->executeS('SELECT m.*, a.name account_name, a.is_header FROM `'._DB_PREFIX_.'pulse_acc_map` m LEFT JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.code=m.account_code WHERE 1'.($type ? ' AND m.map_type="'.pSQL($type).'"' : '').' ORDER BY m.map_type, m.key_value');
    }

    /** Account code for a rule, or null when the item is unmapped (it then shows up in the unmapped list). */
    public static function mapAccount($type, $key, $field = 'account_code')
    {
        $m = self::map($type, $key);
        return $m && !empty($m[$field]) ? $m[$field] : null;
    }

    public static function saveMap(array $d)
    {
        if (empty($d['map_type']) || empty($d['key_value'])) { throw new PrestaShopException('Rule needs a type and a key'); }
        foreach (array('account_code', 'tax_account_code', 'contra_account_code') as $f) {
            if (!empty($d[$f])) { $a = self::account($d[$f]); if (!$a) { throw new PrestaShopException('Unknown account '.$d[$f]); } if ($a['is_header']) { throw new PrestaShopException('Account '.$d[$f].' is a heading — nothing may post to it'); } }
        }
        $row = array(
            'map_type' => pSQL($d['map_type']), 'key_value' => pSQL(Tools::substr($d['key_value'], 0, 64)), 'label' => pSQL(isset($d['label']) ? Tools::substr($d['label'], 0, 128) : ''),
            'account_code' => !empty($d['account_code']) ? pSQL($d['account_code']) : null, 'tax_account_code' => !empty($d['tax_account_code']) ? pSQL($d['tax_account_code']) : null,
            'contra_account_code' => !empty($d['contra_account_code']) ? pSQL($d['contra_account_code']) : null,
            'cost_centre' => pSQL(isset($d['cost_centre']) ? Tools::substr($d['cost_centre'], 0, 32) : ''), 'wht_rate_pct' => (float) (isset($d['wht_rate_pct']) ? $d['wht_rate_pct'] : 0),
            'note' => pSQL(isset($d['note']) ? Tools::substr($d['note'], 0, 255) : ''), 'active' => isset($d['active']) ? (int) (bool) $d['active'] : 1,
        );
        self::$mapCache = array();
        $ex = Db::getInstance()->getValue('SELECT id_pulse_acc_map FROM `'._DB_PREFIX_.'pulse_acc_map` WHERE map_type="'.pSQL($d['map_type']).'" AND key_value="'.pSQL($d['key_value']).'"');
        if ($ex) { Db::getInstance()->update('pulse_acc_map', $row, 'id_pulse_acc_map='.(int) $ex, 0, true); return (int) $ex; }
        Db::getInstance()->insert('pulse_acc_map', $row, true);
        return (int) Db::getInstance()->Insert_ID();
    }

    /**
     * Everything the hotel can post that has no rule yet. This is the screen the accountant checks
     * before a month-end: an unmapped charge code silently parks revenue in the suspense list.
     */
    public static function unmapped()
    {
        $out = array();
        if (self::fd()) {
            foreach (Db::getInstance()->executeS('SELECT c.code, c.name, c.department, c.is_payment FROM `'._DB_PREFIX_.'pulse_charge_code` c WHERE c.active=1 AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_acc_map` m WHERE m.map_type="charge_code" AND m.key_value=c.code AND m.active=1 AND m.account_code IS NOT NULL)') as $r) {
                $out[] = array('kind' => 'charge_code', 'key' => $r['code'], 'label' => $r['name'].' ('.$r['department'].($r['is_payment'] ? ', payment' : '').')', 'hint' => $r['is_payment'] ? 'Needs a cash/bank/receivable account' : 'Needs a revenue account');
            }
        }
        if (self::rpt()) {
            foreach (Db::getInstance()->executeS('SELECT c.code, c.name, c.group_name FROM `'._DB_PREFIX_.'pulse_expense_category` c WHERE c.active=1 AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_acc_map` m WHERE m.map_type="expense_category" AND m.key_value=c.code AND m.active=1 AND m.account_code IS NOT NULL)') as $r) {
                $out[] = array('kind' => 'expense_category', 'key' => $r['code'], 'label' => $r['name'].' ('.$r['group_name'].')', 'hint' => 'Needs an expense account');
            }
        }
        if (self::inv()) {
            foreach (Db::getInstance()->executeS('SELECT c.code, c.name, c.group_name FROM `'._DB_PREFIX_.'pulse_inv_category` c WHERE c.active=1 AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_acc_map` m WHERE m.map_type="inv_category" AND m.key_value=c.code AND m.active=1 AND m.account_code IS NOT NULL AND m.contra_account_code IS NOT NULL)') as $r) {
                $out[] = array('kind' => 'inv_category', 'key' => $r['code'], 'label' => $r['name'].' ('.$r['group_name'].')', 'hint' => 'Needs a stock account and a cost-of-sales account');
            }
        }
        if (self::pos()) {
            foreach (Db::getInstance()->executeS('SELECT DISTINCT major_group FROM `'._DB_PREFIX_.'pulse_pos_category` WHERE active=1 AND major_group NOT IN (SELECT key_value FROM `'._DB_PREFIX_.'pulse_acc_map` WHERE map_type="pos_major_group" AND active=1 AND account_code IS NOT NULL)') as $r) {
                $out[] = array('kind' => 'pos_major_group', 'key' => $r['major_group'], 'label' => 'POS major group '.$r['major_group'], 'hint' => 'Needs an F&B revenue account');
            }
        }
        foreach (Db::getInstance()->executeS('SELECT source, source_ref, last_error, business_date FROM `'._DB_PREFIX_.'pulse_acc_queue` WHERE status="failed" ORDER BY date_upd DESC LIMIT 50') as $r) {
            $out[] = array('kind' => 'failed_post', 'key' => $r['source'].' '.$r['source_ref'], 'label' => $r['business_date'].' — '.$r['last_error'], 'hint' => 'Fix the rule then re-run the queue');
        }
        return $out;
    }

    /* ---------------- periods ---------------- */

    public static function period($date) { return Tools::substr((string) $date, 0, 7); }

    public static function periodRow($code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_period` WHERE code="'.pSQL($code).'"'); }

    public static function periods($limit = 36) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_period` ORDER BY code DESC LIMIT '.(int) $limit); }

    /** Create the period a date falls in plus $ahead following months. Safe to call repeatedly. */
    public static function ensurePeriods($date, $ahead = 12)
    {
        $t = strtotime(Tools::substr((string) $date, 0, 7).'-01');
        if (!$t) { $t = strtotime(date('Y-m-01')); }
        for ($i = 0; $i <= (int) $ahead; $i++) {
            $m = date('Y-m', strtotime('+'.$i.' month', $t));
            if (self::periodRow($m)) { continue; }
            Db::getInstance()->insert('pulse_acc_period', array(
                'code' => pSQL($m), 'year' => (int) Tools::substr($m, 0, 4), 'month' => (int) Tools::substr($m, 5, 2),
                'date_from' => pSQL($m.'-01'), 'date_to' => pSQL(date('Y-m-t', strtotime($m.'-01'))), 'status' => 'open',
                'is_year_end' => (int) Tools::substr($m, 5, 2) === 12 ? 1 : 0, 'date_add' => date('Y-m-d H:i:s'),
            ), true);
        }
        return true;
    }

    /** Throws when the period is closed or locked — the hard block on posting into a closed month. */
    public static function assertPeriodOpen($date)
    {
        $code = self::period($date);
        $p = self::periodRow($code);
        if (!$p) { self::ensurePeriods($date, 1); $p = self::periodRow($code); }
        if (!$p) { throw new PrestaShopException('No accounting period for '.$code); }
        if ($p['status'] !== 'open') { throw new PrestaShopException('Period '.$code.' is '.$p['status'].' — reopen it or post to the next open period'); }
        return $code;
    }

    public static function periodOpen($date) { $p = self::periodRow(self::period($date)); return $p && $p['status'] === 'open'; }

    /**
     * Close a period: block further postings and (optionally) write the closing entry that sweeps
     * revenue and expense into the current-year result account.
     */
    public static function closePeriod($code, $withClosingEntry = true)
    {
        $p = self::periodRow($code);
        if (!$p) { throw new PrestaShopException('Unknown period '.$code); }
        if ($p['status'] !== 'open') { throw new PrestaShopException('Period '.$code.' is already '.$p['status']); }
        $pending = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_queue` WHERE status="pending" AND business_date BETWEEN "'.pSQL($p['date_from']).'" AND "'.pSQL($p['date_to']).'"');
        if ($pending) { throw new PrestaShopException($pending.' documents for '.$code.' are still waiting to post — drain the queue first'); }
        $drafts = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_journal` WHERE status="draft" AND period="'.pSQL($code).'"');
        if ($drafts) { throw new PrestaShopException($drafts.' draft journals in '.$code.' — post or delete them first'); }
        $idJ = null;
        if ($withClosingEntry) { $idJ = self::closingEntry($p); }
        Db::getInstance()->update('pulse_acc_period', array('status' => 'closed', 'closing_journal' => $idJ ? (int) $idJ : null, 'closed_by' => self::emp(), 'date_closed' => date('Y-m-d H:i:s')), 'id_pulse_acc_period='.(int) $p['id_pulse_acc_period']);
        PulseCoreService::audit('pulseaccounts', 'period_close', array('period' => $code, 'journal' => $idJ), 'pulse_acc_period', (int) $p['id_pulse_acc_period']);
        PulseCoreService::event('actionPulseAccPeriodClosed', array('period' => $code, 'id_journal' => $idJ));
        return $idJ;
    }

    public static function reopenPeriod($code)
    {
        $p = self::periodRow($code);
        if (!$p) { throw new PrestaShopException('Unknown period '.$code); }
        if ($p['status'] === 'locked') { throw new PrestaShopException('Period '.$code.' is locked by a year-end close and cannot be reopened'); }
        if ($p['closing_journal']) { PulseAccJournal::reverse((int) $p['closing_journal'], 'Period '.$code.' reopened'); }
        Db::getInstance()->update('pulse_acc_period', array('status' => 'open', 'closing_journal' => null, 'closed_by' => null, 'date_closed' => null), 'id_pulse_acc_period='.(int) $p['id_pulse_acc_period'], 0, true);
        PulseCoreService::audit('pulseaccounts', 'period_reopen', array('period' => $code), 'pulse_acc_period', (int) $p['id_pulse_acc_period']);
        return true;
    }

    /** Sweep the month's revenue and expense balances into 3500 Current year result. */
    protected static function closingEntry(array $p)
    {
        $clearing = Configuration::get('PULSE_ACC_PL_CLEARING');
        $rows = Db::getInstance()->executeS('SELECT l.account_code, a.type, ROUND(SUM(l.debit),2) d, ROUND(SUM(l.credit),2) c FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.period="'.pSQL($p['code']).'" AND a.type IN ("revenue","expense") GROUP BY l.account_code, a.type HAVING ROUND(SUM(l.debit)-SUM(l.credit),2)<>0');
        if (!$rows) { return null; }
        $lines = array(); $net = 0;
        foreach ($rows as $r) {
            $bal = round((float) $r['d'] - (float) $r['c'], 2);
            $lines[] = array('account' => $r['account_code'], 'debit' => $bal < 0 ? abs($bal) : 0, 'credit' => $bal > 0 ? $bal : 0, 'memo' => 'Close '.$p['code']);
            $net += $bal;
        }
        $net = round($net, 2);
        $lines[] = array('account' => $clearing, 'debit' => $net > 0 ? $net : 0, 'credit' => $net < 0 ? abs($net) : 0, 'memo' => ($net > 0 ? 'Loss' : 'Profit').' for '.$p['code']);
        return PulseAccJournal::post(array(
            'type' => 'closing', 'source' => 'closing', 'source_ref' => 'period:'.$p['code'], 'business_date' => $p['date_to'],
            'memo' => 'Closing entry '.$p['code'], 'reference' => $p['code'], 'lines' => $lines, 'allow_closed' => true,
        ));
    }

    /**
     * Year end: close every month of the year, roll the current-year result into retained earnings
     * and lock the twelve periods so nothing can slip back in.
     */
    public static function yearEnd($year)
    {
        $year = (int) $year;
        $last = $year.'-12-31';
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_period` WHERE `year`='.$year.' AND `status`="open" ORDER BY code') as $p) { self::closePeriod($p['code'], true); }
        $clearing = Configuration::get('PULSE_ACC_PL_CLEARING'); $retained = Configuration::get('PULSE_ACC_RETAINED_EARNINGS');
        $bal = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.debit)-SUM(l.credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l WHERE l.posted=1 AND l.account_code="'.pSQL($clearing).'" AND l.business_date<="'.pSQL($last).'"'), 2);
        $idJ = null;
        if (abs($bal) > 0.009) {
            $idJ = PulseAccJournal::post(array(
                'type' => 'closing', 'source' => 'closing', 'source_ref' => 'year:'.$year, 'business_date' => $last, 'memo' => 'Year-end roll to retained earnings '.$year, 'reference' => (string) $year, 'allow_closed' => true,
                'lines' => array(
                    array('account' => $clearing, 'debit' => $bal < 0 ? abs($bal) : 0, 'credit' => $bal > 0 ? $bal : 0, 'memo' => 'Clear current year result'),
                    array('account' => $retained, 'debit' => $bal > 0 ? $bal : 0, 'credit' => $bal < 0 ? abs($bal) : 0, 'memo' => 'Retained earnings '.$year),
                ),
            ));
        }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_acc_period` SET `status`="locked" WHERE `year`='.$year);
        PulseCoreService::audit('pulseaccounts', 'year_end', array('year' => $year, 'result' => -$bal, 'journal' => $idJ));
        return array('year' => $year, 'result' => round(-$bal, 2), 'id_journal' => $idJ);
    }

    /* ---------------- bank accounts ---------------- */

    public static function bankAccounts($activeOnly = true) { return Db::getInstance()->executeS('SELECT b.*, a.name gl_name FROM `'._DB_PREFIX_.'pulse_acc_bank_account` b LEFT JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.code=b.account_code WHERE 1'.($activeOnly ? ' AND b.active=1' : '').' ORDER BY b.sort, b.code'); }
    public static function bankAccount($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_bank_account` WHERE id_pulse_acc_bank_account='.(int) $id); }
    public static function bankByAccountCode($code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_bank_account` WHERE account_code="'.pSQL($code).'" AND active=1 ORDER BY sort LIMIT 1'); }

    /** Create the default naira bank rows on install if the property has none beyond the seeded cash boxes. */
    public static function ensureBankAccounts()
    {
        foreach (array(
            array('code' => 'ZEN-CUR', 'name' => 'Zenith Bank — current', 'bank_name' => 'Zenith Bank', 'account_code' => '1120', 'sort' => 4),
            array('code' => 'GTB-CUR', 'name' => 'GTBank — current', 'bank_name' => 'Guaranty Trust Bank', 'account_code' => '1121', 'sort' => 5),
        ) as $b) {
            if (Db::getInstance()->getValue('SELECT id_pulse_acc_bank_account FROM `'._DB_PREFIX_.'pulse_acc_bank_account` WHERE code="'.pSQL($b['code']).'"')) { continue; }
            Db::getInstance()->insert('pulse_acc_bank_account', array('code' => pSQL($b['code']), 'name' => pSQL($b['name']), 'type' => 'bank', 'bank_name' => pSQL($b['bank_name']), 'currency' => pSQL(self::currency()), 'account_code' => pSQL($b['account_code']), 'sort' => (int) $b['sort']), true);
        }
        return true;
    }

    public static function saveBankAccount(array $d)
    {
        if (empty($d['code']) || empty($d['name'])) { throw new PrestaShopException('Bank account needs a code and a name'); }
        if (empty($d['account_code']) || !self::account($d['account_code'])) { throw new PrestaShopException('Pick the GL account this bank posts to'); }
        $row = array(
            'code' => pSQL(Tools::substr($d['code'], 0, 16)), 'name' => pSQL(Tools::substr($d['name'], 0, 96)), 'type' => pSQL(isset($d['type']) ? $d['type'] : 'bank'),
            'bank_name' => pSQL(isset($d['bank_name']) ? Tools::substr($d['bank_name'], 0, 96) : ''), 'account_no' => pSQL(isset($d['account_no']) ? Tools::substr($d['account_no'], 0, 32) : ''),
            'account_name' => pSQL(isset($d['account_name']) ? Tools::substr($d['account_name'], 0, 128) : ''), 'branch' => pSQL(isset($d['branch']) ? Tools::substr($d['branch'], 0, 96) : ''),
            'currency' => pSQL(isset($d['currency']) ? Tools::substr($d['currency'], 0, 3) : self::currency()), 'account_code' => pSQL($d['account_code']),
            'opening_balance' => round((float) (isset($d['opening_balance']) ? $d['opening_balance'] : 0), 2), 'opening_date' => !empty($d['opening_date']) ? pSQL($d['opening_date']) : null,
            'imprest_float' => round((float) (isset($d['imprest_float']) ? $d['imprest_float'] : 0), 2), 'active' => isset($d['active']) ? (int) (bool) $d['active'] : 1,
            'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0), 'note' => pSQL(isset($d['note']) ? Tools::substr($d['note'], 0, 255) : ''),
        );
        if (!empty($d['id_pulse_acc_bank_account'])) { Db::getInstance()->update('pulse_acc_bank_account', $row, 'id_pulse_acc_bank_account='.(int) $d['id_pulse_acc_bank_account'], 0, true); return (int) $d['id_pulse_acc_bank_account']; }
        Db::getInstance()->insert('pulse_acc_bank_account', $row, true);
        return (int) Db::getInstance()->Insert_ID();
    }

    /* ---------------- dashboard ---------------- */

    /** One indexed query per tile — this screen loads on every page of the accounts section. */
    public static function dashboard($date = null)
    {
        $date = $date ? $date : self::bd();
        $period = self::period($date);
        $q = Db::getInstance()->getRow('SELECT SUM(status="pending") pending, SUM(status="failed") failed, MIN(IF(status="pending",business_date,NULL)) oldest FROM `'._DB_PREFIX_.'pulse_acc_queue`');
        $cash = Db::getInstance()->executeS('SELECT b.id_pulse_acc_bank_account, b.code, b.name, b.type, b.currency, b.account_code, ROUND(b.opening_balance + COALESCE((SELECT SUM(l.debit-l.credit) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l WHERE l.posted=1 AND l.account_code=b.account_code),0),2) balance FROM `'._DB_PREFIX_.'pulse_acc_bank_account` b WHERE b.active=1 ORDER BY b.sort, b.code');
        $today = Db::getInstance()->getRow('SELECT ROUND(SUM(IF(a.type="revenue",l.credit-l.debit,0)),2) revenue, ROUND(SUM(IF(a.subtype="cash",l.debit-l.credit,0)),2) cash_movement FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.business_date="'.pSQL($date).'"');
        $mtd = Db::getInstance()->getRow('SELECT ROUND(SUM(IF(a.type="revenue",l.credit-l.debit,0)),2) revenue, ROUND(SUM(IF(a.type="expense",l.debit-l.credit,0)),2) expense FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.period="'.pSQL($period).'"');
        return array(
            'business_date' => $date, 'period' => $period, 'period_row' => self::periodRow($period),
            'queue_pending' => (int) $q['pending'], 'queue_failed' => (int) $q['failed'], 'queue_oldest' => $q['oldest'],
            'cash' => $cash, 'cash_total' => array_sum(array_map(function ($r) { return (float) $r['balance']; }, $cash)),
            'today' => $today, 'mtd' => $mtd, 'mtd_result' => round((float) $mtd['revenue'] - (float) $mtd['expense'], 2),
            'ar' => PulseAccAr::ageingSummary(), 'ap' => PulseAccAp::ageingSummary(),
            'unmapped' => count(self::unmapped()), 'periods' => self::periods(6),
            'guest_ledger' => self::controlBalance('guest_ledger'), 'city_ledger' => self::controlBalance('city_ledger'),
            'deposits' => self::controlBalance('deposit'), 'vat_due' => PulseAccTax::vatDue($period),
            'einvoice_queued' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_einvoice` WHERE status IN ("queued","failed")'),
            'unreconciled' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_bank_line` WHERE match_state="unmatched"'),
        );
    }

    /** Balance of every account flagged as a control account of the given kind (guest_ledger, ap, …). */
    public static function controlBalance($controlOf, $upTo = null)
    {
        return round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.debit-l.credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND a.control_of="'.pSQL($controlOf).'"'.($upTo ? ' AND l.business_date<="'.pSQL($upTo).'"' : '')), 2);
    }

    /** CSV body for any report table — same helper the rest of the suite uses. */
    public static function toCsv(array $rows)
    {
        if (!$rows) { return ''; }
        $f = fopen('php://temp', 'r+');
        fputcsv($f, array_keys($rows[0]));
        foreach ($rows as $r) { fputcsv($f, $r); }
        rewind($f); $s = stream_get_contents($f); fclose($f);
        return $s;
    }
}
