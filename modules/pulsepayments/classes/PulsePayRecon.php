<?php
/**
 * Settlement reconciliation: import a gateway statement (CSV), match it against our ledger,
 * show matched / unmatched / fee variance, and push the gateway fee into the expense ledger (category BANK).
 * Column names differ per gateway, so the header is mapped by alias rather than by position.
 */
class PulsePayRecon
{
    /** Header aliases seen on Paystack, Flutterwave and Interswitch settlement exports. */
    protected static $map = array(
        'gateway_ref' => array('transaction reference', 'reference', 'txn ref', 'txnref', 'transaction_reference', 'trans ref', 'id', 'transaction id', 'flw ref', 'flw_ref', 'payment reference'),
        'reference' => array('merchant reference', 'customer reference', 'tx_ref', 'tx ref', 'our reference', 'merchant_reference', 'order ref'),
        'gross' => array('amount', 'gross', 'transaction amount', 'gross amount', 'amount paid'),
        'fee' => array('fee', 'fees', 'charge', 'charges', 'app fee', 'merchant fee', 'transaction fee'),
        'net' => array('net', 'amount settled', 'settled amount', 'net amount', 'settlement amount'),
        'paid_at' => array('paid at', 'date', 'transaction date', 'created at', 'paid_at', 'settlement date'),
        'currency' => array('currency', 'currency code'),
        'status' => array('status', 'transaction status'),
    );

