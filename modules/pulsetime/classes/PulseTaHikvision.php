<?php
/**
 * Hikvision access-control and face terminals over ISAPI (DS-K1T series, MinMoe, DS-K2600 controllers).
 *
 * Auth is HTTP digest against a device user (curl does the digest dance).
 * Pull:      POST /ISAPI/AccessControl/AcsEvent?format=json  with an AcsEventCond block, paged by
 *            searchResultPosition until responseStatusStrg stops saying "MORE".
 * Users:     POST /ISAPI/AccessControl/UserInfo/Record?format=json     (create/update)
 *            PUT  /ISAPI/AccessControl/UserInfo/Delete?format=json     (delete by employeeNo)
 *            POST /ISAPI/AccessControl/UserInfo/Search?format=json     (list)
 * Clock:     GET/PUT /ISAPI/System/time  (XML, not JSON — Hikvision is inconsistent here)
 * Identity:  GET /ISAPI/System/deviceInfo?format=json
 *
 * Inbound push: the same terminals can be told (Network ▸ HTTP Listening) to POST every event to a URL.
 * Point them at /iclock/event?SN=<serial> and PulseTaAdms::hikEvent() ingests it — same registered-serial
 * check, same rate limit, same size cap as the ZK push endpoint.
 *
 * Event codes: major 5 (access control) is the default; the minor codes that count as a legal punch differ
 * across firmware families, so `event_minor` is a device option (empty = accept every minor with an
 * employeeNo). Verify against your firmware version before trusting a new site.
 */
class PulseTaHikvision extends PulseTaDeviceBase
{
    protected $vendor = 'hikvision';
    protected $defaultPort = 80;

    protected function isoLocal($when) { $ts = is_numeric($when) ? (int) $when : strtotime($when); return date('Y-m-d\TH:i:s', $ts).$this->tzOffset($ts); }
    protected function tzOffset($ts) { $o = (int) date('Z', $ts); $s = $o < 0 ? '-' : '+'; $o = abs($o); return $s.str_pad((int) ($o / 3600), 2, '0', STR_PAD_LEFT).':'.str_pad((int) (($o % 3600) / 60), 2, '0', STR_PAD_LEFT); }

    protected function searchId() { return 'PULSE-'.Tools::substr(md5(uniqid('', true)), 0, 16); }

    public function testConnection()
    {
        $r = $this->json('GET', 'ISAPI/System/deviceInfo?format=json', null, array(), 'digest');
        $d = isset($r['DeviceInfo']) ? $r['DeviceInfo'] : $r;
        $time = '';
        try { $t = $this->http('GET', 'ISAPI/System/time', null, array('Accept: application/xml'), 'digest'); if (preg_match('#<localTime>([^<]+)</localTime>#', $t['body'], $m)) { $time = date('Y-m-d H:i:s', strtotime($m[1])); } } catch (Exception $e) { $time = ''; }
        $drift = $time ? (strtotime($time) - strtotime($this->deviceNow())) : null;
        return array('ok' => true, 'firmware' => isset($d['firmwareVersion']) ? $d['firmwareVersion'].' '.(isset($d['firmwareReleasedDate']) ? $d['firmwareReleasedDate'] : '') : '',
            'model' => isset($d['model']) ? $d['model'] : '', 'serial' => isset($d['serialNumber']) ? $d['serialNumber'] : $this->dev['serial'],
            'users' => 0, 'punches' => 0, 'device_time' => $time, 'drift_sec' => $drift,
            'message' => 'ISAPI reachable'.(isset($d['deviceName']) ? ' — '.$d['deviceName'] : '').($drift !== null && abs($drift) > 60 ? ' — clock is out by '.abs((int) round($drift / 60)).' min, run Sync time' : ''));
    }

    /** Hikvision's clock endpoint speaks XML even when everything else is asked for as JSON. */
    public function syncTime()
    {
        $before = '';
        try { $t = $this->http('GET', 'ISAPI/System/time', null, array('Accept: application/xml'), 'digest'); if (preg_match('#<localTime>([^<]+)</localTime>#', $t['body'], $m)) { $before = date('Y-m-d H:i:s', strtotime($m[1])); } } catch (Exception $e) { $before = ''; }
        $target = $this->deviceNow();
        $tz = (string) $this->opt('isapi_timezone', 'CST-1:00:00'); // Africa/Lagos is UTC+1; Hikvision writes it POSIX-style, hence the inverted sign
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Time version="2.0" xmlns="http://www.hikvision.com/ver20/XMLSchema">'
            .'<timeMode>manual</timeMode><localTime>'.htmlspecialchars($this->isoLocal($target), ENT_QUOTES, 'UTF-8').'</localTime>'
            .'<timeZone>'.htmlspecialchars($tz, ENT_QUOTES, 'UTF-8').'</timeZone></Time>';
        $r = $this->http('PUT', 'ISAPI/System/time', $xml, array('Content-Type: application/xml'), 'digest');
        if ((int) $r['http'] >= 300) { $this->fail('device refused the clock set (HTTP '.$r['http'].')', PulseTaDeviceException::REJECTED); }
        return array('ok' => true, 'before' => $before, 'after' => $target, 'timezone' => $this->dev['timezone']);
    }

