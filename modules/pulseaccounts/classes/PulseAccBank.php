<?php
/**
 * Banking: import a bank statement CSV, match it against the ledger by amount / date / reference,
 * match the rest by hand, report what is still unreconciled, and run the petty-cash imprest book
 * against the front-office cashier floats.
 *
 * Nigerian bank exports vary wildly (Zenith, GTBank, Access, Moniepoint all differ), so the header is
 * mapped by alias and both the "Debit/Credit" and the single signed "Amount" layouts are understood.
 */
class PulseAccBank
{
    /** Header aliases seen on Zenith, GTBank, Access, UBA, First Bank and Moniepoint statement exports. */
    protected static $map = array(
        'txn_date' => array('date', 'transaction date', 'trans date', 'posting date', 'tran date', 'date posted', 'transactiondate'),
        'value_date' => array('value date', 'valuedate', 'value dt', 'effective date'),
        'description' => array('description', 'narration', 'remarks', 'details', 'particulars', 'transaction details', 'transaction remarks', 'narrative'),
        'reference' => array('reference', 'ref', 'transaction reference', 'ref no', 'reference number', 'cheque no', 'instrument no', 'session id', 'transaction id'),
        'money_out' => array('debit', 'withdrawal', 'debit amount', 'dr', 'money out', 'withdrawals', 'outflow'),
        'money_in' => array('credit', 'deposit', 'credit amount', 'cr', 'money in', 'lodgement', 'lodgements', 'inflow'),
        'amount' => array('amount', 'transaction amount', 'amount (ngn)', 'value'),
        'balance' => array('balance', 'running balance', 'closing balance', 'bal', 'balance after'),
    );

    public static function statements($idBankAccount = null, $limit = 50)
    {
        return Db::getInstance()->executeS('SELECT s.*, b.name bank_name, b.code bank_code FROM `'._DB_PREFIX_.'pulse_acc_bank_statement` s INNER JOIN `'._DB_PREFIX_.'pulse_acc_bank_account` b ON b.id_pulse_acc_bank_account=s.id_pulse_acc_bank_account WHERE 1'.($idBankAccount ? ' AND s.id_pulse_acc_bank_account='.(int) $idBankAccount : '').' ORDER BY s.id_pulse_acc_bank_statement DESC LIMIT '.(int) $limit);
    }

    public static function statement($id) { return Db::getInstance()->getRow('SELECT s.*, b.name bank_name, b.code bank_code, b.account_code FROM `'._DB_PREFIX_.'pulse_acc_bank_statement` s INNER JOIN `'._DB_PREFIX_.'pulse_acc_bank_account` b ON b.id_pulse_acc_bank_account=s.id_pulse_acc_bank_account WHERE s.id_pulse_acc_bank_statement='.(int) $id); }

    public static function lines($idStatement, $state = null, $limit = 1000)
    {
        return Db::getInstance()->executeS('SELECT l.*, j.journal_no, j.memo journal_memo FROM `'._DB_PREFIX_.'pulse_acc_bank_line` l LEFT JOIN `'._DB_PREFIX_.'pulse_acc_journal` j ON j.id_pulse_acc_journal=l.id_pulse_acc_journal WHERE l.id_pulse_acc_bank_statement='.(int) $idStatement.($state ? ' AND l.match_state="'.pSQL($state).'"' : '').' ORDER BY l.txn_date, l.id_pulse_acc_bank_line LIMIT '.(int) $limit);
    }

