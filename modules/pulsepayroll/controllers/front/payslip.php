<?php
/**
 * /pulse/payslip?t=<48-char token> — the confidential payslip download.
 *
 * Two gates, not one. The token is unguessable (sha256 over the shop cookie key, the run, the employee
 * and a nonce, truncated to 48 hex characters) and expires; and even with the token the page will not
 * render a figure until the employee has entered their payslip PIN, which is stored hashed. Failed
 * attempts are rate-limited per token and per address, and every view is stamped on the payslip so an
 * employee can be told exactly when theirs was last opened.
 */
require_once _PS_MODULE_DIR_.'pulsepayroll/classes/autoload.php';

class PulsePayrollPayslipModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    protected $slip = null;

    public function init() { parent::init(); $this->display_header = false; $this->display_footer = false; }
    public function setMedia() { return true; }

    public function initContent()
    {
        parent::initContent();
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        $token = (string) Tools::getValue('t');
        $this->slip = PulsePrPayslip::byToken($token);
        $assign = array('token' => $token, 'hotel' => Configuration::get('PS_SHOP_NAME'), 'css' => $this->module->getPathUri().'views/css/payslip.css', 'error' => '', 'doc' => null, 'unlocked' => false);
        if (!$this->slip) {
            $assign['error'] = 'This payslip link is not valid, or it has expired. Ask the payroll office to send you a new one.';
            $this->context->smarty->assign($assign);
            return $this->setTemplate('payslip.tpl');
        }
        $emp = PulsePrService::employee((int) $this->slip['id_pulse_pr_employee']);
        if (!$emp) { $assign['error'] = 'This payslip no longer has an employee record. Ask the payroll office.'; $this->context->smarty->assign($assign); return $this->setTemplate('payslip.tpl'); }
        $assign['period'] = $this->slip['period'];
        $assign['name'] = $this->slip['employee_name'];
        if (Tools::isSubmit('unlock')) {
            if (PulsePrService::pinThrottled($token)) { $assign['error'] = 'Too many attempts. Wait fifteen minutes and try again.'; }
            elseif (PulsePrService::checkPin((string) Tools::getValue('pin'), (string) $emp['payslip_pin'])) {
                PulsePrService::pinClear($token);
                PulsePrPayslip::stampViewed((int) $this->slip['id_pulse_pr_payslip']);
                PulsePrService::log((int) $this->slip['id_pulse_pr_run'], 'payslip_view', 'payslip', array('ip' => Tools::getRemoteAddr()), (int) $this->slip['id_pulse_pr_payslip']);
                $doc = PulsePrPayslip::document((int) $this->slip['id_pulse_pr_payslip']);
                if (isset($doc['employee']['payslip_pin'])) { unset($doc['employee']['payslip_pin']); }
                $assign['doc'] = $doc; $assign['unlocked'] = true;
                $assign['currency'] = $doc['currency'];
            } else {
                PulsePrService::pinAttempt($token);
                $assign['error'] = 'That PIN is not right. Your PIN is the last four characters of your staff number unless you have changed it.';
            }
        }
        $this->context->smarty->assign($assign);
        $this->setTemplate('payslip.tpl');
    }
}
