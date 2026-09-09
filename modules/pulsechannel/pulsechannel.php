<?php
/** Pulse Channel — two-way channel manager: ARI push, OTA reservation delivery, mappings, parity. Benchmarks: eZee Centrix, RateTiger, SiteMinder. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseChannel extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulseChannel' => 'Channel Manager', 'AdminPulseChannelAri' => 'ARI Calendar', 'AdminPulseChannelMapping' => 'Channel Mappings', 'AdminPulseChannelReservations' => 'Channel Reservations', 'AdminPulseChannelLogs' => 'Channel Logs', 'AdminPulseChannelSettings' => 'Channel Settings');
    protected $hooks = array('displayBackOfficeHeader', 'moduleRoutes', 'actionValidateOrder', 'actionPulseCheckIn', 'actionPulseCheckOut', 'actionPulseStayChanged', 'actionPulseNoShow', 'actionPulseRoomStatusChange', 'actionPulseNightAuditClosed', 'actionPulseChannelReservation', 'actionPulseChannelAriPushed', 'actionPulseChannelHealth');

    public function __construct()
    {
        $this->name = 'pulsechannel'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Channel Manager'); $this->description = $this->l('Two-way OTA connectivity: availability/rate/restriction push with a dirty-cell queue, reservation delivery with a failed queue, room-type and rate-plan mappings, parity checks, allotments and per-channel health.');
        $this->confirmUninstall = $this->l('Uninstall Channel Manager? Channels, mappings, ARI cells, the push queue, delivered reservations and logs will be dropped. Bookings already created in QloApps stay.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 80;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach (array('PULSE_CH_CRON_TOKEN' => Tools::passwdGen(32), 'PULSE_CH_API_SECRET' => Tools::passwdGen(48), 'PULSE_CH_ARI_DAYS' => 180, 'PULSE_CH_MIN_LOS' => 1, 'PULSE_CH_RELEASE_DAYS' => 0,
            'PULSE_CH_BATCH_SIZE' => 200, 'PULSE_CH_MAX_ATTEMPTS' => 6, 'PULSE_CH_BACKOFF_BASE' => 2, 'PULSE_CH_ALERT_MINUTES' => 30, 'PULSE_CH_ALERT_EMAIL' => (string) Configuration::get('PS_SHOP_EMAIL'), 'PULSE_CH_ALERT_PHONE' => '',
            'PULSE_CH_OVERSELL_BUFFER' => 0, 'PULSE_CH_OVERBOOK_ACTION' => 'accept_flag', 'PULSE_CH_HTTP_TIMEOUT' => 20, 'PULSE_CH_PAYMENT_MODULE' => 'bankwire', 'PULSE_CH_TAX_PCT' => 7.5,
            'PULSE_CH_LOG_KEEP_DAYS' => 45, 'PULSE_CH_AUTO_DELIVER' => 1, 'PULSE_CH_PARITY_TOLERANCE' => 1) as $k => $v) { Configuration::updateValue($k, $v); }
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array('CRON_TOKEN', 'API_SECRET', 'ARI_DAYS', 'MIN_LOS', 'RELEASE_DAYS', 'BATCH_SIZE', 'MAX_ATTEMPTS', 'BACKOFF_BASE', 'ALERT_MINUTES', 'ALERT_EMAIL', 'ALERT_PHONE', 'OVERSELL_BUFFER', 'OVERBOOK_ACTION', 'HTTP_TIMEOUT', 'PAYMENT_MODULE', 'TAX_PCT', 'LOG_KEEP_DAYS', 'AUTO_DELIVER', 'PARITY_TOLERANCE') as $k) { Configuration::deleteByName('PULSE_CH_'.$k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    protected function runSql($f)
    {
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/'.$f.'.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) { return false; } }
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseChannel')); }
    public function hookDisplayBackOfficeHeader() { if (strpos($this->context->controller->controller_name, 'AdminPulseChannel') === 0) { $this->context->controller->addCSS($this->_path.'views/css/channel.css'); $this->context->controller->addJS($this->_path.'views/js/channel.js'); } }
    public function hookModuleRoutes() { return array('pulsechannel-api' => array('controller' => 'api', 'rule' => 'pulse/api/channel{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name))); }

    /* ---------- availability triggers: every event that moves a room in or out of stock dirties ARI ---------- */

    /** A web/desk order was placed in QloApps — the rooms it consumed must come off every channel. */
    public function hookActionValidateOrder($p)
    {
        if (empty($p['order']) || !Validate::isLoadedObject($p['order'])) { return; }
        foreach (Db::getInstance()->executeS('SELECT DISTINCT id_product, MIN(date_from) f, MAX(date_to) t FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_order='.(int) $p['order']->id.' GROUP BY id_product') as $r) {
            PulseChAri::markDirty((int) $r['id_product'], $r['f'], $r['t'], 'order');
        }
    }
    public function hookActionPulseCheckIn($p) { $this->dirtyFromBooking($p, 'check_in'); }
    public function hookActionPulseCheckOut($p) { $this->dirtyFromBooking($p, 'check_out'); }
    public function hookActionPulseStayChanged($p) { $this->dirtyFromBooking($p, 'stay_changed', isset($p['old_to']) ? $p['old_to'] : null); }
    public function hookActionPulseNoShow($p) { $this->dirtyFromBooking($p, 'no_show'); }
    /** A room going out of order (or coming back) changes physical stock for its type from today forward. */
    public function hookActionPulseRoomStatusChange($p)
    {
        if (empty($p['id_room']) || (!in_array(isset($p['to']) ? $p['to'] : '', array('out_of_order', 'out_of_service')) && !in_array(isset($p['from']) ? $p['from'] : '', array('out_of_order', 'out_of_service')))) { return; }
        $idProduct = (int) Db::getInstance()->getValue('SELECT id_product FROM `'._DB_PREFIX_.'htl_room_information` WHERE id='.(int) $p['id_room']);
        $until = Db::getInstance()->getValue('SELECT ooo_until FROM `'._DB_PREFIX_.'pulse_room_status` WHERE id_room='.(int) $p['id_room']);
        if ($idProduct) { PulseChAri::markDirty($idProduct, PulseChService::businessDate(), $until ? $until : date('Y-m-d', strtotime(PulseChService::businessDate().' +'.(int) PulseChService::windowDays().' day')), 'ooo'); }
    }
    /** After the night audit rolls the date, recompute the whole window: the first day drops off and a new far day appears. */
    public function hookActionPulseNightAuditClosed($p) { PulseChAri::rebuild(); }

    public function hookActionPulseChannelReservation($p) {}
    public function hookActionPulseChannelAriPushed($p) {}
    public function hookActionPulseChannelHealth($p) {}

    /** Shared: dirty the room type of the booking in a Pulse event payload over its stay range. */
    protected function dirtyFromBooking($p, $reason, $extraTo = null)
    {
        if (empty($p['booking']['id_product'])) { return; }
        $to = $extraTo && $extraTo > $p['booking']['date_to'] ? $extraTo : $p['booking']['date_to'];
        PulseChAri::markDirty((int) $p['booking']['id_product'], $p['booking']['date_from'], $to, $reason);
    }
}
