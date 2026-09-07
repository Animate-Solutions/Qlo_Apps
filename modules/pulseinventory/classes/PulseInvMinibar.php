<?php
/** Minibar: par list per room type, consumption posted to the guest folio (MINI) and deducted from the MINIBAR store; refills from central; guest amenities par & consumption per occupied room. */
class PulseInvMinibar
{
    public static function parList($idProduct = null) { return Db::getInstance()->executeS('SELECT m.*, i.name, i.sku, i.sale_price, i.avg_cost, i.unit FROM `'._DB_PREFIX_.'pulse_inv_minibar_item` m INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=m.id_pulse_inv_item WHERE i.active=1'.($idProduct ? ' AND (m.id_product IS NULL OR m.id_product='.(int) $idProduct.')' : '').' ORDER BY m.sort, i.name'); }
    public static function roomGuest($idRoom) { if (!class_exists('HotelBookingDetail')) { return null; } return Db::getInstance()->getRow('SELECT b.id id_htl_booking, b.id_customer, b.id_product, r.room_num, CONCAT(c.firstname," ",c.lastname) guest FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room WHERE b.id_room='.(int) $idRoom.' AND b.id_status=2 AND b.is_cancelled=0 AND b.is_refunded=0'); }
    /** Post consumption: $lines = [[id, qty]]. Charges the folio unless complimentary; always deducts stock. */
    public static function post($idRoom, array $lines, $complimentary = false, $emp = null, $bookingOverride = null)
    {
        $g = $bookingOverride ?: self::roomGuest($idRoom); $store = PulseInvService::storeId('MINIBAR'); $total = 0; $out = array(); $desc = array();
        foreach ($lines as $l) { $q = (float) $l['qty']; if ($q <= 0) { continue; } $i = PulseInvService::item($l['id']); $price = (float) $i['sale_price']; PulseInvService::move($i['id_pulse_inv_item'], $store, 'minibar', -$q, null, array('reference' => 'Rm '.($g ? $g['room_num'] : $idRoom), 'reason' => $complimentary ? 'complimentary' : 'guest consumption', 'id_room' => (int) $idRoom, 'department' => 'rooms', 'force' => 1, 'emp' => $emp)); $out[] = array('id' => (int) $i['id_pulse_inv_item'], 'name' => $i['name'], 'qty' => $q, 'price' => $price); $total += $q * $price; $desc[] = ($q * 1).'x '.$i['name']; }
        if (!$out) { throw new PrestaShopException('Nothing to post'); }
        $line = null;
        if (!$complimentary && $g && class_exists('PulseFolio') && ($f = PulseFolio::openForBooking($g['id_htl_booking']))) { $cc = PulseChargeCode::byCode('MINI'); $taxPct = $cc ? (float) $cc['tax_rate'] : 0; $line = $f->post('MINI', 'Minibar: '.implode(', ', $desc), 1, round($total / (1 + $taxPct / 100), 2), $taxPct, false, null, 'minibar', 'Rm '.$g['room_num']); }
        Db::getInstance()->insert('pulse_inv_minibar_post', array('id_room' => (int) $idRoom, 'id_htl_booking' => $g ? (int) $g['id_htl_booking'] : null, 'lines' => pSQL(json_encode($out), true), 'total' => round($total, 2), 'folio_line' => $line ? (int) $line : null, 'complimentary' => (int) $complimentary, 'id_employee' => $emp !== null ? (int) $emp : PulseInvService::emp(), 'business_date' => PulseInvService::bd(), 'date_add' => date('Y-m-d H:i:s')));
        PulseCoreService::event('actionPulseMinibarPost', array('id_room' => $idRoom, 'total' => $total, 'charged' => (bool) $line));
        return array('total' => round($total, 2), 'charged' => (bool) $line, 'lines' => $out);
    }
    /** Refill the minibar store from central for the day (based on today's consumption). */
    public static function refillSuggestion() { return Db::getInstance()->executeS('SELECT i.id_pulse_inv_item, i.name, i.unit, COALESCE(s.qty,0) minibar_qty, COALESCE(sm.qty,0) main_qty, COALESCE((SELECT SUM(-m.qty) FROM `'._DB_PREFIX_.'pulse_inv_movement` m WHERE m.id_pulse_inv_item=i.id_pulse_inv_item AND m.type="minibar" AND m.business_date>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)),0) used_7d, mb.par*(SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_status=1) par_total FROM `'._DB_PREFIX_.'pulse_inv_minibar_item` mb INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=mb.id_pulse_inv_item LEFT JOIN `'._DB_PREFIX_.'pulse_inv_stock` s ON s.id_pulse_inv_item=i.id_pulse_inv_item AND s.id_pulse_inv_store='.(int) PulseInvService::storeId('MINIBAR').' LEFT JOIN `'._DB_PREFIX_.'pulse_inv_stock` sm ON sm.id_pulse_inv_item=i.id_pulse_inv_item AND sm.id_pulse_inv_store='.(int) PulseInvService::storeId('MAIN').' ORDER BY i.name'); }
    public static function consumptionReport($from, $to) { return Db::getInstance()->executeS('SELECT i.name, SUM(-m.qty) qty, ROUND(SUM(-m.qty*i.avg_cost),2) cost, ROUND(SUM(-m.qty*COALESCE(i.sale_price,0)),2) revenue, SUM(IF(m.reason="complimentary",-m.qty,0)) comp_qty FROM `'._DB_PREFIX_.'pulse_inv_movement` m INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=m.id_pulse_inv_item WHERE m.type="minibar" AND m.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY i.id_pulse_inv_item ORDER BY revenue DESC'); }
    /* ---------- guest amenities ---------- */
    public static function amenityList() { return Db::getInstance()->executeS('SELECT a.*, i.name, i.unit, i.avg_cost, COALESCE(s.qty,0) hk_qty FROM `'._DB_PREFIX_.'pulse_inv_amenity` a INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=a.id_pulse_inv_item LEFT JOIN `'._DB_PREFIX_.'pulse_inv_stock` s ON s.id_pulse_inv_item=i.id_pulse_inv_item AND s.id_pulse_inv_store='.(int) PulseInvService::storeId('HK').' ORDER BY i.name'); }
    /** Standard daily consumption = par × occupied rooms (daily) + par × arrivals (arrival). Posted by night audit as 'amenity' from the HK store. */
    public static function postDailyAmenities($bd)
    {
        if (!class_exists('HotelBookingDetail') || Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_inv_movement` WHERE type="amenity" AND reference="AMEN '.pSQL($bd).'"')) { return 0; }
        $occ = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_status=2 AND is_cancelled=0'); $arr = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE date_from="'.pSQL($bd).'" AND id_status>=2 AND is_cancelled=0');
        $store = PulseInvService::storeId('HK'); $n = 0;
        foreach (self::amenityList() as $a) { $q = $a['frequency'] === 'daily' ? $a['par_per_room'] * $occ : ($a['frequency'] === 'arrival' ? $a['par_per_room'] * $arr : 0); if ($q <= 0) { continue; } try { PulseInvService::move($a['id_pulse_inv_item'], $store, 'amenity', -$q, null, array('reference' => 'AMEN '.$bd, 'reason' => 'standard consumption '.$occ.' occ / '.$arr.' arr', 'department' => 'housekeeping', 'force' => 1, 'business_date' => $bd)); $n++; } catch (Exception $e) {} }
        return $n;
    }
    public static function costPerOccupiedRoom($from, $to)
    {
        $occ = !Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_night_audit"') ? 0 : (int) Db::getInstance()->getValue('SELECT COALESCE(SUM(rooms_occupied),0) FROM `'._DB_PREFIX_.'pulse_night_audit` WHERE status="closed" AND business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"');
        $rows = Db::getInstance()->executeS('SELECT c.group_name, ROUND(SUM(-m.value),2) cost FROM `'._DB_PREFIX_.'pulse_inv_movement` m INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=m.id_pulse_inv_item INNER JOIN `'._DB_PREFIX_.'pulse_inv_category` c ON c.id_pulse_inv_category=i.id_pulse_inv_category WHERE m.type IN ("consume","amenity","minibar","waste","sale") AND m.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY c.group_name ORDER BY cost DESC');
        foreach ($rows as &$r) { $r['per_occupied_room'] = $occ ? round($r['cost'] / $occ, 2) : null; } return array('occupied_room_nights' => $occ, 'rows' => $rows);
    }
}
