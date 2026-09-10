<?php
/**
 * Anviz — CrossChex Cloud / CrossChex Standard OpenAPI.
 *
 * Two shapes are supported because Anviz ships both:
 *
 *  mode=openapi (default) — the CrossChex envelope. Every call is a POST to one endpoint with
 *      {"header":{"nameSpace":..,"nameAction":..,"version":"1.0","requestId":..,"timestamp":..},"payload":{..}}
 *      authorize.token / token           -> a bearer token valid for a period the reply states
 *      attendance.record / getrecord     -> {begin_time, end_time, order, page, per_page}
 *      employee.employee / getemployee   -> {page, per_page}
 *      employee.employee / addemployee   -> the enrolment push
 *
 *  mode=rest — the flat form some CrossChex Standard builds expose: a token endpoint plus
 *      GET /api/employee and GET /api/records?begin=..&end=..  with Authorization: Bearer.
 *
 * The older TC/IP binary protocol on port 5010 is deliberately NOT implemented: it needs Anviz's SDK
 * documentation under NDA, and guessing at it would put wrong times on payslips. Devices on that protocol
 * should export to a file and use the CSV adapter, or be moved onto CrossChex.
 */
class PulseTaAnviz extends PulseTaDeviceBase
{
    protected $vendor = 'anviz';
    protected $defaultPort = 443;

    protected $token = '';

    protected function mode() { return $this->opt('mode', 'openapi') === 'rest' ? 'rest' : 'openapi'; }
    protected function iso($when) { $ts = is_numeric($when) ? (int) $when : strtotime($when); return date('Y-m-d\TH:i:sP', $ts); }
    protected function requestId() { return sprintf('%08x-%04x-%04x-%04x-%012x', mt_rand(0, 0xffffffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffffffff)); }

    /** One CrossChex envelope call. */
    protected function envelope($nameSpace, $nameAction, array $payload)
    {
        $body = json_encode(array('header' => array('nameSpace' => $nameSpace, 'nameAction' => $nameAction, 'version' => (string) $this->opt('api_version', '1.0'),
            'requestId' => $this->requestId(), 'timestamp' => $this->iso(time())), 'payload' => $payload));
        $h = array('Content-Type: application/json', 'Accept: application/json');
        if ($this->token !== '') { $h[] = 'Authorization: Bearer '.$this->token; }
        $r = $this->http('POST', (string) $this->opt('api_path', ''), $body, $h);
        $j = json_decode($r['body'], true);
        if (!is_array($j)) { $this->fail('non-JSON reply from CrossChex: '.Tools::substr(trim(strip_tags($r['body'])), 0, 140), PulseTaDeviceException::BAD_RESPONSE); }
        $code = isset($j['header']['nameSpace']) && $j['header']['nameSpace'] === 'System' ? 500 : 0;
        if (isset($j['payload']['code']) && (int) $j['payload']['code'] !== 0 && (int) $j['payload']['code'] !== 200) {
            $this->fail(isset($j['payload']['message']) ? $j['payload']['message'] : ('CrossChex code '.$j['payload']['code']), PulseTaDeviceException::REJECTED);
        }
        if ($code) { $this->fail(isset($j['payload']['message']) ? $j['payload']['message'] : 'CrossChex system error', PulseTaDeviceException::REJECTED); }
        return isset($j['payload']) ? $j['payload'] : $j;
    }

