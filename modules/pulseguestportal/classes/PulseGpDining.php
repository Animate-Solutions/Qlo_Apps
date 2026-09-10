<?php
/**
 * In-room dining. The menu, the check and the kitchen fire all belong to Pulse POS — this class is the
 * translation layer between a TV cart and a real room-service check, plus the guest-facing order tracker.
 * With POS absent the section simply reports itself unavailable rather than pretending to take orders.
 */
class PulseGpDining
{
    /** The room-service outlet: a setting when the property has more than one, otherwise the first active one. */
    public static function outlet()
    {
        $id = (int) PulseGpService::cfg('ORDER_OUTLET', 0);
        if ($id && Db::getInstance()->getValue('SELECT id_pulse_pos_outlet FROM `'._DB_PREFIX_.'pulse_pos_outlet` WHERE id_pulse_pos_outlet='.$id.' AND active=1')) { return $id; }
        return (int) Db::getInstance()->getValue('SELECT id_pulse_pos_outlet FROM `'._DB_PREFIX_.'pulse_pos_outlet` WHERE type="room_service" AND active=1 ORDER BY id_pulse_pos_outlet');
    }

    /**
     * Menu for the TV: only available items, images and allergen notes where the kitchen has recorded them,
     * with the current service period so the screen can say "breakfast until 10:30".
     */
    public static function menu($lang = 'en')
    {
        if (!PulseGpService::pos()) { return array('available' => 0, 'reason' => 'F&B ordering is not switched on', 'categories' => array(), 'items' => array(), 'modifiers' => array()); }
        $o = self::outlet();
        if (!$o) { return array('available' => 0, 'reason' => 'No room-service outlet is configured', 'categories' => array(), 'items' => array(), 'modifiers' => array()); }
        $m = PulsePosService::menu($o);
        $allergens = json_decode((string) PulseCoreService::setting('pulseguestportal', 'allergens'), true);
        if (!is_array($allergens)) { $allergens = array(); }
        $images = Db::getInstance()->executeS('SELECT id_pulse_pos_item, image, prep_minutes FROM `'._DB_PREFIX_.'pulse_pos_item` WHERE image<>"" OR prep_minutes>0');
        $meta = array();
        foreach ($images as $i) { $meta[(int) $i['id_pulse_pos_item']] = array('image' => $i['image'], 'prep' => (int) $i['prep_minutes']); }
        $items = array();
        foreach ($m['items'] as $i) {
            if (!$i['avail']) { continue; }
            $items[] = array('id' => (int) $i['id'], 'name' => $i['name'], 'cat' => (int) $i['cat'], 'price' => round((float) $i['price'], 2), 'mods' => $i['mods'],
                'image' => isset($meta[$i['id']]) ? $meta[$i['id']]['image'] : '', 'prep' => isset($meta[$i['id']]) ? $meta[$i['id']]['prep'] : 0,
                'allergens' => isset($allergens[$i['id']]) ? $allergens[$i['id']] : '');
        }
        $tray = (float) Db::getInstance()->getValue('SELECT tray_charge FROM `'._DB_PREFIX_.'pulse_pos_outlet` WHERE id_pulse_pos_outlet='.(int) $o);
        return array('available' => 1, 'outlet' => $o, 'period' => isset($m['price_level']['period']) ? $m['price_level']['period'] : null, 'tray_charge' => round($tray, 2),
            'periods' => Db::getInstance()->executeS('SELECT name, start_time, end_time FROM `'._DB_PREFIX_.'pulse_pos_service_period` WHERE active=1 AND (id_pulse_pos_outlet IS NULL OR id_pulse_pos_outlet='.(int) $o.') ORDER BY start_time'),
            'categories' => $m['categories'], 'items' => $items, 'modifiers' => $m['modifiers'], 'lang' => PulseGpService::lang($lang));
    }

    /** Allergen notes are portal metadata, not POS data — the kitchen keeps them here per item. */
    public static function allergens() { $a = json_decode((string) PulseCoreService::setting('pulseguestportal', 'allergens'), true); return is_array($a) ? $a : array(); }
    public static function saveAllergens(array $map) { $clean = array(); foreach ($map as $id => $txt) { $txt = trim((string) $txt); if ($txt !== '') { $clean[(int) $id] = Tools::substr($txt, 0, 120); } } PulseCoreService::setting('pulseguestportal', 'allergens', json_encode($clean)); return count($clean); }

