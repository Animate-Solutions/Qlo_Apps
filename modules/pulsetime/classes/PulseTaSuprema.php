<?php
/**
 * Suprema BioStar 2 local REST API (the server on the property's own PC, not the cloud).
 *
 * Session:  POST /api/login  {"User":{"login_id":..,"password":..}}  -> the `bs-session-id` response header,
 *           which every later call must carry. The session is cached for the life of this object.
 * Punches:  POST /api/events/search with a Query block (period condition + optional device filter + paging).
 * Users:    GET/POST /api/users, DELETE /api/users/{id}
 * Fleet:    GET /api/devices
 *
 * BioStar 2 ships with a self-signed certificate on 443. Certificate verification stays ON by default; the
 * `allow_self_signed` device option turns it off explicitly, so nobody disables TLS checking by accident.
 */
class PulseTaSuprema extends PulseTaDeviceBase
{
    protected $vendor = 'suprema';
    protected $defaultPort = 443;

    protected $session = '';

    protected function iso($when) { $ts = is_numeric($when) ? (int) $when : strtotime($when); return gmdate('Y-m-d\TH:i:s.000\Z', $ts); }

    /** BioStar 2 rejects calls without a browser-ish User-Agent, which is why it is set explicitly. */
    protected function headers()
    {
        $h = array('Content-Type: application/json', 'Accept: application/json', 'User-Agent: Mozilla/5.0 (compatible; PulseTime/'.(class_exists('PulseTime') ? PulseTime::VERSION : '1.0.0').')');
        if ($this->session !== '') { $h[] = 'bs-session-id: '.$this->session; }
        return $h;
    }

    protected function login()
    {
        if ($this->session !== '') { return $this->session; }
        if ($this->cred('user') === '') { $this->fail('no BioStar 2 operator stored', PulseTaDeviceException::NOT_CONFIGURED); }
        $body = json_encode(array('User' => array('login_id' => $this->cred('user'), 'password' => $this->cred('password'))));
        $r = $this->http('POST', 'api/login', $body, $this->headers());
        if ((int) $r['http'] >= 400) { $this->fail('BioStar 2 refused the login (HTTP '.$r['http'].')', PulseTaDeviceException::AUTH_FAILED); }
        $sid = isset($r['headers']['bs-session-id']) ? $r['headers']['bs-session-id'] : '';
        if ($sid === '') { $this->fail('BioStar 2 returned no bs-session-id header', PulseTaDeviceException::BAD_RESPONSE); }
        $this->session = $sid;
        return $sid;
    }

    protected function call($method, $path, array $body = null)
    {
        $this->login();
        $r = $this->http($method, $path, $body === null ? null : json_encode($body), $this->headers());
        if ((int) $r['http'] === 401) { $this->session = ''; $this->login(); $r = $this->http($method, $path, $body === null ? null : json_encode($body), $this->headers()); }
        if ((int) $r['http'] >= 400) { $this->fail('BioStar 2 answered HTTP '.$r['http'].' for '.$path, PulseTaDeviceException::REJECTED); }
        $j = json_decode($r['body'], true);
        if (!is_array($j)) { $this->fail('non-JSON reply from '.$path, PulseTaDeviceException::BAD_RESPONSE); }
        return $j;
    }

    public function testConnection()
    {
        $this->login();
        $d = $this->call('GET', 'api/devices');
        $list = isset($d['DeviceCollection']['rows']) ? $d['DeviceCollection']['rows'] : (isset($d['rows']) ? $d['rows'] : array());
        $mine = null;
        foreach ((array) $list as $row) { if ($this->dev['serial'] !== '' && (string) (isset($row['id']) ? $row['id'] : '') === (string) $this->dev['serial']) { $mine = $row; } }
        $u = $this->call('GET', 'api/users?limit=1');
        $total = isset($u['UserCollection']['total']) ? (int) $u['UserCollection']['total'] : 0;
        return array('ok' => true, 'firmware' => $mine && isset($mine['firmware_version']) ? $mine['firmware_version'] : '',
            'model' => $mine && isset($mine['device_type_name']) ? $mine['device_type_name'] : 'BioStar 2 server',
            'serial' => $this->dev['serial'], 'users' => $total, 'punches' => 0, 'device_time' => '', 'drift_sec' => null,
            'message' => 'BioStar 2 reachable — '.count((array) $list).' device(s) on the server, '.$total.' user(s)'.($this->dev['serial'] !== '' && !$mine ? '. WARNING: device id "'.$this->dev['serial'].'" is not in that list.' : ''));
    }

