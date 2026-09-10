<?php
/**
 * The synthetic payroll self-check.
 *
 * It builds a handful of throw-away employees on the shipped Nigerian structure, runs them through the
 * real calculator — not a parallel implementation — and asserts the answers against figures an accountant
 * can reproduce with a calculator and the published bands. It then deletes everything it created.
 *
 * What it proves:
 *   1. the elements always sum to the printed gross, at four salary levels;
 *   2. pension is 8/10 of basic + housing + transport and nothing else;
 *   3. annual PAYE de-annualised over twelve months sums back to the annual charge exactly;
 *   4. rent relief is 20% of declared rent capped at N500,000, and only where the evidence is verified;
 *   5. a service-charge share is taxed but is not pensionable;
 *   6. a mid-year joiner is annualised over the periods remaining, not over twelve;
 *   7. a leaver's final settlement closes the year on income actually received, and an over-deduction is
 *      reported rather than silently kept;
 *   8. NHF is not deducted without a recorded, dated consent, and is deducted with one;
 *   9. a loan recovery that would push net pay below zero is capped and the shortfall becomes arrears;
 *  10. a second calculation of an unchanged period is byte-identical to the first.
 *
 * Run it from Payroll Settings, or  php modules/pulsepayroll/cron/payroll.php <token> selfcheck
 */
class PulsePrSelfCheck
{
    const PREFIX = 'ZZSELFCHK';

    /** @return array cases (each with expected/actual/pass), passed, failed, ok */
    public static function run()
    {
        $cases = array();
        try {
            self::cleanup();
            $cases = array_merge($cases, self::salaryLevels());
            $cases = array_merge($cases, self::rentRelief());
            $cases = array_merge($cases, self::troncTreatment());
            $cases = array_merge($cases, self::nhfConsent());
            $cases = array_merge($cases, self::joiner());
            $cases = array_merge($cases, self::leaver());
            $cases = array_merge($cases, self::loanFloor());
            $cases = array_merge($cases, self::deannualisation());
            $cases = array_merge($cases, self::historicalRegime());
            $cases = array_merge($cases, self::secondPack());
            $cases = array_merge($cases, self::determinism());
        } catch (Exception $e) {
            $cases[] = array('case' => 'self-check', 'metric' => 'execution', 'expected' => 'completes', 'actual' => $e->getMessage(), 'pass' => false);
        }
        self::cleanup();
        $passed = 0; $failed = 0;
        foreach ($cases as $c) { if ($c['pass']) { $passed++; } else { $failed++; } }
        PulsePrService::log(null, 'self_check', 'run', array('passed' => $passed, 'failed' => $failed));
        return array('cases' => $cases, 'passed' => $passed, 'failed' => $failed, 'ok' => $failed === 0, 'run_at' => date('Y-m-d H:i:s'));
    }

    /* ---------------- the cases ---------------- */

