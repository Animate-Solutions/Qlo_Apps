<?php
/**
 * The CRM's send path. It is a subclass of Front Desk's PulseComms rather than a second mailer: the
 * CRM templates are injected into PulseComms' own template map (a subclass shares the parent's static
 * property), so every CRM send still lands in pulse_comms_log through the same adapter the rest of the
 * suite uses. What is added here is the marketing discipline PulseComms has no reason to know about —
 * consent, blacklist, quiet hours, per-guest suppression and open/click tracking.
 */
require_once dirname(__FILE__).'/PulseCrmCommsBase.php';

class PulseCrmComms extends PulseCrmCommsBase
{
    /** Templates the CRM contributes to the shared PulseComms map. */
    public static function templates()
    {
        return array(
            'crm_prearrival'   => array('subject' => 'Getting ready for you — {hotel}', 'sms' => 'Dear {name}, we look forward to seeing you on {from}. Reply UPGRADE for a suite at a members rate, or tell us anything you need. — {hotel}'),
            'crm_welcome'      => array('subject' => 'Welcome to {hotel}, {name}', 'sms' => 'Welcome {name}. You are in room {room}. Order food, request housekeeping and see your bill here: {portal_url}'),
            'crm_midstay'      => array('subject' => 'How is everything, {name}?', 'sms' => 'Dear {name}, how are we doing so far? Thirty seconds: {survey_url} — anything wrong, we fix it tonight. {hotel}'),
            'crm_thankyou'     => array('subject' => 'Thank you for staying with us — {hotel}', 'sms' => 'Thank you for staying with us {name}. Tell us how we did: {survey_url}'),
            'crm_review_ask'   => array('subject' => 'Would you leave us a review?', 'sms' => 'Dear {name}, if we did well would you post a short review? {review_url} It genuinely helps a Port Harcourt hotel. — {hotel}'),
            'crm_winback'      => array('subject' => 'We have missed you at {hotel}', 'sms' => 'Dear {name}, it has been a while. Book direct this month and we will hold a members rate for you. {book_url}'),
            'crm_birthday'     => array('subject' => 'Happy birthday from all of us at {hotel}', 'sms' => 'Happy birthday {name}! Everyone at {hotel} wishes you a wonderful day. Your next stay comes with a complimentary upgrade, subject to availability.'),
            'crm_anniversary'  => array('subject' => 'Happy anniversary — {hotel}', 'sms' => 'Happy anniversary {name}! Stay with us this month and dinner for two is on the house.'),
            'crm_loyalty_welcome' => array('subject' => 'Welcome to {program} — you are member {member_no}', 'sms' => 'Welcome to {program}, {name}. Member number {member_no}, tier {tier}. Balance {points} points.'),
            'crm_loyalty_statement' => array('subject' => 'Your {program} points', 'sms' => 'Dear {name}, your {program} balance is {points} points ({tier}). Redeem at the desk or in the app.'),
            'crm_points_expiring' => array('subject' => 'Points expiring soon — {program}', 'sms' => 'Dear {name}, {points_expiring} of your points expire on {expiry_date}. A stay or a meal keeps them alive.'),
            'crm_tier_up'      => array('subject' => 'You have reached {tier} — {program}', 'sms' => 'Congratulations {name}, you are now {tier} with {program}. Late checkout and room upgrades are yours from your next stay.'),
            'crm_case_apology' => array('subject' => 'About your stay — {hotel}', 'sms' => 'Dear {name}, thank you for telling us what went wrong. {recovery_detail} — {hotel} management.'),
            'crm_campaign'     => array('subject' => '{subject}', 'sms' => '{text}'),
            'crm_survey_invite' => array('subject' => '{subject}', 'sms' => '{text}'),
        );
    }

    /** Injects the CRM templates into the map PulseComms reads. Cheap and idempotent; call it before any send. */
    public static function register()
    {
        static $done = false;
        if ($done) { return; }
        foreach (self::templates() as $k => $v) { if (!isset(self::$templates[$k])) { self::$templates[$k] = $v; } }
        $done = true;
    }

    /** Register an ad-hoc template built from a campaign or journey step body, so PulseComms can render it. */
    public static function registerAdHoc($code, $subject, $text)
    {
        self::register();
        self::$templates[$code] = array('subject' => $subject, 'sms' => $text);
        return $code;
    }

    /* ---------- gates ---------- */

