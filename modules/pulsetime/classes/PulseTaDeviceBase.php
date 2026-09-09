<?php
/**
 * Shared plumbing for every clocking-device adapter: hard timeouts, bounded retries, credential access,
 * timezone normalisation and punch normalisation.
 *
 * Two rules hold everywhere in this file, because a 52-room hotel on an intermittent link depends on them:
 *  1. Nothing blocks forever. Every socket and every curl handle carries a connect timeout and a read timeout.
 *  2. Nothing is destructive by accident. clearLog() is only ever called by an explicit action or by a device
 *     whose `clear_after_pull` flag was deliberately turned on, and only after the punches are committed.
 */
abstract class PulseTaDeviceBase implements PulseTaDeviceInterface
{
    protected $dev;
    protected $vendor = 'generic';
    protected $defaultPort = 80;

    public function __construct(array $device)
    {
        $this->dev = array_merge(array('id_pulse_ta_device' => 0, 'name' => '', 'protocol' => 'http', 'host' => '', 'port' => 0, 'endpoint' => '/', 'serial' => '',
            'timezone' => 'Africa/Lagos', 'direction_mode' => 'both', 'timeout_sec' => 8, 'retries' => 2, 'test_mode' => 0, 'credentials' => array(), 'options' => array()), $device);
        if ((int) $this->dev['timeout_sec'] < 2) { $this->dev['timeout_sec'] = 8; }
        if ((int) $this->dev['retries'] < 0) { $this->dev['retries'] = 0; }
    }

    /* ---------- configuration helpers ---------- */

    protected function cred($k, $default = '') { return isset($this->dev['credentials'][$k]) && $this->dev['credentials'][$k] !== '' ? $this->dev['credentials'][$k] : $default; }
    protected function opt($k, $default = null) { return isset($this->dev['options'][$k]) && $this->dev['options'][$k] !== '' ? $this->dev['options'][$k] : $default; }
    protected function name() { return $this->dev['name'] ? $this->dev['name'] : $this->vendor; }
    protected function timeout() { return max(2, (int) $this->dev['timeout_sec']); }
    protected function attempts() { return max(1, (int) $this->dev['retries'] + 1); }
    protected function fail($msg, $code = PulseTaDeviceException::UNREACHABLE) { throw new PulseTaDeviceException($msg, $code, $this->name()); }
    protected function port() { return (int) $this->dev['port'] ?: $this->defaultPort; }

    /** Scheme://host:port/endpoint with no trailing slash — throws rather than firing a request at nothing. */
    protected function baseUrl()
    {
        if (empty($this->dev['host'])) { $this->fail('No host set for this '.$this->vendor.' device', PulseTaDeviceException::NOT_CONFIGURED); }
        $scheme = $this->dev['protocol'] === 'https' ? 'https' : 'http';
        $base = $scheme.'://'.$this->dev['host'].':'.$this->port();
        $ep = trim((string) $this->dev['endpoint'], '/');
        return $ep === '' ? $base : $base.'/'.$ep;
    }

    /* ---------- HTTP ---------- */

