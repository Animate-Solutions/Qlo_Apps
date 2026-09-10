<?php
/**
 * Bank payment files.
 *
 * A Nigerian hotel pays salaries by uploading a bulk-transfer file to its own bank's corporate portal.
 * The file is split by beneficiary bank, carries the NIBSS institution code for each beneficiary, and is
 * checked at the counter against a control total and a record count — so those two numbers are printed
 * on the screen, stored with the file, and reproduced in the file itself.
 *
 * Two rules the module enforces without exception:
 *  - a payment file is only ever written from an APPROVED run or an APPROVED casual batch;
 *  - the sum of the rows must equal the control total to the kobo, or nothing is written at all.
 *
 * Two layouts ship: the NIBSS-style bulk upload most banks accept, and a generic mapper for a bank with
 * its own column order, driven by a column list the property configures.
 */
class PulsePrBankFile
{
    /** The columns the NIBSS-style layout emits, in order. */
    public static function nibssColumns() { return array('beneficiary_bank_code', 'beneficiary_bank', 'account_number', 'account_name', 'amount', 'narration', 'staff_number', 'email', 'phone'); }

    public static function files($idRun = null, $limit = 100)
    {
        return Db::getInstance()->executeS('SELECT f.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_pr_bank_file` f LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=f.id_employee WHERE 1'.($idRun ? ' AND f.id_pulse_pr_run='.(int) $idRun : '').' ORDER BY f.id_pulse_pr_bank_file DESC LIMIT '.(int) $limit);
    }

