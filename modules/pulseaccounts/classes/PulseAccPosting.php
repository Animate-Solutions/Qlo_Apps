<?php
/**
 * Posting rules: turns folio lines, POS checks, expenses, GRNs and stock movements into balanced journals.
 * Nothing posts synchronously in the operational path — suite events only enqueue, the night audit or the
 * accountant's "post now" drains the queue. A sweep() pass catches anything the events missed (a machine
 * that was offline, a module installed later, an expense approved without an event).
 */
class PulseAccPosting
{
    /** Add a document to the posting queue. Cheap and idempotent — UNIQUE(source, source_ref). */
    public static function enqueue($source, $ref, array $payload = array(), $date = null)
    {
        $date = $date ? $date : PulseAccService::bd();
        return Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_acc_queue` (`source`,`source_ref`,`business_date`,`payload`,`status`,`date_add`,`date_upd`) VALUES ("'.pSQL($source).'","'.pSQL(Tools::substr($ref, 0, 96)).'","'.pSQL($date).'","'.pSQL(json_encode($payload), true).'","pending",NOW(),NOW())');
    }

    public static function queue($status = 'pending', $limit = 200, $date = null)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_queue` WHERE status="'.pSQL($status).'"'.($date ? ' AND business_date<="'.pSQL($date).'"' : '').' ORDER BY business_date, id_pulse_acc_queue LIMIT '.(int) $limit);
    }

    public static function queueCounts()
    {
        $r = Db::getInstance()->getRow('SELECT SUM(status="pending") pending, SUM(status="posted") posted, SUM(status="failed") failed, SUM(status="skipped") skipped FROM `'._DB_PREFIX_.'pulse_acc_queue`');
        return array('pending' => (int) $r['pending'], 'posted' => (int) $r['posted'], 'failed' => (int) $r['failed'], 'skipped' => (int) $r['skipped']);
    }

