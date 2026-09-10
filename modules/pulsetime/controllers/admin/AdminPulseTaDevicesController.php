<?php
/** Devices — the fleet register, per-device credentials, health, the push-endpoint traffic log and the ADMS command queue. */
class AdminPulseTaDevicesController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Devices'); }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to change devices')); }
    }

    public function initContent()
    {
        parent::initContent();
        $edit = (int) Tools::getValue('id_device') ? PulseTaDevice::get((int) Tools::getValue('id_device')) : null;
        if ($edit) { unset($edit['credentials_enc']); }
        $this->context->smarty->assign(array(
            'devices' => PulseTaDevice::statusAll(), 'adapters' => PulseTaDevice::adapters(), 'defaults' => $this->allDefaults(),
            'edit' => $edit, 'departments' => PulseTaService::departments(),
            'traffic' => PulseTaDevice::traffic((int) Tools::getValue('id_device') ?: null, 60),
            'commands' => (int) Tools::getValue('id_device') ? PulseTaDevice::commands((int) Tools::getValue('id_device'), 40) : array(),
            'push_url' => PulseTaService::pushUrl(), 'push_enabled' => PulseTaAdms::enabled() ? 1 : 0,
            'require_key' => (int) PulseTaService::cfg('PUSH_REQUIRE_KEY', 0), 'key_param' => PulseTaService::cfg('PUSH_KEY_PARAM', 'pushkey'),
            'push_key' => (string) PulseCoreService::setting('pulsetime', 'push_key'),
            'jobs' => PulseTaService::jobs('queued,failed', 60), 'selftest' => $this->selftest,
            'timezones' => array('Africa/Lagos', 'Africa/Accra', 'Africa/Nairobi', 'Africa/Johannesburg', 'UTC', 'Europe/London'),
            'protocols' => array('tcp', 'udp', 'http', 'https', 'local', 'file'),
            'settings_url' => $this->context->link->getAdminLink('AdminPulseTaSettings'),
            'enrolment_url' => $this->context->link->getAdminLink('AdminPulseTaEnrolment'),
            'ajax_url' => $this->context->link->getAdminLink('AdminPulseTaDevices'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('devices.tpl');
    }

    protected $selftest = null;

    protected function allDefaults()
    {
        $out = array();
        foreach (array_keys(PulseTaDevice::adapters()) as $a) { $out[$a] = PulseTaDevice::adapterDefaults($a); }
        return $out;
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveDevice')) {
                $this->assertEdit();
                $id = PulseTaDevice::save(array(
                    'name' => Tools::getValue('name'), 'adapter' => Tools::getValue('adapter', 'PulseTaSimulator'), 'brand' => Tools::getValue('brand'),
                    'location' => Tools::getValue('location'), 'department' => Tools::getValue('department', ''), 'mode' => Tools::getValue('mode'),
                    'protocol' => Tools::getValue('protocol'), 'host' => Tools::getValue('host'), 'port' => (int) Tools::getValue('port'),
                    'endpoint' => Tools::getValue('endpoint'), 'serial' => Tools::getValue('serial'), 'timezone' => Tools::getValue('timezone'),
                    'direction_mode' => Tools::getValue('direction_mode'), 'poll_interval_min' => (int) Tools::getValue('poll_interval_min'),
                    'timeout_sec' => (int) Tools::getValue('timeout_sec'), 'retries' => (int) Tools::getValue('retries'),
                    'clear_after_pull' => (int) Tools::getValue('clear_after_pull'), 'test_mode' => (int) Tools::getValue('test_mode'),
                    'status' => Tools::getValue('status', 'pending'), 'note' => Tools::getValue('note'), 'options_json' => Tools::getValue('options_json', ''),
                    'credentials' => array('user' => Tools::getValue('cred_user'), 'password' => Tools::getValue('cred_password'),
                        'comm_key' => Tools::getValue('cred_comm_key'), 'api_key' => Tools::getValue('cred_api_key'),
                        'api_secret' => Tools::getValue('cred_api_secret'), 'push_key' => Tools::getValue('cred_push_key')),
                ), (int) Tools::getValue('id_device_save'));
                $this->confirmations[] = $this->l('Device saved').' (#'.$id.')';
            }
            if (Tools::isSubmit('testDevice')) {
                $r = PulseTaDevice::test((int) Tools::getValue('id_device_act'));
                if (!empty($r['ok'])) { $this->confirmations[] = $this->l('Device online').' — '.(isset($r['message']) ? $r['message'] : '').(isset($r['firmware']) && $r['firmware'] ? ' ('.$r['firmware'].')' : ''); }
                else { $this->errors[] = $r['error']; }
            }
            if (Tools::isSubmit('pollDevice')) {
                $r = PulseTaDevice::poll((int) Tools::getValue('id_device_act'), true);
                if (!empty($r['ok'])) { $this->confirmations[] = sprintf($this->l('Pulled %d punch(es), %d new.'), $r['pulled'], $r['stored']); }
                else { $this->errors[] = $r['error']; }
            }
            if (Tools::isSubmit('pollAll')) {
                $r = PulseTaDevice::pollAll(true);
                $this->confirmations[] = sprintf($this->l('Polled %d device(s): %d punch(es) read, %d new.'), $r['devices'], $r['pulled'], $r['stored']);
                foreach (array_slice($r['errors'], 0, 6) as $e) { $this->errors[] = $e; }
            }
            if (Tools::isSubmit('syncTime')) {
                $this->assertEdit();
                $r = PulseTaService::runAdapter((int) Tools::getValue('id_device_act'), 'syncTime');
                $this->confirmations[] = $this->l('Clock set').(isset($r['before']) && $r['before'] ? ' — device was '.$r['before'].', now '.$r['after'] : '').(isset($r['note']) ? ' '.$r['note'] : '');
            }
            if (Tools::isSubmit('clearLog')) {
                $this->assertEdit();
                if (Tools::getValue('confirm_clear') !== 'CLEAR') { throw new PrestaShopException($this->l('Type CLEAR to confirm — this erases the attendance log on the device itself.')); }
                $r = PulseTaService::runAdapter((int) Tools::getValue('id_device_act'), 'clearLog');
                PulseTaService::audit('device_clear_log', array('id_device' => (int) Tools::getValue('id_device_act')), 'pulse_ta_device', (int) Tools::getValue('id_device_act'));
                $this->confirmations[] = $this->l('Device log cleared.').(isset($r['note']) ? ' '.$r['note'] : '');
            }
            if (Tools::isSubmit('claimDevice')) {
                $this->assertEdit();
                PulseTaDevice::claim((int) Tools::getValue('id_device_act'), Tools::getValue('claim_name', ''), Tools::getValue('claim_location', ''), Tools::getValue('claim_department', ''));
                $this->confirmations[] = $this->l('Device claimed — it will start delivering punches on its next call-in.');
            }
            if (Tools::isSubmit('blockDevice')) { $this->assertEdit(); PulseTaDevice::block((int) Tools::getValue('id_device_act'), Tools::getValue('block_reason', 'Blocked by an administrator')); $this->confirmations[] = $this->l('Device blocked. It can no longer deliver punches.'); }
            if (Tools::isSubmit('reconcileDevice')) {
                $this->assertEdit();
                $r = PulseTaEnrolment::reconcileDevice((int) Tools::getValue('id_device_act'));
                $this->confirmations[] = sprintf($this->l('%d user(s) on the device, %d newly discovered, %d unmapped, %d missing from the device.'), $r['on_device'], $r['discovered'], $r['unmapped'], count($r['missing_from_device']));
            }
            if (Tools::isSubmit('queueCmd')) {
                $this->assertEdit();
                $cmd = trim((string) Tools::getValue('adms_cmd'));
                if ($cmd === '') { throw new PrestaShopException($this->l('Enter the ADMS command to queue')); }
                $id = PulseTaAdms::queue((int) Tools::getValue('id_device_act'), $cmd, 'manual');
                $this->confirmations[] = sprintf($this->l('Command #%d queued — the device collects it on its next call-in.'), $id);
            }
            if (Tools::isSubmit('runSelfTest')) { $this->selftest = PulseTaZkProtocol::selfTest(); $this->confirmations[] = sprintf($this->l('ZK protocol self-test: %d passed, %d failed.'), $this->selftest['passed'], $this->selftest['failed']); }
            if (Tools::isSubmit('runQueue')) { $r = PulseTaService::runQueue(); $this->confirmations[] = sprintf($this->l('Queue: %d done, %d retried, %d given up.'), $r['done'], $r['retried'], $r['failed']); foreach (array_slice($r['errors'], 0, 5) as $e) { $this->errors[] = $e; } }
            if (Tools::isSubmit('rotateKey')) {
                $this->assertEdit();
                $k = Tools::passwdGen(32);
                PulseCoreService::setting('pulsetime', 'push_key', $k);
                PulseTaService::audit('push_key_rotate', array('by' => PulseTaService::emp()));
                $this->confirmations[] = $this->l('Shared push key rotated — set the new key on every push device or they will be refused.').' '.$k;
            }
        } catch (PulseTaDeviceException $e) { $this->errors[] = $e->userMessage(); }
        catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    protected function json($d) { die(json_encode($d)); }
    public function ajaxProcessTestDevice() { $this->json(PulseTaDevice::test((int) Tools::getValue('id_device'))); }
    public function ajaxProcessPollDevice() { $this->json(PulseTaDevice::poll((int) Tools::getValue('id_device'), true)); }
    public function ajaxProcessSelfTest() { $this->json(PulseTaZkProtocol::selfTest()); }
}
