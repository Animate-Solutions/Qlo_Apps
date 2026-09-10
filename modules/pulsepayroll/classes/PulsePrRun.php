<?php
/**
 * The payroll run: draft → calculated → approved → paid → posted.
 *
 * The controls that make a run trustworthy, borrowed wholesale from the way Pulse Accounts posts:
 *  - a run recalculates from scratch every time, so there is no half-updated state to reason about;
 *  - the result carries a hash, and a re-run of an unchanged period reproduces it exactly;
 *  - a run that does not reconcile (elements not summing to gross, or gross minus deductions not equal
 *    to net) refuses to leave the calculated state;
 *  - approval is one-way: once approved, the figures are frozen and only a reopen (which is audited)
 *    can change them, and a reopen is refused once the run is paid or posted;
 *  - nothing posts twice — the GL journal is idempotent on the run's own reference.
 */
class PulsePrRun
{
    public static function runs(array $f = array(), $limit = 100)
    {
        $w = array('1');
        if (!empty($f['period'])) { $w[] = 'r.period="'.pSQL($f['period']).'"'; }
        if (!empty($f['status'])) { $w[] = 'r.status IN ("'.implode('","', array_map('pSQL', explode(',', $f['status']))).'")'; }
        if (!empty($f['run_type'])) { $w[] = 'r.run_type="'.pSQL($f['run_type']).'"'; }
        return Db::getInstance()->executeS('SELECT r.*, CONCAT(a.firstname," ",a.lastname) approver FROM `'._DB_PREFIX_.'pulse_pr_run` r LEFT JOIN `'._DB_PREFIX_.'employee` a ON a.id_employee=r.approved_by WHERE '.implode(' AND ', $w).' ORDER BY r.period DESC, r.id_pulse_pr_run DESC LIMIT '.(int) $limit);
    }

    public static function get($id) { return Db::getInstance()->getRow('SELECT r.*, CONCAT(a.firstname," ",a.lastname) approver FROM `'._DB_PREFIX_.'pulse_pr_run` r LEFT JOIN `'._DB_PREFIX_.'employee` a ON a.id_employee=r.approved_by WHERE r.id_pulse_pr_run='.(int) $id); }

