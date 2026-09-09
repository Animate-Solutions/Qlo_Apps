<?php
/**
 * Key Card facade: settings, validity windows, common doors, the offline job queue and the desk dashboard.
 * Everything Front Desk related is optional — the module issues, cancels and audits keys standalone.
 */
class PulseKcService
{
    /** Front Desk present? (bookings, folios, traces, tickets). */
    public static function fd() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFdService'); }
    public static function bd() { return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    public static function emp() { $c = Context::getContext(); return isset($c->employee) && $c->employee->id ? (int) $c->employee->id : 0; }

    public static function cfg($k, $default = null) { $v = Configuration::get('PULSE_KC_'.$k); return ($v === false || $v === null || $v === '') ? $default : $v; }
    public static function nextNo($prefix = 'K')
    {
        $n = (int) PulseCoreService::setting('pulsekeycard', 'seq_'.$prefix) + 1;
        PulseCoreService::setting('pulsekeycard', 'seq_'.$prefix, $n);
        return $prefix.date('ymd').str_pad($n % 10000, 4, '0', STR_PAD_LEFT);
    }

    /** Per-room card sequence — a new key must out-rank every card cut for that room before it. */
    public static function nextSequence($roomNums)
    {
        $key = 'seq_room_'.md5(implode(',', (array) $roomNums));
        $n = (int) PulseCoreService::setting('pulsekeycard', $key) + 1;
        PulseCoreService::setting('pulsekeycard', $key, $n);
        return $n;
    }

    /* ---------- validity ---------- */

    /** Hotel check-in / check-out clock, read from Front Desk when it is installed. */
    public static function checkInTime() { return Configuration::get('PULSE_FD_CHECKIN_TIME') ?: (PulseCoreService::setting('pulsefrontdesk', 'checkin_time') ?: '14:00'); }
    public static function checkOutTime() { return Configuration::get('PULSE_FD_CHECKOUT_TIME') ?: (PulseCoreService::setting('pulsefrontdesk', 'checkout_time') ?: '12:00'); }

    /**
     * Validity window for a stay: arrival at check-in time minus the early grace, departure at
     * check-out time plus the late grace. A key cut mid-stay starts now (minus a minute of clock skew).
     * @return array [valid_from, valid_to] as 'Y-m-d H:i:s'
     */
    public static function window($dateFrom, $dateTo, $now = true)
    {
        $early = (float) self::cfg('EARLY_GRACE_HRS', 2);
        $late = (float) self::cfg('LATE_GRACE_HRS', 2);
        $from = strtotime($dateFrom.' '.self::checkInTime()) - (int) round($early * 3600);
        $to = strtotime($dateTo.' '.self::checkOutTime()) + (int) round($late * 3600);
        if ($now && $from < time()) { $from = time() - 60; }
        if ($to <= $from) { $to = $from + 3600; }
        return array(date('Y-m-d H:i:s', $from), date('Y-m-d H:i:s', $to));
    }

    /* ---------- doors ---------- */

    public static function doors($activeOnly = true, $type = null)
    {
        return Db::getInstance()->executeS('SELECT d.*, r.room_num FROM `'._DB_PREFIX_.'pulse_kc_door` d LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=d.id_room
            WHERE 1'.($activeOnly ? ' AND d.active=1' : '').($type ? ' AND d.type="'.pSQL($type).'"' : '').' ORDER BY FIELD(d.type,"common","lift","gate","back_of_house","wall_reader","safe","room"), d.name');
    }
    public static function door($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_kc_door` WHERE id_pulse_kc_door='.(int) $id); }
    public static function defaultDoorIds()
    {
        $ids = array();
        foreach (Db::getInstance()->executeS('SELECT id_pulse_kc_door FROM `'._DB_PREFIX_.'pulse_kc_door` WHERE active=1 AND is_default=1') as $d) { $ids[] = (int) $d['id_pulse_kc_door']; }
        return $ids;
    }
    /** Turn a csv of door ids into the vendor lock codes an adapter expects. */
    public static function doorCodes($csv)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $csv)));
        if (!$ids) { return array(); }
        $out = array();
        foreach (Db::getInstance()->executeS('SELECT code, lock_id FROM `'._DB_PREFIX_.'pulse_kc_door` WHERE id_pulse_kc_door IN ('.implode(',', $ids).')') as $d) { $out[] = $d['lock_id'] ? $d['lock_id'] : $d['code']; }
        return $out;
    }
    public static function saveDoor(array $d, $id = 0)
    {
        $row = array('code' => pSQL($d['code']), 'name' => pSQL($d['name']), 'type' => pSQL(isset($d['type']) ? $d['type'] : 'common'), 'lock_id' => pSQL(isset($d['lock_id']) ? $d['lock_id'] : ''),
            'id_room' => !empty($d['id_room']) ? (int) $d['id_room'] : null, 'floor' => pSQL(isset($d['floor']) ? $d['floor'] : ''), 'zone' => pSQL(isset($d['zone']) ? $d['zone'] : ''),
            'is_default' => !empty($d['is_default']) ? 1 : 0, 'id_pulse_kc_encoder' => !empty($d['id_pulse_kc_encoder']) ? (int) $d['id_pulse_kc_encoder'] : null,
            'active' => isset($d['active']) ? (int) $d['active'] : 1, 'date_upd' => date('Y-m-d H:i:s'));
        if ($id) { Db::getInstance()->update('pulse_kc_door', $row, 'id_pulse_kc_door='.(int) $id); }
        else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_kc_door', $row, false, true, Db::INSERT_IGNORE); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    /** Make sure every guest room has a `room` door row so lock audit can be attributed. */
    public static function syncRoomDoors()
    {
        $n = 0;
        foreach (Db::getInstance()->executeS('SELECT r.id, r.room_num, r.floor FROM `'._DB_PREFIX_.'htl_room_information` r
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_door` d ON d.id_room=r.id AND d.type="room" WHERE d.id_pulse_kc_door IS NULL') as $r) {
            self::saveDoor(array('code' => 'R'.$r['room_num'], 'name' => 'Room '.$r['room_num'], 'type' => 'room', 'lock_id' => 'LK-'.$r['room_num'], 'id_room' => (int) $r['id'], 'floor' => $r['floor']));
            $n++;
        }
        return $n;
    }

    /* ---------- offline job queue ---------- */

    /** Park work the encoder could not take right now; cron/expire.php drains it. Payload is encrypted. */
    public static function queue($type, array $payload, $idKey = null, $idEncoder = null, $idDoor = null, $error = null)
    {
        Db::getInstance()->insert('pulse_kc_job', array('type' => pSQL($type), 'id_pulse_kc_key' => $idKey ? (int) $idKey : null, 'id_pulse_kc_encoder' => $idEncoder ? (int) $idEncoder : null,
            'id_pulse_kc_door' => $idDoor ? (int) $idDoor : null, 'payload_enc' => pSQL(PulseCoreService::encrypt(json_encode($payload)), true), 'attempts' => 0,
            'last_error' => pSQL(Tools::substr((string) $error, 0, 255)), 'next_try_at' => date('Y-m-d H:i:s', time() + 300), 'status' => 'queued',
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
        return (int) Db::getInstance()->Insert_ID();
    }

    /** Drain the queue. Backs off 5min, 15min, 45min… and gives up after $maxAttempts. */
    public static function runQueue($maxAttempts = 6, $limit = 50)
    {
        $done = 0; $failed = 0;
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_kc_job` WHERE status="queued" AND next_try_at<=NOW() ORDER BY id_pulse_kc_job LIMIT '.(int) $limit) as $j) {
            $attempts = (int) $j['attempts'] + 1;
            try {
                $payload = json_decode((string) PulseCoreService::decrypt($j['payload_enc']), true);
                if (!is_array($payload)) { $payload = array(); }
                if ($j['type'] === 'encode') { PulseKcKey::retryEncode((int) $j['id_pulse_kc_key'], (int) $j['id_pulse_kc_encoder']); }
                elseif ($j['type'] === 'cancel') { PulseKcKey::cancel((int) $j['id_pulse_kc_key'], isset($payload['reason']) ? $payload['reason'] : 'queued cancel', true); }
                elseif ($j['type'] === 'mobile_revoke') { PulseKcMobileKey::revoke((int) $j['id_pulse_kc_key'], isset($payload['reason']) ? $payload['reason'] : 'queued revoke'); }
                elseif ($j['type'] === 'audit_pull') { PulseKcAudit::pull((int) $j['id_pulse_kc_door']); }
                elseif ($j['type'] === 'blacklist') { PulseKcKey::blacklistSerial(isset($payload['card_serial']) ? $payload['card_serial'] : '', (int) $j['id_pulse_kc_encoder']); }
                Db::getInstance()->update('pulse_kc_job', array('status' => 'done', 'attempts' => $attempts, 'last_error' => '', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_job='.(int) $j['id_pulse_kc_job']);
                $done++;
            } catch (Exception $e) {
                $give = $attempts >= (int) $maxAttempts;
                Db::getInstance()->update('pulse_kc_job', array('status' => $give ? 'failed' : 'queued', 'attempts' => $attempts, 'last_error' => pSQL(Tools::substr($e->getMessage(), 0, 255)),
                    'next_try_at' => date('Y-m-d H:i:s', time() + min(3600, 300 * $attempts)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_job='.(int) $j['id_pulse_kc_job']);
                if ($give) { $failed++; }
            }
        }
        return array('done' => $done, 'failed' => $failed);
    }

    public static function jobs($status = 'queued,failed')
    {
        return Db::getInstance()->executeS('SELECT j.*, k.key_no, k.room_nums FROM `'._DB_PREFIX_.'pulse_kc_job` j LEFT JOIN `'._DB_PREFIX_.'pulse_kc_key` k ON k.id_pulse_kc_key=j.id_pulse_kc_key
            WHERE j.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'") ORDER BY j.id_pulse_kc_job DESC LIMIT 100');
    }

    /* ---------- desk lookups ---------- */

    /** Room + in-house guest search for the Key Desk: matches room number, guest name or folio/booking id. */
    public static function search($q)
    {
        $q = trim((string) $q);
        $where = $q === '' ? '' : ' AND (r.room_num LIKE "%'.pSQL($q).'%" OR CONCAT(c.firstname," ",c.lastname) LIKE "%'.pSQL($q).'%" OR b.id='.(int) $q.')';
        if (!class_exists('HotelBookingDetail')) { return array(); }
        return Db::getInstance()->executeS('SELECT b.id id_htl_booking, b.id_customer, b.id_room, b.date_from, b.date_to, r.room_num, r.floor,
                CONCAT(c.firstname," ",c.lastname) guest, c.email, pl.name room_type,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_key` k WHERE k.id_htl_booking=b.id AND k.status="issued") active_keys
            FROM `'._DB_PREFIX_.'htl_booking_detail` b
            INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room
            LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer
            LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=b.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' AND pl.id_shop='.(int) Context::getContext()->shop->id.'
            WHERE b.is_cancelled=0 AND b.is_refunded=0 AND b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.$where.'
            ORDER BY r.room_num LIMIT 40');
    }

    /** Arrivals due today that have no key yet — the "cut keys before the rush" list. */
    public static function arrivalsWithoutKeys()
    {
        if (!class_exists('HotelBookingDetail')) { return array(); }
        return Db::getInstance()->executeS('SELECT b.id id_htl_booking, b.id_room, b.date_from, b.date_to, r.room_num, CONCAT(c.firstname," ",c.lastname) guest
            FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room
            LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer
            WHERE b.is_cancelled=0 AND b.is_refunded=0 AND b.date_from="'.pSQL(self::bd()).'" AND b.id_status='.(int) HotelBookingDetail::STATUS_ALLOTED.'
              AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_kc_key` k WHERE k.id_htl_booking=b.id AND k.status IN ("issued","pending")) ORDER BY r.room_num');
    }

    /** Counters for the Key Desk header. */
    public static function dashboard()
    {
        $db = Db::getInstance();
        return array(
            'issued_today' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_key` WHERE business_date="'.pSQL(self::bd()).'" AND status="issued"'),
            'active' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_key` WHERE status="issued" AND valid_to>NOW()'),
            'failed' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_key` WHERE status="failed"'),
            'queued' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_job` WHERE status="queued"'),
            'mobile' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_mobile_key` WHERE status IN ("issued","active") AND valid_to>NOW()'),
            'encoders_offline' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_encoder` WHERE active=1 AND status="offline"'),
            'low_battery' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_door` WHERE active=1 AND battery_pct IS NOT NULL AND battery_pct<='.(int) self::cfg('BATTERY_PCT', 20)),
            'denied_24h' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_lock_audit` WHERE result="denied" AND opened_at>DATE_SUB(NOW(), INTERVAL 24 HOUR)'),
        );
    }

    /** Desk trace when something needs a human — silently ignored if Front Desk is not installed. */
    public static function alert($text, $idBooking = null, $idRoom = null)
    {
        if (class_exists('PulseTrace')) { PulseTrace::add('alert', $text, date('Y-m-d H:i:s'), $idBooking ? (int) $idBooking : null, $idRoom ? (int) $idRoom : null, null, 'frontdesk'); }
        return PulseCoreService::audit('pulsekeycard', 'alert', array('text' => $text, 'id_room' => $idRoom), 'htl_booking_detail', $idBooking);
    }
}