    /** A room attendant, a supervisor, a duty manager and a GM, all on the shipped 40/25/15/10/10 split. */
    protected static function salaryLevels()
    {
        $expect = array(
            array('label' => 'Room attendant', 'monthly' => 150000, 'basic' => 60000, 'bht' => 120000, 'pension_ee' => 9600, 'pension_er' => 12000, 'annual_paye' => 132720, 'paye' => 11060, 'nsitf' => 1500, 'itf' => 1500, 'net' => 129340),
            array('label' => 'F&B supervisor', 'monthly' => 450000, 'basic' => 180000, 'bht' => 360000, 'pension_ee' => 28800, 'pension_er' => 36000, 'annual_paye' => 699792, 'paye' => 58316, 'nsitf' => 4500, 'itf' => 4500, 'net' => 362884),
            array('label' => 'Duty manager', 'monthly' => 1200000, 'basic' => 480000, 'bht' => 960000, 'pension_ee' => 76800, 'pension_er' => 96000, 'annual_paye' => 2260464, 'paye' => 188372, 'nsitf' => 12000, 'itf' => 12000, 'net' => 934828),
            array('label' => 'General manager', 'monthly' => 4000000, 'basic' => 1600000, 'bht' => 3200000, 'pension_ee' => 256000, 'pension_er' => 320000, 'annual_paye' => 9263440, 'paye' => 771953.33, 'nsitf' => 40000, 'itf' => 40000, 'net' => 2972046.67),
        );
        $out = array();
        // build the whole synthetic roster before calculating anybody: the employer-side size tests
        // (pension from 3 staff, ITF from 5) are property-wide, so they must see a property, not one row
        $ids = array();
        foreach ($expect as $i => $e) { $ids[$i] = self::employee('L'.$i, $e['monthly'], array('hire_date' => '2024-01-01')); }
        self::employee('L9', 150000, array('hire_date' => '2024-01-01'));
        foreach ($expect as $i => $e) {
            $id = $ids[$i];
            $r = self::calc($id, '2026-08');
            $s = $r['slip'];
            $sumEarnings = 0; foreach ($r['lines'] as $l) { if ($l['type'] === 'earning') { $sumEarnings = round($sumEarnings + $l['amount'], 2); } }
            $out[] = self::assertEq($e['label'], 'gross', $e['monthly'], $s['gross']);
            $out[] = self::assertEq($e['label'], 'earning lines sum to gross', $s['gross'], $sumEarnings);
            $out[] = self::assertEq($e['label'], 'basic (40%)', $e['basic'], $s['basic']);
            $out[] = self::assertEq($e['label'], 'pension base BHT (80%)', $e['bht'], $s['bht']);
            $out[] = self::assertEq($e['label'], 'pension employee 8% of BHT', $e['pension_ee'], $s['pension_ee']);
            $out[] = self::assertEq($e['label'], 'pension employer 10% of BHT', $e['pension_er'], $s['pension_er']);
            $out[] = self::assertEq($e['label'], 'annual PAYE charge', $e['annual_paye'], $s['paye_annual']);
            $out[] = self::assertEq($e['label'], 'PAYE this month', $e['paye'], $s['paye']);
            $out[] = self::assertEq($e['label'], 'NSITF 1% of gross (employer)', $e['nsitf'], $s['nsitf_er']);
            $out[] = self::assertEq($e['label'], 'ITF 1% of gross (employer, accrued)', $e['itf'], $s['itf_er']);
            $out[] = self::assertEq($e['label'], 'net pay', $e['net'], $s['net_pay']);
            $out[] = self::assertEq($e['label'], 'NHF without consent', 0, $s['nhf']);
        }
        return $out;
    }

    /** Rent relief: capped at N500,000, and withheld entirely until the evidence is marked verified. */
    protected static function rentRelief()
    {
        $out = array();
        $id = self::employee('R1', 4000000, array('hire_date' => '2024-01-01'));
        self::declare($id, 'RENT', 6000000, 0);
        $r = self::calc($id, '2026-08');
        $out[] = self::assertEq('GM, rent declared but evidence NOT verified', 'annual PAYE unchanged', 9263440, $r['slip']['paye_annual']);
        $out[] = self::assertEq('GM, rent declared but evidence NOT verified', 'relief given', 0, $r['slip']['reliefs_total'] - self::pensionRelief($r));
        self::declare($id, 'RENT', 6000000, 1);
        $r = self::calc($id, '2026-08');
        $out[] = self::assertEq('GM, rent N6,000,000 evidenced', 'rent relief (20% capped at 500,000)', 500000, self::reliefAmount($r, 'RENT'));
        $out[] = self::assertEq('GM, rent N6,000,000 evidenced', 'chargeable income', 44428000, $r['slip']['chargeable_income']);
        $out[] = self::assertEq('GM, rent N6,000,000 evidenced', 'annual PAYE charge', 9148440, $r['slip']['paye_annual']);
        $out[] = self::assertEq('GM, rent N6,000,000 evidenced', 'PAYE this month', 762370, $r['slip']['paye']);
        $out[] = self::assertEq('GM, rent N6,000,000 evidenced', 'annual saving vs no relief (500,000 at 23%)', 115000, round(9263440 - $r['slip']['paye_annual'], 2));
        return $out;
    }

