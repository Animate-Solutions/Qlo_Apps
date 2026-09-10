<?php
/**
 * ZKTeco ADMS ("push SDK") server side, plus the Hikvision HTTP event listener.
 *
 * This is the one surface in Pulse that unauthenticated hardware on the LAN dials into, so it is written
 * defensively and every rule below is deliberate:
 *
 *  * A serial we do not know is NEVER trusted. It is recorded as a `pending` device and shown on the Devices
 *    screen for an administrator to claim. Until it is claimed its punches are counted and logged but not
 *    stored, so a rogue box plugged into the staff switch cannot manufacture attendance.
 *  * A claimed device authenticates by its registered serial plus, when PULSE_TA_PUSH_REQUIRE_KEY is on, a
 *    shared key compared with hash_equals(). An optional per-device IP allowlist narrows it further.
 *  * Every request is rate-limited per serial per minute and the body is capped; oversize or excessive
 *    traffic is logged and refused rather than parsed.
 *  * Nothing device-supplied is ever concatenated into SQL (pSQL/casts throughout) and the stored sample is
 *    stripped of control characters; the admin templates escape it again on the way out.
 *  * Replies are the terse plain-text strings the firmware expects. A device that gets anything else retries
 *    forever and floods the log, so even a refusal answers 200 with a body the firmware understands.
 */
class PulseTaAdms
{
    const T_DEVICE = 'pulse_ta_device';
    const T_CMD = 'pulse_ta_device_cmd';
    const T_LOG = 'pulse_ta_device_log';

    /* ---------- configuration ---------- */

    public static function cfg($k, $default = null) { $v = Configuration::get('PULSE_TA_'.$k); return ($v === false || $v === null || $v === '') ? $default : $v; }
    public static function enabled() { return (int) self::cfg('PUSH_ENABLED', 1) === 1; }
    public static function maxBytes() { return max(4096, (int) self::cfg('PUSH_MAX_BYTES', 1048576)); }

    /* ---------- logging ---------- */

