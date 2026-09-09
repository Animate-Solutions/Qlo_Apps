<?php
/**
 * /pulse/api/hr/{resource}/{id}
 *
 * Auth, in order of preference:
 *   X-Pulse-Ess: <sid.exp.sig>       a staff portal session — the subject is resolved server-side from the row
 *   Authorization: Bearer <token>    a pulse_api_token with scope hr | manager | ess
 * `ping` and `login` are the only resources reachable without one, and both are rate limited per address.
 *
 * An employee id in the request NEVER selects the subject of an ESS call. Where a bearer token with the `hr`
 * scope asks for a specific person, the id is honoured — that is an office integration, not a phone.
 */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulsehr/classes/autoload.php';

class PulseHrApiModuleFrontController extends PulseApiController
{
    protected $ess = null; protected $body = array();
    protected $resources = array('ping' => 'ping', 'login' => 'login', 'logout' => 'logout', 'me' => 'me', 'employees' => 'employees', 'employee' => 'employee',
        'leave_balance' => 'leaveBalance', 'leave_request' => 'leaveRequest', 'leave_types' => 'leaveTypes', 'roster' => 'roster', 'swap' => 'swap',
        'clock' => 'clock', 'punches' => 'punches', 'payslips' => 'payslips', 'reveal' => 'reveal', 'documents' => 'documents',
        'document_expiry' => 'documentExpiry', 'cases' => 'cases', 'acknowledge' => 'acknowledge', 'update_request' => 'updateRequest', 'coverage' => 'coverage');