    /**
     * Place an order: opens a real POS check of type room_service against the room's in-house booking, adds
     * every line with its modifiers and fires it to the kitchen. $clientId makes an offline replay idempotent.
     */
    public static function order(array $device, array $session, array $lines, $note = '', $clientId = '')
    {
        if (!PulseGpService::pos()) { throw new PrestaShopException('Room service ordering is not available right now — please dial 0', 503); }
        // the key is client-supplied and client_id is globally unique, so it is namespaced to this screen:
        // one room can neither read nor block another room's order by guessing its idempotency key
        $clientId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $clientId);
        if ($clientId === '') { $clientId = md5(json_encode($lines).microtime(true)); }
        $clientId = Tools::substr('d'.(int) $device['id_pulse_gp_device'].'-'.$clientId, 0, 40);
        $seen = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_order` WHERE client_id="'.pSQL($clientId).'" AND id_pulse_gp_device='.(int) $device['id_pulse_gp_device']);
        if ($seen) { return self::view($seen); }
        if (!$lines) { throw new PrestaShopException('Your tray is empty', 400); }
        $o = self::outlet();
        if (!$o) { throw new PrestaShopException('No room-service outlet is configured', 503); }
        $emp = (int) Db::getInstance()->getValue('SELECT id_employee FROM `'._DB_PREFIX_.'pulse_pos_staff` WHERE role="manager" AND active=1 ORDER BY id_employee');
        if (!$emp) { $emp = (int) Db::getInstance()->getValue('SELECT id_employee FROM `'._DB_PREFIX_.'pulse_pos_staff` WHERE active=1 ORDER BY id_employee'); }
        if (!$emp) { throw new PrestaShopException('No POS user is configured to take portal orders', 503); }
        $now = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_gp_order', array('client_id' => pSQL($clientId), 'id_pulse_gp_device' => (int) $device['id_pulse_gp_device'],
            'id_room' => (int) $device['id_room'], 'room_num' => pSQL($device['room_num']), 'id_htl_booking' => (int) $session['id_htl_booking'], 'id_customer' => (int) $session['id_customer'],
            'items_json' => pSQL(json_encode($lines), true), 'items_count' => count($lines), 'note' => pSQL(Tools::substr((string) $note, 0, 255)),
            'business_date' => pSQL(PulseGpService::bd()), 'date_add' => $now, 'date_upd' => $now));
        $id = (int) Db::getInstance()->Insert_ID();
        if (!$id) { throw new PrestaShopException('That order could not be recorded — please dial 0 and the desk will take it', 503); }
        try {
            $idCheck = PulsePosService::open(array('id_outlet' => $o, 'order_type' => 'room_service', 'id_room' => (int) $device['id_room'], 'id_employee' => $emp, 'covers' => 1));
            foreach ($lines as $l) {
                PulsePosService::addLine($idCheck, (int) $l['id_item'], (float) (isset($l['qty']) ? $l['qty'] : 1), isset($l['mods']) && is_array($l['mods']) ? $l['mods'] : array(), 1,
                    'TV order'.(isset($l['note']) && $l['note'] ? ': '.Tools::substr($l['note'], 0, 100) : ''), $emp);
            }
            if ($note) { PulsePosService::update($idCheck, array('note' => Tools::substr($note, 0, 255)), $emp); }
            PulsePosService::send($idCheck, $emp);
            $c = PulsePosService::get($idCheck, false);
            Db::getInstance()->update('pulse_gp_order', array('id_pulse_pos_check' => (int) $idCheck, 'check_no' => pSQL($c['check_no']), 'total' => (float) $c['total'], 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_order='.$id);
        } catch (Exception $e) {
            Db::getInstance()->update('pulse_gp_order', array('status' => 'failed', 'fail_reason' => pSQL(Tools::substr($e->getMessage(), 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_order='.$id);
            if (class_exists('PulseTrace')) { PulseTrace::add('alert', 'Room service order from room '.$device['room_num'].' failed: '.$e->getMessage(), $now, (int) $session['id_htl_booking'], (int) $device['id_room'], null, 'fnb'); }
            throw new PrestaShopException('The kitchen could not take that order — the front desk has been told ('.$e->getMessage().')', 503);
        }
        PulseGpService::audit('order', array('room' => $device['room_num'], 'lines' => count($lines)), 'pulse_gp_order', $id);
        PulseGpService::event('actionPulsePortalOrder', array('id_order' => $id, 'id_check' => (int) $idCheck, 'id_room' => (int) $device['id_room']));
        return self::get($id);
    }

    /** $idBooking scopes the read to one stay — the TV must never be able to read another room's order by id. */
    public static function get($id, $idBooking = 0) { $r = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_order` WHERE id_pulse_gp_order='.(int) $id.($idBooking ? ' AND id_htl_booking='.(int) $idBooking : '')); return $r ? self::view($r) : null; }
    public static function view(array $r)
    {
        $kitchen = null;
        if ($r['id_pulse_pos_check'] && PulseGpService::pos()) {
            $kitchen = Db::getInstance()->getValue('SELECT GROUP_CONCAT(DISTINCT kot_status) FROM `'._DB_PREFIX_.'pulse_pos_check_line` WHERE id_pulse_pos_check='.(int) $r['id_pulse_pos_check'].' AND voided=0');
        }
        return array('id' => (int) $r['id_pulse_gp_order'], 'client_id' => $r['client_id'], 'check_no' => $r['check_no'], 'status' => $r['status'], 'kitchen' => $kitchen,
            'items' => (int) $r['items_count'], 'total' => round((float) $r['total'], 2), 'note' => $r['note'], 'fail_reason' => $r['fail_reason'],
            'date_add' => $r['date_add'], 'date_ready' => $r['date_ready'], 'eta_minutes' => self::eta($r));
    }
    /** A believable ETA: the longest prep time on the check plus the delivery allowance, from the fire time. */
    protected static function eta(array $r)
    {
        if (in_array($r['status'], array('ready', 'delivered', 'cancelled', 'failed'))) { return 0; }
        $prep = (int) PulseGpService::cfg('DELIVERY_MINUTES', 10);
        if ($r['id_pulse_pos_check'] && PulseGpService::pos()) { $prep += (int) Db::getInstance()->getValue('SELECT COALESCE(MAX(i.prep_minutes),15) FROM `'._DB_PREFIX_.'pulse_pos_check_line` l INNER JOIN `'._DB_PREFIX_.'pulse_pos_item` i ON i.id_pulse_pos_item=l.id_pulse_pos_item WHERE l.id_pulse_pos_check='.(int) $r['id_pulse_pos_check'].' AND l.voided=0'); }
        else { $prep += 15; }
        $left = (int) ceil(($prep * 60 - (time() - strtotime($r['date_add']))) / 60);
        return max(0, $left);
    }

