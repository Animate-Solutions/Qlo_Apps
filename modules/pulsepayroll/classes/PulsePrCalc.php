<?php
/**
 * The payroll calculator. One employee, one period, one deterministic answer.
 *
 * The order is fixed and every step rounds at the element level, so the printed gross is always exactly
 * the sum of the earning lines and a re-run of an unchanged period reproduces the same bytes:
 *   1. split the period into segments wherever the pay structure changes mid-month
 *   2. value every element at its full contractual rate, in sequence, resolving named bases as it goes
 *   3. prorate the proratable ones by the segment's share of the period; a joiner, a leaver and unpaid
 *      leave all fall out of the same arithmetic
 *   4. build the named bases (BASIC, BHT, GROSS, TAXABLE_GROSS) once, from the elements
 *   5. ask the country pack for contributions, reliefs and income tax
 *   6. recover loans and arrears, but never past the protected net floor — the shortfall is parked
 *   7. net = earnings − deductions, and it can never be negative
 */
class PulsePrCalc
{
    /** Named bases every element, relief and contribution may refer to. Defined once so a jurisdiction change does not ripple. */
    public static function baseNames() { return array('PACKAGE', 'BASIC', 'BHT', 'GROSS', 'TAXABLE_GROSS', 'NSITF_BASE', 'NET_BEFORE_LOAN'); }

