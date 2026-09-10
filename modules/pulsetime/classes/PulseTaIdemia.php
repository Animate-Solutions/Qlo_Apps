<?php
/**
 * IDEMIA / Morpho SIGMA (SIGMA Lite, Lite+, Wide, VISIONPASS) over the terminal's HTTP/JSON
 * remote-messaging interface.
 *
 * Read this before you deploy it: IDEMIA's full integration stack — MorphoManager, the MA5G SDK, the
 * biometric template format — is licensed and under NDA, and this module ships none of it. What is
 * implemented here is the documented HTTP/JSON surface a SIGMA terminal exposes when remote messaging is
 * enabled on it: an enrolment push, a transaction pull and a heartbeat. Every path, the auth style and the
 * field names are device options, because IDEMIA versions them per firmware and per distributor.
 *
 *   path_transactions   transactions      ?from=&to=&limit=&offset=
 *   path_users          users
 *   path_heartbeat      status
 *   auth_style          bearer | basic | api_key   (api_key uses header_name, default X-API-Key)
 *   field_user / field_time / field_direction / field_verify  — rename the response fields without a release
 *
 * Verify against your firmware version before the first live payroll run: pull a day, compare the punch
 * times against the terminal's own event list, and only then enable the device.
 */
class PulseTaIdemia extends PulseTaDeviceBase
{
    protected $vendor = 'idemia';
    protected $defaultPort = 443;

    protected function authHeaders()
    {
        $style = (string) $this->opt('auth_style', 'bearer');
        $h = array('Content-Type: application/json', 'Accept: application/json');
        if ($style === 'api_key') { $h[] = ((string) $this->opt('header_name', 'X-API-Key')).': '.$this->cred('api_key'); }
        elseif ($style === 'bearer') { $t = $this->cred('api_key', $this->cred('token')); if ($t !== '') { $h[] = 'Authorization: Bearer '.$t; } }
        return $h;
    }

    protected function authMode() { return (string) $this->opt('auth_style', 'bearer') === 'basic' ? 'basic' : 'none'; }

    protected function call($method, $path, array $body = null)
    {
        $r = $this->http($method, $path, $body === null ? null : json_encode($body), $this->authHeaders(), $this->authMode());
        if ((int) $r['http'] >= 400) { $this->fail('SIGMA answered HTTP '.$r['http'].' for '.$path, (int) $r['http'] === 404 ? PulseTaDeviceException::NOT_CONFIGURED : PulseTaDeviceException::REJECTED); }
        $j = json_decode($r['body'], true);
        if (!is_array($j)) { $this->fail('non-JSON reply from '.$path.' — check path_'.$path.' against your firmware', PulseTaDeviceException::BAD_RESPONSE); }
        return $j;
    }

    /** Response shapes vary; pull the list out of whichever wrapper this firmware used. */
    protected function listOf($j)
    {
        foreach (array('transactions', 'data', 'items', 'records', 'events', 'users', 'list') as $k) { if (isset($j[$k]) && is_array($j[$k])) { return $j[$k]; } }
        return isset($j[0]) && is_array($j[0]) ? $j : array();
    }

    protected function f($row, $optKey, $default) { $k = (string) $this->opt($optKey, $default); return isset($row[$k]) ? $row[$k] : null; }

    public function testConnection()
    {
        $j = $this->call('GET', (string) $this->opt('path_heartbeat', 'status'));
        $time = isset($j['datetime']) ? $j['datetime'] : (isset($j['time']) ? $j['time'] : '');
        $time = $time ? date('Y-m-d H:i:s', strtotime($time)) : '';
        $drift = $time ? (strtotime($time) - strtotime($this->deviceNow())) : null;
        return array('ok' => true, 'firmware' => isset($j['firmware']) ? $j['firmware'] : (isset($j['version']) ? $j['version'] : ''),
            'model' => isset($j['model']) ? $j['model'] : 'SIGMA', 'serial' => isset($j['serial']) ? $j['serial'] : $this->dev['serial'],
            'users' => isset($j['userCount']) ? (int) $j['userCount'] : 0, 'punches' => isset($j['transactionCount']) ? (int) $j['transactionCount'] : 0,
            'device_time' => $time, 'drift_sec' => $drift,
            'message' => 'SIGMA remote messaging reachable. Verify the transaction times against the terminal before the first payroll run.');
    }

