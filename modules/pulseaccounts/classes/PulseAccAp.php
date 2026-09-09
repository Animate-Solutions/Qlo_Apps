<?php
/**
 * Accounts payable: supplier bills (raised from a GRN or standalone), payment runs, ageing,
 * withholding-tax certificates and a remittance advice.
 *
 * A GRN posts stock Dr / GRN-accrual Cr. Billing that GRN clears the accrual, brings the input VAT in
 * and creates the payable; the WHT the hotel is obliged to withhold is deducted at bill or payment time
 * and parked in 2230 until it is remitted to FIRS.
 */
class PulseAccAp
{
    public static function suppliers($activeOnly = true)
    {
        if (!PulseAccService::inv()) { return array(); }
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_supplier` WHERE 1'.($activeOnly ? ' AND active=1' : '').' ORDER BY name');
    }

    public static function supplier($id) { return PulseAccService::inv() ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_supplier` WHERE id_pulse_inv_supplier='.(int) $id) : null; }

    /** GRNs that have been received but never billed — the accrual that has to be cleared each month end. */
    public static function unbilledGrns($limit = 200)
    {
        if (!PulseAccService::inv()) { return array(); }
        return Db::getInstance()->executeS('SELECT g.*, s.name supplier_name, s.payment_terms_days, s.tin FROM `'._DB_PREFIX_.'pulse_inv_grn` g LEFT JOIN `'._DB_PREFIX_.'pulse_inv_supplier` s ON s.id_pulse_inv_supplier=g.id_pulse_inv_supplier
            WHERE NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_acc_bill` b WHERE b.id_pulse_inv_grn=g.id_pulse_inv_grn AND b.status<>"cancelled") ORDER BY g.business_date DESC LIMIT '.(int) $limit);
    }

    /**
     * Bill a GRN: accrual Dr, input VAT Dr, trade payables Cr (less any WHT withheld).
     * $d may override the supplier's invoice number, date, VAT and WHT rate.
     */
    public static function billFromGrn($idGrn, array $d = array())
    {
        if (!PulseAccService::inv()) { throw new PrestaShopException('Pulse Inventory is not installed'); }
        $g = Db::getInstance()->getRow('SELECT g.*, s.name supplier_name, s.payment_terms_days, s.tin FROM `'._DB_PREFIX_.'pulse_inv_grn` g LEFT JOIN `'._DB_PREFIX_.'pulse_inv_supplier` s ON s.id_pulse_inv_supplier=g.id_pulse_inv_supplier WHERE g.id_pulse_inv_grn='.(int) $idGrn);
        if (!$g) { throw new PrestaShopException('Unknown GRN'); }
        if (Db::getInstance()->getValue('SELECT id_pulse_acc_bill FROM `'._DB_PREFIX_.'pulse_acc_bill` WHERE id_pulse_inv_grn='.(int) $idGrn.' AND status<>"cancelled"')) { throw new PrestaShopException('GRN '.$g['grn_no'].' is already billed'); }
        $net = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM((l.qty-l.rejected_qty)*l.unit_price),0) FROM `'._DB_PREFIX_.'pulse_inv_grn_line` l WHERE l.id_pulse_inv_grn='.(int) $idGrn), 2);
        $vat = isset($d['vat_amount']) ? round((float) $d['vat_amount'], 2) : round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM((l.qty-l.rejected_qty)*l.unit_price*l.tax_pct/100),0) FROM `'._DB_PREFIX_.'pulse_inv_grn_line` l WHERE l.id_pulse_inv_grn='.(int) $idGrn), 2);
        $lines = array(array('description' => 'Goods received on '.$g['grn_no'], 'account_code' => '2120', 'qty' => 1, 'unit_price' => $net, 'tax_rate' => $net > 0 ? round($vat / $net * 100, 3) : 0, 'tax_amount' => $vat, 'line_total' => round($net + $vat, 2), 'is_accrual_clear' => 1));
        return self::bill(array_merge(array(
            'id_pulse_inv_supplier' => $g['id_pulse_inv_supplier'], 'supplier_name' => $g['supplier_name'], 'tin' => $g['tin'],
            'id_pulse_inv_grn' => (int) $idGrn, 'grn_no' => $g['grn_no'], 'supplier_invoice_no' => $g['invoice_no'] ? $g['invoice_no'] : '',
            'bill_date' => $g['invoice_date'] ? $g['invoice_date'] : $g['business_date'], 'terms_days' => (int) $g['payment_terms_days'], 'lines' => $lines,
        ), $d));
    }

    /**
     * Create a supplier bill. Lines with is_accrual_clear=1 hit 2120 (they were already expensed or
     * capitalised when the goods arrived); every other line hits its own expense/asset account.
     */
    public static function bill(array $d)
    {
        $sup = !empty($d['id_pulse_inv_supplier']) ? self::supplier((int) $d['id_pulse_inv_supplier']) : null;
        $name = isset($d['supplier_name']) && $d['supplier_name'] ? $d['supplier_name'] : ($sup ? $sup['name'] : '');
        if ($name === '') { throw new PrestaShopException('A bill needs a supplier'); }
        $date = !empty($d['bill_date']) ? Tools::substr($d['bill_date'], 0, 10) : PulseAccService::bd();
        $terms = (int) (!empty($d['terms_days']) ? $d['terms_days'] : ($sup && $sup['payment_terms_days'] ? $sup['payment_terms_days'] : Configuration::get('PULSE_ACC_AP_TERMS_DAYS')));
        $whtRate = round((float) (isset($d['wht_rate_pct']) ? $d['wht_rate_pct'] : 0), 3);
        $prepared = array(); $sub = 0; $vat = 0;
        foreach ((isset($d['lines']) ? $d['lines'] : array()) as $l) {
            $qty = (float) (isset($l['qty']) ? $l['qty'] : 1);
            $price = round((float) (isset($l['unit_price']) ? $l['unit_price'] : 0), 2);
            if (abs($qty * $price) < 0.005) { continue; }
            $lineNet = round($qty * $price, 2);
            $rate = (float) (isset($l['tax_rate']) ? $l['tax_rate'] : 0);
            $t = isset($l['tax_amount']) ? round((float) $l['tax_amount'], 2) : round($lineNet * $rate / 100, 2);
            $acct = isset($l['account_code']) ? $l['account_code'] : '7120';
            if (!PulseAccService::account($acct)) { throw new PrestaShopException('Unknown account '.$acct.' on the bill'); }
            $prepared[] = array('description' => pSQL(Tools::substr(isset($l['description']) ? $l['description'] : 'Supply', 0, 255)), 'account_code' => pSQL($acct),
                'department' => pSQL(isset($l['department']) ? $l['department'] : 'general'), 'qty' => $qty, 'unit_price' => $price, 'tax_rate' => $rate,
                'tax_amount' => $t, 'line_total' => round($lineNet + $t, 2), 'id_pulse_inv_item' => !empty($l['id_pulse_inv_item']) ? (int) $l['id_pulse_inv_item'] : null,
                'is_accrual_clear' => !empty($l['is_accrual_clear']) ? 1 : 0);
            $sub += $lineNet; $vat += $t;
        }
        if (!$prepared) { throw new PrestaShopException('Bill has no lines'); }
        $sub = round($sub, 2); $vat = round($vat, 2); $total = round($sub + $vat, 2);
        $wht = $whtRate > 0 ? round($sub * $whtRate / 100, 2) : 0;
        $no = PulseAccService::nextNo('BILL', 5);
        Db::getInstance()->insert('pulse_acc_bill', array(
            'bill_no' => pSQL($no), 'supplier_invoice_no' => pSQL(Tools::substr(isset($d['supplier_invoice_no']) ? $d['supplier_invoice_no'] : '', 0, 64)),
            'id_pulse_inv_supplier' => !empty($d['id_pulse_inv_supplier']) ? (int) $d['id_pulse_inv_supplier'] : null, 'supplier_name' => pSQL(Tools::substr($name, 0, 128)),
            'tin' => pSQL(isset($d['tin']) ? Tools::substr($d['tin'], 0, 32) : ''), 'id_pulse_inv_grn' => !empty($d['id_pulse_inv_grn']) ? (int) $d['id_pulse_inv_grn'] : null,
            'grn_no' => pSQL(isset($d['grn_no']) ? $d['grn_no'] : ''), 'id_pulse_expense' => !empty($d['id_pulse_expense']) ? (int) $d['id_pulse_expense'] : null,
            'bill_date' => pSQL($date), 'due_date' => pSQL(date('Y-m-d', strtotime($date.' +'.$terms.' day'))), 'terms_days' => $terms,
            'subtotal' => $sub, 'vat_amount' => $vat, 'total' => $total, 'wht_rate_pct' => $whtRate, 'wht_amount' => $wht,
            'paid' => 0, 'balance' => round($total - $wht, 2), 'status' => 'approved', 'period' => pSQL(PulseAccService::period($date)),
            'note' => pSQL(isset($d['note']) ? Tools::substr($d['note'], 0, 255) : ''), 'id_employee' => PulseAccService::emp(),
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        $jLines = array();
        foreach ($prepared as $p) {
            $p['id_pulse_acc_bill'] = $id;
            Db::getInstance()->insert('pulse_acc_bill_line', $p, true);
            $jLines[] = array('account' => $p['account_code'], 'debit' => round($p['line_total'] - $p['tax_amount'], 2), 'memo' => $no.' — '.$p['description'], 'department' => $p['department'], 'cost_centre' => $p['department'], 'id_supplier' => isset($d['id_pulse_inv_supplier']) ? $d['id_pulse_inv_supplier'] : null, 'entity' => 'pulse_acc_bill', 'id_entity' => $id);
        }
        // abs(), not >0: a credit-note bill (negative VAT) or a negative WHT rate must still reach the journal,
        // otherwise the entry is short by that amount and PulseAccJournal::post refuses the whole bill
        if (abs($vat) > 0.004) { $jLines[] = array('account' => '1270', 'debit' => $vat, 'memo' => 'Input VAT '.$no, 'tax_code' => 'VAT', 'entity' => 'pulse_acc_bill', 'id_entity' => $id); }
        if (abs($wht) > 0.004) { $jLines[] = array('account' => '2230', 'credit' => $wht, 'memo' => 'WHT '.number_format($whtRate, 1).'% withheld — '.$name, 'tax_code' => 'WHT', 'entity' => 'pulse_acc_bill', 'id_entity' => $id); }
        $jLines[] = array('account' => '2110', 'credit' => round($total - $wht, 2), 'memo' => $no.' '.$name, 'id_supplier' => isset($d['id_pulse_inv_supplier']) ? $d['id_pulse_inv_supplier'] : null, 'entity' => 'pulse_acc_bill', 'id_entity' => $id);
        $idJ = PulseAccJournal::post(array('type' => 'purchase', 'source' => 'bill', 'source_ref' => 'bill:'.$id, 'business_date' => $date, 'reference' => isset($d['supplier_invoice_no']) ? $d['supplier_invoice_no'] : $no, 'memo' => 'Supplier bill '.$no.' — '.$name, 'lines' => $jLines));
        Db::getInstance()->update('pulse_acc_bill', array('id_pulse_acc_journal' => (int) $idJ), 'id_pulse_acc_bill='.(int) $id);
        if (!empty($d['id_pulse_inv_grn'])) { Db::getInstance()->update('pulse_inv_grn', array('status' => 'invoiced'), 'id_pulse_inv_grn='.(int) $d['id_pulse_inv_grn']); }
        if ($vat > 0.004) { PulseAccTax::recordVat('input', 'bill', 'bill:'.$id, array('doc_no' => isset($d['supplier_invoice_no']) && $d['supplier_invoice_no'] ? $d['supplier_invoice_no'] : $no, 'party_name' => $name, 'tin' => isset($d['tin']) ? $d['tin'] : '', 'department' => 'general', 'net_amount' => $sub, 'vat_rate' => $sub > 0 ? round($vat / $sub * 100, 3) : PulseAccService::vatPct(), 'vat_amount' => $vat, 'consumption_tax' => 0, 'business_date' => $date, 'id_journal' => $idJ)); }
        if ($wht > 0.004) { PulseAccTax::recordWht(array('direction' => 'deducted', 'party_type' => 'supplier', 'party_name' => $name, 'tin' => isset($d['tin']) ? $d['tin'] : '', 'id_party' => isset($d['id_pulse_inv_supplier']) ? $d['id_pulse_inv_supplier'] : null, 'wht_type' => isset($d['wht_type']) ? $d['wht_type'] : 'services', 'base_amount' => $sub, 'rate_pct' => $whtRate, 'amount' => $wht, 'source' => 'bill', 'source_ref' => 'bill:'.$id, 'doc_no' => $no, 'business_date' => $date, 'id_journal' => $idJ)); }
        PulseCoreService::audit('pulseaccounts', 'ap_bill', array('no' => $no, 'supplier' => $name, 'total' => $total, 'wht' => $wht), 'pulse_acc_bill', $id);
        return $id;
    }

    /**
     * Pay one or more bills. A "payment run" is simply several payments sharing a run number so the
     * remittance advice and the bank file line up.
     */
    public static function paymentRun(array $idBills, array $d = array())
    {
        $run = PulseAccService::nextNo('RUN', 4);
        $date = !empty($d['payment_date']) ? $d['payment_date'] : PulseAccService::bd();
        $bank = !empty($d['id_pulse_acc_bank_account']) ? PulseAccService::bankAccount((int) $d['id_pulse_acc_bank_account']) : null;
        $method = isset($d['method']) ? $d['method'] : 'transfer';
        $bySupplier = array();
        foreach (array_unique(array_map('intval', $idBills)) as $idBill) {
            $b = self::billRow((int) $idBill);
            if (!$b || round((float) $b['balance'], 2) <= 0.009 || $b['status'] === 'cancelled') { continue; }
            $k = (int) $b['id_pulse_inv_supplier'].'|'.$b['supplier_name'];
            if (!isset($bySupplier[$k])) { $bySupplier[$k] = array(); }
            $bySupplier[$k][] = $b;
        }
        if (!$bySupplier) { throw new PrestaShopException('No payable bills selected'); }
        $ids = array();
        foreach ($bySupplier as $k => $bills) { $ids[] = self::pay($bills, $run, $date, $method, $bank, isset($d['reference']) ? $d['reference'] : ''); }
        PulseCoreService::audit('pulseaccounts', 'ap_payment_run', array('run' => $run, 'payments' => count($ids)));
        return array('run_no' => $run, 'payments' => $ids);
    }

    /** One supplier, one payment, N bills. WHT already withheld on the bill is not deducted twice. */
    protected static function pay(array $bills, $run, $date, $method, $bank, $reference)
    {
        $first = $bills[0];
        $gross = 0;
        foreach ($bills as $b) { $gross += round((float) $b['balance'], 2); }
        $gross = round($gross, 2);
        $cash = $bank ? $bank['account_code'] : PulseAccService::mapAccount('payment_method', $method);
        if (!$cash) { throw new PrestaShopException('No cash/bank account for method "'.$method.'"'); }
        $no = PulseAccService::nextNo('PAY', 5);
        Db::getInstance()->insert('pulse_acc_payment', array(
            'payment_no' => pSQL($no), 'run_no' => pSQL($run), 'id_pulse_inv_supplier' => $first['id_pulse_inv_supplier'] ? (int) $first['id_pulse_inv_supplier'] : null,
            'supplier_name' => pSQL($first['supplier_name']), 'payment_date' => pSQL($date), 'method' => pSQL($method),
            'id_pulse_acc_bank_account' => $bank ? (int) $bank['id_pulse_acc_bank_account'] : null, 'gross_amount' => $gross, 'wht_amount' => 0, 'amount' => $gross,
            'reference' => pSQL(Tools::substr($reference, 0, 64)), 'status' => 'paid', 'period' => pSQL(PulseAccService::period($date)),
            'id_employee' => PulseAccService::emp(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        $lines = array();
        foreach ($bills as $b) {
            $amt = round((float) $b['balance'], 2);
            $lines[] = array('account' => '2110', 'debit' => $amt, 'memo' => $no.' — '.$b['bill_no'].($b['supplier_invoice_no'] ? ' / '.$b['supplier_invoice_no'] : ''), 'id_supplier' => $b['id_pulse_inv_supplier'], 'entity' => 'pulse_acc_bill', 'id_entity' => (int) $b['id_pulse_acc_bill']);
            Db::getInstance()->insert('pulse_acc_allocation', array('kind' => 'ap', 'source_type' => 'payment', 'id_source' => $id, 'id_target' => (int) $b['id_pulse_acc_bill'], 'amount' => $amt, 'id_employee' => PulseAccService::emp(), 'date_add' => date('Y-m-d H:i:s')), true);
            Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_acc_bill` SET paid=paid+'.$amt.', balance=ROUND(balance-'.$amt.',2), status=IF(ROUND(balance-'.$amt.',2)<=0.009,"paid","part_paid"), date_upd=NOW() WHERE id_pulse_acc_bill='.(int) $b['id_pulse_acc_bill']);
        }
        $lines[] = array('account' => $cash, 'credit' => $gross, 'memo' => $no.' '.$first['supplier_name'].($reference ? ' '.$reference : ''), 'id_supplier' => $first['id_pulse_inv_supplier'], 'entity' => 'pulse_acc_payment', 'id_entity' => $id);
        $idJ = PulseAccJournal::post(array('type' => 'payment', 'source' => 'ap_payment', 'source_ref' => 'pay:'.$id, 'business_date' => $date, 'reference' => $reference ? $reference : $no, 'memo' => 'Payment '.$no.' — '.$first['supplier_name'], 'lines' => $lines));
        Db::getInstance()->update('pulse_acc_payment', array('id_pulse_acc_journal' => (int) $idJ), 'id_pulse_acc_payment='.(int) $id);
        PulseCoreService::audit('pulseaccounts', 'ap_payment', array('no' => $no, 'supplier' => $first['supplier_name'], 'amount' => $gross), 'pulse_acc_payment', $id);
        return $id;
    }

    /* ---------------- reads ---------------- */

    public static function billRow($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_bill` WHERE id_pulse_acc_bill='.(int) $id); }

    public static function billFull($id)
    {
        $b = self::billRow($id);
        if ($b) {
            $b['lines'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_bill_line` WHERE id_pulse_acc_bill='.(int) $id);
            $b['payments'] = Db::getInstance()->executeS('SELECT a.*, p.payment_no, p.payment_date, p.method, p.reference FROM `'._DB_PREFIX_.'pulse_acc_allocation` a INNER JOIN `'._DB_PREFIX_.'pulse_acc_payment` p ON p.id_pulse_acc_payment=a.id_source WHERE a.kind="ap" AND a.id_target='.(int) $id.' ORDER BY a.date_add');
        }
        return $b;
    }

