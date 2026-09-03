<?php
class AdminPulseReportsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Reports Dashboard'); }
    public function initContent()
    {
        parent::initContent();
        $bd = PulseCoreService::businessDate(); $preset = Tools::getValue('preset', 'yesterday');
        $ranges = array('yesterday' => array(date('Y-m-d', strtotime($bd.' -1 day')), date('Y-m-d', strtotime($bd.' -1 day'))), 'today' => array($bd, $bd), 'week' => array(date('Y-m-d', strtotime($bd.' -6 days')), $bd), 'mtd' => array(date('Y-m-01', strtotime($bd)), $bd), 'last_month' => array(date('Y-m-01', strtotime($bd.' -1 month')), date('Y-m-t', strtotime($bd.' -1 month'))), 'ytd' => array(date('Y-01-01', strtotime($bd)), $bd), 'custom' => array(Tools::getValue('from', $bd), Tools::getValue('to', $bd)));
        list($from, $to) = isset($ranges[$preset]) ? $ranges[$preset] : $ranges['yesterday'];
        $d = PulseReportData::period($from, $to);
        $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1; $pf = date('Y-m-d', strtotime($from." -$days days")); $pt = date('Y-m-d', strtotime($to." -$days days")); $prev = PulseReportData::period($pf, $pt);
        if (Tools::getValue('export')) { $rows = array(); foreach ($d['revenue']['daily'] as $x) { $rows[] = $x; } header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="pulse-revenue-'.$from.'-'.$to.'.csv"'); $f = fopen('php://output', 'w'); if ($rows) { fputcsv($f, array_keys($rows[0])); foreach ($rows as $r) { fputcsv($f, $r); } } fclose($f); exit; }
        // charts
        $lab = array(); $rooms = array(); $fnb = array(); $oth = array(); foreach ($d['revenue']['daily'] as $x) { $lab[] = date('d', strtotime($x['d'])); $rooms[] = $x['rooms']; $fnb[] = $x['fnb']; $oth[] = $x['other']; }
        $olab = array(); $occ = array(); foreach ($d['occupancy']['daily'] as $x) { $olab[] = date('d', strtotime($x['d'])); $avail = max(1, $x['rooms_total'] - $x['rooms_ooo']); $occ[] = round($x['rooms_occupied'] / $avail * 100); }
        $exl = array(); $exv = array(); foreach ($d['expenses']['daily'] as $x) { $exl[] = date('d', strtotime($x['d'])); $exv[] = $x['total']; }
        $charts = array('revenue' => count($lab) > 1 ? PulseChart::bars($lab, array(array('name' => 'Rooms', 'values' => $rooms), array('name' => 'F&B', 'values' => $fnb), array('name' => 'Other', 'values' => $oth))) : '', 'occupancy' => PulseChart::line($olab, $occ, 680, 160, '#2e86c1', '%'), 'expenses' => count($exl) > 1 ? PulseChart::line($exl, $exv, 680, 160, '#c0392b') : '',
            'rev_mix' => PulseChart::donut(array_map(function ($x) { return array(ucfirst($x['department']), $x['net']); }, $d['revenue']['by_department'])), 'exp_mix' => PulseChart::donut(array_map(function ($k, $v) { return array(ucfirst(str_replace('_', ' ', $k)), $v); }, array_keys($d['expenses']['by_group']), $d['expenses']['by_group'])),
            'pay_mix' => PulseChart::donut(array_map(function ($x) { return array(ucfirst(str_replace('_', ' ', $x['method'])), $x['total']); }, $d['payments']['by_method'])));
        // budget vs actual for the month of $to
        $y = (int) date('Y', strtotime($to)); $m = (int) date('n', strtotime($to)); $budget = array(); foreach (Db::getInstance()->executeS('SELECT line, amount FROM `'._DB_PREFIX_.'pulse_budget` WHERE year='.$y.' AND month='.$m) as $b) { $budget[$b['line']] = (float) $b['amount']; }
        $mtd = PulseReportData::period(date('Y-m-01', strtotime($to)), $to);
        $bva = array(array('Room revenue', $mtd['revenue']['rooms'], isset($budget['room_revenue']) ? $budget['room_revenue'] : null), array('F&B revenue', $mtd['revenue']['fnb'], isset($budget['fnb_revenue']) ? $budget['fnb_revenue'] : null), array('Other revenue', $mtd['revenue']['net'] - $mtd['revenue']['rooms'] - $mtd['revenue']['fnb'], isset($budget['other_revenue']) ? $budget['other_revenue'] : null), array('Total expenses', $mtd['expenses']['total'], isset($budget['total_expenses']) ? $budget['total_expenses'] : null), array('Occupancy %', $mtd['occupancy']['occupancy_pct'], isset($budget['occupancy_pct']) ? $budget['occupancy_pct'] : null), array('ADR', $mtd['occupancy']['adr'], isset($budget['adr']) ? $budget['adr'] : null));
        $this->context->smarty->assign(array('d' => $d, 'prev' => $prev, 'from' => $from, 'to' => $to, 'preset' => $preset, 'charts' => $charts, 'bva' => $bva, 'mtd' => $mtd, 'self_url' => self::$currentIndex.'&token='.$this->token, 'business_date' => $bd, 'cur' => $this->context->currency->sign,
            'links' => array('expenses' => $this->context->link->getAdminLink('AdminPulseExpenses'), 'schedules' => $this->context->link->getAdminLink('AdminPulseReportSchedules'), 'fd' => $this->context->link->getAdminLink('AdminPulseFdReports'))));
        $this->setTemplate('dashboard.tpl');
    }
    public function postProcess()
    {
        if (Tools::isSubmit('previewOwner')) { $r = PulseOwnerSnapshot::build(Tools::getValue('report', 'owner_daily'), Tools::getValue('bd', date('Y-m-d', strtotime(PulseCoreService::businessDate().' -1 day')))); die($r['html']); }
        return parent::postProcess();
    }
}