    /**
     * Calculate one employee for one period.
     * @param array $emp   pulse_pr_employee row
     * @param array $run   pulse_pr_run row (period, period_from, period_to, run_type, country)
     * @param array $opts  extras: tronc (amount), one_off (code => amount), dry (do not touch loan schedules)
     * @return array the payslip header, its lines and the full working
     */
    public static function employee(array $emp, array $run, array $opts = array())
    {
        $country = !empty($emp['country']) ? $emp['country'] : $run['country'];
        $pack = PulsePrStatutory::pack($country);
        $from = $run['period_from']; $to = $run['period_to']; $period = $run['period'];
        $finalSettlement = $run['run_type'] === 'final_settlement' || (!empty($emp['exit_date']) && $emp['exit_date'] >= $from && $emp['exit_date'] <= $to);

        $ts = PulsePrService::timesheet((int) $emp['id_pulse_pr_employee'], $period, $emp['id_hr_employee']);
        $pro = PulsePrService::proration($emp, $from, $to, $ts ? $ts : null);
        $segments = self::segments($emp, $from, $to, $pro);

        /* ---- 2 & 3: value and prorate the elements ---- */
        $lines = array(); $full = array();
        $lastSeg = count($segments) - 1;
        foreach ($segments as $i => $seg) {
            $structure = PulsePrService::structure((int) $emp['id_pulse_pr_employee'], $seg['effective_on'], $emp['grade']);
            $valued = self::valueStructure($structure, $emp, $ts, $opts);
            foreach ($valued as $code => $v) {
                if ($v['element']['calc'] === 'statutory') { continue; }
                $prorate = (int) $v['element']['proratable'] === 1;
                if (!$prorate && $i !== $lastSeg) { continue; }
                $amount = $prorate ? PulsePrService::money($v['amount'] * $seg['factor']) : PulsePrService::money($v['amount']);
                if (!isset($lines[$code])) { $lines[$code] = array('code' => $code, 'element' => $v['element'], 'amount' => 0.0, 'units' => 0.0, 'rate' => (float) $v['rate'], 'base_amount' => (float) $v['base'], 'percent' => (float) $v['percent'], 'prorated' => $prorate ? 1 : 0, 'note' => ''); }
                $lines[$code]['amount'] = PulsePrService::money($lines[$code]['amount'] + $amount);
                $lines[$code]['units'] += (float) $v['units'] * ($prorate ? $seg['factor'] : 1);
                if ($prorate && $seg['factor'] < 1) { $lines[$code]['note'] = 'Prorated '.rtrim(rtrim(number_format($pro['days'], 3, '.', ''), '0'), '.').'/'.rtrim(rtrim(number_format($pro['days_in_period'], 3, '.', ''), '0'), '.').' days'; }
                if (!isset($full[$code])) { $full[$code] = 0.0; }
                if ((int) $v['element']['recurring']) { $full[$code] = PulsePrService::money($v['amount']); }
            }
        }
        $lines = self::balancePercentStructure($lines, $emp, $pro);

        /* ---- extra earnings that are not part of the standing structure ---- */
        foreach (self::extraEarnings($emp, $run, $opts) as $code => $x) {
            $el = PulsePrService::element($code);
            if (!$el || !(int) $el['active']) { continue; }
            $amt = PulsePrService::money($x['amount']);
            if (PulsePrService::zero($amt)) { continue; }
            if (!isset($lines[$code])) { $lines[$code] = array('code' => $code, 'element' => $el, 'amount' => 0.0, 'units' => 0.0, 'rate' => 0.0, 'base_amount' => 0.0, 'percent' => 0.0, 'prorated' => 0, 'note' => isset($x['note']) ? $x['note'] : ''); }
            $lines[$code]['amount'] = PulsePrService::money($lines[$code]['amount'] + $amt);
            if (isset($x['note'])) { $lines[$code]['note'] = $x['note']; }
        }

        /* ---- 4: the named bases, built from the elements ---- */
        $bases = self::bases($lines, $emp);
        $fullBases = self::fullBases($full, $lines, $emp);

        /* ---- 5: contributions, reliefs, income tax ---- */
        $ytd = self::ytd($emp, $run, $country);
        $idx = PulsePrService::periodIndex($period, $country);
        $elapsedYear = min($idx['periods'], (int) $ytd['periods'] + 1);
        $ctx = array(
            'country' => $country, 'period' => $period, 'period_from' => $from, 'period_to' => $to,
            'periods_per_year' => $idx['periods'], 'period_index' => $idx['index'], 'remaining_periods' => $idx['remaining'],
            'periods_elapsed_year' => $elapsedYear, 'employee' => $emp, 'declarations' => PulsePrService::declarations((int) $emp['id_pulse_pr_employee'], $to),
            'proration' => $pro, 'joiner' => !empty($pro['joiner']), 'leaver' => !empty($pro['leaver']), 'final_settlement' => $finalSettlement,
            'ytd' => $ytd, 'bases' => $bases, 'full_bases' => $fullBases,
            'period_taxable' => $bases['TAXABLE_GROSS'], 'period_gross' => $bases['GROSS'],
            'period_taxable_recurring_full' => $fullBases['TAXABLE_GROSS'], 'period_gross_recurring_full' => $fullBases['GROSS'],
            'annualisation_periods' => $idx['periods'], 'periods_elapsed' => $elapsedYear,
        );
        $ann = $pack->annualisation($ctx);
        $ctx['annualisation_periods'] = max(1, (int) $ann['periods']);
        $ctx['annualisation_reason'] = $ann['reason'];
        // where the window is shorter than the tax year the cumulative pointer counts inside that window
        $ctx['periods_elapsed'] = min($ctx['annualisation_periods'], max(1, (int) $ytd['periods'] + 1));
        $ctx['contributions'] = $pack->contributions($ctx);
        $ctx['projected_taxable'] = $pack->projectedTaxable($ctx);
        $ctx['projected_gross'] = $ctx['projected_taxable'];
        $ctx['reliefs'] = $pack->reliefs($ctx);
        $paye = $pack->payeForPeriod($ctx);

        /* ---- deduction and employer-cost lines ---- */
        $deductions = array(); $employer = array(); $information = array();
        $payeEl = PulsePrService::element('PAYE');
        if ($payeEl) { $deductions['PAYE'] = array('code' => 'PAYE', 'element' => $payeEl, 'amount' => PulsePrService::money($paye['period_tax']), 'units' => 0, 'rate' => 0, 'base_amount' => $paye['chargeable'], 'percent' => 0, 'prorated' => 0, 'note' => trim(($paye['basis'] === 'annual' ? 'Annual charge '.number_format($paye['annual_tax'], 2).' over '.$ctx['annualisation_periods'].' period(s). ' : '').$paye['note'])); }
        foreach ($ctx['contributions'] as $code => $c) {
            $el = PulsePrService::element($code);
            if ($el && (int) $el['active'] && !PulsePrService::zero($c['employee'])) {
                $deductions[$code] = array('code' => $code, 'element' => $el, 'amount' => PulsePrService::money($c['employee']), 'units' => 0, 'rate' => 0, 'base_amount' => $c['base'], 'percent' => $c['employee_pct'], 'prorated' => 0, 'note' => $c['note'].($c['consent_date'] ? ' (consent '.$c['consent_date'].')' : ''));
            } elseif ($el && $c['mode'] === 'opt_in' && !$c['consented']) {
                $information[$code.'_NOTE'] = array('code' => $code, 'element' => array_merge($el, array('type' => 'information')), 'amount' => 0.0, 'units' => 0, 'rate' => 0, 'base_amount' => 0, 'percent' => 0, 'prorated' => 0, 'note' => $c['note']);
            }
            $erCode = self::employerElementCode($code);
            $erEl = PulsePrService::element($erCode);
            if ($erEl && (int) $erEl['active'] && !PulsePrService::zero($c['employer'])) {
                $employer[$erCode] = array('code' => $erCode, 'element' => $erEl, 'amount' => PulsePrService::money($c['employer']), 'units' => 0, 'rate' => 0, 'base_amount' => $c['base'], 'percent' => $c['employer_pct'], 'prorated' => 0, 'note' => $c['note']);
            }
        }
        // standing non-statutory deductions (union dues, cooperative, damage recovery, voluntary pension)
        foreach ($lines as $code => $l) {
            if ($l['element']['type'] === 'deduction' && $l['element']['calc'] !== 'statutory') { $deductions[$code] = $l; unset($lines[$code]); }
            elseif ($l['element']['type'] === 'employer') { $employer[$code] = $l; unset($lines[$code]); }
            elseif ($l['element']['type'] === 'information') { $information[$code] = $l; unset($lines[$code]); }
        }

        /* ---- 6: loans and arrears, against the protected net floor ---- */
        $earnTotal = 0; foreach ($lines as $l) { $earnTotal = PulsePrService::money($earnTotal + $l['amount']); }
        $dedTotal = 0; foreach ($deductions as $l) { $dedTotal = PulsePrService::money($dedTotal + $l['amount']); }
        $floor = PulsePrService::money($earnTotal * (float) PulsePrService::cfg('MIN_NET_PCT', 0) / 100);
        $available = PulsePrService::money($earnTotal - $dedTotal - $floor);
        $recovery = PulsePrLoan::recoveryFor((int) $emp['id_pulse_pr_employee'], $period, $available, empty($opts['dry']));
        foreach ($recovery['lines'] as $code => $r) {
            $el = PulsePrService::element($code);
            if (!$el || PulsePrService::zero($r['amount'])) { continue; }
            $deductions[$code] = array('code' => $code, 'element' => $el, 'amount' => PulsePrService::money($r['amount']), 'units' => 0, 'rate' => 0, 'base_amount' => 0, 'percent' => 0, 'prorated' => 0, 'note' => $r['note']);
            $dedTotal = PulsePrService::money($dedTotal + $r['amount']);
        }
        if (!PulsePrService::zero($recovery['shortfall'])) {
            $el = PulsePrService::element('ARREARS_NEW');
            if ($el) { $information['ARREARS_NEW'] = array('code' => 'ARREARS_NEW', 'element' => $el, 'amount' => PulsePrService::money($recovery['shortfall']), 'units' => 0, 'rate' => 0, 'base_amount' => 0, 'percent' => 0, 'prorated' => 0, 'note' => 'Recovery could not be taken in full without pushing net pay below the protected floor — parked as arrears'); }
        }
        if (!PulsePrService::zero($paye['over_deducted'])) {
            $el = PulsePrService::element('PAYE_OVER');
            if ($el) { $information['PAYE_OVER'] = array('code' => 'PAYE_OVER', 'element' => $el, 'amount' => PulsePrService::money($paye['over_deducted']), 'units' => 0, 'rate' => 0, 'base_amount' => 0, 'percent' => 0, 'prorated' => 0, 'note' => 'PAYE already deducted this tax year exceeds the tax due on the income actually received. Claim the refund from '.PulsePrService::cfg('TAX_STATE', 'the state internal revenue service').'.'); }
        }

        /* ---- 7: net ---- */
        $net = PulsePrService::money($earnTotal - $dedTotal);
        if ($net < 0) { $net = 0.0; }
        $employerCost = 0; foreach ($employer as $l) { $employerCost = PulsePrService::money($employerCost + $l['amount']); }

        $contribAmount = function ($c, $side) use ($ctx) { return isset($ctx['contributions'][$c]) ? PulsePrService::money($ctx['contributions'][$c][$side]) : 0.0; };

        $slip = array(
            'id_pulse_pr_employee' => (int) $emp['id_pulse_pr_employee'], 'period' => $period, 'staff_no' => $emp['staff_no'],
            'employee_name' => trim($emp['firstname'].' '.$emp['lastname']), 'department' => $emp['department'], 'position' => $emp['position'],
            'grade' => $emp['grade'], 'cost_centre' => $emp['cost_centre'] ? $emp['cost_centre'] : $emp['department'],
            'days_paid' => $pro['days'], 'days_in_period' => $pro['days_in_period'], 'proration' => $pro['factor'],
            'basic' => $bases['BASIC'], 'bht' => $bases['BHT'], 'gross' => $earnTotal, 'taxable_gross' => $bases['TAXABLE_GROSS'], 'pensionable' => $bases['BHT'],
            'paye' => isset($deductions['PAYE']) ? $deductions['PAYE']['amount'] : 0.0, 'paye_annual' => PulsePrService::money($paye['annual_tax']),
            'annualisation_periods' => (int) $ctx['annualisation_periods'], 'chargeable_income' => PulsePrService::money($paye['chargeable']),
            'reliefs_total' => PulsePrService::money(isset($paye['relief_total']) ? $paye['relief_total'] : 0),
            'pension_ee' => $contribAmount('PENSION', 'employee'), 'pension_er' => $contribAmount('PENSION', 'employer'),
            'nhf' => $contribAmount('NHF', 'employee'), 'nhis' => $contribAmount('NHIS', 'employee'),
            'nhf_consent_date' => isset($ctx['contributions']['NHF']) ? $ctx['contributions']['NHF']['consent_date'] : null,
            'nsitf_er' => $contribAmount('NSITF', 'employer'), 'itf_er' => $contribAmount('ITF', 'employer'),
            'loan_recovered' => PulsePrService::money($recovery['loan']), 'arrears_added' => PulsePrService::money($recovery['shortfall']), 'arrears_recovered' => PulsePrService::money($recovery['arrears']),
            'total_earnings' => $earnTotal, 'total_deductions' => $dedTotal, 'net_pay' => $net, 'employer_cost' => $employerCost,
            'ytd_gross' => PulsePrService::money($ytd['gross'] + $earnTotal), 'ytd_taxable' => PulsePrService::money($ytd['taxable'] + $bases['TAXABLE_GROSS']),
            'ytd_paye' => PulsePrService::money($ytd['paye'] + (isset($deductions['PAYE']) ? $deductions['PAYE']['amount'] : 0)),
            'ytd_pension_ee' => PulsePrService::money($ytd['pension_ee'] + $contribAmount('PENSION', 'employee')),
            'ytd_nhf' => PulsePrService::money($ytd['nhf'] + $contribAmount('NHF', 'employee')), 'ytd_net' => PulsePrService::money($ytd['net'] + $net),
            'bank_name' => $emp['bank_name'], 'bank_code' => $emp['bank_code'], 'account_no' => $emp['account_no'], 'pay_method' => $emp['pay_method'],
        );
        return array('slip' => $slip, 'lines' => self::flatten($lines, $deductions, $employer, $information), 'paye' => $paye, 'ctx' => $ctx, 'bases' => $bases, 'proration' => $pro, 'recovery' => $recovery);
    }

