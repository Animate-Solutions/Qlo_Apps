<?php
/**
 * Creates reservations INSIDE QloApps from Pulse (walk-in, phone, group rooming list, waitlist conversion, day-use).
 * Mirrors what QloApps' back-office "Book Now" does: customer → cart → HotelCartBookingData rows → validateOrder.
 * NOTE: the cart/booking-data field set is the QloApps 1.6.x/1.7 shape; confirm against classes/HotelCartBookingData.php on your build.
 */
class PulseReservation
{
    /** Availability count for a room type over a range, honouring overbooking allowance. Returns [physical, booked, available, over_allowed]. */
    public static function availability($idProduct, $from, $to, $excludeBlock = 0)
    {
        $physical = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` r LEFT JOIN `'._DB_PREFIX_.'pulse_room_status` s ON s.id_room=r.id WHERE r.id_product='.(int) $idProduct.' AND r.id_status=1 AND (s.hk_status IS NULL OR s.hk_status NOT IN ("out_of_order","out_of_service"))');
        // peak concurrent bookings on any night of the range
        $peak = 0;
        for ($d = strtotime($from); $d < strtotime($to); $d += 86400) {
            $day = date('Y-m-d', $d);
            $n = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_product='.(int) $idProduct.' AND is_refunded=0 AND is_cancelled=0 AND id_status<>'.(int) HotelBookingDetail::STATUS_CHECKED_OUT.' AND date_from<="'.$day.'" AND date_to>"'.$day.'"');
            $n += (int) Db::getInstance()->getValue('SELECT COALESCE(SUM(a.blocked-a.picked_up),0) FROM `'._DB_PREFIX_.'pulse_group_block_allot` a INNER JOIN `'._DB_PREFIX_.'pulse_group_block` b ON b.id_pulse_group_block=a.id_pulse_group_block WHERE a.id_product='.(int) $idProduct.' AND b.status IN ("tentative","definite") AND b.cutoff_date>="'.pSQL(PulseCoreService::businessDate()).'" AND b.date_from<="'.$day.'" AND b.date_to>"'.$day.'" AND b.id_pulse_group_block<>'.(int) $excludeBlock);
            $peak = max($peak, $n);
        }
        $over = (int) Db::getInstance()->getValue('SELECT max_over FROM `'._DB_PREFIX_.'pulse_overbooking` WHERE id_product='.(int) $idProduct);
        return array('physical' => $physical, 'booked' => $peak, 'available' => max(0, $physical - $peak), 'over_allowed' => max(0, $physical + $over - $peak));
    }

    /** Rate quote per night (tax incl) using QloApps pricing for the date range. */
    public static function quote($idProduct, $from, $to, $override = null)
    {
        $nights = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400));
        if ($override !== null) { return array('nights' => $nights, 'per_night' => (float) $override, 'total' => round($override * $nights, 2)); }
        $total = 0;
        for ($d = strtotime($from); $d < strtotime($to); $d += 86400) {
            $day = date('Y-m-d', $d);
            if (class_exists('HotelRoomTypeFeaturePricing') && method_exists('HotelRoomTypeFeaturePricing', 'getRoomTypeFeaturePricesPerDay')) {
                $total += (float) HotelRoomTypeFeaturePricing::getRoomTypeFeaturePricesPerDay($idProduct, $day, true);
            } else {
                $total += (float) Product::getPriceStatic($idProduct, true);
            }
        }
        return array('nights' => $nights, 'per_night' => round($total / $nights, 2), 'total' => round($total, 2));
    }

    public static function findOrCreateCustomer(array $g)
    {
        $email = trim($g['email']);
        if ($email && ($id = (int) Customer::customerExists($email, true))) { return new Customer($id); }
        $c = new Customer();
        $c->firstname = $g['firstname']; $c->lastname = $g['lastname'];
        $c->email = $email ?: strtolower(preg_replace('/[^a-z0-9]/i', '', $g['firstname'].$g['lastname'])).'.'.time().'@walkin.local';
        $c->passwd = Tools::encrypt(Tools::passwdGen(12)); $c->active = 1; $c->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
        $c->add();
        PulseGuestProfile::save($c->id, array('phone' => isset($g['phone']) ? $g['phone'] : '', 'nationality' => isset($g['nationality']) ? $g['nationality'] : ''));
        return $c;
    }

    /**
     * Create a confirmed reservation. $rooms = [[id_product, id_room|null, adults, children, rate_override|null], ...]
     * $opts: source, day_use(bool), day_use_until, id_group_block, comment, payment_module, order_state, deposit, deposit_method
     * Returns array of created htl_booking_detail ids (+ id_order).
     */
    public static function create(array $guest, $from, $to, array $rooms, array $opts = array())
    {
        $ctx = Context::getContext();
        if ($from >= $to) { throw new PrestaShopException('Departure must be after arrival'); }
        foreach ($rooms as $r) {
            $av = self::availability($r['id_product'], $from, $to, isset($opts['id_group_block']) ? $opts['id_group_block'] : 0);
            if ($av['over_allowed'] < 1 && empty($opts['id_group_block'])) { throw new PrestaShopException('No availability for room type #'.$r['id_product'].' ('.$av['booked'].'/'.$av['physical'].' booked)'); }
        }
        $customer = self::findOrCreateCustomer($guest);
        $cart = new Cart(); $cart->id_customer = $customer->id; $cart->id_currency = $ctx->currency->id; $cart->id_lang = $ctx->language->id;
        $cart->id_shop = $ctx->shop->id; $cart->id_shop_group = $ctx->shop->id_shop_group; $cart->id_guest = 0; $cart->secure_key = $customer->secure_key;
        $addr = (int) Address::getFirstCustomerAddressId($customer->id); if ($addr) { $cart->id_address_delivery = $cart->id_address_invoice = $addr; }
        $cart->add();
        $idHotel = 0;
        foreach ($rooms as $r) {
            $idHotel = (int) Db::getInstance()->getValue('SELECT id_hotel FROM `'._DB_PREFIX_.'htl_room_type` WHERE id_product='.(int) $r['id_product']);
            $room = !empty($r['id_room']) ? (int) $r['id_room'] : (int) PulseRoom::autoAssign($r['id_product'], $from, $to, $customer->id);
            if (!$room && empty($opts['id_group_block'])) { throw new PrestaShopException('No physical room free for type #'.$r['id_product']); }
            $q = self::quote($r['id_product'], $from, $to, isset($r['rate_override']) ? $r['rate_override'] : null);
            $cart->updateQty($q['nights'], (int) $r['id_product']);
            Db::getInstance()->insert('htl_cart_booking_data', array(
                'id_cart' => (int) $cart->id, 'id_guest' => 0, 'id_customer' => (int) $customer->id, 'id_currency' => (int) $cart->id_currency,
                'id_product' => (int) $r['id_product'], 'id_room' => $room, 'id_hotel' => $idHotel, 'booking_type' => 1, 'comment' => pSQL(isset($opts['comment']) ? $opts['comment'] : ''),
                'quantity' => (int) $q['nights'], 'date_from' => pSQL($from), 'date_to' => pSQL($to), 'adults' => (int) $r['adults'], 'children' => (int) $r['children'],
                'is_refunded' => 0, 'is_back_order' => 0, 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
            ));
        }
        // create the order through a payment module like QloApps back office does
        $modName = !empty($opts['payment_module']) ? $opts['payment_module'] : (Configuration::get('PULSE_FD_WALKIN_PAYMENT_MODULE') ?: 'bankwire');
        $pm = Module::getInstanceByName($modName);
        if (!$pm || !($pm instanceof PaymentModule)) { throw new PrestaShopException('Payment module "'.$modName.'" not available — set one in Front Desk Settings'); }
        $state = !empty($opts['order_state']) ? (int) $opts['order_state'] : (int) (Configuration::get('PULSE_FD_WALKIN_ORDER_STATE') ?: Configuration::get('PS_OS_PAYMENT'));
        $pm->validateOrder((int) $cart->id, $state, $cart->getOrderTotal(true, Cart::BOTH), isset($opts['source']) ? 'Front desk ('.$opts['source'].')' : 'Front desk', isset($opts['comment']) ? $opts['comment'] : '', array(), (int) $cart->id_currency, false, $customer->secure_key);
        $idOrder = (int) $pm->currentOrder;
        if (!$idOrder) { throw new PrestaShopException('Order creation failed'); }
        $ids = array(); $i = 0;
        foreach (Db::getInstance()->executeS('SELECT id, id_room, id_product, total_price_tax_incl FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_order='.$idOrder.' ORDER BY id') as $b) {
            $ids[] = (int) $b['id'];
            $r = isset($rooms[$i]) ? $rooms[$i] : end($rooms); $i++;
            Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_booking_ext` (id_htl_booking,id_pulse_group_block,day_use,day_use_until,source,precheckin_token,checkout_token) VALUES ('.(int) $b['id'].','.(!empty($opts['id_group_block']) ? (int) $opts['id_group_block'] : 'NULL').','.(!empty($opts['day_use']) ? 1 : 0).','.(!empty($opts['day_use_until']) ? '"'.pSQL($opts['day_use_until']).'"' : 'NULL').',"'.pSQL(isset($opts['source']) ? $opts['source'] : 'walkin').'","'.sha1(uniqid('pc', true)).'","'.sha1(uniqid('co', true)).'") ON DUPLICATE KEY UPDATE source=VALUES(source)');
            if ($b['id_room']) { PulseRoom::setFoStatus($b['id_room'], $from === PulseCoreService::businessDate() ? 'due_in' : 'vacant', $b['id']); }
            // contracted / override rate (per room, matched by creation order): rewrite booking totals so night audit posts the agreed price
            if (isset($r['rate_override']) && $r['rate_override'] !== null && $r['rate_override'] !== '' && (int) $r['id_product'] === (int) $b['id_product']) {
                $q = self::quote($r['id_product'], $from, $to, $r['rate_override']); $cc = PulseChargeCode::byCode('ROOM');
                Db::getInstance()->update('htl_booking_detail', array('total_price_tax_incl' => (float) $q['total'], 'total_price_tax_excl' => round($q['total'] / (1 + $cc['tax_rate'] / 100), 2)), 'id='.(int) $b['id']);
            }
        }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'orders` o SET o.total_paid=(SELECT SUM(total_price_tax_incl) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_order=o.id_order), o.total_paid_tax_incl=o.total_paid, o.total_products_wt=o.total_paid WHERE o.id_order='.$idOrder);
        if (!empty($opts['id_group_block'])) { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_group_block_allot` SET picked_up=picked_up+1 WHERE id_pulse_group_block='.(int) $opts['id_group_block'].' AND id_product='.(int) $rooms[0]['id_product']); }
        if (!empty($opts['deposit']) && (float) $opts['deposit'] > 0 && $ids) {
            $b = PulseFdService::booking($ids[0]); $f = PulseFolio::ensureForBooking($b);
            $f->post(isset($opts['deposit_method']) ? $opts['deposit_method'] : 'CASH', 'Deposit at reservation', 1, (float) $opts['deposit'], 0, true, strtolower(isset($opts['deposit_method']) ? $opts['deposit_method'] : 'cash'));
        }
        PulseCoreService::audit('pulsefrontdesk', 'reservation_create', array('order' => $idOrder, 'source' => isset($opts['source']) ? $opts['source'] : 'walkin'), 'orders', $idOrder);
        PulseComms::send('confirmation', $customer, array('id_htl_booking' => $ids ? $ids[0] : 0, 'from' => $from, 'to' => $to));
        return array('id_order' => $idOrder, 'bookings' => $ids);
    }

    /** Extend or shorten a stay. Reprices remaining nights at the current nightly rate. */
    public static function changeDates($idBooking, $newTo, $newFrom = null)
    {
        $b = PulseFdService::booking($idBooking);
        if (!$b) { throw new PrestaShopException('Booking not found'); }
        $from = $newFrom ?: $b['date_from'];
        if ((int) $b['id_status'] === HotelBookingDetail::STATUS_CHECKED_IN) { $from = $b['date_from']; }
        if ($newTo <= $from) { throw new PrestaShopException('Departure must be after arrival'); }
        $cur = (int) $b['nights']; $nightly = (float) $b['total_price_tax_incl'] / max(1, $cur);
        // availability for extension window in the same room
        if ($newTo > $b['date_to']) {
            $clash = Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_room='.(int) $b['id_room'].' AND id<>'.(int) $idBooking.' AND is_refunded=0 AND is_cancelled=0 AND id_status<>'.(int) HotelBookingDetail::STATUS_CHECKED_OUT.' AND date_from<"'.pSQL($newTo).'" AND date_to>"'.pSQL($b['date_to']).'"');
            if ($clash) { throw new PrestaShopException('Room '.$b['room_num'].' is booked by another guest in that period — move the guest first'); }
        }
        $nights = (int) ((strtotime($newTo) - strtotime($from)) / 86400); $cc = PulseChargeCode::byCode('ROOM');
        $total = round($nightly * $nights, 2);
        Db::getInstance()->update('htl_booking_detail', array('date_from' => pSQL($from), 'date_to' => pSQL($newTo), 'total_price_tax_incl' => $total, 'total_price_tax_excl' => round($total / (1 + $cc['tax_rate'] / 100), 2), 'date_upd' => date('Y-m-d H:i:s')), 'id='.(int) $idBooking);
        // keep the QloApps order total honest
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'orders` o SET o.total_paid=(SELECT SUM(total_price_tax_incl) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_order=o.id_order), o.total_paid_tax_incl=o.total_paid, o.total_products_wt=o.total_paid WHERE o.id_order='.(int) $b['id_order']);
        PulseCoreService::audit('pulsefrontdesk', 'change_dates', array('from' => $b['date_from'].'→'.$from, 'to' => $b['date_to'].'→'.$newTo, 'nights' => $cur.'→'.$nights), 'htl_booking_detail', $idBooking);
        PulseCoreService::event('actionPulseStayChanged', array('booking' => PulseFdService::booking($idBooking), 'old_to' => $b['date_to'], 'new_to' => $newTo));
        return $nights;
    }

    /** Upgrade/downgrade room type mid-stay or at check-in, with a per-night differential posted (or waived). */
    public static function changeRoomType($idBooking, $toRoom, $chargeDiffPerNight, $reason = 'Upgrade')
    {
        $b = PulseFdService::booking($idBooking);
        $room = Db::getInstance()->getRow('SELECT r.*, pl.name FROM `'._DB_PREFIX_.'htl_room_information` r INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=r.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' WHERE r.id='.(int) $toRoom);
        if (!$room) { throw new PrestaShopException('Room not found'); }
        $free = array_map(function ($r) { return (int) $r['id_room']; }, PulseRoom::availableRooms($room['id_product'], max($b['date_from'], PulseCoreService::businessDate()), $b['date_to'], $idBooking));
        if (!in_array((int) $toRoom, $free)) { throw new PrestaShopException('Room '.$room['room_num'].' is not available'); }
        Db::getInstance()->update('htl_booking_detail', array('id_product' => (int) $room['id_product'], 'id_room' => (int) $toRoom, 'room_num' => pSQL($room['room_num']), 'room_type_name' => pSQL($room['name'])), 'id='.(int) $idBooking);
        if ((int) $b['id_status'] === HotelBookingDetail::STATUS_CHECKED_IN) {
            Db::getInstance()->insert('pulse_room_move', array('id_htl_booking' => (int) $idBooking, 'from_room' => (int) $b['id_room'], 'to_room' => (int) $toRoom, 'reason' => pSQL($reason), 'id_employee' => (int) Context::getContext()->employee->id, 'date_add' => date('Y-m-d H:i:s')));
            PulseRoom::setFoStatus($b['id_room'], 'vacant', null); PulseRoom::setHkStatus($b['id_room'], 'vacant_dirty', 'upgrade'); PulseHousekeeping::createTask($b['id_room'], 'clean', 3, 'Upgrade move — clean');
            PulseRoom::setFoStatus($toRoom, 'occupied', $idBooking); PulseRoom::setHkStatus($toRoom, 'occupied_clean', 'upgrade');
            Db::getInstance()->update('pulse_folio', array('id_room' => (int) $toRoom), 'id_htl_booking='.(int) $idBooking.' AND status="open"');
            PulseCoreService::event('actionPulseCheckOut', array('booking' => $b, 'id_room' => $b['id_room'], 'room_move' => true));
            PulseCoreService::event('actionPulseCheckIn', array('booking' => PulseFdService::booking($idBooking), 'id_room' => $toRoom, 'room_move' => true));
        } else { PulseRoom::setFoStatus($toRoom, 'due_in', $idBooking); }
        if ((float) $chargeDiffPerNight > 0) {
            $remaining = max(1, (int) ((strtotime($b['date_to']) - strtotime(max($b['date_from'], PulseCoreService::businessDate()))) / 86400));
            $f = PulseFolio::ensureForBooking(PulseFdService::booking($idBooking));
            $f->post('UPG', $reason.' to '.$room['name'].' ('.$remaining.' night(s))', $remaining, (float) $chargeDiffPerNight);
        }
        PulseCoreService::audit('pulsefrontdesk', 'room_type_change', array('to' => $room['room_num'], 'diff' => $chargeDiffPerNight), 'htl_booking_detail', $idBooking);
        return true;
    }
}
