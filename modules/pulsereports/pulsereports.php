<?php
/** Pulse Reports — consolidated operations & finance dashboard, expenditure ledger, budgets, scheduled owner/manager reports. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseReports extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulseReports' => 'Reports Dashboard', 'AdminPulseExpenses' => 'Expenses & Budgets', 'AdminPulseReportSchedules' => 'Scheduled Reports');
    protected $hooks = array('displayBackOfficeHeader', 'actionPulseNightAuditClosed');

    public function __construct()
    {
        $this->name = 'pulsereports'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Reports & Owner Snapshot'); $this->description = $this->l('Comprehensive operations and finance dashboard, expense ledger with approvals and budgets, automatic daily/weekly/monthly owner reports by email and SMS.');
        $this->confirmUninstall = $this->l('Uninstall Reports? Expense ledger, budgets and report history will be dropped.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/install.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) { return false; } }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 60;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach (array('PULSE_RPT_APPROVAL_LIMIT' => 50000, 'PULSE_RPT_VARIANCE_ALERT' => 1000, 'PULSE_RPT_HIGH_BALANCE' => 200000, 'PULSE_RPT_CRON_TOKEN' => Tools::passwdGen(32)) as $k => $v) { Configuration::updateValue($k, $v); }
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array('APPROVAL_LIMIT', 'VARIANCE_ALERT', 'HIGH_BALANCE', 'CRON_TOKEN') as $k) { Configuration::deleteByName('PULSE_RPT_'.$k); }
        Db::getInstance()->execute(str_replace('PREFIX_', _DB_PREFIX_, str_replace(";\n", '; ', Tools::file_get_contents(dirname(__FILE__).'/sql/uninstall.sql'))));
        foreach (array('expense_category', 'expense', 'budget', 'report_schedule', 'report_log') as $t) { Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'pulse_'.$t.'`'); }
        return parent::uninstall();
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseReports')); }
    public function hookDisplayBackOfficeHeader() { if (strpos($this->context->controller->controller_name, 'AdminPulseReport') === 0 || $this->context->controller->controller_name === 'AdminPulseExpenses') { $this->context->controller->addCSS($this->_path.'views/css/reports.css'); } }
    /** Night audit just closed a business date → sync expense feeds and send the daily reports flagged "after audit". */
    public function hookActionPulseNightAuditClosed($p) { try { PulseOwnerSnapshot::runDue(true); } catch (Exception $e) { PulseCoreService::audit('pulsereports', 'snapshot_error', $e->getMessage()); } }
}
