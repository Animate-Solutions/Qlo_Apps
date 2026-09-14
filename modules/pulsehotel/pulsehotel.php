<?php
/** Pulse Hotel — multi-property: the hotel a session works in, who may work in it, and the switcher. */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseHotel extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulseHotelSelect' => 'Choose Hotel', 'AdminPulseHotelAccess' => 'Hotel Access');
    protected $hooks = array('displayBackOfficeHeader', 'actionAdminControllerSetMedia', 'displayBackOfficeTop', 'actionPulseHotelChanged');

    public function __construct()
    {
        $this->name = 'pulsehotel'; $this->tab = 'administration'; $this->version = self::VERSION; $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0; $this->bootstrap = true; $this->dependencies = array('pulsecore'); $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Hotel');
        $this->description = $this->l('Multi-property for the Pulse suite: a hotel is chosen at sign-in, access is granted per employee, every screen and query is scoped to that hotel, and the top bar carries a switcher.');
        $this->confirmUninstall = $this->l('Uninstall Pulse Hotel? Hotel access grants and the session log will be dropped. The id_hotel columns on Pulse tables are left in place.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 170;
        foreach ($this->tabs as $c => $n) {
            if (Tab::getIdFromClassName($c)) { continue; }
            $t = new Tab(); $t->class_name = $c; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++;
            foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; }
            // Tab::add() returns false when it cannot seed the access table, which is the normal case
            // when the module is installed from the command line — there is no employee in context
            // then. The tab row itself is written regardless, so the id is what says whether it worked.
            $t->add();
            if (!$t->id) { return false; }
        }
        foreach ($this->defaults() as $k => $v) { Configuration::updateValue($k, $v); }
        $this->seedAccessFromProfiles();
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) { if ($id = (int) Tab::getIdFromClassName($c)) { $t = new Tab($id); $t->delete(); } }
        foreach (array_keys($this->defaults()) as $k) { Configuration::deleteByName($k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    protected function defaults()
    {
        return array(
            // On a single-property install the picker is pointless: sign in straight into the only hotel.
            'PULSE_HOTEL_SKIP_WHEN_ONE' => 1,
            // Honour QloApps' own profile→hotel table until every employee has been granted explicitly.
            'PULSE_HOTEL_PROFILE_FALLBACK' => 1,
            // Show the hotel name in the top bar even when the person holds only one hotel.
            'PULSE_HOTEL_ALWAYS_SHOW' => 1,
        );
    }

    protected function runSql($f)
    {
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/sql/'.$f.'.sql'));
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { if (!Db::getInstance()->execute($q)) { return false; } }
        return true;
    }

    /** First install on a live site: mirror whatever the profile→hotel table already says, so nobody is locked out. */
    protected function seedAccessFromProfiles()
    {
        $now = date('Y-m-d H:i:s');
        PulseDb::execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_hotel_access`
            (id_employee, id_hotel, is_default, can_switch, date_add, date_upd)
            SELECT e.id_employee, ha.id_hotel, 0, 1, "'.pSQL($now).'", "'.pSQL($now).'"
            FROM `'._DB_PREFIX_.'employee` e
            INNER JOIN `'._DB_PREFIX_.'htl_access` ha ON ha.id_profile = e.id_profile AND ha.access = 1
            INNER JOIN `'._DB_PREFIX_.'htl_branch_info` hbi ON hbi.id = ha.id_hotel');
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseHotelAccess')); }

    public function hookDisplayBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path.'views/css/hotel.css');
    }

    /**
     * The gate. Runs before any Pulse back-office screen renders:
     *  - no hotel granted at all → the session is ended with an explanation, because there is nothing to work on
     *  - nothing chosen yet      → the picker
     *  - the chosen hotel was revoked mid-session → back to the picker
     */
    public function hookActionAdminControllerSetMedia($params)
    {
        $ctrl = $this->context->controller->controller_name;
        if (!PulseHotelContext::enforced($ctrl)) { return; }
        if (Tools::getValue('ajax')) { return; }

        // One hotel and one grant: choose it silently rather than asking a question with one answer.
        if (!PulseHotelContext::id() && (int) Configuration::get('PULSE_HOTEL_SKIP_WHEN_ONE') === 1) {
            $rows = PulseHotelContext::allowed();
            if (count($rows) === 1) { try { PulseHotelContext::set((int) $rows[0]['id_hotel']); } catch (Exception $e) { /* falls through to the gate */ } }
        }

        $to = PulseHotelContext::gate($ctrl);
        if ($to === 'SIGNOUT') {
            $this->context->employee->logout();
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin').'&pulse_no_hotel=1');
        }
        if ($to) { Tools::redirectAdmin($to); }
    }

    /** The switcher, rendered into the admin top bar. */
    public function hookDisplayBackOfficeTop()
    {
        if (strpos($this->context->controller->controller_name, 'AdminPulse') !== 0) { return ''; }
        if ($this->context->controller->controller_name === 'AdminPulseHotelSelect') { return ''; }
        $cur = PulseHotelContext::current();
        if (!$cur && !(int) Configuration::get('PULSE_HOTEL_ALWAYS_SHOW')) { return ''; }
        $this->context->smarty->assign(array(
            'pulse_hotel_current' => $cur,
            'pulse_hotel_list' => PulseHotelContext::allowed(),
            'pulse_hotel_may_switch' => PulseHotelContext::maySwitch(),
            'pulse_hotel_switch_url' => $this->context->link->getAdminLink('AdminPulseHotelSelect'),
            'pulse_hotel_token' => Tools::getAdminTokenLite('AdminPulseHotelSelect'),
        ));
        return $this->display(__FILE__, 'views/templates/admin/switcher.tpl');
    }

    /** A hotel change invalidates anything a module cached for the previous one. */
    public function hookActionPulseHotelChanged($params)
    {
        PulseHotelContext::reset();
    }
}
