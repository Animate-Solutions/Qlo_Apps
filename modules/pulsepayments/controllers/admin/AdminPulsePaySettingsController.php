<?php
/** Settings: gateway credentials (masked), capabilities, surcharge and auto-capture policy, terminals, webhook URLs to paste into the gateway dashboard. */
class AdminPulsePaySettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Payment Settings');
        $this->fields_options = array('policy' => array('title' => $this->l('Payment policy'), 'fields' => array(
            'PULSE_PAY_DEFAULT_GATEWAY' => array('title' => $this->l('Preferred gateway'), 'type' => 'text', 'desc' => $this->l('Gateway code (paystack, flutterwave, interswitch, manual). The first active gateway serving the channel is used if this one cannot.')),
            'PULSE_PAY_SURCHARGE_PCT' => array('title' => $this->l('Card surcharge %'), 'type' => 'text', 'desc' => $this->l('0 to absorb the gateway fee. Anything above 0 is added to the guest amount.')),
            'PULSE_PAY_SURCHARGE_VAT_PCT' => array('title' => $this->l('VAT % on the surcharge'), 'type' => 'text'),
            'PULSE_PAY_SURCHARGE_CHANNELS' => array('title' => $this->l('Channels that carry a surcharge'), 'type' => 'text', 'desc' => $this->l('Comma separated: web, desk, pos, portal, link, terminal')),
            'PULSE_PAY_PREAUTH_DAYS' => array('title' => $this->l('Pre-authorisation validity (days)'), 'type' => 'text'),
            'PULSE_PAY_PREAUTH_BUFFER_PCT' => array('title' => $this->l('Suggested hold buffer % over the stay estimate'), 'type' => 'text'),
            'PULSE_PAY_PREAUTH_WARN_HOURS' => array('title' => $this->l('Warn when a hold expires within (hours)'), 'type' => 'text'),
            'PULSE_PAY_AUTOCAPTURE' => array('title' => $this->l('At check-out'), 'type' => 'select', 'list' => array(array('id' => 'warn', 'name' => $this->l('Warn the cashier about an open hold')), array('id' => 'checkout', 'name' => $this->l('Capture the folio balance automatically')), array('id' => 'release', 'name' => $this->l('Release the hold after check-out'))), 'identifier' => 'id'),
            'PULSE_PAY_LINK_HOURS' => array('title' => $this->l('Payment link validity (hours)'), 'type' => 'text'),
            'PULSE_PAY_TERMINAL_TIMEOUT_MIN' => array('title' => $this->l('Terminal request timeout (minutes)'), 'type' => 'text'),
            'PULSE_PAY_FEE_TOLERANCE' => array('title' => $this->l('Fee variance tolerance'), 'type' => 'text', 'desc' => $this->l('Statement fees within this amount of our estimate count as matched.')),
            'PULSE_PAY_BANK_NAME' => array('title' => $this->l('Transfer: bank name'), 'type' => 'text'),
            'PULSE_PAY_BANK_ACCOUNT_NAME' => array('title' => $this->l('Transfer: account name'), 'type' => 'text'),
            'PULSE_PAY_BANK_ACCOUNT' => array('title' => $this->l('Transfer: account number'), 'type' => 'text'),
            'PULSE_PAY_FALLBACK_EMAIL' => array('title' => $this->l('Fallback e-mail for walk-ins'), 'type' => 'text', 'desc' => $this->l('Gateways insist on an e-mail address; this one is used when the guest has none.')),
            'PULSE_PAY_CRON_TOKEN' => array('title' => $this->l('Cron token'), 'type' => 'text'),
        ), 'submit' => array('title' => $this->l('Save'))));
    }

    public function initContent()
    {
        parent::initContent();
        $gws = PulsePayService::gateways();
        foreach ($gws as &$g) {
            $g['secret_masked'] = PulsePayService::masked($g['secret_key']);
            $g['webhook_masked'] = PulsePayService::masked($g['webhook_secret']);
            $g['extra_decoded'] = json_decode(PulsePayService::dec($g['extra']), true);
            if (!is_array($g['extra_decoded'])) { $g['extra_decoded'] = array(); }
            $g['webhook_url'] = PulsePayService::webhookUrl($g['code']);
            $g['caps'] = array_filter(explode(',', $g['capabilities']));
        }
        $this->context->smarty->assign(array('gateways' => $gws, 'terminals' => PulsePayTerminal::terminals(false), 'self_url' => self::$currentIndex.'&token='.$this->token,
            'pay_url' => PulsePayService::baseUrl().'pulse/pay', 'api_url' => PulsePayService::baseUrl().'pulse/api/payments', 'cron_token' => Configuration::get('PULSE_PAY_CRON_TOKEN')));
        $this->content .= $this->context->smarty->fetch($this->getTemplatePath().'pulse_pay_settings/gateways.tpl');
        $this->context->smarty->assign('content', $this->content);
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveGateway')) {
                PulsePayService::saveGateway(Tools::getValue('code'), array(
                    'active' => Tools::getValue('active'), 'test_mode' => Tools::getValue('test_mode'), 'name' => Tools::getValue('name'), 'endpoint' => Tools::getValue('endpoint'),
                    'public_key' => Tools::getValue('public_key'), 'merchant_id' => Tools::getValue('merchant_id'), 'secret_key' => Tools::getValue('secret_key'), 'webhook_secret' => Tools::getValue('webhook_secret'),
                    'channels' => Tools::getValue('channels'), 'fee_percent' => Tools::getValue('fee_percent'), 'fee_flat' => Tools::getValue('fee_flat'), 'fee_cap' => Tools::getValue('fee_cap'), 'fee_flat_waive_below' => Tools::getValue('fee_flat_waive_below'),
                    'extra' => array('product_id' => Tools::getValue('x_product_id'), 'pay_item_id' => Tools::getValue('x_pay_item_id'), 'merchant_code' => Tools::getValue('x_merchant_code'), 'query_url' => Tools::getValue('x_query_url'), 'bank_name' => Tools::getValue('x_bank_name'), 'account_name' => Tools::getValue('x_account_name'), 'account_number' => Tools::getValue('x_account_number'), 'logo_url' => Tools::getValue('x_logo_url'), 'country' => Tools::getValue('x_country')),
                ));
                $this->confirmations[] = $this->l('Gateway saved');
            }
            if (Tools::isSubmit('testGateway')) {
                $code = Tools::getValue('code'); $a = PulsePayService::adapter($code);
                if (!$a) { throw new PrestaShopException($this->l('Adapter not available')); }
                $probe = PulsePayService::createTx(array('gateway' => $code, 'amount' => 100, 'purpose' => 'other', 'channel' => 'desk', 'description' => 'Connection test', 'state' => 'intent', 'ref_prefix' => 'TST'));
                $r = $a->verify($probe['reference'], $probe);
                Db::getInstance()->update('pulse_pay_gateway', array('last_error' => pSQL(Tools::substr(isset($r['error']) ? $r['error'] : '', 0, 255)), 'last_ok_at' => empty($r['error']) ? date('Y-m-d H:i:s') : null), 'code="'.pSQL($code).'"', 0, true);
                Db::getInstance()->update('pulse_pay_transaction', array('state' => 'voided', 'failed_reason' => 'connection test'), 'id_pulse_pay_transaction='.(int) $probe['id_pulse_pay_transaction']);
                $this->confirmations[] = $this->l('Reached ').$code.' — '.$this->l('gateway replied: ').(isset($r['error']) && $r['error'] ? $r['error'] : $this->l('reference unknown, which is the expected answer for a test reference'));
            }
            if (Tools::isSubmit('saveTerminal')) { PulsePayTerminal::save(array('id' => Tools::getValue('id_terminal'), 'code' => Tools::getValue('tcode'), 'label' => Tools::getValue('tlabel'), 'bank' => Tools::getValue('tbank'), 'terminal_id' => Tools::getValue('ttid'), 'merchant_id' => Tools::getValue('tmid'), 'station' => Tools::getValue('tstation'), 'mode' => Tools::getValue('tmode'), 'active' => Tools::getValue('tactive', 1))); $this->confirmations[] = $this->l('Terminal saved'); }
            if (Tools::isSubmit('newCronToken')) { Configuration::updateValue('PULSE_PAY_CRON_TOKEN', Tools::passwdGen(32)); $this->confirmations[] = $this->l('Cron token regenerated'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
