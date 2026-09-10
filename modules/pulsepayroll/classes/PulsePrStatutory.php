<?php
/**
 * The rate tables and the pack registry. Everything time-sensitive is read with an as-at date so a
 * back-dated recalculation of an old period picks up the rules that were in force then, not today's.
 * Adding a 2027 band change is one INSERT into pulse_pr_tax_band with effective_from='2027-01-01' and
 * an effective_to on the row it supersedes — no code changes anywhere.
 */
class PulsePrStatutory
{
    protected static $packs = array();

    /** The pack for a country, instantiated once per request. Falls back to the generic table-driven pack. */
    public static function pack($country)
    {
        $country = Tools::strtoupper(Tools::substr((string) $country, 0, 2));
        if (isset(self::$packs[$country])) { return self::$packs[$country]; }
        $row = PulsePrService::countryRow($country);
        $cls = $row && $row['statutory_class'] ? $row['statutory_class'] : 'PulsePrStatutoryGeneric';
        if (!class_exists($cls) || !in_array('PulsePrStatutoryInterface', class_implements($cls))) { $cls = 'PulsePrStatutoryGeneric'; }
        self::$packs[$country] = new $cls($country);
        return self::$packs[$country];
    }

    public static function reset() { self::$packs = array(); }

    /* ---------------- bands ---------------- */

