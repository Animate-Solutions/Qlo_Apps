<?php
class AdminPulsePosReportsController extends ModuleAdminController
{
    protected $reports = array('summary' => 'Sales summary', 'outlet' => 'By outlet', 'day' => 'By day', 'hour' => 'By hour', 'category' => 'By category', 'item' => 'By item (with cost & margin)', 'engineering' => 'Menu engineering', 'server' => 'By server', 'payments' => 'Payments by method', 'voids' => 'Voids', 'discounts' => 'Discounts', 'comps' => 'Comps', 'sessions' => 'Cashier sessions (Z)', 'kitchen' => 'Kitchen prep times', 'room' => 'Room charges', 'cogs' => 'Cost of sales', 'waste' => 'Waste');
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('POS Reports'); }
    protected function run($r, $f, $t, $o) { switch ($r) { case 'summary': return array(PulsePosReport::summary($f, $t, $o)); case 'outlet': return PulsePosReport::byOutlet($f, $t); case 'day': return PulsePosReport::byDay($f, $t, $o); case 'hour': return PulsePosReport::byHour($f, $t, $o); case 'category': return PulsePosReport::byCategory($f, $t, $o); case 'item': return PulsePosReport::byItem($f, $t, $o); case 'engineering': return PulsePosReport::menuEngineering($f, $t); case 'server': return PulsePosReport::byServer($f, $t, $o); case 'payments': return PulsePosReport::payments($f, $t, $o); case 'voids': return PulsePosReport::voids($f, $t, $o); case 'discounts': return PulsePosReport::discounts($f, $t, $o); case 'comps': return PulsePosReport::comps($f, $t); case 'sessions': return PulsePosReport::sessions($f, $t); case 'kitchen': return PulsePosReport::kitchenTimes($f, $t); case 'room': return PulsePosReport::roomCharges($f, $t); case 'cogs': return PulsePosInventory::cogs($f, $t); case 'waste': return PulsePosInventory::waste($f, $t); } return array(); }
    public function initContent()
    {
        parent::initContent();
        $r = Tools::getValue('report', 'summary'); $f = Tools::getValue('from', PulsePosService::bd()); $t = Tools::getValue('to', PulsePosService::bd()); $o = (int) Tools::getValue('outlet') ?: null;
        $rows = $this->run($r, $f, $t, $o);
        if (Tools::getValue('export')) { header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="pos-'.$r.'-'.$f.'-'.$t.'.csv"'); die(PulsePosReport::toCsv($rows)); }
        $this->context->smarty->assign(array('reports' => $this->reports, 'report' => $r, 'from' => $f, 'to' => $t, 'outlet' => $o, 'rows' => $rows, 'columns' => $rows ? array_keys($rows[0]) : array(), 'outlets' => Db::getInstance()->executeS('SELECT id_pulse_pos_outlet, name FROM `'._DB_PREFIX_.'pulse_pos_outlet`'), 'self_url' => self::$currentIndex.'&token='.$this->token));
        $this->setTemplate('reports.tpl');
    }
}
