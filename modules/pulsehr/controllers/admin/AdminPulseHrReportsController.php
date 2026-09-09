<?php
/** HR reports with a CSV export on every one: headcount vs establishment, turnover, leavers, absence, expiry, leave liability, service bands, pay basis and labour hours per occupied room. */
class AdminPulseHrReportsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('HR Reports'); }

    protected function rows($report, $from, $to)
    {
        switch ($report) {
            case 'turnover': return PulseHrReport::turnover($from, $to);
            case 'leavers': return PulseHrReport::leavers($from, $to);
            case 'absence': $a = PulseHrReport::absence($from, $to); return $a['rows'];
            case 'expiry': return PulseHrReport::expiryDashboard((int) Tools::getValue('days', 45));
            case 'liability': $l = PulseHrLeave::liability((int) Tools::getValue('year', date('Y'))); return $l['rows'];
            case 'service': return PulseHrReport::service();
            case 'paybasis': return PulseHrReport::payBasisOn(Tools::getValue('on_date', $to), Tools::getValue('department'));
            case 'labour': $x = PulseHrReport::labourPerOccupiedRoom($from, $to, Tools::getValue('department')); return $x['rows'];
            default: return PulseHrReport::headcount();
        }
    }

    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-m-01'));
        $to = Tools::getValue('to', PulseHrService::bd());
        $report = Tools::getValue('report', 'headcount');
        $this->context->smarty->assign(array(
            'report' => $report, 'from' => $from, 'to' => $to, 'rows' => $this->rows($report, $from, $to),
            'headcount' => PulseHrReport::headcount(), 'absence' => PulseHrReport::absence($from, $to),
            'labour' => PulseHrReport::labourPerOccupiedRoom($from, $to, Tools::getValue('department')),
            'liability' => PulseHrLeave::liability((int) Tools::getValue('year', date('Y'))),
            'departments' => PulseHrService::departments(), 'department' => Tools::getValue('department'),
            'days' => (int) Tools::getValue('days', 45), 'year' => (int) Tools::getValue('year', date('Y')), 'on_date' => Tools::getValue('on_date', $to),
            'fd' => PulseHrService::fd(), 'ta' => PulseHrService::ta(),
            'self_url' => self::$currentIndex.'&token='.$this->token,
            'employee_url' => $this->context->link->getAdminLink('AdminPulseHrEmployees'),
        ));
        $this->setTemplate('reports.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('exportCsv')) {
            $report = Tools::getValue('report', 'headcount');
            $csv = PulseHrReport::toCsv($this->rows($report, Tools::getValue('from', date('Y-m-01')), Tools::getValue('to', PulseHrService::bd())));
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="pulsehr-'.preg_replace('/[^a-z]/', '', $report).'-'.date('Ymd').'.csv"');
            die($csv);
        }
        return parent::postProcess();
    }
}