    protected function auth()
    {
        if ($this->token !== '') { return $this->token; }
        $key = $this->cred('api_key'); $secret = $this->cred('api_secret', $this->cred('password'));
        if ($key === '' || $secret === '') { $this->fail('no CrossChex API key/secret stored', PulseTaDeviceException::NOT_CONFIGURED); }
        if ($this->mode() === 'rest') {
            $r = $this->json('POST', (string) $this->opt('path_token', 'api/oauth/token'), json_encode(array('grant_type' => 'client_credentials', 'client_id' => $key, 'client_secret' => $secret)));
            $t = isset($r['access_token']) ? $r['access_token'] : (isset($r['payload']['token']) ? $r['payload']['token'] : (isset($r['token']) ? $r['token'] : ''));
            if (!$t) { $this->fail('token endpoint returned no token', PulseTaDeviceException::AUTH_FAILED); }
            $this->token = $t;
            return $t;
        }
        $p = $this->envelope('authorize.token', 'token', array('api_key' => $key, 'api_secret' => $secret));
        $t = isset($p['token']) ? $p['token'] : '';
        if (!$t) { $this->fail('authorize.token returned no token', PulseTaDeviceException::AUTH_FAILED); }
        $this->token = $t;
        return $t;
    }

    protected function bearer() { return array('Authorization: Bearer '.$this->auth(), 'Content-Type: application/json', 'Accept: application/json'); }

    public function testConnection()
    {
        $this->auth();
        $users = 0;
        try {
            if ($this->mode() === 'rest') { $r = $this->json('GET', (string) $this->opt('path_users', 'api/employee').'?page=1&per_page=1', null, $this->bearer()); $users = isset($r['total']) ? (int) $r['total'] : 0; }
            else { $p = $this->envelope('employee.employee', 'getemployee', array('page' => 1, 'per_page' => 1)); $users = isset($p['pagination']['total']) ? (int) $p['pagination']['total'] : (isset($p['total']) ? (int) $p['total'] : 0); }
        } catch (Exception $e) { $users = 0; }
        return array('ok' => true, 'firmware' => 'CrossChex '.$this->mode(), 'model' => 'Anviz CrossChex', 'serial' => $this->dev['serial'], 'users' => $users, 'punches' => 0,
            'device_time' => '', 'drift_sec' => null, 'message' => 'CrossChex token accepted'.($users ? ' — '.$users.' employee(s) visible' : '').'.');
    }

    public function pullPunches($since)
    {
        $this->auth();
        $from = $since ? strtotime($since) : strtotime('-2 day');
        $out = array(); $page = 1; $per = (int) $this->opt('page_size', 100); $guard = 0;
        while ($guard < (int) $this->opt('max_pages', 100)) {
            $guard++;
            if ($this->mode() === 'rest') {
                $r = $this->json('GET', (string) $this->opt('path_records', 'api/records').'?begin='.rawurlencode($this->iso($from)).'&end='.rawurlencode($this->iso(time() + 300)).'&page='.$page.'&per_page='.$per, null, $this->bearer());
                $rows = isset($r['list']) ? $r['list'] : (isset($r['data']) ? $r['data'] : array());
            } else {
                $p = $this->envelope('attendance.record', 'getrecord', array('begin_time' => $this->iso($from), 'end_time' => $this->iso(time() + 300), 'order' => 'asc', 'page' => $page, 'per_page' => $per));
                $rows = isset($p['list']) ? $p['list'] : (isset($p['records']) ? $p['records'] : array());
            }
            foreach ((array) $rows as $e) {
                $ref = isset($e['workno']) ? (string) $e['workno'] : (isset($e['employee']['workno']) ? (string) $e['employee']['workno'] : (isset($e['user_id']) ? (string) $e['user_id'] : ''));
                if ($ref === '') { continue; }
                $when = isset($e['checktime']) ? $e['checktime'] : (isset($e['punch_time']) ? $e['punch_time'] : (isset($e['time']) ? $e['time'] : ''));
                if (!$when) { continue; }
                $out[] = $this->punch($ref, date('Y-m-d H:i:s', strtotime($when)), isset($e['checktype']) ? $e['checktype'] : (isset($e['type']) ? $e['type'] : ''),
                    isset($e['verifymode']) ? $e['verifymode'] : (isset($e['verify']) ? $e['verify'] : ''), isset($e['workcode']) ? $e['workcode'] : '', $e);
            }
            if (count((array) $rows) < $per) { break; }
            $page++;
        }
        return $this->after($out, $since);
    }