    /** A service-charge share is taxable pay but never enters the pension base. */
    protected static function troncTreatment()
    {
        $id = self::employee('T1', 450000, array('hire_date' => '2024-01-01'));
        $r = self::calc($id, '2026-08', array('tronc' => 45000));
        return array(
            self::assertEq('Supervisor + N45,000 service charge', 'gross', 495000, $r['slip']['gross']),
            self::assertEq('Supervisor + N45,000 service charge', 'taxable gross', 495000, $r['slip']['taxable_gross']),
            self::assertEq('Supervisor + N45,000 service charge', 'pension base unchanged at BHT', 360000, $r['slip']['bht']),
            self::assertEq('Supervisor + N45,000 service charge', 'pension employee unchanged', 28800, $r['slip']['pension_ee']),
            self::assertEq('Supervisor + N45,000 service charge', 'annual PAYE (699,792 + 45,000 at 18%)', 707892, $r['slip']['paye_annual']),
            self::assertEq('Supervisor + N45,000 service charge', 'NSITF 1% of the larger gross', 4950, $r['slip']['nsitf_er']),
        );
    }

    /** NHF is voluntary from 2026: nothing without a dated consent, 2.5% of basic with one. */
    protected static function nhfConsent()
    {
        $out = array();
        $id = self::employee('N1', 450000, array('hire_date' => '2024-01-01'));
        $r = self::calc($id, '2026-08');
        $out[] = self::assertEq('Supervisor, no NHF consent', 'NHF deducted', 0, $r['slip']['nhf']);
        $out[] = self::assertEq('Supervisor, no NHF consent', 'net pay', 362884, $r['slip']['net_pay']);
        self::consent($id, 'NHF_CONSENT', '2026-01-15');
        $r = self::calc($id, '2026-08');
        $out[] = self::assertEq('Supervisor, NHF consent on file', 'NHF 2.5% of basic 180,000', 4500, $r['slip']['nhf']);
        $out[] = self::assertEq('Supervisor, NHF consent on file', 'consent date shown on the payslip', '2026-01-15', $r['slip']['nhf_consent_date']);
        // annual chargeable falls by 12 x 4,500 = 54,000, taxed at 18%, so the annual charge falls by 9,720
        $out[] = self::assertEq('Supervisor, NHF consent on file', 'annual PAYE (NHF is pre-tax)', 690072, $r['slip']['paye_annual']);
        return $out;
    }

    /** A joiner on 16 September is annualised over the four periods remaining, not over twelve. */
    protected static function joiner()
    {
        $id = self::employee('J1', 150000, array('hire_date' => '2026-09-16'));
        $r = self::calc($id, '2026-09');
        return array(
            self::assertEq('Joiner 16 Sep 2026 at N150,000', 'days paid', 15, $r['slip']['days_paid']),
            self::assertEq('Joiner 16 Sep 2026 at N150,000', 'gross (15/30 of 150,000)', 75000, $r['slip']['gross']),
            self::assertEq('Joiner 16 Sep 2026 at N150,000', 'pension base BHT', 60000, $r['slip']['bht']),
            self::assertEq('Joiner 16 Sep 2026 at N150,000', 'pension employee', 4800, $r['slip']['pension_ee']),
            self::assertEq('Joiner 16 Sep 2026 at N150,000', 'annualisation periods', 4, $r['slip']['annualisation_periods']),
            self::assertEq('Joiner 16 Sep 2026 at N150,000', 'chargeable income (525,000 less 33,600 pension)', 491400, $r['slip']['chargeable_income']),
            self::assertEq('Joiner 16 Sep 2026 at N150,000', 'annual PAYE (below the 800,000 threshold)', 0, $r['slip']['paye_annual']),
            self::assertEq('Joiner 16 Sep 2026 at N150,000', 'PAYE this month', 0, $r['slip']['paye']),
            self::assertEq('Joiner 16 Sep 2026 at N150,000', 'net pay', 70200, $r['slip']['net_pay']),
        );
    }

