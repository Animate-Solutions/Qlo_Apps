<?php
/** HR dashboard: headcount against establishment, who is off, what expires, what somebody has to do today. */
class AdminPulseHrController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('HR'); }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array(
            'd' => PulseHrService::dashboard(),
            'self_url' => self::$currentIndex.'&token='.$this->token,
            'employees_url' => $this->context->link->getAdminLink('AdminPulseHrEmployees'),
            'leave_url' => $this->context->link->getAdminLink('AdminPulseHrLeave'),
            'roster_url' => $this->context->link->getAdminLink('AdminPulseHrRoster'),
            'lifecycle_url' => $this->context->link->getAdminLink('AdminPulseHrLifecycle'),
            'reports_url' => $this->context->link->getAdminLink('AdminPulseHrReports'),
            'ess_url' => $this->context->link->getModuleLink('pulsehr', 'ess', array(), true),
            'fd' => PulseHrService::fd(), 'ta' => PulseHrService::ta(), 'pr' => PulseHrService::pr(), 'kc' => PulseHrService::kc(), 'pos' => PulseHrService::pos(),
        ));
        $this->setTemplate('dashboard.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('reviewPunch')) { PulseHrEss::reviewPunch((int) Tools::getValue('id_punch'), (int) Tools::getValue('accept'), Tools::getValue('review_note')); $this->confirmations[] = $this->l('Punch reviewed'); }
            if (Tools::isSubmit('accrueNow')) { $n = PulseHrLeave::accrueMonth(Tools::getValue('month')); $this->confirmations[] = $this->l('Leave accrued for').' '.$n.' '.$this->l('balances'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
