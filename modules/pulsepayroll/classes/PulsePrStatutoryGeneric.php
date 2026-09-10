<?php
/**
 * The table-driven pack. It can run any country whose income tax is a progressive band table applied to
 * (taxable pay − pre-tax contributions − reliefs), on either an annual or a monthly basis, with no
 * country-specific behaviour beyond what the tables say. Ghana is driven entirely by this class.
 *
 * A country whose rules need more than that subclasses this and overrides only the parts that differ —
 * PulsePrStatutoryNigeria overrides annualisation, de-annualisation and the mid-year joiner projection.
 */
class PulsePrStatutoryGeneric implements PulsePrStatutoryInterface
{
    protected $country;
    protected $row;

    public function __construct($country = 'NG')
    {
        $this->country = Tools::strtoupper(Tools::substr((string) $country, 0, 2));
        $this->row = PulsePrService::countryRow($this->country);
    }

    public function countryCode() { return $this->country; }
    public function label() { return ($this->row ? $this->row['name'] : $this->country).' — table-driven pack'; }
    public function verified() { return $this->row ? (bool) $this->row['verified'] : false; }
    public function payeMode() { return $this->row && $this->row['paye_mode'] === 'non_cumulative' ? 'non_cumulative' : 'cumulative'; }
    public function payeBasis() { return $this->row && $this->row['paye_basis'] === 'monthly' ? 'monthly' : 'annual'; }

    public function warnings()
    {
        $w = array();
        if (!$this->verified()) { $w[] = 'The '.($this->row ? $this->row['name'] : $this->country).' pack is a starting point built from published rates. Verify the bands, reliefs and contribution rates against the current local circulars before running a live payroll on it.'; }
        if (!PulsePrStatutory::bands($this->country, date('Y-m-d'))) { $w[] = 'No income-tax bands are in force today for '.$this->country.'. Add them on the Statutory screen with an effective-from date.'; }
        return $w;
    }

    /** Table-driven countries do not annualise unless their band basis says annual. */
    public function annualisation(array $ctx)
    {
        if ($this->payeBasis() === 'monthly') { return array('periods' => 1, 'reason' => 'Bands are published on a monthly basis and are applied to the period directly.'); }
        return array('periods' => (int) $ctx['periods_per_year'], 'reason' => 'Annual bands applied to the projected year, then spread evenly over the pay periods.');
    }

    /**
     * The projection the bands see: what is actually being paid this period (prorated pay plus any
     * one-off such as a bonus or a service-charge share) plus the contractual recurring pay for every
     * remaining period in the annualisation window. A one-off is added once and never multiplied.
     */
    public function projectedTaxable(array $ctx)
    {
        if ($this->payeBasis() === 'monthly') { return round((float) $ctx['period_taxable'], 2); }
        $n = max(1, (int) $ctx['annualisation_periods']);
        return round((float) $ctx['period_taxable'] + (float) $ctx['period_taxable_recurring_full'] * ($n - 1), 2);
    }

    /**
     * Reliefs in force, resolved against their base. A relief flagged requires_evidence yields nothing
     * unless the employee has a declaration in force with the evidence marked verified — an unevidenced
     * claim never quietly reduces tax.
     */
    public function reliefs(array $ctx)
    {
        $out = array();
        foreach (PulsePrStatutory::reliefRows($this->country, $ctx['period_to']) as $r) {
            $decl = ($r['declaration_code'] && isset($ctx['declarations'][$r['declaration_code']])) ? $ctx['declarations'][$r['declaration_code']] : null;
            if ((int) $r['requires_evidence']) {
                if (!$decl) { $out[$r['code']] = array('amount' => 0.0, 'evidence_ok' => false, 'note' => $r['name'].' not claimed — no declaration on file'); continue; }
                if (!(int) $decl['evidence_verified']) { $out[$r['code']] = array('amount' => 0.0, 'evidence_ok' => false, 'note' => $r['name'].' declared but the evidence is not verified — relief withheld'); continue; }
            }
            $base = $this->reliefBase($r, $ctx, $decl);
            $amount = $this->reliefAmount($r, $base);
            if ($r['basis'] === 'monthly' && $this->payeBasis() === 'annual') { $amount = round($amount * (int) $ctx['annualisation_periods'], 2); }
            if ($r['basis'] === 'annual' && $this->payeBasis() === 'monthly') { $amount = round($amount / max(1, (int) $ctx['periods_per_year']), 2); }
            $out[$r['code']] = array('amount' => round($amount, 2), 'evidence_ok' => true, 'note' => $r['name'], 'requires_evidence' => (int) $r['requires_evidence']);
        }
        return $out;
    }

