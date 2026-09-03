<?php
/**
 * Single aggregator every report and the dashboard read from. All figures for a business-date range.
 * Each block is guarded so the module works with any subset of Pulse installed.
 */
class PulseReportData
{
    protected static function has($table) { static $c = array(); if (!isset($c[$table])) { $c[$table] = (bool) Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.$table.'"'); } return $c[$table]; }
    protected static function v($sql) { return (float) Db::getInstance()->getValue($sql); }
    protected static function rows($sql) { return Db::getInstance()->executeS($sql) ?: array(); }
    protected static function range($from, $to) { return 'BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'; }

    /** Everything for one range. */
    public static function period($from, $to)
    {
        $d = array('from' => $from, 'to' => $to, 'days' => max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1));
        $d['revenue'] = self::revenue($from, $to);
        $d['occupancy'] = self::occupancy($from, $to);
        $d['payments'] = self::payments($from, $to);
        $d['ledgers'] = self::ledgers();
        $d['expenses'] = self::expenses($from, $to);
        $d['frontoffice'] = self::frontOffice($from, $to);
        $d['housekeeping'] = self::housekeeping($from, $to);
        $d['laundry'] = self::laundry($from, $to);
        $d['maintenance'] = self::maintenance($from, $to);
        $d['guests'] = self::guests($from, $to);
        $d['cash'] = self::cashiers($from, $to);
        $d['alerts'] = self::alerts();
        $d['pl'] = self::pl($d);
        return $d;
    }

    public static function revenue($from, $to)
    {
        if (!self::has('pulse_folio_line')) { return array('rooms' => 0, 'fnb' => 0, 'laundry' => 0, 'minibar' => 0, 'spa' => 0, 'telephone' => 0, 'other' => 0, 'net' => 0, 'tax' => 0, 'gross' => 0, 'by_department' => array(), 'adjustments' => 0, 'voids' => 0); }
        $rows = self::rows('SELECT department, ROUND(SUM(amount_tax_incl/(1+tax_rate/100)),2) net, ROUND(SUM(amount_tax_incl - amount_tax_incl/(1+tax_rate/100)),2) tax, ROUND(SUM(amount_tax_incl),2) gross, COUNT(*) n FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND is_payment=0 AND business_date '.self::range($from, $to).' GROUP BY department ORDER BY net DESC');
        $r = array('rooms' => 0, 'fnb' => 0, 'laundry' => 0, 'minibar' => 0, 'spa' => 0, 'telephone' => 0, 'other' => 0, 'net' => 0, 'tax' => 0, 'gross' => 0, 'by_department' => $rows);
        foreach ($rows as $x) { $k = in_array($x['department'], array('rooms', 'fnb', 'laundry', 'minibar', 'spa', 'telephone')) ? $x['department'] : 'other'; if ($x['department'] === 'adjustment') { $r['adjustments'] = (float) $x['net']; } $r[$k] += (float) $x['net']; $r['net'] += (float) $x['net']; $r['tax'] += (float) $x['tax']; $r['gross'] += (float) $x['gross']; }
        $r['voids'] = self::v('SELECT COALESCE(SUM(amount_tax_incl),0) FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=1 AND is_payment=0 AND transferred_to IS NULL AND business_date '.self::range($from, $to));
        $r['daily'] = self::rows('SELECT business_date d, ROUND(SUM(IF(department="rooms",amount_tax_incl/(1+tax_rate/100),0)),2) rooms, ROUND(SUM(IF(department="fnb",amount_tax_incl/(1+tax_rate/100),0)),2) fnb, ROUND(SUM(IF(department NOT IN ("rooms","fnb"),amount_tax_incl/(1+tax_rate/100),0)),2) other FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND is_payment=0 AND business_date '.self::range($from, $to).' GROUP BY business_date ORDER BY business_date');
        return $r;
    }

    public static function occupancy($from, $to)
    {
        $o = array('rooms_total' => (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_status IN (1,3)'), 'room_nights_sold' => 0, 'room_nights_available' => 0, 'occupancy_pct' => 0, 'adr' => 0, 'revpar' => 0, 'daily' => array(), 'by_type' => array());
        if (self::has('pulse_night_audit')) {
            $o['daily'] = self::rows('SELECT business_date d, rooms_total, rooms_ooo, rooms_occupied, arrivals, departures, no_shows, room_revenue, fnb_revenue, other_revenue, payments FROM `'._DB_PREFIX_.'pulse_night_audit` WHERE status="closed" AND business_date '.self::range($from, $to).' ORDER BY business_date');
            foreach ($o['daily'] as $x) { $o['room_nights_sold'] += (int) $x['rooms_occupied']; $o['room_nights_available'] += max(0, (int) $x['rooms_total'] - (int) $x['rooms_ooo']); }
        }
        if (!$o['room_nights_available']) { $o['room_nights_available'] = $o['rooms_total'] * max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1); $o['room_nights_sold'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` b JOIN (SELECT 1) x WHERE b.is_refunded=0 AND b.is_cancelled=0 AND b.id_status>=2 AND b.date_from<="'.pSQL($to).'" AND b.date_to>"'.pSQL($from).'"'); $o['estimated'] = true; }
        $roomRev = self::has('pulse_folio_line') ? self::v('SELECT COALESCE(SUM(amount_tax_incl/(1+tax_rate/100)),0) FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND is_payment=0 AND department="rooms" AND business_date '.self::range($from, $to)) : 0;
        $o['occupancy_pct'] = $o['room_nights_available'] ? round($o['room_nights_sold'] / $o['room_nights_available'] * 100, 1) : 0;
        $o['adr'] = $o['room_nights_sold'] ? round($roomRev / $o['room_nights_sold'], 2) : 0;
        $o['revpar'] = $o['room_nights_available'] ? round($roomRev / $o['room_nights_available'], 2) : 0;
        $o['by_type'] = self::rows('SELECT b.room_type_name type, COUNT(*) nights, ROUND(AVG(b.total_price_tax_incl/GREATEST(1,DATEDIFF(b.date_to,b.date_from))),2) avg_rate FROM `'._DB_PREFIX_.'htl_booking_detail` b WHERE b.is_refunded=0 AND b.is_cancelled=0 AND b.id_status>=2 AND b.date_from<="'.pSQL($to).'" AND b.date_to>"'.pSQL($from).'" GROUP BY b.room_type_name ORDER BY nights DESC');
        $o['ooo_now'] = self::has('pulse_room_status') ? (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_room_status` WHERE hk_status IN ("out_of_order","out_of_service")') : 0;
        $o['in_house_now'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_refunded=0 AND is_cancelled=0 AND id_status=2');
        $o['forecast'] = self::forecast(7);
        return $o;
    }

    public static function forecast($days)
    {
        $rooms = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_status=1'); $out = array(); $bd = class_exists('PulseCore') ? PulseCoreService::businessDate() : date('Y-m-d');
        for ($i = 1; $i <= $days; $i++) { $d = date('Y-m-d', strtotime($bd." +$i day")); $occ = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_refunded=0 AND is_cancelled=0 AND id_status<>3 AND date_from<="'.$d.'" AND date_to>"'.$d.'"'); $out[] = array('d' => $d, 'occupied' => $occ, 'pct' => $rooms ? round($occ / $rooms * 100) : 0, 'arrivals' => (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_refunded=0 AND is_cancelled=0 AND date_from="'.$d.'"')); }
        return $out;
    }

    public static function payments($from, $to)
    {
        if (!self::has('pulse_folio_line')) { return array('total' => 0, 'by_method' => array()); }
        $rows = self::rows('SELECT COALESCE(payment_method,"unknown") method, COUNT(*) n, ROUND(SUM(amount_tax_incl),2) total FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND is_payment=1 AND business_date '.self::range($from, $to).' GROUP BY payment_method ORDER BY total DESC');
        $t = 0; foreach ($rows as $r) { $t += (float) $r['total']; }
        $online = self::v('SELECT COALESCE(SUM(op.amount),0) FROM `'._DB_PREFIX_.'order_payment` op WHERE DATE(op.date_add) '.self::range($from, $to));
        return array('total' => round($t, 2), 'by_method' => $rows, 'online_orders' => round($online, 2));
    }

    public static function ledgers()
    {
        return array('guest_ledger' => self::has('pulse_folio') ? self::v('SELECT COALESCE(SUM(balance),0) FROM `'._DB_PREFIX_.'pulse_folio` WHERE status="open" AND type IN ("guest","group","master")') : 0,
            'city_ledger' => self::has('pulse_company') ? self::v('SELECT COALESCE(SUM(ledger_balance),0) FROM `'._DB_PREFIX_.'pulse_company`') : 0,
            'city_ledger_top' => self::has('pulse_company') ? self::rows('SELECT name, ledger_balance, credit_limit FROM `'._DB_PREFIX_.'pulse_company` WHERE ledger_balance>0 ORDER BY ledger_balance DESC LIMIT 5') : array(),
            'deposits_held' => self::has('pulse_folio_line') ? self::v('SELECT COALESCE(SUM(l.amount_tax_incl),0) FROM `'._DB_PREFIX_.'pulse_folio_line` l INNER JOIN `'._DB_PREFIX_.'pulse_folio` f ON f.id_pulse_folio=l.id_pulse_folio INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=f.id_htl_booking WHERE l.is_payment=1 AND l.voided=0 AND f.status="open" AND b.id_status=1') : 0);
    }

    public static function expenses($from, $to)
    {
        if (!class_exists('PulseExpense')) { return array('total' => 0, 'by_category' => array(), 'by_group' => array(), 'pending' => 0); }
        $g = array(); foreach (PulseExpense::byGroup($from, $to) as $x) { $g[$x['group_name']] = (float) $x['total']; }
        return array('total' => PulseExpense::total($from, $to), 'by_category' => PulseExpense::byCategory($from, $to), 'by_group' => $g, 'pending' => count(PulseExpense::pending()), 'pending_amount' => self::v('SELECT COALESCE(SUM(amount),0) FROM `'._DB_PREFIX_.'pulse_expense` WHERE status="submitted"'),
            'daily' => self::rows('SELECT business_date d, ROUND(SUM(amount),2) total FROM `'._DB_PREFIX_.'pulse_expense` WHERE status IN ("approved","paid") AND business_date '.self::range($from, $to).' GROUP BY business_date ORDER BY business_date'));
    }

    /** Simple operating statement. GOP-style: revenue net of tax − operating expenses. */
    public static function pl(array $d)
    {
        $rev = $d['revenue']['net']; $exp = $d['expenses']['total']; $g = $d['expenses']['by_group'];
        $cos = isset($g['cost_of_sales']) ? $g['cost_of_sales'] : 0; $pay = isset($g['payroll']) ? $g['payroll'] : 0; $util = isset($g['utilities']) ? $g['utilities'] : 0; $rep = isset($g['repairs']) ? $g['repairs'] : 0;
        return array('revenue' => $rev, 'cost_of_sales' => $cos, 'gross_profit' => $rev - $cos, 'payroll' => $pay, 'utilities' => $util, 'repairs' => $rep, 'other_opex' => $exp - $cos - $pay - $util - $rep, 'total_expenses' => $exp, 'gop' => $rev - $exp, 'gop_pct' => $rev > 0 ? round(($rev - $exp) / $rev * 100, 1) : 0,
            'cash_in' => $d['payments']['total'], 'cash_out' => $exp, 'net_cash' => $d['payments']['total'] - $exp);
    }

    public static function frontOffice($from, $to)
    {
        $o = array('arrivals' => 0, 'departures' => 0, 'no_shows' => 0, 'walk_ins' => 0, 'cancellations' => 0, 'upsell_revenue' => 0, 'upsell_count' => 0, 'tickets_open' => 0, 'tickets_raised' => 0, 'tickets_sla_breached' => 0, 'complaints' => 0, 'traces_open' => 0, 'source_mix' => array());
        $o['arrivals'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE date_from '.self::range($from, $to).' AND is_cancelled=0 AND is_refunded=0 AND id_status>=2');
        $o['departures'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE date_to '.self::range($from, $to).' AND id_status=3');
        $o['no_shows'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE date_from '.self::range($from, $to).' AND comment LIKE "%NO-SHOW%"');
        $o['cancellations'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_cancelled=1 AND comment NOT LIKE "%NO-SHOW%" AND DATE(date_upd) '.self::range($from, $to));
        if (self::has('pulse_booking_ext')) { $o['source_mix'] = self::rows('SELECT x.source, COUNT(*) n FROM `'._DB_PREFIX_.'pulse_booking_ext` x INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=x.id_htl_booking WHERE b.date_from '.self::range($from, $to).' GROUP BY x.source ORDER BY n DESC'); $o['walk_ins'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_booking_ext` x INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=x.id_htl_booking WHERE x.source="walkin" AND b.date_from '.self::range($from, $to)); }
        $web = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` b WHERE b.date_from '.self::range($from, $to).' AND b.is_cancelled=0'.(self::has('pulse_booking_ext') ? ' AND b.id NOT IN (SELECT id_htl_booking FROM `'._DB_PREFIX_.'pulse_booking_ext`)' : '')); if ($web) { $o['source_mix'][] = array('source' => 'web', 'n' => $web); }
        if (self::has('pulse_upsell_sale')) { $r = Db::getInstance()->getRow('SELECT COUNT(*) n, COALESCE(SUM(amount_tax_incl),0) a FROM `'._DB_PREFIX_.'pulse_upsell_sale` WHERE DATE(date_add) '.self::range($from, $to)); $o['upsell_count'] = (int) $r['n']; $o['upsell_revenue'] = (float) $r['a']; }
        if (self::has('pulse_ticket')) { $o['tickets_raised'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ticket` WHERE DATE(date_add) '.self::range($from, $to)); $o['tickets_open'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ticket` WHERE status NOT IN ("resolved","closed")'); $o['complaints'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ticket` WHERE category="complaint" AND DATE(date_add) '.self::range($from, $to)); $o['tickets_sla_breached'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ticket` WHERE DATE(date_add) '.self::range($from, $to).' AND ((date_resolved IS NOT NULL AND date_resolved>sla_due) OR (date_resolved IS NULL AND sla_due<NOW()))'); $o['tickets_by_category'] = self::rows('SELECT category, COUNT(*) n FROM `'._DB_PREFIX_.'pulse_ticket` WHERE DATE(date_add) '.self::range($from, $to).' GROUP BY category ORDER BY n DESC'); }
        if (self::has('pulse_trace')) { $o['traces_open'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_trace` WHERE status="open"'); }
        if (self::has('pulse_waitlist')) { $o['waitlist'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_waitlist` WHERE status="waiting"'); }
        return $o;
    }

    public static function housekeeping($from, $to)
    {
        if (!self::has('pulse_housekeeping_task')) { return array(); }
        $r = Db::getInstance()->getRow('SELECT COUNT(*) tasks, SUM(status="done") done, SUM(status="skipped") skipped, ROUND(AVG(IF(status="done",TIMESTAMPDIFF(MINUTE,date_add,date_done),NULL))) avg_min FROM `'._DB_PREFIX_.'pulse_housekeeping_task` WHERE business_date '.self::range($from, $to));
        $r['status_now'] = self::rows('SELECT hk_status, COUNT(*) n FROM `'._DB_PREFIX_.'pulse_room_status` GROUP BY hk_status');
        $r['ooo_rooms'] = self::rows('SELECT r.room_num, s.hk_status, s.ooo_reason, s.ooo_until FROM `'._DB_PREFIX_.'pulse_room_status` s INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=s.id_room WHERE s.hk_status IN ("out_of_order","out_of_service")');
        $r['open_now'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_housekeeping_task` WHERE status IN ("open","in_progress")');
        $r['by_attendant'] = self::rows('SELECT CONCAT(e.firstname," ",e.lastname) attendant, COUNT(*) tasks, SUM(t.status="done") done, ROUND(AVG(IF(t.status="done",TIMESTAMPDIFF(MINUTE,t.date_add,t.date_done),NULL))) avg_min FROM `'._DB_PREFIX_.'pulse_housekeeping_task` t INNER JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=t.assigned_to WHERE t.business_date '.self::range($from, $to).' GROUP BY t.assigned_to ORDER BY tasks DESC');
        return $r;
    }

    public static function laundry($from, $to)
    {
        if (!self::has('pulse_laundry_order')) { return array(); }
        $r = Db::getInstance()->getRow('SELECT COUNT(*) orders, COALESCE(SUM(pieces),0) pieces, ROUND(COALESCE(SUM(IF(type="guest",total_tax_incl,0)),0),2) revenue, SUM(service<>"normal") express, SUM(ready_at>promised_at) late, ROUND(AVG(TIMESTAMPDIFF(HOUR,collected_at,ready_at)),1) turnaround_h, SUM(type="house") house_orders FROM `'._DB_PREFIX_.'pulse_laundry_order` WHERE status<>"cancelled" AND business_date '.self::range($from, $to));
        $r['in_process'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_laundry_order` WHERE status IN ("requested","collected","washing","ready")');
        $r['claims'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_laundry_claim` WHERE DATE(date_add) '.self::range($from, $to));
        $r['claims_open'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_laundry_claim` WHERE status="open"');
        $r['linen_shortfall'] = class_exists('PulseLaundryService') ? array_values(array_filter(PulseLaundryService::linenStatus(), function ($l) { return $l['shortfall'] > 0; })) : array();
        $r['linen_discarded'] = (int) self::v('SELECT COALESCE(SUM(qty),0) FROM `'._DB_PREFIX_.'pulse_linen_movement` WHERE type="discard" AND business_date '.self::range($from, $to));
        return $r;
    }

    public static function maintenance($from, $to)
    {
        if (!self::has('pulse_work_order')) { return array(); }
        $r = class_exists('PulseMaintenanceService') ? PulseMaintenanceService::kpis($from, $to) : array();
        $r['open_now'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_work_order` WHERE status NOT IN ("completed","verified","cancelled")');
        $r['overdue_now'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_work_order` WHERE status NOT IN ("completed","verified","cancelled") AND due_at<NOW()');
        $r['emergencies'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_work_order` WHERE priority="emergency" AND DATE(date_add) '.self::range($from, $to));
        $r['open_list'] = self::rows('SELECT w.wo_no, w.subject, w.priority, w.status, r.room_num, w.due_at FROM `'._DB_PREFIX_.'pulse_work_order` w LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=w.id_room WHERE w.status NOT IN ("completed","verified","cancelled") ORDER BY FIELD(w.priority,"emergency","high","normal","low"), w.due_at LIMIT 10');
        $r['by_category'] = class_exists('PulseMaintenanceService') ? PulseMaintenanceService::byCategory($from, $to) : array();
        $r['low_stock'] = class_exists('PulseMaintenanceService') ? PulseMaintenanceService::lowStock() : array();
        $r['assets_oos'] = self::rows('SELECT code, name FROM `'._DB_PREFIX_.'pulse_asset` WHERE status="out_of_service"');
        $r['meters'] = self::rows('SELECT meter, MAX(reading)-MIN(reading) delta, MAX(read_at) last_read FROM `'._DB_PREFIX_.'pulse_meter_reading` WHERE DATE(read_at) '.self::range($from, $to).' GROUP BY meter');
        return $r;
    }

    public static function guests($from, $to)
    {
        $r = array('vip_in_house' => 0, 'repeat_pct' => 0, 'nationality_mix' => array(), 'top_companies' => array(), 'avg_los' => 0);
        $r['avg_los'] = round(self::v('SELECT AVG(DATEDIFF(date_to,date_from)) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE date_from '.self::range($from, $to).' AND is_cancelled=0'), 1);
        if (self::has('pulse_guest_profile')) {
            $r['vip_in_house'] = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'pulse_guest_profile` p ON p.id_customer=b.id_customer WHERE b.id_status=2 AND p.vip_level>0');
            $tot = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE date_from '.self::range($from, $to).' AND is_cancelled=0 AND id_status>=2');
            $rep = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'pulse_guest_profile` p ON p.id_customer=b.id_customer WHERE b.date_from '.self::range($from, $to).' AND b.is_cancelled=0 AND b.id_status>=2 AND p.stays>1');
            $r['repeat_pct'] = $tot ? round($rep / $tot * 100, 1) : 0;
            $r['nationality_mix'] = self::rows('SELECT COALESCE(NULLIF(p.nationality,""),"n/a") nationality, COUNT(*) n FROM `'._DB_PREFIX_.'htl_booking_detail` b LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` p ON p.id_customer=b.id_customer WHERE b.date_from '.self::range($from, $to).' AND b.is_cancelled=0 GROUP BY nationality ORDER BY n DESC LIMIT 8');
            $r['top_companies'] = self::rows('SELECT c.name, COUNT(*) nights, ROUND(SUM(b.total_price_tax_incl),2) value FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'pulse_guest_profile` p ON p.id_customer=b.id_customer INNER JOIN `'._DB_PREFIX_.'pulse_company` c ON c.id_pulse_company=p.id_pulse_company WHERE b.date_from '.self::range($from, $to).' AND b.is_cancelled=0 GROUP BY c.id_pulse_company ORDER BY value DESC LIMIT 5');
        }
        return $r;
    }

    public static function cashiers($from, $to)
    {
        if (!self::has('pulse_cashier_session')) { return array(); }
        $r = Db::getInstance()->getRow('SELECT COUNT(*) shifts, ROUND(COALESCE(SUM(variance),0),2) variance_total, SUM(ABS(COALESCE(variance,0))>0.009) shifts_with_variance, SUM(status="open") open_now FROM `'._DB_PREFIX_.'pulse_cashier_session` WHERE business_date '.self::range($from, $to));
        $r['variances'] = self::rows('SELECT CONCAT(e.firstname," ",e.lastname) cashier, s.business_date, s.expected_cash, s.counted_cash, s.variance FROM `'._DB_PREFIX_.'pulse_cashier_session` s INNER JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=s.id_employee WHERE s.business_date '.self::range($from, $to).' AND ABS(COALESCE(s.variance,0))>0.009 ORDER BY ABS(s.variance) DESC');
        $r['cash_taken'] = self::has('pulse_folio_line') ? self::v('SELECT COALESCE(SUM(amount_tax_incl),0) FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE is_payment=1 AND voided=0 AND payment_method="cash" AND business_date '.self::range($from, $to)) : 0;
        $r['voids_count'] = self::has('pulse_folio_line') ? (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=1 AND transferred_to IS NULL AND business_date '.self::range($from, $to)) : 0;
        return $r;
    }

    /** Things an owner or GM should act on today. */
    public static function alerts()
    {
        $a = array();
        if (self::has('pulse_cashier_session')) { foreach (self::rows('SELECT CONCAT(e.firstname," ",e.lastname) c, s.variance FROM `'._DB_PREFIX_.'pulse_cashier_session` s INNER JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=s.id_employee WHERE s.date_close>=DATE_SUB(NOW(), INTERVAL 1 DAY) AND ABS(COALESCE(s.variance,0))>='.(float) (Configuration::get('PULSE_RPT_VARIANCE_ALERT') ?: 1000)) as $x) { $a[] = array('level' => 'danger', 'text' => 'Cashier variance '.number_format($x['variance'], 2).' — '.$x['c']); } }
        if (self::has('pulse_company')) { foreach (self::rows('SELECT name, ledger_balance, credit_limit FROM `'._DB_PREFIX_.'pulse_company` WHERE credit_limit>0 AND ledger_balance>=credit_limit*0.9') as $x) { $a[] = array('level' => 'warning', 'text' => $x['name'].' city ledger at '.number_format($x['ledger_balance'], 0).' of '.number_format($x['credit_limit'], 0).' limit'); } }
        if (self::has('pulse_folio')) { $n = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_folio` f INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=f.id_htl_booking WHERE f.status="open" AND b.id_status=2 AND f.balance>'.(float) (Configuration::get('PULSE_RPT_HIGH_BALANCE') ?: 200000)); if ($n) { $a[] = array('level' => 'warning', 'text' => $n.' in-house folio(s) above the high-balance limit'); } }
        if (self::has('pulse_work_order')) { $n = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_work_order` WHERE status NOT IN ("completed","verified","cancelled") AND due_at<NOW()'); if ($n) { $a[] = array('level' => 'warning', 'text' => $n.' maintenance work order(s) overdue'); } $e = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_work_order` WHERE priority="emergency" AND status NOT IN ("completed","verified","cancelled")'); if ($e) { $a[] = array('level' => 'danger', 'text' => $e.' EMERGENCY work order(s) open'); } }
        if (self::has('pulse_room_status')) { $n = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_room_status` WHERE hk_status IN ("out_of_order","out_of_service") AND ooo_until<CURDATE()'); if ($n) { $a[] = array('level' => 'warning', 'text' => $n.' room(s) out of order past their expected return date'); } }
        if (self::has('pulse_ticket')) { $n = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ticket` WHERE category="complaint" AND status NOT IN ("resolved","closed")'); if ($n) { $a[] = array('level' => 'warning', 'text' => $n.' unresolved guest complaint(s)'); } }
        if (self::has('pulse_night_audit')) { $last = Db::getInstance()->getValue('SELECT MAX(business_date) FROM `'._DB_PREFIX_.'pulse_night_audit` WHERE status="closed"'); if ($last && $last < date('Y-m-d', strtotime('-1 day'))) { $a[] = array('level' => 'danger', 'text' => 'Night audit has not closed since '.$last); } $f = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_night_audit` WHERE status="failed"'); if ($f) { $a[] = array('level' => 'danger', 'text' => 'A night audit run failed — see Night Audit log'); } }
        if (class_exists('PulseExpense')) { $p = PulseExpense::pending(); if ($p) { $a[] = array('level' => 'info', 'text' => count($p).' expense(s) awaiting approval'); } }
        if (self::has('pulse_part')) { $n = (int) self::v('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_part` WHERE active=1 AND qty_on_hand<=reorder_level'); if ($n) { $a[] = array('level' => 'info', 'text' => $n.' spare part(s) below reorder level'); } }
        if (file_exists(_PS_MODULE_DIR_.'pulselicense/classes/PulseLicenseService.php') && Module::isEnabled('pulselicense')) { require_once _PS_MODULE_DIR_.'pulselicense/classes/PulseLicenseService.php'; $s = PulseLicenseService::status(); if ($s['state'] !== 'valid' || ($s['days_left'] !== null && $s['days_left'] <= 30)) { $a[] = array('level' => $s['state'] === 'valid' ? 'info' : 'danger', 'text' => 'Pulse license: '.$s['message']); } }
        return $a;
    }

    /** Percentage change helper for comparisons. */
    public static function pct($now, $before) { if (!$before) { return null; } return round(($now - $before) / abs($before) * 100, 1); }
}
