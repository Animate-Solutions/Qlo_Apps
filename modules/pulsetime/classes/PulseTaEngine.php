<?php
/**
 * The attendance engine: punches in, a timesheet out.
 *
 * The whole design turns on one thing — a hotel runs 22:00–06:00 every night of the year, so a "day" is not
 * a calendar day. The rules used here:
 *
 *  * A shift owns a WINDOW, not a date. For a night shift rostered on Monday the window runs from Monday
 *    22:00 minus the early grace to Tuesday 06:00 plus the late grace, and every punch inside it belongs to
 *    Monday's timesheet. Tuesday's own window starts at Tuesday 22:00 and cannot reach back into it.
 *  * Business-date attribution is `shift_start` by default (PULSE_TA_ATTRIBUTE_BY): the night of Monday is
 *    paid as Monday, which is how a rota is written and how a night allowance is argued about.
 *  * A punch is consumed by exactly one timesheet. The link table `pulse_ta_timesheet_punch` records which,
 *    so the 05:55 clock-out of a night shift can never also be read as an early arrival for the morning one.
 *  * All arithmetic is on real timestamps. Nothing here does date maths on a device's encoded counter, and
 *    nothing assumes end > start on the clock face.
 *  * Punches are never modified. Corrections arrive as approved `pulse_ta_adjustment` rows and are applied
 *    on top, so a rebuild is always reproducible from evidence plus decisions.
 */
class PulseTaEngine
{
    const T = 'pulse_ta_timesheet';
    const T_LINK = 'pulse_ta_timesheet_punch';

    protected static $rebuilding = array();

    /* ---------- helpers ---------- */

    protected static function cfg($k, $d = null) { return PulseTaService::cfg($k, $d); }

    /** Round a timestamp onto a minute grid. 'none' leaves it alone. */
    public static function roundTs($ts, $mode, $stepMin)
    {
        $stepMin = (int) $stepMin;
        if ($stepMin <= 0 || $mode === 'none' || $mode === '') { return (int) $ts; }
        $s = $stepMin * 60;
        if ($mode === 'up') { return (int) (ceil($ts / $s) * $s); }
        if ($mode === 'down') { return (int) (floor($ts / $s) * $s); }
        return (int) (round($ts / $s) * $s);
    }

    /** Minutes of [$from,$to] that fall inside the nightly premium window, which itself crosses midnight. */
    public static function nightMinutes($from, $to)
    {
        $nf = (string) self::cfg('NIGHT_FROM', '22:00');
        $nt = (string) self::cfg('NIGHT_TO', '06:00');
        if ($to <= $from) { return 0; }
        $total = 0;
        $dayStart = strtotime(date('Y-m-d', $from).' 00:00:00');
        for ($d = $dayStart - 86400; $d <= $to + 86400; $d += 86400) {
            $s = strtotime(date('Y-m-d', $d).' '.$nf);
            $e = strtotime(date('Y-m-d', $d).' '.$nt);
            if ($e <= $s) { $e += 86400; }               // 22:00 -> 06:00 next day
            $total += max(0, min($to, $e) - max($from, $s));
        }
        return (int) round($total / 60);
    }

