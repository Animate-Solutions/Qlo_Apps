<?php
/**
 * Casual and per-shift pay on a weekly cycle.
 *
 * Banqueting extras, laundry casuals and the porters a Port Harcourt hotel brings in for a wedding are
 * paid weekly, usually in cash, from a sheet a supervisor signs. The screen has to be fast: pick the
 * week, pull the approved timesheets, adjust a couple of lines, approve, print the sheet, pay.
 *
 * Tax treatment is deliberately separate from the monthly cycle. Casual earnings are not annualised —
 * there is no year to annualise over — so they carry a flat deduction rate (zero by default, because
 * most Nigerian properties treat a genuine casual engagement as outside PAYE and account for it as a
 * service cost) which the property sets once in Payroll Settings.
 */
class PulsePrCasual
{
    public static function batches($limit = 60)
    {
        return Db::getInstance()->executeS('SELECT b.*, CONCAT(e.firstname," ",e.lastname) approver FROM `'._DB_PREFIX_.'pulse_pr_casual_batch` b LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=b.approved_by ORDER BY b.week_start DESC, b.id_pulse_pr_casual_batch DESC LIMIT '.(int) $limit);
    }

    public static function batch($id)
    {
        $b = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_casual_batch` WHERE id_pulse_pr_casual_batch='.(int) $id);
        if ($b) { $b['lines'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_casual_line` WHERE id_pulse_pr_casual_batch='.(int) $id.' ORDER BY department, name'); }
        return $b;
    }

