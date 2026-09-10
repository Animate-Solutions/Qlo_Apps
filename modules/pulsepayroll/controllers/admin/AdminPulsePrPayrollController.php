<?php
/** Payroll dashboard, runs, run detail (payslips, variance, bank files, audit trail) and payslip view. */
class AdminPulsePrPayrollController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Payroll'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_payslip')) {
            $this->context->smarty->assign(array('doc' => PulsePrPayslip::document($id), 'self_url' => $self));
            return $this->setTemplate('payslip.tpl');
        }
        if ($id = (int) Tools::getValue('id_run')) {
            $run = PulsePrRun::get($id);
            if (!$run) { $this->errors[] = $this->l('Unknown run'); return $this->setTemplate('runs.tpl'); }
            $this->context->smarty->assign(array(
                'run' => $run, 'payslips' => PulsePrRun::payslips($id), 'by_department' => PulsePrRun::byDepartment($id),
                'variance' => PulsePrRun::variance($id), 'variance_pct' => (float) PulsePrService::cfg('VARIANCE_PCT', 15),
                'verify' => PulsePrRun::verify($id), 'gl' => PulsePrRun::glAggregation($id), 'bank' => PulsePrBankFile::summaryForRun($id),
                'cash_rows' => PulsePrBankFile::cashRowsForRun($id), 'audit' => PulsePrService::auditTrail($id, 60),
                'acc' => PulsePrService::acc(), 'self_url' => $self, 'currency' => $this->context->currency->sign,
                'errors_list' => $run['errors'] ? explode("\n", $run['errors']) : array(),
            ));
            return $this->setTemplate('run.tpl');
        }
        $this->context->smarty->assign(array(
            'dash' => PulsePrRun::dashboard(), 'runs' => PulsePrRun::runs(array(), 40), 'self_url' => $self,
            'departments' => PulsePrService::departments(), 'countries' => PulsePrService::countries(),
            'period' => Tools::getValue('period', date('Y-m')), 'currency' => $this->context->currency->sign,
        ));
        $this->setTemplate('runs.tpl');
    }

    public function postProcess()
    {
        $self = self::$currentIndex.'&token='.$this->token;
        try {
            if (Tools::isSubmit('createRun')) {
                $id = PulsePrRun::create(array('period' => Tools::getValue('period'), 'run_type' => Tools::getValue('run_type'), 'department' => Tools::getValue('department'), 'country' => Tools::getValue('country'), 'pay_date' => Tools::getValue('pay_date'), 'note' => Tools::getValue('note')));
                Tools::redirectAdmin($self.'&id_run='.$id.'&conf=3');
            }
            if (Tools::isSubmit('calcRun')) { $r = PulsePrRun::calculate((int) Tools::getValue('id_run_a')); $this->confirmations[] = sprintf($this->l('Calculated %d payslips'), $r['headcount']).($r['errors'] ? ' — '.count($r['errors']).$this->l(' could not be calculated') : ''); }
            if (Tools::isSubmit('approveRun')) { PulsePrRun::approve((int) Tools::getValue('id_run_a')); $this->confirmations[] = $this->l('Run approved'); }
            if (Tools::isSubmit('reopenRun')) { PulsePrRun::reopen((int) Tools::getValue('id_run_a'), Tools::getValue('reason')); $this->confirmations[] = $this->l('Run reopened'); }
            if (Tools::isSubmit('payRun')) { PulsePrRun::markPaid((int) Tools::getValue('id_run_a'), Tools::getValue('note')); $this->confirmations[] = $this->l('Run marked as paid'); }
            if (Tools::isSubmit('postRun')) { $j = PulsePrRun::post((int) Tools::getValue('id_run_a')); $this->confirmations[] = $j ? sprintf($this->l('Posted to the general ledger (journal %d)'), $j) : $this->l('Pulse Accounts is not installed — the run is complete but nothing was posted'); }
            if (Tools::isSubmit('cancelRun')) { PulsePrRun::cancel((int) Tools::getValue('id_run_a'), Tools::getValue('reason')); $this->confirmations[] = $this->l('Run cancelled'); }
            if (Tools::isSubmit('bankFile')) { $ids = PulsePrBankFile::generateForRun((int) Tools::getValue('id_run_a'), Tools::getValue('split', 'bank'), Tools::getValue('template')); $this->confirmations[] = sprintf($this->l('%d payment file(s) generated'), count($ids)); }
            if (Tools::isSubmit('emailRun')) { $r = PulsePrPayslip::emailRun((int) Tools::getValue('id_run_a')); $this->confirmations[] = sprintf($this->l('%1$d payslips emailed, %2$d failed'), $r['sent'], $r['failed']); }
            if (Tools::isSubmit('emailSlip')) { PulsePrPayslip::email((int) Tools::getValue('id_payslip_a')); $this->confirmations[] = $this->l('Payslip emailed'); }
            if (Tools::isSubmit('reissueSlip')) { PulsePrPayslip::reissueToken((int) Tools::getValue('id_payslip_a')); $this->confirmations[] = $this->l('Download link reissued — the old link no longer works'); }
            if (Tools::isSubmit('voidFile')) { PulsePrBankFile::setStatus((int) Tools::getValue('id_file'), 'void'); $this->confirmations[] = $this->l('Payment file voided'); }
            if (Tools::isSubmit('downloadFile') || Tools::getValue('download_file')) { $this->downloadFile((int) (Tools::getValue('id_file') ? Tools::getValue('id_file') : Tools::getValue('download_file'))); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    /** Stream a stored payment file and mark it downloaded, so the audit trail shows who took it to the bank. */
    protected function downloadFile($id)
    {
        $f = PulsePrBankFile::file($id);
        if (!$f) { $this->errors[] = $this->l('Unknown payment file'); return; }
        PulsePrBankFile::setStatus($id, 'downloaded');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$f['filename'].'"');
        die($f['body']);
    }
}