    /** ER_PENSION / ER_NSITF / ER_ITF / ER_NHIS — one convention so a new scheme needs no code change. */
    protected static function employerElementCode($code) { return 'ER_'.$code; }

    /**
     * Split the period wherever the standing structure changes date. One segment is the normal case; a
     * promotion effective on the 16th produces two, each valued at its own rate for its own days.
     */
    protected static function segments(array $emp, $from, $to, array $pro)
    {
        $id = (int) $emp['id_pulse_pr_employee'];
        $changes = Db::getInstance()->executeS('SELECT DISTINCT effective_from FROM `'._DB_PREFIX_.'pulse_pr_employee_element` WHERE id_pulse_pr_employee='.$id.' AND effective_from>"'.pSQL($from).'" AND effective_from<="'.pSQL($to).'" ORDER BY effective_from');
        if (!$changes || $pro['factor'] <= 0) { return array(array('from' => $from, 'to' => $to, 'effective_on' => $to, 'factor' => $pro['factor'])); }
        $bounds = array($from);
        foreach ($changes as $c) { $bounds[] = $c['effective_from']; }
        $segments = array(); $n = count($bounds);
        $calendarDays = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
        $allocated = 0;
        for ($i = 0; $i < $n; $i++) {
            $segFrom = $bounds[$i];
            $segTo = $i + 1 < $n ? date('Y-m-d', strtotime($bounds[$i + 1].' -1 day')) : $to;
            $segEmp = array_merge($emp, array());
            $segPro = PulsePrService::proration($segEmp, $segFrom, $segTo, null);
            // scale each segment against the whole period's denominator so the segments sum to the period factor
            $segCal = (int) round((strtotime($segTo) - strtotime($segFrom)) / 86400) + 1;
            $share = $calendarDays > 0 ? $segCal / $calendarDays : 0;
            $factor = $i + 1 < $n ? round($pro['factor'] * $share, 6) : round($pro['factor'] - $allocated, 6);
            $allocated = round($allocated + $factor, 6);
            $segments[] = array('from' => $segFrom, 'to' => $segTo, 'effective_on' => $segTo, 'factor' => max(0, $factor), 'days' => $segPro['days']);
        }
        return $segments;
    }

