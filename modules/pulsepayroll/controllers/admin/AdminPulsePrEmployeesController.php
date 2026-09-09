<?php
/** The payroll roster: pay details, bank details, the pay structure, declarations and consents, timesheets and opening balances. */
class AdminPulsePrEmployeesController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Payroll Employees'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        $period = Tools::getValue('period', date('Y-m'));
        if ($id = (int) Tools::getValue('id_employee_pr')) {
            $e = PulsePrService::employee($id);
            if (!$e) { $this->errors[] = $this->l('Unknown employee'); return $this->setTemplate('list.tpl'); }
            $this->context->smarty->assign(array(
                'e' => $e, 'structure' => PulsePrService::structure($id, PulsePrService::periodTo($period), $e['grade']),
                'structure_rows' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_employee_element` WHERE id_pulse_pr_employee='.$id.' ORDER BY effective_from DESC, element_code'),
                'declarations' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_declaration` WHERE id_pulse_pr_employee='.$id.' ORDER BY date_from DESC'),
                'elements' => PulsePrService::elements(), 'banks' => PulsePrService::banks(),
                'payslips' => PulsePrPayslip::forEmployee($id, 24), 'ytd' => PulsePrPayslip::ytdSummary($id, (int) date('Y')),
                'loans' => PulsePrLoan::loans(array('id_pulse_pr_employee' => $id)), 'balances' => PulsePrLoan::balanceFor($id),
                'tronc' => PulsePrTronc::statement($id), 'arrears' => PulsePrLoan::arrears($id),
                'timesheet' => PulsePrService::timesheet($id, $period, $e['id_hr_employee']),
                'opening' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_opening` WHERE id_pulse_pr_employee='.$id.' ORDER BY tax_year DESC'),
                'countries' => PulsePrService::countries(), 'period' => $period, 'self_url' => $self, 'hr' => PulsePrService::hr(), 'ta' => PulsePrService::ta(),
                'link_payroll' => $this->context->link->getAdminLink('AdminPulsePrPayroll'),
            ));
            return $this->setTemplate('employee.tpl');
        }
        $f = array('q' => Tools::getValue('q'), 'department' => Tools::getValue('department'), 'status' => Tools::getValue('status', 'active,probation,on_leave,suspended'));
        $this->context->smarty->assign(array(
            'employees' => PulsePrService::employees($f), 'departments' => PulsePrService::departments(), 'filters' => $f,
            'countries' => PulsePrService::countries(), 'banks' => PulsePrService::banks(), 'self_url' => $self,
            'hr' => PulsePrService::hr(), 'ta' => PulsePrService::ta(), 'period' => $period,
        ));
        $this->setTemplate('list.tpl');
    }

    public function postProcess()
    {
        $self = self::$currentIndex.'&token='.$this->token;
        try {
            if (Tools::isSubmit('saveEmployee')) {
                $id = PulsePrService::saveEmployee($this->fields());
                Tools::redirectAdmin($self.'&id_employee_pr='.$id.'&conf=3');
            }
            if (Tools::isSubmit('syncHr')) { $n = PulsePrService::syncAllFromHr(); $this->confirmations[] = sprintf($this->l('%d employees synchronised from Pulse HR'), $n); }
            if (Tools::isSubmit('saveStructure')) { PulsePrService::saveStructureLine(array('id_pulse_pr_employee' => (int) Tools::getValue('id_employee_pr'), 'element_code' => Tools::getValue('element_code'), 'amount' => Tools::getValue('amount'), 'percent' => Tools::getValue('percent'), 'units' => Tools::getValue('units'), 'effective_from' => Tools::getValue('effective_from'), 'effective_to' => Tools::getValue('effective_to'), 'note' => Tools::getValue('note'))); $this->confirmations[] = $this->l('Pay structure line added'); }
            if (Tools::isSubmit('deleteStructure')) { PulsePrService::deleteStructureLine((int) Tools::getValue('id_structure')); $this->confirmations[] = $this->l('Pay structure line removed'); }
            if (Tools::isSubmit('saveDeclaration')) { PulsePrService::saveDeclaration(array('id_pulse_pr_employee' => (int) Tools::getValue('id_employee_pr'), 'code' => Tools::getValue('code'), 'annual_value' => Tools::getValue('annual_value'), 'evidence_ref' => Tools::getValue('evidence_ref'), 'evidence_verified' => Tools::getValue('evidence_verified'), 'consented' => Tools::getValue('consented'), 'consent_date' => Tools::getValue('consent_date'), 'consent_channel' => Tools::getValue('consent_channel'), 'date_from' => Tools::getValue('date_from'), 'note' => Tools::getValue('dnote'))); $this->confirmations[] = $this->l('Declaration saved'); }
            if (Tools::isSubmit('endDeclaration')) { PulsePrService::endDeclaration((int) Tools::getValue('id_declaration'), Tools::getValue('date_to')); $this->confirmations[] = $this->l('Declaration ended — history preserved'); }
            if (Tools::isSubmit('saveTimesheet')) { PulsePrService::saveTimesheet(array('id_pulse_pr_employee' => (int) Tools::getValue('id_employee_pr'), 'period' => Tools::getValue('ts_period'), 'days_worked' => Tools::getValue('days_worked'), 'hours_worked' => Tools::getValue('hours_worked'), 'shifts' => Tools::getValue('shifts'), 'ot_hours' => Tools::getValue('ot_hours'), 'night_shifts' => Tools::getValue('night_shifts'), 'unpaid_days' => Tools::getValue('unpaid_days'), 'approved' => Tools::getValue('approved'), 'note' => Tools::getValue('tsnote'))); $this->confirmations[] = $this->l('Timesheet saved'); }
            if (Tools::isSubmit('saveOpening')) { $this->saveOpening(); $this->confirmations[] = $this->l('Opening year-to-date saved'); }
            if (Tools::isSubmit('setPin')) { PulsePrService::saveEmployee(array('id_pulse_pr_employee' => (int) Tools::getValue('id_employee_pr'), 'staff_no' => Tools::getValue('staff_no'), 'firstname' => Tools::getValue('firstname'), 'lastname' => Tools::getValue('lastname'), 'payslip_pin' => Tools::getValue('payslip_pin'))); $this->confirmations[] = $this->l('Payslip PIN updated'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    /** Opening balances for a property that is moving to Pulse mid-year: the YTD the old system already paid. */
    protected function saveOpening()
    {
        $id = (int) Tools::getValue('id_employee_pr');
        $year = (int) Tools::getValue('tax_year', date('Y'));
        if (!$id || !$year) { throw new PrestaShopException('Pick the employee and the tax year'); }
        Db::getInstance()->delete('pulse_pr_opening', 'id_pulse_pr_employee='.$id.' AND tax_year='.$year);
        Db::getInstance()->insert('pulse_pr_opening', array(
            'id_pulse_pr_employee' => $id, 'tax_year' => $year, 'periods' => (int) Tools::getValue('o_periods'),
            'gross' => (float) Tools::getValue('o_gross'), 'taxable' => (float) Tools::getValue('o_taxable'), 'paye' => (float) Tools::getValue('o_paye'),
            'pension_ee' => (float) Tools::getValue('o_pension_ee'), 'pension_er' => (float) Tools::getValue('o_pension_er'), 'nhf' => (float) Tools::getValue('o_nhf'),
            'net' => (float) Tools::getValue('o_net'), 'note' => pSQL(Tools::substr((string) Tools::getValue('o_note'), 0, 160)), 'date_add' => date('Y-m-d H:i:s'),
        ), true);
        PulsePrService::log(null, 'opening_save', 'employee', array('year' => $year), $id);
        return true;
    }

    protected function fields()
    {
        $d = array();
        foreach (array('id_pulse_pr_employee', 'staff_no', 'firstname', 'lastname', 'department', 'section', 'position', 'grade', 'cost_centre',
            'employment_type', 'pay_basis', 'pay_rate', 'country', 'currency', 'hire_date', 'exit_date', 'status', 'tin', 'tax_state', 'rsa_pin',
            'pfa', 'nhf_no', 'nsitf_no', 'nin', 'bank_name', 'bank_code', 'account_no', 'account_name', 'email', 'phone', 'pay_method',
            'on_hold', 'hold_reason', 'note', 'id_hr_employee', 'id_employee') as $k) {
            if (Tools::getValue($k) !== false) { $d[$k] = Tools::getValue($k); }
        }
        if (Tools::getValue('payslip_pin')) { $d['payslip_pin'] = Tools::getValue('payslip_pin'); }
        return $d;
    }
}
