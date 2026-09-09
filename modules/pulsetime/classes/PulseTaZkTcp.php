<?php
/**
 * ZKTeco and its rebadges — eSSL, Granding, Timmy, Realtime and most of the "biometric attendance" boxes
 * sold in Nigeria — over the native protocol on TCP or UDP port 4370.
 *
 * This is the adapter that has to be right: it covers the overwhelming majority of installed readers.
 * The wire format itself lives in PulseTaZkProtocol (framing, ones-complement checksum, comm-key hash,
 * timestamp codec, record layouts) so it can be self-tested with no hardware; this class owns the session:
 * connect -> optional comm-key auth -> read -> exit, with a hard timeout on every socket operation.
 *
 * Reading supports both firmware generations:
 *   * the buffered path — CMD_DATA_WRRQ, then CMD_DATA_RDY chunks, then CMD_FREE_DATA;
 *   * the legacy path — CMD_ATTLOG_RRQ answered by CMD_PREPARE_DATA followed by CMD_DATA frames.
 * The buffered path is tried first and the legacy path is the automatic fallback.
 *
 * The device is NOT disabled during a pull by default. Disabling it is the textbook way to get a clean read,
 * and it is also how you stop 40 room attendants clocking in at 06:00 while the poller runs. Turn the
 * `disable_during_pull` option on only for a reader nobody uses during the poll window.
 *
 * Writes are deliberately limited to pushUser(), deleteUser(), syncTime() and an explicit clearLog().
 */
class PulseTaZkTcp extends PulseTaDeviceBase
{
    protected $vendor = 'zkteco';
    protected $defaultPort = 4370;

    protected $fp = null;
    protected $sessionId = 0;
    protected $replyNo = 65534;
    protected $lastReply = null;
    protected $sizes = array();

    /* ---------- session ---------- */

    protected function isTcp() { return $this->dev['protocol'] !== 'udp'; }

    /** Open the socket and negotiate a session; comm-key auth only happens if the device asks for it. */
    protected function open()
    {
        if ($this->fp) { return; }
        $this->fp = $this->socket($this->isTcp() ? 'tcp' : 'udp');
        $this->sessionId = 0; $this->replyNo = 65534;
        $r = $this->send(PulseTaZkProtocol::CMD_CONNECT);
        $this->sessionId = (int) $r['session'];
        if ((int) $r['command'] === PulseTaZkProtocol::CMD_ACK_UNAUTH) {
            $key = $this->cred('comm_key', $this->opt('comm_key', 0));
            if ((string) $key === '') { $this->closeQuiet(); $this->fail('the device is asking for its comm key and none is stored', PulseTaDeviceException::AUTH_FAILED); }
            $r = $this->send(PulseTaZkProtocol::CMD_AUTH, PulseTaZkProtocol::commKey((int) $key, $this->sessionId));
            if ((int) $r['command'] !== PulseTaZkProtocol::CMD_ACK_OK) { $this->closeQuiet(); $this->fail('comm key rejected by the device', PulseTaDeviceException::AUTH_FAILED); }
        } elseif ((int) $r['command'] !== PulseTaZkProtocol::CMD_ACK_OK) {
            $this->closeQuiet();
            $this->fail('device answered '.$this->cmdName((int) $r['command']).' to CMD_CONNECT', (int) $r['command'] === PulseTaZkProtocol::CMD_ACK_ERROR ? PulseTaDeviceException::BUSY : PulseTaDeviceException::BAD_RESPONSE);
        }
    }

    protected function close()
    {
        if (!$this->fp) { return; }
        try { $this->send(PulseTaZkProtocol::CMD_EXIT); } catch (Exception $e) { /* the socket is going away anyway */ }
        $this->closeQuiet();
    }

    protected function closeQuiet() { if ($this->fp) { @fclose($this->fp); } $this->fp = null; $this->sessionId = 0; }

    /** Send one command and read exactly one reply. */
    protected function send($command, $payload = '')
    {
        if (!$this->fp) { $this->fail('no session open', PulseTaDeviceException::UNREACHABLE); }
        $this->replyNo = PulseTaZkProtocol::nextReply($this->replyNo);
        $frame = PulseTaZkProtocol::frame($command, $this->sessionId, $this->replyNo, $payload);
        $wire = $this->isTcp() ? PulseTaZkProtocol::tcpWrap($frame) : $frame;
        if (@fwrite($this->fp, $wire) === false) { $this->fail('write failed on port '.$this->port(), PulseTaDeviceException::UNREACHABLE); }
        return $this->receive();
    }