    public static function bills(array $f = array(), $limit = 200)
    {
        $w = array('1');
        if (!empty($f['id_pulse_inv_supplier'])) { $w[] = 'b.id_pulse_inv_supplier='.(int) $f['id_pulse_inv_supplier']; }
        if (!empty($f['status'])) { $w[] = 'b.status="'.pSQL($f['status']).'"'; }
        if (!empty($f['open'])) { $w[] = 'b.status IN ("approved","part_paid","disputed") AND b.balance>0.009'; }
        if (!empty($f['due_by'])) { $w[] = 'b.due_date<="'.pSQL($f['due_by']).'"'; }
        if (!empty($f['from'])) { $w[] = 'b.bill_date>="'.pSQL($f['from']).'"'; }
        if (!empty($f['to'])) { $w[] = 'b.bill_date<="'.pSQL($f['to']).'"'; }
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w[] = '(b.bill_no LIKE "%'.$q.'%" OR b.supplier_invoice_no LIKE "%'.$q.'%" OR b.supplier_name LIKE "%'.$q.'%")'; }
        return Db::getInstance()->executeS('SELECT b.*, DATEDIFF(CURDATE(), b.due_date) days_overdue FROM `'._DB_PREFIX_.'pulse_acc_bill` b WHERE '.implode(' AND ', $w).' ORDER BY b.due_date, b.id_pulse_acc_bill LIMIT '.(int) $limit);
    }

    public static function payments(array $f = array(), $limit = 200)
    {
        $w = array('1');
        if (!empty($f['run_no'])) { $w[] = 'p.run_no="'.pSQL($f['run_no']).'"'; }
        if (!empty($f['from'])) { $w[] = 'p.payment_date>="'.pSQL($f['from']).'"'; }
        if (!empty($f['to'])) { $w[] = 'p.payment_date<="'.pSQL($f['to']).'"'; }
        return Db::getInstance()->executeS('SELECT p.* FROM `'._DB_PREFIX_.'pulse_acc_payment` p WHERE '.implode(' AND ', $w).' ORDER BY p.payment_date DESC, p.id_pulse_acc_payment DESC LIMIT '.(int) $limit);
    }

    public static function ageing($asOf = null)
    {
        $asOf = $asOf ? $asOf : date('Y-m-d');
        return Db::getInstance()->executeS('SELECT b.id_pulse_inv_supplier, b.supplier_name, COUNT(*) bills, ROUND(SUM(b.balance),2) total,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date)<=0, b.balance,0)),2) b_current,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date) BETWEEN 1 AND 30, b.balance,0)),2) b_30,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date) BETWEEN 31 AND 60, b.balance,0)),2) b_60,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date) BETWEEN 61 AND 90, b.balance,0)),2) b_90,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date) BETWEEN 91 AND 120, b.balance,0)),2) b_120,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date)>120, b.balance,0)),2) b_over,
            MAX(DATEDIFF("'.pSQL($asOf).'", b.due_date)) oldest_days
            FROM `'._DB_PREFIX_.'pulse_acc_bill` b WHERE b.status IN ("approved","part_paid","disputed") AND b.balance>0.009 AND b.bill_date<="'.pSQL($asOf).'"
            GROUP BY b.id_pulse_inv_supplier, b.supplier_name ORDER BY total DESC');
    }

    public static function ageingSummary($asOf = null)
    {
        $asOf = $asOf ? $asOf : date('Y-m-d');
        $r = Db::getInstance()->getRow('SELECT ROUND(COALESCE(SUM(b.balance),0),2) total,
            ROUND(COALESCE(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date)<=0, b.balance,0)),0),2) b_current,
            ROUND(COALESCE(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date) BETWEEN 1 AND 30, b.balance,0)),0),2) b_30,
            ROUND(COALESCE(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date) BETWEEN 31 AND 60, b.balance,0)),0),2) b_60,
            ROUND(COALESCE(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date) BETWEEN 61 AND 90, b.balance,0)),0),2) b_90,
            ROUND(COALESCE(SUM(IF(DATEDIFF("'.pSQL($asOf).'", b.due_date)>90, b.balance,0)),0),2) b_over
            FROM `'._DB_PREFIX_.'pulse_acc_bill` b WHERE b.status IN ("approved","part_paid","disputed") AND b.balance>0.009');
        return $r ? $r : array('total' => 0, 'b_current' => 0, 'b_30' => 0, 'b_60' => 0, 'b_90' => 0, 'b_over' => 0);
    }

    /** Printable remittance advice for a payment run — what the supplier needs to apply the transfer. */
    public static function remittance($runNo)
    {
        $payments = self::payments(array('run_no' => $runNo), 500);
        if (!$payments) { throw new PrestaShopException('Unknown payment run '.$runNo); }
        $out = array('run_no' => $runNo, 'hotel' => Configuration::get('PULSE_ACC_HOTEL_NAME'), 'address' => Configuration::get('PULSE_ACC_HOTEL_ADDRESS'), 'tin' => Configuration::get('PULSE_ACC_HOTEL_TIN'), 'date' => $payments[0]['payment_date'], 'total' => 0, 'suppliers' => array());
        foreach ($payments as $p) {
            $bills = Db::getInstance()->executeS('SELECT a.amount, b.bill_no, b.supplier_invoice_no, b.bill_date, b.due_date, b.total, b.wht_amount FROM `'._DB_PREFIX_.'pulse_acc_allocation` a INNER JOIN `'._DB_PREFIX_.'pulse_acc_bill` b ON b.id_pulse_acc_bill=a.id_target WHERE a.kind="ap" AND a.source_type="payment" AND a.id_source='.(int) $p['id_pulse_acc_payment']);
            $out['suppliers'][] = array('payment' => $p, 'bills' => $bills, 'wht' => round((float) array_sum(array_map(function ($b) { return (float) $b['wht_amount']; }, $bills)), 2));
            $out['total'] += (float) $p['amount'];
        }
        $out['total'] = round($out['total'], 2);
        return $out;
    }

    public static function disputeBill($id, $note)
    {
        Db::getInstance()->update('pulse_acc_bill', array('status' => 'disputed', 'note' => pSQL(Tools::substr($note, 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_bill='.(int) $id);
        PulseCoreService::audit('pulseaccounts', 'ap_dispute', array('note' => $note), 'pulse_acc_bill', (int) $id);
        return true;
    }

    /** Cancel a bill by reversing its journal — the posted entry itself is never touched. */
    public static function cancelBill($id, $reason)
    {
        $b = self::billRow($id);
        if (!$b) { throw new PrestaShopException('Unknown bill'); }
        if (round((float) $b['paid'], 2) > 0.009) { throw new PrestaShopException('Bill '.$b['bill_no'].' is part paid — reverse the payment first'); }
        if ($b['id_pulse_acc_journal']) { PulseAccJournal::reverse((int) $b['id_pulse_acc_journal'], $reason); }
        Db::getInstance()->update('pulse_acc_bill', array('status' => 'cancelled', 'balance' => 0, 'note' => pSQL(Tools::substr('Cancelled: '.$reason, 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_bill='.(int) $id);
        return true;
    }
}