    /**
     * A supervisor leaving on 10 September, having been paid Jan to Aug at 450,000. The year closes on
     * the income actually received, which is less than the projection those eight months were taxed on,
     * so the tax already deducted exceeds the tax due. The default is to clamp the deduction at zero and
     * report the over-deduction for the employee to reclaim.
     */
    protected static function leaver()
    {
        $id = self::employee('X1', 450000, array('hire_date' => '2024-01-01', 'exit_date' => '2026-09-10', 'status' => 'exited'));
        self::opening($id, 2026, 8, 3600000, 3600000, 466528, 230400);
        $r = self::calc($id, '2026-09', array(), 'final_settlement');
        return array(
            self::assertEq('Leaver 10 Sep 2026, supervisor', 'days paid', 10, $r['slip']['days_paid']),
            self::assertEq('Leaver 10 Sep 2026, supervisor', 'gross (10/30 of 450,000)', 150000, $r['slip']['gross']),
            self::assertEq('Leaver 10 Sep 2026, supervisor', 'pension employee', 9600, $r['slip']['pension_ee']),
            self::assertEq('Leaver 10 Sep 2026, supervisor', 'annualisation periods (the year is closed on 9)', 9, $r['slip']['annualisation_periods']),
            self::assertEq('Leaver 10 Sep 2026, supervisor', 'chargeable income (3,750,000 less 240,000 pension)', 3510000, $r['slip']['chargeable_income']),
            self::assertEq('Leaver 10 Sep 2026, supervisor', 'tax due on the year actually earned', 421800, $r['slip']['paye_annual']),
            self::assertEq('Leaver 10 Sep 2026, supervisor', 'PAYE this month (clamped, never negative)', 0, $r['slip']['paye']),
            self::assertEq('Leaver 10 Sep 2026, supervisor', 'PAYE over-deducted, reported to reclaim', 44728, self::informationAmount($r, 'PAYE_OVER')),
            self::assertEq('Leaver 10 Sep 2026, supervisor', 'net pay', 140400, $r['slip']['net_pay']),
        );
    }

    /** A loan instalment bigger than the payslip can bear: capped, and the balance parked as arrears. */
    protected static function loanFloor()
    {
        $id = self::employee('B1', 150000, array('hire_date' => '2024-01-01'));
        self::loan($id, 150000, '2026-08');
        $r = self::calc($id, '2026-08');
        return array(
            self::assertEq('Room attendant with a N150,000 instalment due', 'gross', 150000, $r['slip']['gross']),
            self::assertEq('Room attendant with a N150,000 instalment due', 'loan recovered (all the payslip can bear)', 129340, $r['slip']['loan_recovered']),
            self::assertEq('Room attendant with a N150,000 instalment due', 'shortfall parked as arrears', 20660, $r['slip']['arrears_added']),
            self::assertEq('Room attendant with a N150,000 instalment due', 'net pay is zero, never negative', 0, $r['slip']['net_pay']),
            self::assertEq('Room attendant with a N150,000 instalment due', 'recovery + arrears = the instalment', 150000, round((float) $r['slip']['loan_recovered'] + (float) $r['slip']['arrears_added'], 2)),
        );
    }

