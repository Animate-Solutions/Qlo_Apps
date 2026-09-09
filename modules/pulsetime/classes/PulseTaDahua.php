<?php
/**
 * Dahua access controllers and face terminals (ASI series, VTO) over the HTTP CGI API with digest auth.
 *
 * Records come out of the recordFinder state machine, which is four calls, not one:
 *   1. factory.create  -> a finder object id
 *   2. startFind       -> how many records match the condition
 *   3. doFind (paged)  -> the records, as records[N].Field=value lines
 *   4. destroy         -> release the finder
 * A single-shot `action=find` exists on some builds and is used as the fallback when factory.create is
 * refused. The response body is Dahua's key=value text format, not JSON.
 *
 * Users:  /cgi-bin/AccessUser.cgi?action=insertMulti | updateMulti | removeMulti
 * Clock:  /cgi-bin/global.cgi?action=getCurrentTime | setCurrentTime
 * Ident:  /cgi-bin/magicBox.cgi?action=getSystemInfo | getSerialNo | getSoftwareVersion
 *
 * Verify against your firmware version: Dahua renamed the record table from AccessControlCardRec to
 * AccessControlCardRec / RecordFinder variants across generations, so the table name is a device option.
 */
class PulseTaDahua extends PulseTaDeviceBase
{
    protected $vendor = 'dahua';
    protected $defaultPort = 80;

