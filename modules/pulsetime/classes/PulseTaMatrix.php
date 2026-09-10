<?php
/**
 * Matrix COSEC (VEGA, PANEL LITE, ARGO, DOOR controllers) over the COSEC REST API with HTTP Basic auth.
 *
 * Matrix publishes its API per product family and has moved the version segment more than once, so every
 * path here is a device option with a sensible default rather than a constant:
 *
 *   base            device endpoint field, default  cosec/api/v1
 *   path_events     attendance/events        ?from=&to=&page=&size=
 *   path_users      users
 *   path_status     device/status
 *
 * Verify against your firmware version. If your controller answers on a different path, change the option —
 * no code change is needed. Matrix also supports a CSV/SQL push from COSEC CENTRA; where a site has that,
 * the CSV adapter is the lower-risk route.
 */
class PulseTaMatrix extends PulseTaDeviceBase
{
    protected $vendor = 'matrix';
    protected $defaultPort = 80;

    protected function base() { return $this->dev['endpoint'] && trim($this->dev['endpoint'], '/') !== '' ? '' : 'cosec/api/v1/'; }

    protected function get($path, array $query = array())
    {
        $q = $query ? (strpos($path, '?') === false ? '?' : '&').http_build_query($query) : '';
        return $this->json('GET', $this->base().$path.$q, null, array(), 'basic');
    }

    public function testConnection()
    {
        $r = $this->get((string) $this->opt('path_status', 'device/status'));
        $d = isset($r['data']) ? $r['data'] : $r;
        $time = isset($d['dateTime']) ? date('Y-m-d H:i:s', strtotime($d['dateTime'])) : (isset($d['time']) ? date('Y-m-d H:i:s', strtotime($d['time'])) : '');
        $drift = $time ? (strtotime($time) - strtotime($this->deviceNow())) : null;
        return array('ok' => true, 'firmware' => isset($d['firmwareVersion']) ? $d['firmwareVersion'] : (isset($d['version']) ? $d['version'] : ''),
            'model' => isset($d['model']) ? $d['model'] : 'COSEC', 'serial' => isset($d['serialNumber']) ? $d['serialNumber'] : $this->dev['serial'],
            'users' => isset($d['userCount']) ? (int) $d['userCount'] : 0, 'punches' => isset($d['eventCount']) ? (int) $d['eventCount'] : 0,
            'device_time' => $time, 'drift_sec' => $drift, 'message' => 'COSEC REST reachable at '.rtrim($this->baseUrl(), '/').'/'.$this->base());
    }

    public function pullPunches($since)
    {
        $from = $since ? date('Y-m-d H:i:s', strtotime($since)) : date('Y-m-d H:i:s', strtotime('-2 day'));
        $to = date('Y-m-d H:i:s', time() + 300);
        $page = (int) $this->opt('first_page', 1); $size = (int) $this->opt('page_size', 100); $out = array(); $guard = 0;
        while ($guard < (int) $this->opt('max_pages', 100)) {
            $guard++;
            $r = $this->get((string) $this->opt('path_events', 'attendance/events'), array(
                (string) $this->opt('param_from', 'from') => $from, (string) $this->opt('param_to', 'to') => $to,
                (string) $this->opt('param_page', 'page') => $page, (string) $this->opt('param_size', 'size') => $size));
            $rows = isset($r['data']) && is_array($r['data']) ? $r['data'] : (isset($r['events']) ? $r['events'] : (isset($r['records']) ? $r['records'] : array()));
            if (isset($rows['events'])) { $rows = $rows['events']; }
            foreach ((array) $rows as $e) {
                if (!is_array($e)) { continue; }
                $ref = isset($e['userId']) ? (string) $e['userId'] : (isset($e['UserID']) ? (string) $e['UserID'] : (isset($e['employeeCode']) ? (string) $e['employeeCode'] : ''));
                if ($ref === '') { continue; }
                $when = isset($e['eventDateTime']) ? $e['eventDateTime'] : (isset($e['dateTime']) ? $e['dateTime'] : (isset($e['date']) && isset($e['time']) ? $e['date'].' '.$e['time'] : ''));
                if (!$when) { continue; }
                $out[] = $this->punch($ref, date('Y-m-d H:i:s', strtotime($when)),
                    isset($e['inOut']) ? $e['inOut'] : (isset($e['direction']) ? $e['direction'] : (isset($e['eventType']) ? $e['eventType'] : '')),
                    isset($e['authMode']) ? $e['authMode'] : (isset($e['credential']) ? $e['credential'] : ''), isset($e['workCode']) ? $e['workCode'] : '', $e);
            }
            if (count((array) $rows) < $size) { break; }
            $page++;
        }
        return $this->after($out, $since);
    }

