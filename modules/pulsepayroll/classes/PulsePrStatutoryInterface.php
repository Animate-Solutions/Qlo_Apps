<?php
/**
 * The contract every country pack implements. Rates, bands, reliefs and contribution percentages live in
 * pulse_pr_tax_band / pulse_pr_relief / pulse_pr_contribution and are never written into a pack — a rate
 * change is a dated insert, not a code release. What a pack owns is the logic a table cannot express:
 * whether income tax is annualised and how it is de-annualised, whether the year is cumulative, which
 * reliefs need evidence, how a mid-year joiner is projected, and what a final settlement does.
 *
 * $ctx passed to every method carries, at minimum:
 *   country, period (YYYY-MM), period_from, period_to, index, remaining, periods_per_year,
 *   employee (row), declarations (code => row), proration (array), joiner, leaver, final_settlement,
 *   ytd (gross, taxable, paye, pension_ee, nhf, periods), period_taxable, projected_taxable,
 *   period_pensionable, projected_pensionable, contributions (code => resolved amounts)
 */
interface PulsePrStatutoryInterface
{
    /** Short label shown on the statutory screen and on the payslip footer. */
    public function label();

    /** ISO-2 country code this pack serves. */
    public function countryCode();

    /**
     * Income tax for one pay period.
     * @return array period_tax, annual_tax, chargeable, reliefs (code => amount), basis, over_deducted, note
     */
    public function payeForPeriod(array $ctx);

    /**
     * The reliefs and pre-tax deductions allowed against the projected income for the tax year.
     * @return array code => array(amount, evidence_ok, note)
     */
    public function reliefs(array $ctx);

    /**
     * Employee and employer contributions for the period.
     * @return array code => array(employee, employer, base, pre_tax, consented, note)
     */
    public function contributions(array $ctx);

    /**
     * How the period's taxable pay is grossed up to a year for the band calculation, and back down again.
     * @return array periods (the annualisation factor actually used), reason
     */
    public function annualisation(array $ctx);

    /**
     * The taxable income the bands are applied to, projected over the annualisation window. This is where
     * a mid-year joiner, a leaver and a one-off payment stop being the same calculation.
     * @return float
     */
    public function projectedTaxable(array $ctx);

    /** True when the pack has been verified for production use in that jurisdiction. */
    public function verified();

    /** Anything the payroll officer must be told before trusting a run on this pack. */
    public function warnings();
}
