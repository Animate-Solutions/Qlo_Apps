<?php
/** Check-in / pre-arrival upsell prompts driven by real availability. */
class PulseUpsell
{
    /** Offers applicable to a booking right now: [id, type, name, price_tax_excl, per, qty, total_excl, target_room (for upgrades)] */
    public static function offersFor($idBooking)
    {
        $b = PulseFdService::booking($idBooking); if (!$b) { return array(); }
        $out = array(); $nights = max(1, (int) $b['nights']); $pax = max(1, (int) $b['adults'] + (int) $b['children']);
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_upsell_offer` WHERE active=1 ORDER BY sort') as $o) {
            $qty = $o['per'] === 'night' ? $nights : ($o['per'] === 'person' ? $pax * $nights : 1);
            if ($o['type'] === 'room_upgrade') {
                // next room types priced above the current one with availability over the stay
                $cur = (float) Product::getPriceStatic((int) $b['id_product'], true);
                $types = Db::getInstance()->executeS('SELECT rt.id_product, pl.name FROM `'._DB_PREFIX_.'htl_room_type` rt INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=rt.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' WHERE rt.id_hotel='.(int) $b['id_hotel'].' AND rt.id_product<>'.(int) $b['id_product']);
                foreach ($types as $t) {
                    $price = (float) Product::getPriceStatic((int) $t['id_product'], true); if ($price <= $cur) { continue; }
                    $av = PulseReservation::availability($t['id_product'], max($b['date_from'], PulseCoreService::businessDate()), $b['date_to']);
                    if ($av['physical'] && ($av['available'] / $av['physical'] * 100) < $o['min_avail_pct']) { continue; }
                    $free = PulseRoom::availableRooms($t['id_product'], max($b['date_from'], PulseCoreService::businessDate()), $b['date_to'], $idBooking); if (!$free) { continue; }
                    $diff = $o['price_tax_excl'] > 0 ? (float) $o['price_tax_excl'] : round(($price - $cur) / (1 + PulseChargeCode::byCode('UPG')['tax_rate'] / 100) * 0.7, 2); // default: 70% of rack differential
                    $out[] = array('id' => $o['id_pulse_upsell_offer'], 'type' => 'room_upgrade', 'name' => 'Upgrade to '.$t['name'], 'price_tax_excl' => $diff, 'per' => 'night', 'qty' => $nights, 'total_excl' => round($diff * $nights, 2), 'target_room' => $free[0]['id_room'], 'target_room_num' => $free[0]['room_num'], 'charge_code' => 'UPG');
                }
                continue;
            }
            $out[] = array('id' => $o['id_pulse_upsell_offer'], 'type' => $o['type'], 'name' => $o['name'], 'price_tax_excl' => (float) $o['price_tax_excl'], 'per' => $o['per'], 'qty' => $qty, 'total_excl' => round($o['price_tax_excl'] * $qty, 2), 'charge_code' => $o['charge_code']);
        }
        return $out;
    }

    public static function accept($idBooking, array $offer, $stage = 'checkin')
    {
        if ($offer['type'] === 'room_upgrade') { PulseReservation::changeRoomType($idBooking, (int) $offer['target_room'], (float) $offer['price_tax_excl'], 'Upsell upgrade'); $amt = $offer['total_excl']; }
        else { $f = PulseFolio::ensureForBooking(PulseFdService::booking($idBooking)); $f->post($offer['charge_code'], $offer['name'], (float) $offer['qty'], (float) $offer['price_tax_excl']); $amt = $offer['total_excl']; }
        Db::getInstance()->insert('pulse_upsell_sale', array('id_pulse_upsell_offer' => (int) $offer['id'], 'id_htl_booking' => (int) $idBooking, 'amount_tax_incl' => (float) $amt, 'id_employee' => isset(Context::getContext()->employee) ? (int) Context::getContext()->employee->id : 0, 'stage' => pSQL($stage), 'date_add' => date('Y-m-d H:i:s')));
        return true;
    }
}
