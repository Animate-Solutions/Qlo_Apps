<?php
/** Pay elements and the grade default structures every new employee inherits. */
class AdminPulsePrElementsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Pay Elements'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        $this->context->smarty->assign(array(
            'elements' => PulsePrService::elements(false), 'bases' => PulsePrCalc::baseNames(),
            'grades' => Db::getInstance()->executeS('SELECT DISTINCT grade FROM `'._DB_PREFIX_.'pulse_pr_employee_element` WHERE id_pulse_pr_employee IS NULL AND grade<>"" ORDER BY grade'),
            'grade' => Tools::getValue('grade', 'DEFAULT'),
            'grade_rows' => Db::getInstance()->executeS('SELECT s.*, e.name, e.type FROM `'._DB_PREFIX_.'pulse_pr_employee_element` s LEFT JOIN `'._DB_PREFIX_.'pulse_pr_element` e ON e.code=s.element_code WHERE s.id_pulse_pr_employee IS NULL AND s.grade="'.pSQL(Tools::getValue('grade', 'DEFAULT')).'" ORDER BY e.sequence, s.element_code'),
            'edit' => Tools::getValue('code') ? PulsePrService::element(Tools::getValue('code')) : null,
            'self_url' => $self, 'contributions' => PulsePrStatutory::contributionRows(PulsePrService::country(), date('Y-m-d')),
        ));
        $this->setTemplate('elements.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveElement')) {
                $d = array();
                foreach (array('code', 'name', 'type', 'calc', 'percent_of', 'default_value', 'formula', 'statutory_code', 'taxable', 'pensionable',
                    'nsitfable', 'in_basic', 'proratable', 'recurring', 'gl_account', 'department', 'sequence', 'show_on_payslip', 'active', 'note') as $k) { $d[$k] = Tools::getValue($k); }
                PulsePrService::saveElement($d);
                $this->confirmations[] = $this->l('Pay element saved');
            }
            if (Tools::isSubmit('saveGradeLine')) {
                PulsePrService::saveStructureLine(array('grade' => Tools::getValue('grade_code'), 'element_code' => Tools::getValue('element_code'), 'amount' => Tools::getValue('amount'), 'percent' => Tools::getValue('percent'), 'units' => Tools::getValue('units'), 'effective_from' => Tools::getValue('effective_from'), 'effective_to' => Tools::getValue('effective_to'), 'note' => Tools::getValue('note')));
                $this->confirmations[] = $this->l('Grade structure line added');
            }
            if (Tools::isSubmit('deleteGradeLine')) { PulsePrService::deleteStructureLine((int) Tools::getValue('id_structure')); $this->confirmations[] = $this->l('Grade structure line removed'); }
            if (Tools::isSubmit('checkGrade')) { $this->checkGrade(Tools::getValue('grade', 'DEFAULT')); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    /** Sanity-check a grade structure before anyone is paid on it: do the percentages add up to the package? */
    protected function checkGrade($grade)
    {
        $rows = Db::getInstance()->executeS('SELECT s.*, e.type FROM `'._DB_PREFIX_.'pulse_pr_employee_element` s LEFT JOIN `'._DB_PREFIX_.'pulse_pr_element` e ON e.code=s.element_code WHERE s.id_pulse_pr_employee IS NULL AND s.grade="'.pSQL($grade).'" AND (s.effective_to IS NULL OR s.effective_to>="'.pSQL(date('Y-m-d')).'")');
        $pct = 0; $fixed = 0;
        foreach ($rows as $r) { if ($r['type'] !== 'earning') { continue; } $pct += (float) $r['percent']; $fixed += (float) $r['amount']; }
        if (abs($pct - 100) < 0.0001) { $this->confirmations[] = sprintf($this->l('Grade %s: the earning percentages add up to 100%% of the package.'), $grade).($fixed > 0 ? ' '.sprintf($this->l('There are also fixed amounts totalling %s on top.'), number_format($fixed, 2)) : ''); }
        elseif ($pct > 0) { $this->warnings[] = sprintf($this->l('Grade %1$s: the earning percentages add up to %2$s%% of the package, not 100%%. Gross pay will not equal the contractual package.'), $grade, number_format($pct, 2)); }
        else { $this->warnings[] = sprintf($this->l('Grade %s has no percentage-based earnings — every element is a fixed amount, so the contractual package on the employee record is not used.'), $grade); }
        return true;
    }
}
