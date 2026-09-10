<?php
/**
 * CRM shared helpers: cross-module guards, business date, signing, quiet hours (Africa/Lagos),
 * merge-tag rendering and the figures behind the CRM dashboard.
 */
class PulseCrmService
{
    const TZ = 'Africa/Lagos';

    public static function fd()
    {
        return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFolio');
    }
    /** Is the Front Desk guest 360 table there to join against? Everything cross-module asks this first. */
    public static function gp()
    {
        return self::tableExists('pulse_guest_profile');
    }
    public static function co()
    {
        return self::tableExists('pulse_company');
    }
    public static function comms()
    {
        return class_exists('PulseComms');
    }
    public static function bd()
    {
        return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d');
    }
    public static function emp()
    {
        $c = Context::getContext();
        return isset($c->employee) && $c->employee->id ? (int) $c->employee->id : 0;
    }
    public static function cfg($k, $default = null)
    {
        $v = Configuration::get('PULSE_CRM_' . $k);
        return ($v === false || $v === '') ? $default : $v;
    }
    public static function db()
    {
        return Db::getInstance();
    }

    /** Cached table-existence probe — every cross-module read is optional, so we ask before we join. */
    public static function tableExists($table)
    {
        static $seen = array();
        if (!isset($seen[$table])) {
            $seen[$table] = (bool) Db::getInstance()->executeS('SHOW TABLES LIKE "' . _DB_PREFIX_ . pSQL($table) . '"');
        }
        return $seen[$table];
    }

    /** Short sequential document numbers (case numbers, member numbers). */
    public static function nextNo($prefix, $width = 4)
    {
        $n = (int) PulseCoreService::setting('pulsecrm', 'seq_' . $prefix) + 1;
        PulseCoreService::setting('pulsecrm', 'seq_' . $prefix, $n);
        return $prefix . date('ym') . str_pad($n % (int) pow(10, $width), $width, '0', STR_PAD_LEFT);
    }

    public static function token($bytes = 16)
    {
        return bin2hex(Tools::getBytes($bytes));
    }

    /** HMAC over a payload with the module secret — used on click-redirects, where the URL travels in the query string. */
    public static function sign($payload)
    {
        return substr(hash_hmac('sha256', (string) $payload, self::cfg('SECRET', _COOKIE_KEY_)), 0, 32);
    }
    public static function verify($payload, $sig)
    {
        $good = self::sign($payload);
        return strlen($sig) === strlen($good) && hash_equals($good, (string) $sig);
    }

    /** True when $when (default now) falls inside the hotel's quiet window in Africa/Lagos. */
    public static function inQuietHours($from = null, $to = null, $when = null)
    {
        $from = $from ? $from : self::cfg('QUIET_FROM', '21:00');
        $to = $to ? $to : self::cfg('QUIET_TO', '08:00');
        if (!$from || !$to || $from === $to) {
            return false;
        }
        try {
            $d = new DateTime($when ? $when : 'now', new DateTimeZone(self::TZ));
        } catch (Exception $e) {
            return false;
        }
        $mins = (int) $d->format('G') * 60 + (int) $d->format('i');
        $f = self::minutes($from);
        $t = self::minutes($to);
        return $f <= $t ? ($mins >= $f && $mins < $t) : ($mins >= $f || $mins < $t);
    }
    protected static function minutes($hhmm)
    {
        $p = explode(':', $hhmm);
        return (int) $p[0] * 60 + (isset($p[1]) ? (int) $p[1] : 0);
    }

    /** Next moment outside the quiet window, as 'Y-m-d H:i:s' in server time. */
    public static function afterQuiet($from = null, $to = null)
    {
        $to = $to ? $to : self::cfg('QUIET_TO', '08:00');
        if (!self::inQuietHours($from, $to)) {
            return date('Y-m-d H:i:s');
        }
        try {
            $d = new DateTime('now', new DateTimeZone(self::TZ));
            $p = explode(':', $to);
            $d->setTime((int) $p[0], isset($p[1]) ? (int) $p[1] : 0, 0);
            if ($d->getTimestamp() <= time()) {
                $d->modify('+1 day');
            }
            $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
            return $d->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return date('Y-m-d H:i:s', time() + 3600);
        }
    }

