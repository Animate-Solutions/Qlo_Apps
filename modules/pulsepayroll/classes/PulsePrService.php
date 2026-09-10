<?php
/**
 * Shared helpers for Pulse Payroll: environment guards, the employee roster (from Pulse HR when it is
 * installed, from the local table otherwise), timesheets (from Pulse Time when it is installed, from a
 * manual entry screen otherwise), money rounding, proration, sequences and the module audit trail.
 * Every cross-module read in the module goes through this class so there is exactly one place to look.
 */
class PulsePrService
{
    protected static $countryCache = array();
    protected static $elementCache = array();
    protected static $staffCount = null;

    /* ---------------- environment ---------------- */

    public static function hr() { return Module::isEnabled('pulsehr') && self::tableExists('pulse_hr_employee'); }
    public static function ta() { return Module::isEnabled('pulsetime') && self::tableExists('pulse_ta_punch'); }
    public static function acc() { return Module::isEnabled('pulseaccounts') && class_exists('PulseAccPosting'); }
    public static function fd() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFolio'); }
    public static function pos() { return Module::isEnabled('pulsepos') && self::tableExists('pulse_pos_check'); }
    public static function comms() { return class_exists('PulseComms'); }
    public static function tableExists($t) { return (bool) Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.pSQL($t).'"'); }
    public static function bd() { return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    public static function emp() { $c = Context::getContext(); return isset($c->employee) && $c->employee ? (int) $c->employee->id : 0; }
    public static function cfg($k, $default = null) { $v = Configuration::get('PULSE_PR_'.$k); return ($v === false || $v === null || $v === '') ? $default : $v; }
    public static function country() { return self::cfg('COUNTRY', 'NG'); }
    public static function currency() { return self::cfg('CURRENCY', 'NGN'); }
    public static function periodsPerYear() { $n = (int) self::cfg('PERIODS_PER_YEAR', 12); return $n > 0 ? $n : 12; }

    /**
     * Headcount for the employer-size tests on a contribution row (pension at 3 staff, ITF at 5).
     * A configured figure wins. A zero means "nobody has told us yet" — and treating that as "fewer than
     * three employees" silently suppresses a mandatory employer contribution, so the live roster answers
     * instead. Cached: contributions() asks once per employee per run.
     */
    public static function employerStaffCount()
    {
        $n = (int) self::cfg('EMPLOYER_STAFF_COUNT', 0);
        if ($n > 0) { return $n; }
        if (self::$staffCount === null) { self::$staffCount = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE status<>"exited"'); }
        return self::$staffCount;
    }

    /** Money rounds at the element level, to the country's rounding rule, and never with a float compare. */
    public static function money($x, $country = null)
    {
        $dp = (int) self::cfg('ROUND_DP', 2);
        $c = $country ? self::countryRow($country) : null;
        if ($c) { $dp = (int) $c['rounding_dp']; if ($c['rounding'] === 'floor') { $m = pow(10, $dp); return floor((float) $x * $m) / $m; } if ($c['rounding'] === 'ceil') { $m = pow(10, $dp); return ceil((float) $x * $m) / $m; } }
        return round((float) $x, $dp);
    }

    public static function zero($x) { return abs((float) $x) < 0.005; }

    /** A numeric column that may be absent from a row (an older schema, a projection that did not select it). */
    public static function num($row, $key) { return isset($row[$key]) && $row[$key] !== null ? (float) $row[$key] : 0.0; }

    public static function nextNo($prefix, $width = 5)
    {
        $n = (int) PulseCoreService::setting('pulsepayroll', 'seq_'.$prefix) + 1;
        PulseCoreService::setting('pulsepayroll', 'seq_'.$prefix, $n);
        return $prefix.date('y').str_pad($n, $width, '0', STR_PAD_LEFT);
    }

    /** Module-local audit row; also mirrors to the suite audit trail so the whole hotel has one timeline. */
    public static function log($idRun, $event, $entity = 'run', $detail = null, $idEntity = null)
    {
        Db::getInstance()->insert('pulse_pr_audit', array(
            'id_pulse_pr_run' => $idRun ? (int) $idRun : null, 'entity' => pSQL($entity), 'id_entity' => $idEntity ? (int) $idEntity : null,
            'event' => pSQL(Tools::substr($event, 0, 48)), 'detail' => pSQL(is_string($detail) ? $detail : json_encode($detail), true),
            'id_employee' => self::emp(), 'ip' => pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 45)), 'date_add' => date('Y-m-d H:i:s'),
        ), true);
        if (class_exists('PulseCoreService')) { PulseCoreService::audit('pulsepayroll', $event, $detail, 'pulse_pr_'.$entity, $idEntity ? $idEntity : $idRun); }
        return true;
    }

    public static function auditTrail($idRun, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT a.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_pr_audit` a LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=a.id_employee WHERE a.id_pulse_pr_run='.(int) $idRun.' ORDER BY a.id_pulse_pr_audit DESC LIMIT '.(int) $limit);
    }

    /* ---------------- countries ---------------- */

    public static function countryRow($code)
    {
        $code = Tools::strtoupper(Tools::substr((string) $code, 0, 2));
        if (!isset(self::$countryCache[$code])) { self::$countryCache[$code] = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_country` WHERE code="'.pSQL($code).'"'); }
        return self::$countryCache[$code] ? self::$countryCache[$code] : null;
    }

    public static function countries($activeOnly = true) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_country` WHERE 1'.($activeOnly ? ' AND active=1' : '').' ORDER BY code'); }

    /* ---------------- pay elements ---------------- */

    public static function elements($activeOnly = true, $type = null)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_element` WHERE 1'.($activeOnly ? ' AND active=1' : '').($type ? ' AND type="'.pSQL($type).'"' : '').' ORDER BY sequence, code');
    }

    /** Element rows are read once per element per request — the calculator asks for them in a tight loop. */
    public static function element($code)
    {
        $code = Tools::strtoupper((string) $code);
        if (!isset(self::$elementCache[$code])) { self::$elementCache[$code] = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_element` WHERE code="'.pSQL($code).'"'); }
        return self::$elementCache[$code] ? self::$elementCache[$code] : null;
    }

    public static function saveElement(array $d)
    {
        $code = Tools::strtoupper(trim(Tools::substr((string) (isset($d['code']) ? $d['code'] : ''), 0, 24)));
        if ($code === '' || !preg_match('/^[A-Z0-9_]+$/', $code)) { throw new PrestaShopException('Element code must be short, upper-case and alphanumeric'); }
        if (empty($d['name'])) { throw new PrestaShopException('Element name is required'); }
        $types = array('earning', 'deduction', 'employer', 'information');
        $calcs = array('fixed', 'percent', 'rate_units', 'formula', 'statutory');
        $row = array(
            'code' => pSQL($code), 'name' => pSQL(Tools::substr($d['name'], 0, 96)),
            'type' => pSQL(in_array(isset($d['type']) ? $d['type'] : '', $types) ? $d['type'] : 'earning'),
            'calc' => pSQL(in_array(isset($d['calc']) ? $d['calc'] : '', $calcs) ? $d['calc'] : 'fixed'),
            'percent_of' => pSQL(Tools::strtoupper(Tools::substr(isset($d['percent_of']) ? $d['percent_of'] : '', 0, 24))),
            'default_value' => (float) (isset($d['default_value']) ? $d['default_value'] : 0),
            'formula' => pSQL(Tools::substr(isset($d['formula']) ? $d['formula'] : '', 0, 255)),
            'statutory_code' => pSQL(Tools::strtoupper(Tools::substr(isset($d['statutory_code']) ? $d['statutory_code'] : '', 0, 24))),
            'taxable' => !empty($d['taxable']) ? 1 : 0, 'pensionable' => !empty($d['pensionable']) ? 1 : 0, 'nsitfable' => !empty($d['nsitfable']) ? 1 : 0,
            'in_basic' => !empty($d['in_basic']) ? 1 : 0, 'proratable' => !empty($d['proratable']) ? 1 : 0, 'recurring' => !empty($d['recurring']) ? 1 : 0,
            'gl_account' => pSQL(Tools::substr(isset($d['gl_account']) ? $d['gl_account'] : '', 0, 16)),
            'department' => pSQL(Tools::substr(isset($d['department']) ? $d['department'] : '', 0, 32)),
            'sequence' => (int) (isset($d['sequence']) ? $d['sequence'] : 100), 'show_on_payslip' => isset($d['show_on_payslip']) ? (int) (bool) $d['show_on_payslip'] : 1,
            'active' => isset($d['active']) ? (int) (bool) $d['active'] : 1, 'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 255)),
        );
        if ($row['calc'] === 'percent' && $row['percent_of'] === '') { throw new PrestaShopException('A percent element needs a named base (BASIC, BHT, GROSS, TAXABLE_GROSS or another element code)'); }
        self::$elementCache = array();
        $ex = (int) Db::getInstance()->getValue('SELECT id_pulse_pr_element FROM `'._DB_PREFIX_.'pulse_pr_element` WHERE code="'.pSQL($code).'"');
        if ($ex) { Db::getInstance()->update('pulse_pr_element', $row, 'id_pulse_pr_element='.$ex, 0, true); self::log(null, 'element_save', 'element', $row, $ex); return $ex; }
        Db::getInstance()->insert('pulse_pr_element', $row, true);
        $id = (int) Db::getInstance()->Insert_ID();
        self::log(null, 'element_save', 'element', $row, $id);
        return $id;
    }

    /* ---------------- employees ---------------- */

    /**
     * The payroll roster. Pulse HR owns the person; payroll keeps a mirror row carrying the things only
     * payroll needs (RSA PIN, bank details, tax state, payslip PIN). When HR is absent the mirror row is
     * the master and the Payroll Employees screen edits it directly.
     */
    public static function employees(array $f = array())
    {
        $w = array('1');
        if (!empty($f['department'])) { $w[] = 'e.department="'.pSQL($f['department']).'"'; }
        if (!empty($f['status'])) { $w[] = 'e.status IN ("'.implode('","', array_map('pSQL', explode(',', $f['status']))).'")'; }
        if (!empty($f['employment_type'])) { $w[] = 'e.employment_type IN ("'.implode('","', array_map('pSQL', explode(',', $f['employment_type']))).'")'; }
        if (!empty($f['country'])) { $w[] = 'e.country="'.pSQL($f['country']).'"'; }
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w[] = '(e.staff_no LIKE "%'.$q.'%" OR e.firstname LIKE "%'.$q.'%" OR e.lastname LIKE "%'.$q.'%")'; }
        if (!empty($f['ids'])) { $w[] = 'e.id_pulse_pr_employee IN ('.implode(',', array_map('intval', (array) $f['ids'])).')'; }
        return Db::getInstance()->executeS('SELECT e.* FROM `'._DB_PREFIX_.'pulse_pr_employee` e WHERE '.implode(' AND ', $w).' ORDER BY e.department, e.lastname, e.firstname');
    }

    public static function employee($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE id_pulse_pr_employee='.(int) $id); }
    public static function employeeByStaffNo($no) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE staff_no="'.pSQL($no).'"'); }
    public static function departments() { return Db::getInstance()->executeS('SELECT department, COUNT(*) n FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE status<>"exited" GROUP BY department ORDER BY department'); }

    public static function saveEmployee(array $d)
    {
        $id = (int) (isset($d['id_pulse_pr_employee']) ? $d['id_pulse_pr_employee'] : 0);
        // An update writes every column, so a partial save must inherit the ones it did not mention —
        // otherwise setting a payslip PIN (four fields) would reset the pay rate to zero, blank the bank
        // details and drop the Pulse HR link. Pass an empty string to clear a field deliberately.
        if ($id) {
            $cur = self::employee($id);
            if ($cur) { foreach ($cur as $k => $v) { if (!array_key_exists($k, $d) && $k !== 'payslip_pin') { $d[$k] = $v; } } }
        }
        if (empty($d['staff_no']) || empty($d['firstname']) || empty($d['lastname'])) { throw new PrestaShopException('Staff number, first name and last name are required'); }
        $row = array(
            'staff_no' => pSQL(Tools::substr($d['staff_no'], 0, 24)), 'firstname' => pSQL(Tools::substr($d['firstname'], 0, 64)), 'lastname' => pSQL(Tools::substr($d['lastname'], 0, 64)),
            'id_hr_employee' => !empty($d['id_hr_employee']) ? (int) $d['id_hr_employee'] : null, 'id_employee' => !empty($d['id_employee']) ? (int) $d['id_employee'] : null,
            'department' => pSQL(Tools::substr(!empty($d['department']) ? $d['department'] : 'general', 0, 32)), 'section' => pSQL(Tools::substr(isset($d['section']) ? $d['section'] : '', 0, 32)),
            'position' => pSQL(Tools::substr(isset($d['position']) ? $d['position'] : '', 0, 96)), 'grade' => pSQL(Tools::substr(isset($d['grade']) ? $d['grade'] : '', 0, 24)),
            'cost_centre' => pSQL(Tools::substr(isset($d['cost_centre']) ? $d['cost_centre'] : (isset($d['department']) ? $d['department'] : ''), 0, 32)),
            'employment_type' => pSQL(in_array(isset($d['employment_type']) ? $d['employment_type'] : '', array('permanent', 'fixed_term', 'contract', 'casual', 'service', 'intern')) ? $d['employment_type'] : 'permanent'),
            'pay_basis' => pSQL(in_array(isset($d['pay_basis']) ? $d['pay_basis'] : '', array('monthly', 'daily', 'hourly', 'per_shift')) ? $d['pay_basis'] : 'monthly'),
            'pay_rate' => (float) (isset($d['pay_rate']) ? $d['pay_rate'] : 0),
            'country' => pSQL(Tools::strtoupper(Tools::substr(isset($d['country']) ? $d['country'] : self::country(), 0, 2))),
            'currency' => pSQL(Tools::substr(isset($d['currency']) ? $d['currency'] : self::currency(), 0, 3)),
            'hire_date' => !empty($d['hire_date']) ? pSQL($d['hire_date']) : null, 'exit_date' => !empty($d['exit_date']) ? pSQL($d['exit_date']) : null,
            'status' => pSQL(in_array(isset($d['status']) ? $d['status'] : '', array('active', 'probation', 'suspended', 'on_leave', 'exited')) ? $d['status'] : 'active'),
            'tin' => pSQL(Tools::substr(isset($d['tin']) ? $d['tin'] : '', 0, 32)), 'tax_state' => pSQL(Tools::substr(!empty($d['tax_state']) ? $d['tax_state'] : 'Rivers', 0, 32)),
            'rsa_pin' => pSQL(Tools::substr(isset($d['rsa_pin']) ? $d['rsa_pin'] : '', 0, 32)), 'pfa' => pSQL(Tools::substr(isset($d['pfa']) ? $d['pfa'] : '', 0, 96)),
            'nhf_no' => pSQL(Tools::substr(isset($d['nhf_no']) ? $d['nhf_no'] : '', 0, 32)), 'nsitf_no' => pSQL(Tools::substr(isset($d['nsitf_no']) ? $d['nsitf_no'] : '', 0, 32)),
            'nin' => pSQL(Tools::substr(isset($d['nin']) ? $d['nin'] : '', 0, 32)),
            'bank_name' => pSQL(Tools::substr(isset($d['bank_name']) ? $d['bank_name'] : '', 0, 96)), 'bank_code' => pSQL(Tools::substr(isset($d['bank_code']) ? $d['bank_code'] : '', 0, 16)),
            'account_no' => pSQL(Tools::substr(isset($d['account_no']) ? $d['account_no'] : '', 0, 24)), 'account_name' => pSQL(Tools::substr(isset($d['account_name']) ? $d['account_name'] : trim($d['firstname'].' '.$d['lastname']), 0, 128)),
            'email' => pSQL(Tools::substr(isset($d['email']) ? $d['email'] : '', 0, 128)), 'phone' => pSQL(Tools::substr(isset($d['phone']) ? $d['phone'] : '', 0, 32)),
            'pay_method' => pSQL(in_array(isset($d['pay_method']) ? $d['pay_method'] : '', array('bank', 'cash', 'cheque')) ? $d['pay_method'] : 'bank'),
            'on_hold' => !empty($d['on_hold']) ? 1 : 0, 'hold_reason' => pSQL(Tools::substr(isset($d['hold_reason']) ? $d['hold_reason'] : '', 0, 160)),
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 255)), 'date_upd' => date('Y-m-d H:i:s'),
        );
        if ($row['pay_method'] === 'bank' && $row['account_no'] !== '' && !preg_match('/^[0-9]{8,20}$/', $row['account_no'])) { throw new PrestaShopException('A bank account number must be 8 to 20 digits'); }
        if (!empty($d['payslip_pin'])) { $row['payslip_pin'] = pSQL(self::hashPin($d['payslip_pin'])); }
        self::$staffCount = null;
        if ($id) { Db::getInstance()->update('pulse_pr_employee', $row, 'id_pulse_pr_employee='.$id, 0, true); }
        else {
            if (self::employeeByStaffNo($row['staff_no'])) { throw new PrestaShopException('Staff number '.$d['staff_no'].' already exists'); }
            $row['date_add'] = date('Y-m-d H:i:s');
            if (!isset($row['payslip_pin'])) { $row['payslip_pin'] = pSQL(self::hashPin(self::defaultPin($row['staff_no']))); }
            Db::getInstance()->insert('pulse_pr_employee', $row, true); $id = (int) Db::getInstance()->Insert_ID();
        }
        self::log(null, 'employee_save', 'employee', array('staff_no' => $row['staff_no']), $id);
        return $id;
    }

    /** Payslip PIN: hashed with the shop cookie key exactly as PulsePosService::login does for POS PINs. */
    public static function hashPin($pin) { return md5(_COOKIE_KEY_.'pulsepr'.$pin); }
    /** Compared with hash_equals: a PIN check that leaks its answer through timing is not a check. */
    public static function checkPin($pin, $hash) { return $hash !== '' && (function_exists('hash_equals') ? hash_equals((string) $hash, self::hashPin($pin)) : self::hashPin($pin) === $hash); }
    /** Default PIN until the employee sets their own: the last four characters of the staff number. */
    public static function defaultPin($staffNo) { return Tools::substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $staffNo), -4); }

    /**
     * Shared PIN throttle for every route that gates pay data on a PIN — the tokenised payslip page and the
     * ESS API alike. Five wrong attempts against one key from one address in fifteen minutes and it stops
     * answering. Anything that reads a payslip must go through this; a four-digit PIN with no throttle is a
     * four-digit PIN with no protection.
     */
    public static function pinKey($subject) { return 'pr_pin_'.md5((string) $subject.'|'.Tools::getRemoteAddr()); }

    public static function pinThrottled($subject)
    {
        $d = json_decode((string) PulseCoreService::setting('pulsepayroll', self::pinKey($subject)), true);
        if (!is_array($d) || !isset($d['n'], $d['t']) || (int) $d['t'] < time() - 900) { return false; }
        return (int) $d['n'] >= 5;
    }

    public static function pinAttempt($subject)
    {
        $d = json_decode((string) PulseCoreService::setting('pulsepayroll', self::pinKey($subject)), true);
        $n = (is_array($d) && isset($d['t'], $d['n']) && (int) $d['t'] > time() - 900) ? (int) $d['n'] + 1 : 1;
        return PulseCoreService::setting('pulsepayroll', self::pinKey($subject), json_encode(array('n' => $n, 't' => time())));
    }

    public static function pinClear($subject) { return PulseCoreService::setting('pulsepayroll', self::pinKey($subject), json_encode(array('n' => 0, 't' => time()))); }

    /**
     * Mirror one Pulse HR employee into the payroll roster, reading the contract effective on the date
     * rather than the current row. Degrades silently when Pulse HR is not installed.
     */
    public static function syncFromHr($idHrEmployee, $onDate = null)
    {
        if (!self::hr()) { return false; }
        $onDate = $onDate ? $onDate : self::bd();
        $h = (class_exists('PulseHrEmployee') && method_exists('PulseHrEmployee', 'get')) ? PulseHrEmployee::get((int) $idHrEmployee) : null;
        if (!$h) { $h = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE id_pulse_hr_employee='.(int) $idHrEmployee); }
        if (!$h) { return false; }
        $c = self::hrContract((int) $idHrEmployee, $onDate);
        // what HR actually knows. Department, section, position and grade live behind id_* joins in HR, so
        // accept either the flat column or the joined label; the contract in force wins where it carries one.
        $hr = array(
            'id_hr_employee' => (int) $idHrEmployee,
            'staff_no' => self::pick($h, array('staff_no'), 'HR'.(int) $idHrEmployee),
            'firstname' => self::pick($h, array('firstname')), 'lastname' => self::pick($h, array('lastname')),
            'department' => self::pick($h, array('department', 'dept_code')), 'section' => self::pick($h, array('section', 'section_name')),
            'position' => self::pick($h, array('position', 'position_title')), 'grade' => self::pick($h, array('grade', 'grade_code')),
            'cost_centre' => self::pick($h, array('cost_centre', 'department', 'dept_code')),
            'hire_date' => self::pick($h, array('hire_date')), 'exit_date' => self::pick($h, array('exit_date')),
            'status' => self::pick($h, array('status'), 'active'),
            'tin' => self::pick($h, array('tin')), 'rsa_pin' => self::pick($h, array('rsa_pin')), 'pfa' => self::pick($h, array('pfa')),
            'nhf_no' => self::pick($h, array('nhf_no')), 'nin' => self::pick($h, array('nin', 'national_id')),
            'bank_name' => self::pick($h, array('bank_name')), 'bank_code' => self::pick($h, array('bank_code')),
            'account_no' => self::pick($h, array('account_no')), 'account_name' => self::pick($h, array('account_name')),
            'email' => self::pick($h, array('email')), 'phone' => self::pick($h, array('phone')),
            'id_employee' => self::pick($h, array('id_employee')),
        );
        if ($c) {
            foreach (array('type' => 'employment_type', 'pay_basis' => 'pay_basis', 'pay_rate' => 'pay_rate', 'currency' => 'currency',
                'cost_centre' => 'cost_centre', 'dept_code' => 'department', 'section_name' => 'section', 'position_title' => 'position', 'grade_code' => 'grade') as $k => $to) {
                if (isset($c[$k]) && $c[$k] !== null && $c[$k] !== '') { $hr[$to] = $c[$k]; }
            }
        }
        $ex = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE id_hr_employee='.(int) $idHrEmployee);
        // start from what payroll already holds so a sync never blanks a payroll-only field (tax state, NSITF
        // number, bank code, hold flag) and — the expensive one — never resets the pay rate to zero because no
        // contract version was in force on the date. HR overlays only what it actually knows.
        $d = array();
        if ($ex) { $d = $ex; unset($d['payslip_pin'], $d['date_add'], $d['date_upd']); $d['staff_no'] = $ex['staff_no']; }
        foreach ($hr as $k => $v) { if ($v !== null && $v !== '') { $d[$k] = $v; } }
        if ($ex) { $d['id_pulse_pr_employee'] = (int) $ex['id_pulse_pr_employee']; $d['staff_no'] = $ex['staff_no']; }
        return self::saveEmployee($d);
    }

    /** First non-empty of a list of candidate column names on a foreign row, else the default. */
    protected static function pick(array $row, array $names, $default = '')
    {
        foreach ($names as $n) { if (isset($row[$n]) && $row[$n] !== null && $row[$n] !== '') { return $row[$n]; } }
        return $default;
    }

    /**
     * The Pulse HR contract effective on a date. Payroll must never read the current contract row —
     * a mid-year promotion has to be picked up as it stood in the period being paid.
     */
    public static function hrContract($idHrEmployee, $onDate)
    {
        if (!self::hr() || !self::tableExists('pulse_hr_contract')) { return null; }
        if (class_exists('PulseHrContract') && method_exists('PulseHrContract', 'onDate')) {
            $c = PulseHrContract::onDate((int) $idHrEmployee, $onDate);
            if ($c) { return $c; }
        }
        if (class_exists('PulseHrService') && method_exists('PulseHrService', 'contractOn')) {
            $c = PulseHrService::contractOn((int) $idHrEmployee, $onDate);
            if ($c) { return $c; }
        }
        // Pulse HR dates its contract versions effective_from/effective_to; probe rather than assume, so a
        // column name that differs degrades to "no contract" instead of a failed query
        $cols = self::columns('pulse_hr_contract');
        $from = in_array('effective_from', $cols) ? 'effective_from' : (in_array('date_start', $cols) ? 'date_start' : null);
        $to = in_array('effective_to', $cols) ? 'effective_to' : (in_array('date_end', $cols) ? 'date_end' : null);
        if (!$from || !$to) { return null; }
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_contract` WHERE id_pulse_hr_employee='.(int) $idHrEmployee.(in_array('status', $cols) ? ' AND status<>"draft"' : '').' AND `'.bqSQL($from).'`<="'.pSQL($onDate).'" AND (`'.bqSQL($to).'` IS NULL OR `'.bqSQL($to).'`>="'.pSQL($onDate).'") ORDER BY `'.bqSQL($from).'` DESC LIMIT 1');
    }

    /** Pull the whole HR roster into payroll. Returns the number of rows created or refreshed. */
    public static function syncAllFromHr($onDate = null)
    {
        if (!self::hr()) { return 0; }
        $n = 0;
        foreach (Db::getInstance()->executeS('SELECT id_pulse_hr_employee FROM `'._DB_PREFIX_.'pulse_hr_employee`') as $r) {
            try { if (self::syncFromHr((int) $r['id_pulse_hr_employee'], $onDate)) { $n++; } } catch (Exception $e) { self::log(null, 'hr_sync_fail', 'employee', $e->getMessage(), (int) $r['id_pulse_hr_employee']); }
        }
        return $n;
    }

    public static function markExit($idHrEmployee, $exitDate = null)
    {
        $exitDate = $exitDate ? $exitDate : self::bd();
        return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pr_employee` SET status="exited", exit_date="'.pSQL($exitDate).'", date_upd=NOW() WHERE id_hr_employee='.(int) $idHrEmployee);
    }

    /* ---------------- pay structures ---------------- */

    /** The elements assigned to an employee on a date, falling back to the grade default then to DEFAULT. */
    public static function structure($idEmployee, $onDate, $grade = '')
    {
        $rows = Db::getInstance()->executeS('SELECT s.*, e.name, e.type, e.calc, e.percent_of, e.taxable, e.pensionable, e.nsitfable, e.in_basic, e.proratable, e.recurring, e.gl_account, e.sequence, e.statutory_code, e.formula, e.default_value
            FROM `'._DB_PREFIX_.'pulse_pr_employee_element` s INNER JOIN `'._DB_PREFIX_.'pulse_pr_element` e ON e.code=s.element_code
            WHERE s.id_pulse_pr_employee='.(int) $idEmployee.' AND s.effective_from<="'.pSQL($onDate).'" AND (s.effective_to IS NULL OR s.effective_to>="'.pSQL($onDate).'") AND e.active=1 ORDER BY e.sequence, s.element_code');
        if ($rows) { return self::latestPerElement($rows); }
        foreach (array_filter(array($grade, 'DEFAULT')) as $g) {
            $rows = Db::getInstance()->executeS('SELECT s.*, e.name, e.type, e.calc, e.percent_of, e.taxable, e.pensionable, e.nsitfable, e.in_basic, e.proratable, e.recurring, e.gl_account, e.sequence, e.statutory_code, e.formula, e.default_value
                FROM `'._DB_PREFIX_.'pulse_pr_employee_element` s INNER JOIN `'._DB_PREFIX_.'pulse_pr_element` e ON e.code=s.element_code
                WHERE s.id_pulse_pr_employee IS NULL AND s.grade="'.pSQL($g).'" AND s.effective_from<="'.pSQL($onDate).'" AND (s.effective_to IS NULL OR s.effective_to>="'.pSQL($onDate).'") AND e.active=1 ORDER BY e.sequence, s.element_code');
            if ($rows) { return self::latestPerElement($rows); }
        }
        return array();
    }

    /** Two rows for the same element with different effective dates: the later one wins. */
    protected static function latestPerElement(array $rows)
    {
        $out = array();
        foreach ($rows as $r) { $c = $r['element_code']; if (!isset($out[$c]) || $r['effective_from'] > $out[$c]['effective_from']) { $out[$c] = $r; } }
        return array_values($out);
    }

    public static function saveStructureLine(array $d)
    {
        if (empty($d['element_code'])) { throw new PrestaShopException('Pick a pay element'); }
        if (!self::element($d['element_code'])) { throw new PrestaShopException('Unknown pay element '.$d['element_code']); }
        $row = array(
            'id_pulse_pr_employee' => !empty($d['id_pulse_pr_employee']) ? (int) $d['id_pulse_pr_employee'] : null,
            'grade' => pSQL(Tools::substr(isset($d['grade']) ? $d['grade'] : '', 0, 24)), 'element_code' => pSQL(Tools::strtoupper($d['element_code'])),
            'amount' => (float) (isset($d['amount']) ? $d['amount'] : 0), 'percent' => (float) (isset($d['percent']) ? $d['percent'] : 0), 'units' => (float) (isset($d['units']) ? $d['units'] : 0),
            'effective_from' => pSQL(!empty($d['effective_from']) ? $d['effective_from'] : date('Y-m-01')),
            'effective_to' => !empty($d['effective_to']) ? pSQL($d['effective_to']) : null,
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 160)), 'date_add' => date('Y-m-d H:i:s'),
        );
        if ($row['id_pulse_pr_employee'] === null && $row['grade'] === '') { throw new PrestaShopException('A structure line needs either an employee or a grade'); }
        Db::getInstance()->insert('pulse_pr_employee_element', $row, true);
        $id = (int) Db::getInstance()->Insert_ID();
        self::log(null, 'structure_save', 'structure', $row, $id);
        return $id;
    }

    public static function deleteStructureLine($id)
    {
        Db::getInstance()->delete('pulse_pr_employee_element', 'id_pulse_pr_employee_element='.(int) $id);
        self::log(null, 'structure_delete', 'structure', null, (int) $id);
        return true;
    }

    /* ---------------- declarations, consents and evidence ---------------- */

    /** Every declaration in force for an employee on a date, keyed by code. */
    public static function declarations($idEmployee, $onDate)
    {
        $out = array();
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_declaration` WHERE id_pulse_pr_employee='.(int) $idEmployee.' AND date_from<="'.pSQL($onDate).'" AND (date_to IS NULL OR date_to>="'.pSQL($onDate).'") ORDER BY date_from') as $r) { $out[$r['code']] = $r; }
        return $out;
    }

    public static function saveDeclaration(array $d)
    {
        if (empty($d['id_pulse_pr_employee']) || empty($d['code'])) { throw new PrestaShopException('A declaration needs an employee and a code'); }
        $code = Tools::strtoupper(Tools::substr($d['code'], 0, 24));
        $consented = !empty($d['consented']) ? 1 : 0;
        $consentDate = !empty($d['consent_date']) ? $d['consent_date'] : ($consented ? date('Y-m-d') : null);
        if ($consented && !$consentDate) { throw new PrestaShopException('A consent must carry the date it was given'); }
        $row = array(
            'id_pulse_pr_employee' => (int) $d['id_pulse_pr_employee'], 'code' => pSQL($code),
            'annual_value' => (float) (isset($d['annual_value']) ? $d['annual_value'] : 0), 'percent' => (float) (isset($d['percent']) ? $d['percent'] : 0),
            'evidence_ref' => pSQL(Tools::substr(isset($d['evidence_ref']) ? $d['evidence_ref'] : '', 0, 160)),
            'evidence_verified' => !empty($d['evidence_verified']) ? 1 : 0,
            'consented' => $consented, 'consent_date' => $consentDate ? pSQL($consentDate) : null,
            'consent_channel' => pSQL(Tools::substr(isset($d['consent_channel']) ? $d['consent_channel'] : 'form', 0, 32)),
            'date_from' => pSQL(!empty($d['date_from']) ? $d['date_from'] : date('Y-01-01')), 'date_to' => !empty($d['date_to']) ? pSQL($d['date_to']) : null,
            'id_employee' => self::emp(), 'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 255)),
            'date_upd' => date('Y-m-d H:i:s'),
        );
        $id = (int) (isset($d['id_pulse_pr_declaration']) ? $d['id_pulse_pr_declaration'] : 0);
        if ($id) { Db::getInstance()->update('pulse_pr_declaration', $row, 'id_pulse_pr_declaration='.$id, 0, true); }
        else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_pr_declaration', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        self::log(null, 'declaration_save', 'declaration', $row, $id);
        return $id;
    }

    /** End a declaration or withdraw a consent — never delete it, the payslip history has to stay explicable. */
    public static function endDeclaration($id, $dateTo = null)
    {
        Db::getInstance()->update('pulse_pr_declaration', array('date_to' => pSQL($dateTo ? $dateTo : date('Y-m-d')), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_declaration='.(int) $id);
        self::log(null, 'declaration_end', 'declaration', array('date_to' => $dateTo), (int) $id);
        return true;
    }

    /* ---------------- timesheets ---------------- */

    /**
     * The approved timesheet for an employee and period. Prefers Pulse Time's approved timesheet, falls
     * back to the local manual row. An unapproved Pulse Time timesheet is never used — payroll only ever
     * pays from something a supervisor has signed.
     */
    public static function timesheet($idEmployee, $period, $idHrEmployee = null)
    {
        $local = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_timesheet` WHERE id_pulse_pr_employee='.(int) $idEmployee.' AND period="'.pSQL($period).'"');
        if ($local && (int) $local['approved']) { return $local; }
        $remote = self::timesheetFromTa($idHrEmployee, $period);
        if ($remote) { return $remote; }
        return $local;
    }

    /** Read Pulse Time's approved timesheet for the period. Column names are probed so a schema drift degrades instead of fataling. */
    public static function timesheetFromTa($idHrEmployee, $period)
    {
        if (!$idHrEmployee || !self::ta() || !self::tableExists('pulse_ta_timesheet')) { return null; }
        if (class_exists('PulseTaService') && method_exists('PulseTaService', 'timesheet')) {
            $t = PulseTaService::timesheet((int) $idHrEmployee, $period);
            if (is_array($t) && !empty($t['approved'])) { return self::normaliseTimesheet($t); }
            return null;
        }
        $cols = self::columns('pulse_ta_timesheet');
        if (!in_array('period', $cols) || !in_array('approved', $cols)) { return null; }
        $key = in_array('id_pulse_hr_employee', $cols) ? 'id_pulse_hr_employee' : (in_array('id_employee', $cols) ? 'id_employee' : null);
        if (!$key) { return null; }
        $t = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_timesheet` WHERE `'.bqSQL($key).'`='.(int) $idHrEmployee.' AND `period`="'.pSQL($period).'" AND `approved`=1');
        return $t ? self::normaliseTimesheet($t) : null;
    }

    /** Fill in every field the calculator expects, whatever the source called them. */
    public static function normaliseTimesheet(array $t)
    {
        $map = array('days_worked' => array('days_worked', 'days'), 'hours_worked' => array('hours_worked', 'hours'), 'shifts' => array('shifts', 'shift_count'),
            'ot_hours' => array('ot_hours', 'overtime_hours'), 'ot_rest_hours' => array('ot_rest_hours', 'rest_day_hours'), 'ot_holiday_hours' => array('ot_holiday_hours', 'holiday_hours'),
            'night_shifts' => array('night_shifts', 'nights'), 'unpaid_days' => array('unpaid_days', 'lwop_days'), 'absent_days' => array('absent_days', 'absent'));
        $out = array('source' => 'pulsetime', 'approved' => 1);
        foreach ($map as $k => $names) { $out[$k] = 0; foreach ($names as $n) { if (isset($t[$n])) { $out[$k] = (float) $t[$n]; break; } } }
        return $out;
    }

    public static function columns($table)
    {
        $out = array();
        foreach ((array) Db::getInstance()->executeS('SHOW COLUMNS FROM `'._DB_PREFIX_.bqSQL($table).'`') as $c) { $out[] = $c['Field']; }
        return $out;
    }

    public static function saveTimesheet(array $d)
    {
        if (empty($d['id_pulse_pr_employee']) || empty($d['period'])) { throw new PrestaShopException('A timesheet needs an employee and a period'); }
        $row = array(
            'id_pulse_pr_employee' => (int) $d['id_pulse_pr_employee'], 'period' => pSQL(Tools::substr($d['period'], 0, 10)), 'source' => 'manual',
            'days_worked' => (float) (isset($d['days_worked']) ? $d['days_worked'] : 0), 'hours_worked' => (float) (isset($d['hours_worked']) ? $d['hours_worked'] : 0),
            'shifts' => (float) (isset($d['shifts']) ? $d['shifts'] : 0), 'ot_hours' => (float) (isset($d['ot_hours']) ? $d['ot_hours'] : 0),
            'ot_rest_hours' => (float) (isset($d['ot_rest_hours']) ? $d['ot_rest_hours'] : 0), 'ot_holiday_hours' => (float) (isset($d['ot_holiday_hours']) ? $d['ot_holiday_hours'] : 0),
            'night_shifts' => (float) (isset($d['night_shifts']) ? $d['night_shifts'] : 0), 'unpaid_days' => (float) (isset($d['unpaid_days']) ? $d['unpaid_days'] : 0),
            'absent_days' => (float) (isset($d['absent_days']) ? $d['absent_days'] : 0),
            'approved' => !empty($d['approved']) ? 1 : 0, 'approved_by' => !empty($d['approved']) ? self::emp() : null,
            'date_approved' => !empty($d['approved']) ? date('Y-m-d H:i:s') : null,
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 160)), 'date_upd' => date('Y-m-d H:i:s'),
        );
        $ex = (int) Db::getInstance()->getValue('SELECT id_pulse_pr_timesheet FROM `'._DB_PREFIX_.'pulse_pr_timesheet` WHERE id_pulse_pr_employee='.(int) $d['id_pulse_pr_employee'].' AND period="'.pSQL($d['period']).'"');
        if ($ex) { Db::getInstance()->update('pulse_pr_timesheet', $row, 'id_pulse_pr_timesheet='.$ex, 0, true); return $ex; }
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_pr_timesheet', $row, true);
        return (int) Db::getInstance()->Insert_ID();
    }

    /* ---------------- proration ---------------- */

    /**
     * Days actually payable in a period for one employee, on the configured basis.
     * calendar = days in the month · working = the configured working days · thirtieths = always 30.
     */
    public static function proration(array $emp, $from, $to, array $timesheet = null)
    {
        $basis = self::cfg('PRORATION', 'calendar');
        $start = strtotime($from); $end = strtotime($to);
        $calendarDays = (int) round(($end - $start) / 86400) + 1;
        $denominator = $basis === 'thirtieths' ? 30 : ($basis === 'working' ? (float) self::cfg('WORKING_DAYS', 26) : $calendarDays);
        $inFrom = $start; $inTo = $end;
        if (!empty($emp['hire_date']) && strtotime($emp['hire_date']) > $inFrom) { $inFrom = strtotime($emp['hire_date']); }
        if (!empty($emp['exit_date']) && strtotime($emp['exit_date']) < $inTo) { $inTo = strtotime($emp['exit_date']); }
        if ($inTo < $inFrom) { return array('days' => 0, 'days_in_period' => $denominator, 'factor' => 0, 'unpaid_days' => 0, 'joiner' => true, 'leaver' => true); }
        $servedDays = (int) round(($inTo - $inFrom) / 86400) + 1;
        $days = $basis === 'calendar' ? $servedDays : round($denominator * $servedDays / $calendarDays, 3);
        $unpaid = $timesheet ? (float) (isset($timesheet['unpaid_days']) ? $timesheet['unpaid_days'] : 0) : 0;
        $days = max(0, $days - $unpaid);
        // the factor is deliberately NOT rounded: rounding it to six places before it multiplies a salary
        // is what puts 59,999.94 on a payslip that should read 60,000.00
        $factor = $denominator > 0 ? $days / $denominator : 0;
        if ($factor > 1) { $factor = 1; $days = $denominator; }
        return array(
            'days' => round($days, 3), 'days_in_period' => round($denominator, 3), 'factor' => $factor, 'unpaid_days' => $unpaid,
            'joiner' => !empty($emp['hire_date']) && strtotime($emp['hire_date']) > $start,
            'leaver' => !empty($emp['exit_date']) && strtotime($emp['exit_date']) <= $end,
        );
    }

    /* ---------------- period helpers ---------------- */

    public static function periodFrom($period) { return Tools::substr((string) $period, 0, 7).'-01'; }
    public static function periodTo($period) { return date('Y-m-t', strtotime(self::periodFrom($period))); }
    public static function taxYear($period, $country = 'NG')
    {
        $c = self::countryRow($country);
        $start = $c && $c['tax_year_start'] ? $c['tax_year_start'] : '01-01';
        $y = (int) Tools::substr((string) $period, 0, 4);
        $m = (int) Tools::substr((string) $period, 5, 2);
        $startMonth = (int) Tools::substr($start, 0, 2);
        return $m >= $startMonth ? $y : $y - 1;
    }

    /** 1-based index of a monthly period within its tax year, and how many periods remain including it. */
    public static function periodIndex($period, $country = 'NG')
    {
        $c = self::countryRow($country);
        $startMonth = (int) Tools::substr($c && $c['tax_year_start'] ? $c['tax_year_start'] : '01-01', 0, 2);
        $m = (int) Tools::substr((string) $period, 5, 2);
        $idx = (($m - $startMonth) + 12) % 12 + 1;
        $n = self::periodsPerYear();
        return array('index' => $idx, 'remaining' => max(1, $n - $idx + 1), 'periods' => $n);
    }

    /* ---------------- accruals raised by the night audit ---------------- */

    /** Keeps the ITF remittance row for the current month alive so the annual figure is never a surprise in April. */
    public static function accrueDaily($businessDate)
    {
        $period = Tools::substr((string) $businessDate, 0, 7);
        $itf = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(itf_er),0) FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run WHERE p.period LIKE "'.pSQL(Tools::substr($period, 0, 4)).'-%" AND r.status IN ("approved","paid","posted")'), 2);
        $year = (int) Tools::substr($period, 0, 4);
        if ($itf > 0) { self::upsertRemittance('itf', $year.'-12', 'Industrial Training Fund', $itf, ($year + 1).'-04-01'); }
        return true;
    }

    /** Create or refresh a statutory remittance row; a row already paid is never overwritten. */
    public static function upsertRemittance($scheme, $period, $authority, $amount, $dueDate)
    {
        $ex = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_remittance` WHERE scheme="'.pSQL($scheme).'" AND period="'.pSQL($period).'"');
        if ($ex && $ex['status'] === 'paid') { return (int) $ex['id_pulse_pr_remittance']; }
        $row = array('scheme' => pSQL($scheme), 'period' => pSQL($period), 'authority' => pSQL(Tools::substr($authority, 0, 96)), 'amount_due' => round((float) $amount, 2), 'due_date' => pSQL($dueDate), 'date_upd' => date('Y-m-d H:i:s'));
        if ($ex) {
            if ((float) $ex['amount_paid'] > 0 && (float) $ex['amount_paid'] < $row['amount_due']) { $row['status'] = 'part'; }
            Db::getInstance()->update('pulse_pr_remittance', $row, 'id_pulse_pr_remittance='.(int) $ex['id_pulse_pr_remittance'], 0, true);
            return (int) $ex['id_pulse_pr_remittance'];
        }
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_pr_remittance', $row, true);
        return (int) Db::getInstance()->Insert_ID();
    }

    public static function remittances($status = null, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_remittance` WHERE 1'.($status ? ' AND status="'.pSQL($status).'"' : '').' ORDER BY due_date DESC, scheme LIMIT '.(int) $limit);
    }

    public static function payRemittance($id, $amount, $date, $reference)
    {
        $r = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_remittance` WHERE id_pulse_pr_remittance='.(int) $id);
        if (!$r) { throw new PrestaShopException('Unknown remittance'); }
        $paid = round((float) $r['amount_paid'] + (float) $amount, 2);
        $status = $paid + 0.005 >= (float) $r['amount_due'] ? 'paid' : 'part';
        Db::getInstance()->update('pulse_pr_remittance', array('amount_paid' => $paid, 'date_paid' => pSQL($date), 'reference' => pSQL(Tools::substr($reference, 0, 96)), 'status' => $status, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_remittance='.(int) $id);
        self::log(null, 'remittance_pay', 'remittance', array('scheme' => $r['scheme'], 'amount' => $amount), (int) $id);
        return true;
    }

    /* ---------------- misc ---------------- */

    public static function banks($activeOnly = true) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_bank` WHERE 1'.($activeOnly ? ' AND active=1' : '').' ORDER BY sort, name'); }

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

    /** Occupied room-nights in a period, for cost-per-occupied-room. Zero when Front Desk is absent. */
    public static function occupiedRoomNights($from, $to)
    {
        if (!self::fd() || !class_exists('HotelBookingDetail')) { return 0; }
        return (int) Db::getInstance()->getValue('SELECT COALESCE(SUM(DATEDIFF(LEAST(date_to,"'.pSQL($to).'"), GREATEST(date_from,"'.pSQL($from).'"))),0) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_cancelled=0 AND is_refunded=0 AND date_from<="'.pSQL($to).'" AND date_to>="'.pSQL($from).'"');
    }
}