    /**
     * Twelve monthly deductions on an unchanged salary must sum to the annual charge exactly. The GM is
     * the awkward case because 9,263,440 / 12 is 771,953.333…, so the months alternate between .33 and
     * .34 and a naive divide-and-round would lose four kobo over the year.
     */
    protected static function deannualisation()
    {
        $id = self::employee('D1', 4000000, array('hire_date' => '2024-01-01'));
        $paidToDate = 0; $months = array();
        for ($m = 1; $m <= 12; $m++) {
            self::opening($id, 2026, $m - 1, round(4000000 * ($m - 1), 2), round(4000000 * ($m - 1), 2), $paidToDate, round(256000 * ($m - 1), 2));
            $r = self::calc($id, '2026-'.str_pad($m, 2, '0', STR_PAD_LEFT));
            $months[$m] = round((float) $r['slip']['paye'], 2);
            $paidToDate = round($paidToDate + $months[$m], 2);
        }
        return array(
            self::assertEq('GM, twelve months de-annualised', 'month 1 PAYE', 771953.33, $months[1]),
            self::assertEq('GM, twelve months de-annualised', 'month 2 PAYE', 771953.34, $months[2]),
            self::assertEq('GM, twelve months de-annualised', 'twelve months sum to the annual charge', 9263440, $paidToDate),
            self::assertEq('GM, twelve months de-annualised', 'no drift against annual / 12 x 12', 0, round($paidToDate - 9263440, 2)),
        );
    }

    /**
     * A back-dated 2025 period must still produce the old answer: the Consolidated Relief Allowance is
     * alive again (higher of N200,000 or 1% of gross, plus 20% of gross), NHF is mandatory again, and the
     * PITA bands apply. Nothing about that is in code — it is entirely the effective_to dates on the rows.
     */
    protected static function historicalRegime()
    {
        $id = self::employee('H1', 450000, array('hire_date' => '2020-01-01'));
        $r = self::calc($id, '2025-08');
        return array(
            self::assertEq('Back-dated 2025, supervisor N450,000', 'gross', 450000, $r['slip']['gross']),
            self::assertEq('Back-dated 2025, supervisor N450,000', 'CRA reinstated (200,000 + 20% of 5,400,000)', 1280000, self::reliefAmount($r, 'CRA')),
            self::assertEq('Back-dated 2025, supervisor N450,000', 'NHF mandatory again, 2.5% of basic', 4500, $r['slip']['nhf']),
            self::assertEq('Back-dated 2025, supervisor N450,000', 'chargeable income under PITA', 3720400, $r['slip']['chargeable_income']),
            self::assertEq('Back-dated 2025, supervisor N450,000', 'annual PAYE on the 2011 bands', 684896, $r['slip']['paye_annual']),
            self::assertEq('Back-dated 2025, supervisor N450,000', 'PAYE this month', 57074.67, $r['slip']['paye']),
            self::assertEq('Back-dated 2025, supervisor N450,000', 'net pay', 359625.33, $r['slip']['net_pay']),
        );
    }

    /**
     * The Ghana pack, driven entirely from the tables by PulsePrStatutoryGeneric: monthly bands applied to
     * the period directly, no annualisation, SSNIT rather than PenCom, and none of the Nigerian schemes.
     * These figures are a seam test, not tax advice — the pack is shipped unverified for exactly that reason.
     */
    protected static function secondPack()
    {
        $id = self::employee('G1', 5000, array('hire_date' => '2024-01-01', 'country' => 'GH', 'currency' => 'GHS'));
        $r = self::calc($id, '2026-08');
        return array(
            self::assertEq('Ghana pack, GHS 5,000 a month', 'gross', 5000, $r['slip']['gross']),
            self::assertEq('Ghana pack, GHS 5,000 a month', 'SSNIT employee 5.5% of basic', 110, self::deductionAmount($r, 'SSNIT')),
            self::assertEq('Ghana pack, GHS 5,000 a month', 'chargeable (monthly basis, SSNIT pre-tax)', 4890, $r['slip']['chargeable_income']),
            self::assertEq('Ghana pack, GHS 5,000 a month', 'PAYE this month on the monthly bands', 821, $r['slip']['paye']),
            self::assertEq('Ghana pack, GHS 5,000 a month', 'no Nigerian NSITF on a Ghanaian employee', 0, $r['slip']['nsitf_er']),
            self::assertEq('Ghana pack, GHS 5,000 a month', 'net pay', 4069, $r['slip']['net_pay']),
        );
    }