    /** One row per inbound request. This is the audit trail for a surface with no login, so it is never optional. */
    public static function log($serial, $path, $table, $result, $message = '', $bytes = 0, $rowsIn = 0, $rowsKept = 0, $idDevice = null, $sample = '')
    {
        return Db::getInstance()->insert(self::T_LOG, PulseTaService::nulls(array(
            'id_pulse_ta_device' => $idDevice ? (int) $idDevice : null, 'serial' => pSQL(Tools::substr((string) $serial, 0, 64)), 'direction' => 'in',
            'path' => pSQL(Tools::substr((string) $path, 0, 64)), 'table_name' => pSQL(Tools::substr((string) $table, 0, 32)),
            'ip' => pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 45)), 'bytes' => (int) $bytes, 'rows_in' => (int) $rowsIn, 'rows_kept' => (int) $rowsKept,
            'result' => pSQL($result), 'message' => pSQL(Tools::substr((string) $message, 0, 255)),
            // Device-supplied text: reduced to printable ASCII before it is stored, so a malformed or binary
            // body can neither break the utf8 column nor smuggle control characters into an admin screen.
            // The templates escape it again on the way out.
            'sample' => pSQL(substr(preg_replace('/[^\x20-\x7E]/', ' ', (string) $sample), 0, 512), true),
            'date_add' => date('Y-m-d H:i:s'),
        )));
    }

    /* ---------- admission control ---------- */

    /** A serial is device-supplied: keep it to the character set real firmware uses and a sane length. */
    public static function cleanSerial($sn) { return Tools::substr(preg_replace('/[^A-Za-z0-9_.:\-]/', '', (string) $sn), 0, 64); }

    /** Requests from one serial in the last minute — the flood guard for a device stuck in a retry loop. */
    protected static function recentRequests($serial)
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::T_LOG.'` WHERE `serial`="'.pSQL($serial).'" AND `date_add`>DATE_SUB(NOW(), INTERVAL 60 SECOND)');
    }

    /**
     * Requests from one ADDRESS in the last minute. The per-serial bucket alone is not a rate limit: a caller
     * that varies the SN on every request gets a fresh bucket each time and is never throttled, so the address
     * is counted as well. Both counters read the traffic log, which is why every terminal path logs.
     */
    protected static function recentFromIp()
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::T_LOG.'` WHERE `ip`="'.pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 45)).'" AND `date_add`>DATE_SUB(NOW(), INTERVAL 60 SECOND)');
    }

    /** How many unclaimed serials are already parked. A serial-varying flood must not fill the device table. */
    protected static function pendingCount()
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::T_DEVICE.'` WHERE `status`="pending"');
    }

    public static function bySerial($serial) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T_DEVICE.'` WHERE `serial`="'.pSQL($serial).'"'); }

    /**
     * Decide whether this request may proceed.
     * @return array [ok, device|null, code, reason] — code is one of ok, pending_device, rejected, rate_limited, too_large
     */
    public static function admit($serial, $path, $bytes = 0)
    {
        if (!self::enabled()) { return array('ok' => false, 'device' => null, 'code' => 'rejected', 'reason' => 'Push endpoint disabled in T&A Settings'); }
        $serial = self::cleanSerial($serial);
        if ($bytes > self::maxBytes()) { self::log($serial, $path, '', 'too_large', 'body '.$bytes.' bytes', $bytes); return array('ok' => false, 'device' => null, 'code' => 'too_large', 'reason' => 'Body too large'); }
        // The address limit is checked before the SN is even required, so a caller that omits or rotates the
        // serial is throttled by the only identifier it cannot forge cheaply.
        $limit = (int) self::cfg('PUSH_RATE_PER_MIN', 120);
        $ipLimit = (int) self::cfg('PUSH_RATE_PER_IP_MIN', max(1, $limit) * 4);
        if ($ipLimit > 0 && self::recentFromIp() >= $ipLimit) { self::log($serial, $path, '', 'rate_limited', $ipLimit.'/min exceeded for this address', $bytes); return array('ok' => false, 'device' => null, 'code' => 'rate_limited', 'reason' => 'Rate limited'); }
        if ($serial === '') { self::log('', $path, '', 'rejected', 'no SN in the request', $bytes); return array('ok' => false, 'device' => null, 'code' => 'rejected', 'reason' => 'No SN in the request'); }
        if ($limit > 0 && self::recentRequests($serial) >= $limit) { self::log($serial, $path, '', 'rate_limited', $limit.'/min exceeded for this serial', $bytes); return array('ok' => false, 'device' => null, 'code' => 'rate_limited', 'reason' => 'Rate limited'); }

        $dev = self::bySerial($serial);
        if (!$dev) {
            if (!(int) self::cfg('PUSH_AUTO_REGISTER', 1)) { self::log($serial, $path, '', 'rejected', 'unknown serial, auto-register off', $bytes); return array('ok' => false, 'device' => null, 'code' => 'rejected', 'reason' => 'Unknown serial'); }
            // A serial-varying caller would otherwise mint an unbounded number of device rows.
            $maxPending = (int) self::cfg('PUSH_MAX_PENDING', 10);
            if ($maxPending > 0 && self::pendingCount() >= $maxPending) {
                self::log($serial, $path, '', 'rejected', 'refused: '.$maxPending.' unclaimed serials already waiting — claim or delete them in T&A ▸ Devices', $bytes);
                return array('ok' => false, 'device' => null, 'code' => 'rejected', 'reason' => 'Too many unclaimed devices are already waiting');
            }
            $dev = self::registerPending($serial);
            self::log($serial, $path, '', 'pending_device', 'new serial registered as pending — claim it in T&A ▸ Devices', $bytes, 0, 0, $dev ? (int) $dev['id_pulse_ta_device'] : null);
            return array('ok' => false, 'device' => $dev, 'code' => 'pending_device', 'reason' => 'Device is pending — an administrator must claim it');
        }
        if ($dev['status'] === 'blocked') { self::log($serial, $path, '', 'rejected', 'device blocked', $bytes, 0, 0, (int) $dev['id_pulse_ta_device']); return array('ok' => false, 'device' => $dev, 'code' => 'rejected', 'reason' => 'Device blocked'); }
        if ($dev['status'] !== 'active') { self::log($serial, $path, '', 'pending_device', 'device not yet claimed', $bytes, 0, 0, (int) $dev['id_pulse_ta_device']); return array('ok' => false, 'device' => $dev, 'code' => 'pending_device', 'reason' => 'Device is pending — an administrator must claim it'); }

        $opts = json_decode((string) $dev['options_json'], true); $opts = is_array($opts) ? $opts : array();
        if (!empty($opts['allow_ips'])) {
            $ip = (string) Tools::getRemoteAddr(); $ok = false;
            foreach (array_map('trim', explode(',', (string) $opts['allow_ips'])) as $allow) { if ($allow !== '' && ($allow === $ip || strpos($ip, rtrim($allow, '*')) === 0)) { $ok = true; } }
            if (!$ok) { self::log($serial, $path, '', 'rejected', 'ip '.$ip.' not in the device allowlist', $bytes, 0, 0, (int) $dev['id_pulse_ta_device']); return array('ok' => false, 'device' => $dev, 'code' => 'rejected', 'reason' => 'IP not allowed'); }
        }
        if ((int) self::cfg('PUSH_REQUIRE_KEY', 0)) {
            $param = (string) self::cfg('PUSH_KEY_PARAM', 'pushkey');
            $supplied = (string) Tools::getValue($param, isset($_SERVER['HTTP_X_PULSE_PUSH_KEY']) ? $_SERVER['HTTP_X_PULSE_PUSH_KEY'] : '');
            $expected = (string) self::deviceKey($dev);
            if ($expected === '' || !hash_equals($expected, $supplied)) {
                self::log($serial, $path, '', 'rejected', 'shared key missing or wrong', $bytes, 0, 0, (int) $dev['id_pulse_ta_device']);
                return array('ok' => false, 'device' => $dev, 'code' => 'rejected', 'reason' => 'Shared key rejected');
            }
        }
        return array('ok' => true, 'device' => $dev, 'code' => 'ok', 'reason' => '');
    }

    /** The device's own push key, falling back to the property-wide one. */
    public static function deviceKey($dev)
    {
        if (!empty($dev['credentials_enc'])) {
            $j = json_decode((string) PulseCoreService::decrypt($dev['credentials_enc']), true);
            if (is_array($j) && !empty($j['push_key'])) { return (string) $j['push_key']; }
        }
        return (string) PulseCoreService::setting('pulsetime', 'push_key');
    }

    /** A serial nobody has seen becomes a pending row — visible, claimable, and inert until claimed. */
    public static function registerPending($serial)
    {
        // `name` is UNIQUE, so a clash must be resolved before the insert or the row is silently lost.
        $base = Tools::substr('Unclaimed '.$serial, 0, 56);
        $name = $base;
        for ($i = 2; $i < 40 && Db::getInstance()->getValue('SELECT id_pulse_ta_device FROM `'._DB_PREFIX_.self::T_DEVICE.'` WHERE `name`="'.pSQL($name).'"'); $i++) { $name = $base.' ('.$i.')'; }
        Db::getInstance()->insert(self::T_DEVICE, array(
            'name' => pSQL($name), 'brand' => 'zkteco', 'adapter' => 'PulseTaZkPush', 'location' => 'Unknown', 'mode' => 'push', 'protocol' => 'http',
            'host' => pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 128)), 'port' => 80, 'endpoint' => '/iclock', 'serial' => pSQL($serial),
            'timezone' => pSQL((string) self::cfg('TZ', 'Africa/Lagos')), 'direction_mode' => 'both', 'poll_interval_min' => 0, 'timeout_sec' => 8,
            'health' => 'online', 'status' => 'pending', 'last_seen_at' => date('Y-m-d H:i:s'),
            'note' => pSQL('Registered itself from '.Tools::substr((string) Tools::getRemoteAddr(), 0, 45).' — claim it to start accepting its punches.'),
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::audit('pulsetime', 'device_pending', array('serial' => $serial, 'ip' => Tools::getRemoteAddr()), self::T_DEVICE, $id);
        PulseCoreService::event('actionPulseTaDeviceHealth', array('id_device' => $id, 'serial' => $serial, 'state' => 'pending'));
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T_DEVICE.'` WHERE id_pulse_ta_device='.$id);
    }

    /** A call-in is proof of life: refresh the device and clear any "stopped reporting" exception it raised. */
    public static function touch($dev, $punchAt = null)
    {
        $u = array('last_seen_at' => date('Y-m-d H:i:s'), 'health' => 'online', 'error_count' => 0, 'last_error' => '', 'date_upd' => date('Y-m-d H:i:s'));
        if ($punchAt) { $u['last_punch_at'] = pSQL($punchAt); }
        Db::getInstance()->update(self::T_DEVICE, $u, 'id_pulse_ta_device='.(int) $dev['id_pulse_ta_device']);
        if (isset($dev['health']) && $dev['health'] !== 'online') {
            PulseTaExceptionQueue::closeByKey('device_offline', 'dev'.(int) $dev['id_pulse_ta_device'], 'Device "'.$dev['name'].'" called in again');
        }
    }

    /* ---------- the four firmware endpoints ---------- */

    /**
     * GET /iclock/cdata?SN=..&options=all&pushver=..  — the handshake. The reply is a plain-text option block
     * the firmware parses line by line; the first line must be exactly "GET OPTION FROM: <SN>".
     * Both the short names in the ZK documentation (Stamp, OpStamp) and the long names current firmware
     * actually reads (ATTLOGStamp, OPERLOGStamp) are emitted — devices ignore what they do not understand.
     */
    public static function handshake($dev)
    {
        $sn = $dev ? $dev['serial'] : self::cleanSerial(Tools::getValue('SN'));
        $stamp = $dev && $dev['last_cursor'] !== '' ? $dev['last_cursor'] : '0';
        $lines = array(
            'GET OPTION FROM: '.$sn,
            'Stamp='.$stamp,
            'OpStamp='.$stamp,
            'ATTLOGStamp='.$stamp,
            'OPERLOGStamp='.$stamp,
            'ATTPHOTOStamp=0',
            'ErrorDelay='.(int) self::cfg('PUSH_ERROR_DELAY', 30),
            'Delay='.(int) self::cfg('PUSH_DELAY', 10),
            'TransTimes='.(string) self::cfg('PUSH_TRANS_TIMES', '00:00;14:00'),
            'TransInterval='.(int) self::cfg('PUSH_TRANS_INTERVAL', 1),
            'TransFlag='.(string) self::cfg('PUSH_TRANS_FLAG', 'TransData AttLog OpLog AttPhoto EnrollUser ChgUser EnrollFP ChgFP UserPic'),
            'TimeZone='.(int) self::cfg('PUSH_TIMEZONE', 1),
            'Realtime='.((int) self::cfg('PUSH_REALTIME', 1) ? 1 : 0),
            'Encrypt=0',
            'ServerVer=2.4.1',
            'PushProtVer=2.4.1',
        );
        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * POST /iclock/cdata?SN=..&table=ATTLOG — tab-separated punch rows:
     *     PIN <TAB> DateTime <TAB> Status <TAB> Verify <TAB> WorkCode <TAB> Reserved1 <TAB> Reserved2
     * A few firmwares emit Verify before Status; the device option `attlog_swap` flips them.
     * The reply must be "OK: <count>" or the device re-sends the whole block forever.
     */
    public static function attlog($dev, $body)
    {
        $opts = json_decode((string) $dev['options_json'], true); $opts = is_array($opts) ? $opts : array();
        $swap = !empty($opts['attlog_swap']);
        $lines = preg_split('/\r\n|\r|\n/', (string) $body);
        $rows = array(); $seen = 0; $latest = null;
        foreach ($lines as $line) {
            if (trim($line) === '') { continue; }
            $seen++;
            $f = explode("\t", rtrim($line, "\r"));
            if (count($f) < 2) { $f = preg_split('/\s{2,}|,/', trim($line)); }
            $ref = isset($f[0]) ? trim($f[0]) : '';
            $when = isset($f[1]) ? trim($f[1]) : '';
            if ($ref === '' || $when === '') { continue; }
            // Only a literal calendar stamp is accepted. strtotime() also understands "now", "tomorrow" and
            // "-5 days", so a malformed or hostile row could otherwise back-date or re-date a punch at will.
            if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $when)) { continue; }
            $ts = strtotime($when);
            if (!$ts) { continue; }
            $a = isset($f[2]) ? trim($f[2]) : '';
            $b = isset($f[3]) ? trim($f[3]) : '';
            $state = $swap ? $b : $a;
            $verify = $swap ? $a : $b;
            $rows[] = array(
                'device_serial' => $dev['serial'], 'employee_ref' => Tools::substr($ref, 0, 32),
                'punched_at' => self::toShopTime($dev, $ts), 'device_time' => date('Y-m-d H:i:s', $ts),
                'direction' => self::direction($dev, $state), 'verify_mode' => self::verify($verify),
                'work_code' => isset($f[4]) ? Tools::substr(trim($f[4]), 0, 16) : '', 'raw' => Tools::substr($line, 0, 1000), 'source' => 'device',
            );
            if ($latest === null || $ts > $latest) { $latest = $ts; }
        }
        $kept = $rows ? PulseTaPunch::ingestMany($dev, $rows) : 0;
        if ($latest) { Db::getInstance()->update(self::T_DEVICE, array('last_cursor' => pSQL((string) $latest)), 'id_pulse_ta_device='.(int) $dev['id_pulse_ta_device']); }
        self::touch($dev, $latest ? date('Y-m-d H:i:s', $latest) : null);
        self::log($dev['serial'], 'cdata', 'ATTLOG', 'ok', $kept.' of '.count($rows).' stored, '.($seen - count($rows)).' unparseable', strlen((string) $body), $seen, $kept, (int) $dev['id_pulse_ta_device'], isset($lines[0]) ? $lines[0] : '');
        // Acknowledge every row the device sent, not only the ones that parsed: a firmware that is told it
        // delivered fewer rows than it sent re-sends the whole block forever, and one malformed line would
        // then loop the device against the link until somebody notices. The rows dropped are on the log above.
        return 'OK: '.$seen;
    }

    /**
     * POST /iclock/cdata?SN=..&table=OPERLOG — operation log: door opens, admin logins, and the USER / FP
     * lines a device emits when somebody is enrolled at the terminal. Those USER lines are the cheapest way
     * to discover a device user id, so they are harvested into the enrolment discovery list.
     */
    public static function operlog($dev, $body)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $body);
        $users = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || Tools::strtoupper(Tools::substr($line, 0, 5)) !== 'USER ') { continue; }
            $kv = array();
            foreach (preg_split('/\t/', Tools::substr($line, 5)) as $pair) {
                $p = strpos($pair, '=');
                if ($p > 0) { $kv[trim(Tools::substr($pair, 0, $p))] = trim(Tools::substr($pair, $p + 1)); }
            }
            if (empty($kv['PIN'])) { continue; }
            if (PulseTaEnrolment::discover((int) $dev['id_pulse_ta_device'], $kv['PIN'], isset($kv['Name']) ? $kv['Name'] : '', isset($kv['Card']) ? $kv['Card'] : '', isset($kv['Pri']) ? (int) $kv['Pri'] : 0)) { $users++; }
        }
        self::touch($dev);
        self::log($dev['serial'], 'cdata', 'OPERLOG', 'ok', $users.' user line(s) harvested', strlen((string) $body), count($lines), $users, (int) $dev['id_pulse_ta_device'], isset($lines[0]) ? $lines[0] : '');
        return 'OK: '.count(array_filter(array_map('trim', $lines)));
    }

    /** Any other table (ATTPHOTO, BIODATA, USERINFO…) — logged and acknowledged so the device stops retrying. */
    public static function otherTable($dev, $table, $body)
    {
        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $body)));
        self::touch($dev);
        self::log($dev['serial'], 'cdata', Tools::substr((string) $table, 0, 32), 'ok', 'acknowledged, not stored', strlen((string) $body), count($lines), 0, (int) $dev['id_pulse_ta_device'], reset($lines) ? reset($lines) : '');
        return 'OK: '.count($lines);
    }

    /**
     * GET /iclock/getrequest?SN=.. — the device asking for work. Commands go out one per line as
     * "C:<id>:<command>", e.g. C:12:DATA UPDATE USERINFO PIN=1042<TAB>Name=Chidinma<TAB>Pri=0.
     * "OK" alone means nothing to do.
     */
    public static function getrequest($dev)
    {
        $batch = (int) self::cfg('PUSH_CMD_BATCH', 5);
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.self::T_CMD.'` WHERE id_pulse_ta_device='.(int) $dev['id_pulse_ta_device']
            .' AND status="queued" AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY id_pulse_ta_device_cmd LIMIT '.max(1, $batch));
        self::touch($dev);
        if (!$rows) { self::log($dev['serial'], 'getrequest', '', 'ok', 'no commands queued', 0, 0, 0, (int) $dev['id_pulse_ta_device']); return 'OK'; }
        $out = array(); $ids = array();
        foreach ($rows as $r) { $out[] = 'C:'.(int) $r['id_pulse_ta_device_cmd'].':'.str_replace(array("\r", "\n"), array('', ''), $r['cmd']); $ids[] = (int) $r['id_pulse_ta_device_cmd']; }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T_CMD.'` SET status="sent", sent_at=NOW(), attempts=attempts+1, date_upd=NOW() WHERE id_pulse_ta_device_cmd IN ('.implode(',', array_map('intval', $ids)).')');
        self::log($dev['serial'], 'getrequest', '', 'ok', count($ids).' command(s) handed over', 0, 0, count($ids), (int) $dev['id_pulse_ta_device']);
        return implode("\r\n", $out)."\r\n";
    }

    /**
     * POST /iclock/devicecmd?SN=.. — the result of a command, as "ID=12&Return=0&CMD=DATA" lines.
     * Return=0 is success on every firmware seen in the field; anything else is recorded verbatim.
     */
    public static function devicecmd($dev, $body)
    {
        $done = 0; $failed = 0;
        foreach (preg_split('/\r\n|\r|\n/', (string) $body) as $line) {
            $line = trim($line);
            if ($line === '') { continue; }
            $kv = array();
            parse_str(str_replace('&amp;', '&', $line), $kv);
            $id = isset($kv['ID']) ? (int) $kv['ID'] : 0;
            if (!$id) { continue; }
            $ret = isset($kv['Return']) ? (string) $kv['Return'] : '';
            $ok = ($ret === '0' || $ret === '');
            if ($ok) { $done++; } else { $failed++; }
            Db::getInstance()->update(self::T_CMD, array(
                'status' => $ok ? 'done' : 'failed', 'replied_at' => date('Y-m-d H:i:s'),
                'return_code' => pSQL(Tools::substr($ret, 0, 16)), 'reply' => pSQL(Tools::substr($line, 0, 255), true), 'date_upd' => date('Y-m-d H:i:s'),
            ), 'id_pulse_ta_device_cmd='.$id.' AND id_pulse_ta_device='.(int) $dev['id_pulse_ta_device']);
            if ($ok) { PulseTaEnrolment::markCommandDone($id, (int) $dev['id_pulse_ta_device']); }
        }
        self::touch($dev);
        self::log($dev['serial'], 'devicecmd', '', 'ok', $done.' done, '.$failed.' failed', strlen((string) $body), $done + $failed, $done, (int) $dev['id_pulse_ta_device']);
        return 'OK';
    }

    /**
     * GET /iclock/ping — some firmwares check reachability before the handshake.
     * It logs like every other path: the traffic log is what both rate-limit counters read, so a path that
     * answers without logging is a path that can be hammered for free.
     */
    public static function ping($dev)
    {
        if ($dev) { self::touch($dev); }
        self::log($dev ? $dev['serial'] : '', 'ping', '', 'ok', 'reachability check', 0, 0, 0, $dev ? (int) $dev['id_pulse_ta_device'] : null);
        return 'OK';
    }

    /** Record a handshake. Same reason as ping(): every terminal path must leave a row for the rate limiter. */
    public static function logHandshake($dev, $serial, $bytes = 0)
    {
        return self::log($dev ? $dev['serial'] : $serial, 'cdata', '', 'ok', 'handshake / option block served', $bytes, 0, 0, $dev ? (int) $dev['id_pulse_ta_device'] : null);
    }

    /* ---------- outbound command queue ---------- */

    /** Queue one ADMS command for a push device. Returns the command id used in the C:<id>: prefix. */
    public static function queue($idDevice, $cmd, $kind = 'other', $ttlHours = 72)
    {
        Db::getInstance()->insert(self::T_CMD, array(
            'id_pulse_ta_device' => (int) $idDevice, 'cmd' => pSQL($cmd, true), 'kind' => pSQL(Tools::substr($kind, 0, 32)), 'status' => 'queued',
            'expires_at' => date('Y-m-d H:i:s', time() + max(1, (int) $ttlHours) * 3600), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ));
        return (int) Db::getInstance()->Insert_ID();
    }

    /** DATA UPDATE USERINFO — the enrolment push for a push-mode device. */
    public static function queueUser($idDevice, array $employee)
    {
        $f = array('PIN='.(string) $employee['device_user_id'],
            'Name='.Tools::substr(str_replace(array("\t", "\r", "\n"), ' ', (string) (isset($employee['name']) ? $employee['name'] : '')), 0, 24),
            'Pri='.(int) (isset($employee['privilege']) ? $employee['privilege'] : 0),
            'Passwd='.(string) (isset($employee['password']) ? $employee['password'] : ''),
            'Card='.(string) (isset($employee['card_no']) ? $employee['card_no'] : ''),
            'Grp='.(string) (isset($employee['group']) ? $employee['group'] : '1'),
            'TZ=0000000000000000');
        return self::queue($idDevice, 'DATA UPDATE USERINFO '.implode("\t", $f), 'push_user');
    }

    public static function queueDeleteUser($idDevice, $deviceUserId) { return self::queue($idDevice, 'DATA DELETE USERINFO PIN='.(string) $deviceUserId, 'delete_user'); }
    public static function queueClearLog($idDevice) { return self::queue($idDevice, 'CLEAR LOG', 'clear_log'); }
    public static function queueCheckData($idDevice) { return self::queue($idDevice, 'CHECK', 'check'); }
    public static function queueReboot($idDevice) { return self::queue($idDevice, 'REBOOT', 'reboot'); }

    /** SET OPTIONS DateTime=<zk counter> — the only way to set the clock on a push-only device. */
    public static function queueSetTime($idDevice, $tz = 'Africa/Lagos')
    {
        try { $d = new DateTime('now', new DateTimeZone(Configuration::get('PS_TIMEZONE') ?: 'Africa/Lagos')); $d->setTimezone(new DateTimeZone($tz)); $when = $d->format('Y-m-d H:i:s'); }
        catch (Exception $e) { $when = date('Y-m-d H:i:s'); }
        return self::queue($idDevice, 'SET OPTIONS DateTime='.PulseTaZkProtocol::encodeTime($when), 'sync_time', 6);
    }

    /* ---------- Hikvision HTTP event listener ---------- */

    /**
     * POST /iclock/event?SN=<serial> — Hikvision terminals configured under Network ▸ HTTP Listening send
     * either a bare JSON body or a multipart body whose first part is the JSON event. The same admission
     * rules apply: registered serial, optional shared key, rate limit, size cap.
     */
    public static function hikEvent($dev, $body, $contentType = '')
    {
        $json = trim((string) $body);
        if (stripos((string) $contentType, 'multipart/') === 0 || Tools::substr($json, 0, 2) === '--') {
            $s = strpos($json, '{'); $e = strrpos($json, '}');
            $json = ($s !== false && $e !== false && $e > $s) ? Tools::substr($json, $s, $e - $s + 1) : '';
        }
        $j = json_decode($json, true);
        if (!is_array($j)) { self::log($dev['serial'], 'event', 'HIK', 'error', 'unparseable event body', strlen((string) $body), 0, 0, (int) $dev['id_pulse_ta_device'], Tools::substr((string) $body, 0, 200)); return 'OK'; }
        $e = isset($j['AccessControllerEvent']) ? $j['AccessControllerEvent'] : $j;
        $ref = isset($e['employeeNoString']) ? (string) $e['employeeNoString'] : (isset($e['employeeNo']) ? (string) $e['employeeNo'] : '');
        $when = isset($j['dateTime']) ? $j['dateTime'] : (isset($e['dateTime']) ? $e['dateTime'] : '');
        if ($ref === '' || $ref === '0' || !$when || !strtotime($when)) {
            self::touch($dev);
            self::log($dev['serial'], 'event', 'HIK', 'ok', 'event carried no employee number — ignored', strlen((string) $body), 1, 0, (int) $dev['id_pulse_ta_device'], Tools::substr($json, 0, 200));
            return 'OK';
        }
        $ts = strtotime($when);
        $row = array('device_serial' => $dev['serial'], 'employee_ref' => Tools::substr($ref, 0, 32), 'punched_at' => self::toShopTime($dev, $ts),
            'device_time' => date('Y-m-d H:i:s', $ts), 'direction' => self::direction($dev, isset($e['attendanceStatus']) ? $e['attendanceStatus'] : ''),
            'verify_mode' => self::verify(isset($e['currentVerifyMode']) ? $e['currentVerifyMode'] : (isset($e['cardNo']) && $e['cardNo'] ? 'card' : '')),
            'work_code' => '', 'raw' => Tools::substr($json, 0, 1000), 'source' => 'device');
        $kept = PulseTaPunch::ingestMany($dev, array($row));
        self::touch($dev, date('Y-m-d H:i:s', $ts));
        self::log($dev['serial'], 'event', 'HIK', 'ok', $kept ? 'punch stored' : 'duplicate ignored', strlen((string) $body), 1, $kept, (int) $dev['id_pulse_ta_device'], Tools::substr($json, 0, 200));
        return 'OK';
    }

    /* ---------- shared normalisation (mirrors PulseTaDeviceBase for the push path) ---------- */

    public static function toShopTime($dev, $deviceLocal)
    {
        $shopTz = Configuration::get('PS_TIMEZONE') ? Configuration::get('PS_TIMEZONE') : (string) self::cfg('TZ', 'Africa/Lagos');
        $devTz = $dev && $dev['timezone'] ? $dev['timezone'] : $shopTz;
        $local = is_numeric($deviceLocal) ? date('Y-m-d H:i:s', (int) $deviceLocal) : (string) $deviceLocal;
        if ($devTz === $shopTz) { return date('Y-m-d H:i:s', strtotime($local)); }
        try { $d = new DateTime($local, new DateTimeZone($devTz)); $d->setTimezone(new DateTimeZone($shopTz)); return $d->format('Y-m-d H:i:s'); }
        catch (Exception $e) { return date('Y-m-d H:i:s', strtotime($local)); }
    }

    public static function direction($dev, $code)
    {
        $mode = $dev && isset($dev['direction_mode']) ? $dev['direction_mode'] : 'both';
        if ($mode === 'in' || $mode === 'out') { return $mode; }
        if ($mode === 'auto') { return 'unknown'; }
        $c = Tools::strtolower(trim((string) $code));
        $numeric = array('0' => 'in', '1' => 'out', '2' => 'break_out', '3' => 'break_in', '4' => 'ot_in', '5' => 'ot_out');
        if ($c !== '' && ctype_digit($c)) { return isset($numeric[$c]) ? $numeric[$c] : 'unknown'; }
        foreach (array('checkin' => 'in', 'checkout' => 'out', 'breakout' => 'break_out', 'breakin' => 'break_in', 'overtimein' => 'ot_in', 'overtimeout' => 'ot_out') as $n => $v) {
            if ($c !== '' && strpos(str_replace(array(' ', '-', '_'), '', $c), $n) !== false) { return $v; }
        }
        return 'unknown';
    }

    public static function verify($code)
    {
        $c = Tools::strtolower(trim((string) $code));
        $numeric = array('0' => 'password', '1' => 'finger', '2' => 'card', '3' => 'finger', '4' => 'card', '15' => 'face', '20' => 'palm');
        if ($c !== '' && ctype_digit($c)) { return isset($numeric[$c]) ? $numeric[$c] : 'other'; }
        foreach (array('finger' => 'finger', 'fp' => 'finger', 'face' => 'face', 'card' => 'card', 'password' => 'password', 'pin' => 'password', 'palm' => 'palm', 'iris' => 'iris') as $n => $v) {
            if ($c !== '' && strpos($c, $n) !== false) { return $v; }
        }
        return 'other';
    }

    /** Housekeeping for the cron: drop old traffic rows and expire commands nobody collected. */
    public static function purge()
    {
        $days = (int) self::cfg('LOG_RETENTION', 30);
        $rows = 0;
        if ($days > 0) {
            Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.self::T_LOG.'` WHERE date_add<DATE_SUB(NOW(), INTERVAL '.$days.' DAY)');
            $rows = (int) Db::getInstance()->Affected_Rows();
        }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T_CMD.'` SET status="expired", date_upd=NOW() WHERE status IN ("queued","sent") AND expires_at IS NOT NULL AND expires_at<NOW()');
        return array('log_rows' => $rows, 'commands_expired' => (int) Db::getInstance()->Affected_Rows());
    }
}