    public function pullPunches($since)
    {
        $from = $since ? strtotime($since) : strtotime('-2 day');
        $conditions = array(array('column' => 'datetime', 'operator' => 3, 'values' => array($this->iso($from), $this->iso(time() + 300))));
        if ($this->dev['serial'] !== '' && !$this->opt('all_devices')) { $conditions[] = array('column' => 'device_id.id', 'operator' => 0, 'values' => array((string) $this->dev['serial'])); }
        $codes = trim((string) $this->opt('event_codes', ''));
        if ($codes !== '') { $conditions[] = array('column' => 'event_type_id.code', 'operator' => 0, 'values' => array_map('trim', explode(',', $codes))); }
        $limit = (int) $this->opt('page_size', 200); $offset = 0; $out = array(); $guard = 0;
        while ($guard < (int) $this->opt('max_pages', 100)) {
            $guard++;
            $r = $this->call('POST', 'api/events/search', array('Query' => array('limit' => $limit, 'offset' => $offset, 'conditions' => $conditions,
                'orders' => array(array('column' => 'datetime', 'descending' => false)))));
            $rows = isset($r['EventCollection']['rows']) ? $r['EventCollection']['rows'] : (isset($r['rows']) ? $r['rows'] : array());
            foreach ((array) $rows as $e) {
                $ref = isset($e['user_id']['user_id']) ? (string) $e['user_id']['user_id'] : (isset($e['user_id']) && is_scalar($e['user_id']) ? (string) $e['user_id'] : '');
                if ($ref === '' || $ref === '0') { continue; }
                $when = isset($e['datetime']) ? date('Y-m-d H:i:s', strtotime($e['datetime'])) : '';
                if (!$when) { continue; }
                $code = isset($e['event_type_id']['code']) ? $e['event_type_id']['code'] : '';
                $out[] = $this->punch($ref, $when, isset($e['tna_key']) ? $e['tna_key'] : '', $this->supremaVerify($code), '', $e);
            }
            if (count((array) $rows) < $limit) { break; }
            $offset += $limit;
        }
        return $this->after($out, $since);
    }

    /** BioStar 2 event codes: 4096 family = verify success, with the low bits naming the credential. */
    protected function supremaVerify($code)
    {
        $c = (int) $code;
        if ($c === 0) { return ''; }
        $map = array(4097 => 'finger', 4098 => 'face', 4099 => 'card', 4100 => 'password', 4101 => 'card', 4102 => 'finger', 4361 => 'face');
        return isset($map[$c]) ? $map[$c] : '';
    }

    public function pullUsers()
    {
        $limit = (int) $this->opt('page_size', 200); $offset = 0; $out = array(); $guard = 0;
        while ($guard < 100) {
            $guard++;
            $r = $this->call('GET', 'api/users?limit='.$limit.'&offset='.$offset);
            $rows = isset($r['UserCollection']['rows']) ? $r['UserCollection']['rows'] : (isset($r['rows']) ? $r['rows'] : array());
            foreach ((array) $rows as $u) {
                $out[] = array('device_user_id' => isset($u['user_id']) ? (string) $u['user_id'] : '', 'name' => isset($u['name']) ? $u['name'] : '',
                    'card_no' => isset($u['cards'][0]['card_id']) ? (string) $u['cards'][0]['card_id'] : '', 'privilege' => isset($u['permission']['id']) ? (int) $u['permission']['id'] : 0,
                    'has_finger' => !empty($u['fingerprint_templates']) ? 1 : 0, 'has_face' => (!empty($u['face_templates']) || !empty($u['visualFaces'])) ? 1 : 0,
                    'has_card' => !empty($u['cards']) ? 1 : 0, 'has_password' => !empty($u['password']) ? 1 : 0);
            }
            if (count((array) $rows) < $limit) { break; }
            $offset += $limit;
        }
        return $out;
    }

