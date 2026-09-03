<?php
class AdminPulseLaundrySettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Laundry Settings');
        $this->fields_options = array('laundry' => array('title' => $this->l('Laundry'), 'fields' => array(
            'PULSE_LDY_TAX_PCT' => array('title' => $this->l('Tax % on guest laundry'), 'type' => 'text'), 'PULSE_LDY_EXPRESS_PCT' => array('title' => $this->l('Express surcharge %'), 'type' => 'text'), 'PULSE_LDY_SAMEDAY_PCT' => array('title' => $this->l('Same-day surcharge %'), 'type' => 'text'),
            'PULSE_LDY_NORMAL_HRS' => array('title' => $this->l('Normal turnaround (hours)'), 'type' => 'text'), 'PULSE_LDY_EXPRESS_HRS' => array('title' => $this->l('Express turnaround (hours)'), 'type' => 'text'), 'PULSE_LDY_SAMEDAY_HRS' => array('title' => $this->l('Same-day turnaround (hours)'), 'type' => 'text'),
            'PULSE_LDY_CUTOFF' => array('title' => $this->l('Same-day cut-off time'), 'type' => 'text'), 'PULSE_LDY_POST_AT' => array('title' => $this->l('Post to folio when'), 'type' => 'select', 'list' => array(array('id' => 'ready', 'name' => 'Ready'), array('id' => 'delivered', 'name' => 'Delivered')), 'identifier' => 'id'),
        ), 'submit' => array('title' => $this->l('Save'))));
    }
    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array('items' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_laundry_item` ORDER BY category, sort'), 'vendors' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_laundry_vendor`'), 'self_url' => self::$currentIndex.'&token='.$this->token));
        $this->content .= $this->context->smarty->fetch($this->getTemplatePath().'pulse_laundry_settings/pricelist.tpl');
        $this->context->smarty->assign('content', $this->content);
    }
    public function postProcess()
    {
        if (Tools::isSubmit('saveItem')) { $id = (int) Tools::getValue('id_item'); $d = array('name' => pSQL(Tools::getValue('name')), 'category' => pSQL(Tools::getValue('category')), 'price_wash' => (float) Tools::getValue('price_wash'), 'price_dryclean' => (float) Tools::getValue('price_dryclean'), 'price_press' => (float) Tools::getValue('price_press'), 'active' => (int) Tools::getValue('active', 1)); $id ? Db::getInstance()->update('pulse_laundry_item', $d, 'id_pulse_laundry_item='.$id) : Db::getInstance()->insert('pulse_laundry_item', $d); $this->confirmations[] = $this->l('Item saved'); }
        if (Tools::isSubmit('saveVendor')) { $id = (int) Tools::getValue('id_vendor'); $d = array('name' => pSQL(Tools::getValue('vname')), 'contact' => pSQL(Tools::getValue('vcontact')), 'phone' => pSQL(Tools::getValue('vphone')), 'rate_per_kg' => (float) Tools::getValue('vrate'), 'turnaround_hours' => (int) Tools::getValue('vhours')); $id ? Db::getInstance()->update('pulse_laundry_vendor', $d, 'id_pulse_laundry_vendor='.$id) : Db::getInstance()->insert('pulse_laundry_vendor', $d); $this->confirmations[] = $this->l('Vendor saved'); }
        return parent::postProcess();
    }
}
