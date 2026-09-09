<?php
/**
 * Guest service requests from the TV: housekeeping, maintenance, laundry pickup, DND / make-up-room,
 * wake-up calls, late check-out and express check-out. Every request becomes a real Pulse ticket or
 * housekeeping task where those modules exist, and always leaves a row here with a live status so the
 * guest sees what happened — including when an integration was down and the desk has to pick it up.
 */
class PulseGpRequest
{
    const TYPES = array(
        'housekeeping' => 'Clean my room now', 'turndown' => 'Turndown service', 'towels' => 'Extra towels', 'amenities' => 'Toiletries & amenities',
        'maintenance' => 'Something needs fixing', 'laundry' => 'Laundry pickup', 'dnd_on' => 'Do not disturb', 'dnd_off' => 'Do not disturb off',
        'mur' => 'Make up my room', 'wakeup' => 'Wake-up call', 'late_checkout' => 'Late check-out', 'express_checkout' => 'Express check-out',
        'transport' => 'Airport transfer / taxi', 'other' => 'Something else',
    );

    public static function label($type) { return isset(self::TYPES[$type]) ? self::TYPES[$type] : $type; }

    /**
     * Create a request. $device gives the room, $session the stay (required — nothing personal without a
     * checked-in booking). $d may carry detail, qty and scheduled_for (wake-up).
     */
    public static function create($type, array $device, array $session, array $d = array())
    {
        if (!isset(self::TYPES[$type])) { throw new PrestaShopException('Unknown request type', 400); }
        $idRoom = (int) $device['id_room']; $idBooking = (int) $session['id_htl_booking'];
        $b = PulseGpService::booking($idBooking);
        $now = date('Y-m-d H:i:s');
        $sched = null;
        if ($type === 'wakeup') {
            $sched = self::parseWhen(isset($d['scheduled_for']) ? $d['scheduled_for'] : '');
            if (!$sched) { throw new PrestaShopException('A wake-up time is required (HH:MM)', 400); }
        }
        if ($type === 'late_checkout' && !empty($d['scheduled_for'])) { $sched = self::parseWhen($d['scheduled_for'], true); }
        // one open request of the same kind per stay is enough — a guest pressing twice must not raise two tickets
        $dup = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_request` WHERE id_htl_booking='.$idBooking.' AND type="'.pSQL($type).'" AND status IN ("new","ack","in_progress") AND date_add>DATE_SUB(NOW(), INTERVAL 30 MINUTE)');
        if ($dup && !in_array($type, array('wakeup', 'other', 'maintenance'))) { return self::view($dup); }
        Db::getInstance()->insert('pulse_gp_request', array(
            'request_no' => pSQL(PulseGpService::nextNo('GR')), 'type' => pSQL($type), 'id_pulse_gp_device' => (int) $device['id_pulse_gp_device'],
            'id_room' => $idRoom ? $idRoom : null, 'room_num' => pSQL($device['room_num']), 'id_htl_booking' => $idBooking, 'id_customer' => (int) $session['id_customer'],
            'guest_name' => pSQL($b ? $b['guest'] : $session['guest_name']), 'detail' => pSQL(Tools::substr((string) (isset($d['detail']) ? $d['detail'] : ''), 0, 255)),
            'qty' => max(1, (int) (isset($d['qty']) ? $d['qty'] : 1)), 'scheduled_for' => $sched ? pSQL($sched) : null, 'locale' => pSQL($session['locale']),
            'business_date' => pSQL(PulseGpService::bd()), 'date_add' => $now, 'date_upd' => $now,
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        try { self::dispatch($id, $type, $idRoom, $idBooking, $session, $d, $sched); }
        catch (Exception $e) { Db::getInstance()->update('pulse_gp_request', array('status' => 'failed', 'fail_reason' => pSQL(Tools::substr($e->getMessage(), 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_request='.$id);
            if (class_exists('PulseTrace')) { PulseTrace::add('alert', 'Portal request '.self::label($type).' from room '.$device['room_num'].' could not be routed: '.$e->getMessage(), $now, $idBooking, $idRoom, null, 'frontdesk'); } }
        PulseGpService::audit('request', array('type' => $type, 'room' => $device['room_num']), 'pulse_gp_request', $id);
        PulseGpService::event('actionPulsePortalRequest', array('id_request' => $id, 'type' => $type, 'id_room' => $idRoom, 'id_htl_booking' => $idBooking));
        return self::get($id);
    }

    /** Route the request into the module that actually does the work. */
    protected static function dispatch($id, $type, $idRoom, $idBooking, array $session, array $d, $sched)
    {
        $detail = isset($d['detail']) ? Tools::substr((string) $d['detail'], 0, 255) : '';
        $u = array('date_upd' => date('Y-m-d H:i:s'));
        switch ($type) {
            case 'housekeeping': case 'turndown': case 'mur':
                if (class_exists('PulseHousekeeping')) { $u['id_hk_task'] = (int) PulseHousekeeping::createTask($idRoom, $type === 'turndown' ? 'turndown' : 'clean', $type === 'mur' ? 1 : 2, 'Guest asked from the TV: '.self::label($type).($detail ? ' — '.$detail : '')); $u['status'] = 'ack'; }
                break;
            case 'towels': case 'amenities':
                $u['id_pulse_ticket'] = self::ticket('housekeeping', 'housekeeping', 'normal', self::label($type).' — room '.self::roomNum($idRoom), $detail ? $detail : self::label($type), $idRoom, $idBooking, $session);
                if (class_exists('PulseHousekeeping')) { $u['id_hk_task'] = (int) PulseHousekeeping::createTask($idRoom, $type === 'towels' ? 'linen' : 'clean', 3, self::label($type).($detail ? ' — '.$detail : '')); }
                $u['status'] = 'ack';
                break;
            case 'maintenance':
                $u['id_pulse_ticket'] = self::ticket('maintenance', 'maintenance', 'high', 'Room '.self::roomNum($idRoom).' — guest reported a fault', $detail ? $detail : 'Reported from the in-room TV', $idRoom, $idBooking, $session);
                $u['status'] = 'ack';
                break;
            case 'laundry':
                $u['ext_ref'] = self::laundryPickup($idRoom, $detail, isset($d['service']) ? $d['service'] : 'normal');
                $u['status'] = 'ack';
                break;
            case 'transport': case 'other':
                $u['id_pulse_ticket'] = self::ticket('service', 'frontdesk', 'normal', self::label($type).' — room '.self::roomNum($idRoom), $detail ? $detail : self::label($type), $idRoom, $idBooking, $session);
                $u['status'] = 'ack';
                break;
            case 'wakeup':
                if (class_exists('PulseTrace')) { PulseTrace::add('wake_up', 'Wake-up call requested from the TV', $sched, $idBooking, $idRoom, (int) $session['id_customer'], 'frontdesk'); }
                $u['status'] = 'ack';
                break;
            case 'late_checkout':
                $u['id_pulse_ticket'] = self::ticket('service', 'frontdesk', 'normal', 'Late check-out requested — room '.self::roomNum($idRoom), 'Guest asked for late check-out'.($sched ? ' until '.$sched : '').($detail ? '. '.$detail : ''), $idRoom, $idBooking, $session);
                $u['status'] = 'ack';
                break;
            case 'express_checkout':
                $u['id_pulse_ticket'] = self::ticket('service', 'frontdesk', 'high', 'Express check-out — room '.self::roomNum($idRoom), 'Guest asked to check out from the TV. Bill reviewed on screen.'.($detail ? ' '.$detail : ''), $idRoom, $idBooking, $session);
                if (class_exists('PulseTrace')) { PulseTrace::add('alert', 'Express check-out asked from room '.self::roomNum($idRoom), date('Y-m-d H:i:s'), $idBooking, $idRoom, null, 'frontdesk'); }
                $u['status'] = 'ack';
                break;
            case 'dnd_on':
                self::closeOpen($idBooking, 'dnd_off'); $u['status'] = 'ack';
                if (class_exists('PulseTrace')) { PulseTrace::add('trace', 'Do not disturb switched ON from the TV — room '.self::roomNum($idRoom), date('Y-m-d H:i:s'), $idBooking, $idRoom, null, 'housekeeping'); }
                break;
            case 'dnd_off':
                self::closeOpen($idBooking, 'dnd_on'); $u['status'] = 'done';
                break;
        }
        Db::getInstance()->update('pulse_gp_request', $u, 'id_pulse_gp_request='.(int) $id);
    }

    protected static function roomNum($idRoom) { return (string) Db::getInstance()->getValue('SELECT room_num FROM `'._DB_PREFIX_.'htl_room_information` WHERE id='.(int) $idRoom); }
    protected static function ticket($category, $department, $priority, $title, $body, $idRoom, $idBooking, array $session)
    {
        if (!class_exists('PulseTicket')) { return null; }
        return (int) PulseTicket::create(array('category' => $category, 'department' => $department, 'priority' => $priority, 'title' => $title, 'description' => $body,
            'id_room' => $idRoom, 'id_htl_booking' => $idBooking, 'id_customer' => (int) $session['id_customer'], 'source' => 'portal'));
    }

    /**
     * Laundry pickup. In-process when Pulse Laundry is installed (nothing to go wrong at 2 a.m.), otherwise
     * the documented HTTP endpoint with a timeout so a slow link cannot hang the TV.
     */
    public static function laundryPickup($idRoom, $note, $service = 'normal')
    {
        if (PulseGpService::laundry()) {
            $items = PulseLaundryService::items();
            if (!$items) { throw new PrestaShopException('No laundry price list is configured'); }
            $id = PulseLaundryService::createOrder('guest', array(array('id_item' => (int) $items[0]['id_pulse_laundry_item'], 'process' => 'wash', 'qty' => 1)),
                array('id_room' => (int) $idRoom, 'service' => in_array($service, array('normal', 'express', 'same_day')) ? $service : 'normal', 'note' => 'Portal request'.($note ? ': '.$note : '')));
            return 'laundry:'.(int) $id;
        }
        $url = PulseGpService::cfg('LAUNDRY_API', '');
        $token = PulseGpService::cfg('API_TOKEN', '');
        if (!$url || !$token) { throw new PrestaShopException('Laundry is not available from the portal — the front desk will arrange the pickup'); }
        $ch = curl_init(rtrim($url, '/').'/request/'.(int) $idRoom);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => (int) PulseGpService::cfg('HTTP_TIMEOUT', 6), CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_POST => 1,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer '.$token), CURLOPT_POSTFIELDS => json_encode(array('service' => $service, 'note' => $note))));
        $res = curl_exec($ch); $err = curl_error($ch); $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($err !== '' || $http >= 400) { throw new PrestaShopException('Laundry service did not answer ('.($err ? $err : 'HTTP '.$http).')'); }
        $j = json_decode($res, true);
        return 'laundry:'.(isset($j['data']['id_order']) ? (int) $j['data']['id_order'] : 0);
    }

    /** "07:30", "2026-09-09 07:30" or "tomorrow 07:30" — always resolved into the next occurrence. */
    public static function parseWhen($v, $todayOnly = false)
    {
        $v = trim((string) $v);
        if ($v === '') { return null; }
        if (preg_match('/^([01]?[0-9]|2[0-3]):([0-5][0-9])$/', $v, $m)) {
            $t = strtotime(date('Y-m-d').' '.str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.$m[2].':00');
            if (!$todayOnly && $t <= time()) { $t = strtotime('+1 day', $t); }
            return date('Y-m-d H:i:s', $t);
        }
        $t = strtotime($v);
        return $t ? date('Y-m-d H:i:s', $t) : null;
    }

    public static function closeOpen($idBooking, $type) { return Db::getInstance()->update('pulse_gp_request', array('status' => 'done', 'date_upd' => date('Y-m-d H:i:s')), 'id_htl_booking='.(int) $idBooking.' AND type="'.pSQL($type).'" AND status IN ("new","ack","in_progress")'); }
    public static function dndOn($idBooking) { return (bool) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_gp_request` WHERE id_htl_booking='.(int) $idBooking.' AND type="dnd_on" AND status IN ("new","ack","in_progress")'); }

    public static function get($id) { $r = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_request` WHERE id_pulse_gp_request='.(int) $id); return $r ? self::view($r) : null; }
    /** What the guest screen is allowed to see about a request. */
    public static function view(array $r)
    {
        return array('id' => (int) $r['id_pulse_gp_request'], 'request_no' => $r['request_no'], 'type' => $r['type'], 'label' => self::label($r['type']),
            'status' => $r['status'], 'detail' => $r['detail'], 'scheduled_for' => $r['scheduled_for'], 'date_add' => $r['date_add'], 'ticket' => (int) $r['id_pulse_ticket']);
    }
    public static function recent($idRoom, $idBooking, $limit = 12)
    {
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_request` WHERE id_htl_booking='.(int) $idBooking.' ORDER BY id_pulse_gp_request DESC LIMIT '.(int) $limit);
        $out = array();
        foreach ($rows as $r) { $out[] = self::view($r); }
        return $out;
    }

    /** Desk queue for the dashboard, joined to the ticket status so one screen tells the whole story. */
    public static function queue($status = 'new,ack,in_progress', $date = null)
    {
        $st = '"'.implode('","', array_map('pSQL', explode(',', $status))).'"';
        $hasTickets = (bool) Db::getInstance()->getValue('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_ticket"');
        return Db::getInstance()->executeS('SELECT r.*'.($hasTickets ? ', t.status ticket_status, t.ticket_no' : ', NULL ticket_status, NULL ticket_no').'
            FROM `'._DB_PREFIX_.'pulse_gp_request` r '.($hasTickets ? 'LEFT JOIN `'._DB_PREFIX_.'pulse_ticket` t ON t.id_pulse_ticket=r.id_pulse_ticket ' : '').'
            WHERE r.status IN ('.$st.')'.($date ? ' AND r.business_date="'.pSQL($date).'"' : '').' ORDER BY FIELD(r.status,"new","ack","in_progress"), r.id_pulse_gp_request DESC LIMIT 200');
    }
    public static function setStatus($id, $status)
    {
        if (!in_array($status, array('new', 'ack', 'in_progress', 'done', 'cancelled', 'failed'))) { throw new PrestaShopException('Unknown status'); }
        Db::getInstance()->update('pulse_gp_request', array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_request='.(int) $id);
        $r = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_request` WHERE id_pulse_gp_request='.(int) $id);
        if ($r && $r['id_room'] && in_array($status, array('in_progress', 'done'))) {
            foreach (PulseGpDevice::byRoom((int) $r['id_room']) as $d) { PulseGpDevice::command((int) $d['id_pulse_gp_device'], 'notify', array('kind' => 'request', 'text' => self::label($r['type']).' — '.$status)); }
        }
        return true;
    }

    /** Wake-up calls that have come due; the cron rings them through Comms and the PABX trace. */
    public static function dueWakeups()
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_request` WHERE type="wakeup" AND status IN ("new","ack") AND scheduled_for IS NOT NULL AND scheduled_for<=NOW()');
    }
}
