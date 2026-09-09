<?php
/**
 * Timesheets and the approval workflow that locks a period.
 *
 * Payroll is allowed to trust a locked period and nothing else. Locking is therefore a real gate:
 *   open -> submitted -> approved -> locked
 * A period cannot be approved while a `block`-severity exception is open in it, because those are exactly
 * the cases where somebody's pay would be wrong. Once locked, no punch can be added, no adjustment made and
 * no rebuild run for those dates; a mistake is fixed by reopening the period with a recorded reason, which
 * leaves an audit row naming the employee who did it.
 */
class PulseTaTimesheet
{
    const T = 'pulse_ta_timesheet';
    const T_PERIOD = 'pulse_ta_period';

    /* ---------- reading ---------- */

    public static function get($idStaff, $date)
    {
        return Db::getInstance()->getRow('SELECT t.*, s.staff_no, CONCAT(s.firstname," ",s.lastname) staff_name, s.position, p.name period_name, p.status period_status
            FROM `'._DB_PREFIX_.self::T.'` t INNER JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=t.id_pulse_ta_staff
            LEFT JOIN `'._DB_PREFIX_.self::T_PERIOD.'` p ON p.id_pulse_ta_period=t.id_pulse_ta_period
            WHERE t.id_pulse_ta_staff='.(int) $idStaff.' AND t.business_date="'.pSQL($date).'"');
    }

    /** The punches behind one timesheet, in the order the engine read them. Evidence for a dispute. */
    public static function punches($idTimesheet)
    {
        return Db::getInstance()->executeS('SELECT tp.role, tp.seq, tp.virtual, p.*, d.name device_name FROM `'._DB_PREFIX_.'pulse_ta_timesheet_punch` tp
            INNER JOIN `'._DB_PREFIX_.'pulse_ta_punch` p ON p.id_pulse_ta_punch=tp.id_pulse_ta_punch
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=p.id_pulse_ta_device
            WHERE tp.id_pulse_ta_timesheet='.(int) $idTimesheet.' ORDER BY tp.seq, p.punched_at');
    }

    /** Grid for the Timesheets screen: one row per staff member per day in the range. */
    public static function grid($from, $to, $department = '', $status = '')
    {
        return Db::getInstance()->executeS('SELECT t.*, s.staff_no, CONCAT(s.firstname," ",s.lastname) staff_name, s.position, s.pay_basis,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_exception` e WHERE e.id_pulse_ta_timesheet=t.id_pulse_ta_timesheet AND e.status="open") open_exceptions
            FROM `'._DB_PREFIX_.self::T.'` t INNER JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=t.id_pulse_ta_staff
            WHERE t.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'
            .($department ? ' AND t.department="'.pSQL($department).'"' : '').($status ? ' AND t.status="'.pSQL($status).'"' : '')
            .' ORDER BY s.department, s.lastname, s.firstname, t.business_date');
    }

    /** Per-person totals over a range — what a payroll run reads. */
    public static function totals($from, $to, $department = '')
    {
        return Db::getInstance()->executeS('SELECT t.id_pulse_ta_staff, s.staff_no, CONCAT(s.firstname," ",s.lastname) staff_name, s.department, s.position, s.pay_basis,
                s.hourly_rate, s.daily_rate, COUNT(*) days,
                SUM(t.status NOT IN ("absent","rest","leave","holiday") AND t.worked_minutes>0) days_worked,
                SUM(t.status="absent") days_absent, SUM(t.status="leave") days_leave,
                SUM(t.worked_minutes) worked_minutes, SUM(t.ot_minutes) ot_minutes, SUM(t.ot_weighted_minutes) ot_weighted_minutes,
                SUM(t.night_minutes) night_minutes, SUM(t.late_minutes) late_minutes, SUM(t.short_minutes) short_minutes,
                MIN(t.locked) all_locked, SUM(t.has_exception) with_exceptions
            FROM `'._DB_PREFIX_.self::T.'` t INNER JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=t.id_pulse_ta_staff
            WHERE t.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'.($department ? ' AND t.department="'.pSQL($department).'"' : '').'
            GROUP BY t.id_pulse_ta_staff ORDER BY s.department, s.lastname, s.firstname');
    }

    /** Department roll-up for the cost report and the labour-per-room KPI. */
    public static function byDepartment($from, $to)
    {
        return Db::getInstance()->executeS('SELECT t.department, COUNT(DISTINCT t.id_pulse_ta_staff) staff, COUNT(*) days,
                SUM(t.worked_minutes) worked_minutes, SUM(t.ot_minutes) ot_minutes, SUM(t.ot_weighted_minutes) ot_weighted_minutes,
                SUM(t.night_minutes) night_minutes, SUM(t.status="absent") absences, SUM(t.late_minutes>0) late_days
            FROM `'._DB_PREFIX_.self::T.'` t WHERE t.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"
            GROUP BY t.department ORDER BY worked_minutes DESC');
    }

    /* ---------- periods ---------- */

    public static function periods($limit = 60)
    {
        return Db::getInstance()->executeS('SELECT p.*, CONCAT(a.firstname," ",a.lastname) approver, CONCAT(b.firstname," ",b.lastname) submitter
            FROM `'._DB_PREFIX_.self::T_PERIOD.'` p LEFT JOIN `'._DB_PREFIX_.'employee` a ON a.id_employee=p.id_employee_approved
            LEFT JOIN `'._DB_PREFIX_.'employee` b ON b.id_employee=p.id_employee_submitted
            ORDER BY p.date_from DESC, p.department LIMIT '.(int) $limit);
    }

    public static function period($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T_PERIOD.'` WHERE id_pulse_ta_period='.(int) $id); }

    /** Is this person's date inside an approved or locked period? The gate every write checks. */
    public static function isLocked($idStaff, $date)
    {
        $s = PulseTaService::staff($idStaff);
        $dept = $s ? $s['department'] : '';
        $n = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::T_PERIOD.'` WHERE status IN ("approved","locked")
            AND date_from<="'.pSQL($date).'" AND date_to>="'.pSQL($date).'" AND (department="" OR department="'.pSQL($dept).'")');
        if ($n) { return true; }
        return (bool) Db::getInstance()->getValue('SELECT locked FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_ta_staff='.(int) $idStaff.' AND business_date="'.pSQL($date).'"');
    }

    public static function createPeriod($name, $from, $to, $department = '')
    {
        if (!strtotime($from) || !strtotime($to) || strtotime($to) < strtotime($from)) { throw new PrestaShopException('Choose a valid period'); }
        $clash = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T_PERIOD.'` WHERE department="'.pSQL($department).'"
            AND date_from<="'.pSQL($to).'" AND date_to>="'.pSQL($from).'" AND status<>"reopened"');
        if ($clash) { throw new PrestaShopException('That range overlaps the existing period "'.$clash['name'].'"'); }
        Db::getInstance()->insert(self::T_PERIOD, array('name' => pSQL(Tools::substr($name, 0, 64)), 'department' => pSQL($department),
            'date_from' => pSQL($from), 'date_to' => pSQL($to), 'status' => 'open', 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID();
        self::refreshPeriod($id);
        PulseTaService::audit('period_create', array('id' => $id, 'name' => $name, 'from' => $from, 'to' => $to, 'department' => $department), self::T_PERIOD, $id);
        return $id;
    }

    /** Recount a period's totals and its blocking exceptions, and stamp its timesheets with the period id. */
    public static function refreshPeriod($id)
    {
        $p = self::period($id);
        if (!$p) { return false; }
        $where = 't.business_date BETWEEN "'.pSQL($p['date_from']).'" AND "'.pSQL($p['date_to']).'"'.($p['department'] ? ' AND t.department="'.pSQL($p['department']).'"' : '');
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T.'` t SET t.id_pulse_ta_period='.(int) $id.' WHERE '.$where.' AND (t.id_pulse_ta_period IS NULL OR t.id_pulse_ta_period='.(int) $id.')');
        $agg = Db::getInstance()->getRow('SELECT COUNT(DISTINCT t.id_pulse_ta_staff) staff, SUM(t.worked_minutes) w, SUM(t.ot_weighted_minutes) o
            FROM `'._DB_PREFIX_.self::T.'` t WHERE '.$where);
        $open = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_exception` e WHERE e.status="open" AND e.severity="block"
            AND e.business_date BETWEEN "'.pSQL($p['date_from']).'" AND "'.pSQL($p['date_to']).'"'.($p['department'] ? ' AND e.department="'.pSQL($p['department']).'"' : ''));
        Db::getInstance()->update(self::T_PERIOD, array('staff_count' => (int) (isset($agg['staff']) ? $agg['staff'] : 0),
            'worked_minutes' => (int) (isset($agg['w']) ? $agg['w'] : 0), 'ot_weighted_minutes' => (int) (isset($agg['o']) ? $agg['o'] : 0),
            'open_exceptions' => $open, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_period='.(int) $id);
        return true;
    }

    /** Rebuild every day in the period, then recount. The button a supervisor presses before submitting. */
    public static function rebuildPeriod($id)
    {
        $p = self::period($id);
        if (!$p) { throw new PrestaShopException('Period not found'); }
        if (in_array($p['status'], array('approved', 'locked'), true)) { throw new PrestaShopException('That period is '.$p['status'].' — reopen it first'); }
        $r = PulseTaEngine::buildRange($p['date_from'], $p['date_to'], $p['department']);
        self::refreshPeriod($id);
        return $r;
    }

    public static function submitPeriod($id, $note = '')
    {
        $p = self::period($id);
        if (!$p) { throw new PrestaShopException('Period not found'); }
        if ($p['status'] !== 'open' && $p['status'] !== 'reopened') { throw new PrestaShopException('That period is already '.$p['status']); }
        self::refreshPeriod($id);
        Db::getInstance()->update(self::T_PERIOD, PulseTaService::nulls(array('status' => 'submitted', 'id_employee_submitted' => PulseTaService::emp() ?: null,
            'submitted_at' => date('Y-m-d H:i:s'), 'note' => pSQL(Tools::substr($note, 0, 255)), 'date_upd' => date('Y-m-d H:i:s'))), 'id_pulse_ta_period='.(int) $id);
        PulseTaService::audit('period_submit', array('id' => (int) $id, 'note' => $note), self::T_PERIOD, (int) $id);
        return true;
    }

    /**
     * Approve and lock. This is the moment payroll may start trusting these numbers, so it refuses while a
     * blocking exception is open and it stamps every timesheet in the range as locked.
     */
    public static function approvePeriod($id, $force = false)
    {
        $p = self::period($id);
        if (!$p) { throw new PrestaShopException('Period not found'); }
        if (in_array($p['status'], array('approved', 'locked'), true)) { throw new PrestaShopException('That period is already '.$p['status']); }
        $emp = PulseTaService::emp();
        if (!$emp) { throw new PrestaShopException('An approving employee is required'); }
        self::refreshPeriod($id);
        $p = self::period($id);
        if ((int) $p['open_exceptions'] > 0 && !$force) {
            throw new PrestaShopException((int) $p['open_exceptions'].' blocking exception(s) are still open in this period — clear them on the Exceptions screen before approving, or approve with override if you are certain.');
        }
        $where = 'business_date BETWEEN "'.pSQL($p['date_from']).'" AND "'.pSQL($p['date_to']).'"'.($p['department'] ? ' AND department="'.pSQL($p['department']).'"' : '');
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET locked=1, id_pulse_ta_period='.(int) $id.', date_upd=NOW() WHERE '.$where);
        $rows = (int) Db::getInstance()->Affected_Rows();
        Db::getInstance()->update(self::T_PERIOD, array('status' => 'locked', 'id_employee_approved' => (int) $emp,
            'approved_at' => date('Y-m-d H:i:s'), 'locked_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_period='.(int) $id);
        PulseTaService::audit('period_approve', array('id' => (int) $id, 'name' => $p['name'], 'from' => $p['date_from'], 'to' => $p['date_to'],
            'department' => $p['department'], 'timesheets' => $rows, 'forced' => $force ? 1 : 0, 'open_exceptions' => (int) $p['open_exceptions']), self::T_PERIOD, (int) $id);
        PulseCoreService::event('actionPulseTaTimesheetApproved', array('id_period' => (int) $id, 'from' => $p['date_from'], 'to' => $p['date_to'],
            'department' => $p['department'], 'timesheets' => $rows, 'worked_minutes' => (int) $p['worked_minutes'], 'ot_weighted_minutes' => (int) $p['ot_weighted_minutes']));
        return array('ok' => true, 'timesheets' => $rows);
    }

    /** Reopening is deliberately noisy: it needs a reason and it is audited with the employee who did it. */
    public static function reopenPeriod($id, $reason)
    {
        $reason = trim((string) $reason);
        if ($reason === '') { throw new PrestaShopException('A reason is required to reopen an approved period'); }
        $p = self::period($id);
        if (!$p) { throw new PrestaShopException('Period not found'); }
        $where = 'business_date BETWEEN "'.pSQL($p['date_from']).'" AND "'.pSQL($p['date_to']).'"'.($p['department'] ? ' AND department="'.pSQL($p['department']).'"' : '');
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET locked=0, date_upd=NOW() WHERE '.$where);
        Db::getInstance()->update(self::T_PERIOD, array('status' => 'reopened', 'reopen_reason' => pSQL(Tools::substr($reason, 0, 255), true), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_period='.(int) $id);
        PulseTaService::audit('period_reopen', array('id' => (int) $id, 'name' => $p['name'], 'reason' => $reason), self::T_PERIOD, (int) $id);
        PulseTaService::alert('Timesheet period "'.$p['name'].'" was reopened after approval — '.$reason);
        return true;
    }

    /**
     * Lock one day for one person without a whole period — used when payroll pays a casual by the shift.
     * Unlocking is refused inside an approved period: otherwise this is a quiet back door around
     * reopenPeriod(), which demands a reason and raises an alert.
     */
    public static function lockDay($idStaff, $date, $lock = true)
    {
        if (!$lock) {
            $s = PulseTaService::staff($idStaff);
            $dept = $s ? $s['department'] : '';
            $p = Db::getInstance()->getRow('SELECT name FROM `'._DB_PREFIX_.self::T_PERIOD.'` WHERE status IN ("approved","locked")
                AND date_from<="'.pSQL($date).'" AND date_to>="'.pSQL($date).'" AND (department="" OR department="'.pSQL($dept).'")');
            if ($p) { throw new PrestaShopException('That day is inside the approved period "'.$p['name'].'" — reopen the period with a reason instead of unlocking one day'); }
        }
        Db::getInstance()->update(self::T, array('locked' => $lock ? 1 : 0, 'date_upd' => date('Y-m-d H:i:s')),
            'id_pulse_ta_staff='.(int) $idStaff.' AND business_date="'.pSQL($date).'"');
        PulseTaService::audit($lock ? 'timesheet_lock' : 'timesheet_unlock', array('id_staff' => (int) $idStaff, 'date' => $date), self::T, (int) $idStaff);
        return true;
    }

    /**
     * What Payroll should read: approved, locked totals only. Anything unlocked is excluded, and the caller is
     * told how many days were skipped so it can refuse to run rather than under-pay somebody quietly.
     */
    public static function payrollExtract($from, $to, $department = '')
    {
        $rows = Db::getInstance()->executeS('SELECT t.id_pulse_ta_staff, s.staff_no, s.id_hr_employee, s.department, s.pay_basis, s.hourly_rate, s.daily_rate,
                COUNT(*) days, SUM(t.worked_minutes) worked_minutes, SUM(t.ot_minutes) ot_minutes, SUM(t.ot_weighted_minutes) ot_weighted_minutes,
                SUM(t.night_minutes) night_minutes, SUM(t.status="absent") days_absent,
                SUM(t.status NOT IN ("absent","rest","leave","holiday") AND t.worked_minutes>0) days_worked
            FROM `'._DB_PREFIX_.self::T.'` t INNER JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=t.id_pulse_ta_staff
            WHERE t.locked=1 AND t.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'.($department ? ' AND t.department="'.pSQL($department).'"' : '').'
            GROUP BY t.id_pulse_ta_staff ORDER BY s.department, s.staff_no');
        $unlocked = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::T.'` WHERE locked=0
            AND business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'.($department ? ' AND department="'.pSQL($department).'"' : ''));
        return array('rows' => is_array($rows) ? $rows : array(), 'unlocked_days' => $unlocked,
            'complete' => $unlocked === 0, 'from' => $from, 'to' => $to, 'department' => $department);
    }

    /** Overtime register: who worked it, under which rule, and whether it was approved. */
    public static function overtime($from, $to, $department = '')
    {
        return Db::getInstance()->executeS('SELECT t.*, s.staff_no, CONCAT(s.firstname," ",s.lastname) staff_name, s.position
            FROM `'._DB_PREFIX_.self::T.'` t INNER JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=t.id_pulse_ta_staff
            WHERE t.ot_minutes>0 AND t.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'.($department ? ' AND t.department="'.pSQL($department).'"' : '').'
            ORDER BY t.business_date DESC, t.ot_minutes DESC');
    }

    /** Minutes as 7h 45m — used everywhere in the templates so hours never read as a raw decimal. */
    public static function hm($minutes)
    {
        $m = (int) $minutes;
        $sign = $m < 0 ? '-' : '';
        $m = abs($m);
        return $sign.(int) ($m / 60).'h '.str_pad($m % 60, 2, '0', STR_PAD_LEFT).'m';
    }
}
