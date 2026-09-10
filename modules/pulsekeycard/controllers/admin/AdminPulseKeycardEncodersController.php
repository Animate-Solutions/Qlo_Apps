<?php
/** Encoders — register each workstation encoder, store its credentials encrypted, probe it, see what it can do. */
class AdminPulseKeycardEncodersController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Encoders'); }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to change encoders')); }
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array(
            'encoders' => PulseKcEncoder::statusAll(), 'adapters' => PulseKcEncoder::adapters(),
            'locations' => array('front_desk', 'back_office', 'housekeeping', 'security', 'engineering', 'mobile'),
            'protocols' => array('http', 'https', 'tcp', 'local'), 'edit' => (int) Tools::getValue('id_encoder') ? PulseKcEncoder::get((int) Tools::getValue('id_encoder')) : null,
            'agent_port' => (int) PulseKcService::cfg('AGENT_PORT', 7070), 'local_agent' => (int) PulseKcService::cfg('LOCAL_AGENT', 1),
            'stale_hrs' => (int) PulseKcService::cfg('ENCODER_STALE_HRS', 6), 'jobs' => PulseKcService::jobs('queued,failed'),
            'ajax_url' => $this->context->link->getAdminLink('AdminPulseKeycardEncoders'), 'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('encoders.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveEncoder')) {
                $this->assertEdit();
                $id = PulseKcEncoder::save(array(
                    'name' => Tools::getValue('name'), 'adapter' => Tools::getValue('adapter', 'PulseKcAdapterSimulator'), 'location' => Tools::getValue('location', 'front_desk'),
                    'protocol' => Tools::getValue('protocol', 'http'), 'host' => Tools::getValue('host'), 'port' => (int) Tools::getValue('port'),
                    'endpoint' => Tools::getValue('endpoint', '/'), 'encoder_ref' => Tools::getValue('encoder_ref'),
                    'local_only' => (int) Tools::getValue('local_only'), 'test_mode' => (int) Tools::getValue('test_mode'), 'timeout_sec' => (int) Tools::getValue('timeout_sec', 8),
                    'active' => (int) Tools::getValue('active', 1), 'options_json' => Tools::getValue('options_json', ''),
                    'credentials' => array('user' => Tools::getValue('cred_user'), 'password' => Tools::getValue('cred_password'), 'api_key' => Tools::getValue('cred_api_key'),
                        'site_code' => Tools::getValue('cred_site_code'), 'site_id' => Tools::getValue('cred_site_id'), 'installation' => Tools::getValue('cred_installation'), 'hotel_code' => Tools::getValue('cred_hotel_code')),
                ), (int) Tools::getValue('id_encoder'));
                $this->confirmations[] = $this->l('Encoder saved').' (#'.$id.')';
            }
            if (Tools::isSubmit('testEncoder')) {
                $r = PulseKcEncoder::test((int) Tools::getValue('id_encoder'));
                if (!empty($r['ok'])) { $this->confirmations[] = $this->l('Encoder online').' — '.(isset($r['message']) ? $r['message'] : '').' '.(isset($r['firmware']) ? '('.$r['firmware'].')' : ''); }
                else { $this->errors[] = $r['error']; }
            }
            if (Tools::isSubmit('deleteEncoder')) {
                $this->assertEdit();
                $id = (int) Tools::getValue('id_encoder');
                if ((int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_key` WHERE id_pulse_kc_encoder='.$id.' AND status="issued"')) { throw new PrestaShopException($this->l('This encoder still has live keys — deactivate it instead of deleting it')); }
                Db::getInstance()->update('pulse_kc_encoder', array('active' => 0, 'status' => 'disabled', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_encoder='.$id);
                PulseCoreService::audit('pulsekeycard', 'encoder_disable', array('id' => $id), 'pulse_kc_encoder', $id);
                $this->confirmations[] = $this->l('Encoder deactivated');
            }
            if (Tools::isSubmit('runQueue')) { $r = PulseKcService::runQueue(); $this->confirmations[] = sprintf($this->l('Queue run: %d done, %d given up'), $r['done'], $r['failed']); }
        } catch (PulseKcEncoderException $e) { $this->errors[] = $e->userMessage(); }
        catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    protected function json($d) { die(json_encode($d)); }
    public function ajaxProcessTestEncoder() { $this->json(PulseKcEncoder::test((int) Tools::getValue('id_encoder'))); }
    public function ajaxProcessAgentReport() { PulseKcEncoder::markSeen((int) Tools::getValue('id_encoder'), (int) Tools::getValue('ok') === 1, Tools::getValue('error')); $this->json(array('ok' => true)); }
}
