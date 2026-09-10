<?php
/**
 * City ledger / accounts receivable: invoice a company folio, age the debt, take receipts and allocate
 * them across invoices, raise credit notes, hold a stop list against the credit limit and print dunning letters.
 *
 * The GL side of AR is already there before an invoice exists: settling a guest folio to the city ledger
 * posts Dr 1220 / Cr 1210 through the folio rules. An invoice is therefore a *document* over balances the
 * ledger already carries — it drives ageing, allocation and e-invoicing, and it does not re-post revenue.
 * Receipts and credit notes do post, because they move money and reverse revenue respectively.
 */
class PulseAccAr
{
    public static function companies($activeOnly = true)
    {
        if (!PulseAccService::fd()) { return array(); }
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_company` WHERE 1'.($activeOnly ? ' AND active=1' : '').' ORDER BY name');
    }

    public static function company($id) { return PulseAccService::fd() ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_company` WHERE id_pulse_company='.(int) $id) : null; }

    /** Folio lines on a company's ledger folios that are not yet on an invoice. */
    public static function billable($idCompany, $to = null)
    {
        if (!PulseAccService::fd()) { return array(); }
        $to = $to ? $to : PulseAccService::bd();
        return Db::getInstance()->executeS('SELECT l.*, f.folio_no FROM `'._DB_PREFIX_.'pulse_folio_line` l INNER JOIN `'._DB_PREFIX_.'pulse_folio` f ON f.id_pulse_folio=l.id_pulse_folio
            WHERE f.id_pulse_company='.(int) $idCompany.' AND f.type="company" AND l.voided=0 AND l.is_payment=0 AND l.business_date<="'.pSQL($to).'"
            AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_acc_invoice_line` il INNER JOIN `'._DB_PREFIX_.'pulse_acc_invoice` i ON i.id_pulse_acc_invoice=il.id_pulse_acc_invoice AND i.status<>"cancelled" WHERE il.id_pulse_folio_line=l.id_pulse_folio_line)
            ORDER BY l.business_date, l.id_pulse_folio_line');
    }

    /**
     * Raise an invoice for a company. $idLines limits it to specific folio lines; leave empty to bill
     * everything outstanding up to $to. Amounts come straight off the folio, tax inclusive.
     */
    public static function invoiceCompany($idCompany, array $idLines = array(), $to = null, $note = '')
    {
        $co = self::company($idCompany);
        if (!$co) { throw new PrestaShopException('Unknown company'); }
        $rows = self::billable($idCompany, $to);
        if ($idLines) { $keep = array_map('intval', $idLines); $rows = array_filter($rows, function ($r) use ($keep) { return in_array((int) $r['id_pulse_folio_line'], $keep); }); }
        if (!$rows) { throw new PrestaShopException('Nothing to invoice for '.$co['name']); }
        $date = $to ? $to : PulseAccService::bd();
        $terms = (int) ($co['payment_terms_days'] ? $co['payment_terms_days'] : Configuration::get('PULSE_ACC_AR_TERMS_DAYS'));
        $no = PulseAccService::nextNo('INV', 5);
        $sub = 0; $tax = 0; $idFolio = null;
        foreach ($rows as $r) { $amt = round((float) $r['amount_tax_incl'], 2); $rate = (float) $r['tax_rate']; $net = $rate > 0 ? round($amt / (1 + $rate / 100), 2) : $amt; $sub += $net; $tax += round($amt - $net, 2); $idFolio = (int) $r['id_pulse_folio']; }
        $total = round($sub + $tax, 2);
        Db::getInstance()->insert('pulse_acc_invoice', array(
            'invoice_no' => pSQL($no), 'type' => 'invoice', 'id_pulse_company' => (int) $idCompany, 'company_name' => pSQL($co['name']), 'tin' => pSQL($co['tin']),
            'email' => pSQL($co['email']), 'address' => pSQL($co['address']), 'id_pulse_folio' => $idFolio, 'invoice_date' => pSQL($date),
            'due_date' => pSQL(date('Y-m-d', strtotime($date.' +'.$terms.' day'))), 'terms_days' => $terms, 'currency' => pSQL(PulseAccService::currency()),
            'subtotal' => round($sub, 2), 'tax_amount' => round($tax, 2), 'total' => $total, 'balance' => $total, 'status' => 'issued',
            'period' => pSQL(PulseAccService::period($date)), 'note' => pSQL(Tools::substr($note, 0, 255)), 'id_employee' => PulseAccService::emp(),
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        foreach ($rows as $r) {
            $amt = round((float) $r['amount_tax_incl'], 2); $rate = (float) $r['tax_rate']; $net = $rate > 0 ? round($amt / (1 + $rate / 100), 2) : $amt;
            Db::getInstance()->insert('pulse_acc_invoice_line', array(
                'id_pulse_acc_invoice' => $id, 'description' => pSQL(Tools::substr($r['description'], 0, 255)), 'department' => pSQL($r['department']),
                'account_code' => pSQL((string) PulseAccService::mapAccount('department', $r['department'])), 'qty' => (float) $r['qty'], 'unit_price' => $net,
                'tax_rate' => $rate, 'tax_amount' => round($amt - $net, 2), 'line_total' => $amt, 'id_pulse_folio_line' => (int) $r['id_pulse_folio_line'], 'business_date' => pSQL($r['business_date']),
            ), true);
        }
        PulseCoreService::audit('pulseaccounts', 'ar_invoice', array('no' => $no, 'company' => $co['name'], 'total' => $total), 'pulse_acc_invoice', $id);
        if (Configuration::get('PULSE_ACC_EINV_ENABLED') && $total >= (float) Configuration::get('PULSE_ACC_EINV_MIN_TOTAL')) { PulseAccTax::queueEinvoice($id); }
        return $id;
    }

    /** A manual invoice (venue hire, a commission bill, anything not on a folio). */
    public static function invoiceManual($idCompany, array $lines, $date = null, $note = '', $name = null)
    {
        $co = self::company($idCompany);
        $date = $date ? $date : PulseAccService::bd();
        $terms = (int) ($co && $co['payment_terms_days'] ? $co['payment_terms_days'] : Configuration::get('PULSE_ACC_AR_TERMS_DAYS'));
        $no = PulseAccService::nextNo('INV', 5);
        $prepared = array(); $sub = 0; $tax = 0;
        foreach ($lines as $l) {
            $qty = (float) (isset($l['qty']) ? $l['qty'] : 1); $price = round((float) (isset($l['unit_price']) ? $l['unit_price'] : 0), 2);
            if ($qty <= 0 || abs($price) < 0.005) { continue; }
            $rate = (float) (isset($l['tax_rate']) ? $l['tax_rate'] : PulseAccService::vatPct());
            $net = round($qty * $price, 2); $t = round($net * $rate / 100, 2);
            $prepared[] = array('description' => pSQL(Tools::substr(isset($l['description']) ? $l['description'] : 'Charge', 0, 255)), 'department' => pSQL(isset($l['department']) ? $l['department'] : 'general'),
                'account_code' => pSQL(isset($l['account_code']) ? $l['account_code'] : '4400'), 'qty' => $qty, 'unit_price' => $price, 'tax_rate' => $rate, 'tax_amount' => $t, 'line_total' => round($net + $t, 2), 'business_date' => pSQL($date));
            $sub += $net; $tax += $t;
        }
        if (!$prepared) { throw new PrestaShopException('Invoice has no lines'); }
        $total = round($sub + $tax, 2);
        Db::getInstance()->insert('pulse_acc_invoice', array(
            'invoice_no' => pSQL($no), 'type' => 'invoice', 'id_pulse_company' => $idCompany ? (int) $idCompany : null, 'company_name' => pSQL($co ? $co['name'] : ($name ? $name : 'Cash sale')),
            'tin' => pSQL($co ? $co['tin'] : ''), 'email' => pSQL($co ? $co['email'] : ''), 'address' => pSQL($co ? $co['address'] : ''),
            'invoice_date' => pSQL($date), 'due_date' => pSQL(date('Y-m-d', strtotime($date.' +'.$terms.' day'))), 'terms_days' => $terms, 'currency' => pSQL(PulseAccService::currency()),
            'subtotal' => round($sub, 2), 'tax_amount' => round($tax, 2), 'total' => $total, 'balance' => $total, 'status' => 'issued',
            'period' => pSQL(PulseAccService::period($date)), 'note' => pSQL(Tools::substr($note, 0, 255)), 'id_employee' => PulseAccService::emp(),
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        $jLines = array(array('account' => '1220', 'debit' => $total, 'memo' => $no.' '.($co ? $co['name'] : ''), 'id_pulse_company' => $idCompany, 'entity' => 'pulse_acc_invoice', 'id_entity' => $id));
        foreach ($prepared as $p) {
            $p['id_pulse_acc_invoice'] = $id;
            Db::getInstance()->insert('pulse_acc_invoice_line', $p, true);
            $jLines[] = array('account' => $p['account_code'], 'credit' => round($p['line_total'] - $p['tax_amount'], 2), 'memo' => $p['description'], 'department' => $p['department'], 'cost_centre' => $p['department'], 'entity' => 'pulse_acc_invoice', 'id_entity' => $id);
        }
        if ($tax > 0.004) { $jLines[] = array('account' => '2210', 'credit' => round($tax, 2), 'memo' => 'VAT '.$no, 'tax_code' => 'VAT'); }
        $idJ = PulseAccJournal::post(array('type' => 'sales', 'source' => 'ar_receipt', 'source_ref' => 'inv:'.$id, 'business_date' => $date, 'reference' => $no, 'memo' => 'Invoice '.$no, 'lines' => $jLines));
        Db::getInstance()->update('pulse_acc_invoice', array('id_pulse_acc_journal' => (int) $idJ), 'id_pulse_acc_invoice='.(int) $id);
        if ($tax > 0.004) { PulseAccTax::recordVat('output', 'invoice', 'inv:'.$id, array('doc_no' => $no, 'party_name' => $co ? $co['name'] : $name, 'department' => 'general', 'net_amount' => $sub, 'vat_rate' => PulseAccService::vatPct(), 'vat_amount' => $tax, 'consumption_tax' => 0, 'business_date' => $date, 'id_journal' => $idJ)); }
        PulseCoreService::audit('pulseaccounts', 'ar_invoice_manual', array('no' => $no, 'total' => $total), 'pulse_acc_invoice', $id);
        if (Configuration::get('PULSE_ACC_EINV_ENABLED') && $total >= (float) Configuration::get('PULSE_ACC_EINV_MIN_TOTAL')) { PulseAccTax::queueEinvoice($id); }
        return $id;
    }

    /** Credit note against an invoice: revenue allowance Dr, VAT Dr, city ledger Cr. */
    public static function creditNote($idInvoice, $amount, $reason, $date = null)
    {
        $inv = self::invoice($idInvoice);
        if (!$inv) { throw new PrestaShopException('Unknown invoice'); }
        $amount = round((float) $amount, 2);
        if ($amount <= 0) { throw new PrestaShopException('Credit amount must be positive'); }
        if ($amount > round((float) $inv['balance'], 2) + 0.009) { throw new PrestaShopException('Credit of '.number_format($amount, 2).' is more than the '.number_format($inv['balance'], 2).' outstanding on '.$inv['invoice_no']); }
        $date = $date ? $date : PulseAccService::bd();
        $rate = (float) $inv['total'] > 0 ? round((float) $inv['tax_amount'] / ((float) $inv['total'] - (float) $inv['tax_amount']) * 100, 3) : 0;
        $net = $rate > 0 ? round($amount / (1 + $rate / 100), 2) : $amount;
        $tax = round($amount - $net, 2);
        $no = PulseAccService::nextNo('CN', 5);
        Db::getInstance()->insert('pulse_acc_invoice', array(
            'invoice_no' => pSQL($no), 'type' => 'credit_note', 'id_pulse_company' => $inv['id_pulse_company'] ? (int) $inv['id_pulse_company'] : null, 'company_name' => pSQL($inv['company_name']),
            'tin' => pSQL($inv['tin']), 'email' => pSQL($inv['email']), 'id_credited_invoice' => (int) $idInvoice, 'invoice_date' => pSQL($date), 'due_date' => pSQL($date), 'terms_days' => 0,
            'currency' => pSQL($inv['currency']), 'subtotal' => -$net, 'tax_amount' => -$tax, 'total' => -$amount, 'balance' => 0, 'status' => 'issued',
            'period' => pSQL(PulseAccService::period($date)), 'note' => pSQL(Tools::substr($reason, 0, 255)), 'id_employee' => PulseAccService::emp(),
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        Db::getInstance()->insert('pulse_acc_invoice_line', array('id_pulse_acc_invoice' => $id, 'description' => pSQL('Credit against '.$inv['invoice_no'].' — '.$reason), 'department' => 'general', 'account_code' => '4190', 'qty' => 1, 'unit_price' => -$net, 'tax_rate' => $rate, 'tax_amount' => -$tax, 'line_total' => -$amount, 'business_date' => pSQL($date)), true);
        $lines = array(array('account' => '4190', 'debit' => $net, 'memo' => $no.' — '.$reason, 'cost_centre' => 'general', 'entity' => 'pulse_acc_invoice', 'id_entity' => $id));
        if ($tax > 0.004) { $lines[] = array('account' => '2210', 'debit' => $tax, 'memo' => 'VAT credit '.$no, 'tax_code' => 'VAT'); }
        $lines[] = array('account' => '1220', 'credit' => $amount, 'memo' => $no.' '.$inv['company_name'], 'id_pulse_company' => $inv['id_pulse_company'], 'entity' => 'pulse_acc_invoice', 'id_entity' => $id);
        $idJ = PulseAccJournal::post(array('type' => 'adjustment', 'source' => 'ar_receipt', 'source_ref' => 'cn:'.$id, 'business_date' => $date, 'reference' => $no, 'memo' => 'Credit note '.$no.' against '.$inv['invoice_no'], 'lines' => $lines));
        Db::getInstance()->update('pulse_acc_invoice', array('id_pulse_acc_journal' => (int) $idJ), 'id_pulse_acc_invoice='.(int) $id);
        Db::getInstance()->insert('pulse_acc_allocation', array('kind' => 'ar', 'source_type' => 'credit_note', 'id_source' => $id, 'id_target' => (int) $idInvoice, 'amount' => $amount, 'id_employee' => PulseAccService::emp(), 'date_add' => date('Y-m-d H:i:s')), true);
        self::recalcInvoice((int) $idInvoice);
        if ($inv['id_pulse_company'] && PulseAccService::fd()) { $co = new PulseCompany((int) $inv['id_pulse_company']); if (Validate::isLoadedObject($co)) { $co->ledger_balance = round((float) $co->ledger_balance - $amount, 2); $co->update(); } }
        if ($tax > 0.004) { PulseAccTax::recordVat('output', 'credit_note', 'cn:'.$id, array('doc_no' => $no, 'party_name' => $inv['company_name'], 'department' => 'general', 'net_amount' => -$net, 'vat_rate' => $rate, 'vat_amount' => -$tax, 'consumption_tax' => 0, 'business_date' => $date, 'id_journal' => $idJ)); }
        PulseCoreService::audit('pulseaccounts', 'ar_credit_note', array('no' => $no, 'against' => $inv['invoice_no'], 'amount' => $amount), 'pulse_acc_invoice', $id);
        return $id;
    }

    /**
     * Money in from a company. WHT the customer withheld is recorded as a receivable credit so the
     * invoice still clears in full — the classic Nigerian "they paid 95%" problem.
     */
    public static function receipt(array $d)
    {
        $idCompany = (int) (isset($d['id_pulse_company']) ? $d['id_pulse_company'] : 0);
        $co = self::company($idCompany);
        $amount = round((float) (isset($d['amount']) ? $d['amount'] : 0), 2);
        $wht = round((float) (isset($d['wht_amount']) ? $d['wht_amount'] : 0), 2);
        if ($amount <= 0 && $wht <= 0) { throw new PrestaShopException('Receipt amount must be positive'); }
        $date = !empty($d['receipt_date']) ? $d['receipt_date'] : PulseAccService::bd();
        $method = isset($d['method']) ? $d['method'] : 'transfer';
        $bank = !empty($d['id_pulse_acc_bank_account']) ? PulseAccService::bankAccount((int) $d['id_pulse_acc_bank_account']) : null;
        $cash = $bank ? $bank['account_code'] : PulseAccService::mapAccount('payment_method', $method);
        if (!$cash) { throw new PrestaShopException('No cash/bank account for method "'.$method.'"'); }
        $no = PulseAccService::nextNo('RCT', 5);
        Db::getInstance()->insert('pulse_acc_receipt', array(
            'receipt_no' => pSQL($no), 'id_pulse_company' => $idCompany ? $idCompany : null, 'company_name' => pSQL($co ? $co['name'] : (isset($d['company_name']) ? $d['company_name'] : 'Customer')),
            'receipt_date' => pSQL($date), 'method' => pSQL($method), 'id_pulse_acc_bank_account' => $bank ? (int) $bank['id_pulse_acc_bank_account'] : null,
            'amount' => $amount, 'wht_amount' => $wht, 'wht_cert_no' => pSQL(isset($d['wht_cert_no']) ? Tools::substr($d['wht_cert_no'], 0, 32) : ''),
            'allocated' => 0, 'unallocated' => round($amount + $wht, 2), 'reference' => pSQL(isset($d['reference']) ? Tools::substr($d['reference'], 0, 64) : ''),
            'note' => pSQL(isset($d['note']) ? Tools::substr($d['note'], 0, 255) : ''), 'status' => 'posted', 'period' => pSQL(PulseAccService::period($date)),
            'id_employee' => PulseAccService::emp(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        $lines = array(array('account' => $cash, 'debit' => $amount, 'memo' => $no.' '.($co ? $co['name'] : ''), 'id_pulse_company' => $idCompany, 'entity' => 'pulse_acc_receipt', 'id_entity' => $id));
        if ($wht > 0.004) { $lines[] = array('account' => '1260', 'debit' => $wht, 'memo' => 'WHT credit note '.(isset($d['wht_cert_no']) ? $d['wht_cert_no'] : ''), 'tax_code' => 'WHT', 'id_pulse_company' => $idCompany, 'entity' => 'pulse_acc_receipt', 'id_entity' => $id); }
        $lines[] = array('account' => '1220', 'credit' => round($amount + $wht, 2), 'memo' => $no.' '.($co ? $co['name'] : ''), 'id_pulse_company' => $idCompany, 'entity' => 'pulse_acc_receipt', 'id_entity' => $id);
        $idJ = PulseAccJournal::post(array('type' => 'receipt', 'source' => 'ar_receipt', 'source_ref' => 'rct:'.$id, 'business_date' => $date, 'reference' => $no, 'memo' => 'Receipt '.$no, 'lines' => $lines));
        Db::getInstance()->update('pulse_acc_receipt', array('id_pulse_acc_journal' => (int) $idJ), 'id_pulse_acc_receipt='.(int) $id);
        if ($wht > 0.004) {
            PulseAccTax::recordWht(array('direction' => 'suffered', 'party_type' => 'company', 'party_name' => $co ? $co['name'] : 'Customer', 'tin' => $co ? $co['tin'] : '', 'id_party' => $idCompany,
                'wht_type' => 'services', 'base_amount' => round(($amount + $wht), 2), 'rate_pct' => $amount + $wht > 0 ? round($wht / ($amount + $wht) * 100, 3) : 0, 'amount' => $wht,
                'source' => 'ar_receipt', 'source_ref' => 'rct:'.$id, 'doc_no' => isset($d['wht_cert_no']) ? $d['wht_cert_no'] : $no, 'business_date' => $date, 'id_journal' => $idJ));
        }
        if ($co && PulseAccService::fd()) { $c = new PulseCompany($idCompany); if (Validate::isLoadedObject($c)) { $c->ledger_balance = round((float) $c->ledger_balance - ($amount + $wht), 2); $c->update(); } }
        // allocate: explicit picks first, otherwise oldest invoice first
        if (!empty($d['allocate']) && is_array($d['allocate'])) { foreach ($d['allocate'] as $idInv => $amt) { if ((float) $amt > 0) { self::allocate($id, (int) $idInv, (float) $amt); } } }
        elseif (!empty($d['auto_allocate'])) { self::autoAllocate($id); }
        PulseCoreService::audit('pulseaccounts', 'ar_receipt', array('no' => $no, 'amount' => $amount, 'wht' => $wht), 'pulse_acc_receipt', $id);
        return $id;
    }

    /** Allocate part of a receipt (or credit note) to one invoice. Part payments across many invoices are just repeated calls. */
    public static function allocate($idReceipt, $idInvoice, $amount)
    {
        $r = self::receiptRow($idReceipt);
        $inv = self::invoice($idInvoice);
        if (!$r || !$inv) { throw new PrestaShopException('Unknown receipt or invoice'); }
        $amount = round((float) $amount, 2);
        if ($amount <= 0) { throw new PrestaShopException('Allocation must be positive'); }
        if ($amount > round((float) $r['unallocated'], 2) + 0.009) { throw new PrestaShopException('Only '.number_format($r['unallocated'], 2).' is unallocated on '.$r['receipt_no']); }
        if ($amount > round((float) $inv['balance'], 2) + 0.009) { throw new PrestaShopException('Invoice '.$inv['invoice_no'].' only has '.number_format($inv['balance'], 2).' outstanding'); }
        Db::getInstance()->insert('pulse_acc_allocation', array('kind' => 'ar', 'source_type' => 'receipt', 'id_source' => (int) $idReceipt, 'id_target' => (int) $idInvoice, 'amount' => $amount, 'id_employee' => PulseAccService::emp(), 'date_add' => date('Y-m-d H:i:s')), true);
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_acc_receipt` SET allocated=allocated+'.$amount.', unallocated=unallocated-'.$amount.', date_upd=NOW() WHERE id_pulse_acc_receipt='.(int) $idReceipt);
        self::recalcInvoice((int) $idInvoice);
        return true;
    }

    public static function unallocate($idAllocation)
    {
        $a = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_allocation` WHERE id_pulse_acc_allocation='.(int) $idAllocation);
        if (!$a) { return false; }
        Db::getInstance()->delete('pulse_acc_allocation', 'id_pulse_acc_allocation='.(int) $idAllocation);
        if ($a['source_type'] === 'receipt') { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_acc_receipt` SET allocated=allocated-'.(float) $a['amount'].', unallocated=unallocated+'.(float) $a['amount'].', date_upd=NOW() WHERE id_pulse_acc_receipt='.(int) $a['id_source']); }
        self::recalcInvoice((int) $a['id_target']);
        return true;
    }

    /** Oldest invoice first — the way a bank transfer with no remittance advice actually gets applied. */
    public static function autoAllocate($idReceipt)
    {
        $r = self::receiptRow($idReceipt);
        if (!$r) { return 0; }
        $left = round((float) $r['unallocated'], 2); $n = 0;
        foreach (self::openInvoices((int) $r['id_pulse_company']) as $inv) {
            if ($left < 0.005) { break; }
            $take = min($left, round((float) $inv['balance'], 2));
            if ($take < 0.005) { continue; }
            self::allocate($idReceipt, (int) $inv['id_pulse_acc_invoice'], $take);
            $left = round($left - $take, 2); $n++;
        }
        return $n;
    }

    public static function recalcInvoice($idInvoice)
    {
        $alloc = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(amount),0) FROM `'._DB_PREFIX_.'pulse_acc_allocation` WHERE kind="ar" AND id_target='.(int) $idInvoice), 2);
        $inv = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_invoice` WHERE id_pulse_acc_invoice='.(int) $idInvoice);
        if (!$inv) { return false; }
        $bal = round((float) $inv['total'] - $alloc, 2);
        $status = $inv['status'];
        if (!in_array($status, array('cancelled', 'written_off', 'draft'))) { $status = $bal <= 0.009 ? 'paid' : ($alloc > 0.009 ? 'part_paid' : 'issued'); }
        Db::getInstance()->update('pulse_acc_invoice', array('allocated' => $alloc, 'balance' => $bal, 'status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_invoice='.(int) $idInvoice);
        return true;
    }

    /** Write an uncollectable invoice off to bad debts. */
    public static function writeOff($idInvoice, $reason, $date = null)
    {
        $inv = self::invoice($idInvoice);
        if (!$inv) { throw new PrestaShopException('Unknown invoice'); }
        $bal = round((float) $inv['balance'], 2);
        if ($bal <= 0.009) { throw new PrestaShopException('Nothing outstanding on '.$inv['invoice_no']); }
        $date = $date ? $date : PulseAccService::bd();
        $idJ = PulseAccJournal::post(array('type' => 'adjustment', 'source' => 'ar_receipt', 'source_ref' => 'wo:'.(int) $idInvoice, 'business_date' => $date, 'reference' => $inv['invoice_no'], 'memo' => 'Bad debt write-off '.$inv['invoice_no'].' — '.$reason, 'lines' => array(
            array('account' => '7190', 'debit' => $bal, 'memo' => 'Bad debt '.$inv['invoice_no'].' '.$inv['company_name'], 'cost_centre' => 'admin'),
            array('account' => '1220', 'credit' => $bal, 'memo' => 'Write off '.$inv['invoice_no'], 'id_pulse_company' => $inv['id_pulse_company'], 'entity' => 'pulse_acc_invoice', 'id_entity' => (int) $idInvoice),
        )));
        Db::getInstance()->update('pulse_acc_invoice', array('status' => 'written_off', 'balance' => 0, 'note' => pSQL(Tools::substr('Written off: '.$reason, 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_invoice='.(int) $idInvoice);
        if ($inv['id_pulse_company'] && PulseAccService::fd()) { $co = new PulseCompany((int) $inv['id_pulse_company']); if (Validate::isLoadedObject($co)) { $co->ledger_balance = round((float) $co->ledger_balance - $bal, 2); $co->update(); } }
        PulseCoreService::audit('pulseaccounts', 'ar_write_off', array('invoice' => $inv['invoice_no'], 'amount' => $bal, 'reason' => $reason), 'pulse_acc_invoice', (int) $idInvoice);
        return $idJ;
    }

    /* ---------------- reads ---------------- */

    public static function invoice($id)
    {
        $i = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_invoice` WHERE id_pulse_acc_invoice='.(int) $id);
        if ($i) {
            $i['lines'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_invoice_line` WHERE id_pulse_acc_invoice='.(int) $id.' ORDER BY business_date, id_pulse_acc_invoice_line');
            $i['allocations'] = Db::getInstance()->executeS('SELECT a.*, r.receipt_no, r.receipt_date, r.method, c.invoice_no credit_no FROM `'._DB_PREFIX_.'pulse_acc_allocation` a LEFT JOIN `'._DB_PREFIX_.'pulse_acc_receipt` r ON r.id_pulse_acc_receipt=a.id_source AND a.source_type="receipt" LEFT JOIN `'._DB_PREFIX_.'pulse_acc_invoice` c ON c.id_pulse_acc_invoice=a.id_source AND a.source_type="credit_note" WHERE a.kind="ar" AND a.id_target='.(int) $id.' ORDER BY a.date_add');
            $i['einvoice'] = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_einvoice` WHERE id_pulse_acc_invoice='.(int) $id);
        }
        return $i;
    }

    public static function invoices(array $f = array(), $limit = 200)
    {
        $w = array('1');
        if (!empty($f['id_pulse_company'])) { $w[] = 'i.id_pulse_company='.(int) $f['id_pulse_company']; }
        if (!empty($f['status'])) { $w[] = 'i.status="'.pSQL($f['status']).'"'; }
        if (!empty($f['from'])) { $w[] = 'i.invoice_date>="'.pSQL($f['from']).'"'; }
        if (!empty($f['to'])) { $w[] = 'i.invoice_date<="'.pSQL($f['to']).'"'; }
        if (!empty($f['open'])) { $w[] = 'i.status IN ("issued","part_paid")'; }
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w[] = '(i.invoice_no LIKE "%'.$q.'%" OR i.company_name LIKE "%'.$q.'%")'; }
        return Db::getInstance()->executeS('SELECT i.*, DATEDIFF(CURDATE(), i.due_date) days_overdue FROM `'._DB_PREFIX_.'pulse_acc_invoice` i WHERE '.implode(' AND ', $w).' ORDER BY i.invoice_date DESC, i.id_pulse_acc_invoice DESC LIMIT '.(int) $limit);
    }

    public static function openInvoices($idCompany) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_invoice` WHERE id_pulse_company='.(int) $idCompany.' AND type="invoice" AND status IN ("issued","part_paid") AND balance>0.009 ORDER BY due_date, id_pulse_acc_invoice'); }
    public static function receiptRow($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_receipt` WHERE id_pulse_acc_receipt='.(int) $id); }
    public static function receipts(array $f = array(), $limit = 200)
    {
        $w = array('1');
        if (!empty($f['id_pulse_company'])) { $w[] = 'r.id_pulse_company='.(int) $f['id_pulse_company']; }
        if (!empty($f['from'])) { $w[] = 'r.receipt_date>="'.pSQL($f['from']).'"'; }
        if (!empty($f['to'])) { $w[] = 'r.receipt_date<="'.pSQL($f['to']).'"'; }
        if (!empty($f['unallocated'])) { $w[] = 'r.unallocated>0.009'; }
        return Db::getInstance()->executeS('SELECT r.* FROM `'._DB_PREFIX_.'pulse_acc_receipt` r WHERE '.implode(' AND ', $w).' ORDER BY r.receipt_date DESC, r.id_pulse_acc_receipt DESC LIMIT '.(int) $limit);
    }

    /** Ageing by company, one indexed pass over open invoices. */
    public static function ageing($asOf = null)
    {
        $asOf = $asOf ? $asOf : date('Y-m-d');
        return Db::getInstance()->executeS('SELECT i.id_pulse_company, i.company_name, COUNT(*) invoices, ROUND(SUM(i.balance),2) total,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date)<=0, i.balance,0)),2) b_current,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date) BETWEEN 1 AND 30, i.balance,0)),2) b_30,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date) BETWEEN 31 AND 60, i.balance,0)),2) b_60,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date) BETWEEN 61 AND 90, i.balance,0)),2) b_90,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date) BETWEEN 91 AND 120, i.balance,0)),2) b_120,
            ROUND(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date)>120, i.balance,0)),2) b_over,
            MAX(DATEDIFF("'.pSQL($asOf).'", i.due_date)) oldest_days
            FROM `'._DB_PREFIX_.'pulse_acc_invoice` i WHERE i.type="invoice" AND i.status IN ("issued","part_paid") AND i.balance>0.009 AND i.invoice_date<="'.pSQL($asOf).'"
            GROUP BY i.id_pulse_company, i.company_name ORDER BY total DESC');
    }

    public static function ageingSummary($asOf = null)
    {
        $asOf = $asOf ? $asOf : date('Y-m-d');
        $r = Db::getInstance()->getRow('SELECT ROUND(COALESCE(SUM(i.balance),0),2) total,
            ROUND(COALESCE(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date)<=0, i.balance,0)),0),2) b_current,
            ROUND(COALESCE(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date) BETWEEN 1 AND 30, i.balance,0)),0),2) b_30,
            ROUND(COALESCE(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date) BETWEEN 31 AND 60, i.balance,0)),0),2) b_60,
            ROUND(COALESCE(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date) BETWEEN 61 AND 90, i.balance,0)),0),2) b_90,
            ROUND(COALESCE(SUM(IF(DATEDIFF("'.pSQL($asOf).'", i.due_date)>90, i.balance,0)),0),2) b_over
            FROM `'._DB_PREFIX_.'pulse_acc_invoice` i WHERE i.type="invoice" AND i.status IN ("issued","part_paid") AND i.balance>0.009');
        return $r ? $r : array('total' => 0, 'b_current' => 0, 'b_30' => 0, 'b_60' => 0, 'b_90' => 0, 'b_over' => 0);
    }

    /** Statement of account: invoices, credit notes and receipts with a running balance. */
    public static function statement($idCompany, $from = null, $to = null)
    {
        $from = $from ? $from : date('Y-m-01', strtotime('-3 month'));
        $to = $to ? $to : date('Y-m-d');
        $rows = Db::getInstance()->executeS('SELECT invoice_date d, invoice_no ref, IF(type="credit_note","Credit note","Invoice") kind, note memo, total debit, 0 credit FROM `'._DB_PREFIX_.'pulse_acc_invoice` WHERE id_pulse_company='.(int) $idCompany.' AND status<>"cancelled" AND invoice_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"
            UNION ALL SELECT receipt_date d, receipt_no ref, "Receipt" kind, CONCAT(method," ",reference) memo, 0 debit, amount+wht_amount credit FROM `'._DB_PREFIX_.'pulse_acc_receipt` WHERE id_pulse_company='.(int) $idCompany.' AND status="posted" AND receipt_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"
            ORDER BY d, ref');
        $opening = round((float) Db::getInstance()->getValue('SELECT COALESCE((SELECT SUM(total) FROM `'._DB_PREFIX_.'pulse_acc_invoice` WHERE id_pulse_company='.(int) $idCompany.' AND status<>"cancelled" AND invoice_date<"'.pSQL($from).'"),0) - COALESCE((SELECT SUM(amount+wht_amount) FROM `'._DB_PREFIX_.'pulse_acc_receipt` WHERE id_pulse_company='.(int) $idCompany.' AND status="posted" AND receipt_date<"'.pSQL($from).'"),0)'), 2);
        $bal = $opening;
        foreach ($rows as &$r) { $bal = round($bal + (float) $r['debit'] - (float) $r['credit'], 2); $r['balance'] = $bal; }
        return array('company' => self::company($idCompany), 'from' => $from, 'to' => $to, 'opening' => $opening, 'rows' => $rows, 'closing' => $bal);
    }

    /**
     * Companies that should not get more credit: over the limit, or with debt older than the stop-list
     * setting. Front Desk can call this before routing another charge to the city ledger.
     */
    public static function stopList($asOf = null)
    {
        $days = (int) Configuration::get('PULSE_ACC_STOP_LIST_DAYS');
        $out = array();
        foreach (self::ageing($asOf) as $a) {
            $co = self::company((int) $a['id_pulse_company']);
            $limit = $co ? round((float) $co['credit_limit'], 2) : 0;
            $reasons = array();
            if ($limit > 0 && (float) $a['total'] > $limit) { $reasons[] = 'over limit by '.number_format((float) $a['total'] - $limit, 2); }
            if ((int) $a['oldest_days'] > $days) { $reasons[] = (int) $a['oldest_days'].' days overdue'; }
            if ($reasons) { $out[] = array_merge($a, array('credit_limit' => $limit, 'reasons' => implode(', ', $reasons))); }
        }
        return $out;
    }

    public static function onStop($idCompany)
    {
        foreach (self::stopList() as $s) { if ((int) $s['id_pulse_company'] === (int) $idCompany) { return $s['reasons']; } }
        return false;
    }

    /**
     * Dunning: level 1 reminder / 2 second notice / 3 final demand, chosen from how far past due the
     * oldest invoice is (PULSE_ACC_DUNNING_DAYS, default 7,21,45).
     */
    public static function dunningCandidates($asOf = null)
    {
        $asOf = $asOf ? $asOf : date('Y-m-d');
        $steps = array_map('intval', explode(',', (string) Configuration::get('PULSE_ACC_DUNNING_DAYS')));
        while (count($steps) < 3) { $steps[] = 45; }
        $out = array();
        foreach (self::ageing($asOf) as $a) {
            $d = (int) $a['oldest_days'];
            if ($d < $steps[0]) { continue; }
            $level = $d >= $steps[2] ? 3 : ($d >= $steps[1] ? 2 : 1);
            $last = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_dunning` WHERE id_pulse_company='.(int) $a['id_pulse_company'].' ORDER BY as_of DESC, id_pulse_acc_dunning DESC');
            $out[] = array_merge($a, array('level' => $level, 'overdue' => round((float) $a['total'] - (float) $a['b_current'], 2), 'last_sent' => $last && $last['status'] === 'sent' ? $last['sent_at'] : null, 'last_level' => $last ? (int) $last['level'] : 0));
        }
        return $out;
    }

    public static function dunningLetter($idCompany, $level = null, $asOf = null)
    {
        $asOf = $asOf ? $asOf : date('Y-m-d');
        $co = self::company($idCompany);
        if (!$co) { throw new PrestaShopException('Unknown company'); }
        $invoices = self::openInvoices($idCompany);
        if (!$invoices) { throw new PrestaShopException('Nothing outstanding for '.$co['name']); }
        $total = 0; $overdue = 0; $refs = array();
        foreach ($invoices as $i) { $total += (float) $i['balance']; if ($i['due_date'] < $asOf) { $overdue += (float) $i['balance']; } $refs[] = $i['invoice_no']; }
        if ($level === null) { $c = self::dunningCandidates($asOf); $level = 1; foreach ($c as $x) { if ((int) $x['id_pulse_company'] === (int) $idCompany) { $level = (int) $x['level']; } } }
        $hotel = Configuration::get('PULSE_ACC_HOTEL_NAME'); $addr = Configuration::get('PULSE_ACC_HOTEL_ADDRESS');
        $tone = array(
            1 => 'Our records show the invoices below are now past their due date. If payment has already been made, please send us the transfer details so we can apply it.',
            2 => 'Despite our earlier reminder the invoices below remain unpaid. Please arrange settlement within seven (7) days to keep your account open.',
            3 => 'FINAL DEMAND. The invoices below are seriously overdue. Unless payment reaches us within seven (7) days your company account will be placed on stop and future bookings will require payment in advance. We may also refer the debt for recovery.',
        );
        $rows = '';
        foreach ($invoices as $i) { $rows .= '<tr><td>'.$i['invoice_no'].'</td><td>'.$i['invoice_date'].'</td><td>'.$i['due_date'].'</td><td style="text-align:right">'.number_format((float) $i['total'], 2).'</td><td style="text-align:right">'.number_format((float) $i['balance'], 2).'</td><td>'.max(0, (int) ((strtotime($asOf) - strtotime($i['due_date'])) / 86400)).'</td></tr>'; }
        $body = '<div class="pulse-letter"><h2>'.Tools::safeOutput($hotel).'</h2><p>'.nl2br(Tools::safeOutput($addr)).'</p>'
            .'<p><strong>'.Tools::safeOutput($co['name']).'</strong><br>'.Tools::safeOutput($co['address']).'<br>'.Tools::safeOutput($co['contact_name']).'</p>'
            .'<p>'.date('d F Y', strtotime($asOf)).'</p>'
            .'<h3>'.($level === 3 ? 'Final demand' : ($level === 2 ? 'Second notice' : 'Statement reminder')).' — account '.Tools::safeOutput($co['name']).'</h3>'
            .'<p>'.$tone[$level].'</p>'
            .'<table border="1" cellspacing="0" cellpadding="4" width="100%"><thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th>Total</th><th>Outstanding</th><th>Days late</th></tr></thead><tbody>'.$rows.'</tbody>'
            .'<tfoot><tr><th colspan="4">Total outstanding</th><th style="text-align:right">'.number_format($total, 2).'</th><th></th></tr><tr><th colspan="4">Of which overdue</th><th style="text-align:right">'.number_format($overdue, 2).'</th><th></th></tr></tfoot></table>'
            .'<p>Payment may be made to '.Tools::safeOutput((string) Configuration::get('PULSE_ACC_HOTEL_NAME')).' by bank transfer; please quote the invoice numbers on the advice. Where withholding tax is deducted, kindly forward the credit note so we can apply it to your account.</p>'
            .'<p>Yours faithfully,<br>Accounts Department<br>'.Tools::safeOutput($hotel).'</p></div>';
        Db::getInstance()->insert('pulse_acc_dunning', array(
            'id_pulse_company' => (int) $idCompany, 'company_name' => pSQL($co['name']), 'level' => (int) $level, 'as_of' => pSQL($asOf),
            'balance' => round($total, 2), 'overdue' => round($overdue, 2), 'invoices' => pSQL(Tools::substr(implode(', ', $refs), 0, 255)),
            'body' => pSQL($body, true), 'status' => 'draft', 'id_employee' => PulseAccService::emp(), 'date_add' => date('Y-m-d H:i:s'),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::audit('pulseaccounts', 'ar_dunning', array('company' => $co['name'], 'level' => $level, 'balance' => round($total, 2)), 'pulse_acc_dunning', $id);
        return $id;
    }

    public static function dunningLog($limit = 100) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_dunning` ORDER BY id_pulse_acc_dunning DESC LIMIT '.(int) $limit); }
    public static function dunningRow($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_dunning` WHERE id_pulse_acc_dunning='.(int) $id); }

    /** Mark a letter sent, e-mailing it when the company has an address and mail is configured. */
    public static function dunningSend($id)
    {
        $d = self::dunningRow($id);
        if (!$d) { throw new PrestaShopException('Unknown letter'); }
        $co = self::company((int) $d['id_pulse_company']);
        $sent = false;
        if ($co && $co['email'] && Validate::isEmail($co['email'])) {
            $titles = array(1 => 'Statement reminder', 2 => 'Second notice', 3 => 'Final demand');
            $lvl = (int) $d['level']; $subject = (isset($titles[$lvl]) ? $titles[$lvl] : $titles[1]).' — '.Configuration::get('PULSE_ACC_HOTEL_NAME');
            $from = Configuration::get('PULSE_ACC_HOTEL_EMAIL'); $from = $from ? $from : Configuration::get('PS_SHOP_EMAIL');
            $sent = (bool) @Mail::Send((int) Context::getContext()->language->id, 'pulse_dunning', $subject, array('{body}' => $d['body'], '{company}' => $d['company_name'], '{balance}' => number_format((float) $d['balance'], 2)), $co['email'], $co['name'], $from, Configuration::get('PULSE_ACC_HOTEL_NAME'), null, null, _PS_MODULE_DIR_.'pulseaccounts/mails/', false);
        }
        Db::getInstance()->update('pulse_acc_dunning', array('status' => 'sent', 'sent_at' => date('Y-m-d H:i:s')), 'id_pulse_acc_dunning='.(int) $id);
        return $sent;
    }
}
