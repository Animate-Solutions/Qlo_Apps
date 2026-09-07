<?php
/** Pulse POS — restaurant/bar/room-service point of sale. Benchmarks: eZee Burrp / Optimus, Oracle MICROS Simphony. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulsePos extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulsePos' => 'F&B POS', 'AdminPulsePosMenu' => 'POS Menu', 'AdminPulsePosInventory' => 'F&B Inventory', 'AdminPulsePosReports' => 'POS Reports', 'AdminPulsePosSettings' => 'POS Settings');
    protected $hooks = array('displayBackOfficeHeader', 'moduleRoutes', 'actionPulseBeforeCheckOut', 'actionPulseNightAuditClosed', 'actionPulsePosBillSettled', 'actionPulsePosKotFired', 'actionPulsePosItemReady', 'actionPulsePosRoomCharge', 'actionPulsePosStockMove');

    public function __construct()
    {
        $this->name = 'pulsepos'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse POS'); $this->description = $this->l('Touch point of sale for restaurant, bar and room service: tables, menu with modifiers and combos, KDS and kitchen printing, split/merge/transfer, discounts and comps with authorisation, split tenders, room charge, cashier X/Z, recipes and stock, full reporting.');
        $this->confirmUninstall = $this->l('Uninstall POS? All checks, menu, stock and session data will be dropped.');
    }
    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/install.sql'));
        $sql = preg_replace('/^\s*--.*(?:\r\n|\r|\n|$)/m', '', $sql);
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (!Db::getInstance()->execute($q)) { return false; } }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 20;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach (array('PULSE_POS_AUTO_LOGOUT_MIN' => 3, 'PULSE_POS_CASH_ROUNDING' => 0, 'PULSE_POS_KDS_LATE_MIN' => 15, 'PULSE_POS_TIP_ON_CARD' => 1, 'PULSE_POS_ROOM_GUEST_CHECK' => 1, 'PULSE_POS_DEFAULT_TENDER' => 'cash', 'PULSE_POS_DRAWER_HOST' => '', 'PULSE_POS_RECEIPT_HOST' => '', 'PULSE_POS_BLIND_CLOSE' => 1) as $k => $v) { Configuration::updateValue($k, $v); }
        // give the first back-office employee manager PIN 1234 so the POS can be opened straight after install
        $emp = (int) Db::getInstance()->getValue('SELECT MIN(id_employee) FROM `'._DB_PREFIX_.'employee` WHERE active=1');
        if ($emp) { Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_pos_staff` (id_employee, pin_hash, role, can_void_sent, can_discount, max_discount_pct, can_reopen, can_comp, can_settle) VALUES ('.$emp.', "'.pSQL(PulsePosService::setPin($emp, '1234')).'", "manager", 1, 1, 100, 1, 1, 1)'); }
        return true;
    }
    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array_filter(array_map('trim', explode("\n", Tools::file_get_contents(dirname(__FILE__).'/sql/uninstall.sql')))) as $q) { Db::getInstance()->execute(str_replace('PREFIX_', _DB_PREFIX_, $q)); }
        return parent::uninstall();
    }
    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulsePos')); }
    public function hookDisplayBackOfficeHeader() { if (strpos($this->context->controller->controller_name, 'AdminPulsePos') === 0) { $this->context->controller->addCSS($this->_path.'views/css/admin.css'); } }
    public function hookModuleRoutes()
    {
        return array(
            'pulsepos-app' => array('controller' => 'app', 'rule' => 'pulse/pos', 'keywords' => array(), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsepos-kds' => array('controller' => 'kds', 'rule' => 'pulse/kds', 'keywords' => array(), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsepos-api' => array('controller' => 'api', 'rule' => 'pulse/api/pos{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name)));
    }
    /** Guest checking out with an open room-service/bar tab → settle it to the room automatically and alert the desk. */
    public function hookActionPulseBeforeCheckOut($p)
    {
        if (empty($p['booking']['id'])) { return; }
        foreach (Db::getInstance()->executeS('SELECT id_pulse_pos_check, check_no, total, paid FROM `'._DB_PREFIX_.'pulse_pos_check` WHERE id_htl_booking='.(int) $p['booking']['id'].' AND status IN ("open","printed","reopened")') as $c) {
            try { if ($c['total'] - $c['paid'] > 0) { $mgr = (int) Db::getInstance()->getValue('SELECT id_employee FROM `'._DB_PREFIX_.'pulse_pos_staff` WHERE role="manager" AND active=1 LIMIT 1'); PulsePosPayment::pay($c['id_pulse_pos_check'], 'room', $c['total'] - $c['paid'], $mgr ?: 1, array('booking' => array('id_htl_booking' => $p['booking']['id'], 'id_customer' => $p['booking']['id_customer'], 'id_room' => $p['booking']['id_room'], 'room_num' => $p['booking']['room_num'], 'guest' => $p['booking']['guest']))); } } catch (Exception $e) { if (class_exists('PulseTrace')) { PulseTrace::add('alert', 'Open POS check '.$c['check_no'].' could not be charged at check-out: '.$e->getMessage(), date('Y-m-d H:i:s'), (int) $p['booking']['id'], (int) $p['id_room'], null, 'fnb'); } }
        }
    }
    /** End of day: mark dirty tables free, close abandoned checks report, expire count-downs. */
    public function hookActionPulseNightAuditClosed($p) { Db::getInstance()->update('pulse_pos_table', array('status' => 'free'), 'status="dirty"'); $n = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pos_check` WHERE status IN ("open","printed","reopened")'); if ($n && class_exists('PulseTrace')) { PulseTrace::add('alert', $n.' POS check(s) still open after night audit', date('Y-m-d H:i:s'), null, null, null, 'fnb'); } }
    public function hookActionPulsePosBillSettled($p) {} public function hookActionPulsePosKotFired($p) {} public function hookActionPulsePosItemReady($p) {} public function hookActionPulsePosRoomCharge($p) {} public function hookActionPulsePosStockMove($p) {}
}