    public function pullPunches($since)
    {
        $from = $since ? date('Y-m-d H:i:s', strtotime($since)) : date('Y-m-d H:i:s', strtotime('-2 day'));
        $to = date('Y-m-d H:i:s', time() + 300);
        $page = 0; $max = (int) $this->opt('page_size', 60); $guard = 0; $out = array(); $sid = $this->searchId();
        $minor = trim((string) $this->opt('event_minor', ''));
        $minors = $minor === '' ? array() : array_map('intval', array_filter(array_map('trim', explode(',', $minor))));
        while ($guard < (int) $this->opt('max_pages', 200)) {
            $guard++;
            $cond = array('searchID' => $sid, 'searchResultPosition' => $page, 'maxResults' => $max,
                'major' => (int) $this->opt('event_major', 5), 'minor' => 0,
                'startTime' => $this->isoLocal($from), 'endTime' => $this->isoLocal($to));
            if ($this->opt('event_minor_single')) { $cond['minor'] = (int) $this->opt('event_minor_single'); }
            $r = $this->json('POST', 'ISAPI/AccessControl/AcsEvent?format=json', json_encode(array('AcsEventCond' => $cond)), array(), 'digest');
            $ev = isset($r['AcsEvent']) ? $r['AcsEvent'] : array();
            $list = isset($ev['InfoList']) && is_array($ev['InfoList']) ? $ev['InfoList'] : array();
            foreach ($list as $e) {
                $ref = isset($e['employeeNoString']) ? (string) $e['employeeNoString'] : (isset($e['employeeNo']) ? (string) $e['employeeNo'] : '');
                if ($ref === '' || $ref === '0') { continue; }
                if ($minors && !in_array((int) (isset($e['minor']) ? $e['minor'] : 0), $minors, true)) { continue; }
                $when = isset($e['time']) ? date('Y-m-d H:i:s', strtotime($e['time'])) : '';
                if (!$when) { continue; }
                $state = isset($e['attendanceStatus']) ? $e['attendanceStatus'] : (isset($e['inOutType']) ? $e['inOutType'] : '');
                $out[] = $this->punch($ref, $when, $state, isset($e['currentVerifyMode']) ? $e['currentVerifyMode'] : (isset($e['cardNo']) && $e['cardNo'] ? 'card' : ''), '', $e);
            }
            $status = isset($ev['responseStatusStrg']) ? Tools::strtoupper($ev['responseStatusStrg']) : 'OK';
            $n = isset($ev['numOfMatches']) ? (int) $ev['numOfMatches'] : count($list);
            $page += $n > 0 ? $n : $max;
            if ($status !== 'MORE' || $n === 0) { break; }
        }
        return $this->after($out, $since);
    }

    public function pullUsers()
    {
        $out = array(); $page = 0; $max = (int) $this->opt('page_size', 60); $guard = 0; $sid = $this->searchId();
        while ($guard < 200) {
            $guard++;
            $r = $this->json('POST', 'ISAPI/AccessControl/UserInfo/Search?format=json',
                json_encode(array('UserInfoSearchCond' => array('searchID' => $sid, 'searchResultPosition' => $page, 'maxResults' => $max))), array(), 'digest');
            $s = isset($r['UserInfoSearch']) ? $r['UserInfoSearch'] : array();
            $list = isset($s['UserInfo']) && is_array($s['UserInfo']) ? $s['UserInfo'] : array();
            foreach ($list as $u) {
                $out[] = array('device_user_id' => isset($u['employeeNo']) ? (string) $u['employeeNo'] : '', 'name' => isset($u['name']) ? $u['name'] : '',
                    'card_no' => '', 'privilege' => (isset($u['userType']) && $u['userType'] === 'administrators') ? 1 : 0,
                    'has_finger' => isset($u['numOfFP']) ? (int) ((int) $u['numOfFP'] > 0) : 0, 'has_face' => isset($u['numOfFace']) ? (int) ((int) $u['numOfFace'] > 0) : 0,
                    'has_card' => isset($u['numOfCard']) ? (int) ((int) $u['numOfCard'] > 0) : 0, 'has_password' => isset($u['password']) && $u['password'] !== '' ? 1 : 0);
            }
            $n = isset($s['numOfMatches']) ? (int) $s['numOfMatches'] : count($list);
            $page += $n > 0 ? $n : $max;
            if ((isset($s['responseStatusStrg']) ? Tools::strtoupper($s['responseStatusStrg']) : 'OK') !== 'MORE' || $n === 0) { break; }
        }
        return $out;
    }

