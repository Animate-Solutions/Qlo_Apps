<?php
/**
 * Interswitch adapter — WebPAY redirect (SHA-512 hash of productId + txnref + amount + siteRedirectURL + macKey)
 * and the Quickteller/WebPAY transaction query (Hash header = SHA-512 of productId + transactionreference + macKey).
 * Amounts travel in kobo. WebPAY has no refund or hold API in Nigeria: refunds go through the bank and are logged here.
 * There is no signed callback either, so the "webhook" endpoint only tells us to re-query — the query is the truth.
 */
class PulsePayInterswitch extends PulsePayAdapter
{
    protected $code = 'interswitch';

    protected function pay() { return $this->base('https://webpay.interswitchng.com'); }
    protected function query() { return rtrim((string) $this->extra('query_url', 'https://webpay.interswitchng.com/collections/api/v1/gettransaction.json'), '/'); }
    protected function productId() { return (string) ($this->cfg('merchant_id') ? $this->cfg('merchant_id') : $this->extra('product_id', '')); }
    protected function payItemId() { return (string) $this->extra('pay_item_id', '101'); }
    protected function mac() { return (string) $this->cfg('secret_key'); }
    protected function kobo($a) { return (int) round(((float) $a) * 100); }

    /** WebPAY is a browser form post, so the "link" is our own pay page which renders and auto-submits that form. */
    public function paymentLink(array $tx, array $opts = array())
    {
        if (!$this->productId() || !$this->mac()) { return $this->fail('Interswitch product id / MAC key not configured'); }
        $ret = isset($opts['return_url']) ? $opts['return_url'] : '';
        $fields = array(
            'product_id' => $this->productId(), 'pay_item_id' => $this->payItemId(), 'amount' => $this->kobo($tx['amount']), 'currency' => (string) $this->extra('currency_code', '566'),
            'site_redirect_url' => $ret, 'txn_ref' => $tx['reference'], 'hash' => $this->redirectHash($tx['reference'], $this->kobo($tx['amount']), $ret),
            'cust_id' => (string) (int) $tx['id_customer'], 'cust_name' => (string) $tx['customer_name'], 'cust_email' => (string) $tx['customer_email'],
            'pay_item_name' => Tools::substr((string) $tx['description'], 0, 60), 'site_name' => Configuration::get('PS_SHOP_NAME'),
        );
        $this->logLocal('webpay_form', $tx['reference'], array('amount' => $tx['amount'], 'txn_ref' => $tx['reference']), array('action' => $this->pay().'/paydirect/pay'));
        return array('ok' => true, 'state' => 'intent', 'form_action' => $this->pay().'/paydirect/pay', 'form_fields' => $fields, 'redirect_url' => null, 'gateway_ref' => $tx['reference'], 'raw' => array('mode' => 'webpay_form'));
    }

    public function charge(array $tx, array $opts = array()) { return $this->paymentLink($tx, $opts); }

    /** Query the collection: response code "00" means the money is ours. */
    public function verify($reference, array $tx = array())
    {
        if (!$this->productId() || !$this->mac()) { return $this->fail('Interswitch product id / MAC key not configured'); }
        $amount = isset($tx['amount']) ? $this->kobo($tx['amount']) : 0;
        $url = $this->query().'?merchantcode='.rawurlencode((string) $this->extra('merchant_code', $this->productId())).'&transactionreference='.rawurlencode($reference).'&amount='.$amount;
        $r = $this->request('GET', $url, null, array('Hash: '.hash('sha512', $this->productId().$reference.$this->mac())), 'verify', $reference);
        if (!$r['ok'] || !is_array($r['json'])) { return $this->fail($r['error'] ? $r['error'] : 'Interswitch query failed', $r['json']); }
        return $this->fromQuery($r['json'], $reference);
    }

    /**
     * Interswitch posts the browser back to our redirect URL rather than signing a server webhook,
     * so we treat any notification as untrusted and answer it by re-querying the transaction.
     */
    public function webhookVerify($rawBody, array $headers)
    {
        $d = json_decode($rawBody, true);
        if (!is_array($d)) { parse_str((string) $rawBody, $d); }
        $ref = isset($d['txnref']) ? $d['txnref'] : (isset($d['txnRef']) ? $d['txnRef'] : (isset($d['transactionreference']) ? $d['transactionreference'] : null));
        return array('ok' => (bool) $ref, 'error' => $ref ? null : 'No transaction reference in notification', 'event_id' => 'isw-'.Tools::substr(hash('sha256', $rawBody.$ref), 0, 48), 'event_type' => 'notification', 'reference' => $ref, 'data' => is_array($d) ? $d : array(), 'requery' => true);
    }

    /** SHA-512(txn_ref + product_id + pay_item_id + amount + site_redirect_url + mac_key) as WebPAY documents it. */
    protected function redirectHash($ref, $amountKobo, $redirect) { return hash('sha512', $ref.$this->productId().$this->payItemId().$amountKobo.$redirect.$this->mac()); }

    protected function fromQuery(array $d, $reference)
    {
        $code = isset($d['ResponseCode']) ? $d['ResponseCode'] : (isset($d['responseCode']) ? $d['responseCode'] : '');
        $ok = ($code === '00');
        $amt = isset($d['Amount']) ? $d['Amount'] : (isset($d['amount']) ? $d['amount'] : 0);
        return array(
            'ok' => $ok, 'state' => $ok ? 'captured' : ($code === '' || $code === 'Z6' ? 'intent' : 'failed'),
            'gateway_ref' => isset($d['PaymentReference']) ? $d['PaymentReference'] : $reference, 'amount' => round(((float) $amt) / 100, 2), 'fee' => null,
            'auth_code' => isset($d['RetrievalReferenceNumber']) ? $d['RetrievalReferenceNumber'] : null, 'rrn' => isset($d['RetrievalReferenceNumber']) ? $d['RetrievalReferenceNumber'] : null,
            'card_last4' => isset($d['CardNumber']) ? Tools::substr(preg_replace('/[^0-9]/', '', $d['CardNumber']), -4) : null, 'card_brand' => null, 'bank' => isset($d['BankName']) ? $d['BankName'] : null,
            'method' => 'card', 'paid_at' => isset($d['TransactionDate']) ? $d['TransactionDate'] : null,
            'error' => $ok ? null : (isset($d['ResponseDescription']) ? $d['ResponseDescription'] : 'Response code '.$code), 'raw' => $d,
        );
    }
}
