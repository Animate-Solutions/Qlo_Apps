<?php
class AdminPulseExpensesController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Expenses & Budgets');
        $this->fields_options = array('rpt' => array('title' => $this->l('Expense & alert settings'), 'fields' => array('PULSE_RPT_APPROVAL_LIMIT' => array('title' => $this->l('Expenses above this amount need approval'), 'type' => 'text'), 'PULSE_RPT_VARIANCE_ALERT' => array('title' => $this->l('Alert on cashier variance above'), 'type' => 'text'), 'PULSE_RPT_HIGH_BALANCE' => array('title' => $this->l('Alert on in-house folio balance above'), 'type' => 'text')), 'submit' => array('title' => $this->l('Save'))));
    }
    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-m-01')); $to = Tools::getValue('to', PulseCoreService::businessDate()); $y = (int) Tools::getValue('year', date('Y')); $m = (int) Tools::getValue('month', date('n'));
        $budget = array(); foreach (Db::getInstance()->executeS('SELECT line, amount FROM `'._DB_PREFIX_.'pulse_budget` WHERE year='.$y.' AND month='.$m) as $b) { $budget[$b['line']] = $b['amount']; }
        $this->context->smarty->assign(array('expenses' => PulseExpense::list_($from, $to), 'pending' => PulseExpense::pending(), 'by_category' => PulseExpense::byCategory($from, $to), 'total' => PulseExpense::total($from, $to),
            'categories' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_expense_category` WHERE active=1 ORDER BY group_name, sort'), 'from' => $from, 'to' => $to, 'year' => $y, 'month' => $m, 'budget' => $budget,
            'budget_lines' => array('room_revenue' => 'Room revenue', 'fnb_revenue' => 'F&B revenue', 'other_revenue' => 'Other revenue', 'total_expenses' => 'Total expenses', 'occupancy_pct' => 'Occupancy %', 'adr' => 'ADR'), 'self_url' => self::$currentIndex.'&token='.$this->token, 'cur' => $this->context->currency->sign));
        $this->content .= $this->context->smarty->fetch($this->getTemplatePath().'pulse_expenses/expenses.tpl');
        $this->context->smarty->assign('content', $this->content);
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('addExpense')) { PulseExpense::add(array('id_category' => Tools::getValue('id_category'), 'department' => Tools::getValue('department'), 'description' => Tools::getValue('description'), 'payee' => Tools::getValue('payee'), 'amount' => Tools::getValue('amount'), 'tax_amount' => Tools::getValue('tax_amount'), 'payment_method' => Tools::getValue('payment_method'), 'reference' => Tools::getValue('reference'), 'business_date' => Tools::getValue('business_date') ?: PulseCoreService::businessDate())); $this->confirmations[] = $this->l('Expense recorded'); }
            if (Tools::isSubmit('setExpStatus')) { PulseExpense::setStatus((int) Tools::getValue('id_expense'), Tools::getValue('status')); $this->confirmations[] = $this->l('Expense updated'); }
            if (Tools::isSubmit('syncFeeds')) { $n = PulseExpense::syncFeeds(Tools::getValue('sync_date', PulseCoreService::businessDate())); $this->confirmations[] = sprintf($this->l('%d cost entries pulled from Maintenance / Laundry / Cashier'), $n); }
            if (Tools::isSubmit('saveBudget')) { $y = (int) Tools::getValue('year'); $m = (int) Tools::getValue('month'); foreach ((array) Tools::getValue('b') as $line => $amt) { if ($amt === '') { continue; } Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_budget` (year,month,line,amount) VALUES ('.$y.','.$m.',"'.pSQL($line).'",'.(float) $amt.') ON DUPLICATE KEY UPDATE amount=VALUES(amount)'); } $this->confirmations[] = $this->l('Budget saved'); }
            if (Tools::isSubmit('addCategory')) { Db::getInstance()->insert('pulse_expense_category', array('code' => pSQL(strtoupper(Tools::getValue('ccode'))), 'name' => pSQL(Tools::getValue('cname')), 'group_name' => pSQL(Tools::getValue('cgroup')))); $this->confirmations[] = $this->l('Category added'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
