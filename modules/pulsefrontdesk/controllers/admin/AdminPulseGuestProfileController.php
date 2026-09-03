<?php
/** Guest profiles: VIP, company, preferences, blacklist, ID documents, stay history. Lists QloApps customers with stay stats. */
class AdminPulseGuestProfileController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; $this->table = 'customer'; $this->className = 'Customer'; $this->identifier = 'id_customer'; $this->addRowAction('view');
        parent::__construct(); $this->meta_title = $this->l('Guest Profiles');
        $this->_select = 'gp.vip_level, gp.stays, gp.nights, gp.lifetime_revenue, gp.last_stay, gp.blacklisted, comp.name company';
        $this->_join = 'LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=a.id_customer LEFT JOIN `'._DB_PREFIX_.'pulse_company` comp ON comp.id_pulse_company=gp.id_pulse_company';
        $this->fields_list = array(
            'id_customer' => array('title' => 'ID'), 'firstname' => array('title' => $this->l('First name')), 'lastname' => array('title' => $this->l('Last name')), 'email' => array('title' => 'Email'),
            'company' => array('title' => $this->l('Company'), 'havingFilter' => true), 'vip_level' => array('title' => 'VIP', 'havingFilter' => true),
            'stays' => array('title' => $this->l('Stays'), 'havingFilter' => true), 'nights' => array('title' => $this->l('Nights'), 'havingFilter' => true),
            'lifetime_revenue' => array('title' => $this->l('Revenue'), 'type' => 'price', 'havingFilter' => true), 'last_stay' => array('title' => $this->l('Last stay'), 'type' => 'date', 'havingFilter' => true),
            'blacklisted' => array('title' => $this->l('Blacklist'), 'type' => 'bool', 'havingFilter' => true),
        );
    }

    public function initContent()
    {
        if (Tools::getValue('duplicates')) {
            $this->context->smarty->assign(array('dups' => PulseGuestProfile::findDuplicates(), 'self_url' => self::$currentIndex.'&token='.$this->token));
            $this->context->smarty->assign('content', $this->context->smarty->fetch($this->getTemplatePath().'pulse_guest_profile/duplicates.tpl')); return;
        }
        if ($this->display === 'view') {
            $id = (int) Tools::getValue('id_customer');
            $this->context->smarty->assign(array('p' => PulseGuestProfile::get($id), 'companies' => Db::getInstance()->executeS('SELECT id_pulse_company, name FROM `'._DB_PREFIX_.'pulse_company` WHERE active=1 ORDER BY name'), 'self_url' => self::$currentIndex.'&token='.$this->token.'&id_customer='.$id.'&viewcustomer'));
            $this->context->smarty->assign('content', $this->context->smarty->fetch($this->getTemplatePath().'pulse_guest_profile/view.tpl'));
            return;
        }
        parent::initContent();
    }

    public function initToolbar() { parent::initToolbar(); unset($this->toolbar_btn['new']); $this->toolbar_btn['dups'] = array('href' => self::$currentIndex.'&token='.$this->token.'&duplicates=1', 'desc' => $this->l('Find duplicates'), 'icon' => 'process-icon-refresh'); }

    public function postProcess()
    {
        if (Tools::isSubmit('mergeProfiles')) { PulseGuestProfile::merge((int) Tools::getValue('keep'), (int) Tools::getValue('merge')); $this->confirmations[] = $this->l('Profiles merged'); }
        if (Tools::isSubmit('saveProfile')) {
            $prefs = array(); foreach (array('pillow', 'floor', 'smoking', 'newspaper', 'dietary', 'other') as $k) { $prefs[$k] = Tools::getValue('pref_'.$k); }
            PulseGuestProfile::save((int) Tools::getValue('id_customer'), array('vip_level' => Tools::getValue('vip_level'), 'id_pulse_company' => Tools::getValue('id_pulse_company'), 'blacklisted' => Tools::getValue('blacklisted'), 'blacklist_reason' => Tools::getValue('blacklist_reason'), 'nationality' => Tools::getValue('nationality'), 'phone' => Tools::getValue('phone'), 'address' => Tools::getValue('address'), 'notes' => Tools::getValue('notes'), 'preferences' => $prefs));
            $this->confirmations[] = $this->l('Profile saved');
        }
        return parent::postProcess();
    }
}
