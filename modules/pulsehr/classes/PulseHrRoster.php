<?php
/**
 * Shift planning: a weekly grid by department, publish-to-staff, swap requests, and coverage warnings against
 * the occupancy the hotel is actually running. Night shifts carry a flag Payroll reads for the allowance.
 * A planned roster is private to the office; only a published one appears on the staff portal.
 */
class PulseHrRoster
{
    const T = 'pulse_hr_roster';

    public static function weekStart($date = null)
    {
        $d = strtotime($date ? $date : PulseHrService::bd());
        $start = (int) PulseHrService::cfg('WEEK_START', 1); // 1 = Monday
        $shift = ((int) date('N', $d) - $start + 7) % 7;
        return date('Y-m-d', strtotime('-'.$shift.' day', $d));
    }
    public static function weekDates($from) { $out = array(); for ($i = 0; $i < 7; $i++) { $out[] = date('Y-m-d', strtotime($from.' +'.$i.' day')); } return $out; }

    public static function get($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_hr_roster='.(int) $id); }

    /** Every roster cell in a window, keyed employee → date, plus the people who should appear in the grid. */
    public static function grid($from, $to, $dept = null)
    {
        $staff = PulseHrEmployee::search(array('department' => $dept, 'limit' => 400));
        $cells = Db::getInstance()->executeS('SELECT r.*, s.code shift_code, s.name shift_name, s.start_time, s.end_time, s.colour, s.night, s.paid_hours
            FROM `'._DB_PREFIX_.self::T.'` r LEFT JOIN `'._DB_PREFIX_.'pulse_hr_shift` s ON s.id_pulse_hr_shift=r.id_pulse_hr_shift
            WHERE r.roster_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND r.status<>"cancelled"'.($dept ? ' AND r.department="'.pSQL($dept).'"' : ''));
        $by = array();
        foreach ($cells as $c) { $by[(int) $c['id_pulse_hr_employee']][$c['roster_date']] = $c; }
        $leave = array();
        foreach (PulseHrLeave::calendar($from, $to, $dept) as $idEmp => $l) { $leave[$idEmp] = $l['days']; }
        return array('staff' => $staff, 'cells' => $by, 'leave' => $leave, 'dates' => self::rangeDates($from, $to));
    }
    public static function rangeDates($from, $to) { $out = array(); for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) { $out[] = date('Y-m-d', $d); } return $out; }

    /**
     * Set one cell. An empty shift with off=0 clears the cell entirely; a published roster keeps its published
     * state until it is republished, so staff never see a half-edited week.
     */
    public static function set($idEmployee, $date, $idShift, $isOff = 0, $note = '')
    {
        $e = PulseHrEmployee::get($idEmployee);
        if (!$e) { throw new PrestaShopException('Employee not found'); }
        if ($e['status'] === 'exited') { throw new PrestaShopException($e['firstname'].' has left and cannot be rostered'); }
        $date = date('Y-m-d', strtotime($date));
        $existing = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE roster_date="'.pSQL($date).'" AND id_pulse_hr_employee='.(int) $idEmployee);
        if (!$idShift && !$isOff) {
            if ($existing) { Db::getInstance()->delete(self::T, 'id_pulse_hr_roster='.(int) $existing['id_pulse_hr_roster']); }
            return 0;
        }
        $onLeave = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_leave_request` WHERE id_pulse_hr_employee='.(int) $idEmployee.' AND status IN ("approved","taken") AND "'.pSQL($date).'" BETWEEN date_from AND date_to');
        if ($onLeave && $idShift) { throw new PrestaShopException($e['firstname'].' is on approved leave on '.date('j M', strtotime($date))); }
        $row = array('roster_date' => pSQL($date), 'id_pulse_hr_employee' => (int) $idEmployee, 'id_pulse_hr_shift' => $idShift ? (int) $idShift : null,
            'department' => pSQL($e['dept_code']), 'section' => pSQL(Tools::substr((string) $e['section_name'], 0, 32)), 'is_off' => $isOff ? 1 : 0, 'note' => pSQL($note),
            'id_employee_created' => PulseHrService::emp(), 'date_upd' => date('Y-m-d H:i:s'));
        if ($existing) { Db::getInstance()->update(self::T, $row, 'id_pulse_hr_roster='.(int) $existing['id_pulse_hr_roster'], 0, true); return (int) $existing['id_pulse_hr_roster']; }
        $row['status'] = 'planned'; $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert(self::T, $row, true, true, Db::INSERT_IGNORE);
        return (int) Db::getInstance()->Insert_ID();
    }

    /** Copy the previous week forward — how a roster is actually built on a Friday afternoon. */
    public static function copyWeek($fromWeek, $toWeek, $dept = null)
    {
        $n = 0;
        foreach (self::rangeDates($fromWeek, date('Y-m-d', strtotime($fromWeek.' +6 day'))) as $i => $d) {
            $target = date('Y-m-d', strtotime($toWeek.' +'.$i.' day'));
            foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE roster_date="'.pSQL($d).'" AND status<>"cancelled"'.($dept ? ' AND department="'.pSQL($dept).'"' : '')) as $c) {
                try { self::set((int) $c['id_pulse_hr_employee'], $target, (int) $c['id_pulse_hr_shift'], (int) $c['is_off'], $c['note']); $n++; }
                catch (Exception $e) { continue; } // leave, exits and clashes just do not copy
            }
        }
        return $n;
    }

    public static function clearWeek($from, $to, $dept = null)
    {
        return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.self::T.'` WHERE roster_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND status="planned"'.($dept ? ' AND department="'.pSQL($dept).'"' : ''));
    }

    /** Publish the week: staff can now see it on the portal, and the suite hears about it. */
    public static function publish($from, $to, $dept = null)
    {
        $n = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::T.'` WHERE roster_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND status="planned"'.($dept ? ' AND department="'.pSQL($dept).'"' : ''));
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET status="published", published_at=NOW(), published_by='.(int) PulseHrService::emp().', date_upd=NOW()
            WHERE roster_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND status="planned"'.($dept ? ' AND department="'.pSQL($dept).'"' : ''));
        PulseCoreService::audit('pulsehr', 'roster_published', array('from' => $from, 'to' => $to, 'department' => $dept, 'shifts' => $n));
        PulseCoreService::event('actionPulseHrRosterPublished', array('date_from' => $from, 'date_to' => $to, 'department' => $dept, 'shifts' => $n));
        return $n;
    }