    /**
     * Value a structure in sequence order. Each element may refer to a named base or to an element
     * already computed, so BASIC is known by the time HOUSING asks for a percentage of it.
     */
    protected static function valueStructure(array $structure, array $emp, $ts, array $opts)
    {
        $out = array(); $computed = array();
        $package = (float) $emp['pay_rate'];
        foreach ($structure as $s) {
            $code = $s['element_code'];
            // the assignment decides how the element is valued: a percentage on the line wins, then a fixed
            // amount on the line, and only failing both does the element's own default calculation apply
            $calc = $s['calc'];
            if (in_array($calc, array('fixed', 'percent'))) { $calc = (float) $s['percent'] > 0 ? 'percent' : ((float) $s['amount'] > 0 ? 'fixed' : $calc); }
            $percentOf = !empty($s['percent_of']) ? Tools::strtoupper($s['percent_of']) : 'PACKAGE';
            $el = array('code' => $code, 'name' => $s['name'], 'type' => $s['type'], 'calc' => $calc, 'percent_of' => $percentOf,
                'taxable' => $s['taxable'], 'pensionable' => $s['pensionable'], 'nsitfable' => $s['nsitfable'], 'in_basic' => $s['in_basic'],
                'proratable' => $s['proratable'], 'recurring' => $s['recurring'], 'gl_account' => $s['gl_account'], 'sequence' => $s['sequence'],
                'statutory_code' => $s['statutory_code'], 'formula' => $s['formula']);
            $amount = 0.0; $units = 0.0; $rate = 0.0; $base = 0.0; $percent = (float) $s['percent'];
            switch ($calc) {
                case 'percent':
                    $base = self::resolveBase($percentOf, $computed, $emp, $package);
                    $percent = (float) $s['percent'] > 0 ? (float) $s['percent'] : (float) $s['default_value'];
                    $amount = PulsePrService::money($base * $percent / 100);
                    break;
                case 'rate_units':
                    $rate = (float) $s['amount'] > 0 ? (float) $s['amount'] : (float) $s['default_value'];
                    $units = self::unitsFor($code, $s, $ts, $emp);
                    $amount = PulsePrService::money($rate * $units);
                    break;
                case 'formula':
                    $amount = PulsePrService::money(self::evaluate($s['formula'], array_merge(self::basesFromComputed($computed, $emp), $computed, array('PACKAGE' => $package))));
                    break;
                case 'statutory':
                    $amount = 0.0;
                    break;
                case 'fixed':
                default:
                    $amount = PulsePrService::money((float) $s['amount'] > 0 ? (float) $s['amount'] : (float) $s['default_value']);
                    break;
            }
            $computed[$code] = $amount;
            $out[$code] = array('element' => $el, 'amount' => $amount, 'units' => $units, 'rate' => $rate, 'base' => $base, 'percent' => $percent);
        }
        return $out;
    }

