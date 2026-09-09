<?php
/**
 * ZKTeco wire protocol, on its own so it can be reasoned about — and self-tested — without a device.
 *
 * Frame (both TCP and UDP):
 *     [command:2][checksum:2][session_id:2][reply_no:2]  little-endian, then the payload.
 * Over TCP the whole frame is additionally wrapped in an 8-byte envelope:
 *     "\x50\x50\x82\x7d" + [size:4 little-endian]     size = 8 + strlen(payload)
 * The checksum is the 16-bit ones-complement sum of the frame (header + payload) with the checksum
 * field zeroed: sum little-endian 16-bit words, folding any carry above 0xFFFF back in, add a trailing
 * odd byte, fold again, then complement.
 *
 * Timestamps are a single unsigned 32-bit second counter based on the year 2000, on a calendar where
 * every month is 31 days long:
 *     t = (((y % 100) * 12 * 31) + ((m - 1) * 31) + (d - 1)) * 86400 + h * 3600 + i * 60 + s
 * Decoding walks the same ladder back. Getting this wrong shifts every punch by hours or days, which is
 * why selfTest() exists and why the Devices screen has a button that runs it.
 *
 * Every slice in here is a BYTE slice: plain substr()/strlen(), never Tools::substr()/Tools::strlen(),
 * which are mb_* wrappers and count UTF-8 characters. A ZK frame is binary — a timestamp, a card number or
 * a uid routinely contains a byte pair that is a valid multi-byte sequence — and a character-wise slice
 * silently returns the wrong bytes, which decodes to the wrong employee at the wrong time.
 */
class PulseTaZkProtocol
{
    const CMD_CONNECT = 1000;
    const CMD_EXIT = 1001;
    const CMD_ENABLEDEVICE = 1002;
    const CMD_DISABLEDEVICE = 1003;
    const CMD_RESTART = 1004;
    const CMD_REFRESHDATA = 1013;
    const CMD_AUTH = 1102;
    const CMD_PREPARE_DATA = 1500;
    const CMD_DATA = 1501;
    const CMD_FREE_DATA = 1502;
    const CMD_DATA_WRRQ = 1503;
    const CMD_DATA_RDY = 1504;
    const CMD_ACK_OK = 2000;
    const CMD_ACK_ERROR = 2001;
    const CMD_ACK_DATA = 2002;
    const CMD_ACK_RETRY = 2003;
    const CMD_ACK_REPEAT = 2004;
    const CMD_ACK_UNAUTH = 2005;
    const CMD_ATTLOG_RRQ = 13;
    const CMD_CLEAR_ATTLOG = 15;
    const CMD_USERTEMP_RRQ = 9;
    const CMD_USER_WRQ = 8;
    const CMD_DELETE_USER = 18;
    const CMD_GET_TIME = 201;
    const CMD_SET_TIME = 202;
    const CMD_OPTIONS_RRQ = 11;
    const CMD_GET_FREE_SIZES = 50;
    const CMD_GET_VERSION = 1100;

    const FCT_ATTLOG = 1;
    const FCT_FINGERTMP = 2;
    const FCT_USER = 5;

    const TCP_MAGIC = "\x50\x50\x82\x7d";
    const USHRT_MAX = 0xFFFF;

    /* ---------- framing ---------- */

    /** 16-bit ones-complement sum over $buf, which must already carry a zeroed checksum field. */
    public static function checksum($buf)
    {
        $len = strlen($buf); $sum = 0; $i = 0;
        while ($len > 1) {
            $sum += (ord($buf[$i]) | (ord($buf[$i + 1]) << 8));
            $i += 2; $len -= 2;
            if ($sum > self::USHRT_MAX) { $sum -= self::USHRT_MAX; }
        }
        if ($len) { $sum += ord($buf[$i]); }
        while ($sum > self::USHRT_MAX) { $sum -= self::USHRT_MAX; }
        $sum = ~$sum;
        while ($sum < 0) { $sum += self::USHRT_MAX; }
        return $sum & self::USHRT_MAX;
    }