    /** Read one reply frame. Over TCP the 8-byte envelope tells us exactly how many bytes to expect. */
    protected function receive()
    {
        if ($this->isTcp()) {
            $head = $this->readExact($this->fp, 8);
            $size = PulseTaZkProtocol::tcpSize($head);
            if ($size === null) { $this->fail('bad TCP envelope from the device — is something other than a ZK reader on port '.$this->port().'?', PulseTaDeviceException::BAD_RESPONSE); }
            if ($size < 8 || $size > 8 * 1024 * 1024) { $this->fail('device declared an implausible frame size of '.$size.' bytes', PulseTaDeviceException::BAD_RESPONSE); }
            $frame = $this->readExact($this->fp, $size);
        } else {
            $frame = fread($this->fp, 65536);
            $meta = stream_get_meta_data($this->fp);
            if (!empty($meta['timed_out'])) { $this->fail('UDP read timed out', PulseTaDeviceException::TIMED_OUT); }
            if ($frame === false || strlen($frame) < 8) { $this->fail('short UDP reply', PulseTaDeviceException::BAD_RESPONSE); }
        }
        $p = PulseTaZkProtocol::parse($frame);
        if (!$p) { $this->fail('unparseable reply', PulseTaDeviceException::BAD_RESPONSE); }
        if (!$p['valid'] && !$this->opt('ignore_checksum')) { $this->fail('reply failed the checksum — the link is corrupting frames', PulseTaDeviceException::BAD_RESPONSE); }
        $this->lastReply = $p;
        return $p;
    }

    protected function cmdName($c)
    {
        $m = array(2000 => 'CMD_ACK_OK', 2001 => 'CMD_ACK_ERROR', 2002 => 'CMD_ACK_DATA', 2003 => 'CMD_ACK_RETRY', 2004 => 'CMD_ACK_REPEAT', 2005 => 'CMD_ACK_UNAUTH',
            1500 => 'CMD_PREPARE_DATA', 1501 => 'CMD_DATA', 1502 => 'CMD_FREE_DATA');
        return isset($m[$c]) ? $m[$c] : ('command '.$c);
    }

    /* ---------- bulk reads ---------- */

    /**
     * Buffered read (CMD_DATA_WRRQ). The device either hands the block back inline as CMD_DATA, or
     * acknowledges with the total size and expects us to pull it in chunks with CMD_DATA_RDY.
     * @return string|false the raw block, or false when this firmware does not support the buffered path
     */
    protected function readBuffered($innerCommand, $fct = 0)
    {
        $r = $this->send(PulseTaZkProtocol::CMD_DATA_WRRQ, PulseTaZkProtocol::bufferRequest($innerCommand, $fct, 0));
        $cmd = (int) $r['command'];
        if ($cmd === PulseTaZkProtocol::CMD_DATA) { return $r['payload']; }
        if ($cmd === PulseTaZkProtocol::CMD_PREPARE_DATA) { return $this->drainPrepared($r); }
        if ($cmd !== PulseTaZkProtocol::CMD_ACK_OK || strlen($r['payload']) < 5) { return false; }
        $s = unpack('Vsize', substr($r['payload'], 1, 4));
        $total = (int) $s['size'];
        if ($total <= 0) { $this->freeData(); return ''; }
        $chunk = $this->isTcp() ? 0xFFC0 : 16384;
        $out = ''; $start = 0;
        while ($start < $total) {
            $want = min($chunk, $total - $start);
            $out .= $this->readChunk($start, $want);
            $start += $want;
        }
        $this->freeData();
        return $out;
    }

    /** One CMD_DATA_RDY chunk. */
    protected function readChunk($start, $size)
    {
        $r = $this->send(PulseTaZkProtocol::CMD_DATA_RDY, pack('V', (int) $start).pack('V', (int) $size));
        $cmd = (int) $r['command'];
        if ($cmd === PulseTaZkProtocol::CMD_DATA) { return $r['payload']; }
        if ($cmd === PulseTaZkProtocol::CMD_PREPARE_DATA) { return $this->drainPrepared($r); }
        $this->fail('device answered '.$this->cmdName($cmd).' to a chunk request at offset '.$start, PulseTaDeviceException::BAD_RESPONSE);
    }

