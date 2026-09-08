<?php
class AdminPulseInventoryController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Inventory & Stores'); }
    public function initContent()
    {
        parent::initContent();
        $st = (int) Tools::getValue('store') ?: null; $cat = (int) Tools::getValue('cat') ?: null; $from = Tools::getValue('from', date('Y-m-d', strtotime('-7 days'))); $to = Tools::getValue('to', PulseInvService::bd());
        $val = 0; foreach (PulseInvService::valuation() as $v) { $val += (float) $v['value']; }
        $this->context->smarty->assign(array('stock' => PulseInvService::stockList($st, $cat, (bool) Tools::getValue('low')), 'stores' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_store` WHERE active=1'), 'categories' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_category` WHERE active=1 ORDER BY name'), 'store' => $st, 'cat' => $cat, 'low' => (bool) Tools::getValue('low'),
            'valuation' => PulseInvService::valuation(), 'total_value' => $val, 'expiring' => PulseInvService::expiring((int) Configuration::get('PULSE_INV_EXPIRY_DAYS')), 'reorder' => PulseInvService::reorderSuggestions(), 'movements' => PulseInvService::movements($from, $to, $st), 'from' => $from, 'to' => $to,
            'open_requests' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_inv_request` WHERE status IN ("submitted","approved","partially_issued")'), 'open_counts' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_inv_count` WHERE status IN ("open","counted")'),
            'self_url' => self::$currentIndex.'&token='.$this->token, 'links' => array('pur' => $this->context->link->getAdminLink('AdminPulseInventoryPurchasing'), 'cnt' => $this->context->link->getAdminLink('AdminPulseInventoryCounts'), 'mb' => $this->context->link->getAdminLink('AdminPulseInventoryMinibar'), 'rep' => $this->context->link->getAdminLink('AdminPulseInventoryReports'), 'set' => $this->context->link->getAdminLink('AdminPulseInventorySettings')), 'cur' => $this->context->currency->sign, 'types' => array('issue', 'consume', 'waste', 'return', 'production')));
        $this->setTemplate('stock.tpl');
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('quickMove')) { $q = (float) Tools::getValue('qty'); $type = Tools::getValue('mtype'); $sign = in_array($type, array('return', 'production')) ? 1 : -1; PulseInvService::move((int) Tools::getValue('id_item'), (int) Tools::getValue('id_store'), $type, $sign * abs($q), $type === 'production' ? null : null, array('reason' => Tools::getValue('reason'), 'department' => Tools::getValue('department'), 'reference' => Tools::getValue('reference'))); $this->confirmations[] = $this->l('Movement posted'); }
            if (Tools::isSubmit('transfer')) { PulseInvService::transfer((int) Tools::getValue('id_item'), (int) Tools::getValue('from_store'), (int) Tools::getValue('to_store'), abs((float) Tools::getValue('qty')), 'TRF', Tools::getValue('reason')); $this->confirmations[] = $this->l('Transferred'); }
            if (Tools::isSubmit('importLinked')) { $n = PulseInvService::importLinked(); $this->confirmations[] = sprintf($this->l('%d linked items imported from POS / Maintenance'), $n); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