    /** Build one command frame (no TCP envelope). */
    public static function frame($command, $sessionId, $replyNo, $payload = '')
    {
        $payload = (string) $payload;
        $zeroed = pack('vvvv', $command & self::USHRT_MAX, 0, $sessionId & self::USHRT_MAX, $replyNo & self::USHRT_MAX).$payload;
        $ck = self::checksum($zeroed);
        return pack('vvvv', $command & self::USHRT_MAX, $ck, $sessionId & self::USHRT_MAX, $replyNo & self::USHRT_MAX).$payload;
    }

    /** Verify the checksum of a received frame. */
    public static function verify($frame)
    {
        if (strlen($frame) < 8) { return false; }
        $h = unpack('vcommand/vchecksum/vsession/vreply', substr($frame, 0, 8));
        $zeroed = pack('vvvv', $h['command'], 0, $h['session'], $h['reply']).substr($frame, 8);
        return self::checksum($zeroed) === (int) $h['checksum'];
    }

    /** Split a frame into its header fields plus the payload. */
    public static function parse($frame)
    {
        if (strlen($frame) < 8) { return null; }
        $h = unpack('vcommand/vchecksum/vsession/vreply', substr($frame, 0, 8));
        $h['payload'] = (string) substr($frame, 8);
        $h['valid'] = self::verify($frame);
        return $h;
    }

    /** TCP envelope around a frame. */
    public static function tcpWrap($frame) { return self::TCP_MAGIC.pack('V', strlen($frame)).$frame; }

    /** Read the 8-byte TCP envelope; returns the declared payload size or null when the magic is wrong. */
    public static function tcpSize($header8)
    {
        if (strlen($header8) < 8 || substr($header8, 0, 4) !== self::TCP_MAGIC) { return null; }
        $s = unpack('Vsize', substr($header8, 4, 4));
        return (int) $s['size'];
    }

    /** The next reply number, wrapping the way the firmware does. */
    public static function nextReply($replyNo)
    {
        $replyNo++;
        if ($replyNo >= self::USHRT_MAX) { $replyNo -= self::USHRT_MAX; }
        return $replyNo;
    }

    /* ---------- comm-key authentication ---------- */

    /**
     * Session key for CMD_AUTH: the numeric comm key is bit-reversed into 32 bits, the session id is added,
     * the four bytes are XORed with 'Z','K','S','O' and the two 16-bit halves are swapped. Bytes 0, 1 and 3
     * are then XORed with a tick byte and byte 2 CARRIES that tick byte — the device cannot know the tick,
     * so it reads it out of byte 2 and undoes the XOR on the other three. Putting anything else in byte 2
     * makes the device recover three wrong bytes and answer CMD_ACK_UNAUTH.
     */
    public static function commKey($commKey, $sessionId, $ticks = 50)
    {
        $key = (int) $commKey; $k = 0;
        for ($i = 0; $i < 32; $i++) { $k = (($k << 1) | (($key & (1 << $i)) ? 1 : 0)) & 0xFFFFFFFF; }
        $k = ($k + (int) $sessionId) & 0xFFFFFFFF;
        $b = array($k & 0xFF, ($k >> 8) & 0xFF, ($k >> 16) & 0xFF, ($k >> 24) & 0xFF);
        $b = array($b[0] ^ 0x5A, $b[1] ^ 0x4B, $b[2] ^ 0x53, $b[3] ^ 0x4F); // 'Z' 'K' 'S' 'O'
        $b = array($b[2], $b[3], $b[0], $b[1]);                              // swap the 16-bit halves
        $t = $ticks & 0xFF;
        return chr($b[0] ^ $t).chr($b[1] ^ $t).chr($t).chr($b[3] ^ $t);
    }

    /* ---------- timestamps ---------- */

