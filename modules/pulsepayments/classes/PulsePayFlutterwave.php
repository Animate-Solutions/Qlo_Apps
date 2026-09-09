<?php
/**
 * Flutterwave v3 adapter — /v3/payments (hosted checkout), /v3/transactions/:id/verify,
 * /v3/transactions/verify_by_reference, /v3/tokenized-charges, /v3/transactions/:id/refund.
 * Webhooks carry the flat secret hash in the verif-hash header (Flutterwave does not sign the body).
 */
class PulsePayFlutterwave extends PulsePayAdapter
{
    protected $code = 'flutterwave';

    protected function api() { return $this->base('https://api.flutterwave.com'); }
    protected function sk() { return (string) $this->cfg('secret_key'); }
    protected function headers() { return array('Authorization: Bearer '.$this->sk()); }
    protected function customer(array $tx) { return array('email' => !empty($tx['customer_email']) ? $tx['customer_email'] : (Configuration::get('PULSE_PAY_FALLBACK_EMAIL') ?: 'guest@hotel.local'), 'phonenumber' => (string) (isset($tx['customer_phone']) ? $tx['customer_phone'] : ''), 'name' => (string) (isset($tx['customer_name']) ? $tx['customer_name'] : 'Guest')); }

    public function paymentLink(array $tx, array $opts = array())
    {
        if (!$this->sk()) { return $this->fail('Flutterwave secret key not configured'); }
        $body = array(
            'tx_ref' => $tx['reference'], 'amount' => (string) round((float) $tx['amount'], 2), 'currency' => $tx['currency'],
            'redirect_url' => isset($opts['return_url']) ? $opts['return_url'] : null, 'customer' => $this->customer($tx),
            'customizations' => array('title' => Configuration::get('PS_SHOP_NAME'), 'description' => (string) $tx['description'], 'logo' => (string) $this->extra('logo_url', '')),
            'meta' => array('reference' => $tx['reference'], 'purpose' => $tx['purpose'], 'channel' => $tx['channel'], 'id_htl_booking' => (int) $tx['id_htl_booking'], 'id_pulse_folio' => (int) $tx['id_pulse_folio']),
        );
        if (!empty($opts['channels']) && is_array($opts['channels'])) { $body['payment_options'] = implode(',', $opts['channels']); }
        $r = $this->request('POST', $this->api().'/v3/payments', $body, $this->headers(), 'payments', $tx['reference']);
        if (!$r['ok'] || empty($r['json']['status']) || $r['json']['status'] !== 'success') { return $this->fail($this->err($r), $r['json']); }
        return array('ok' => true, 'state' => 'intent', 'redirect_url' => $r['json']['data']['link'], 'gateway_ref' => $tx['reference'], 'raw' => $r['json']);
    }

    /** Tokenised charge when a card token is on file, otherwise fall back to hosted checkout. */
    public function charge(array $tx, array $opts = array())
    {
        $token = isset($opts['token']) && $opts['token'] ? $opts['token'] : null;
        if (!$token) { return $this->paymentLink($tx, $opts); }
        $c = $this->customer($tx);
        $body = array('token' => $token, 'currency' => $tx['currency'], 'country' => (string) $this->extra('country', 'NG'), 'amount' => round((float) $tx['amount'], 2), 'email' => $c['email'], 'first_name' => $c['name'], 'tx_ref' => $tx['reference'], 'narration' => Tools::substr((string) $tx['description'], 0, 100));
        $r = $this->request('POST', $this->api().'/v3/tokenized-charges', $body, $this->headers(), 'tokenized_charge', $tx['reference']);
        if (!$r['ok'] || empty($r['json']['status']) || $r['json']['status'] !== 'success') { return $this->fail($this->err($r), $r['json']); }
        return $this->fromTransaction(isset($r['json']['data']) ? $r['json']['data'] : array());
    }

    /** No card hold on v3 — keep the token and debit the real amount at check-out. */
    public function authorize(array $tx, array $opts = array())
    {
        $token = isset($opts['token']) && $opts['token'] ? $opts['token'] : null;
        if (!$token) { return $this->fail('Flutterwave needs a saved card token to reserve a card — record a manual hold instead'); }
        $this->logLocal('authorize', $tx['reference'], array('mode' => 'deferred', 'amount' => $tx['amount']), array('held' => 'card token retained'));
        return array('ok' => true, 'state' => 'authorized', 'hold_type' => 'deferred', 'gateway_ref' => 'AUTH-'.$tx['reference'], 'auth_token' => $token, 'raw' => array('mode' => 'deferred_token'));
    }

    public function capture(array $tx, $amount, array $opts = array())
    {
        $token = isset($opts['token']) && $opts['token'] ? $opts['token'] : (isset($tx['auth_token']) ? $tx['auth_token'] : null);
        if (!$token) { return $this->fail('No stored card token to capture against'); }
        $ref = isset($opts['reference']) ? $opts['reference'] : $tx['reference'].'-C'.date('His');
        $sub = array_merge($tx, array('reference' => $ref, 'amount' => $amount));
        $out = $this->charge($sub, array('token' => $token));
        if (!empty($out['ok'])) { $out['capture_reference'] = $ref; }
        return $out;
    }

    public function void(array $tx, $reason = '') { $this->logLocal('void', $tx['reference'], array('reason' => $reason), array('released' => true)); return array('ok' => true, 'state' => 'voided', 'raw' => array('released' => 'deferred token discarded')); }