    /** The same period calculated twice must produce identical bytes. */
    protected static function determinism()
    {
        $id = self::employee('Z1', 450000, array('hire_date' => '2024-01-01'));
        $a = self::canonical(self::calc($id, '2026-08'));
        $b = self::canonical(self::calc($id, '2026-08'));
        return array(self::assertEq('Re-run of an unchanged period', 'result hash', sha1($a), sha1($b)));
    }

    /* ---------------- helpers ---------------- */

    protected static function canonical(array $r)
    {
        $out = number_format((float) $r['slip']['gross'], 2, '.', '').'|'.number_format((float) $r['slip']['net_pay'], 2, '.', '');
        foreach ($r['lines'] as $l) { $out .= '|'.$l['element_code'].'='.number_format((float) $l['amount'], 2, '.', '').':'.$l['type']; }
        return $out;
    }

    protected static function pensionRelief(array $r) { return isset($r['ctx']['reliefs']['PENSION']) ? (float) $r['ctx']['reliefs']['PENSION']['amount'] : 0.0; }
    protected static function reliefAmount(array $r, $code) { return isset($r['ctx']['reliefs'][$code]) ? (float) $r['ctx']['reliefs'][$code]['amount'] : 0.0; }
    protected static function informationAmount(array $r, $code) { return self::lineAmount($r, $code, 'information'); }
    protected static function deductionAmount(array $r, $code) { return self::lineAmount($r, $code, 'deduction'); }
    protected static function lineAmount(array $r, $code, $type)
    {
        foreach ($r['lines'] as $l) { if ($l['element_code'] === $code && $l['type'] === $type) { return (float) $l['amount']; } }
        return 0.0;
    }

    /** Run the real calculator against a synthetic run header — no run row, no payslip written. */
    protected static function calc($idEmployee, $period, array $opts = array(), $runType = 'regular')
    {
        $emp = PulsePrService::employee($idEmployee);
        if (!$emp) { throw new PrestaShopException('Self-check employee '.$idEmployee.' disappeared'); }
        $run = array(
            'id_pulse_pr_run' => 0, 'run_no' => 'SELFCHK', 'period' => $period, 'run_type' => $runType, 'country' => 'NG', 'currency' => 'NGN',
            'period_from' => PulsePrService::periodFrom($period), 'period_to' => PulsePrService::periodTo($period),
            'pay_date' => PulsePrService::periodTo($period), 'department' => '',
        );
        $opts['dry'] = true;
        return PulsePrCalc::employee($emp, $run, $opts);
    }

    protected static function employee($suffix, $monthly, array $extra = array())
    {
        $staff = self::PREFIX.'-'.$suffix;
        $ex = PulsePrService::employeeByStaffNo($staff);
        $d = array_merge(array(
            'id_pulse_pr_employee' => $ex ? (int) $ex['id_pulse_pr_employee'] : 0, 'staff_no' => $staff, 'firstname' => 'Self', 'lastname' => 'Check '.$suffix,
            'department' => 'general', 'grade' => 'DEFAULT', 'pay_basis' => 'monthly', 'pay_rate' => $monthly, 'country' => 'NG', 'currency' => 'NGN',
            'status' => 'active', 'pay_method' => 'cash', 'employment_type' => 'permanent',
        ), $extra);
        return PulsePrService::saveEmployee($d);
    }

    protected static function declare($idEmployee, $code, $annualValue, $verified)
    {
        Db::getInstance()->delete('pulse_pr_declaration', 'id_pulse_pr_employee='.(int) $idEmployee.' AND code="'.pSQL($code).'"');
        return PulsePrService::saveDeclaration(array('id_pulse_pr_employee' => $idEmployee, 'code' => $code, 'annual_value' => $annualValue,
            'evidence_ref' => 'Self-check tenancy agreement', 'evidence_verified' => $verified ? 1 : 0, 'date_from' => '2026-01-01'));
    }

