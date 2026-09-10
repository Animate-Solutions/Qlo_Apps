<?php
/**
 * Manual adapter — bank transfer into the hotel account and the physical bank POS terminal on the desk,
 * where the only integration anyone will ever build is a human reading the RRN off the slip.
 * Nothing here talks to the internet, so it is the fallback that always works when the link is down.
 */
class PulsePayManual extends PulsePayAdapter
{
    protected $code = 'manual';

    /** Park the money as awaiting confirmation; a cashier confirms it with the RRN / transfer reference. */
    public function charge(array $tx, array $opts = array())
    {
        $method = isset($opts['method']) ? $opts['method'] : (isset($tx['method']) ? $tx['method'] : 'transfer');
        $rrn = isset($opts['rrn']) ? preg_replace('/[^A-Za-z0-9-]/', '', $opts['rrn']) : '';
        $this->logLocal('charge', $tx['reference'], array('method' => $method, 'amount' => $tx['amount'], 'rrn' => $rrn), array('state' => $rrn ? 'captured' : 'awaiting_confirmation'));
        if ($rrn !== '') { return $this->confirmed($tx, $opts, $rrn); }
        return array('ok' => true, 'state' => 'awaiting_confirmation', 'gateway_ref' => null, 'method' => $method, 'instructions' => $this->instructions($tx), 'raw' => array('mode' => 'awaiting_confirmation'));
    }

    /** Manual confirmation: RRN (or transfer reference), auth code and last 4 keyed by the cashier. */
    public function confirm(array $tx, array $opts = array())
    {
        $rrn = isset($opts['rrn']) ? preg_replace('/[^A-Za-z0-9-]/', '', $opts['rrn']) : '';
        if ($rrn === '') { return $this->fail('An RRN or transfer reference is required to confirm a manual payment'); }
        $this->logLocal('confirm', $tx['reference'], array('rrn' => $rrn, 'auth_code' => isset($opts['auth_code']) ? $opts['auth_code'] : ''), array('state' => 'captured'));
        return $this->confirmed($tx, $opts, $rrn);
    }

    /** A manual hold is a signed authority slip in the drawer, not money — recorded so check-out knows it exists. */
    public function authorize(array $tx, array $opts = array())
    {
        $this->logLocal('authorize', $tx['reference'], array('amount' => $tx['amount'], 'note' => isset($opts['note']) ? $opts['note'] : ''), array('hold' => 'manual'));
        return array('ok' => true, 'state' => 'authorized', 'hold_type' => 'manual', 'gateway_ref' => 'MANUAL-'.Tools::strtoupper(Tools::substr(sha1($tx['reference'].microtime(true)), 0, 8)), 'card_last4' => isset($opts['card_last4']) ? preg_replace('/[^0-9]/', '', $opts['card_last4']) : null, 'raw' => array('mode' => 'manual_hold'));
    }

    /** Capturing a manual hold means swiping the card on the bank terminal; without an RRN it stays pending. */
    public function capture(array $tx, $amount, array $opts = array())
    {
        $rrn = isset($opts['rrn']) ? preg_replace('/[^A-Za-z0-9-]/', '', $opts['rrn']) : '';
        $this->logLocal('capture', $tx['reference'], array('amount' => $amount, 'rrn' => $rrn), array('state' => $rrn ? 'captured' : 'awaiting_confirmation'));
        if ($rrn === '') { return array('ok' => true, 'state' => 'awaiting_confirmation', 'gateway_ref' => null, 'raw' => array('mode' => 'terminal_pending')); }
        return $this->confirmed($tx, $opts, $rrn);
    }

    public function void(array $tx, $reason = '') { $this->logLocal('void', $tx['reference'], array('reason' => $reason), array('state' => 'voided')); return array('ok' => true, 'state' => 'voided', 'raw' => array('mode' => 'manual_release')); }
    public function refund(array $tx, $amount, $reason = '') { $this->logLocal('refund', $tx['reference'], array('amount' => $amount, 'reason' => $reason), array('state' => 'refunded')); return array('ok' => true, 'state' => 'refunded', 'gateway_ref' => 'MREF-'.Tools::strtoupper(Tools::substr(sha1($tx['reference'].$amount.microtime(true)), 0, 10)), 'raw' => array('mode' => 'manual_refund', 'note' => 'Refund executed at the bank; recorded here for the ledger')); }

    /** Nothing to ask: a manual payment is exactly what the cashier keyed in. */
    public function verify($reference, array $tx = array()) { return array('ok' => !empty($tx['gateway_ref']), 'state' => isset($tx['state']) ? $tx['state'] : 'awaiting_confirmation', 'gateway_ref' => isset($tx['gateway_ref']) ? $tx['gateway_ref'] : null, 'amount' => isset($tx['amount']) ? (float) $tx['amount'] : null, 'raw' => array('mode' => 'manual')); }

    public function paymentLink(array $tx, array $opts = array()) { return array('ok' => true, 'state' => 'awaiting_confirmation', 'redirect_url' => null, 'instructions' => $this->instructions($tx), 'gateway_ref' => null, 'raw' => array('mode' => 'bank_transfer_instructions')); }

    protected function confirmed(array $tx, array $opts, $rrn)
    {
        return array(
            'ok' => true, 'state' => 'captured', 'gateway_ref' => $rrn, 'rrn' => $rrn,
            'auth_code' => isset($opts['auth_code']) ? preg_replace('/[^A-Za-z0-9]/', '', $opts['auth_code']) : null,
            'card_last4' => isset($opts['card_last4']) ? Tools::substr(preg_replace('/[^0-9]/', '', $opts['card_last4']), -4) : null,
            'card_brand' => isset($opts['card_brand']) ? $opts['card_brand'] : null, 'bank' => isset($opts['bank']) ? $opts['bank'] : (string) $this->extra('bank_name', ''),
            'method' => isset($opts['method']) ? $opts['method'] : (isset($tx['method']) ? $tx['method'] : 'card'),
            'amount' => isset($opts['amount']) ? round((float) $opts['amount'], 2) : (isset($tx['amount']) ? round((float) $tx['amount'], 2) : null),
            'fee' => 0, 'paid_at' => date('Y-m-d H:i:s'), 'raw' => array('mode' => 'manual_confirmed', 'rrn' => $rrn),
        );
    }

    /** Bank details shown on the pay page and on the emailed link when the guest chooses transfer. */
    public function instructions(array $tx)
    {
        return array(
            'bank' => (string) $this->extra('bank_name', Configuration::get('PULSE_PAY_BANK_NAME')),
            'account_name' => (string) $this->extra('account_name', Configuration::get('PULSE_PAY_BANK_ACCOUNT_NAME')),
            'account_number' => (string) $this->extra('account_number', Configuration::get('PULSE_PAY_BANK_ACCOUNT')),
            'amount' => round((float) $tx['amount'], 2), 'narration' => $tx['reference'],
            'note' => 'Use '.$tx['reference'].' as the transfer narration, then send the receipt to reception.',
        );
    }
}
