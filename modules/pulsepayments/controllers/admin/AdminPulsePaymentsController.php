<?php
/** Payments dashboard: today's takings by gateway and channel, open pre-auths with expiry warnings, failures and the terminal queue. */
class AdminPulsePaymentsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Payments'); }

    public function initContent()
    {
        parent::initContent();
        $date = Tools::getValue('bdate', PulsePayService::bd());
        $this->context->smarty->assign(array(
            'd' => PulsePayService::dashboard($date), 'business_date' => $date, 'self_url' => self::$currentIndex.'&token='.$this->token,
            'tx_url' => $this->context->link->getAdminLink('AdminPulsePayTransactions'), 'link_url' => $this->context->link->getAdminLink('AdminPulsePayLinks'),
            'gateways' => PulsePayService::gateways(true), 'terminals' => PulsePayTerminal::terminals(), 'warn_hours' => (int) Configuration::get('PULSE_PAY_PREAUTH_WARN_HOURS'),
            'fd' => PulsePayService::fd(), 'pos' => PulsePayService::pos(), 'events' => PulsePayWebhook::recent(15), 'currency' => $this->context->currency->sign,
        ));
        $this->setTemplate('dashboard.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('capturePre')) { $r = PulsePayService::capture(Tools::getValue('reference'), (float) Tools::getValue('amount'), array('rrn' => Tools::getValue('rrn'), 'auth_code' => Tools::getValue('auth_code'))); if (empty($r['ok'])) { $this->errors[] = $r['error']; } else { $this->confirmations[] = $this->l('Captured'); } }
            if (Tools::isSubmit('voidPre')) { PulsePayService::voidTx(Tools::getValue('reference'), Tools::getValue('reason')); $this->confirmations[] = $this->l('Hold released'); }
            if (Tools::isSubmit('topUpPre')) { PulsePayService::topUp(Tools::getValue('reference'), (float) Tools::getValue('amount'), Tools::getValue('reason')); $this->confirmations[] = $this->l('Hold topped up'); }
            if (Tools::isSubmit('verifyTx')) { $r = PulsePayService::verify(Tools::getValue('reference')); $this->confirmations[] = $this->l('Gateway says: ').(isset($r['state']) ? $r['state'] : '—').(isset($r['error']) && $r['error'] ? ' ('.$r['error'].')' : ''); }
            if (Tools::isSubmit('terminalPush')) { $r = PulsePayTerminal::request(array('amount' => (float) Tools::getValue('amount'), 'terminal' => Tools::getValue('terminal'), 'source' => 'desk', 'id_htl_booking' => (int) Tools::getValue('id_htl_booking') ?: null, 'id_pulse_folio' => (int) Tools::getValue('id_pulse_folio') ?: null, 'auto_settle' => 1, 'description' => Tools::getValue('description'))); $this->confirmations[] = $this->l('Sent to terminal — reference ').$r['reference']; }
            if (Tools::isSubmit('terminalAnswer')) { PulsePayTerminal::result(Tools::getValue('reference'), array('approved' => (int) Tools::getValue('approved'), 'rrn' => Tools::getValue('rrn'), 'auth_code' => Tools::getValue('auth_code'), 'card_last4' => Tools::getValue('card_last4'), 'card_brand' => Tools::getValue('card_brand'), 'message' => Tools::getValue('message'))); $this->confirmations[] = $this->l('Terminal result recorded'); }
            if (Tools::isSubmit('terminalCancel')) { PulsePayTerminal::cancel(Tools::getValue('reference'), $this->l('Cancelled at the desk')); $this->confirmations[] = $this->l('Terminal request cancelled'); }
            if (Tools::isSubmit('rollDaily')) { $n = PulsePayService::rollDaily(Tools::getValue('bdate', PulsePayService::bd())); $this->confirmations[] = sprintf($this->l('Settlement summary rolled (%d rows)'), $n); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
