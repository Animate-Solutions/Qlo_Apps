<?php
/** Pulse HR — employee master, org structure, effective-dated contracts, documents, leave, roster, lifecycle checklists, discipline & appraisal, and a mobile staff self-service portal. Benchmarks: Oracle OPERA/HRMS add-ons, eZee HR, SeamlessHR, PeopleHum, BambooHR, Zoho People. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseHr extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulseHr' => 'HR', 'AdminPulseHrEmployees' => 'Employees', 'AdminPulseHrOrg' => 'Org & Positions', 'AdminPulseHrLeave' => 'Leave',
        'AdminPulseHrRoster' => 'Roster', 'AdminPulseHrLifecycle' => 'Onboarding & Exit', 'AdminPulseHrPerformance' => 'Discipline & Appraisal',
        'AdminPulseHrReports' => 'HR Reports', 'AdminPulseHrSettings' => 'HR Settings');
    protected $hooks = array('displayBackOfficeHeader', 'moduleRoutes', 'actionPulseNightAuditClosed',
        'actionPulseHrEmployeeHired', 'actionPulseHrEmployeeExited', 'actionPulseHrLeaveApproved', 'actionPulseHrRosterPublished', 'actionPulseHrMobilePunch');

    public function __construct()
    {
        $this->name = 'pulsehr'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse HR'); $this->description = $this->l('The employee record and everything around the person: org structure and establishment, versioned contracts payroll can trust, documents that expire, leave with accrual and approvals, shift rosters with occupancy-driven coverage, onboarding and exit checklists that issue and revoke keys and POS logins, discipline and appraisal, and a mobile staff portal with geofenced clocking.');
        $this->confirmUninstall = $this->l('Uninstall HR? Employees, contracts, documents, leave balances, rosters, checklists, appraisals and every staff portal session will be dropped. Key cards and POS logins already issued stay where they are.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 140;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach (array(
            'PULSE_HR_STAFF_PREFIX' => 'PH', 'PULSE_HR_PROBATION_MONTHS' => 6, 'PULSE_HR_PROBATION_REMIND_DAYS' => 30, 'PULSE_HR_ANNUAL_LEAVE_DAYS' => 21,
            'PULSE_HR_LEAVE_DAY_DIVISOR' => 26, 'PULSE_HR_ACCRUAL_DAY' => 1, 'PULSE_HR_LEAVE_CLASH_WARN' => 3, 'PULSE_HR_REST_DAY' => 7, 'PULSE_HR_WEEK_START' => 1,
            'PULSE_HR_DOC_REMIND_DAYS' => 30, 'PULSE_HR_CONTRACT_REMIND_DAYS' => 30, 'PULSE_HR_WARNING_LIFE_MONTHS' => 12,
            'PULSE_HR_SHIFT_MINUTES' => 480, 'PULSE_HR_PUNCH_GRACE_MIN' => 120, 'PULSE_HR_PUNCH_DEDUPE_SEC' => 120, 'PULSE_HR_MAX_SHIFT_HOURS' => 16,
            'PULSE_HR_ESS_ENABLED' => 1, 'PULSE_HR_ESS_TTL_MIN' => 30, 'PULSE_HR_ESS_CLOCK' => 1, 'PULSE_HR_ESS_SELF_UPDATE' => 1, 'PULSE_HR_ESS_SHOW_PAYSLIP' => 1,
            'PULSE_HR_ESS_PAYSLIP_WINDOW_MIN' => 5, 'PULSE_HR_ESS_MAX_FAILS' => 5, 'PULSE_HR_ESS_FAIL_WINDOW_MIN' => 15, 'PULSE_HR_ESS_LOGIN_PER_MIN' => 10,
            'PULSE_HR_ESS_RATE_PER_MIN' => 30, 'PULSE_HR_ESS_PUNCH_PER_MIN' => 6, 'PULSE_HR_PIN_MIN_LENGTH' => 4,
            'PULSE_HR_GEO_LAT' => '4.8156', 'PULSE_HR_GEO_LNG' => '7.0498', 'PULSE_HR_GEO_RADIUS_M' => 200, 'PULSE_HR_GEO_ENFORCE' => 1,
            'PULSE_HR_GEO_MAX_ACCURACY_M' => 100, 'PULSE_HR_GEO_QR_WAIVES' => 1, 'PULSE_HR_ESS_QR_ROTATE_MIN' => 0, 'PULSE_HR_ESS_QR_CODE' => Tools::passwdGen(10),
            'PULSE_HR_ESS_SECRET' => Tools::passwdGen(48), 'PULSE_HR_CRON_TOKEN' => Tools::passwdGen(32),
        ) as $k => $v) { Configuration::updateValue($k, $v); }
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array('STAFF_PREFIX', 'PROBATION_MONTHS', 'PROBATION_REMIND_DAYS', 'ANNUAL_LEAVE_DAYS', 'LEAVE_DAY_DIVISOR', 'ACCRUAL_DAY', 'LEAVE_CLASH_WARN', 'REST_DAY', 'WEEK_START',
            'DOC_REMIND_DAYS', 'CONTRACT_REMIND_DAYS', 'WARNING_LIFE_MONTHS', 'SHIFT_MINUTES', 'PUNCH_GRACE_MIN', 'PUNCH_DEDUPE_SEC', 'MAX_SHIFT_HOURS',
            'ESS_ENABLED', 'ESS_TTL_MIN', 'ESS_CLOCK', 'ESS_SELF_UPDATE', 'ESS_SHOW_PAYSLIP', 'ESS_PAYSLIP_WINDOW_MIN', 'ESS_MAX_FAILS', 'ESS_FAIL_WINDOW_MIN',
            'ESS_LOGIN_PER_MIN', 'ESS_RATE_PER_MIN', 'ESS_PUNCH_PER_MIN', 'PIN_MIN_LENGTH', 'GEO_LAT', 'GEO_LNG', 'GEO_RADIUS_M', 'GEO_ENFORCE',
            'GEO_MAX_ACCURACY_M', 'GEO_QR_WAIVES', 'ESS_QR_ROTATE_MIN', 'ESS_QR_CODE', 'ESS_SECRET', 'CRON_TOKEN') as $k) { Configuration::deleteByName('PULSE_HR_'.$k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    protected function runSql($f)
    {
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/'.$f.'.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) { return false; } }
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseHr')); }

    public function hookDisplayBackOfficeHeader()
    {
        if (strpos($this->context->controller->controller_name, 'AdminPulseHr') === 0) {
            $this->context->controller->addCSS($this->_path.'views/css/hr.css');
            $this->context->controller->addJS($this->_path.'views/js/hr.js');
        }
    }

    public function hookModuleRoutes()
    {
        return array(
            'pulsehr-api' => array('controller' => 'api', 'rule' => 'pulse/api/hr{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsehr-ess' => array('controller' => 'ess', 'rule' => 'pulse/hr', 'keywords' => array(), 'params' => array('fc' => 'module', 'module' => $this->name)),
        );
    }

    /**
     * Night audit: accrue this month's leave, roll leave requests whose dates have arrived, refresh document
     * statuses, close the roster day and warn about tomorrow's coverage. Nothing here is allowed to break the
     * audit — every step is wrapped, because a failed HR accrual must not stop the hotel closing its day.
     */
    public function hookActionPulseNightAuditClosed($p)
    {
        $d = isset($p['business_date']) ? $p['business_date'] : PulseHrService::bd();
        foreach (array('accrue', 'roll', 'documents', 'roster') as $step) {
            try {
                if ($step === 'accrue' && (int) date('j', strtotime($d)) === (int) PulseHrService::cfg('ACCRUAL_DAY', 1)) { PulseHrLeave::accrueMonth(date('Y-m', strtotime($d))); }
                if ($step === 'roll') { PulseHrLeave::rollDay($d); }
                if ($step === 'documents') { PulseHrDocument::refreshStatuses(); }
                if ($step === 'roster') { PulseHrRoster::rollDay($d); }
            } catch (Exception $e) { PulseCoreService::audit('pulsehr', 'night_audit_hook_failed', array('step' => $step, 'error' => $e->getMessage())); }
        }
    }

    public function hookActionPulseHrEmployeeHired($p) {}
    public function hookActionPulseHrEmployeeExited($p) {}
    public function hookActionPulseHrLeaveApproved($p) {}
    public function hookActionPulseHrRosterPublished($p) {}
    public function hookActionPulseHrMobilePunch($p) {}
}
