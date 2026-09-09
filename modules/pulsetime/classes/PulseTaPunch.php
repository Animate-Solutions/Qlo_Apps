<?php
/**
 * The punch store. Punches are evidence.
 *
 * `pulse_ta_punch` is append-only: nothing in this module ever UPDATEs a punch's time, direction, employee
 * reference or raw payload. A correction is a row in `pulse_ta_adjustment` carrying an approver, and the
 * engine applies adjustments on top of the punches when it builds a timesheet. That way "the supervisor said
 * he clocked out at 06:10" and "the reader recorded nothing" are both still visible six months later when
 * somebody disputes a payslip.
 *
 * De-duplication is a unique index on sha1(device serial | employee ref | punched_at), so a device re-sending
 * its whole log after a network glitch — which they all do — costs one failed insert and nothing else.
 *
 * The only column ever written after insert is `id_pulse_ta_staff`, and only to fill it in when an unmatched
 * device reference is later mapped to a person on the Enrolment screen. That is resolution of identity, not
 * revision of evidence, and it is audited.
 */
class PulseTaPunch
{
    const T = 'pulse_ta_punch';

    /**
     * The dedupe key. It hashes the values EXACTLY as the columns will hold them — the reference truncated to
     * 32 and the serial to 64 — because two references that differ only past the column width would otherwise
     * hash differently, store identically, and give one person two punches at the same second.
     */
    public static function hash($serial, $ref, $at)
    {
        return sha1(Tools::substr((string) $serial, 0, 64).'|'.Tools::substr((string) $ref, 0, 32).'|'.date('Y-m-d H:i:s', strtotime((string) $at)));
    }

    /**
     * Store one normalised punch.
     * @return int  the punch id, or 0 when it was a duplicate or failed validation
     */
    public static function ingest($dev, array $p)
    {
        $ref = isset($p['employee_ref']) ? trim((string) $p['employee_ref']) : '';
        $at = isset($p['punched_at']) ? (string) $p['punched_at'] : '';
        if ($ref === '' || $at === '' || !strtotime($at)) { return 0; }
        $ts = strtotime($at);
        // A punch from the future is a device with a wrong clock, not a person. Store it, flag it, never pair it.
        $future = $ts > time() + 3600;
        $serial = isset($p['device_serial']) && $p['device_serial'] !== '' ? $p['device_serial'] : (is_array($dev) ? (string) $dev['serial'] : '');
        $idDevice = is_array($dev) ? (int) $dev['id_pulse_ta_device'] : (int) $dev;
        $hash = self::hash($serial, $ref, $at);
        if (Db::getInstance()->getValue('SELECT id_pulse_ta_punch FROM `'._DB_PREFIX_.self::T.'` WHERE dedupe_hash="'.pSQL($hash).'"')) { return 0; }
        $staff = PulseTaEnrolment::resolve($idDevice, $ref);
        $row = array(
            'id_pulse_ta_device' => $idDevice ?: null, 'device_serial' => pSQL(Tools::substr($serial, 0, 64)),
            'employee_ref' => pSQL(Tools::substr($ref, 0, 32)), 'id_pulse_ta_staff' => $staff ? (int) $staff : null,
            'punched_at' => pSQL(date('Y-m-d H:i:s', $ts)),
            'device_time' => !empty($p['device_time']) ? pSQL(date('Y-m-d H:i:s', strtotime($p['device_time']))) : null,
            'direction' => pSQL(self::enumOr(isset($p['direction']) ? $p['direction'] : 'unknown', array('in', 'out', 'break_out', 'break_in', 'ot_in', 'ot_out', 'unknown'), 'unknown')),
            'verify_mode' => pSQL(self::enumOr(isset($p['verify_mode']) ? $p['verify_mode'] : 'other', array('finger', 'face', 'card', 'password', 'palm', 'iris', 'vein', 'mobile', 'manual', 'other'), 'other')),
            'work_code' => pSQL(Tools::substr((string) (isset($p['work_code']) ? $p['work_code'] : ''), 0, 16)),
            'source' => pSQL(self::enumOr(isset($p['source']) ? $p['source'] : 'device', array('device', 'mobile', 'manual', 'import', 'pos', 'adjustment'), 'device')),
            'latitude' => isset($p['latitude']) && $p['latitude'] !== '' ? (float) $p['latitude'] : null,
            'longitude' => isset($p['longitude']) && $p['longitude'] !== '' ? (float) $p['longitude'] : null,
            'accuracy_m' => isset($p['accuracy_m']) && $p['accuracy_m'] !== '' ? (int) $p['accuracy_m'] : null,
            'business_date' => pSQL(date('Y-m-d', $ts)),
            'raw' => pSQL(self::safeRaw(isset($p['raw']) ? $p['raw'] : ''), true),
            'dedupe_hash' => pSQL($hash),
            'id_employee_entered' => !empty($p['id_employee_entered']) ? (int) $p['id_employee_entered'] : null,
            'date_add' => date('Y-m-d H:i:s'),
        );
        if (!Db::getInstance()->insert(self::T, PulseTaService::nulls($row), false, true, Db::INSERT_IGNORE)) { return 0; }
        $id = (int) Db::getInstance()->Insert_ID();
        if (!$id) { return 0; }
        if ($future) {
            PulseTaExceptionQueue::raise($staff ? (int) $staff : null, date('Y-m-d', $ts), 'future_punch', 'warn',
                'Punch dated '.date('Y-m-d H:i', $ts).' is in the future — the device clock is wrong. Run Sync time on "'.(is_array($dev) ? $dev['name'] : $serial).'".', 0, null, $id);
        }
        if (!$staff) {
            PulseTaExceptionQueue::raise(null, date('Y-m-d', $ts), 'unmatched_ref', 'warn',
                'Device user id "'.Tools::substr($ref, 0, 32).'" on '.(is_array($dev) ? $dev['name'] : $serial).' is not mapped to anyone — map it in T&A ▸ Enrolment.', 0, null, $id, $serial.':'.$ref);
        }
        return $id;
    }

