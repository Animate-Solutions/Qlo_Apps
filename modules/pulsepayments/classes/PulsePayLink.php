<?php
/**
 * Tokenised payment links: pre-arrival deposits, self check-out balances and city-ledger invoices.
 * A link carries its own expiry, use count and amount lock, and never exposes a folio id in the URL.
 */
class PulsePayLink
{
    /** Create a link row. $d: amount, purpose, id_htl_booking, id_pulse_folio, id_pulse_pos_check, id_customer, title, expires_hours, max_uses, amount_locked. */
    public static function create(array $d)
    {
        $amount = round((float) $d['amount'], 2);
        if ($amount <= 0 && !empty($d['amount_locked'])) { throw new PrestaShopException('A locked payment link needs an amount'); }
        $hours = (int) (isset($d['expires_hours']) ? $d['expires_hours'] : Configuration::get('PULSE_PAY_LINK_HOURS'));
        if ($hours <= 0) { $hours = 72; }
        $name = isset($d['customer_name']) ? $d['customer_name'] : '';
        $email = isset($d['customer_email']) ? $d['customer_email'] : '';
        if (!empty($d['id_htl_booking']) && (!$name || !$email)) {
            $b = Db::getInstance()->getRow('SELECT b.id_customer, CONCAT(c.firstname," ",c.lastname) guest, c.email FROM `'._DB_PREFIX_.'htl_booking_detail` b LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer WHERE b.id='.(int) $d['id_htl_booking']);
            if ($b) { $name = $name ?: $b['guest']; $email = $email ?: $b['email']; $d['id_customer'] = isset($d['id_customer']) ? $d['id_customer'] : $b['id_customer']; }
        }
        $row = array(
            'token' => pSQL(sha1(uniqid('pl', true).mt_rand())), 'short_code' => pSQL(self::shortCode()),
            'purpose' => pSQL(isset($d['purpose']) ? $d['purpose'] : 'folio'), 'gateway' => pSQL(isset($d['gateway']) ? $d['gateway'] : ''),
            'amount' => $amount, 'amount_locked' => (int) (isset($d['amount_locked']) ? $d['amount_locked'] : 1), 'min_amount' => round((float) (isset($d['min_amount']) ? $d['min_amount'] : 0), 2),
            'currency' => pSQL(PulsePayService::currency()), 'max_uses' => (int) (isset($d['max_uses']) ? $d['max_uses'] : 1),
            'id_customer' => isset($d['id_customer']) ? (int) $d['id_customer'] : null, 'id_htl_booking' => isset($d['id_htl_booking']) ? (int) $d['id_htl_booking'] : null,
            'id_pulse_folio' => isset($d['id_pulse_folio']) ? (int) $d['id_pulse_folio'] : null, 'id_pulse_pos_check' => isset($d['id_pulse_pos_check']) ? (int) $d['id_pulse_pos_check'] : null,
            'id_pulse_company' => isset($d['id_pulse_company']) ? (int) $d['id_pulse_company'] : null,
            'customer_name' => pSQL($name), 'customer_email' => pSQL($email), 'customer_phone' => pSQL(isset($d['customer_phone']) ? $d['customer_phone'] : ''),
            'title' => pSQL(Tools::substr(isset($d['title']) ? $d['title'] : Configuration::get('PS_SHOP_NAME').' — payment', 0, 128)),
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 255)), 'expires_at' => date('Y-m-d H:i:s', strtotime('+'.$hours.' hours')),
            'id_employee' => PulsePayService::emp(), 'business_date' => pSQL(PulsePayService::bd()), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        );
        Db::getInstance()->insert('pulse_pay_link', $row, true);
        $row['id_pulse_pay_link'] = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::audit('pulsepayments', 'link_create', array('amount' => $amount, 'purpose' => $row['purpose'], 'short' => $row['short_code']), 'pulse_pay_link', $row['id_pulse_pay_link']);
        return $row;
    }

    /** Six readable characters — a guest can be read this over the phone. */
    protected static function shortCode()
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do { $c = ''; for ($i = 0; $i < 6; $i++) { $c .= $alphabet[mt_rand(0, Tools::strlen($alphabet) - 1)]; } } while (Db::getInstance()->getValue('SELECT id_pulse_pay_link FROM `'._DB_PREFIX_.'pulse_pay_link` WHERE short_code="'.pSQL($c).'"'));
        return $c;
    }

    public static function url(array $link) { return PulsePayService::baseUrl().'pulse/pay?t='.$link['token']; }
    public static function shortUrl(array $link) { return PulsePayService::baseUrl().'pulse/pay?c='.$link['short_code']; }

    public static function byToken($token)
    {
        $token = preg_replace('/[^A-Za-z0-9]/', '', (string) $token);
        if ($token === '') { return null; }
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_link` WHERE token="'.pSQL($token).'" OR short_code="'.pSQL(Tools::strtoupper($token)).'"');
    }

    /** open / paid / expired / used-up, with what is still owed. */
    public static function status($token)
    {
        $l = self::byToken($token);
        if (!$l) { return array('ok' => false, 'error' => 'Unknown link'); }
        $expired = $l['expires_at'] && strtotime($l['expires_at']) < time();
        if ($expired && $l['status'] === 'open') { self::setStatus((int) $l['id_pulse_pay_link'], 'expired'); $l['status'] = 'expired'; }
        return array('ok' => true, 'short_code' => $l['short_code'], 'status' => $l['status'], 'amount' => (float) $l['amount'], 'paid' => (float) $l['amount_paid'],
            'outstanding' => round((float) $l['amount'] - (float) $l['amount_paid'], 2), 'uses' => (int) $l['uses'], 'max_uses' => (int) $l['max_uses'],
            'expires_at' => $l['expires_at'], 'usable' => self::usable($l), 'url' => self::url($l));
    }

    public static function usable(array $l) { return $l['status'] === 'open' && (int) $l['uses'] < (int) $l['max_uses'] && (!$l['expires_at'] || strtotime($l['expires_at']) >= time()); }
    public static function setStatus($id, $status) { Db::getInstance()->update('pulse_pay_link', array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pay_link='.(int) $id); }
    public static function cancel($id) { self::setStatus($id, 'cancelled'); PulseCoreService::audit('pulsepayments', 'link_cancel', null, 'pulse_pay_link', (int) $id); }

    /**
     * Record a successful payment against the link and close it when it is fully paid or out of uses.
     * $tx is the transaction that paid: the unique key on pulse_pay_posting(gateway_ref, purpose) makes the
     * credit happen once even though the webhook, the sweep cron and the guest's return page all try it.
     */
    public static function credit(array $link, $amount, array $tx = array())
    {
        if (!empty($tx['id_pulse_pay_transaction'])) {
            $key = !empty($tx['gateway_ref']) ? $tx['gateway_ref'] : $tx['reference'];
            Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_pay_posting` (id_pulse_pay_transaction, gateway_ref, purpose, id_target, amount, date_add) VALUES ('.(int) $tx['id_pulse_pay_transaction'].',"'.pSQL($key).'","link",'.(int) $link['id_pulse_pay_link'].','.(float) $amount.',"'.date('Y-m-d H:i:s').'")');
            if (!Db::getInstance()->Affected_Rows()) { return $link['status']; }
        }
        $paid = round((float) $link['amount_paid'] + (float) $amount, 2);
        $uses = (int) $link['uses'] + 1;
        $status = ($link['amount'] > 0 && $paid + 0.009 >= (float) $link['amount']) || $uses >= (int) $link['max_uses'] ? 'paid' : ($paid > 0 ? 'partly_paid' : 'open');
        Db::getInstance()->update('pulse_pay_link', array('amount_paid' => $paid, 'uses' => $uses, 'status' => pSQL($status), 'paid_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pay_link='.(int) $link['id_pulse_pay_link']);
        return $status;
    }

    public static function all($status = null, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT l.*, ROUND(l.amount-l.amount_paid,2) outstanding, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_pay_link` l LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=l.id_employee'.($status ? ' WHERE l.status="'.pSQL($status).'"' : '').' ORDER BY l.id_pulse_pay_link DESC LIMIT '.(int) $limit);
    }

    /** Mark links nobody used as expired; run from the sweep cron. */
    public static function expireStale()
    {
        $n = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pay_link` WHERE status IN ("open","partly_paid") AND expires_at IS NOT NULL AND expires_at<NOW()');
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pay_link` SET status="expired", date_upd=NOW() WHERE status IN ("open","partly_paid") AND expires_at IS NOT NULL AND expires_at<NOW()');
        return $n;
    }

    /** Email the link to the guest through Pulse Comms when it is installed. */
    public static function send(array $link)
    {
        Db::getInstance()->update('pulse_pay_link', array('sent_at' => date('Y-m-d H:i:s')), 'id_pulse_pay_link='.(int) $link['id_pulse_pay_link']);
        if (!class_exists('PulseComms') || !$link['id_customer']) { return false; }
        $c = new Customer((int) $link['id_customer']);
        if (!Validate::isLoadedObject($c)) { return false; }
        return PulseComms::send('payment_link', $c, array('link_url' => self::url($link), 'amount' => $link['amount'], 'title' => $link['title'], 'expires_at' => $link['expires_at'], 'id_htl_booking' => $link['id_htl_booking']));
    }
}