    public function pushUser(array $employee)
    {
        $ref = trim((string) (isset($employee['device_user_id']) ? $employee['device_user_id'] : ''));
        if ($ref === '') { $this->fail('no device user id supplied', PulseTaDeviceException::REJECTED); }
        $body = array('UserInfo' => array(
            'employeeNo' => $ref, 'name' => Tools::substr((string) (isset($employee['name']) ? $employee['name'] : ''), 0, 32),
            'userType' => (isset($employee['privilege']) && (int) $employee['privilege'] > 0) ? 'administrators' : 'normal',
            'Valid' => array('enable' => true, 'beginTime' => $this->isoLocal(isset($employee['valid_from']) ? $employee['valid_from'] : 'now'),
                'endTime' => $this->isoLocal(isset($employee['valid_to']) ? $employee['valid_to'] : strtotime('+10 year')), 'timeType' => 'local'),
            'doorRight' => (string) $this->opt('door_right', '1'),
            'RightPlan' => array(array('doorNo' => (int) $this->opt('door_no', 1), 'planTemplateNo' => (string) $this->opt('plan_template', '1'))),
        ));
        if (!empty($employee['password'])) { $body['UserInfo']['password'] = (string) $employee['password']; }
        $r = $this->json('POST', 'ISAPI/AccessControl/UserInfo/Record?format=json', json_encode($body), array(), 'digest');
        $st = isset($r['statusCode']) ? (int) $r['statusCode'] : 1;
        if ($st !== 1 && $st !== 0) { $this->fail(isset($r['statusString']) ? $r['statusString'] : ('ISAPI status '.$st), PulseTaDeviceException::REJECTED); }
        if (!empty($employee['card_no'])) { $this->pushCard($ref, $employee['card_no']); }
        return array('ok' => true, 'device_user_id' => $ref, 'note' => 'Face or fingerprint still has to be captured at the terminal or pushed as a template.');
    }

    /** A card is a separate ISAPI record from the user; failing to add it must not fail the whole enrolment. */
    protected function pushCard($ref, $card)
    {
        try {
            $this->json('POST', 'ISAPI/AccessControl/CardInfo/Record?format=json',
                json_encode(array('CardInfo' => array('employeeNo' => (string) $ref, 'cardNo' => (string) $card, 'cardType' => 'normalCard'))), array(), 'digest');
        } catch (Exception $e) { /* the identity is written; the card can be added by hand */ }
    }

    public function deleteUser($deviceUserId)
    {
        $r = $this->json('PUT', 'ISAPI/AccessControl/UserInfo/Delete?format=json',
            json_encode(array('UserInfoDelCond' => array('EmployeeNoList' => array(array('employeeNo' => (string) $deviceUserId))))), array(), 'digest');
        $st = isset($r['statusCode']) ? (int) $r['statusCode'] : 1;
        if ($st !== 1 && $st !== 0) { $this->fail(isset($r['statusString']) ? $r['statusString'] : ('ISAPI status '.$st), PulseTaDeviceException::REJECTED); }
        return array('ok' => true);
    }

    public function deviceInfo()
    {
        $r = $this->json('GET', 'ISAPI/System/deviceInfo?format=json', null, array(), 'digest');
        $d = isset($r['DeviceInfo']) ? $r['DeviceInfo'] : $r;
        return array('vendor' => $this->vendor, 'name' => isset($d['deviceName']) ? $d['deviceName'] : '', 'serial' => isset($d['serialNumber']) ? $d['serialNumber'] : '',
            'firmware' => isset($d['firmwareVersion']) ? $d['firmwareVersion'] : '', 'model' => isset($d['model']) ? $d['model'] : '', 'mac' => isset($d['macAddress']) ? $d['macAddress'] : '',
            'users' => 0, 'punches' => 0);
    }

    public function capabilities()
    {
        return array('vendor' => 'Hikvision ISAPI (DS-K1T / MinMoe / DS-K2600)', 'pull' => true, 'push_endpoint' => true, 'sync_time' => true, 'push_user' => true,
            'delete_user' => true, 'pull_users' => true, 'clear_log' => false, 'card' => true, 'face' => true, 'palm' => false, 'realtime' => true, 'work_codes' => false,
            'default_port' => 80, 'verify_note' => 'Event minor codes differ between firmware families — set event_minor for your model after checking a live pull.');
    }
}
