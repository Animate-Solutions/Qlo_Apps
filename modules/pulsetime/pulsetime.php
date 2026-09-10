<?php
/** Pulse Time & Attendance — biometric device fleet, punch capture, shift pairing, exceptions and timesheets. Benchmarks: ZKTeco BioTime 8.5, eSSL eTimeTrackLite, Suprema BioStar 2 T&A, Hikvision iVMS attendance, Matrix COSEC, Sage 300 People T&A. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseTime extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulseTaBoard' => 'Live Board', 'AdminPulseTaPunches' => 'Punches', 'AdminPulseTaExceptions' => 'Exceptions', 'AdminPulseTaTimesheets' => 'Timesheets',
        'AdminPulseTaDevices' => 'Devices', 'AdminPulseTaEnrolment' => 'Enrolment', 'AdminPulseTaOvertime' => 'Overtime & Roster', 'AdminPulseTaSettings' => 'T&A Settings');
    protected $hooks = array('displayBackOfficeHeader', 'moduleRoutes', 'actionPulseNightAuditClosed', 'actionPulseHrEmployeeHired', 'actionPulseHrEmployeeExited', 'actionPulseHrRosterPublished',
        'actionPulseTaPunch', 'actionPulseTaException', 'actionPulseTaTimesheetApproved', 'actionPulseTaDeviceHealth');

    public function __construct()
    {
        $this->name = 'pulsetime'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Time & Attendance');
        $this->description = $this->l('Biometric clocking for ZKTeco/eSSL, Hikvision, Suprema, Anviz, Matrix, Dahua, IDEMIA and FingerTec devices with an ADMS push endpoint, a hardware-free simulator, night-shift-safe pairing, an exception queue and locked timesheets for payroll.');
        $this->confirmUninstall = $this->l('Uninstall Time & Attendance? Devices, enrolments, punches, timesheets and approved periods will be dropped. Export the punch register first — it is the evidence behind every payslip.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 150;
        foreach ($this->tabs as $c => $n) { $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++; foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } if (!$t->add()) { return false; } }
        foreach (array(
            'PULSE_TA_TZ' => 'Africa/Lagos', 'PULSE_TA_DAY_START' => '00:00', 'PULSE_TA_ATTRIBUTE_BY' => 'shift_start',
            'PULSE_TA_ROUND_MIN' => 5, 'PULSE_TA_ROUND_MODE' => 'nearest', 'PULSE_TA_ROUND_IN' => 'up', 'PULSE_TA_ROUND_OUT' => 'down',
            'PULSE_TA_MIN_GAP_SEC' => 60, 'PULSE_TA_DEFAULT_SHIFT' => 'GEN', 'PULSE_TA_AUTO_DIRECTION' => 1,
            'PULSE_TA_OT_APPROVAL' => 1, 'PULSE_TA_WEEK_START' => 1, 'PULSE_TA_NIGHT_FROM' => '22:00', 'PULSE_TA_NIGHT_TO' => '06:00',
            'PULSE_TA_ABSENT_AFTER_MIN' => 240, 'PULSE_TA_POS_RECONCILE' => 1, 'PULSE_TA_POS_TOLERANCE_MIN' => 20, 'PULSE_TA_LATE_EXCEPTION_MIN' => 30,
            'PULSE_TA_PUSH_ENABLED' => 1, 'PULSE_TA_PUSH_KEY_PARAM' => 'pushkey', 'PULSE_TA_PUSH_REQUIRE_KEY' => 0,
            'PULSE_TA_PUSH_MAX_BYTES' => 1048576, 'PULSE_TA_PUSH_RATE_PER_MIN' => 120, 'PULSE_TA_PUSH_RATE_PER_IP_MIN' => 480, 'PULSE_TA_PUSH_MAX_PENDING' => 10, 'PULSE_TA_PUSH_AUTO_REGISTER' => 1,
            'PULSE_TA_PUSH_DELAY' => 10, 'PULSE_TA_PUSH_TRANS_INTERVAL' => 1, 'PULSE_TA_PUSH_ERROR_DELAY' => 30, 'PULSE_TA_PUSH_REALTIME' => 1, 'PULSE_TA_PUSH_TIMEZONE' => 1,
            'PULSE_TA_LOG_RETENTION' => 30, 'PULSE_TA_PUNCH_RETENTION' => 0, 'PULSE_TA_HTTP_TIMEOUT' => 8,
            'PULSE_TA_POLL_OVERLAP_MIN' => 120, 'PULSE_TA_DEVICE_STALE_MIN' => 60, 'PULSE_TA_JOB_MAX_ATTEMPTS' => 6, 'PULSE_TA_PUSH_CMD_BATCH' => 5, 'PULSE_TA_PUSH_TRANS_TIMES' => '00:00;14:00',
            'PULSE_TA_MOBILE_PUNCH' => 1, 'PULSE_TA_GEOFENCE_M' => 250, 'PULSE_TA_SITE_LAT' => '', 'PULSE_TA_SITE_LNG' => '',
            'PULSE_TA_CRON_TOKEN' => Tools::passwdGen(32), 'PULSE_TA_KIOSK_TOKEN' => Tools::passwdGen(32),
        ) as $k => $v) { Configuration::updateValue($k, $v); }
        if (class_exists('PulseTaService')) { PulseTaService::syncRoster(); }
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array('TZ', 'DAY_START', 'ATTRIBUTE_BY', 'ROUND_MIN', 'ROUND_MODE', 'ROUND_IN', 'ROUND_OUT', 'MIN_GAP_SEC', 'DEFAULT_SHIFT', 'AUTO_DIRECTION', 'OT_APPROVAL', 'WEEK_START',
            'NIGHT_FROM', 'NIGHT_TO', 'ABSENT_AFTER_MIN', 'POS_RECONCILE', 'POS_TOLERANCE_MIN', 'LATE_EXCEPTION_MIN', 'PUSH_ENABLED', 'PUSH_KEY_PARAM', 'PUSH_REQUIRE_KEY', 'PUSH_MAX_BYTES', 'PUSH_RATE_PER_MIN', 'PUSH_RATE_PER_IP_MIN', 'PUSH_MAX_PENDING',
            'PUSH_AUTO_REGISTER', 'PUSH_DELAY', 'PUSH_TRANS_INTERVAL', 'PUSH_ERROR_DELAY', 'PUSH_REALTIME', 'PUSH_TIMEZONE', 'LOG_RETENTION', 'PUNCH_RETENTION', 'HTTP_TIMEOUT',
            'POLL_OVERLAP_MIN', 'DEVICE_STALE_MIN', 'JOB_MAX_ATTEMPTS', 'PUSH_CMD_BATCH', 'PUSH_TRANS_TIMES',
            'MOBILE_PUNCH', 'GEOFENCE_M', 'SITE_LAT', 'SITE_LNG', 'CRON_TOKEN', 'KIOSK_TOKEN') as $k) { Configuration::deleteByName('PULSE_TA_'.$k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    protected function runSql($f)
    {
        $path = dirname(__FILE__).'/sql/'.$f.'.sql';
        if (!file_exists($path)) { return true; }
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents($path));
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) {
            if ($q !== '' && !Db::getInstance()->execute($q)) { return false; }
        }
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseTaBoard')); }

    /** Exact tab match, not a prefix test: AdminPulseTapeChart already exists in Front Desk and must not pick up our CSS. */
    public function hookDisplayBackOfficeHeader()
    {
        if (array_key_exists($this->context->controller->controller_name, $this->tabs)) {
            $this->context->controller->addCSS($this->_path.'views/css/time.css');
            $this->context->controller->addJS($this->_path.'views/js/time.js');
        }
    }

    /**
     * Two routes. The API is the usual Pulse shape. The `iclock` route is not ours to choose — ZKTeco ADMS
     * firmware has the path burned in and will only ever GET/POST /iclock/cdata, /iclock/getrequest,
     * /iclock/devicecmd and friends at the document root of whatever host and port it is given. See README
     * for what to do when friendly URLs are off.
     */
    public function hookModuleRoutes()
    {
        return array(
            'pulsetime-api' => array('controller' => 'api', 'rule' => 'pulse/api/time{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsetime-iclock' => array('controller' => 'iclock', 'rule' => 'iclock/{:action}', 'keywords' => array('action' => array('regexp' => '[a-zA-Z_]+', 'param' => 'action')), 'params' => array('fc' => 'module', 'module' => $this->name)),
        );
    }

    /** Night audit closed: rebuild yesterday's timesheets, raise the exceptions a supervisor fixes in the morning, then tidy the logs. */
    public function hookActionPulseNightAuditClosed($p)
    {
        $date = !empty($p['business_date']) ? $p['business_date'] : date('Y-m-d', strtotime('-1 day'));
        try {
            $r = PulseTaEngine::buildDay($date);
            PulseCoreService::audit('pulsetime', 'night_build', array('date' => $date, 'timesheets' => $r['timesheets'], 'exceptions' => $r['exceptions']));
        } catch (Exception $e) { PulseCoreService::audit('pulsetime', 'night_build_failed', array('date' => $date, 'error' => $e->getMessage())); }
    }

    /** A new hire in Pulse HR mirrors into the local roster and is queued onto every active device. */
    public function hookActionPulseHrEmployeeHired($p)
    {
        if (empty($p['id_hr_employee'])) { return; }
        try { $id = PulseTaService::syncOne((int) $p['id_hr_employee']); if ($id) { PulseTaEnrolment::provisionAll($id); } }
        catch (Exception $e) { PulseCoreService::audit('pulsetime', 'hire_sync_failed', array('error' => $e->getMessage())); }
    }

    /** An exit revokes the fingerprint on every reader — a leaver who can still clock in is a payroll fraud waiting to happen. */
    public function hookActionPulseHrEmployeeExited($p)
    {
        if (empty($p['id_hr_employee'])) { return; }
        try { $s = PulseTaService::staffByHr((int) $p['id_hr_employee']); if ($s) { PulseTaEnrolment::revokeAll((int) $s['id_pulse_ta_staff'], 'Employee exited'); } }
        catch (Exception $e) { PulseCoreService::audit('pulsetime', 'exit_revoke_failed', array('error' => $e->getMessage())); }
    }

    /** HR published a roster — mirror it locally so pairing keeps working if HR is later disabled. */
    public function hookActionPulseHrRosterPublished($p)
    {
        try { PulseTaRoster::importFromHr(isset($p['date_from']) ? $p['date_from'] : date('Y-m-d'), isset($p['date_to']) ? $p['date_to'] : date('Y-m-d', strtotime('+13 day'))); }
        catch (Exception $e) { PulseCoreService::audit('pulsetime', 'roster_import_failed', array('error' => $e->getMessage())); }
    }

    public function hookActionPulseTaPunch($p) {}
    public function hookActionPulseTaException($p) {}
    public function hookActionPulseTaTimesheetApproved($p) {}
    public function hookActionPulseTaDeviceHealth($p) {}
}
