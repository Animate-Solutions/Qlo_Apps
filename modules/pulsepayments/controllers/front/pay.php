<?php
/** Branded pay page: /pulse/pay?t=<token> (or ?c=<short code>) — choose a method, redirect to the gateway, land on a receipt. */
require_once _PS_MODULE_DIR_.'pulsepayments/classes/autoload.php';

class PulsePaymentsPayModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    protected $link;

    public function init()
    {
        parent::init();
        $this->link = PulsePayLink::byToken(Tools::getValue('t') ? Tools::getValue('t') : Tools::getValue('c'));
    }

    public function postProcess()
    {
        if (!$this->link || !Tools::isSubmit('submitPay')) { return; }
        try {
            $gateway = preg_replace('/[^a-z_]/', '', Tools::getValue('gateway'));
            $method = preg_replace('/[^a-z_]/', '', Tools::getValue('method', 'card'));
            $amount = $this->link['amount_locked'] ? (float) $this->link['amount'] : (float) Tools::getValue('amount');
            $r = PulsePayService::startLinkPayment($this->link, $gateway, $method, $this->returnUrl(), $amount);
            if (empty($r['ok'])) { throw new PrestaShopException($r['error'] ? $r['error'] : 'We could not start that payment. Please try another method.'); }
            $tx = $r['tx'];
            if (!empty($tx['redirect_url'])) { Tools::redirect($tx['redirect_url']); }
            if (!empty($tx['form_action'])) { $this->context->smarty->assign(array('form_action' => $tx['form_action'], 'form_fields' => $tx['form_fields'])); return; }
            $this->context->smarty->assign(array('tx' => $tx, 'instructions' => isset($tx['instructions']) ? $tx['instructions'] : null));
        } catch (Exception $e) { $this->context->smarty->assign('error', $e->getMessage()); }
    }

    public function initContent()
    {
        parent::initContent();
        if (!$this->link) { $this->context->smarty->assign('invalid', true); return $this->setTemplate('pay.tpl'); }
        $status = PulsePayLink::status($this->link['token']);
        // Coming back from the gateway: verify before we tell the guest anything.
        if (Tools::getValue('r') || Tools::getValue('reference') || Tools::getValue('trxref') || Tools::getValue('tx_ref') || Tools::getValue('txnref')) {
            // Only ever verify a reference that belongs to this link — the query string is the guest's to forge.
            $claimed = Tools::getValue('reference') ?: (Tools::getValue('trxref') ?: (Tools::getValue('tx_ref') ?: Tools::getValue('txnref')));
            $ref = Db::getInstance()->getValue('SELECT reference FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE id_pulse_pay_link='.(int) $this->link['id_pulse_pay_link'].($claimed ? ' AND (reference="'.pSQL($claimed).'" OR gateway_ref="'.pSQL($claimed).'")' : '').' ORDER BY id_pulse_pay_transaction DESC');
            if ($ref) {
                $v = PulsePayService::verify($ref);
                $tx = PulsePayService::tx($ref);
                if ($tx && in_array($tx['state'], array('captured', 'settled'))) { $l = PulsePayLink::byToken($this->link['token']); PulsePayLink::credit($l, (float) $tx['amount_captured'], $tx); }
                if ($tx) { unset($tx['auth_token'], $tx['raw'], $tx['idempotency_key'], $tx['customer_email'], $tx['customer_phone']); }
                $this->context->smarty->assign(array('receipt' => $tx, 'verified' => !empty($v['ok']), 'verify_error' => isset($v['error']) ? $v['error'] : null));
                $status = PulsePayLink::status($this->link['token']);
            }
        }
        $gateways = array();
        foreach (PulsePayService::gateways(true) as $g) { if (in_array('link', explode(',', $g['channels'])) || $g['code'] === 'manual') { $gateways[] = array('code' => $g['code'], 'name' => $g['name'], 'test_mode' => (int) $g['test_mode']); } }
        $manual = PulsePayService::adapter('manual');
        $this->context->smarty->assign(array(
            'l' => $this->link, 'status' => $status, 'gateways' => $gateways, 'hotel' => Configuration::get('PS_SHOP_NAME'), 'currency' => $this->context->currency->sign,
            'bank' => $manual ? $manual->instructions(array('amount' => $this->link['amount'], 'reference' => $this->link['short_code'])) : null,
            'usable' => PulsePayLink::usable($this->link), 'outstanding' => round((float) $this->link['amount'] - (float) $this->link['amount_paid'], 2),
        ));
        $this->setTemplate('pay.tpl');
    }

    /** Where the gateway sends the browser back — same page, which then verifies and prints the receipt. */
    protected function returnUrl() { return PulsePayService::baseUrl().'pulse/pay?t='.$this->link['token'].'&r=1'; }
}