    /** Overtime rules in force on a date, by scope. */
    public static function rules($date, $department = '')
    {
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_ot_rule` WHERE active=1
            AND effective_from<="'.pSQL($date).'" AND (effective_to IS NULL OR effective_to>="'.pSQL($date).'")
            AND (department="" OR department="'.pSQL($department).'") ORDER BY department DESC, sort');
        $out = array();
        foreach ((array) $rows as $r) { if (!isset($out[$r['scope']])) { $out[$r['scope']] = $r; } }
        return $out;
    }

    /** Monday-based (or configured) week start for a date. */
    public static function weekStart($date)
    {
        $start = (int) self::cfg('WEEK_START', 1); // 0=Sunday .. 6=Saturday
        $dow = (int) date('w', strtotime($date));
        $back = ($dow - $start + 7) % 7;
        return date('Y-m-d', strtotime($date.' -'.$back.' day'));
    }

    /**
     * Walk a direction-resolved punch sequence and pair it into worked intervals. Pure: no database, no
     * settings, no clock — which is why it can be tested against a night shift crossing midnight, a month
     * boundary and a missed clock-out without any hardware.
     *
     * All arithmetic is on absolute unix timestamps, so 22:00 → 06:00 is simply eight hours; nothing here
     * compares clock faces or assumes the out time is "later in the day" than the in time.
     *
     * $seq: ordered list of array(id, ts, role in|out, raw_direction, source, device).
     * @return array intervals, raw_seconds, rounded_seconds, break_seconds, missing_in, missing_out, first_in, last_out
     */
    public static function pair(array $seq, $inMode = 'none', $outMode = 'none', $roundStep = 0)
    {
        $intervals = array(); $rawSeconds = 0; $roundedSeconds = 0; $breakSeconds = 0;
        $openIn = null; $missingIn = 0; $missingOut = 0; $prevOut = null;
        foreach ($seq as $s) {
            if ($s['role'] === 'in') {
                if ($openIn !== null) { $missingOut++; }         // two ins in a row: the clock-out between them never happened
                if ($prevOut !== null) { $breakSeconds += max(0, $s['ts'] - $prevOut); }
                $openIn = $s;
            } else {
                if ($openIn === null) { $missingIn++; $prevOut = $s['ts']; continue; }
                $a = $openIn['ts']; $b = $s['ts'];
                if ($b > $a) {
                    $ra = self::roundTs($a, $inMode, $roundStep);
                    $rb = self::roundTs($b, $outMode, $roundStep);
                    if ($rb < $ra) { $rb = $ra; }
                    $rawSeconds += ($b - $a);
                    $roundedSeconds += ($rb - $ra);
                    $intervals[] = array('in' => $openIn, 'out' => $s, 'raw' => $b - $a, 'rounded_in' => $ra, 'rounded_out' => $rb);
                }
                $prevOut = $b; $openIn = null;
            }
        }
        if ($openIn !== null) { $missingOut++; }
        $firstIn = null;
        foreach ($seq as $s) { if ($s['role'] === 'in') { $firstIn = $s['ts']; break; } }
        $lastOut = null;
        for ($i = count($seq) - 1; $i >= 0; $i--) { if ($seq[$i]['role'] === 'out') { $lastOut = $seq[$i]['ts']; break; } }
        return array('intervals' => $intervals, 'raw_seconds' => $rawSeconds, 'rounded_seconds' => $roundedSeconds, 'break_seconds' => $breakSeconds,
            'missing_in' => $missingIn, 'missing_out' => $missingOut, 'first_in' => $firstIn, 'last_out' => $lastOut);
    }

    /**
     * Resolve each punch's in/out role. A device that reports its own state is trusted; an 'unknown' punch
     * alternates from whatever the sequence has established so far, which is what a single-reader door needs.
     * Repeat punches inside $minGap seconds are dropped as double taps.
     *
     * The gap test has to look at the RAW direction, not the resolved role. On a single reader that reports
     * no direction the role is inferred by alternating, so a finger read twice in the same second resolves to
     * in,out — the two roles differ, the same-role test never fires, and an eight-hour shift is paired as
     * twenty seconds of work plus a missing clock-out. So a pair of direction-less punches inside the gap is
     * a double tap by definition, whatever the alternation would have made of them.
     * @return array [sequence, duplicates]
     */
    public static function resolveDirections(array $punches, $minGap = 60, array $ignore = array())
    {
        $known = array('in', 'break_in', 'ot_in', 'out', 'break_out', 'ot_out');
        $seq = array(); $inside = false; $lastTs = null; $lastRole = null; $lastStated = false; $dupes = 0;
        foreach ($punches as $p) {
            if (isset($ignore[(int) $p['id_pulse_ta_punch']])) { continue; }
            $ts = strtotime($p['punched_at']);
            $d = $p['direction'];
            $stated = in_array($d, $known, true);
            if (in_array($d, array('in', 'break_in', 'ot_in'), true)) { $role = 'in'; }
            elseif (in_array($d, array('out', 'break_out', 'ot_out'), true)) { $role = 'out'; }
            else { $role = $inside ? 'out' : 'in'; }
            if ($lastTs !== null && ($ts - $lastTs) <= $minGap && ($role === $lastRole || (!$stated && !$lastStated))) { $dupes++; continue; }
            $seq[] = array('id' => (int) $p['id_pulse_ta_punch'], 'ts' => $ts, 'role' => $role, 'raw_direction' => $d,
                'source' => isset($p['source']) ? $p['source'] : 'device', 'device' => isset($p['id_pulse_ta_device']) ? (int) $p['id_pulse_ta_device'] : 0);
            $inside = ($role === 'in');
            $lastTs = $ts; $lastRole = $role; $lastStated = $stated;
        }
        return array('sequence' => $seq, 'duplicates' => $dupes);
    }

    /* ---------- window ---------- */

    /**
     * The shift window for one person on one business date.
     * @return array [shift(row|null), day_type, start(ts|null), end(ts|null), window_from(ts), window_to(ts), scheduled_minutes]
     */
    public static function window($staff, $date)
    {
        $r = PulseTaRoster::get((int) $staff['id_pulse_ta_staff'], $date);
        $dayType = $r ? $r['day_type'] : 'work';
        $shift = null;
        if ($r && $r['id_pulse_ta_shift']) { $shift = PulseTaService::shift((int) $r['id_pulse_ta_shift']); }
        if (!$shift && $dayType === 'work') { $shift = PulseTaService::defaultShift($staff); }
        $dayStart = strtotime($date.' '.((string) self::cfg('DAY_START', '00:00')).':00');
        if (!$shift) {
            // Rest day, leave or a property with no shifts defined: the whole business day is the window, so a
            // call-in on a day off is still captured and paid at the rest-day rate.
            return array('shift' => null, 'day_type' => $dayType, 'start' => null, 'end' => null,
                'window_from' => $dayStart, 'window_to' => $dayStart + 86400 - 1, 'scheduled_minutes' => 0);
        }
        $start = strtotime($date.' '.$shift['start_time']);
        $end = strtotime($date.' '.$shift['end_time']);
        if ((int) $shift['crosses_midnight'] || $end <= $start) { $end += 86400; }
        if ((int) $shift['is_split'] && $shift['split2_end']) {
            $e2 = strtotime($date.' '.$shift['split2_end']);
            if ($e2 <= $start) { $e2 += 86400; }
            if ($e2 > $end) { $end = $e2; }
        }
        $scheduled = (int) round(($end - $start) / 60) - ((int) $shift['break_paid'] ? 0 : (int) $shift['break_minutes']);
        if ((int) $shift['paid_minutes'] > 0 && !(int) $shift['is_split']) { $scheduled = (int) $shift['paid_minutes']; }
        return array('shift' => $shift, 'day_type' => $dayType,
            'start' => $start, 'end' => $end,
            'window_from' => $start - (int) $shift['window_before_min'] * 60,
            'window_to' => $end + (int) $shift['window_after_min'] * 60,
            'scheduled_minutes' => max(0, $scheduled));
    }

    /* ---------- build ---------- */

    /**
     * Build (or rebuild) one person's timesheet for one business date.
     * @return array the timesheet row as computed, plus 'skipped' when the period is locked
     */
    public static function buildOne($idStaff, $date, $depth = 0)
    {
        $idStaff = (int) $idStaff;
        $key = $idStaff.'|'.$date;
        if (isset(self::$rebuilding[$key])) { return array('skipped' => 'already rebuilding'); }
        self::$rebuilding[$key] = 1;
        try { $r = self::buildInner($idStaff, $date, $depth); } catch (Exception $e) { unset(self::$rebuilding[$key]); throw $e; }
        unset(self::$rebuilding[$key]);
        return $r;
    }

    protected static function buildInner($idStaff, $date, $depth)
    {
        $staff = PulseTaService::staff($idStaff);
        if (!$staff) { return array('skipped' => 'no such staff member'); }
        $existing = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_ta_staff='.$idStaff.' AND business_date="'.pSQL($date).'"');
        if ($existing && (int) $existing['locked']) { return array_merge($existing, array('skipped' => 'period locked')); }
        // The row's own flag is not enough: a person with no timesheet yet for a date inside an approved
        // period (a late hire, a device that only just delivered) would otherwise get a fresh, unlocked row
        // built into a locked period and quietly change what payroll already read.
        if (!$existing && PulseTaTimesheet::isLocked($idStaff, $date)) { return array('skipped' => 'period locked'); }

        $w = self::window($staff, $date);
        $shift = $w['shift'];
        $from = date('Y-m-d H:i:s', $w['window_from']);
        $to = date('Y-m-d H:i:s', $w['window_to']);
        $idTs = $existing ? (int) $existing['id_pulse_ta_timesheet'] : 0;

        /* --- 1. the punches this timesheet may claim --- */
        $punches = Db::getInstance()->executeS('SELECT p.* FROM `'._DB_PREFIX_.'pulse_ta_punch` p
            WHERE p.id_pulse_ta_staff='.$idStaff.' AND p.punched_at>="'.pSQL($from).'" AND p.punched_at<="'.pSQL($to).'"
            AND p.punched_at<="'.pSQL(date('Y-m-d H:i:s', time() + 3600)).'"
            AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.self::T_LINK.'` tp
                INNER JOIN `'._DB_PREFIX_.self::T.'` t ON t.id_pulse_ta_timesheet=tp.id_pulse_ta_timesheet
                WHERE tp.id_pulse_ta_punch=p.id_pulse_ta_punch AND t.business_date<>"'.pSQL($date).'"
                  AND (t.business_date<"'.pSQL($date).'" OR t.locked=1))
            ORDER BY p.punched_at, p.id_pulse_ta_punch');
        $punches = is_array($punches) ? $punches : array();