    /** Merge-tag values for one guest: name, stay, loyalty, links. Never throws — a missing module just leaves a tag blank. */
    public static function mergeVars($idCustomer, array $extra = array())
    {
        $c = new Customer((int) $idCustomer);
        $v = array(
            'hotel' => Configuration::get('PS_SHOP_NAME'),
            'name' => $c->firstname,
            'first_name' => $c->firstname,
            'last_name' => $c->lastname,
            'full_name' => trim($c->firstname . ' ' . $c->lastname),
            'email' => $c->email,
            'city' => 'Port Harcourt',
            'today' => date('d/m/Y'),
            'stays' => 0,
            'nights' => 0,
            'last_stay' => '',
            'tier' => '',
            'points' => 0,
            'member_no' => '',
            'room' => '',
            'from' => '',
            'to' => ''
        );
        $gp = self::gp() ? Db::getInstance()->getRow('SELECT stays, nights, last_stay, phone FROM `' . _DB_PREFIX_ . 'pulse_guest_profile` WHERE id_customer=' . (int) $idCustomer) : null;
        if ($gp) {
            $v['stays'] = (int) $gp['stays'];
            $v['nights'] = (int) $gp['nights'];
            $v['last_stay'] = $gp['last_stay'];
            $v['phone'] = $gp['phone'];
        }
        $m = PulseCrmLoyalty::memberOf($idCustomer);
        if ($m) {
            $v['tier'] = $m['tier_name'];
            $v['points'] = (int) $m['points_balance'];
            $v['member_no'] = $m['member_no'];
        }
        if (!empty($extra['id_htl_booking']) && self::fd() && class_exists('PulseFdService') && ($b = PulseFdService::booking((int) $extra['id_htl_booking']))) {
            $v['room'] = $b['room_num'];
            $v['from'] = $b['date_from'];
            $v['to'] = $b['date_to'];
            $v['ref'] = $b['order_ref'];
            $v['nights_stay'] = $b['nights'];
        }
        return array_merge($v, $extra);
    }

    /** Replace {tags} in a body. Unknown tags are stripped so a guest never sees {this}. */
    public static function render($text, array $vars)
    {
        foreach ($vars as $k => $val) {
            if (is_scalar($val)) {
                $text = str_replace('{' . $k . '}', $val, $text);
            }
        }
        return preg_replace('/\{[a-z_]{2,32}\}/', '', $text);
    }

