<?php
/** Channel registry (endpoints, credentials, commission, allotment) and the module-wide settings. */
class AdminPulseChannelSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Channel Settings');
        $this->fields_options = array('channel' => array('title' => $this->l('Channel manager'), 'fields' => array(
            'PULSE_CH_ARI_DAYS' => array('title' => $this->l('ARI window (days ahead)'), 'type' => 'text'),
            'PULSE_CH_MIN_LOS' => array('title' => $this->l('Default minimum length of stay'), 'type' => 'text'),
            'PULSE_CH_RELEASE_DAYS' => array('title' => $this->l('Default release days'), 'type' => 'text'),
            'PULSE_CH_OVERSELL_BUFFER' => array('title' => $this->l('Global oversell buffer (rooms)'), 'type' => 'text'),
            'PULSE_CH_OVERBOOK_ACTION' => array('title' => $this->l('When an OTA booking would oversell'), 'type' => 'select', 'list' => array(array('id' => 'accept_flag', 'name' => 'Accept it, flag it and alert the desk'), array('id' => 'queue', 'name' => 'Hold it in the failed queue for manual assignment')), 'identifier' => 'id'),
            'PULSE_CH_BATCH_SIZE' => array('title' => $this->l('Default push batch size (cells)'), 'type' => 'text'),
            'PULSE_CH_MAX_ATTEMPTS' => array('title' => $this->l('Retries before a batch is poisoned'), 'type' => 'text'),
            'PULSE_CH_BACKOFF_BASE' => array('title' => $this->l('Backoff base (minutes ^ attempt)'), 'type' => 'text'),
            'PULSE_CH_HTTP_TIMEOUT' => array('title' => $this->l('Default HTTP timeout (seconds)'), 'type' => 'text'),
            'PULSE_CH_ALERT_MINUTES' => array('title' => $this->l('Alert after a channel has failed for (minutes)'), 'type' => 'text'),
            'PULSE_CH_ALERT_EMAIL' => array('title' => $this->l('Alert email'), 'type' => 'text'),
            'PULSE_CH_ALERT_PHONE' => array('title' => $this->l('Alert phone (SMS via Pulse Comms)'), 'type' => 'text'),
            'PULSE_CH_TAX_PCT' => array('title' => $this->l('VAT % assumed on OTA gross amounts'), 'type' => 'text'),
            'PULSE_CH_PARITY_TOLERANCE' => array('title' => $this->l('Parity drift tolerance (%)'), 'type' => 'text'),
            'PULSE_CH_AUTO_DELIVER' => array('title' => $this->l('Create bookings automatically on arrival'), 'type' => 'bool'),
            'PULSE_CH_PAYMENT_MODULE' => array('title' => $this->l('Payment module used for OTA orders'), 'type' => 'text'),
            'PULSE_CH_LOG_KEEP_DAYS' => array('title' => $this->l('Keep message logs for (days)'), 'type' => 'text'),
            'PULSE_CH_CRON_TOKEN' => array('title' => $this->l('Cron token'), 'type' => 'text'),
            'PULSE_CH_API_SECRET' => array('title' => $this->l('Inbound webhook shared secret (HMAC)'), 'type' => 'text'),
        ), 'submit' => array('title' => $this->l('Save'))));
    }

    public function initContent()
    {
        parent::initContent();
        $edit = null; $creds = array();
        if (($id = (int) Tools::getValue('id_channel'))) { $edit = PulseChService::channel($id); if ($edit) { $creds = PulseChService::credentials($edit); } }
        $this->context->smarty->assign(array(
            'channels' => PulseChService::channels(), 'edit' => $edit, 'has_creds' => (bool) $creds, 'cred_keys' => implode(', ', array_keys($creds)),
            'adapters' => array('PulseChAdapterGeneric' => 'Generic JSON contract (partner / intermediary)', 'PulseChAdapterHttp' => 'OTA HTTP (OpenTravel XML or JSON)', 'PulseChAdapterCsv' => 'CSV / e-mail file drop'),
            'rate_plans' => PulseChService::ratePlans(false),
            'cron_token' => Configuration::get('PULSE_CH_CRON_TOKEN'),
            'shop_url' => Tools::getShopDomainSsl(true).__PS_BASE_URI__,
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->content .= $this->context->smarty->fetch($this->getTemplatePath().'pulse_channel_settings/channels.tpl');
        $this->context->smarty->assign('content', $this->content);
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveChannel')) {
                $id = PulseChService::saveChannel(array(
                    'id_pulse_ch_channel' => (int) Tools::getValue('id_pulse_ch_channel'), 'code' => Tools::getValue('c_code'), 'name' => Tools::getValue('c_name'), 'adapter' => Tools::getValue('c_adapter'),
                    'endpoint' => Tools::getValue('c_endpoint'), 'pull_endpoint' => Tools::getValue('c_pull'), 'ack_endpoint' => Tools::getValue('c_ack'),
                    'auth_type' => Tools::getValue('c_auth'), 'auth_header' => Tools::getValue('c_auth_header'), 'hotel_code' => Tools::getValue('c_hotel_code'),
                    'currency_iso' => Tools::getValue('c_currency'), 'commission_pct' => Tools::getValue('c_commission'), 'payload_format' => Tools::getValue('c_format'),
                    'payload_template' => Tools::getValue('c_template'), 'sync_mode' => Tools::getValue('c_mode'), 'push_window_days' => Tools::getValue('c_window'),
                    'allotment' => Tools::getValue('c_allot'), 'oversell_buffer' => Tools::getValue('c_buffer'), 'release_days' => Tools::getValue('c_release'),
                    'batch_size' => Tools::getValue('c_batch'), 'timeout_sec' => Tools::getValue('c_timeout'),
                    'csv_in_dir' => Tools::getValue('c_csv_in'), 'csv_out_dir' => Tools::getValue('c_csv_out'),
                    'enabled' => Tools::getValue('c_enabled'), 'test_mode' => Tools::getValue('c_test'), 'auto_deliver' => Tools::getValue('c_auto'), 'notes' => Tools::getValue('c_notes'),
                ), array('username' => Tools::getValue('c_user'), 'password' => Tools::getValue('c_pass'), 'api_key' => Tools::getValue('c_key'), 'secret' => Tools::getValue('c_secret')));
                $this->confirmations[] = $this->l('Channel saved.');
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_channel='.$id.'&conf=4');
            }
            if (Tools::isSubmit('toggleChannel')) {
                $c = PulseChService::channel((int) Tools::getValue('id_channel_t'));
                if ($c) { Db::getInstance()->update('pulse_ch_channel', array('enabled' => $c['enabled'] ? 0 : 1, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_channel='.(int) $c['id_pulse_ch_channel']); $this->confirmations[] = $c['name'].($c['enabled'] ? ' '.$this->l('disabled') : ' '.$this->l('enabled')); }
            }
            if (Tools::isSubmit('testChannel')) {
                $c = PulseChService::channel((int) Tools::getValue('id_channel_t'));
                if (!$c) { throw new PrestaShopException($this->l('Channel not found')); }
                $r = PulseChService::adapter($c)->testConnection();
                if (!empty($r['ok'])) { PulseChService::health((int) $c['id_pulse_ch_channel'], true); $this->confirmations[] = $c['name'].': '.$this->l('connection ok'); }
                else { PulseChService::health((int) $c['id_pulse_ch_channel'], false, $r['error']); $this->errors[] = $c['name'].': '.$r['error']; }
            }
            if (Tools::isSubmit('deleteChannel')) {
                $id = (int) Tools::getValue('id_channel_t');
                foreach (array('pulse_ch_ari', 'pulse_ch_queue', 'pulse_ch_mapping') as $t) { Db::getInstance()->delete($t, 'id_pulse_ch_channel='.$id); }
                Db::getInstance()->delete('pulse_ch_channel', 'id_pulse_ch_channel='.$id);
                $this->confirmations[] = $this->l('Channel removed. Delivered reservations and logs are kept for audit.');
            }
            if (Tools::isSubmit('rotateSecret')) { Configuration::updateValue('PULSE_CH_API_SECRET', Tools::passwdGen(48)); $this->confirmations[] = $this->l('Webhook secret rotated — give partners the new value.'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