        /* --- 2. approved adjustments --- */
        $adj = PulseTaExceptionQueue::adjustments($idStaff, $date);
        $ignore = array(); $setMinutes = null; $extraOt = 0; $waiveLate = false; $forcedType = null;
        foreach ($adj as $a) {
            switch ($a['type']) {
                case 'ignore_punch': if ($a['id_pulse_ta_punch']) { $ignore[(int) $a['id_pulse_ta_punch']] = 1; } break;
                case 'set_minutes': $setMinutes = max(0, (int) $a['minutes']); break;
                case 'add_overtime': $extraOt += max(0, (int) $a['minutes']); break;
                case 'waive_late': $waiveLate = true; break;
                case 'paid_absence': $forcedType = 'paid_absence'; break;
                case 'unpaid_absence': $forcedType = 'unpaid_absence'; break;
                default: break; // add_punch already exists as a punch row with source='adjustment'
            }
        }

        /* --- 3. resolve direction and drop duplicates --- */
        $minGap = max(0, (int) self::cfg('MIN_GAP_SEC', 60));
        $ignored = array();
        foreach ($punches as $p) { if (isset($ignore[(int) $p['id_pulse_ta_punch']])) { $ignored[] = $p; } }
        $res = self::resolveDirections($punches, $minGap, $ignore);
        $seq = $res['sequence']; $dupes = $res['duplicates'];

