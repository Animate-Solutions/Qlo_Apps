<?php
/**
 * Physical bank POS terminals (GTBank, Moniepoint, OPay, Interswitch…).
 * Two working modes, because in Nigeria both are real:
 *   claim  — a small terminal app polls this queue, takes the amount to the PAX/telpo device and posts the RRN back;
 *   manual — nobody polls anything, the cashier swipes and keys the RRN, last-4 and auth code off the slip.
 * Either way the desk or POS pushes a request and polls for the result.
 */
class PulsePayTerminal
{
    public static function terminals($activeOnly = true) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_terminal`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY station, code'); }
    public static function terminal($code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_terminal` WHERE code="'.pSQL($code).'" OR id_pulse_pay_terminal='.(int) $code); }

    /** Push an amount to a terminal. Returns the request row including its reference for polling. */
    public static function request(array $d)
    {
        $amount = round((float) $d['amount'], 2);
        if ($amount <= 0) { throw new PrestaShopException('Terminal amount must be greater than zero'); }
        $t = !empty($d['terminal']) ? self::terminal($d['terminal']) : null;
        $mins = (int) Configuration::get('PULSE_PAY_TERMINAL_TIMEOUT_MIN') ?: 10;
        $tx = PulsePayService::createTx(array(
            'gateway' => 'manual', 'type' => 'charge', 'purpose' => !empty($d['id_pulse_pos_check']) ? 'pos' : 'folio', 'channel' => 'terminal', 'method' => 'card',
            'amount' => $amount, 'state' => 'awaiting_confirmation', 'id_customer' => isset($d['id_customer']) ? $d['id_customer'] : null,
            'id_htl_booking' => isset($d['id_htl_booking']) ? $d['id_htl_booking'] : null, 'id_pulse_folio' => isset($d['id_pulse_folio']) ? $d['id_pulse_folio'] : null,
            'id_pulse_pos_check' => isset($d['id_pulse_pos_check']) ? $d['id_pulse_pos_check'] : null,
            'description' => isset($d['description']) ? $d['description'] : 'Bank POS terminal charge', 'ref_prefix' => 'TRM',
        ));
        Db::getInstance()->insert('pulse_pay_terminal_request', array(
            'id_pulse_pay_terminal' => $t ? (int) $t['id_pulse_pay_terminal'] : null, 'id_pulse_pay_transaction' => (int) $tx['id_pulse_pay_transaction'],
            'reference' => pSQL($tx['reference']), 'amount' => $amount, 'station' => pSQL(isset($d['station']) ? $d['station'] : ($t ? $t['station'] : '')),
            'requested_by' => PulsePayService::emp(), 'source' => pSQL(isset($d['source']) ? $d['source'] : 'desk'),
            'id_pulse_pos_check' => isset($d['id_pulse_pos_check']) ? (int) $d['id_pulse_pos_check'] : null, 'id_htl_booking' => isset($d['id_htl_booking']) ? (int) $d['id_htl_booking'] : null,
            'id_pulse_folio' => isset($d['id_pulse_folio']) ? (int) $d['id_pulse_folio'] : null, 'auto_settle' => (int) !empty($d['auto_settle']),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+'.$mins.' minutes')), 'business_date' => pSQL(PulsePayService::bd()), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::event('actionPulsePayTerminalRequest', array('id_request' => $id, 'reference' => $tx['reference'], 'amount' => $amount, 'terminal' => $t ? $t['code'] : null));
        return array('id_request' => $id, 'reference' => $tx['reference'], 'amount' => $amount, 'status' => 'queued', 'terminal' => $t ? $t['code'] : null, 'expires_at' => date('Y-m-d H:i:s', strtotime('+'.$mins.' minutes')));
    }

    /** A terminal app takes the next queued job for its device (mode=claim). */
    public static function claim($terminalCode)
    {
        $t = self::terminal($terminalCode);
        if (!$t) { throw new PrestaShopException('Unknown terminal '.$terminalCode); }
        Db::getInstance()->update('pulse_pay_terminal', array('last_seen' => date('Y-m-d H:i:s')), 'id_pulse_pay_terminal='.(int) $t['id_pulse_pay_terminal']);
        $r = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_terminal_request` WHERE status="queued" AND (id_pulse_pay_terminal='.(int) $t['id_pulse_pay_terminal'].' OR id_pulse_pay_terminal IS NULL) AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY id_pulse_pay_terminal_request LIMIT 1');
        if (!$r) { return null; }
        // the status="queued" in the WHERE is the claim itself: two devices polling at once, only one gets the job
        Db::getInstance()->update('pulse_pay_terminal_request', array('status' => 'claimed', 'id_pulse_pay_terminal' => (int) $t['id_pulse_pay_terminal'], 'claimed_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pay_terminal_request='.(int) $r['id_pulse_pay_terminal_request'].' AND status="queued"');
        if (!Db::getInstance()->Affected_Rows()) { return null; }
        return array('id_request' => (int) $r['id_pulse_pay_terminal_request'], 'reference' => $r['reference'], 'amount' => (float) $r['amount'], 'currency' => PulsePayService::currency());
    }

    /**
     * Post the outcome — from the terminal app or from a cashier keying the slip.
     * $d: approved, rrn, auth_code, card_last4, card_brand, message.
     */
    public static function result($reference, array $d)
    {
        $r = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_terminal_request` WHERE reference="'.pSQL($reference).'"');
        if (!$r) { throw new PrestaShopException('Unknown terminal request '.$reference); }
        if (in_array($r['status'], array('approved', 'declined', 'cancelled'))) { return self::poll($reference); }
        $approved = !empty($d['approved']);
        $rrn = preg_replace('/[^A-Za-z0-9-]/', '', isset($d['rrn']) ? $d['rrn'] : '');
        if ($approved && $rrn === '') { throw new PrestaShopException('An approved terminal charge needs the RRN from the slip'); }
        Db::getInstance()->update('pulse_pay_terminal_request', array(
            'status' => $approved ? 'approved' : 'declined', 'answered_at' => date('Y-m-d H:i:s'), 'rrn' => pSQL($rrn),
            'auth_code' => pSQL(preg_replace('/[^A-Za-z0-9]/', '', isset($d['auth_code']) ? $d['auth_code'] : '')), 'card_last4' => pSQL(Tools::substr(preg_replace('/[^0-9]/', '', isset($d['card_last4']) ? $d['card_last4'] : ''), -4)),
            'card_brand' => pSQL(Tools::substr(isset($d['card_brand']) ? $d['card_brand'] : '', 0, 24)), 'response_message' => pSQL(Tools::substr(isset($d['message']) ? $d['message'] : ($approved ? 'Approved' : 'Declined'), 0, 128)), 'date_upd' => date('Y-m-d H:i:s'),
        ), 'id_pulse_pay_terminal_request='.(int) $r['id_pulse_pay_terminal_request'].' AND status IN ("queued","claimed","expired")');
        // the guarded UPDATE is the answer lock: a second result racing this one changes nothing and just reads the outcome back
        if (!Db::getInstance()->Affected_Rows()) { return self::poll($reference); }
        $tx = PulsePayService::tx($reference);
        if ($tx) {
            $a = PulsePayService::adapter('manual');
            $res = $approved ? $a->confirm($tx, array('rrn' => $rrn, 'auth_code' => isset($d['auth_code']) ? $d['auth_code'] : '', 'card_last4' => isset($d['card_last4']) ? $d['card_last4'] : '', 'card_brand' => isset($d['card_brand']) ? $d['card_brand'] : '', 'bank' => isset($d['bank']) ? $d['bank'] : '', 'method' => 'card', 'amount' => $r['amount']))
                : array('ok' => false, 'state' => 'failed', 'error' => isset($d['message']) ? $d['message'] : 'Declined at the terminal');
            $tx = PulsePayService::applyResult($tx, $res);
            if ($approved && $r['auto_settle']) { PulsePayService::postToLedger($tx, 'capture', (float) $r['amount']); }
        }
        PulseCoreService::audit('pulsepayments', 'terminal_result', array('reference' => $reference, 'approved' => $approved, 'rrn' => $rrn), 'pulse_pay_terminal_request', (int) $r['id_pulse_pay_terminal_request']);
        return self::poll($reference);
    }

    /** What the requester polls for. */
    public static function poll($reference)
    {
        $r = Db::getInstance()->getRow('SELECT r.*, t.code terminal_code, t.label terminal_label FROM `'._DB_PREFIX_.'pulse_pay_terminal_request` r LEFT JOIN `'._DB_PREFIX_.'pulse_pay_terminal` t ON t.id_pulse_pay_terminal=r.id_pulse_pay_terminal WHERE r.reference="'.pSQL($reference).'"');
        if (!$r) { return array('ok' => false, 'error' => 'Unknown terminal request'); }
        if ($r['status'] === 'queued' && $r['expires_at'] && strtotime($r['expires_at']) < time()) { Db::getInstance()->update('pulse_pay_terminal_request', array('status' => 'expired', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pay_terminal_request='.(int) $r['id_pulse_pay_terminal_request']); $r['status'] = 'expired'; }
        return array('ok' => true, 'reference' => $r['reference'], 'status' => $r['status'], 'amount' => (float) $r['amount'], 'rrn' => $r['rrn'], 'auth_code' => $r['auth_code'],
            'card_last4' => $r['card_last4'], 'card_brand' => $r['card_brand'], 'message' => $r['response_message'], 'terminal' => $r['terminal_code'], 'settled' => (int) $r['auto_settle'] && $r['status'] === 'approved');
    }

    public static function cancel($reference, $reason = '') { Db::getInstance()->update('pulse_pay_terminal_request', array('status' => 'cancelled', 'response_message' => pSQL(Tools::substr($reason, 0, 128)), 'date_upd' => date('Y-m-d H:i:s')), 'reference="'.pSQL($reference).'" AND status IN ("queued","claimed")'); PulsePayService::voidTx($reference, $reason ?: 'Terminal request cancelled'); return true; }

    public static function queue($date = null) { $d = $date ? $date : PulsePayService::bd(); return Db::getInstance()->executeS('SELECT r.*, t.code terminal_code, t.label terminal_label FROM `'._DB_PREFIX_.'pulse_pay_terminal_request` r LEFT JOIN `'._DB_PREFIX_.'pulse_pay_terminal` t ON t.id_pulse_pay_terminal=r.id_pulse_pay_terminal WHERE r.business_date="'.pSQL($d).'" ORDER BY r.id_pulse_pay_terminal_request DESC LIMIT 100'); }

    /** Stop pretending a request that nobody answered is still live. */
    public static function expireStale()
    {
        $rows = Db::getInstance()->executeS('SELECT reference FROM `'._DB_PREFIX_.'pulse_pay_terminal_request` WHERE status IN ("queued","claimed") AND expires_at IS NOT NULL AND expires_at<NOW()');
        foreach ($rows as $r) { Db::getInstance()->update('pulse_pay_terminal_request', array('status' => 'expired', 'date_upd' => date('Y-m-d H:i:s')), 'reference="'.pSQL($r['reference']).'"'); Db::getInstance()->update('pulse_pay_transaction', array('state' => 'expired', 'date_upd' => date('Y-m-d H:i:s')), 'reference="'.pSQL($r['reference']).'" AND state="awaiting_confirmation"'); }
        return count($rows);
    }

    public static function save(array $d)
    {
        $id = (int) (isset($d['id']) ? $d['id'] : 0);
        $row = array('code' => pSQL(Tools::strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', $d['code']))), 'label' => pSQL($d['label']), 'bank' => pSQL(isset($d['bank']) ? $d['bank'] : ''), 'terminal_id' => pSQL(isset($d['terminal_id']) ? $d['terminal_id'] : ''), 'merchant_id' => pSQL(isset($d['merchant_id']) ? $d['merchant_id'] : ''), 'station' => pSQL(isset($d['station']) ? $d['station'] : ''), 'mode' => pSQL(isset($d['mode']) && $d['mode'] === 'claim' ? 'claim' : 'manual'), 'active' => (int) !empty($d['active']));
        if ($id) { Db::getInstance()->update('pulse_pay_terminal', $row, 'id_pulse_pay_terminal='.$id); } else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_pay_terminal', $row, true, true, Db::INSERT_IGNORE); }
        return true;
    }
}
