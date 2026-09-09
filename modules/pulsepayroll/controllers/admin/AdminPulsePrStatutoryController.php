<?php
/** Countries, tax bands, reliefs and contributions — the effective-dated tables a rate change edits instead of a code release. */
class AdminPulsePrStatutoryController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Statutory & Countries'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        $country = Tools::getValue('country', PulsePrService::country());
        $asAt = Tools::getValue('as_at', date('Y-m-d'));
        $pack = PulsePrStatutory::pack($country);
        $this->context->smarty->assign(array(
            'country' => $country, 'as_at' => $asAt, 'countries' => PulsePrService::countries(false), 'row' => PulsePrService::countryRow($country),
            'pack_label' => $pack->label(), 'pack_verified' => $pack->verified(), 'warnings_list' => $pack->warnings(),
            'bands_now' => PulsePrStatutory::bands($country, $asAt),
            'bands_all' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_tax_band` WHERE country="'.pSQL($country).'" ORDER BY effective_from DESC, seq'),
            'reliefs_now' => PulsePrStatutory::reliefRows($country, $asAt),
            'reliefs_all' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_relief` WHERE country="'.pSQL($country).'" ORDER BY effective_from DESC, sort'),
            'contribs_now' => PulsePrStatutory::contributionRows($country, $asAt),
            'contribs_all' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_contribution` WHERE country="'.pSQL($country).'" ORDER BY effective_from DESC, sort'),
            'bases' => PulsePrCalc::baseNames(), 'remittances' => PulsePrService::remittances(null, 40),
            'classes' => array('PulsePrStatutoryGeneric' => 'Generic (table-driven)', 'PulsePrStatutoryNigeria' => 'Nigeria (Nigeria Tax Act 2025)'),
            'self_url' => $self,
        ));
        $this->setTemplate('statutory.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveCountry')) { PulsePrStatutory::saveCountry($this->grab(array('code', 'name', 'currency', 'tax_year_start', 'statutory_class', 'paye_mode', 'paye_basis', 'rounding', 'rounding_dp', 'verified', 'active', 'note'))); $this->confirmations[] = $this->l('Country saved'); }
            if (Tools::isSubmit('saveBand')) { PulsePrStatutory::saveBand($this->grab(array('id_pulse_pr_tax_band', 'country', 'regime', 'seq', 'band_from', 'band_to', 'rate_pct', 'basis', 'effective_from', 'effective_to', 'note'))); $this->confirmations[] = $this->l('Tax band saved'); }
            if (Tools::isSubmit('saveRelief')) { PulsePrStatutory::saveRelief($this->grab(array('id_pulse_pr_relief', 'country', 'code', 'name', 'type', 'value_pct', 'value_fixed', 'cap', 'base', 'basis', 'requires_evidence', 'declaration_code', 'conditions', 'sort', 'effective_from', 'effective_to', 'active'))); $this->confirmations[] = $this->l('Relief saved'); }
            if (Tools::isSubmit('saveContribution')) { PulsePrStatutory::saveContribution($this->grab(array('id_pulse_pr_contribution', 'country', 'code', 'name', 'employee_pct', 'employer_pct', 'base', 'floor', 'ceiling', 'frequency', 'mode', 'consent_code', 'pre_tax', 'employer_min_staff', 'employer_min_turnover', 'remit_within_days', 'remit_rule', 'penalty_pct_month', 'gl_liability', 'gl_expense', 'sort', 'effective_from', 'effective_to', 'active'))); $this->confirmations[] = $this->l('Contribution saved'); }
            if (Tools::isSubmit('deleteRate')) { PulsePrStatutory::deleteRow(Tools::getValue('rate_table'), (int) Tools::getValue('rate_id')); $this->confirmations[] = $this->l('Rate row deleted'); }
            if (Tools::isSubmit('supersede')) { $this->supersede(); }
            if (Tools::isSubmit('payRemittance')) { PulsePrService::payRemittance((int) Tools::getValue('id_remittance'), (float) Tools::getValue('amount_paid'), Tools::getValue('date_paid', date('Y-m-d')), Tools::getValue('reference')); $this->confirmations[] = $this->l('Remittance recorded'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    /**
     * Close the current band set on a date and copy it forward as the starting point for the new one.
     * This is the safe way to make a rate change: the old rows keep their effective_to so a back-dated
     * recalculation of last year still produces last year's answer.
     */
    protected function supersede()
    {
        $country = Tools::getValue('country', PulsePrService::country());
        $from = Tools::getValue('new_from');
        if (!$from || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { throw new PrestaShopException('Give the date the new bands take effect, as YYYY-MM-DD'); }
        $to = date('Y-m-d', strtotime($from.' -1 day'));
        $current = PulsePrStatutory::bands($country, $to);
        if (!$current) { throw new PrestaShopException('There are no bands in force on '.$to.' to supersede'); }
        foreach ($current as $b) { Db::getInstance()->update('pulse_pr_tax_band', array('effective_to' => pSQL($to)), 'id_pulse_pr_tax_band='.(int) $b['id_pulse_pr_tax_band']); }
        foreach ($current as $b) {
            $b['id_pulse_pr_tax_band'] = 0; $b['effective_from'] = $from; $b['effective_to'] = null;
            $b['note'] = Tools::substr('Copied forward from the set that ended '.$to.'. Edit the rates before the next run.', 0, 160);
            PulsePrStatutory::saveBand($b);
        }
        PulsePrService::log(null, 'bands_supersede', 'tax_band', array('country' => $country, 'from' => $from, 'rows' => count($current)));
        $this->confirmations[] = sprintf($this->l('%1$d bands closed on %2$s and copied forward from %3$s — edit the new rates now.'), count($current), $to, $from);
        return true;
    }

    protected function grab(array $keys)
    {
        $d = array();
        foreach ($keys as $k) { if (Tools::getValue($k) !== false) { $d[$k] = Tools::getValue($k); } }
        return $d;
    }
}
