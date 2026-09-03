<?php
/**
 * Thin bridge to the pulsepayments module (card pre-authorisation, capture, terminal sync, payment links).
 * When pulsepayments is not installed the desk still works: pre-auth becomes a recorded manual hold.
 */
class PulsePaymentBridge
{
    public static function available() { return Module::isInstalled('pulsepayments') && Module::isEnabled('pulsepayments') && class_exists('PulsePayments') && method_exists('PulsePayments', 'authorize'); }

    /** Pre-authorise (hold) an amount against the guest's card. Returns [ref, amount]. */
    public static function preAuthorize($idBooking, $amount, $token = null)
    {
        $b = PulseFdService::booking($idBooking);
        if (self::available()) { $r = PulsePayments::authorize((int) $b['id_customer'], (float) $amount, $token, array('id_htl_booking' => $idBooking)); $ref = $r['reference']; }
        else { $ref = 'MANUAL-'.strtoupper(substr(sha1($idBooking.time()), 0, 8)); }
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_booking_ext` (id_htl_booking, card_auth_ref, card_auth_amount) VALUES ('.(int) $idBooking.',"'.pSQL($ref).'",'.(float) $amount.') ON DUPLICATE KEY UPDATE card_auth_ref=VALUES(card_auth_ref), card_auth_amount=VALUES(card_auth_amount)');
        PulseCoreService::audit('pulsefrontdesk', 'card_preauth', array('ref' => $ref, 'amount' => $amount), 'htl_booking_detail', $idBooking);
        return array('ref' => $ref, 'amount' => $amount);
    }

    /** Capture up to the held amount at check-out and post it as a CARD payment on the folio. */
    public static function capture($idBooking, PulseFolio $folio, $amount)
    {
        $x = Db::getInstance()->getRow('SELECT card_auth_ref, card_auth_amount FROM `'._DB_PREFIX_.'pulse_booking_ext` WHERE id_htl_booking='.(int) $idBooking);
        if (!$x || !$x['card_auth_ref']) { throw new PrestaShopException('No card authorisation on file'); }
        if (self::available()) { $r = PulsePayments::capture($x['card_auth_ref'], (float) $amount); if (empty($r['ok'])) { throw new PrestaShopException('Capture failed: '.(isset($r['error']) ? $r['error'] : 'unknown')); } }
        $folio->post('CARD', 'Card capture '.$x['card_auth_ref'], 1, (float) $amount, 0, true, 'card', 'payments', $x['card_auth_ref']);
        Db::getInstance()->update('pulse_booking_ext', array('card_auth_ref' => null, 'card_auth_amount' => null), 'id_htl_booking='.(int) $idBooking);
        return true;
    }

    /** Payment link for self check-out / pre-arrival deposit. */
    public static function paymentLink($idBooking, PulseFolio $folio, $amount)
    {
        if (self::available() && method_exists('PulsePayments', 'paymentLink')) { return PulsePayments::paymentLink((float) $amount, array('id_pulse_folio' => $folio->id, 'id_htl_booking' => $idBooking)); }
        return null;
    }
}