    /** What one person is rostered for — the portal's "my shifts" and the punch matcher both use this. */
    public static function forEmployee($idEmployee, $from, $to, $publishedOnly = true)
    {
        return Db::getInstance()->executeS('SELECT r.*, s.code shift_code, s.name shift_name, s.start_time, s.end_time, s.night, s.paid_hours, s.colour
            FROM `'._DB_PREFIX_.self::T.'` r LEFT JOIN `'._DB_PREFIX_.'pulse_hr_shift` s ON s.id_pulse_hr_shift=r.id_pulse_hr_shift
            WHERE r.id_pulse_hr_employee='.(int) $idEmployee.' AND r.roster_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"
              AND r.status'.($publishedOnly ? '="published"' : '<>"cancelled"').' ORDER BY r.roster_date');
    }

    /** The roster cell a punch belongs to: today's, or last night's night shift that is still running. */
    public static function cellForPunch($idEmployee, $at)
    {
        $ts = strtotime($at);
        foreach (array(date('Y-m-d', $ts), date('Y-m-d', $ts - 86400)) as $d) {
            $c = Db::getInstance()->getRow('SELECT r.*, s.start_time, s.end_time, s.night FROM `'._DB_PREFIX_.self::T.'` r LEFT JOIN `'._DB_PREFIX_.'pulse_hr_shift` s ON s.id_pulse_hr_shift=r.id_pulse_hr_shift
                WHERE r.id_pulse_hr_employee='.(int) $idEmployee.' AND r.roster_date="'.pSQL($d).'" AND r.is_off=0 AND r.status IN ("planned","published")');
            if (!$c || !$c['start_time']) { continue; }
            $start = strtotime($d.' '.$c['start_time']); $end = strtotime($d.' '.$c['end_time']);
            if ($end <= $start) { $end += 86400; } // 22:00–06:00
            $grace = (int) PulseHrService::cfg('PUNCH_GRACE_MIN', 120) * 60;
            if ($ts >= $start - $grace && $ts <= $end + $grace) { return $c; }
        }
        return null;
    }

    /** Rostered hours in a window — the denominator of labour hours per occupied room when there is no punch data. */
    public static function hours($from, $to, $dept = null)
    {
        return (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(s.paid_hours),0) FROM `'._DB_PREFIX_.self::T.'` r
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_shift` s ON s.id_pulse_hr_shift=r.id_pulse_hr_shift
            WHERE r.roster_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND r.is_off=0 AND r.status IN ("planned","published")'.($dept ? ' AND r.department="'.pSQL($dept).'"' : ''));
    }

    /* ---------- swaps ---------- */

    /** A member of staff asks a colleague to take their shift. Nothing moves until a supervisor approves. */
    public static function requestSwap($idRoster, $idRequestedBy, $idEmployeeTo, $reason = '')
    {
        $r = self::get($idRoster);
        if (!$r) { throw new PrestaShopException('That shift is not on the roster'); }
        if ((int) $r['id_pulse_hr_employee'] !== (int) $idRequestedBy) { throw new PrestaShopException('You can only swap your own shift'); }
        if ($r['roster_date'] < date('Y-m-d')) { throw new PrestaShopException('That shift is in the past'); }
        if ((int) $idEmployeeTo === (int) $idRequestedBy) { throw new PrestaShopException('Pick a different colleague'); }
        if (Db::getInstance()->getValue('SELECT id_pulse_hr_roster_swap FROM `'._DB_PREFIX_.'pulse_hr_roster_swap` WHERE id_pulse_hr_roster='.(int) $idRoster.' AND status IN ("pending","accepted")')) { throw new PrestaShopException('A swap is already in flight for that shift'); }
        $to = $idEmployeeTo ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE roster_date="'.pSQL($r['roster_date']).'" AND id_pulse_hr_employee='.(int) $idEmployeeTo) : null;
        Db::getInstance()->insert('pulse_hr_roster_swap', array('id_pulse_hr_roster' => (int) $idRoster, 'id_requested_by' => (int) $idRequestedBy,
            'id_pulse_hr_employee_to' => $idEmployeeTo ? (int) $idEmployeeTo : null, 'id_roster_to' => $to ? (int) $to['id_pulse_hr_roster'] : null,
            'reason' => pSQL($reason), 'status' => 'pending', 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), true);
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::audit('pulsehr', 'roster_swap_request', array('id_roster' => (int) $idRoster, 'from' => (int) $idRequestedBy, 'to' => (int) $idEmployeeTo, 'date' => $r['roster_date']), 'pulse_hr_roster_swap', $id);
        return $id;
    }

    public static function swaps($status = 'pending')
    {
        return Db::getInstance()->executeS('SELECT sw.*, r.roster_date, sh.code shift_code, sh.name shift_name, r.department,
                CONCAT(a.firstname," ",a.lastname) from_name, a.staff_no from_staff_no, CONCAT(b.firstname," ",b.lastname) to_name, b.staff_no to_staff_no
            FROM `'._DB_PREFIX_.'pulse_hr_roster_swap` sw
            INNER JOIN `'._DB_PREFIX_.self::T.'` r ON r.id_pulse_hr_roster=sw.id_pulse_hr_roster
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_shift` sh ON sh.id_pulse_hr_shift=r.id_pulse_hr_shift
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_employee` a ON a.id_pulse_hr_employee=sw.id_requested_by
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_employee` b ON b.id_pulse_hr_employee=sw.id_pulse_hr_employee_to
            WHERE 1'.($status ? ' AND sw.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'")' : '').' ORDER BY r.roster_date');
    }

