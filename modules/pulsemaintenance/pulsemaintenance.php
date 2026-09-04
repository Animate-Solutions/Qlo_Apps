<?php
/** Pulse Maintenance — work orders, assets, preventive maintenance, parts, meters. Benchmarks: eZee Maintenance (Housekeeping), OPERA Task Sheets / Work Orders. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseMaintenance extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulseMaintenance' => 'Maintenance', 'AdminPulseMaintenanceAssets' => 'Assets & Parts', 'AdminPulseMaintenancePm' => 'Preventive Maintenance');
    protected $hooks = array('displayBackOfficeHeader', 'moduleRoutes', 'actionPulseTicketCreated', 'actionPulseHousekeepingTask', 'actionPulseWorkOrder', 'actionPulseWorkOrderStatus');

    public function __construct()
    {
        $this->name = 'pulsemaintenance'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Maintenance'); $this->description = $this->l('Work orders with SLAs, asset register, preventive maintenance programme, spare parts, meter readings, technician mobile API.');
        $this->confirmUninstall = $this->l('Uninstall Maintenance? Work orders, assets, PM schedules and parts stock will be dropped.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 50;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach (array('PULSE_MNT_LABOUR_RATE' => 2500, 'PULSE_MNT_RELEASE_ROOM_AT' => 'completed', 'PULSE_MNT_AUTO_WO_FROM_TICKET' => 1, 'PULSE_MNT_AUTO_WO_FROM_HK' => 1, 'PULSE_MNT_ALERT_PHONE' => '', 'PULSE_MNT_ALERT_EMAIL' => '', 'PULSE_MNT_CRON_TOKEN' => Tools::passwdGen(32)) as $k => $v) { Configuration::updateValue($k, $v); }
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array('LABOUR_RATE', 'RELEASE_ROOM_AT', 'AUTO_WO_FROM_TICKET', 'AUTO_WO_FROM_HK', 'ALERT_PHONE', 'ALERT_EMAIL', 'CRON_TOKEN') as $k) { Configuration::deleteByName('PULSE_MNT_'.$k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    protected function runSql($f)
    {
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/'.$f.'.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) { return false; } }
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseMaintenance')); }
    public function hookDisplayBackOfficeHeader() { if (strpos($this->context->controller->controller_name, 'AdminPulseMaintenance') === 0) { $this->context->controller->addCSS($this->_path.'views/css/maintenance.css'); } }
    public function hookModuleRoutes() { return array('pulsemaintenance-api' => array('controller' => 'api', 'rule' => 'pulse/api/maintenance{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name))); }

    /** Front Desk ticket of category maintenance → work order automatically. */
    public function hookActionPulseTicketCreated($p)
    {
        if (!Configuration::get('PULSE_MNT_AUTO_WO_FROM_TICKET') || empty($p['id_ticket'])) { return; }
        $t = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ticket` WHERE id_pulse_ticket='.(int) $p['id_ticket']);
        if (!$t || $t['category'] !== 'maintenance') { return; } $prio = array('urgent' => 'emergency', 'high' => 'high', 'normal' => 'normal', 'low' => 'low');
        PulseMaintenanceService::createWo(array('category' => 'other', 'id_room' => $t['id_room'], 'priority' => isset($prio[$t['priority']]) ? $prio[$t['priority']] : 'normal', 'subject' => $t['subject'], 'description' => $t['body'], 'source' => $t['source'] === 'portal' ? 'portal' : 'ticket', 'source_ref' => $t['ticket_no'], 'id_pulse_ticket' => $t['id_pulse_ticket']));
    }
    /** Housekeeping "maintenance" task → work order. */
    public function hookActionPulseHousekeepingTask($p)
    {
        if (!Configuration::get('PULSE_MNT_AUTO_WO_FROM_HK') || empty($p['type']) || $p['type'] !== 'maintenance') { return; }
        $t = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_housekeeping_task` WHERE id_pulse_housekeeping_task='.(int) $p['id_task']);
        if ($t) { PulseMaintenanceService::createWo(array('id_room' => $t['id_room'], 'priority' => $t['priority'] <= 2 ? 'high' : 'normal', 'subject' => 'Housekeeping reported: '.($t['note'] ?: 'maintenance needed'), 'source' => 'housekeeping', 'source_ref' => 'HK'.$t['id_pulse_housekeeping_task'])); }
    }
    public function hookActionPulseWorkOrder($p) {}
    public function hookActionPulseWorkOrderStatus($p) {}
}
