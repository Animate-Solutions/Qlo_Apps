<?php
class AdminPulseLicenseController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Pulse License'); }

    public function initContent()
    {
        parent::initContent();
        $s = PulseLicenseService::status();
        if (Tools::getValue('blocked')) { $this->errors[] = sprintf($this->l('Pulse is not licensed (%s). Activate a license or start a trial to continue.'), $s['message']); }
        if (Tools::getValue('unlicensed')) { $this->errors[] = sprintf($this->l('The module "%s" is not included in your license.'), Tools::getValue('unlicensed')); }
        $this->context->smarty->assign(array(
            'lic' => $s, 'domain' => PulseLicenseService::domain(), 'fingerprint' => PulseLicenseService::fingerprint(),
            'trial_used' => Configuration::get('PULSE_LICENSE_TRIAL_USED'), 'activated' => Configuration::get('PULSE_LICENSE_ACTIVATED'), 'last_ok' => Configuration::get('PULSE_LICENSE_LAST_OK'),
            'log' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_license_log` ORDER BY id_pulse_license_log DESC LIMIT 20'),
            'modules' => array('pulsefrontdesk' => 'Front Desk', 'pulsepos' => 'F&B POS', 'pulsepayments' => 'Payments', 'pulsechannel' => 'Channel Manager', 'pulsekeycard' => 'Key Cards', 'pulseguestportal' => 'Guest Portal', 'pulselaundry' => 'Laundry', 'pulsemaintenance' => 'Maintenance', 'pulsereports' => 'Reports & Owner Snapshot'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('license.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('activateKey')) { $d = PulseLicenseService::activate(Tools::getValue('license_key')); $this->confirmations[] = sprintf($this->l('License activated for %s'), $d['licensee']); }
            if (Tools::isSubmit('startTrial')) { PulseLicenseService::startTrial(); $this->confirmations[] = $this->l('30-day trial started'); }
            if (Tools::isSubmit('refreshLicense')) { $r = PulseLicenseService::heartbeat(true); $this->confirmations[] = $r ? $this->l('License server confirmed status: ').$r['status'] : $this->l('License server not reachable or not configured for this key'); }
            if (Tools::isSubmit('deactivateKey')) { PulseLicenseService::deactivate(); $this->confirmations[] = $this->l('License removed'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
