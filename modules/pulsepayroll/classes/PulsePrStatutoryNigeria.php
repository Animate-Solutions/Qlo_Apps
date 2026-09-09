<?php
/**
 * Nigeria — complete pack, Nigeria Tax Act 2025, effective 1 January 2026.
 *
 * What the tables carry: the six annual PAYE bands, rent relief (20% of annual rent capped at N500,000),
 * pension 8/10 on basic+housing+transport, NSITF 1% employer, ITF 1% employer, NHF 2.5% of basic as an
 * opt-in scheme, NHIS off by default — each row dated, with the pre-2026 CRA regime kept alongside under
 * an effective_to of 2025-12-31 so a back-dated 2025 recalculation still produces the old numbers.
 *
 * What this class carries, because no table can say it:
 *  - annualisation over the pay periods remaining in the tax year, so a mid-year joiner is projected on
 *    what they will actually earn this year rather than on twelve months they will not work;
 *  - cumulative de-annualisation, so twelve monthly deductions sum to the annual charge to the kobo;
 *  - a final settlement, which closes the year on the income actually received and either refunds the
 *    over-deduction through net pay or (the default) reports it for the employee to claim from the
 *    state internal revenue service;
 *  - the 1%-of-gross minimum tax, which applied before 2026 and does not apply from 2026;
 *  - the behavioural rule around NHF: voluntary since the 2025 Act, so it is deducted only against a
 *    dated, recorded consent, and both the consent and its absence are stated on the payslip.
 */
class PulsePrStatutoryNigeria extends PulsePrStatutoryGeneric
{
    const ACT_2025_FROM = '2026-01-01';

    public function __construct($country = 'NG') { parent::__construct('NG'); }

    public function label() { return 'Nigeria — Nigeria Tax Act 2025 (PAYE, PenCom, NSITF, ITF, NHF, NHIA)'; }
    public function verified() { return true; }

    /** True once the period falls under the Nigeria Tax Act 2025 rather than PITA as amended. */
    public function underAct2025($periodTo) { return $periodTo >= self::ACT_2025_FROM; }

    public function warnings()
    {
        $w = array();
        if (!PulsePrStatutory::bands('NG', date('Y-m-d'))) { $w[] = 'No Nigerian PAYE bands are in force today — check the effective dates on the Statutory screen.'; }
        if (!(int) PulsePrService::cfg('EMPLOYER_STAFF_COUNT', 0)) { $w[] = 'Set the employer headcount and annual turnover in Payroll Settings: the ITF liability only arises at five or more employees or N50m turnover. Until they are set the module falls back to the live payroll roster ('.PulsePrService::employerStaffCount().' staff) for the size tests.'; }
        $noPfa = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE status NOT IN ("exited") AND (rsa_pin="" OR pfa="")');
        if ($noPfa) { $w[] = $noPfa.' active staff have no RSA PIN or PFA on file — their pension remittance line cannot be filed.'; }
        return $w;
    }

    /**
     * The annualisation window. A full-year employee is projected over the whole tax year; a joiner over
     * the periods remaining including the one being paid; a leaver's final settlement over the periods
     * they were actually paid in, because for them the year is finished.
     */
    public function annualisation(array $ctx)
    {
        $n = (int) $ctx['periods_per_year'];
        if (!empty($ctx['final_settlement']) || !empty($ctx['leaver'])) {
            $served = max(1, (int) $ctx['periods_elapsed_year']);
            return array('periods' => min($n, $served), 'reason' => 'Final settlement: the tax year is closed on the '.min($n, $served).' period(s) actually paid.');
        }
        $w = $this->joinerWindow($ctx);
        if ($w) { return array('periods' => $w, 'reason' => 'Mid-year joiner: annualised over the '.$w.' pay period(s) of the tax year from the hire date.'); }
        return array('periods' => $n, 'reason' => 'Annual bands applied to the projected tax year, de-annualised cumulatively over '.$n.' periods.');
    }

    /**
     * The pay periods a mid-year joiner will actually be paid in this tax year, counted from the HIRE DATE
     * and not from the period being run — otherwise the window is right in the month they join and reverts
     * to a full year the month after, which projects an income they will never earn and taxes it.
     * Zero for anyone whose hire date is not inside the tax year being paid.
     */
    protected function joinerWindow(array $ctx)
    {
        $emp = isset($ctx['employee']) ? $ctx['employee'] : array();
        if (empty($emp['hire_date'])) { return 0; }
        $hire = Tools::substr((string) $emp['hire_date'], 0, 7);
        if ((int) PulsePrService::taxYear($hire, $this->country) !== (int) $ctx['ytd']['tax_year']) { return 0; }
        $n = (int) $ctx['periods_per_year'];
        $idx = PulsePrService::periodIndex($hire, $this->country);
        $w = min($n, max(1, $n - (int) $idx['index'] + 1));
        return $w < $n ? $w : 0;
    }

