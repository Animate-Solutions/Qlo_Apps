<?php
/**
 * Front-desk operations service: arrivals/departures, check-in, check-out, room move, no-show, walk-in.
 * Works on QloApps htl_booking_detail rows and raises Pulse events for other modules.
 */
class PulseFdService
{
    const BOOKING = 'htl_booking_detail';

    /* ---------- queries ---------- */

    protected static function bookingSelect()
    {
        return 'SELECT b.*, CONCAT(c.firstname," ",c.lastname) guest, c.email, r.room_num, r.floor, o.reference order_ref, o.total_paid_real,
                gp.vip_level, gp.blacklisted, gp.id_pulse_company, comp.name company_name,
                f.id_pulse_folio, f.folio_no, f.balance,
                DATEDIFF(b.date_to,b.date_from) nights
            FROM `'._DB_PREFIX_.self::BOOKING.'` b
            LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer
            LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room
            LEFT JOIN `'._DB_PREFIX_.'orders` o ON o.id_order=b.id_order
            LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=b.id_customer
            LEFT JOIN `'._DB_PREFIX_.'pulse_company` comp ON comp.id_pulse_company=gp.id_pulse_company
            LEFT JOIN `'._DB_PREFIX_.'pulse_folio` f ON f.id_htl_booking=b.id AND f.status="open" AND f.type="guest"
            WHERE b.is_refunded=0 AND b.is_cancelled=0 ';
    }

    public static function booking($id)
    {
        return Db::getInstance()->getRow(self::bookingSelect().' AND b.id='.(int) $id);
    }

    public static function arrivals($date = null)
    {
        $d = $date ? pSQL($date) : PulseCoreService::businessDate();
        return Db::getInstance()->executeS(self::bookingSelect().' AND b.date_from="'.$d.'" AND b.id_status='.(int) HotelBookingDetail::STATUS_ALLOTED.' ORDER BY gp.vip_level DESC, r.room_num');
    }