        /* --- 4. pair --- */
        $roundStep = (int) self::cfg('ROUND_MIN', 5);
        $roundOff = ((string) self::cfg('ROUND_MODE', 'nearest')) === 'none';
        $inMode = $roundOff ? 'none' : (string) self::cfg('ROUND_IN', 'up');
        $outMode = $roundOff ? 'none' : (string) self::cfg('ROUND_OUT', 'down');
        $p = self::pair($seq, $inMode, $outMode, $roundStep);
        $intervals = $p['intervals']; $rawSeconds = $p['raw_seconds']; $roundedSeconds = $p['rounded_seconds'];
        $missingIn = $p['missing_in']; $missingOut = $p['missing_out']; $breakSeconds = $p['break_seconds'];
        $firstIn = $p['first_in']; $lastOut = $p['last_out'];

        /* --- 5. breaks --- */
        $rawMinutes = (int) round($rawSeconds / 60);
        $workedMinutes = (int) round($roundedSeconds / 60);
        $breakMinutes = 0;
        if ($shift) {
            if ((int) $shift['break_punched']) {
                $breakMinutes = (int) round($breakSeconds / 60);   // already excluded by the pairing, reported for the payslip
            } elseif (!(int) $shift['break_paid'] && (int) $shift['break_minutes'] > 0 && $workedMinutes > (int) $shift['break_minutes']) {
                $breakMinutes = (int) $shift['break_minutes'];
                $workedMinutes -= $breakMinutes;
            }
        }
        if ($setMinutes !== null) { $workedMinutes = $setMinutes; }
        $workedMinutes = max(0, $workedMinutes);
        $roundedDelta = $workedMinutes - max(0, $rawMinutes - $breakMinutes);