    /** Create a run. A period may hold one regular run plus any number of supplementary or bonus runs. */
    public static function create(array $d)
    {
        $period = Tools::substr((string) (isset($d['period']) ? $d['period'] : date('Y-m')), 0, 7);
        if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $period)) { throw new PrestaShopException('The period must look like 2026-08'); }
        $type = in_array(isset($d['run_type']) ? $d['run_type'] : '', array('regular', 'supplementary', 'bonus', 'final_settlement', 'casual')) ? $d['run_type'] : 'regular';
        if ($type === 'regular' && Db::getInstance()->getValue('SELECT id_pulse_pr_run FROM `'._DB_PREFIX_.'pulse_pr_run` WHERE period="'.pSQL($period).'" AND run_type="regular" AND status<>"cancelled"')) {
            throw new PrestaShopException('A regular run for '.$period.' already exists — create a supplementary run instead, or cancel the existing one');
        }
        $from = PulsePrService::periodFrom($period); $to = PulsePrService::periodTo($period);
        $payDay = min((int) PulsePrService::cfg('PAY_DAY', 26), (int) date('t', strtotime($from)));
        $row = array(
            'run_no' => pSQL(PulsePrService::nextNo('PR', 5)), 'period' => pSQL($period), 'run_type' => pSQL($type),
            'country' => pSQL(Tools::strtoupper(Tools::substr(isset($d['country']) ? $d['country'] : PulsePrService::country(), 0, 2))),
            'currency' => pSQL(Tools::substr(isset($d['currency']) ? $d['currency'] : PulsePrService::currency(), 0, 3)),
            'period_from' => pSQL($from), 'period_to' => pSQL($to),
            'pay_date' => pSQL(!empty($d['pay_date']) ? $d['pay_date'] : date('Y-m-d', strtotime(Tools::substr($from, 0, 8).str_pad($payDay, 2, '0', STR_PAD_LEFT)))),
            'status' => 'draft', 'department' => pSQL(Tools::substr(isset($d['department']) ? $d['department'] : '', 0, 32)),
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 255)),
            'business_date' => pSQL(PulsePrService::bd()), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        );
        Db::getInstance()->insert('pulse_pr_run', $row, true);
        $id = (int) Db::getInstance()->Insert_ID();
        PulsePrService::log($id, 'run_create', 'run', $row, $id);
        return $id;
    }

    /** Who is in this run: active staff on a monthly basis, plus anyone who left inside the period. */
    public static function population(array $run)
    {
        $w = array('e.country="'.pSQL($run['country']).'"');
        if ($run['department']) { $w[] = 'e.department="'.pSQL($run['department']).'"'; }
        $w[] = '(e.hire_date IS NULL OR e.hire_date<="'.pSQL($run['period_to']).'")';
        $w[] = '(e.exit_date IS NULL OR e.exit_date>="'.pSQL($run['period_from']).'")';
        $w[] = 'e.on_hold=0';
        if ($run['run_type'] === 'final_settlement') { $w[] = 'e.status="exited" AND e.exit_date BETWEEN "'.pSQL($run['period_from']).'" AND "'.pSQL($run['period_to']).'"'; }
        else { $w[] = 'e.status IN ("active","probation","on_leave","exited")'; $w[] = 'e.employment_type<>"casual"'; $w[] = 'e.pay_basis="monthly"'; }
        return Db::getInstance()->executeS('SELECT e.* FROM `'._DB_PREFIX_.'pulse_pr_employee` e WHERE '.implode(' AND ', $w).' ORDER BY e.department, e.lastname, e.firstname, e.id_pulse_pr_employee');
    }

    /**
     * Calculate (or recalculate) the whole run. Wipes the previous result and its loan recoveries first,
     * so a second calculate of an unchanged period lands on exactly the same numbers.
     */
    public static function calculate($id)
    {
        $t0 = microtime(true);
        $run = self::get($id);
        if (!$run) { throw new PrestaShopException('Unknown run'); }
        if (in_array($run['status'], array('approved', 'paid', 'posted'))) { throw new PrestaShopException('Run '.$run['run_no'].' is '.$run['status'].' — reopen it before recalculating'); }
        PulsePrLoan::unwindRun($id);
        Db::getInstance()->delete('pulse_pr_payslip_line', 'id_pulse_pr_run='.(int) $id);
        Db::getInstance()->delete('pulse_pr_payslip', 'id_pulse_pr_run='.(int) $id);

        $tronc = self::troncFor($run);
        $errors = array(); $t = self::emptyTotals(); $canon = array();
        foreach (self::population($run) as $emp) {
            try {
                $r = PulsePrCalc::employee($emp, $run, array('tronc' => isset($tronc[(int) $emp['id_pulse_pr_employee']]) ? $tronc[(int) $emp['id_pulse_pr_employee']] : 0));
                $check = self::reconcile($r);
                if ($check !== true) { $errors[] = $emp['staff_no'].' '.trim($emp['firstname'].' '.$emp['lastname']).': '.$check; continue; }
                $idSlip = self::writePayslip($id, $run, $r);
                PulsePrLoan::stampPayslip($idSlip, $r['recovery']['detail']);
                $t = self::addTotals($t, $r['slip']);
                $canon[] = self::canonical($r);
            } catch (Exception $e) {
                $errors[] = $emp['staff_no'].' '.trim($emp['firstname'].' '.$emp['lastname']).': '.$e->getMessage();
            }
        }
        sort($canon);
        $hash = sha1(implode("\n", $canon));
        Db::getInstance()->update('pulse_pr_run', array(
            'status' => 'calculated', 'headcount' => (int) $t['headcount'], 'total_gross' => $t['gross'], 'total_taxable' => $t['taxable'],
            'total_paye' => $t['paye'], 'total_pension_ee' => $t['pension_ee'], 'total_pension_er' => $t['pension_er'], 'total_nhf' => $t['nhf'],
            'total_nsitf' => $t['nsitf'], 'total_itf' => $t['itf'], 'total_other_ded' => $t['other_ded'], 'total_loan' => $t['loan'],
            'total_arrears' => $t['arrears'], 'total_net' => $t['net'], 'total_employer_cost' => $t['employer_cost'],
            'result_hash' => pSQL($hash), 'calc_ms' => (int) round((microtime(true) - $t0) * 1000),
            'errors' => $errors ? pSQL(implode("\n", $errors), true) : null,
            'calculated_by' => PulsePrService::emp(), 'date_calculated' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), 'id_pulse_pr_run='.(int) $id, 0, true);
        PulsePrService::log($id, 'run_calculate', 'run', array('headcount' => $t['headcount'], 'gross' => $t['gross'], 'net' => $t['net'], 'hash' => $hash, 'errors' => count($errors)), $id);
        PulseCoreService::event('actionPulsePayrollCalculated', array('id_run' => $id, 'period' => $run['period'], 'headcount' => $t['headcount'], 'gross' => $t['gross']));
        return array('headcount' => $t['headcount'], 'errors' => $errors, 'hash' => $hash, 'totals' => $t);
    }

    /** The reconciliation an accountant would do by hand, done on every payslip before it is written. */
    public static function reconcile(array $r)
    {
        $earn = 0; $ded = 0;
        foreach ($r['lines'] as $l) {
            if ($l['type'] === 'earning') { $earn = round($earn + (float) $l['amount'], 2); }
            elseif ($l['type'] === 'deduction') { $ded = round($ded + (float) $l['amount'], 2); }
        }
        $s = $r['slip'];
        if (abs($earn - (float) $s['gross']) > 0.004) { return 'the earning lines total '.number_format($earn, 2).' but the gross is '.number_format($s['gross'], 2); }
        if (abs($ded - (float) $s['total_deductions']) > 0.004) { return 'the deduction lines total '.number_format($ded, 2).' but the deductions total is '.number_format($s['total_deductions'], 2); }
        if (abs(($earn - $ded) - (float) $s['net_pay']) > 0.004 && (float) $s['net_pay'] > 0) { return 'gross '.number_format($earn, 2).' less deductions '.number_format($ded, 2).' does not equal net '.number_format($s['net_pay'], 2); }
        if ((float) $s['net_pay'] < 0) { return 'net pay is negative'; }
        return true;
    }

    protected static function writePayslip($idRun, array $run, array $r)
    {
        $s = $r['slip'];
        $s['id_pulse_pr_run'] = (int) $idRun;
        $s['token'] = self::payslipToken($idRun, (int) $s['id_pulse_pr_employee']);
        $s['date_add'] = date('Y-m-d H:i:s');
        foreach ($s as $k => $v) { if (is_string($v)) { $s[$k] = pSQL($v); } }
        Db::getInstance()->insert('pulse_pr_payslip', $s, true);
        $id = (int) Db::getInstance()->Insert_ID();
        foreach ($r['lines'] as $l) {
            $l['id_pulse_pr_payslip'] = $id; $l['id_pulse_pr_run'] = (int) $idRun;
            foreach ($l as $k => $v) { if (is_string($v)) { $l[$k] = pSQL($v); } }
            Db::getInstance()->insert('pulse_pr_payslip_line', $l, true);
        }
        return $id;
    }

    /** A payslip URL must not be guessable; the token is derived from the shop cookie key and a nonce. */
    public static function payslipToken($idRun, $idEmployee)
    {
        return Tools::substr(hash('sha256', _COOKIE_KEY_.'|payslip|'.(int) $idRun.'|'.(int) $idEmployee.'|'.Tools::passwdGen(16)), 0, 48);
    }

    /** The canonical string a run hashes, so "byte-identical" is a testable claim and not a hope. */
    protected static function canonical(array $r)
    {
        $s = $r['slip'];
        $out = $s['staff_no'].'|'.number_format((float) $s['gross'], 2, '.', '').'|'.number_format((float) $s['net_pay'], 2, '.', '').'|'.number_format((float) $s['paye'], 2, '.', '');
        foreach ($r['lines'] as $l) { $out .= '|'.$l['element_code'].'='.number_format((float) $l['amount'], 2, '.', ''); }
        return $out;
    }

    protected static function emptyTotals()
    {
        return array('headcount' => 0, 'gross' => 0.0, 'taxable' => 0.0, 'paye' => 0.0, 'pension_ee' => 0.0, 'pension_er' => 0.0, 'nhf' => 0.0,
            'nsitf' => 0.0, 'itf' => 0.0, 'other_ded' => 0.0, 'loan' => 0.0, 'arrears' => 0.0, 'net' => 0.0, 'employer_cost' => 0.0);
    }

    protected static function addTotals(array $t, array $s)
    {
        $t['headcount']++;
        $t['gross'] = round($t['gross'] + (float) $s['gross'], 2);
        $t['taxable'] = round($t['taxable'] + (float) $s['taxable_gross'], 2);
        $t['paye'] = round($t['paye'] + (float) $s['paye'], 2);
        $t['pension_ee'] = round($t['pension_ee'] + (float) $s['pension_ee'], 2);
        $t['pension_er'] = round($t['pension_er'] + (float) $s['pension_er'], 2);
        $t['nhf'] = round($t['nhf'] + (float) $s['nhf'], 2);
        $t['nsitf'] = round($t['nsitf'] + (float) $s['nsitf_er'], 2);
        $t['itf'] = round($t['itf'] + (float) $s['itf_er'], 2);
        $t['loan'] = round($t['loan'] + (float) $s['loan_recovered'] + (float) $s['arrears_recovered'], 2);
        $t['arrears'] = round($t['arrears'] + (float) $s['arrears_added'], 2);
        $t['net'] = round($t['net'] + (float) $s['net_pay'], 2);
        $t['employer_cost'] = round($t['employer_cost'] + (float) $s['employer_cost'], 2);
        $t['other_ded'] = round((float) $t['gross'] - $t['paye'] - $t['pension_ee'] - $t['nhf'] - $t['loan'] - $t['net'], 2);
        return $t;
    }

    /** Approved service-charge amounts for the period, by employee. */
    protected static function troncFor(array $run)
    {
        $out = array();
        foreach (Db::getInstance()->executeS('SELECT l.id_pulse_pr_employee, ROUND(SUM(l.amount),2) amount FROM `'._DB_PREFIX_.'pulse_pr_tronc_line` l INNER JOIN `'._DB_PREFIX_.'pulse_pr_tronc_pool` p ON p.id_pulse_pr_tronc_pool=l.id_pulse_pr_tronc_pool WHERE p.period="'.pSQL($run['period']).'" AND p.status IN ("approved","paid") AND (l.paid_in_run IS NULL OR l.paid_in_run='.(int) $run['id_pulse_pr_run'].') GROUP BY l.id_pulse_pr_employee') as $r) {
            $out[(int) $r['id_pulse_pr_employee']] = (float) $r['amount'];
        }
        return $out;
    }

    /**
     * Approve. This is the gate: no bank file, no payslip email and no GL posting happens before it, and
     * the figures cannot move afterwards without an audited reopen.
     */
    public static function approve($id)
    {
        $run = self::get($id);
        if (!$run) { throw new PrestaShopException('Unknown run'); }
        if ($run['status'] !== 'calculated') { throw new PrestaShopException('Run '.$run['run_no'].' is '.$run['status'].' — only a calculated run can be approved'); }
        if ($run['errors']) { throw new PrestaShopException('Run '.$run['run_no'].' has unresolved calculation errors — fix them and recalculate before approving'); }
        if (!(int) $run['headcount']) { throw new PrestaShopException('Run '.$run['run_no'].' has no payslips'); }
        $check = self::verify($id);
        if (!$check['ok']) { throw new PrestaShopException('Run '.$run['run_no'].' does not reconcile: '.implode('; ', $check['problems'])); }
        Db::getInstance()->update('pulse_pr_run', array('status' => 'approved', 'approved_by' => PulsePrService::emp(), 'date_approved' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_run='.(int) $id);
        self::markTroncPaid($id, $run);
        self::raiseRemittances($run);
        PulsePrService::log($id, 'run_approve', 'run', array('run_no' => $run['run_no'], 'net' => $run['total_net'], 'hash' => $run['result_hash']), $id);
        PulseCoreService::event('actionPulsePayrollApproved', array('id_run' => $id, 'period' => $run['period'], 'net' => (float) $run['total_net'], 'headcount' => (int) $run['headcount']));
        return true;
    }

    /**
     * Re-check a stored run against its own payslips. Called before approval and available on demand,
     * because a run that has been sitting for a week deserves to be proved again before money moves.
     */
    public static function verify($id)
    {
        $run = self::get($id);
        $problems = array();
        if (!$run) { return array('ok' => false, 'problems' => array('Unknown run')); }
        $sum = Db::getInstance()->getRow('SELECT COUNT(*) n, ROUND(SUM(gross),2) gross, ROUND(SUM(total_deductions),2) ded, ROUND(SUM(net_pay),2) net, ROUND(SUM(employer_cost),2) cost FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $id);
        if ((int) $sum['n'] !== (int) $run['headcount']) { $problems[] = 'the run says '.(int) $run['headcount'].' payslips but '.(int) $sum['n'].' are stored'; }
        if (abs((float) $sum['gross'] - (float) $run['total_gross']) > 0.009) { $problems[] = 'the payslips total '.number_format((float) $sum['gross'], 2).' gross against the run header '.number_format((float) $run['total_gross'], 2); }
        if (abs((float) $sum['net'] - (float) $run['total_net']) > 0.009) { $problems[] = 'the payslips total '.number_format((float) $sum['net'], 2).' net against the run header '.number_format((float) $run['total_net'], 2); }
        if (abs(((float) $sum['gross'] - (float) $sum['ded']) - (float) $sum['net']) > 0.009) { $problems[] = 'gross less deductions does not equal net across the run'; }
        $bad = Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $id.' AND net_pay<0');
        if ((int) $bad) { $problems[] = (int) $bad.' payslips have negative net pay'; }
        $lineMismatch = Db::getInstance()->getValue('SELECT COUNT(*) FROM (SELECT p.id_pulse_pr_payslip, p.gross, ROUND(COALESCE(SUM(IF(l.type="earning",l.amount,0)),0),2) e FROM `'._DB_PREFIX_.'pulse_pr_payslip` p LEFT JOIN `'._DB_PREFIX_.'pulse_pr_payslip_line` l ON l.id_pulse_pr_payslip=p.id_pulse_pr_payslip WHERE p.id_pulse_pr_run='.(int) $id.' GROUP BY p.id_pulse_pr_payslip HAVING ABS(p.gross-e)>0.004) x');
        if ((int) $lineMismatch) { $problems[] = (int) $lineMismatch.' payslips whose earning lines do not sum to the printed gross'; }
        $noBank = Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $id.' AND pay_method="bank" AND net_pay>0 AND (COALESCE(account_no,"")="" OR COALESCE(bank_code,"")="")');
        if ((int) $noBank) { $problems[] = (int) $noBank.' staff are paid by bank but have no account number or bank code — the payment file would be short'; }
        return array('ok' => !$problems, 'problems' => $problems, 'totals' => $sum);
    }

    /** Reopen an approved run. Refused once money has moved or the GL has it. */
    public static function reopen($id, $reason)
    {
        $run = self::get($id);
        if (!$run) { throw new PrestaShopException('Unknown run'); }
        if (in_array($run['status'], array('paid', 'posted'))) { throw new PrestaShopException('Run '.$run['run_no'].' is '.$run['status'].' — it can no longer be reopened. Correct it with a supplementary run.'); }
        if ($run['status'] !== 'approved') { throw new PrestaShopException('Only an approved run can be reopened'); }
        if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_bank_file` WHERE id_pulse_pr_run='.(int) $id.' AND status<>"void"')) { throw new PrestaShopException('A bank payment file has already been generated from run '.$run['run_no'].'. Void it first.'); }
        if (trim((string) $reason) === '') { throw new PrestaShopException('A reopen needs a reason — it goes on the audit trail'); }
        Db::getInstance()->update('pulse_pr_run', array('status' => 'calculated', 'approved_by' => null, 'date_approved' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_run='.(int) $id, 0, true);
        PulsePrService::log($id, 'run_reopen', 'run', array('run_no' => $run['run_no'], 'reason' => $reason), $id);
        return true;
    }

    /** Mark paid once the bank has been instructed. */
    public static function markPaid($id, $note = '')
    {
        $run = self::get($id);
        if (!$run) { throw new PrestaShopException('Unknown run'); }
        if ($run['status'] !== 'approved') { throw new PrestaShopException('Run '.$run['run_no'].' is '.$run['status'].' — approve it first'); }
        Db::getInstance()->update('pulse_pr_run', array('status' => 'paid', 'paid_by' => PulsePrService::emp(), 'date_paid' => date('Y-m-d H:i:s'), 'note' => pSQL(Tools::substr($run['note'].' '.$note, 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_run='.(int) $id);
        PulsePrService::log($id, 'run_paid', 'run', array('run_no' => $run['run_no'], 'note' => $note), $id);
        return true;
    }

    public static function cancel($id, $reason)
    {
        $run = self::get($id);
        if (!$run) { throw new PrestaShopException('Unknown run'); }
        if (in_array($run['status'], array('paid', 'posted'))) { throw new PrestaShopException('Run '.$run['run_no'].' is '.$run['status'].' and cannot be cancelled'); }
        PulsePrLoan::unwindRun($id);
        // hand any service-charge pool this run had claimed back, or nobody could ever pay it
        $pools = array();
        foreach (Db::getInstance()->executeS('SELECT DISTINCT id_pulse_pr_tronc_pool FROM `'._DB_PREFIX_.'pulse_pr_tronc_line` WHERE paid_in_run='.(int) $id) as $p) { $pools[] = (int) $p['id_pulse_pr_tronc_pool']; }
        Db::getInstance()->update('pulse_pr_tronc_line', array('paid_in_run' => null), 'paid_in_run='.(int) $id, 0, true);
        foreach ($pools as $ip) {
            if ((int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_tronc_line` WHERE id_pulse_pr_tronc_pool='.$ip.' AND paid_in_run IS NOT NULL')) { continue; }
            Db::getInstance()->update('pulse_pr_tronc_pool', array('status' => 'approved', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_tronc_pool='.$ip.' AND status="paid"');
        }
        Db::getInstance()->update('pulse_pr_run', array('status' => 'cancelled', 'note' => pSQL(Tools::substr('Cancelled: '.$reason, 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_run='.(int) $id);
        PulsePrService::log($id, 'run_cancel', 'run', array('reason' => $reason), $id);
        return true;
    }

    /* ---------------- GL posting ---------------- */

    /**
     * Post an approved run to the general ledger through Pulse Accounts. The aggregation matches what
     * PulseAccPosting::payroll() expects: departmental payroll cost on the debit side (gross plus the
     * employer's own contributions), the statutory liabilities and net pay on the credit side.
     *
     * The run is proved to balance here first — sum(departments) must equal the deductions plus net —
     * because a journal that will not balance should never reach the ledger at all. Idempotent: the
     * source_ref carries the run number, so a second post returns the journal that already exists and a
     * supplementary run in the same period gets its own journal rather than colliding with the regular one.
     */
    public static function post($id)
    {
        $run = self::get($id);
        if (!$run) { throw new PrestaShopException('Unknown run'); }
        if (!in_array($run['status'], array('approved', 'paid'))) { throw new PrestaShopException('Run '.$run['run_no'].' is '.$run['status'].' — only an approved or paid run posts to the ledger'); }
        if ((int) $run['id_acc_journal']) { return (int) $run['id_acc_journal']; }
        if (!PulsePrService::acc()) { PulsePrService::log($id, 'run_post_skipped', 'run', 'Pulse Accounts is not installed — the run is complete but nothing was posted to the GL', $id); return null; }

        $agg = self::glAggregation($id);
        $lhs = 0; foreach ($agg['departments'] as $v) { $lhs = round($lhs + $v, 2); }
        $rhs = round(array_sum($agg['deductions']) + $agg['net'], 2);
        if (abs($lhs - $rhs) > 0.009) { throw new PrestaShopException('Run '.$run['run_no'].' will not balance in the ledger: departmental cost '.number_format($lhs, 2).' against deductions and net pay '.number_format($rhs, 2).'. Nothing has been posted.'); }

        $ref = $run['run_type'] === 'regular' ? $run['period'] : $run['period'].'/'.$run['run_no'];
        $idJournal = PulseAccPosting::payroll($ref, $agg['departments'], $agg['deductions'], $run['pay_date']);
        self::postLoanReclass($run, $agg);
        Db::getInstance()->update('pulse_pr_run', array('status' => 'posted', 'id_acc_journal' => (int) $idJournal, 'date_posted' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_run='.(int) $id);
        PulsePrService::log($id, 'run_post', 'run', array('run_no' => $run['run_no'], 'journal' => $idJournal, 'debit' => $lhs), $id);
        PulseCoreService::event('actionPulsePayrollPosted', array('id_run' => $id, 'period' => $run['period'], 'id_journal' => $idJournal, 'amount' => $lhs));
        return (int) $idJournal;
    }

    /**
     * PulseAccPosting::payroll() has a fixed account map and lands every non-statutory deduction in
     * 2130 Accrued expenses. A loan recovery is not an accrued expense — it reduces the staff loan
     * receivable — so a small second journal moves it. Idempotent on its own source_ref, and skipped
     * silently when the property's chart of accounts does not carry a staff-loan account.
     */
    protected static function postLoanReclass(array $run, array $agg)
    {
        $amount = round((float) $agg['totals']['loan'] + (float) $agg['totals']['arrears'], 2);
        if ($amount < 0.005 || !class_exists('PulseAccService') || !class_exists('PulseAccJournal')) { return null; }
        if (!PulseAccService::account('1240') || !PulseAccService::account('2130')) { return null; }
        try {
            return PulseAccJournal::post(array(
                'type' => 'general', 'source' => 'payroll', 'source_ref' => 'loanrecovery:'.$run['run_no'], 'business_date' => $run['pay_date'],
                'reference' => $run['run_no'], 'memo' => 'Staff loan recoveries '.$run['period'],
                'lines' => array(
                    array('account' => '2130', 'debit' => $amount, 'memo' => 'Loan recoveries withheld from '.$run['period'].' payroll'),
                    array('account' => '1240', 'credit' => $amount, 'memo' => 'Staff advances and loans reduced by the '.$run['period'].' recoveries'),
                ),
            ));
        } catch (Exception $e) {
            PulsePrService::log((int) $run['id_pulse_pr_run'], 'loan_reclass_failed', 'run', $e->getMessage(), (int) $run['id_pulse_pr_run']);
            return null;
        }
    }

    /**
     * The numbers the GL journal is built from.
     * Departmental cost = gross pay + every employer-borne contribution for that department.
     * Deduction buckets map to the accounts PulseAccPosting::payroll already knows: PAYE 2155,
     * pension 2150 (both sides), NSITF/ITF/NHF 2160, everything else 2130. Net pay lands on 2140.
     */
    public static function glAggregation($id)
    {
        $rows = Db::getInstance()->executeS('SELECT COALESCE(NULLIF(cost_centre,""),department) dept, ROUND(SUM(gross),2) gross, ROUND(SUM(employer_cost),2) employer_cost FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $id.' GROUP BY dept');
        $departments = array();
        foreach ($rows as $r) { $departments[$r['dept']] = round((float) $r['gross'] + (float) $r['employer_cost'], 2); }
        $t = Db::getInstance()->getRow('SELECT ROUND(SUM(paye),2) paye, ROUND(SUM(pension_ee),2) pension_ee, ROUND(SUM(pension_er),2) pension_er, ROUND(SUM(nhf),2) nhf, ROUND(SUM(nhis),2) nhis, ROUND(SUM(nsitf_er),2) nsitf, ROUND(SUM(itf_er),2) itf, ROUND(SUM(employer_cost),2) employer_cost, ROUND(SUM(loan_recovered),2) loan, ROUND(SUM(arrears_recovered),2) arrears, ROUND(SUM(net_pay),2) net, ROUND(SUM(gross),2) gross, ROUND(SUM(total_deductions),2) ded FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $id);
        $other = round((float) $t['ded'] - (float) $t['paye'] - (float) $t['pension_ee'] - (float) $t['nhf'] - (float) $t['nhis'], 2);
        // the debit side carries the whole employer cost, so every employer-borne scheme has to reach a
        // credit bucket. Pension has its own; NSITF and ITF are named; anything else the country pack adds
        // (NHIA employer, a future scheme) is the residual and lands with them on 2160 — otherwise the
        // journal is short by exactly that amount and post() refuses the run for ever.
        $employerOther = round((float) $t['employer_cost'] - (float) $t['pension_er'] - (float) $t['nsitf'] - (float) $t['itf'], 2);
        $deductions = array(
            'paye' => round((float) $t['paye'], 2),
            'pension' => round((float) $t['pension_ee'] + (float) $t['pension_er'], 2),
            'nsitf' => round((float) $t['nsitf'] + (float) $t['itf'] + (float) $t['nhf'] + (float) $t['nhis'] + max(0, $employerOther), 2),
            'other' => max(0, $other),
        );
        return array('departments' => $departments, 'deductions' => $deductions, 'net' => round((float) $t['net'], 2), 'totals' => $t);
    }

    /**
     * Stamp the service-charge pools this run actually paid, the moment it is approved. Without it every
     * later run in the same period picks the same shares up again and pays the pool twice — troncFor()
     * already allows the stamped run itself through, so a reopen and recalculate is unaffected.
     */
    protected static function markTroncPaid($id, array $run)
    {
        if (!(int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_payslip_line` WHERE id_pulse_pr_run='.(int) $id.' AND element_code="TRONC"')) { return false; }
        foreach (Db::getInstance()->executeS('SELECT id_pulse_pr_tronc_pool FROM `'._DB_PREFIX_.'pulse_pr_tronc_pool` WHERE period="'.pSQL($run['period']).'" AND status="approved"') as $p) {
            PulsePrTronc::markPaidInRun((int) $p['id_pulse_pr_tronc_pool'], (int) $id);
        }
        return true;
    }

    /** Statutory remittance rows with their due dates, raised the moment a run is approved. */
    protected static function raiseRemittances(array $run)
    {
        $t = Db::getInstance()->getRow('SELECT ROUND(SUM(paye),2) paye, ROUND(SUM(pension_ee),2) pension_ee, ROUND(SUM(pension_er),2) pension_er, ROUND(SUM(nhf),2) nhf, ROUND(SUM(nsitf_er),2) nsitf, ROUND(SUM(itf_er),2) itf FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run WHERE p.period="'.pSQL($run['period']).'" AND r.status IN ("approved","paid","posted")');
        $pay = $run['pay_date'];
        $monthEnd = date('Y-m-t', strtotime($run['period'].'-01'));
        if ((float) $t['paye'] > 0) { PulsePrService::upsertRemittance('paye', $run['period'], PulsePrService::cfg('TAX_STATE', 'State Internal Revenue Service'), (float) $t['paye'], date('Y-m-10', strtotime('+1 month', strtotime($monthEnd)))); }
        $pension = round((float) $t['pension_ee'] + (float) $t['pension_er'], 2);
        if ($pension > 0) { PulsePrService::upsertRemittance('pension', $run['period'], 'PenCom / PFA (RSA)', $pension, date('Y-m-d', strtotime($pay.' +9 day'))); }
        if ((float) $t['nsitf'] > 0) { PulsePrService::upsertRemittance('nsitf', $run['period'], 'Nigeria Social Insurance Trust Fund', (float) $t['nsitf'], date('Y-m-10', strtotime('+1 month', strtotime($monthEnd)))); }
        if ((float) $t['nhf'] > 0) { PulsePrService::upsertRemittance('nhf', $run['period'], 'Federal Mortgage Bank of Nigeria (NHF)', (float) $t['nhf'], date('Y-m-t', strtotime('+1 month', strtotime($monthEnd)))); }
        $year = (int) Tools::substr($run['period'], 0, 4);
        $itfYear = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(p.itf_er),0) FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run WHERE p.period LIKE "'.pSQL((string) $year).'-%" AND r.status IN ("approved","paid","posted")'), 2);
        if ($itfYear > 0) { PulsePrService::upsertRemittance('itf', $year.'-12', 'Industrial Training Fund', $itfYear, ($year + 1).'-04-01'); }
        return true;
    }

    /* ---------------- results ---------------- */

    public static function payslips($idRun, $department = null)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $idRun.($department ? ' AND department="'.pSQL($department).'"' : '').' ORDER BY department, employee_name');
    }

    public static function byDepartment($idRun)
    {
        return Db::getInstance()->executeS('SELECT department, COUNT(*) headcount, ROUND(SUM(gross),2) gross, ROUND(SUM(paye),2) paye, ROUND(SUM(pension_ee),2) pension_ee, ROUND(SUM(pension_er),2) pension_er, ROUND(SUM(nsitf_er),2) nsitf, ROUND(SUM(itf_er),2) itf, ROUND(SUM(total_deductions),2) deductions, ROUND(SUM(net_pay),2) net, ROUND(SUM(gross+employer_cost),2) total_cost FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $idRun.' GROUP BY department ORDER BY department');
    }

    /**
     * The single most useful control in payroll: what moved against last period, and by how much.
     * Anything over the configured threshold is flagged, and a starter or a leaver is labelled as such
     * so the payroll officer is not chasing a variance that is simply a new hire.
     */
    public static function variance($idRun, $threshold = null)
    {
        $run = self::get($idRun);
        if (!$run) { return array(); }
        $threshold = $threshold === null ? (float) PulsePrService::cfg('VARIANCE_PCT', 15) : (float) $threshold;
        $prev = date('Y-m', strtotime($run['period'].'-01 -1 month'));
        $rows = Db::getInstance()->executeS('SELECT c.id_pulse_pr_employee, c.staff_no, c.employee_name, c.department, c.gross, c.net_pay, c.paye,
                COALESCE(p.gross,0) prev_gross, COALESCE(p.net_pay,0) prev_net, COALESCE(p.paye,0) prev_paye, (p.id_pulse_pr_payslip IS NULL) is_new
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` c
            LEFT JOIN (SELECT x.* FROM `'._DB_PREFIX_.'pulse_pr_payslip` x INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=x.id_pulse_pr_run WHERE x.period="'.pSQL($prev).'" AND r.status<>"cancelled") p ON p.id_pulse_pr_employee=c.id_pulse_pr_employee
            WHERE c.id_pulse_pr_run='.(int) $idRun.' ORDER BY c.department, c.employee_name');
        $out = array();
        foreach ($rows as $r) {
            $delta = round((float) $r['gross'] - (float) $r['prev_gross'], 2);
            $pct = (float) $r['prev_gross'] > 0 ? round($delta / (float) $r['prev_gross'] * 100, 2) : ((float) $r['gross'] > 0 ? 100.0 : 0.0);
            $r['delta'] = $delta; $r['pct'] = $pct;
            $r['flag'] = (int) $r['is_new'] ? 'new' : (abs($pct) >= $threshold ? 'variance' : '');
            if ((float) $r['gross'] <= 0 && (float) $r['prev_gross'] > 0) { $r['flag'] = 'stopped'; }
            $out[] = $r;
        }
        // anyone paid last period who is not in this one at all
        foreach (Db::getInstance()->executeS('SELECT p.id_pulse_pr_employee, p.staff_no, p.employee_name, p.department, p.gross prev_gross, p.net_pay prev_net, p.paye prev_paye FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run WHERE p.period="'.pSQL($prev).'" AND r.status<>"cancelled" AND p.id_pulse_pr_employee NOT IN (SELECT id_pulse_pr_employee FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $idRun.')') as $r) {
            $out[] = array_merge($r, array('gross' => 0, 'net_pay' => 0, 'paye' => 0, 'is_new' => 0, 'delta' => -round((float) $r['prev_gross'], 2), 'pct' => -100.0, 'flag' => 'dropped'));
        }
        return $out;
    }

    /** Dashboard roll-up for the payroll landing screen. */
    public static function dashboard()
    {
        $period = date('Y-m');
        $last = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_run` WHERE status IN ("approved","paid","posted") ORDER BY period DESC, id_pulse_pr_run DESC');
        $open = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_run` WHERE status IN ("draft","calculated","approved") ORDER BY period DESC');
        $heads = Db::getInstance()->getRow('SELECT COUNT(*) total, SUM(status="active") active, SUM(status="probation") probation, SUM(employment_type="casual") casuals, SUM(on_hold=1) on_hold FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE status<>"exited"');
        $pack = PulsePrStatutory::pack(PulsePrService::country());
        return array(
            'period' => $period, 'business_date' => PulsePrService::bd(), 'last_run' => $last, 'open_runs' => $open,
            'headcount' => $heads, 'country' => PulsePrService::country(), 'pack' => $pack->label(), 'pack_verified' => $pack->verified(),
            'warnings' => $pack->warnings(),
            'loans_out' => round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(balance),0) FROM `'._DB_PREFIX_.'pulse_pr_loan` WHERE status IN ("disbursed","repaying")'), 2),
            'arrears_out' => round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(balance),0) FROM `'._DB_PREFIX_.'pulse_pr_arrears` WHERE status IN ("open","part")'), 2),
            'remittances_due' => PulsePrService::remittances('due', 12),
            'remittances_overdue' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_remittance` WHERE status IN ("due","part") AND due_date<"'.pSQL(date('Y-m-d')).'" ORDER BY due_date'),
            'acc' => PulsePrService::acc(), 'hr' => PulsePrService::hr(), 'ta' => PulsePrService::ta(), 'fd' => PulsePrService::fd(),
            'no_structure' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_employee` e WHERE e.status IN ("active","probation") AND e.pay_rate<=0'),
            'tronc_draft' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_tronc_pool` WHERE status="draft"'),
            'casual_draft' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_casual_batch` WHERE status="draft"'),
        );
    }
}