    protected static function consent($idEmployee, $code, $date)
    {
        Db::getInstance()->delete('pulse_pr_declaration', 'id_pulse_pr_employee='.(int) $idEmployee.' AND code="'.pSQL($code).'"');
        return PulsePrService::saveDeclaration(array('id_pulse_pr_employee' => $idEmployee, 'code' => $code, 'consented' => 1, 'consent_date' => $date,
            'consent_channel' => 'signed form', 'evidence_verified' => 1, 'date_from' => $date));
    }

    protected static function opening($idEmployee, $year, $periods, $gross, $taxable, $paye, $pension)
    {
        Db::getInstance()->delete('pulse_pr_opening', 'id_pulse_pr_employee='.(int) $idEmployee.' AND tax_year='.(int) $year);
        if ($periods <= 0) { return true; }
        return Db::getInstance()->insert('pulse_pr_opening', array(
            'id_pulse_pr_employee' => (int) $idEmployee, 'tax_year' => (int) $year, 'periods' => (int) $periods,
            'gross' => round((float) $gross, 2), 'taxable' => round((float) $taxable, 2), 'paye' => round((float) $paye, 2),
            'pension_ee' => round((float) $pension, 2), 'net' => 0, 'note' => 'self-check', 'date_add' => date('Y-m-d H:i:s'),
        ), true);
    }

    protected static function loan($idEmployee, $amount, $period)
    {
        $id = PulsePrLoan::apply(array('id_pulse_pr_employee' => $idEmployee, 'principal' => $amount, 'instalments' => 1, 'first_period' => $period, 'purpose' => 'self-check'));
        Db::getInstance()->update('pulse_pr_loan', array('status' => 'disbursed', 'date_disbursed' => date('Y-m-d')), 'id_pulse_pr_loan='.(int) $id);
        return $id;
    }

    protected static function assertEq($case, $metric, $expected, $actual)
    {
        $pass = is_numeric($expected) && is_numeric($actual) ? abs((float) $expected - (float) $actual) < 0.005 : (string) $expected === (string) $actual;
        return array('case' => $case, 'metric' => $metric,
            'expected' => is_numeric($expected) ? number_format((float) $expected, 2) : (string) $expected,
            'actual' => is_numeric($actual) ? number_format((float) $actual, 2) : (string) $actual, 'pass' => $pass);
    }

    /** Remove every trace of the check. Called before and after, so a crashed run leaves nothing behind. */
    public static function cleanup()
    {
        $ids = array();
        foreach (Db::getInstance()->executeS('SELECT id_pulse_pr_employee FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE staff_no LIKE "'.pSQL(self::PREFIX).'-%"') as $r) { $ids[] = (int) $r['id_pulse_pr_employee']; }
        if (!$ids) { return true; }
        $in = implode(',', $ids);
        foreach (Db::getInstance()->executeS('SELECT id_pulse_pr_loan FROM `'._DB_PREFIX_.'pulse_pr_loan` WHERE id_pulse_pr_employee IN ('.$in.')') as $l) { Db::getInstance()->delete('pulse_pr_loan_schedule', 'id_pulse_pr_loan='.(int) $l['id_pulse_pr_loan']); }
        Db::getInstance()->delete('pulse_pr_loan', 'id_pulse_pr_employee IN ('.$in.')');
        Db::getInstance()->delete('pulse_pr_arrears', 'id_pulse_pr_employee IN ('.$in.')');
        Db::getInstance()->delete('pulse_pr_declaration', 'id_pulse_pr_employee IN ('.$in.')');
        Db::getInstance()->delete('pulse_pr_opening', 'id_pulse_pr_employee IN ('.$in.')');
        Db::getInstance()->delete('pulse_pr_timesheet', 'id_pulse_pr_employee IN ('.$in.')');
        Db::getInstance()->delete('pulse_pr_employee_element', 'id_pulse_pr_employee IN ('.$in.')');
        Db::getInstance()->delete('pulse_pr_tronc_line', 'id_pulse_pr_employee IN ('.$in.')');
        Db::getInstance()->delete('pulse_pr_employee', 'id_pulse_pr_employee IN ('.$in.')');
        return true;
    }
}