    public static function departures($date = null)
    {
        $d = $date ? pSQL($date) : PulseCoreService::businessDate();
        return Db::getInstance()->executeS(self::bookingSelect().' AND b.date_to="'.$d.'" AND b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' ORDER BY r.room_num');
    }

    public static function inHouse()
    {
        return Db::getInstance()->executeS(self::bookingSelect().' AND b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' ORDER BY r.room_num');
    }

    /** Booked but never checked in and stay date has passed. */
    public static function noShowCandidates($businessDate)
    {
        return Db::getInstance()->executeS(self::bookingSelect().' AND b.date_from<"'.pSQL($businessDate).'" AND b.id_status='.(int) HotelBookingDetail::STATUS_ALLOTED);
    }

    /* ---------- operations ---------- */

    /**
     * Check a booking in. $identity = ['id_type','id_number','issuing_country','expiry','scan_path'] (required unless setting says otherwise).
     * $idRoom lets the agent (re)assign a specific room at check-in.
     */
    public static function checkIn($idBooking, array $identity = array(), $idRoom = null, array $opts = array())
    {
        $b = self::booking($idBooking);
        if (!$b) { throw new PrestaShopException('Booking not found'); }
        if ((int) $b['id_status'] !== HotelBookingDetail::STATUS_ALLOTED) { throw new PrestaShopException('Booking is not awaiting check-in'); }
        if ($b['blacklisted'] && empty($opts['override_blacklist'])) { throw new PrestaShopException('Guest is blacklisted — manager override required'); }
        if ($b['date_from'] > PulseCoreService::businessDate() && empty($opts['early'])) { throw new PrestaShopException('Arrival date is in the future — confirm early check-in'); }

        // room assignment
        if ($idRoom && (int) $idRoom !== (int) $b['id_room']) {
            $free = array_map(function ($r) { return (int) $r['id_room']; }, PulseRoom::availableRooms($b['id_product'], $b['date_from'], $b['date_to'], $idBooking));
            if (!in_array((int) $idRoom, $free)) { throw new PrestaShopException('Selected room is not available'); }
            $room = new HotelRoomInformation((int) $idRoom);
            Db::getInstance()->update(self::BOOKING, array('id_room' => (int) $idRoom, 'room_num' => pSQL($room->room_num)), 'id='.(int) $idBooking);
            $b['id_room'] = (int) $idRoom; $b['room_num'] = $room->room_num;
        }
        $hk = Db::getInstance()->getValue('SELECT hk_status FROM `'._DB_PREFIX_.'pulse_room_status` WHERE id_room='.(int) $b['id_room']);
        if (in_array($hk, array('vacant_dirty', 'out_of_order', 'out_of_service')) && empty($opts['override_dirty'])) {
            throw new PrestaShopException('Room '.$b['room_num'].' is '.str_replace('_', ' ', $hk).' — assign another room or override');
        }
        // ID capture
        if (PulseCoreService::setting('pulsefrontdesk', 'require_id') !== '0') {
            if (empty($identity['id_number']) || empty($identity['id_type'])) { throw new PrestaShopException('Guest ID is required at check-in'); }
        }
        if (!empty($identity['id_number'])) {
            Db::getInstance()->insert('pulse_guest_identity', array(
                'id_customer' => (int) $b['id_customer'], 'id_htl_booking' => (int) $idBooking,
                'id_type' => pSQL($identity['id_type']), 'id_number' => pSQL($identity['id_number']),
                'issuing_country' => pSQL(isset($identity['issuing_country']) ? $identity['issuing_country'] : ''),
                'expiry' => !empty($identity['expiry']) ? pSQL($identity['expiry']) : null,
                'scan_path' => pSQL(isset($identity['scan_path']) ? $identity['scan_path'] : ''),
                'id_employee' => (int) Context::getContext()->employee->id, 'date_add' => date('Y-m-d H:i:s'),
            ));
        }
        // status
        Db::getInstance()->update(self::BOOKING, array('id_status' => HotelBookingDetail::STATUS_CHECKED_IN, 'check_in' => date('Y-m-d H:i:s')), 'id='.(int) $idBooking);
        self::syncOrderState($b['id_order'], 'checkin');
        PulseRoom::setFoStatus($b['id_room'], 'occupied', $idBooking);
        PulseRoom::setHkStatus($b['id_room'], 'occupied_clean', 'checkin');
        // folio + profile
        $folio = PulseFolio::ensureForBooking($b);
        if (!empty($opts['deposit']) && (float) $opts['deposit'] > 0) {
            $folio->post($opts['deposit_method'], 'Deposit at check-in', 1, (float) $opts['deposit'], 0, true, strtolower($opts['deposit_method']));
        }
        PulseGuestProfile::touch($b['id_customer']);
        // registration card (desk signature) / pre-auth / upsells accepted in the check-in dialog
        if (!empty($opts['signature'])) { PulseRegistrationCard::sign($idBooking, $opts['signature'], isset($opts['signed_name']) ? $opts['signed_name'] : $b['guest'], 'desk'); }
        if (!empty($opts['preauth']) && (float) $opts['preauth'] > 0) { PulsePaymentBridge::preAuthorize($idBooking, (float) $opts['preauth']); }
        if (!empty($opts['upsells']) && is_array($opts['upsells'])) { foreach ($opts['upsells'] as $u) { PulseUpsell::accept($idBooking, $u, 'checkin'); } }
        Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_booking_ext` (id_htl_booking, source, precheckin_token, checkout_token) VALUES ('.(int) $idBooking.',"web","'.sha1(uniqid('pc', true)).'","'.sha1(uniqid('co', true)).'")');
        if (($pabx = PulsePabx::driver()) && Configuration::get('PULSE_FD_PABX_URL')) { $pabx->setRoomPhone(PulsePabx::extensionForRoom($b['id_room']), true, $b['guest']); }
        PulseComms::send('welcome', new Customer((int) $b['id_customer']), array('id_htl_booking' => $idBooking));
        PulseCoreService::audit('pulsefrontdesk', 'check_in', array('room' => $b['room_num']), 'htl_booking_detail', $idBooking);
        PulseCoreService::event('actionPulseCheckIn', array('booking' => self::booking($idBooking), 'folio' => $folio, 'id_room' => $b['id_room']));
        return $folio;
    }

    public static function checkOut($idBooking, array $opts = array())
    {
        $b = self::booking($idBooking);
        if (!$b || (int) $b['id_status'] !== HotelBookingDetail::STATUS_CHECKED_IN) { throw new PrestaShopException('Booking is not in-house'); }
        $folio = PulseFolio::ensureForBooking($b);
        // late checkout fee
        $lateAfter = PulseCoreService::setting('pulsefrontdesk', 'checkout_time') ?: '12:00';
        if (date('H:i') > $lateAfter && !empty($opts['late_fee']) && (float) $opts['late_fee'] > 0) {
            $folio->post('LATE', 'Late checkout after '.$lateAfter, 1, (float) $opts['late_fee']);
        }
        // early departure: charge only nights stayed (room charges are posted nightly by night audit, so nothing to reverse)
        if ($folio->balance > 0.009 && !empty($opts['settle_method']) && $opts['settle_method'] === 'CAPTURE') {
            PulsePaymentBridge::capture($idBooking, $folio, $folio->balance);
        }
        if ($folio->balance > 0.009 && !empty($opts['settle_method']) && $opts['settle_method'] === 'FX' && !empty($opts['fx_iso'])) {
            $folio->postForeignPayment('CASH', 'Settlement at check-out', (float) $opts['fx_amount'], $opts['fx_iso'], 'cash_fx');
        }
        if ($folio->balance > 0.009) {
            if (!empty($opts['settle_method']) && !in_array($opts['settle_method'], array('CL', 'CAPTURE', 'FX'))) {
                $folio->post($opts['settle_method'], 'Settlement at check-out', 1, $folio->balance, 0, true, strtolower($opts['settle_method']));
            } elseif (!empty($opts['settle_method']) && $opts['settle_method'] === 'CL' && !empty($opts['id_company'])) {
                $folio->settleToCityLedger(new PulseCompany((int) $opts['id_company']));
            } else {
                throw new PrestaShopException('Outstanding balance '.$folio->balance.' — settle or route to city ledger');
            }
        } elseif ($folio->balance < -0.009 && empty($opts['refund_method'])) {
            throw new PrestaShopException('Folio in credit '.abs($folio->balance).' — choose a refund method');
        } elseif ($folio->balance < -0.009) {
            $folio->post('ADJ', 'Refund of credit balance', 1, $folio->balance, 0, false, strtolower($opts['refund_method']), 'frontdesk', 'refund');
        }
        $folio->close();
        Db::getInstance()->update(self::BOOKING, array('id_status' => HotelBookingDetail::STATUS_CHECKED_OUT, 'check_out' => date('Y-m-d H:i:s')), 'id='.(int) $idBooking);
        self::syncOrderState($b['id_order'], 'checkout');
        PulseRoom::setFoStatus($b['id_room'], 'vacant', null);
        PulseRoom::setHkStatus($b['id_room'], 'vacant_dirty', 'checkout');
        PulseHousekeeping::createTask($b['id_room'], 'clean', 3, 'Departure clean');
        PulseGuestProfile::recordStay($b['id_customer'], (int) $b['nights'], (float) $folio->total_charges, $b['date_to']);
        if (($pabx = PulsePabx::driver()) && Configuration::get('PULSE_FD_PABX_URL')) { $pabx->setRoomPhone(PulsePabx::extensionForRoom($b['id_room']), false); $pabx->cancelWakeUp(PulsePabx::extensionForRoom($b['id_room'])); }
        if (empty($opts['no_receipt'])) { self::emailReceipt($idBooking, $folio); }
        PulseCoreService::audit('pulsefrontdesk', 'check_out', array('room' => $b['room_num'], 'folio' => $folio->folio_no), 'htl_booking_detail', $idBooking);
        PulseCoreService::event('actionPulseCheckOut', array('booking' => $b, 'folio' => $folio, 'id_room' => $b['id_room']));
        return $folio;
    }

    public static function roomMove($idBooking, $toRoom, $reason)
    {
        $b = self::booking($idBooking);
        if (!$b || (int) $b['id_status'] !== HotelBookingDetail::STATUS_CHECKED_IN) { throw new PrestaShopException('Only in-house bookings can be moved'); }
        $free = array_map(function ($r) { return (int) $r['id_room']; }, PulseRoom::availableRooms($b['id_product'], PulseCoreService::businessDate(), $b['date_to'], $idBooking));
        if (!in_array((int) $toRoom, $free)) { throw new PrestaShopException('Target room not available (or different room type — upgrade via rate change first)'); }
        $room = new HotelRoomInformation((int) $toRoom);
        Db::getInstance()->update(self::BOOKING, array('id_room' => (int) $toRoom, 'room_num' => pSQL($room->room_num)), 'id='.(int) $idBooking);
        Db::getInstance()->update('pulse_folio', array('id_room' => (int) $toRoom), 'id_htl_booking='.(int) $idBooking.' AND status="open"');
        Db::getInstance()->insert('pulse_room_move', array('id_htl_booking' => (int) $idBooking, 'from_room' => (int) $b['id_room'], 'to_room' => (int) $toRoom, 'reason' => pSQL($reason), 'id_employee' => (int) Context::getContext()->employee->id, 'date_add' => date('Y-m-d H:i:s')));
        PulseRoom::setFoStatus($b['id_room'], 'vacant', null);
        PulseRoom::setHkStatus($b['id_room'], 'vacant_dirty', 'room_move');
        PulseHousekeeping::createTask($b['id_room'], 'clean', 3, 'Room move — clean');
        PulseRoom::setFoStatus($toRoom, 'occupied', $idBooking);
        PulseRoom::setHkStatus($toRoom, 'occupied_clean', 'room_move');
        PulseCoreService::event('actionPulseRoomMove', array('booking' => self::booking($idBooking), 'from_room' => $b['id_room'], 'to_room' => $toRoom));
        PulseCoreService::event('actionPulseCheckOut', array('booking' => $b, 'id_room' => $b['id_room'], 'room_move' => true));
        PulseCoreService::event('actionPulseCheckIn', array('booking' => self::booking($idBooking), 'id_room' => $toRoom, 'room_move' => true));
        return true;
    }

    public static function markNoShow($idBooking, $chargeFirstNight = true)
    {
        $b = self::booking($idBooking);
        if (!$b) { return false; }
        if ($chargeFirstNight) {
            $folio = PulseFolio::ensureForBooking($b);
            $folio->post('ROOM', 'No-show charge (1 night)', 1, (float) $b['total_price_tax_excl'] / max(1, (int) $b['nights']), null, false, null, 'night_audit', 'no_show');
            if ($folio->balance <= 0.009) { $folio->close(); }
        }
        Db::getInstance()->update(self::BOOKING, array('is_cancelled' => 1, 'comment' => pSQL(trim($b['comment'].' [NO-SHOW '.PulseCoreService::businessDate().']'))), 'id='.(int) $idBooking);
        PulseRoom::setFoStatus($b['id_room'], 'vacant', null);
        PulseCoreService::audit('pulsefrontdesk', 'no_show', null, 'htl_booking_detail', $idBooking);
        PulseCoreService::event('actionPulseNoShow', array('booking' => $b));
        return true;
    }

    /**
     * Map check-in/out to a QloApps order state if the site has configured one.
     * QloApps 1.7 ships "Check-in"/"Check-out" order states; the exact Configuration keys vary by version,
     * so they are read from Front Desk settings (PULSE_FD_OS_CHECKIN / PULSE_FD_OS_CHECKOUT).
     */
    protected static function syncOrderState($idOrder, $which)
    {
        $idState = (int) Configuration::get('PULSE_FD_OS_'.strtoupper($which));
        if (!$idState) { return; }
        // Only move the order when every room on it has reached the same state
        $target = $which === 'checkin' ? HotelBookingDetail::STATUS_CHECKED_IN : HotelBookingDetail::STATUS_CHECKED_OUT;
        $pending = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::BOOKING.'` WHERE id_order='.(int) $idOrder.' AND is_refunded=0 AND is_cancelled=0 AND id_status<'.(int) $target);
        if ($pending === 0) {
            $order = new Order((int) $idOrder);
            if (Validate::isLoadedObject($order) && (int) $order->current_state !== $idState) {
                $h = new OrderHistory(); $h->id_order = $order->id; $h->changeIdOrderState($idState, $order, true); $h->add();
            }
        }
    }

    /** Email the closed folio as an HTML receipt (and SMS notice). */
    public static function emailReceipt($idBooking, PulseFolio $folio)
    {
        $b = self::booking($idBooking); if (!$b) { return false; }
        $cur = Context::getContext()->currency->sign; $rows = '';
        foreach ($folio->lines() as $l) { $rows .= '<tr><td>'.htmlspecialchars($l['business_date']).'</td><td>'.htmlspecialchars($l['description']).'</td><td align="right">'.($l['is_payment'] ? '' : $cur.number_format($l['amount_tax_incl'], 2)).'</td><td align="right">'.($l['is_payment'] ? $cur.number_format($l['amount_tax_incl'], 2) : '').'</td></tr>'; }
        $html = '<h2>'.htmlspecialchars(Configuration::get('PS_SHOP_NAME')).'</h2><p>Folio <b>'.$folio->folio_no.'</b> &middot; '.htmlspecialchars($b['guest']).' &middot; Room '.htmlspecialchars($b['room_num']).' &middot; '.$b['date_from'].' to '.$b['date_to'].'</p>
            <table width="100%" cellpadding="4" style="border-collapse:collapse;font-size:13px"><tr style="background:#eee"><th align="left">Date</th><th align="left">Description</th><th align="right">Charge</th><th align="right">Payment</th></tr>'.$rows.'
            <tr><td colspan="2"><b>Totals</b></td><td align="right"><b>'.$cur.number_format($folio->total_charges, 2).'</b></td><td align="right"><b>'.$cur.number_format($folio->total_payments, 2).'</b></td></tr>
            <tr><td colspan="3"><b>Balance</b></td><td align="right"><b>'.$cur.number_format($folio->balance, 2).'</b></td></tr></table><p>Thank you for staying with us.</p>';
        return PulseComms::send('checkout_receipt', new Customer((int) $b['id_customer']), array('id_htl_booking' => $idBooking, 'folio' => $folio->folio_no, 'html' => $html));
    }

    /** Hourly: post the late check-out fee to due-outs still in-house past checkout time + grace (once), and send express-checkout links in the morning. */
    public static function automateCheckoutDay()
    {
        $bd = PulseCoreService::businessDate(); $n = 0;
        $checkout = Configuration::get('PULSE_FD_CHECKOUT_TIME') ?: '12:00'; $grace = (int) (Configuration::get('PULSE_FD_LATE_GRACE') ?: 60); $fee = (float) Configuration::get('PULSE_FD_LATE_FEE');
        $deadline = date('H:i', strtotime($bd.' '.$checkout) + $grace * 60);
        foreach (self::departures($bd) as $d) {
            $x = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_booking_ext` WHERE id_htl_booking='.(int) $d['id']);
            if (date('H:i') < '10:30' && (!$x || empty($x['express_sent']))) { /* express link once per morning */ }
            if ($fee > 0 && date('H:i') >= $deadline && date('Y-m-d') === $bd && (!$x || !$x['late_fee_posted'])) {
                $f = PulseFolio::ensureForBooking($d); $f->post('LATE', 'Late check-out after '.$checkout.' (auto)', 1, $fee);
                Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_booking_ext` (id_htl_booking, late_fee_posted) VALUES ('.(int) $d['id'].',1) ON DUPLICATE KEY UPDATE late_fee_posted=1');
                PulseTrace::add('alert', 'Late check-out fee auto-posted — room '.$d['room_num'], date('Y-m-d H:i:s'), $d['id'], $d['id_room'], $d['id_customer']); $n++;
            }
        }
        return $n;
    }

    /** Morning batch: send express-checkout links to today's departures (idempotent via comms log). */
    public static function sendExpressCheckoutLinks()
    {
        $bd = PulseCoreService::businessDate(); $n = 0;
        foreach (self::departures($bd) as $d) {
            if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_comms_log` WHERE template="express_checkout" AND id_htl_booking='.(int) $d['id'])) { continue; }
            if (PulseComms::send('express_checkout', new Customer((int) $d['id_customer']), array('id_htl_booking' => $d['id']))) { $n++; }
        }
        return $n;
    }

    /** Pre-arrival: send pre-check-in links for arrivals in N days. */
    public static function sendPrecheckinLinks($daysAhead = 2)
    {
        $d = date('Y-m-d', strtotime(PulseCoreService::businessDate()." +$daysAhead day")); $n = 0;
        foreach (self::arrivals($d) as $a) {
            if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_comms_log` WHERE template="precheckin" AND id_htl_booking='.(int) $a['id'])) { continue; }
            Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_booking_ext` (id_htl_booking, source, precheckin_token, checkout_token) VALUES ('.(int) $a['id'].',"web","'.sha1(uniqid('pc', true)).'","'.sha1(uniqid('co', true)).'")');
            if (PulseComms::send('precheckin', new Customer((int) $a['id_customer']), array('id_htl_booking' => $a['id']))) { $n++; }
        }
        return $n;
    }
}