    /** Has this guest already had an automated send today (or inside $days)? Stops journey and campaign collisions. */
    public static function suppressed($idCustomer, $days = 1)
    {
        $days = max(0, (int) $days);
        if (!$days) { return false; }
        return (bool) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_send_log` WHERE id_customer='.(int) $idCustomer.' AND send_date>=DATE_SUB(CURDATE(), INTERVAL '.($days - 1).' DAY)');
    }

    public static function logSend($idCustomer, $channel, $kind, $reference)
    {
        return Db::getInstance()->insert('pulse_crm_send_log', array('id_customer' => (int) $idCustomer, 'channel' => pSQL($channel), 'kind' => pSQL($kind), 'reference' => pSQL($reference), 'send_date' => date('Y-m-d'), 'date_add' => date('Y-m-d H:i:s')));
    }

    /**
     * The one door every CRM message goes through.
     * Returns array(ok, reason) — a refusal is not an error, it is the compliance answer.
     */
    public static function deliver($idCustomer, $channel, $template, array $vars, array $opt = array())
    {
        self::register();
        if (!isset(self::$templates[$template])) { return array('ok' => false, 'reason' => 'unknown_template'); }
        $may = PulseCrmProfile::mayContact($idCustomer, $channel === 'whatsapp' ? 'whatsapp' : ($channel === 'sms' ? 'sms' : 'email'));
        if ($may !== true && empty($opt['transactional'])) { return array('ok' => false, 'reason' => $may); }
        if (empty($opt['ignore_quiet']) && PulseCrmService::inQuietHours(isset($opt['quiet_from']) ? $opt['quiet_from'] : null, isset($opt['quiet_to']) ? $opt['quiet_to'] : null)) { return array('ok' => false, 'reason' => 'quiet_hours'); }
        if (!empty($opt['suppress_days']) && self::suppressed($idCustomer, $opt['suppress_days'])) { return array('ok' => false, 'reason' => 'suppressed'); }
        $c = new Customer((int) $idCustomer);
        if (!Validate::isLoadedObject($c)) { return array('ok' => false, 'reason' => 'no_customer'); }
        $phone = PulseCrmService::gp() ? Db::getInstance()->getValue('SELECT phone FROM `'._DB_PREFIX_.'pulse_guest_profile` WHERE id_customer='.(int) $idCustomer) : null;
        $vars['id_customer'] = (int) $idCustomer; $vars['name'] = isset($vars['name']) && $vars['name'] ? $vars['name'] : $c->firstname;
        try {
            if ($channel === 'email') {
                if (!$c->email || !Validate::isEmail($c->email) || strpos($c->email, '@walkin.local') !== false) { return array('ok' => false, 'reason' => 'no_email'); }
                $ok = self::sendRaw($c->email, null, $template, $vars);
            } elseif ($channel === 'whatsapp') {
                if (!$phone) { return array('ok' => false, 'reason' => 'no_phone'); }
                $ok = self::viaWhatsApp($phone, $template, $vars);
            } else {
                if (!$phone) { return array('ok' => false, 'reason' => 'no_phone'); }
                $ok = self::sendRaw(null, $phone, $template, $vars);
            }
        } catch (Exception $e) { return array('ok' => false, 'reason' => 'error', 'error' => $e->getMessage()); }
        if ($ok) { self::logSend($idCustomer, $channel, isset($opt['kind']) ? $opt['kind'] : 'campaign', isset($opt['reference']) ? $opt['reference'] : ''); }
        return array('ok' => (bool) $ok, 'reason' => $ok ? 'sent' : 'send_failed');
    }

    /**
     * WhatsApp explicitly, whatever the shop-wide channel setting says. PulseComms picks its channel from
     * PULSE_FD_SMS_CHANNEL; a campaign has to be able to choose per message, so we drive the same adapter
     * directly and write the same log row.
     */
    protected static function viaWhatsApp($phone, $template, array $vars)
    {
        $t = self::$templates[$template];
        $text = self::fill($t['sms'], self::vars($vars));
        $a = self::adapter();
        if (!$a) { self::logFailure('whatsapp', $template, $phone, $vars, 'No SMS/WhatsApp adapter configured'); return false; }
        $r = $a->sendWhatsApp($phone, $text);
        if (!PulseCrmService::tableExists('pulse_comms_log')) { return !empty($r['ok']); }
        Db::getInstance()->insert('pulse_comms_log', array('channel' => 'whatsapp', 'template' => pSQL($template), 'to_addr' => pSQL($phone),
            'id_htl_booking' => !empty($vars['id_htl_booking']) ? (int) $vars['id_htl_booking'] : null, 'id_customer' => !empty($vars['id_customer']) ? (int) $vars['id_customer'] : null,
            'status' => !empty($r['ok']) ? 'sent' : 'failed', 'provider_ref' => pSQL(isset($r['ref']) ? $r['ref'] : ''), 'error' => pSQL(isset($r['error']) ? $r['error'] : ''),
            'date_add' => date('Y-m-d H:i:s'), 'date_sent' => !empty($r['ok']) ? date('Y-m-d H:i:s') : null));
        return !empty($r['ok']);
    }

    protected static function logFailure($channel, $template, $to, array $vars, $error)
    {
        if (!PulseCrmService::tableExists('pulse_comms_log')) { return; }
        Db::getInstance()->insert('pulse_comms_log', array('channel' => pSQL($channel), 'template' => pSQL($template), 'to_addr' => pSQL($to),
            'id_htl_booking' => !empty($vars['id_htl_booking']) ? (int) $vars['id_htl_booking'] : null, 'id_customer' => !empty($vars['id_customer']) ? (int) $vars['id_customer'] : null,
            'status' => 'failed', 'error' => pSQL($error), 'date_add' => date('Y-m-d H:i:s')));
    }

    /* ---------- tracking mark-up ---------- */

    /** Wrap an HTML body with a tracking pixel, rewritten links and a footer carrying the unsubscribe link. */
    public static function trackify($html, $token, $unsubUrl)
    {
        $base = PulseCrmService::baseUrl();
        $html = preg_replace_callback('#href="(https?://[^"]+)"#i', function ($m) use ($token) {
            $u = $m[1];
            if (strpos($u, 'pulse/crm/') !== false) { return $m[0]; }
            return 'href="'.PulseCrmService::link('track', array('a' => 'click', 't' => $token, 'u' => base64_encode($u), 's' => PulseCrmService::sign($token.'|'.$u))).'"';
        }, $html);
        $pixel = '<img src="'.PulseCrmService::link('track', array('a' => 'open', 't' => $token)).'" width="1" height="1" alt="" style="display:none">';
        $foot = '<p style="font-size:11px;color:#888;margin-top:24px">You are receiving this because you asked us to keep in touch. '
            .'<a href="'.$unsubUrl.'">Unsubscribe</a> — one click, no questions.</p>';
        return $html.$foot.$pixel.'<!-- '.htmlspecialchars($base, ENT_QUOTES, 'UTF-8').' -->';
    }
}