    /** Import a CSV file into a settlement batch and match every row. */
    public static function import($gateway, $path, $filename = null)
    {
        if (!is_file($path) || !is_readable($path)) { throw new PrestaShopException('Settlement file could not be read'); }
        $fh = fopen($path, 'r');
        if (!$fh) { throw new PrestaShopException('Settlement file could not be opened'); }
        $sep = self::delimiter($path);
        $head = fgetcsv($fh, 0, $sep);
        if (!$head) { fclose($fh); throw new PrestaShopException('Settlement file is empty'); }
        $cols = self::mapHeader($head);
        if (!isset($cols['gateway_ref']) && !isset($cols['reference'])) { fclose($fh); throw new PrestaShopException('No reference column found — expected one of: transaction reference, tx_ref, merchant reference'); }
        Db::getInstance()->insert('pulse_pay_settlement', array('gateway' => pSQL($gateway), 'filename' => pSQL(Tools::substr($filename ? $filename : basename($path), 0, 255)), 'status' => 'imported', 'id_employee' => PulsePayService::emp(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), true);
        $idS = (int) Db::getInstance()->Insert_ID();
        $n = 0; $gross = 0; $fee = 0; $net = 0; $from = null; $to = null;
        while (($r = fgetcsv($fh, 0, $sep)) !== false) {
            if (count($r) === 1 && trim((string) $r[0]) === '') { continue; }
            $v = self::pick($r, $cols);
            if ($v['gateway_ref'] === '' && $v['reference'] === '') { continue; }
            $g = self::money($v['gross']); $f = self::money($v['fee']); $nt = $v['net'] !== '' ? self::money($v['net']) : round($g - $f, 2);
            if ($f == 0 && $v['net'] !== '') { $f = round($g - $nt, 2); }
            $paid = $v['paid_at'] !== '' ? date('Y-m-d H:i:s', strtotime($v['paid_at'])) : null;
            if ($paid) { $d = Tools::substr($paid, 0, 10); $from = ($from === null || $d < $from) ? $d : $from; $to = ($to === null || $d > $to) ? $d : $to; }
            Db::getInstance()->insert('pulse_pay_settlement_line', array(
                'id_pulse_pay_settlement' => $idS, 'gateway_ref' => pSQL($v['gateway_ref']), 'reference' => pSQL($v['reference']), 'paid_at' => $paid ? pSQL($paid) : null,
                'gross' => $g, 'fee' => $f, 'net' => $nt, 'currency' => pSQL($v['currency'] !== '' ? Tools::strtoupper(Tools::substr($v['currency'], 0, 3)) : PulsePayService::currency()),
                'raw' => pSQL(Tools::substr(implode(' | ', $r), 0, 2000), true),
            ), true);
            $n++; $gross += $g; $fee += $f; $net += $nt;
        }
        fclose($fh);
        Db::getInstance()->update('pulse_pay_settlement', array('rows_total' => $n, 'gross_total' => round($gross, 2), 'fee_total' => round($fee, 2), 'net_total' => round($net, 2), 'period_from' => $from ? pSQL($from) : null, 'period_to' => $to ? pSQL($to) : null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pay_settlement='.$idS, 0, true);
        self::match($idS);
        PulseCoreService::audit('pulsepayments', 'settlement_import', array('gateway' => $gateway, 'rows' => $n, 'gross' => round($gross, 2)), 'pulse_pay_settlement', $idS);
        return $idS;
    }

    /** Match every line of a batch against pulse_pay_transaction and flag the variances. */
    public static function match($idSettlement)
    {
        $s = self::settlement($idSettlement);
        if (!$s) { return false; }
        $tol = (float) Configuration::get('PULSE_PAY_FEE_TOLERANCE');
        $matched = 0; $variance = 0; $unmatched = 0;
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_settlement_line` WHERE id_pulse_pay_settlement='.(int) $idSettlement) as $l) {
            $where = array();
            if ($l['gateway_ref'] !== '') { $where[] = 'gateway_ref="'.pSQL($l['gateway_ref']).'"'; $where[] = 'reference="'.pSQL($l['gateway_ref']).'"'; }
            if ($l['reference'] !== '') { $where[] = 'reference="'.pSQL($l['reference']).'"'; $where[] = 'gateway_ref="'.pSQL($l['reference']).'"'; }
            $tx = $where ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_transaction` WHERE gateway="'.pSQL($s['gateway']).'" AND ('.implode(' OR ', $where).') ORDER BY id_pulse_pay_transaction LIMIT 1') : null;
            $state = 'unmatched'; $var = 0; $note = '';
            if ($tx) {
                $ours = round((float) $tx['amount_captured'] > 0 ? (float) $tx['amount_captured'] : (float) $tx['amount'], 2);
                if (abs($ours - (float) $l['gross']) > 0.009) { $state = 'amount_variance'; $var = round((float) $l['gross'] - $ours, 2); $note = 'Statement '.number_format($l['gross'], 2).' vs ledger '.number_format($ours, 2); }
                elseif (abs((float) $tx['fee'] - (float) $l['fee']) > max(0.009, $tol)) { $state = 'fee_variance'; $var = round((float) $l['fee'] - (float) $tx['fee'], 2); $note = 'Fee '.number_format($l['fee'], 2).' vs estimated '.number_format($tx['fee'], 2); }
                else { $state = 'matched'; }
                Db::getInstance()->update('pulse_pay_transaction', array('fee' => (float) $l['fee'], 'net' => (float) $l['net'], 'state' => in_array($tx['state'], array('captured', 'settled')) ? 'settled' : pSQL($tx['state']), 'settled_at' => $l['paid_at'] ? pSQL($l['paid_at']) : date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pay_transaction='.(int) $tx['id_pulse_pay_transaction']);
            }
            Db::getInstance()->update('pulse_pay_settlement_line', array('id_pulse_pay_transaction' => $tx ? (int) $tx['id_pulse_pay_transaction'] : null, 'match_state' => pSQL($state), 'variance' => $var, 'note' => pSQL($note)), 'id_pulse_pay_settlement_line='.(int) $l['id_pulse_pay_settlement_line'], 0, true);
            if ($state === 'matched') { $matched++; } elseif ($state === 'unmatched') { $unmatched++; } else { $variance++; }
        }
        Db::getInstance()->update('pulse_pay_settlement', array('rows_matched' => $matched, 'rows_unmatched' => $unmatched, 'rows_variance' => $variance, 'status' => $unmatched + $variance === 0 ? 'reconciled' : 'imported', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pay_settlement='.(int) $idSettlement);
        return array('matched' => $matched, 'unmatched' => $unmatched, 'variance' => $variance);
    }

    /** Transactions we captured in the statement period that the gateway never listed — the other half of the check. */
    public static function missing($idSettlement)
    {
        $s = self::settlement($idSettlement);
        if (!$s || !$s['period_from']) { return array(); }
        return Db::getInstance()->executeS('SELECT t.* FROM `'._DB_PREFIX_.'pulse_pay_transaction` t WHERE t.gateway="'.pSQL($s['gateway']).'" AND t.type<>"preauth" AND t.state IN ("captured","partially_refunded") AND t.business_date BETWEEN "'.pSQL($s['period_from']).'" AND "'.pSQL($s['period_to']).'" AND t.id_pulse_pay_transaction NOT IN (SELECT COALESCE(id_pulse_pay_transaction,0) FROM `'._DB_PREFIX_.'pulse_pay_settlement_line` WHERE id_pulse_pay_settlement='.(int) $idSettlement.') ORDER BY t.business_date, t.reference');
    }

    /** Post the batch's total gateway fee as a BANK expense so the P&L carries the real cost of taking cards. */
    public static function postFeeExpense($idSettlement)
    {
        $s = self::settlement($idSettlement);
        if (!$s) { throw new PrestaShopException('Unknown settlement'); }
        if ($s['fee_expense_posted']) { return false; }
        if (!PulsePayService::rpt()) { throw new PrestaShopException('Pulse Reports is not installed — no expense ledger to post to'); }
        if ((float) $s['fee_total'] <= 0) { throw new PrestaShopException('This statement carries no fees'); }
        $id = PulseExpense::add(array(
            'category' => 'BANK', 'department' => 'admin', 'description' => ucfirst($s['gateway']).' settlement fees '.($s['period_from'] ? $s['period_from'].' → '.$s['period_to'] : $s['filename']),
            'payee' => ucfirst($s['gateway']), 'amount' => round((float) $s['fee_total'], 2), 'payment_method' => 'transfer', 'reference' => 'SETTLE-'.(int) $s['id_pulse_pay_settlement'],
            'source' => 'payments', 'source_ref' => 'settlement:'.(int) $s['id_pulse_pay_settlement'], 'business_date' => $s['period_to'] ? $s['period_to'] : PulsePayService::bd(), 'status' => 'approved',
        ));
        Db::getInstance()->update('pulse_pay_settlement', array('fee_expense_posted' => 1, 'status' => 'closed', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pay_settlement='.(int) $idSettlement);
        PulseCoreService::audit('pulsepayments', 'settlement_fee_expense', array('settlement' => $idSettlement, 'fee' => $s['fee_total'], 'expense' => $id), 'pulse_pay_settlement', (int) $idSettlement);
        return $id;
    }

    public static function settlements($limit = 50) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_settlement` ORDER BY id_pulse_pay_settlement DESC LIMIT '.(int) $limit); }
    public static function settlement($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_settlement` WHERE id_pulse_pay_settlement='.(int) $id); }
    public static function lines($id, $state = null) { return Db::getInstance()->executeS('SELECT l.*, t.reference our_reference, t.channel, t.state tx_state, t.business_date FROM `'._DB_PREFIX_.'pulse_pay_settlement_line` l LEFT JOIN `'._DB_PREFIX_.'pulse_pay_transaction` t ON t.id_pulse_pay_transaction=l.id_pulse_pay_transaction WHERE l.id_pulse_pay_settlement='.(int) $id.($state ? ' AND l.match_state="'.pSQL($state).'"' : '').' ORDER BY l.match_state, l.id_pulse_pay_settlement_line LIMIT 1000'); }
    public static function remove($id) { Db::getInstance()->delete('pulse_pay_settlement_line', 'id_pulse_pay_settlement='.(int) $id); Db::getInstance()->delete('pulse_pay_settlement', 'id_pulse_pay_settlement='.(int) $id); return true; }

    /* ---------- CSV helpers ---------- */
    /** Sniff , vs ; from the header line only — European exports of Nigerian statements do exist. */
    protected static function delimiter($path) { $fh = fopen($path, 'r'); $first = $fh ? (string) fgets($fh, 8192) : ''; if ($fh) { fclose($fh); } return substr_count($first, ';') > substr_count($first, ',') ? ';' : ','; }
    protected static function mapHeader(array $head)
    {
        $cols = array();
        foreach ($head as $i => $h) {
            $k = trim(Tools::strtolower(str_replace(array('"', '_'), array('', ' '), (string) $h)));
            foreach (self::$map as $field => $aliases) { if (!isset($cols[$field]) && in_array($k, $aliases)) { $cols[$field] = $i; } }
        }
        return $cols;
    }
    protected static function pick(array $row, array $cols)
    {
        $o = array();
        foreach (array('gateway_ref', 'reference', 'gross', 'fee', 'net', 'paid_at', 'currency', 'status') as $f) { $o[$f] = isset($cols[$f]) && isset($row[$cols[$f]]) ? trim((string) $row[$cols[$f]]) : ''; }
        return $o;
    }
    protected static function money($v) { $v = preg_replace('/[^0-9.-]/', '', (string) $v); return $v === '' || $v === '-' ? 0 : round((float) $v, 2); }
}
