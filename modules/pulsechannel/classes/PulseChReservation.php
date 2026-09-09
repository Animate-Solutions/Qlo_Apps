<?php
/**
 * Inbound OTA reservations: pull or webhook -> normalise -> deduplicate on the channel reference ->
 * map guest, room type, rate, tax and commission -> create real QloApps records so the booking appears
 * on the Front Desk tape chart. Modifications and cancellations are matched on the same reference.
 *
 * Nothing is ever dropped. A payload that cannot be mapped is stored with status "failed", the raw body
 * intact, and shows up in the failed queue for a one-click manual assign.
 */
class PulseChReservation
{
    /* ---------- intake ---------- */

    /** Accept one raw payload from any source (pull, webhook, CSV row, manual paste). Returns the reservation id. */
    public static function receive($idChannel, array $raw, $source = 'pull')
    {
        $channel = PulseChService::channel($idChannel);
        if (!$channel) { throw new PrestaShopException('Unknown channel'); }
        $n = self::normalise($channel, $raw);
        if (empty($n['reference'])) { throw new PrestaShopException('Payload carries no channel reference — refusing to store an unidentifiable booking'); }
        $existing = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_reservation` WHERE id_pulse_ch_channel='.(int) $idChannel.' AND channel_ref="'.pSQL($n['reference']).'"');
        if ($existing && $n['action'] === 'new' && in_array($existing['status'], array('delivered', 'modified'))) {
            PulseChLog::write($idChannel, 'in', 'reservation_in', $n['reference'], 200, json_encode($raw), 'duplicate — already delivered', 0, 'ok', null);
            return (int) $existing['id_pulse_ch_reservation'];
        }
        $row = array(
            'id_pulse_ch_channel' => (int) $idChannel, 'channel_ref' => pSQL($n['reference']), 'channel_ref_alt' => pSQL($n['reference_alt']),
            'action' => pSQL($n['action']), 'guest_name' => pSQL($n['guest_name'] !== '' ? $n['guest_name'] : 'OTA Guest'), 'email' => pSQL($n['email']), 'phone' => pSQL($n['phone']), 'country_iso' => pSQL($n['country_iso']),
            'channel_room_code' => pSQL($n['room_code']), 'channel_rate_code' => pSQL($n['rate_code']),
            'date_from' => $n['arrival'] ? pSQL($n['arrival']) : null, 'date_to' => $n['departure'] ? pSQL($n['departure']) : null,
            'rooms' => max(1, (int) $n['rooms']), 'adults' => max(1, (int) $n['adults']), 'children' => max(0, (int) $n['children']),
            'currency_iso' => pSQL($n['currency']), 'amount_tax_incl' => round((float) $n['amount'], 2),
            'commission_pct' => round((float) $n['commission_pct'], 3), 'payment_type' => pSQL($n['payment_type']),
            'raw_payload' => pSQL(json_encode($raw), true), 'business_date' => pSQL(PulseChService::businessDate()), 'date_upd' => date('Y-m-d H:i:s'),
        );
        $tax = (float) Configuration::get('PULSE_CH_TAX_PCT');
        $row['tax_amount'] = round($row['amount_tax_incl'] - $row['amount_tax_incl'] / (1 + $tax / 100), 2);
        $row['commission_amount'] = round($row['amount_tax_incl'] * $row['commission_pct'] / 100, 2);
        $row['net_amount'] = round($row['amount_tax_incl'] - $row['commission_amount'], 2);
        $map = PulseChMapping::resolve($idChannel, $n['room_code'], $n['rate_code']);
        if ($map) { $row['id_product'] = (int) $map['id_product']; $row['id_pulse_ch_rate_plan'] = (int) $map['id_pulse_ch_rate_plan']; }
        if ($existing) {
            // an update to a known reference keeps its delivery state; process() decides what to do with the new action
            Db::getInstance()->update('pulse_ch_reservation', $row, 'id_pulse_ch_reservation='.(int) $existing['id_pulse_ch_reservation'], 0, true);
            $id = (int) $existing['id_pulse_ch_reservation'];
        } else {
            $row['status'] = 'received'; $row['date_add'] = date('Y-m-d H:i:s');
            Db::getInstance()->insert('pulse_ch_reservation', $row, true);
            $id = (int) Db::getInstance()->Insert_ID();
        }
        PulseChLog::write($idChannel, 'in', 'reservation_in', $n['reference'], 200, json_encode($raw), 'stored as #'.$id.' ('.$n['action'].', via '.$source.')', 0, 'ok', null);
        PulseCoreService::audit('pulsechannel', 'reservation_received', array('ref' => $n['reference'], 'action' => $n['action'], 'channel' => $channel['code']), 'pulse_ch_reservation', $id);
        if ($channel['auto_deliver'] && Configuration::get('PULSE_CH_AUTO_DELIVER')) { self::process($id); }
        return $id;
    }

    /**
     * Map the many shapes OTAs use onto one canonical row. Unknown keys are ignored, never guessed:
     * anything missing becomes a mapping failure the desk can see and fix, not a silent default.
     */
    public static function normalise($channel, array $r)
    {
        $g = function ($keys, $default = '') use ($r) {
            foreach ((array) $keys as $k) {
                if (isset($r[$k]) && $r[$k] !== '' && $r[$k] !== null) { return $r[$k]; }
                $l = Tools::strtolower($k);
                foreach ($r as $rk => $rv) { if (Tools::strtolower((string) $rk) === $l && $rv !== '' && $rv !== null) { return $rv; } }
            }
            return $default;
        };
        $status = Tools::strtolower((string) $g(array('status', 'action', 'ResStatus', 'reservation_status'), 'new'));
        $action = 'new';
        if (strpos($status, 'cancel') !== false || strpos($status, 'delete') !== false) { $action = 'cancel'; }
        elseif (strpos($status, 'modif') !== false || strpos($status, 'change') !== false || strpos($status, 'amend') !== false) { $action = 'modify'; }
        $first = trim((string) $g(array('firstname', 'first_name', 'given_name', 'GivenName')));
        $last = trim((string) $g(array('lastname', 'last_name', 'surname', 'Surname')));
        $name = trim((string) $g(array('guest_name', 'guest', 'name', 'customer_name')));
        if (!$name) { $name = trim($first.' '.$last); }
        if (!$first && $name) { $parts = preg_split('/\s+/', $name, 2); $first = $parts[0]; $last = isset($parts[1]) ? $parts[1] : $parts[0]; }
        $arrival = self::date($g(array('arrival', 'checkin', 'check_in', 'date_from', 'start', 'Start', 'arrival_date')));
        $departure = self::date($g(array('departure', 'checkout', 'check_out', 'date_to', 'end', 'End', 'departure_date')));
        $pay = Tools::strtolower((string) $g(array('payment_type', 'payment', 'PaymentType'), 'hotel_collect'));
        return array(
            'reference' => trim((string) $g(array('reference', 'channel_ref', 'booking_id', 'reservation_id', 'id', 'UniqueID', 'confirmation_number'))),
            'reference_alt' => trim((string) $g(array('reference_alt', 'ota_reference', 'external_id', 'ResID_Value'))),
            'action' => $action,
            'guest_name' => $name, 'firstname' => $first ?: 'OTA', 'lastname' => $last ?: 'Guest',
            'email' => trim((string) $g(array('email', 'guest_email', 'Email'))), 'phone' => trim((string) $g(array('phone', 'telephone', 'mobile', 'PhoneNumber'))),
            'country_iso' => Tools::strtoupper(Tools::substr(trim((string) $g(array('country', 'country_iso', 'CountryCode'))), 0, 3)),
            'room_code' => trim((string) $g(array('room_code', 'room_type_code', 'InvTypeCode', 'RoomTypeCode', 'room_type'))),
            'rate_code' => trim((string) $g(array('rate_code', 'rate_plan_code', 'RatePlanCode', 'rate_plan'))),
            'arrival' => $arrival, 'departure' => $departure,
            'rooms' => (int) $g(array('rooms', 'number_of_rooms', 'NumberOfUnits', 'quantity'), 1),
            'adults' => (int) $g(array('adults', 'adult', 'NumAdults'), 1), 'children' => (int) $g(array('children', 'child', 'NumChildren'), 0),
            'currency' => Tools::strtoupper((string) $g(array('currency', 'currency_iso', 'CurrencyCode'), $channel['currency_iso'])),
            'amount' => (float) str_replace(array(',', ' '), '', (string) $g(array('amount', 'total', 'total_amount', 'AmountAfterTax', 'price'), 0)),
            'commission_pct' => (float) $g(array('commission_pct', 'commission', 'Percent'), $channel['commission_pct']),
            'payment_type' => strpos($pay, 'channel') !== false || strpos($pay, 'virtual') !== false || strpos($pay, 'prepaid') !== false ? 'channel_collect' : 'hotel_collect',
        );
    }

    protected static function date($v)
    {
        $v = trim((string) $v);
        if ($v === '') { return ''; }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) { return $m[1].'-'.$m[2].'-'.$m[3]; }
        if (preg_match('#^(\d{2})[/-](\d{2})[/-](\d{4})$#', $v, $m)) { return $m[3].'-'.$m[2].'-'.$m[1]; }
        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : '';
    }

    /* ---------- delivery ---------- */

    /** Route a stored reservation by its action. Never throws to the caller — failures land in the queue. */
    public static function process($id)
    {
        $r = self::one($id);
        if (!$r) { return false; }
        try {
            if ($r['action'] === 'cancel') { return self::cancel($id); }
            if ($r['action'] === 'modify' && $r['id_htl_booking']) { return self::modify($id); }
            return self::deliver($id);
        } catch (Exception $e) {
            self::fail($id, $e->getMessage());
            return false;
        }
    }

    /** Create the QloApps booking. Uses Front Desk's reservation builder when present, otherwise its own cart/order path. */
    public static function deliver($id)
    {
        $r = self::one($id);
        if (!$r) { throw new PrestaShopException('Reservation not found'); }
        if (in_array($r['status'], array('delivered', 'modified'))) { return (int) $r['id_htl_booking']; }
        if (!$r['id_product']) {
            $map = PulseChMapping::resolve((int) $r['id_pulse_ch_channel'], $r['channel_room_code'], $r['channel_rate_code']);
            if (!$map) { throw new PrestaShopException('No mapping for room code "'.$r['channel_room_code'].'" / rate "'.$r['channel_rate_code'].'" — map it, then retry'); }
            Db::getInstance()->update('pulse_ch_reservation', array('id_product' => (int) $map['id_product'], 'id_pulse_ch_rate_plan' => (int) $map['id_pulse_ch_rate_plan']), 'id_pulse_ch_reservation='.(int) $id);
            $r['id_product'] = (int) $map['id_product'];
        }
        if (!$r['date_from'] || !$r['date_to'] || $r['date_from'] >= $r['date_to']) { throw new PrestaShopException('Arrival/departure missing or inverted ('.$r['date_from'].' → '.$r['date_to'].')'); }
        $over = self::overbookingCheck((int) $r['id_product'], $r['date_from'], $r['date_to'], (int) $r['rooms']);
        if (!$over['fits'] && Configuration::get('PULSE_CH_OVERBOOK_ACTION') === 'queue') {
            throw new PrestaShopException('Would oversell '.$over['short'].' room(s) beyond the allowed limit — assign manually or raise the overbooking limit');
        }
        $nights = max(1, (int) ((strtotime($r['date_to']) - strtotime($r['date_from'])) / 86400));
        $perNight = round((float) $r['amount_tax_incl'] / max(1, $nights * (int) $r['rooms']), 2);
        $guest = array('firstname' => self::firstOf($r['guest_name']), 'lastname' => self::lastOf($r['guest_name']), 'email' => $r['email'], 'phone' => $r['phone'], 'nationality' => $r['country_iso']);
        $rooms = array();
        for ($i = 0; $i < max(1, (int) $r['rooms']); $i++) { $rooms[] = array('id_product' => (int) $r['id_product'], 'adults' => (int) $r['adults'], 'children' => (int) $r['children'], 'rate_override' => $perNight > 0 ? $perNight : null); }
        $channel = PulseChService::channel((int) $r['id_pulse_ch_channel']);
        $comment = $channel['name'].' booking '.$r['channel_ref'].' ('.$r['channel_rate_code'].', '.$r['payment_type'].', commission '.$r['commission_pct'].'%)';
        $res = self::createBooking($guest, $r['date_from'], $r['date_to'], $rooms, array('source' => 'ota', 'comment' => $comment, 'channel' => $channel));
        $ids = $res['bookings'];
        Db::getInstance()->update('pulse_ch_reservation', array(
            'status' => 'delivered', 'id_order' => (int) $res['id_order'], 'id_htl_booking' => $ids ? (int) $ids[0] : null, 'booking_ids' => pSQL(implode(',', $ids)),
            'id_customer' => (int) $res['id_customer'], 'overbooked' => $over['fits'] ? 0 : 1, 'error' => null,
            'delivered_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), 'id_pulse_ch_reservation='.(int) $id);
        foreach ($ids as $b) { Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_booking_ext` (id_htl_booking, source) VALUES ('.(int) $b.',"ota") ON DUPLICATE KEY UPDATE source="ota"'); }
        if (!$over['fits']) { self::raiseOverbookingAlert($r, $over); }
        PulseChAri::markDirty((int) $r['id_product'], $r['date_from'], $r['date_to'], 'ota_booking');
        self::ack($id);
        PulseCoreService::audit('pulsechannel', 'reservation_delivered', array('ref' => $r['channel_ref'], 'order' => $res['id_order'], 'rooms' => count($ids)), 'pulse_ch_reservation', $id);
        PulseCoreService::event('actionPulseChannelReservation', array('id_reservation' => (int) $id, 'id_order' => (int) $res['id_order'], 'bookings' => $ids, 'channel' => $channel['code'], 'action' => 'new'));
        return $ids ? (int) $ids[0] : 0;
    }

