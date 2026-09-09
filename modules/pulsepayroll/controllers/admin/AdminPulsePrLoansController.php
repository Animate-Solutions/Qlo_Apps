<?php
/** Staff loans and salary advances: application, approval, disbursement, the schedule, and the arrears book. */
class AdminPulsePrLoansController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Loans & Advances'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_loan')) {
            $l = PulsePrLoan::loan($id);
            if (!$l) { $this->errors[] = $this->l('Unknown loan'); return $this->setTemplate('loans.tpl'); }
            $this->context->smarty->assign(array('l' => $l, 'self_url' => $self, 'currency' => $this->context->currency->sign));
            return $this->setTemplate('loan.tpl');
        }
        $f = array('status' => Tools::getValue('status'), 'q' => Tools::getValue('q'));
        $this->context->smarty->assign(array(
            'loans' => PulsePrLoan::loans($f), 'arrears' => PulsePrLoan::arrears(), 'filters' => $f,
            'employees' => PulsePrService::employees(array('status' => 'active,probation,on_leave')),
            'book' => PulsePrReport::loanBook(), 'self_url' => $self, 'currency' => $this->context->currency->sign,
        ));
        $this->setTemplate('loans.tpl');
    }

    public function postProcess()
    {
        $self = self::$currentIndex.'&token='.$this->token;
        try {
            if (Tools::isSubmit('applyLoan')) {
                $id = PulsePrLoan::apply(array('id_pulse_pr_employee' => Tools::getValue('id_pulse_pr_employee'), 'type' => Tools::getValue('type'), 'purpose' => Tools::getValue('purpose'), 'principal' => Tools::getValue('principal'), 'interest_pct' => Tools::getValue('interest_pct'), 'instalments' => Tools::getValue('instalments'), 'first_period' => Tools::getValue('first_period'), 'note' => Tools::getValue('note')));
                Tools::redirectAdmin($self.'&id_loan='.$id.'&conf=3');
            }
            if (Tools::isSubmit('loanStatus')) { PulsePrLoan::setStatus((int) Tools::getValue('id_loan_a'), Tools::getValue('status_to'), Tools::getValue('note')); $this->confirmations[] = $this->l('Loan updated'); }
            if (Tools::isSubmit('rebuildSchedule')) { PulsePrLoan::buildSchedule((int) Tools::getValue('id_loan_a')); $this->confirmations[] = $this->l('Repayment schedule rebuilt'); }
            if (Tools::isSubmit('waiveArrears')) { PulsePrLoan::waiveArrears((int) Tools::getValue('id_arrears'), Tools::getValue('reason')); $this->confirmations[] = $this->l('Arrears waived'); }
            if (Tools::isSubmit('deferInstalment')) { Db::getInstance()->update('pulse_pr_loan_schedule', array('status' => 'deferred'), 'id_pulse_pr_loan_schedule='.(int) Tools::getValue('id_schedule')); PulsePrService::log(null, 'loan_defer', 'loan', null, (int) Tools::getValue('id_loan_a')); $this->confirmations[] = $this->l('Instalment deferred'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
