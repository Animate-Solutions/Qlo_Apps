<?php
/** Chart of accounts: the tree, balances per account, add/edit/deactivate, CSV export. */
class AdminPulseAccCoaController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Chart of Accounts'); }

    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-01-01'));
        $to = Tools::getValue('to', PulseAccService::bd());
        if (Tools::getValue('export')) {
            $rows = array();
            foreach (PulseAccService::accounts(null, false) as $a) { $rows[] = array('code' => $a['code'], 'name' => $a['name'], 'type' => $a['type'], 'subtype' => $a['subtype'], 'parent' => $a['parent_code'], 'usali_dept' => $a['usali_dept'], 'normal_balance' => $a['normal_balance'], 'header' => $a['is_header'], 'control_of' => $a['control_of'], 'cashflow' => $a['cashflow'], 'active' => $a['active']); }
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="chart-of-accounts.csv"');
            die(PulseAccService::toCsv($rows));
        }
        $balances = array();
        foreach (Db::getInstance()->executeS('SELECT account_code, ROUND(SUM(debit-credit),2) balance, ROUND(SUM(IF(business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'",debit-credit,0)),2) period_movement, COUNT(*) lines FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE posted=1 GROUP BY account_code') as $b) { $balances[$b['account_code']] = $b; }
        $this->context->smarty->assign(array(
            'tree' => PulseAccService::tree(), 'accounts' => PulseAccService::accounts(null, false), 'balances' => $balances,
            'edit' => Tools::getValue('id_account') ? PulseAccService::accountById((int) Tools::getValue('id_account')) : null,
            'from' => $from, 'to' => $to, 'self_url' => self::$currentIndex.'&token='.$this->token, 'currency' => $this->context->currency->sign,
            'link_gl' => $this->context->link->getAdminLink('AdminPulseAccReports'),
            'types' => array('asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'revenue' => 'Revenue', 'expense' => 'Expense'),
            'depts' => array('rooms' => 'Rooms', 'fnb' => 'Food & Beverage', 'other_operated' => 'Other operated', 'undistributed' => 'Undistributed', 'fixed_charges' => 'Fixed charges', 'non_operating' => 'Non-operating', 'balance_sheet' => 'Balance sheet'),
            'flows' => array('none' => 'None', 'cash' => 'Cash & equivalents', 'operating' => 'Operating', 'investing' => 'Investing', 'financing' => 'Financing'),
            'controls' => array('' => '—', 'guest_ledger' => 'Guest ledger', 'city_ledger' => 'City ledger (AR)', 'ap' => 'Trade payables', 'grn_accrual' => 'GRN accrual', 'vat_output' => 'VAT output', 'vat_input' => 'VAT input', 'wht_payable' => 'WHT payable', 'wht_receivable' => 'WHT receivable', 'bank' => 'Bank / cash', 'inventory' => 'Inventory', 'fixed_asset' => 'Fixed assets', 'deposit' => 'Advance deposits', 'consumption_tax' => 'Consumption tax'),
        ));
        $this->setTemplate('coa.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveAccount')) {
                $id = PulseAccService::saveAccount(array(
                    'id_pulse_acc_account' => (int) Tools::getValue('id_pulse_acc_account'), 'code' => Tools::getValue('code'), 'name' => Tools::getValue('name'),
                    'type' => Tools::getValue('type'), 'subtype' => Tools::getValue('subtype'), 'parent_code' => Tools::getValue('parent_code'),
                    'usali_dept' => Tools::getValue('usali_dept'), 'is_header' => Tools::getValue('is_header'), 'is_control' => Tools::getValue('is_control'),
                    'control_of' => Tools::getValue('control_of'), 'cashflow' => Tools::getValue('cashflow'), 'is_contra' => Tools::getValue('is_contra'),
                    'active' => Tools::getValue('active', 1), 'sort' => Tools::getValue('sort'), 'note' => Tools::getValue('note'),
                ));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_account='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('removeAccount')) { $r = PulseAccService::deactivateAccount((int) Tools::getValue('id_account_s')); $this->confirmations[] = $r === 'deleted' ? $this->l('Account deleted') : $this->l('Account has postings — deactivated instead'); }
            if (Tools::isSubmit('toggleAccount')) { $a = PulseAccService::accountById((int) Tools::getValue('id_account_s')); if ($a) { Db::getInstance()->update('pulse_acc_account', array('active' => $a['active'] ? 0 : 1), 'id_pulse_acc_account='.(int) $a['id_pulse_acc_account']); $this->confirmations[] = $this->l('Account updated'); } }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
