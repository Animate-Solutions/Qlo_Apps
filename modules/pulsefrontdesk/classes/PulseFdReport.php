<?php
/** Manager reports. All are date-range on business_date and return arrays ready for the report template or CSV export. */
class PulseFdReport
{
    /** Daily occupancy, ADR, RevPAR from night-audit snapshots. */
    public static function occupancy($from, $to)
    {
        $rows = Db::getInstance()->executeS('SELECT business_date, rooms_total, rooms_ooo, rooms_occupied, arrivals, departures, no_shows, room_revenue, fnb_revenue, other_revenue, tax, payments, guest_ledger, city_ledger
            FROM `'._DB_PREFIX_.'pulse_night_audit` WHERE status="closed" AND business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY business_date');
        foreach ($rows as &$r) {
            $avail = max(1, $r['rooms_total'] - $r['rooms_ooo']);
            $r['occupancy_pct'] = round($r['rooms_occupied'] / $avail * 100, 1);
            $r['adr'] = $r['rooms_occupied'] ? round($r['room_revenue'] / $r['rooms_occupied'], 2) : 0;
            $r['revpar'] = round($r['room_revenue'] / $avail, 2);
            $r['total_revenue'] = round($r['room_revenue'] + $r['fnb_revenue'] + $r['other_revenue'], 2);
        }
        return $rows;
    }

    public static function revenueByDepartment($from, $to)
    {
        return Db::getInstance()->executeS('SELECT department, COUNT(*) postings, ROUND(SUM(amount_tax_incl/(1+tax_rate/100)),2) net, ROUND(SUM(amount_tax_incl - amount_tax_incl/(1+tax_rate/100)),2) tax, ROUND(SUM(amount_tax_incl),2) gross
            FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND is_payment=0 AND business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY department ORDER BY gross DESC');
    }

    public static function paymentsByMethod($from, $to)
    {
        return Db::getInstance()->executeS('SELECT payment_method, COUNT(*) n, ROUND(SUM(amount_tax_incl),2) total FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND is_payment=1 AND business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY payment_method ORDER BY total DESC');
    }

    public static function cashierShifts($from, $to)
    {
        return Db::getInstance()->executeS('SELECT s.*, CONCAT(e.firstname," ",e.lastname) cashier FROM `'._DB_PREFIX_.'pulse_cashier_session` s INNER JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=s.id_employee WHERE s.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY s.date_open DESC');
    }

    /** Guest ledger: every open folio with balance. */
    public static function guestLedger()
    {
        return Db::getInstance()->executeS('SELECT f.folio_no, f.type, f.balance, f.date_add, r.room_num, CONCAT(c.firstname," ",c.lastname) guest, comp.name company
            FROM `'._DB_PREFIX_.'pulse_folio` f LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=f.id_room LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=f.id_customer LEFT JOIN `'._DB_PREFIX_.'pulse_company` comp ON comp.id_pulse_company=f.id_pulse_company
            WHERE f.status="open" AND ABS(f.balance)>0.009 ORDER BY f.balance DESC');
    }

    public static function cityLedger()
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_company` WHERE ledger_balance<>0 ORDER BY ledger_balance DESC');
    }

    /** Housekeeping productivity: tasks done per attendant per day. */
    public static function housekeeping($from, $to)
    {
        return Db::getInstance()->executeS('SELECT t.business_date, CONCAT(e.firstname," ",e.lastname) attendant, t.type, COUNT(*) tasks, SUM(t.status="done") done, ROUND(AVG(TIMESTAMPDIFF(MINUTE,t.date_add,t.date_done)),0) avg_minutes
            FROM `'._DB_PREFIX_.'pulse_housekeeping_task` t LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=t.assigned_to
            WHERE t.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY t.business_date, t.assigned_to, t.type ORDER BY t.business_date DESC');
    }

    /** Forecast: expected occupancy for the next N days from confirmed bookings. */
    public static function forecast($days = 14)
    {
        $out = array();
        $rooms = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_status=1');
        for ($i = 0; $i < $days; $i++) {
            $d = date('Y-m-d', strtotime(PulseCoreService::businessDate()." +$i day"));
            $occ = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_refunded=0 AND is_cancelled=0 AND id_status<>'.(int) HotelBookingDetail::STATUS_CHECKED_OUT.' AND date_from<="'.$d.'" AND date_to>"'.$d.'"');
            $arr = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_refunded=0 AND is_cancelled=0 AND date_from="'.$d.'"');
            $dep = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_refunded=0 AND is_cancelled=0 AND date_to="'.$d.'"');
            $out[] = array('date' => $d, 'occupied' => $occ, 'arrivals' => $arr, 'departures' => $dep, 'occupancy_pct' => $rooms ? round($occ / $rooms * 100, 1) : 0);
        }
        return $out;
    }

    public static function toCsv(array $rows)
    {
        if (!$rows) { return ''; }
        $f = fopen('php://temp', 'r+');
        fputcsv($f, array_keys($rows[0]));
        foreach ($rows as $r) { fputcsv($f, $r); }
        rewind($f); $csv = stream_get_contents($f); fclose($f);
        return $csv;
    }
}
