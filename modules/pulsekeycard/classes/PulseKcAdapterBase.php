<?php
/** Shared plumbing for lock adapters: short-timeout HTTP/TCP calls, credential access, uniform error typing. */
abstract class PulseKcAdapterBase
{
    protected $cfg;
    protected $defaultPort = 80;
    protected $vendor = 'generic';

    public function __construct(array $config)
    {
        $this->cfg = array_merge(array('protocol' => 'http', 'host' => '', 'port' => 0, 'endpoint' => '/', 'encoder_ref' => '', 'timeout' => 8, 'test_mode' => 0, 'name' => '', 'credentials' => array(), 'options' => array()), $config);
        if ((int) $this->cfg['timeout'] < 2) { $this->cfg['timeout'] = 8; }
    }

    protected function cred($k, $default = '') { return isset($this->cfg['credentials'][$k]) && $this->cfg['credentials'][$k] !== '' ? $this->cfg['credentials'][$k] : $default; }
    protected function opt($k, $default = null) { return isset($this->cfg['options'][$k]) ? $this->cfg['options'][$k] : $default; }
    protected function name() { return $this->cfg['name'] ? $this->cfg['name'] : $this->vendor; }
    protected function fail($msg, $code = PulseKcEncoderException::UNREACHABLE) { throw new PulseKcEncoderException($msg, $code, $this->name()); }

    /** Base URL for the encoder service; throws NOT_CONFIGURED rather than firing a request at nothing. */
    protected function baseUrl()
    {
        if (empty($this->cfg['host'])) { $this->fail('No host set for '.$this->vendor.' encoder', PulseKcEncoderException::NOT_CONFIGURED); }
        $scheme = $this->cfg['protocol'] === 'https' ? 'https' : 'http';
        $port = (int) $this->cfg['port'] ?: $this->defaultPort;
        return $scheme.'://'.$this->cfg['host'].':'.$port.'/'.trim($this->cfg['endpoint'], '/');
    }

    /**
     * One HTTP call with a hard timeout and a single retry on a connect-level failure.
     * $body is a string already encoded by the caller (JSON or XML); $headers is a plain array of header lines.
     */
    protected function http($method, $path, $body = null, array $headers = array(), $expectJson = true)
    {
        $url = rtrim($this->baseUrl(), '/').($path ? '/'.ltrim($path, '/') : '');
        $timeout = (int) $this->cfg['timeout'];
        $attempt = 0; $lastErr = ''; $lastNo = 0;
        while ($attempt < 2) {
            $attempt++;
            $ch = curl_init($url);
            $o = array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(4, $timeout), CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADER => 0, CURLOPT_FOLLOWLOCATION => 0);
            if ($body !== null) { $o[CURLOPT_POSTFIELDS] = $body; }
            if ($this->cfg['protocol'] === 'https' && $this->opt('insecure')) { $o[CURLOPT_SSL_VERIFYPEER] = 0; $o[CURLOPT_SSL_VERIFYHOST] = 0; }
            curl_setopt_array($ch, $o);
            $res = curl_exec($ch); $err = curl_error($ch); $no = curl_errno($ch); $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($err === '') {
                if ($http >= 500) { $this->fail('HTTP '.$http.' from encoder service', PulseKcEncoderException::BAD_RESPONSE); }
                if ($http === 401 || $http === 403) { $this->fail('Encoder refused the credentials (HTTP '.$http.')', PulseKcEncoderException::NOT_CONFIGURED); }
                if (!$expectJson) { return array('http' => $http, 'body' => $res); }
                $j = json_decode($res, true);
                if (!is_array($j)) { $this->fail('non-JSON reply: '.Tools::substr((string) $res, 0, 120), PulseKcEncoderException::BAD_RESPONSE); }
                $j['_http'] = $http;
                return $j;
            }
            $lastErr = $err; $lastNo = $no;
            if ($no !== CURLE_OPERATION_TIMEOUTED && $no !== CURLE_COULDNT_CONNECT) { break; }
            usleep(250000);
        }
        $this->fail($lastErr, $lastNo === CURLE_OPERATION_TIMEOUTED ? PulseKcEncoderException::TIMED_OUT : PulseKcEncoderException::UNREACHABLE);
    }

    /** Raw socket exchange for the vendors whose desk service speaks a line protocol rather than HTTP. */
    protected function tcp($frame, $terminator = "\n")
    {
        if (empty($this->cfg['host'])) { $this->fail('No host set for '.$this->vendor.' encoder', PulseKcEncoderException::NOT_CONFIGURED); }
        $timeout = (int) $this->cfg['timeout'];
        $fp = @fsockopen($this->cfg['host'], (int) $this->cfg['port'] ?: $this->defaultPort, $errno, $errstr, min(4, $timeout));
        if (!$fp) { $this->fail($errstr ? $errstr : 'connection refused', PulseKcEncoderException::UNREACHABLE); }
        stream_set_timeout($fp, $timeout);
        fwrite($fp, $frame.$terminator);
        $out = ''; $start = time();
        while (!feof($fp)) {
            $chunk = fgets($fp, 4096);
            $info = stream_get_meta_data($fp);
            if ($info['timed_out'] || (time() - $start) >= $timeout) { fclose($fp); $this->fail('socket read timed out', PulseKcEncoderException::TIMED_OUT); }
            if ($chunk === false) { break; }
            $out .= $chunk;
            if (strpos($out, $terminator) !== false && Tools::strlen(trim($out))) { break; }
        }
        fclose($fp);
        if (trim($out) === '') { $this->fail('empty reply from encoder socket', PulseKcEncoderException::BAD_RESPONSE); }
        return trim($out);
    }

    /** yyyy-mm-dd HH:ii -> vendor format helper. */
    protected function fmt($dt, $format = 'Y-m-d H:i') { return date($format, is_numeric($dt) ? (int) $dt : strtotime($dt)); }

    /** Guest name trimmed to what a 16-bit lock display / card track can carry. */
    protected function shortName($name, $len = 24) { return Tools::substr(trim(preg_replace('/\s+/', ' ', (string) $name)), 0, $len); }

    /** Normalise vendor event codes onto the pulse_kc_lock_audit enum. */
    protected function mapEvent($code)
    {
        $c = Tools::strtolower((string) $code);
        $map = array('open' => 'open', 'opened' => 'open', 'entry' => 'open', 'guest_open' => 'open', 'staff_open' => 'staff_open', 'master' => 'staff_open',
            'denied' => 'denied', 'reject' => 'denied', 'invalid' => 'denied', 'expired' => 'expired_card', 'deadbolt' => 'deadbolt', 'privacy' => 'dnd_blocked',
            'dnd' => 'dnd_blocked', 'battery' => 'battery_low', 'low_battery' => 'battery_low', 'ajar' => 'door_ajar', 'emergency' => 'emergency', 'pass' => 'pass_used');
        foreach ($map as $needle => $ev) { if (strpos($c, $needle) !== false) { return $ev; } }
        return 'unknown';
    }
}