    /** Units for a rate x units element: from the approved timesheet where the code is a known one. */
    protected static function unitsFor($code, array $s, $ts, array $emp)
    {
        if ((float) $s['units'] > 0) { return (float) $s['units']; }
        if (!$ts) { return 0.0; }
        $map = array('OT' => 'ot_hours', 'OT_REST' => 'ot_rest_hours', 'OT_HOLIDAY' => 'ot_holiday_hours', 'SHIFT' => 'night_shifts', 'ABSENCE' => 'unpaid_days');
        if (!isset($map[$code])) { return 0.0; }
        return (float) (isset($ts[$map[$code]]) ? $ts[$map[$code]] : 0);
    }

    /** Resolve a named base or a previously computed element code. */
    protected static function resolveBase($name, array $computed, array $emp, $package)
    {
        $name = Tools::strtoupper((string) $name);
        if ($name === '' || $name === 'PACKAGE') { return (float) $package; }
        if (isset($computed[$name])) { return (float) $computed[$name]; }
        $b = self::basesFromComputed($computed, $emp);
        return isset($b[$name]) ? (float) $b[$name] : 0.0;
    }

    /** The named bases as they stand part-way through valuing a structure. */
    protected static function basesFromComputed(array $computed, array $emp)
    {
        $basic = 0; $bht = 0; $gross = 0; $taxable = 0;
        foreach ($computed as $code => $amt) {
            $el = PulsePrService::element($code);
            if (!$el || $el['type'] !== 'earning') { continue; }
            $gross += (float) $amt;
            if ((int) $el['taxable']) { $taxable += (float) $amt; }
            if ((int) $el['pensionable']) { $bht += (float) $amt; }
            if ((int) $el['in_basic']) { $basic += (float) $amt; }
        }
        return array('PACKAGE' => (float) $emp['pay_rate'], 'BASIC' => round($basic, 2), 'BHT' => round($bht, 2), 'GROSS' => round($gross, 2), 'TAXABLE_GROSS' => round($taxable, 2), 'NSITF_BASE' => round($gross, 2), 'NET_BEFORE_LOAN' => 0.0);
    }

