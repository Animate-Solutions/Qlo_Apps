<?php
/** Pulse Laundry — guest laundry & valet, house linen, vendors, claims. Benchmarks: eZee Laundry Management, OPERA laundry postings via IFC. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseLaundry extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulseLaundry' => 'Laundry', 'AdminPulseLaundryLinen' => 'Linen & Par Stock', 'AdminPulseLaundrySettings' => 'Laundry Settings');
    protected $hooks = array('displayBackOfficeHeader', 'moduleRoutes', 'actionPulseCheckOut', 'actionPulseLaundryOrder', 'actionPulseLaundryStatus');

    public function __construct()
    {
        $this->name = 'pulselaundry'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Laundry'); $this->description = $this->l('Guest laundry orders with post-to-folio, express services, house linen par stock, vendors, damage/loss claims and reports.');
        $this->confirmUninstall = $this->l('Uninstall Laundry? Orders, linen counts and claims will be dropped.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 40;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach (array('PULSE_LDY_TAX_PCT' => 7.5, 'PULSE_LDY_EXPRESS_PCT' => 50, 'PULSE_LDY_SAMEDAY_PCT' => 100, 'PULSE_LDY_NORMAL_HRS' => 24, 'PULSE_LDY_EXPRESS_HRS' => 6, 'PULSE_LDY_SAMEDAY_HRS' => 10, 'PULSE_LDY_POST_AT' => 'delivered', 'PULSE_LDY_CUTOFF' => '10:00') as $k => $v) { Configuration::updateValue($k, $v); }
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array('TAX_PCT', 'EXPRESS_PCT', 'SAMEDAY_PCT', 'NORMAL_HRS', 'EXPRESS_HRS', 'SAMEDAY_HRS', 'POST_AT', 'CUTOFF') as $k) { Configuration::deleteByName('PULSE_LDY_'.$k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    protected function runSql($f)
    {
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/'.$f.'.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) { return false; } }
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseLaundry')); }
    public function hookDisplayBackOfficeHeader() { if (strpos($this->context->controller->controller_name, 'AdminPulseLaundry') === 0) { $this->context->controller->addCSS($this->_path.'views/css/laundry.css'); } }
    public function hookModuleRoutes() { return array('pulselaundry-api' => array('controller' => 'api', 'rule' => 'pulse/api/laundry{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name))); }

    /** Guest checking out with laundry still in process → post it now and alert the desk. */
    public function hookActionPulseCheckOut($p)
    {
        if (empty($p['booking']['id']) || !empty($p['room_move'])) { return; }
        foreach (Db::getInstance()->executeS('SELECT id_pulse_laundry_order, order_no, status, posted_line FROM `'._DB_PREFIX_.'pulse_laundry_order` WHERE id_htl_booking='.(int) $p['booking']['id'].' AND status NOT IN ("delivered","cancelled")') as $o) {
            if (!$o['posted_line']) { PulseLaundryService::postToFolio($o['id_pulse_laundry_order']); }
            if (class_exists('PulseTrace')) { PulseTrace::add('alert', 'Guest checked out with laundry '.$o['order_no'].' still '.$o['status'], date('Y-m-d H:i:s'), (int) $p['booking']['id'], (int) $p['id_room'], null, 'laundry'); }
        }
    }
    public function hookActionPulseLaundryOrder($p) {}
    public function hookActionPulseLaundryStatus($p) {}
}
