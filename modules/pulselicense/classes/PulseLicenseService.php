<?php
/**
 * Pulse licensing. Keys are RSA-SHA256 signed JSON: base64url(payload).base64url(signature)
 * Payload: { lid, licensee, property, domains[], rooms, modules[], type: perpetual|subscription|trial,
 *            issued, expires|null, maintenance_until|null, grace_days, server|null, features{} }
 * States: valid | grace | over_cap | expired | revoked | invalid | none
 */
class PulseLicenseService
{
    const CFG = 'PULSE_LICENSE_KEY';
    const TRIAL_DAYS = 30;
    const OFFLINE_TOLERANCE_DAYS = 30;   // how long a server-bound license may run without a successful heartbeat
    const DEFAULT_GRACE = 14;

    protected static $cache;

    /* ---------- crypto ---------- */
    public static function publicKey() { return Tools::file_get_contents(_PS_MODULE_DIR_.'pulselicense/keys/public.pem'); }
    public static function b64d($s) { return base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4)); }
    public static function b64e($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

    /** Parse + verify a key string. Returns payload array or throws. */
    public static function parse($key)
    {
        $key = trim(preg_replace('/\s+/', '', $key));
        if (substr_count($key, '.') !== 1) { throw new Exception('Malformed key'); }
        list($p, $s) = explode('.', $key);
        $payload = self::b64d($p); $sig = self::b64d($s);
        if (!function_exists('openssl_verify')) { throw new Exception('OpenSSL extension is required'); }
        $ok = openssl_verify($payload, $sig, self::publicKey(), OPENSSL_ALGO_SHA256);
        if ($ok !== 1) { throw new Exception('Signature verification failed'); }
        $d = json_decode($payload, true);
        if (!$d || empty($d['lid']) || empty($d['type'])) { throw new Exception('Key payload is incomplete'); }
        return $d;
    }

    /* ---------- environment ---------- */
    public static function domain() { return strtolower(preg_replace('/^www\./', '', Tools::getShopDomain())); }
    public static function fingerprint() { return sha1(self::domain().'|'._COOKIE_KEY_); }
    public static function roomsInUse() { return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_status IN (1,3)'); }

    public static function domainMatches(array $domains)
    {
        $d = self::domain();
        foreach ($domains as $pat) {
            $pat = strtolower(trim($pat));
            if ($pat === '*' || $pat === $d) { return true; }
            if (strpos($pat, '*.') === 0 && substr($d, -strlen(substr($pat, 1))) === substr($pat, 1)) { return true; }
            if (in_array($pat, array('localhost', '127.0.0.1')) && in_array($d, array('localhost', '127.0.0.1'))) { return true; }
        }
        return false;
    }

    /* ---------- activation ---------- */
    public static function activate($key)
    {
        $d = self::parse($key);
        if (!self::domainMatches($d['domains'])) { throw new Exception('This key is issued for '.implode(', ', $d['domains']).' — current site is '.self::domain()); }
        if (!empty($d['expires']) && $d['expires'] < date('Y-m-d') && $d['type'] !== 'perpetual') { throw new Exception('This key expired on '.$d['expires']); }
        Configuration::updateValue(self::CFG, $key);
        Configuration::updateValue('PULSE_LICENSE_ACTIVATED', date('Y-m-d H:i:s'));
        Configuration::updateValue('PULSE_LICENSE_REMOTE', '');
        Configuration::updateValue('PULSE_LICENSE_LAST_OK', '');
        self::$cache = null;
        self::log('activate', $d['lid'], $d['type'].' for '.$d['licensee']);
        if (!empty($d['server'])) { self::heartbeat(true); }
        return $d;
    }

    public static function deactivate()
    {
        $s = self::status();
        self::log('deactivate', isset($s['lid']) ? $s['lid'] : '', '');
        foreach (array(self::CFG, 'PULSE_LICENSE_ACTIVATED', 'PULSE_LICENSE_REMOTE', 'PULSE_LICENSE_LAST_OK') as $k) { Configuration::deleteByName($k); }
        self::$cache = null;
    }

    /** One-time local trial: signed by nobody, so it is bound to fingerprint with an HMAC and can only be started once per install. */
    public static function startTrial()
    {
        if (Configuration::get('PULSE_LICENSE_TRIAL_USED')) { throw new Exception('The trial has already been used on this installation'); }
        $exp = date('Y-m-d', strtotime('+'.self::TRIAL_DAYS.' days'));
        Configuration::updateValue('PULSE_LICENSE_TRIAL_USED', $exp);
        Configuration::updateValue('PULSE_LICENSE_TRIAL_MAC', hash_hmac('sha256', $exp, self::fingerprint()));
        self::$cache = null;
        self::log('trial', 'TRIAL', 'until '.$exp);
    }

    protected static function trialPayload()
    {
        $exp = Configuration::get('PULSE_LICENSE_TRIAL_USED');
        if (!$exp || Configuration::get('PULSE_LICENSE_TRIAL_MAC') !== hash_hmac('sha256', $exp, self::fingerprint())) { return null; }
        return array('lid' => 'TRIAL', 'licensee' => 'Trial', 'property' => Configuration::get('PS_SHOP_NAME'), 'domains' => array('*'), 'rooms' => 0, 'modules' => array('*'), 'type' => 'trial', 'issued' => date('Y-m-d'), 'expires' => $exp, 'maintenance_until' => null, 'grace_days' => 0, 'server' => null, 'features' => array());
    }

    /* ---------- online heartbeat (optional) ---------- */
    public static function heartbeat($force = false)
    {
        $s = self::status(true);
        if (empty($s['payload']['server'])) { return null; }
        $last = Configuration::get('PULSE_LICENSE_LAST_OK');
        if (!$force && $last && strtotime($last) > time() - 6 * 3600) { return json_decode(Configuration::get('PULSE_LICENSE_REMOTE'), true); }
        $body = json_encode(array('lid' => $s['payload']['lid'], 'fingerprint' => self::fingerprint(), 'domain' => self::domain(), 'rooms' => self::roomsInUse(), 'version' => Configuration::get('PULSE_FD_VERSION'), 'ts' => time()));
        $ctx = stream_context_create(array('http' => array('method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $body, 'timeout' => 8), 'ssl' => array('verify_peer' => true)));
        $raw = @file_get_contents(rtrim($s['payload']['server'], '/').'/heartbeat', false, $ctx);
        if ($raw === false) { self::log('heartbeat_fail', $s['payload']['lid'], 'unreachable'); return null; }
        $r = json_decode($raw, true);
        // server replies are signed with the same vendor key: {data: base64url json, sig: base64url}
        if (!$r || empty($r['data']) || empty($r['sig']) || openssl_verify(self::b64d($r['data']), self::b64d($r['sig']), self::publicKey(), OPENSSL_ALGO_SHA256) !== 1) { self::log('heartbeat_fail', $s['payload']['lid'], 'bad signature'); return null; }
        $data = json_decode(self::b64d($r['data']), true);
        if ($data['lid'] !== $s['payload']['lid'] || abs(time() - (int) $data['ts']) > 86400) { return null; }
        Configuration::updateValue('PULSE_LICENSE_REMOTE', json_encode($data));
        Configuration::updateValue('PULSE_LICENSE_LAST_OK', date('Y-m-d H:i:s'));
        self::$cache = null;
        return $data;
    }

    /* ---------- status ---------- */
    public static function status($raw = false)
    {
        if (self::$cache && !$raw) { return self::$cache; }
        $out = array('state' => 'none', 'message' => 'No license installed', 'payload' => null, 'days_left' => null, 'rooms_used' => self::roomsInUse());
        $key = Configuration::get(self::CFG); $d = null;
        if ($key) { try { $d = self::parse($key); } catch (Exception $e) { $out['state'] = 'invalid'; $out['message'] = $e->getMessage(); return self::$cache = $out; } }
        if (!$d) { $d = self::trialPayload(); }
        if (!$d) { return self::$cache = $out; }
        $out['payload'] = $d; $out['lid'] = $d['lid']; $out['type'] = $d['type']; $out['licensee'] = $d['licensee']; $out['rooms_cap'] = (int) $d['rooms']; $out['modules'] = $d['modules']; $out['expires'] = $d['expires']; $out['maintenance_until'] = isset($d['maintenance_until']) ? $d['maintenance_until'] : null;
        $grace = isset($d['grace_days']) ? (int) $d['grace_days'] : self::DEFAULT_GRACE;
        $today = date('Y-m-d');
        if (!self::domainMatches($d['domains'])) { $out['state'] = 'invalid'; $out['message'] = 'License is bound to '.implode(', ', $d['domains']); return self::$cache = $out; }
        // remote revocation / offline tolerance
        if (!empty($d['server'])) {
            $remote = json_decode(Configuration::get('PULSE_LICENSE_REMOTE'), true); $lastOk = Configuration::get('PULSE_LICENSE_LAST_OK');
            if ($remote && $remote['status'] === 'revoked') { $out['state'] = 'revoked'; $out['message'] = 'License revoked by vendor'.(!empty($remote['reason']) ? ': '.$remote['reason'] : ''); return self::$cache = $out; }
            if ($remote && !empty($remote['expires'])) { $d['expires'] = $remote['expires']; $out['expires'] = $remote['expires']; }
            $anchor = $lastOk ?: Configuration::get('PULSE_LICENSE_ACTIVATED');
            if ($anchor && strtotime($anchor) < time() - self::OFFLINE_TOLERANCE_DAYS * 86400) { $out['state'] = 'grace'; $out['message'] = 'License server not reached since '.$anchor.' — connect to the internet or contact support'; $out['offline'] = true; }
        }
        // expiry (subscription / trial)
        if ($d['type'] !== 'perpetual' && !empty($d['expires'])) {
            $left = (int) floor((strtotime($d['expires']) - strtotime($today)) / 86400); $out['days_left'] = $left;
            if ($left < -$grace) { $out['state'] = 'expired'; $out['message'] = ucfirst($d['type']).' expired on '.$d['expires'].' (grace period over)'; return self::$cache = $out; }
            if ($left < 0) { $out['state'] = 'grace'; $out['message'] = ucfirst($d['type']).' expired on '.$d['expires'].' — '.($grace + $left).' day(s) of grace remaining'; return self::$cache = $out; }
        }
        // room cap
        if ((int) $d['rooms'] > 0 && $out['rooms_used'] > (int) $d['rooms']) { $out['state'] = 'over_cap'; $out['message'] = $out['rooms_used'].' rooms configured, licence covers '.$d['rooms'].' — contact Animate to extend'; return self::$cache = $out; }
        if ($out['state'] === 'none') { $out['state'] = 'valid'; $out['message'] = ucfirst($d['type']).' license for '.$d['licensee'].($d['type'] === 'perpetual' ? (!empty($d['maintenance_until']) ? ' (updates until '.$d['maintenance_until'].')' : '') : ' until '.$d['expires']); }
        return self::$cache = $out;
    }

    public static function isBlocked() { return in_array(self::status()['state'], array('none', 'invalid', 'expired', 'revoked')); }

    /** Is a Pulse module covered by the license? */
    public static function entitled($module)
    {
        $s = self::status();
        if (self::isBlocked()) { return false; }
        if (in_array($module, array('pulsecore', 'pulselicense'))) { return true; }
        return in_array('*', (array) $s['modules']) || in_array($module, (array) $s['modules']);
    }

    /** Gate for Pulse back-office controllers. Returns null (ok) or a redirect URL. */
    public static function gate($controllerName)
    {
        if (strpos($controllerName, 'AdminPulse') !== 0 || in_array($controllerName, array('AdminPulseLicense', 'AdminPulseCore'))) { return null; }
        if (self::isBlocked()) { return Context::getContext()->link->getAdminLink('AdminPulseLicense').'&blocked='.$controllerName; }
        $module = Db::getInstance()->getValue('SELECT module FROM `'._DB_PREFIX_.'tab` WHERE class_name="'.pSQL($controllerName).'"');
        if ($module && !self::entitled($module)) { return Context::getContext()->link->getAdminLink('AdminPulseLicense').'&unlicensed='.$module; }
        return null;
    }

    /** Called by PulseApiController — throws when the API must refuse. */
    public static function assertApi()
    {
        if (self::isBlocked()) { throw new PrestaShopException('Pulse license '.self::status()['state'], 402); }
    }

    public static function log($event, $lid, $detail)
    {
        Db::getInstance()->insert('pulse_license_log', array('event' => pSQL($event), 'lid' => pSQL($lid), 'detail' => pSQL($detail), 'fingerprint' => self::fingerprint(), 'date_add' => date('Y-m-d H:i:s')));
    }
}