    /**
     * One HTTP call with a hard timeout and bounded retries on connect-level failures only.
     * $auth: 'none' | 'basic' | 'digest' | header lines are added by the caller through $headers.
     * @return array [http, body, headers]
     */
    protected function http($method, $path, $body = null, array $headers = array(), $auth = 'none', $absolute = false)
    {
        $url = $absolute ? $path : rtrim($this->baseUrl(), '/').($path === '' ? '' : '/'.ltrim($path, '/'));
        $attempt = 0; $lastErr = ''; $lastNo = 0;
        while ($attempt < $this->attempts()) {
            $attempt++;
            $ch = curl_init($url);
            $o = array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => $this->timeout(), CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout()),
                CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADER => 1, CURLOPT_FOLLOWLOCATION => 0,
                CURLOPT_USERAGENT => 'PulseTime/'.(class_exists('PulseTime') ? PulseTime::VERSION : '1.0.0'));
            if ($body !== null) { $o[CURLOPT_POSTFIELDS] = $body; }
            if ($auth === 'digest' || $auth === 'basic') {
                $o[CURLOPT_HTTPAUTH] = $auth === 'digest' ? CURLAUTH_DIGEST : CURLAUTH_BASIC;
                $o[CURLOPT_USERPWD] = $this->cred('user').':'.$this->cred('password');
            }
            if ($this->dev['protocol'] === 'https' && $this->opt('allow_self_signed')) { $o[CURLOPT_SSL_VERIFYPEER] = 0; $o[CURLOPT_SSL_VERIFYHOST] = 0; }
            else { $o[CURLOPT_SSL_VERIFYPEER] = 1; $o[CURLOPT_SSL_VERIFYHOST] = 2; }
            curl_setopt_array($ch, $o);
            $res = curl_exec($ch); $err = curl_error($ch); $no = curl_errno($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            if ($err === '') {
                // CURLINFO_HEADER_SIZE is a byte offset, so the split must be a byte slice — Tools::substr
                // counts characters and would tear the header/body boundary on any non-ASCII response.
                $rawHeaders = substr((string) $res, 0, $hlen); $payload = (string) substr((string) $res, $hlen);
                if ($http === 401 || $http === 403) { $this->fail('device refused the credentials (HTTP '.$http.')', PulseTaDeviceException::AUTH_FAILED); }
                if ($http >= 500) { $this->fail('HTTP '.$http.' from the device', PulseTaDeviceException::BAD_RESPONSE); }
                return array('http' => $http, 'body' => $payload, 'headers' => $this->parseHeaders($rawHeaders));
            }
            $lastErr = $err; $lastNo = $no;
            if ($no !== CURLE_OPERATION_TIMEOUTED && $no !== CURLE_COULDNT_CONNECT && $no !== CURLE_COULDNT_RESOLVE_HOST) { break; }
            usleep(300000);
        }
        $this->fail($lastErr ? $lastErr : 'no response', $lastNo === CURLE_OPERATION_TIMEOUTED ? PulseTaDeviceException::TIMED_OUT : PulseTaDeviceException::UNREACHABLE);
    }

    /** Same call, decoded as JSON; a non-JSON reply is a BAD_RESPONSE rather than a silent empty array. */
    protected function json($method, $path, $body = null, array $headers = array(), $auth = 'none', $absolute = false)
    {
        $r = $this->http($method, $path, $body, array_merge(array('Content-Type: application/json', 'Accept: application/json'), $headers), $auth, $absolute);
        $j = json_decode($r['body'], true);
        if (!is_array($j)) { $this->fail('non-JSON reply: '.Tools::substr(trim(strip_tags($r['body'])), 0, 160), PulseTaDeviceException::BAD_RESPONSE); }
        $j['_http'] = $r['http']; $j['_headers'] = $r['headers'];
        return $j;
    }

    protected function parseHeaders($raw)
    {
        $out = array();
        foreach (preg_split('/\r?\n/', (string) $raw) as $line) {
            $p = strpos($line, ':');
            if ($p > 0) { $out[Tools::strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1)); }
        }
        return $out;
    }

    /* ---------- raw sockets ---------- */

    /** Blocking TCP/UDP connect with a hard timeout. Returns the stream; the caller always fclose()s it. */
    protected function socket($proto = null)
    {
        if (empty($this->dev['host'])) { $this->fail('No host set for this '.$this->vendor.' device', PulseTaDeviceException::NOT_CONFIGURED); }
        $proto = $proto ? $proto : ($this->dev['protocol'] === 'udp' ? 'udp' : 'tcp');
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client($proto.'://'.$this->dev['host'].':'.$this->port(), $errno, $errstr, min(5, $this->timeout()));
        if (!$fp) { $this->fail($errstr ? $errstr : 'connection refused on port '.$this->port(), PulseTaDeviceException::UNREACHABLE); }
        stream_set_timeout($fp, $this->timeout());
        return $fp;
    }

    /** Read exactly $n bytes or fail on timeout. Short reads are the classic source of "it works on my desk" bugs. */
    protected function readExact($fp, $n)
    {
        $buf = ''; $deadline = microtime(true) + $this->timeout();
        while (strlen($buf) < $n) {
            if (microtime(true) > $deadline) { $this->fail('short read: wanted '.$n.' bytes, got '.strlen($buf), PulseTaDeviceException::TIMED_OUT); }
            $chunk = fread($fp, $n - strlen($buf));
            $meta = stream_get_meta_data($fp);
            if (!empty($meta['timed_out'])) { $this->fail('socket read timed out after '.strlen($buf).' of '.$n.' bytes', PulseTaDeviceException::TIMED_OUT); }
            if ($chunk === false || ($chunk === '' && feof($fp))) { $this->fail('device closed the connection after '.strlen($buf).' of '.$n.' bytes', PulseTaDeviceException::BAD_RESPONSE); }
            $buf .= $chunk;
        }
        return $buf;
    }

    /* ---------- normalisation ---------- */

    /** Server timezone (the shop's), used as the storage timezone for every punch. */
    protected function shopTz() { $tz = Configuration::get('PS_TIMEZONE'); return $tz ? $tz : (Configuration::get('PULSE_TA_TZ') ?: 'Africa/Lagos'); }

    /**
     * A device shows local wall-clock time in whatever timezone it was set to. We store punches in the shop's
     * timezone, so a device configured to a different zone is converted here — a one-hour drift in punch times
     * destroys a payroll, so the conversion is explicit rather than assumed.
     */
    protected function toShopTime($deviceLocal)
    {
        $devTz = $this->dev['timezone'] ? $this->dev['timezone'] : $this->shopTz();
        $shopTz = $this->shopTz();
        if ($devTz === $shopTz) { return date('Y-m-d H:i:s', is_numeric($deviceLocal) ? (int) $deviceLocal : strtotime($deviceLocal)); }
        try {
            $d = new DateTime(is_numeric($deviceLocal) ? date('Y-m-d H:i:s', (int) $deviceLocal) : (string) $deviceLocal, new DateTimeZone($devTz));
            $d->setTimezone(new DateTimeZone($shopTz));
            return $d->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return date('Y-m-d H:i:s', is_numeric($deviceLocal) ? (int) $deviceLocal : strtotime($deviceLocal));
        }
    }

    /** Wall-clock now in the device's own timezone — what syncTime() writes to the reader. */
    protected function deviceNow()
    {
        $devTz = $this->dev['timezone'] ? $this->dev['timezone'] : $this->shopTz();
        try { $d = new DateTime('now', new DateTimeZone($this->shopTz())); $d->setTimezone(new DateTimeZone($devTz)); return $d->format('Y-m-d H:i:s'); }
        catch (Exception $e) { return date('Y-m-d H:i:s'); }
    }

    /** Vendor verify codes onto the pulse_ta_punch enum. Unknown codes become 'other' rather than being dropped. */
    protected function mapVerify($code)
    {
        $c = Tools::strtolower(trim((string) $code));
        $numeric = array('0' => 'password', '1' => 'finger', '2' => 'card', '3' => 'finger', '4' => 'card', '5' => 'finger', '9' => 'other',
            '15' => 'face', '16' => 'other', '20' => 'palm', '25' => 'palm');
        if ($c !== '' && ctype_digit($c) && isset($numeric[$c])) { return $numeric[$c]; }
        foreach (array('finger' => 'finger', 'fp' => 'finger', 'face' => 'face', 'facial' => 'face', 'card' => 'card', 'rfid' => 'card', 'mifare' => 'card',
            'pin' => 'password', 'pwd' => 'password', 'password' => 'password', 'palm' => 'palm', 'vein' => 'vein', 'iris' => 'iris', 'mobile' => 'mobile', 'qr' => 'mobile') as $needle => $v) {
            if ($c !== '' && strpos($c, $needle) !== false) { return $v; }
        }
        return 'other';
    }

    /**
     * Vendor punch-state codes onto our direction enum, honouring the device's direction_mode:
     * a single reader bolted to the staff door is wired 'in' or 'out' and its state byte means nothing.
     */
    protected function mapDirection($code)
    {
        $mode = $this->dev['direction_mode'];
        if ($mode === 'in' || $mode === 'out') { return $mode; }
        if ($mode === 'auto') { return 'unknown'; }
        $c = Tools::strtolower(trim((string) $code));
        $numeric = array('0' => 'in', '1' => 'out', '2' => 'break_out', '3' => 'break_in', '4' => 'ot_in', '5' => 'ot_out');
        if ($c !== '' && ctype_digit($c)) { return isset($numeric[$c]) ? $numeric[$c] : 'unknown'; }
        foreach (array('checkin' => 'in', 'check_in' => 'in', 'entry' => 'in', 'in' => 'in', 'checkout' => 'out', 'check_out' => 'out', 'exit' => 'out', 'out' => 'out',
            'breakout' => 'break_out', 'breakin' => 'break_in', 'overtimein' => 'ot_in', 'overtimeout' => 'ot_out') as $needle => $v) {
            if ($c !== '' && strpos(str_replace(array(' ', '-'), '', $c), $needle) !== false) { return $v; }
        }
        return 'unknown';
    }

    /** Build one normalised punch. Every adapter funnels through this so the punch store never sees vendor shapes. */
    protected function punch($ref, $deviceLocalTime, $state = null, $verify = null, $workCode = '', $raw = null)
    {
        return array(
            'device_serial' => (string) $this->dev['serial'],
            'employee_ref' => trim((string) $ref),
            'punched_at' => $this->toShopTime($deviceLocalTime),
            'device_time' => date('Y-m-d H:i:s', is_numeric($deviceLocalTime) ? (int) $deviceLocalTime : strtotime((string) $deviceLocalTime)),
            'direction' => $this->mapDirection($state),
            'verify_mode' => $this->mapVerify($verify),
            'work_code' => Tools::substr((string) $workCode, 0, 16),
            'raw' => is_string($raw) ? Tools::substr($raw, 0, 1000) : Tools::substr(json_encode($raw === null ? array($ref, $deviceLocalTime, $state, $verify) : $raw), 0, 1000),
        );
    }

    /** Only punches at or after $since survive — devices happily hand back their whole log every time you ask. */
    protected function after($punches, $since)
    {
        if (!$since) { return $punches; }
        $cut = date('Y-m-d H:i:s', strtotime($since));
        $out = array();
        foreach ($punches as $p) { if ($p['punched_at'] >= $cut) { $out[] = $p; } }
        return $out;
    }

    /* ---------- defaults an adapter may override ---------- */

    public function syncTime() { $this->fail('time sync', PulseTaDeviceException::UNSUPPORTED); }
    public function pushUser(array $employee) { $this->fail('user provisioning', PulseTaDeviceException::UNSUPPORTED); }
    public function deleteUser($deviceUserId) { $this->fail('user removal', PulseTaDeviceException::UNSUPPORTED); }
    public function pullUsers() { $this->fail('user listing', PulseTaDeviceException::UNSUPPORTED); }
    public function clearLog() { $this->fail('log clearing', PulseTaDeviceException::UNSUPPORTED); }
    public function deviceInfo() { return array('vendor' => $this->vendor, 'name' => $this->name(), 'serial' => $this->dev['serial'], 'firmware' => '', 'users' => 0, 'punches' => 0); }

    public function capabilities()
    {
        return array('vendor' => $this->vendor, 'pull' => true, 'push_endpoint' => false, 'sync_time' => false, 'push_user' => false, 'delete_user' => false,
            'pull_users' => false, 'clear_log' => false, 'card' => false, 'face' => false, 'palm' => false, 'realtime' => false, 'work_codes' => false);
    }
}
