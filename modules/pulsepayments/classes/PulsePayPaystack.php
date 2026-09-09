<?php
/**
 * Paystack adapter — transaction/initialize, transaction/verify, charge_authorization, refund.
 * Auth header: Authorization: Bearer sk_live_… ; webhooks signed HMAC-SHA512 of the raw body in x-paystack-signature.
 * Paystack has no card hold, so a "pre-authorisation" here is a reusable authorization code kept on file
 * (hold_type=deferred) and the money only moves at capture — which is exactly what a hotel check-in needs.
 */
class PulsePayPaystack extends PulsePayAdapter
{
    protected $code = 'paystack';

    protected function api() { return $this->base('https://api.paystack.co'); }
    protected function sk() { return (string) $this->cfg('secret_key'); }
    protected function headers() { return array('Authorization: Bearer '.$this->sk()); }
    protected function kobo($amount) { return (int) round(((float) $amount) * 100); }
    protected function naira($minor) { return round(((float) $minor) / 100, 2); }
    protected function email(array $tx) { return $tx && !empty($tx['customer_email']) ? $tx['customer_email'] : (Configuration::get('PULSE_PAY_FALLBACK_EMAIL') ?: 'guest@'.Tools::strtolower(preg_replace('/[^a-z0-9.]/i', '', Configuration::get('PS_SHOP_NAME')) ?: 'hotel').'.local'); }

    /** Hosted checkout URL (payment link, portal deposit, self check-out). */
    public function paymentLink(array $tx, array $opts = array())
    {
        if (!$this->sk()) { return $this->fail('Paystack secret key not configured'); }
        $body = array(
            'email' => $this->email($tx), 'amount' => $this->kobo($tx['amount']), 'currency' => $tx['currency'], 'reference' => $tx['reference'],
            'callback_url' => isset($opts['return_url']) ? $opts['return_url'] : null,
            'metadata' => array('reference' => $tx['reference'], 'purpose' => $tx['purpose'], 'channel' => $tx['channel'], 'id_htl_booking' => (int) $tx['id_htl_booking'], 'id_pulse_folio' => (int) $tx['id_pulse_folio'], 'custom_fields' => array(array('display_name' => 'Description', 'variable_name' => 'description', 'value' => (string) $tx['description']))),
        );
        if (!empty($opts['channels']) && is_array($opts['channels'])) { $body['channels'] = $opts['channels']; }
        $r = $this->request('POST', $this->api().'/transaction/initialize', $body, $this->headers(), 'initialize', $tx['reference']);
        if (!$r['ok'] || empty($r['json']['status'])) { return $this->fail($this->err($r), $r['json']); }
        return array('ok' => true, 'state' => 'intent', 'redirect_url' => $r['json']['data']['authorization_url'], 'gateway_ref' => $r['json']['data']['reference'], 'access_code' => $r['json']['data']['access_code'], 'raw' => $r['json']);
    }

    /** Debit a stored authorization code straight away (no guest interaction). */
    public function charge(array $tx, array $opts = array())
    {
        $auth = isset($opts['token']) && $opts['token'] ? $opts['token'] : null;
        if (!$auth) { return $this->paymentLink($tx, $opts); }
        $body = array('authorization_code' => $auth, 'email' => $this->email($tx), 'amount' => $this->kobo($tx['amount']), 'currency' => $tx['currency'], 'reference' => $tx['reference'], 'metadata' => array('purpose' => $tx['purpose'], 'reference' => $tx['reference']));
        $r = $this->request('POST', $this->api().'/transaction/charge_authorization', $body, $this->headers(), 'charge_authorization', $tx['reference']);
        if (!$r['ok'] || empty($r['json']['status'])) { return $this->fail($this->err($r), $r['json']); }
        return $this->fromTransaction($r['json']['data']);
    }

    /**
     * Reserve the card for check-in. A ₦50 verification debit is deliberately NOT taken:
     * we only accept an authorization code the guest already created (booking payment or portal card save).
     */
    public function authorize(array $tx, array $opts = array())
    {
        $auth = isset($opts['token']) && $opts['token'] ? $opts['token'] : null;
        if (!$auth) { return $this->fail('Paystack cannot hold a card without a saved authorization code — record a manual hold instead'); }
        $brand = isset($opts['card_brand']) ? $opts['card_brand'] : null;
        $this->logLocal('authorize', $tx['reference'], array('mode' => 'deferred', 'amount' => $tx['amount']), array('held' => 'authorization code retained'));
        return array('ok' => true, 'state' => 'authorized', 'hold_type' => 'deferred', 'gateway_ref' => 'AUTH-'.$tx['reference'], 'auth_token' => $auth, 'card_brand' => $brand, 'raw' => array('mode' => 'deferred_authorization'));
    }

    /** Capture = debit the retained authorization for the final amount (full or partial). */
    public function capture(array $tx, $amount, array $opts = array())
    {
        $auth = isset($opts['token']) && $opts['token'] ? $opts['token'] : (isset($tx['auth_token']) ? $tx['auth_token'] : null);
        if (!$auth) { return $this->fail('No stored authorization to capture against'); }
        $ref = isset($opts['reference']) ? $opts['reference'] : $tx['reference'].'-C'.date('His');
        $body = array('authorization_code' => $auth, 'email' => $this->email($tx), 'amount' => $this->kobo($amount), 'currency' => $tx['currency'], 'reference' => $ref, 'metadata' => array('capture_of' => $tx['reference']));
        $r = $this->request('POST', $this->api().'/transaction/charge_authorization', $body, $this->headers(), 'capture', $tx['reference']);
        if (!$r['ok'] || empty($r['json']['status'])) { return $this->fail($this->err($r), $r['json']); }
        $out = $this->fromTransaction($r['json']['data']);
        $out['capture_reference'] = $ref;
        return $out;
    }

