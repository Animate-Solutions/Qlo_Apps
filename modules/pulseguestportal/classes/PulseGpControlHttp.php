<?php
/**
 * Generic HTTP adapter for a room controller / BMS gateway that speaks JSON over the LAN.
 *
 * Request  POST {endpoint}/room/{room}/point/{code}   {"action":"on|off|toggle|open|close|set|scene","value":22}
 * Headers  Content-Type: application/json, X-Pulse-Signature: hex hmac-sha256 of the raw body with the shared key,
 *          X-Pulse-Timestamp: unix seconds (the gateway should reject anything older than its own window)
 * Reply    {"ok":true,"state":"on","value":22}            — anything else is treated as a failure
 * Discover GET  {endpoint}/room/{room}/points  → {"points":[{"code","type","label","state","value","min","max"}]}
 * Probe    GET  {endpoint}/health
 *
 * Every call has a hard timeout and one retry on a connect-level failure; a failure never blocks the guest
 * screen, it degrades to "we could not reach the room controller — the desk can do this for you".
 */
class PulseGpControlHttp implements PulseGpControlInterface
{
    protected $cfg;
    public function __construct(array $config) { $this->cfg = array_merge(array('endpoint' => '', 'key' => '', 'timeout' => 4, 'insecure' => 0), $config); }

    protected function url($path) { return rtrim($this->cfg['endpoint'], '/').'/'.ltrim($path, '/'); }
    protected function sign($body) { return hash_hmac('sha256', $body, (string) $this->cfg['key']); }

    protected function call($method, $path, array $body = null)
    {
        if (!$this->cfg['endpoint']) { return array('ok' => false, 'message' => 'No room-controller endpoint is configured'); }
        $raw = $body === null ? '' : json_encode($body);
        $ts = time();
        $headers = array('Content-Type: application/json', 'X-Pulse-Timestamp: '.$ts, 'X-Pulse-Signature: '.$this->sign($ts.'.'.$raw));
        $attempt = 0; $lastErr = '';
        while ($attempt < 2) {
            $attempt++;
            $ch = curl_init($this->url($path));
            $o = array(CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => (int) $this->cfg['timeout'], CURLOPT_CONNECTTIMEOUT => min(3, (int) $this->cfg['timeout']),
                CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => 0);
            if ($raw !== '') { $o[CURLOPT_POSTFIELDS] = $raw; }
            if (!empty($this->cfg['insecure'])) { $o[CURLOPT_SSL_VERIFYPEER] = 0; $o[CURLOPT_SSL_VERIFYHOST] = 0; }
            curl_setopt_array($ch, $o);
            $res = curl_exec($ch); $err = curl_error($ch); $no = curl_errno($ch); $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($err === '') {
                $j = json_decode($res, true);
                if (!is_array($j)) { return array('ok' => false, 'message' => 'Room controller replied with non-JSON (HTTP '.$http.')'); }
                if ($http >= 400) { return array('ok' => false, 'message' => isset($j['error']) ? $j['error'] : 'Room controller returned HTTP '.$http); }
                return $j + array('ok' => true);
            }
            $lastErr = $err;
            if ($no !== CURLE_OPERATION_TIMEOUTED && $no !== CURLE_COULDNT_CONNECT) { break; }
            usleep(200000);
        }
        return array('ok' => false, 'message' => 'Room controller unreachable ('.$lastErr.')');
    }

    public function apply($idRoom, array $point, $action, $value = null)
    {
        $r = $this->call('POST', 'room/'.(int) $idRoom.'/point/'.rawurlencode($point['code']), array('action' => $action, 'value' => $value === null ? null : (float) $value, 'endpoint' => $point['endpoint']));
        return array('ok' => !empty($r['ok']), 'state' => isset($r['state']) ? (string) $r['state'] : $point['state'], 'value' => isset($r['value']) ? (float) $r['value'] : (float) $point['value'],
            'message' => isset($r['message']) ? $r['message'] : (empty($r['ok']) ? 'failed' : 'ok'));
    }
    public function read($idRoom, array $point)
    {
        $r = $this->call('GET', 'room/'.(int) $idRoom.'/point/'.rawurlencode($point['code']));
        return array('ok' => !empty($r['ok']), 'state' => isset($r['state']) ? (string) $r['state'] : $point['state'], 'value' => isset($r['value']) ? (float) $r['value'] : (float) $point['value'],
            'message' => isset($r['message']) ? $r['message'] : 'ok');
    }
    public function discover($idRoom)
    {
        $r = $this->call('GET', 'room/'.(int) $idRoom.'/points');
        if (empty($r['ok']) || !isset($r['points']) || !is_array($r['points'])) { return array(); }
        $out = array(); $i = 0;
        foreach ($r['points'] as $p) {
            if (empty($p['code'])) { continue; }
            $out[] = array('code' => Tools::substr((string) $p['code'], 0, 32), 'type' => in_array(isset($p['type']) ? $p['type'] : '', array('light', 'ac', 'curtain', 'scene', 'tv', 'socket')) ? $p['type'] : 'light',
                'label' => Tools::substr(isset($p['label']) ? $p['label'] : $p['code'], 0, 64), 'state' => isset($p['state']) ? Tools::substr($p['state'], 0, 32) : 'off',
                'value' => isset($p['value']) ? (float) $p['value'] : 0, 'min_value' => isset($p['min']) ? (float) $p['min'] : 0, 'max_value' => isset($p['max']) ? (float) $p['max'] : 100,
                'endpoint' => isset($p['endpoint']) ? Tools::substr($p['endpoint'], 0, 255) : '', 'sort' => ++$i);
        }
        return $out;
    }
    public function test() { $r = $this->call('GET', 'health'); return array('ok' => !empty($r['ok']), 'message' => isset($r['message']) ? $r['message'] : (empty($r['ok']) ? 'No answer from the room controller' : 'Room controller answered')); }
}
