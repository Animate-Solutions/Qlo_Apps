<?php
/** Every message in and out, with the full request/response body, plus the 24-hour health table. */
class AdminPulseChannelLogsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Channel Logs'); }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array(
            'channels' => PulseChService::channels(), 'id_channel' => (int) Tools::getValue('id_channel'),
            'status' => Tools::getValue('status'), 'type' => Tools::getValue('type'),
            'log_types' => array('ari_push', 'reservation_pull', 'reservation_in', 'ack', 'test', 'alert', 'api_ari', 'api_auth'),
            'logs' => PulseChLog::recent((int) Tools::getValue('id_channel'), Tools::getValue('status'), Tools::getValue('type'), 300),
            'detail' => Tools::getValue('id_log') ? PulseChLog::one((int) Tools::getValue('id_log')) : null,
            'health' => PulseChLog::health(), 'queue' => PulseChAri::queue(null, (int) Tools::getValue('id_channel'), 100),
            'keep_days' => Configuration::get('PULSE_CH_LOG_KEEP_DAYS'), 'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('logs.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('pruneLogs')) { $n = PulseChService::pruneLogs(); $this->confirmations[] = sprintf($this->l('%d old log row(s) removed.'), $n); }
            if (Tools::isSubmit('requeue')) { PulseChAri::requeue((int) Tools::getValue('id_queue')); $this->confirmations[] = $this->l('Batch re-queued.'); }
            if (Tools::isSubmit('cancelQueue')) { Db::getInstance()->update('pulse_ch_queue', array('status' => 'cancelled', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_queue='.(int) Tools::getValue('id_queue')); $this->confirmations[] = $this->l('Batch cancelled.'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
