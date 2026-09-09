<?php
/** Transaction ledger: filter, inspect the raw gateway payload and the call log, and capture / void / refund. */
class AdminPulsePayTransactionsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Transactions'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($ref = Tools::getValue('reference')) {
            $t = PulsePayService::tx($ref);
            if ($t) {
                $t['auth_token_masked'] = $t['auth_token'] ? PulsePayService::masked($t['auth_token']) : '';
                unset($t['auth_token']);
                $this->context->smarty->assign(array('t' => $t, 'logs' => PulsePayService::logs($t['reference']), 'refunds' => PulsePayService::refunds($t['reference']),
                    'children' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE id_parent='.(int) $t['id_pulse_pay_transaction'].' ORDER BY id_pulse_pay_transaction'),
                    'postings' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_posting` WHERE id_pulse_pay_transaction='.(int) $t['id_pulse_pay_transaction']),
                    'self_url' => $self, 'currency' => $this->context->currency->sign));
                return $this->setTemplate('transaction.tpl');
            }
            $this->errors[] = $this->l('No transaction with that reference');
        }
        $f = array('from' => Tools::getValue('from', date('Y-m-01')), 'to' => Tools::getValue('to', PulsePayService::bd()), 'gateway' => Tools::getValue('gateway'), 'state' => Tools::getValue('state'), 'channel' => Tools::getValue('channel'), 'type' => Tools::getValue('type'), 'q' => Tools::getValue('q'));
        $this->context->smarty->assign(array(
            'rows' => PulsePayService::transactions($f), 'f' => $f, 'self_url' => $self, 'gateways' => PulsePayService::gateways(),
            'states' => array('intent', 'awaiting_confirmation', 'authorized', 'captured', 'partially_captured', 'settled', 'refunded', 'partially_refunded', 'voided', 'failed', 'expired'),
            'channels' => array('web', 'desk', 'pos', 'portal', 'link', 'terminal'), 'types' => array('charge', 'preauth', 'capture', 'refund', 'void'),
            'disputes' => Db::getInstance()->executeS('SELECT d.*, t.reference FROM `'._DB_PREFIX_.'pulse_pay_dispute` d LEFT JOIN `'._DB_PREFIX_.'pulse_pay_transaction` t ON t.id_pulse_pay_transaction=d.id_pulse_pay_transaction ORDER BY d.id_pulse_pay_dispute DESC LIMIT 50'),
            'currency' => $this->context->currency->sign,
        ));
        $this->setTemplate('transactions.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('doCapture')) { $r = PulsePayService::capture(Tools::getValue('reference'), (float) Tools::getValue('amount'), array('rrn' => Tools::getValue('rrn'), 'auth_code' => Tools::getValue('auth_code'))); if (empty($r['ok'])) { $this->errors[] = $r['error']; } else { $this->confirmations[] = $this->l('Captured'); } }
            if (Tools::isSubmit('doVoid')) { PulsePayService::voidTx(Tools::getValue('reference'), Tools::getValue('reason')); $this->confirmations[] = $this->l('Voided'); }
            if (Tools::isSubmit('doRefund')) { $r = PulsePayService::refund(Tools::getValue('reference'), (float) Tools::getValue('amount'), Tools::getValue('reason'), (int) $this->context->employee->id); if (empty($r['ok'])) { $this->errors[] = $r['error']; } else { $this->confirmations[] = $this->l('Refunded'); } }
            if (Tools::isSubmit('doVerify')) { $r = PulsePayService::verify(Tools::getValue('reference')); $this->confirmations[] = $this->l('Gateway says: ').(isset($r['state']) ? $r['state'] : '—').(!empty($r['error']) ? ' ('.$r['error'].')' : ''); }
            if (Tools::isSubmit('doConfirmManual')) {
                $tx = PulsePayService::tx(Tools::getValue('reference'));
                if (!$tx) { throw new PrestaShopException($this->l('Unknown reference')); }
                // a second confirmation with a different RRN would key a second posting and double the folio line
                if (in_array($tx['state'], array('captured', 'settled', 'refunded', 'partially_refunded'))) { throw new PrestaShopException($this->l('That payment is already confirmed — void or refund it instead')); }
                $a = PulsePayService::adapter('manual');
                $res = $a->confirm($tx, array('rrn' => Tools::getValue('rrn'), 'auth_code' => Tools::getValue('auth_code'), 'card_last4' => Tools::getValue('card_last4'), 'card_brand' => Tools::getValue('card_brand'), 'bank' => Tools::getValue('bank'), 'method' => Tools::getValue('method', $tx['method']), 'amount' => Tools::getValue('amount') ? (float) Tools::getValue('amount') : $tx['amount']));
                if (empty($res['ok'])) { throw new PrestaShopException($res['error']); }
                $tx = PulsePayService::applyResult($tx, $res);
                PulsePayService::postToLedger($tx, 'capture', (float) $tx['amount_captured']);
                $this->confirmations[] = $this->l('Payment confirmed and posted');
            }
            if (Tools::isSubmit('addDispute')) { PulsePayService::dispute(array('reference' => Tools::getValue('reference'), 'category' => Tools::getValue('category'), 'amount' => (float) Tools::getValue('amount'), 'reason' => Tools::getValue('reason'), 'dispute_ref' => Tools::getValue('dispute_ref'), 'due_at' => Tools::getValue('due_at'))); $this->confirmations[] = $this->l('Dispute logged'); }
            if (Tools::isSubmit('setDispute')) { Db::getInstance()->update('pulse_pay_dispute', array('status' => pSQL(Tools::getValue('status')), 'evidence' => pSQL(Tools::getValue('evidence'), true), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pay_dispute='.(int) Tools::getValue('id_dispute')); $this->confirmations[] = $this->l('Dispute updated'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
