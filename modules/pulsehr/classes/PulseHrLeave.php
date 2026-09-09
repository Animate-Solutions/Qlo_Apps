<?php
/**
 * Leave: types with accrual rules, per-grade entitlements, balances, requests with an approval chain,
 * a departmental calendar, blackout periods that stop a full house being run by nobody, encashment,
 * and the leave liability finance has to carry.
 *
 * A balance is never edited by hand from two places: every movement goes through this class.
 */
class PulseHrLeave
{
    const T = 'pulse_hr_leave_request';

    /* ---------- types and entitlements ---------- */
    public static function types($activeOnly = true) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_leave_type`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY sort, name'); }
    public static function type($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_leave_type` WHERE id_pulse_hr_leave_type='.(int) $id); }
    public static function typeByCode($code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_leave_type` WHERE code="'.pSQL($code).'"'); }

    public static function saveType(array $d, $id = 0)
    {
        $row = array('code' => pSQL(Tools::strtoupper($d['code'])), 'name' => pSQL($d['name']), 'paid' => !empty($d['paid']) ? 1 : 0,
            'accrual' => pSQL(in_array(isset($d['accrual']) ? $d['accrual'] : '', array('none', 'monthly', 'annual', 'on_event')) ? $d['accrual'] : 'none'),
            'days_per_year' => (float) (isset($d['days_per_year']) ? $d['days_per_year'] : 0), 'carry_over_cap' => (float) (isset($d['carry_over_cap']) ? $d['carry_over_cap'] : 0),
            'max_consecutive' => (int) (isset($d['max_consecutive']) ? $d['max_consecutive'] : 0), 'min_service_months' => (int) (isset($d['min_service_months']) ? $d['min_service_months'] : 0),
            'gender' => pSQL(in_array(isset($d['gender']) ? $d['gender'] : '', array('any', 'm', 'f')) ? $d['gender'] : 'any'),
            'working_days_only' => !empty($d['working_days_only']) ? 1 : 0, 'requires_document' => !empty($d['requires_document']) ? 1 : 0, 'encashable' => !empty($d['encashable']) ? 1 : 0,
            'colour' => pSQL(isset($d['colour']) ? $d['colour'] : '#2e86c1'), 'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0), 'active' => isset($d['active']) ? (int) $d['active'] : 1);
        if (!$row['code'] || !$row['name']) { throw new PrestaShopException('A leave type needs a code and a name'); }
        if ($id) { Db::getInstance()->update('pulse_hr_leave_type', $row, 'id_pulse_hr_leave_type='.(int) $id, 0, true); } else { Db::getInstance()->insert('pulse_hr_leave_type', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    public static function entitlements() { return Db::getInstance()->executeS('SELECT en.*, t.code type_code, t.name type_name, g.code grade_code, g.name grade_name FROM `'._DB_PREFIX_.'pulse_hr_leave_entitlement` en INNER JOIN `'._DB_PREFIX_.'pulse_hr_leave_type` t ON t.id_pulse_hr_leave_type=en.id_pulse_hr_leave_type INNER JOIN `'._DB_PREFIX_.'pulse_hr_grade` g ON g.id_pulse_hr_grade=en.id_pulse_hr_grade ORDER BY t.sort, g.level'); }
    public static function saveEntitlement($idType, $idGrade, $days)
    {
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_hr_leave_entitlement` (id_pulse_hr_leave_type,id_pulse_hr_grade,days) VALUES ('.(int) $idType.','.(int) $idGrade.','.(float) $days.') ON DUPLICATE KEY UPDATE days=VALUES(days)');
        return true;
    }

    /** Days a person is entitled to for a type this year: the grade override, then the grade's annual figure for ANN, then the type default. */
    public static function entitlementFor($idEmployee, $idType, $year = null)
    {
        $e = PulseHrEmployee::get($idEmployee); $t = self::type($idType);
        if (!$e || !$t) { return 0; }
        if ($e['id_pulse_hr_grade']) {
            $d = Db::getInstance()->getValue('SELECT days FROM `'._DB_PREFIX_.'pulse_hr_leave_entitlement` WHERE id_pulse_hr_leave_type='.(int) $idType.' AND id_pulse_hr_grade='.(int) $e['id_pulse_hr_grade']);
            if ($d !== false && $d !== null) { return (float) $d; }
            if ($t['code'] === 'ANN') { $g = Db::getInstance()->getRow('SELECT annual_leave_days FROM `'._DB_PREFIX_.'pulse_hr_grade` WHERE id_pulse_hr_grade='.(int) $e['id_pulse_hr_grade']); if ($g) { return (float) $g['annual_leave_days']; } }
        }
        return (float) $t['days_per_year'];
    }

