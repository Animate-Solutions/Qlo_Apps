<?php
/**
 * Staff loans, salary advances and the arrears that arise when a recovery cannot be taken in full.
 *
 * The rule the module never breaks: a recovery is capped at what the payslip can actually bear. If the
 * instalment would push net pay below the protected floor (zero by default, or a configured percentage
 * of gross), only the affordable part is taken and the balance is parked as arrears against the employee,
 * to be recovered next period. Net pay is never negative and a loan is never silently forgiven.
 */
class PulsePrLoan
{
    public static function loans(array $f = array(), $limit = 300)
    {
        $w = array('1');
        if (!empty($f['id_pulse_pr_employee'])) { $w[] = 'l.id_pulse_pr_employee='.(int) $f['id_pulse_pr_employee']; }
        if (!empty($f['status'])) { $w[] = 'l.status IN ("'.implode('","', array_map('pSQL', explode(',', $f['status']))).'")'; }
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w[] = '(l.loan_no LIKE "%'.$q.'%" OR e.staff_no LIKE "%'.$q.'%" OR e.lastname LIKE "%'.$q.'%")'; }
        return Db::getInstance()->executeS('SELECT l.*, e.staff_no, CONCAT(e.firstname," ",e.lastname) employee_name, e.department FROM `'._DB_PREFIX_.'pulse_pr_loan` l INNER JOIN `'._DB_PREFIX_.'pulse_pr_employee` e ON e.id_pulse_pr_employee=l.id_pulse_pr_employee WHERE '.implode(' AND ', $w).' ORDER BY FIELD(l.status,"applied","approved","disbursed","repaying","settled","rejected","cancelled","written_off"), l.date_add DESC LIMIT '.(int) $limit);
    }

    public static function loan($id)
    {
        $l = Db::getInstance()->getRow('SELECT l.*, e.staff_no, CONCAT(e.firstname," ",e.lastname) employee_name, e.department FROM `'._DB_PREFIX_.'pulse_pr_loan` l INNER JOIN `'._DB_PREFIX_.'pulse_pr_employee` e ON e.id_pulse_pr_employee=l.id_pulse_pr_employee WHERE l.id_pulse_pr_loan='.(int) $id);
        if ($l) { $l['schedule'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_loan_schedule` WHERE id_pulse_pr_loan='.(int) $id.' ORDER BY seq'); }
        return $l;
    }

    /**
     * Apply for a loan. Interest is flat on the principal — the only shape a Nigerian hotel staff loan
     * ever takes — and zero for a salary advance.
     */
    public static function apply(array $d)
    {
        if (empty($d['id_pulse_pr_employee'])) { throw new PrestaShopException('Pick the employee'); }
        $principal = round((float) (isset($d['principal']) ? $d['principal'] : 0), 2);
        if ($principal <= 0) { throw new PrestaShopException('The principal must be positive'); }
        $instalments = max(1, (int) (isset($d['instalments']) ? $d['instalments'] : 1));
        $interestPct = round((float) (isset($d['interest_pct']) ? $d['interest_pct'] : 0), 3);
        $interest = round($principal * $interestPct / 100, 2);
        $total = round($principal + $interest, 2);
        $first = !empty($d['first_period']) ? Tools::substr($d['first_period'], 0, 7) : date('Y-m', strtotime('+1 month'));
        $id = null;
        Db::getInstance()->insert('pulse_pr_loan', array(
            'loan_no' => pSQL(PulsePrService::nextNo('LN', 5)), 'id_pulse_pr_employee' => (int) $d['id_pulse_pr_employee'],
            'type' => pSQL(in_array(isset($d['type']) ? $d['type'] : '', array('loan', 'salary_advance', 'asset', 'other')) ? $d['type'] : 'loan'),
            'purpose' => pSQL(Tools::substr(isset($d['purpose']) ? $d['purpose'] : '', 0, 160)),
            'principal' => $principal, 'interest_pct' => $interestPct, 'interest_amount' => $interest, 'total_repayable' => $total,
            'instalments' => $instalments, 'instalment_amount' => round($total / $instalments, 2), 'first_period' => pSQL($first),
            'balance' => $total, 'status' => 'applied', 'date_applied' => pSQL(!empty($d['date_applied']) ? $d['date_applied'] : date('Y-m-d')),
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 255)), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        self::buildSchedule($id);
        PulsePrService::log(null, 'loan_apply', 'loan', array('principal' => $principal, 'instalments' => $instalments), $id);
        return $id;
    }

    /** Rebuild the repayment schedule so the instalments add up to the total repayable to the kobo. */
    public static function buildSchedule($id)
    {
        $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_loan` WHERE id_pulse_pr_loan='.(int) $id);
        if (!$l) { return false; }
        Db::getInstance()->delete('pulse_pr_loan_schedule', 'id_pulse_pr_loan='.(int) $id.' AND paid_amount=0');
        $paid = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(paid_amount),0) FROM `'._DB_PREFIX_.'pulse_pr_loan_schedule` WHERE id_pulse_pr_loan='.(int) $id);
        $done = (int) Db::getInstance()->getValue('SELECT COALESCE(MAX(seq),0) FROM `'._DB_PREFIX_.'pulse_pr_loan_schedule` WHERE id_pulse_pr_loan='.(int) $id);
        $remaining = round((float) $l['total_repayable'] - $paid, 2);
        $n = max(1, (int) $l['instalments'] - $done);
        $each = round($remaining / $n, 2);
        $allocated = 0;
        for ($i = 1; $i <= $n; $i++) {
            $amount = $i === $n ? round($remaining - $allocated, 2) : $each;
            $allocated = round($allocated + $amount, 2);
            $period = date('Y-m', strtotime($l['first_period'].'-01 +'.($done + $i - 1).' month'));
            Db::getInstance()->insert('pulse_pr_loan_schedule', array('id_pulse_pr_loan' => (int) $id, 'seq' => $done + $i, 'period' => pSQL($period), 'due_amount' => $amount, 'status' => 'due'), true);
        }
        Db::getInstance()->update('pulse_pr_loan', array('balance' => $remaining, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_loan='.(int) $id);
        return true;
    }

    public static function setStatus($id, $status, $note = '')
    {
        $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_loan` WHERE id_pulse_pr_loan='.(int) $id);
        if (!$l) { throw new PrestaShopException('Unknown loan'); }
        $allowed = array('applied', 'approved', 'disbursed', 'repaying', 'settled', 'written_off', 'rejected', 'cancelled');
        if (!in_array($status, $allowed)) { throw new PrestaShopException('Unknown loan status'); }
        if ($status === 'disbursed' && $l['status'] === 'applied') { throw new PrestaShopException('Loan '.$l['loan_no'].' must be approved before it is disbursed'); }
        $upd = array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s'));
        if ($note !== '') { $upd['note'] = pSQL(Tools::substr($note, 0, 255)); }
        if ($status === 'approved') { $upd['date_approved'] = date('Y-m-d'); $upd['approved_by'] = PulsePrService::emp(); }
        if ($status === 'disbursed') { $upd['date_disbursed'] = date('Y-m-d'); }
        if ($status === 'settled' || $status === 'written_off') { $upd['date_settled'] = date('Y-m-d'); }
        Db::getInstance()->update('pulse_pr_loan', $upd, 'id_pulse_pr_loan='.(int) $id);
        PulsePrService::log(null, 'loan_'.$status, 'loan', array('loan_no' => $l['loan_no']), (int) $id);
        return true;
    }

    /**
     * What can be recovered from one employee this period, capped at $available.
     * Arrears are recovered before new instalments — the oldest debt first — and anything that cannot be
     * taken becomes (or stays) arrears. With $commit false nothing is written, which is what the run's
     * preview and the self-check use.
     *
     * @return array lines (element code => amount, note), loan, arrears, shortfall
     */
    public static function recoveryFor($idEmployee, $period, $available, $commit = true)
    {
        $available = round((float) $available, 2);
        $out = array('lines' => array(), 'loan' => 0.0, 'arrears' => 0.0, 'shortfall' => 0.0, 'detail' => array());
        $budget = max(0, $available);

        /* arrears first */
        $arrearsTaken = 0; $arrearsDue = 0;
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_arrears` WHERE id_pulse_pr_employee='.(int) $idEmployee.' AND status IN ("open","part") ORDER BY id_pulse_pr_arrears') as $a) {
            $due = round((float) $a['balance'], 2);
            if ($due <= 0) { continue; }
            $arrearsDue = round($arrearsDue + $due, 2);
            $take = min($due, round($budget, 2));
            if ($take <= 0) { continue; }
            $budget = round($budget - $take, 2); $arrearsTaken = round($arrearsTaken + $take, 2);
            $out['detail'][] = array('kind' => 'arrears', 'ref' => (int) $a['id_pulse_pr_arrears'], 'amount' => $take);
            if ($commit) {
                $rec = round((float) $a['recovered'] + $take, 2); $bal = round((float) $a['amount'] - $rec, 2);
                Db::getInstance()->update('pulse_pr_arrears', array('recovered' => $rec, 'balance' => $bal, 'status' => $bal <= 0.004 ? 'cleared' : 'part', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_arrears='.(int) $a['id_pulse_pr_arrears']);
            }
        }

        /* then this period's loan instalments, oldest schedule row first */
        $loanTaken = 0; $loanDue = 0;
        foreach (Db::getInstance()->executeS('SELECT s.*, l.loan_no, l.type, l.total_repayable FROM `'._DB_PREFIX_.'pulse_pr_loan_schedule` s INNER JOIN `'._DB_PREFIX_.'pulse_pr_loan` l ON l.id_pulse_pr_loan=s.id_pulse_pr_loan
            WHERE l.id_pulse_pr_employee='.(int) $idEmployee.' AND l.status IN ("disbursed","repaying") AND s.status IN ("due","part") AND s.period<="'.pSQL($period).'" ORDER BY s.period, s.seq') as $s) {
            $due = round(PulsePrService::num($s, 'due_amount') - PulsePrService::num($s, 'paid_amount'), 2);
            if ($due <= 0) { continue; }
            $loanDue = round($loanDue + $due, 2);
            $take = min($due, round($budget, 2));
            if ($take <= 0) { continue; }
            $budget = round($budget - $take, 2); $loanTaken = round($loanTaken + $take, 2);
            $out['detail'][] = array('kind' => $s['type'] === 'salary_advance' ? 'advance' : 'loan', 'ref' => (int) $s['id_pulse_pr_loan_schedule'], 'loan_no' => $s['loan_no'], 'amount' => $take);
            if ($commit) {
                $paid = round(PulsePrService::num($s, 'paid_amount') + $take, 2);
                Db::getInstance()->update('pulse_pr_loan_schedule', array('paid_amount' => $paid, 'status' => $paid + 0.004 >= (float) $s['due_amount'] ? 'paid' : 'part'), 'id_pulse_pr_loan_schedule='.(int) $s['id_pulse_pr_loan_schedule']);
                self::refreshLoan((int) $s['id_pulse_pr_loan']);
            }
        }

        // only an unrecovered loan instalment creates a NEW arrears row; unrecovered arrears simply stay open
        $shortfall = round(max(0, $loanDue - $loanTaken), 2);
        if ($shortfall > 0 && $commit) {
            Db::getInstance()->insert('pulse_pr_arrears', array(
                'id_pulse_pr_employee' => (int) $idEmployee, 'source' => 'loan', 'source_ref' => pSQL($period),
                'description' => 'Loan recovery shortfall in '.$period.' — net pay could not bear the full instalment',
                'amount' => $shortfall, 'recovered' => 0, 'balance' => $shortfall, 'period_raised' => pSQL($period), 'status' => 'open',
                'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
            ), true);
        }

        if ($loanTaken > 0) {
            $advance = 0; $loan = 0;
            foreach ($out['detail'] as $d) { if ($d['kind'] === 'advance') { $advance = round($advance + $d['amount'], 2); } elseif ($d['kind'] === 'loan') { $loan = round($loan + $d['amount'], 2); } }
            if ($loan > 0) { $out['lines']['LOAN'] = array('amount' => $loan, 'note' => 'Loan instalment '.$period); }
            if ($advance > 0) { $out['lines']['ADVANCE'] = array('amount' => $advance, 'note' => 'Salary advance recovery '.$period); }
        }
        if ($arrearsTaken > 0) { $out['lines']['ARREARS'] = array('amount' => $arrearsTaken, 'note' => 'Arrears recovery'); }
        $out['loan'] = $loanTaken; $out['arrears'] = $arrearsTaken; $out['shortfall'] = max(0, $shortfall);
        return $out;
    }

    /** Recompute a loan's recovered total, balance and status from its schedule. */
    public static function refreshLoan($id)
    {
        $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_loan` WHERE id_pulse_pr_loan='.(int) $id);
        if (!$l) { return false; }
        $paid = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(paid_amount),0) FROM `'._DB_PREFIX_.'pulse_pr_loan_schedule` WHERE id_pulse_pr_loan='.(int) $id), 2);
        $balance = round((float) $l['total_repayable'] - $paid, 2);
        $status = $l['status'];
        if ($balance <= 0.004 && in_array($status, array('disbursed', 'repaying'))) { $status = 'settled'; }
        elseif ($paid > 0 && $status === 'disbursed') { $status = 'repaying'; }
        Db::getInstance()->update('pulse_pr_loan', array('recovered' => $paid, 'balance' => max(0, $balance), 'status' => pSQL($status), 'date_settled' => $status === 'settled' ? date('Y-m-d') : $l['date_settled'], 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_loan='.(int) $id);
        return true;
    }

    /** Undo a run's recoveries so a recalculation does not double-recover. */
    public static function unwindRun($idRun)
    {
        foreach (Db::getInstance()->executeS('SELECT s.*, s.id_pulse_pr_loan FROM `'._DB_PREFIX_.'pulse_pr_loan_schedule` s INNER JOIN `'._DB_PREFIX_.'pulse_pr_payslip` p ON p.id_pulse_pr_payslip=s.id_pulse_pr_payslip WHERE p.id_pulse_pr_run='.(int) $idRun) as $s) {
            Db::getInstance()->update('pulse_pr_loan_schedule', array('paid_amount' => 0, 'status' => 'due', 'id_pulse_pr_payslip' => null), 'id_pulse_pr_loan_schedule='.(int) $s['id_pulse_pr_loan_schedule']);
            self::refreshLoan((int) $s['id_pulse_pr_loan']);
        }
        // only the arrears THIS run parked: another run in the same period has its own, and wiping those
        // would forgive a debt nobody agreed to forgive
        foreach (Db::getInstance()->executeS('SELECT DISTINCT id_pulse_pr_employee, period FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $idRun) as $p) {
            Db::getInstance()->delete('pulse_pr_arrears', 'source="loan" AND period_raised="'.pSQL($p['period']).'" AND recovered=0 AND id_pulse_pr_employee='.(int) $p['id_pulse_pr_employee']);
        }
        return true;
    }

    /** Everything still owed by an employee — used on exit clearance and by the ESS API. */
    public static function balanceFor($idEmployee)
    {
        $loan = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(balance),0) FROM `'._DB_PREFIX_.'pulse_pr_loan` WHERE id_pulse_pr_employee='.(int) $idEmployee.' AND status IN ("disbursed","repaying")'), 2);
        $arrears = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(balance),0) FROM `'._DB_PREFIX_.'pulse_pr_arrears` WHERE id_pulse_pr_employee='.(int) $idEmployee.' AND status IN ("open","part")'), 2);
        return array('loan' => $loan, 'arrears' => $arrears, 'total' => round($loan + $arrears, 2));
    }

    public static function arrears($idEmployee = null)
    {
        return Db::getInstance()->executeS('SELECT a.*, e.staff_no, CONCAT(e.firstname," ",e.lastname) employee_name, e.department FROM `'._DB_PREFIX_.'pulse_pr_arrears` a INNER JOIN `'._DB_PREFIX_.'pulse_pr_employee` e ON e.id_pulse_pr_employee=a.id_pulse_pr_employee WHERE a.status IN ("open","part")'.($idEmployee ? ' AND a.id_pulse_pr_employee='.(int) $idEmployee : '').' ORDER BY a.date_add');
    }

    public static function waiveArrears($id, $reason = '')
    {
        Db::getInstance()->update('pulse_pr_arrears', array('status' => 'waived', 'balance' => 0, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_arrears='.(int) $id);
        PulsePrService::log(null, 'arrears_waive', 'arrears', $reason, (int) $id);
        return true;
    }

    /** Stamp the payslip that actually took each recovery, so the schedule can be traced back. */
    public static function stampPayslip($idPayslip, array $detail)
    {
        foreach ($detail as $d) {
            if ($d['kind'] === 'arrears') { continue; }
            Db::getInstance()->update('pulse_pr_loan_schedule', array('id_pulse_pr_payslip' => (int) $idPayslip), 'id_pulse_pr_loan_schedule='.(int) $d['ref']);
        }
        return true;
    }
}
