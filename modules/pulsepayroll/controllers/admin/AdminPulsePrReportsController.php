<?php
/** Payroll reports: register, department cost, statutory schedules, YTD, headcount reconciliation, hospitality KPIs, bank summary. All exportable. */
class AdminPulsePrReportsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('Payroll Reports');
    }

    public function initContent()
    {
        parent::initContent();
        $r = Tools::getValue('r', 'department');
        $p = array(
            'period' => Tools::getValue('period', date('Y-m')),
            'year' => (int) Tools::getValue('year', date('Y')),
            'department' => Tools::getValue('department'),
            'id_run' => (int) Tools::getValue('id_run'),
        );
        if (!$p['id_run']) {
            $p['id_run'] = (int) Db::getInstance()->getValue('SELECT id_pulse_pr_run FROM `' . _DB_PREFIX_ . 'pulse_pr_run` WHERE period="' . pSQL($p['period']) . '" AND status<>"cancelled" ORDER BY FIELD(run_type,"regular") DESC, id_pulse_pr_run DESC');
        }
        $data = PulsePrReport::run($r, $p);
        if (Tools::getValue('export')) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="payroll-' . $r . '-' . $p['period'] . '.csv"');
            die(PulsePrService::toCsv(PulsePrReport::flatten($r, $data)));
        }
        $this->context->smarty->assign(array(
            'r' => $r,
            'p' => $p,
            'data' => $data,
            'self_url' => self::$currentIndex . '&token=' . $this->token,
            'currency' => $this->context->currency->sign,
            'departments' => PulsePrService::departments(),
            'runs' => PulsePrRun::runs(array(), 40),
            'fd' => PulsePrService::fd(),
            'acc' => PulsePrService::acc(),
            'reports' => array(
                'register' => 'Payroll register',
                'department' => 'Department cost summary',
                'variance' => 'Variance against last period',
                'paye' => 'PAYE schedule (state IRS)',
                'pension' => 'Pension schedule (PenCom/PFA)',
                'nsitf' => 'NSITF schedule',
                'itf' => 'ITF annual accrual',
                'nhf' => 'NHF schedule (consented only)',
                'ytd' => 'Year to date per employee',
                'headcount' => 'Headcount reconciliation',
                'kpi' => 'Labour cost per occupied room',
                'loans' => 'Loan book',
                'tronc' => 'Service charge history',
                'bank' => 'Bank payment summary',
            ),
        ));
        $this->setTemplate('reports.tpl');
    }
}
