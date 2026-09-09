<?php
/**
 * Guest portal shared plumbing: settings, branding, languages, rate limiting, weather, the home payload
 * and the small integration guards. Everything cross-module is optional — the portal runs on its own.
 */
class PulseGpService
{
    const LANGS = array('en' => 'English', 'fr' => 'Français', 'pcm' => 'Plain English', 'ar' => 'العربية');
    const SECTIONS = array('welcome', 'folio', 'dining', 'requests', 'messages', 'directory', 'tv', 'vod', 'radio', 'games', 'cast', 'controls', 'checkout');

    public static function cfg($name, $default = null) { $v = Configuration::get('PULSE_GP_'.$name); return ($v === false || $v === null || $v === '') ? $default : $v; }
    public static function bd() { return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    public static function emp() { $c = Context::getContext(); return isset($c->employee) && $c->employee ? (int) $c->employee->id : 0; }
    public static function fd() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFolio'); }
    public static function pos() { return Module::isEnabled('pulsepos') && class_exists('PulsePosService'); }
    public static function laundry() { return Module::isEnabled('pulselaundry') && class_exists('PulseLaundryService'); }
    public static function audit($event, $payload = null, $entity = null, $idEntity = null) { if (class_exists('PulseCoreService')) { PulseCoreService::audit('pulseguestportal', $event, $payload, $entity, $idEntity); } }
    public static function event($name, array $p = array()) { if (class_exists('PulseCoreService')) { PulseCoreService::event($name, $p); } }
    public static function nextNo($prefix) { $n = (int) PulseCoreService::setting('pulseguestportal', 'seq_'.$prefix) + 1; PulseCoreService::setting('pulseguestportal', 'seq_'.$prefix, $n); return $prefix.date('ymd').str_pad($n % 10000, 4, '0', STR_PAD_LEFT); }

    /* ---------- languages & branding ---------- */
    public static function langs() { $l = array_filter(array_map('trim', explode(',', (string) self::cfg('LANGS', 'en,fr,pcm,ar')))); $out = array(); foreach ($l as $c) { if (isset(self::LANGS[$c])) { $out[$c] = self::LANGS[$c]; } } return $out ? $out : array('en' => 'English'); }
    public static function lang($code) { $l = self::langs(); return isset($l[$code]) ? $code : self::defaultLang(); }
    public static function defaultLang() { $d = self::cfg('DEFAULT_LANG', 'en'); $l = self::langs(); return isset($l[$d]) ? $d : key($l); }
    public static function rtl($code) { return $code === 'ar'; }
    public static function sections() { $s = array_filter(array_map('trim', explode(',', (string) self::cfg('SECTIONS', implode(',', self::SECTIONS))))); return array_values(array_intersect($s, self::SECTIONS)); }
    public static function sectionOn($s) { return in_array($s, self::sections()); }

    /**
     * Theme values are settings, never hard-coded — the Carvington palette is only the shipped default.
     * The values land inside a <style> block on the TV page, so a colour must look like a colour and a font
     * stack may not carry markup: a typo in the back office can spoil the look, never the page.
     */
    public static function theme()
    {
        return array(
            'hotel' => self::cfg('HOTEL_NAME', Configuration::get('PS_SHOP_NAME')), 'logo' => self::cfg('LOGO', ''),
            'primary' => self::colour(self::cfg('THEME_PRIMARY', '#00424B'), '#00424B'), 'cream' => self::colour(self::cfg('THEME_CREAM', '#F1E9E1'), '#F1E9E1'),
            'accent' => self::colour(self::cfg('THEME_ACCENT', '#C9A27E'), '#C9A27E'), 'sand' => self::colour(self::cfg('THEME_SAND', '#E2D6C8'), '#E2D6C8'),
            'font_display' => self::fontStack(self::cfg('FONT_DISPLAY', 'Georgia, "Times New Roman", serif')), 'font_body' => self::fontStack(self::cfg('FONT_BODY', 'Inter, "Helvetica Neue", Arial, sans-serif')),
            'currency' => Context::getContext()->currency ? Context::getContext()->currency->sign : '₦',
        );
    }
    public static function colour($v, $fallback) { $v = trim((string) $v); return preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9,.\s%]+\)|[a-zA-Z]{3,20})$/', $v) ? $v : $fallback; }
    public static function fontStack($v) { $v = trim(preg_replace('/[<>{};]/', '', (string) $v)); return $v === '' ? 'Arial, sans-serif' : Tools::substr($v, 0, 160); }

    /* ---------- adult PIN ---------- */
    public static function hashPin($pin) { return hash('sha256', trim($pin).'|'._COOKIE_KEY_); }
    public static function checkPin($pin) { $h = self::cfg('ADULT_PIN_HASH', ''); return $h ? hash_equals((string) $h, self::hashPin($pin)) : false; }

    /* ---------- rate limiting (per device, per minute) ---------- */
    public static function rateHit($bucket, $limit = null)
    {
        $limit = (int) ($limit === null ? self::cfg('RATE_PER_MIN', 120) : $limit);
        if ($limit <= 0) { return true; }
        $w = (int) floor(time() / 60);
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_gp_rate` (`bucket`,`window_start`,`hits`) VALUES ("'.pSQL(Tools::substr($bucket, 0, 64)).'",'.$w.',1) ON DUPLICATE KEY UPDATE `hits`=`hits`+1');
        $hits = (int) Db::getInstance()->getValue('SELECT hits FROM `'._DB_PREFIX_.'pulse_gp_rate` WHERE bucket="'.pSQL(Tools::substr($bucket, 0, 64)).'" AND window_start='.$w);
        if ($hits > $limit) { throw new PrestaShopException('Too many requests', 429); }
        if (($w % 30) === 0) { Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_gp_rate` WHERE window_start<'.($w - 60)); }
        return true;
    }

    /* ---------- weather (open-meteo, no key, cached; the TV must never wait on the internet) ---------- */
    public static function weather()
    {
        $cache = json_decode((string) PulseCoreService::setting('pulseguestportal', 'weather'), true);
        if (is_array($cache) && isset($cache['t']) && (time() - (int) $cache['t']) < 1800) { return $cache; }
        $lat = (float) self::cfg('WEATHER_LAT', 4.8156); $lon = (float) self::cfg('WEATHER_LON', 7.0498);
        $url = self::cfg('WEATHER_URL', 'https://api.open-meteo.com/v1/forecast').'?latitude='.$lat.'&longitude='.$lon.'&current=temperature_2m,weather_code&timezone=Africa%2FLagos';
        $out = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => (int) self::cfg('WEATHER_TIMEOUT', 4), CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => 0));
            $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
            if ($err === '' && $res) { $j = json_decode($res, true); if (isset($j['current']['temperature_2m'])) { $out = array('t' => time(), 'temp' => round((float) $j['current']['temperature_2m'], 1), 'code' => (int) $j['current']['weather_code'], 'text' => self::weatherText((int) $j['current']['weather_code']), 'city' => self::cfg('WEATHER_CITY', 'Port Harcourt')); } }
        }
        if (!$out) { $out = is_array($cache) ? array_merge($cache, array('stale' => 1)) : array('t' => time(), 'temp' => null, 'code' => 0, 'text' => '', 'city' => self::cfg('WEATHER_CITY', 'Port Harcourt'), 'stale' => 1); }
        else { PulseCoreService::setting('pulseguestportal', 'weather', json_encode($out)); }
        return $out;
    }
    /** WMO weather codes, condensed to the handful a hotel screen needs. */
    public static function weatherText($code)
    {
        if ($code === 0) { return 'Clear'; }
        if ($code <= 3) { return 'Partly cloudy'; }
        if ($code <= 48) { return 'Misty'; }
        if ($code <= 67) { return 'Rain'; }
        if ($code <= 77) { return 'Showers'; }
        if ($code <= 82) { return 'Heavy showers'; }
        return 'Thunderstorms';
    }

    /* ---------- images ---------- */
    /** Store an uploaded image under /upload/pulseguestportal/ and return its shop-relative path. */
    public static function uploadImage($field, $prefix = 'img')
    {
        if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) { return ''; }
        $size = (int) $_FILES[$field]['size'];
        if ($size <= 0 || $size > 4194304) { throw new PrestaShopException('Image must be 4 MB or smaller'); }
        $info = @getimagesize($_FILES[$field]['tmp_name']);
        $ext = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp');
        if (!$info || !isset($ext[$info[2]])) { throw new PrestaShopException('Only JPG, PNG, GIF or WebP images are accepted'); }
        $dir = self::uploadDir();
        $name = preg_replace('/[^a-z0-9_]/i', '', $prefix).'_'.date('ymdHis').Tools::substr(md5(uniqid('', true)), 0, 6).'.'.$ext[$info[2]];
        if (!@move_uploaded_file($_FILES[$field]['tmp_name'], $dir.$name)) { throw new PrestaShopException('Could not write the image — check permissions on /upload/pulseguestportal/'); }
        @chmod($dir.$name, 0644);
        return 'upload/pulseguestportal/'.$name;
    }
    public static function uploadDir()
    {
        $dir = _PS_ROOT_DIR_.'/upload/pulseguestportal/';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); @file_put_contents($dir.'index.php', '<?php header("Location: ../"); exit;'); }
        return $dir;
    }

    /* ---------- home payload ---------- */
    /**
     * Everything the welcome screen needs in one call. $session may be null (unpaired room or vacant room):
     * in that case nothing personal is included — no name, no folio, no messages.
     */
    public static function home(array $device, $session = null, $lang = null)
    {
        $lang = $lang ? self::lang($lang) : (isset($device['locale']) ? self::lang($device['locale']) : self::defaultLang());
        $inHouse = $session && !empty($session['id_htl_booking']);
        $out = array(
            'device' => array('id' => (int) $device['id_pulse_gp_device'], 'room_num' => $device['room_num'], 'type' => $device['type'], 'status' => $device['status']),
            'theme' => self::theme(), 'lang' => $lang, 'rtl' => self::rtl($lang), 'languages' => self::langs(), 'sections' => self::sections(),
            'business_date' => self::bd(), 'server_time' => date('c'), 'weather' => self::weather(), 'in_house' => $inHouse ? 1 : 0,
            'wifi' => array('ssid' => self::cfg('WIFI_SSID', ''), 'password' => self::cfg('WIFI_PASSWORD', '')),
            'promos' => PulseGpContent::promos('home', $lang, $inHouse ? (int) $session['id_htl_booking'] : null),
            'guest' => null, 'stay' => null, 'folio' => null, 'unread' => 0, 'orders' => array(), 'requests' => array(),
        );
        if (!$inHouse) { return $out; }
        $b = self::booking((int) $session['id_htl_booking']);
        if (!$b) { return $out; }
        $out['guest'] = array('name' => $b['guest'], 'first' => $b['firstname'], 'vip' => isset($b['vip_level']) ? (int) $b['vip_level'] : 0);
        $out['stay'] = array('room_num' => $b['room_num'], 'room_type' => $b['room_type_name'], 'date_from' => $b['date_from'], 'date_to' => $b['date_to'], 'nights' => (int) $b['nights'],
            'departs_today' => $b['date_to'] === self::bd() ? 1 : 0, 'checkout_time' => self::cfg('CHECKOUT_TIME', '12:00'));
        $out['folio'] = PulseGpService::folioSummary((int) $session['id_htl_booking']);
        $out['unread'] = PulseGpMessaging::unreadForGuest((int) $session['id_htl_booking']);
        $out['orders'] = PulseGpDining::roomOrders((int) $session['id_room'], 24, (int) $session['id_htl_booking']);
        $out['requests'] = PulseGpRequest::recent((int) $session['id_room'], (int) $session['id_htl_booking']);
        return $out;
    }

    /** Booking row for a stay, with the guest's first name split out for the greeting. */
    public static function booking($idBooking)
    {
        $b = Db::getInstance()->getRow('SELECT b.id, b.id_order, b.id_customer, b.id_room, b.room_type_name, b.date_from, b.date_to, b.total_price_tax_incl, b.id_product,
                DATEDIFF(b.date_to,b.date_from) nights, r.room_num, r.floor, c.firstname, c.lastname, c.email, CONCAT(c.firstname," ",c.lastname) guest
            FROM `'._DB_PREFIX_.'htl_booking_detail` b
            INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer
            LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room
            WHERE b.id='.(int) $idBooking);
        if ($b && Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_guest_profile"')) { $b['vip_level'] = (int) Db::getInstance()->getValue('SELECT vip_level FROM `'._DB_PREFIX_.'pulse_guest_profile` WHERE id_customer='.(int) $b['id_customer']); }
        return $b;
    }

    /** Live folio: balance plus the lines the guest is allowed to see (payments and charges, never internal notes). */
    public static function folioSummary($idBooking, $withLines = true)
    {
        if (!self::fd() || !$idBooking) { return array('available' => 0, 'balance' => 0, 'charges' => 0, 'payments' => 0, 'lines' => array()); }
        $f = PulseFolio::openForBooking((int) $idBooking);
        if (!$f) { return array('available' => 0, 'balance' => 0, 'charges' => 0, 'payments' => 0, 'lines' => array()); }
        $out = array('available' => 1, 'folio_no' => $f->folio_no, 'balance' => round((float) $f->balance, 2), 'charges' => round((float) $f->total_charges, 2), 'payments' => round((float) $f->total_payments, 2), 'lines' => array());
        if ($withLines) {
            foreach ($f->lines() as $l) {
                $out['lines'][] = array('date' => $l['date_add'], 'business_date' => $l['business_date'], 'description' => $l['description'], 'department' => $l['department'],
                    'qty' => (float) $l['qty'], 'amount' => round((float) $l['amount_tax_incl'], 2), 'is_payment' => (int) $l['is_payment']);
            }
        }
        return $out;
    }
}
