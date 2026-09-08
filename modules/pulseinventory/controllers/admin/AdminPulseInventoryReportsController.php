<?php
class AdminPulseInventoryReportsController extends ModuleAdminController
{
    protected $reports = array('stock' => 'Stock on hand (all stores)', 'valuation' => 'Valuation by group and store', 'movements' => 'Movements', 'consumption' => 'Consumption by department', 'purchases' => 'Purchases by category', 'reorder' => 'Reorder suggestions', 'expiry' => 'Expiring batches', 'slow' => 'Slow-moving stock', 'waste' => 'Waste', 'counts' => 'Count variances', 'suppliers' => 'Supplier performance', 'minibar' => 'Minibar consumption', 'cpor' => 'Cost per occupied room', 'card' => 'Stock card (choose item)');
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Inventory Reports'); }
    protected function runReport($r, $f, $t, $item) { switch ($r) { case 'stock': return PulseInvService::stockList(); case 'valuation': return PulseInvService::valuation(); case 'movements': return PulseInvService::movements($f, $t); case 'consumption': return PulseInvReport::consumptionByDept($f, $t); case 'purchases': return PulseInvReport::purchasesByCategory($f, $t); case 'reorder': return PulseInvService::reorderSuggestions(); case 'expiry': return PulseInvService::expiring(90); case 'slow': return PulseInvReport::slowMoving(60); case 'waste': return PulseInvReport::waste($f, $t); case 'counts': return PulseInvReport::countVariances($f, $t); case 'suppliers': return PulseInvPurchasing::supplierPerformance($f, $t); case 'minibar': return PulseInvMinibar::consumptionReport($f, $t); case 'cpor': $c = PulseInvMinibar::costPerOccupiedRoom($f, $t); return $c['rows']; case 'card': return $item ? PulseInvReport::stockCard($item, $f, $t) : array(); } return array(); }
    public function initContent()
    {
        parent::initContent();
        $r = Tools::getValue('report', 'stock'); $f = Tools::getValue('from', date('Y-m-01')); $t = Tools::getValue('to', PulseInvService::bd()); $item = (int) Tools::getValue('item');
        $rows = $this->runReport($r, $f, $t, $item);
        if (Tools::getValue('export')) { header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="inventory-'.$r.'-'.$f.'-'.$t.'.csv"'); die(PulseInvReport::toCsv($rows)); }
        $this->context->smarty->assign(array('reports' => $this->reports, 'report' => $r, 'from' => $f, 'to' => $t, 'item' => $item, 'rows' => $rows, 'columns' => $rows ? array_keys($rows[0]) : array(), 'items' => Db::getInstance()->executeS('SELECT id_pulse_inv_item, name FROM `'._DB_PREFIX_.'pulse_inv_item` WHERE active=1 ORDER BY name'), 'self_url' => self::$currentIndex.'&token='.$this->token));
        $this->setTemplate('reports.tpl');
    }
}
