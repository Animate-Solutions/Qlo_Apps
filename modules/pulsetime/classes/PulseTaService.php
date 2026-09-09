<?php
/**
 * Time & Attendance facade: settings, the local roster mirror, the live board, the retry queue and the
 * cross-module guards.
 *
 * Pulse HR owns the employee record when it is installed. This module never depends on it at runtime:
 * `pulse_ta_staff` is always the canonical key inside Time & Attendance, and syncRoster() mirrors HR into it.
 * With HR absent the same table is maintained by hand on the Enrolment screen and everything else — devices,
 * punches, pairing, exceptions, timesheets, approval — works unchanged.
 */
class PulseTaService
{
    /** Pulse HR present? (employee master, contracts, published roster). */
    public static function hr() { return Module::isEnabled('pulsehr') && class_exists('PulseHrService'); }
    /** Front Desk present? (business date, occupancy for the labour-per-room figure). */
    public static function fd() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFdService'); }
    /** POS present? pulse_pos_clock is another punch source to reconcile against, not a competing truth. */
    public static function pos() { return Module::isEnabled('pulsepos') && self::tableExists('pulse_pos_clock'); }

    public static function tableExists($t)
    {
        static $cache = array();
        if (!isset($cache[$t])) { $cache[$t] = (bool) Db::getInstance()->getValue('SHOW TABLES LIKE "'._DB_PREFIX_.pSQL($t).'"'); }
        return $cache[$t];
    }

