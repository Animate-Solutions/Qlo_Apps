<?php
/** Pulse Inventory & Stores — hotel-wide stock: F&B, housekeeping supplies & guest amenities, minibar, front-office stock, engineering consumables. Purchasing, GRN, requisitions, counts, expiry, valuation. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseInventory extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulseInventory' => 'Inventory & Stores', 'AdminPulseInventoryPurchasing' => 'Purchasing & Requisitions', 'AdminPulseInventoryCounts' => 'Stock Counts', 'AdminPulseInventoryMinibar' => 'Minibar & Amenities', 'AdminPulseInventoryReports' => 'Inventory Reports', 'AdminPulseInventorySettings' => 'Items, Stores & Suppliers');
    protected $hooks = array('displayBackOfficeHeader', 'moduleRoutes', 'actionPulsePosStockMove', 'actionPulseMaintenancePartMove', 'actionPulseNightAuditClosed', 'actionPulseBeforeCheckOut', 'actionPulseInvRequest', 'actionPulseInvReceived', 'actionPulseMinibarPost');

    public function __construct()
    {
        $this->name = 'pulseinventory'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Inventory & Stores'); $this->description = $this->l('Multi-store hotel inventory: purchasing (PR → PO → GRN → invoice), requisitions and transfers, weighted-average valuation, batches and expiry, stock counts, minibar and guest amenities, reorder suggestions, integrated with POS recipes, Maintenance parts and the expense ledger.');
        $this->confirmUninstall = $this->l('Uninstall Inventory? All stock, purchasing and count data will be dropped.');
    }
    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/install.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) { return false; } }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 70;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach (array('PULSE_INV_PO_APPROVAL_LIMIT' => 200000, 'PULSE_INV_AUTO_AMENITY' => 1, 'PULSE_INV_EXPIRY_DAYS' => 30, 'PULSE_INV_CRON_TOKEN' => Tools::passwdGen(32), 'PULSE_INV_MINIBAR_CHECKOUT_TRACE' => 1) as $k => $v) { Configuration::updateValue($k, $v); }
        PulseInvService::importLinked();
        return true;
    }
    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array('PO_APPROVAL_LIMIT', 'AUTO_AMENITY', 'EXPIRY_DAYS', 'CRON_TOKEN', 'MINIBAR_CHECKOUT_TRACE') as $k) { Configuration::deleteByName('PULSE_INV_'.$k); }
        foreach (array_filter(array_map('trim', explode("\n", Tools::file_get_contents(dirname(__FILE__).'/sql/uninstall.sql')))) as $q) { Db::getInstance()->execute(str_replace('PREFIX_', _DB_PREFIX_, $q)); }
        return parent::uninstall();
    }
    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseInventory')); }
    public function hookDisplayBackOfficeHeader() { if (strpos($this->context->controller->controller_name, 'AdminPulseInventory') === 0) { $this->context->controller->addCSS($this->_path.'views/css/inventory.css'); } }
    public function hookModuleRoutes() { return array('pulseinventory-api' => array('controller' => 'api', 'rule' => 'pulse/api/inventory{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name))); }
    public function hookActionPulsePosStockMove($p) { PulseInvService::onPosStockMove($p); }
    public function hookActionPulseMaintenancePartMove($p) { PulseInvService::onPartMove($p); }
    /** Night audit: standard amenity consumption for the closed date; expiry + reorder alerts as traces. */
    public function hookActionPulseNightAuditClosed($p)
    {
        $bd = isset($p['business_date']) ? $p['business_date'] : date('Y-m-d', strtotime('-1 day'));
        if (Configuration::get('PULSE_INV_AUTO_AMENITY')) { PulseInvMinibar::postDailyAmenities($bd); }
        if (class_exists('PulseTrace')) { $exp = PulseInvService::expiring((int) Configuration::get('PULSE_INV_EXPIRY_DAYS')); if ($exp) { PulseTrace::add('alert', count($exp).' stock batch(es) expiring within '.(int) Configuration::get('PULSE_INV_EXPIRY_DAYS').' days — see Inventory', date('Y-m-d H:i:s'), null, null, null, 'stores'); } $re = PulseInvService::reorderSuggestions(); if ($re) { PulseTrace::add('alert', count($re).' item(s) below reorder level — see Purchasing', date('Y-m-d H:i:s'), null, null, null, 'stores'); } }
    }
    /** Check-out: remind the desk to confirm minibar if nothing was posted today for that room. */
    public function hookActionPulseBeforeCheckOut($p)
    {
        if (!Configuration::get('PULSE_INV_MINIBAR_CHECKOUT_TRACE') || empty($p['id_room']) || !class_exists('PulseTrace')) { return; }
        if (!Db::getInstance()->getValue('SELECT id_pulse_inv_minibar_post FROM `'._DB_PREFIX_.'pulse_inv_minibar_post` WHERE id_room='.(int) $p['id_room'].' AND business_date="'.pSQL(PulseInvService::bd()).'"') && Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_inv_minibar_item`')) { PulseTrace::add('alert', 'Minibar not checked today before check-out — room '.(int) $p['id_room'], date('Y-m-d H:i:s'), (int) $p['booking']['id'], (int) $p['id_room'], null, 'housekeeping'); }
    }
    public function hookActionPulseInvRequest($p) {} public function hookActionPulseInvReceived($p) {} public function hookActionPulseMinibarPost($p) {}
}
