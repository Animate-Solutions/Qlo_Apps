<?php
/** Digital registration card with e-signature (desk tablet or pre-arrival link). */
class PulseRegistrationCard
{
    public static function termsVersion() { return Configuration::get('PULSE_FD_TERMS_VERSION') ?: '1.0'; }
    public static function terms() { return Configuration::get('PULSE_FD_TERMS_TEXT') ?: 'I agree to settle all charges incurred during my stay, to the hotel\'s check-out time, no-smoking and conduct policies, and to the processing of my personal data for registration purposes as required by law.'; }

    public static function snapshot($idBooking)
    {
        $b = PulseFdService::booking($idBooking); $p = PulseGuestProfile::get($b['id_customer']);
        return array('guest' => $b['guest'], 'email' => $b['email'], 'phone' => $p['phone'], 'nationality' => $p['nationality'], 'address' => $p['address'], 'room' => $b['room_num'], 'room_type' => $b['room_type_name'], 'from' => $b['date_from'], 'to' => $b['date_to'], 'nights' => $b['nights'], 'adults' => $b['adults'], 'children' => $b['children'], 'rate_total' => $b['total_price_tax_incl'], 'order' => $b['order_ref'], 'company' => $b['company_name'], 'id' => $p['identities'] ? $p['identities'][0]['id_type'].' '.$p['identities'][0]['id_number'] : '');
    }

    public static function sign($idBooking, $signatureDataUrl, $signedName, $channel = 'desk')
    {
        if (strpos($signatureDataUrl, 'data:image/png;base64,') !== 0 || strlen($signatureDataUrl) > 400000) { throw new PrestaShopException('Invalid signature'); }
        $b = PulseFdService::booking($idBooking);
        Db::getInstance()->insert('pulse_registration_card', array('id_htl_booking' => (int) $idBooking, 'id_customer' => (int) $b['id_customer'], 'terms_version' => pSQL(self::termsVersion()), 'terms_accepted' => 1, 'signature' => pSQL($signatureDataUrl, true), 'signed_name' => pSQL($signedName), 'ip' => pSQL(Tools::getRemoteAddr()), 'channel' => pSQL($channel), 'snapshot' => pSQL(json_encode(self::snapshot($idBooking)), true), 'date_add' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::audit('pulsefrontdesk', 'regcard_signed', array('channel' => $channel), 'htl_booking_detail', $idBooking);
        return $id;
    }

    public static function forBooking($idBooking) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_registration_card` WHERE id_htl_booking='.(int) $idBooking.' ORDER BY date_add DESC'); }
}