    /**
     * Work the queue. Each row is independent: one bad document never blocks the rest of the day,
     * it just parks as failed with the reason on the dashboard.
     */
    public static function drain($limit = 400, $upToDate = null)
    {
        $max = (int) Configuration::get('PULSE_ACC_RETRY_MAX'); $max = $max > 0 ? $max : 5;
        $done = 0; $failed = 0; $skipped = 0;
        foreach (self::queue('pending', (int) $limit, $upToDate) as $q) {
            try {
                $id = self::build($q['source'], $q['source_ref'], $q['business_date']);
                if ($id === null) { Db::getInstance()->update('pulse_acc_queue', array('status' => 'skipped', 'last_error' => 'Nothing to post', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_queue='.(int) $q['id_pulse_acc_queue']); $skipped++; continue; }
                Db::getInstance()->update('pulse_acc_queue', array('status' => 'posted', 'id_pulse_acc_journal' => (int) $id, 'last_error' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_queue='.(int) $q['id_pulse_acc_queue'], 0, true);
                $done++;
            } catch (Exception $e) {
                $attempts = (int) $q['attempts'] + 1;
                Db::getInstance()->update('pulse_acc_queue', array('status' => $attempts >= $max ? 'failed' : 'pending', 'attempts' => $attempts, 'last_error' => pSQL(Tools::substr($e->getMessage(), 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_queue='.(int) $q['id_pulse_acc_queue']);
                $failed++;
            }
        }
        return array('posted' => $done, 'failed' => $failed, 'skipped' => $skipped);
    }

    /** Push a failed/skipped row back into the queue after the accountant fixed the rule. */
    public static function retry($id = null)
    {
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_acc_queue` SET status="pending", attempts=0, last_error=NULL, date_upd=NOW() WHERE status IN ("failed","skipped")'.($id ? ' AND id_pulse_acc_queue='.(int) $id : ''));
        return true;
    }

    /** Route a queued document to its builder. Returns the journal id, or null when there is nothing to post. */
    public static function build($source, $ref, $date)
    {
        $parts = explode(':', $ref, 2); $key = isset($parts[1]) ? $parts[1] : '';
        switch ($source) {
            case 'folio': return self::folioLine((int) $key);
            case 'pos': return self::posCheck((int) $key);
            case 'expense': return self::expense((int) $key);
            case 'grn': return self::grn((int) $key);
            case 'inventory': return self::stockDay($key ? $key : $date);
            default: throw new PrestaShopException('No posting rule for source "'.$source.'"');
        }
    }

    /**
     * Find everything on a business date that should be in the ledger and is not queued yet.
     * This is the safety net that makes the whole design forgiving of missed events.
     */
    public static function sweep($date = null)
    {
        $date = $date ? $date : PulseAccService::bd();
        $n = 0;
        if (PulseAccService::fd()) {
            foreach (Db::getInstance()->executeS('SELECT l.id_pulse_folio_line FROM `'._DB_PREFIX_.'pulse_folio_line` l WHERE l.business_date="'.pSQL($date).'" AND l.voided=0 AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_acc_queue` q WHERE q.source="folio" AND q.source_ref=CONCAT("line:",l.id_pulse_folio_line))') as $r) { self::enqueue('folio', 'line:'.(int) $r['id_pulse_folio_line'], array(), $date); $n++; }
        }
        if (PulseAccService::pos()) {
            foreach (Db::getInstance()->executeS('SELECT c.id_pulse_pos_check FROM `'._DB_PREFIX_.'pulse_pos_check` c WHERE c.business_date="'.pSQL($date).'" AND c.status="settled" AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_acc_queue` q WHERE q.source="pos" AND q.source_ref=CONCAT("check:",c.id_pulse_pos_check))') as $r) { self::enqueue('pos', 'check:'.(int) $r['id_pulse_pos_check'], array(), $date); $n++; }
        }
        if (PulseAccService::rpt()) {
            foreach (Db::getInstance()->executeS('SELECT e.id_pulse_expense FROM `'._DB_PREFIX_.'pulse_expense` e WHERE e.business_date<="'.pSQL($date).'" AND e.status IN ("approved","paid") AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_acc_queue` q WHERE q.source="expense" AND q.source_ref=CONCAT("exp:",e.id_pulse_expense)) LIMIT 500') as $r) { self::enqueue('expense', 'exp:'.(int) $r['id_pulse_expense'], array(), $date); $n++; }
        }
        if (PulseAccService::inv()) {
            foreach (Db::getInstance()->executeS('SELECT g.id_pulse_inv_grn FROM `'._DB_PREFIX_.'pulse_inv_grn` g WHERE g.business_date<="'.pSQL($date).'" AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_acc_queue` q WHERE q.source="grn" AND q.source_ref=CONCAT("grn:",g.id_pulse_inv_grn)) LIMIT 500') as $r) { self::enqueue('grn', 'grn:'.(int) $r['id_pulse_inv_grn'], array(), $date); $n++; }
            if ((int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_inv_movement` WHERE business_date="'.pSQL($date).'" AND type IN ("consume","issue","waste","sale","minibar","amenity","count_adjust")')) { self::enqueue('inventory', 'day:'.$date, array(), $date); $n++; }
        }
        return $n;
    }

    /* ================= folio lines ================= */

    /**
     * A folio charge: guest ledger Dr, revenue Cr, VAT (and Rivers State consumption tax on F&B) Cr.
     * A folio payment: cash/bank/AR/deposit Dr, guest ledger Cr.
     * Lines that came from POS are skipped — the whole check posts once from the POS builder so that
     * food and beverage land in their own accounts instead of one lump of "restaurant".
     */
    public static function folioLine($idLine)
    {
        if (!PulseAccService::fd()) { throw new PrestaShopException('Front Desk is not installed'); }
        $l = Db::getInstance()->getRow('SELECT l.*, f.folio_no, f.type folio_type, f.id_pulse_company, f.id_customer, f.id_htl_booking FROM `'._DB_PREFIX_.'pulse_folio_line` l INNER JOIN `'._DB_PREFIX_.'pulse_folio` f ON f.id_pulse_folio=l.id_pulse_folio WHERE l.id_pulse_folio_line='.(int) $idLine);
        if (!$l) { return null; }
        if ($l['voided']) { return null; }
        if ($l['source'] === 'pos') { return null; }
        $amount = round((float) $l['amount_tax_incl'], 2);
        if (abs($amount) < 0.005) { return null; }
        $ledger = PulseAccService::mapAccount('folio_type', $l['folio_type']);
        if (!$ledger) { $ledger = '1210'; }
        $code = (string) $l['description'];
        $cc = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_charge_code` WHERE id_pulse_charge_code='.(int) $l['id_pulse_charge_code']);
        $chargeCode = $cc ? $cc['code'] : 'MISC';
        $rule = PulseAccService::map('charge_code', $chargeCode);
        if (!$rule || empty($rule['account_code'])) { throw new PrestaShopException('Charge code '.$chargeCode.' has no posting rule'); }
        $dept = $l['department'] ? $l['department'] : (isset($rule['cost_centre']) ? $rule['cost_centre'] : 'general');
        $memo = Tools::substr($l['folio_no'].' — '.$code, 0, 240);
        $lines = array();

        if ((int) $l['is_payment']) {
            // settlement: money (or a receivable / deposit release) in, guest ledger down
            $cash = $rule['account_code'];
            $lines[] = array('account' => $cash, 'debit' => $amount > 0 ? $amount : 0, 'credit' => $amount < 0 ? abs($amount) : 0, 'memo' => $memo, 'cost_centre' => $dept, 'entity' => 'pulse_folio_line', 'id_entity' => $idLine, 'id_pulse_company' => $l['id_pulse_company']);
            $lines[] = array('account' => $ledger, 'debit' => $amount < 0 ? abs($amount) : 0, 'credit' => $amount > 0 ? $amount : 0, 'memo' => $memo, 'cost_centre' => $dept, 'entity' => 'pulse_folio_line', 'id_entity' => $idLine, 'id_pulse_company' => $l['id_pulse_company']);
        } else {
            $taxRate = (float) $l['tax_rate'];
            $net = $taxRate > 0 ? round($amount / (1 + $taxRate / 100), 2) : $amount;
            $tax = round($amount - $net, 2);
            $split = self::splitTax($tax, $taxRate, $l['department']);
            $lines[] = array('account' => $ledger, 'debit' => $amount > 0 ? $amount : 0, 'credit' => $amount < 0 ? abs($amount) : 0, 'memo' => $memo, 'cost_centre' => $dept, 'entity' => 'pulse_folio_line', 'id_entity' => $idLine, 'id_pulse_company' => $l['id_pulse_company']);
            $lines[] = array('account' => $rule['account_code'], 'debit' => $net < 0 ? abs($net) : 0, 'credit' => $net > 0 ? $net : 0, 'memo' => $memo, 'department' => $l['department'], 'cost_centre' => $dept, 'entity' => 'pulse_folio_line', 'id_entity' => $idLine, 'id_pulse_company' => $l['id_pulse_company']);
            if (abs($split['vat']) > 0.004) { $lines[] = array('account' => $rule['tax_account_code'] ? $rule['tax_account_code'] : '2210', 'debit' => $split['vat'] < 0 ? abs($split['vat']) : 0, 'credit' => $split['vat'] > 0 ? $split['vat'] : 0, 'memo' => 'VAT '.$memo, 'cost_centre' => $dept, 'tax_code' => 'VAT', 'entity' => 'pulse_folio_line', 'id_entity' => $idLine); }
            if (abs($split['consumption']) > 0.004) { $lines[] = array('account' => '2240', 'debit' => $split['consumption'] < 0 ? abs($split['consumption']) : 0, 'credit' => $split['consumption'] > 0 ? $split['consumption'] : 0, 'memo' => 'Consumption tax '.$memo, 'cost_centre' => $dept, 'tax_code' => 'CONS', 'entity' => 'pulse_folio_line', 'id_entity' => $idLine); }
        }

        $id = PulseAccJournal::post(array(
            'type' => (int) $l['is_payment'] ? 'receipt' : 'sales', 'source' => 'folio', 'source_ref' => 'line:'.(int) $idLine,
            'business_date' => $l['business_date'], 'reference' => $l['folio_no'], 'memo' => $memo, 'lines' => $lines,
        ));
        if (!(int) $l['is_payment'] && isset($split) && ($split['vat'] > 0.004 || $split['consumption'] > 0.004)) {
            PulseAccTax::recordVat('output', 'folio', 'line:'.(int) $idLine, array(
                'doc_no' => $l['folio_no'], 'party_name' => self::folioParty($l), 'department' => $l['department'],
                'net_amount' => isset($net) ? $net : 0, 'vat_rate' => PulseAccService::vatPct(), 'vat_amount' => $split['vat'], 'consumption_tax' => $split['consumption'],
                'business_date' => $l['business_date'], 'id_journal' => $id,
            ));
        }
        return $id;
    }

    protected static function folioParty(array $l)
    {
        if (!empty($l['id_pulse_company'])) { $n = Db::getInstance()->getValue('SELECT name FROM `'._DB_PREFIX_.'pulse_company` WHERE id_pulse_company='.(int) $l['id_pulse_company']); if ($n) { return $n; } }
        if (!empty($l['id_customer'])) { $n = Db::getInstance()->getValue('SELECT CONCAT(firstname," ",lastname) FROM `'._DB_PREFIX_.'customer` WHERE id_customer='.(int) $l['id_customer']); if ($n) { return $n; } }
        return 'Walk-in guest';
    }

    /** Split a tax amount into VAT and Rivers State consumption tax when an F&B line carries both. */
    public static function splitTax($tax, $taxRate, $department)
    {
        $tax = round((float) $tax, 2);
        $vatPct = PulseAccService::vatPct(); $consPct = PulseAccService::consumptionPct();
        $both = in_array($department, array('fnb', 'minibar')) && $consPct > 0 && $taxRate >= $vatPct + $consPct - 0.01;
        if (!$both || $vatPct + $consPct <= 0) { return array('vat' => $tax, 'consumption' => 0); }
        $vat = round($tax * $vatPct / ($vatPct + $consPct), 2);
        return array('vat' => $vat, 'consumption' => round($tax - $vat, 2));
    }

    /* ================= POS checks ================= */

    /**
     * A settled POS check, split by major group so food, soft drinks and liquor reach their own accounts.
     * The debit side follows the tender: cash, card, transfer, the guest folio for a room charge,
     * the city ledger for a company account.
     */
    public static function posCheck($idCheck)
    {
        if (!PulseAccService::pos()) { throw new PrestaShopException('Pulse POS is not installed'); }
        $c = Db::getInstance()->getRow('SELECT c.*, o.name outlet_name, o.code outlet_code FROM `'._DB_PREFIX_.'pulse_pos_check` c LEFT JOIN `'._DB_PREFIX_.'pulse_pos_outlet` o ON o.id_pulse_pos_outlet=c.id_pulse_pos_outlet WHERE c.id_pulse_pos_check='.(int) $idCheck);
        if (!$c || $c['status'] !== 'settled') { return null; }
        $total = round((float) $c['total'], 2);
        if (abs($total) < 0.005) { return null; }
        $memo = trim($c['outlet_name'].' check '.$c['check_no']);
        $lines = array(); $revenue = 0;

        // revenue by major group, net of tax, discounts already netted into line_total
        $groups = Db::getInstance()->executeS('SELECT COALESCE(cat.major_group,"other") grp, ROUND(SUM(l.line_total),2) gross FROM `'._DB_PREFIX_.'pulse_pos_check_line` l LEFT JOIN `'._DB_PREFIX_.'pulse_pos_item` i ON i.id_pulse_pos_item=l.id_pulse_pos_item LEFT JOIN `'._DB_PREFIX_.'pulse_pos_category` cat ON cat.id_pulse_pos_category=i.id_pulse_pos_category WHERE l.id_pulse_pos_check='.(int) $idCheck.' AND l.voided=0 GROUP BY grp');
        $grossLines = 0; foreach ($groups as $g) { $grossLines += (float) $g['gross']; }
        $tax = round((float) $c['tax_total'], 2); $service = round((float) $c['service_charge'], 2);
        $netTotal = round($total - $tax - $service, 2);
        foreach ($groups as $g) {
            $share = $grossLines > 0 ? (float) $g['gross'] / $grossLines : 0;
            $amt = round($netTotal * $share, 2);
            if (abs($amt) < 0.005) { continue; }
            $acct = PulseAccService::mapAccount('pos_major_group', $g['grp']);
            if (!$acct) { throw new PrestaShopException('POS major group "'.$g['grp'].'" has no posting rule'); }
            $lines[] = array('account' => $acct, 'credit' => $amt, 'memo' => $memo.' — '.$g['grp'], 'department' => 'fnb', 'cost_centre' => 'fnb', 'entity' => 'pulse_pos_check', 'id_entity' => $idCheck);
            $revenue += $amt;
        }
        // rounding crumbs from the share split land on the largest group
        $drift = round($netTotal - $revenue, 2);
        if (abs($drift) >= 0.005 && $lines) { $lines[0]['credit'] = round($lines[0]['credit'] + $drift, 2); }
        // a discounted or credited check can carry a negative service charge or tax: post it on the other side
        // rather than dropping the line, or the journal would never balance and the check could never post
        if (abs($service) > 0.004) { $lines[] = array('account' => '4250', 'debit' => $service < 0 ? abs($service) : 0, 'credit' => $service > 0 ? $service : 0, 'memo' => 'Service charge '.$c['check_no'], 'department' => 'fnb', 'cost_centre' => 'fnb', 'entity' => 'pulse_pos_check', 'id_entity' => $idCheck); }
        $split = self::splitTax($tax, PulseAccService::vatPct() + PulseAccService::consumptionPct(), 'fnb');
        if (abs($split['vat']) > 0.004) { $lines[] = array('account' => '2210', 'debit' => $split['vat'] < 0 ? abs($split['vat']) : 0, 'credit' => $split['vat'] > 0 ? $split['vat'] : 0, 'memo' => 'VAT '.$c['check_no'], 'tax_code' => 'VAT', 'cost_centre' => 'fnb'); }
        if (abs($split['consumption']) > 0.004) { $lines[] = array('account' => '2240', 'debit' => $split['consumption'] < 0 ? abs($split['consumption']) : 0, 'credit' => $split['consumption'] > 0 ? $split['consumption'] : 0, 'memo' => 'Consumption tax '.$c['check_no'], 'tax_code' => 'CONS', 'cost_centre' => 'fnb'); }

        // tenders
        $paid = 0;
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_payment` WHERE id_pulse_pos_check='.(int) $idCheck.' AND voided=0') as $p) {
            $amt = round((float) $p['amount'], 2);
            if (abs($amt) < 0.005) { continue; }
            $acct = PulseAccService::mapAccount('payment_method', $p['method']);
            if (!$acct) { throw new PrestaShopException('POS tender "'.$p['method'].'" has no posting rule'); }
            $lines[] = array('account' => $acct, 'debit' => $amt > 0 ? $amt : 0, 'credit' => $amt < 0 ? abs($amt) : 0, 'memo' => $memo.' — '.$p['method'].($p['reference'] ? ' '.$p['reference'] : ''), 'cost_centre' => 'fnb', 'entity' => 'pulse_pos_check', 'id_entity' => $idCheck, 'id_pulse_company' => $p['id_pulse_company']);
            $paid += $amt;
            if (round((float) $p['tip'], 2) > 0.004) { $tipAmt = round((float) $p['tip'], 2); $lines[] = array('account' => $acct, 'debit' => $tipAmt, 'memo' => 'Gratuity '.$c['check_no'], 'cost_centre' => 'fnb'); $lines[] = array('account' => '2340', 'credit' => $tipAmt, 'memo' => 'Gratuity payable '.$c['check_no'], 'cost_centre' => 'fnb'); }
        }
        $short = round($total - $paid, 2);
        if (abs($short) >= 0.005) { $lines[] = array('account' => '1210', 'debit' => $short > 0 ? $short : 0, 'credit' => $short < 0 ? abs($short) : 0, 'memo' => $memo.' — unsettled balance', 'cost_centre' => 'fnb', 'entity' => 'pulse_pos_check', 'id_entity' => $idCheck); }

        $id = PulseAccJournal::post(array(
            'type' => 'sales', 'source' => 'pos', 'source_ref' => 'check:'.(int) $idCheck, 'business_date' => $c['business_date'],
            'reference' => $c['check_no'], 'memo' => $memo, 'lines' => $lines,
        ));
        if (abs($split['vat']) > 0.004 || abs($split['consumption']) > 0.004) {
            PulseAccTax::recordVat('output', 'pos', 'check:'.(int) $idCheck, array(
                'doc_no' => $c['check_no'], 'party_name' => $c['guest_name'] ? $c['guest_name'] : 'Restaurant guest', 'department' => 'fnb',
                'net_amount' => $netTotal + $service, 'vat_rate' => PulseAccService::vatPct(), 'vat_amount' => $split['vat'], 'consumption_tax' => $split['consumption'],
                'business_date' => $c['business_date'], 'id_journal' => $id,
            ));
        }
        return $id;
    }

    /* ================= expenses ================= */

    /**
     * An approved or paid expense from Pulse Reports: expense Dr, input VAT Dr where the category is
     * VAT-bearing, cash/bank/AP Cr, and WHT Cr where the payee type attracts a deduction.
     */
    public static function expense($idExpense)
    {
        if (!PulseAccService::rpt()) { throw new PrestaShopException('Pulse Reports is not installed'); }
        $e = Db::getInstance()->getRow('SELECT e.*, c.code cat_code, c.name cat_name, c.group_name FROM `'._DB_PREFIX_.'pulse_expense` e INNER JOIN `'._DB_PREFIX_.'pulse_expense_category` c ON c.id_pulse_expense_category=e.id_pulse_expense_category WHERE e.id_pulse_expense='.(int) $idExpense);
        if (!$e) { return null; }
        if (!in_array($e['status'], array('approved', 'paid'))) { return null; }
        $gross = round((float) $e['amount'], 2);
        if (abs($gross) < 0.005) { return null; }
        $rule = PulseAccService::map('expense_category', $e['cat_code']);
        if (!$rule || empty($rule['account_code'])) { throw new PrestaShopException('Expense category '.$e['cat_code'].' has no posting rule'); }
        $acct = $rule['account_code'];
        if ($e['group_name'] === 'payroll') { $p = PulseAccService::mapAccount('payroll_department', $e['department']); if ($p) { $acct = $p; } }
        $vat = round((float) $e['tax_amount'], 2);
        $net = round($gross - $vat, 2);
        $vatAcct = !empty($rule['tax_account_code']) ? $rule['tax_account_code'] : null;
        $credit = PulseAccService::mapAccount('payment_method', $e['payment_method']);
        if (!$credit) { throw new PrestaShopException('Payment method "'.$e['payment_method'].'" has no posting rule'); }
        $whtRate = (float) $rule['wht_rate_pct'];
        $threshold = (float) Configuration::get('PULSE_ACC_WHT_THRESHOLD');
        $wht = $whtRate > 0 && $net >= $threshold ? round($net * $whtRate / 100, 2) : 0;
        $memo = Tools::substr($e['expense_no'].' — '.$e['description'], 0, 240);
        $dept = $e['department'] ? $e['department'] : (isset($rule['cost_centre']) ? $rule['cost_centre'] : 'general');

        $lines = array(array('account' => $acct, 'debit' => $net, 'memo' => $memo, 'department' => $dept, 'cost_centre' => $dept, 'entity' => 'pulse_expense', 'id_entity' => $idExpense));
        // abs(), not >0: a credited expense carries negative tax and post() moves a negative debit to the credit side —
        // dropping the line instead would leave the journal short by exactly that amount and it would never post
        if (abs($vat) > 0.004 && $vatAcct) { $lines[] = array('account' => $vatAcct, 'debit' => $vat, 'memo' => 'Input VAT '.$memo, 'tax_code' => 'VAT', 'cost_centre' => $dept, 'entity' => 'pulse_expense', 'id_entity' => $idExpense); }
        elseif (abs($vat) > 0.004) { $lines[0]['debit'] = round($lines[0]['debit'] + $vat, 2); }
        if (abs($wht) > 0.004) { $lines[] = array('account' => '2230', 'credit' => $wht, 'memo' => 'WHT '.number_format($whtRate, 1).'% '.$memo, 'tax_code' => 'WHT', 'cost_centre' => $dept, 'entity' => 'pulse_expense', 'id_entity' => $idExpense); }
        $lines[] = array('account' => $credit, 'credit' => round($gross - $wht, 2), 'memo' => $memo, 'cost_centre' => $dept, 'entity' => 'pulse_expense', 'id_entity' => $idExpense);

        $id = PulseAccJournal::post(array(
            'type' => $e['payment_method'] === 'credit' ? 'purchase' : 'payment', 'source' => 'expense', 'source_ref' => 'exp:'.(int) $idExpense,
            'business_date' => $e['business_date'], 'reference' => $e['reference'] ? $e['reference'] : $e['expense_no'], 'memo' => $memo, 'lines' => $lines,
        ));
        if ($vat > 0.004 && $vatAcct) {
            PulseAccTax::recordVat('input', 'expense', 'exp:'.(int) $idExpense, array('doc_no' => $e['expense_no'], 'party_name' => $e['payee'], 'department' => $dept, 'net_amount' => $net, 'vat_rate' => PulseAccService::vatPct(), 'vat_amount' => $vat, 'consumption_tax' => 0, 'business_date' => $e['business_date'], 'id_journal' => $id));
        }
        if ($wht > 0.004) {
            PulseAccTax::recordWht(array(
                'direction' => 'deducted', 'party_type' => 'supplier', 'party_name' => $e['payee'] ? $e['payee'] : $e['cat_name'],
                'wht_type' => $e['cat_code'] === 'RENT' ? 'rent' : 'services', 'base_amount' => $net, 'rate_pct' => $whtRate, 'amount' => $wht,
                'source' => 'expense', 'source_ref' => 'exp:'.(int) $idExpense, 'doc_no' => $e['expense_no'], 'business_date' => $e['business_date'], 'id_journal' => $id,
            ));
        }
        return $id;
    }

    /* ================= goods received & stock ================= */

    /** A GRN: stock Dr by item category, GRN accrual Cr. VAT waits for the supplier's invoice. */
    public static function grn($idGrn)
    {
        if (!PulseAccService::inv()) { throw new PrestaShopException('Pulse Inventory is not installed'); }
        $g = Db::getInstance()->getRow('SELECT g.*, s.name supplier_name FROM `'._DB_PREFIX_.'pulse_inv_grn` g LEFT JOIN `'._DB_PREFIX_.'pulse_inv_supplier` s ON s.id_pulse_inv_supplier=g.id_pulse_inv_supplier WHERE g.id_pulse_inv_grn='.(int) $idGrn);
        if (!$g) { return null; }
        $rows = Db::getInstance()->executeS('SELECT c.code cat_code, c.name cat_name, ROUND(SUM((l.qty-l.rejected_qty)*l.unit_price),2) net FROM `'._DB_PREFIX_.'pulse_inv_grn_line` l INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=l.id_pulse_inv_item INNER JOIN `'._DB_PREFIX_.'pulse_inv_category` c ON c.id_pulse_inv_category=i.id_pulse_inv_category WHERE l.id_pulse_inv_grn='.(int) $idGrn.' GROUP BY c.id_pulse_inv_category');
        if (!$rows) { return null; }
        $memo = 'GRN '.$g['grn_no'].($g['supplier_name'] ? ' — '.$g['supplier_name'] : '');
        $lines = array(); $total = 0;
        foreach ($rows as $r) {
            $net = round((float) $r['net'], 2);
            if (abs($net) < 0.005) { continue; }
            $stock = PulseAccService::mapAccount('inv_category', $r['cat_code'], 'contra_account_code');
            if (!$stock) { throw new PrestaShopException('Stock category '.$r['cat_code'].' has no stock account'); }
            $lines[] = array('account' => $stock, 'debit' => $net, 'memo' => $memo.' — '.$r['cat_name'], 'cost_centre' => 'general', 'entity' => 'pulse_inv_grn', 'id_entity' => $idGrn, 'id_supplier' => $g['id_pulse_inv_supplier']);
            $total += $net;
        }
        if (!$lines) { return null; }
        $lines[] = array('account' => '2120', 'credit' => round($total, 2), 'memo' => $memo, 'entity' => 'pulse_inv_grn', 'id_entity' => $idGrn, 'id_supplier' => $g['id_pulse_inv_supplier']);
        return PulseAccJournal::post(array('type' => 'purchase', 'source' => 'grn', 'source_ref' => 'grn:'.(int) $idGrn, 'business_date' => $g['business_date'], 'reference' => $g['grn_no'], 'memo' => $memo, 'lines' => $lines));
    }

    /**
     * One journal a day for stock leaving the stores: cost of sales (or the department expense) Dr,
     * stock Cr, valued at the movement cost the inventory module already recorded.
     */
    public static function stockDay($date)
    {
        if (!PulseAccService::inv()) { throw new PrestaShopException('Pulse Inventory is not installed'); }
        $date = Tools::substr((string) $date, 0, 10);
        $rows = Db::getInstance()->executeS('SELECT c.code cat_code, c.name cat_name, m.department, ROUND(SUM(-m.value),2) cost FROM `'._DB_PREFIX_.'pulse_inv_movement` m INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=m.id_pulse_inv_item INNER JOIN `'._DB_PREFIX_.'pulse_inv_category` c ON c.id_pulse_inv_category=i.id_pulse_inv_category WHERE m.business_date="'.pSQL($date).'" AND m.type IN ("consume","issue","waste","sale","minibar","amenity","count_adjust") GROUP BY c.id_pulse_inv_category, m.department');
        if (!$rows) { return null; }
        $lines = array(); $byStock = array();
        foreach ($rows as $r) {
            $cost = round((float) $r['cost'], 2);
            if (abs($cost) < 0.005) { continue; }
            $cogs = PulseAccService::mapAccount('inv_category', $r['cat_code']);
            $stock = PulseAccService::mapAccount('inv_category', $r['cat_code'], 'contra_account_code');
            if (!$cogs || !$stock) { throw new PrestaShopException('Stock category '.$r['cat_code'].' has no cost-of-sales / stock account'); }
            $dept = $r['department'] ? $r['department'] : 'general';
            $lines[] = array('account' => $cogs, 'debit' => $cost > 0 ? $cost : 0, 'credit' => $cost < 0 ? abs($cost) : 0, 'memo' => 'Stock issued '.$date.' — '.$r['cat_name'], 'department' => $dept, 'cost_centre' => $dept);
            if (!isset($byStock[$stock])) { $byStock[$stock] = 0; }
            $byStock[$stock] += $cost;
        }
        foreach ($byStock as $acct => $amt) {
            $amt = round($amt, 2);
            if (abs($amt) < 0.005) { continue; }
            $lines[] = array('account' => $acct, 'debit' => $amt < 0 ? abs($amt) : 0, 'credit' => $amt > 0 ? $amt : 0, 'memo' => 'Stock relieved '.$date);
        }
        if (count($lines) < 2) { return null; }
        return PulseAccJournal::post(array('type' => 'general', 'source' => 'inventory', 'source_ref' => 'day:'.$date, 'business_date' => $date, 'reference' => 'Stock '.$date, 'memo' => 'Stock consumption '.$date, 'lines' => $lines));
    }

    /* ================= payroll ================= */

    /**
     * Payroll journal from a summary the accountant keys in (Pulse has no payroll module yet):
     * gross to the departmental payroll accounts, PAYE / pension / net pay to their liabilities.
     */
    public static function payroll($period, array $byDepartment, array $deductions, $date = null)
    {
        $date = $date ? $date : date('Y-m-t', strtotime($period.'-01'));
        $lines = array(); $gross = 0;
        foreach ($byDepartment as $dept => $amount) {
            $amount = round((float) $amount, 2);
            if (abs($amount) < 0.005) { continue; }
            $acct = PulseAccService::mapAccount('payroll_department', $dept);
            if (!$acct) { throw new PrestaShopException('No payroll account for department "'.$dept.'"'); }
            $lines[] = array('account' => $acct, 'debit' => $amount, 'memo' => 'Payroll '.$period.' — '.$dept, 'department' => $dept, 'cost_centre' => $dept);
            $gross += $amount;
        }
        if (!$lines) { throw new PrestaShopException('Nothing to post'); }
        $ded = 0;
        foreach (array('paye' => '2155', 'pension' => '2150', 'nsitf' => '2160', 'other' => '2130') as $k => $acct) {
            $v = round((float) (isset($deductions[$k]) ? $deductions[$k] : 0), 2);
            if ($v < 0.005) { continue; }
            $lines[] = array('account' => $acct, 'credit' => $v, 'memo' => Tools::strtoupper($k).' '.$period);
            $ded += $v;
        }
        $lines[] = array('account' => '2140', 'credit' => round($gross - $ded, 2), 'memo' => 'Net pay '.$period);
        return PulseAccJournal::post(array('type' => 'general', 'source' => 'payroll', 'source_ref' => 'payroll:'.$period, 'business_date' => $date, 'reference' => 'Payroll '.$period, 'memo' => 'Payroll '.$period, 'lines' => $lines));
    }

    /* ================= opening balances ================= */

    /** Opening balances as one journal against retained earnings; runs once per date. */
    public static function opening(array $balances, $date, $memo = 'Opening balances')
    {
        $lines = array(); $net = 0;
        foreach ($balances as $code => $amount) {
            $amount = round((float) $amount, 2);
            if (abs($amount) < 0.005) { continue; }
            $lines[] = array('account' => $code, 'debit' => $amount > 0 ? $amount : 0, 'credit' => $amount < 0 ? abs($amount) : 0, 'memo' => $memo);
            $net += $amount;
        }
        $net = round($net, 2);
        if (abs($net) > 0.004) { $lines[] = array('account' => Configuration::get('PULSE_ACC_RETAINED_EARNINGS'), 'debit' => $net < 0 ? abs($net) : 0, 'credit' => $net > 0 ? $net : 0, 'memo' => $memo.' — balancing figure'); }
        return PulseAccJournal::post(array('type' => 'opening', 'source' => 'opening', 'source_ref' => 'ob:'.$date, 'business_date' => $date, 'reference' => 'Opening', 'memo' => $memo, 'lines' => $lines, 'allow_closed' => true));
    }
}