    protected static function enumOr($v, array $allowed, $fallback) { return in_array((string) $v, $allowed, true) ? (string) $v : $fallback; }

    /**
     * The raw vendor payload, made safe to store and to render without losing it. Valid UTF-8 is kept as it
     * is (a name with an accent stays readable); anything else — a binary frame, a truncated multi-byte
     * sequence — is hexed rather than dropped, because this column is the evidence in a pay dispute.
     */
    protected static function safeRaw($raw)
    {
        $s = is_string($raw) ? $raw : json_encode($raw);
        $s = (string) $s;
        if ($s === '') { return ''; }
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $s);
        if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) { $s = 'hex:'.bin2hex($s); }
        return substr($s, 0, 1000);
    }

    /** Bulk ingest; returns how many were genuinely new. */
    public static function ingestMany($dev, array $punches)
    {
        $n = 0; $touched = array(); $ids = array();
        foreach ($punches as $p) {
            $id = self::ingest($dev, $p);
            if ($id) { $n++; $ids[] = $id; $touched[date('Y-m-d', strtotime($p['punched_at']))] = 1; }
        }
        if ($n) {
            self::warnIfLocked($dev, $ids);
            PulseCoreService::event('actionPulseTaPunch', array('id_device' => is_array($dev) ? (int) $dev['id_pulse_ta_device'] : (int) $dev, 'count' => $n, 'dates' => array_keys($touched)));
        }
        return $n;
    }

    /**
     * A device punch is never refused — the reader's log is evidence and evidence is always stored. But a
     * punch that lands on a day payroll has already locked will never reach a timesheet, so it would vanish
     * silently. One alert per batch says so, with the range, so somebody decides whether to reopen.
     */
    protected static function warnIfLocked($dev, array $ids)
    {
        if (!$ids) { return 0; }
        $r = Db::getInstance()->getRow('SELECT COUNT(*) n, MIN(p.business_date) f, MAX(p.business_date) t
            FROM `'._DB_PREFIX_.self::T.'` p INNER JOIN `'._DB_PREFIX_.'pulse_ta_timesheet` ts ON ts.id_pulse_ta_staff=p.id_pulse_ta_staff
                AND ts.business_date IN (p.business_date, DATE_SUB(p.business_date, INTERVAL 1 DAY))
            WHERE ts.locked=1 AND p.id_pulse_ta_punch IN ('.implode(',', array_map('intval', $ids)).')');
        if (!$r || !(int) $r['n']) { return 0; }
        PulseTaService::alert((int) $r['n'].' punch(es) from "'.(is_array($dev) ? $dev['name'] : $dev).'" arrived for days that are already approved and locked ('
            .$r['f'].' to '.$r['t'].'). They are stored but will not reach a timesheet until the period is reopened.');
        return (int) $r['n'];
    }

    /**
     * A punch recorded by a human, not a device: the kiosk, the mobile portal, or a supervisor keying one in.
     * A manual punch is still evidence — it goes into the same append-only table with source='manual' and the
     * employee who keyed it, and it is visible as such everywhere.
     */
    public static function manual($idStaff, $at, $direction = 'unknown', $source = 'manual', array $extra = array())
    {
        $s = PulseTaService::staff($idStaff);
        if (!$s) { throw new PrestaShopException('Staff member not found'); }
        if (!strtotime((string) $at)) { throw new PrestaShopException('Invalid punch time'); }
        if (PulseTaTimesheet::isLocked((int) $idStaff, date('Y-m-d', strtotime($at)))) { throw new PrestaShopException('That period is approved and locked — reopen it before adding punches'); }
        $serial = isset($extra['device_serial']) ? $extra['device_serial'] : ($source === 'mobile' ? 'MOBILE' : ($source === 'pos' ? 'POS' : 'MANUAL'));
        $p = array_merge(array('device_serial' => $serial, 'employee_ref' => $s['staff_no'], 'punched_at' => date('Y-m-d H:i:s', strtotime($at)),
            'direction' => $direction, 'verify_mode' => $source === 'mobile' ? 'mobile' : 'manual', 'source' => $source,
            'raw' => isset($extra['raw']) ? $extra['raw'] : ($source.' entry'), 'id_employee_entered' => PulseTaService::emp()), $extra);
        // A manual punch bypasses the device mapping, so the staff link is set directly.
        $hash = self::hash($serial, $s['staff_no'], $p['punched_at']);
        if (Db::getInstance()->getValue('SELECT id_pulse_ta_punch FROM `'._DB_PREFIX_.self::T.'` WHERE dedupe_hash="'.pSQL($hash).'"')) { return 0; }
        Db::getInstance()->insert(self::T, PulseTaService::nulls(array(
            'id_pulse_ta_device' => isset($extra['id_pulse_ta_device']) ? (int) $extra['id_pulse_ta_device'] : null,
            'device_serial' => pSQL($serial), 'employee_ref' => pSQL(Tools::substr((string) $s['staff_no'], 0, 32)), 'id_pulse_ta_staff' => (int) $idStaff,
            'punched_at' => pSQL($p['punched_at']), 'device_time' => pSQL($p['punched_at']),
            'direction' => pSQL(self::enumOr($direction, array('in', 'out', 'break_out', 'break_in', 'ot_in', 'ot_out', 'unknown'), 'unknown')),
            'verify_mode' => pSQL($p['verify_mode']), 'work_code' => pSQL(Tools::substr((string) (isset($extra['work_code']) ? $extra['work_code'] : ''), 0, 16)),
            'source' => pSQL(self::enumOr($source, array('device', 'mobile', 'manual', 'import', 'pos', 'adjustment'), 'manual')),
            'latitude' => isset($extra['latitude']) && $extra['latitude'] !== '' ? (float) $extra['latitude'] : null,
            'longitude' => isset($extra['longitude']) && $extra['longitude'] !== '' ? (float) $extra['longitude'] : null,
            'accuracy_m' => isset($extra['accuracy_m']) && $extra['accuracy_m'] !== '' ? (int) $extra['accuracy_m'] : null,
            'business_date' => pSQL(date('Y-m-d', strtotime($p['punched_at']))),
            'raw' => pSQL(self::safeRaw($p['raw']), true), 'dedupe_hash' => pSQL($hash),
            'id_employee_entered' => PulseTaService::emp() ?: null, 'date_add' => date('Y-m-d H:i:s'),
        )));
        $id = (int) Db::getInstance()->Insert_ID();
        PulseTaService::audit('punch_manual', array('id_staff' => (int) $idStaff, 'at' => $p['punched_at'], 'direction' => $direction, 'source' => $source), self::T, $id);
        PulseCoreService::event('actionPulseTaPunch', array('id_staff' => (int) $idStaff, 'count' => 1, 'source' => $source, 'dates' => array(date('Y-m-d', strtotime($p['punched_at'])))));
        return $id;
    }

    /**
     * Fill in the person on punches that arrived before the device reference was mapped. This is the one
     * post-insert write on a punch row and it is audited, because it changes whose timesheet a punch lands on.
     */
    public static function attachStaff($idDevice, $ref, $idStaff, $since = null)
    {
        // $since bounds the back-fill. A reader PIN is routinely recycled when somebody leaves, and without a
        // bound the new holder would inherit the leaver's punches — a payroll error, in the one place this
        // table is ever written after insert.
        $n = Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET id_pulse_ta_staff='.(int) $idStaff
            .' WHERE id_pulse_ta_staff IS NULL AND employee_ref="'.pSQL($ref).'" AND id_pulse_ta_device='.(int) $idDevice
            .($since && strtotime($since) ? ' AND punched_at>="'.pSQL(date('Y-m-d H:i:s', strtotime($since))).'"' : ''));
        $rows = (int) Db::getInstance()->Affected_Rows();
        if ($rows) { PulseTaService::audit('punch_attach_staff', array('id_device' => (int) $idDevice, 'ref' => $ref, 'id_staff' => (int) $idStaff, 'rows' => $rows, 'since' => $since), self::T, (int) $idStaff); }
        return $n ? $rows : 0;
    }

    public static function get($id)
    {
        return Db::getInstance()->getRow('SELECT p.*, d.name device_name, CONCAT(s.firstname," ",s.lastname) staff_name, s.staff_no, s.department
            FROM `'._DB_PREFIX_.self::T.'` p LEFT JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=p.id_pulse_ta_device
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=p.id_pulse_ta_staff WHERE p.id_pulse_ta_punch='.(int) $id);
    }

    /** Filtered punch register. $f: from, to, id_staff, id_device, department, source, unmatched, q. */
    public static function search(array $f = array(), $limit = 300)
    {
        $w = ' WHERE 1';
        if (!empty($f['from'])) { $w .= ' AND p.punched_at>="'.pSQL($f['from']).' 00:00:00"'; }
        if (!empty($f['to'])) { $w .= ' AND p.punched_at<="'.pSQL($f['to']).' 23:59:59"'; }
        if (!empty($f['id_staff'])) { $w .= ' AND p.id_pulse_ta_staff='.(int) $f['id_staff']; }
        if (!empty($f['id_device'])) { $w .= ' AND p.id_pulse_ta_device='.(int) $f['id_device']; }
        if (!empty($f['department'])) { $w .= ' AND s.department="'.pSQL($f['department']).'"'; }
        if (!empty($f['source'])) { $w .= ' AND p.source="'.pSQL($f['source']).'"'; }
        if (!empty($f['unmatched'])) { $w .= ' AND p.id_pulse_ta_staff IS NULL'; }
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w .= ' AND (p.employee_ref LIKE "%'.$q.'%" OR s.staff_no LIKE "%'.$q.'%" OR s.firstname LIKE "%'.$q.'%" OR s.lastname LIKE "%'.$q.'%")'; }
        return Db::getInstance()->executeS('SELECT p.*, d.name device_name, CONCAT(s.firstname," ",s.lastname) staff_name, s.staff_no, s.department
            FROM `'._DB_PREFIX_.self::T.'` p LEFT JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=p.id_pulse_ta_device
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=p.id_pulse_ta_staff'.$w
            .' ORDER BY p.punched_at DESC, p.id_pulse_ta_punch DESC LIMIT '.max(1, min(2000, (int) $limit)));
    }

    /** Every punch for one person inside a window, oldest first — what the engine pairs. */
    public static function forStaffWindow($idStaff, $from, $to)
    {
        return Db::getInstance()->executeS('SELECT p.*, d.direction_mode FROM `'._DB_PREFIX_.self::T.'` p
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=p.id_pulse_ta_device
            WHERE p.id_pulse_ta_staff='.(int) $idStaff.' AND p.punched_at>="'.pSQL($from).'" AND p.punched_at<="'.pSQL($to).'"
            ORDER BY p.punched_at, p.id_pulse_ta_punch');
    }

    /** Device references nobody has claimed — the Enrolment screen's "unknown people are clocking" list. */
    public static function unmatched($days = 30)
    {
        return Db::getInstance()->executeS('SELECT p.employee_ref, p.id_pulse_ta_device, d.name device_name, COUNT(*) punches,
                MIN(p.punched_at) first_at, MAX(p.punched_at) last_at
            FROM `'._DB_PREFIX_.self::T.'` p LEFT JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=p.id_pulse_ta_device
            WHERE p.id_pulse_ta_staff IS NULL AND p.punched_at>=DATE_SUB(NOW(), INTERVAL '.max(1, (int) $days).' DAY)
            GROUP BY p.employee_ref, p.id_pulse_ta_device ORDER BY punches DESC, last_at DESC LIMIT 200');
    }

    /**
     * POS clock rows as punches to reconcile against. `pulse_pos_clock` is another source of truth about who
     * was on shift, not a competitor: a waiter who clocked into the POS but never touched the biometric
     * reader is a real presence, and the exception screen shows both sides.
     */
    public static function posClock($idStaff, $from, $to)
    {
        if (!PulseTaService::pos()) { return array(); }
        $s = PulseTaService::staff($idStaff);
        if (!$s || (empty($s['id_employee']) && empty($s['id_pos_staff']))) { return array(); }
        $idEmp = (int) (!empty($s['id_pos_staff']) ? $s['id_pos_staff'] : $s['id_employee']);
        return Db::getInstance()->executeS('SELECT id_pulse_pos_clock, id_employee, clock_in, clock_out, business_date
            FROM `'._DB_PREFIX_.'pulse_pos_clock` WHERE id_employee='.$idEmp.'
            AND ((clock_in BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'") OR (clock_out BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'")) ORDER BY clock_in');
    }

    /** Retention: only ever purges punches older than the (opt-in, default off) retention setting. */
    public static function purge()
    {
        $days = (int) PulseTaService::cfg('PUNCH_RETENTION', 0);
        if ($days <= 0) { return 0; }
        Db::getInstance()->execute('DELETE p FROM `'._DB_PREFIX_.self::T.'` p
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_timesheet_punch` tp ON tp.id_pulse_ta_punch=p.id_pulse_ta_punch
            WHERE p.punched_at<DATE_SUB(NOW(), INTERVAL '.$days.' DAY) AND tp.id_pulse_ta_punch IS NULL');
        return (int) Db::getInstance()->Affected_Rows();
    }
}
