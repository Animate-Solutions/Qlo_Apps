<?php
/** Pulse Accounts — double-entry general ledger, AR/AP, Nigerian tax, banking, fixed assets and USALI reporting for the hotel. Benchmarks: OPERA + Sun/Oracle Financials, eZee back-office export, Sage 50 / QuickBooks, USALI 11th edition. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseAccounts extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array(
        'AdminPulseAccounts' => 'Accounts', 'AdminPulseAccCoa' => 'Chart of Accounts', 'AdminPulseAccJournals' => 'Journals',
        'AdminPulseAccAr' => 'Receivables', 'AdminPulseAccAp' => 'Payables', 'AdminPulseAccTax' => 'Tax',
        'AdminPulseAccBank' => 'Banking', 'AdminPulseAccAssets' => 'Fixed Assets', 'AdminPulseAccReports' => 'Accounting Reports',
        'AdminPulseAccSettings' => 'Posting Rules & Settings',
    );
    protected $hooks = array(
        'displayBackOfficeHeader', 'moduleRoutes', 'actionPulseFolioPost', 'actionPulsePosBillSettled', 'actionPulseInvReceived',
        'actionPulseNightAuditClosed', 'actionPulseAccJournalPosted', 'actionPulseAccPeriodClosed', 'actionPulseAccDepreciationRun',
    );

    public function __construct()
    {
        $this->name = 'pulseaccounts'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Accounts');
        $this->description = $this->l('Hotel general ledger: chart of accounts, automatic double-entry posting from folios, POS, expenses and stock, city-ledger AR, supplier AP, VAT/WHT and FIRS e-invoicing, bank reconciliation, fixed assets with depreciation runs, period control and USALI departmental reporting.');
        $this->confirmUninstall = $this->l('Uninstall Accounts? The chart of accounts, every journal, invoice, bill, asset and reconciliation will be dropped. Folios and expenses in the other modules are untouched.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 110;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach ($this->defaults() as $k => $v) { Configuration::updateValue($k, $v); }
        PulseAccService::ensurePeriods(date('Y-m-d'), 12);
        PulseAccService::ensureBankAccounts();
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array_keys($this->defaults()) as $k) { Configuration::deleteByName($k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    /** Nigerian defaults: VAT 7.5%, Rivers State consumption tax 5% on F&B, WHT 5% services / 10% rent, naira, 30-day terms. */
    protected function defaults()
    {
        return array(
            'PULSE_ACC_VAT_PCT' => 7.5, 'PULSE_ACC_CONSUMPTION_PCT' => 5, 'PULSE_ACC_WHT_SERVICES_PCT' => 5, 'PULSE_ACC_WHT_RENT_PCT' => 10,
            'PULSE_ACC_WHT_THRESHOLD' => 0, 'PULSE_ACC_AR_TERMS_DAYS' => 30, 'PULSE_ACC_AP_TERMS_DAYS' => 30, 'PULSE_ACC_CURRENCY' => 'NGN',
            'PULSE_ACC_POST_MODE' => 'audit', 'PULSE_ACC_QUEUE_BATCH' => 400, 'PULSE_ACC_RETRY_MAX' => 5,
            'PULSE_ACC_DEFAULT_BANK' => '1121', 'PULSE_ACC_RETAINED_EARNINGS' => '3400', 'PULSE_ACC_PL_CLEARING' => '3500',
            'PULSE_ACC_HOTEL_NAME' => Configuration::get('PS_SHOP_NAME'), 'PULSE_ACC_HOTEL_TIN' => '', 'PULSE_ACC_HOTEL_ADDRESS' => '', 'PULSE_ACC_HOTEL_EMAIL' => Configuration::get('PS_SHOP_EMAIL'),
            'PULSE_ACC_EINV_ENABLED' => 0, 'PULSE_ACC_EINV_ENDPOINT' => '', 'PULSE_ACC_EINV_BUSINESS_ID' => '', 'PULSE_ACC_EINV_SERVICE_ID' => '',
            'PULSE_ACC_EINV_KEY' => '', 'PULSE_ACC_EINV_SECRET' => '', 'PULSE_ACC_EINV_TIMEOUT' => 20, 'PULSE_ACC_EINV_MIN_TOTAL' => 0,
            'PULSE_ACC_DUNNING_DAYS' => '7,21,45', 'PULSE_ACC_STOP_LIST_DAYS' => 60, 'PULSE_ACC_BANK_MATCH_DAYS' => 3,
            'PULSE_ACC_CRON_TOKEN' => Tools::passwdGen(32),
        );
    }

    protected function runSql($f)
    {
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/'.$f.'.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) { return false; } }
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseAccounts')); }
    public function hookDisplayBackOfficeHeader() { if (strpos($this->context->controller->controller_name, 'AdminPulseAcc') === 0) { $this->context->controller->addCSS($this->_path.'views/css/accounts.css'); $this->context->controller->addJS($this->_path.'views/js/accounts.js'); } }
    public function hookModuleRoutes() { return array('pulseaccounts-api' => array('controller' => 'api', 'rule' => 'pulse/api/accounts{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name))); }

    /* ---------------- events consumed: enqueue only, never post inside someone else's transaction ---------------- */

    /** A folio line was written. Queue it; the night audit (or the accountant's "post now") turns it into a journal. */
    public function hookActionPulseFolioPost($p)
    {
        if (empty($p['id_line'])) { return; }
        PulseAccPosting::enqueue('folio', 'line:'.(int) $p['id_line'], array('code' => isset($p['code']) ? $p['code'] : '', 'amount' => isset($p['amount']) ? $p['amount'] : 0));
    }

    /** A POS check settled: post the whole check so food and beverage split by major group instead of one REST line. */
    public function hookActionPulsePosBillSettled($p)
    {
        if (empty($p['id_check'])) { return; }
        PulseAccPosting::enqueue('pos', 'check:'.(int) $p['id_check'], array('total' => isset($p['total']) ? $p['total'] : 0, 'outlet' => isset($p['outlet']) ? $p['outlet'] : ''));
    }

    /** Goods received: inventory Dr / GRN accrual Cr. The supplier invoice clears the accrual later. */
    public function hookActionPulseInvReceived($p)
    {
        if (empty($p['id'])) { return; }
        PulseAccPosting::enqueue('grn', 'grn:'.(int) $p['id'], array('grn_no' => isset($p['grn']) ? $p['grn'] : '', 'total' => isset($p['total']) ? $p['total'] : 0));
    }

    /** Night audit closed the day: sweep anything the events missed, then drain the queue in one batch. */
    public function hookActionPulseNightAuditClosed($p)
    {
        $d = isset($p['business_date']) ? $p['business_date'] : PulseAccService::bd();
        PulseAccService::ensurePeriods($d, 2);
        PulseAccPosting::sweep($d);
        if (Configuration::get('PULSE_ACC_POST_MODE') !== 'manual') { PulseAccPosting::drain((int) Configuration::get('PULSE_ACC_QUEUE_BATCH'), $d); }
    }

    public function hookActionPulseAccJournalPosted($p) {}
    public function hookActionPulseAccPeriodClosed($p) {}
    public function hookActionPulseAccDepreciationRun($p) {}
}