    public function pullPunches($since)
    {
        $from = $since ? date('c', strtotime($since)) : date('c', strtotime('-2 day'));
        $to = date('c', time() + 300);
        $limit = (int) $this->opt('page_size', 100); $offset = 0; $out = array(); $guard = 0;
        while ($guard < (int) $this->opt('max_pages', 100)) {
            $guard++;
            $q = http_build_query(array((string) $this->opt('param_from', 'from') => $from, (string) $this->opt('param_to', 'to') => $to,
                (string) $this->opt('param_limit', 'limit') => $limit, (string) $this->opt('param_offset', 'offset') => $offset));
            $rows = $this->listOf($this->call('GET', (string) $this->opt('path_transactions', 'transactions').'?'.$q));
            foreach ((array) $rows as $e) {
                if (!is_array($e)) { continue; }
                $ref = $this->f($e, 'field_user', 'userId');
                if ($ref === null) { $ref = isset($e['user_id']) ? $e['user_id'] : (isset($e['personId']) ? $e['personId'] : null); }
                if ($ref === null || (string) $ref === '') { continue; }
                $when = $this->f($e, 'field_time', 'datetime');
                if ($when === null) { $when = isset($e['timestamp']) ? $e['timestamp'] : (isset($e['time']) ? $e['time'] : null); }
                if ($when === null) { continue; }
                $ts = is_numeric($when) ? (int) $when : strtotime((string) $when);
                if (!$ts) { continue; }
                $out[] = $this->punch((string) $ref, date('Y-m-d H:i:s', $ts), $this->f($e, 'field_direction', 'direction'), $this->f($e, 'field_verify', 'authType'), '', $e);
            }
            if (count((array) $rows) < $limit) { break; }
            $offset += $limit;
        }
        return $this->after($out, $since);
    }

    public function pullUsers()
    {
        $rows = $this->listOf($this->call('GET', (string) $this->opt('path_users', 'users')));
        $out = array();
        foreach ((array) $rows as $u) {
            if (!is_array($u)) { continue; }
            $out[] = array('device_user_id' => (string) (isset($u['userId']) ? $u['userId'] : (isset($u['id']) ? $u['id'] : '')),
                'name' => isset($u['name']) ? $u['name'] : trim((isset($u['firstName']) ? $u['firstName'] : '').' '.(isset($u['lastName']) ? $u['lastName'] : '')),
                'card_no' => isset($u['cardNumber']) ? (string) $u['cardNumber'] : '', 'privilege' => 0,
                'has_finger' => !empty($u['fingers']) || !empty($u['biometrics']) ? 1 : 0, 'has_face' => !empty($u['face']) ? 1 : 0,
                'has_card' => !empty($u['cardNumber']) ? 1 : 0, 'has_password' => !empty($u['pin']) ? 1 : 0);
        }
        return $out;
    }

    public function pushUser(array $employee)
    {
        $ref = trim((string) (isset($employee['device_user_id']) ? $employee['device_user_id'] : ''));
        if ($ref === '') { $this->fail('no device user id supplied', PulseTaDeviceException::REJECTED); }
        $name = trim((string) (isset($employee['name']) ? $employee['name'] : ''));
        $parts = explode(' ', $name, 2);
        $body = array('userId' => $ref, 'name' => Tools::substr($name, 0, 48), 'firstName' => $parts[0], 'lastName' => isset($parts[1]) ? $parts[1] : '',
            'validFrom' => date('c', strtotime(isset($employee['valid_from']) ? $employee['valid_from'] : 'now')),
            'validTo' => date('c', strtotime(isset($employee['valid_to']) ? $employee['valid_to'] : '+10 year')));
        if (!empty($employee['card_no'])) { $body['cardNumber'] = (string) $employee['card_no']; }
        if (!empty($employee['password'])) { $body['pin'] = (string) $employee['password']; }
        $this->call((string) $this->opt('user_method', 'POST'), (string) $this->opt('path_users', 'users'), $body);
        return array('ok' => true, 'device_user_id' => $ref,
            'note' => 'Biometric templates are IDEMIA-format and are enrolled at the terminal or through MorphoManager — Pulse writes the identity and validity only.');
    }

    public function deleteUser($deviceUserId)
    {
        $this->call('DELETE', (string) $this->opt('path_users', 'users').'/'.rawurlencode((string) $deviceUserId));
        return array('ok' => true);
    }

    public function deviceInfo()
    {
        $j = $this->call('GET', (string) $this->opt('path_heartbeat', 'status'));
        return array('vendor' => $this->vendor, 'name' => isset($j['model']) ? $j['model'] : 'SIGMA', 'serial' => isset($j['serial']) ? $j['serial'] : $this->dev['serial'],
            'firmware' => isset($j['firmware']) ? $j['firmware'] : '', 'users' => isset($j['userCount']) ? (int) $j['userCount'] : 0, 'punches' => isset($j['transactionCount']) ? (int) $j['transactionCount'] : 0);
    }

    public function capabilities()
    {
        return array('vendor' => 'IDEMIA / Morpho SIGMA (HTTP remote messaging)', 'pull' => true, 'push_endpoint' => false, 'sync_time' => false, 'push_user' => true,
            'delete_user' => true, 'pull_users' => true, 'clear_log' => false, 'card' => true, 'face' => true, 'palm' => false, 'realtime' => false, 'work_codes' => false,
            'default_port' => 443, 'licensed_sdk' => true,
            'verify_note' => 'NOT vendor-certified. MorphoManager and the MA5G SDK are licensed and under NDA and are not shipped. Every path, the auth style and the response field names are device options. Pull one day and reconcile against the terminal event list before enabling this device for payroll.');
    }
}