    public function pullUsers()
    {
        $this->auth();
        $out = array(); $page = 1; $per = (int) $this->opt('page_size', 100); $guard = 0;
        while ($guard < 100) {
            $guard++;
            if ($this->mode() === 'rest') { $r = $this->json('GET', (string) $this->opt('path_users', 'api/employee').'?page='.$page.'&per_page='.$per, null, $this->bearer()); $rows = isset($r['list']) ? $r['list'] : (isset($r['data']) ? $r['data'] : array()); }
            else { $p = $this->envelope('employee.employee', 'getemployee', array('page' => $page, 'per_page' => $per)); $rows = isset($p['list']) ? $p['list'] : array(); }
            foreach ((array) $rows as $u) {
                $out[] = array('device_user_id' => isset($u['workno']) ? (string) $u['workno'] : (isset($u['user_id']) ? (string) $u['user_id'] : ''),
                    'name' => trim((isset($u['first_name']) ? $u['first_name'] : '').' '.(isset($u['last_name']) ? $u['last_name'] : (isset($u['name']) ? $u['name'] : ''))),
                    'card_no' => isset($u['card']) ? (string) $u['card'] : '', 'privilege' => 0,
                    'has_finger' => !empty($u['fingerprint']) ? 1 : 0, 'has_face' => !empty($u['face']) ? 1 : 0, 'has_card' => !empty($u['card']) ? 1 : 0, 'has_password' => !empty($u['password']) ? 1 : 0);
            }
            if (count((array) $rows) < $per) { break; }
            $page++;
        }
        return $out;
    }

    public function pushUser(array $employee)
    {
        $this->auth();
        $ref = trim((string) (isset($employee['device_user_id']) ? $employee['device_user_id'] : ''));
        if ($ref === '') { $this->fail('no device user id supplied', PulseTaDeviceException::REJECTED); }
        $name = trim((string) (isset($employee['name']) ? $employee['name'] : ''));
        $parts = explode(' ', $name, 2);
        $payload = array('workno' => $ref, 'first_name' => $parts[0], 'last_name' => isset($parts[1]) ? $parts[1] : '', 'department' => (string) $this->opt('department_id', ''));
        if (!empty($employee['card_no'])) { $payload['card'] = (string) $employee['card_no']; }
        if ($this->mode() === 'rest') { $this->json('POST', (string) $this->opt('path_users', 'api/employee'), json_encode($payload), $this->bearer()); }
        else { $this->envelope('employee.employee', 'addemployee', $payload); }
        return array('ok' => true, 'device_user_id' => $ref, 'note' => 'Fingerprint or face is enrolled at the terminal — CrossChex distributes it to the devices in the group.');
    }

    public function deleteUser($deviceUserId)
    {
        $this->auth();
        if ($this->mode() === 'rest') { $this->http('DELETE', (string) $this->opt('path_users', 'api/employee').'/'.rawurlencode((string) $deviceUserId), null, $this->bearer()); }
        else { $this->envelope('employee.employee', 'deleteemployee', array('workno' => (string) $deviceUserId)); }
        return array('ok' => true);
    }

    public function deviceInfo()
    {
        $this->auth();
        return array('vendor' => $this->vendor, 'name' => 'Anviz CrossChex ('.$this->mode().')', 'serial' => $this->dev['serial'], 'firmware' => (string) $this->opt('api_version', '1.0'), 'users' => 0, 'punches' => 0);
    }

    public function capabilities()
    {
        return array('vendor' => 'Anviz CrossChex Cloud / OpenAPI', 'pull' => true, 'push_endpoint' => false, 'sync_time' => false, 'push_user' => true,
            'delete_user' => true, 'pull_users' => true, 'clear_log' => false, 'card' => true, 'face' => true, 'palm' => false, 'realtime' => false, 'work_codes' => true,
            'default_port' => 443,
            'verify_note' => 'Anviz issues the API key, secret and regional endpoint per account. The legacy TC/IP binary protocol on port 5010 is not implemented — export to file and use the CSV adapter for those terminals.');
    }
}
