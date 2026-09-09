<?php
/** Pulse Guest Portal — in-room TV / tablet guest experience. Benchmarks: Hoteza TV, SuitePad, Nevotek, Samsung LYNK REACH. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseGuestPortal extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulseGuestPortal' => 'Guest Portal', 'AdminPulseGuestPortalDevices' => 'Portal Devices', 'AdminPulseGuestPortalContent' => 'Portal Content',
        'AdminPulseGuestPortalChannels' => 'Channels & VOD', 'AdminPulseGuestPortalMessages' => 'Guest Messages', 'AdminPulseGuestPortalSettings' => 'Portal Settings');
    protected $hooks = array('displayBackOfficeHeader', 'moduleRoutes', 'actionPulseCheckIn', 'actionPulseCheckOut', 'actionPulseRoomMove', 'actionPulsePosItemReady',
        'actionPulsePosBillSettled', 'actionPulseFolioPost', 'actionPulseNightAuditClosed', 'actionPulsePortalDevicePaired', 'actionPulsePortalRequest',
        'actionPulsePortalOrder', 'actionPulsePortalMessage', 'actionPulsePortalFeedback', 'actionPulsePortalVodPlay');

    public function __construct()
    {
        $this->name = 'pulseguestportal'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Guest Portal'); $this->description = $this->l('In-room TV and tablet portal: device registry and pairing, welcome screen, live folio, room-service ordering into POS, service requests, two-way messaging, hotel directory, IP-multicast channel guide, VOD, casting, room controls and express check-out with feedback.');
        $this->confirmUninstall = $this->l('Uninstall Guest Portal? Devices, sessions, portal content, channels, the VOD catalogue, guest messages and feedback will be dropped.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 100;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach (array(
            'PULSE_GP_HOTEL_NAME' => 'The Carvington Hotel & Suites', 'PULSE_GP_LOGO' => '', 'PULSE_GP_THEME_PRIMARY' => '#00424B', 'PULSE_GP_THEME_CREAM' => '#F1E9E1',
            'PULSE_GP_THEME_ACCENT' => '#C9A27E', 'PULSE_GP_THEME_SAND' => '#E2D6C8', 'PULSE_GP_FONT_DISPLAY' => 'Georgia, "Times New Roman", serif', 'PULSE_GP_FONT_BODY' => 'Inter, "Helvetica Neue", Arial, sans-serif',
            'PULSE_GP_LANGS' => 'en,fr,pcm,ar', 'PULSE_GP_DEFAULT_LANG' => 'en', 'PULSE_GP_SECTIONS' => implode(',', PulseGpService::SECTIONS),
            'PULSE_GP_AUTO_PAIR' => 0, 'PULSE_GP_PAIR_CIDR' => '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,169.254.0.0/16,127.0.0.0/8', 'PULSE_GP_SESSION_TTL_MIN' => 120, 'PULSE_GP_HEARTBEAT_SEC' => 60, 'PULSE_GP_OFFLINE_MIN' => 5, 'PULSE_GP_RATE_PER_MIN' => 120,
            'PULSE_GP_WIPE_POLICY' => 'wipe', 'PULSE_GP_CHECKOUT_TIME' => '12:00', 'PULSE_GP_CHECKOUT_GRACE_HRS' => 4, 'PULSE_GP_LATE_CHECKOUT_UNTIL' => '14:00',
            'PULSE_GP_ADULT_PIN_HASH' => '', 'PULSE_GP_CONTROL_ADAPTER' => 'PulseGpControlSimulator', 'PULSE_GP_CONTROL_ENDPOINT' => '', 'PULSE_GP_CONTROL_KEY' => '',
            'PULSE_GP_CONTROL_TIMEOUT' => 4, 'PULSE_GP_CONTROL_INSECURE' => 0, 'PULSE_GP_ORDER_OUTLET' => 0, 'PULSE_GP_DELIVERY_MINUTES' => 10,
            'PULSE_GP_WIFI_SSID' => 'Carvington-Guest', 'PULSE_GP_WIFI_PASSWORD' => '', 'PULSE_GP_WEATHER_CITY' => 'Port Harcourt', 'PULSE_GP_WEATHER_LAT' => '4.8156',
            'PULSE_GP_WEATHER_LON' => '7.0498', 'PULSE_GP_WEATHER_URL' => 'https://api.open-meteo.com/v1/forecast', 'PULSE_GP_WEATHER_TIMEOUT' => 4,
            'PULSE_GP_CAST_TTL_MIN' => 10, 'PULSE_GP_VOD_CHARGE_CODE' => 'VOD', 'PULSE_GP_VOD_TAX_PCT' => 7.5, 'PULSE_GP_FEEDBACK_ALERT_AT' => 3,
            'PULSE_GP_HTTP_TIMEOUT' => 6, 'PULSE_GP_LAUNDRY_API' => '', 'PULSE_GP_API_TOKEN' => '', 'PULSE_GP_SECRET' => Tools::passwdGen(48), 'PULSE_GP_CRON_TOKEN' => Tools::passwdGen(32),
        ) as $k => $v) { Configuration::updateValue($k, $v); }
        // paid VOD needs a charge code, but only Front Desk owns that table — the portal installs standalone
        if (Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_charge_code"')) {
            Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_charge_code` (`code`,`name`,`department`,`default_price`,`tax_rate`,`is_payment`) VALUES ("VOD","In-room Movie","misc",0,7.5,0)');
        }
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array('HOTEL_NAME', 'LOGO', 'THEME_PRIMARY', 'THEME_CREAM', 'THEME_ACCENT', 'THEME_SAND', 'FONT_DISPLAY', 'FONT_BODY', 'LANGS', 'DEFAULT_LANG', 'SECTIONS',
            'AUTO_PAIR', 'PAIR_CIDR', 'SESSION_TTL_MIN', 'HEARTBEAT_SEC', 'OFFLINE_MIN', 'RATE_PER_MIN', 'WIPE_POLICY', 'CHECKOUT_TIME', 'CHECKOUT_GRACE_HRS', 'LATE_CHECKOUT_UNTIL',
            'ADULT_PIN_HASH', 'CONTROL_ADAPTER', 'CONTROL_ENDPOINT', 'CONTROL_KEY', 'CONTROL_TIMEOUT', 'CONTROL_INSECURE', 'ORDER_OUTLET', 'DELIVERY_MINUTES',
            'WIFI_SSID', 'WIFI_PASSWORD', 'WEATHER_CITY', 'WEATHER_LAT', 'WEATHER_LON', 'WEATHER_URL', 'WEATHER_TIMEOUT', 'CAST_TTL_MIN', 'VOD_CHARGE_CODE', 'VOD_TAX_PCT',
            'FEEDBACK_ALERT_AT', 'HTTP_TIMEOUT', 'LAUNDRY_API', 'API_TOKEN', 'SECRET', 'CRON_TOKEN') as $k) { Configuration::deleteByName('PULSE_GP_'.$k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    protected function runSql($f)
    {
        $path = dirname(__FILE__).'/sql/'.$f.'.sql';
        if (!file_exists($path)) { return true; }
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents($path));
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) {
            if ($q !== '' && !Db::getInstance()->execute($q)) { return false; }
        }
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseGuestPortal')); }
    public function hookDisplayBackOfficeHeader()
    {
        if (strpos($this->context->controller->controller_name, 'AdminPulseGuestPortal') === 0) {
            $this->context->controller->addCSS($this->_path.'views/css/admin.css');
            $this->context->controller->addJS($this->_path.'views/js/admin.js');
        }
    }
    /** /pulse/tv is what the Samsung URL Launcher points at; /pulse/api/portal/* is the JSON API. */
    public function hookModuleRoutes()
    {
        return array(
            'pulseguestportal-app' => array('controller' => 'portal', 'rule' => 'pulse/tv', 'keywords' => array(), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulseguestportal-api' => array('controller' => 'api', 'rule' => 'pulse/api/portal{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name)),
        );
    }

    /** Check-in: arm the room's screens with the new stay and drop a welcome message in the guest's inbox. */
    public function hookActionPulseCheckIn($p)
    {
        if (empty($p['id_room'])) { return; }
        PulseGpDevice::armRoom((int) $p['id_room'], isset($p['booking']) ? (array) $p['booking'] : array());
        if (!empty($p['booking']['id']) && !PulseGpService::cfg('NO_WELCOME_MESSAGE', 0)) {
            try { PulseGpMessaging::fromDesk((int) $p['booking']['id'], (int) $p['id_room'], 'Welcome to '.PulseGpService::cfg('HOTEL_NAME', Configuration::get('PS_SHOP_NAME')).'. Anything you need, message us here — the front desk answers day and night.', 0); }
            catch (Exception $e) { PulseGpService::audit('checkin_message_failed', array('error' => $e->getMessage())); }
        }
    }

    /** Check-out: revoke the session, wipe the screen and close anything still open on the portal. */
    public function hookActionPulseCheckOut($p)
    {
        if (empty($p['id_room'])) { return; }
        if (!empty($p['booking']['id'])) {
            PulseGpSession::revokeForBooking((int) $p['booking']['id'], empty($p['room_move']) ? 'checkout' : 'room_move');
            Db::getInstance()->update('pulse_gp_request', array('status' => 'done', 'date_upd' => date('Y-m-d H:i:s')), 'id_htl_booking='.(int) $p['booking']['id'].' AND type IN ("dnd_on","mur") AND status IN ("new","ack","in_progress")');
        }
        PulseGpDevice::wipeRoom((int) $p['id_room'], empty($p['room_move']) ? 'checkout' : 'room_move');
    }

    /** Room move: the old screen forgets the guest, the new one is armed. */
    public function hookActionPulseRoomMove($p)
    {
        if (!empty($p['from_room'])) { PulseGpSession::revokeForRoom((int) $p['from_room'], 'room_move'); PulseGpDevice::wipeRoom((int) $p['from_room'], 'room_move'); }
        if (!empty($p['to_room'])) { PulseGpDevice::armRoom((int) $p['to_room'], isset($p['booking']) ? (array) $p['booking'] : array()); }
    }

    /** Kitchen marked an item ready — tell the room's TV if the check came from the portal. */
    public function hookActionPulsePosItemReady($p)
    {
        $idCheck = 0;
        if (!empty($p['id_check'])) { $idCheck = (int) $p['id_check']; }
        elseif (!empty($p['line']['id_pulse_pos_check'])) { $idCheck = (int) $p['line']['id_pulse_pos_check']; }
        elseif (!empty($p['id_line'])) { $idCheck = (int) Db::getInstance()->getValue('SELECT id_pulse_pos_check FROM `'._DB_PREFIX_.'pulse_pos_check_line` WHERE id_pulse_pos_check_line='.(int) $p['id_line']); }
        if ($idCheck) { PulseGpDining::markReady($idCheck); }
    }
    /** A settled room-service check closes the guest's order tracker. */
    public function hookActionPulsePosBillSettled($p) { if (!empty($p['id_check'])) { PulseGpDining::markDelivered((int) $p['id_check']); } }

    /** A new folio line refreshes the balance badge on the screen in that room. */
    public function hookActionPulseFolioPost($p)
    {
        if (empty($p['folio']) || !is_object($p['folio']) || empty($p['folio']->id_room)) { return; }
        foreach (PulseGpDevice::byRoom((int) $p['folio']->id_room) as $d) { PulseGpDevice::command((int) $d['id_pulse_gp_device'], 'folio_refresh', array('code' => isset($p['code']) ? $p['code'] : '')); }
    }

    /** Night audit: expire stale sessions, casting codes and acked commands so the tables stay small. */
    public function hookActionPulseNightAuditClosed($p)
    {
        PulseGpSession::purge(7);
        PulseGpEntertainment::castExpire();
        Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_gp_command` WHERE status IN ("acked","expired") AND date_add<DATE_SUB(NOW(), INTERVAL 3 DAY)');
        Db::getInstance()->update('pulse_gp_command', array('status' => 'expired'), 'status IN ("queued","sent") AND date_add<DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }

    public function hookActionPulsePortalDevicePaired($p) {}
    public function hookActionPulsePortalRequest($p) {}
    public function hookActionPulsePortalOrder($p) {}
    public function hookActionPulsePortalMessage($p) {}
    public function hookActionPulsePortalFeedback($p) {}
    public function hookActionPulsePortalVodPlay($p) {}
}