    /** Import a CSV into a statement batch, then auto-match every row. */
    public static function import($idBankAccount, $path, $filename = null)
    {
        $ba = PulseAccService::bankAccount((int) $idBankAccount);
        if (!$ba) { throw new PrestaShopException('Pick a bank account to import into'); }
        if (!is_file($path) || !is_readable($path)) { throw new PrestaShopException('Statement file could not be read'); }
        $fh = fopen($path, 'r');
        if (!$fh) { throw new PrestaShopException('Statement file could not be opened'); }
        $sep = self::delimiter($path);
        $head = fgetcsv($fh, 0, $sep);
        if (!$head) { fclose($fh); throw new PrestaShopException('Statement file is empty'); }
        $cols = self::mapHeader($head);
        if (!isset($cols['txn_date'])) { fclose($fh); throw new PrestaShopException('No date column found — expected one of: date, transaction date, value date, posting date'); }
        if (!isset($cols['money_in']) && !isset($cols['money_out']) && !isset($cols['amount'])) { fclose($fh); throw new PrestaShopException('No amount columns found — expected debit/credit or a single amount column'); }
        Db::getInstance()->insert('pulse_acc_bank_statement', array('id_pulse_acc_bank_account' => (int) $idBankAccount, 'filename' => pSQL(Tools::substr($filename ? $filename : basename($path), 0, 255)), 'status' => 'imported', 'id_employee' => PulseAccService::emp(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), true);
        $idS = (int) Db::getInstance()->Insert_ID();
        $n = 0; $in = 0; $out = 0; $from = null; $to = null; $lastBalance = null; $firstBalance = null;
        while (($r = fgetcsv($fh, 0, $sep)) !== false) {
            if (count($r) === 1 && trim((string) $r[0]) === '') { continue; }
            $v = self::pick($r, $cols);
            $d = self::date($v['txn_date']);
            if (!$d) { continue; }
            $mi = self::money($v['money_in']); $mo = self::money($v['money_out']);
            if ($mi == 0 && $mo == 0 && $v['amount'] !== '') { $a = self::money($v['amount']); if ($a >= 0) { $mi = $a; } else { $mo = abs($a); } }
            if ($mi == 0 && $mo == 0) { continue; }
            $bal = $v['balance'] !== '' ? self::money($v['balance']) : null;
            if ($firstBalance === null && $bal !== null) { $firstBalance = round($bal - $mi + $mo, 2); }
            if ($bal !== null) { $lastBalance = $bal; }
            Db::getInstance()->insert('pulse_acc_bank_line', array(
                'id_pulse_acc_bank_statement' => $idS, 'id_pulse_acc_bank_account' => (int) $idBankAccount, 'txn_date' => pSQL($d),
                'value_date' => $v['value_date'] !== '' && self::date($v['value_date']) ? pSQL(self::date($v['value_date'])) : null,
                'description' => pSQL(Tools::substr($v['description'], 0, 255)), 'reference' => pSQL(Tools::substr($v['reference'], 0, 96)),
                'money_in' => $mi, 'money_out' => $mo, 'balance' => $bal, 'match_state' => 'unmatched',
                'raw' => pSQL(Tools::substr(implode(' | ', $r), 0, 1000), true), 'date_add' => date('Y-m-d H:i:s'),
            ), true);
            $n++; $in += $mi; $out += $mo;
            $from = ($from === null || $d < $from) ? $d : $from;
            $to = ($to === null || $d > $to) ? $d : $to;
        }
        fclose($fh);
        Db::getInstance()->update('pulse_acc_bank_statement', array('rows_total' => $n, 'rows_unmatched' => $n, 'total_in' => round($in, 2), 'total_out' => round($out, 2), 'period_from' => $from ? pSQL($from) : null, 'period_to' => $to ? pSQL($to) : null, 'opening_balance' => $firstBalance, 'closing_balance' => $lastBalance, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_bank_statement='.(int) $idS);
        self::autoMatch($idS);
        PulseCoreService::audit('pulseaccounts', 'bank_import', array('bank' => $ba['code'], 'rows' => $n, 'in' => round($in, 2), 'out' => round($out, 2)), 'pulse_acc_bank_statement', $idS);
        return $idS;
    }

    /**
     * Auto-match: for each unmatched statement row find a posted journal line on this bank's GL account
     * with the same amount and side, inside the date window, preferring a reference the narration mentions.
     * A journal line already claimed by another statement row is never matched twice.
     */
    public static function autoMatch($idStatement)
    {
        $s = self::statement($idStatement);
        if (!$s) { return array('matched' => 0, 'unmatched' => 0); }
        $window = (int) Configuration::get('PULSE_ACC_BANK_MATCH_DAYS'); $window = $window > 0 ? $window : 3;
        $matched = 0; $unmatched = 0;
        foreach (self::lines($idStatement, 'unmatched') as $l) {
            $amount = round((float) $l['money_in'] > 0 ? (float) $l['money_in'] : (float) $l['money_out'], 2);
            $isIn = (float) $l['money_in'] > 0;
            $tokens = self::tokens($l['reference'].' '.$l['description']);
            $cands = Db::getInstance()->executeS('SELECT jl.id_pulse_acc_journal_line, jl.id_pulse_acc_journal, jl.memo, jl.business_date, j.journal_no, j.reference
                FROM `'._DB_PREFIX_.'pulse_acc_journal_line` jl INNER JOIN `'._DB_PREFIX_.'pulse_acc_journal` j ON j.id_pulse_acc_journal=jl.id_pulse_acc_journal
                WHERE jl.posted=1 AND jl.account_code="'.pSQL($s['account_code']).'" AND ABS('.($isIn ? 'jl.debit' : 'jl.credit').' - '.$amount.')<0.01 AND '.($isIn ? 'jl.credit' : 'jl.debit').'<0.01
                AND jl.business_date BETWEEN DATE_SUB("'.pSQL($l['txn_date']).'", INTERVAL '.$window.' DAY) AND DATE_ADD("'.pSQL($l['txn_date']).'", INTERVAL '.$window.' DAY)
                AND jl.id_pulse_acc_journal_line NOT IN (SELECT COALESCE(id_pulse_acc_journal_line,0) FROM `'._DB_PREFIX_.'pulse_acc_bank_line` WHERE id_pulse_acc_journal_line IS NOT NULL)
                ORDER BY ABS(DATEDIFF(jl.business_date,"'.pSQL($l['txn_date']).'")) LIMIT 20');
            if (!$cands) { $unmatched++; continue; }
            $best = null; $bestScore = -1;
            foreach ($cands as $c) {
                $score = 0;
                $hay = Tools::strtolower($c['memo'].' '.$c['reference'].' '.$c['journal_no']);
                foreach ($tokens as $t) { if ($t !== '' && strpos($hay, $t) !== false) { $score += 2; } }
                $score -= abs((strtotime($c['business_date']) - strtotime($l['txn_date'])) / 86400);
                if ($score > $bestScore) { $bestScore = $score; $best = $c; }
            }
            if ($best === null) { $unmatched++; continue; }
            $exact = $bestScore >= 2;
            Db::getInstance()->update('pulse_acc_bank_line', array(
                'match_state' => $exact ? 'auto' : 'unmatched', 'id_pulse_acc_journal' => $exact ? (int) $best['id_pulse_acc_journal'] : null,
                'id_pulse_acc_journal_line' => $exact ? (int) $best['id_pulse_acc_journal_line'] : null,
                'match_note' => pSQL($exact ? 'Matched '.$best['journal_no'].' on amount and reference' : 'Amount matches '.$best['journal_no'].' but no reference in the narration — confirm by hand'),
            ), 'id_pulse_acc_bank_line='.(int) $l['id_pulse_acc_bank_line']);
            if ($exact) { $matched++; } else { $unmatched++; }
        }
        $totals = Db::getInstance()->getRow('SELECT SUM(match_state IN ("auto","manual","created")) m, SUM(match_state="unmatched") u FROM `'._DB_PREFIX_.'pulse_acc_bank_line` WHERE id_pulse_acc_bank_statement='.(int) $idStatement);
        Db::getInstance()->update('pulse_acc_bank_statement', array('rows_matched' => (int) $totals['m'], 'rows_unmatched' => (int) $totals['u'], 'status' => (int) $totals['u'] === 0 ? 'reconciled' : 'imported', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_bank_statement='.(int) $idStatement);
        return array('matched' => $matched, 'unmatched' => $unmatched);
    }

    /** Match a statement row to a journal by hand (the accountant found the pair the matcher missed). */
    public static function matchManual($idLine, $idJournal)
    {
        $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_bank_line` WHERE id_pulse_acc_bank_line='.(int) $idLine);
        if (!$l) { throw new PrestaShopException('Unknown statement line'); }
        $ba = PulseAccService::bankAccount((int) $l['id_pulse_acc_bank_account']);
        $jl = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE id_pulse_acc_journal='.(int) $idJournal.' AND account_code="'.pSQL($ba['account_code']).'" LIMIT 1');
        if (!$jl) { throw new PrestaShopException('That journal has no line on '.$ba['account_code'].' — it cannot be this bank movement'); }
        Db::getInstance()->update('pulse_acc_bank_line', array('match_state' => 'manual', 'id_pulse_acc_journal' => (int) $idJournal, 'id_pulse_acc_journal_line' => (int) $jl['id_pulse_acc_journal_line'], 'match_note' => pSQL('Matched by hand')), 'id_pulse_acc_bank_line='.(int) $idLine);
        self::refreshCounts((int) $l['id_pulse_acc_bank_statement']);
        return true;
    }

    public static function unmatch($idLine)
    {
        $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_bank_line` WHERE id_pulse_acc_bank_line='.(int) $idLine);
        if (!$l) { return false; }
        Db::getInstance()->update('pulse_acc_bank_line', array('match_state' => 'unmatched', 'id_pulse_acc_journal' => null, 'id_pulse_acc_journal_line' => null, 'match_note' => null), 'id_pulse_acc_bank_line='.(int) $idLine, 0, true);
        self::refreshCounts((int) $l['id_pulse_acc_bank_statement']);
        return true;
    }

    public static function ignoreLine($idLine, $note)
    {
        $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_bank_line` WHERE id_pulse_acc_bank_line='.(int) $idLine);
        if (!$l) { return false; }
        Db::getInstance()->update('pulse_acc_bank_line', array('match_state' => 'ignored', 'match_note' => pSQL(Tools::substr($note, 0, 255))), 'id_pulse_acc_bank_line='.(int) $idLine);
        self::refreshCounts((int) $l['id_pulse_acc_bank_statement']);
        return true;
    }

    /**
     * Create the missing journal straight from a statement row — bank charges, interest, a transfer the
     * hotel never recorded. The account the other side goes to is picked by the accountant.
     */
    public static function createFromLine($idLine, $accountCode, $memo = '')
    {
        $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_bank_line` WHERE id_pulse_acc_bank_line='.(int) $idLine);
        if (!$l) { throw new PrestaShopException('Unknown statement line'); }
        if ($l['match_state'] !== 'unmatched') { throw new PrestaShopException('That line is already '.$l['match_state']); }
        $ba = PulseAccService::bankAccount((int) $l['id_pulse_acc_bank_account']);
        $in = round((float) $l['money_in'], 2); $out = round((float) $l['money_out'], 2);
        $amount = $in > 0 ? $in : $out;
        $text = $memo !== '' ? $memo : ($l['description'] ? $l['description'] : 'Bank movement '.$l['txn_date']);
        $idJ = PulseAccJournal::post(array(
            'type' => $in > 0 ? 'receipt' : 'payment', 'source' => 'bank', 'source_ref' => 'line:'.(int) $idLine, 'business_date' => $l['txn_date'],
            'reference' => $l['reference'], 'memo' => Tools::substr($text, 0, 240),
            'lines' => array(
                array('account' => $ba['account_code'], 'debit' => $in > 0 ? $amount : 0, 'credit' => $out > 0 ? $amount : 0, 'memo' => $text, 'entity' => 'pulse_acc_bank_line', 'id_entity' => (int) $idLine),
                array('account' => $accountCode, 'debit' => $out > 0 ? $amount : 0, 'credit' => $in > 0 ? $amount : 0, 'memo' => $text, 'entity' => 'pulse_acc_bank_line', 'id_entity' => (int) $idLine),
            ),
        ));
        $jl = Db::getInstance()->getValue('SELECT id_pulse_acc_journal_line FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE id_pulse_acc_journal='.(int) $idJ.' AND account_code="'.pSQL($ba['account_code']).'" LIMIT 1');
        Db::getInstance()->update('pulse_acc_bank_line', array('match_state' => 'created', 'id_pulse_acc_journal' => (int) $idJ, 'id_pulse_acc_journal_line' => (int) $jl, 'match_note' => pSQL('Journal created from the statement')), 'id_pulse_acc_bank_line='.(int) $idLine);
        self::refreshCounts((int) $l['id_pulse_acc_bank_statement']);
        return $idJ;
    }

    protected static function refreshCounts($idStatement)
    {
        $t = Db::getInstance()->getRow('SELECT SUM(match_state IN ("auto","manual","created","ignored")) m, SUM(match_state="unmatched") u FROM `'._DB_PREFIX_.'pulse_acc_bank_line` WHERE id_pulse_acc_bank_statement='.(int) $idStatement);
        Db::getInstance()->update('pulse_acc_bank_statement', array('rows_matched' => (int) $t['m'], 'rows_unmatched' => (int) $t['u'], 'status' => (int) $t['u'] === 0 ? 'reconciled' : 'imported', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_bank_statement='.(int) $idStatement);
    }

    /**
     * The reconciliation itself: our ledger balance, the statement balance, and both sides of the
     * difference — statement rows we have no journal for, and journals the bank has not shown yet.
     */
    public static function reconciliation($idStatement)
    {
        $s = self::statement($idStatement);
        if (!$s) { return null; }
        $ba = PulseAccService::bankAccount((int) $s['id_pulse_acc_bank_account']);
        $glBalance = round((float) $ba['opening_balance'] + (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(debit-credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE posted=1 AND account_code="'.pSQL($ba['account_code']).'"'.($s['period_to'] ? ' AND business_date<="'.pSQL($s['period_to']).'"' : '')), 2);
        $unmatchedLines = self::lines($idStatement, 'unmatched');
        $outstanding = $s['period_from'] ? Db::getInstance()->executeS('SELECT jl.*, j.journal_no, j.memo journal_memo FROM `'._DB_PREFIX_.'pulse_acc_journal_line` jl INNER JOIN `'._DB_PREFIX_.'pulse_acc_journal` j ON j.id_pulse_acc_journal=jl.id_pulse_acc_journal
            WHERE jl.posted=1 AND jl.account_code="'.pSQL($ba['account_code']).'" AND jl.business_date BETWEEN "'.pSQL($s['period_from']).'" AND "'.pSQL($s['period_to']).'"
            AND jl.id_pulse_acc_journal_line NOT IN (SELECT COALESCE(id_pulse_acc_journal_line,0) FROM `'._DB_PREFIX_.'pulse_acc_bank_line` WHERE id_pulse_acc_bank_statement='.(int) $idStatement.' AND id_pulse_acc_journal_line IS NOT NULL)
            ORDER BY jl.business_date LIMIT 500') : array();
        $unpresented = 0; $undeposited = 0;
        foreach ($outstanding as $o) { $unpresented += (float) $o['credit']; $undeposited += (float) $o['debit']; }
        return array(
            'statement' => $s, 'bank_account' => $ba, 'gl_balance' => $glBalance, 'statement_balance' => $s['closing_balance'] !== null ? round((float) $s['closing_balance'], 2) : null,
            'difference' => $s['closing_balance'] !== null ? round($glBalance - (float) $s['closing_balance'], 2) : null,
            'unmatched_lines' => $unmatchedLines, 'outstanding' => $outstanding,
            'unpresented' => round($unpresented, 2), 'undeposited' => round($undeposited, 2),
        );
    }

    /** Every unmatched row across every statement — the report the auditor asks for. */
    public static function unreconciled($limit = 500)
    {
        return Db::getInstance()->executeS('SELECT l.*, b.code bank_code, b.name bank_name, s.filename FROM `'._DB_PREFIX_.'pulse_acc_bank_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_bank_account` b ON b.id_pulse_acc_bank_account=l.id_pulse_acc_bank_account INNER JOIN `'._DB_PREFIX_.'pulse_acc_bank_statement` s ON s.id_pulse_acc_bank_statement=l.id_pulse_acc_bank_statement WHERE l.match_state="unmatched" ORDER BY l.txn_date DESC LIMIT '.(int) $limit);
    }

    public static function removeStatement($id)
    {
        Db::getInstance()->delete('pulse_acc_bank_line', 'id_pulse_acc_bank_statement='.(int) $id);
        Db::getInstance()->delete('pulse_acc_bank_statement', 'id_pulse_acc_bank_statement='.(int) $id);
        return true;
    }

    /* ---------------- petty cash / imprest ---------------- */

    public static function pettyBook($idBankAccount, $from = null, $to = null, $limit = 300)
    {
        $from = $from ? $from : date('Y-m-01');
        $to = $to ? $to : date('Y-m-d');
        return Db::getInstance()->executeS('SELECT p.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_acc_petty_cash` p LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=p.id_employee WHERE p.id_pulse_acc_bank_account='.(int) $idBankAccount.' AND p.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY p.business_date, p.id_pulse_acc_petty_cash LIMIT '.(int) $limit);
    }

    public static function pettyBalance($idBankAccount)
    {
        $ba = PulseAccService::bankAccount((int) $idBankAccount);
        if (!$ba) { return 0; }
        return round((float) $ba['opening_balance'] + (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(debit-credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE posted=1 AND account_code="'.pSQL($ba['account_code']).'"'), 2);
    }

    /**
     * A movement in the imprest box. float_in / reimburse top the box up from the bank;
     * expense and variance take money out; return sends it back to the bank.
     */
    public static function pettyMove($idBankAccount, $type, $amount, $description, array $x = array())
    {
        $ba = PulseAccService::bankAccount((int) $idBankAccount);
        if (!$ba) { throw new PrestaShopException('Unknown petty cash account'); }
        $amount = round(abs((float) $amount), 2);
        if ($amount < 0.005) { throw new PrestaShopException('Amount must be positive'); }
        $date = !empty($x['business_date']) ? $x['business_date'] : PulseAccService::bd();
        $inflow = in_array($type, array('float_in', 'reimburse'));
        $signed = $inflow ? $amount : -$amount;
        $counter = isset($x['account_code']) && $x['account_code'] ? $x['account_code'] : ($inflow ? Configuration::get('PULSE_ACC_DEFAULT_BANK') : '7120');
        if ($type === 'variance') { $counter = isset($x['account_code']) && $x['account_code'] ? $x['account_code'] : '7120'; }
        $lines = array(
            array('account' => $ba['account_code'], 'debit' => $inflow ? $amount : 0, 'credit' => $inflow ? 0 : $amount, 'memo' => Tools::substr($description, 0, 240), 'cost_centre' => isset($x['cost_centre']) ? $x['cost_centre'] : 'general'),
            array('account' => $counter, 'debit' => $inflow ? 0 : $amount, 'credit' => $inflow ? $amount : 0, 'memo' => Tools::substr($description, 0, 240), 'cost_centre' => isset($x['cost_centre']) ? $x['cost_centre'] : 'general'),
        );
        $idJ = PulseAccJournal::post(array('type' => $inflow ? 'receipt' : 'payment', 'source' => 'bank', 'source_ref' => null, 'business_date' => $date, 'reference' => isset($x['reference']) ? $x['reference'] : '', 'memo' => 'Petty cash — '.Tools::substr($description, 0, 200), 'lines' => $lines));
        $balance = self::pettyBalance($idBankAccount);
        Db::getInstance()->insert('pulse_acc_petty_cash', array(
            'id_pulse_acc_bank_account' => (int) $idBankAccount, 'type' => pSQL($type), 'business_date' => pSQL($date),
            'description' => pSQL(Tools::substr($description, 0, 255)), 'amount' => $signed, 'balance_after' => $balance,
            'reference' => pSQL(isset($x['reference']) ? Tools::substr($x['reference'], 0, 64) : ''), 'id_pulse_cashier_session' => !empty($x['id_pulse_cashier_session']) ? (int) $x['id_pulse_cashier_session'] : null,
            'id_pulse_expense' => !empty($x['id_pulse_expense']) ? (int) $x['id_pulse_expense'] : null, 'id_pulse_acc_journal' => (int) $idJ,
            'id_employee' => PulseAccService::emp(), 'date_add' => date('Y-m-d H:i:s'),
        ), true);
        PulseCoreService::audit('pulseaccounts', 'petty_cash', array('type' => $type, 'amount' => $signed, 'balance' => $balance), 'pulse_acc_bank_account', (int) $idBankAccount);
        return (int) Db::getInstance()->Insert_ID();
    }

    /**
     * Front-office cashier sessions and their variances, so the imprest book can absorb a short/over
     * drawer instead of it quietly disappearing. Sessions already carried into the book are flagged.
     */
    public static function cashierSessions($from = null, $to = null)
    {
        if (!PulseAccService::fd() || !PulseAccService::tableExists('pulse_cashier_session')) { return array(); }
        $from = $from ? $from : date('Y-m-01');
        $to = $to ? $to : date('Y-m-d');
        return Db::getInstance()->executeS('SELECT s.*, CONCAT(e.firstname," ",e.lastname) cashier,
            (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_petty_cash` p WHERE p.id_pulse_cashier_session=s.id_pulse_cashier_session) in_book
            FROM `'._DB_PREFIX_.'pulse_cashier_session` s LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=s.id_employee
            WHERE s.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY s.business_date DESC, s.id_pulse_cashier_session DESC LIMIT 200');
    }

    /** Post a cashier's counted variance into the imprest book (short = expense, over = other income). */
    public static function postSessionVariance($idSession, $idBankAccount)
    {
        $s = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_cashier_session` WHERE id_pulse_cashier_session='.(int) $idSession);
        if (!$s) { throw new PrestaShopException('Unknown cashier session'); }
        if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_petty_cash` WHERE id_pulse_cashier_session='.(int) $idSession)) { throw new PrestaShopException('That session is already in the book'); }
        $var = round((float) $s['variance'], 2);
        if (abs($var) < 0.005) { throw new PrestaShopException('That session balanced — nothing to post'); }
        $desc = 'Cashier session '.(int) $idSession.' '.($var < 0 ? 'short' : 'over').' on '.$s['business_date'];
        return self::pettyMove($idBankAccount, 'variance', abs($var), $desc, array('business_date' => $s['business_date'], 'id_pulse_cashier_session' => (int) $idSession, 'account_code' => $var < 0 ? '7120' : '4400', 'reference' => 'SESSION-'.(int) $idSession));
    }

    /* ---------------- CSV helpers ---------------- */

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
        foreach (array('txn_date', 'value_date', 'description', 'reference', 'money_in', 'money_out', 'amount', 'balance') as $f) { $o[$f] = isset($cols[$f]) && isset($row[$cols[$f]]) ? trim((string) $row[$cols[$f]]) : ''; }
        return $o;
    }

    protected static function money($v) { $v = preg_replace('/[^0-9.\-]/', '', (string) $v); return $v === '' || $v === '-' ? 0 : round((float) $v, 2); }

    /** Nigerian statements use dd/mm/yyyy far more often than the American order — read it that way. */
    protected static function date($v)
    {
        $v = trim((string) $v);
        if ($v === '') { return null; }
        if (preg_match('#^(\d{4})[-/](\d{1,2})[-/](\d{1,2})#', $v, $m)) { return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]); }
        if (preg_match('#^(\d{1,2})[-/](\d{1,2})[-/](\d{2,4})#', $v, $m)) { $y = (int) $m[3]; if ($y < 100) { $y += 2000; } return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]); }
        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : null;
    }
}
