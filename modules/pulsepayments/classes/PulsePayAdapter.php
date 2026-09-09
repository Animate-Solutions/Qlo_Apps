<?php
/**
 * Shared plumbing for gateway adapters: cURL with connect+read timeouts, bounded retry with backoff,
 * and a redacted request/response log row in pulse_pay_log for every attempt.
 * At 2am on a bad link the retry is what saves the shift; the log is what settles the argument next morning.
 */
abstract class PulsePayAdapter implements PulsePayGatewayInterface
{
    const CONNECT_TIMEOUT = 8;
    const READ_TIMEOUT = 25;
    const RETRIES = 3;

    protected $cfg = array();
    protected $code = 'adapter';
    protected $caps = array();

    public function init(array $config) { $this->cfg = $config; if (!empty($config['code'])) { $this->code = $config['code']; } if (!empty($config['capabilities'])) { $this->caps = array_filter(array_map('trim', explode(',', $config['capabilities']))); } return $this; }
    public function supports($capability) { return in_array($capability, $this->caps); }
    public function capabilities() { return $this->caps; }
    protected function cfg($k, $d = null) { return isset($this->cfg[$k]) && $this->cfg[$k] !== '' ? $this->cfg[$k] : $d; }
    protected function extra($k, $d = null) { $e = $this->cfg('extra'); if (is_string($e)) { $e = json_decode($e, true); } return is_array($e) && isset($e[$k]) && $e[$k] !== '' ? $e[$k] : $d; }
    protected function base($default) { $b = $this->cfg('endpoint', $default); return rtrim($b, '/'); }
    protected function fail($msg, $raw = array()) { return array('ok' => false, 'error' => $msg, 'raw' => $raw); }

    /** Gateway fee estimate from the configured rate card (percent + flat, capped, flat waived under a threshold). */
    public function fee($amount)
    {
        $amount = round((float) $amount, 2);
        $f = $amount * (float) $this->cfg('fee_percent', 0) / 100;
        if ((float) $this->cfg('fee_flat_waive_below', 0) <= 0 || $amount >= (float) $this->cfg('fee_flat_waive_below', 0)) { $f += (float) $this->cfg('fee_flat', 0); }
        $cap = (float) $this->cfg('fee_cap', 0);
        return round($cap > 0 ? min($f, $cap) : $f, 2);
    }

    /**
     * HTTP call with retry. Retries only on transport failure, 429 and 5xx — never on a 4xx business answer,
     * and never on a POST whose idempotency we cannot guarantee (pass $idempotent=false to send once).
     */
    protected function request($method, $url, $body = null, array $headers = array(), $operation = 'call', $reference = null, $idempotent = true)
    {
        $attempts = $idempotent ? self::RETRIES : 1;
        $last = array('ok' => false, 'http' => 0, 'body' => '', 'error' => 'not attempted');
        for ($i = 1; $i <= $attempts; $i++) {
            $t0 = microtime(true);
            $ch = curl_init();
            $payload = is_array($body) ? json_encode($body) : $body;
            curl_setopt_array($ch, array(
                CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT, CURLOPT_TIMEOUT => self::READ_TIMEOUT,
                CURLOPT_HTTPHEADER => array_merge(array('Content-Type: application/json', 'Accept: application/json', 'User-Agent: PulseHMS/'.(defined('_PS_VERSION_') ? _PS_VERSION_ : '1.6')), $headers),
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false,
            ));
            if ($payload !== null && $method !== 'GET') { curl_setopt($ch, CURLOPT_POSTFIELDS, $payload); }
            $raw = curl_exec($ch); $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $ok = ($raw !== false && $http >= 200 && $http < 300);
            $this->log($operation, $reference, $url, $method, $payload, $raw === false ? '' : $raw, $http, $i, $ms, $ok, $ok ? null : ($err ?: 'HTTP '.$http));
            $last = array('ok' => $ok, 'http' => $http, 'body' => $raw === false ? '' : $raw, 'error' => $ok ? null : ($err ?: 'HTTP '.$http), 'json' => $raw === false ? null : json_decode($raw, true));
            if ($ok) { return $last; }
            $retryable = ($raw === false || $http === 0 || $http === 429 || $http >= 500);
            if (!$retryable || $i === $attempts) { return $last; }
            sleep(min(8, (int) pow(2, $i)));
        }
        return $last;
    }

    /** Never let a secret key, card PAN or CVV reach the log table. */
    public static function redact($s)
    {
        if ($s === null || $s === '') { return ''; }
        if (!is_string($s)) { $s = json_encode($s); }
        $s = preg_replace('/(sk_(?:live|test)_)[A-Za-z0-9]+/', '$1***', $s);
        $s = preg_replace('/(FLWSECK[_-][A-Za-z0-9-]{0,8})[A-Za-z0-9-]*/', '$1***', $s);
        $s = preg_replace('/("(?:secret_key|secretKey|mac_key|macKey|authorization|password|pin|cvv|cvc|card_number|pan)"\s*:\s*")[^"]*/i', '$1***', $s);
        $s = preg_replace('/(Bearer\s+)[A-Za-z0-9._-]+/', '$1***', $s);
        $s = preg_replace('/\b(\d{6})\d{4,9}(\d{4})\b/', '$1******$2', $s);
        return Tools::substr($s, 0, 60000);
    }

    protected function log($operation, $reference, $url, $method, $request, $response, $http, $attempt, $ms, $ok, $error)
    {
        Db::getInstance()->insert('pulse_pay_log', array(
            'gateway' => pSQL($this->code), 'operation' => pSQL($operation), 'reference' => pSQL($reference), 'url' => pSQL(self::redact($url)), 'method' => pSQL($method),
            'request' => pSQL(self::redact($request), true), 'response' => pSQL(self::redact($response), true), 'http_code' => (int) $http, 'attempt' => (int) $attempt,
            'duration_ms' => (int) $ms, 'ok' => (int) $ok, 'error' => pSQL(Tools::substr((string) $error, 0, 255)), 'date_add' => date('Y-m-d H:i:s'),
        ), true);
    }

    /** Record an operation that never left the building (manual confirmations, offline queueing) so the audit trail is complete. */
    protected function logLocal($operation, $reference, $request, $response, $ok = true, $error = null)
    {
        $this->log($operation, $reference, 'local://'.$this->code.'/'.$operation, 'LOCAL', is_array($request) ? json_encode($request) : $request, is_array($response) ? json_encode($response) : $response, $ok ? 200 : 500, 1, 0, $ok, $error);
    }

    /* ---------- sensible defaults an adapter may override ---------- */
    public function authorize(array $tx, array $opts = array()) { return $this->fail($this->code.' does not support pre-authorisation'); }
    public function capture(array $tx, $amount, array $opts = array()) { return $this->fail($this->code.' does not support capture'); }
    public function void(array $tx, $reason = '') { return $this->fail($this->code.' does not support void'); }
    public function refund(array $tx, $amount, $reason = '') { return $this->fail($this->code.' does not support refunds through the API — refund at the bank and log it here'); }
    public function paymentLink(array $tx, array $opts = array()) { return $this->fail($this->code.' does not issue payment links'); }
    public function webhookVerify($rawBody, array $headers) { return array('ok' => false, 'error' => 'No webhook for '.$this->code); }
}
