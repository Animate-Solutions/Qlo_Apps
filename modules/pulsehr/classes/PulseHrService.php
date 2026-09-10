<?php
/**
 * HR shared helpers: cross-module guards, business date, settings, sequences, rate limiting,
 * the org lookups every screen needs, occupancy-driven roster coverage and the HR dashboard figures.
 * Everything cross-module is optional — the module installs and runs on its own.
 */
class PulseHrService
{
    const TZ = 'Africa/Lagos';

    /* ---------- guards ---------- */
    public static function fd() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFolio'); }
    public static function kc() { return Module::isEnabled('pulsekeycard') && class_exists('PulseKcStaff'); }
    public static function pos() { return Module::isEnabled('pulsepos') && self::tableExists('pulse_pos_staff'); }
    /** Pulse Time owns the punch store when it is installed; we hand mobile punches over and stop being the truth. */
    public static function ta() { return Module::isEnabled('pulsetime') && self::tableExists('pulse_ta_punch'); }
    public static function pr() { return Module::isEnabled('pulsepayroll') && self::tableExists('pulse_pr_payslip'); }
    public static function comms() { return class_exists('PulseComms'); }

    public static function db() { return Db::getInstance(); }
    public static function bd() { return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    public static function emp() { $c = Context::getContext(); return isset($c->employee) && $c->employee->id ? (int) $c->employee->id : 0; }
    public static function cfg($k, $default = null) { $v = Configuration::get('PULSE_HR_'.$k); return ($v === false || $v === '') ? $default : $v; }

    /** Cached table-existence probe — every cross-module read asks before it joins. */
    public static function tableExists($table)
    {
        static $seen = array();
        if (!isset($seen[$table])) { $seen[$table] = (bool) Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.pSQL($table).'"'); }
        return $seen[$table];
    }

    public static function nextNo($prefix, $width = 4)
    {
        $n = (int) PulseCoreService::setting('pulsehr', 'seq_'.$prefix) + 1;
        PulseCoreService::setting('pulsehr', 'seq_'.$prefix, $n);
        return $prefix.date('ym').str_pad($n % (int) pow(10, $width), $width, '0', STR_PAD_LEFT);
    }

    /** The house PIN hash — the same one PulsePosService::login uses, so one PIN can serve both. */
    public static function pinHash($pin) { return hash('sha256', $pin.'|'._COOKIE_KEY_); }

    /** Fixed-window rate limiter shared by the ESS login and the mobile punch. Throws 429 when the bucket overflows. */
    public static function rateHit($bucket, $limit = null)
    {
        $limit = (int) ($limit === null ? self::cfg('ESS_RATE_PER_MIN', 30) : $limit);
        if ($limit <= 0) { return true; }
        $w = (int) floor(time() / 60); $b = pSQL(Tools::substr($bucket, 0, 64));
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_hr_rate` (`bucket`,`window_start`,`hits`) VALUES ("'.$b.'",'.$w.',1) ON DUPLICATE KEY UPDATE `hits`=`hits`+1');
        $hits = (int) Db::getInstance()->getValue('SELECT hits FROM `'._DB_PREFIX_.'pulse_hr_rate` WHERE bucket="'.$b.'" AND window_start='.$w);
        if ($hits > $limit) { throw new PrestaShopException('Too many requests — wait a minute and try again', 429); }
        if (($w % 30) === 0) { Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_hr_rate` WHERE window_start<'.($w - 120)); }
        return true;
    }

