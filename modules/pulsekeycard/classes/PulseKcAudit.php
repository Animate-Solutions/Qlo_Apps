<?php
/**
 * Lock audit trail: pull door-open events from locks that report them, attribute each swipe to a key
 * (and therefore to a guest or a member of staff), and turn a flat battery into a maintenance work order.
 */
class PulseKcAudit
{
    const T = 'pulse_kc_lock_audit';

    /** Pull one door's audit through its adapter and store what is new. Returns rows ingested. */
    public static function pull($idDoor)
    {
        $d = PulseKcService::door($idDoor);
        if (!$d) { throw new PrestaShopException('Door not found'); }
        if (!$d['lock_id']) { throw new PrestaShopException('Door '.$d['name'].' has no lock id — set it in Key Card ▸ Lock Audit'); }
        $encoder = PulseKcEncoder::pick($d['id_pulse_kc_encoder'] ? (int) $d['id_pulse_kc_encoder'] : null);
        $adapter = PulseKcEncoder::adapter($encoder);
        $caps = $adapter->capabilities();
        if (empty($caps['audit'])) { throw new PulseKcEncoderException('This lock system does not expose an audit trail', PulseKcEncoderException::UNSUPPORTED, $encoder['name']); }
        $rows = $adapter->readAudit($d['lock_id']);
        PulseKcEncoder::markSeen((int) $encoder['id_pulse_kc_encoder'], true);
        $n = 0; $battery = null;
        foreach ((array) $rows as $r) { if (self::ingest($d, $r)) { $n++; } if (isset($r['battery_pct']) && $r['battery_pct'] !== null) { $battery = (int) $r['battery_pct']; } }
        $u = array('last_audit_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'));
        if ($battery !== null) { $u['battery_pct'] = $battery; $u['battery_checked_at'] = date('Y-m-d H:i:s'); }
        Db::getInstance()->update('pulse_kc_door', $u, 'id_pulse_kc_door='.(int) $idDoor);
        PulseCoreService::event('actionPulseLockAudit', array('id_door' => (int) $idDoor, 'rows' => $n, 'battery_pct' => $battery));
        return $n;
    }

    /** Pull every door that has a lock id; used by cron and the "refresh all" button. */
    public static function pullAll($limit = 100)
    {
        $done = 0; $errors = array();
        foreach (Db::getInstance()->executeS('SELECT id_pulse_kc_door, name FROM `'._DB_PREFIX_.'pulse_kc_door` WHERE active=1 AND lock_id<>"" ORDER BY last_audit_at IS NULL DESC, last_audit_at LIMIT '.(int) $limit) as $d) {
            try { $done += (int) self::pull((int) $d['id_pulse_kc_door']); }
            catch (Exception $e) { $errors[] = $d['name'].': '.($e instanceof PulseKcEncoderException ? $e->userMessage() : $e->getMessage()); }
        }
        return array('rows' => $done, 'errors' => $errors);
    }

    /**
     * Store one event, attributing it to the key that carries that serial and to the room the door belongs to.
     * The unique key on (lock, time, serial, event) makes repeated pulls idempotent.
     */
    public static function ingest(array $door, array $r, $source = 'lock')
    {
        $when = isset($r['opened_at']) ? date('Y-m-d H:i:s', strtotime($r['opened_at'])) : date('Y-m-d H:i:s');
        $serial = isset($r['card_serial']) ? (string) $r['card_serial'] : '';
        $key = $serial ? Db::getInstance()->getRow('SELECT id_pulse_kc_key, guest_name, type FROM `'._DB_PREFIX_.'pulse_kc_key` WHERE card_serial="'.pSQL($serial).'" ORDER BY id_pulse_kc_key DESC') : null;
        $event = isset($r['event']) ? $r['event'] : 'open';
        if ($key && $event === 'open' && in_array($key['type'], array('staff', 'master'))) { $event = 'staff_open'; }
        $ok = Db::getInstance()->insert(self::T, array(
            'id_pulse_kc_door' => (int) $door['id_pulse_kc_door'], 'lock_id' => pSQL($door['lock_id']), 'id_room' => !empty($door['id_room']) ? (int) $door['id_room'] : null,
            'card_serial' => pSQL($serial), 'id_pulse_kc_key' => $key ? (int) $key['id_pulse_kc_key'] : null, 'holder' => pSQL($key ? Tools::substr($key['guest_name'], 0, 128) : ''),
            'event' => pSQL($event), 'result' => pSQL(isset($r['result']) ? $r['result'] : 'granted'),
            'battery_pct' => isset($r['battery_pct']) && $r['battery_pct'] !== null ? (int) $r['battery_pct'] : null,
            'source' => pSQL($source), 'opened_at' => pSQL($when), 'business_date' => pSQL(date('Y-m-d', strtotime($when))),
            'raw' => pSQL(Tools::substr((string) (isset($r['raw']) ? $r['raw'] : ''), 0, 255)), 'date_add' => date('Y-m-d H:i:s'),
        ), false, true, Db::INSERT_IGNORE);
        return $ok && (int) Db::getInstance()->Affected_Rows() > 0;
    }

    /** "Who opened this door" for a security incident: every swipe on a room between two timestamps. */
    public static function forRoom($idRoom, $from = null, $to = null, $limit = 300)
    {
        $w = '';
        if ($from) { $w .= ' AND a.opened_at>="'.pSQL($from).'"'; }
        if ($to) { $w .= ' AND a.opened_at<="'.pSQL($to).'"'; }
        return Db::getInstance()->executeS('SELECT a.*, d.name door_name, k.key_no, k.type key_type, k.id_htl_booking, k.guest_name, g.name staff_group, g.shift_start, g.shift_end, g.days_mask,
                CONCAT(e.firstname," ",e.lastname) staff_name
            FROM `'._DB_PREFIX_.self::T.'` a
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_door` d ON d.id_pulse_kc_door=a.id_pulse_kc_door
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_key` k ON k.id_pulse_kc_key=a.id_pulse_kc_key
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_staff_group` g ON g.id_pulse_kc_staff_group=k.id_pulse_kc_staff_group
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=k.id_employee_holder
            WHERE a.id_room='.(int) $idRoom.$w.' ORDER BY a.opened_at DESC LIMIT '.(int) $limit);
    }

    /** Filtered audit list for the security screen. $f: id_door, id_room, event, result, q, from, to. */
    public static function search(array $f = array(), $limit = 300)
    {
        $w = ' WHERE 1';
        if (!empty($f['id_door'])) { $w .= ' AND a.id_pulse_kc_door='.(int) $f['id_door']; }
        if (!empty($f['id_room'])) { $w .= ' AND a.id_room='.(int) $f['id_room']; }
        if (!empty($f['event'])) { $w .= ' AND a.event="'.pSQL($f['event']).'"'; }
        if (!empty($f['result'])) { $w .= ' AND a.result="'.pSQL($f['result']).'"'; }
        if (!empty($f['from'])) { $w .= ' AND a.opened_at>="'.pSQL($f['from']).' 00:00:00"'; }
        if (!empty($f['to'])) { $w .= ' AND a.opened_at<="'.pSQL($f['to']).' 23:59:59"'; }
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w .= ' AND (a.card_serial LIKE "%'.$q.'%" OR a.holder LIKE "%'.$q.'%" OR a.lock_id LIKE "%'.$q.'%" OR d.name LIKE "%'.$q.'%")'; }
        return Db::getInstance()->executeS('SELECT a.*, d.name door_name, r.room_num, k.key_no, k.type key_type, g.name staff_group, g.shift_start, g.shift_end, g.days_mask
            FROM `'._DB_PREFIX_.self::T.'` a
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_door` d ON d.id_pulse_kc_door=a.id_pulse_kc_door
            LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=a.id_room
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_key` k ON k.id_pulse_kc_key=a.id_pulse_kc_key
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_staff_group` g ON g.id_pulse_kc_staff_group=k.id_pulse_kc_staff_group'.$w.'
            ORDER BY a.opened_at DESC LIMIT '.(int) $limit);
    }

    /** Locks below the battery threshold, worst first. */
    public static function batteryReport()
    {
        $pct = (int) PulseKcService::cfg('BATTERY_PCT', 20);
        return Db::getInstance()->executeS('SELECT d.*, r.room_num FROM `'._DB_PREFIX_.'pulse_kc_door` d LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=d.id_room
            WHERE d.active=1 AND d.battery_pct IS NOT NULL AND d.battery_pct<='.$pct.' ORDER BY d.battery_pct');
    }

    /**
     * pulse_ticket.department is an ENUM whose members differ by Front Desk build: some ship 'maintenance',
     * the current one calls the same department 'engineering'. Pick whichever the column actually allows so
     * the row is not silently truncated to ''. Routing to a work order keys off category='maintenance' anyway.
     */
    protected static function ticketDept()
    {
        static $dept = null;
        if ($dept === null) {
            $col = Db::getInstance()->getRow('SHOW COLUMNS FROM `'._DB_PREFIX_.'pulse_ticket` LIKE "department"');
            $dept = ($col && isset($col['Type']) && strpos($col['Type'], "'maintenance'") !== false) ? 'maintenance' : 'engineering';
        }
        return $dept;
    }

    /**
     * One maintenance work order per flat lock, at most one per door per week.
     * Silently does nothing when Pulse Maintenance / Front Desk tickets are not installed.
     */
    public static function raiseBatteryTickets()
    {
        if (!class_exists('PulseTicket')) { return 0; }
        $n = 0;
        foreach (self::batteryReport() as $d) {
            if ($d['battery_ticket_at'] && strtotime($d['battery_ticket_at']) > time() - 7 * 86400) { continue; }
            PulseTicket::create(array('category' => 'maintenance', 'department' => self::ticketDept(), 'priority' => (int) $d['battery_pct'] <= 10 ? 'urgent' : 'high',
                'title' => 'Door lock battery low — '.$d['name'].' ('.(int) $d['battery_pct'].'%)',
                'description' => 'Lock '.$d['lock_id'].' on '.$d['name'].($d['room_num'] ? ' (room '.$d['room_num'].')' : '').' reported '.(int) $d['battery_pct'].'% battery at '.$d['battery_checked_at'].'. Replace the cells and re-read the audit trail.',
                'id_room' => $d['id_room'] ? (int) $d['id_room'] : null, 'source' => 'keycard'));
            Db::getInstance()->update('pulse_kc_door', array('battery_ticket_at' => date('Y-m-d H:i:s')), 'id_pulse_kc_door='.(int) $d['id_pulse_kc_door']);
            PulseCoreService::audit('pulsekeycard', 'battery_ticket', array('door' => $d['name'], 'battery_pct' => (int) $d['battery_pct']), 'pulse_kc_door', (int) $d['id_pulse_kc_door']);
            $n++;
        }
        return $n;
    }

    /** Denied swipes in the last day, grouped by door — the first place security looks. */
    public static function deniedSummary($hours = 24)
    {
        return Db::getInstance()->executeS('SELECT d.name door_name, r.room_num, COUNT(*) denials, MAX(a.opened_at) last_at
            FROM `'._DB_PREFIX_.self::T.'` a LEFT JOIN `'._DB_PREFIX_.'pulse_kc_door` d ON d.id_pulse_kc_door=a.id_pulse_kc_door
            LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=a.id_room
            WHERE a.result="denied" AND a.opened_at>DATE_SUB(NOW(), INTERVAL '.(int) $hours.' HOUR) GROUP BY a.id_pulse_kc_door ORDER BY denials DESC');
    }

    /** Cron: drop audit rows past the retention setting. */
    public static function purge($days = null)
    {
        $days = (int) ($days !== null ? $days : PulseKcService::cfg('AUDIT_RETENTION', 180));
        if ($days < 7) { $days = 7; }
        Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.self::T.'` WHERE opened_at < DATE_SUB(NOW(), INTERVAL '.$days.' DAY)');
        return (int) Db::getInstance()->Affected_Rows();
    }
}