    /** 'Y-m-d H:i:s' (or a unix timestamp) -> the ZK 32-bit second counter. */
    public static function encodeTime($when)
    {
        $ts = is_numeric($when) ? (int) $when : strtotime((string) $when);
        $y = (int) date('Y', $ts); $mo = (int) date('n', $ts); $d = (int) date('j', $ts);
        $h = (int) date('G', $ts); $mi = (int) date('i', $ts); $s = (int) date('s', $ts);
        return ((($y % 100) * 12 * 31) + (($mo - 1) * 31) + ($d - 1)) * 86400 + $h * 3600 + $mi * 60 + $s;
    }

    /** The ZK 32-bit second counter -> 'Y-m-d H:i:s' as device-local wall clock (no timezone applied here). */
    public static function decodeTime($t)
    {
        $t = (int) $t;
        $s = $t % 60; $t = (int) ($t / 60);
        $mi = $t % 60; $t = (int) ($t / 60);
        $h = $t % 24; $t = (int) ($t / 24);
        $d = ($t % 31) + 1; $t = (int) ($t / 31);
        $mo = ($t % 12) + 1; $t = (int) ($t / 12);
        $y = $t + 2000;
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $mi, $s);
    }

    /** Some firmwares (and the ADMS SET OPTIONS path) want the six raw components instead of the counter. */
    public static function encodeTime6($when)
    {
        $ts = is_numeric($when) ? (int) $when : strtotime((string) $when);
        return chr((int) date('Y', $ts) % 100).chr((int) date('n', $ts)).chr((int) date('j', $ts)).chr((int) date('G', $ts)).chr((int) date('i', $ts)).chr((int) date('s', $ts));
    }

    /* ---------- record decoding ---------- */

    /** Strip the leading 4-byte size prefix a buffered read puts in front of the record block. */
    public static function stripSizePrefix($data)
    {
        if (strlen($data) < 4) { return $data; }
        $n = unpack('Vsize', substr($data, 0, 4));
        if ((int) $n['size'] === strlen($data) - 4) { return (string) substr($data, 4); }
        return $data;
    }

    /** Pick the attendance record width: the device's own record count first, then divisibility, then 40. */
    public static function recordSize($len, $records = 0, $force = 0)
    {
        if ($force && in_array((int) $force, array(8, 16, 40), true)) { return (int) $force; }
        if ($records > 0 && $len > 0 && $len % $records === 0 && in_array((int) ($len / $records), array(8, 16, 40), true)) { return (int) ($len / $records); }
        foreach (array(40, 16, 8) as $w) { if ($len > 0 && $len % $w === 0) { return $w; } }
        return 40;
    }

    /**
     * Attendance block -> array of [uid, employee_ref, state, verify, timestamp 'Y-m-d H:i:s', work_code, raw].
     * 40 bytes: <H uid><24s user_id><B verify><4s time><B state><8s reserved>   (current firmwares)
     * 16 bytes: <I user_id><4s time><B verify><B state><2s reserved><I work_code>
     *  8 bytes: <H uid><B verify><4s time><B state>                             (very old firmwares)
     * Firmwares differ over which of the two single bytes is "verify" and which is "punch state"; the device
     * option `swap_state_verify` flips them without touching this decoder's callers.
     */
    public static function parseAttendance($data, $records = 0, $force = 0, $swap = false)
    {
        $data = self::stripSizePrefix($data);
        $len = strlen($data);
        if ($len < 8) { return array(); }
        $w = self::recordSize($len, $records, $force);
        $out = array();
        for ($o = 0; $o + $w <= $len; $o += $w) {
            $chunk = substr($data, $o, $w);
            if ($w === 40) {
                $uid = unpack('v', substr($chunk, 0, 2)); $uid = (int) $uid[1];
                $ref = rtrim(substr($chunk, 2, 24), "\0");
                $a = ord($chunk[26]);
                $t = unpack('V', substr($chunk, 27, 4)); $t = (int) $t[1];
                $b = ord($chunk[31]);
                $work = '';
            } elseif ($w === 16) {
                $u = unpack('V', substr($chunk, 0, 4)); $uid = (int) $u[1]; $ref = (string) $uid;
                $t = unpack('V', substr($chunk, 4, 4)); $t = (int) $t[1];
                $a = ord($chunk[8]); $b = ord($chunk[9]);
                $wc = unpack('V', substr($chunk, 12, 4)); $work = (int) $wc[1] ? (string) (int) $wc[1] : '';
            } else {
                $uid = unpack('v', substr($chunk, 0, 2)); $uid = (int) $uid[1]; $ref = (string) $uid;
                $a = ord($chunk[2]);
                $t = unpack('V', substr($chunk, 3, 4)); $t = (int) $t[1];
                $b = ord($chunk[7]); $work = '';
            }
            if ($ref === '' || $t <= 0) { continue; }
            $verify = $swap ? $b : $a; $state = $swap ? $a : $b;
            $out[] = array('uid' => $uid, 'employee_ref' => $ref, 'verify' => $verify, 'state' => $state, 'timestamp' => self::decodeTime($t),
                'work_code' => (string) $work, 'raw' => 'zk'.$w.':'.bin2hex($chunk));
        }
        return $out;
    }

    /**
     * User block -> array of [uid, device_user_id, name, privilege, password, card_no, group].
     * 72 bytes: <H uid><B privilege><8s password><24s name><4s card><c 1><7s group><x><24s user_id>
     * 28 bytes: <H uid><B privilege><5s password><8s name><5s card><B group><h timezone><I user_id>
     */
    public static function parseUsers($data, $force = 0)
    {
        $data = self::stripSizePrefix($data);
        $len = strlen($data);
        if ($len < 28) { return array(); }
        $w = ($force === 28 || $force === 72) ? (int) $force : ($len % 72 === 0 ? 72 : ($len % 28 === 0 ? 28 : 72));
        $out = array();
        for ($o = 0; $o + $w <= $len; $o += $w) {
            $chunk = substr($data, $o, $w);
            $uid = unpack('v', substr($chunk, 0, 2)); $uid = (int) $uid[1];
            $priv = ord($chunk[2]);
            if ($w === 72) {
                $pwd = rtrim(substr($chunk, 3, 8), "\0");
                $name = rtrim(substr($chunk, 11, 24), "\0");
                $card = unpack('V', substr($chunk, 35, 4)); $card = (int) $card[1];
                $group = rtrim(substr($chunk, 40, 7), "\0");
                $ref = rtrim(substr($chunk, 48, 24), "\0");
            } else {
                $pwd = rtrim(substr($chunk, 3, 5), "\0");
                $name = rtrim(substr($chunk, 8, 8), "\0");
                $card = unpack('V', substr($chunk, 16, 4).chr(0)); $card = (int) $card[1] & 0xFFFFFFFF;
                $group = (string) ord($chunk[21]);
                $r = unpack('V', substr($chunk, 24, 4)); $ref = (string) (int) $r[1];
            }
            if ($ref === '') { $ref = (string) $uid; }
            $out[] = array('uid' => $uid, 'device_user_id' => $ref, 'name' => trim($name), 'privilege' => $priv, 'password' => $pwd, 'card_no' => $card ? (string) $card : '', 'group' => $group);
        }
        return $out;
    }

    /** Encode one user for CMD_USER_WRQ in the 72-byte layout current firmwares expect. */
    public static function packUser72($uid, $ref, $name, $privilege = 0, $password = '', $card = 0, $group = '1')
    {
        return pack('v', (int) $uid & self::USHRT_MAX)
            .chr((int) $privilege & 0xFF)
            .str_pad(substr((string) $password, 0, 8), 8, "\0")
            .str_pad(substr((string) $name, 0, 24), 24, "\0")
            .pack('V', (int) $card & 0xFFFFFFFF)
            .chr(1)
            .str_pad(substr((string) $group, 0, 7), 7, "\0")
            .chr(0)
            .str_pad(substr((string) $ref, 0, 24), 24, "\0");
    }

    /** Encode one user in the older 28-byte layout (set device option user_packet_size=28). */
    public static function packUser28($uid, $ref, $name, $privilege = 0, $password = '', $card = 0, $group = 0)
    {
        return pack('v', (int) $uid & self::USHRT_MAX)
            .chr((int) $privilege & 0xFF)
            .str_pad(substr((string) $password, 0, 5), 5, "\0")
            .str_pad(substr((string) $name, 0, 8), 8, "\0")
            .str_pad(substr(pack('V', (int) $card & 0xFFFFFFFF), 0, 5), 5, "\0")
            .chr((int) $group & 0xFF)
            .pack('v', 0)
            .pack('V', (int) $ref & 0xFFFFFFFF);
    }

    /** The 11-byte request body of a buffered read: <c 1><v inner command><V fct><V ext>. */
    public static function bufferRequest($innerCommand, $fct = 0, $ext = 0)
    {
        return chr(1).pack('v', (int) $innerCommand & self::USHRT_MAX).pack('V', (int) $fct).pack('V', (int) $ext);
    }

    /** Decode the CMD_GET_FREE_SIZES reply (20 signed 32-bit fields on firmwares that answer it). */
    public static function parseSizes($payload)
    {
        if (strlen($payload) < 80) { return array(); }
        $f = array_values(unpack('l20', substr($payload, 0, 80)));
        return array('users' => (int) $f[4], 'fingers' => (int) $f[6], 'records' => (int) $f[8], 'admins' => (int) $f[10], 'cards' => (int) $f[12],
            'finger_capacity' => (int) $f[14], 'user_capacity' => (int) $f[15], 'record_capacity' => (int) $f[16]);
    }

    /**
     * Offline self-test of the two things that silently ruin a payroll: the checksum and the timestamp codec.
     * Runs with no device and no database. Returned by the Devices screen's "Run protocol self-test" button.
     * @return array [ok, passed, failed, cases]
     */
    public static function selfTest()
    {
        $cases = array(); $pass = 0; $fail = 0;
        $add = function ($name, $got, $want) use (&$cases, &$pass, &$fail) {
            $ok = ((string) $got === (string) $want);
            $cases[] = array('name' => $name, 'got' => (string) $got, 'want' => (string) $want, 'ok' => $ok);
            if ($ok) { $pass++; } else { $fail++; }
        };

        // 1. timestamp round trip across every hour of a year, including the midnight and month boundaries a night shift lives on
        $drift = 0; $checked = 0;
        for ($day = 0; $day < 366; $day++) {
            foreach (array('00:00:00', '05:59:59', '06:00:00', '13:37:07', '22:00:00', '23:59:59') as $clock) {
                $when = date('Y-m-d', strtotime('2026-01-01 +'.$day.' day')).' '.$clock;
                $back = self::decodeTime(self::encodeTime($when));
                $checked++;
                if ($back !== $when) { $drift++; }
            }
        }
        $add('timestamp round trip over '.$checked.' stamps in 2026', $drift.' mismatches', '0 mismatches');

        // 2. explicit vectors computed by hand from the documented formula
        $add('encode 2026-09-08 07:31:22', self::encodeTime('2026-09-08 07:31:22'), (((26 * 12 * 31) + (8 * 31) + 7) * 86400) + 7 * 3600 + 31 * 60 + 22);
        $add('encode 2000-01-01 00:00:00', self::encodeTime('2000-01-01 00:00:00'), 0);
        $add('decode 0', self::decodeTime(0), '2000-01-01 00:00:00');
        $add('decode 86399', self::decodeTime(86399), '2000-01-01 23:59:59');
        $add('decode 86400', self::decodeTime(86400), '2000-01-02 00:00:00');
        $add('midnight crossing 22:00 -> 06:00 decodes to exactly 8h', (strtotime(self::decodeTime(self::encodeTime('2026-03-01 06:00:00'))) - strtotime(self::decodeTime(self::encodeTime('2026-02-28 22:00:00')))), 8 * 3600);
        $add('month rollover 31 Jan 23:00 -> 1 Feb 07:00 is exactly 8h', self::encodeTime('2026-02-01 07:00:00') - self::encodeTime('2026-01-31 23:00:00'), 8 * 3600);
        $add('year rollover 31 Dec 22:00 -> 1 Jan 06:00 is exactly 8h', self::encodeTime('2027-01-01 06:00:00') - self::encodeTime('2026-12-31 22:00:00'), 8 * 3600);
        // The counter runs on a calendar where every month has 31 days, so raw counter subtraction is NOT elapsed
        // time across a short month: 28 Feb 22:00 to 1 Mar 06:00 is 8 hours of wall clock but 3 days + 8 hours of
        // counter. Nothing in this module may do arithmetic on the encoded value — always decode first.
        $add('raw counter subtraction across 28 Feb is deliberately not 8h', self::encodeTime('2026-03-01 06:00:00') - self::encodeTime('2026-02-28 22:00:00'), 3 * 86400 + 8 * 3600);

        // 3. checksum: a frame plus its own checksum is the ones-complement identity 0xFFFF... verified by re-checking
        $frame = self::frame(self::CMD_CONNECT, 0, 65535, '');
        $add('CMD_CONNECT frame length', strlen($frame), 8);
        $add('CMD_CONNECT frame verifies', self::verify($frame) ? 'yes' : 'no', 'yes');
        $p = self::parse($frame);
        $add('CMD_CONNECT command survives round trip', $p['command'], self::CMD_CONNECT);
        $payload = self::bufferRequest(self::CMD_ATTLOG_RRQ, 0, 0);
        $add('buffered read request length', strlen($payload), 11);
        $f2 = self::frame(self::CMD_DATA_WRRQ, 4660, 7, $payload);
        $add('CMD_DATA_WRRQ frame verifies', self::verify($f2) ? 'yes' : 'no', 'yes');
        $add('CMD_DATA_WRRQ session survives', self::parse($f2)['session'], 4660);
        $bad = $f2; $bad[9] = chr(ord($bad[9]) ^ 0xFF);
        $add('a corrupted frame fails the checksum', self::verify($bad) ? 'passed' : 'failed', 'failed');
        $add('checksum of an all-zero 8-byte frame is the ones-complement of 0', self::checksum(str_repeat("\0", 8)), 0xFFFE);
        $add('checksum handles an odd trailing byte', self::checksum("\x01\x00\x02") === self::checksum("\x01\x00\x02") ? 'stable' : 'unstable', 'stable');

        // 4. TCP envelope
        $wrapped = self::tcpWrap($f2);
        $add('TCP envelope adds 8 bytes', strlen($wrapped) - strlen($f2), 8);
        $add('TCP magic', bin2hex(substr($wrapped, 0, 4)), '5050827d');
        $add('TCP declared size', self::tcpSize(substr($wrapped, 0, 8)), strlen($f2));

        // 5. comm-key derivation is deterministic and four bytes wide
        $add('comm key length', strlen(self::commKey(123456, 4660)), 4);
        $add('comm key is deterministic', bin2hex(self::commKey(123456, 4660)) === bin2hex(self::commKey(123456, 4660)) ? 'yes' : 'no', 'yes');
        // MakeKey discards one derived byte (byte 2 carries the tick instead), so two session ids differing
        // only in the low byte legitimately produce the same key. Compare ids that differ higher up.
        $add('comm key varies with the session', bin2hex(self::commKey(123456, 4660)) === bin2hex(self::commKey(123456, 4916)) ? 'same' : 'different', 'different');
        // Byte 2 is the tick byte (50 = 0x32), not a key byte — the device reads it back to undo the XOR.
        $add('comm key 0 with session 0', bin2hex(self::commKey(0, 0)), '617d3279');
        $add('comm key 123456 with session 4660', bin2hex(self::commKey(123456, 4660)), '267f32eb');
        $add('comm key carries the tick in byte 2', bin2hex(substr(self::commKey(987654, 321), 2, 1)), '32');

        // 6. attendance decoding of a synthetic 40-byte record, then the 16- and 8-byte layouts
        $t = self::encodeTime('2026-09-08 22:04:11');
        $rec40 = pack('v', 12).str_pad('1042', 24, "\0").chr(1).pack('V', $t).chr(0).str_repeat("\0", 8);
        $r = self::parseAttendance(pack('V', 40).$rec40);
        $add('40-byte record count', count($r), 1);
        $add('40-byte employee ref', isset($r[0]) ? $r[0]['employee_ref'] : '', '1042');
        $add('40-byte timestamp', isset($r[0]) ? $r[0]['timestamp'] : '', '2026-09-08 22:04:11');
        $add('40-byte verify mode', isset($r[0]) ? $r[0]['verify'] : '', 1);
        $rec16 = pack('V', 77).pack('V', $t).chr(1).chr(1).str_repeat("\0", 2).pack('V', 3);
        $r16 = self::parseAttendance($rec16);
        $add('16-byte employee ref', isset($r16[0]) ? $r16[0]['employee_ref'] : '', '77');
        $add('16-byte timestamp', isset($r16[0]) ? $r16[0]['timestamp'] : '', '2026-09-08 22:04:11');
        $add('16-byte work code', isset($r16[0]) ? $r16[0]['work_code'] : '', '3');
        $rec8 = pack('v', 9).chr(1).pack('V', $t).chr(1);
        $r8 = self::parseAttendance($rec8, 1, 8);
        $add('8-byte employee ref', isset($r8[0]) ? $r8[0]['employee_ref'] : '', '9');
        $add('8-byte timestamp', isset($r8[0]) ? $r8[0]['timestamp'] : '', '2026-09-08 22:04:11');
        $add('state/verify swap option flips the two bytes', self::parseAttendance(pack('V', 40).$rec40, 0, 40, true)[0]['state'], 1);
        // Binary safety: a record whose uid bytes happen to be a valid UTF-8 sequence must still land on the
        // right offsets. A character-wise slice (Tools::substr) decodes this to the wrong person and the wrong
        // year, so this case is the regression guard for that whole class of bug.
        $recHigh = "\xc3\xa9".str_pad('2051', 24, "\0").chr(1).pack('V', self::encodeTime('2026-09-08 23:15:00')).chr(1).str_repeat("\0", 8);
        $rh = self::parseAttendance($rec40.$recHigh, 2, 40);
        $add('two 40-byte records with high bytes decode as two', count($rh), 2);
        $add('high-byte record employee ref', isset($rh[1]) ? $rh[1]['employee_ref'] : '', '2051');
        $add('high-byte record timestamp', isset($rh[1]) ? $rh[1]['timestamp'] : '', '2026-09-08 23:15:00');

        // 7. user encode/decode round trip
        $u = self::packUser72(12, '1042', 'Chidinma Okoro', 0, '', 700123, '1');
        $add('72-byte user record length', strlen($u), 72);
        $du = self::parseUsers(pack('V', 72).$u);
        $add('user ref round trip', isset($du[0]) ? $du[0]['device_user_id'] : '', '1042');
        $add('user name round trip', isset($du[0]) ? $du[0]['name'] : '', 'Chidinma Okoro');
        $add('user card round trip', isset($du[0]) ? $du[0]['card_no'] : '', '700123');
        $u28 = self::packUser28(12, 1042, 'Chidi', 0, '', 0, 1);
        $add('28-byte user record length', strlen($u28), 28);
        $add('28-byte user ref round trip', self::parseUsers($u28, 28)[0]['device_user_id'], '1042');

        // 8. record-width detection
        $add('record width from a device record count', self::recordSize(400, 10), 40);
        $add('record width falls back to 40', self::recordSize(80), 40);
        $add('record width honours a forced 16', self::recordSize(80, 0, 16), 16);

        return array('ok' => $fail === 0, 'passed' => $pass, 'failed' => $fail, 'cases' => $cases);
    }
}