    public static function bd() { return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    public static function emp() { $c = Context::getContext(); return isset($c->employee) && $c->employee->id ? (int) $c->employee->id : 0; }
    public static function cfg($k, $default = null) { $v = Configuration::get('PULSE_TA_'.$k); return ($v === false || $v === null || $v === '') ? $default : $v; }
    public static function audit($event, $payload = null, $entity = null, $idEntity = null) { return PulseCoreService::audit('pulsetime', $event, $payload, $entity, $idEntity); }

    /**
     * Make PHP nulls explicit before a Db::insert/update.
     *
     * Db::insert()/update() default to $null_values = false, which quotes a PHP null as '' — 0 in a nullable
     * INT, 0000-00-00 in a nullable DATE, and a hard error on a strict server. That silently breaks every
     * `IS NULL` test in this module (an unmatched punch, an unmapped enrolment, an open-ended overtime rule).
     * Passing $null_values = true instead is not the answer: it would turn every legitimate '' into NULL and
     * so break the NOT NULL DEFAULT '' columns. So nulls are converted to the SQL literal here and the flag
     * stays off. Db handles array('type' => 'sql') on both paths.
     */
    public static function nulls(array $row)
    {
        foreach ($row as $k => $v) { if ($v === null) { $row[$k] = array('type' => 'sql', 'value' => 'NULL'); } }
        return $row;
    }

    /** Departments the suite already uses, so a Time & Attendance department matches an HR and a Payroll one. */
    public static function departments()
    {
        return array('rooms' => 'Rooms / Front Office', 'housekeeping' => 'Housekeeping', 'fnb' => 'Food & Beverage', 'kitchen' => 'Kitchen',
            'laundry' => 'Laundry', 'maintenance' => 'Maintenance', 'security' => 'Security', 'sales' => 'Sales & Marketing',
            'accounts' => 'Accounts', 'admin' => 'Administration', 'management' => 'Management');
    }

    /** The URL a push device must be pointed at. Shown on the Devices screen and in the README. */
    public static function pushUrl()
    {
        $base = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__;
        return rtrim($base, '/').'/iclock/';
    }

    /**
     * Call one adapter method on a device row. Kept here so every call site — poller, cron, admin screen,
     * API — goes through the same place and a device in test mode never reaches real hardware for a write.
     */
    public static function runAdapter($dev, $method, array $args = array())
    {
        if (!is_array($dev)) { $dev = PulseTaDevice::get($dev); }
        if (!$dev) { throw new PulseTaDeviceException('Device not found', PulseTaDeviceException::NOT_CONFIGURED); }
        $writes = array('pushUser', 'deleteUser', 'clearLog', 'syncTime');
        if ((int) $dev['test_mode'] && in_array($method, $writes, true)) {
            return array('ok' => true, 'test_mode' => 1, 'note' => 'Device is in test mode — '.$method.' was not sent to the hardware.');
        }
        $adapter = PulseTaDevice::adapter($dev);
        return call_user_func_array(array($adapter, $method), $args);
    }

    /* ---------- shifts ---------- */

    public static function shifts($activeOnly = true)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_shift`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY sort, code');
    }
    public static function shift($id) { return $id ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_shift` WHERE id_pulse_ta_shift='.(int) $id) : null; }
    public static function shiftByCode($code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_shift` WHERE code="'.pSQL($code).'"'); }

    /** The fallback shift for a staff member with no roster row: their own default, else the department default, else the configured one. */
    public static function defaultShift($staff = null)
    {
        if (is_array($staff) && !empty($staff['id_pulse_ta_shift']) && ($s = self::shift((int) $staff['id_pulse_ta_shift']))) { return $s; }
        if (is_array($staff) && !empty($staff['department'])) {
            $s = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_shift` WHERE active=1 AND department="'.pSQL($staff['department']).'" ORDER BY sort LIMIT 1');
            if ($s) { return $s; }
        }
        $s = self::shiftByCode((string) self::cfg('DEFAULT_SHIFT', 'GEN'));
        return $s ? $s : Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_shift` WHERE active=1 ORDER BY sort LIMIT 1');
    }

    /* ---------- staff / local roster mirror ---------- */

    public static function staff($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_staff` WHERE id_pulse_ta_staff='.(int) $id); }
    public static function staffByHr($idHr) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_staff` WHERE id_hr_employee='.(int) $idHr); }
    public static function staffByNo($no) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_staff` WHERE staff_no="'.pSQL($no).'"'); }

    public static function staffList($department = '', $status = 'active', $q = '')
    {
        return Db::getInstance()->executeS('SELECT s.*, sh.code shift_code, sh.name shift_name,
            (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_enrolment` e WHERE e.id_pulse_ta_staff=s.id_pulse_ta_staff AND e.status<>"removed") enrolments
            FROM `'._DB_PREFIX_.'pulse_ta_staff` s LEFT JOIN `'._DB_PREFIX_.'pulse_ta_shift` sh ON sh.id_pulse_ta_shift=s.id_pulse_ta_shift
            WHERE 1'.($department ? ' AND s.department="'.pSQL($department).'"' : '').($status ? ' AND s.status="'.pSQL($status).'"' : '')
            .($q ? ' AND (s.staff_no LIKE "%'.pSQL($q).'%" OR s.firstname LIKE "%'.pSQL($q).'%" OR s.lastname LIKE "%'.pSQL($q).'%")' : '')
            .' ORDER BY s.department, s.lastname, s.firstname');
    }

    /** Create or update one local staff row. $d: staff_no, firstname, lastname, department, position, id_hr_employee, id_employee, id_pulse_ta_shift, pay_basis, rates. */
    public static function saveStaff(array $d, $id = 0)
    {
        $row = array(
            'staff_no' => pSQL(Tools::substr(trim((string) $d['staff_no']), 0, 24)),
            'firstname' => pSQL(Tools::substr((string) (isset($d['firstname']) ? $d['firstname'] : ''), 0, 64)),
            'lastname' => pSQL(Tools::substr((string) (isset($d['lastname']) ? $d['lastname'] : ''), 0, 64)),
            'department' => pSQL(isset($d['department']) ? $d['department'] : 'admin'),
            'section' => pSQL(Tools::substr((string) (isset($d['section']) ? $d['section'] : ''), 0, 48)),
            'position' => pSQL(Tools::substr((string) (isset($d['position']) ? $d['position'] : ''), 0, 64)),
            'id_hr_employee' => !empty($d['id_hr_employee']) ? (int) $d['id_hr_employee'] : null,
            'id_employee' => !empty($d['id_employee']) ? (int) $d['id_employee'] : null,
            'id_pos_staff' => !empty($d['id_pos_staff']) ? (int) $d['id_pos_staff'] : null,
            'id_pulse_ta_shift' => !empty($d['id_pulse_ta_shift']) ? (int) $d['id_pulse_ta_shift'] : null,
            'pay_basis' => pSQL(isset($d['pay_basis']) ? $d['pay_basis'] : 'monthly'),
            'hourly_rate' => round((float) (isset($d['hourly_rate']) ? $d['hourly_rate'] : 0), 2),
            'daily_rate' => round((float) (isset($d['daily_rate']) ? $d['daily_rate'] : 0), 2),
            'ot_eligible' => isset($d['ot_eligible']) ? (int) $d['ot_eligible'] : 1,
            'source' => pSQL(isset($d['source']) ? $d['source'] : 'local'),
            'status' => pSQL(isset($d['status']) ? $d['status'] : 'active'),
            'exit_date' => !empty($d['exit_date']) ? pSQL($d['exit_date']) : null,
            'date_upd' => date('Y-m-d H:i:s'),
        );
        if ($row['staff_no'] === '') { throw new PrestaShopException('A staff number is required'); }
        if ($id) { Db::getInstance()->update('pulse_ta_staff', self::nulls($row), 'id_pulse_ta_staff='.(int) $id); }
        else {
            $existing = self::staffByNo($row['staff_no']);
            if ($existing) { $id = (int) $existing['id_pulse_ta_staff']; Db::getInstance()->update('pulse_ta_staff', self::nulls($row), 'id_pulse_ta_staff='.$id); }
            else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_ta_staff', self::nulls($row)); $id = (int) Db::getInstance()->Insert_ID(); }
        }
        self::audit('staff_save', array('id' => $id, 'staff_no' => $row['staff_no']), 'pulse_ta_staff', $id);
        return $id;
    }

    /**
     * Mirror the Pulse HR employee master into the local roster. Safe to call when HR is absent — it simply
     * reports zero. HR-sourced rows are marked source='hr' so a hand-keyed row is never overwritten.
     */
    public static function syncRoster()
    {
        if (!self::hr() || !self::tableExists('pulse_hr_employee')) { return array('synced' => 0, 'skipped' => 0, 'hr' => false); }
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_employee`');
        $n = 0; $skipped = 0;
        foreach ((array) $rows as $e) { if (self::mirror($e)) { $n++; } else { $skipped++; } }
        return array('synced' => $n, 'skipped' => $skipped, 'hr' => true);
    }

    public static function syncOne($idHrEmployee)
    {
        if (!self::hr() || !self::tableExists('pulse_hr_employee')) { return 0; }
        $e = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE id_pulse_hr_employee='.(int) $idHrEmployee);
        return $e ? self::mirror($e) : 0;
    }

    /** One HR row -> one local staff row. Column names are read defensively: HR may evolve its schema. */
    protected static function mirror(array $e)
    {
        $g = function ($k, $d = '') use ($e) { return isset($e[$k]) && $e[$k] !== null ? $e[$k] : $d; };
        $idHr = (int) $g('id_pulse_hr_employee', 0);
        if (!$idHr) { return 0; }
        $no = (string) $g('staff_no', $g('employee_no', 'HR'.$idHr));
        $existing = self::staffByHr($idHr);
        $status = (string) $g('status', 'active');
        $map = array('active' => 'active', 'probation' => 'active', 'on_leave' => 'active', 'suspended' => 'suspended', 'exited' => 'exited');
        return self::saveStaff(array(
            'staff_no' => $no, 'firstname' => $g('firstname'), 'lastname' => $g('lastname'), 'department' => $g('department', 'admin'),
            'section' => $g('section'), 'position' => $g('position', $g('job_title', '')), 'id_hr_employee' => $idHr,
            'id_employee' => (int) $g('id_employee', 0) ?: null, 'id_pos_staff' => (int) $g('id_pos_staff', 0) ?: null,
            'pay_basis' => in_array($g('pay_basis'), array('monthly', 'daily', 'hourly', 'shift'), true) ? $g('pay_basis') : 'monthly',
            'source' => 'hr', 'status' => isset($map[$status]) ? $map[$status] : 'active', 'exit_date' => $g('exit_date', null),
            'id_pulse_ta_shift' => $existing ? $existing['id_pulse_ta_shift'] : null,
        ), $existing ? (int) $existing['id_pulse_ta_staff'] : 0);
    }

    /* ---------- live board ---------- */

    /**
     * Who is in, right now, by department. A punch pairs to an "in" when the most recent punch for that
     * person is an in-type (or an unknown-direction punch in an odd position). Night shifts are handled by
     * looking back a full window rather than at today's calendar date.
     */
    public static function board($department = '')
    {
        $since = date('Y-m-d H:i:s', strtotime('-20 hour'));
        $rows = Db::getInstance()->executeS('SELECT s.id_pulse_ta_staff, s.staff_no, s.firstname, s.lastname, s.department, s.position,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_punch` p WHERE p.id_pulse_ta_staff=s.id_pulse_ta_staff AND p.punched_at>="'.pSQL($since).'") punches,
                (SELECT MIN(p.punched_at) FROM `'._DB_PREFIX_.'pulse_ta_punch` p WHERE p.id_pulse_ta_staff=s.id_pulse_ta_staff AND p.punched_at>="'.pSQL($since).'") first_at,
                (SELECT MAX(p.punched_at) FROM `'._DB_PREFIX_.'pulse_ta_punch` p WHERE p.id_pulse_ta_staff=s.id_pulse_ta_staff AND p.punched_at>="'.pSQL($since).'") last_at,
                (SELECT p.direction FROM `'._DB_PREFIX_.'pulse_ta_punch` p WHERE p.id_pulse_ta_staff=s.id_pulse_ta_staff AND p.punched_at>="'.pSQL($since).'" ORDER BY p.punched_at DESC, p.id_pulse_ta_punch DESC LIMIT 1) last_dir,
                (SELECT d.name FROM `'._DB_PREFIX_.'pulse_ta_punch` p LEFT JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=p.id_pulse_ta_device WHERE p.id_pulse_ta_staff=s.id_pulse_ta_staff AND p.punched_at>="'.pSQL($since).'" ORDER BY p.punched_at DESC LIMIT 1) last_device,
                r.day_type, sh.code shift_code, sh.name shift_name, sh.start_time, sh.end_time, sh.crosses_midnight, sh.grace_in_min
            FROM `'._DB_PREFIX_.'pulse_ta_staff` s
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_roster` r ON r.id_pulse_ta_staff=s.id_pulse_ta_staff AND r.roster_date="'.pSQL(self::bd()).'"
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_shift` sh ON sh.id_pulse_ta_shift=r.id_pulse_ta_shift
            WHERE s.status="active"'.($department ? ' AND s.department="'.pSQL($department).'"' : '').'
            ORDER BY s.department, s.lastname, s.firstname');
        $out = array(); $now = time();
        foreach ((array) $rows as $r) {
            $in = false;
            if ((int) $r['punches'] > 0) { $in = in_array($r['last_dir'], array('in', 'break_in', 'ot_in'), true) || ($r['last_dir'] === 'unknown' && (int) $r['punches'] % 2 === 1); }
            $r['is_in'] = $in ? 1 : 0;
            $r['on_site_minutes'] = $in && $r['first_at'] ? (int) round(($now - strtotime($r['first_at'])) / 60) : 0;
            $r['expected'] = $r['shift_code'] ? $r['start_time'].'–'.$r['end_time'] : '';
            $r['late_minutes'] = 0;
            if ($r['shift_code'] && $r['first_at'] && $r['day_type'] === 'work') {
                $start = strtotime(self::bd().' '.$r['start_time']);
                $r['late_minutes'] = max(0, (int) round((strtotime($r['first_at']) - $start) / 60) - (int) $r['grace_in_min']);
            }
            $r['state'] = $in ? 'in' : ((int) $r['punches'] > 0 ? 'left' : (($r['day_type'] && $r['day_type'] !== 'work') ? $r['day_type'] : 'not_in'));
            $out[] = $r;
        }
        return $out;
    }

    /** Headline counters for the Live Board and the API. */
    public static function dashboard()
    {
        $db = Db::getInstance();
        $bd = self::bd();
        $board = self::board();
        $in = 0; $late = 0; $notIn = 0;
        foreach ($board as $b) { if ($b['is_in']) { $in++; } if ($b['late_minutes'] > 0) { $late++; } if ($b['state'] === 'not_in') { $notIn++; } }
        return array(
            'business_date' => $bd,
            'on_site' => $in, 'late' => $late, 'expected_not_in' => $notIn, 'active_staff' => count($board),
            'punches_today' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_punch` WHERE business_date="'.pSQL($bd).'"'),
            'unmatched' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_punch` WHERE id_pulse_ta_staff IS NULL AND business_date>=DATE_SUB("'.pSQL($bd).'", INTERVAL 7 DAY)'),
            'open_exceptions' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_exception` WHERE status="open"'),
            'blocking_exceptions' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_exception` WHERE status="open" AND severity="block"'),
            'devices_total' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_device`'),
            'devices_offline' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_device` WHERE status="active" AND health IN ("offline","degraded")'),
            'devices_pending' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_device` WHERE status="pending"'),
            'queue' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_job` WHERE status="queued"'),
            'hr' => self::hr() ? 1 : 0, 'pos' => self::pos() ? 1 : 0,
        );
    }

    /** Labour hours against occupied rooms — the hospitality KPI that makes a T&A module worth buying. */
    public static function labourPerOccupiedRoom($from, $to)
    {
        $mins = (float) Db::getInstance()->getValue('SELECT SUM(worked_minutes) FROM `'._DB_PREFIX_.'pulse_ta_timesheet` WHERE business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"');
        $rooms = 0;
        if (class_exists('HotelBookingDetail')) {
            $rooms = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_cancelled=0 AND is_refunded=0 AND date_from<="'.pSQL($to).'" AND date_to>="'.pSQL($from).'"');
        }
        return array('worked_hours' => round($mins / 60, 1), 'occupied_room_nights' => $rooms, 'hours_per_occupied_room' => $rooms > 0 ? round($mins / 60 / $rooms, 2) : null);
    }

    /* ---------- retry / provisioning queue ---------- */

    public static function queueJob($type, $idDevice = null, $idStaff = null, array $payload = array(), $delaySec = 0)
    {
        Db::getInstance()->insert('pulse_ta_job', self::nulls(array('type' => pSQL($type), 'id_pulse_ta_device' => $idDevice ? (int) $idDevice : null,
            'id_pulse_ta_staff' => $idStaff ? (int) $idStaff : null, 'payload' => pSQL(json_encode($payload), true), 'attempts' => 0,
            'next_try_at' => date('Y-m-d H:i:s', time() + max(0, (int) $delaySec)), 'status' => 'queued',
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'))));
        return (int) Db::getInstance()->Insert_ID();
    }

    public static function jobs($statuses = 'queued,failed', $limit = 100)
    {
        $s = array_map('pSQL', array_filter(array_map('trim', explode(',', $statuses))));
        return Db::getInstance()->executeS('SELECT j.*, d.name device_name, CONCAT(s.firstname," ",s.lastname) staff_name FROM `'._DB_PREFIX_.'pulse_ta_job` j
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=j.id_pulse_ta_device
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=j.id_pulse_ta_staff
            WHERE j.status IN ("'.implode('","', $s).'") ORDER BY j.next_try_at LIMIT '.(int) $limit);
    }

    /**
     * Drain the queue with a growing back-off. Nothing in here may throw: a dead device must not stop the
     * cron reaching the next job.
     */
    public static function runQueue($limit = 50)
    {
        $done = 0; $failed = 0; $retried = 0; $errors = array();
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_job` WHERE status="queued" AND next_try_at<=NOW() ORDER BY next_try_at LIMIT '.(int) $limit);
        foreach ((array) $rows as $j) {
            Db::getInstance()->update('pulse_ta_job', array('status' => 'running', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_job='.(int) $j['id_pulse_ta_job']);
            $payload = json_decode((string) $j['payload'], true); $payload = is_array($payload) ? $payload : array();
            try {
                switch ($j['type']) {
                    case 'push_user': PulseTaEnrolment::push((int) $j['id_pulse_ta_staff'], (int) $j['id_pulse_ta_device']); break;
                    case 'delete_user': PulseTaEnrolment::revoke((int) $j['id_pulse_ta_staff'], (int) $j['id_pulse_ta_device'], isset($payload['reason']) ? $payload['reason'] : 'queued revoke'); break;
                    case 'pull': PulseTaDevice::poll((int) $j['id_pulse_ta_device'], true); break;
                    case 'sync_time': PulseTaDevice::adapter((int) $j['id_pulse_ta_device'])->syncTime(); break;
                    case 'clear_log': PulseTaDevice::adapter((int) $j['id_pulse_ta_device'])->clearLog(); break;
                    case 'build': PulseTaEngine::buildDay(isset($payload['date']) ? $payload['date'] : date('Y-m-d', strtotime('-1 day'))); break;
                    case 'import': PulseTaDevice::poll((int) $j['id_pulse_ta_device'], true); break;
                    default: throw new PrestaShopException('Unknown job type '.$j['type']);
                }
                Db::getInstance()->update('pulse_ta_job', array('status' => 'done', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_job='.(int) $j['id_pulse_ta_job']);
                $done++;
            } catch (Exception $e) {
                $attempts = (int) $j['attempts'] + 1;
                $give = $attempts >= (int) self::cfg('JOB_MAX_ATTEMPTS', 6);
                $msg = $e instanceof PulseTaDeviceException ? $e->userMessage() : $e->getMessage();
                Db::getInstance()->update('pulse_ta_job', array('status' => $give ? 'failed' : 'queued', 'attempts' => $attempts,
                    'last_error' => pSQL(Tools::substr($msg, 0, 255)), 'next_try_at' => date('Y-m-d H:i:s', time() + min(3600, 60 * $attempts * $attempts)),
                    'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_job='.(int) $j['id_pulse_ta_job']);
                if ($give) { $failed++; $errors[] = $j['type'].' #'.$j['id_pulse_ta_job'].': '.$msg; } else { $retried++; }
            }
        }
        return array('done' => $done, 'failed' => $failed, 'retried' => $retried, 'errors' => $errors);
    }

    /** Drop a line on the Front Desk trace when it is installed; always leave an audit row behind. */
    public static function alert($message, $department = '')
    {
        self::audit('alert', array('message' => $message, 'department' => $department));
        if (class_exists('PulseTrace')) { PulseTrace::add('alert', Tools::substr($message, 0, 250), date('Y-m-d H:i:s'), null, null, null, 'time'); }
        return true;
    }
}
