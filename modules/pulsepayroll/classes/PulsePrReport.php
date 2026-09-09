<?php
/**
 * Payroll reporting: the register, the departmental cost summary, the statutory schedules each authority
 * wants in its own shape, year-to-date per employee, headcount reconciliation, the bank payment summary,
 * and the two hospitality numbers a general manager actually asks for — labour cost per occupied room
 * and payroll as a percentage of revenue.
 *
 * Every report returns plain rows so the CSV export and the screen can never drift apart.
 */
class PulsePrReport
{
    /** The register: one row per employee for a run, with every element that carried an amount. */
    public static function register($idRun)
    {
        $slips = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $idRun.' ORDER BY department, employee_name');
        $codes = array();
        foreach (Db::getInstance()->executeS('SELECT DISTINCT l.element_code, l.element_name, l.type, MIN(l.sequence) seq FROM `'._DB_PREFIX_.'pulse_pr_payslip_line` l WHERE l.id_pulse_pr_run='.(int) $idRun.' AND l.type IN ("earning","deduction") GROUP BY l.element_code, l.element_name, l.type ORDER BY seq, l.element_code') as $c) { $codes[$c['element_code']] = $c; }
        $byslip = array();
        foreach (Db::getInstance()->executeS('SELECT id_pulse_pr_payslip, element_code, amount FROM `'._DB_PREFIX_.'pulse_pr_payslip_line` WHERE id_pulse_pr_run='.(int) $idRun) as $l) { $byslip[(int) $l['id_pulse_pr_payslip']][$l['element_code']] = (float) $l['amount']; }
        $rows = array();
        foreach ($slips as $s) {
            $r = array('staff_no' => $s['staff_no'], 'name' => $s['employee_name'], 'department' => $s['department'], 'grade' => $s['grade'], 'days' => $s['days_paid']);
            foreach ($codes as $code => $c) { $r[$code] = isset($byslip[(int) $s['id_pulse_pr_payslip']][$code]) ? round($byslip[(int) $s['id_pulse_pr_payslip']][$code], 2) : 0; }
            $r['gross'] = round((float) $s['gross'], 2); $r['deductions'] = round((float) $s['total_deductions'], 2); $r['net'] = round((float) $s['net_pay'], 2);
            $r['employer_cost'] = round((float) $s['employer_cost'], 2);
            $rows[] = $r;
        }
        return array('columns' => $codes, 'rows' => $rows, 'totals' => self::totalRow($rows));
    }

    protected static function totalRow(array $rows)
    {
        if (!$rows) { return array(); }
        $t = array('staff_no' => '', 'name' => 'TOTAL', 'department' => '', 'grade' => '', 'days' => '');
        foreach ($rows as $r) { foreach ($r as $k => $v) { if (in_array($k, array('staff_no', 'name', 'department', 'grade', 'days'))) { continue; } $t[$k] = round((isset($t[$k]) ? $t[$k] : 0) + (float) $v, 2); } }
        return $t;
    }

    /** Departmental cost, the way a hotel P&L wants it: gross, employer contributions, total cost. */
    public static function departmentCost($period)
    {
        return Db::getInstance()->executeS('SELECT p.department, COUNT(*) headcount, ROUND(SUM(p.gross),2) gross, ROUND(SUM(p.pension_er),2) pension_er, ROUND(SUM(p.nsitf_er),2) nsitf_er, ROUND(SUM(p.itf_er),2) itf_er, ROUND(SUM(p.employer_cost),2) employer_cost, ROUND(SUM(p.gross+p.employer_cost),2) total_cost, ROUND(SUM(p.paye),2) paye, ROUND(SUM(p.net_pay),2) net
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.period="'.pSQL($period).'" AND r.status<>"cancelled" GROUP BY p.department ORDER BY total_cost DESC');
    }

    /* ---------------- statutory schedules ---------------- */

    /** State IRS PAYE schedule: the columns a Rivers State (or any state) monthly return asks for. */
    public static function payeSchedule($period)
    {
        return Db::getInstance()->executeS('SELECT p.staff_no, p.employee_name, e.tin, e.nin, p.department, ROUND(p.gross,2) gross_emolument, ROUND(p.reliefs_total,2) reliefs, ROUND(p.pension_ee,2) pension, ROUND(p.nhf,2) nhf, ROUND(p.chargeable_income,2) chargeable_annual, ROUND(p.paye_annual,2) annual_tax, ROUND(p.paye,2) monthly_paye, e.tax_state
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p LEFT JOIN `'._DB_PREFIX_.'pulse_pr_employee` e ON e.id_pulse_pr_employee=p.id_pulse_pr_employee INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.period="'.pSQL($period).'" AND r.status IN ("approved","paid","posted") ORDER BY p.department, p.employee_name');
    }

    /** PenCom / PFA remittance schedule: RSA PIN, PFA, the split between the two sides. */
    public static function pensionSchedule($period)
    {
        return Db::getInstance()->executeS('SELECT p.staff_no, p.employee_name, e.rsa_pin, e.pfa, p.department, ROUND(p.bht,2) pensionable_base, ROUND(p.pension_ee,2) employee_8pct, ROUND(p.pension_er,2) employer_10pct, ROUND(p.pension_ee+p.pension_er,2) total
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p LEFT JOIN `'._DB_PREFIX_.'pulse_pr_employee` e ON e.id_pulse_pr_employee=p.id_pulse_pr_employee INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.period="'.pSQL($period).'" AND r.status IN ("approved","paid","posted") AND (p.pension_ee>0 OR p.pension_er>0) ORDER BY e.pfa, p.employee_name');
    }

    /** NSITF: 1% of total monthly emolument, employer-borne, everybody. */
    public static function nsitfSchedule($period)
    {
        return Db::getInstance()->executeS('SELECT p.staff_no, p.employee_name, e.nsitf_no, p.department, ROUND(p.gross,2) monthly_emolument, ROUND(p.nsitf_er,2) nsitf_1pct
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p LEFT JOIN `'._DB_PREFIX_.'pulse_pr_employee` e ON e.id_pulse_pr_employee=p.id_pulse_pr_employee INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.period="'.pSQL($period).'" AND r.status IN ("approved","paid","posted") ORDER BY p.department, p.employee_name');
    }

    /** ITF: accrued monthly, remitted annually — so the schedule is a year, not a month. */
    public static function itfSchedule($year)
    {
        return Db::getInstance()->executeS('SELECT p.period, COUNT(*) headcount, ROUND(SUM(p.gross),2) annual_gross_payroll, ROUND(SUM(p.itf_er),2) itf_1pct
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.period LIKE "'.pSQL((string) (int) $year).'-%" AND r.status IN ("approved","paid","posted") GROUP BY p.period ORDER BY p.period');
    }

    /**
     * NHF: only the people who have actually consented. The schedule carries the consent date because
     * the whole point of the 2025 change is that a deduction without one is not lawful.
     */
    public static function nhfSchedule($period)
    {
        return Db::getInstance()->executeS('SELECT p.staff_no, p.employee_name, e.nhf_no, p.department, ROUND(p.basic,2) basic, ROUND(p.nhf,2) nhf_2_5pct, p.nhf_consent_date
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p LEFT JOIN `'._DB_PREFIX_.'pulse_pr_employee` e ON e.id_pulse_pr_employee=p.id_pulse_pr_employee INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.period="'.pSQL($period).'" AND r.status IN ("approved","paid","posted") AND p.nhf>0 ORDER BY p.employee_name');
    }

    public static function schedule($which, $period)
    {
        switch ($which) {
            case 'pension': return self::pensionSchedule($period);
            case 'nsitf': return self::nsitfSchedule($period);
            case 'itf': return self::itfSchedule((int) Tools::substr($period, 0, 4));
            case 'nhf': return self::nhfSchedule($period);
            default: return self::payeSchedule($period);
        }
    }

    /* ---------------- year to date ---------------- */

    public static function ytd($year, $department = null)
    {
        return Db::getInstance()->executeS('SELECT p.staff_no, p.employee_name, p.department, COUNT(*) periods, ROUND(SUM(p.gross),2) gross, ROUND(SUM(p.taxable_gross),2) taxable, ROUND(SUM(p.paye),2) paye, ROUND(SUM(p.pension_ee),2) pension_ee, ROUND(SUM(p.pension_er),2) pension_er, ROUND(SUM(p.nhf),2) nhf, ROUND(SUM(p.loan_recovered),2) loans, ROUND(SUM(p.total_deductions),2) deductions, ROUND(SUM(p.net_pay),2) net
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run
            WHERE p.period LIKE "'.pSQL((string) (int) $year).'-%" AND r.status IN ("approved","paid","posted")'.($department ? ' AND p.department="'.pSQL($department).'"' : '').'
            GROUP BY p.id_pulse_pr_employee, p.staff_no, p.employee_name, p.department ORDER BY p.department, p.employee_name');
    }

    /**
     * Headcount reconciliation: who was paid, who is on the roster, and the difference explained.
     * The month-end question a financial controller always asks and payroll can rarely answer quickly.
     */
    public static function headcountReconciliation($period)
    {
        $from = PulsePrService::periodFrom($period); $to = PulsePrService::periodTo($period);
        $paid = (int) Db::getInstance()->getValue('SELECT COUNT(DISTINCT p.id_pulse_pr_employee) FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run WHERE p.period="'.pSQL($period).'" AND r.status<>"cancelled"');
        $roster = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE status IN ("active","probation","on_leave") AND employment_type<>"casual" AND pay_basis="monthly"');
        $joiners = Db::getInstance()->executeS('SELECT staff_no, CONCAT(firstname," ",lastname) name, department, hire_date FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE hire_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY hire_date');
        $leavers = Db::getInstance()->executeS('SELECT staff_no, CONCAT(firstname," ",lastname) name, department, exit_date FROM `'._DB_PREFIX_.'pulse_pr_employee` WHERE exit_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY exit_date');
        $unpaid = Db::getInstance()->executeS('SELECT e.staff_no, CONCAT(e.firstname," ",e.lastname) name, e.department, e.status, e.on_hold, e.hold_reason FROM `'._DB_PREFIX_.'pulse_pr_employee` e WHERE e.status IN ("active","probation","on_leave") AND e.employment_type<>"casual" AND e.pay_basis="monthly" AND (e.hire_date IS NULL OR e.hire_date<="'.pSQL($to).'") AND e.id_pulse_pr_employee NOT IN (SELECT p.id_pulse_pr_employee FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run WHERE p.period="'.pSQL($period).'" AND r.status<>"cancelled") ORDER BY e.department, e.lastname');
        $casuals = (int) Db::getInstance()->getValue('SELECT COALESCE(SUM(headcount),0) FROM `'._DB_PREFIX_.'pulse_pr_casual_batch` WHERE period="'.pSQL($period).'" AND status<>"cancelled"');
        return array('period' => $period, 'paid' => $paid, 'roster' => $roster, 'joiners' => $joiners, 'leavers' => $leavers, 'not_paid' => $unpaid, 'casual_engagements' => $casuals, 'difference' => $roster - $paid);
    }

    /**
     * The hospitality numbers. Labour cost per occupied room needs Front Desk for the occupancy; payroll
     * as a percentage of revenue needs Accounts for the revenue. Both degrade to null rather than to a
     * number that looks real and is not.
     */
    public static function hospitalityKpi($period)
    {
        $from = PulsePrService::periodFrom($period); $to = PulsePrService::periodTo($period);
        $cost = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(p.gross+p.employer_cost),0) FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run WHERE p.period="'.pSQL($period).'" AND r.status<>"cancelled"'), 2);
        $casual = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(total_gross),0) FROM `'._DB_PREFIX_.'pulse_pr_casual_batch` WHERE period="'.pSQL($period).'" AND status<>"cancelled"'), 2);
        $total = round($cost + $casual, 2);
        $nights = PulsePrService::occupiedRoomNights($from, $to);
        $revenue = null;
        if (PulsePrService::acc() && PulsePrService::tableExists('pulse_acc_journal_line')) {
            $revenue = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.credit-l.debit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND a.type="revenue" AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'), 2);
        }
        return array(
            'period' => $period, 'payroll_cost' => $cost, 'casual_cost' => $casual, 'total_labour_cost' => $total,
            'occupied_room_nights' => $nights, 'cost_per_occupied_room' => $nights > 0 ? round($total / $nights, 2) : null,
            'revenue' => $revenue, 'labour_pct_of_revenue' => ($revenue !== null && $revenue > 0) ? round($total / $revenue * 100, 2) : null,
            'departments' => self::departmentCost($period),
        );
    }

    /** Loan and arrears book, for the finance pack. */
    public static function loanBook()
    {
        return Db::getInstance()->executeS('SELECT l.loan_no, e.staff_no, CONCAT(e.firstname," ",e.lastname) employee_name, e.department, l.type, l.principal, l.interest_amount, l.total_repayable, l.recovered, l.balance, l.instalments, l.instalment_amount, l.status, l.date_disbursed
            FROM `'._DB_PREFIX_.'pulse_pr_loan` l INNER JOIN `'._DB_PREFIX_.'pulse_pr_employee` e ON e.id_pulse_pr_employee=l.id_pulse_pr_employee
            WHERE l.status IN ("disbursed","repaying") ORDER BY l.balance DESC');
    }

    /** Service-charge distribution history for the finance pack and for a staff notice board. */
    public static function troncHistory($year)
    {
        return Db::getInstance()->executeS('SELECT period, pool_no, basis, gross_pool, admin_amount, breakage_amount, distributable, distributed, participants, management_capped_amount, status FROM `'._DB_PREFIX_.'pulse_pr_tronc_pool` WHERE period LIKE "'.pSQL((string) (int) $year).'-%" AND status<>"cancelled" ORDER BY period');
    }

    /** One dispatcher so the CSV export and the screen can never drift apart. */
    public static function run($which, array $p)
    {
        $period = isset($p['period']) ? $p['period'] : date('Y-m');
        $year = isset($p['year']) ? (int) $p['year'] : (int) date('Y');
        switch ($which) {
            case 'register': return self::register((int) (isset($p['id_run']) ? $p['id_run'] : 0));
            case 'department': return self::departmentCost($period);
            case 'paye': return self::payeSchedule($period);
            case 'pension': return self::pensionSchedule($period);
            case 'nsitf': return self::nsitfSchedule($period);
            case 'itf': return self::itfSchedule($year);
            case 'nhf': return self::nhfSchedule($period);
            case 'ytd': return self::ytd($year, isset($p['department']) ? $p['department'] : null);
            case 'headcount': return self::headcountReconciliation($period);
            case 'kpi': return self::hospitalityKpi($period);
            case 'loans': return self::loanBook();
            case 'tronc': return self::troncHistory($year);
            case 'bank': return PulsePrBankFile::summaryForRun((int) (isset($p['id_run']) ? $p['id_run'] : 0));
            case 'variance': return PulsePrRun::variance((int) (isset($p['id_run']) ? $p['id_run'] : 0));
            default: return array();
        }
    }

    /** Flatten a report result into CSV rows, whatever shape it came back in. */
    public static function flatten($which, $data)
    {
        if (!is_array($data)) { return array(); }
        switch ($which) {
            case 'register':
                $rows = isset($data['rows']) ? $data['rows'] : array();
                if (!empty($data['totals'])) { $rows[] = $data['totals']; }
                return $rows;
            case 'headcount':
                $rows = array(array('line' => 'Employees paid', 'value' => $data['paid']), array('line' => 'Roster headcount', 'value' => $data['roster']), array('line' => 'Difference', 'value' => $data['difference']), array('line' => 'Casual engagements', 'value' => $data['casual_engagements']));
                foreach ($data['joiners'] as $j) { $rows[] = array('line' => 'Joiner '.$j['staff_no'].' '.$j['name'].' ('.$j['department'].')', 'value' => $j['hire_date']); }
                foreach ($data['leavers'] as $j) { $rows[] = array('line' => 'Leaver '.$j['staff_no'].' '.$j['name'].' ('.$j['department'].')', 'value' => $j['exit_date']); }
                foreach ($data['not_paid'] as $j) { $rows[] = array('line' => 'On roster, not paid: '.$j['staff_no'].' '.$j['name'].' ('.$j['department'].')', 'value' => $j['on_hold'] ? 'on hold — '.$j['hold_reason'] : $j['status']); }
                return $rows;
            case 'kpi':
                $rows = array();
                foreach (array('payroll_cost', 'casual_cost', 'total_labour_cost', 'occupied_room_nights', 'cost_per_occupied_room', 'revenue', 'labour_pct_of_revenue') as $k) { $rows[] = array('metric' => $k, 'value' => $data[$k] === null ? 'n/a' : $data[$k]); }
                foreach ($data['departments'] as $d) { $rows[] = array('metric' => 'Department cost — '.$d['department'], 'value' => $d['total_cost']); }
                return $rows;
            case 'bank':
                $rows = array();
                foreach ($data['by_bank'] as $b) { $rows[] = array('bank' => $b['bank_name'], 'code' => $b['bank_code'], 'beneficiaries' => $b['n'], 'amount' => $b['total']); }
                $rows[] = array('bank' => 'Cash / cheque', 'code' => '', 'beneficiaries' => $data['cash']['n'], 'amount' => $data['cash']['total']);
                $rows[] = array('bank' => 'TOTAL NET', 'code' => '', 'beneficiaries' => '', 'amount' => $data['total']);
                return $rows;
            default: return $data;
        }
    }
}