    /**
     * Projected taxable income for the window. For a final settlement the year is already history, so the
     * projection is the income actually received: year-to-date plus this period. Otherwise it is what is
     * being paid this period plus the contractual recurring pay for each remaining period.
     */
    public function projectedTaxable(array $ctx)
    {
        if (!empty($ctx['final_settlement']) || !empty($ctx['leaver'])) { return round((float) $ctx['ytd']['taxable'] + (float) $ctx['period_taxable'], 2); }
        if ($this->joinerWindow($ctx)) { return round((float) $ctx['ytd']['taxable'] + (float) $ctx['period_taxable'] + (float) $ctx['period_taxable_recurring_full'] * $this->joinerRemaining($ctx), 2); }
        return parent::projectedTaxable($ctx);
    }

    /** Pay periods still to come inside a joiner's shortened window, after the one being paid. */
    protected function joinerRemaining(array $ctx) { return max(0, (int) $ctx['annualisation_periods'] - (int) $ctx['periods_elapsed']); }

    /**
     * On a final settlement the pre-tax contributions are history too: the relief is what was actually
     * contributed this tax year, not a projection. Anything else understates the relief and over-taxes
     * the last payslip a leaver ever receives.
     */
    public function projectedContribution($code, array $ctx)
    {
        $final = !empty($ctx['final_settlement']) || !empty($ctx['leaver']);
        $joiner = !$final && $this->joinerWindow($ctx);
        if (!$final && !$joiner) { return parent::projectedContribution($code, $ctx); }
        if (!isset($ctx['contributions'][$code])) { return 0.0; }
        $c = $ctx['contributions'][$code];
        if (empty($c['pre_tax'])) { return 0.0; }
        $ytdKey = array('PENSION' => 'pension_ee', 'NHF' => 'nhf');
        $ytd = isset($ytdKey[$code]) && isset($ctx['ytd'][$ytdKey[$code]]) ? (float) $ctx['ytd'][$ytdKey[$code]] : 0.0;
        // the relief must be projected on exactly the same footing as the pay it comes off: what has
        // actually been contributed, plus this period, plus the contractual rate for the periods still to come
        $full = $joiner && isset($c['employee_full']) ? (float) $c['employee_full'] * $this->joinerRemaining($ctx) : 0.0;
        return round($ytd + (float) $c['employee'] + $full, 2);
    }

    /**
     * Income tax for the period, plus the pre-2026 minimum-tax floor where the period predates the Act.
     * Also surfaces the NHF position on every payslip: deducted with a dated consent, or explicitly not.
     */
    public function payeForPeriod(array $ctx)
    {
        $r = parent::payeForPeriod($ctx);
        if (!$this->underAct2025($ctx['period_to'])) {
            $min = $this->minimumTax($ctx);
            if ($min > $r['annual_tax']) {
                $r['annual_tax'] = $min;
                $r['note'] = trim($r['note'].' Minimum tax of 1% of gross income applied (PITA, pre-2026).');
                $n = max(1, (int) $ctx['annualisation_periods']);
                $elapsed = min($n, max(1, (int) $ctx['periods_elapsed']));
                $toDate = round($min * $elapsed / $n, 2);
                $period = round($toDate - (float) $ctx['ytd']['paye'], 2);
                if ($period < 0) { $r['over_deducted'] = round($r['over_deducted'] - $period, 2); $period = 0.0; }
                $r['period_tax'] = $period;
            }
        }
        $r['act'] = $this->underAct2025($ctx['period_to']) ? 'Nigeria Tax Act 2025' : 'PITA as amended (pre-2026)';
        if (!empty($ctx['contributions']['NHF']) && !$ctx['contributions']['NHF']['consented']) {
            $r['note'] = trim($r['note'].' NHF is voluntary and no consent is recorded — nothing deducted.');
        }
        return $r;
    }

    /** 1% of gross income — the floor that applied under PITA and was abolished from 2026. */
    protected function minimumTax(array $ctx)
    {
        $gross = (float) $ctx['projected_gross'];
        return round($gross * 0.01, 2);
    }
}