    /* ---------- balances ---------- */

    /** Make sure a balance row exists for every active type this year. Safe to call as often as you like. */
    public static function openBalances($idEmployee, $year = null)
    {
        $year = (int) ($year ? $year : date('Y'));
        // one probe for the whole year rather than one per type — this runs for every employee on the liability report
        $have = array();
        foreach (Db::getInstance()->executeS('SELECT id_pulse_hr_leave_type FROM `'._DB_PREFIX_.'pulse_hr_leave_balance` WHERE id_pulse_hr_employee='.(int) $idEmployee.' AND year='.$year) as $b) { $have[(int) $b['id_pulse_hr_leave_type']] = 1; }
        foreach (self::types() as $t) {
            if (isset($have[(int) $t['id_pulse_hr_leave_type']])) { continue; }
            $opening = $t['accrual'] === 'annual' ? self::entitlementFor($idEmployee, (int) $t['id_pulse_hr_leave_type'], $year) : 0;
            Db::getInstance()->insert('pulse_hr_leave_balance', array('id_pulse_hr_employee' => (int) $idEmployee, 'id_pulse_hr_leave_type' => (int) $t['id_pulse_hr_leave_type'],
                'year' => $year, 'opening' => $opening, 'date_upd' => date('Y-m-d H:i:s')), true, true, Db::INSERT_IGNORE);
        }
        return true;
    }

