<?php
/**
 * Device registry and pairing. A TV boots the URL Launcher page carrying its MAC (and often its serial);
 * the portal resolves the room from the registry, and an unknown device waits in `pending` until the desk
 * approves it. The device token identifies the ROOM and nothing else — the guest comes from the booking.
 */
class PulseGpDevice
{
    /** MACs arrive as 00:16:6C:.., 00-16-6c-.. or bare hex depending on firmware — one canonical form is stored. */
    public static function normalise($v) { $v = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $v)); return Tools::substr($v, 0, 64); }
    public static function uid($mac, $serial) { $m = self::normalise($mac); return $m ? 'mac:'.$m : ($serial ? 'sn:'.self::normalise($serial) : ''); }

    public static function byId($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_device` WHERE id_pulse_gp_device='.(int) $id); }
    public static function byToken($token) { if (!preg_match('/^[a-f0-9]{64}$/', (string) $token)) { return null; } $d = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_device` WHERE token="'.pSQL($token).'"'); return $d ? $d : null; }
    public static function byUid($uid) { $d = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_device` WHERE uid="'.pSQL($uid).'"'); return $d ? $d : null; }
    public static function byRoom($idRoom) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_device` WHERE id_room='.(int) $idRoom.' AND status IN ("active","pending") ORDER BY type, id_pulse_gp_device'); }
    public static function newToken() { return hash('sha256', uniqid('gp', true).Tools::passwdGen(24)._COOKIE_KEY_); }

    /**
     * Pairing is unauthenticated by nature — the set has nothing to present but its MAC, which is not a secret
     * (it is printed on the back of the TV and visible to anything on the VLAN). Two guards keep that from
     * becoming "anyone on the internet can read room 214's folio": the request must come from the hotel network,
     * and a token is handed out once per activation. A set that loses its token needs a desk re-issue.
     */
    public static function pairNetworkAllowed()
    {
        $cidrs = array_filter(array_map('trim', explode(',', (string) PulseGpService::cfg('PAIR_CIDR', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,169.254.0.0/16,127.0.0.0/8'))));
        if (!$cidrs || in_array('0.0.0.0/0', $cidrs)) { return true; }
        $ip = (string) Tools::getRemoteAddr();
        $n = ip2long($ip);
        if ($n === false) { return false; } // IPv6 guest network: allowlist it explicitly or open the CIDR list
        foreach ($cidrs as $c) {
            $p = explode('/', $c); $base = ip2long($p[0]); $bits = isset($p[1]) ? (int) $p[1] : 32;
            if ($base === false || $bits < 0 || $bits > 32) { continue; }
            $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
            if (($n & $mask) === ($base & $mask)) { return true; }
        }
        return false;
    }

    /** Hand the token over once, then remember that we did. */
    protected static function claimToken(array $dev)
    {
        if ($dev['status'] !== 'active') { return null; }
        if (!empty($dev['token_claimed_at'])) { return null; }
        Db::getInstance()->update('pulse_gp_device', array('token_claimed_at' => date('Y-m-d H:i:s')), 'id_pulse_gp_device='.(int) $dev['id_pulse_gp_device'].' AND token_claimed_at IS NULL');
        if (!Db::getInstance()->Affected_Rows()) { return null; } // another caller got there first
        PulseGpService::audit('device_token_claim', array('ip' => (string) Tools::getRemoteAddr()), 'pulse_gp_device', (int) $dev['id_pulse_gp_device']);
        return $dev['token'];
    }

    /**
     * Pairing handshake. Returns the device row plus what the launcher should do next:
     * `active` → carry on, `pending` → show the pairing code and wait for the desk, `blocked` → show a holding screen.
     * A room number posted by the client is only ever a *hint* used when the desk pre-registered nothing.
     */
    public static function pair(array $d)
    {
        $uid = self::uid(isset($d['mac']) ? $d['mac'] : '', isset($d['serial']) ? $d['serial'] : '');
        if (!$uid) { throw new PrestaShopException('A MAC address or serial number is required to pair', 400); }
        if (!self::pairNetworkAllowed()) { throw new PrestaShopException('Pairing is only accepted from the hotel network', 403); }
        $now = date('Y-m-d H:i:s');
        $dev = self::byUid($uid);
        $ip = Tools::substr((string) Tools::getRemoteAddr(), 0, 45);
        // everything here arrives unauthenticated from the set itself — clip each field to its column
        $upd = array('mac' => pSQL(Tools::substr(self::normalise(isset($d['mac']) ? $d['mac'] : ''), 0, 32)), 'serial' => pSQL(Tools::substr((string) (isset($d['serial']) ? $d['serial'] : ''), 0, 64)),
            'model' => pSQL(Tools::substr((string) (isset($d['model']) ? $d['model'] : ''), 0, 64)), 'firmware' => pSQL(Tools::substr((string) (isset($d['firmware']) ? $d['firmware'] : ''), 0, 64)),
            'app_version' => pSQL(Tools::substr((string) (isset($d['app_version']) ? $d['app_version'] : ''), 0, 32)), 'ip' => pSQL($ip), 'last_seen' => $now, 'date_upd' => $now);
        if ($dev) {
            $upd['boots'] = (int) $dev['boots'] + 1;
            Db::getInstance()->update('pulse_gp_device', $upd, 'id_pulse_gp_device='.(int) $dev['id_pulse_gp_device']);
            $dev = self::byId($dev['id_pulse_gp_device']);
        } else {
            $room = self::resolveRoom(isset($d['room_num']) ? $d['room_num'] : '');
            $auto = (int) PulseGpService::cfg('AUTO_PAIR', 0) && $room;
            Db::getInstance()->insert('pulse_gp_device', array_merge($upd, array(
                'uid' => pSQL($uid), 'type' => pSQL(in_array(isset($d['type']) ? $d['type'] : '', array('tv', 'tablet', 'phone', 'cast', 'kiosk')) ? $d['type'] : 'tv'),
                'id_room' => $room ? (int) $room['id_room'] : null, 'room_num' => $room ? pSQL($room['room_num']) : null, 'floor' => $room ? pSQL($room['floor']) : null,
                'token' => pSQL(self::newToken()), 'locale' => pSQL(PulseGpService::defaultLang()), 'status' => $auto ? 'active' : 'pending',
                'label' => pSQL($room ? 'Room '.$room['room_num'] : 'Unassigned device'), 'boots' => 1, 'paired_at' => $auto ? $now : null, 'date_add' => $now,
            )));
            $dev = self::byId((int) Db::getInstance()->Insert_ID());
            PulseGpService::audit('device_seen', array('uid' => $uid, 'ip' => $ip, 'auto' => $auto ? 1 : 0), 'pulse_gp_device', (int) $dev['id_pulse_gp_device']);
            PulseGpService::event('actionPulsePortalDevicePaired', array('id_device' => (int) $dev['id_pulse_gp_device'], 'uid' => $uid, 'status' => $dev['status']));
        }
        return array(
            'id_device' => (int) $dev['id_pulse_gp_device'], 'status' => $dev['status'], 'token' => self::claimToken($dev),
            'room_num' => $dev['room_num'], 'pair_code' => self::pairCode($dev), 'label' => $dev['label'], 'locale' => $dev['locale'],
            'heartbeat' => (int) PulseGpService::cfg('HEARTBEAT_SEC', 60), 'theme' => PulseGpService::theme(), 'languages' => PulseGpService::langs(),
        );
    }

    /** Six digits the desk clerk reads off the TV screen when approving — derived, so it needs no storage. */
    public static function pairCode(array $dev) { return Tools::substr(strtoupper(hash('crc32b', $dev['uid'].'|'.(int) $dev['id_pulse_gp_device'])), 0, 6); }

    /** Room lookup used for a hint from the client; never trusted for identity, only for the desk's convenience. */
    public static function resolveRoom($roomNum)
    {
        $roomNum = trim((string) $roomNum);
        if ($roomNum === '') { return null; }
        $r = Db::getInstance()->getRow('SELECT id id_room, room_num, floor FROM `'._DB_PREFIX_.'htl_room_information` WHERE room_num="'.pSQL($roomNum).'"');
        return $r ? $r : null;
    }

    /** Desk approves a pending device and binds it to a room. */
    public static function approve($idDevice, $idRoom, $label = '', $type = null)
    {
        $r = $idRoom ? Db::getInstance()->getRow('SELECT id id_room, room_num, floor FROM `'._DB_PREFIX_.'htl_room_information` WHERE id='.(int) $idRoom) : null;
        if (!$r) { throw new PrestaShopException('Choose the room this device lives in'); }
        $d = self::byId($idDevice);
        if (!$d) { throw new PrestaShopException('Device not found'); }
        $u = array('id_room' => (int) $r['id_room'], 'room_num' => pSQL($r['room_num']), 'floor' => pSQL($r['floor']), 'status' => 'active',
            'label' => pSQL($label ? $label : 'Room '.$r['room_num']), 'paired_at' => date('Y-m-d H:i:s'), 'paired_by' => PulseGpService::emp(), 'date_upd' => date('Y-m-d H:i:s'));
        if ($type && in_array($type, array('tv', 'tablet', 'phone', 'cast', 'kiosk'))) { $u['type'] = pSQL($type); }
        if (!$d['token']) { $u['token'] = pSQL(self::newToken()); }
        $u['token_claimed_at'] = null; // approving arms one token collection on the set's next boot
        Db::getInstance()->update('pulse_gp_device', $u, 'id_pulse_gp_device='.(int) $idDevice);
        self::command($idDevice, 'reload', array('reason' => 'paired'));
        PulseGpService::audit('device_approve', array('room' => $r['room_num']), 'pulse_gp_device', (int) $idDevice);
        PulseGpService::event('actionPulsePortalDevicePaired', array('id_device' => (int) $idDevice, 'id_room' => (int) $r['id_room'], 'status' => 'active'));
        return true;
    }

    public static function setStatus($idDevice, $status)
    {
        if (!in_array($status, array('pending', 'active', 'blocked', 'retired'))) { throw new PrestaShopException('Unknown device status'); }
        Db::getInstance()->update('pulse_gp_device', array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_device='.(int) $idDevice);
        if ($status !== 'active') { PulseGpSession::revokeForDevice($idDevice, 'device_'.$status); }
        PulseGpService::audit('device_status', array('status' => $status), 'pulse_gp_device', (int) $idDevice);
        return true;
    }

    /** Rotate the token — used when a TV is swapped out or a token is suspected of having leaked. */
    public static function rotateToken($idDevice) { $t = self::newToken(); Db::getInstance()->update('pulse_gp_device', array('token' => pSQL($t), 'token_claimed_at' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_device='.(int) $idDevice); PulseGpSession::revokeForDevice($idDevice, 'token_rotated'); PulseGpService::audit('device_token_rotate', null, 'pulse_gp_device', (int) $idDevice); return $t; }

    public static function heartbeat($idDevice, array $d = array())
    {
        $u = array('last_seen' => date('Y-m-d H:i:s'), 'ip' => pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 45)), 'date_upd' => date('Y-m-d H:i:s'));
        foreach (array('firmware' => 64, 'app_version' => 32, 'model' => 64) as $k => $len) { if (!empty($d[$k]) && is_scalar($d[$k])) { $u[$k] = pSQL(Tools::substr((string) $d[$k], 0, $len)); } }
        if (!empty($d['locale'])) { $u['locale'] = pSQL(PulseGpService::lang($d['locale'])); }
        Db::getInstance()->update('pulse_gp_device', $u, 'id_pulse_gp_device='.(int) $idDevice);
        return self::popCommands($idDevice);
    }

    public static function online(array $dev) { $mins = (int) PulseGpService::cfg('OFFLINE_MIN', 5); return $dev['last_seen'] && strtotime($dev['last_seen']) > time() - $mins * 60; }

    /* ---------- command queue (remote reload, message push, wipe) ---------- */
    public static function command($idDevice, $type, array $payload = array())
    {
        Db::getInstance()->insert('pulse_gp_command', array('id_pulse_gp_device' => (int) $idDevice, 'type' => pSQL($type), 'payload' => pSQL(json_encode($payload), true),
            'id_employee' => PulseGpService::emp(), 'date_add' => date('Y-m-d H:i:s')));
        return (int) Db::getInstance()->Insert_ID();
    }
    public static function broadcast($type, array $payload = array(), $where = 'status="active"')
    {
        $n = 0;
        foreach (Db::getInstance()->executeS('SELECT id_pulse_gp_device FROM `'._DB_PREFIX_.'pulse_gp_device` WHERE '.$where) as $d) { self::command((int) $d['id_pulse_gp_device'], $type, $payload); $n++; }
        return $n;
    }
    /** Handed to the device on its heartbeat; marked sent so a flaky link re-delivers only what was never acked. */
    public static function popCommands($idDevice)
    {
        $out = array();
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_command` WHERE id_pulse_gp_device='.(int) $idDevice.' AND status IN ("queued","sent") AND date_add>DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY id_pulse_gp_command LIMIT 20') as $c) {
            $out[] = array('id' => (int) $c['id_pulse_gp_command'], 'type' => $c['type'], 'payload' => json_decode($c['payload'], true));
        }
        if ($out) { Db::getInstance()->update('pulse_gp_command', array('status' => 'sent', 'date_sent' => date('Y-m-d H:i:s')), 'id_pulse_gp_device='.(int) $idDevice.' AND status="queued"'); }
        return $out;
    }
    public static function ackCommands($idDevice, array $ids)
    {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) { return 0; }
        Db::getInstance()->update('pulse_gp_command', array('status' => 'acked', 'date_ack' => date('Y-m-d H:i:s')), 'id_pulse_gp_device='.(int) $idDevice.' AND id_pulse_gp_command IN ('.implode(',', $ids).')');
        return count($ids);
    }

    /**
     * Wipe on checkout: revoke sessions, clear the guest's traces from the device's own state and tell the
     * TV to reload into the vacant-room screen. The next guest must never see the last guest's data.
     */
    public static function wipeRoom($idRoom, $reason = 'checkout')
    {
        $policy = PulseGpService::cfg('WIPE_POLICY', 'wipe');
        $n = 0;
        foreach (self::byRoom($idRoom) as $d) {
            PulseGpSession::revokeForDevice((int) $d['id_pulse_gp_device'], $reason);
            if ($policy !== 'keep') { self::command((int) $d['id_pulse_gp_device'], 'wipe', array('reason' => $reason, 'lock' => $policy === 'lock' ? 1 : 0)); }
            self::command((int) $d['id_pulse_gp_device'], 'reload', array('reason' => $reason));
            Db::getInstance()->update('pulse_gp_device', array('last_wipe' => date('Y-m-d H:i:s'), 'locale' => pSQL(PulseGpService::defaultLang()), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_device='.(int) $d['id_pulse_gp_device']);
            $n++;
        }
        Db::getInstance()->update('pulse_gp_cast', array('status' => 'ended', 'date_upd' => date('Y-m-d H:i:s')), 'id_room='.(int) $idRoom.' AND status IN ("waiting","paired")');
        PulseGpService::audit('device_wipe', array('id_room' => (int) $idRoom, 'reason' => $reason, 'devices' => $n, 'policy' => $policy), 'pulse_gp_device', 0);
        return $n;
    }

    /** Check-in: arm the room's devices with the new stay so the welcome screen personalises on the next poll. */
    public static function armRoom($idRoom, array $booking = array())
    {
        $n = 0;
        foreach (self::byRoom($idRoom) as $d) {
            PulseGpSession::revokeForDevice((int) $d['id_pulse_gp_device'], 'new_stay');
            self::command((int) $d['id_pulse_gp_device'], 'reload', array('reason' => 'check_in', 'guest' => isset($booking['guest']) ? $booking['guest'] : ''));
            $n++;
        }
        return $n;
    }

    /** Devices grouped by floor for the dashboard, with an online flag computed from the heartbeat window. */
    public static function board()
    {
        $rows = Db::getInstance()->executeS('SELECT d.*, r.room_num rn, r.floor fl FROM `'._DB_PREFIX_.'pulse_gp_device` d LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=d.id_room WHERE d.status<>"retired" ORDER BY COALESCE(r.floor,d.floor,""), COALESCE(r.room_num,d.room_num,""), d.id_pulse_gp_device');
        $out = array();
        foreach ($rows as $d) {
            $d['online'] = self::online($d) ? 1 : 0; $d['floor'] = $d['fl'] ? $d['fl'] : $d['floor']; $d['room_num'] = $d['rn'] ? $d['rn'] : $d['room_num'];
            $d['pair_code'] = self::pairCode($d);
            $out[$d['floor'] === null || $d['floor'] === '' ? 'Unassigned' : $d['floor']][] = $d;
        }
        return $out;
    }

    public static function counts()
    {
        $mins = (int) PulseGpService::cfg('OFFLINE_MIN', 5);
        return Db::getInstance()->getRow('SELECT COUNT(*) total, SUM(status="active") active, SUM(status="pending") pending, SUM(status="blocked") blocked,
            SUM(status="active" AND last_seen>DATE_SUB(NOW(), INTERVAL '.$mins.' MINUTE)) online FROM `'._DB_PREFIX_.'pulse_gp_device` WHERE status<>"retired"');
    }
}