    /* ---------- org lookups ---------- */
    public static function departments($activeOnly = true) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_department`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY sort, name'); }
    public static function department($code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_department` WHERE code="'.pSQL($code).'"'); }
    public static function departmentById($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_department` WHERE id_pulse_hr_department='.(int) $id); }
    public static function sections($idDept = null) { return Db::getInstance()->executeS('SELECT s.*, d.code dept_code FROM `'._DB_PREFIX_.'pulse_hr_section` s INNER JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=s.id_pulse_hr_department WHERE s.active=1'.($idDept ? ' AND s.id_pulse_hr_department='.(int) $idDept : '').' ORDER BY d.sort, s.name'); }
    public static function grades($activeOnly = true) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_grade`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY level'); }
    public static function positions($idDept = null) { return Db::getInstance()->executeS('SELECT p.*, d.code dept_code, d.name dept_name, g.code grade_code, g.name grade_name FROM `'._DB_PREFIX_.'pulse_hr_position` p INNER JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=p.id_pulse_hr_department LEFT JOIN `'._DB_PREFIX_.'pulse_hr_grade` g ON g.id_pulse_hr_grade=p.id_pulse_hr_grade WHERE p.active=1'.($idDept ? ' AND p.id_pulse_hr_department='.(int) $idDept : '').' ORDER BY d.sort, p.title'); }
    public static function shifts($activeOnly = true) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_shift`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY sort, code'); }
    public static function shift($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_shift` WHERE id_pulse_hr_shift='.(int) $id); }

    public static function saveDepartment(array $d, $id = 0)
    {
        $row = array('code' => pSQL(Tools::strtolower(preg_replace('/[^A-Za-z0-9_]/', '', $d['code']))), 'name' => pSQL($d['name']), 'cost_centre' => pSQL(isset($d['cost_centre']) ? $d['cost_centre'] : ''),
            'credit_minutes_per_room' => (int) (isset($d['credit_minutes_per_room']) ? $d['credit_minutes_per_room'] : 0), 'id_head' => isset($d['id_head']) && $d['id_head'] ? (int) $d['id_head'] : null,
            'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0), 'active' => isset($d['active']) ? (int) $d['active'] : 1);
        if (!$row['code'] || !$row['name']) { throw new PrestaShopException('A department needs a code and a name'); }
        if ($id) { Db::getInstance()->update('pulse_hr_department', $row, 'id_pulse_hr_department='.(int) $id, 0, true); } else { Db::getInstance()->insert('pulse_hr_department', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    public static function saveGrade(array $d, $id = 0)
    {
        $row = array('code' => pSQL($d['code']), 'name' => pSQL($d['name']), 'level' => (int) (isset($d['level']) ? $d['level'] : 1),
            'salary_min' => round((float) (isset($d['salary_min']) ? $d['salary_min'] : 0), 2), 'salary_max' => round((float) (isset($d['salary_max']) ? $d['salary_max'] : 0), 2),
            'annual_leave_days' => (float) (isset($d['annual_leave_days']) ? $d['annual_leave_days'] : self::cfg('ANNUAL_LEAVE_DAYS', 21)),
            'notice_days' => (int) (isset($d['notice_days']) ? $d['notice_days'] : 30), 'active' => isset($d['active']) ? (int) $d['active'] : 1);
        if (!$row['code'] || !$row['name']) { throw new PrestaShopException('A grade needs a code and a name'); }
        if ($row['salary_max'] > 0 && $row['salary_max'] < $row['salary_min']) { throw new PrestaShopException('The top of the band cannot be below the bottom'); }
        if ($id) { Db::getInstance()->update('pulse_hr_grade', $row, 'id_pulse_hr_grade='.(int) $id, 0, true); } else { Db::getInstance()->insert('pulse_hr_grade', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    public static function savePosition(array $d, $id = 0)
    {
        $row = array('code' => pSQL($d['code']), 'title' => pSQL($d['title']), 'id_pulse_hr_department' => (int) $d['id_pulse_hr_department'],
            'id_pulse_hr_section' => !empty($d['id_pulse_hr_section']) ? (int) $d['id_pulse_hr_section'] : null, 'id_pulse_hr_grade' => !empty($d['id_pulse_hr_grade']) ? (int) $d['id_pulse_hr_grade'] : null,
            'establishment' => (int) (isset($d['establishment']) ? $d['establishment'] : 1), 'night_shift' => !empty($d['night_shift']) ? 1 : 0, 'active' => isset($d['active']) ? (int) $d['active'] : 1);
        if (!$row['code'] || !$row['title'] || !$row['id_pulse_hr_department']) { throw new PrestaShopException('A position needs a code, a title and a department'); }
        if ($id) { Db::getInstance()->update('pulse_hr_position', $row, 'id_pulse_hr_position='.(int) $id, 0, true); } else { Db::getInstance()->insert('pulse_hr_position', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    public static function saveSection(array $d, $id = 0)
    {
        $row = array('id_pulse_hr_department' => (int) $d['id_pulse_hr_department'], 'code' => pSQL($d['code']), 'name' => pSQL($d['name']), 'active' => isset($d['active']) ? (int) $d['active'] : 1);
        if (!$row['code'] || !$row['name'] || !$row['id_pulse_hr_department']) { throw new PrestaShopException('A section needs a department, a code and a name'); }
        if ($id) { Db::getInstance()->update('pulse_hr_section', $row, 'id_pulse_hr_section='.(int) $id, 0, true); } else { Db::getInstance()->insert('pulse_hr_section', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    public static function saveShift(array $d, $id = 0)
    {
        $row = array('code' => pSQL($d['code']), 'name' => pSQL($d['name']), 'start_time' => pSQL(self::time($d['start_time'])), 'end_time' => pSQL(self::time($d['end_time'])),
            'break_minutes' => (int) (isset($d['break_minutes']) ? $d['break_minutes'] : 0), 'night' => !empty($d['night']) ? 1 : 0, 'split' => !empty($d['split']) ? 1 : 0, 'on_call' => !empty($d['on_call']) ? 1 : 0,
            'department' => !empty($d['department']) ? pSQL($d['department']) : null, 'colour' => pSQL(isset($d['colour']) ? $d['colour'] : '#5bc0de'), 'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0), 'active' => isset($d['active']) ? (int) $d['active'] : 1);
        $row['paid_hours'] = isset($d['paid_hours']) && $d['paid_hours'] !== '' ? round((float) $d['paid_hours'], 2) : self::shiftHours($row['start_time'], $row['end_time'], $row['break_minutes']);
        if (!$row['code'] || !$row['name']) { throw new PrestaShopException('A shift needs a code and a name'); }
        if ($id) { Db::getInstance()->update('pulse_hr_shift', $row, 'id_pulse_hr_shift='.(int) $id, 0, true); } else { Db::getInstance()->insert('pulse_hr_shift', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    public static function time($t) { $t = trim((string) $t); return preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $t) ? (strlen($t) === 5 ? $t.':00' : $t) : '00:00:00'; }

    /** Paid hours for a shift, handling the 22:00–06:00 case that hotels actually run. */
    public static function shiftHours($start, $end, $breakMinutes = 0)
    {
        $s = strtotime('2000-01-01 '.$start); $e = strtotime('2000-01-01 '.$end);
        if ($e <= $s) { $e += 86400; }
        return round(max(0, ($e - $s) / 3600 - (int) $breakMinutes / 60), 2);
    }

    /** Working days between two dates, Sunday off by default (Nigerian hotels run six-day weeks). */
    public static function workingDays($from, $to)
    {
        $d = strtotime($from); $end = strtotime($to); $rest = (int) self::cfg('REST_DAY', 7); $n = 0;
        while ($d <= $end) { if ((int) date('N', $d) !== $rest) { $n++; } $d = strtotime('+1 day', $d); }
        return $n;
    }
    public static function calendarDays($from, $to) { return max(0, (int) round((strtotime($to) - strtotime($from)) / 86400) + 1); }

    /** Metres between two WGS84 points — the geofence maths for a mobile punch. */
    public static function distanceMetres($lat1, $lng1, $lat2, $lng2)
    {
        $r = 6371000.0; $p1 = deg2rad((float) $lat1); $p2 = deg2rad((float) $lat2);
        $dp = deg2rad((float) $lat2 - (float) $lat1); $dl = deg2rad((float) $lng2 - (float) $lng1);
        $a = sin($dp / 2) * sin($dp / 2) + cos($p1) * cos($p2) * sin($dl / 2) * sin($dl / 2);
        return round($r * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a))), 2);
    }

    /* ---------- occupancy (Front Desk when present, bookings otherwise) ---------- */

    /** Rooms occupied on a date: the closed night audit first, then live bookings, then nothing. */
    public static function roomsOccupied($date)
    {
        if (self::tableExists('pulse_night_audit')) {
            $n = Db::getInstance()->getRow('SELECT rooms_occupied, rooms_total, rooms_ooo FROM `'._DB_PREFIX_.'pulse_night_audit` WHERE business_date="'.pSQL($date).'" AND status="closed"');
            if ($n) { return array('occupied' => (int) $n['rooms_occupied'], 'total' => (int) $n['rooms_total'], 'ooo' => (int) $n['rooms_ooo'], 'source' => 'night audit'); }
        }
        if (!self::tableExists('htl_booking_detail')) { return array('occupied' => 0, 'total' => 0, 'ooo' => 0, 'source' => 'none'); }
        $occ = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_cancelled=0 AND is_refunded=0 AND date_from<="'.pSQL($date).'" AND date_to>"'.pSQL($date).'"');
        $tot = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information`');
        return array('occupied' => $occ, 'total' => $tot, 'ooo' => 0, 'source' => 'bookings');
    }

    public static function occupancyPct($date)
    {
        $o = self::roomsOccupied($date); $avail = max(1, $o['total'] - $o['ooo']);
        return $o['total'] ? round($o['occupied'] / $avail * 100, 1) : 0;
    }

    /**
     * Coverage for one date and department: heads rostered against heads the occupancy asks for.
     * A department with credit_minutes_per_room = 0 is not room-driven, so it only reports what is rostered.
     */
    public static function coverage($date, $dept = null)
    {
        $occ = self::roomsOccupied($date);
        $shiftMin = max(60, (int) self::cfg('SHIFT_MINUTES', 480));
        $out = array();
        foreach (self::departments() as $d) {
            if ($dept && $d['code'] !== $dept) { continue; }
            $rostered = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_roster` WHERE roster_date="'.pSQL($date).'" AND department="'.pSQL($d['code']).'" AND is_off=0 AND status IN ("planned","published")');
            $onLeave = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_leave_request` r INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=r.id_pulse_hr_employee
                INNER JOIN `'._DB_PREFIX_.'pulse_hr_department` dp ON dp.id_pulse_hr_department=e.id_pulse_hr_department
                WHERE r.status IN ("approved","taken") AND dp.code="'.pSQL($d['code']).'" AND "'.pSQL($date).'" BETWEEN r.date_from AND r.date_to');
            $needed = (int) $d['credit_minutes_per_room'] > 0 ? (int) ceil($occ['occupied'] * (int) $d['credit_minutes_per_room'] / $shiftMin) : 0;
            $out[] = array('code' => $d['code'], 'name' => $d['name'], 'rostered' => $rostered, 'on_leave' => $onLeave, 'needed' => $needed,
                'short' => $needed > 0 ? max(0, $needed - $rostered) : 0, 'room_driven' => (int) $d['credit_minutes_per_room'] > 0 ? 1 : 0, 'occupied' => $occ['occupied']);
        }
        return $out;
    }

    /* ---------- dashboard ---------- */

    /** Everything the HR landing screen shows, in one pass. */
    public static function dashboard()
    {
        $db = Db::getInstance(); $today = self::bd();
        $heads = $db->executeS('SELECT d.code, d.name, COUNT(e.id_pulse_hr_employee) headcount,
                (SELECT COALESCE(SUM(p.establishment),0) FROM `'._DB_PREFIX_.'pulse_hr_position` p WHERE p.id_pulse_hr_department=d.id_pulse_hr_department AND p.active=1) establishment
            FROM `'._DB_PREFIX_.'pulse_hr_department` d
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_department=d.id_pulse_hr_department AND e.status<>"exited"
            WHERE d.active=1 GROUP BY d.id_pulse_hr_department ORDER BY d.sort');
        foreach ($heads as &$h) { $h['variance'] = (int) $h['headcount'] - (int) $h['establishment']; }
        unset($h);
        return array(
            'business_date' => $today,
            'headcount' => $heads,
            'total' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE status<>"exited"'),
            'probation' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE status="probation"'),
            'suspended' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE status="suspended"'),
            'on_leave_today' => PulseHrLeave::onLeave($today),
            'probation_due' => PulseHrEmployee::probationDue((int) self::cfg('PROBATION_REMIND_DAYS', 30)),
            'doc_expiry' => PulseHrDocument::expiring((int) self::cfg('DOC_REMIND_DAYS', 30)),
            'contract_expiry' => PulseHrContract::expiring((int) self::cfg('CONTRACT_REMIND_DAYS', 30)),
            'pending_leave' => PulseHrLeave::pending(),
            'open_tasks' => PulseHrLifecycle::openTasks(15),
            'coverage' => self::coverage($today),
            'occupancy' => self::roomsOccupied($today),
            'birthdays' => PulseHrEmployee::birthdays(7),
            'flagged_punches' => PulseHrEss::flaggedPunches($today, 10),
            'change_requests' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_change_request` WHERE status="pending"'),
            'labour' => PulseHrReport::labourPerOccupiedRoom(date('Y-m-d', strtotime($today.' -6 day')), $today),
        );
    }
}
