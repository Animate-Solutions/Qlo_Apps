<?php
/**
 * The supervisor's queue: every place where the punch record and the roster disagree.
 *
 * Rows are deduplicated on sha1(staff|date|type|key), so rebuilding a day a dozen times does not bury a
 * supervisor under a dozen copies of the same missing clock-out — the row is reused and its detail refreshed.
 *
 * A fix is auditable by construction. Resolving an exception writes a `pulse_ta_adjustment` row carrying the
 * approving employee, the reason and the timestamp, and only then rebuilds the timesheet. The punch table is
 * never touched, so the original evidence and the correction sit side by side for as long as the property
 * keeps its records.
 */
class PulseTaExceptionQueue
{
    const T = 'pulse_ta_exception';
    const T_ADJ = 'pulse_ta_adjustment';

    public static function types()
    {
        return array(
            'missing_in' => 'No clock-in for a rostered shift',
            'missing_out' => 'Clocked in but never clocked out',
            'no_punches' => 'Rostered but no punches at all',
            'late' => 'Late beyond the grace period',
            'early_out' => 'Left before the end of the shift',
            'short_shift' => 'Worked less than the minimum shift',
            'overlong_shift' => 'Pairing longer than the maximum shift — probably a missed clock-out',
            'unmatched_ref' => 'A device user id nobody is mapped to',
            'duplicate_punch' => 'Two punches within the minimum gap',
            'pos_mismatch' => 'POS clock and biometric record disagree',
            'device_offline' => 'A clocking device stopped reporting',
            'future_punch' => 'A punch dated in the future — device clock is wrong',
        );
    }

    public static function hash($idStaff, $date, $type, $key = '') { return sha1((int) $idStaff.'|'.(string) $date.'|'.(string) $type.'|'.(string) $key); }

    /**
     * Raise (or refresh) one exception.
     * @return int the exception id
     */
    public static function raise($idStaff, $date, $type, $severity = 'warn', $detail = '', $minutes = 0, $idTimesheet = null, $idPunch = null, $key = '')
    {
        $hash = self::hash($idStaff, $date, $type, $key);
        $dept = '';
        if ($idStaff) { $s = PulseTaService::staff($idStaff); $dept = $s ? $s['department'] : ''; }
        $ex = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE dedupe_hash="'.pSQL($hash).'"');
        $row = array('id_pulse_ta_staff' => $idStaff ? (int) $idStaff : null, 'business_date' => pSQL($date), 'department' => pSQL($dept),
            'id_pulse_ta_timesheet' => $idTimesheet ? (int) $idTimesheet : null, 'id_pulse_ta_punch' => $idPunch ? (int) $idPunch : null,
            'type' => pSQL($type), 'severity' => pSQL(in_array($severity, array('info', 'warn', 'block'), true) ? $severity : 'warn'),
            'detail' => pSQL(Tools::substr((string) $detail, 0, 255), true), 'minutes' => (int) $minutes, 'date_upd' => date('Y-m-d H:i:s'));
        if ($ex) {
            // A resolved exception is never silently reopened by a rebuild: the supervisor's decision stands.
            if ($ex['status'] !== 'open') { return (int) $ex['id_pulse_ta_exception']; }
            Db::getInstance()->update(self::T, PulseTaService::nulls($row), 'id_pulse_ta_exception='.(int) $ex['id_pulse_ta_exception']);
            return (int) $ex['id_pulse_ta_exception'];
        }
        $row['dedupe_hash'] = pSQL($hash); $row['status'] = 'open'; $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert(self::T, PulseTaService::nulls($row), false, true, Db::INSERT_IGNORE);
        $id = (int) Db::getInstance()->Insert_ID();
        if ($id) { PulseCoreService::event('actionPulseTaException', array('id_exception' => $id, 'type' => $type, 'id_staff' => $idStaff, 'date' => $date, 'severity' => $severity)); }
        return $id;
    }

