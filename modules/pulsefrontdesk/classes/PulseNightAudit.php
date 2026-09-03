<?php
/**
 * Night audit (end-of-day). Runs manually from the back office or via cron/night_audit.php.
 * Steps (OPERA/eZee sequence): pre-checks → no-shows → post room & tax for in-house → HK tasks for next day →
 * snapshot statistics → close cashier sessions → roll business date.
 */
class PulseNightAudit
{
    protected $date; protected $log = array(); protected $id;

    public function __construct($businessDate = null)
    {
        $this->date = $businessDate ?: PulseCoreService::businessDate();
    }

    protected function log($m) { $this->log[] = date('H:i:s').' '.$m; }

    /** Blocking issues the auditor must resolve first. */
    public function preChecks()
    {
        $issues = array();
        $dep = Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_refunded=0 AND is_cancelled=0 AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND date_to<="'.pSQL($this->date).'"');
        if ($dep) { $issues[] = $dep.' guest(s) due out today are still checked in — check out or extend stay'; }
        $open = Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_cashier_session` WHERE status="open"');
        if ($open) { $issues[] = $open.' cashier session(s) still open — cashiers must close their shift'; }
        if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_night_audit` WHERE business_date="'.pSQL($this->date).'" AND status="closed"')) { $issues[] = 'Business date '.$this->date.' is already closed'; }
        return $issues;
    }