    /** Open a week. Monday to Sunday by default, which is how the duty roster is written. */
    public static function createBatch(array $d)
    {
        $start = !empty($d['week_start']) ? $d['week_start'] : date('Y-m-d', strtotime('monday this week'));
        $end = !empty($d['week_end']) ? $d['week_end'] : date('Y-m-d', strtotime($start.' +6 day'));
        $row = array(
            'batch_no' => pSQL(PulsePrService::nextNo('CW', 5)), 'week_start' => pSQL($start), 'week_end' => pSQL($end),
            'period' => pSQL(Tools::substr($end, 0, 7)), 'department' => pSQL(Tools::substr(isset($d['department']) ? $d['department'] : '', 0, 32)),
            'pay_method' => pSQL(in_array(isset($d['pay_method']) ? $d['pay_method'] : '', array('cash', 'bank', 'mixed')) ? $d['pay_method'] : 'cash'),
            'pay_date' => pSQL(!empty($d['pay_date']) ? $d['pay_date'] : $end), 'status' => 'draft',
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 255)), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        );
        Db::getInstance()->insert('pulse_pr_casual_batch', $row, true);
        $id = (int) Db::getInstance()->Insert_ID();
        PulsePrService::log(null, 'casual_create', 'casual', $row, $id);
        return $id;
    }

    /**
     * Pull every casual on the roster into the batch, with their units taken from the approved timesheet
     * where Pulse Time has one and left at zero where it does not — a supervisor then keys the days in.
     */
    public static function pullFromTimesheets($id)
    {
        $b = self::batch($id);
        if (!$b) { throw new PrestaShopException('Unknown batch'); }
        if ($b['status'] !== 'draft') { throw new PrestaShopException('Batch '.$b['batch_no'].' is '.$b['status'].' and can no longer be built'); }
        $f = array('employment_type' => 'casual,service', 'status' => 'active,probation');
        if ($b['department']) { $f['department'] = $b['department']; }
        $n = 0;
        foreach (PulsePrService::employees($f) as $e) {
            if (Db::getInstance()->getValue('SELECT id_pulse_pr_casual_line FROM `'._DB_PREFIX_.'pulse_pr_casual_line` WHERE id_pulse_pr_casual_batch='.(int) $id.' AND id_pulse_pr_employee='.(int) $e['id_pulse_pr_employee'])) { continue; }
            $ts = PulsePrService::timesheet((int) $e['id_pulse_pr_employee'], $b['period'], $e['id_hr_employee']);
            $basis = in_array($e['pay_basis'], array('daily', 'hourly', 'per_shift')) ? $e['pay_basis'] : 'daily';
            $units = 0;
            if ($ts) { $units = $basis === 'hourly' ? (float) $ts['hours_worked'] : ($basis === 'per_shift' ? (float) $ts['shifts'] : (float) $ts['days_worked']); }
            self::saveLine(array(
                'id_pulse_pr_casual_batch' => $id, 'id_pulse_pr_employee' => (int) $e['id_pulse_pr_employee'], 'staff_no' => $e['staff_no'],
                'name' => trim($e['firstname'].' '.$e['lastname']), 'phone' => $e['phone'], 'department' => $e['department'], 'role' => $e['position'],
                'basis' => $basis, 'units' => $units, 'rate' => (float) $e['pay_rate'] > 0 ? (float) $e['pay_rate'] : (float) PulsePrService::cfg('CASUAL_DAY_RATE', 7500),
                'bank_code' => $e['bank_code'], 'account_no' => $e['account_no'], 'source' => $ts && $ts['source'] === 'pulsetime' ? 'pulsetime' : 'manual',
            ));
            $n++;
        }
        self::refresh($id);
        return $n;
    }

    /** Add or update one line. The arithmetic is deliberately trivial and visible: units x rate, less tax. */
    public static function saveLine(array $d)
    {
        if (empty($d['id_pulse_pr_casual_batch'])) { throw new PrestaShopException('A casual line needs a batch'); }
        $b = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_casual_batch` WHERE id_pulse_pr_casual_batch='.(int) $d['id_pulse_pr_casual_batch']);
        if (!$b) { throw new PrestaShopException('Unknown batch'); }
        if (!in_array($b['status'], array('draft'))) { throw new PrestaShopException('Batch '.$b['batch_no'].' is '.$b['status'].' and can no longer be edited'); }
        if (empty($d['name'])) { throw new PrestaShopException('A casual line needs a name'); }
        $units = round((float) (isset($d['units']) ? $d['units'] : 0), 3);
        $rate = round((float) (isset($d['rate']) ? $d['rate'] : 0), 2);
        $gross = PulsePrService::money($units * $rate);
        $taxPct = (float) PulsePrService::cfg('CASUAL_TAX_PCT', 0);
        $tax = PulsePrService::money($gross * $taxPct / 100);
        $other = PulsePrService::money(isset($d['other_deduction']) ? $d['other_deduction'] : 0);
        $net = PulsePrService::money($gross - $tax - $other);
        if ($net < 0) { $other = PulsePrService::money($gross - $tax); $net = 0.0; }
        $row = array(
            'id_pulse_pr_casual_batch' => (int) $d['id_pulse_pr_casual_batch'],
            'id_pulse_pr_employee' => !empty($d['id_pulse_pr_employee']) ? (int) $d['id_pulse_pr_employee'] : null,
            'staff_no' => pSQL(Tools::substr(isset($d['staff_no']) ? $d['staff_no'] : '', 0, 24)), 'name' => pSQL(Tools::substr($d['name'], 0, 128)),
            'phone' => pSQL(Tools::substr(isset($d['phone']) ? $d['phone'] : '', 0, 32)),
            'department' => pSQL(Tools::substr(!empty($d['department']) ? $d['department'] : 'general', 0, 32)),
            'role' => pSQL(Tools::substr(isset($d['role']) ? $d['role'] : '', 0, 96)),
            'basis' => pSQL(in_array(isset($d['basis']) ? $d['basis'] : '', array('daily', 'hourly', 'per_shift')) ? $d['basis'] : 'daily'),
            'units' => $units, 'rate' => $rate, 'gross' => $gross, 'tax' => $tax, 'other_deduction' => $other, 'net' => $net,
            'source' => pSQL(in_array(isset($d['source']) ? $d['source'] : '', array('pulsetime', 'manual')) ? $d['source'] : 'manual'),
            'bank_code' => pSQL(Tools::substr(isset($d['bank_code']) ? $d['bank_code'] : '', 0, 16)),
            'account_no' => pSQL(Tools::substr(isset($d['account_no']) ? $d['account_no'] : '', 0, 24)),
            'signed_off_by' => PulsePrService::emp(), 'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 160)),
        );
        $id = (int) (isset($d['id_pulse_pr_casual_line']) ? $d['id_pulse_pr_casual_line'] : 0);
        if ($id) { Db::getInstance()->update('pulse_pr_casual_line', $row, 'id_pulse_pr_casual_line='.$id, 0, true); }
        else { Db::getInstance()->insert('pulse_pr_casual_line', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        self::refresh((int) $d['id_pulse_pr_casual_batch']);
        return $id;
    }

    public static function deleteLine($id)
    {
        $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_casual_line` WHERE id_pulse_pr_casual_line='.(int) $id);
        if (!$l) { return false; }
        $b = Db::getInstance()->getRow('SELECT status FROM `'._DB_PREFIX_.'pulse_pr_casual_batch` WHERE id_pulse_pr_casual_batch='.(int) $l['id_pulse_pr_casual_batch']);
        if ($b && $b['status'] !== 'draft') { throw new PrestaShopException('The batch is '.$b['status'].' and can no longer be edited'); }
        Db::getInstance()->delete('pulse_pr_casual_line', 'id_pulse_pr_casual_line='.(int) $id);
        self::refresh((int) $l['id_pulse_pr_casual_batch']);
        return true;
    }

    public static function refresh($id)
    {
        $t = Db::getInstance()->getRow('SELECT COUNT(*) n, ROUND(SUM(gross),2) gross, ROUND(SUM(tax),2) tax, ROUND(SUM(net),2) net FROM `'._DB_PREFIX_.'pulse_pr_casual_line` WHERE id_pulse_pr_casual_batch='.(int) $id);
        Db::getInstance()->update('pulse_pr_casual_batch', array('headcount' => (int) $t['n'], 'total_gross' => round((float) $t['gross'], 2), 'total_tax' => round((float) $t['tax'], 2), 'total_net' => round((float) $t['net'], 2), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_casual_batch='.(int) $id);
        return true;
    }

    public static function approve($id)
    {
        $b = self::batch($id);
        if (!$b) { throw new PrestaShopException('Unknown batch'); }
        if ($b['status'] !== 'draft') { throw new PrestaShopException('Batch '.$b['batch_no'].' is already '.$b['status']); }
        if (!(int) $b['headcount']) { throw new PrestaShopException('Batch '.$b['batch_no'].' has no lines'); }
        $sum = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(net),0) FROM `'._DB_PREFIX_.'pulse_pr_casual_line` WHERE id_pulse_pr_casual_batch='.(int) $id), 2);
        if (abs($sum - (float) $b['total_net']) > 0.009) { self::refresh($id); }
        $zero = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_casual_line` WHERE id_pulse_pr_casual_batch='.(int) $id.' AND units<=0');
        if ($zero) { throw new PrestaShopException($zero.' line(s) have no days or hours — remove them or key in the units before approving'); }
        Db::getInstance()->update('pulse_pr_casual_batch', array('status' => 'approved', 'approved_by' => PulsePrService::emp(), 'date_approved' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_casual_batch='.(int) $id);
        PulsePrService::log(null, 'casual_approve', 'casual', array('batch' => $b['batch_no'], 'headcount' => $b['headcount'], 'net' => $b['total_net']), (int) $id);
        return true;
    }

    public static function markPaid($id)
    {
        $b = self::batch($id);
        if (!$b) { throw new PrestaShopException('Unknown batch'); }
        if ($b['status'] !== 'approved') { throw new PrestaShopException('Batch '.$b['batch_no'].' is '.$b['status'].' — approve it first'); }
        Db::getInstance()->update('pulse_pr_casual_batch', array('status' => 'paid', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_casual_batch='.(int) $id);
        PulsePrService::log(null, 'casual_paid', 'casual', array('batch' => $b['batch_no']), (int) $id);
        return true;
    }

    /**
     * Post an approved casual batch to the ledger as one journal: departmental casual-labour cost against
     * cash (or the bank), with any deduction parked in accrued expenses. Skipped silently when Pulse
     * Accounts is not installed — the batch still completes, it just does not reach the GL.
     */
    public static function post($id)
    {
        $b = self::batch($id);
        if (!$b) { throw new PrestaShopException('Unknown batch'); }
        if (!in_array($b['status'], array('approved', 'paid'))) { throw new PrestaShopException('Batch '.$b['batch_no'].' is '.$b['status'].' — approve it first'); }
        if ((int) $b['id_acc_journal']) { return (int) $b['id_acc_journal']; }
        if (!PulsePrService::acc()) { PulsePrService::log(null, 'casual_post_skipped', 'casual', 'Pulse Accounts is not installed', (int) $id); return null; }
        $rows = Db::getInstance()->executeS('SELECT department, ROUND(SUM(gross),2) gross FROM `'._DB_PREFIX_.'pulse_pr_casual_line` WHERE id_pulse_pr_casual_batch='.(int) $id.' GROUP BY department');
        $byDept = array(); foreach ($rows as $r) { $byDept[$r['department']] = round((float) $r['gross'], 2); }
        $deductions = array('other' => round((float) $b['total_tax'] + ((float) $b['total_gross'] - (float) $b['total_tax'] - (float) $b['total_net']), 2));
        $lhs = 0; foreach ($byDept as $v) { $lhs = round($lhs + $v, 2); }
        if (abs($lhs - round($deductions['other'] + (float) $b['total_net'], 2)) > 0.009) { throw new PrestaShopException('Casual batch '.$b['batch_no'].' will not balance in the ledger — nothing has been posted'); }
        $idJournal = PulseAccPosting::payroll('casual '.$b['batch_no'], $byDept, $deductions, $b['pay_date']);
        Db::getInstance()->update('pulse_pr_casual_batch', array('id_acc_journal' => (int) $idJournal, 'status' => 'posted', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_casual_batch='.(int) $id);
        PulsePrService::log(null, 'casual_post', 'casual', array('batch' => $b['batch_no'], 'journal' => $idJournal), (int) $id);
        return (int) $idJournal;
    }

    /** The signing sheet a supervisor takes to the pay-out table. */
    public static function payoutSheet($id)
    {
        return Db::getInstance()->executeS('SELECT staff_no, name, department, role, basis, units, rate, gross, tax, other_deduction, net, phone, bank_code, account_no FROM `'._DB_PREFIX_.'pulse_pr_casual_line` WHERE id_pulse_pr_casual_batch='.(int) $id.' ORDER BY department, name');
    }
}
