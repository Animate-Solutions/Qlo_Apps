<?php
/**
 * The roster Time & Attendance pairs against.
 *
 * Pulse HR owns roster planning when it is installed; this table is the local mirror the engine actually
 * reads, so pairing keeps working if HR is disabled, uninstalled or simply not bought. Without HR the grid on
 * the Overtime & Roster screen maintains it directly.
 *
 * A staff member with no roster row for a date is not treated as absent — that would mark the whole property
 * absent on day one. They fall back to their default shift and are classified by their punches.
 */
class PulseTaRoster
{
    const T = 'pulse_ta_roster';

    public static function get($idStaff, $date)
    {
        return Db::getInstance()->getRow('SELECT r.*, s.code shift_code, s.name shift_name, s.start_time, s.end_time, s.crosses_midnight, s.is_night,
                s.break_minutes, s.break_paid, s.break_punched, s.grace_in_min, s.grace_out_min, s.window_before_min, s.window_after_min,
                s.min_shift_min, s.max_shift_min, s.is_split, s.split2_start, s.split2_end, s.paid_minutes, s.colour
            FROM `'._DB_PREFIX_.self::T.'` r LEFT JOIN `'._DB_PREFIX_.'pulse_ta_shift` s ON s.id_pulse_ta_shift=r.id_pulse_ta_shift
            WHERE r.id_pulse_ta_staff='.(int) $idStaff.' AND r.roster_date="'.pSQL($date).'"');
    }

    public static function set($idStaff, $date, $idShift, $dayType = 'work', $note = '', $published = 1, $source = 'local')
    {
        $row = array('id_pulse_ta_staff' => (int) $idStaff, 'roster_date' => pSQL($date),
            'id_pulse_ta_shift' => $idShift ? (int) $idShift : null,
            'day_type' => pSQL(in_array($dayType, array('work', 'rest', 'leave', 'holiday', 'training', 'off_site'), true) ? $dayType : 'work'),
            'source' => pSQL(in_array($source, array('local', 'hr', 'auto'), true) ? $source : 'local'),
            'note' => pSQL(Tools::substr((string) $note, 0, 128)), 'published' => $published ? 1 : 0, 'date_upd' => date('Y-m-d H:i:s'));
        $ex = Db::getInstance()->getValue('SELECT id_pulse_ta_roster FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_ta_staff='.(int) $idStaff.' AND roster_date="'.pSQL($date).'"');
        if ($ex) { Db::getInstance()->update(self::T, PulseTaService::nulls($row), 'id_pulse_ta_roster='.(int) $ex); return (int) $ex; }
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert(self::T, PulseTaService::nulls($row), false, true, Db::INSERT_IGNORE);
        return (int) Db::getInstance()->Insert_ID();
    }

    /** The week grid: one row per staff member, one column per date. */
    public static function week($from, $days = 7, $department = '')
    {
        $dates = array();
        for ($i = 0; $i < max(1, (int) $days); $i++) { $dates[] = date('Y-m-d', strtotime($from.' +'.$i.' day')); }
        $staff = PulseTaService::staffList($department);
        $rows = Db::getInstance()->executeS('SELECT r.id_pulse_ta_staff, r.roster_date, r.day_type, r.published, r.note, s.code, s.colour, s.start_time, s.end_time, s.crosses_midnight, s.is_night
            FROM `'._DB_PREFIX_.self::T.'` r LEFT JOIN `'._DB_PREFIX_.'pulse_ta_shift` s ON s.id_pulse_ta_shift=r.id_pulse_ta_shift
            WHERE r.roster_date BETWEEN "'.pSQL($dates[0]).'" AND "'.pSQL(end($dates)).'"');
        $map = array();
        foreach ((array) $rows as $r) { $map[(int) $r['id_pulse_ta_staff']][$r['roster_date']] = $r; }
        $grid = array();
        foreach ($staff as $s) {
            $cells = array();
            foreach ($dates as $d) { $cells[$d] = isset($map[(int) $s['id_pulse_ta_staff']][$d]) ? $map[(int) $s['id_pulse_ta_staff']][$d] : null; }
            $s['cells'] = $cells;
            $grid[] = $s;
        }
        return array('dates' => $dates, 'rows' => $grid);
    }

    /** Coverage per shift per date — the "who is on nights this week" check a duty manager actually runs. */
    public static function coverage($from, $days = 7, $department = '')
    {
        $to = date('Y-m-d', strtotime($from.' +'.(max(1, (int) $days) - 1).' day'));
        return Db::getInstance()->executeS('SELECT r.roster_date, sh.code, sh.name, sh.is_night, s.department, COUNT(*) heads
            FROM `'._DB_PREFIX_.self::T.'` r
            INNER JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=r.id_pulse_ta_staff AND s.status="active"
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_shift` sh ON sh.id_pulse_ta_shift=r.id_pulse_ta_shift
            WHERE r.day_type="work" AND r.roster_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'.($department ? ' AND s.department="'.pSQL($department).'"' : '').'
            GROUP BY r.roster_date, sh.id_pulse_ta_shift, s.department ORDER BY r.roster_date, sh.sort');
    }

    /**
     * Apply a shift pattern across a date range for a set of staff. `$pattern` is a list of shift codes (or
     * 'REST') cycled day by day, which is how a hotel actually writes a rota: 2 earlies, 2 lates, 2 nights, 2 off.
     */
    public static function applyPattern(array $idStaffList, $from, $to, array $pattern, $publish = 1)
    {
        if (!$pattern) { throw new PrestaShopException('Choose at least one shift for the pattern'); }
        $shifts = array();
        foreach (PulseTaService::shifts(false) as $s) { $shifts[$s['code']] = $s; }
        $n = 0; $day = 0;
        for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
            $code = $pattern[$day % count($pattern)];
            $day++;
            foreach ($idStaffList as $idStaff) {
                if (Tools::strtoupper($code) === 'REST') { self::set((int) $idStaff, date('Y-m-d', $t), null, 'rest', '', $publish); }
                elseif (isset($shifts[$code])) { self::set((int) $idStaff, date('Y-m-d', $t), (int) $shifts[$code]['id_pulse_ta_shift'], 'work', '', $publish); }
                else { continue; }
                $n++;
            }
        }
        PulseTaService::audit('roster_pattern', array('staff' => count($idStaffList), 'from' => $from, 'to' => $to, 'pattern' => implode(',', $pattern), 'rows' => $n));
        return $n;
    }

    /**
     * Mirror the Pulse HR roster into the local table. Reads defensively: HR may name its columns differently,
     * and a missing HR install simply returns zero.
     */
    public static function importFromHr($from, $to)
    {
        if (!PulseTaService::hr() || !PulseTaService::tableExists('pulse_hr_roster')) { return 0; }
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_roster` WHERE roster_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"');
        $shiftByCode = array();
        foreach (PulseTaService::shifts(false) as $s) { $shiftByCode[Tools::strtoupper($s['code'])] = (int) $s['id_pulse_ta_shift']; }
        $n = 0;
        foreach ((array) $rows as $r) {
            $idHr = isset($r['id_pulse_hr_employee']) ? (int) $r['id_pulse_hr_employee'] : 0;
            if (!$idHr) { continue; }
            $s = PulseTaService::staffByHr($idHr);
            if (!$s) { $id = PulseTaService::syncOne($idHr); $s = $id ? PulseTaService::staff($id) : null; }
            if (!$s) { continue; }
            $code = Tools::strtoupper((string) (isset($r['shift_code']) ? $r['shift_code'] : ''));
            $idShift = isset($shiftByCode[$code]) ? $shiftByCode[$code] : null;
            $type = isset($r['day_type']) && in_array($r['day_type'], array('work', 'rest', 'leave', 'holiday', 'training', 'off_site'), true) ? $r['day_type'] : ($idShift ? 'work' : 'rest');
            self::set((int) $s['id_pulse_ta_staff'], $r['roster_date'], $idShift, $type, isset($r['note']) ? $r['note'] : '', isset($r['published']) ? (int) $r['published'] : 1, 'hr');
            $n++;
        }
        PulseTaService::audit('roster_import_hr', array('from' => $from, 'to' => $to, 'rows' => $n));
        return $n;
    }

    /** Public holidays in a range, keyed by date. */
    public static function holidays($from, $to)
    {
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_holiday` WHERE active=1 AND holiday_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"');
        $out = array();
        foreach ((array) $rows as $r) { $out[$r['holiday_date']] = $r; }
        return $out;
    }

    public static function isHoliday($date)
    {
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_holiday` WHERE active=1 AND holiday_date="'.pSQL($date).'" ORDER BY multiplier DESC LIMIT 1');
    }
}