    /** Resolve a relief's named base: a declared amount, a contribution already computed, or a pay base. */
    protected function reliefBase(array $r, array $ctx, $decl)
    {
        $base = $r['base'];
        if ($base === 'DECLARED') { return $decl ? (float) $decl['annual_value'] : 0.0; }
        if (Tools::substr($base, 0, 8) === 'CONTRIB:') { return $this->projectedContribution(Tools::substr($base, 8), $ctx); }
        $named = isset($ctx['bases'][$base]) ? (float) $ctx['bases'][$base] : 0.0;
        return $this->payeBasis() === 'annual' ? round($named * (int) $ctx['annualisation_periods'], 2) : $named;
    }

    /**
     * A pre-tax contribution projected over the annualisation window, on exactly the same footing as the
     * taxable pay it will be deducted from: what is actually being contributed this period, plus the
     * contractual contribution for every remaining period. Multiplying a half-month contribution by twelve
     * is the classic mid-year-joiner error and this is where it is avoided.
     */
    public function projectedContribution($code, array $ctx)
    {
        if (!isset($ctx['contributions'][$code])) { return 0.0; }
        $c = $ctx['contributions'][$code];
        if (empty($c['pre_tax'])) { return 0.0; }
        $period = (float) $c['employee'];
        if ($this->payeBasis() !== 'annual') { return $period; }
        $n = max(1, (int) $ctx['annualisation_periods']);
        $full = isset($c['employee_full']) ? (float) $c['employee_full'] : $period;
        return round($period + $full * ($n - 1), 2);
    }

    protected function reliefAmount(array $r, $base)
    {
        switch ($r['type']) {
            case 'fixed': return (float) $r['value_fixed'];
            case 'percent_of': return round($base * ((float) $r['value_pct'] > 0 ? (float) $r['value_pct'] : 100) / 100, 2);
            case 'greater_of':
                $pct = round($base * (float) $r['value_pct'] / 100, 2);
                $onePct = round($base * 0.01, 2);
                return round(max((float) $r['value_fixed'], $onePct) + $pct, 2);
            case 'capped_percent':
            default:
                $v = round($base * (float) $r['value_pct'] / 100, 2);
                if ($r['cap'] !== null && $r['cap'] !== '' && $v > (float) $r['cap']) { $v = (float) $r['cap']; }
                return $v;
        }
    }

    /**
     * Employee and employer contributions for the period. An opt_in scheme deducts nothing without a
     * dated, recorded consent; an opt_out scheme deducts unless the employee has opted out. Employer-side
     * schemes with a size test are skipped when the property is below the threshold.
     */
    public function contributions(array $ctx)
    {
        $out = array();
        $staff = PulsePrService::employerStaffCount();
        $turnover = (float) PulsePrService::cfg('EMPLOYER_TURNOVER', 0);
        foreach (PulsePrStatutory::contributionRows($this->country, $ctx['period_to']) as $c) {
            $consent = null; $consented = true; $note = $c['name'];
            if ($c['mode'] === 'disabled') { continue; }
            if ($c['consent_code'] && isset($ctx['declarations'][$c['consent_code']])) { $consent = $ctx['declarations'][$c['consent_code']]; }
            if ($c['mode'] === 'opt_in') {
                $consented = $consent && (int) $consent['consented'] && $consent['consent_date'];
                if (!$consented) { $note = $c['name'].' not deducted — no recorded employee consent'; }
            } elseif ($c['mode'] === 'opt_out') {
                $consented = !($consent && !(int) $consent['consented']);
                if (!$consented) { $note = $c['name'].' — employee has opted out'; }
            }
            $base = isset($ctx['bases'][$c['base']]) ? (float) $ctx['bases'][$c['base']] : 0.0;
            $fullBase = isset($ctx['full_bases'][$c['base']]) ? (float) $ctx['full_bases'][$c['base']] : $base;
            $applied = PulsePrStatutory::applyContribution($c, $base);
            $appliedFull = PulsePrStatutory::applyContribution($c, $fullBase);
            $employerDue = true;
            if ((int) $c['employer_min_staff'] > 0 || (float) $c['employer_min_turnover'] > 0) {
                $employerDue = ($staff >= (int) $c['employer_min_staff'] && (int) $c['employer_min_staff'] > 0) || ($turnover >= (float) $c['employer_min_turnover'] && (float) $c['employer_min_turnover'] > 0);
                if (!$employerDue) { $note .= ' — employer side not due at this size'; }
            }
            $out[$c['code']] = array(
                'code' => $c['code'], 'name' => $c['name'],
                'employee' => $consented ? $applied['employee'] : 0.0,
                'employer' => ($employerDue ? $applied['employer'] : 0.0),
                'employee_full' => $consented ? $appliedFull['employee'] : 0.0, 'employer_full' => $employerDue ? $appliedFull['employer'] : 0.0,
                'base' => $applied['base'], 'base_name' => $c['base'], 'capped' => $applied['capped'],
                'employee_pct' => (float) $c['employee_pct'], 'employer_pct' => (float) $c['employer_pct'],
                'pre_tax' => (int) $c['pre_tax'], 'mode' => $c['mode'], 'frequency' => $c['frequency'],
                'consented' => $consented ? 1 : 0, 'consent_date' => $consent && $consented ? $consent['consent_date'] : null,
                'gl_liability' => $c['gl_liability'], 'gl_expense' => $c['gl_expense'],
                'remit_within_days' => (int) $c['remit_within_days'], 'remit_rule' => $c['remit_rule'], 'note' => $note,
            );
        }
        return $out;
    }