    /** Tax bands in force on a date, ordered from the bottom band up. */
    public static function bands($country, $onDate, $regime = 'paye')
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_tax_band` WHERE country="'.pSQL($country).'" AND regime="'.pSQL($regime).'" AND effective_from<="'.pSQL($onDate).'" AND (effective_to IS NULL OR effective_to>="'.pSQL($onDate).'") ORDER BY seq, band_from');
    }

    /**
     * Progressive tax over a set of bands. Bands are absolute thresholds, not widths, so a band change is
     * readable as it is published. Returns the tax and the per-band working for the payslip explanation.
     */
    public static function applyBands($chargeable, array $bands)
    {
        $chargeable = round((float) $chargeable, 2);
        if ($chargeable <= 0 || !$bands) { return array('tax' => 0.0, 'working' => array()); }
        $tax = 0; $working = array();
        foreach ($bands as $b) {
            $from = (float) $b['band_from'];
            $to = ($b['band_to'] === null || $b['band_to'] === '') ? null : (float) $b['band_to'];
            if ($chargeable <= $from) { continue; }
            $slice = $to === null ? $chargeable - $from : min($chargeable, $to) - $from;
            if ($slice <= 0) { continue; }
            $amt = round($slice * (float) $b['rate_pct'] / 100, 2);
            $tax += $amt;
            $working[] = array('from' => $from, 'to' => $to, 'rate' => (float) $b['rate_pct'], 'slice' => round($slice, 2), 'tax' => $amt);
        }
        return array('tax' => round($tax, 2), 'working' => $working);
    }

    /* ---------------- reliefs ---------------- */

    public static function reliefRows($country, $onDate)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_relief` WHERE country="'.pSQL($country).'" AND active=1 AND effective_from<="'.pSQL($onDate).'" AND (effective_to IS NULL OR effective_to>="'.pSQL($onDate).'") ORDER BY sort, code');
    }

    /* ---------------- contributions ---------------- */

    public static function contributionRows($country, $onDate)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_contribution` WHERE country="'.pSQL($country).'" AND active=1 AND effective_from<="'.pSQL($onDate).'" AND (effective_to IS NULL OR effective_to>="'.pSQL($onDate).'") ORDER BY sort, code');
    }

    public static function contribution($country, $code, $onDate)
    {
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_contribution` WHERE country="'.pSQL($country).'" AND code="'.pSQL($code).'" AND active=1 AND effective_from<="'.pSQL($onDate).'" AND (effective_to IS NULL OR effective_to>="'.pSQL($onDate).'") ORDER BY effective_from DESC LIMIT 1');
    }

    /**
     * Apply a contribution row to a base. Floors and ceilings are per period on a monthly frequency and
     * per year on an annual one; the caller has already resolved the base amount from the named base.
     */
    public static function applyContribution(array $c, $base)
    {
        $base = round((float) $base, 2);
        if ($base < (float) $c['floor']) { return array('employee' => 0.0, 'employer' => 0.0, 'base' => $base, 'capped' => false); }
        $capped = false;
        if ($c['ceiling'] !== null && $c['ceiling'] !== '' && $base > (float) $c['ceiling']) { $base = (float) $c['ceiling']; $capped = true; }
        return array(
            'employee' => round($base * (float) $c['employee_pct'] / 100, 2),
            'employer' => round($base * (float) $c['employer_pct'] / 100, 2),
            'base' => $base, 'capped' => $capped,
        );
    }

    /* ---------------- admin editing ---------------- */

    public static function saveBand(array $d)
    {
        if (empty($d['country']) || empty($d['effective_from'])) { throw new PrestaShopException('A band needs a country and an effective-from date'); }
        $row = array(
            'country' => pSQL(Tools::strtoupper(Tools::substr($d['country'], 0, 2))), 'regime' => pSQL(Tools::substr(isset($d['regime']) ? $d['regime'] : 'paye', 0, 32)),
            'seq' => (int) (isset($d['seq']) ? $d['seq'] : 1), 'band_from' => (float) (isset($d['band_from']) ? $d['band_from'] : 0),
            'band_to' => (isset($d['band_to']) && $d['band_to'] !== '' && $d['band_to'] !== null) ? (float) $d['band_to'] : null,
            'rate_pct' => (float) (isset($d['rate_pct']) ? $d['rate_pct'] : 0),
            'basis' => pSQL(in_array(isset($d['basis']) ? $d['basis'] : '', array('annual', 'monthly')) ? $d['basis'] : 'annual'),
            'effective_from' => pSQL($d['effective_from']), 'effective_to' => !empty($d['effective_to']) ? pSQL($d['effective_to']) : null,
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 160)),
        );
        if ($row['band_to'] !== null && $row['band_to'] <= $row['band_from']) { throw new PrestaShopException('A band ceiling must be above its floor'); }
        $id = (int) (isset($d['id_pulse_pr_tax_band']) ? $d['id_pulse_pr_tax_band'] : 0);
        if ($id) { Db::getInstance()->update('pulse_pr_tax_band', $row, 'id_pulse_pr_tax_band='.$id, 0, true); }
        else { Db::getInstance()->insert('pulse_pr_tax_band', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        PulsePrService::log(null, 'band_save', 'tax_band', $row, $id);
        return $id;
    }

    public static function saveRelief(array $d)
    {
        if (empty($d['country']) || empty($d['code']) || empty($d['effective_from'])) { throw new PrestaShopException('A relief needs a country, a code and an effective-from date'); }
        $row = array(
            'country' => pSQL(Tools::strtoupper(Tools::substr($d['country'], 0, 2))), 'code' => pSQL(Tools::strtoupper(Tools::substr($d['code'], 0, 24))),
            'name' => pSQL(Tools::substr(isset($d['name']) ? $d['name'] : $d['code'], 0, 96)),
            'type' => pSQL(in_array(isset($d['type']) ? $d['type'] : '', array('fixed', 'percent_of', 'capped_percent', 'greater_of')) ? $d['type'] : 'capped_percent'),
            'value_pct' => (float) (isset($d['value_pct']) ? $d['value_pct'] : 0), 'value_fixed' => (float) (isset($d['value_fixed']) ? $d['value_fixed'] : 0),
            'cap' => (isset($d['cap']) && $d['cap'] !== '' && $d['cap'] !== null) ? (float) $d['cap'] : null,
            'base' => pSQL(Tools::substr(isset($d['base']) ? $d['base'] : 'GROSS', 0, 24)),
            'basis' => pSQL(in_array(isset($d['basis']) ? $d['basis'] : '', array('annual', 'monthly')) ? $d['basis'] : 'annual'),
            'requires_evidence' => !empty($d['requires_evidence']) ? 1 : 0,
            'declaration_code' => !empty($d['declaration_code']) ? pSQL(Tools::strtoupper(Tools::substr($d['declaration_code'], 0, 24))) : null,
            'conditions' => pSQL(Tools::substr(isset($d['conditions']) ? $d['conditions'] : '', 0, 255)), 'sort' => (int) (isset($d['sort']) ? $d['sort'] : 10),
            'effective_from' => pSQL($d['effective_from']), 'effective_to' => !empty($d['effective_to']) ? pSQL($d['effective_to']) : null,
            'active' => isset($d['active']) ? (int) (bool) $d['active'] : 1,
        );
        $id = (int) (isset($d['id_pulse_pr_relief']) ? $d['id_pulse_pr_relief'] : 0);
        if ($id) { Db::getInstance()->update('pulse_pr_relief', $row, 'id_pulse_pr_relief='.$id, 0, true); }
        else { Db::getInstance()->insert('pulse_pr_relief', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        PulsePrService::log(null, 'relief_save', 'relief', $row, $id);
        return $id;
    }

    public static function saveContribution(array $d)
    {
        if (empty($d['country']) || empty($d['code']) || empty($d['effective_from'])) { throw new PrestaShopException('A contribution needs a country, a code and an effective-from date'); }
        $row = array(
            'country' => pSQL(Tools::strtoupper(Tools::substr($d['country'], 0, 2))), 'code' => pSQL(Tools::strtoupper(Tools::substr($d['code'], 0, 24))),
            'name' => pSQL(Tools::substr(isset($d['name']) ? $d['name'] : $d['code'], 0, 96)),
            'employee_pct' => (float) (isset($d['employee_pct']) ? $d['employee_pct'] : 0), 'employer_pct' => (float) (isset($d['employer_pct']) ? $d['employer_pct'] : 0),
            'base' => pSQL(Tools::strtoupper(Tools::substr(isset($d['base']) ? $d['base'] : 'BHT', 0, 24))),
            'floor' => (float) (isset($d['floor']) ? $d['floor'] : 0),
            'ceiling' => (isset($d['ceiling']) && $d['ceiling'] !== '' && $d['ceiling'] !== null) ? (float) $d['ceiling'] : null,
            'frequency' => pSQL(in_array(isset($d['frequency']) ? $d['frequency'] : '', array('monthly', 'annual')) ? $d['frequency'] : 'monthly'),
            'mode' => pSQL(in_array(isset($d['mode']) ? $d['mode'] : '', array('mandatory', 'opt_in', 'opt_out', 'disabled')) ? $d['mode'] : 'mandatory'),
            'consent_code' => !empty($d['consent_code']) ? pSQL(Tools::strtoupper(Tools::substr($d['consent_code'], 0, 24))) : null,
            'pre_tax' => isset($d['pre_tax']) ? (int) (bool) $d['pre_tax'] : 1,
            'employer_min_staff' => (int) (isset($d['employer_min_staff']) ? $d['employer_min_staff'] : 0),
            'employer_min_turnover' => (float) (isset($d['employer_min_turnover']) ? $d['employer_min_turnover'] : 0),
            'remit_within_days' => (int) (isset($d['remit_within_days']) ? $d['remit_within_days'] : 0),
            'remit_rule' => pSQL(Tools::substr(isset($d['remit_rule']) ? $d['remit_rule'] : '', 0, 160)),
            'penalty_pct_month' => (float) (isset($d['penalty_pct_month']) ? $d['penalty_pct_month'] : 0),
            'gl_liability' => !empty($d['gl_liability']) ? pSQL(Tools::substr($d['gl_liability'], 0, 16)) : null,
            'gl_expense' => !empty($d['gl_expense']) ? pSQL(Tools::substr($d['gl_expense'], 0, 16)) : null,
            'sort' => (int) (isset($d['sort']) ? $d['sort'] : 10),
            'effective_from' => pSQL($d['effective_from']), 'effective_to' => !empty($d['effective_to']) ? pSQL($d['effective_to']) : null,
            'active' => isset($d['active']) ? (int) (bool) $d['active'] : 1,
        );
        $id = (int) (isset($d['id_pulse_pr_contribution']) ? $d['id_pulse_pr_contribution'] : 0);
        if ($id) { Db::getInstance()->update('pulse_pr_contribution', $row, 'id_pulse_pr_contribution='.$id, 0, true); }
        else { Db::getInstance()->insert('pulse_pr_contribution', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        PulsePrService::log(null, 'contribution_save', 'contribution', $row, $id);
        return $id;
    }

    public static function saveCountry(array $d)
    {
        if (empty($d['code']) || empty($d['name'])) { throw new PrestaShopException('A country needs a code and a name'); }
        $row = array(
            'code' => pSQL(Tools::strtoupper(Tools::substr($d['code'], 0, 2))), 'name' => pSQL(Tools::substr($d['name'], 0, 64)),
            'currency' => pSQL(Tools::strtoupper(Tools::substr(isset($d['currency']) ? $d['currency'] : 'NGN', 0, 3))),
            'tax_year_start' => pSQL(Tools::substr(isset($d['tax_year_start']) ? $d['tax_year_start'] : '01-01', 0, 5)),
            'statutory_class' => pSQL(Tools::substr(isset($d['statutory_class']) ? $d['statutory_class'] : 'PulsePrStatutoryGeneric', 0, 64)),
            'paye_mode' => pSQL(in_array(isset($d['paye_mode']) ? $d['paye_mode'] : '', array('cumulative', 'non_cumulative')) ? $d['paye_mode'] : 'cumulative'),
            'paye_basis' => pSQL(in_array(isset($d['paye_basis']) ? $d['paye_basis'] : '', array('annual', 'monthly')) ? $d['paye_basis'] : 'annual'),
            'rounding' => pSQL(in_array(isset($d['rounding']) ? $d['rounding'] : '', array('round', 'floor', 'ceil')) ? $d['rounding'] : 'round'),
            'rounding_dp' => (int) (isset($d['rounding_dp']) ? $d['rounding_dp'] : 2),
            'verified' => !empty($d['verified']) ? 1 : 0, 'active' => isset($d['active']) ? (int) (bool) $d['active'] : 1,
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 255)), 'date_upd' => date('Y-m-d H:i:s'),
        );
        $ex = (int) Db::getInstance()->getValue('SELECT id_pulse_pr_country FROM `'._DB_PREFIX_.'pulse_pr_country` WHERE code="'.$row['code'].'"');
        if ($ex) { Db::getInstance()->update('pulse_pr_country', $row, 'id_pulse_pr_country='.$ex, 0, true); $id = $ex; }
        else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_pr_country', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        self::reset();
        PulsePrService::log(null, 'country_save', 'country', $row, $id);
        return $id;
    }

    /** Delete a rate row. Only ever used on a row that has not been used in a calculated run. */
    public static function deleteRow($table, $id)
    {
        $allowed = array('pulse_pr_tax_band' => 'id_pulse_pr_tax_band', 'pulse_pr_relief' => 'id_pulse_pr_relief', 'pulse_pr_contribution' => 'id_pulse_pr_contribution');
        if (!isset($allowed[$table])) { throw new PrestaShopException('Not a rate table'); }
        Db::getInstance()->delete($table, $allowed[$table].'='.(int) $id);
        PulsePrService::log(null, 'rate_delete', 'rate', array('table' => $table), (int) $id);
        return true;
    }
}
