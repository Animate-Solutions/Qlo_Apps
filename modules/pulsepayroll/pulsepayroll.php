<?php
/** Pulse Payroll — multi-country statutory payroll, service charge distribution, casual pay, loans and bank files for the hotel. Benchmarks: Sage 300 People, SeamlessHR, PaidHR, Workpay, OPERA/Sun payroll interfaces. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulsePayroll extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array(
        'AdminPulsePrPayroll' => 'Payroll', 'AdminPulsePrEmployees' => 'Payroll Employees', 'AdminPulsePrElements' => 'Pay Elements',
        'AdminPulsePrCasual' => 'Casual & Weekly Pay', 'AdminPulsePrTronc' => 'Service Charge', 'AdminPulsePrLoans' => 'Loans & Advances',
        'AdminPulsePrStatutory' => 'Statutory & Countries', 'AdminPulsePrReports' => 'Payroll Reports', 'AdminPulsePrSettings' => 'Payroll Settings',
    );
    protected $hooks = array(
        'displayBackOfficeHeader', 'moduleRoutes', 'actionPulseNightAuditClosed', 'actionPulseHrEmployeeHired', 'actionPulseHrEmployeeExited',
        'actionPulsePayrollCalculated', 'actionPulsePayrollApproved', 'actionPulsePayrollPosted', 'actionPulsePayrollTroncDistributed',
    );

    public function __construct()
    {
        $this->name = 'pulsepayroll'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Payroll');
        $this->description = $this->l('Hotel payroll: effective-dated statutory packs (Nigeria complete, Ghana as a starting point), pay elements and structures, monthly runs with proration and a variance control, service charge / tronc distribution, weekly casual pay from approved timesheets, staff loans with automatic recovery and arrears, NIBSS bank payment files, tokenised payslips and GL posting through Pulse Accounts.');
        $this->confirmUninstall = $this->l('Uninstall Payroll? Every run, payslip, loan, service-charge pool and bank file will be dropped. Journals already posted to Pulse Accounts stay where they are.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 160;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach ($this->defaults() as $k => $v) { Configuration::updateValue($k, $v); }
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array_keys($this->defaults()) as $k) { Configuration::deleteByName($k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    /** Nigerian defaults: naira, 30ths proration, service charge 10% with a 10% management cap, payslips tokenised and PIN-gated. */
    protected function defaults()
    {
        return array(
            'PULSE_PR_COUNTRY' => 'NG', 'PULSE_PR_CURRENCY' => 'NGN', 'PULSE_PR_PAY_DAY' => 26, 'PULSE_PR_PRORATION' => 'calendar',
            'PULSE_PR_PERIODS_PER_YEAR' => 12, 'PULSE_PR_MIN_NET_PCT' => 0, 'PULSE_PR_LEAVER_REFUND' => 0,
            'PULSE_PR_VARIANCE_PCT' => 15, 'PULSE_PR_ROUND_DP' => 2, 'PULSE_PR_WORKING_DAYS' => 26, 'PULSE_PR_MONTH_HOURS' => 208,
            'PULSE_PR_OT_MULTIPLIER' => 1.5, 'PULSE_PR_OT_REST_MULTIPLIER' => 2, 'PULSE_PR_OT_HOLIDAY_MULTIPLIER' => 2,
            'PULSE_PR_NIGHT_ALLOWANCE' => 2500, 'PULSE_PR_TRONC_PCT' => 10, 'PULSE_PR_TRONC_ADMIN_PCT' => 0, 'PULSE_PR_TRONC_MGMT_CAP_PCT' => 10,
            'PULSE_PR_TRONC_BASIS' => 'points', 'PULSE_PR_CASUAL_TAX_PCT' => 0, 'PULSE_PR_CASUAL_DAY_RATE' => 7500,
            'PULSE_PR_EMPLOYER_STAFF_COUNT' => 0, 'PULSE_PR_EMPLOYER_TURNOVER' => 0,
            'PULSE_PR_TAX_STATE' => 'Rivers State Internal Revenue Service', 'PULSE_PR_PFA_DEFAULT' => '',
            'PULSE_PR_BANK_ACCOUNT' => '', 'PULSE_PR_BANK_NAME' => '', 'PULSE_PR_BANK_CODE' => '', 'PULSE_PR_BANK_FILE_TEMPLATE' => 'nibss',
            'PULSE_PR_PAYSLIP_PIN_MODE' => 'staff_no', 'PULSE_PR_PAYSLIP_EMAIL' => 1, 'PULSE_PR_PAYSLIP_TOKEN_DAYS' => 90,
            'PULSE_PR_POST_GL' => 1, 'PULSE_PR_AUTO_APPROVE' => 0, 'PULSE_PR_CRON_TOKEN' => Tools::passwdGen(32),
        );
    }

    protected function runSql($f)
    {
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/'.$f.'.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) { return false; } }
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulsePrPayroll')); }
    public function hookDisplayBackOfficeHeader() { if (strpos($this->context->controller->controller_name, 'AdminPulsePr') === 0) { $this->context->controller->addCSS($this->_path.'views/css/payroll.css'); $this->context->controller->addJS($this->_path.'views/js/payroll.js'); } }

    public function hookModuleRoutes()
    {
        return array(
            'pulsepayroll-api' => array('controller' => 'api', 'rule' => 'pulse/api/payroll{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsepayroll-payslip' => array('controller' => 'payslip', 'rule' => 'pulse/payslip', 'keywords' => array(), 'params' => array('fc' => 'module', 'module' => $this->name)),
        );
    }

    /* ---------------- events consumed ---------------- */

    /** Night audit closed: accrue the month's ITF and roll the service-charge pool figure from the day's revenue. */
    public function hookActionPulseNightAuditClosed($p)
    {
        $d = isset($p['business_date']) ? $p['business_date'] : PulsePrService::bd();
        try { PulsePrService::accrueDaily($d); } catch (Exception $e) { PulsePrService::log(null, 'night_audit', 'accrual', $e->getMessage()); }
    }

    /** A new hire in Pulse HR: mirror the person into the payroll roster so the next run picks them up. */
    public function hookActionPulseHrEmployeeHired($p)
    {
        $id = $this->hrId($p);
        if (!$id) { return; }
        try { PulsePrService::syncFromHr($id); } catch (Exception $e) { PulsePrService::log(null, 'hr_sync', 'hire', $e->getMessage()); }
    }

    /** An exit in Pulse HR: mark the payroll record exited so proration and the final settlement are right. */
    public function hookActionPulseHrEmployeeExited($p)
    {
        $id = $this->hrId($p);
        if (!$id) { return; }
        try { PulsePrService::markExit($id, isset($p['exit_date']) ? $p['exit_date'] : null); } catch (Exception $e) { PulsePrService::log(null, 'hr_sync', 'exit', $e->getMessage()); }
    }

    /** The Pulse HR employee id off a lifecycle event. NOT id_employee — that is the PrestaShop back-office user. */
    protected function hrId($p) { foreach (array('id_pulse_hr_employee', 'id_hr_employee') as $k) { if (!empty($p[$k])) { return (int) $p[$k]; } } return 0; }

    public function hookActionPulsePayrollCalculated($p) {}
    public function hookActionPulsePayrollApproved($p) {}
    public function hookActionPulsePayrollPosted($p) {}
    public function hookActionPulsePayrollTroncDistributed($p) {}
}
