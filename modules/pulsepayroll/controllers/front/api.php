<?php
/** /pulse/api/payroll/{resource}/{id} — the ESS portal in Pulse HR and any manager app read payslips, YTD, loan balances and service-charge statements here. */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulsepayroll/classes/autoload.php';

class PulsePayrollApiModuleFrontController extends PulseApiController
{
    protected $resources = array(
        'ping' => 'ping', 'payslips' => 'payslips', 'payslip' => 'payslip', 'ytd' => 'ytd', 'loan_balance' => 'loanBalance',
        'service_charge_statement' => 'serviceCharge', 'runs' => 'runs', 'run' => 'run', 'remittances' => 'remittances',
    );

    protected function ping() { $p = PulsePrStatutory::pack(PulsePrService::country()); return array('module' => 'pulsepayroll', 'country' => PulsePrService::country(), 'pack' => $p->label(), 'verified' => $p->verified()); }

    /** The list an employee sees in the staff portal: approved runs only, and only their own. */
    protected function payslips($idEmployee, $body)
    {
        $this->requireScope('ess');
        $id = $this->resolveEmployee($idEmployee, $body);
        $out = array();
        foreach (PulsePrPayslip::forEmployee($id, 24) as $p) {
            $out[] = array('id' => (int) $p['id_pulse_pr_payslip'], 'period' => $p['period'], 'gross' => (float) $p['gross'],
                'deductions' => (float) $p['total_deductions'], 'net' => (float) $p['net_pay'], 'pay_date' => $p['pay_date'],
                'url' => PulsePrPayslip::url($p['token']));
        }
        return $out;
    }

    /**
     * One payslip. The token is required even inside the API: an employee id alone is never enough to
     * read someone's pay, and a leaked bearer token must not turn into a leaked payroll.
     */
    protected function payslip($id, $body)
    {
        $this->requireScope('ess');
        $token = isset($body['token']) ? $body['token'] : Tools::getValue('token');
        $p = $token ? PulsePrPayslip::byToken($token) : null;
        if (!$p || ($id && (int) $p['id_pulse_pr_payslip'] !== (int) $id)) { throw new PrestaShopException('Payslip not found', 404); }
        $doc = PulsePrPayslip::document((int) $p['id_pulse_pr_payslip']);
        PulsePrPayslip::stampViewed((int) $p['id_pulse_pr_payslip']);
        if (isset($doc['employee']['payslip_pin'])) { unset($doc['employee']['payslip_pin']); }
        return $doc;
    }

    protected function ytd($idEmployee, $body)
    {
        $this->requireScope('ess');
        $id = $this->resolveEmployee($idEmployee, $body);
        $year = isset($body['year']) ? (int) $body['year'] : (int) date('Y');
        return array('year' => $year, 'summary' => PulsePrPayslip::ytdSummary($id, $year), 'by_element' => PulsePrPayslip::ytdLines($id, $year));
    }

    protected function loanBalance($idEmployee, $body)
    {
        $this->requireScope('ess');
        $id = $this->resolveEmployee($idEmployee, $body);
        $b = PulsePrLoan::balanceFor($id);
        $b['loans'] = array();
        foreach (PulsePrLoan::loans(array('id_pulse_pr_employee' => $id, 'status' => 'disbursed,repaying')) as $l) {
            $b['loans'][] = array('loan_no' => $l['loan_no'], 'type' => $l['type'], 'principal' => (float) $l['principal'], 'balance' => (float) $l['balance'], 'instalment' => (float) $l['instalment_amount'], 'status' => $l['status']);
        }
        return $b;
    }

    protected function serviceCharge($idEmployee, $body)
    {
        $this->requireScope('ess');
        $id = $this->resolveEmployee($idEmployee, $body);
        $from = isset($body['from']) ? Tools::substr($body['from'], 0, 7) : date('Y-01');
        $to = isset($body['to']) ? Tools::substr($body['to'], 0, 7) : date('Y-m');
        return PulsePrTronc::statement($id, $from, $to);
    }

    protected function runs()
    {
        $this->requireScope('payroll');
        return PulsePrRun::runs(array(), 24);
    }

    protected function run($id)
    {
        $this->requireScope('payroll');
        $r = PulsePrRun::get($id);
        if (!$r) { throw new PrestaShopException('Unknown run', 404); }
        return array('run' => $r, 'by_department' => PulsePrRun::byDepartment($id), 'verify' => PulsePrRun::verify($id));
    }

    protected function remittances()
    {
        $this->requireScope('payroll');
        return PulsePrService::remittances(null, 40);
    }

    /**
     * Resolve which employee the caller may read.
     *
     * A payroll-scoped token is a back-office integration and may name anyone. An ess-scoped token is a
     * shared staff-portal credential — it identifies the app, not the person — so it must prove which
     * employee it is acting for by presenting that employee's staff number and payslip PIN. Pay data is
     * never handed out on a bearer token alone.
     */
    protected function resolveEmployee($id, array $body = array())
    {
        if (in_array('payroll', explode(',', (string) $this->token['scopes']))) {
            if (!$id) { throw new PrestaShopException('An employee id is required', 400); }
            return (int) $id;
        }
        $staff = isset($body['staff_no']) ? $body['staff_no'] : Tools::getValue('staff_no');
        $pin = isset($body['pin']) ? $body['pin'] : Tools::getValue('pin');
        if (!$staff || !$pin) { throw new PrestaShopException('A staff number and payslip PIN are required', 401); }
        if (PulsePrService::pinThrottled('api:'.$staff)) { throw new PrestaShopException('Too many attempts', 429); }
        $e = PulsePrService::employeeByStaffNo($staff);
        if (!$e || !PulsePrService::checkPin($pin, (string) $e['payslip_pin'])) { PulsePrService::pinAttempt('api:'.$staff); throw new PrestaShopException('Unauthorized', 401); }
        PulsePrService::pinClear('api:'.$staff);
        if ($id && (int) $id !== (int) $e['id_pulse_pr_employee']) { throw new PrestaShopException('Forbidden', 403); }
        return (int) $e['id_pulse_pr_employee'];
    }
}
