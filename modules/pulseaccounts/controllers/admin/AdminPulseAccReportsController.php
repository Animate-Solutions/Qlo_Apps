<?php
/** Reports: trial balance, GL drill-down, USALI P&L, balance sheet, cash flow, budget vs actual, revenue analysis, daily revenue journal. All exportable to CSV. */
class AdminPulseAccReportsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Accounting Reports'); }

    public function initContent()
    {
        parent::initContent();
        $r = Tools::getValue('r', 'tb');
        $from = Tools::getValue('from', date('Y-m-01'));
        $to = Tools::getValue('to', PulseAccService::bd());
        $data = $this->run($r, $from, $to);
        if (Tools::getValue('export')) {
            $rows = $this->flatten($r, $data);
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="'.$r.'-'.$from.'-'.$to.'.csv"');
            die(PulseAccService::toCsv($rows));
        }
        $this->context->smarty->assign(array(
            'r' => $r, 'from' => $from, 'to' => $to, 'data' => $data, 'self_url' => self::$currentIndex.'&token='.$this->token,
            'currency' => $this->context->currency->sign, 'accounts' => PulseAccService::accounts(null, false, true),
            'account_code' => Tools::getValue('account_code', '1210'), 'year' => (int) Tools::getValue('year', date('Y')),
            'month' => Tools::getValue('month') !== false ? (int) Tools::getValue('month') : null, 'date' => Tools::getValue('date', PulseAccService::bd()),
            'periods' => PulseAccService::periods(24), 'business_date' => PulseAccService::bd(),
            'reports' => array('tb' => 'Trial balance', 'gl' => 'General ledger', 'pl' => 'USALI departmental P&L', 'bs' => 'Balance sheet', 'cf' => 'Cash flow (indirect)', 'budget' => 'Budget vs actual', 'rev_dept' => 'Revenue by department', 'rev_code' => 'Revenue by charge code', 'drj' => 'Daily revenue journal'),
            'link_journals' => $this->context->link->getAdminLink('AdminPulseAccJournals'),
        ));
        $this->setTemplate('reports.tpl');
    }

    /** One dispatcher so the CSV export and the screen can never drift apart. */
    protected function run($r, $from, $to)
    {
        switch ($r) {
            case 'gl': return PulseAccReport::generalLedger(Tools::getValue('account_code', '1210'), $from, $to);
            case 'pl': return PulseAccReport::usaliPl($from, $to, (bool) Tools::getValue('compare'));
            case 'bs': return PulseAccReport::balanceSheet($to);
            case 'cf': return PulseAccReport::cashFlow($from, $to);
            case 'budget': return PulseAccReport::budgetVsActual((int) Tools::getValue('year', date('Y')), Tools::getValue('month') ? (int) Tools::getValue('month') : null);
            case 'rev_dept': return PulseAccReport::revenueByDepartment($from, $to);
            case 'rev_code': return PulseAccReport::revenueByChargeCode($from, $to);
            case 'drj': return PulseAccReport::dailyRevenueJournal(Tools::getValue('date', PulseAccService::bd()));
            default: return PulseAccReport::trialBalance($from, $to, (bool) Tools::getValue('include_zero'));
        }
    }

    /** Flatten a report structure into CSV rows. */
    protected function flatten($r, $data)
    {
        if (!is_array($data)) { return array(); }
        switch ($r) {
            case 'tb': return $data['rows'];
            case 'gl': return isset($data['rows']) ? $data['rows'] : array();
            case 'pl':
                $rows = array();
                foreach ($data['departments'] as $k => $d) { $rows[] = array('section' => $d['label'], 'line' => 'Revenue', 'amount' => $d['revenue']); $rows[] = array('section' => $d['label'], 'line' => 'Cost of sales', 'amount' => $d['cost_of_sales']); $rows[] = array('section' => $d['label'], 'line' => 'Payroll', 'amount' => $d['payroll']); $rows[] = array('section' => $d['label'], 'line' => 'Other expense', 'amount' => $d['other']); $rows[] = array('section' => $d['label'], 'line' => 'Departmental profit', 'amount' => $d['profit']); }
                foreach ($data['undistributed']['groups'] as $g) { $rows[] = array('section' => 'Undistributed', 'line' => $g['label'], 'amount' => round($g['total'], 2)); }
                $rows[] = array('section' => 'Summary', 'line' => 'Total revenue', 'amount' => $data['total_revenue']);
                $rows[] = array('section' => 'Summary', 'line' => 'Gross operating profit', 'amount' => $data['gop']);
                $rows[] = array('section' => 'Summary', 'line' => 'Fixed charges', 'amount' => $data['fixed']['total']);
                $rows[] = array('section' => 'Summary', 'line' => 'EBITDA', 'amount' => $data['ebitda']);
                $rows[] = array('section' => 'Summary', 'line' => 'Net profit', 'amount' => $data['net_profit']);
                return $rows;
            case 'bs':
                $rows = array();
                foreach ($data['groups'] as $g) { foreach ($g['rows'] as $x) { $rows[] = array('section' => $g['label'], 'code' => $x['code'], 'name' => $x['name'], 'amount' => $x['amount']); } $rows[] = array('section' => $g['label'], 'code' => '', 'name' => 'Total '.$g['label'], 'amount' => $g['total']); }
                $rows[] = array('section' => 'Equity', 'code' => '', 'name' => 'Result for the year', 'amount' => $data['result_for_year']);
                return $rows;
            case 'cf':
                $rows = array(array('line' => 'Profit for the period', 'amount' => $data['profit']), array('line' => 'Add back depreciation', 'amount' => $data['depreciation']));
                foreach ($data['working_capital'] as $k => $v) { $rows[] = array('line' => 'Movement in '.$k, 'amount' => $v); }
                $rows[] = array('line' => 'Cash from operating activities', 'amount' => $data['operating']);
                $rows[] = array('line' => 'Investing activities', 'amount' => $data['investing']);
                $rows[] = array('line' => 'Financing activities', 'amount' => $data['financing']);
                $rows[] = array('line' => 'Opening cash', 'amount' => $data['opening_cash']);
                $rows[] = array('line' => 'Closing cash', 'amount' => $data['closing_cash']);
                return $rows;
            case 'budget': return isset($data['rows']) ? $data['rows'] : array();
            case 'drj':
                $rows = array();
                foreach ($data['gl'] as $g) { $rows[] = array('section' => 'GL revenue', 'code' => $g['code'], 'name' => $g['name'], 'amount' => $g['amount']); }
                foreach ($data['settlements'] as $s) { $rows[] = array('section' => 'Settlement', 'code' => $s['account_code'], 'name' => $s['account_name'], 'amount' => $s['amount']); }
                $rows[] = array('section' => 'Check', 'code' => '', 'name' => 'GL revenue total', 'amount' => $data['gl_total']);
                $rows[] = array('section' => 'Check', 'code' => '', 'name' => 'Night audit revenue', 'amount' => $data['audit_revenue']);
                $rows[] = array('section' => 'Check', 'code' => '', 'name' => 'Variance', 'amount' => $data['variance_vs_audit']);
                return $rows;
            default: return $data;
        }
    }
}