    public function refund(array $tx, $amount, $reason = '')
    {
        $id = $this->gatewayId($tx);
        if (!$id) { return $this->fail('No Flutterwave transaction id on this payment'); }
        $r = $this->request('POST', $this->api().'/v3/transactions/'.rawurlencode($id).'/refund', array('amount' => round((float) $amount, 2), 'comments' => Tools::substr((string) $reason, 0, 200)), $this->headers(), 'refund', $tx['reference'], false);
        if (!$r['ok'] || empty($r['json']['status']) || $r['json']['status'] !== 'success') { return $this->fail($this->err($r), $r['json']); }
        return array('ok' => true, 'state' => 'refunded', 'gateway_ref' => isset($r['json']['data']['id']) ? (string) $r['json']['data']['id'] : null, 'raw' => $r['json']);
    }

    /** Verify by numeric id when we have one, else by our own tx_ref — the redirect gives us both. */
    public function verify($reference, array $tx = array())
    {
        $id = $this->gatewayId($tx);
        $url = $id ? $this->api().'/v3/transactions/'.rawurlencode($id).'/verify' : $this->api().'/v3/transactions/verify_by_reference?tx_ref='.rawurlencode($reference);
        $r = $this->request('GET', $url, null, $this->headers(), 'verify', $reference);
        if (!$r['ok'] || empty($r['json']['status']) || $r['json']['status'] !== 'success') { return $this->fail($this->err($r), $r['json']); }
        return $this->fromTransaction(isset($r['json']['data']) ? $r['json']['data'] : array());
    }

    public function webhookVerify($rawBody, array $headers)
    {
        $secret = (string) $this->cfg('webhook_secret');
        $sent = isset($headers['verif-hash']) ? $headers['verif-hash'] : (isset($headers['verif_hash']) ? $headers['verif_hash'] : '');
        $ok = $secret !== '' && $sent !== '' && hash_equals($secret, $sent);
        $d = json_decode($rawBody, true);
        $data = isset($d['data']) ? $d['data'] : (is_array($d) ? $d : array());
        $ref = isset($data['tx_ref']) ? $data['tx_ref'] : (isset($data['txRef']) ? $data['txRef'] : null);
        return array('ok' => $ok, 'error' => $ok ? null : 'verif-hash mismatch', 'event_id' => isset($data['id']) ? 'flw-'.$data['id'].'-'.(isset($d['event']) ? $d['event'] : 'e') : Tools::substr(hash('sha256', $rawBody), 0, 64), 'event_type' => isset($d['event']) ? $d['event'] : (isset($d['event.type']) ? $d['event.type'] : 'charge.completed'), 'reference' => $ref, 'data' => $data, 'result' => $this->fromTransaction($data));
    }

    protected function gatewayId(array $tx) { if (!empty($tx['gateway_ref']) && preg_match('/^[0-9]+$/', $tx['gateway_ref'])) { return $tx['gateway_ref']; } if (!empty($tx['raw'])) { $r = json_decode($tx['raw'], true); if (isset($r['id']) && preg_match('/^[0-9]+$/', (string) $r['id'])) { return (string) $r['id']; } } return null; }

    /** Normalise a v3 transaction object. */
    protected function fromTransaction($d)
    {
        if (!is_array($d) || !$d) { return $this->fail('Empty transaction payload'); }
        $status = Tools::strtolower(isset($d['status']) ? $d['status'] : 'failed');
        $map = array('successful' => 'captured', 'success' => 'captured', 'pending' => 'intent', 'failed' => 'failed', 'cancelled' => 'voided');
        $card = isset($d['card']) ? $d['card'] : array();
        return array(
            'ok' => in_array($status, array('successful', 'success')), 'state' => isset($map[$status]) ? $map[$status] : 'failed',
            'gateway_ref' => isset($d['id']) ? (string) $d['id'] : (isset($d['tx_ref']) ? $d['tx_ref'] : null), 'gateway_id' => isset($d['id']) ? (string) $d['id'] : null,
            'amount' => isset($d['amount']) ? round((float) $d['amount'], 2) : null, 'fee' => isset($d['app_fee']) ? round((float) $d['app_fee'], 2) : null,
            'auth_code' => isset($d['flw_ref']) ? $d['flw_ref'] : null, 'auth_token' => isset($card['token']) ? $card['token'] : null,
            'card_last4' => isset($card['last_4digits']) ? $card['last_4digits'] : null, 'card_brand' => isset($card['type']) ? $card['type'] : (isset($d['payment_type']) ? $d['payment_type'] : null), 'bank' => isset($card['issuer']) ? $card['issuer'] : null,
            'method' => $this->methodFor(isset($d['payment_type']) ? $d['payment_type'] : 'card'), 'paid_at' => isset($d['created_at']) ? $d['created_at'] : null,
            'error' => in_array($status, array('successful', 'success')) ? null : (isset($d['processor_response']) ? $d['processor_response'] : $status), 'raw' => $d,
        );
    }

    protected function methodFor($t) { $m = array('card' => 'card', 'banktransfer' => 'transfer', 'bank transfer' => 'transfer', 'account' => 'transfer', 'ussd' => 'ussd', 'mobilemoney' => 'mobile_money', 'mobilemoneyghana' => 'mobile_money', 'nqr' => 'online'); $t = Tools::strtolower(str_replace('_', '', (string) $t)); return isset($m[$t]) ? $m[$t] : 'online'; }
    protected function err($r) { if (!empty($r['json']['message'])) { return $r['json']['message']; } return $r['error'] ? $r['error'] : 'Flutterwave call failed'; }
}