    /** Close every open exception of a type whose dedupe key matches — used when the underlying cause is fixed. */
    public static function closeByKey($type, $key, $resolution)
    {
        $rows = Db::getInstance()->executeS('SELECT id_pulse_ta_exception, id_pulse_ta_staff, business_date, dedupe_hash FROM `'._DB_PREFIX_.self::T.'` WHERE status="open" AND type="'.pSQL($type).'"');
        $n = 0;
        foreach ((array) $rows as $r) {
            if (self::hash($r['id_pulse_ta_staff'], $r['business_date'], $type, $key) !== $r['dedupe_hash']) { continue; }
            Db::getInstance()->update(self::T, PulseTaService::nulls(array('status' => 'auto_closed', 'resolution' => pSQL(Tools::substr($resolution, 0, 255), true),
                'id_employee_resolved' => PulseTaService::emp() ?: null, 'resolved_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'))),
                'id_pulse_ta_exception='.(int) $r['id_pulse_ta_exception']);
            $n++;
        }
        return $n;
    }

    /** Anything a rebuild of this day/person no longer sees is closed automatically, so the queue stays honest. */
    public static function autoCloseFor($idStaff, $date, array $keepIds)
    {
        $sql = 'UPDATE `'._DB_PREFIX_.self::T.'` SET status="auto_closed", resolution="Cleared by a timesheet rebuild", resolved_at=NOW(), date_upd=NOW()
            WHERE status="open" AND id_pulse_ta_staff='.(int) $idStaff.' AND business_date="'.pSQL($date).'"';
        $keep = array_filter(array_map('intval', $keepIds));
        if ($keep) { $sql .= ' AND id_pulse_ta_exception NOT IN ('.implode(',', $keep).')'; }
        Db::getInstance()->execute($sql);
        return (int) Db::getInstance()->Affected_Rows();
    }

    public static function get($id)
    {
        return Db::getInstance()->getRow('SELECT e.*, s.staff_no, CONCAT(s.firstname," ",s.lastname) staff_name, s.department dept,
                t.shift_code, t.shift_start, t.shift_end, t.first_in, t.last_out, t.worked_minutes, t.locked
            FROM `'._DB_PREFIX_.self::T.'` e LEFT JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=e.id_pulse_ta_staff
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_timesheet` t ON t.id_pulse_ta_timesheet=e.id_pulse_ta_timesheet
            WHERE e.id_pulse_ta_exception='.(int) $id);
    }

    /** The queue. $f: status, type, department, from, to, severity, id_staff. */
    public static function search(array $f = array(), $limit = 300)
    {
        $w = ' WHERE 1';
        $w .= ' AND e.status="'.pSQL(isset($f['status']) && $f['status'] !== '' ? $f['status'] : 'open').'"';
        if (!empty($f['type'])) { $w .= ' AND e.type="'.pSQL($f['type']).'"'; }
        if (!empty($f['severity'])) { $w .= ' AND e.severity="'.pSQL($f['severity']).'"'; }
        if (!empty($f['department'])) { $w .= ' AND e.department="'.pSQL($f['department']).'"'; }
        if (!empty($f['id_staff'])) { $w .= ' AND e.id_pulse_ta_staff='.(int) $f['id_staff']; }
        if (!empty($f['from'])) { $w .= ' AND e.business_date>="'.pSQL($f['from']).'"'; }
        if (!empty($f['to'])) { $w .= ' AND e.business_date<="'.pSQL($f['to']).'"'; }
        return Db::getInstance()->executeS('SELECT e.*, s.staff_no, CONCAT(s.firstname," ",s.lastname) staff_name,
                t.shift_code, t.shift_start, t.shift_end, t.first_in, t.last_out, t.worked_minutes, t.locked,
                CONCAT(emp.firstname," ",emp.lastname) resolved_by
            FROM `'._DB_PREFIX_.self::T.'` e LEFT JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=e.id_pulse_ta_staff
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_timesheet` t ON t.id_pulse_ta_timesheet=e.id_pulse_ta_timesheet
            LEFT JOIN `'._DB_PREFIX_.'employee` emp ON emp.id_employee=e.id_employee_resolved'.$w
            .' ORDER BY FIELD(e.severity,"block","warn","info"), e.business_date DESC, e.id_pulse_ta_exception DESC LIMIT '.max(1, min(1000, (int) $limit)));
    }

    public static function counts()
    {
        $rows = Db::getInstance()->executeS('SELECT type, severity, COUNT(*) n FROM `'._DB_PREFIX_.self::T.'` WHERE status="open" GROUP BY type, severity');
        $out = array('total' => 0, 'block' => 0, 'by_type' => array());
        foreach ((array) $rows as $r) { $out['total'] += (int) $r['n']; if ($r['severity'] === 'block') { $out['block'] += (int) $r['n']; } $out['by_type'][$r['type']] = (isset($out['by_type'][$r['type']]) ? $out['by_type'][$r['type']] : 0) + (int) $r['n']; }
        return $out;
    }

    /* ---------- resolution ---------- */

    /**
     * Fix an exception. Every fix is an adjustment row with an approver — the punch table is never edited.
     * $how: add_punch | ignore_punch | set_minutes | add_overtime | paid_absence | unpaid_absence | waive_late | waive
     * $d:   punched_at, direction, minutes, id_punch, reason
     */
    public static function resolve($idException, $how, array $d = array())
    {
        $e = self::get($idException);
        if (!$e) { throw new PrestaShopException('Exception not found'); }
        if ($e['status'] !== 'open') { throw new PrestaShopException('That exception is already '.$e['status']); }
        $reason = trim((string) (isset($d['reason']) ? $d['reason'] : ''));
        if ($reason === '') { throw new PrestaShopException('A reason is required — a corrected timesheet has to say why'); }
        $emp = PulseTaService::emp();
        if (!$emp) { throw new PrestaShopException('An approving employee is required'); }
        if ($e['id_pulse_ta_staff'] && PulseTaTimesheet::isLocked((int) $e['id_pulse_ta_staff'], $e['business_date'])) {
            throw new PrestaShopException('That period is approved and locked — reopen it in Timesheets before correcting this');
        }
        $idAdj = null;
        if ($how !== 'waive') {
            if (!in_array($how, array('add_punch', 'ignore_punch', 'set_minutes', 'add_overtime', 'paid_absence', 'unpaid_absence', 'waive_late'), true)) { throw new PrestaShopException('Unknown correction '.$how); }
            if ($how === 'add_punch' && (empty($d['punched_at']) || !strtotime($d['punched_at']))) { throw new PrestaShopException('A punch time is required'); }
            if ($how === 'ignore_punch' && empty($d['id_punch'])) { throw new PrestaShopException('Choose the punch to ignore'); }
            $idAdj = self::adjust((int) $e['id_pulse_ta_staff'], $e['business_date'], $how, array_merge($d, array('id_pulse_ta_exception' => (int) $idException, 'reason' => $reason)));
        }
        Db::getInstance()->update(self::T, PulseTaService::nulls(array('status' => $how === 'waive' ? 'waived' : 'resolved', 'id_pulse_ta_adjustment' => $idAdj ? (int) $idAdj : null,
            'resolution' => pSQL(Tools::substr(($how === 'waive' ? 'Waived: ' : ucfirst(str_replace('_', ' ', $how)).': ').$reason, 0, 255), true),
            'id_employee_resolved' => (int) $emp, 'resolved_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'))), 'id_pulse_ta_exception='.(int) $idException);
        PulseTaService::audit('exception_resolve', array('id_exception' => (int) $idException, 'type' => $e['type'], 'how' => $how, 'reason' => $reason,
            'id_staff' => (int) $e['id_pulse_ta_staff'], 'date' => $e['business_date'], 'id_adjustment' => $idAdj), self::T, (int) $idException);
        if ($e['id_pulse_ta_staff']) { PulseTaEngine::buildOne((int) $e['id_pulse_ta_staff'], $e['business_date']); }
        return array('ok' => true, 'id_adjustment' => $idAdj);
    }

    /** Write an approved adjustment. Called by resolve() and directly from the Timesheets screen. */
    public static function adjust($idStaff, $date, $type, array $d = array())
    {
        $emp = PulseTaService::emp();
        Db::getInstance()->insert(self::T_ADJ, PulseTaService::nulls(array(
            'id_pulse_ta_staff' => (int) $idStaff, 'business_date' => pSQL($date), 'type' => pSQL($type),
            'punched_at' => !empty($d['punched_at']) ? pSQL(date('Y-m-d H:i:s', strtotime($d['punched_at']))) : null,
            'direction' => pSQL(in_array(isset($d['direction']) ? $d['direction'] : '', array('in', 'out', 'break_out', 'break_in', 'ot_in', 'ot_out'), true) ? $d['direction'] : 'unknown'),
            'id_pulse_ta_punch' => !empty($d['id_punch']) ? (int) $d['id_punch'] : null,
            'minutes' => (int) (isset($d['minutes']) ? $d['minutes'] : 0),
            'reason' => pSQL(Tools::substr((string) (isset($d['reason']) ? $d['reason'] : ''), 0, 255), true),
            'id_pulse_ta_exception' => !empty($d['id_pulse_ta_exception']) ? (int) $d['id_pulse_ta_exception'] : null,
            'id_employee_requested' => $emp ?: null, 'id_employee_approver' => $emp ?: null, 'approved_at' => date('Y-m-d H:i:s'),
            'status' => 'approved', 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        )));
        $id = (int) Db::getInstance()->Insert_ID();
        // An added punch becomes a real punch row tagged source='adjustment' and carrying the approver, so the
        // engine reads it like any other evidence and the payslip trail shows exactly where it came from.
        if ($type === 'add_punch' && !empty($d['punched_at'])) {
            try {
                PulseTaPunch::manual($idStaff, $d['punched_at'], isset($d['direction']) ? $d['direction'] : 'unknown', 'adjustment',
                    array('device_serial' => 'ADJUST', 'verify_mode' => 'manual', 'raw' => 'adjustment #'.$id.': '.Tools::substr((string) (isset($d['reason']) ? $d['reason'] : ''), 0, 200)));
            } catch (Exception $e) { PulseTaService::audit('adjustment_punch_failed', array('id_adjustment' => $id, 'error' => $e->getMessage()), self::T_ADJ, $id); }
        }
        PulseTaService::audit('adjustment', array('id' => $id, 'id_staff' => (int) $idStaff, 'date' => $date, 'type' => $type,
            'minutes' => (int) (isset($d['minutes']) ? $d['minutes'] : 0), 'reason' => isset($d['reason']) ? $d['reason'] : ''), self::T_ADJ, $id);
        return $id;
    }

    /** Approved adjustments the engine must apply for one person on one day. */
    public static function adjustments($idStaff, $date)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.self::T_ADJ.'` WHERE id_pulse_ta_staff='.(int) $idStaff
            .' AND business_date="'.pSQL($date).'" AND status="approved" ORDER BY id_pulse_ta_adjustment');
    }

    /** The audit trail behind one corrected day, for the Timesheets detail panel and any later dispute. */
    public static function adjustmentTrail($idStaff, $date)
    {
        return Db::getInstance()->executeS('SELECT a.*, CONCAT(e.firstname," ",e.lastname) approver, x.type exception_type
            FROM `'._DB_PREFIX_.self::T_ADJ.'` a LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=a.id_employee_approver
            LEFT JOIN `'._DB_PREFIX_.self::T.'` x ON x.id_pulse_ta_exception=a.id_pulse_ta_exception
            WHERE a.id_pulse_ta_staff='.(int) $idStaff.' AND a.business_date="'.pSQL($date).'" ORDER BY a.id_pulse_ta_adjustment');
    }

    /** Void an adjustment (it stays on file as `void`, with the reason) and rebuild the day. */
    public static function voidAdjustment($idAdjustment, $reason)
    {
        $a = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T_ADJ.'` WHERE id_pulse_ta_adjustment='.(int) $idAdjustment);
        if (!$a) { throw new PrestaShopException('Adjustment not found'); }
        if (PulseTaTimesheet::isLocked((int) $a['id_pulse_ta_staff'], $a['business_date'])) { throw new PrestaShopException('That period is locked — reopen it first'); }
        Db::getInstance()->update(self::T_ADJ, array('status' => 'void', 'reason' => pSQL(Tools::substr($a['reason'].' | VOIDED: '.$reason, 0, 255), true), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_adjustment='.(int) $idAdjustment);
        PulseTaService::audit('adjustment_void', array('id' => (int) $idAdjustment, 'reason' => $reason), self::T_ADJ, (int) $idAdjustment);
        PulseTaEngine::buildOne((int) $a['id_pulse_ta_staff'], $a['business_date']);
        return true;
    }
}