    public static function baseUrl()
    {
        $u = self::cfg('BASE_URL');
        if ($u) {
            return rtrim($u, '/') . '/';
        }
        return (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . Configuration::get('PS_SHOP_DOMAIN') . __PS_BASE_URI__;
    }
    public static function link($controller, array $params = array())
    {
        return Context::getContext()->link->getModuleLink('pulsecrm', $controller, $params);
    }

    /* ---------- dashboard ---------- */

    /** Today's arrivals decorated with VIP level, occasions, service notes, loyalty tier and any open recovery case. */
    public static function arrivalsBoard($date = null)
    {
        $d = $date ? $date : self::bd();
        if (!self::fd() || !class_exists('PulseFdService') || !class_exists('HotelBookingDetail')) {
            return array();
        }
        $rows = PulseFdService::arrivals($d);
        foreach ($rows as &$r) {
            $id = (int) $r['id_customer'];
            $r['occasions'] = PulseCrmProfile::occasionsNear($id, 3);
            $r['service_notes'] = Db::getInstance()->executeS('SELECT category, value FROM `' . _DB_PREFIX_ . 'pulse_crm_preference` WHERE id_customer=' . $id . ' AND active=1 AND is_service_note=1');
            $r['prefs'] = Db::getInstance()->executeS('SELECT category, value FROM `' . _DB_PREFIX_ . 'pulse_crm_preference` WHERE id_customer=' . $id . ' AND active=1 AND is_service_note=0 ORDER BY category LIMIT 6');
            $m = PulseCrmLoyalty::memberOf($id);
            $r['tier'] = $m ? $m['tier_name'] : '';
            $r['points'] = $m ? (int) $m['points_balance'] : 0;
            $r['member_no'] = $m ? $m['member_no'] : '';
            $r['open_cases'] = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_case` WHERE id_customer=' . $id . ' AND status NOT IN ("closed")');
            $r['nps_band'] = Db::getInstance()->getValue('SELECT nps_band FROM `' . _DB_PREFIX_ . 'pulse_crm_profile_ext` WHERE id_customer=' . $id);
        }
        return $rows;
    }

    /** Headline numbers for the dashboard tiles. */
    public static function kpis($days = 90)
    {
        $db = Db::getInstance();
        $from = date('Y-m-d', strtotime('-' . (int) $days . ' day'));
        $nps = self::npsFor($from, date('Y-m-d'));
        return array(
            'nps' => $nps['nps'],
            'nps_responses' => $nps['responses'],
            'promoters' => $nps['promoters'],
            'detractors' => $nps['detractors'],
            'members' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_member` WHERE status="active"'),
            'points_out' => (int) $db->getValue('SELECT COALESCE(SUM(points_balance),0) FROM `' . _DB_PREFIX_ . 'pulse_crm_member` WHERE status="active"'),
            'liability' => round((float) $db->getValue('SELECT COALESCE(SUM(m.points_balance*p.point_value),0) FROM `' . _DB_PREFIX_ . 'pulse_crm_member` m INNER JOIN `' . _DB_PREFIX_ . 'pulse_crm_loyalty_program` p ON p.id_pulse_crm_loyalty_program=m.id_pulse_crm_loyalty_program WHERE m.status="active"'), 2),
            'cases_open' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_case` WHERE status NOT IN ("closed")'),
            'recovery_cost' => round((float) $db->getValue('SELECT COALESCE(SUM(recovery_cost),0) FROM `' . _DB_PREFIX_ . 'pulse_crm_case` WHERE business_date>="' . pSQL($from) . '"'), 2),
            'reviews_avg' => round((float) $db->getValue('SELECT COALESCE(AVG(rating_pct),0) FROM `' . _DB_PREFIX_ . 'pulse_crm_review` WHERE review_date>="' . pSQL($from) . '"'), 1),
            'reviews_unanswered' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_review` WHERE responded=0'),
            'opt_in_email' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_consent` WHERE channel="email" AND state="opt_in"'),
            'opt_out_email' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_consent` WHERE channel="email" AND state="opt_out"'),
            'segments' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_segment` WHERE active=1'),
            'journeys_active' => (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_journey_run` WHERE status="active"'),
        );
    }

    /** Net promoter score over a window: promoters minus detractors as a percentage of completed responses. */
    public static function npsFor($from, $to)
    {
        $r = Db::getInstance()->getRow('SELECT COUNT(*) responses, SUM(nps>=9) promoters, SUM(nps<=6) detractors, ROUND(AVG(gss),1) gss
            FROM `' . _DB_PREFIX_ . 'pulse_crm_survey_response` WHERE status="completed" AND nps IS NOT NULL AND business_date BETWEEN "' . pSQL($from) . '" AND "' . pSQL($to) . '"');
        $n = (int) $r['responses'];
        return array(
            'responses' => $n,
            'promoters' => (int) $r['promoters'],
            'detractors' => (int) $r['detractors'],
            'gss' => (float) $r['gss'],
            'nps' => $n ? (int) round((($r['promoters'] - $r['detractors']) / $n) * 100) : 0
        );
    }

    /** Monthly NPS trend for the dashboard chart. */
    public static function npsTrend($months = 12)
    {
        return Db::getInstance()->executeS('SELECT DATE_FORMAT(business_date,"%Y-%m") ym, COUNT(*) responses, ROUND(AVG(gss),1) gss,
                ROUND((SUM(nps>=9)-SUM(nps<=6))/COUNT(*)*100) nps
            FROM `' . _DB_PREFIX_ . 'pulse_crm_survey_response` WHERE status="completed" AND nps IS NOT NULL AND business_date>=DATE_SUB(CURDATE(), INTERVAL ' . (int) $months . ' MONTH)
            GROUP BY ym ORDER BY ym');
    }
}