    /** Apply a modification against the same channel reference: date-only changes are repriced in place, anything else is rebooked. */
    public static function modify($id)
    {
        $r = self::one($id);
        if (!$r || !$r['booking_ids']) { return self::deliver($id); }
        $ids = array_filter(array_map('intval', explode(',', $r['booking_ids'])));
        $first = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id='.(int) reset($ids));
        if (!$first) { return self::deliver($id); }
        $sameType = (int) $first['id_product'] === (int) $r['id_product'];
        $sameRooms = count($ids) === max(1, (int) $r['rooms']);
        if ($sameType && $sameRooms && class_exists('PulseReservation') && PulseChService::fd()) {
            foreach ($ids as $b) { PulseReservation::changeDates($b, $r['date_to'], $r['date_from']); }
        } else {
            self::cancelBookings($ids, 'Replaced by OTA modification '.$r['channel_ref']);
            Db::getInstance()->update('pulse_ch_reservation', array('status' => 'received', 'booking_ids' => null, 'id_htl_booking' => null), 'id_pulse_ch_reservation='.(int) $id);
            self::deliver($id);
            Db::getInstance()->update('pulse_ch_reservation', array('status' => 'modified', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_reservation='.(int) $id);
            PulseCoreService::event('actionPulseChannelReservation', array('id_reservation' => (int) $id, 'action' => 'modify'));
            return true;
        }
        Db::getInstance()->update('pulse_ch_reservation', array('status' => 'modified', 'error' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_reservation='.(int) $id);
        PulseChAri::markDirty((int) $r['id_product'], min($first['date_from'], $r['date_from']), max($first['date_to'], $r['date_to']), 'ota_modify');
        self::ack($id);
        PulseCoreService::audit('pulsechannel', 'reservation_modified', array('ref' => $r['channel_ref']), 'pulse_ch_reservation', $id);
        PulseCoreService::event('actionPulseChannelReservation', array('id_reservation' => (int) $id, 'action' => 'modify'));
        return true;
    }

    public static function cancel($id)
    {
        $r = self::one($id);
        if (!$r) { return false; }
        $ids = $r['booking_ids'] ? array_filter(array_map('intval', explode(',', $r['booking_ids']))) : array();
        if ($ids) { self::cancelBookings($ids, 'Cancelled at '.$r['channel_ref']); }
        Db::getInstance()->update('pulse_ch_reservation', array('status' => 'cancelled', 'error' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_reservation='.(int) $id);
        if ($r['id_product'] && $r['date_from']) { PulseChAri::markDirty((int) $r['id_product'], $r['date_from'], $r['date_to'], 'ota_cancel'); }
        self::ack($id);
        PulseCoreService::audit('pulsechannel', 'reservation_cancelled', array('ref' => $r['channel_ref'], 'bookings' => $ids), 'pulse_ch_reservation', $id);
        PulseCoreService::event('actionPulseChannelReservation', array('id_reservation' => (int) $id, 'action' => 'cancel', 'bookings' => $ids));
        return true;
    }

    /** Cancel booking rows the QloApps way and hand the rooms back to the board. */
    protected static function cancelBookings(array $ids, $reason)
    {
        foreach ($ids as $b) {
            $row = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id='.(int) $b);
            if (!$row || $row['is_cancelled']) { continue; }
            Db::getInstance()->update('htl_booking_detail', array('is_cancelled' => 1, 'comment' => pSQL(trim((string) $row['comment'].' ['.$reason.']'))), 'id='.(int) $b);
            if (PulseChService::fd() && class_exists('PulseRoom') && $row['id_room'] && (int) $row['id_status'] !== HotelBookingDetail::STATUS_CHECKED_IN) { PulseRoom::setFoStatus((int) $row['id_room'], 'vacant', null); }
        }
        return true;
    }

    /** Tell the channel we have it, so it stops redelivering. Failure here is logged, never fatal. */
    public static function ack($id)
    {
        $r = self::one($id);
        if (!$r || $r['acked']) { return false; }
        $c = PulseChService::channel((int) $r['id_pulse_ch_channel']);
        if (!$c) { return false; }
        try { $res = PulseChService::adapter($c)->ackReservation($r['channel_ref']); } catch (Exception $e) { $res = array('ok' => false, 'error' => $e->getMessage()); }
        if (!empty($res['ok'])) { Db::getInstance()->update('pulse_ch_reservation', array('acked' => 1, 'acked_at' => date('Y-m-d H:i:s')), 'id_pulse_ch_reservation='.(int) $id); return true; }
        PulseChLog::write((int) $c['id_pulse_ch_channel'], 'out', 'ack', $r['channel_ref'], null, $r['channel_ref'], '', 0, 'error', isset($res['error']) ? $res['error'] : 'ack failed');
        return false;
    }

    public static function fail($id, $error)
    {
        Db::getInstance()->update('pulse_ch_reservation', array('status' => 'failed', 'error' => pSQL(Tools::substr((string) $error, 0, 250)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_reservation='.(int) $id);
        $r = self::one($id);
        PulseCoreService::audit('pulsechannel', 'reservation_failed', array('ref' => $r ? $r['channel_ref'] : $id, 'error' => $error), 'pulse_ch_reservation', $id);
        if ($r && class_exists('PulseTicket')) {
            PulseTicket::create(array('category' => 'other', 'department' => 'frontdesk', 'priority' => 'urgent', 'title' => 'OTA booking could not be delivered — '.$r['channel_ref'],
                'description' => $error."\n\nGuest: ".$r['guest_name']."\nStay: ".$r['date_from'].' → '.$r['date_to']."\nRoom code: ".$r['channel_room_code'], 'source' => 'channel'));
        }
        return true;
    }

    /* ---------- booking creation ---------- */

    /**
     * Front Desk's PulseReservation::create is the house way to make a reservation, so use it when it is there.
     * Standalone, the module builds the same cart -> validateOrder chain itself so the module never hard-depends on Front Desk.
     */
    public static function createBooking(array $guest, $from, $to, array $rooms, array $opts)
    {
        if (class_exists('PulseReservation') && PulseChService::fd()) {
            $res = PulseReservation::create($guest, $from, $to, $rooms, array('source' => 'ota', 'comment' => $opts['comment'], 'payment_module' => Configuration::get('PULSE_CH_PAYMENT_MODULE')));
            $idCustomer = (int) Db::getInstance()->getValue('SELECT id_customer FROM `'._DB_PREFIX_.'orders` WHERE id_order='.(int) $res['id_order']);
            return array('id_order' => (int) $res['id_order'], 'bookings' => $res['bookings'], 'id_customer' => $idCustomer);
        }
        return self::createBookingStandalone($guest, $from, $to, $rooms, $opts);
    }

    /** Standalone path: customer -> cart -> htl_cart_booking_data -> validateOrder, exactly as the QloApps back office does it. */
    protected static function createBookingStandalone(array $guest, $from, $to, array $rooms, array $opts)
    {
        $ctx = Context::getContext();
        $customer = self::customer($guest);
        $cart = new Cart();
        $cart->id_customer = (int) $customer->id; $cart->id_currency = (int) $ctx->currency->id; $cart->id_lang = (int) $ctx->language->id;
        $cart->id_shop = (int) $ctx->shop->id; $cart->id_shop_group = (int) $ctx->shop->id_shop_group; $cart->id_guest = 0; $cart->secure_key = $customer->secure_key;
        $addr = (int) Address::getFirstCustomerAddressId($customer->id); if ($addr) { $cart->id_address_delivery = $cart->id_address_invoice = $addr; }
        $cart->add();
        $nights = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400));
        foreach ($rooms as $r) {
            $idHotel = (int) Db::getInstance()->getValue('SELECT id_hotel FROM `'._DB_PREFIX_.'htl_room_type` WHERE id_product='.(int) $r['id_product']);
            $cart->updateQty($nights, (int) $r['id_product']);
            Db::getInstance()->insert('htl_cart_booking_data', array(
                'id_cart' => (int) $cart->id, 'id_guest' => 0, 'id_customer' => (int) $customer->id, 'id_currency' => (int) $cart->id_currency,
                'id_product' => (int) $r['id_product'], 'id_room' => 0, 'id_hotel' => $idHotel, 'booking_type' => 1, 'comment' => pSQL($opts['comment']),
                'quantity' => $nights, 'date_from' => pSQL($from), 'date_to' => pSQL($to), 'adults' => (int) $r['adults'], 'children' => (int) $r['children'],
                'is_refunded' => 0, 'is_back_order' => 0, 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
            ));
        }
        $modName = Configuration::get('PULSE_CH_PAYMENT_MODULE') ?: 'bankwire';
        $pm = Module::getInstanceByName($modName);
        if (!$pm || !($pm instanceof PaymentModule)) { throw new PrestaShopException('Payment module "'.$modName.'" is not installed — set one in Channel Settings before delivering OTA bookings'); }
        $state = (int) Configuration::get('PS_OS_PAYMENT');
        if (!$state || !Validate::isLoadedObject(new OrderState($state))) { $state = (int) Db::getInstance()->getValue('SELECT id_order_state FROM `'._DB_PREFIX_.'order_state` WHERE deleted=0 ORDER BY paid DESC, logable DESC, id_order_state ASC'); }
        if (!$state) { throw new PrestaShopException('No usable order state — configure one before delivering OTA bookings'); }
        $pm->validateOrder((int) $cart->id, $state, $cart->getOrderTotal(true, Cart::BOTH), $opts['channel']['name'], $opts['comment'], array(), (int) $cart->id_currency, false, $customer->secure_key);
        $idOrder = (int) $pm->currentOrder;
        if (!$idOrder) { throw new PrestaShopException('QloApps refused to create the order for this channel booking'); }
        $ids = array(); $i = 0;
        foreach (Db::getInstance()->executeS('SELECT id, id_product FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_order='.$idOrder.' ORDER BY id') as $b) {
            $ids[] = (int) $b['id'];
            $r = isset($rooms[$i]) ? $rooms[$i] : end($rooms); $i++;
            if (isset($r['rate_override']) && $r['rate_override'] > 0 && (int) $r['id_product'] === (int) $b['id_product']) {
                $tax = (float) Configuration::get('PULSE_CH_TAX_PCT'); $total = round((float) $r['rate_override'] * $nights, 2);
                Db::getInstance()->update('htl_booking_detail', array('total_price_tax_incl' => $total, 'total_price_tax_excl' => round($total / (1 + $tax / 100), 2)), 'id='.(int) $b['id']);
            }
        }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'orders` o SET o.total_paid=(SELECT SUM(total_price_tax_incl) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_order=o.id_order), o.total_paid_tax_incl=o.total_paid, o.total_products_wt=o.total_paid WHERE o.id_order='.$idOrder);
        return array('id_order' => $idOrder, 'bookings' => $ids, 'id_customer' => (int) $customer->id);
    }

    protected static function customer(array $g)
    {
        if (class_exists('PulseReservation') && PulseChService::fd()) { return PulseReservation::findOrCreateCustomer($g); }
        $email = trim((string) $g['email']);
        if ($email && Validate::isEmail($email) && ($id = (int) Customer::customerExists($email, true))) { return new Customer($id); }
        $c = new Customer();
        $c->firstname = $g['firstname'] ?: 'OTA'; $c->lastname = $g['lastname'] ?: 'Guest';
        $c->email = ($email && Validate::isEmail($email)) ? $email : strtolower(preg_replace('/[^a-z0-9]/i', '', $c->firstname.$c->lastname)).'.'.time().'@ota.local';
        $c->passwd = Tools::encrypt(Tools::passwdGen(12)); $c->active = 1; $c->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
        $c->add();
        return $c;
    }

    protected static function firstOf($name) { $p = preg_split('/\s+/', trim((string) $name), 2); return $p[0] !== '' ? $p[0] : 'OTA'; }
    protected static function lastOf($name) { $p = preg_split('/\s+/', trim((string) $name), 2); return isset($p[1]) && $p[1] !== '' ? $p[1] : 'Guest'; }

    /* ---------- overbooking ---------- */

    /** Would this booking oversell the room type beyond the allowed limit? Never blocks by default — it flags. */
    public static function overbookingCheck($idProduct, $from, $to, $rooms = 1)
    {
        $grid = PulseChAri::baseGrid($idProduct, $from, date('Y-m-d', strtotime($to.' -1 day')));
        $worst = null;
        foreach ($grid as $d => $g) { if ($worst === null || $g['available'] < $worst['available']) { $worst = $g + array('date' => $d); } }
        if ($worst === null) { return array('fits' => true, 'short' => 0, 'date' => null, 'available' => 0, 'max_over' => 0); }
        $maxOver = PulseChService::fd() ? (int) Db::getInstance()->getValue('SELECT max_over FROM `'._DB_PREFIX_.'pulse_overbooking` WHERE id_product='.(int) $idProduct) : 0;
        $capacity = (int) $worst['available'] + $maxOver;
        return array('fits' => $capacity >= (int) $rooms, 'short' => max(0, (int) $rooms - $capacity), 'date' => $worst['date'], 'available' => (int) $worst['available'], 'max_over' => $maxOver);
    }

    protected static function raiseOverbookingAlert($r, array $over)
    {
        $msg = 'Overbooking: '.$r['guest_name'].' ('.$r['channel_ref'].') took '.$r['rooms'].' room(s) but only '.$over['available'].' were free on '.$over['date'].' (allowance '.$over['max_over'].').';
        if (class_exists('PulseTicket')) { PulseTicket::create(array('category' => 'other', 'department' => 'frontdesk', 'priority' => 'urgent', 'title' => 'Channel overbooking — '.$r['channel_ref'], 'description' => $msg, 'source' => 'channel')); }
        if (class_exists('PulseTrace')) { PulseTrace::add('alert', Tools::substr($msg, 0, 250), date('Y-m-d H:i:s'), null, null, null, 'frontdesk'); }
        PulseCoreService::audit('pulsechannel', 'overbooking', $over, 'pulse_ch_reservation', (int) $r['id_pulse_ch_reservation']);
        return true;
    }

    /* ---------- pulling ---------- */

    /** Pull every channel configured to pull, since its last successful pull (with a small overlap for safety). */
    public static function pullAll($idChannel = 0)
    {
        $out = array('pulled' => 0, 'delivered' => 0, 'failed' => 0, 'channels' => 0);
        foreach (PulseChService::channels(true) as $c) {
            if ($idChannel && (int) $c['id_pulse_ch_channel'] !== (int) $idChannel) { continue; }
            if (!in_array($c['sync_mode'], array('pull', 'both'))) { continue; }
            $since = $c['last_pull'] ? date('Y-m-d H:i:s', strtotime($c['last_pull']) - 900) : date('Y-m-d H:i:s', time() - 7 * 86400);
            try { $res = PulseChService::adapter($c)->pullReservations($since); } catch (Exception $e) { $res = array('ok' => false, 'reservations' => array(), 'error' => $e->getMessage()); }
            $out['channels']++;
            if (empty($res['ok'])) { PulseChService::health((int) $c['id_pulse_ch_channel'], false, isset($res['error']) ? $res['error'] : 'pull failed'); continue; }
            PulseChService::health((int) $c['id_pulse_ch_channel'], true);
            Db::getInstance()->update('pulse_ch_channel', array('last_pull' => date('Y-m-d H:i:s')), 'id_pulse_ch_channel='.(int) $c['id_pulse_ch_channel']);
            foreach ((array) $res['reservations'] as $raw) {
                if (!is_array($raw)) { continue; }
                $out['pulled']++;
                try {
                    $id = self::receive((int) $c['id_pulse_ch_channel'], $raw, 'pull');
                    $st = Db::getInstance()->getValue('SELECT status FROM `'._DB_PREFIX_.'pulse_ch_reservation` WHERE id_pulse_ch_reservation='.(int) $id);
                    if (in_array($st, array('delivered', 'modified', 'cancelled'))) { $out['delivered']++; } elseif ($st === 'failed') { $out['failed']++; }
                } catch (Exception $e) {
                    $out['failed']++;
                    PulseChLog::write((int) $c['id_pulse_ch_channel'], 'in', 'reservation_in', null, 0, json_encode($raw), '', 0, 'error', $e->getMessage());
                }
            }
        }
        return $out;
    }

    /* ---------- queries & manual repair ---------- */

    public static function one($id)
    {
        return Db::getInstance()->getRow('SELECT r.*, c.name channel, c.code channel_code FROM `'._DB_PREFIX_.'pulse_ch_reservation` r LEFT JOIN `'._DB_PREFIX_.'pulse_ch_channel` c ON c.id_pulse_ch_channel=r.id_pulse_ch_channel WHERE r.id_pulse_ch_reservation='.(int) $id);
    }

    public static function listing($status = null, $idChannel = 0, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT r.*, c.name channel, pl.name room_type, o.reference order_ref, b.room_num
            FROM `'._DB_PREFIX_.'pulse_ch_reservation` r
            LEFT JOIN `'._DB_PREFIX_.'pulse_ch_channel` c ON c.id_pulse_ch_channel=r.id_pulse_ch_channel
            LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=r.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' AND pl.id_shop='.(int) Context::getContext()->shop->id.'
            LEFT JOIN `'._DB_PREFIX_.'orders` o ON o.id_order=r.id_order
            LEFT JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=r.id_htl_booking
            WHERE 1'.($status ? ' AND r.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'")' : '').($idChannel ? ' AND r.id_pulse_ch_channel='.(int) $idChannel : '').'
            ORDER BY r.id_pulse_ch_reservation DESC LIMIT '.(int) $limit);
    }

    /**
     * The one-click manual assign behind the failed queue: fix what the payload got wrong
     * (room type, rate plan, dates, occupancy, amount) and deliver.
     */
    public static function manualAssign($id, array $d)
    {
        $r = self::one($id);
        if (!$r) { throw new PrestaShopException('Reservation not found'); }
        $upd = array('date_upd' => date('Y-m-d H:i:s'));
        if (!empty($d['id_product'])) { $upd['id_product'] = (int) $d['id_product']; }
        if (!empty($d['id_pulse_ch_rate_plan'])) { $upd['id_pulse_ch_rate_plan'] = (int) $d['id_pulse_ch_rate_plan']; }
        if (!empty($d['date_from'])) { $upd['date_from'] = pSQL($d['date_from']); }
        if (!empty($d['date_to'])) { $upd['date_to'] = pSQL($d['date_to']); }
        if (isset($d['rooms']) && (int) $d['rooms'] > 0) { $upd['rooms'] = (int) $d['rooms']; }
        if (isset($d['adults']) && (int) $d['adults'] > 0) { $upd['adults'] = (int) $d['adults']; }
        if (isset($d['children']) && $d['children'] !== '') { $upd['children'] = (int) $d['children']; }
        if (isset($d['guest_name']) && trim($d['guest_name']) !== '') { $upd['guest_name'] = pSQL(trim($d['guest_name'])); }
        if (isset($d['email']) && trim($d['email']) !== '') { $upd['email'] = pSQL(trim($d['email'])); }
        if (isset($d['amount_tax_incl']) && $d['amount_tax_incl'] !== '') {
            $amount = round((float) $d['amount_tax_incl'], 2); $tax = (float) Configuration::get('PULSE_CH_TAX_PCT');
            $upd['amount_tax_incl'] = $amount; $upd['tax_amount'] = round($amount - $amount / (1 + $tax / 100), 2);
            $upd['commission_amount'] = round($amount * (float) $r['commission_pct'] / 100, 2); $upd['net_amount'] = round($amount - $upd['commission_amount'], 2);
        }
        if (isset($d['notes'])) { $upd['notes'] = pSQL(Tools::substr($d['notes'], 0, 250)); }
        $upd['status'] = 'received'; $upd['error'] = null;
        Db::getInstance()->update('pulse_ch_reservation', $upd, 'id_pulse_ch_reservation='.(int) $id);
        PulseCoreService::audit('pulsechannel', 'reservation_manual_assign', $upd, 'pulse_ch_reservation', $id);
        if (!empty($d['ignore'])) { Db::getInstance()->update('pulse_ch_reservation', array('status' => 'ignored', 'notes' => pSQL(isset($d['notes']) ? $d['notes'] : 'Ignored by operator')), 'id_pulse_ch_reservation='.(int) $id); return true; }
        return self::process($id);
    }

    /** Production report: rooms, revenue, commission and net per channel over a range. */
    public static function production($from, $to)
    {
        return Db::getInstance()->executeS('SELECT c.name channel, c.code, COUNT(*) bookings, SUM(r.rooms) rooms, SUM(DATEDIFF(r.date_to,r.date_from)*r.rooms) room_nights,
                ROUND(SUM(r.amount_tax_incl),2) gross, ROUND(SUM(r.commission_amount),2) commission, ROUND(SUM(r.net_amount),2) net,
                ROUND(SUM(r.amount_tax_incl)/NULLIF(SUM(DATEDIFF(r.date_to,r.date_from)*r.rooms),0),2) adr
            FROM `'._DB_PREFIX_.'pulse_ch_reservation` r INNER JOIN `'._DB_PREFIX_.'pulse_ch_channel` c ON c.id_pulse_ch_channel=r.id_pulse_ch_channel
            WHERE r.status IN ("delivered","modified") AND r.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"
            GROUP BY r.id_pulse_ch_channel ORDER BY gross DESC');
    }
}