    /**
     * CMD_PREPARE_DATA declares how many bytes follow; the data then arrives as one or more CMD_DATA frames
     * (over TCP usually a single large enveloped frame, over UDP a stream of datagrams) and is closed by
     * CMD_ACK_OK. Collect until the declared size is met, then swallow the acknowledgement.
     */
    protected function drainPrepared($prepared)
    {
        $declared = strlen($prepared['payload']) >= 4 ? (int) current(unpack('Vsize', substr($prepared['payload'], 0, 4))) : 0;
        $out = ''; $guard = 0;
        while (($declared === 0 || strlen($out) < $declared) && $guard < 4096) {
            $guard++;
            $r = $this->receive();
            $cmd = (int) $r['command'];
            if ($cmd === PulseTaZkProtocol::CMD_DATA) { $out .= $r['payload']; continue; }
            if ($cmd === PulseTaZkProtocol::CMD_ACK_OK) { return $out; }
            if ($cmd === PulseTaZkProtocol::CMD_ACK_ERROR) { $this->fail('device aborted the transfer after '.strlen($out).' bytes', PulseTaDeviceException::BAD_RESPONSE); }
        }
        if ($declared > 0 && strlen($out) >= $declared) { try { $this->receive(); } catch (Exception $e) { /* the trailing ACK is optional on some firmwares */ } }
        return $out;
    }

    protected function freeData() { try { $this->send(PulseTaZkProtocol::CMD_FREE_DATA); } catch (Exception $e) { /* best effort */ } }

    /** Legacy read: ask for the table directly and follow the prepare/data/free dance. */
    protected function readLegacy($command)
    {
        $r = $this->send($command);
        $cmd = (int) $r['command'];
        if ($cmd === PulseTaZkProtocol::CMD_DATA) { return $r['payload']; }
        if ($cmd === PulseTaZkProtocol::CMD_PREPARE_DATA) { $d = $this->drainPrepared($r); $this->freeData(); return $d; }
        if ($cmd === PulseTaZkProtocol::CMD_ACK_OK) { return ''; }
        $this->fail('device answered '.$this->cmdName($cmd).' to '.$command, PulseTaDeviceException::BAD_RESPONSE);
    }

    /** Record and user counters, when the firmware answers CMD_GET_FREE_SIZES. */
    protected function readSizes()
    {
        try {
            $r = $this->send(PulseTaZkProtocol::CMD_GET_FREE_SIZES);
            if ((int) $r['command'] === PulseTaZkProtocol::CMD_ACK_OK) { $this->sizes = PulseTaZkProtocol::parseSizes($r['payload']); }
        } catch (Exception $e) { $this->sizes = array(); }
        return $this->sizes;
    }

    /** A `~Name` option string, e.g. ~SerialNumber, ~DeviceName, ~ZKFPVersion, ~Platform. */
    protected function option($name)
    {
        try {
            $r = $this->send(PulseTaZkProtocol::CMD_OPTIONS_RRQ, $name."\0");
            $v = trim(str_replace("\0", '', $r['payload']));
            $eq = strpos($v, '=');
            return $eq === false ? $v : trim(substr($v, $eq + 1));
        } catch (Exception $e) { return ''; }
    }

    /* ---------- interface ---------- */

    public function testConnection()
    {
        $this->open();
        try {
            $sizes = $this->readSizes();
            $fw = ''; $serial = '';
            try { $r = $this->send(PulseTaZkProtocol::CMD_GET_VERSION); $fw = trim(str_replace("\0", '', $r['payload'])); } catch (Exception $e) { $fw = ''; }
            $serial = $this->option('~SerialNumber');
            $name = $this->option('~DeviceName');
            $time = '';
            try { $r = $this->send(PulseTaZkProtocol::CMD_GET_TIME); if (strlen($r['payload']) >= 4) { $t = unpack('Vt', substr($r['payload'], 0, 4)); $time = PulseTaZkProtocol::decodeTime((int) $t['t']); } } catch (Exception $e) { $time = ''; }
            $drift = $time ? (strtotime($time) - strtotime($this->deviceNow())) : null;
        } catch (Exception $e) { $this->close(); throw $e; }
        $this->close();
        return array('ok' => true, 'firmware' => $fw, 'model' => $name, 'serial' => $serial,
            'users' => isset($sizes['users']) ? (int) $sizes['users'] : 0, 'punches' => isset($sizes['records']) ? (int) $sizes['records'] : 0,
            'device_time' => $time, 'drift_sec' => $drift,
            'message' => 'ZK device reachable on '.$this->dev['host'].':'.$this->port().($fw ? ' — '.$fw : '').($drift !== null && abs($drift) > 60 ? ' — CLOCK IS '.($drift > 0 ? 'AHEAD' : 'BEHIND').' BY '.abs((int) round($drift / 60)).' MIN, run Sync time' : ''));
    }