    /**
     * A percent-of-package structure whose percentages add to 100 must produce elements that add to the
     * package exactly. Any rounding crumb lands on the largest element rather than quietly widening gross.
     */
    protected static function balancePercentStructure(array $lines, array $emp, array $pro)
    {
        $pct = 0; $sum = 0; $largest = null;
        foreach ($lines as $code => $l) {
            if ($l['element']['type'] !== 'earning' || $l['element']['calc'] !== 'percent' || Tools::strtoupper($l['element']['percent_of']) !== 'PACKAGE') { continue; }
            $pct += (float) $l['percent']; $sum = round($sum + $l['amount'], 2);
            if ($largest === null || $l['amount'] > $lines[$largest]['amount']) { $largest = $code; }
        }
        if ($largest === null || abs($pct - 100) > 0.0001) { return $lines; }
        $target = PulsePrService::money((float) $emp['pay_rate'] * $pro['factor']);
        $drift = round($target - $sum, 2);
        if (abs($drift) >= 0.005) { $lines[$largest]['amount'] = PulsePrService::money($lines[$largest]['amount'] + $drift); $lines[$largest]['note'] = trim($lines[$largest]['note'].' Carries the '.number_format($drift, 2).' rounding difference so the elements sum to the package.'); }
        return $lines;
    }

    /** One-off earnings that are not standing structure: a service-charge share, a bonus, back pay. */
    protected static function extraEarnings(array $emp, array $run, array $opts)
    {
        $out = array();
        if (!empty($opts['tronc'])) { $out['TRONC'] = array('amount' => (float) $opts['tronc'], 'note' => 'Service charge distribution '.$run['period']); }
        foreach ((isset($opts['one_off']) ? (array) $opts['one_off'] : array()) as $code => $amount) {
            if (PulsePrService::zero($amount)) { continue; }
            $out[Tools::strtoupper($code)] = array('amount' => (float) $amount, 'note' => 'Entered on the run');
        }
        return $out;
    }

    /** The named bases, built once from the final element lines. */
    public static function bases(array $lines, array $emp)
    {
        $basic = 0; $bht = 0; $gross = 0; $taxable = 0; $nsitf = 0;
        foreach ($lines as $l) {
            if ($l['element']['type'] !== 'earning') { continue; }
            $gross = round($gross + $l['amount'], 2);
            if ((int) $l['element']['taxable']) { $taxable = round($taxable + $l['amount'], 2); }
            if ((int) $l['element']['pensionable']) { $bht = round($bht + $l['amount'], 2); }
            if ((int) $l['element']['in_basic']) { $basic = round($basic + $l['amount'], 2); }
            if ((int) $l['element']['nsitfable']) { $nsitf = round($nsitf + $l['amount'], 2); }
        }
        return array('PACKAGE' => (float) $emp['pay_rate'], 'BASIC' => $basic, 'BHT' => $bht, 'GROSS' => $gross, 'TAXABLE_GROSS' => $taxable, 'NSITF_BASE' => $nsitf, 'NET_BEFORE_LOAN' => 0.0);
    }

