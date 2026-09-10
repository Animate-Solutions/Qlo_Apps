<?php
/**
 * Contract every payment gateway adapter implements.
 * Money is always passed in shop currency major units (naira, not kobo) — adapters convert.
 * Every method returns array('ok'=>bool, 'state'=>string, 'gateway_ref'=>string, 'redirect_url'=>string,
 * 'auth_token'=>string, 'fee'=>float, 'error'=>string, 'raw'=>array) so PulsePayService never has to know the vendor.
 */
interface PulsePayGatewayInterface
{
    /** Receive the decrypted pulse_pay_gateway row (keys: code, endpoint, public_key, secret_key, merchant_id, webhook_secret, extra, test_mode, currency, fee_*). */
    public function init(array $config);

    /** Capability flag: charge|capture|void|refund|verify|link|webhook|token|deferred_preauth|manual_preauth|terminal. */
    public function supports($capability);

    /** Immediate debit. $tx is the pulse_pay_transaction row; $opts may carry token, email, channels, redirect. */
    public function charge(array $tx, array $opts = array());

    /** Hold funds (or reserve a reusable authorisation when the gateway has no true hold). */
    public function authorize(array $tx, array $opts = array());

    /** Take $amount off a hold/authorisation. */
    public function capture(array $tx, $amount, array $opts = array());

    /** Release an uncaptured hold. */
    public function void(array $tx, $reason = '');

    /** Refund a captured transaction, fully or partially. */
    public function refund(array $tx, $amount, $reason = '');

    /** Ask the gateway what really happened — the source of truth when a webhook never arrived. */
    public function verify($reference, array $tx = array());

    /** Hosted checkout URL for a payment link / redirect flow. */
    public function paymentLink(array $tx, array $opts = array());

    /** Validate a webhook body+headers. Returns array('ok'=>bool,'event_id'=>string,'event_type'=>string,'reference'=>string,'data'=>array). */
    public function webhookVerify($rawBody, array $headers);
}
