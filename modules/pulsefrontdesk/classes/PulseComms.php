<?php
/** Guest communications: templated email (PrestaShop Mail) + SMS/WhatsApp via adapter. Every send is logged. */
class PulseComms
{
    protected static $templates = array(
        'confirmation'   => array('subject' => 'Your reservation at {hotel}', 'sms' => 'Dear {name}, your reservation at {hotel} for {from} to {to} is confirmed. Ref {ref}. Pre-check-in: {precheckin_url}'),
        'precheckin'     => array('subject' => 'Check in online before you arrive — {hotel}', 'sms' => 'Dear {name}, save time at the desk: complete your registration online {precheckin_url}'),
        'welcome'        => array('subject' => 'Welcome to {hotel}', 'sms' => 'Welcome to {hotel}, {name}! You are in room {room}. Dial 0 for the front desk. Enjoy your stay.'),
        'wake_up'        => array('subject' => 'Wake-up call', 'sms' => 'Good morning {name}, this is your {time} wake-up call from {hotel}.'),
        'checkout_receipt' => array('subject' => 'Your receipt from {hotel} — folio {folio}', 'sms' => 'Thank you for staying at {hotel}, {name}. Your receipt for folio {folio} has been emailed. Safe travels!'),
        'express_checkout' => array('subject' => 'Express check-out — {hotel}', 'sms' => 'Dear {name}, you depart today. Review your bill and check out from your phone: {checkout_url}'),
        'waitlist_offer' => array('subject' => 'A room is now available — {hotel}', 'sms' => 'Good news {name}: a {room_type} at {hotel} is now available for {from} to {to}. Reply or call within 24h to confirm.'),
        'ticket_update'  => array('subject' => 'Update on your request — {hotel}', 'sms' => 'Dear {name}, your request "{title}" is now {status}. — {hotel}'),
        'owner_snapshot' => array('subject' => 'Owner snapshot', 'sms' => '{text}'),
        'laundry_delivered' => array('subject' => 'Your laundry has been delivered — {hotel}', 'sms' => 'Dear {name}, your laundry (order {order_no}) has been delivered to your room. — {hotel}'),
        'maintenance_emergency' => array('subject' => 'EMERGENCY maintenance: {subject}', 'sms' => 'EMERGENCY work order raised: {subject}. Respond immediately.'),
    );

    protected static function adapter()
    {
        $cls = Configuration::get('PULSE_FD_SMS_ADAPTER') ?: 'PulseCommsTermii';
        if (!Configuration::get('PULSE_FD_SMS_API_KEY') || !class_exists($cls)) { return null; }
        return new $cls(array('api_key' => Configuration::get('PULSE_FD_SMS_API_KEY'), 'sender_id' => Configuration::get('PULSE_FD_SMS_SENDER') ?: 'Hotel'));
    }

    protected static function vars(array $extra)
    {
        $v = array('hotel' => Configuration::get('PS_SHOP_NAME'), 'name' => '', 'room' => '', 'ref' => '', 'from' => '', 'to' => '', 'time' => '', 'folio' => '', 'title' => '', 'status' => '', 'room_type' => '', 'precheckin_url' => '', 'checkout_url' => '');
        if (!empty($extra['id_htl_booking'])) {
            $b = PulseFdService::booking($extra['id_htl_booking']); $x = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_booking_ext` WHERE id_htl_booking='.(int) $extra['id_htl_booking']);
            if ($b) { $v['name'] = $b['guest']; $v['room'] = $b['room_num']; $v['ref'] = $b['order_ref']; $v['from'] = $b['date_from']; $v['to'] = $b['date_to']; $v['folio'] = $b['folio_no']; }
            $link = Context::getContext()->link;
            if ($x) { $v['precheckin_url'] = $link->getModuleLink('pulsefrontdesk', 'precheckin', array('t' => $x['precheckin_token'])); $v['checkout_url'] = $link->getModuleLink('pulsefrontdesk', 'selfcheckout', array('t' => $x['checkout_token'])); }
        }
        return array_merge($v, $extra);
    }

    protected static function fill($text, array $vars) { foreach ($vars as $k => $val) { if (is_scalar($val)) { $text = str_replace('{'.$k.'}', $val, $text); } } return $text; }

    /** Send a template to a customer (email + SMS/WhatsApp if phone known). */
    public static function send($template, Customer $customer, array $extra = array())
    {
        $phone = Db::getInstance()->getValue('SELECT phone FROM `'._DB_PREFIX_.'pulse_guest_profile` WHERE id_customer='.(int) $customer->id);
        $extra['name'] = $customer->firstname; $extra['id_customer'] = $customer->id;
        return self::sendRaw($customer->email, $phone, $template, $extra);
    }

    public static function sendRaw($email, $phone, $template, array $extra = array())
    {
        if (!isset(self::$templates[$template])) { return false; }
        $t = self::$templates[$template]; $v = self::vars($extra); $text = self::fill($t['sms'], $v); $subject = self::fill($t['subject'], $v); $ok = array();
        if ($email && strpos($email, '@walkin.local') === false && Validate::isEmail($email)) {
            $html = !empty($extra['html']) ? $extra['html'] : '<p>'.nl2br(htmlspecialchars($text)).'</p>';
            $sent = Mail::Send((int) Context::getContext()->language->id, 'pulse_generic', $subject, array('{message}' => $html, '{title}' => $subject), $email, null, null, null, null, null, _PS_MODULE_DIR_.'pulsefrontdesk/mails/');
            self::log('email', $template, $email, $extra, $sent ? 'sent' : 'failed', null, $sent ? null : 'Mail::Send failed'); $ok[] = (bool) $sent;
        }
        if ($phone && ($a = self::adapter())) {
            $ch = Configuration::get('PULSE_FD_SMS_CHANNEL') === 'whatsapp' ? 'whatsapp' : 'sms';
            $r = $ch === 'whatsapp' ? $a->sendWhatsApp($phone, $text) : $a->sendSms($phone, $text);
            self::log($ch, $template, $phone, $extra, $r['ok'] ? 'sent' : 'failed', $r['ref'], $r['error']); $ok[] = $r['ok'];
        }
        return in_array(true, $ok, true);
    }

    protected static function log($channel, $template, $to, $extra, $status, $ref, $error)
    {
        Db::getInstance()->insert('pulse_comms_log', array('channel' => pSQL($channel), 'template' => pSQL($template), 'to_addr' => pSQL($to), 'id_htl_booking' => !empty($extra['id_htl_booking']) ? (int) $extra['id_htl_booking'] : null, 'id_customer' => !empty($extra['id_customer']) ? (int) $extra['id_customer'] : null, 'status' => pSQL($status), 'provider_ref' => pSQL($ref), 'error' => pSQL($error), 'date_add' => date('Y-m-d H:i:s'), 'date_sent' => $status === 'sent' ? date('Y-m-d H:i:s') : null));
    }
}
