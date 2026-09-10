<?php
/** Channel Manager dashboard: per-channel health, queue depth, unmapped inventory, parity summary and production. */
class AdminPulseChannelController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Channel Manager'); }

    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-m-01'));
        $to = Tools::getValue('to', PulseChService::businessDate());
        $this->context->smarty->assign(array(
            'channels' => PulseChService::dashboard(), 'unmapped' => PulseChMapping::unmapped(), 'broken' => PulseChMapping::broken(),
            'queue' => PulseChAri::queue('pending,failed,poison', 0, 40), 'failed_res' => PulseChReservation::listing('failed', 0, 25),
            'recent' => PulseChReservation::listing('delivered,modified,cancelled', 0, 20), 'parity' => PulseChAri::paritySummary(30),
            'production' => PulseChReservation::production($from, $to), 'health' => PulseChLog::health(),
            'errors_log' => PulseChLog::recent(0, 'error', null, 15),
            'from' => $from, 'to' => $to, 'business_date' => PulseChService::businessDate(), 'window' => PulseChService::windowDays(),
            'fd' => PulseChService::fd(), 'cron_token' => Configuration::get('PULSE_CH_CRON_TOKEN'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
            'ari_url' => $this->context->link->getAdminLink('AdminPulseChannelAri'), 'map_url' => $this->context->link->getAdminLink('AdminPulseChannelMapping'),
            'res_url' => $this->context->link->getAdminLink('AdminPulseChannelReservations'), 'log_url' => $this->context->link->getAdminLink('AdminPulseChannelLogs'),
            'set_url' => $this->context->link->getAdminLink('AdminPulseChannelSettings'),
        ));
        $this->setTemplate('dashboard.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('syncNow')) {
                $r = PulseChService::syncAll((int) Tools::getValue('id_channel'));
                $this->confirmations[] = sprintf($this->l('Sync done — %d batch(es) pushed, %d cells, %d reservation(s) pulled, %d delivered, %d failed.'), $r['pushed'], $r['cells'], $r['pulled'], $r['delivered'], $r['failed']);
                if ($r['push_errors']) { $this->warnings[] = sprintf($this->l('%d batch(es) failed and were re-queued with backoff.'), $r['push_errors']); }
            }
            if (Tools::isSubmit('rebuildAri')) { $r = PulseChAri::rebuild((int) Tools::getValue('id_channel')); $this->confirmations[] = sprintf($this->l('ARI rebuilt for %d channel(s), %d cell(s) changed.'), $r['channels'], $r['cells_changed']); }
            if (Tools::isSubmit('requeue')) { PulseChAri::requeue((int) Tools::getValue('id_queue')); $this->confirmations[] = $this->l('Batch queued for the next drain.'); }
            if (Tools::isSubmit('closeOut')) { $n = PulseChAri::closeOut(Tools::getValue('co_from'), Tools::getValue('co_to'), (int) Tools::getValue('id_channel'), (bool) Tools::getValue('co_open')); $this->confirmations[] = sprintf($this->l('%d cell(s) updated.'), $n); }
            if (Tools::isSubmit('testChannel')) {
                $c = PulseChService::channel((int) Tools::getValue('id_channel'));
                if (!$c) { throw new PrestaShopException($this->l('Channel not found')); }
                $r = PulseChService::adapter($c)->testConnection();
                if (!empty($r['ok'])) { PulseChService::health((int) $c['id_pulse_ch_channel'], true); $this->confirmations[] = $c['name'].': '.$this->l('connection ok'); }
                else { PulseChService::health((int) $c['id_pulse_ch_channel'], false, $r['error']); $this->errors[] = $c['name'].': '.$r['error']; }
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
