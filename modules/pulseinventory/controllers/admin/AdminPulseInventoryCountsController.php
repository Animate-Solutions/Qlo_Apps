<?php
class AdminPulseInventoryCountsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Stock Counts'); }
    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array('counts' => Db::getInstance()->executeS('SELECT c.*, st.name store, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_inv_count` c INNER JOIN `'._DB_PREFIX_.'pulse_inv_store` st ON st.id_pulse_inv_store=c.id_pulse_inv_store LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=c.counted_by ORDER BY c.id_pulse_inv_count DESC LIMIT 40'), 'stores' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_store` WHERE active=1'), 'sheet' => ($id = (int) Tools::getValue('id_count')) ? PulseInvCount::sheet($id) : null, 'items' => Db::getInstance()->executeS('SELECT id_pulse_inv_item, name FROM `'._DB_PREFIX_.'pulse_inv_item` WHERE active=1 ORDER BY name'), 'self_url' => self::$currentIndex.'&token='.$this->token));
        $this->setTemplate('counts.tpl');
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('openCount')) { $id = PulseInvCount::open((int) Tools::getValue('id_store'), Tools::getValue('ctype', 'full'), (array) Tools::getValue('cycle_items'), Tools::getValue('cnote')); Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_count='.$id); }
            if (Tools::isSubmit('enterCount')) { PulseInvCount::enter((int) Tools::getValue('id_count'), (array) Tools::getValue('counted')); $this->confirmations[] = $this->l('Counts saved'); }
            if (Tools::isSubmit('postCount')) { $v = PulseInvCount::post((int) Tools::getValue('id_count')); $this->confirmations[] = sprintf($this->l('Count posted — net variance value %s'), $v); }
            if (Tools::isSubmit('cancelCount')) { Db::getInstance()->update('pulse_inv_count', array('status' => 'cancelled'), 'id_pulse_inv_count='.(int) Tools::getValue('id_count').' AND status<>"posted"'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