    /** The tracker on the screen: this stay's orders only, so the next guest in the room never sees the last one's. */
    public static function roomOrders($idRoom, $hours = 24, $idBooking = 0)
    {
        $out = array();
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_order` WHERE id_room='.(int) $idRoom.($idBooking ? ' AND id_htl_booking='.(int) $idBooking : '').' AND date_add>DATE_SUB(NOW(), INTERVAL '.(int) $hours.' HOUR) ORDER BY id_pulse_gp_order DESC LIMIT 10') as $r) { $out[] = self::view($r); }
        return $out;
    }

    /** Called from the POS item-ready event: mark the order ready and push the TV a notification. */
    public static function markReady($idCheck)
    {
        $r = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_order` WHERE id_pulse_pos_check='.(int) $idCheck);
        if (!$r || in_array($r['status'], array('ready', 'delivered', 'cancelled'))) { return false; }
        $pending = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pos_check_line` WHERE id_pulse_pos_check='.(int) $idCheck.' AND voided=0 AND kot_status IN ("pending","held","fired","preparing")');
        $status = $pending ? 'preparing' : 'ready';
        Db::getInstance()->update('pulse_gp_order', array('status' => $status, 'date_ready' => $status === 'ready' ? date('Y-m-d H:i:s') : null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_order='.(int) $r['id_pulse_gp_order']);
        if ($status === 'ready' && $r['id_room']) {
            foreach (PulseGpDevice::byRoom((int) $r['id_room']) as $d) { PulseGpDevice::command((int) $d['id_pulse_gp_device'], 'order_ready', array('check_no' => $r['check_no'], 'text' => 'Your order is on its way up')); }
        }
        return $status;
    }
    /** Settled or delivered checks close the tracker. */
    public static function markDelivered($idCheck) { return Db::getInstance()->update('pulse_gp_order', array('status' => 'delivered', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pos_check='.(int) $idCheck.' AND status<>"cancelled"'); }

    public static function today($date = null) { $d = $date ? $date : PulseGpService::bd(); return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_order` WHERE business_date="'.pSQL($d).'" ORDER BY id_pulse_gp_order DESC'); }
}
