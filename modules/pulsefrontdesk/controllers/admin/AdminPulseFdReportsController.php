<?php
class AdminPulseFdReportsController extends ModuleAdminController
{
    protected $reports = array('occupancy' => 'Occupancy / ADR / RevPAR', 'revenue' => 'Revenue by department', 'payments' => 'Payments by method', 'cashier' => 'Cashier shifts', 'guest_ledger' => 'Guest ledger (open folios)', 'city_ledger' => 'City ledger (companies)', 'housekeeping' => 'Housekeeping productivity', 'forecast' => '14-day forecast');

    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Front Desk Reports'); }

    protected function run($r, $from, $to)
    {
        switch ($r) {
            case 'occupancy': return PulseFdReport::occupancy($from, $to);
            case 'revenue': return PulseFdReport::revenueByDepartment($from, $to);
            case 'payments': return PulseFdReport::paymentsByMethod($from, $to);
            case 'cashier': return PulseFdReport::cashierShifts($from, $to);
            case 'guest_ledger': return PulseFdReport::guestLedger();
            case 'city_ledger': return PulseFdReport::cityLedger();
            case 'housekeeping': return PulseFdReport::housekeeping($from, $to);
            case 'forecast': return PulseFdReport::forecast(14);
        }
        return array();
    }

    public function initContent()
    {
        parent::initContent();
        $r = Tools::getValue('report', 'occupancy'); $from = Tools::getValue('from', date('Y-m-01')); $to = Tools::getValue('to', PulseCoreService::businessDate());
        $rows = $this->run($r, $from, $to);
        if (Tools::getValue('export')) { header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="'.$r.'_'.$from.'_'.$to.'.csv"'); die(PulseFdReport::toCsv($rows)); }
        $this->context->smarty->assign(array('reports' => $this->reports, 'report' => $r, 'from' => $from, 'to' => $to, 'rows' => $rows, 'columns' => $rows ? array_keys($rows[0]) : array(), 'self_url' => self::$currentIndex.'&token='.$this->token));
        $this->setTemplate('reports.tpl');
    }
}
