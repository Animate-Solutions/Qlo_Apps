<?php
/**
 * Pulse Core — part of the Pulse hospitality suite for QloApps 1.7
 *
 * Benchmark: —
 * @author    Animate Solutions Limited
 * @license   Proprietary
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__).'/classes/autoload.php';

class PulseCore extends Module
{
    const VERSION = '0.1.0';

    /** Hooks this module registers (core, custom, and listened). */
    protected $hooksToRegister = array('displayBackOfficeHeader', 'actionAdminControllerSetMedia', 'moduleRoutes', 'actionPulseEvent');

    public function __construct()
    {
        $this->name = 'pulsecore';
        $this->tab = 'administration';
        $this->version = self::VERSION;
        $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');


        parent::__construct();

        $this->displayName = $this->l('Pulse Core');
        $this->description = $this->l('Shared services for the Pulse hospitality suite: settings, event bus, REST API base, audit log.');
        $this->confirmUninstall = $this->l('Uninstall Pulse Core? All of its data tables will be dropped.');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        foreach ($this->hooksToRegister as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }
        return $this->runSql('install') && $this->installTab();
    }

    public function uninstall()
    {
        return $this->uninstallTab() && $this->runSql('uninstall') && parent::uninstall();
    }

    protected function runSql($file)
    {
        $path = dirname(__FILE__).'/sql/'.$file.'.sql';
        if (!file_exists($path)) {
            return true;
        }
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents($path));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) {
            if (!Db::getInstance()->execute($q)) {
                return false;
            }
        }
        return true;
    }

    protected function installTab()
    {
        $tab = new Tab();
        $tab->class_name = 'AdminPulseCore';
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentModulesSf');
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Pulse';
        }
        return $tab->add();
    }

    protected function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminPulseCore');
        if ($idTab) {
            $tab = new Tab($idTab);
            return $tab->delete();
        }
        return true;
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseCore'));
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $this->context->controller->addCSS($this->_path.'views/css/admin.css');
        $this->context->controller->addJS($this->_path.'views/js/admin.js');
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        // TODO: implement actionAdminControllerSetMedia (no-op until built)
    }

    public function hookModuleRoutes($params)
    {
        return array(
            'pulsecore-api' => array(
                'controller' => 'api',
                'rule' => 'pulse/api/core{/:resource}{/:id}',
                'keywords' => array(
                    'resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'),
                    'id' => array('regexp' => '[0-9]+', 'param' => 'id'),
                ),
                'params' => array('fc' => 'module', 'module' => $this->name),
            ),
        );
    }
}