    public function syncTime()
    {
        $this->open();
        $before = '';
        try {
            try { $r = $this->send(PulseTaZkProtocol::CMD_GET_TIME); if (strlen($r['payload']) >= 4) { $t = unpack('Vt', substr($r['payload'], 0, 4)); $before = PulseTaZkProtocol::decodeTime((int) $t['t']); } } catch (Exception $e) { $before = ''; }
            $target = $this->deviceNow();
            $r = $this->send(PulseTaZkProtocol::CMD_SET_TIME, pack('V', PulseTaZkProtocol::encodeTime($target)));
            if ((int) $r['command'] !== PulseTaZkProtocol::CMD_ACK_OK) { $this->fail('device refused the clock set', PulseTaDeviceException::REJECTED); }
            try { $this->send(PulseTaZkProtocol::CMD_REFRESHDATA); } catch (Exception $e) { /* not every firmware answers it */ }
        } catch (Exception $e) { $this->close(); throw $e; }
        $this->close();
        return array('ok' => true, 'before' => $before, 'after' => $target, 'timezone' => $this->dev['timezone']);
    }

    public function pullPunches($since)
    {
        $this->open();
        $disable = (int) $this->opt('disable_during_pull', 0);
        try {
            if ($disable) { try { $this->send(PulseTaZkProtocol::CMD_DISABLEDEVICE, "\x00\x00"); } catch (Exception $e) { /* keep going; a busy reader is not a reason to skip the pull */ } }
            $sizes = $this->readSizes();
            $records = isset($sizes['records']) ? (int) $sizes['records'] : 0;
            $data = $this->readBuffered(PulseTaZkProtocol::CMD_ATTLOG_RRQ, (int) $this->opt('attlog_fct', 0));
            if ($data === false) { $data = $this->readLegacy(PulseTaZkProtocol::CMD_ATTLOG_RRQ); }
        } catch (Exception $e) {
            if ($disable) { try { $this->send(PulseTaZkProtocol::CMD_ENABLEDEVICE); } catch (Exception $e2) { /* ignore */ } }
            $this->close();
            throw $e;
        }
        if ($disable) { try { $this->send(PulseTaZkProtocol::CMD_ENABLEDEVICE); } catch (Exception $e) { /* ignore */ } }
        $this->close();
        $rows = PulseTaZkProtocol::parseAttendance((string) $data, $records, (int) $this->opt('record_size', 0), (bool) $this->opt('swap_state_verify', 0));
        $out = array();
        foreach ($rows as $r) { $out[] = $this->punch($r['employee_ref'], $r['timestamp'], $r['state'], $r['verify'], $r['work_code'], $r['raw']); }
        return $this->after($out, $since);
    }

    public function pullUsers()
    {
        $this->open();
        try {
            $data = $this->readBuffered(PulseTaZkProtocol::CMD_USERTEMP_RRQ, PulseTaZkProtocol::FCT_USER);
            if ($data === false) { $data = $this->readLegacy(PulseTaZkProtocol::CMD_USERTEMP_RRQ); }
        } catch (Exception $e) { $this->close(); throw $e; }
        $this->close();
        $users = PulseTaZkProtocol::parseUsers((string) $data, (int) $this->opt('user_packet_size', 0));
        $out = array();
        foreach ($users as $u) {
            $out[] = array('device_user_id' => $u['device_user_id'], 'name' => $u['name'], 'card_no' => $u['card_no'], 'privilege' => (int) $u['privilege'],
                'has_finger' => 0, 'has_face' => 0, 'has_card' => $u['card_no'] !== '' ? 1 : 0, 'has_password' => $u['password'] !== '' ? 1 : 0, 'uid' => (int) $u['uid']);
        }
        return $out;
    }