    /** The same bases at the full contractual rate, for the annualisation projection of a part-period. */
    protected static function fullBases(array $full, array $lines, array $emp)
    {
        $b = array('PACKAGE' => (float) $emp['pay_rate'], 'BASIC' => 0.0, 'BHT' => 0.0, 'GROSS' => 0.0, 'TAXABLE_GROSS' => 0.0, 'NSITF_BASE' => 0.0, 'NET_BEFORE_LOAN' => 0.0);
        foreach ($full as $code => $amt) {
            $el = isset($lines[$code]) ? $lines[$code]['element'] : PulsePrService::element($code);
            if (!$el || $el['type'] !== 'earning') { continue; }
            $b['GROSS'] = round($b['GROSS'] + $amt, 2);
            if ((int) $el['taxable']) { $b['TAXABLE_GROSS'] = round($b['TAXABLE_GROSS'] + $amt, 2); }
            if ((int) $el['pensionable']) { $b['BHT'] = round($b['BHT'] + $amt, 2); }
            if ((int) $el['in_basic']) { $b['BASIC'] = round($b['BASIC'] + $amt, 2); }
            if ((int) $el['nsitfable']) { $b['NSITF_BASE'] = round($b['NSITF_BASE'] + $amt, 2); }
        }
        return $b;
    }