    public static function file($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_bank_file` WHERE id_pulse_pr_bank_file='.(int) $id); }

    /**
     * The payment rows for an approved run: everyone paid by bank with a positive net. Staff paid in cash
     * are deliberately excluded and reported separately so the cashier's float is never a surprise.
     */
    public static function rowsForRun($idRun)
    {
        return Db::getInstance()->executeS('SELECT p.staff_no, p.employee_name, p.department, p.net_pay, p.bank_name, p.bank_code, p.account_no, p.pay_method, e.account_name, e.email, e.phone
            FROM `'._DB_PREFIX_.'pulse_pr_payslip` p LEFT JOIN `'._DB_PREFIX_.'pulse_pr_employee` e ON e.id_pulse_pr_employee=p.id_pulse_pr_employee
            WHERE p.id_pulse_pr_run='.(int) $idRun.' AND p.pay_method="bank" AND p.net_pay>0 ORDER BY p.bank_name, p.employee_name');
    }

    public static function cashRowsForRun($idRun)
    {
        return Db::getInstance()->executeS('SELECT staff_no, employee_name, department, net_pay, pay_method FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $idRun.' AND pay_method<>"bank" AND net_pay>0 ORDER BY department, employee_name');
    }

    public static function rowsForBatch($idBatch)
    {
        return Db::getInstance()->executeS('SELECT staff_no, name employee_name, department, net net_pay, bank_code, account_no, "" bank_name, "bank" pay_method, name account_name, "" email, phone FROM `'._DB_PREFIX_.'pulse_pr_casual_line` WHERE id_pulse_pr_casual_batch='.(int) $idBatch.' AND net>0 AND COALESCE(account_no,"")<>"" ORDER BY name');
    }

    /**
     * Generate the payment file(s) for an approved run.
     * @param string $split 'bank' for one file per beneficiary bank, 'single' for one combined file
     * @return array the ids of the files written
     */
    public static function generateForRun($idRun, $split = 'bank', $template = null)
    {
        $run = PulsePrRun::get($idRun);
        if (!$run) { throw new PrestaShopException('Unknown run'); }
        if (!in_array($run['status'], array('approved', 'paid', 'posted'))) { throw new PrestaShopException('Run '.$run['run_no'].' is '.$run['status'].' — a payment file is only ever written from an approved run'); }
        $rows = self::rowsForRun($idRun);
        if (!$rows) { throw new PrestaShopException('Nobody in run '.$run['run_no'].' is paid by bank transfer'); }
        return self::write($rows, $split, $template, array('id_pulse_pr_run' => (int) $idRun, 'value_date' => $run['pay_date'], 'narration' => 'Salary '.$run['period'], 'label' => $run['run_no']));
    }

    /** The same for a weekly casual batch that is paid by transfer rather than cash. */
    public static function generateForBatch($idBatch, $split = 'bank', $template = null)
    {
        $b = PulsePrCasual::batch($idBatch);
        if (!$b) { throw new PrestaShopException('Unknown batch'); }
        if (!in_array($b['status'], array('approved', 'paid', 'posted'))) { throw new PrestaShopException('Batch '.$b['batch_no'].' is '.$b['status'].' — a payment file is only ever written from an approved batch'); }
        $rows = self::rowsForBatch($idBatch);
        if (!$rows) { throw new PrestaShopException('Nobody in batch '.$b['batch_no'].' has a bank account on file'); }
        return self::write($rows, $split, $template, array('id_pulse_pr_casual_batch' => (int) $idBatch, 'value_date' => $b['pay_date'], 'narration' => 'Casual pay '.$b['week_start'].' to '.$b['week_end'], 'label' => $b['batch_no']));
    }

    /** Split, build, prove and store. Nothing is written unless every group's rows sum to its control total. */
    protected static function write(array $rows, $split, $template, array $meta)
    {
        $template = $template ? $template : PulsePrService::cfg('BANK_FILE_TEMPLATE', 'nibss');
        $banks = array();
        foreach (PulsePrService::banks(false) as $b) { $banks[$b['name']] = $b; if ($b['nibss_code']) { $banks['#'.$b['nibss_code']] = $b; } }
        $groups = array();
        foreach ($rows as $r) {
            $code = trim((string) $r['bank_code']);
            $name = trim((string) $r['bank_name']);
            if ($code === '' && $name !== '' && isset($banks[$name])) { $code = $banks[$name]['nibss_code']; }
            if ($name === '' && $code !== '' && isset($banks['#'.$code])) { $name = $banks['#'.$code]['name']; }
            if (trim((string) $r['account_no']) === '' || $code === '') { throw new PrestaShopException($r['employee_name'].' has no bank code or account number — fix the record and regenerate; nothing has been written'); }
            $r['bank_code'] = $code; $r['bank_name'] = $name !== '' ? $name : $code;
            $key = $split === 'single' ? 'ALL' : $code;
            if (!isset($groups[$key])) { $groups[$key] = array('bank_name' => $split === 'single' ? 'All banks' : $r['bank_name'], 'bank_code' => $split === 'single' ? '' : $code, 'rows' => array()); }
            $groups[$key]['rows'][] = $r;
        }
        $ids = array();
        foreach ($groups as $g) {
            $total = 0; foreach ($g['rows'] as $r) { $total = round($total + (float) $r['net_pay'], 2); }
            $body = $template === 'nibss' ? self::renderNibss($g['rows'], $meta, $total) : self::renderGeneric($g['rows'], $meta, $total);
            $check = self::proveTotal($body, $template, $total);
            if ($check !== true) { throw new PrestaShopException('The payment file for '.$g['bank_name'].' does not prove: '.$check.'. Nothing has been written.'); }
            $no = PulsePrService::nextNo('BF', 5);
            $filename = 'pulse-'.Tools::str2url($meta['label'].'-'.$g['bank_name']).'-'.date('Ymd').'.csv';
            Db::getInstance()->insert('pulse_pr_bank_file', array(
                'id_pulse_pr_run' => isset($meta['id_pulse_pr_run']) ? (int) $meta['id_pulse_pr_run'] : null,
                'id_pulse_pr_casual_batch' => isset($meta['id_pulse_pr_casual_batch']) ? (int) $meta['id_pulse_pr_casual_batch'] : null,
                'file_no' => pSQL($no), 'bank_name' => pSQL(Tools::substr($g['bank_name'], 0, 96)), 'bank_code' => pSQL($g['bank_code']),
                'template' => pSQL($template), 'filename' => pSQL($filename), 'record_count' => count($g['rows']), 'control_total' => $total,
                'checksum' => pSQL(sha1($body)), 'body' => pSQL($body, true), 'value_date' => pSQL($meta['value_date']), 'status' => 'generated',
                'id_employee' => PulsePrService::emp(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
            ), true);
            $ids[] = (int) Db::getInstance()->Insert_ID();
        }
        PulsePrService::log(isset($meta['id_pulse_pr_run']) ? (int) $meta['id_pulse_pr_run'] : null, 'bank_file_generate', 'bank_file', array('files' => count($ids), 'template' => $template, 'split' => $split), $ids ? $ids[0] : null);
        return $ids;
    }

    /**
     * NIBSS-style bulk upload: a header row, one row per beneficiary, then a control row the bank's
     * counter clerk reads back to you. Fields are quoted through fputcsv so a name with a comma in it
     * cannot silently shift every column to the right.
     */
    protected static function renderNibss(array $rows, array $meta, $total)
    {
        $f = fopen('php://temp', 'r+');
        fputcsv($f, self::nibssColumns());
        foreach ($rows as $r) {
            fputcsv($f, array(
                $r['bank_code'], $r['bank_name'], $r['account_no'],
                !empty($r['account_name']) ? $r['account_name'] : $r['employee_name'],
                number_format((float) $r['net_pay'], 2, '.', ''),
                Tools::substr($meta['narration'].' '.$r['staff_no'], 0, 100),
                $r['staff_no'], isset($r['email']) ? $r['email'] : '', isset($r['phone']) ? $r['phone'] : '',
            ));
        }
        fputcsv($f, array('CONTROL', 'RECORDS', count($rows), 'TOTAL', number_format((float) $total, 2, '.', ''), 'VALUE DATE', $meta['value_date'], '', ''));
        rewind($f); $s = stream_get_contents($f); fclose($f);
        return $s;
    }

    /** Generic mapper: the property lists the columns its bank wants, in its bank's order. */
    protected static function renderGeneric(array $rows, array $meta, $total)
    {
        $cols = array_filter(array_map('trim', explode(',', (string) PulseCoreService::setting('pulsepayroll', 'bank_columns'))));
        if (!$cols) { $cols = array('account_number', 'account_name', 'beneficiary_bank_code', 'amount', 'narration'); }
        $f = fopen('php://temp', 'r+');
        fputcsv($f, $cols);
        foreach ($rows as $r) {
            $map = array(
                'account_number' => $r['account_no'], 'account_name' => !empty($r['account_name']) ? $r['account_name'] : $r['employee_name'],
                'beneficiary_bank_code' => $r['bank_code'], 'beneficiary_bank' => $r['bank_name'], 'amount' => number_format((float) $r['net_pay'], 2, '.', ''),
                'narration' => Tools::substr($meta['narration'].' '.$r['staff_no'], 0, 100), 'staff_number' => $r['staff_no'],
                'email' => isset($r['email']) ? $r['email'] : '', 'phone' => isset($r['phone']) ? $r['phone'] : '',
                'department' => $r['department'], 'value_date' => $meta['value_date'], 'currency' => PulsePrService::currency(),
            );
            $line = array();
            foreach ($cols as $c) { $line[] = isset($map[$c]) ? $map[$c] : ''; }
            fputcsv($f, $line);
        }
        fputcsv($f, array_merge(array('CONTROL '.count($rows).' records', number_format((float) $total, 2, '.', '')), array_fill(0, max(0, count($cols) - 2), '')));
        rewind($f); $s = stream_get_contents($f); fclose($f);
        return $s;
    }

    /**
     * Read the file back and add the amount column up again. This is not paranoia: a bank file that does
     * not agree with itself is the single most expensive mistake a payroll department makes.
     */
    protected static function proveTotal($body, $template, $expected)
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($body));
        if (count($lines) < 3) { return 'it has no beneficiary rows'; }
        $header = str_getcsv($lines[0]);
        $amountAt = array_search('amount', $header);
        if ($amountAt === false) { return 'it has no amount column'; }
        $sum = 0; $n = 0;
        for ($i = 1; $i < count($lines) - 1; $i++) {
            $r = str_getcsv($lines[$i]);
            if (!isset($r[$amountAt])) { return 'row '.$i.' is short of columns'; }
            $sum = round($sum + (float) $r[$amountAt], 2); $n++;
        }
        if (abs($sum - round((float) $expected, 2)) > 0.004) { return 'the rows add up to '.number_format($sum, 2).' against a control total of '.number_format((float) $expected, 2); }
        if ($n < 1) { return 'it has no beneficiary rows'; }
        return true;
    }

    public static function setStatus($id, $status)
    {
        if (!in_array($status, array('generated', 'downloaded', 'sent', 'acknowledged', 'void'))) { throw new PrestaShopException('Unknown file status'); }
        Db::getInstance()->update('pulse_pr_bank_file', array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_bank_file='.(int) $id);
        PulsePrService::log(null, 'bank_file_'.$status, 'bank_file', null, (int) $id);
        return true;
    }

    /** Summary for the run screen: what goes to each bank, what goes out in cash, and the grand total. */
    public static function summaryForRun($idRun)
    {
        $byBank = Db::getInstance()->executeS('SELECT COALESCE(NULLIF(bank_name,""),"(no bank on file)") bank_name, bank_code, COUNT(*) n, ROUND(SUM(net_pay),2) total FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $idRun.' AND pay_method="bank" AND net_pay>0 GROUP BY bank_name, bank_code ORDER BY total DESC');
        $cash = Db::getInstance()->getRow('SELECT COUNT(*) n, ROUND(COALESCE(SUM(net_pay),0),2) total FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $idRun.' AND pay_method<>"bank" AND net_pay>0');
        $total = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(net_pay),0) FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_pr_run='.(int) $idRun), 2);
        return array('by_bank' => $byBank, 'cash' => $cash, 'total' => $total, 'files' => self::files($idRun));
    }
}