    protected function authenticate()
    {
        $res = Tools::getValue('resource', 'ping');
        $this->body = json_decode(Tools::file_get_contents('php://input'), true);
        if (!is_array($this->body)) { $this->body = array(); }
        if (file_exists(_PS_MODULE_DIR_.'pulselicense/classes/PulseLicenseService.php') && Module::isEnabled('pulselicense')) {
            require_once _PS_MODULE_DIR_.'pulselicense/classes/PulseLicenseService.php';
            PulseLicenseService::assertApi();
            if (!PulseLicenseService::entitled('pulsehr')) { throw new PrestaShopException('Pulse HR is not licensed', 402); }
        }
        // downstream hooks (traces, tickets, audit) expect an employee in context; the portal acts as the cron user
        $ctx = Context::getContext();
        if (empty($ctx->employee) || !$ctx->employee->id) { $ctx->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1); }
        $tok = isset($_SERVER['HTTP_X_PULSE_ESS']) ? $_SERVER['HTTP_X_PULSE_ESS'] : Tools::getValue('ess_token');
        if ($tok) {
            $this->ess = PulseHrEss::verify($tok);
            PulseHrService::rateHit('ess'.(int) $this->ess['id_pulse_hr_employee'], (int) PulseHrService::cfg('ESS_RATE_PER_MIN', 30));
            return;
        }
        if (in_array($res, array('ping', 'login'))) { PulseHrService::rateHit('hrip'.md5((string) Tools::getRemoteAddr()), (int) PulseHrService::cfg('ESS_LOGIN_PER_MIN', 10)); return; }
        parent::authenticate();
        PulseHrService::rateHit('hrtok'.(int) $this->token['id_pulse_api_token'], (int) PulseHrService::cfg('ESS_RATE_PER_MIN', 30) * 4);
    }

    /* ---------- helpers ---------- */
    /** The subject of a self-service call: the session's employee, and nothing a client can name. */
    protected function self_()
    {
        if (!$this->ess) { throw new PrestaShopException('Sign in on the staff portal for this', 401); }
        return (int) $this->ess['id_pulse_hr_employee'];
    }
    /** An office integration acting on someone: needs the hr scope AND an explicit id. */
    protected function subject($id)
    {
        if ($this->ess) { return $this->self_(); }
        $this->requireScope('hr');
        if (!$id) { throw new PrestaShopException('Which employee?', 400); }
        return (int) $id;
    }
    /** Same, for a supervisor integration acting on their people. */
    protected function managerSubject($id)
    {
        if ($this->ess) { return $this->self_(); }
        $this->requireScope('manager');
        if (!$id) { throw new PrestaShopException('Which employee?', 400); }
        return (int) $id;
    }
    protected function b($k, $default = '') { return isset($this->body[$k]) ? $this->body[$k] : Tools::getValue($k, $default); }

    /* ---------- open ---------- */
    protected function ping()
    {
        return array('module' => 'pulsehr', 'version' => $this->module->version, 'business_date' => PulseHrService::bd(), 'server_time' => date('c'),
            'ess' => (int) PulseHrService::cfg('ESS_ENABLED', 1), 'sections' => PulseHrEss::sections());
    }
    protected function login() { return PulseHrEss::login($this->b('staff_no', ''), $this->b('pin', '')); }
    protected function logout() { if ($this->ess) { PulseHrEss::revoke((int) $this->ess['id_pulse_hr_ess_session'], 'signed out'); } return array('ok' => 1); }

    /* ---------- the person ---------- */
    protected function me() { return array('employee' => PulseHrEss::me($this->self_()), 'sections' => PulseHrEss::sections(), 'next_direction' => PulseHrEss::nextDirection($this->self_())); }
    protected function employees() { $this->requireScope('hr'); return PulseHrEmployee::search(array('q' => Tools::getValue('q'), 'department' => Tools::getValue('department'), 'status' => Tools::getValue('status'), 'limit' => (int) Tools::getValue('limit', 100))); }
    protected function employee($id)
    {
        $idEmployee = $this->subject($id);
        $e = PulseHrEmployee::get($idEmployee);
        if (!$e) { throw new PrestaShopException('Employee not found', 404); }
        if ($this->ess) { return PulseHrEss::me($idEmployee); }
        return array('employee' => $e, 'contract' => PulseHrContract::onDate($idEmployee), 'contracts' => PulseHrContract::history($idEmployee), 'documents' => PulseHrDocument::forEmployee($idEmployee));
    }

    /* ---------- leave ---------- */
    protected function leaveTypes() { return PulseHrLeave::types(); }
    protected function leaveBalance($id) { $e = $this->subject($id); return array('balances' => PulseHrLeave::balances($e, (int) Tools::getValue('year', date('Y'))), 'requests' => PulseHrLeave::requests(null, null, $e, 20)); }
    protected function leaveRequest($id)
    {
        $e = $this->managerSubject($id);
        if ($this->b('cancel')) {
            // withdrawing is only ever your own request: the row's owner decides, not the id in the body
            $r = PulseHrLeave::get((int) $this->b('id_request'));
            if (!$r || (int) $r['id_pulse_hr_employee'] !== $e) { throw new PrestaShopException('Leave request not found', 404); }
            PulseHrLeave::cancel((int) $r['id_pulse_hr_leave_request'], 'Withdrawn by staff');
            return array('ok' => 1, 'request_no' => $r['request_no']);
        }
        return PulseHrLeave::request(array('id_pulse_hr_employee' => $e, 'id_pulse_hr_leave_type' => (int) $this->b('id_leave_type'), 'date_from' => $this->b('date_from'),
            'date_to' => $this->b('date_to'), 'half_day' => $this->b('half_day', 'none'), 'reason' => $this->b('reason', ''), 'id_relief' => (int) $this->b('id_relief', 0),
            'contact_phone' => $this->b('contact_phone', ''), 'address_on_leave' => $this->b('address_on_leave', ''), 'source' => $this->ess ? 'ess' : 'api'));
    }

    /* ---------- roster ---------- */
    protected function roster($id)
    {
        $from = Tools::getValue('from', PulseHrRoster::weekStart());
        $to = Tools::getValue('to', date('Y-m-d', strtotime($from.' +13 day')));
        if ($this->ess) { return array('from' => $from, 'to' => $to, 'shifts' => PulseHrRoster::forEmployee($this->self_(), $from, $to, true)); }
        $this->requireScope('manager');
        return array('from' => $from, 'to' => $to, 'grid' => PulseHrRoster::grid($from, $to, Tools::getValue('department')), 'coverage' => PulseHrService::coverage($from, Tools::getValue('department')));
    }
    /** Ask a colleague to take a shift. The colleague may be named by staff number — resolved here, never trusted as an id. */
    protected function swap()
    {
        $to = (int) $this->b('id_employee_to', 0);
        if (!$to && $this->b('staff_no', '') !== '') {
            $c = PulseHrEmployee::byStaffNo(trim((string) $this->b('staff_no', '')));
            if (!$c || $c['status'] === 'exited') { throw new PrestaShopException('No colleague with that staff number', 404); }
            $to = (int) $c['id_pulse_hr_employee'];
        }
        if (!$to) { throw new PrestaShopException('Say who you are asking to take it', 400); }
        return array('id' => PulseHrRoster::requestSwap((int) $this->b('id_roster'), $this->self_(), $to, $this->b('reason', '')));
    }
    protected function coverage() { $this->requireScope('manager'); return PulseHrService::coverage(Tools::getValue('date', PulseHrService::bd()), Tools::getValue('department')); }

    /* ---------- clocking ---------- */
    /** The mobile punch. Only a portal session may clock, and only for itself. */
    protected function clock()
    {
        $e = $this->self_();
        return PulseHrEss::clock($e, array('direction' => $this->b('direction', ''), 'lat' => $this->b('lat', ''), 'lng' => $this->b('lng', ''),
            'accuracy' => $this->b('accuracy', ''), 'qr' => $this->b('qr', ''), 'device' => $this->b('device', ''), 'note' => $this->b('note', '')));
    }
    protected function punches($id)
    {
        $from = Tools::getValue('from', date('Y-m-01'));
        $to = Tools::getValue('to', PulseHrService::bd());
        if ($this->ess) { return PulseHrEss::punches($from, $to, null, $this->self_(), 100); }
        $this->requireScope('manager');
        return PulseHrEss::punches($from, $to, Tools::getValue('status'), (int) $id, 300);
    }

    /* ---------- payslips (gated) ---------- */
    protected function reveal() { if (!$this->ess) { throw new PrestaShopException('Sign in on the staff portal for this', 401); } return PulseHrEss::confirmPin($this->ess, $this->b('pin', '')); }
    protected function payslips($id)
    {
        if ($this->ess) {
            if (!PulseHrEss::payslipWindowOpen($this->ess)) { return array('available' => 0, 'locked' => 1, 'why' => 'Enter your PIN again to see your payslips'); }
            return PulseHrEss::payslips($this->self_(), (int) Tools::getValue('limit', 12));
        }
        $this->requireScope('hr');
        return PulseHrEss::payslips((int) $id, (int) Tools::getValue('limit', 12));
    }

    /* ---------- documents, cases, detail changes ---------- */
    protected function documents($id) { return PulseHrDocument::forEmployee($this->subject($id)); }
    protected function documentExpiry() { $this->requireScope('hr'); return PulseHrDocument::expiring((int) Tools::getValue('days', 30)); }
    protected function cases($id) { return PulseHrPerformance::cases(null, $this->subject($id), 50); }
    protected function acknowledge() { PulseHrPerformance::acknowledge((int) $this->b('id_case'), $this->self_(), $this->b('response', ''), Tools::getRemoteAddr()); return array('ok' => 1); }
    protected function updateRequest() { return PulseHrEss::requestChange($this->self_(), (string) $this->b('field', ''), (string) $this->b('value', '')); }
}