        /* --- 6. classification --- */
        $lateMinutes = 0; $earlyOut = 0;
        if ($shift && $w['day_type'] === 'work' && $firstIn) { $lateMinutes = max(0, (int) round(($firstIn - $w['start']) / 60) - (int) $shift['grace_in_min']); }
        if ($shift && $w['day_type'] === 'work' && $lastOut) { $earlyOut = max(0, (int) round(($w['end'] - $lastOut) / 60) - (int) $shift['grace_out_min']); }
        if ($waiveLate) { $lateMinutes = 0; }
        $scheduled = (int) $w['scheduled_minutes'];
        $short = max(0, $scheduled - $workedMinutes);

        $status = 'present';
        if ($w['day_type'] === 'rest') { $status = $workedMinutes > 0 ? 'present' : 'rest'; }
        elseif ($w['day_type'] === 'leave') { $status = 'leave'; }
        elseif ($w['day_type'] === 'holiday') { $status = $workedMinutes > 0 ? 'present' : 'holiday'; }
        elseif ($w['day_type'] === 'off_site') { $status = 'off_site'; }
        elseif (!$seq) { $status = 'absent'; }
        elseif ($missingIn || $missingOut) { $status = 'incomplete'; }
        elseif ($lateMinutes > 0) { $status = 'late'; }
        if ($forcedType === 'paid_absence') { $status = 'leave'; }
        if ($forcedType === 'unpaid_absence') { $status = 'absent'; }

