<?php
/** Laundry operations: guest laundry orders (post-to-folio), house linen, vendors, claims. Works without Front Desk (charges then stay unposted). */
class PulseLaundryService
{
    public static function fd() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFolio'); }
    protected static function bd() { return class_exists('PulseCore') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    protected static function emp() { $c = Context::getContext(); return isset($c->employee) ? (int) $c->employee->id : 0; }
    protected static function nextNo($prefix) { $n = (int) PulseCoreService::setting('pulselaundry', 'seq_'.$prefix) + 1; PulseCoreService::setting('pulselaundry', 'seq_'.$prefix, $n); return $prefix.date('ymd').str_pad($n % 10000, 4, '0', STR_PAD_LEFT); }

    public static function items() { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_laundry_item` WHERE active=1 ORDER BY category, sort, name'); }
    public static function surcharge($service) { return $service === 'express' ? (float) Configuration::get('PULSE_LDY_EXPRESS_PCT') : ($service === 'same_day' ? (float) Configuration::get('PULSE_LDY_SAMEDAY_PCT') : 0); }
    public static function turnaround($service) { return (int) ($service === 'express' ? Configuration::get('PULSE_LDY_EXPRESS_HRS') : ($service === 'same_day' ? Configuration::get('PULSE_LDY_SAMEDAY_HRS') : Configuration::get('PULSE_LDY_NORMAL_HRS'))); }

    /** Guest laundry lookup for a room: in-house booking + customer name. */
    public static function guestForRoom($idRoom)
    {
        if (!class_exists('HotelBookingDetail')) { return null; }
        return Db::getInstance()->getRow('SELECT b.id id_htl_booking, b.id_customer, CONCAT(c.firstname," ",c.lastname) guest, r.room_num FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room WHERE b.id_room='.(int) $idRoom.' AND b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND b.is_cancelled=0 AND b.is_refunded=0');
    }

    /** Create an order. $lines = [[id_item, process, qty, condition_note], ...] */
    public static function createOrder($type, array $lines, array $o = array())
    {
        $service = isset($o['service']) ? $o['service'] : 'normal'; $sur = self::surcharge($service);
        $g = ($type === 'guest' && !empty($o['id_room'])) ? self::guestForRoom($o['id_room']) : null;
        $pieces = 0; $sub = 0; $prepared = array();
        foreach ($lines as $l) {
            if ((int) $l['qty'] < 1) { continue; }
            $it = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_laundry_item` WHERE id_pulse_laundry_item='.(int) $l['id_item']);
            if (!$it) { continue; }
            $price = $type === 'guest' ? (float) $it['price_'.$l['process']] : 0;
            $lt = round($price * (int) $l['qty'] * (1 + $sur / 100), 2);
            $prepared[] = array('id_pulse_laundry_item' => (int) $it['id_pulse_laundry_item'], 'item_name' => pSQL($it['name']), 'process' => pSQL($l['process']), 'qty' => (int) $l['qty'], 'unit_price' => $price, 'line_total' => $lt, 'condition_note' => pSQL(isset($l['condition_note']) ? $l['condition_note'] : ''));
            $pieces += (int) $l['qty']; $sub += $lt;
        }
        if (!$prepared) { throw new PrestaShopException('No items'); }
        $tax = $type === 'guest' && empty($o['complimentary']) ? (float) Configuration::get('PULSE_LDY_TAX_PCT') : 0;
        $total = round($sub * (1 + $tax / 100), 2);
        Db::getInstance()->insert('pulse_laundry_order', array(
            'order_no' => self::nextNo('L'), 'type' => pSQL($type), 'id_room' => !empty($o['id_room']) ? (int) $o['id_room'] : null, 'id_htl_booking' => $g ? (int) $g['id_htl_booking'] : null, 'id_customer' => $g ? (int) $g['id_customer'] : null,
            'guest_name' => pSQL($g ? $g['guest'] : (isset($o['guest_name']) ? $o['guest_name'] : '')), 'department' => pSQL(isset($o['department']) ? $o['department'] : ''), 'service' => pSQL($service), 'surcharge_pct' => $sur,
            'id_vendor' => !empty($o['id_vendor']) ? (int) $o['id_vendor'] : null, 'pieces' => $pieces, 'subtotal' => empty($o['complimentary']) ? $sub : 0, 'total_tax_incl' => empty($o['complimentary']) ? $total : 0, 'complimentary' => !empty($o['complimentary']) ? 1 : 0,
            'promised_at' => date('Y-m-d H:i:s', time() + self::turnaround($service) * 3600), 'note' => pSQL(isset($o['note']) ? $o['note'] : ''), 'business_date' => self::bd(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        foreach ($prepared as $p) { $p['id_pulse_laundry_order'] = $id; Db::getInstance()->insert('pulse_laundry_order_line', $p); }
        PulseCoreService::audit('pulselaundry', 'order_create', array('pieces' => $pieces, 'total' => $total), 'pulse_laundry_order', $id);
        PulseCoreService::event('actionPulseLaundryOrder', array('id_order' => $id, 'type' => $type, 'id_room' => isset($o['id_room']) ? $o['id_room'] : null));
        return $id;
    }

    /** Advance status; posting to folio happens at 'delivered' (or 'ready' if setting POST_AT=ready). */
    public static function setStatus($id, $status)
    {
        $o = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_laundry_order` WHERE id_pulse_laundry_order='.(int) $id);
        if (!$o) { return false; }
        $upd = array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s'));
        if ($status === 'collected') { $upd['collected_at'] = date('Y-m-d H:i:s'); $upd['collected_by'] = self::emp(); }
        if ($status === 'ready') { $upd['ready_at'] = date('Y-m-d H:i:s'); }
        if ($status === 'delivered') { $upd['delivered_at'] = date('Y-m-d H:i:s'); $upd['delivered_by'] = self::emp(); }
        Db::getInstance()->update('pulse_laundry_order', $upd, 'id_pulse_laundry_order='.(int) $id);
        $postAt = Configuration::get('PULSE_LDY_POST_AT') ?: 'delivered';
        if ($status === $postAt && $o['type'] === 'guest' && !$o['posted_line'] && !$o['complimentary'] && $o['total_tax_incl'] > 0) { self::postToFolio($id); }
        if ($status === 'delivered' && $o['type'] === 'guest' && class_exists('PulseComms') && $o['id_customer']) { PulseComms::send('laundry_delivered', new Customer((int) $o['id_customer']), array('order_no' => $o['order_no'], 'id_htl_booking' => $o['id_htl_booking'])); }
        PulseCoreService::event('actionPulseLaundryStatus', array('id_order' => $id, 'status' => $status, 'id_room' => $o['id_room']));
        return true;
    }

    public static function postToFolio($id)
    {
        if (!self::fd()) { return false; }
        $o = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_laundry_order` WHERE id_pulse_laundry_order='.(int) $id);
        if (!$o || !$o['id_htl_booking']) { return false; }
        $f = PulseFolio::openForBooking($o['id_htl_booking']);
        if (!$f) { return false; }
        $tax = (float) Configuration::get('PULSE_LDY_TAX_PCT');
        $line = $f->post('LNDY', 'Laundry '.$o['order_no'].' — '.$o['pieces'].' pc'.($o['service'] !== 'normal' ? ' ('.$o['service'].')' : ''), 1, (float) $o['subtotal'], $tax, false, null, 'laundry', $o['order_no']);
        Db::getInstance()->update('pulse_laundry_order', array('posted_line' => (int) $line), 'id_pulse_laundry_order='.(int) $id);
        return $line;
    }

    public static function orders($status = null, $type = null, $date = null)
    {
        return Db::getInstance()->executeS('SELECT o.*, r.room_num, v.name vendor, CONCAT(e.firstname," ",e.lastname) collector FROM `'._DB_PREFIX_.'pulse_laundry_order` o LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=o.id_room LEFT JOIN `'._DB_PREFIX_.'pulse_laundry_vendor` v ON v.id_pulse_laundry_vendor=o.id_vendor LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=o.collected_by
            WHERE 1'.($status ? ' AND o.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'")' : '').($type ? ' AND o.type="'.pSQL($type).'"' : '').($date ? ' AND o.business_date="'.pSQL($date).'"' : '').' ORDER BY FIELD(o.service,"same_day","express","normal"), o.promised_at');
    }

    public static function order($id)
    {
        $o = Db::getInstance()->getRow('SELECT o.*, r.room_num FROM `'._DB_PREFIX_.'pulse_laundry_order` o LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=o.id_room WHERE o.id_pulse_laundry_order='.(int) $id);
        if ($o) { $o['lines'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_laundry_order_line` WHERE id_pulse_laundry_order='.(int) $id); $o['claims'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_laundry_claim` WHERE id_pulse_laundry_order='.(int) $id); }
        return $o;
    }

    /** Damage / loss claim; on settle, optionally credit the guest folio and open a Front Desk ticket. */
    public static function claim($idOrder, $type, $desc, $amount, $idLine = null)
    {
        Db::getInstance()->insert('pulse_laundry_claim', array('id_pulse_laundry_order' => (int) $idOrder, 'id_pulse_laundry_order_line' => $idLine ? (int) $idLine : null, 'type' => pSQL($type), 'description' => pSQL($desc), 'amount_claimed' => (float) $amount, 'id_employee' => self::emp(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
        if ($idLine) { Db::getInstance()->update('pulse_laundry_order_line', array($type === 'loss' ? 'missing' : 'damaged' => 1), 'id_pulse_laundry_order_line='.(int) $idLine); }
        $id = (int) Db::getInstance()->Insert_ID();
        if (class_exists('PulseTicket')) { $o = Db::getInstance()->getRow('SELECT id_room, id_htl_booking, id_customer, order_no FROM `'._DB_PREFIX_.'pulse_laundry_order` WHERE id_pulse_laundry_order='.(int) $idOrder); PulseTicket::create(array('category' => 'complaint', 'department' => 'laundry', 'priority' => 'high', 'subject' => 'Laundry '.$type.' claim — '.$o['order_no'], 'body' => $desc, 'id_room' => $o['id_room'], 'id_htl_booking' => $o['id_htl_booking'], 'id_customer' => $o['id_customer'], 'source' => 'laundry')); }
        return $id;
    }

    public static function settleClaim($idClaim, $status, $amount, $how)
    {
        $c = Db::getInstance()->getRow('SELECT c.*, o.id_htl_booking, o.order_no FROM `'._DB_PREFIX_.'pulse_laundry_claim` c INNER JOIN `'._DB_PREFIX_.'pulse_laundry_order` o ON o.id_pulse_laundry_order=c.id_pulse_laundry_order WHERE c.id_pulse_laundry_claim='.(int) $idClaim);
        Db::getInstance()->update('pulse_laundry_claim', array('status' => pSQL($status), 'amount_settled' => (float) $amount, 'settled_how' => pSQL($how), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_laundry_claim='.(int) $idClaim);
        if ($status === 'settled' && $how === 'folio_credit' && (float) $amount > 0 && self::fd() && $c['id_htl_booking'] && ($f = PulseFolio::openForBooking($c['id_htl_booking']))) {
            $f->post('ADJ', 'Laundry claim credit '.$c['order_no'], 1, -(float) $amount, 0, false, null, 'laundry', 'claim:'.$idClaim);
        }
        return true;
    }

    /* ---------- linen ---------- */
    public static function linenMove($idType, $type, $qty, $idRoom = null, $reason = '')
    {
        $qty = (int) $qty; if ($qty <= 0) { throw new PrestaShopException('Quantity must be positive'); }
        $map = array('issue' => 'qty_clean=qty_clean-Q, qty_in_rooms=qty_in_rooms+Q', 'return' => 'qty_in_rooms=qty_in_rooms-Q, qty_soiled=qty_soiled+Q', 'to_wash' => 'qty_soiled=qty_soiled-Q, qty_in_wash=qty_in_wash+Q', 'from_wash' => 'qty_in_wash=qty_in_wash-Q, qty_clean=qty_clean+Q', 'discard' => 'qty_clean=qty_clean-Q, qty_discarded=qty_discarded+Q', 'purchase' => 'qty_clean=qty_clean+Q', 'count_adjust' => 'qty_clean=Q');
        if (!isset($map[$type])) { throw new PrestaShopException('Bad movement'); }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_linen_type` SET '.str_replace('Q', $qty, $map[$type]).' WHERE id_pulse_linen_type='.(int) $idType);
        Db::getInstance()->insert('pulse_linen_movement', array('id_pulse_linen_type' => (int) $idType, 'type' => pSQL($type), 'qty' => $qty, 'id_room' => $idRoom ? (int) $idRoom : null, 'reason' => pSQL($reason), 'id_employee' => self::emp(), 'business_date' => self::bd(), 'date_add' => date('Y-m-d H:i:s')));
        return true;
    }

    public static function linenStatus()
    {
        $rooms = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_status IN (1,3)');
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_linen_type` WHERE active=1 ORDER BY name');
        foreach ($rows as &$r) { $r['par_target'] = (int) ceil($r['par_per_room'] * $rooms); $r['in_circulation'] = $r['qty_clean'] + $r['qty_in_rooms'] + $r['qty_soiled'] + $r['qty_in_wash']; $r['shortfall'] = max(0, $r['par_target'] - $r['in_circulation']); $r['value'] = round($r['in_circulation'] * $r['unit_cost'], 2); }
        return $rows;
    }

    /* ---------- reports ---------- */
    public static function revenue($from, $to)
    {
        return Db::getInstance()->executeS('SELECT business_date, COUNT(*) orders, SUM(pieces) pieces, SUM(IF(service<>"normal",1,0)) express_orders, ROUND(SUM(total_tax_incl),2) revenue, ROUND(AVG(TIMESTAMPDIFF(HOUR,collected_at,ready_at)),1) avg_turnaround_hrs, SUM(IF(ready_at>promised_at,1,0)) late FROM `'._DB_PREFIX_.'pulse_laundry_order` WHERE type="guest" AND status<>"cancelled" AND business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY business_date ORDER BY business_date');
    }
    public static function linenLoss($from, $to)
    {
        return Db::getInstance()->executeS('SELECT t.name, SUM(IF(m.type="discard",m.qty,0)) discarded, SUM(IF(m.type="purchase",m.qty,0)) purchased, ROUND(SUM(IF(m.type="discard",m.qty,0))*t.unit_cost,2) loss_value FROM `'._DB_PREFIX_.'pulse_linen_movement` m INNER JOIN `'._DB_PREFIX_.'pulse_linen_type` t ON t.id_pulse_linen_type=m.id_pulse_linen_type WHERE m.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY t.id_pulse_linen_type');
    }
    public static function claims($from, $to)
    {
        return Db::getInstance()->executeS('SELECT c.*, o.order_no, o.guest_name FROM `'._DB_PREFIX_.'pulse_laundry_claim` c INNER JOIN `'._DB_PREFIX_.'pulse_laundry_order` o ON o.id_pulse_laundry_order=c.id_pulse_laundry_order WHERE c.date_add BETWEEN "'.pSQL($from).' 00:00:00" AND "'.pSQL($to).' 23:59:59" ORDER BY c.date_add DESC');
    }
}
