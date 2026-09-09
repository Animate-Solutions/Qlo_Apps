<?php
/**
 * Webhook intake: verify the signature, store the event id so a replay is a no-op, then apply the outcome.
 * The guest closing the browser mid-redirect changes nothing — the webhook (or the sweep cron) still lands the money.
 */
class PulsePayWebhook
{
    /** Handle one delivery. Returns array(status, message) — the HTTP status the gateway should see. */
    public static function handle($gateway, $rawBody, array $headers, $remoteIp = null)
    {
        $a = PulsePayService::adapter($gateway);
        if (!$a) { return array(404, 'Unknown gateway'); }
        $v = $a->webhookVerify($rawBody, self::lowerKeys($headers));
        $eventId = !empty($v['event_id']) ? $v['event_id'] : Tools::substr(hash('sha256', $rawBody), 0, 64);
        $stored = Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_pay_event` (gateway, event_id, event_type, reference, signature_ok, payload, remote_ip, date_add) VALUES ("'.pSQL($gateway).'","'.pSQL($eventId).'","'.pSQL(isset($v['event_type']) ? $v['event_type'] : '').'","'.pSQL(isset($v['reference']) ? $v['reference'] : '').'",'.(int) !empty($v['ok']).',"'.pSQL(PulsePayAdapter::redact($rawBody), true).'","'.pSQL((string) $remoteIp).'","'.date('Y-m-d H:i:s').'")');
        $isNew = $stored && Db::getInstance()->Affected_Rows() > 0;
        $idEvent = (int) Db::getInstance()->Insert_ID();
        if (!$isNew) { return array(200, 'Duplicate event ignored'); }
        if (empty($v['ok'])) { self::finish($idEvent, 0, 'Signature check failed: '.(isset($v['error']) ? $v['error'] : '')); return array(401, 'Invalid signature'); }
        $ref = isset($v['reference']) ? $v['reference'] : null;
        if (!$ref) { self::finish($idEvent, 1, 'No reference in payload'); return array(200, 'No reference — nothing to do'); }
        $tx = PulsePayService::tx($ref);
        if (!$tx) { self::finish($idEvent, 1, 'Reference '.$ref.' is not ours'); return array(200, 'Unknown reference'); }
        // Interswitch and anything else without a signed body: never trust the payload, re-query the gateway.
        if (!empty($v['requery'])) { $r = PulsePayService::verify($tx['reference']); self::finish($idEvent, 1, 'Re-queried: '.(isset($r['state']) ? $r['state'] : 'unknown')); return array(200, 'OK'); }
        $type = isset($v['event_type']) ? $v['event_type'] : '';
        if (Tools::strpos($type, 'refund') !== false) { self::applyRefund($tx, $v); self::finish($idEvent, 1, 'Refund event applied'); return array(200, 'OK'); }
        if (Tools::strpos($type, 'dispute') !== false || Tools::strpos($type, 'chargeback') !== false) { PulsePayService::dispute(array('reference' => $tx['reference'], 'gateway' => $gateway, 'reason' => $type, 'amount' => isset($v['data']['amount']) ? $v['data']['amount'] : 0, 'gateway_ref' => $tx['gateway_ref'])); self::finish($idEvent, 1, 'Dispute logged'); return array(200, 'OK'); }
        $res = isset($v['result']) && is_array($v['result']) ? $v['result'] : array();
        if (empty($res['state'])) { $res = PulsePayService::verify($tx['reference']); self::finish($idEvent, 1, 'Verified: '.(isset($res['state']) ? $res['state'] : 'unknown')); return array(200, 'OK'); }
        $was = $tx['state'];
        $tx = PulsePayService::applyResult($tx, $res);
        if (in_array($tx['state'], array('captured', 'settled')) && !in_array($was, array('captured', 'settled'))) {
            PulsePayService::postToLedger($tx, 'capture', (float) $tx['amount_captured']);
            if ($tx['id_pulse_pay_link'] && ($l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_link` WHERE id_pulse_pay_link='.(int) $tx['id_pulse_pay_link']))) { PulsePayLink::credit($l, (float) $tx['amount_captured'], $tx); }
        }
        self::finish($idEvent, 1, 'Applied: '.$tx['state']);
        return array(200, 'OK');
    }

    protected static function applyRefund(array $tx, array $v)
    {
        $amount = isset($v['data']['amount']) ? round(((float) $v['data']['amount']) / ($tx['gateway'] === 'paystack' ? 100 : 1), 2) : (float) $tx['amount_captured'];
        $refunded = round((float) $tx['amount_refunded'] + $amount, 2);
        Db::getInstance()->update('pulse_pay_transaction', array('amount_refunded' => $refunded, 'state' => pSQL($refunded + 0.009 >= (float) $tx['amount_captured'] ? 'refunded' : 'partially_refunded'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pay_transaction='.(int) $tx['id_pulse_pay_transaction']);
        Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_pay_refund` (id_pulse_pay_transaction, reference, gateway, gateway_ref, amount, reason, status, business_date, date_add, date_upd) VALUES ('.(int) $tx['id_pulse_pay_transaction'].',"'.pSQL($tx['reference'].'-WHR').'","'.pSQL($tx['gateway']).'","'.pSQL((string) $tx['gateway_ref']).'",'.$amount.',"Refund notified by gateway","done","'.pSQL(PulsePayService::bd()).'","'.date('Y-m-d H:i:s').'","'.date('Y-m-d H:i:s').'")');
    }

    protected static function finish($idEvent, $handled, $result) { Db::getInstance()->update('pulse_pay_event', array('handled' => (int) $handled, 'result' => pSQL(Tools::substr($result, 0, 255))), 'id_pulse_pay_event='.(int) $idEvent); }

    /** PHP hands headers back in every possible case; normalise once. */
    public static function lowerKeys(array $h) { $o = array(); foreach ($h as $k => $v) { $o[Tools::strtolower(str_replace('_', '-', $k))] = $v; } return $o; }

    /** Read the incoming request headers whatever the SAPI. */
    public static function incomingHeaders()
    {
        $h = array();
        if (function_exists('getallheaders')) { $h = getallheaders(); }
        if (!$h) { foreach ($_SERVER as $k => $v) { if (Tools::substr($k, 0, 5) === 'HTTP_') { $h[str_replace('_', '-', Tools::substr($k, 5))] = $v; } } }
        return self::lowerKeys(is_array($h) ? $h : array());
    }

    public static function recent($limit = 100) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pay_event` ORDER BY id_pulse_pay_event DESC LIMIT '.(int) $limit); }
}
