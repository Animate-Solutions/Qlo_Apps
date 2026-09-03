<?php
class AdminPulseFdSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Front Desk Settings');
        $states = OrderState::getOrderStates($this->context->language->id); array_unshift($states, array('id_order_state' => 0, 'name' => '— none —'));
        $this->fields_options = array('frontdesk' => array('title' => $this->l('Front Desk'), 'fields' => array(
            'PULSE_FD_CHECKIN_TIME' => array('title' => $this->l('Check-in time'), 'type' => 'text', 'size' => 5),
            'PULSE_FD_CHECKOUT_TIME' => array('title' => $this->l('Check-out time'), 'type' => 'text', 'size' => 5),
            'PULSE_FD_REQUIRE_ID' => array('title' => $this->l('Require guest ID at check-in'), 'type' => 'bool'),
            'PULSE_FD_REQUIRE_INSPECTION' => array('title' => $this->l('Rooms need supervisor inspection after cleaning'), 'type' => 'bool'),
            'PULSE_FD_NO_SHOW_CHARGE' => array('title' => $this->l('Charge first night on no-show'), 'type' => 'bool'),
            'PULSE_FD_LATE_FEE' => array('title' => $this->l('Default late checkout fee'), 'type' => 'text', 'suffix' => $this->context->currency->sign),
            'PULSE_FD_OS_CHECKIN' => array('title' => $this->l('QloApps order state on check-in'), 'type' => 'select', 'list' => $states, 'identifier' => 'id_order_state'),
            'PULSE_FD_OS_CHECKOUT' => array('title' => $this->l('QloApps order state on check-out'), 'type' => 'select', 'list' => $states, 'identifier' => 'id_order_state'),
            'PULSE_FD_LATE_GRACE' => array('title' => $this->l('Late check-out grace (minutes) before auto fee'), 'type' => 'text', 'size' => 5),
            'PULSE_FD_WALKIN_PAYMENT_MODULE' => array('title' => $this->l('Payment module used for desk reservations'), 'type' => 'text', 'desc' => $this->l('e.g. bankwire, wsorder, or a Pulse payment module name')),
            'PULSE_FD_WALKIN_ORDER_STATE' => array('title' => $this->l('Order state for desk reservations'), 'type' => 'select', 'list' => $states, 'identifier' => 'id_order_state'),
        ), 'submit' => array('title' => $this->l('Save'))),
        'comms' => array('title' => $this->l('Guest communications (SMS / WhatsApp)'), 'fields' => array(
            'PULSE_FD_SMS_ADAPTER' => array('title' => $this->l('Adapter class'), 'type' => 'text', 'desc' => 'PulseCommsTermii (default) or your own class implementing PulseCommsAdapterInterface'),
            'PULSE_FD_SMS_API_KEY' => array('title' => 'API key', 'type' => 'text'), 'PULSE_FD_SMS_SENDER' => array('title' => $this->l('Sender ID'), 'type' => 'text'),
            'PULSE_FD_SMS_CHANNEL' => array('title' => $this->l('Channel'), 'type' => 'select', 'list' => array(array('id' => 'sms', 'name' => 'SMS'), array('id' => 'whatsapp', 'name' => 'WhatsApp')), 'identifier' => 'id'),
            'PULSE_FD_PRECHECKIN_DAYS' => array('title' => $this->l('Send pre-check-in link (days before arrival)'), 'type' => 'text', 'size' => 3),
        ), 'submit' => array('title' => $this->l('Save'))),
        'regcard' => array('title' => $this->l('Registration card'), 'fields' => array(
            'PULSE_FD_TERMS_VERSION' => array('title' => $this->l('Terms version'), 'type' => 'text', 'size' => 8), 'PULSE_FD_TERMS_TEXT' => array('title' => $this->l('Terms & conditions text'), 'type' => 'textarea', 'cols' => 80, 'rows' => 6),
        ), 'submit' => array('title' => $this->l('Save'))),
        'pabx' => array('title' => $this->l('PABX / telephone interface'), 'fields' => array(
            'PULSE_FD_PABX_DRIVER' => array('title' => $this->l('Driver class'), 'type' => 'text', 'desc' => 'PulsePabxGeneric (HTTP bridge) or a vendor-specific class'), 'PULSE_FD_PABX_URL' => array('title' => $this->l('Bridge URL'), 'type' => 'text'), 'PULSE_FD_PABX_KEY' => array('title' => $this->l('Shared key'), 'type' => 'text'),
            'PULSE_FD_PABX_MAP' => array('title' => $this->l('Room → extension map (JSON)'), 'type' => 'text', 'desc' => '{"101":"1101"} — leave empty when extension = room number'), 'PULSE_FD_PABX_CODES' => array('title' => $this->l('HK dial codes (JSON)'), 'type' => 'text', 'desc' => '{"1":"vacant_clean","2":"vacant_dirty","3":"vacant_inspected","4":"out_of_order"}'),
        ), 'submit' => array('title' => $this->l('Save'))));
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array('charge_codes' => PulseChargeCode::all(), 'self_url' => self::$currentIndex.'&token='.$this->token, 'business_date' => PulseCoreService::businessDate(), 'cron_token' => Configuration::get('PULSE_FD_CRON_TOKEN'), 'upsells' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_upsell_offer` ORDER BY sort')));
        $this->content .= $this->context->smarty->fetch($this->getTemplatePath().'pulse_fd_settings/charge_codes.tpl');
        $this->context->smarty->assign('content', $this->content);
    }

    public function processUpdateOptions()
    {
        parent::processUpdateOptions();
        PulseCoreService::setting('pulsefrontdesk', 'require_id', (string) (int) Configuration::get('PULSE_FD_REQUIRE_ID'));
        PulseCoreService::setting('pulsefrontdesk', 'require_inspection', (string) (int) Configuration::get('PULSE_FD_REQUIRE_INSPECTION'));
        PulseCoreService::setting('pulsefrontdesk', 'no_show_charge', (string) (int) Configuration::get('PULSE_FD_NO_SHOW_CHARGE'));
        PulseCoreService::setting('pulsefrontdesk', 'checkout_time', Configuration::get('PULSE_FD_CHECKOUT_TIME'));
    }

    public function postProcess()
    {
        if (Tools::isSubmit('saveChargeCode')) {
            $id = (int) Tools::getValue('id_pulse_charge_code'); $cc = $id ? new PulseChargeCode($id) : new PulseChargeCode();
            $cc->code = strtoupper(Tools::getValue('code')); $cc->name = Tools::getValue('name'); $cc->department = Tools::getValue('department'); $cc->default_price = (float) Tools::getValue('default_price'); $cc->tax_rate = (float) Tools::getValue('tax_rate'); $cc->is_payment = (int) Tools::getValue('is_payment'); $cc->active = (int) Tools::getValue('active', 1);
            if ($cc->save()) { $this->confirmations[] = $this->l('Charge code saved'); } else { $this->errors[] = $this->l('Could not save charge code'); }
        }
        if (Tools::isSubmit('saveUpsell')) {
            $id = (int) Tools::getValue('id_pulse_upsell_offer'); $d = array('type' => pSQL(Tools::getValue('type')), 'name' => pSQL(Tools::getValue('name')), 'charge_code' => pSQL(Tools::getValue('charge_code')), 'price_tax_excl' => (float) Tools::getValue('price_tax_excl'), 'per' => pSQL(Tools::getValue('per')), 'min_avail_pct' => (int) Tools::getValue('min_avail_pct'), 'active' => (int) Tools::getValue('active', 1), 'sort' => (int) Tools::getValue('sort'));
            $id ? Db::getInstance()->update('pulse_upsell_offer', $d, 'id_pulse_upsell_offer='.$id) : Db::getInstance()->insert('pulse_upsell_offer', $d); $this->confirmations[] = $this->l('Upsell offer saved');
        }
        if (Tools::isSubmit('setBusinessDate') && Validate::isDate(Tools::getValue('business_date'))) { PulseCoreService::setting('pulsefrontdesk', 'business_date', Tools::getValue('business_date')); $this->confirmations[] = $this->l('Business date set'); }
        return parent::postProcess();
    }
}
