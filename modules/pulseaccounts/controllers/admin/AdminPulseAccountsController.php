<?php
/** Accounts dashboard: unposted queue, period status, cash position, AR/AP ageing and the day's revenue journal. */
class AdminPulseAccountsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Accounts'); }

    public function initContent()
    {
        parent::initContent();
        $date = Tools::getValue('date', PulseAccService::bd());
        $this->context->smarty->assign(array(
            'd' => PulseAccService::dashboard($date), 'queue' => PulseAccPosting::queue('pending', 40), 'failed' => PulseAccPosting::queue('failed', 40),
            'counts' => PulseAccPosting::queueCounts(), 'unmapped' => PulseAccService::unmapped(), 'drj' => PulseAccReport::dailyRevenueJournal($date),
            'stop_list' => PulseAccAr::stopList(), 'recent' => PulseAccJournal::search(array(), 15), 'date' => $date,
            'self_url' => self::$currentIndex.'&token='.$this->token, 'currency' => $this->context->currency->sign,
            'link_journals' => $this->context->link->getAdminLink('AdminPulseAccJournals'), 'link_rules' => $this->context->link->getAdminLink('AdminPulseAccSettings'),
            'link_reports' => $this->context->link->getAdminLink('AdminPulseAccReports'), 'link_ar' => $this->context->link->getAdminLink('AdminPulseAccAr'),
            'link_ap' => $this->context->link->getAdminLink('AdminPulseAccAp'), 'link_bank' => $this->context->link->getAdminLink('AdminPulseAccBank'),
            'fd' => PulseAccService::fd(), 'pos' => PulseAccService::pos(), 'inv' => PulseAccService::inv(), 'rpt' => PulseAccService::rpt(),
        ));
        $this->setTemplate('dashboard.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('sweepDay')) { $n = PulseAccPosting::sweep(Tools::getValue('date', PulseAccService::bd())); $this->confirmations[] = sprintf($this->l('%d documents queued'), $n); }
            if (Tools::isSubmit('postNow')) {
                $date = Tools::getValue('date', PulseAccService::bd());
                PulseAccService::ensurePeriods($date, 1);
                PulseAccPosting::sweep($date);
                $r = PulseAccPosting::drain((int) Configuration::get('PULSE_ACC_QUEUE_BATCH'));
                $this->confirmations[] = sprintf($this->l('Posted %d, skipped %d, failed %d'), $r['posted'], $r['skipped'], $r['failed']);
            }
            if (Tools::isSubmit('retryFailed')) { PulseAccPosting::retry((int) Tools::getValue('id_queue') ?: null); $r = PulseAccPosting::drain(200); $this->confirmations[] = sprintf($this->l('Retried — posted %d, failed %d'), $r['posted'], $r['failed']); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