    /**
     * Year-to-date for the employee's tax year: what Pulse has already paid plus any opening balance
     * carried in from the system the property used before. Excludes the run being calculated.
     */
    public static function ytd(array $emp, array $run, $country)
    {
        $year = PulsePrService::taxYear($run['period'], $country);
        $c = PulsePrService::countryRow($country);
        $startMonth = (int) Tools::substr($c && $c['tax_year_start'] ? $c['tax_year_start'] : '01-01', 0, 2);
        $yearFrom = date('Y-m', mktime(0, 0, 0, $startMonth, 1, $year));
        $r = Db::getInstance()->getRow('SELECT COUNT(*) periods, COALESCE(SUM(p.gross),0) gross, COALESCE(SUM(p.taxable_gross),0) taxable, COALESCE(SUM(p.paye),0) paye, COALESCE(SUM(p.pension_ee),0) pension_ee, COALESCE(SUM(p.nhf),0) nhf, COALESCE(SUM(p.net_pay),0) net
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.id_pulse_pr_employee='.(int) $emp['id_pulse_pr_employee'].' AND p.period>="'.pSQL($yearFrom).'" AND p.period<"'.pSQL($run['period']).'" AND r.status<>"cancelled"');
        $o = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_opening` WHERE id_pulse_pr_employee='.(int) $emp['id_pulse_pr_employee'].' AND tax_year='.(int) $year);
        $inWindow = (int) PulsePrService::num($r, 'periods');
        return array(
            'periods' => (int) PulsePrService::num($r, 'periods') + ($o ? (int) PulsePrService::num($o, 'periods') : 0), 'periods_paid_here' => $inWindow,
            'gross' => round(PulsePrService::num($r, 'gross') + ($o ? PulsePrService::num($o, 'gross') : 0), 2), 'taxable' => round(PulsePrService::num($r, 'taxable') + ($o ? PulsePrService::num($o, 'taxable') : 0), 2),
            'paye' => round(PulsePrService::num($r, 'paye') + ($o ? PulsePrService::num($o, 'paye') : 0), 2), 'pension_ee' => round(PulsePrService::num($r, 'pension_ee') + ($o ? PulsePrService::num($o, 'pension_ee') : 0), 2),
            'nhf' => round(PulsePrService::num($r, 'nhf') + ($o ? PulsePrService::num($o, 'nhf') : 0), 2), 'net' => round(PulsePrService::num($r, 'net') + ($o ? PulsePrService::num($o, 'net') : 0), 2),
            'tax_year' => $year,
        );
    }

    /** Turn the four buckets into a flat, ordered payslip line list. */
    protected static function flatten(array $earnings, array $deductions, array $employer, array $information)
    {
        $out = array();
        foreach (array($earnings, $deductions, $employer, $information) as $bucket) {
            foreach ($bucket as $key => $l) {
                $el = $l['element'];
                $out[] = array(
                    'element_code' => $l['code'], 'element_name' => $el['name'], 'type' => $el['type'],
                    'amount' => PulsePrService::money($l['amount']), 'units' => round((float) $l['units'], 3), 'rate' => round((float) $l['rate'], 2),
                    'base_amount' => round((float) $l['base_amount'], 2), 'percent' => round((float) $l['percent'], 4),
                    'taxable' => (int) $el['taxable'], 'pensionable' => (int) $el['pensionable'], 'prorated' => (int) $l['prorated'],
                    'gl_account' => isset($el['gl_account']) ? $el['gl_account'] : '', 'sequence' => (int) $el['sequence'],
                    'note' => Tools::substr((string) $l['note'], 0, 160),
                );
            }
        }
        usort($out, array('PulsePrCalc', 'compareLines'));
        return $out;
    }

    /** Deterministic ordering — a re-run must produce byte-identical output, so ties break on the code. */
    public static function compareLines($a, $b)
    {
        if ($a['sequence'] !== $b['sequence']) { return $a['sequence'] < $b['sequence'] ? -1 : 1; }
        return strcmp($a['element_code'], $b['element_code']);
    }

    /**
     * A tiny arithmetic evaluator for formula elements: numbers, named bases, element codes, + - * / and
     * parentheses. No eval(), no function calls, nothing a payroll formula has any business doing.
     */
    public static function evaluate($expr, array $vars)
    {
        $expr = (string) $expr;
        if (trim($expr) === '') { return 0.0; }
        if (!preg_match('/^[A-Za-z0-9_ .+\-*\/()]*$/', $expr)) { throw new PrestaShopException('A formula may only contain names, numbers, + - * / and parentheses'); }
        preg_match_all('/[A-Za-z_][A-Za-z0-9_]*|[0-9]*\.?[0-9]+|[()+\-*\/]/', $expr, $m);
        $tokens = $m[0];
        foreach ($tokens as $i => $t) {
            if (preg_match('/^[A-Za-z_]/', $t)) {
                $k = Tools::strtoupper($t);
                if (!array_key_exists($k, $vars)) { throw new PrestaShopException('Unknown name "'.$t.'" in formula'); }
                $tokens[$i] = (string) (float) $vars[$k];
            }
        }
        $pos = 0;
        $value = self::parseExpr($tokens, $pos);
        if ($pos < count($tokens)) { throw new PrestaShopException('Could not parse the formula near "'.$tokens[$pos].'"'); }
        return $value;
    }

    protected static function parseExpr(array $t, &$i)
    {
        $v = self::parseTerm($t, $i);
        while ($i < count($t) && ($t[$i] === '+' || $t[$i] === '-')) { $op = $t[$i]; $i++; $r = self::parseTerm($t, $i); $v = $op === '+' ? $v + $r : $v - $r; }
        return $v;
    }

    protected static function parseTerm(array $t, &$i)
    {
        $v = self::parseFactor($t, $i);
        while ($i < count($t) && ($t[$i] === '*' || $t[$i] === '/')) {
            $op = $t[$i]; $i++; $r = self::parseFactor($t, $i);
            if ($op === '/') { if (abs($r) < 1e-12) { throw new PrestaShopException('Division by zero in formula'); } $v = $v / $r; } else { $v = $v * $r; }
        }
        return $v;
    }

    protected static function parseFactor(array $t, &$i)
    {
        if ($i >= count($t)) { throw new PrestaShopException('Formula ends unexpectedly'); }
        if ($t[$i] === '-') { $i++; return -self::parseFactor($t, $i); }
        if ($t[$i] === '+') { $i++; return self::parseFactor($t, $i); }
        if ($t[$i] === '(') { $i++; $v = self::parseExpr($t, $i); if ($i >= count($t) || $t[$i] !== ')') { throw new PrestaShopException('Unbalanced parentheses in formula'); } $i++; return $v; }
        if (!is_numeric($t[$i])) { throw new PrestaShopException('Expected a number in formula, found "'.$t[$i].'"'); }
        $v = (float) $t[$i]; $i++;
        return $v;
    }
}
