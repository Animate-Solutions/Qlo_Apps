<?php
/** Pulse hub: module status, API tokens, audit log tail. */
class AdminPulseCoreController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Pulse'); }

    public function initContent()
    {
        parent::initContent();
        $mods = array();
        foreach (array('pulsecore', 'pulselicense', 'pulsefrontdesk', 'pulsepos', 'pulsepayments', 'pulsechannel', 'pulsekeycard', 'pulseguestportal', 'pulselaundry', 'pulsemaintenance', 'pulsereports') as $n) {
            $m = Module::getInstanceByName($n);
            $mods[] = array('name' => $n, 'installed' => Module::isInstalled($n), 'enabled' => Module::isEnabled($n), 'version' => $m ? $m->version : '—', 'display' => $m ? $m->displayName : $n);
        }
        $this->context->smarty->assign(array(
            'modules' => $mods,
            'tokens' => Db::getInstance()->executeS('SELECT id_pulse_api_token, label, CONCAT(LEFT(token,8),"…") token_short, scopes, active, date_add FROM `'._DB_PREFIX_.'pulse_api_token` ORDER BY date_add DESC'),
            'new_token' => Tools::getValue('new_token'),
            'audit' => Db::getInstance()->executeS('SELECT a.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_audit` a LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=a.id_employee ORDER BY a.id_pulse_audit DESC LIMIT 50'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
            'license' => (Module::isEnabled('pulselicense') && file_exists(_PS_MODULE_DIR_.'pulselicense/classes/PulseLicenseService.php') && (require_once _PS_MODULE_DIR_.'pulselicense/classes/PulseLicenseService.php')) ? PulseLicenseService::status() : null,
        ));
        $this->setTemplate('dashboard.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('createToken')) {
            $tok = Tools::passwdGen(64, 'ALPHANUMERIC');
            Db::getInstance()->insert('pulse_api_token', array('label' => pSQL(Tools::getValue('label')), 'token' => pSQL($tok), 'scopes' => pSQL(implode(',', (array) Tools::getValue('scopes'))), 'active' => 1, 'date_add' => date('Y-m-d H:i:s')));
            PulseCore::audit('pulsecore', 'token_create', array('label' => Tools::getValue('label')));
            Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&new_token='.$tok);
        }
        if (Tools::isSubmit('revokeToken')) {
            Db::getInstance()->update('pulse_api_token', array('active' => 0), 'id_pulse_api_token='.(int) Tools::getValue('id_token'));
            $this->confirmations[] = $this->l('Token revoked');
        }
        return parent::postProcess();
    }
}