    /** Nothing was debited for a deferred hold, so releasing it is a local state change. */
    public function void(array $tx, $reason = '') { $this->logLocal('void', $tx['reference'], array('reason' => $reason), array('released' => true)); return array('ok' => true, 'state' => 'voided', 'raw' => array('released' => 'deferred authorization discarded')); }

    public function refund(array $tx, $amount, $reason = '')
    {
        $target = !empty($tx['gateway_ref']) ? $tx['gateway_ref'] : $tx['reference'];
        $body = array('transaction' => $target, 'amount' => $this->kobo($amount), 'currency' => $tx['currency'], 'customer_note' => Tools::substr((string) $reason, 0, 200), 'merchant_note' => 'Pulse refund '.$tx['reference']);
        $r = $this->request('POST', $this->api().'/refund', $body, $this->headers(), 'refund', $tx['reference'], false);
        if (!$r['ok'] || empty($r['json']['status'])) { return $this->fail($this->err($r), $r['json']); }
        return array('ok' => true, 'state' => 'refunded', 'gateway_ref' => isset($r['json']['data']['id']) ? (string) $r['json']['data']['id'] : null, 'raw' => $r['json']);
    }

    public function verify($reference, array $tx = array())
    {
        $r = $this->request('GET', $this->api().'/transaction/verify/'.rawurlencode($reference), null, $this->headers(), 'verify', $reference);
        if (!$r['ok'] || empty($r['json']['status'])) { return $this->fail($this->err($r), $r['json']); }
        return $this->fromTransaction($r['json']['data']);
    }

    /** HMAC-SHA512 of the raw body keyed with the secret key, compared in constant time. */
    public function webhookVerify($rawBody, array $headers)
    {
        $sig = isset($headers['x-paystack-signature']) ? $headers['x-paystack-signature'] : '';
        $expected = hash_hmac('sha512', $rawBody, $this->sk());
        $ok = $sig !== '' && $this->sk() !== '' && hash_equals($expected, $sig);
        $d = json_decode($rawBody, true);
        $data = isset($d['data']) ? $d['data'] : array();
        $ref = isset($data['reference']) ? $data['reference'] : (isset($data['transaction_reference']) ? $data['transaction_reference'] : null);
        return array('ok' => $ok, 'error' => $ok ? null : 'Signature mismatch', 'event_id' => isset($d['id']) ? (string) $d['id'] : Tools::substr(hash('sha256', $rawBody), 0, 64), 'event_type' => isset($d['event']) ? $d['event'] : 'unknown', 'reference' => $ref, 'data' => $data, 'result' => $this->fromTransaction($data));
    }

    /** Normalise a Paystack transaction object into the adapter contract. */
    protected function fromTransaction($d)
    {
        if (!is_array($d)) { return $this->fail('Empty transaction payload'); }
        $status = isset($d['status']) ? $d['status'] : 'failed';
        $map = array('success' => 'captured', 'failed' => 'failed', 'abandoned' => 'expired', 'reversed' => 'refunded', 'ongoing' => 'intent', 'pending' => 'intent', 'processing' => 'intent', 'queued' => 'intent');
        $a = isset($d['authorization']) ? $d['authorization'] : array();
        return array(
            'ok' => $status === 'success', 'state' => isset($map[$status]) ? $map[$status] : 'failed',
            'gateway_ref' => isset($d['reference']) ? $d['reference'] : null, 'gateway_id' => isset($d['id']) ? (string) $d['id'] : null,
            'amount' => isset($d['amount']) ? $this->naira($d['amount']) : null, 'fee' => isset($d['fees']) ? $this->naira($d['fees']) : null,
            'auth_code' => isset($a['authorization_code']) ? $a['authorization_code'] : null, 'auth_token' => (isset($a['reusable']) && $a['reusable'] && isset($a['authorization_code'])) ? $a['authorization_code'] : null,
            'card_last4' => isset($a['last4']) ? $a['last4'] : null, 'card_brand' => isset($a['brand']) ? $a['brand'] : (isset($d['channel']) ? $d['channel'] : null), 'bank' => isset($a['bank']) ? $a['bank'] : null,
            'method' => $this->methodFor(isset($d['channel']) ? $d['channel'] : 'card'), 'paid_at' => isset($d['paid_at']) ? $d['paid_at'] : null,
            'error' => $status === 'success' ? null : (isset($d['gateway_response']) ? $d['gateway_response'] : $status), 'raw' => $d,
        );
    }

    protected function methodFor($channel) { $m = array('card' => 'card', 'bank' => 'transfer', 'bank_transfer' => 'transfer', 'dedicated_nuban' => 'transfer', 'ussd' => 'ussd', 'mobile_money' => 'mobile_money', 'qr' => 'online', 'eft' => 'transfer'); return isset($m[$channel]) ? $m[$channel] : 'online'; }
    protected function err($r) { if (!empty($r['json']['message'])) { return $r['json']['message']; } return $r['error'] ? $r['error'] : 'Paystack call failed'; }
}
