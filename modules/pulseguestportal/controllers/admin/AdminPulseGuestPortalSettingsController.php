<?php
/** Portal settings: branding, sections, languages, pairing and wipe policy, adult PIN and the room-control adapter. */
class AdminPulseGuestPortalSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Portal Settings');
        $adapters = array();
        foreach (PulseGpControl::adapters() as $k => $v) { $adapters[] = array('id' => $k, 'name' => $v); }
        $this->fields_options = array(
            'brand' => array('title' => $this->l('Branding'), 'icon' => 'icon-picture', 'fields' => array(
                'PULSE_GP_HOTEL_NAME' => array('title' => $this->l('Property name on the screen'), 'type' => 'text'),
                'PULSE_GP_LOGO' => array('title' => $this->l('Logo path (upload below)'), 'type' => 'text'),
                'PULSE_GP_THEME_PRIMARY' => array('title' => $this->l('Primary / background'), 'type' => 'text', 'class' => 'gp-colour'),
                'PULSE_GP_THEME_CREAM' => array('title' => $this->l('Text on primary'), 'type' => 'text', 'class' => 'gp-colour'),
                'PULSE_GP_THEME_ACCENT' => array('title' => $this->l('Accent / focus'), 'type' => 'text', 'class' => 'gp-colour'),
                'PULSE_GP_THEME_SAND' => array('title' => $this->l('Panels / banners'), 'type' => 'text', 'class' => 'gp-colour'),
                'PULSE_GP_FONT_DISPLAY' => array('title' => $this->l('Display font stack'), 'type' => 'text'),
                'PULSE_GP_FONT_BODY' => array('title' => $this->l('Body font stack'), 'type' => 'text'),
            ), 'submit' => array('title' => $this->l('Save'))),
            'behaviour' => array('title' => $this->l('Portal behaviour'), 'icon' => 'icon-cogs', 'fields' => array(
                'PULSE_GP_DEFAULT_LANG' => array('title' => $this->l('Default language'), 'type' => 'select', 'list' => $this->langList(), 'identifier' => 'id'),
                'PULSE_GP_AUTO_PAIR' => array('title' => $this->l('Auto-pair a device that boots with a known room number'), 'type' => 'bool'),
                'PULSE_GP_PAIR_CIDR' => array('title' => $this->l('Accept pairing only from these networks (CIDR, comma separated)'), 'desc' => $this->l('A TV MAC is not a secret, so pairing is restricted to the hotel VLANs. Set 0.0.0.0/0 to accept from anywhere — only do that behind a VPN.'), 'type' => 'text', 'size' => 60),
                'PULSE_GP_SESSION_TTL_MIN' => array('title' => $this->l('Session lifetime (minutes)'), 'type' => 'text'),
                'PULSE_GP_HEARTBEAT_SEC' => array('title' => $this->l('Heartbeat (seconds)'), 'type' => 'text'),
                'PULSE_GP_OFFLINE_MIN' => array('title' => $this->l('Call a screen offline after (minutes)'), 'type' => 'text'),
                'PULSE_GP_RATE_PER_MIN' => array('title' => $this->l('API calls per device per minute'), 'type' => 'text'),
                'PULSE_GP_WIPE_POLICY' => array('title' => $this->l('At check-out'), 'type' => 'select', 'list' => array(array('id' => 'wipe', 'name' => 'Wipe the screen'), array('id' => 'lock', 'name' => 'Wipe and lock until the next check-in'), array('id' => 'keep', 'name' => 'Keep (kiosk / demo)')), 'identifier' => 'id'),
                'PULSE_GP_CHECKOUT_TIME' => array('title' => $this->l('Check-out time'), 'type' => 'text'),
                'PULSE_GP_LATE_CHECKOUT_UNTIL' => array('title' => $this->l('Late check-out offered until'), 'type' => 'text'),
                'PULSE_GP_DELIVERY_MINUTES' => array('title' => $this->l('Room-service delivery allowance (minutes)'), 'type' => 'text'),
                'PULSE_GP_ORDER_OUTLET' => array('title' => $this->l('POS outlet for room service (0 = first room-service outlet)'), 'type' => 'text'),
                'PULSE_GP_FEEDBACK_ALERT_AT' => array('title' => $this->l('Raise a ticket when the rating is at or below'), 'type' => 'text'),
            ), 'submit' => array('title' => $this->l('Save'))),
            'guest' => array('title' => $this->l('Guest information'), 'icon' => 'icon-info', 'fields' => array(
                'PULSE_GP_WIFI_SSID' => array('title' => $this->l('WiFi network'), 'type' => 'text'),
                'PULSE_GP_WIFI_PASSWORD' => array('title' => $this->l('WiFi password shown on screen'), 'type' => 'text'),
                'PULSE_GP_WEATHER_CITY' => array('title' => $this->l('Weather city label'), 'type' => 'text'),
                'PULSE_GP_WEATHER_LAT' => array('title' => $this->l('Latitude'), 'type' => 'text'),
                'PULSE_GP_WEATHER_LON' => array('title' => $this->l('Longitude'), 'type' => 'text'),
                'PULSE_GP_WEATHER_URL' => array('title' => $this->l('Weather endpoint'), 'type' => 'text'),
                'PULSE_GP_WEATHER_TIMEOUT' => array('title' => $this->l('Weather timeout (seconds)'), 'type' => 'text'),
                'PULSE_GP_CAST_TTL_MIN' => array('title' => $this->l('Casting code lifetime (minutes)'), 'type' => 'text'),
                'PULSE_GP_VOD_CHARGE_CODE' => array('title' => $this->l('Charge code for paid VOD'), 'type' => 'text'),
                'PULSE_GP_VOD_TAX_PCT' => array('title' => $this->l('VOD tax %'), 'type' => 'text'),
            ), 'submit' => array('title' => $this->l('Save'))),
            'control' => array('title' => $this->l('Room controls & integrations'), 'icon' => 'icon-lightbulb', 'fields' => array(
                'PULSE_GP_CONTROL_ADAPTER' => array('title' => $this->l('Room-control adapter'), 'type' => 'select', 'list' => $adapters, 'identifier' => 'id'),
                'PULSE_GP_CONTROL_ENDPOINT' => array('title' => $this->l('Controller endpoint (HTTP adapter)'), 'type' => 'text'),
                'PULSE_GP_CONTROL_TIMEOUT' => array('title' => $this->l('Controller timeout (seconds)'), 'type' => 'text'),
                'PULSE_GP_CONTROL_INSECURE' => array('title' => $this->l('Accept a self-signed certificate on the controller'), 'type' => 'bool'),
                'PULSE_GP_LAUNDRY_API' => array('title' => $this->l('Laundry API base (only when Pulse Laundry runs on another host)'), 'type' => 'text'),
                'PULSE_GP_HTTP_TIMEOUT' => array('title' => $this->l('Outbound HTTP timeout (seconds)'), 'type' => 'text'),
            ), 'submit' => array('title' => $this->l('Save'))),
        );
    }
    protected function langList() { $out = array(); foreach (PulseGpService::LANGS as $k => $v) { $out[] = array('id' => $k, 'name' => $v); } return $out; }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array(
            'langs' => PulseGpService::langs(), 'all_langs' => PulseGpService::LANGS, 'sections' => PulseGpService::sections(), 'all_sections' => PulseGpService::SECTIONS,
            'adapter' => PulseGpService::cfg('CONTROL_ADAPTER', 'PulseGpControlSimulator'), 'adapters' => PulseGpControl::adapters(),
            'pin_set' => PulseGpService::cfg('ADULT_PIN_HASH', '') ? 1 : 0, 'logo' => PulseGpService::cfg('LOGO', ''), 'upload_base' => __PS_BASE_URI__,
            'cron_token' => Configuration::get('PULSE_GP_CRON_TOKEN'), 'api_token' => PulseGpService::cfg('API_TOKEN', ''),
            'points' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_gp_control_point`'),
            'control_log' => PulseGpControl::log(null, 20), 'test' => Tools::getValue('tested') ? json_decode(Tools::getValue('tested'), true) : null,
            'portal_url' => $this->context->link->getModuleLink('pulseguestportal', 'portal', array(), true),
            'cron_url' => Tools::getShopDomainSsl(true).__PS_BASE_URI__.'modules/pulseguestportal/cron/portal.php?token='.Configuration::get('PULSE_GP_CRON_TOKEN'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->content .= $this->context->smarty->fetch($this->getTemplatePath().'pulse_guest_portal_settings/settings.tpl');
        $this->context->smarty->assign('content', $this->content);
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveSections')) {
                $s = array_intersect((array) Tools::getValue('section'), PulseGpService::SECTIONS);
                Configuration::updateValue('PULSE_GP_SECTIONS', implode(',', $s));
                $l = array_intersect((array) Tools::getValue('lang'), array_keys(PulseGpService::LANGS));
                Configuration::updateValue('PULSE_GP_LANGS', implode(',', $l ? $l : array('en')));
                PulseGpDevice::broadcast('reload', array('reason' => 'settings'));
                $this->confirmations[] = $this->l('Sections and languages saved — screens will reload');
            }
            if (Tools::isSubmit('savePin')) {
                $pin = trim((string) Tools::getValue('adult_pin'));
                if ($pin === '') { Configuration::updateValue('PULSE_GP_ADULT_PIN_HASH', ''); $this->confirmations[] = $this->l('Adult PIN cleared — adult content stays hidden'); }
                elseif (!preg_match('/^[0-9]{4,8}$/', $pin)) { $this->errors[] = $this->l('The PIN must be 4 to 8 digits'); }
                else { Configuration::updateValue('PULSE_GP_ADULT_PIN_HASH', PulseGpService::hashPin($pin)); $this->confirmations[] = $this->l('Adult PIN saved'); }
            }
            if (Tools::isSubmit('saveControlKey')) { $k = (string) Tools::getValue('control_key'); Configuration::updateValue('PULSE_GP_CONTROL_KEY', $k === '' ? '' : PulseCoreService::encrypt($k)); $this->confirmations[] = $this->l('Controller key stored encrypted'); }
            if (Tools::isSubmit('testControl')) { $r = PulseGpControl::test(); Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&tested='.urlencode(json_encode($r))); }
            if (Tools::isSubmit('provision')) { $n = PulseGpControl::provisionAll(); $this->confirmations[] = sprintf($this->l('%d control point(s) provisioned'), $n); }
            if (Tools::isSubmit('uploadLogo')) { $p = PulseGpService::uploadImage('logofile', 'logo'); if ($p) { Configuration::updateValue('PULSE_GP_LOGO', $p); $this->confirmations[] = $this->l('Logo uploaded'); } }
            if (Tools::isSubmit('makeToken')) {
                $tok = hash('sha256', uniqid('gp', true).Tools::passwdGen(24));
                Db::getInstance()->insert('pulse_api_token', array('label' => pSQL('Guest portal '.date('Y-m-d')), 'token' => pSQL($tok), 'scopes' => 'portal', 'active' => 1, 'date_add' => date('Y-m-d H:i:s')));
                Configuration::updateValue('PULSE_GP_API_TOKEN', $tok);
                $this->confirmations[] = $this->l('API token created with scope portal');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