    public static function balances($idEmployee, $year = null)
    {
        $year = (int) ($year ? $year : date('Y'));
        self::openBalances($idEmployee, $year);
        $rows = Db::getInstance()->executeS('SELECT b.*, t.code, t.name, t.paid, t.encashable, t.colour, t.accrual FROM `'._DB_PREFIX_.'pulse_hr_leave_balance` b
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_leave_type` t ON t.id_pulse_hr_leave_type=b.id_pulse_hr_leave_type
            WHERE b.id_pulse_hr_employee='.(int) $idEmployee.' AND b.year='.$year.' AND t.active=1 ORDER BY t.sort');
        foreach ($rows as &$r) {
            $r['entitlement'] = self::entitlementFor($idEmployee, (int) $r['id_pulse_hr_leave_type'], $year);
            $r['available'] = round((float) $r['opening'] + (float) $r['carried'] + (float) $r['accrued'] + (float) $r['adjustment'] - (float) $r['taken'] - (float) $r['pending'] - (float) $r['encashed'], 2);
        }
        unset($r);
        return $rows;
    }

    public static function balance($idEmployee, $idType, $year = null)
    {
        $year = (int) ($year ? $year : date('Y'));
        foreach (self::balances($idEmployee, $year) as $b) { if ((int) $b['id_pulse_hr_leave_type'] === (int) $idType) { return $b; } }
        return null;
    }
    public static function available($idEmployee, $idType, $year = null) { $b = self::balance($idEmployee, $idType, $year); return $b ? (float) $b['available'] : 0; }

    protected static function move($idEmployee, $idType, $year, array $delta)
    {
        self::openBalances($idEmployee, $year);
        $set = array();
        foreach ($delta as $col => $v) { $set[] = '`'.bqSQL($col).'`=`'.bqSQL($col).'`+'.(float) $v; }
        return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_leave_balance` SET '.implode(',', $set).', date_upd=NOW()
            WHERE id_pulse_hr_employee='.(int) $idEmployee.' AND id_pulse_hr_leave_type='.(int) $idType.' AND year='.(int) $year);
    }

    /** Manual adjustment with a reason — the only way a balance changes outside a request. */
    public static function adjust($idEmployee, $idType, $days, $reason, $year = null)
    {
        $year = (int) ($year ? $year : date('Y'));
        self::move($idEmployee, $idType, $year, array('adjustment' => (float) $days));
        PulseCoreService::audit('pulsehr', 'leave_adjust', array('id_employee' => (int) $idEmployee, 'type' => (int) $idType, 'days' => (float) $days, 'reason' => $reason, 'year' => $year), 'pulse_hr_leave_balance', 0);
        return true;
    }

    /**
     * Monthly accrual for every type with accrual='monthly'. Idempotent per employee/type/month via
     * last_accrued, so the night-audit hook and the cron can both fire without double-crediting anyone.
     */
    public static function accrueMonth($month = null)
    {
        $month = $month ? date('Y-m', strtotime($month.'-01')) : date('Y-m');
        $year = (int) Tools::substr($month, 0, 4);
        $done = 0;
        $types = array();
        foreach (self::types() as $t) { if ($t['accrual'] === 'monthly') { $types[] = $t; } }
        if (!$types) { return 0; }
        foreach (Db::getInstance()->executeS('SELECT id_pulse_hr_employee, hire_date, status FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE status IN ("active","probation","on_leave","suspended")') as $e) {
            foreach ($types as $t) {
                self::openBalances((int) $e['id_pulse_hr_employee'], $year);
                $b = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_leave_balance` WHERE id_pulse_hr_employee='.(int) $e['id_pulse_hr_employee'].' AND id_pulse_hr_leave_type='.(int) $t['id_pulse_hr_leave_type'].' AND year='.$year);
                if (!$b || $b['last_accrued'] === $month) { continue; }
                // a joiner accrues from the month they started, not from January
                if ($e['hire_date'] && date('Y-m', strtotime($e['hire_date'])) > $month) { continue; }
                $annual = self::entitlementFor((int) $e['id_pulse_hr_employee'], (int) $t['id_pulse_hr_leave_type'], $year);
                if ($annual <= 0) { Db::getInstance()->update('pulse_hr_leave_balance', array('last_accrued' => pSQL($month)), 'id_pulse_hr_leave_balance='.(int) $b['id_pulse_hr_leave_balance'], 0, true); continue; }
                Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_leave_balance` SET accrued=accrued+'.round($annual / 12, 2).', last_accrued="'.pSQL($month).'", date_upd=NOW() WHERE id_pulse_hr_leave_balance='.(int) $b['id_pulse_hr_leave_balance']);
                $done++;
            }
        }
        return $done;
    }

    /** Year end: carry what the cap allows into the new year and let the rest lapse (encash it first if the type allows). */
    public static function carryOver($fromYear = null)
    {
        $fromYear = (int) ($fromYear ? $fromYear : date('Y') - 1);
        $to = $fromYear + 1; $moved = 0;
        foreach (Db::getInstance()->executeS('SELECT b.*, t.carry_over_cap FROM `'._DB_PREFIX_.'pulse_hr_leave_balance` b
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_leave_type` t ON t.id_pulse_hr_leave_type=b.id_pulse_hr_leave_type
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=b.id_pulse_hr_employee
            WHERE b.year='.$fromYear.' AND e.status<>"exited" AND t.carry_over_cap>0') as $b) {
            $left = round((float) $b['opening'] + (float) $b['carried'] + (float) $b['accrued'] + (float) $b['adjustment'] - (float) $b['taken'] - (float) $b['pending'] - (float) $b['encashed'], 2);
            $carry = max(0, min((float) $b['carry_over_cap'], $left));
            if ($carry <= 0) { continue; }
            self::openBalances((int) $b['id_pulse_hr_employee'], $to);
            Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_leave_balance` SET carried='.$carry.', date_upd=NOW()
                WHERE id_pulse_hr_employee='.(int) $b['id_pulse_hr_employee'].' AND id_pulse_hr_leave_type='.(int) $b['id_pulse_hr_leave_type'].' AND year='.$to);
            $moved++;
        }
        PulseCoreService::audit('pulsehr', 'leave_carry_over', array('from' => $fromYear, 'to' => $to, 'balances' => $moved));
        return $moved;
    }

    /* ---------- requests ---------- */

    public static function days($from, $to, $halfDay = 'none', $workingDaysOnly = true)
    {
        $n = $workingDaysOnly ? PulseHrService::workingDays($from, $to) : PulseHrService::calendarDays($from, $to);
        if ($halfDay !== 'none' && $n > 0) { $n -= 0.5; }
        return round(max(0, $n), 2);
    }

    /** Who else in the department is already off across these dates — the coverage check before an approval. */
    public static function clash($from, $to, $dept, $excludeRequest = 0)
    {
        return Db::getInstance()->executeS('SELECT r.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, t.code type_code
            FROM `'._DB_PREFIX_.self::T.'` r
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=r.id_pulse_hr_employee
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_leave_type` t ON t.id_pulse_hr_leave_type=r.id_pulse_hr_leave_type
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE r.status IN ("pending","approved","taken") AND r.date_from<="'.pSQL($to).'" AND r.date_to>="'.pSQL($from).'"'
            .($dept ? ' AND d.code="'.pSQL($dept).'"' : '').($excludeRequest ? ' AND r.id_pulse_hr_leave_request<>'.(int) $excludeRequest : '').' ORDER BY r.date_from');
    }

    public static function blackouts($from, $to, $dept = null)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_blackout` WHERE active=1 AND date_from<="'.pSQL($to).'" AND date_to>="'.pSQL($from).'"'
            .($dept ? ' AND (department IS NULL OR department="" OR department="'.pSQL($dept).'")' : '').' ORDER BY date_from');
    }

    public static function saveBlackout(array $d, $id = 0)
    {
        $row = array('name' => pSQL($d['name']), 'department' => !empty($d['department']) ? pSQL($d['department']) : null,
            'date_from' => pSQL(date('Y-m-d', strtotime($d['date_from']))), 'date_to' => pSQL(date('Y-m-d', strtotime($d['date_to']))),
            'max_off' => (int) (isset($d['max_off']) ? $d['max_off'] : 0), 'min_occupancy_pct' => (float) (isset($d['min_occupancy_pct']) ? $d['min_occupancy_pct'] : 0),
            'reason' => pSQL(isset($d['reason']) ? $d['reason'] : ''), 'active' => isset($d['active']) ? (int) $d['active'] : 1);
        if ($row['date_to'] < $row['date_from']) { throw new PrestaShopException('The blackout ends before it starts'); }
        if ($id) { Db::getInstance()->update('pulse_hr_blackout', $row, 'id_pulse_hr_blackout='.(int) $id, 0, true); } else { Db::getInstance()->insert('pulse_hr_blackout', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    /**
     * Raise a request. Everything that would make it un-approvable is checked here, once, so the manager's
     * screen never has to argue with the rules: eligibility, service, consecutive cap, balance, overlap,
     * blackout and departmental coverage.
     */
    public static function request(array $d)
    {
        $idEmployee = (int) $d['id_pulse_hr_employee'];
        $e = PulseHrEmployee::get($idEmployee);
        if (!$e) { throw new PrestaShopException('Employee not found'); }
        if ($e['status'] === 'exited') { throw new PrestaShopException('That member of staff has left'); }
        $t = self::type((int) $d['id_pulse_hr_leave_type']);
        if (!$t || !$t['active']) { throw new PrestaShopException('Unknown leave type'); }
        $from = date('Y-m-d', strtotime($d['date_from'])); $to = date('Y-m-d', strtotime($d['date_to']));
        if ($to < $from) { throw new PrestaShopException('The last day of leave is before the first'); }
        if ($t['gender'] !== 'any' && $t['gender'] !== $e['gender']) { throw new PrestaShopException($t['name'].' does not apply to this member of staff'); }
        if ((int) $t['min_service_months'] > 0 && $e['hire_date'] && strtotime($e['hire_date'].' +'.(int) $t['min_service_months'].' month') > strtotime($from)) {
            throw new PrestaShopException($t['name'].' needs '.(int) $t['min_service_months'].' months of service — '.$e['firstname'].' qualifies from '.date('j M Y', strtotime($e['hire_date'].' +'.(int) $t['min_service_months'].' month')));
        }
        $half = in_array(isset($d['half_day']) ? $d['half_day'] : 'none', array('none', 'start', 'end')) ? $d['half_day'] : 'none';
        $days = isset($d['days']) && $d['days'] !== '' ? round((float) $d['days'], 2) : self::days($from, $to, $half, (int) $t['working_days_only']);
        if ($days <= 0) { throw new PrestaShopException('That range contains no working days'); }
        if ((int) $t['max_consecutive'] > 0 && $days > (float) $t['max_consecutive']) { throw new PrestaShopException($t['name'].' is capped at '.(float) $t['max_consecutive'].' days at a time'); }
        $overlap = Db::getInstance()->getValue('SELECT request_no FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_hr_employee='.$idEmployee.' AND status IN ("pending","approved","taken") AND date_from<="'.pSQL($to).'" AND date_to>="'.pSQL($from).'"');
        if ($overlap) { throw new PrestaShopException('Those dates overlap request '.$overlap); }
        $year = (int) date('Y', strtotime($from));
        if ($t['paid'] && $t['accrual'] !== 'on_event' && $t['accrual'] !== 'none') {
            $avail = self::available($idEmployee, (int) $t['id_pulse_hr_leave_type'], $year);
            if ($days > $avail + 0.001) { throw new PrestaShopException($e['firstname'].' has '.$avail.' day(s) of '.$t['name'].' left and asked for '.$days); }
        }
        $warnings = self::warnings($idEmployee, $e['dept_code'], $from, $to);
        $row = array('request_no' => PulseHrService::nextNo('LV'), 'id_pulse_hr_employee' => $idEmployee, 'id_pulse_hr_leave_type' => (int) $t['id_pulse_hr_leave_type'],
            'date_from' => pSQL($from), 'date_to' => pSQL($to), 'days' => $days, 'half_day' => pSQL($half), 'reason' => pSQL(isset($d['reason']) ? $d['reason'] : ''),
            'id_relief' => !empty($d['id_relief']) ? (int) $d['id_relief'] : null, 'contact_phone' => pSQL(isset($d['contact_phone']) ? $d['contact_phone'] : $e['phone']),
            'address_on_leave' => pSQL(isset($d['address_on_leave']) ? $d['address_on_leave'] : ''), 'status' => 'pending', 'current_level' => 1,
            'id_pulse_hr_document' => !empty($d['id_pulse_hr_document']) ? (int) $d['id_pulse_hr_document'] : null, 'source' => pSQL(isset($d['source']) ? $d['source'] : 'admin'),
            'business_date' => pSQL(PulseHrService::bd()), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'));
        Db::getInstance()->insert(self::T, $row, true);
        $id = (int) Db::getInstance()->Insert_ID();
        foreach (self::chainFor($e) as $lvl => $step) {
            Db::getInstance()->insert('pulse_hr_leave_approval', array('id_pulse_hr_leave_request' => $id, 'level' => (int) $lvl + 1, 'role' => pSQL($step['role']),
                'id_pulse_hr_employee' => $step['id'] ? (int) $step['id'] : null, 'action' => 'pending', 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), true);
        }
        self::move($idEmployee, (int) $t['id_pulse_hr_leave_type'], $year, array('pending' => $days));
        PulseCoreService::audit('pulsehr', 'leave_request', array('request_no' => $row['request_no'], 'type' => $t['code'], 'from' => $from, 'to' => $to, 'days' => $days, 'warnings' => $warnings), self::T, $id);
        return array('id' => $id, 'request_no' => $row['request_no'], 'days' => $days, 'warnings' => $warnings);
    }

    /** Blackouts and coverage — advisory at request time, shown to whoever approves. */
    public static function warnings($idEmployee, $dept, $from, $to)
    {
        $w = array();
        $occ = PulseHrService::occupancyPct($from);
        foreach (self::blackouts($from, $to, $dept) as $b) {
            if ((float) $b['min_occupancy_pct'] > 0 && $occ < (float) $b['min_occupancy_pct']) { continue; }
            $already = count(self::clash($from, $to, $dept));
            if ((int) $b['max_off'] > 0 && $already < (int) $b['max_off']) { continue; }
            $w[] = 'Blackout: '.$b['name'].($b['reason'] ? ' — '.$b['reason'] : '').(((int) $b['max_off']) ? ' (limit '.(int) $b['max_off'].' off, '.$already.' already booked)' : '');
        }
        foreach (PulseHrService::coverage($from, $dept) as $c) {
            if ($c['room_driven'] && $c['rostered'] - 1 < $c['needed']) { $w[] = $c['name'].' needs '.$c['needed'].' on '.date('j M', strtotime($from)).' for '.$c['occupied'].' occupied rooms and would have '.max(0, $c['rostered'] - 1); }
        }
        $others = self::clash($from, $to, $dept);
        if (count($others) >= (int) PulseHrService::cfg('LEAVE_CLASH_WARN', 3)) { $w[] = count($others).' colleague(s) in the same department are already off across those dates'; }
        return $w;
    }

    /** manager → head of department → HR. Steps with nobody named still have to be actioned, by HR. */
    public static function chainFor(array $e)
    {
        $chain = array();
        if (!empty($e['id_manager'])) { $chain[] = array('role' => 'manager', 'id' => (int) $e['id_manager']); }
        $head = $e['id_pulse_hr_department'] ? (int) Db::getInstance()->getValue('SELECT id_head FROM `'._DB_PREFIX_.'pulse_hr_department` WHERE id_pulse_hr_department='.(int) $e['id_pulse_hr_department']) : 0;
        if ($head && $head !== (int) $e['id_manager'] && $head !== (int) $e['id_pulse_hr_employee']) { $chain[] = array('role' => 'hod', 'id' => $head); }
        $chain[] = array('role' => 'hr', 'id' => 0);
        return $chain;
    }

    public static function get($id)
    {
        $r = Db::getInstance()->getRow('SELECT r.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, e.phone, e.email, e.gender, d.code dept_code, d.name dept_name,
                t.code type_code, t.name type_name, t.paid, t.encashable, CONCAT(rl.firstname," ",rl.lastname) relief_name
            FROM `'._DB_PREFIX_.self::T.'` r
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=r.id_pulse_hr_employee
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_leave_type` t ON t.id_pulse_hr_leave_type=r.id_pulse_hr_leave_type
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_employee` rl ON rl.id_pulse_hr_employee=r.id_relief
            WHERE r.id_pulse_hr_leave_request='.(int) $id);
        if ($r) { $r['approvals'] = Db::getInstance()->executeS('SELECT a.*, CONCAT(e.firstname," ",e.lastname) approver_name FROM `'._DB_PREFIX_.'pulse_hr_leave_approval` a LEFT JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=a.id_pulse_hr_employee WHERE a.id_pulse_hr_leave_request='.(int) $id.' ORDER BY a.level'); }
        return $r;
    }

    public static function requests($status = null, $dept = null, $idEmployee = 0, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT r.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.code dept_code, d.name dept_name, t.code type_code, t.name type_name, t.colour
            FROM `'._DB_PREFIX_.self::T.'` r
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=r.id_pulse_hr_employee
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_leave_type` t ON t.id_pulse_hr_leave_type=r.id_pulse_hr_leave_type
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE 1'.($status ? ' AND r.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'")' : '')
            .($dept ? ' AND d.code="'.pSQL($dept).'"' : '').($idEmployee ? ' AND r.id_pulse_hr_employee='.(int) $idEmployee : '')
            .' ORDER BY r.date_from DESC LIMIT '.(int) $limit);
    }
    public static function pending() { return self::requests('pending'); }

    /**
     * One approval step. The last step approves the request outright: the days move from pending to taken and
     * actionPulseHrLeaveApproved goes out so the roster and Key Cards can react.
     */
    public static function decide($idRequest, $action, $comment = '', $idApproverEmployee = 0)
    {
        $r = self::get($idRequest);
        if (!$r) { throw new PrestaShopException('Leave request not found'); }
        if ($r['status'] !== 'pending') { throw new PrestaShopException('That request is already '.$r['status']); }
        if (!in_array($action, array('approved', 'rejected'))) { throw new PrestaShopException('Unknown decision'); }
        $year = (int) date('Y', strtotime($r['date_from']));
        $step = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_leave_approval` WHERE id_pulse_hr_leave_request='.(int) $idRequest.' AND action="pending" ORDER BY level LIMIT 1');
        if ($step) {
            Db::getInstance()->update('pulse_hr_leave_approval', array('action' => pSQL($action), 'comment' => pSQL($comment), 'id_employee' => PulseHrService::emp(),
                'id_pulse_hr_employee' => $idApproverEmployee ? (int) $idApproverEmployee : ($step['id_pulse_hr_employee'] ? (int) $step['id_pulse_hr_employee'] : null), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_leave_approval='.(int) $step['id_pulse_hr_leave_approval'], 0, true);
        }
        if ($action === 'rejected') {
            Db::getInstance()->update(self::T, array('status' => 'rejected', 'decision_note' => pSQL($comment), 'decided_by' => PulseHrService::emp(), 'decided_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_leave_request='.(int) $idRequest, 0, true);
            Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_leave_approval` SET action="skipped", date_upd=NOW() WHERE id_pulse_hr_leave_request='.(int) $idRequest.' AND action="pending"');
            self::move((int) $r['id_pulse_hr_employee'], (int) $r['id_pulse_hr_leave_type'], $year, array('pending' => -(float) $r['days']));
            PulseCoreService::audit('pulsehr', 'leave_rejected', array('request_no' => $r['request_no'], 'comment' => $comment), self::T, (int) $idRequest);
            self::notify($r, 'rejected');
            return 'rejected';
        }
        $more = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_leave_approval` WHERE id_pulse_hr_leave_request='.(int) $idRequest.' AND action="pending"');
        if ($more > 0) {
            Db::getInstance()->update(self::T, array('current_level' => (int) $r['current_level'] + 1, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_leave_request='.(int) $idRequest, 0, true);
            return 'pending';
        }
        Db::getInstance()->update(self::T, array('status' => 'approved', 'decision_note' => pSQL($comment), 'decided_by' => PulseHrService::emp(), 'decided_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_leave_request='.(int) $idRequest, 0, true);
        self::move((int) $r['id_pulse_hr_employee'], (int) $r['id_pulse_hr_leave_type'], $year, array('pending' => -(float) $r['days'], 'taken' => (float) $r['days']));
        // the roster stops pretending they are coming in
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_roster` SET is_off=1, id_pulse_hr_shift=NULL, note=CONCAT("On leave ",COALESCE(note,"")), date_upd=NOW()
            WHERE id_pulse_hr_employee='.(int) $r['id_pulse_hr_employee'].' AND roster_date BETWEEN "'.pSQL($r['date_from']).'" AND "'.pSQL($r['date_to']).'" AND status<>"cancelled"');
        PulseCoreService::audit('pulsehr', 'leave_approved', array('request_no' => $r['request_no'], 'days' => $r['days'], 'from' => $r['date_from'], 'to' => $r['date_to']), self::T, (int) $idRequest);
        PulseCoreService::event('actionPulseHrLeaveApproved', array('id_request' => (int) $idRequest, 'id_pulse_hr_employee' => (int) $r['id_pulse_hr_employee'],
            'staff_no' => $r['staff_no'], 'type' => $r['type_code'], 'date_from' => $r['date_from'], 'date_to' => $r['date_to'], 'days' => (float) $r['days'], 'paid' => (int) $r['paid']));
        self::notify($r, 'approved');
        return 'approved';
    }

    /**
     * Tell the member of staff. Pulse Comms owns the SMS/WhatsApp adapter and its template list, so we reuse its
     * `ticket_update` template ("your request {title} is now {status}") rather than inventing one it does not know.
     * With Comms absent, or no phone on file, the portal is the notification — nothing here is allowed to throw.
     */
    protected static function notify(array $r, $status)
    {
        if (!PulseHrService::comms() || !method_exists('PulseComms', 'sendRaw')) { return false; }
        if (empty($r['phone']) && empty($r['email'])) { return false; }
        try {
            return PulseComms::sendRaw(isset($r['email']) ? $r['email'] : null, isset($r['phone']) ? $r['phone'] : null, 'ticket_update', array(
                'name' => $r['employee_name'], 'title' => $r['type_name'].' '.$r['request_no'].' ('.date('j M', strtotime($r['date_from'])).'–'.date('j M', strtotime($r['date_to'])).')', 'status' => $status));
        } catch (Exception $e) { PulseCoreService::audit('pulsehr', 'leave_notify_failed', array('request_no' => $r['request_no'], 'error' => $e->getMessage())); return false; }
    }

    public static function cancel($idRequest, $why = '')
    {
        $r = self::get($idRequest);
        if (!$r) { throw new PrestaShopException('Leave request not found'); }
        if (in_array($r['status'], array('cancelled', 'rejected'))) { return true; }
        $year = (int) date('Y', strtotime($r['date_from']));
        $col = $r['status'] === 'pending' ? 'pending' : 'taken';
        self::move((int) $r['id_pulse_hr_employee'], (int) $r['id_pulse_hr_leave_type'], $year, array($col => -(float) $r['days']));
        Db::getInstance()->update(self::T, array('status' => 'cancelled', 'decision_note' => pSQL($why), 'decided_by' => PulseHrService::emp(), 'decided_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_leave_request='.(int) $idRequest, 0, true);
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_leave_approval` SET action="skipped", date_upd=NOW() WHERE id_pulse_hr_leave_request='.(int) $idRequest.' AND action="pending"');
        PulseCoreService::audit('pulsehr', 'leave_cancelled', array('request_no' => $r['request_no'], 'why' => $why), self::T, (int) $idRequest);
        return true;
    }

    /** Encash days the type allows — the amount is the daily rate off the contract in force on the day. */
    public static function encash($idEmployee, $idType, $days, $year = null)
    {
        $days = round((float) $days, 2);
        $t = self::type($idType);
        if (!$t || !$t['encashable']) { throw new PrestaShopException('That leave type cannot be encashed'); }
        $year = (int) ($year ? $year : date('Y'));
        if ($days <= 0 || $days > self::available($idEmployee, $idType, $year) + 0.001) { throw new PrestaShopException('There are not that many days to encash'); }
        self::move($idEmployee, $idType, $year, array('encashed' => $days));
        $amount = round($days * self::dailyRate($idEmployee, date('Y-m-d')), 2);
        PulseCoreService::audit('pulsehr', 'leave_encash', array('id_employee' => (int) $idEmployee, 'type' => $t['code'], 'days' => $days, 'amount' => $amount, 'year' => $year), 'pulse_hr_leave_balance', 0);
        return array('days' => $days, 'amount' => $amount);
    }

    /** Daily rate for liability and encashment: monthly salaries divide by the configured divisor (26 by default). */
    public static function dailyRate($idEmployee, $date = null)
    {
        $c = PulseHrContract::onDate($idEmployee, $date);
        if (!$c) { return 0; }
        $div = max(1, (float) PulseHrService::cfg('LEAVE_DAY_DIVISOR', 26));
        if ($c['pay_basis'] === 'monthly') { return round((float) $c['pay_rate'] / $div, 2); }
        if ($c['pay_basis'] === 'hourly') { return round((float) $c['pay_rate'] * ((float) $c['hours_per_week'] / max(1, (float) $c['days_per_week'])), 2); }
        return round((float) $c['pay_rate'], 2);
    }

    public static function onLeave($date = null)
    {
        $d = pSQL($date ? $date : PulseHrService::bd());
        return Db::getInstance()->executeS('SELECT r.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.name dept_name, t.code type_code, t.name type_name, t.colour
            FROM `'._DB_PREFIX_.self::T.'` r
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=r.id_pulse_hr_employee
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_leave_type` t ON t.id_pulse_hr_leave_type=r.id_pulse_hr_leave_type
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE r.status IN ("approved","taken") AND "'.$d.'" BETWEEN r.date_from AND r.date_to ORDER BY d.sort, e.lastname');
    }

    /** Calendar rows for the leave grid: one row per person with the days they are away inside the window. */
    public static function calendar($from, $to, $dept = null)
    {
        $rows = Db::getInstance()->executeS('SELECT r.id_pulse_hr_employee, r.date_from, r.date_to, r.status, t.code type_code, t.colour,
                CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.code dept_code, d.name dept_name
            FROM `'._DB_PREFIX_.self::T.'` r
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=r.id_pulse_hr_employee
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_leave_type` t ON t.id_pulse_hr_leave_type=r.id_pulse_hr_leave_type
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE r.status IN ("pending","approved","taken") AND r.date_from<="'.pSQL($to).'" AND r.date_to>="'.pSQL($from).'"'
            .($dept ? ' AND d.code="'.pSQL($dept).'"' : '').' ORDER BY d.sort, e.lastname');
        $out = array();
        foreach ($rows as $r) {
            $k = (int) $r['id_pulse_hr_employee'];
            if (!isset($out[$k])) { $out[$k] = array('employee_name' => $r['employee_name'], 'staff_no' => $r['staff_no'], 'dept_name' => $r['dept_name'], 'days' => array()); }
            for ($d = max(strtotime($from), strtotime($r['date_from'])); $d <= min(strtotime($to), strtotime($r['date_to'])); $d = strtotime('+1 day', $d)) {
                $out[$k]['days'][date('Y-m-d', $d)] = array('type' => $r['type_code'], 'colour' => $r['colour'], 'status' => $r['status']);
            }
        }
        return $out;
    }

    /** Roll requests whose dates have arrived or passed, and put the person's status back where it belongs. */
    public static function rollDay($date = null)
    {
        $d = pSQL($date ? $date : PulseHrService::bd()); $n = 0;
        $n += (int) Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET status="taken", date_upd=NOW() WHERE status="approved" AND date_from<="'.$d.'"');
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_employee` e INNER JOIN `'._DB_PREFIX_.self::T.'` r ON r.id_pulse_hr_employee=e.id_pulse_hr_employee AND r.status="taken"
            SET e.status="on_leave", e.date_upd=NOW() WHERE e.status="active" AND "'.$d.'" BETWEEN r.date_from AND r.date_to');
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_employee` e SET e.status="active", e.date_upd=NOW() WHERE e.status="on_leave"
            AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.self::T.'` r WHERE r.id_pulse_hr_employee=e.id_pulse_hr_employee AND r.status IN ("approved","taken") AND "'.$d.'" BETWEEN r.date_from AND r.date_to)');
        return $n;
    }

    /** Leave liability: days owed × the daily rate on today's contract, by department — the number finance provides for. */
    public static function liability($year = null)
    {
        $year = (int) ($year ? $year : date('Y'));
        $out = array(); $total = 0;
        foreach (PulseHrEmployee::search(array('limit' => 500)) as $e) {
            $days = 0;
            foreach (self::balances((int) $e['id_pulse_hr_employee'], $year) as $b) { if ($b['paid'] && $b['code'] === 'ANN') { $days += (float) $b['available']; } }
            if ($days <= 0) { continue; }
            $rate = self::dailyRate((int) $e['id_pulse_hr_employee']);
            $value = round($days * $rate, 2); $total += $value;
            $out[] = array('id_pulse_hr_employee' => (int) $e['id_pulse_hr_employee'], 'staff_no' => $e['staff_no'], 'employee_name' => $e['full_name'],
                'dept_name' => $e['dept_name'], 'days' => round($days, 2), 'daily_rate' => $rate, 'value' => $value);
        }
        return array('rows' => $out, 'total' => round($total, 2), 'year' => $year);
    }
}
