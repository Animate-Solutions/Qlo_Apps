<?php
/**
 * Shared transport for HTTP-speaking adapters: timeouts, retry-safe single attempt, logging of every
 * request/response pair, and a common error map. At 2 a.m. on a bad link the timeout is what saves the
 * queue — never remove it, and never let an adapter throw past drainQueue().
 */
abstract class PulseChAdapterBase implements PulseChAdapterInterface
{
    protected $channel;
    protected $cfg;

    public function __construct(array $channel, array $credentials) { $this->channel = $channel; $this->cfg = $credentials; }

    /** The module class is not always loaded in cron, so read the version defensively. */
    public static function version() { return class_exists('PulseChannel') ? PulseChannel::VERSION : '1.0.0'; }
    protected function id() { return (int) $this->channel['id_pulse_ch_channel']; }
    protected function cred($k, $default = '') { return isset($this->cfg[$k]) && $this->cfg[$k] !== '' ? $this->cfg[$k] : $default; }
    protected function timeout() { return max(3, (int) ($this->channel['timeout_sec'] ? $this->channel['timeout_sec'] : Configuration::get('PULSE_CH_HTTP_TIMEOUT'))); }

    /** Authorization headers for the channel's auth_type. $body is needed for hmac signing. */
    protected function authHeaders($body)
    {
        $h = array();
        $header = $this->channel['auth_header'] ? $this->channel['auth_header'] : 'Authorization';
        switch ($this->channel['auth_type']) {
            case 'basic': $h[] = $header.': Basic '.base64_encode($this->cred('username').':'.$this->cred('password')); break;
            case 'bearer': $h[] = $header.': Bearer '.$this->cred('api_key', $this->cred('token')); break;
            case 'api_key': $h[] = $header.': '.$this->cred('api_key'); break;
            case 'hmac':
                $ts = (string) time(); $sig = hash_hmac('sha256', $ts.'.'.$body, $this->cred('secret'));
                $h[] = 'X-Timestamp: '.$ts; $h[] = 'X-Api-Key: '.$this->cred('api_key'); $h[] = $header.': sha256='.$sig;
                break;
            case 'soap_ws_security': break; // credentials travel inside the SOAP envelope built by the caller
            case 'none': default: break;
        }
        return $h;
    }

    /**
     * One HTTP attempt. Returns array(ok, status, body, error, ms). Never throws — the queue decides
     * whether to retry, and a hard failure must still leave a log row behind.
     */
    protected function http($url, $body, $type, $reference = null, array $extraHeaders = array(), $method = 'POST')
    {
        if (!$url) { return array('ok' => false, 'status' => 0, 'body' => '', 'error' => 'No endpoint configured for '.$this->channel['name'], 'ms' => 0); }
        $ct = $this->channel['payload_format'] === 'xml' ? 'application/xml' : ($this->channel['payload_format'] === 'form' ? 'application/x-www-form-urlencoded' : 'application/json');
        $headers = array_merge(array('Content-Type: '.$ct, 'Accept: '.$ct, 'User-Agent: PulseChannel/'.self::version()), $this->authHeaders($body), $extraHeaders);
        $t0 = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $this->timeout(), CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout()), CURLOPT_FOLLOWLOCATION => 0, CURLOPT_SSL_VERIFYPEER => 1));
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, 1); curl_setopt($ch, CURLOPT_POSTFIELDS, $body); } elseif ($method !== 'GET') { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $res = curl_exec($ch); $err = curl_error($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $error = $err ? $this->mapError(0, $err) : ($status >= 400 ? $this->mapError($status, (string) $res) : null);
        PulseChLog::write($this->id(), 'out', $type, $reference, $status, $body, (string) $res, $ms, $error ? 'error' : 'ok', $error);
        return array('ok' => !$error, 'status' => $status, 'body' => (string) $res, 'error' => $error, 'ms' => $ms);
    }

    /** Turn a transport or HTTP failure into an operator-readable sentence. */
    protected function mapError($status, $raw)
    {
        $raw = trim(Tools::substr(strip_tags((string) $raw), 0, 200));
        if (!$status) { return 'Network: '.($raw ?: 'no response'); }
        $map = array(400 => 'Rejected by channel (bad request)', 401 => 'Credentials rejected — re-enter them in Settings', 403 => 'Forbidden — property or user not entitled', 404 => 'Endpoint or hotel code not found', 409 => 'Conflict — the channel already has a newer value', 422 => 'Payload failed channel validation', 429 => 'Rate limited by channel — the queue will back off', 500 => 'Channel server error', 502 => 'Channel gateway error', 503 => 'Channel temporarily unavailable', 504 => 'Channel timed out');
        return (isset($map[$status]) ? $map[$status] : 'HTTP '.$status).($raw ? ': '.$raw : '');
    }

    public function capabilities() { return array('ari' => true, 'rates' => true, 'restrictions' => true, 'pull' => true, 'ack' => true, 'test' => true); }
}