    public function pullUsers()
    {
        $out = array(); $page = (int) $this->opt('first_page', 1); $size = (int) $this->opt('page_size', 100); $guard = 0;
        while ($guard < 100) {
            $guard++;
            $r = $this->get((string) $this->opt('path_users', 'users'), array((string) $this->opt('param_page', 'page') => $page, (string) $this->opt('param_size', 'size') => $size));
            $rows = isset($r['data']) && is_array($r['data']) ? $r['data'] : (isset($r['users']) ? $r['users'] : array());
            foreach ((array) $rows as $u) {
                if (!is_array($u)) { continue; }
                $out[] = array('device_user_id' => isset($u['userId']) ? (string) $u['userId'] : (isset($u['UserID']) ? (string) $u['UserID'] : ''),
                    'name' => isset($u['name']) ? $u['name'] : trim((isset($u['firstName']) ? $u['firstName'] : '').' '.(isset($u['lastName']) ? $u['lastName'] : '')),
                    'card_no' => isset($u['cardNumber']) ? (string) $u['cardNumber'] : '', 'privilege' => 0,
                    'has_finger' => !empty($u['fingerCount']) ? 1 : 0, 'has_face' => !empty($u['faceCount']) ? 1 : 0,
                    'has_card' => !empty($u['cardNumber']) ? 1 : 0, 'has_password' => !empty($u['pin']) ? 1 : 0);
            }
            if (count((array) $rows) < $size) { break; }
            $page++;
        }
        return $out;
    }

    public function pushUser(array $employee)
    {
        $ref = trim((string) (isset($employee['device_user_id']) ? $employee['device_user_id'] : ''));
        if ($ref === '') { $this->fail('no device user id supplied', PulseTaDeviceException::REJECTED); }
        $name = (string) (isset($employee['name']) ? $employee['name'] : '');
        $parts = explode(' ', trim($name), 2);
        $body = array('userId' => $ref, 'name' => Tools::substr($name, 0, 40), 'firstName' => $parts[0], 'lastName' => isset($parts[1]) ? $parts[1] : '',
            'userGroup' => (string) $this->opt('user_group', '1'), 'enabled' => true);
        if (!empty($employee['card_no'])) { $body['cardNumber'] = (string) $employee['card_no']; }
        if (!empty($employee['password'])) { $body['pin'] = (string) $employee['password']; }
        $r = $this->json('POST', $this->base().(string) $this->opt('path_users', 'users'), json_encode($body), array(), 'basic');
        if (isset($r['status']) && Tools::strtolower((string) $r['status']) === 'error') { $this->fail(isset($r['message']) ? $r['message'] : 'COSEC rejected the user', PulseTaDeviceException::REJECTED); }
        return array('ok' => true, 'device_user_id' => $ref, 'note' => 'Credentials (finger, face, card) are enrolled at the COSEC terminal or through COSEC CENTRA.');
    }

    public function deleteUser($deviceUserId)
    {
        $r = $this->http('DELETE', $this->base().(string) $this->opt('path_users', 'users').'/'.rawurlencode((string) $deviceUserId), null, array('Accept: application/json'), 'basic');
        if ((int) $r['http'] >= 400) { $this->fail('COSEC answered HTTP '.$r['http'].' to the delete', PulseTaDeviceException::REJECTED); }
        return array('ok' => true);
    }

    public function deviceInfo()
    {
        $r = $this->get((string) $this->opt('path_status', 'device/status'));
        $d = isset($r['data']) ? $r['data'] : $r;
        return array('vendor' => $this->vendor, 'name' => isset($d['deviceName']) ? $d['deviceName'] : 'COSEC', 'serial' => isset($d['serialNumber']) ? $d['serialNumber'] : $this->dev['serial'],
            'firmware' => isset($d['firmwareVersion']) ? $d['firmwareVersion'] : '', 'users' => isset($d['userCount']) ? (int) $d['userCount'] : 0, 'punches' => isset($d['eventCount']) ? (int) $d['eventCount'] : 0);
    }

    public function capabilities()
    {
        return array('vendor' => 'Matrix COSEC (REST)', 'pull' => true, 'push_endpoint' => false, 'sync_time' => false, 'push_user' => true, 'delete_user' => true,
            'pull_users' => true, 'clear_log' => false, 'card' => true, 'face' => true, 'palm' => false, 'realtime' => false, 'work_codes' => true, 'default_port' => 80,
            'verify_note' => 'Matrix versions its REST paths per product family — every path and query parameter here is a device option. Verify against your firmware version before the first live run.');
    }
}