    public function run($force = false)
    {
        $issues = $this->preChecks();
        if ($issues && !$force) { throw new PrestaShopException(implode("\n", $issues)); }
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_night_audit` (business_date,id_employee,status,date_add) VALUES ("'.pSQL($this->date).'",'.(int) Context::getContext()->employee->id.',"running",NOW()) ON DUPLICATE KEY UPDATE status="running", id_employee=VALUES(id_employee), date_add=NOW()');
        $this->id = (int) Db::getInstance()->getValue('SELECT id_pulse_night_audit FROM `'._DB_PREFIX_.'pulse_night_audit` WHERE business_date="'.pSQL($this->date).'"');
        try {
            $this->log('Night audit started for '.$this->date);
            $noShows = $this->processNoShows();
            $posted = $this->postRoomCharges();
            $released = PulseGroupBlock::releaseExpired($this->date); $this->log("Group blocks released past cut-off: $released");
            $offers = PulseWaitlist::processOffers(); $this->log("Waitlist offers sent: $offers");
            $hk = PulseHousekeeping::generateDailyTasks(date('Y-m-d', strtotime($this->date.' +1 day')));
            $this->log("HK tasks generated for tomorrow: $hk");
            $stats = $this->snapshot($noShows);
            $next = date('Y-m-d', strtotime($this->date.' +1 day'));
            PulseCoreService::setting('pulsefrontdesk', 'business_date', $next);
            $this->log('Business date rolled to '.$next);
            Db::getInstance()->update('pulse_night_audit', array_merge($stats, array('status' => 'closed', 'log' => pSQL(implode("\n", $this->log)), 'date_end' => date('Y-m-d H:i:s'))), 'id_pulse_night_audit='.$this->id);
            PulseCoreService::audit('pulsefrontdesk', 'night_audit', $stats);
            PulseCoreService::event('actionPulseNightAuditClosed', array('business_date' => $this->date, 'stats' => $stats));
            return $stats;
        } catch (Exception $e) {
            $this->log('FAILED: '.$e->getMessage());
            Db::getInstance()->update('pulse_night_audit', array('status' => 'failed', 'log' => pSQL(implode("\n", $this->log))), 'id_pulse_night_audit='.$this->id);
            throw $e;
        }
    }

    protected function processNoShows()
    {
        $n = 0;
        $charge = PulseCoreService::setting('pulsefrontdesk', 'no_show_charge') !== '0';
        foreach (PulseFdService::noShowCandidates($this->date) as $b) {
            PulseFdService::markNoShow($b['id'], $charge);
            $this->log('No-show: booking #'.$b['id'].' '.$b['guest'].' room '.$b['room_num']);
            $n++;
        }
        return $n;
    }

    /** Post tonight's room charge to every in-house folio (idempotent per business date). */
    protected function postRoomCharges()
    {
        $n = 0; $total = 0;
        foreach (PulseFdService::inHouse() as $b) {
            $folio = PulseFolio::ensureForBooking($b);
            $already = Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE id_pulse_folio='.(int) $folio->id.' AND source="night_audit" AND source_ref="room:'.pSQL($this->date).'" AND voided=0');
            if ($already) { continue; }
            // day-use guests are charged DAYUSE at reservation and must be out before night: flag instead of posting a night
            if (Db::getInstance()->getValue('SELECT day_use FROM `'._DB_PREFIX_.'pulse_booking_ext` WHERE id_htl_booking='.(int) $b['id'])) {
                if (!Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE id_pulse_folio='.(int) $folio->id.' AND source="reservation" AND department="rooms" AND voided=0')) { $folio->post('DAYUSE', 'Day use — room '.$b['room_num'], 1, round((float) $b['total_price_tax_excl'], 2), null, false, null, 'reservation', 'dayuse'); }
                PulseTrace::add('alert', 'Day-use guest still in house at audit — room '.$b['room_num'], date('Y-m-d H:i:s'), $b['id'], $b['id_room'], $b['id_customer']);
                $this->log('Day-use still in house: room '.$b['room_num']); continue;
            }
            $nights = max(1, (int) $b['nights']);
            // nightly rate from QloApps booking total; tax at ROOM charge-code rate
            $cc = PulseChargeCode::byCode('ROOM');
            $nightlyTaxIncl = (float) $b['total_price_tax_incl'] / $nights;
            $nightlyTaxExcl = $nightlyTaxIncl / (1 + (float) $cc['tax_rate'] / 100);
            $folio->post('ROOM', 'Room '.$b['room_num'].' — '.$b['room_type_name'].' — night of '.$this->date, 1, round($nightlyTaxExcl, 2), null, false, null, 'night_audit', 'room:'.$this->date);
            $n++; $total += $nightlyTaxIncl;
        }
        $this->log("Room charges posted: $n rooms, ".number_format($total, 2));
        return $n;
    }

    protected function snapshot($noShows)
    {
        $d = pSQL($this->date);
        $rooms = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_status IN (1,3)');
        $ooo = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_room_status` WHERE hk_status IN ("out_of_order","out_of_service")');
        $occ = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_refunded=0 AND is_cancelled=0 AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN);
        $arr = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE date_from="'.$d.'" AND is_refunded=0 AND is_cancelled=0 AND id_status>='.(int) HotelBookingDetail::STATUS_CHECKED_IN);
        $dep = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE date_to="'.$d.'" AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_OUT);
        $rev = Db::getInstance()->getRow('SELECT
              COALESCE(SUM(IF(is_payment=0 AND department="rooms", amount_tax_incl/(1+tax_rate/100),0)),0) room_revenue,
              COALESCE(SUM(IF(is_payment=0 AND department="fnb", amount_tax_incl/(1+tax_rate/100),0)),0) fnb_revenue,
              COALESCE(SUM(IF(is_payment=0 AND department NOT IN ("rooms","fnb","tax","payment"), amount_tax_incl/(1+tax_rate/100),0)),0) other_revenue,
              COALESCE(SUM(IF(is_payment=0, amount_tax_incl - amount_tax_incl/(1+tax_rate/100),0)),0) tax,
              COALESCE(SUM(IF(is_payment=1, amount_tax_incl,0)),0) payments
            FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND business_date="'.$d.'"');
        $guestLedger = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(balance),0) FROM `'._DB_PREFIX_.'pulse_folio` WHERE status="open" AND type IN ("guest","group","master")');
        $cityLedger = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(ledger_balance),0) FROM `'._DB_PREFIX_.'pulse_company`');
        $stats = array('rooms_total' => $rooms, 'rooms_ooo' => $ooo, 'rooms_occupied' => $occ, 'arrivals' => $arr, 'departures' => $dep, 'no_shows' => (int) $noShows,
            'room_revenue' => round($rev['room_revenue'], 2), 'fnb_revenue' => round($rev['fnb_revenue'], 2), 'other_revenue' => round($rev['other_revenue'], 2), 'tax' => round($rev['tax'], 2),
            'payments' => round($rev['payments'], 2), 'guest_ledger' => round($guestLedger, 2), 'city_ledger' => round($cityLedger, 2));
        $this->log('Snapshot: occ '.$occ.'/'.($rooms - $ooo).' rooms, room rev '.$stats['room_revenue'].', payments '.$stats['payments']);
        return $stats;
    }
}
