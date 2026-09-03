<?php
/** Pulse License — perpetual, subscription and trial licensing for the Pulse suite. @author Animate Solutions Limited */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/PulseLicenseService.php';

class PulseLicense extends Module
{
    public function __construct()
    {
        $this->name = 'pulselicense'; $this->tab = 'administration'; $this->version = '1.0.0'; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore');
        $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse License');
        $this->description = $this->l('Activates and enforces Pulse perpetual, subscription and trial licenses.');
    }

    public function install()
    {
        if (!parent::install() || !$this->registerHook('displayBackOfficeHeader') || !$this->registerHook('actionAdminControllerSetMedia') || !$this->registerHook('displayBackOfficeTop')) { return false; }
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/install.sql'));
        if (!Db::getInstance()->execute($sql)) { return false; }
        $t = new Tab(); $t->class_name = 'AdminPulseLicense'; $t->module = $this->name; $t->id_parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $t->position = 99;
        foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = 'License'; }
        return $t->add();
    }

    public function uninstall()
    {
        if ($id = (int) Tab::getIdFromClassName('AdminPulseLicense')) { $t = new Tab($id); $t->delete(); }
        Db::getInstance()->execute(str_replace('PREFIX_', _DB_PREFIX_, Tools::file_get_contents(dirname(__FILE__).'/sql/uninstall.sql')));
        return parent::uninstall();
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseLicense')); }

    /** Enforcement: runs on every back-office controller before rendering. */
    public function hookActionAdminControllerSetMedia($params)
    {
        $ctrl = $this->context->controller->controller_name;
        if (strpos($ctrl, 'AdminPulse') !== 0) { return; }
        if (!Tools::getValue('ajax')) { PulseLicenseService::heartbeat(); }
        if ($url = PulseLicenseService::gate($ctrl)) { Tools::redirectAdmin($url); }
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (strpos($this->context->controller->controller_name, 'AdminPulse') === 0) { $this->context->controller->addCSS($this->_path.'views/css/license.css'); }
    }

    /** Banner when expiring soon, in grace, over cap or offline. */
    public function hookDisplayBackOfficeTop()
    {
        if (strpos($this->context->controller->controller_name, 'AdminPulse') !== 0) { return ''; }
        $s = PulseLicenseService::status();
        $show = in_array($s['state'], array('grace', 'over_cap')) || ($s['state'] === 'valid' && $s['days_left'] !== null && $s['days_left'] <= 30);
        if (!$show) { return ''; }
        $this->context->smarty->assign(array('lic' => $s, 'lic_url' => $this->context->link->getAdminLink('AdminPulseLicense')));
        return $this->display(__FILE__, 'views/templates/hook/banner.tpl');
    }
}