    /** Dahua answers `a.b.c=value` lines; turn that into a nested array. */
    protected function kv($body)
    {
        $out = array();
        foreach (preg_split('/\r?\n/', (string) $body) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) { continue; }
            list($k, $v) = explode('=', $line, 2);
            $out[trim($k)] = trim($v);
        }
        return $out;
    }

    protected function cgi($path, array $query)
    {
        $r = $this->http('GET', $path.'?'.http_build_query($query), null, array('Accept: text/plain'), 'digest');
        if ((int) $r['http'] >= 400) { $this->fail('Dahua answered HTTP '.$r['http'].' for '.$path, PulseTaDeviceException::REJECTED); }
        if (stripos($r['body'], 'Error') === 0) { $this->fail(trim(Tools::substr($r['body'], 0, 120)), PulseTaDeviceException::REJECTED); }
        return $r['body'];
    }

    public function testConnection()
    {
        $info = $this->kv($this->cgi('cgi-bin/magicBox.cgi', array('action' => 'getSystemInfo')));
        $serial = trim(str_replace('sn=', '', $this->cgi('cgi-bin/magicBox.cgi', array('action' => 'getSerialNo'))));
        $sw = trim(str_replace('version=', '', $this->cgi('cgi-bin/magicBox.cgi', array('action' => 'getSoftwareVersion'))));
        $time = '';
        try { $time = trim(str_replace('result=', '', $this->cgi('cgi-bin/global.cgi', array('action' => 'getCurrentTime')))); $time = $time ? date('Y-m-d H:i:s', strtotime($time)) : ''; } catch (Exception $e) { $time = ''; }
        $drift = $time ? (strtotime($time) - strtotime($this->deviceNow())) : null;
        return array('ok' => true, 'firmware' => $sw, 'model' => isset($info['deviceType']) ? $info['deviceType'] : '', 'serial' => $serial ? $serial : $this->dev['serial'],
            'users' => 0, 'punches' => 0, 'device_time' => $time, 'drift_sec' => $drift,
            'message' => 'Dahua CGI reachable'.(isset($info['deviceType']) ? ' — '.$info['deviceType'] : '').($drift !== null && abs($drift) > 60 ? ' — clock is out by '.abs((int) round($drift / 60)).' min' : ''));
    }

    public function syncTime()
    {
        $before = '';
        try { $before = trim(str_replace('result=', '', $this->cgi('cgi-bin/global.cgi', array('action' => 'getCurrentTime')))); } catch (Exception $e) { $before = ''; }
        $target = $this->deviceNow();
        $this->cgi('cgi-bin/global.cgi', array('action' => 'setCurrentTime', 'time' => $target));
        return array('ok' => true, 'before' => $before ? date('Y-m-d H:i:s', strtotime($before)) : '', 'after' => $target, 'timezone' => $this->dev['timezone']);
    }

    public function pullPunches($since)
    {
        $table = (string) $this->opt('record_table', 'AccessControlCardRec');
        $from = $since ? strtotime($since) : strtotime('-2 day');
        $to = time() + 300;
        $finder = null;
        try {
            $created = $this->kv($this->cgi('cgi-bin/recordFinder.cgi', array('action' => 'factory.create', 'name' => $table)));
            $finder = isset($created['result']) ? $created['result'] : null;
        } catch (Exception $e) { $finder = null; }
        if ($finder === null) { return $this->after($this->findOneShot($table, $from, $to), $since); }
        $out = array();
        try {
            $found = $this->kv($this->cgi('cgi-bin/recordFinder.cgi', array('action' => 'startFind', 'object' => $finder,
                'condition.StartTime' => $from, 'condition.EndTime' => $to)));
            $total = isset($found['found']) ? (int) $found['found'] : 0;
            $per = (int) $this->opt('page_size', 100); $got = 0; $guard = 0;
            while ($got < $total && $guard < (int) $this->opt('max_pages', 200)) {
                $guard++;
                $body = $this->cgi('cgi-bin/recordFinder.cgi', array('action' => 'doFind', 'object' => $finder, 'count' => $per));
                $rows = $this->records($body);
                if (!$rows) { break; }
                foreach ($rows as $r) { $p = $this->row($r); if ($p) { $out[] = $p; } }
                $got += count($rows);
            }
        } catch (Exception $e) {
            try { $this->cgi('cgi-bin/recordFinder.cgi', array('action' => 'destroy', 'object' => $finder)); } catch (Exception $e2) { /* ignore */ }
            throw $e;
        }
        try { $this->cgi('cgi-bin/recordFinder.cgi', array('action' => 'destroy', 'object' => $finder)); } catch (Exception $e) { /* the finder times out on its own */ }
        return $this->after($out, $since);
    }

    /** Some builds answer a plain find in one call; used when factory.create is not available. */
    protected function findOneShot($table, $from, $to)
    {
        $body = $this->cgi('cgi-bin/recordFinder.cgi', array('action' => 'find', 'name' => $table, 'condition.StartTime' => $from, 'condition.EndTime' => $to,
            'count' => (int) $this->opt('page_size', 100)));
        $out = array();
        foreach ($this->records($body) as $r) { $p = $this->row($r); if ($p) { $out[] = $p; } }
        return $out;
    }

    /** Split `records[3].CardName=Chidi` lines into one array per record index. */
    protected function records($body)
    {
        $rows = array();
        foreach (preg_split('/\r?\n/', (string) $body) as $line) {
            if (!preg_match('/^records\[(\d+)\]\.(.+?)=(.*)$/', trim($line), $m)) { continue; }
            $rows[(int) $m[1]][$m[2]] = $m[3];
        }
        ksort($rows);
        return array_values($rows);
    }

    protected function row(array $r)
    {
        $ref = isset($r['UserID']) ? (string) $r['UserID'] : (isset($r['CardNo']) ? (string) $r['CardNo'] : '');
        if ($ref === '' || $ref === '0') { return null; }
        $ts = isset($r['CreateTime']) ? (int) $r['CreateTime'] : (isset($r['Time']) ? (int) $r['Time'] : 0);
        if (!$ts) { return null; }
        if (isset($r['Status']) && (string) $r['Status'] === '0' && !$this->opt('include_denied')) { return null; }
        return $this->punch($ref, date('Y-m-d H:i:s', $ts), isset($r['Direction']) ? $r['Direction'] : (isset($r['AttendanceState']) ? $r['AttendanceState'] : ''),
            isset($r['Method']) ? $this->dahuaMethod($r['Method']) : '', '', $r);
    }

    /** Dahua Method codes: 1 card, 2 fingerprint, 3 face(+card), 4 password, 15 face. */
    protected function dahuaMethod($m)
    {
        $map = array('1' => 'card', '2' => 'finger', '3' => 'face', '4' => 'password', '5' => 'card', '15' => 'face', '16' => 'face');
        return isset($map[(string) $m]) ? $map[(string) $m] : '';
    }

    public function pullUsers()
    {
        $body = $this->cgi('cgi-bin/AccessUser.cgi', array('action' => 'list', 'UserIDList[0]' => ''));
        $j = json_decode($body, true);
        $out = array();
        if (is_array($j) && isset($j['UserList'])) {
            foreach ($j['UserList'] as $u) {
                $out[] = array('device_user_id' => isset($u['UserID']) ? (string) $u['UserID'] : '', 'name' => isset($u['UserName']) ? $u['UserName'] : '',
                    'card_no' => isset($u['CardNo']) ? (string) $u['CardNo'] : '', 'privilege' => isset($u['UserType']) ? (int) $u['UserType'] : 0,
                    'has_finger' => !empty($u['FingerPrint']) ? 1 : 0, 'has_face' => !empty($u['FaceInfo']) ? 1 : 0, 'has_card' => !empty($u['CardNo']) ? 1 : 0, 'has_password' => !empty($u['Password']) ? 1 : 0);
            }
            return $out;
        }
        foreach ($this->records($body) as $r) {
            $out[] = array('device_user_id' => isset($r['UserID']) ? (string) $r['UserID'] : '', 'name' => isset($r['CardName']) ? $r['CardName'] : '',
                'card_no' => isset($r['CardNo']) ? (string) $r['CardNo'] : '', 'privilege' => 0, 'has_finger' => 0, 'has_face' => 0, 'has_card' => !empty($r['CardNo']) ? 1 : 0, 'has_password' => 0);
        }
        return $out;
    }

    public function pushUser(array $employee)
    {
        $ref = trim((string) (isset($employee['device_user_id']) ? $employee['device_user_id'] : ''));
        if ($ref === '') { $this->fail('no device user id supplied', PulseTaDeviceException::REJECTED); }
        $q = array('action' => 'insertMulti', 'UserList[0].UserID' => $ref,
            'UserList[0].UserName' => Tools::substr((string) (isset($employee['name']) ? $employee['name'] : ''), 0, 32),
            'UserList[0].UserType' => (isset($employee['privilege']) && (int) $employee['privilege'] > 0) ? 1 : 0,
            'UserList[0].UserStatus' => 1, 'UserList[0].Doors[0]' => (int) $this->opt('door_no', 0),
            'UserList[0].ValidDateStart' => date('Y-m-d H:i:s', strtotime(isset($employee['valid_from']) ? $employee['valid_from'] : 'now')),
            'UserList[0].ValidDateEnd' => date('Y-m-d H:i:s', strtotime(isset($employee['valid_to']) ? $employee['valid_to'] : '+10 year')));
        if (!empty($employee['card_no'])) { $q['UserList[0].CardNo'] = (string) $employee['card_no']; }
        if (!empty($employee['password'])) { $q['UserList[0].Password'] = (string) $employee['password']; }
        try { $this->cgi('cgi-bin/AccessUser.cgi', $q); }
        catch (PulseTaDeviceException $e) { $q['action'] = 'updateMulti'; $this->cgi('cgi-bin/AccessUser.cgi', $q); }
        return array('ok' => true, 'device_user_id' => $ref, 'note' => 'Face and fingerprint are captured at the terminal; this writes the identity, validity and door rights.');
    }

    public function deleteUser($deviceUserId)
    {
        $this->cgi('cgi-bin/AccessUser.cgi', array('action' => 'removeMulti', 'UserIDList[0]' => (string) $deviceUserId));
        return array('ok' => true);
    }

    public function deviceInfo()
    {
        $info = $this->kv($this->cgi('cgi-bin/magicBox.cgi', array('action' => 'getSystemInfo')));
        return array('vendor' => $this->vendor, 'name' => isset($info['deviceType']) ? $info['deviceType'] : 'Dahua',
            'serial' => trim(str_replace('sn=', '', $this->cgi('cgi-bin/magicBox.cgi', array('action' => 'getSerialNo')))),
            'firmware' => trim(str_replace('version=', '', $this->cgi('cgi-bin/magicBox.cgi', array('action' => 'getSoftwareVersion')))), 'users' => 0, 'punches' => 0);
    }

    public function capabilities()
    {
        return array('vendor' => 'Dahua HTTP CGI (ASI / VTO)', 'pull' => true, 'push_endpoint' => false, 'sync_time' => true, 'push_user' => true, 'delete_user' => true,
            'pull_users' => true, 'clear_log' => false, 'card' => true, 'face' => true, 'palm' => false, 'realtime' => false, 'work_codes' => false, 'default_port' => 80,
            'verify_note' => 'The record table name and the Method/Status code meanings differ across Dahua generations — record_table and include_denied are device options.');
    }
}
