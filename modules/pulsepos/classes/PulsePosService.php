<?php
/** Check lifecycle: open, add/void lines, modifiers, send to kitchen, discounts, transfers, split/merge, print, settle, reopen. */
class PulsePosService
{
    public static function bd() { return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    public static function nextNo($p) { $n = (int) PulseCoreService::setting('pulsepos', 'seq_'.$p) + 1; PulseCoreService::setting('pulsepos', 'seq_'.$p, $n); return $p.date('ymd').str_pad($n % 10000, 4, '0', STR_PAD_LEFT); }
    public static function audit($idCheck, $event, $detail = '', $amount = null, $emp = 0, $auth = null) { Db::getInstance()->insert('pulse_pos_audit', array('id_pulse_pos_check' => $idCheck ? (int) $idCheck : null, 'event' => pSQL($event), 'detail' => pSQL($detail), 'amount' => $amount === null ? null : (float) $amount, 'id_employee' => (int) $emp, 'authorised_by' => $auth ? (int) $auth : null, 'date_add' => date('Y-m-d H:i:s'))); }
    public static function fdOn() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFolio'); }

    /* ---------- staff auth ---------- */
    public static function login($pin, $idOutlet)
    {
        foreach (Db::getInstance()->executeS('SELECT s.*, e.firstname, e.lastname FROM `'._DB_PREFIX_.'pulse_pos_staff` s INNER JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=s.id_employee WHERE s.active=1 AND e.active=1') as $s) {
            if (hash('sha256', $pin.'|'._COOKIE_KEY_) === $s['pin_hash'] && (!$s['outlets'] || in_array((int) $idOutlet, array_map('intval', explode(',', $s['outlets']))))) { unset($s['pin_hash']); return $s; }
        }
        return null;
    }
    public static function setPin($idEmployee, $pin) { return hash('sha256', $pin.'|'._COOKIE_KEY_); }
    public static function staff($idEmployee) { return Db::getInstance()->getRow('SELECT s.*, e.firstname, e.lastname FROM `'._DB_PREFIX_.'pulse_pos_staff` s INNER JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=s.id_employee WHERE s.id_employee='.(int) $idEmployee); }

    /* ---------- menu ---------- */
    public static function priceLevel($idOutlet)
    {
        $t = date('H:i:s'); $d = date('N');
        $sp = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_service_period` WHERE active=1 AND (id_pulse_pos_outlet IS NULL OR id_pulse_pos_outlet='.(int) $idOutlet.') AND start_time<="'.$t.'" AND end_time>="'.$t.'" AND days LIKE "%'.$d.'%" ORDER BY id_pulse_pos_outlet DESC LIMIT 1');
        $o = Db::getInstance()->getRow('SELECT default_price_level FROM `'._DB_PREFIX_.'pulse_pos_outlet` WHERE id_pulse_pos_outlet='.(int) $idOutlet);
        return array('level' => $sp ? (int) $sp['price_level'] : (int) $o['default_price_level'], 'period' => $sp ? $sp['name'] : null, 'categories' => $sp && $sp['categories'] ? array_map('intval', explode(',', $sp['categories'])) : null);
    }
    public static function menu($idOutlet)
    {
        $pl = self::priceLevel($idOutlet);
        $cats = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_category` WHERE active=1 ORDER BY sort, name');
        if ($pl['categories']) { $cats = array_values(array_filter($cats, function ($c) use ($pl) { return in_array((int) $c['id_pulse_pos_category'], $pl['categories']); })); }
        $items = Db::getInstance()->executeS('SELECT i.*, t.rate_pct tax_rate, t.inclusive FROM `'._DB_PREFIX_.'pulse_pos_item` i LEFT JOIN `'._DB_PREFIX_.'pulse_pos_tax_group` t ON t.id_pulse_pos_tax_group=i.id_pulse_pos_tax_group WHERE i.active=1 ORDER BY i.sort, i.name');
        $out = array();
        foreach ($items as $i) {
            if ($i['outlets'] && !in_array((int) $idOutlet, array_map('intval', explode(',', $i['outlets'])))) { continue; }
            $p = $i['price'.$pl['level']] !== null ? $i['price'.$pl['level']] : $i['price1'];
            $out[] = array('id' => (int) $i['id_pulse_pos_item'], 'plu' => $i['plu'], 'name' => $i['name'], 'short' => $i['short_name'] ?: $i['name'], 'cat' => (int) $i['id_pulse_pos_category'], 'price' => (float) $p, 'open' => (int) $i['open_price'], 'tax' => (float) $i['tax_rate'], 'avail' => (int) $i['available'] && ($i['count_down'] === null || (int) $i['count_down'] > 0), 'left' => $i['count_down'], 'course' => (int) $i['course'], 'combo' => (int) $i['is_combo'], 'station' => (int) $i['id_pulse_pos_station'],
                'mods' => Db::getInstance()->executeS('SELECT g.id_pulse_pos_modifier_group id, g.name, g.min_select min, g.max_select max, img.required FROM `'._DB_PREFIX_.'pulse_pos_item_modifier_group` img INNER JOIN `'._DB_PREFIX_.'pulse_pos_modifier_group` g ON g.id_pulse_pos_modifier_group=img.id_pulse_pos_modifier_group WHERE img.id_pulse_pos_item='.(int) $i['id_pulse_pos_item'].' ORDER BY g.sort'));
        }
        $mods = array(); foreach (Db::getInstance()->executeS('SELECT id_pulse_pos_modifier id, id_pulse_pos_modifier_group g, name, price FROM `'._DB_PREFIX_.'pulse_pos_modifier` WHERE active=1 ORDER BY sort, name') as $m) { $mods[$m['g']][] = $m; }
$top = Db::getInstance()->executeS('SELECT l.id_pulse_pos_item id, SUM(l.qty) n FROM `'._DB_PREFIX_.'pulse_pos_check_line` l INNER JOIN `'._DB_PREFIX_.'pulse_pos_check` c ON c.id_pulse_pos_check=l.id_pulse_pos_check WHERE c.id_pulse_pos_outlet='.(int) $idOutlet.' AND c.business_date>=DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND l.voided=0 AND l.id_pulse_pos_item>0 GROUP BY l.id_pulse_pos_item ORDER BY n DESC LIMIT 24');
        return array('price_level' => $pl, 'top' => array_map(function ($t) { return (int) $t['id']; }, $top ?: array()), 'speed' => (int) Db::getInstance()->getValue('SELECT speed_screen FROM `'._DB_PREFIX_.'pulse_pos_outlet` WHERE id_pulse_pos_outlet='.(int) $idOutlet), 'categories' => $cats, 'items' => $out, 'modifiers' => $mods, 'discounts' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_discount` WHERE active=1'), 'reasons' => Db::getInstance()->executeS('SELECT type, name FROM `'._DB_PREFIX_.'pulse_pos_reason` WHERE active=1 ORDER BY type, name'));
    }

    /* ---------- checks ---------- */
    public static function open(array $d)
    {
        $o = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_outlet` WHERE id_pulse_pos_outlet='.(int) $d['id_outlet']);
        if (!$o) { throw new PrestaShopException('Outlet not found'); }
        $type = isset($d['order_type']) ? $d['order_type'] : 'dine_in'; $tbl = null;
        if (!empty($d['id_table'])) { $tbl = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_table` WHERE id_pulse_pos_table='.(int) $d['id_table']); if ($tbl && $tbl['id_pulse_pos_check'] && empty($d['join'])) { return (int) $tbl['id_pulse_pos_check']; } }
        $room = null;
        if ($type === 'room_service' || !empty($d['id_room'])) { $room = self::roomGuest((int) $d['id_room']); if (!$room) { throw new PrestaShopException('No in-house guest in that room'); } }
        $pl = self::priceLevel($o['id_pulse_pos_outlet']);
        Db::getInstance()->insert('pulse_pos_check', array('check_no' => self::nextNo($o['code'].'-'), 'id_pulse_pos_outlet' => (int) $o['id_pulse_pos_outlet'], 'id_pulse_pos_table' => $tbl ? (int) $tbl['id_pulse_pos_table'] : null, 'table_code' => $tbl ? pSQL($tbl['code']) : null, 'order_type' => pSQL($type), 'covers' => max(1, (int) (isset($d['covers']) ? $d['covers'] : 1)), 'guest_name' => pSQL(isset($d['guest_name']) ? $d['guest_name'] : ($room ? $room['guest'] : '')), 'id_room' => $room ? (int) $room['id_room'] : null, 'id_htl_booking' => $room ? (int) $room['id_htl_booking'] : null, 'id_customer' => $room ? (int) $room['id_customer'] : null, 'phone' => pSQL(isset($d['phone']) ? $d['phone'] : ''), 'address' => pSQL(isset($d['address']) ? $d['address'] : ''), 'id_server' => (int) $d['id_employee'], 'id_pulse_pos_session' => (int) self::currentSession($o['id_pulse_pos_outlet'], $d['id_employee']), 'price_level' => $pl['level'], 'business_date' => self::bd(), 'id_device' => (int) (isset($d['id_device']) ? $d['id_device'] : 0), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID();
        if ($tbl) { Db::getInstance()->update('pulse_pos_table', array('status' => 'seated', 'id_pulse_pos_check' => $id), 'id_pulse_pos_table='.(int) $tbl['id_pulse_pos_table']); }
        if ($type === 'room_service' && (float) $o['tray_charge'] > 0) { Db::getInstance()->insert('pulse_pos_check_line', array('id_pulse_pos_check' => $id, 'id_pulse_pos_item' => 0, 'name' => 'Tray / delivery charge', 'qty' => 1, 'unit_price' => (float) $o['tray_charge'], 'line_total' => (float) $o['tray_charge'], 'tax_rate' => (float) Db::getInstance()->getValue('SELECT rate_pct FROM `'._DB_PREFIX_.'pulse_pos_tax_group` WHERE id_pulse_pos_tax_group='.(int) $o['id_tax_group']), 'kot_status' => 'served', 'id_employee' => (int) $d['id_employee'], 'date_add' => date('Y-m-d H:i:s'))); self::recalc($id); }
        self::audit($id, 'open', $type.($tbl ? ' '.$tbl['code'] : ''), null, $d['id_employee']);
        return $id;
    }
    public static function roomGuest($idRoom)
    {
        if (!class_exists('HotelBookingDetail')) { return null; }
        return Db::getInstance()->getRow('SELECT b.id id_htl_booking, b.id_customer, b.id_room, r.room_num, CONCAT(c.firstname," ",c.lastname) guest, p.vip_level, p.id_pulse_company FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` p ON p.id_customer=b.id_customer WHERE b.id_room='.(int) $idRoom.' AND b.id_status=2 AND b.is_cancelled=0 AND b.is_refunded=0');
    }
    /** Guest lookup by name across in-house guests (MICROS-style). */
    public static function findGuests($name) { if (!class_exists('HotelBookingDetail')) { return array(); } return Db::getInstance()->executeS('SELECT b.id id_htl_booking, b.id_customer, b.id_room, r.room_num, CONCAT(c.firstname," ",c.lastname) guest, p.vip_level FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` p ON p.id_customer=b.id_customer WHERE b.id_status=2 AND b.is_cancelled=0 AND (c.lastname LIKE "%'.pSQL($name).'%" OR c.firstname LIKE "%'.pSQL($name).'%") ORDER BY c.lastname LIMIT 10'); }

    public static function findRoom($roomNum) { $id = (int) Db::getInstance()->getValue('SELECT id FROM `'._DB_PREFIX_.'htl_room_information` WHERE room_num="'.pSQL($roomNum).'"'); return $id ? self::roomGuest($id) : null; }

    public static function get($id, $withLines = true)
    {
        $c = Db::getInstance()->getRow('SELECT c.*, o.name outlet_name, o.code outlet_code, o.service_charge_pct, o.service_charge_taxable, o.receipt_header, o.receipt_footer, o.allow_room_charge, CONCAT(e.firstname," ",e.lastname) server, r.room_num FROM `'._DB_PREFIX_.'pulse_pos_check` c INNER JOIN `'._DB_PREFIX_.'pulse_pos_outlet` o ON o.id_pulse_pos_outlet=c.id_pulse_pos_outlet LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=c.id_server LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=c.id_room WHERE c.id_pulse_pos_check='.(int) $id);
        if ($c && $withLines) { $c['lines'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_check_line` WHERE id_pulse_pos_check='.(int) $id.' ORDER BY seat, course, id_pulse_pos_check_line'); foreach ($c['lines'] as &$l) { $l['modifiers'] = $l['modifiers'] ? json_decode($l['modifiers'], true) : array(); } $c['payments'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_payment` WHERE voided=0 AND id_pulse_pos_check='.(int) $id); }
        return $c;
    }
    public static function openChecks($idOutlet, $idServer = null) { return Db::getInstance()->executeS('SELECT c.id_pulse_pos_check id, c.check_no, c.table_code, c.order_type, c.covers, c.guest_name, c.total, c.status, c.date_add, r.room_num, CONCAT(e.firstname," ",LEFT(e.lastname,1)) server, (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pos_check_line` l WHERE l.id_pulse_pos_check=c.id_pulse_pos_check AND l.voided=0 AND l.kot_status="pending") unsent FROM `'._DB_PREFIX_.'pulse_pos_check` c LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=c.id_server LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=c.id_room WHERE c.id_pulse_pos_outlet='.(int) $idOutlet.' AND c.status IN ("open","printed","reopened")'.($idServer ? ' AND c.id_server='.(int) $idServer : '').' ORDER BY c.date_add'); }
    public static function tables($idOutlet) { return Db::getInstance()->executeS('SELECT t.*, s.name section, c.total, c.covers, c.check_no, c.status check_status, TIMESTAMPDIFF(MINUTE,c.date_add,NOW()) minutes, CONCAT(e.firstname," ",LEFT(e.lastname,1)) server FROM `'._DB_PREFIX_.'pulse_pos_table` t LEFT JOIN `'._DB_PREFIX_.'pulse_pos_section` s ON s.id_pulse_pos_section=t.id_pulse_pos_section LEFT JOIN `'._DB_PREFIX_.'pulse_pos_check` c ON c.id_pulse_pos_check=t.id_pulse_pos_check LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=c.id_server WHERE t.active=1 AND t.id_pulse_pos_outlet='.(int) $idOutlet.' ORDER BY s.sort, t.code'); }

    /** Add an item. $mods = [[id, qty], ...]; combo choices resolved client-side as modifiers with price 0. */
    public static function addLine($idCheck, $idItem, $qty, array $mods, $seat, $note, $emp, $openPrice = null)
    {
        $c = self::get($idCheck, false); if (!$c || in_array($c['status'], array('settled', 'void'))) { throw new PrestaShopException('Check is closed'); }
        $i = Db::getInstance()->getRow('SELECT i.*, t.rate_pct tax_rate FROM `'._DB_PREFIX_.'pulse_pos_item` i LEFT JOIN `'._DB_PREFIX_.'pulse_pos_tax_group` t ON t.id_pulse_pos_tax_group=i.id_pulse_pos_tax_group WHERE i.id_pulse_pos_item='.(int) $idItem.' AND i.active=1');
        if (!$i) { throw new PrestaShopException('Item not found'); }
        if (!$i['available'] || ($i['count_down'] !== null && (int) $i['count_down'] < $qty)) { throw new PrestaShopException($i['name'].' is not available (86)'); }
        $price = $i['open_price'] && $openPrice !== null ? (float) $openPrice : (float) ($i['price'.$c['price_level']] !== null ? $i['price'.$c['price_level']] : $i['price1']);
        $modList = array(); $modTotal = 0;
        foreach ($mods as $m) { $mm = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_modifier` WHERE id_pulse_pos_modifier='.(int) $m['id']); if ($mm) { $mq = isset($m['qty']) ? (float) $m['qty'] : 1; $modList[] = array('id' => (int) $mm['id_pulse_pos_modifier'], 'name' => $mm['name'], 'price' => (float) $mm['price'], 'qty' => $mq); $modTotal += $mm['price'] * $mq; } }
        foreach (Db::getInstance()->executeS('SELECT g.* FROM `'._DB_PREFIX_.'pulse_pos_item_modifier_group` img INNER JOIN `'._DB_PREFIX_.'pulse_pos_modifier_group` g ON g.id_pulse_pos_modifier_group=img.id_pulse_pos_modifier_group WHERE img.id_pulse_pos_item='.(int) $idItem.' AND (img.required=1 OR g.min_select>0)') as $g) { $n = 0; foreach ($modList as $ml) { if ((int) Db::getInstance()->getValue('SELECT id_pulse_pos_modifier_group FROM `'._DB_PREFIX_.'pulse_pos_modifier` WHERE id_pulse_pos_modifier='.(int) $ml['id']) === (int) $g['id_pulse_pos_modifier_group']) { $n++; } } if ($n < max(1, (int) $g['min_select'])) { throw new PrestaShopException('Choose '.$g['name'].' for '.$i['name']); } }
        $lt = round(($price + $modTotal) * $qty, 2);
        Db::getInstance()->insert('pulse_pos_check_line', array('id_pulse_pos_check' => (int) $idCheck, 'id_pulse_pos_item' => (int) $idItem, 'name' => pSQL($i['name']), 'seat' => max(1, (int) $seat), 'course' => (int) $i['course'], 'qty' => (float) $qty, 'unit_price' => $price, 'modifiers' => pSQL(json_encode($modList), true), 'modifier_total' => $modTotal, 'tax_rate' => (float) $i['tax_rate'], 'line_total' => $lt, 'note' => pSQL($note), 'id_pulse_pos_station' => (int) $i['id_pulse_pos_station'], 'kot_status' => 'pending', 'id_employee' => (int) $emp, 'date_add' => date('Y-m-d H:i:s')));
        $idLine = (int) Db::getInstance()->Insert_ID();
        if ($i['count_down'] !== null) { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pos_item` SET count_down=GREATEST(0,count_down-'.(float) $qty.') WHERE id_pulse_pos_item='.(int) $idItem); }
        if ($c['id_pulse_pos_table']) { Db::getInstance()->update('pulse_pos_table', array('status' => 'ordered'), 'id_pulse_pos_table='.(int) $c['id_pulse_pos_table'].' AND status="seated"'); }
        self::recalc($idCheck);
        return $idLine;
    }

    public static function voidLine($idLine, $reason, $emp, $auth = null)
    {
        $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_check_line` WHERE id_pulse_pos_check_line='.(int) $idLine); if (!$l || $l['voided']) { return false; }
        $sent = $l['kot_status'] !== 'pending';
        if ($sent) { $s = self::staff($emp); if (!$s || !$s['can_void_sent']) { if (!$auth) { throw new PrestaShopException('Manager authorisation required to void a sent item'); } $a = self::staff($auth); if (!$a || !$a['can_void_sent']) { throw new PrestaShopException('Authorising user cannot void sent items'); } } }
        Db::getInstance()->update('pulse_pos_check_line', array('voided' => 1, 'void_reason' => pSQL($reason), 'void_by' => (int) ($auth ?: $emp), 'void_after_send' => (int) $sent, 'kot_status' => 'void'), 'id_pulse_pos_check_line='.(int) $idLine);
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pos_item` SET count_down=count_down+'.(float) $l['qty'].' WHERE id_pulse_pos_item='.(int) $l['id_pulse_pos_item'].' AND count_down IS NOT NULL');
        if ($sent) { PulsePosInventory::reverseLine($l); PulsePosKitchen::notify($l['id_pulse_pos_check'], 'void', $l); }
        self::audit($l['id_pulse_pos_check'], $sent ? 'void_after_send' : 'void', $l['name'].' x'.$l['qty'].' — '.$reason, $l['line_total'], $emp, $auth);
        self::recalc($l['id_pulse_pos_check']);
        return true;
    }

    /** Send pending lines to kitchen/bar. Course firing: pass $course to fire only that course; others go to 'held'. */
    public static function send($idCheck, $emp, $course = null)
    {
        $c = self::get($idCheck); if (!$c) { return false; }
        $kot = self::nextNo('K'); $fired = array();
        foreach ($c['lines'] as $l) {
            if ($l['voided'] || !in_array($l['kot_status'], array('pending', 'held'))) { continue; }
            $st = Db::getInstance()->getRow('SELECT kds FROM `'._DB_PREFIX_.'pulse_pos_station` WHERE id_pulse_pos_station='.(int) $l['id_pulse_pos_station']);
            if ($st && !$st['kds'] && !Db::getInstance()->getValue('SELECT printer_host FROM `'._DB_PREFIX_.'pulse_pos_station` WHERE id_pulse_pos_station='.(int) $l['id_pulse_pos_station'])) { Db::getInstance()->update('pulse_pos_check_line', array('kot_status' => 'served', 'kot_no' => pSQL($kot), 'fired_at' => date('Y-m-d H:i:s'), 'served_at' => date('Y-m-d H:i:s')), 'id_pulse_pos_check_line='.(int) $l['id_pulse_pos_check_line']); PulsePosInventory::consumeLine($l); continue; }
            if ($course !== null && (int) $l['course'] !== (int) $course && (int) $l['course'] > (int) $course) { Db::getInstance()->update('pulse_pos_check_line', array('kot_status' => 'held', 'kot_no' => pSQL($kot)), 'id_pulse_pos_check_line='.(int) $l['id_pulse_pos_check_line']); continue; }
            Db::getInstance()->update('pulse_pos_check_line', array('kot_status' => 'fired', 'kot_no' => pSQL($kot), 'fired_at' => date('Y-m-d H:i:s')), 'id_pulse_pos_check_line='.(int) $l['id_pulse_pos_check_line']);
            PulsePosInventory::consumeLine($l); $fired[] = $l;
        }
        if ($fired) { PulsePosKitchen::ticket($idCheck, $kot, $fired); }
        self::audit($idCheck, 'send', $kot.' '.count($fired).' item(s)'.($course !== null ? ' course '.$course : ''), null, $emp);
        PulseCoreService::event('actionPulsePosKotFired', array('id_check' => $idCheck, 'kot' => $kot, 'lines' => $fired));
        return $kot;
    }
    public static function fireCourse($idCheck, $course, $emp) { foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_check_line` WHERE id_pulse_pos_check='.(int) $idCheck.' AND voided=0 AND kot_status="held" AND course<='.(int) $course) as $l) { Db::getInstance()->update('pulse_pos_check_line', array('kot_status' => 'fired', 'fired_at' => date('Y-m-d H:i:s')), 'id_pulse_pos_check_line='.(int) $l['id_pulse_pos_check_line']); $fired[] = $l; } if (!empty($fired)) { PulsePosKitchen::ticket($idCheck, self::nextNo('K'), $fired); } self::audit($idCheck, 'fire_course', 'course '.$course, null, $emp); return true; }

    public static function discount($idCheck, $idDiscount, $reason, $emp, $auth = null, $customValue = null, $idLine = null)
    {
        $d = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_discount` WHERE id_pulse_pos_discount='.(int) $idDiscount); if (!$d) { throw new PrestaShopException('Discount not found'); }
        $s = self::staff($emp); $by = $emp;
        if ($d['requires_manager'] || !$s['can_discount'] || ($d['type'] === 'pct' && $d['value'] > $s['max_discount_pct'])) { if (!$auth) { throw new PrestaShopException('Manager authorisation required for this discount'); } $a = self::staff($auth); if (!$a || $a['role'] !== 'manager' && $a['role'] !== 'supervisor') { throw new PrestaShopException('Not authorised'); } $by = $auth; }
        $val = $customValue !== null && $d['value'] == 0 ? (float) $customValue : (float) $d['value'];
        if ($idLine) { $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_check_line` WHERE id_pulse_pos_check_line='.(int) $idLine); $amt = $d['type'] === 'pct' ? round($l['line_total'] * $val / 100, 2) : min($val, $l['line_total']); Db::getInstance()->update('pulse_pos_check_line', array('line_discount' => $amt, 'discount_reason' => pSQL($d['name'].': '.$reason)), 'id_pulse_pos_check_line='.(int) $idLine); }
        else { Db::getInstance()->update('pulse_pos_check', array('check_discount_pct' => $d['type'] === 'pct' ? $val : 0, 'check_discount_amount' => $d['type'] === 'amount' ? $val : 0, 'id_check_discount' => (int) $idDiscount, 'discount_reason' => pSQL($d['name'].': '.$reason), 'discount_by' => (int) $by), 'id_pulse_pos_check='.(int) $idCheck); }
        self::audit($idCheck, 'discount', $d['name'].' '.$val.($d['type'] === 'pct' ? '%' : '').' — '.$reason, $val, $emp, $auth);
        self::recalc($idCheck); return true;
    }
    public static function comp($idCheck, $reason, $emp, $auth = null, $idLine = null)
    {
        $s = self::staff($emp); if (!$s['can_comp']) { if (!$auth || !self::staff($auth)['can_comp']) { throw new PrestaShopException('Manager authorisation required to comp'); } }
        if ($idLine) { Db::getInstance()->update('pulse_pos_check_line', array('comp' => 1, 'discount_reason' => pSQL('COMP: '.$reason)), 'id_pulse_pos_check_line='.(int) $idLine); } else { Db::getInstance()->update('pulse_pos_check', array('comp_reason' => pSQL($reason)), 'id_pulse_pos_check='.(int) $idCheck); Db::getInstance()->update('pulse_pos_check_line', array('comp' => 1), 'id_pulse_pos_check='.(int) $idCheck.' AND voided=0'); }
        self::audit($idCheck, 'comp', $reason, null, $emp, $auth); self::recalc($idCheck); return true;
    }

    public static function recalc($idCheck)
    {
        $c = self::get($idCheck); $sub = 0; $disc = 0; $taxable = array();
        foreach ($c['lines'] as $l) { if ($l['voided']) { continue; } $lt = (float) $l['line_total']; $ld = $l['comp'] ? $lt : (float) $l['line_discount']; $sub += $lt; $disc += $ld; $net = $lt - $ld; $taxable[(string) $l['tax_rate']] = (isset($taxable[(string) $l['tax_rate']]) ? $taxable[(string) $l['tax_rate']] : 0) + $net; }
        if ($c['check_discount_pct'] > 0) { $cd = round(($sub - $disc) * $c['check_discount_pct'] / 100, 2); $f = ($sub - $disc) > 0 ? 1 - $cd / ($sub - $disc) : 0; foreach ($taxable as $k => $v) { $taxable[$k] = $v * $f; } $disc += $cd; }
        elseif ($c['check_discount_amount'] > 0) { $cd = min($c['check_discount_amount'], $sub - $disc); $f = ($sub - $disc) > 0 ? 1 - $cd / ($sub - $disc) : 0; foreach ($taxable as $k => $v) { $taxable[$k] = $v * $f; } $disc += $cd; }
        $net = $sub - $disc; $sc = round($net * $c['service_charge_pct'] / 100, 2);
        // tax-inclusive pricing: back out tax from net; service charge taxable per outlet setting
        $tax = 0; foreach ($taxable as $rate => $amt) { $tax += $amt - $amt / (1 + $rate / 100); }
        if ($c['tax_exempt']) { $net -= $tax; $tax = 0; }
        if ($c['service_charge_taxable'] && $sc > 0) { $rate = (float) Db::getInstance()->getValue('SELECT rate_pct FROM `'._DB_PREFIX_.'pulse_pos_tax_group` t INNER JOIN `'._DB_PREFIX_.'pulse_pos_outlet` o ON o.id_tax_group=t.id_pulse_pos_tax_group WHERE o.id_pulse_pos_outlet='.(int) $c['id_pulse_pos_outlet']); $tax += $sc - $sc / (1 + $rate / 100); }
        $total = round($net + $sc, 2); $paid = 0; $tip = 0; foreach ($c['payments'] as $p) { $paid += (float) $p['amount']; $tip += (float) $p['tip']; }
        Db::getInstance()->update('pulse_pos_check', array('subtotal' => round($sub, 2), 'discount_total' => round($disc, 2), 'service_charge' => $sc, 'tax_total' => round($tax, 2), 'total' => $total, 'paid' => round($paid, 2), 'tip_total' => round($tip, 2), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pos_check='.(int) $idCheck);
        return $total;
    }

    public static function update($idCheck, array $d, $emp) { if (isset($d['note_line'])) { Db::getInstance()->update('pulse_pos_check_line', array('note' => pSQL(isset($d['note']) ? $d['note'] : '')), 'id_pulse_pos_check_line='.(int) $d['note_line'].' AND id_pulse_pos_check='.(int) $idCheck); unset($d['note']); } $u = array(); foreach (array('covers', 'guest_name', 'phone', 'address', 'note', 'order_type', 'allergy_note', 'tax_exempt_ref', 'preauth_ref', 'preauth_amount') as $k) { if (isset($d[$k])) { $u[$k] = pSQL($d[$k]); } }
        if (isset($d['tax_exempt'])) { $u['tax_exempt'] = (int) $d['tax_exempt']; self::audit($idCheck, 'tax_exempt', isset($d['tax_exempt_ref']) ? (string) $d['tax_exempt_ref'] : '', null, $emp); }
        if (!empty($d['delivered'])) { $u['delivered_at'] = date('Y-m-d H:i:s'); $u['delivered_by'] = (int) $emp; Db::getInstance()->update('pulse_pos_check_line', array('kot_status' => 'served', 'served_at' => date('Y-m-d H:i:s')), 'id_pulse_pos_check='.(int) $idCheck.' AND voided=0 AND kot_status IN ("fired","preparing","ready")'); self::audit($idCheck, 'delivered', '', null, $emp); } if (isset($d['seat_line']) && isset($d['seat'])) { Db::getInstance()->update('pulse_pos_check_line', array('seat' => (int) $d['seat']), 'id_pulse_pos_check_line='.(int) $d['seat_line']); } if (isset($d['room_num'])) { $r = self::findRoom($d['room_num']); if (!$r) { throw new PrestaShopException('No in-house guest in room '.$d['room_num']); } $u['id_room'] = (int) $r['id_room']; $u['id_htl_booking'] = (int) $r['id_htl_booking']; $u['id_customer'] = (int) $r['id_customer']; $u['guest_name'] = pSQL($r['guest']); } if ($u) { Db::getInstance()->update('pulse_pos_check', $u, 'id_pulse_pos_check='.(int) $idCheck); } if (isset($d['tax_exempt'])) { self::recalc($idCheck); } return true; }
    public static function transferTable($idCheck, $idTable, $emp) { $c = self::get($idCheck, false); $t = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_table` WHERE id_pulse_pos_table='.(int) $idTable); if ($t['id_pulse_pos_check']) { throw new PrestaShopException('Table occupied — use merge'); } if ($c['id_pulse_pos_table']) { Db::getInstance()->update('pulse_pos_table', array('status' => 'free', 'id_pulse_pos_check' => null), 'id_pulse_pos_table='.(int) $c['id_pulse_pos_table']); } Db::getInstance()->update('pulse_pos_table', array('status' => 'ordered', 'id_pulse_pos_check' => (int) $idCheck), 'id_pulse_pos_table='.(int) $idTable); Db::getInstance()->update('pulse_pos_check', array('id_pulse_pos_table' => (int) $idTable, 'table_code' => pSQL($t['code'])), 'id_pulse_pos_check='.(int) $idCheck); self::audit($idCheck, 'transfer_table', $t['code'], null, $emp); return true; }
    public static function transferServer($idCheck, $idServer, $emp) { Db::getInstance()->update('pulse_pos_check', array('id_server' => (int) $idServer), 'id_pulse_pos_check='.(int) $idCheck); self::audit($idCheck, 'transfer_server', (string) $idServer, null, $emp); return true; }
    public static function merge($idFrom, $idInto, $emp) { $f = self::get($idFrom, false); Db::getInstance()->update('pulse_pos_check_line', array('id_pulse_pos_check' => (int) $idInto), 'id_pulse_pos_check='.(int) $idFrom); Db::getInstance()->update('pulse_pos_check', array('status' => 'void', 'merged_into' => (int) $idInto, 'void_reason' => 'merged'), 'id_pulse_pos_check='.(int) $idFrom); if ($f['id_pulse_pos_table']) { Db::getInstance()->update('pulse_pos_table', array('status' => 'free', 'id_pulse_pos_check' => null), 'id_pulse_pos_table='.(int) $f['id_pulse_pos_table']); } self::audit($idInto, 'merge', 'from '.$f['check_no'], null, $emp); self::recalc($idInto); return true; }
    /** Split: $lineIds moved to a new check (by items) or by seat (pass seat) or equal N-way (creates virtual lines by proportion). */
    public static function split($idCheck, $emp, array $lineIds = array(), $seat = null, $ways = null, $amount = null)
    {
        $c = self::get($idCheck);
        if ($amount !== null && (float) $amount > 0 && (float) $amount < $c['total']) { $new = self::cloneHeader($c, $emp, 1); $a = round((float) $amount, 2); Db::getInstance()->insert('pulse_pos_check_line', array('id_pulse_pos_check' => $new, 'id_pulse_pos_item' => 0, 'name' => 'Part payment of '.$c['check_no'], 'qty' => 1, 'unit_price' => $a, 'line_total' => $a, 'tax_rate' => 0, 'kot_status' => 'served', 'id_employee' => (int) $emp, 'date_add' => date('Y-m-d H:i:s'))); Db::getInstance()->insert('pulse_pos_check_line', array('id_pulse_pos_check' => (int) $idCheck, 'id_pulse_pos_item' => 0, 'name' => 'Split '.$a.' to '.$c['check_no'].'/1', 'qty' => 1, 'unit_price' => -$a, 'line_total' => -$a, 'tax_rate' => 0, 'kot_status' => 'served', 'id_employee' => (int) $emp, 'date_add' => date('Y-m-d H:i:s'))); self::recalc($idCheck); self::recalc($new); self::audit($idCheck, 'split', 'amount '.$a, $a, $emp); return array($new); }

        if ($ways && $ways > 1) { $ids = array(); $share = round($c['total'] / $ways, 2); for ($i = 1; $i < $ways; $i++) { $ids[] = self::cloneHeader($c, $emp, $i); Db::getInstance()->insert('pulse_pos_check_line', array('id_pulse_pos_check' => end($ids), 'id_pulse_pos_item' => 0, 'name' => 'Share '.($i + 1).' of '.$ways.' — '.$c['check_no'], 'qty' => 1, 'unit_price' => $share, 'line_total' => $share, 'tax_rate' => 0, 'kot_status' => 'served', 'id_employee' => (int) $emp, 'date_add' => date('Y-m-d H:i:s'))); self::recalc(end($ids)); } Db::getInstance()->insert('pulse_pos_check_line', array('id_pulse_pos_check' => (int) $idCheck, 'id_pulse_pos_item' => 0, 'name' => 'Split '.($ways - 1).' share(s) to other checks', 'qty' => 1, 'unit_price' => -$share * ($ways - 1), 'line_total' => -$share * ($ways - 1), 'tax_rate' => 0, 'kot_status' => 'served', 'id_employee' => (int) $emp, 'date_add' => date('Y-m-d H:i:s'))); self::recalc($idCheck); self::audit($idCheck, 'split', $ways.' ways', null, $emp); return $ids; }
        $new = self::cloneHeader($c, $emp, 1);
        $where = $seat ? 'seat='.(int) $seat : 'id_pulse_pos_check_line IN ('.implode(',', array_map('intval', $lineIds) ?: array(0)).')';
        Db::getInstance()->update('pulse_pos_check_line', array('id_pulse_pos_check' => $new), 'id_pulse_pos_check='.(int) $idCheck.' AND voided=0 AND '.$where);
        self::recalc($idCheck); self::recalc($new); self::audit($idCheck, 'split', $seat ? 'seat '.$seat : count($lineIds).' items', null, $emp); return array($new);
    }
    protected static function cloneHeader($c, $emp, $n) { Db::getInstance()->insert('pulse_pos_check', array('check_no' => pSQL($c['check_no'].'/'.$n), 'id_pulse_pos_outlet' => (int) $c['id_pulse_pos_outlet'], 'id_pulse_pos_table' => $c['id_pulse_pos_table'] ? (int) $c['id_pulse_pos_table'] : null, 'table_code' => pSQL($c['table_code']), 'order_type' => pSQL($c['order_type']), 'covers' => 1, 'id_room' => $c['id_room'] ? (int) $c['id_room'] : null, 'id_htl_booking' => $c['id_htl_booking'] ? (int) $c['id_htl_booking'] : null, 'id_customer' => $c['id_customer'] ? (int) $c['id_customer'] : null, 'id_server' => (int) $c['id_server'], 'id_pulse_pos_session' => (int) $c['id_pulse_pos_session'], 'price_level' => (int) $c['price_level'], 'split_from' => (int) $c['id_pulse_pos_check'], 'business_date' => pSQL($c['business_date']), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'))); return (int) Db::getInstance()->Insert_ID(); }

    public static function printCheck($idCheck, $emp) { $c = self::get($idCheck, false); if ($c['status'] === 'open') { Db::getInstance()->update('pulse_pos_check', array('status' => 'printed', 'date_printed' => date('Y-m-d H:i:s')), 'id_pulse_pos_check='.(int) $idCheck); if ($c['id_pulse_pos_table']) { Db::getInstance()->update('pulse_pos_table', array('status' => 'billed'), 'id_pulse_pos_table='.(int) $c['id_pulse_pos_table']); } } self::audit($idCheck, 'print', '', $c['total'], $emp); return PulsePosPrinter::receipt(self::get($idCheck), false); }

    public static function voidCheck($idCheck, $reason, $emp, $auth = null) { $c = self::get($idCheck); if ($c['paid'] > 0) { throw new PrestaShopException('Check has payments — refund first'); } foreach ($c['lines'] as $l) { if (!$l['voided']) { self::voidLine($l['id_pulse_pos_check_line'], $reason, $emp, $auth); } } Db::getInstance()->update('pulse_pos_check', array('status' => 'void', 'void_reason' => pSQL($reason), 'void_by' => (int) ($auth ?: $emp)), 'id_pulse_pos_check='.(int) $idCheck); if ($c['id_pulse_pos_table']) { Db::getInstance()->update('pulse_pos_table', array('status' => 'free', 'id_pulse_pos_check' => null), 'id_pulse_pos_table='.(int) $c['id_pulse_pos_table']); } self::audit($idCheck, 'void_check', $reason, $c['total'], $emp, $auth); return true; }
    public static function reopen($idCheck, $emp, $auth = null) { $s = self::staff($auth ?: $emp); if (!$s || !$s['can_reopen']) { throw new PrestaShopException('Manager authorisation required to reopen'); } $c = self::get($idCheck); if ($c['status'] !== 'settled') { return false; } foreach ($c['payments'] as $p) { PulsePosPayment::reverse($p, $emp); } Db::getInstance()->update('pulse_pos_check', array('status' => 'reopened', 'reopen_by' => (int) ($auth ?: $emp), 'posted_line' => null, 'paid' => 0), 'id_pulse_pos_check='.(int) $idCheck); self::audit($idCheck, 'reopen', '', $c['total'], $emp, $auth); self::recalc($idCheck); return true; }

    /* ---------- cashier sessions ---------- */
    public static function currentSession($idOutlet, $emp = null) { return (int) Db::getInstance()->getValue('SELECT id_pulse_pos_session FROM `'._DB_PREFIX_.'pulse_pos_session` WHERE status="open" AND id_pulse_pos_outlet='.(int) $idOutlet.($emp ? ' AND id_employee='.(int) $emp : '').' ORDER BY id_pulse_pos_session DESC'); }
    public static function openSession($idOutlet, $emp, $float) { if (self::currentSession($idOutlet, $emp)) { throw new PrestaShopException('You already have an open session'); } Db::getInstance()->insert('pulse_pos_session', array('id_pulse_pos_outlet' => (int) $idOutlet, 'id_employee' => (int) $emp, 'opening_float' => (float) $float, 'business_date' => self::bd(), 'date_open' => date('Y-m-d H:i:s'))); self::audit(null, 'session_open', 'float '.$float, $float, $emp); return (int) Db::getInstance()->Insert_ID(); }
    public static function sessionMove($idSession, $type, $amount, $reason, $emp) { Db::getInstance()->insert('pulse_pos_session_movement', array('id_pulse_pos_session' => (int) $idSession, 'type' => pSQL($type), 'amount' => (float) $amount, 'reason' => pSQL($reason), 'id_employee' => (int) $emp, 'date_add' => date('Y-m-d H:i:s'))); if ($type === 'drop') { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pos_session` SET drops=drops+'.(float) $amount.' WHERE id_pulse_pos_session='.(int) $idSession); } if ($type === 'paid_out') { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pos_session` SET paid_outs=paid_outs+'.(float) $amount.' WHERE id_pulse_pos_session='.(int) $idSession); } if ($type === 'float_add') { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pos_session` SET opening_float=opening_float+'.(float) $amount.' WHERE id_pulse_pos_session='.(int) $idSession); } self::audit(null, 'session_'.$type, $reason, $amount, $emp); }
    public static function sessionReport($idSession)
    {
        $s = Db::getInstance()->getRow('SELECT s.*, CONCAT(e.firstname," ",e.lastname) cashier, o.name outlet FROM `'._DB_PREFIX_.'pulse_pos_session` s INNER JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=s.id_employee INNER JOIN `'._DB_PREFIX_.'pulse_pos_outlet` o ON o.id_pulse_pos_outlet=s.id_pulse_pos_outlet WHERE s.id_pulse_pos_session='.(int) $idSession);
        $s['payments'] = Db::getInstance()->executeS('SELECT method, COUNT(*) n, ROUND(SUM(amount),2) total, ROUND(SUM(tip),2) tips FROM `'._DB_PREFIX_.'pulse_pos_payment` WHERE voided=0 AND id_pulse_pos_session='.(int) $idSession.' GROUP BY method');
        $s['checks'] = Db::getInstance()->getRow('SELECT COUNT(*) n, COALESCE(SUM(covers),0) covers, ROUND(COALESCE(SUM(total),0),2) sales, ROUND(COALESCE(SUM(discount_total),0),2) discounts, ROUND(COALESCE(SUM(service_charge),0),2) service, ROUND(COALESCE(SUM(tax_total),0),2) tax FROM `'._DB_PREFIX_.'pulse_pos_check` WHERE status="settled" AND id_pulse_pos_session='.(int) $idSession);
        $s['voids'] = Db::getInstance()->getRow('SELECT COUNT(*) n, ROUND(COALESCE(SUM(line_total),0),2) total FROM `'._DB_PREFIX_.'pulse_pos_check_line` l INNER JOIN `'._DB_PREFIX_.'pulse_pos_check` c ON c.id_pulse_pos_check=l.id_pulse_pos_check WHERE l.voided=1 AND c.id_pulse_pos_session='.(int) $idSession);
        $s['movements'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_session_movement` WHERE id_pulse_pos_session='.(int) $idSession);
        $cash = 0; foreach ($s['payments'] as $p) { if ($p['method'] === 'cash') { $cash = (float) $p['total']; } }
        $s['expected_cash_calc'] = round((float) $s['opening_float'] + $cash - (float) $s['drops'] - (float) $s['paid_outs'], 2);
        return $s;
    }
    public static function closeSession($idSession, $counted, $note, $emp) { $r = self::sessionReport($idSession); if ($r['status'] !== 'open') { return false; } if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pos_check` WHERE id_pulse_pos_session='.(int) $idSession.' AND status IN ("open","printed","reopened")')) { throw new PrestaShopException('Open checks on this session — settle or transfer them first'); } $z = (int) PulseCoreService::setting('pulsepos', 'z_seq') + 1; PulseCoreService::setting('pulsepos', 'z_seq', $z); Db::getInstance()->update('pulse_pos_session', array('expected_cash' => $r['expected_cash_calc'], 'counted_cash' => (float) $counted, 'variance' => round($counted - $r['expected_cash_calc'], 2), 'z_number' => $z, 'status' => 'closed', 'note' => pSQL($note), 'date_close' => date('Y-m-d H:i:s')), 'id_pulse_pos_session='.(int) $idSession); self::audit(null, 'session_close', 'Z'.$z.' variance '.round($counted - $r['expected_cash_calc'], 2), $counted, $emp); return $z; }
}