    /**
     * Income tax for the period. Annual basis: project the year, take the reliefs off, run the bands,
     * then de-annualise. Monthly basis: run the monthly bands on the period directly.
     */
    public function payeForPeriod(array $ctx)
    {
        $bands = PulsePrStatutory::bands($this->country, $ctx['period_to']);
        if (!$bands) { return array('period_tax' => 0.0, 'annual_tax' => 0.0, 'chargeable' => 0.0, 'reliefs' => array(), 'working' => array(), 'basis' => $this->payeBasis(), 'over_deducted' => 0.0, 'note' => 'No tax bands in force for '.$this->country.' on '.$ctx['period_to']); }
        $reliefs = isset($ctx['reliefs']) ? $ctx['reliefs'] : $this->reliefs($ctx);
        $reliefTotal = 0; foreach ($reliefs as $r) { $reliefTotal += (float) $r['amount']; }
        $reliefTotal = round($reliefTotal, 2);

        if ($this->payeBasis() === 'monthly') {
            $chargeable = round(max(0, (float) $ctx['period_taxable'] - $reliefTotal), 2);
            $r = PulsePrStatutory::applyBands($chargeable, $bands);
            return array('period_tax' => $r['tax'], 'annual_tax' => round($r['tax'] * (int) $ctx['periods_per_year'], 2), 'chargeable' => $chargeable, 'reliefs' => $reliefs, 'relief_total' => $reliefTotal, 'working' => $r['working'], 'basis' => 'monthly', 'over_deducted' => 0.0, 'note' => '');
        }

        $projected = round((float) $ctx['projected_taxable'], 2);
        $chargeable = round(max(0, $projected - $reliefTotal), 2);
        $r = PulsePrStatutory::applyBands($chargeable, $bands);
        $periodTax = $this->deannualise($r['tax'], $ctx);
        $over = 0.0;
        if ($periodTax < 0) {
            if ((int) PulsePrService::cfg('LEAVER_REFUND', 0)) { $over = 0.0; }
            else { $over = round(-$periodTax, 2); $periodTax = 0.0; }
        }
        return array(
            'period_tax' => round($periodTax, 2), 'annual_tax' => $r['tax'], 'chargeable' => $chargeable, 'reliefs' => $reliefs,
            'relief_total' => $reliefTotal, 'working' => $r['working'], 'basis' => 'annual', 'over_deducted' => $over,
            'projected_taxable' => $projected, 'note' => '',
        );
    }

    /**
     * Spread an annual tax over the pay periods without drift. Cumulative mode allocates
     * round(annual x elapsed / periods, 2) and takes off what has already been deducted, so twelve
     * periods sum to exactly the annual figure however the halfpennies fall.
     */
    protected function deannualise($annualTax, array $ctx)
    {
        $n = max(1, (int) $ctx['annualisation_periods']);
        if ($this->payeMode() === 'non_cumulative') { return round($annualTax / $n, 2); }
        $elapsed = max(1, (int) $ctx['periods_elapsed']);
        if ($elapsed > $n) { $elapsed = $n; }
        $toDate = round($annualTax * $elapsed / $n, 2);
        return round($toDate - (float) $ctx['ytd']['paye'], 2);
    }
}