        /* --- 7. overtime --- */
        $holiday = PulseTaRoster::isHoliday($date);
        $rules = self::rules($date, $staff['department']);
        $otDaily = 0; $otWeekly = 0; $otRest = 0; $otHoliday = 0; $weighted = 0.0;
        $eligible = (int) $staff['ot_eligible'] === 1;
        if ($eligible && $workedMinutes > 0) {
            if ($holiday && isset($rules['holiday'])) {
                $otHoliday = $workedMinutes;
                $weighted += $otHoliday * (float) (isset($holiday['multiplier']) && (float) $holiday['multiplier'] > 0 ? $holiday['multiplier'] : $rules['holiday']['multiplier']);
            } elseif ($w['day_type'] === 'rest' && isset($rules['rest_day'])) {
                $otRest = $workedMinutes;
                $weighted += $otRest * (float) $rules['rest_day']['multiplier'];
            } else {
                if (isset($rules['daily'])) {
                    $t = (int) $rules['daily']['threshold_minutes'];
                    $otDaily = max(0, $workedMinutes - $t);
                    if ((int) $rules['daily']['cap_minutes'] > 0) { $otDaily = min($otDaily, (int) $rules['daily']['cap_minutes']); }
                    $weighted += $otDaily * (float) $rules['daily']['multiplier'];
                }
                if (isset($rules['weekly'])) {
                    $ws = self::weekStart($date);
                    $before = (int) Db::getInstance()->getValue('SELECT SUM(worked_minutes) FROM `'._DB_PREFIX_.self::T.'`
                        WHERE id_pulse_ta_staff='.$idStaff.' AND business_date>="'.pSQL($ws).'" AND business_date<"'.pSQL($date).'"');
                    $t = (int) $rules['weekly']['threshold_minutes'];
                    $over = max(0, ($before + $workedMinutes) - $t) - max(0, $before - $t);
                    $otWeekly = max(0, min($workedMinutes, $over) - $otDaily); // never pay the same minute twice
                    if ((int) $rules['weekly']['cap_minutes'] > 0) { $otWeekly = min($otWeekly, (int) $rules['weekly']['cap_minutes']); }
                    $weighted += $otWeekly * (float) $rules['weekly']['multiplier'];
                }
            }
        }
        if ($extraOt > 0) { $otDaily += $extraOt; $weighted += $extraOt * (float) (isset($rules['daily']) ? $rules['daily']['multiplier'] : 1.5); }
        $otTotal = $otDaily + $otWeekly + $otRest + $otHoliday;
        $nightMinutes = 0;
        foreach ($intervals as $iv) { $nightMinutes += self::nightMinutes($iv['rounded_in'], $iv['rounded_out']); }

        /* --- 8. write the timesheet --- */
        $sources = array();
        foreach ($seq as $s) { $sources[$s['source']] = 1; }
        $row = array(
            'id_pulse_ta_staff' => $idStaff, 'business_date' => pSQL($date), 'department' => pSQL($staff['department']),
            'id_pulse_ta_shift' => $shift ? (int) $shift['id_pulse_ta_shift'] : null, 'shift_code' => pSQL($shift ? $shift['code'] : ''),
            'shift_start' => $w['start'] ? pSQL(date('Y-m-d H:i:s', $w['start'])) : null, 'shift_end' => $w['end'] ? pSQL(date('Y-m-d H:i:s', $w['end'])) : null,
            'crosses_midnight' => $shift ? (int) $shift['crosses_midnight'] : 0,
            'first_in' => $firstIn ? pSQL(date('Y-m-d H:i:s', $firstIn)) : null, 'last_out' => $lastOut ? pSQL(date('Y-m-d H:i:s', $lastOut)) : null,
            'pairs' => count($intervals), 'raw_minutes' => $rawMinutes, 'break_minutes' => $breakMinutes, 'worked_minutes' => $workedMinutes,
            'rounded_minutes' => $roundedDelta, 'scheduled_minutes' => $scheduled, 'late_minutes' => $lateMinutes, 'early_out_minutes' => $earlyOut, 'short_minutes' => $short,
            'ot_minutes' => $otTotal, 'ot_daily_minutes' => $otDaily, 'ot_weekly_minutes' => $otWeekly, 'ot_restday_minutes' => $otRest, 'ot_holiday_minutes' => $otHoliday,
            'ot_weighted_minutes' => (int) round($weighted), 'night_minutes' => $nightMinutes,
            'day_type' => pSQL($w['day_type']), 'status' => pSQL($status), 'adjustments' => count($adj),
            'sources' => pSQL(Tools::substr(implode(',', array_keys($sources)), 0, 64)),
            'built_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        );
        if ($idTs) { Db::getInstance()->update(self::T, PulseTaService::nulls($row), 'id_pulse_ta_timesheet='.$idTs); }
        else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert(self::T, PulseTaService::nulls($row)); $idTs = (int) Db::getInstance()->Insert_ID(); }

        /* --- 9. link the punches this timesheet consumed --- */
        Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.self::T_LINK.'` WHERE id_pulse_ta_timesheet='.$idTs);
        $claimed = array(); $i = 0;
        foreach ($seq as $s) {
            $role = $s['role'];
            if ($s['raw_direction'] === 'break_out') { $role = 'break_out'; } elseif ($s['raw_direction'] === 'break_in') { $role = 'break_in'; }
            Db::getInstance()->insert(self::T_LINK, array('id_pulse_ta_timesheet' => $idTs, 'id_pulse_ta_punch' => (int) $s['id'], 'seq' => $i++,
                'role' => pSQL($role), 'virtual' => $s['source'] === 'adjustment' ? 1 : 0), false, true, Db::INSERT_IGNORE);
            $claimed[] = (int) $s['id'];
        }
        foreach ($ignored as $p) {
            Db::getInstance()->insert(self::T_LINK, array('id_pulse_ta_timesheet' => $idTs, 'id_pulse_ta_punch' => (int) $p['id_pulse_ta_punch'], 'seq' => $i++,
                'role' => 'ignored', 'virtual' => 0), false, true, Db::INSERT_IGNORE);
            $claimed[] = (int) $p['id_pulse_ta_punch'];
        }