    /**
     * Create or update a user. ZK identifies a user internally by a 16-bit uid and externally by the PIN the
     * staff member types; on every firmware in the field the PIN is numeric, so a non-numeric device user id
     * is rejected here rather than silently written as uid 0 (which would overwrite the administrator).
     */
    public function pushUser(array $employee)
    {
        $ref = isset($employee['device_user_id']) ? trim((string) $employee['device_user_id']) : '';
        if ($ref === '') { $this->fail('no device user id supplied', PulseTaDeviceException::REJECTED); }
        $uid = isset($employee['uid']) && (int) $employee['uid'] > 0 ? (int) $employee['uid'] : (ctype_digit($ref) ? (int) $ref : 0);
        if ($uid < 1 || $uid > 65535) { $this->fail('ZK devices need a numeric PIN between 1 and 65535 — "'.$ref.'" cannot be used', PulseTaDeviceException::REJECTED); }
        $name = Tools::substr(preg_replace('/[^\x20-\x7E]/', '', (string) (isset($employee['name']) ? $employee['name'] : '')), 0, 24);
        $card = isset($employee['card_no']) && ctype_digit((string) $employee['card_no']) ? (int) $employee['card_no'] : 0;
        $priv = isset($employee['privilege']) ? (int) $employee['privilege'] : 0;
        $pwd = isset($employee['password']) ? (string) $employee['password'] : '';
        $body = (int) $this->opt('user_packet_size', 72) === 28
            ? PulseTaZkProtocol::packUser28($uid, $ref, $name, $priv, $pwd, $card, 1)
            : PulseTaZkProtocol::packUser72($uid, $ref, $name, $priv, $pwd, $card, '1');
        $this->open();
        try {
            $r = $this->send(PulseTaZkProtocol::CMD_USER_WRQ, $body);
            if ((int) $r['command'] !== PulseTaZkProtocol::CMD_ACK_OK) { $this->fail('device refused the user write (uid '.$uid.')', PulseTaDeviceException::REJECTED); }
            try { $this->send(PulseTaZkProtocol::CMD_REFRESHDATA); } catch (Exception $e) { /* optional */ }
        } catch (Exception $e) { $this->close(); throw $e; }
        $this->close();
        return array('ok' => true, 'device_user_id' => $ref, 'uid' => $uid, 'note' => 'The fingerprint or face still has to be enrolled at the reader — this writes the identity, not the biometric template.');
    }

    public function deleteUser($deviceUserId)
    {
        $uid = ctype_digit((string) $deviceUserId) ? (int) $deviceUserId : 0;
        if ($uid < 1) { $this->fail('a numeric PIN is needed to delete a ZK user', PulseTaDeviceException::REJECTED); }
        $this->open();
        try {
            $r = $this->send(PulseTaZkProtocol::CMD_DELETE_USER, pack('v', $uid & 0xFFFF));
            if ((int) $r['command'] !== PulseTaZkProtocol::CMD_ACK_OK) { $this->fail('device refused the user delete (uid '.$uid.')', PulseTaDeviceException::REJECTED); }
            try { $this->send(PulseTaZkProtocol::CMD_REFRESHDATA); } catch (Exception $e) { /* optional */ }
        } catch (Exception $e) { $this->close(); throw $e; }
        $this->close();
        return array('ok' => true);
    }

    /** Destructive: only ever called explicitly, or by the poller when clear_after_pull is on and the pull committed. */
    public function clearLog()
    {
        $this->open();
        try {
            $r = $this->send(PulseTaZkProtocol::CMD_CLEAR_ATTLOG);
            if ((int) $r['command'] !== PulseTaZkProtocol::CMD_ACK_OK) { $this->fail('device refused to clear its log', PulseTaDeviceException::REJECTED); }
            try { $this->send(PulseTaZkProtocol::CMD_REFRESHDATA); } catch (Exception $e) { /* optional */ }
        } catch (Exception $e) { $this->close(); throw $e; }
        $this->close();
        return array('ok' => true);
    }

    public function deviceInfo()
    {
        $this->open();
        try {
            $sizes = $this->readSizes();
            $fw = '';
            try { $r = $this->send(PulseTaZkProtocol::CMD_GET_VERSION); $fw = trim(str_replace("\0", '', $r['payload'])); } catch (Exception $e) { $fw = ''; }
            $info = array('vendor' => $this->vendor, 'name' => $this->option('~DeviceName'), 'serial' => $this->option('~SerialNumber'), 'platform' => $this->option('~Platform'),
                'fp_version' => $this->option('~ZKFPVersion'), 'firmware' => $fw,
                'users' => isset($sizes['users']) ? (int) $sizes['users'] : 0, 'punches' => isset($sizes['records']) ? (int) $sizes['records'] : 0,
                'fingers' => isset($sizes['fingers']) ? (int) $sizes['fingers'] : 0, 'cards' => isset($sizes['cards']) ? (int) $sizes['cards'] : 0,
                'user_capacity' => isset($sizes['user_capacity']) ? (int) $sizes['user_capacity'] : 0, 'record_capacity' => isset($sizes['record_capacity']) ? (int) $sizes['record_capacity'] : 0);
        } catch (Exception $e) { $this->close(); throw $e; }
        $this->close();
        return $info;
    }

    public function capabilities()
    {
        return array('vendor' => 'ZKTeco / eSSL / Granding / Timmy / Realtime (native 4370)', 'pull' => true, 'push_endpoint' => false, 'sync_time' => true,
            'push_user' => true, 'delete_user' => true, 'pull_users' => true, 'clear_log' => true, 'card' => true, 'face' => true, 'palm' => true,
            'realtime' => false, 'work_codes' => true, 'numeric_pin_only' => true, 'protocols' => array('tcp', 'udp'), 'default_port' => 4370);
    }
}