    public function pushUser(array $employee)
    {
        $ref = trim((string) (isset($employee['device_user_id']) ? $employee['device_user_id'] : ''));
        if ($ref === '') { $this->fail('no device user id supplied', PulseTaDeviceException::REJECTED); }
        $body = array('User' => array('user_id' => $ref, 'name' => Tools::substr((string) (isset($employee['name']) ? $employee['name'] : ''), 0, 48),
            'user_group_id' => array('id' => (string) $this->opt('user_group_id', '1')),
            'start_datetime' => $this->iso(isset($employee['valid_from']) ? $employee['valid_from'] : 'now'),
            'expiry_datetime' => $this->iso(isset($employee['valid_to']) ? $employee['valid_to'] : strtotime('+10 year')),
            'access_groups' => array(array('id' => (string) $this->opt('access_group_id', '1')))));
        if (!empty($employee['card_no'])) { $body['User']['cards'] = array(array('card_id' => (string) $employee['card_no'], 'type' => array('id' => '1'))); }
        if (!empty($employee['password'])) { $body['User']['password'] = (string) $employee['password']; }
        $exists = false;
        try { $chk = $this->call('GET', 'api/users/'.rawurlencode($ref)); $exists = isset($chk['User']); } catch (Exception $e) { $exists = false; }
        $r = $exists ? $this->call('PUT', 'api/users/'.rawurlencode($ref), $body) : $this->call('POST', 'api/users', $body);
        return array('ok' => true, 'device_user_id' => $ref, 'raw' => isset($r['Response']['code']) ? $r['Response']['code'] : 'ok',
            'note' => 'Fingerprint or face templates are enrolled at the reader or in BioStar 2 — this writes the identity and the access group.');
    }

    public function deleteUser($deviceUserId)
    {
        $this->call('DELETE', 'api/users/'.rawurlencode((string) $deviceUserId));
        return array('ok' => true);
    }

    public function deviceInfo()
    {
        $d = $this->call('GET', 'api/devices');
        $list = isset($d['DeviceCollection']['rows']) ? $d['DeviceCollection']['rows'] : (isset($d['rows']) ? $d['rows'] : array());
        foreach ((array) $list as $row) {
            if ($this->dev['serial'] === '' || (string) (isset($row['id']) ? $row['id'] : '') === (string) $this->dev['serial']) {
                return array('vendor' => $this->vendor, 'name' => isset($row['name']) ? $row['name'] : '', 'serial' => isset($row['id']) ? (string) $row['id'] : '',
                    'firmware' => isset($row['firmware_version']) ? $row['firmware_version'] : '', 'model' => isset($row['device_type_name']) ? $row['device_type_name'] : '',
                    'users' => isset($row['user_count']) ? (int) $row['user_count'] : 0, 'punches' => 0, 'status' => isset($row['status']) ? $row['status'] : '');
            }
        }
        return array('vendor' => $this->vendor, 'name' => 'BioStar 2', 'serial' => $this->dev['serial'], 'firmware' => '', 'users' => 0, 'punches' => 0);
    }

    public function capabilities()
    {
        return array('vendor' => 'Suprema BioStar 2 (local REST)', 'pull' => true, 'push_endpoint' => false, 'sync_time' => false, 'push_user' => true,
            'delete_user' => true, 'pull_users' => true, 'clear_log' => false, 'card' => true, 'face' => true, 'palm' => false, 'realtime' => false, 'work_codes' => false,
            'default_port' => 443, 'self_signed_option' => true,
            'verify_note' => 'BioStar 2 changed its event and user payloads between 2.7, 2.8 and 2.9 — run Test connection and Preview punches after any server upgrade.');
    }
}
