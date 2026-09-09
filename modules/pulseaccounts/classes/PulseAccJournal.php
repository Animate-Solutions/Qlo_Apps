<?php
/**
 * The journal engine. Everything that touches the general ledger goes through post():
 * it balances to the cent or nothing is written, it refuses closed periods, it refuses header accounts,
 * and the UNIQUE(source, source_ref) key means the same document can never post twice.
 * Posted journals are never deleted — reverse() writes a contra journal instead.
 */
class PulseAccJournal
{
    /**
     * Post (or draft) a journal.
     * @param array $d source, source_ref, business_date, memo, reference, type, status, allow_closed, lines[]
     *                 each line: account (code) or id_account, debit, credit, memo, department, cost_centre,
     *                 entity, id_entity, id_pulse_company, id_supplier, tax_code
     * @return int id of the journal (existing one when the document was already posted)
     */
    public static function post(array $d)
    {
        $source = isset($d['source']) ? $d['source'] : 'manual';
        $ref = isset($d['source_ref']) && $d['source_ref'] !== '' ? Tools::substr((string) $d['source_ref'], 0, 96) : null;
        if ($ref !== null && ($existing = self::bySource($source, $ref))) { return (int) $existing['id_pulse_acc_journal']; }
        $date = isset($d['business_date']) && $d['business_date'] ? Tools::substr((string) $d['business_date'], 0, 10) : PulseAccService::bd();
        $status = isset($d['status']) && $d['status'] === 'draft' ? 'draft' : 'posted';
        $period = empty($d['allow_closed']) && $status === 'posted' ? PulseAccService::assertPeriodOpen($date) : PulseAccService::period($date);
        if (empty($d['allow_closed']) && $status === 'draft') { PulseAccService::ensurePeriods($date, 1); }

        $lines = array(); $debit = 0; $credit = 0; $n = 0;
        foreach ((isset($d['lines']) ? $d['lines'] : array()) as $l) {
            $dr = round((float) (isset($l['debit']) ? $l['debit'] : 0), 2);
            $cr = round((float) (isset($l['credit']) ? $l['credit'] : 0), 2);
            if ($dr < 0) { $cr += -$dr; $dr = 0; }
            if ($cr < 0) { $dr += -$cr; $cr = 0; }
            $dr = round($dr, 2); $cr = round($cr, 2);
            if ($dr < 0.005 && $cr < 0.005) { continue; }
            $a = isset($l['id_account']) ? PulseAccService::accountById($l['id_account']) : PulseAccService::account(isset($l['account']) ? $l['account'] : '');
            if (!$a) { throw new PrestaShopException('Unknown account "'.(isset($l['account']) ? $l['account'] : '?').'" on '.$source.' '.$ref); }
            if ($a['is_header']) { throw new PrestaShopException('Account '.$a['code'].' is a heading — nothing may post to it'); }
            if (!$a['active']) { throw new PrestaShopException('Account '.$a['code'].' is inactive'); }
            $lines[] = array(
                'line_no' => ++$n, 'id_pulse_acc_account' => (int) $a['id_pulse_acc_account'], 'account_code' => pSQL($a['code']), 'account_name' => pSQL($a['name']),
                'debit' => $dr, 'credit' => $cr, 'memo' => pSQL(Tools::substr(isset($l['memo']) ? $l['memo'] : (isset($d['memo']) ? $d['memo'] : ''), 0, 255)),
                'department' => pSQL(isset($l['department']) ? Tools::substr($l['department'], 0, 32) : ''), 'cost_centre' => pSQL(isset($l['cost_centre']) ? Tools::substr($l['cost_centre'], 0, 32) : ''),
                'usali_dept' => pSQL($a['usali_dept']), 'entity' => !empty($l['entity']) ? pSQL(Tools::substr($l['entity'], 0, 32)) : null, 'id_entity' => !empty($l['id_entity']) ? (int) $l['id_entity'] : null,
                'id_pulse_company' => !empty($l['id_pulse_company']) ? (int) $l['id_pulse_company'] : null, 'id_supplier' => !empty($l['id_supplier']) ? (int) $l['id_supplier'] : null,
                'tax_code' => !empty($l['tax_code']) ? pSQL(Tools::substr($l['tax_code'], 0, 16)) : null,
                'business_date' => pSQL($date), 'period' => pSQL($period), 'posted' => $status === 'posted' ? 1 : 0,
            );
            $debit += $dr; $credit += $cr;
        }
        if (count($lines) < 2) { throw new PrestaShopException('A journal needs at least two lines ('.$source.' '.$ref.')'); }
        $debit = round($debit, 2); $credit = round($credit, 2);
        if (abs($debit - $credit) > 0.009) { throw new PrestaShopException('Journal does not balance: debits '.number_format($debit, 2).' vs credits '.number_format($credit, 2).' ('.$source.' '.$ref.')'); }

        $no = PulseAccService::nextNo(self::prefix(isset($d['type']) ? $d['type'] : 'general'), 6);
        $ok = Db::getInstance()->insert('pulse_acc_journal', array(
            'journal_no' => pSQL($no), 'type' => pSQL(isset($d['type']) ? $d['type'] : 'general'), 'source' => pSQL($source), 'source_ref' => $ref !== null ? pSQL($ref) : null,
            'business_date' => pSQL($date), 'period' => pSQL($period), 'reference' => pSQL(Tools::substr(isset($d['reference']) ? $d['reference'] : '', 0, 64)),
            'memo' => pSQL(Tools::substr(isset($d['memo']) ? $d['memo'] : '', 0, 255)), 'status' => $status, 'total_debit' => $debit, 'total_credit' => $credit,
            'reverses' => !empty($d['reverses']) ? (int) $d['reverses'] : null, 'id_employee' => PulseAccService::emp(),
            'date_add' => date('Y-m-d H:i:s'), 'date_posted' => $status === 'posted' ? date('Y-m-d H:i:s') : null, 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        if (!$ok) {
            // lost a race on UNIQUE(source, source_ref): the other writer's journal is the right answer
            if ($ref !== null && ($existing = self::bySource($source, $ref))) { return (int) $existing['id_pulse_acc_journal']; }
            throw new PrestaShopException('Journal could not be written ('.$source.' '.$ref.')');
        }
        $id = (int) Db::getInstance()->Insert_ID();
        foreach ($lines as $l) { $l['id_pulse_acc_journal'] = $id; Db::getInstance()->insert('pulse_acc_journal_line', $l, true); }
        PulseCoreService::audit('pulseaccounts', 'journal_'.$status, array('no' => $no, 'source' => $source, 'ref' => $ref, 'debit' => $debit), 'pulse_acc_journal', $id);
        if ($status === 'posted') { PulseCoreService::event('actionPulseAccJournalPosted', array('id_journal' => $id, 'journal_no' => $no, 'source' => $source, 'amount' => $debit, 'business_date' => $date)); }
        return $id;
    }

    protected static function prefix($type)
    {
        $p = array('sales' => 'SJ', 'receipt' => 'CR', 'purchase' => 'PJ', 'payment' => 'CP', 'depreciation' => 'DP', 'closing' => 'CL', 'opening' => 'OB', 'fx' => 'FX', 'reversal' => 'RV', 'adjustment' => 'AJ');
        return isset($p[$type]) ? $p[$type] : 'GJ';
    }

    public static function bySource($source, $ref) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_journal` WHERE source="'.pSQL($source).'" AND source_ref="'.pSQL($ref).'"'); }
    public static function get($id) { $j = Db::getInstance()->getRow('SELECT j.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_acc_journal` j LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=j.id_employee WHERE j.id_pulse_acc_journal='.(int) $id); if ($j) { $j['lines'] = self::lines($id); } return $j; }
    public static function lines($id) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE id_pulse_acc_journal='.(int) $id.' ORDER BY line_no, id_pulse_acc_journal_line'); }

    public static function search(array $f = array(), $limit = 200)
    {
        $w = array('1');
        if (!empty($f['from'])) { $w[] = 'j.business_date>="'.pSQL($f['from']).'"'; }
        if (!empty($f['to'])) { $w[] = 'j.business_date<="'.pSQL($f['to']).'"'; }
        if (!empty($f['source'])) { $w[] = 'j.source="'.pSQL($f['source']).'"'; }
        if (!empty($f['status'])) { $w[] = 'j.status="'.pSQL($f['status']).'"'; }
        if (!empty($f['period'])) { $w[] = 'j.period="'.pSQL($f['period']).'"'; }
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w[] = '(j.journal_no LIKE "%'.$q.'%" OR j.memo LIKE "%'.$q.'%" OR j.reference LIKE "%'.$q.'%" OR j.source_ref LIKE "%'.$q.'%")'; }
        return Db::getInstance()->executeS('SELECT j.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_acc_journal` j LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=j.id_employee WHERE '.implode(' AND ', $w).' ORDER BY j.business_date DESC, j.id_pulse_acc_journal DESC LIMIT '.(int) $limit);
    }

    /** Post a draft journal (period is re-checked at this moment, not at the moment it was drafted). */
    public static function postDraft($id)
    {
        $j = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_journal` WHERE id_pulse_acc_journal='.(int) $id);
        if (!$j) { throw new PrestaShopException('Unknown journal'); }
        if ($j['status'] !== 'draft') { throw new PrestaShopException('Journal '.$j['journal_no'].' is already '.$j['status']); }
        PulseAccService::assertPeriodOpen($j['business_date']);
        if (abs((float) $j['total_debit'] - (float) $j['total_credit']) > 0.009) { throw new PrestaShopException('Journal '.$j['journal_no'].' does not balance'); }
        Db::getInstance()->update('pulse_acc_journal', array('status' => 'posted', 'date_posted' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_journal='.(int) $id);
        Db::getInstance()->update('pulse_acc_journal_line', array('posted' => 1), 'id_pulse_acc_journal='.(int) $id);
        PulseCoreService::audit('pulseaccounts', 'journal_post', array('no' => $j['journal_no']), 'pulse_acc_journal', (int) $id);
        PulseCoreService::event('actionPulseAccJournalPosted', array('id_journal' => (int) $id, 'journal_no' => $j['journal_no'], 'source' => $j['source'], 'amount' => (float) $j['total_debit'], 'business_date' => $j['business_date']));
        return true;
    }

    /** Delete a draft. A posted journal can only be reversed. */
    public static function deleteDraft($id)
    {
        $j = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_journal` WHERE id_pulse_acc_journal='.(int) $id);
        if (!$j) { return false; }
        if ($j['status'] !== 'draft') { throw new PrestaShopException('Only drafts can be deleted — reverse posted journal '.$j['journal_no'].' instead'); }
        Db::getInstance()->delete('pulse_acc_journal_line', 'id_pulse_acc_journal='.(int) $id);
        Db::getInstance()->delete('pulse_acc_journal', 'id_pulse_acc_journal='.(int) $id);
        PulseCoreService::audit('pulseaccounts', 'journal_draft_delete', array('no' => $j['journal_no']), 'pulse_acc_journal', (int) $id);
        return true;
    }

    /**
     * Reverse a posted journal with a mirror-image contra entry. The original stays exactly as it was;
     * $date lets you reverse into the current open period when the original month is already closed.
     */
    public static function reverse($id, $reason = '', $date = null)
    {
        $j = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_journal` WHERE id_pulse_acc_journal='.(int) $id);
        if (!$j) { throw new PrestaShopException('Unknown journal'); }
        if ($j['status'] !== 'posted') { throw new PrestaShopException('Journal '.$j['journal_no'].' is '.$j['status'].' — only posted journals can be reversed'); }
        $date = $date ? $date : (PulseAccService::periodOpen($j['business_date']) ? $j['business_date'] : PulseAccService::bd());
        $lines = array();
        foreach (self::lines($id) as $l) {
            $lines[] = array('account' => $l['account_code'], 'debit' => (float) $l['credit'], 'credit' => (float) $l['debit'], 'memo' => 'Reversal: '.$l['memo'],
                'department' => $l['department'], 'cost_centre' => $l['cost_centre'], 'entity' => $l['entity'], 'id_entity' => $l['id_entity'],
                'id_pulse_company' => $l['id_pulse_company'], 'id_supplier' => $l['id_supplier'], 'tax_code' => $l['tax_code']);
        }
        $idRev = self::post(array(
            'type' => 'reversal', 'source' => $j['source'], 'source_ref' => 'reverse:'.$j['journal_no'], 'business_date' => $date,
            'reference' => $j['journal_no'], 'memo' => 'Reversal of '.$j['journal_no'].($reason ? ' — '.$reason : ''), 'reverses' => (int) $id, 'lines' => $lines,
        ));
        // both journals stay live: the original keeps its period intact and the contra lands in its own.
        Db::getInstance()->update('pulse_acc_journal', array('status' => 'reversed', 'reversed_by' => (int) $idRev, 'reverse_reason' => pSQL(Tools::substr($reason, 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_journal='.(int) $id);
        PulseCoreService::audit('pulseaccounts', 'journal_reverse', array('no' => $j['journal_no'], 'reason' => $reason, 'reversal' => $idRev), 'pulse_acc_journal', (int) $id);
        return $idRev;
    }

    /**
     * Build a manual journal from the admin form. $rows come straight off the posting screen as
     * parallel arrays of account / debit / credit / memo.
     */
    public static function manual(array $d)
    {
        $lines = array();
        $accounts = isset($d['account']) ? (array) $d['account'] : array();
        foreach ($accounts as $i => $code) {
            if (trim((string) $code) === '') { continue; }
            $lines[] = array(
                'account' => $code, 'debit' => isset($d['debit'][$i]) ? $d['debit'][$i] : 0, 'credit' => isset($d['credit'][$i]) ? $d['credit'][$i] : 0,
                'memo' => isset($d['line_memo'][$i]) ? $d['line_memo'][$i] : (isset($d['memo']) ? $d['memo'] : ''),
                'cost_centre' => isset($d['cost_centre'][$i]) ? $d['cost_centre'][$i] : '', 'department' => isset($d['cost_centre'][$i]) ? $d['cost_centre'][$i] : '',
            );
        }
        return self::post(array(
            'type' => isset($d['type']) ? $d['type'] : 'general', 'source' => 'manual', 'source_ref' => null,
            'business_date' => isset($d['business_date']) ? $d['business_date'] : PulseAccService::bd(),
            'reference' => isset($d['reference']) ? $d['reference'] : '', 'memo' => isset($d['memo']) ? $d['memo'] : '',
            'status' => !empty($d['as_draft']) ? 'draft' : 'posted', 'lines' => $lines,
        ));
    }
}