        /* --- 10. a later, unlocked timesheet may have been holding one of these punches: take it back and rebuild it --- */
        if ($claimed && $depth < 1) {
            $stolen = Db::getInstance()->executeS('SELECT DISTINCT t.id_pulse_ta_staff, t.business_date FROM `'._DB_PREFIX_.self::T_LINK.'` tp
                INNER JOIN `'._DB_PREFIX_.self::T.'` t ON t.id_pulse_ta_timesheet=tp.id_pulse_ta_timesheet
                WHERE tp.id_pulse_ta_punch IN ('.implode(',', array_map('intval', $claimed)).') AND t.id_pulse_ta_timesheet<>'.$idTs.' AND t.locked=0');
            foreach ((array) $stolen as $st) {
                Db::getInstance()->execute('DELETE tp FROM `'._DB_PREFIX_.self::T_LINK.'` tp
                    INNER JOIN `'._DB_PREFIX_.self::T.'` t ON t.id_pulse_ta_timesheet=tp.id_pulse_ta_timesheet
                    WHERE t.id_pulse_ta_staff='.(int) $st['id_pulse_ta_staff'].' AND t.business_date="'.pSQL($st['business_date']).'"
                      AND tp.id_pulse_ta_punch IN ('.implode(',', array_map('intval', $claimed)).')');
                self::buildOne((int) $st['id_pulse_ta_staff'], $st['business_date'], $depth + 1);
            }
        }

        /* --- 11. exceptions --- */
        $keep = self::raiseExceptions($staff, $date, $idTs, $w, $seq, $missingIn, $missingOut, $lateMinutes, $earlyOut, $workedMinutes, $intervals, $dupes, $status);
        PulseTaExceptionQueue::autoCloseFor($idStaff, $date, $keep);
        Db::getInstance()->update(self::T, array('has_exception' => count($keep) ? 1 : 0), 'id_pulse_ta_timesheet='.$idTs);

        $row['id_pulse_ta_timesheet'] = $idTs; $row['has_exception'] = count($keep) ? 1 : 0; $row['exceptions'] = count($keep);
        return $row;
    }

    /** Everything a supervisor needs to look at for this person on this day. Returns the ids raised. */
    protected static function raiseExceptions($staff, $date, $idTs, $w, $seq, $missingIn, $missingOut, $late, $earlyOut, $worked, $intervals, $dupes, $status)
    {
        $idStaff = (int) $staff['id_pulse_ta_staff'];
        $shift = $w['shift'];
        $keep = array();
        $lateThreshold = (int) self::cfg('LATE_EXCEPTION_MIN', 30);
        $work = ($w['day_type'] === 'work');

        if ($work && !$seq) {
            $keep[] = PulseTaExceptionQueue::raise($idStaff, $date, 'no_punches', 'block',
                'Rostered '.($shift ? $shift['code'].' '.Tools::substr($shift['start_time'], 0, 5).'–'.Tools::substr($shift['end_time'], 0, 5) : 'to work').' but no punch was recorded. Record the shift or mark the absence.', 0, $idTs);
        }
        if ($missingOut > 0) {
            $keep[] = PulseTaExceptionQueue::raise($idStaff, $date, 'missing_out', 'block',
                $missingOut.' clock-out(s) missing — last punch '.($seq ? date('H:i', $seq[count($seq) - 1]['ts']) : 'n/a').'. Add the clock-out the supervisor witnessed.', 0, $idTs);
        }
        if ($missingIn > 0) {
            $keep[] = PulseTaExceptionQueue::raise($idStaff, $date, 'missing_in', 'block',
                $missingIn.' clock-in(s) missing — the first punch of the shift was an out. Add the clock-in or ignore the stray punch.', 0, $idTs);
        }
        if ($work && $late > $lateThreshold) {
            $keep[] = PulseTaExceptionQueue::raise($idStaff, $date, 'late', 'info', 'Late by '.$late.' minute(s) beyond the grace period.', $late, $idTs);
        }
        if ($work && $earlyOut > $lateThreshold) {
            $keep[] = PulseTaExceptionQueue::raise($idStaff, $date, 'early_out', 'info', 'Left '.$earlyOut.' minute(s) before the end of the shift.', $earlyOut, $idTs);
        }
        if ($shift && $work && $worked > 0 && $worked < (int) $shift['min_shift_min']) {
            $keep[] = PulseTaExceptionQueue::raise($idStaff, $date, 'short_shift', 'warn',
                'Worked '.$worked.' min against a minimum shift of '.(int) $shift['min_shift_min'].' min.', $worked, $idTs);
        }
        if ($shift && $worked > (int) $shift['max_shift_min'] && (int) $shift['max_shift_min'] > 0) {
            $keep[] = PulseTaExceptionQueue::raise($idStaff, $date, 'overlong_shift', 'block',
                'Paired to '.round($worked / 60, 1).' h against a maximum of '.round((int) $shift['max_shift_min'] / 60, 1).' h — almost certainly a missed clock-out.', $worked, $idTs);
        }
        if ($dupes > 0) {
            $keep[] = PulseTaExceptionQueue::raise($idStaff, $date, 'duplicate_punch', 'info',
                $dupes.' punch(es) within '.(int) self::cfg('MIN_GAP_SEC', 60).'s of the previous one were ignored as double taps.', $dupes, $idTs);
        }
        if ((int) self::cfg('POS_RECONCILE', 1) && PulseTaService::pos()) {
            $tol = max(1, (int) self::cfg('POS_TOLERANCE_MIN', 20));
            $pos = PulseTaPunch::posClock($idStaff, date('Y-m-d H:i:s', $w['window_from']), date('Y-m-d H:i:s', $w['window_to']));
            foreach ((array) $pos as $c) {
                $pin = strtotime($c['clock_in']);
                $firstIn = null;
                foreach ($seq as $s) { if ($s['role'] === 'in') { $firstIn = $s['ts']; break; } }
                if ($firstIn === null) {
                    $keep[] = PulseTaExceptionQueue::raise($idStaff, $date, 'pos_mismatch', 'warn',
                        'Clocked into the POS at '.date('H:i', $pin).' but there is no biometric punch — the reader may have missed them.', 0, $idTs, null, 'pos'.(int) $c['id_pulse_pos_clock']);
                } elseif (abs($firstIn - $pin) > $tol * 60) {
                    $keep[] = PulseTaExceptionQueue::raise($idStaff, $date, 'pos_mismatch', 'info',
                        'POS clock-in '.date('H:i', $pin).' vs biometric '.date('H:i', $firstIn).' — '.abs((int) round(($firstIn - $pin) / 60)).' min apart.',
                        abs((int) round(($firstIn - $pin) / 60)), $idTs, null, 'pos'.(int) $c['id_pulse_pos_clock']);
                }
            }
        }
        return array_values(array_filter($keep));
    }

    /**
     * Build every active staff member's timesheet for one business date.
     * Built in staff order; each person's own days are independent, so this is safe to re-run.
     */
    public static function buildDay($date, $department = '')
    {
        $staff = PulseTaService::staffList($department, 'active');
        $n = 0; $exceptions = 0; $locked = 0; $errors = array();
        foreach ($staff as $s) {
            try {
                $r = self::buildOne((int) $s['id_pulse_ta_staff'], $date);
                if (isset($r['skipped']) && $r['skipped'] === 'period locked') { $locked++; continue; }
                $n++;
                $exceptions += isset($r['exceptions']) ? (int) $r['exceptions'] : 0;
            } catch (Exception $e) { $errors[] = $s['staff_no'].': '.$e->getMessage(); }
        }
        self::flagOfflineDevices($date);
        return array('date' => $date, 'timesheets' => $n, 'exceptions' => $exceptions, 'locked' => $locked, 'errors' => $errors);
    }

    /** Rebuild a whole range, oldest first — the order matters because a night shift claims across midnight. */
    public static function buildRange($from, $to, $department = '')
    {
        $out = array('days' => 0, 'timesheets' => 0, 'exceptions' => 0, 'errors' => array());
        for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
            $r = self::buildDay(date('Y-m-d', $t), $department);
            $out['days']++; $out['timesheets'] += $r['timesheets']; $out['exceptions'] += $r['exceptions'];
            $out['errors'] = array_merge($out['errors'], $r['errors']);
        }
        return $out;
    }

    /** A reader that stopped reporting is itself an exception, or a whole department's day silently vanishes. */
    protected static function flagOfflineDevices($date)
    {
        $stale = (int) self::cfg('DEVICE_STALE_MIN', 60);
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_device` WHERE status="active"
            AND (last_seen_at IS NULL OR last_seen_at<DATE_SUB(NOW(), INTERVAL '.max(5, $stale).' MINUTE))');
        foreach ((array) $rows as $d) {
            PulseTaExceptionQueue::raise(null, $date, 'device_offline', 'warn',
                'Clocking device "'.$d['name'].'" ('.$d['location'].') last reported '.($d['last_seen_at'] ? $d['last_seen_at'] : 'never').'. Punches made on it since then are not in Pulse yet.',
                0, null, null, 'dev'.(int) $d['id_pulse_ta_device']);
        }
        return count((array) $rows);
    }
}