    /** Approve a swap: the two cells exchange people (or the shift simply moves when the colleague was free). */
    public static function decideSwap($idSwap, $action, $note = '')
    {
        $sw = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_roster_swap` WHERE id_pulse_hr_roster_swap='.(int) $idSwap);
        if (!$sw) { throw new PrestaShopException('Swap not found'); }
        if (!in_array($sw['status'], array('pending', 'accepted'))) { throw new PrestaShopException('That swap is already '.$sw['status']); }
        if (!in_array($action, array('approved', 'rejected', 'cancelled'))) { throw new PrestaShopException('Unknown decision'); }
        Db::getInstance()->update('pulse_hr_roster_swap', array('status' => pSQL($action), 'decided_by' => PulseHrService::emp(), 'decided_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_roster_swap='.(int) $idSwap, 0, true);
        if ($action !== 'approved') { return $action; }
        $a = self::get((int) $sw['id_pulse_hr_roster']);
        $b = $sw['id_roster_to'] ? self::get((int) $sw['id_roster_to']) : null;
        if ($b) {
            Db::getInstance()->update(self::T, array('id_pulse_hr_employee' => (int) $b['id_pulse_hr_employee'], 'status' => 'swapped', 'note' => pSQL('Swapped '.$note), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_roster='.(int) $a['id_pulse_hr_roster'], 0, true);
            Db::getInstance()->update(self::T, array('id_pulse_hr_employee' => (int) $a['id_pulse_hr_employee'], 'status' => 'swapped', 'note' => pSQL('Swapped '.$note), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_roster='.(int) $b['id_pulse_hr_roster'], 0, true);
        } else {
            Db::getInstance()->update(self::T, array('id_pulse_hr_employee' => (int) $sw['id_pulse_hr_employee_to'], 'status' => 'swapped', 'note' => pSQL('Covered '.$note), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_roster='.(int) $a['id_pulse_hr_roster'], 0, true);
        }
        PulseCoreService::audit('pulsehr', 'roster_swap_approved', array('id_swap' => (int) $idSwap, 'date' => $a['roster_date']), 'pulse_hr_roster_swap', (int) $idSwap);
        return 'approved';
    }

    /**
     * Roll the roster day at night audit: cancel nothing, but make sure tomorrow exists for anyone on a repeating
     * pattern, and report the coverage gap for the day ahead so the duty manager sees it before 6 a.m.
     */
    public static function rollDay($date = null)
    {
        $d = $date ? date('Y-m-d', strtotime($date)) : PulseHrService::bd();
        $next = date('Y-m-d', strtotime($d.' +1 day'));
        $gaps = array();
        foreach (PulseHrService::coverage($next) as $c) { if ($c['short'] > 0) { $gaps[] = $c['name'].' short by '.$c['short'].' for '.$c['occupied'].' rooms'; } }
        if ($gaps && class_exists('PulseTrace')) {
            PulseTrace::add('alert', 'Roster coverage tomorrow ('.date('j M', strtotime($next)).'): '.implode('; ', $gaps), date('Y-m-d H:i:s'), null, null, null, 'housekeeping');
        }
        return $gaps;
    }
}
