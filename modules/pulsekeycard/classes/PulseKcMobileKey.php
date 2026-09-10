<?php
/**
 * Mobile keys (BLE / QR). Pulse issues a signed, short-lived, device-bound credential and stores it
 * encrypted; the guest app or TV portal fetches it with the delivery token and refreshes it before it lapses.
 * A real BLE lock additionally needs the vendor SDK on the phone — see README ▸ Mobile key.
 */
class PulseKcMobileKey
{
    const T = 'pulse_kc_mobile_key';

    protected static function secret()
    {
        $s = Configuration::get('PULSE_KC_SECRET');
        if (!$s) { $s = Tools::passwdGen(48); Configuration::updateValue('PULSE_KC_SECRET', $s); }
        return $s;
    }
    protected static function b64($raw) { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
    protected static function unb64($s) { $p = strlen($s) % 4; return base64_decode(strtr($s.str_repeat('=', $p ? 4 - $p : 0), '-_', '+/')); }

    /** Envelope: base64url(claims).base64url(hmac) — the same shape the simulator writes to a card. */
    public static function sign(array $claims)
    {
        $json = json_encode($claims);
        return self::b64($json).'.'.self::b64(hash_hmac('sha256', $json, self::secret(), true));
    }
    public static function verify($credential)
    {
        $p = explode('.', (string) $credential);
        if (count($p) !== 2) { return false; }
        $json = self::unb64($p[0]);
        if (!hash_equals(self::b64(hash_hmac('sha256', $json, self::secret(), true)), $p[1])) { return false; }
        $c = json_decode($json, true);
        return is_array($c) && isset($c['exp']) && $c['exp'] >= time() ? $c : false;
    }

    /** Fingerprint a device from whatever the app can supply — never store the raw identifiers. */
    public static function fingerprint($deviceId, $ua = '')
    {
        $raw = trim((string) $deviceId).'|'.Tools::substr((string) $ua, 0, 180);
        return hash('sha256', $raw.'|'.self::secret());
    }

    /**
     * Issue a mobile credential for a stay. Cuts the underlying key row too (type guest, mobile=1) so the
     * mobile key appears in the register and in the lock audit like any other credential.
     * $o: rooms, doors, channel (ble|qr|both), device_id, device_label, deliver (bool), valid_from/valid_to.
     */
    public static function issue($idBooking, array $o = array())
    {
        if (!PulseKcService::cfg('MOBILE_ENABLED', 1)) { throw new PrestaShopException('Mobile keys are switched off in Key Card settings'); }
        $b = PulseKcService::fd() ? PulseFdService::booking((int) $idBooking) : null;
        if (!$b && empty($o['rooms'])) { throw new PrestaShopException('Booking not found — pick the room manually'); }
        $rooms = !empty($o['rooms']) ? array_map('intval', (array) $o['rooms']) : array((int) $b['id_room']);
        if (!empty($o['valid_from']) && !empty($o['valid_to'])) { $from = $o['valid_from']; $to = $o['valid_to']; }
        elseif ($b) { list($from, $to) = PulseKcService::window($b['date_from'], $b['date_to']); }
        else { $from = date('Y-m-d H:i:s'); $to = date('Y-m-d H:i:s', time() + 24 * 3600); }

        $idKey = PulseKcKey::issue(array('type' => 'guest', 'mobile' => 1, 'id_htl_booking' => $idBooking ? (int) $idBooking : null, 'rooms' => $rooms,
            'doors' => isset($o['doors']) ? $o['doors'] : PulseKcService::defaultDoorIds(), 'valid_from' => $from, 'valid_to' => $to,
            'guest_name' => $b ? $b['guest'] : (isset($o['guest_name']) ? $o['guest_name'] : ''), 'note' => 'Mobile key'));

        $token = hash('sha256', uniqid('mk', true).Tools::passwdGen(24));
        $fp = !empty($o['device_id']) ? self::fingerprint($o['device_id'], isset($o['ua']) ? $o['ua'] : '') : null;
        Db::getInstance()->insert(self::T, array(
            'id_pulse_kc_key' => (int) $idKey, 'id_htl_booking' => $idBooking ? (int) $idBooking : null, 'id_customer' => $b ? (int) $b['id_customer'] : (!empty($o['id_customer']) ? (int) $o['id_customer'] : null),
            'token' => pSQL($token), 'credential_enc' => '', 'credential_exp' => date('Y-m-d H:i:s'),
            'channel' => pSQL(isset($o['channel']) ? $o['channel'] : 'both'), 'device_fingerprint' => $fp ? pSQL($fp) : null, 'device_label' => pSQL(Tools::substr((string) (isset($o['device_label']) ? $o['device_label'] : ''), 0, 96)),
            'device_bound_at' => $fp ? date('Y-m-d H:i:s') : null, 'valid_from' => pSQL($from), 'valid_to' => pSQL($to), 'status' => 'issued',
            'business_date' => PulseKcService::bd(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        self::mint($id);
        PulseCoreService::audit('pulsekeycard', 'mobile_key_issue', array('id_key' => $idKey, 'rooms' => PulseKcKey::roomNumbers($rooms), 'valid_to' => $to, 'channel' => isset($o['channel']) ? $o['channel'] : 'both'), self::T, $id);
        if (!isset($o['deliver']) || $o['deliver']) { self::deliver($id); }
        return $id;
    }

    /** (Re)mint the short-lived credential for a mobile key row. Returns the row with the plain credential. */
    public static function mint($id, $fingerprint = null)
    {
        $m = self::get($id);
        if (!$m) { throw new PrestaShopException('Mobile key not found'); }
        if ($m['status'] === 'revoked') { throw new PrestaShopException('This mobile key has been revoked'); }
        if (strtotime($m['valid_to']) < time()) { self::setStatus($id, 'expired'); throw new PrestaShopException('This mobile key has expired'); }
        $ttl = max(15, (int) PulseKcService::cfg('MOBILE_TTL_MIN', 240)) * 60;
        $exp = min(strtotime($m['valid_to']), time() + $ttl);
        $k = PulseKcKey::get((int) $m['id_pulse_kc_key']);
        $claims = array('v' => 1, 'typ' => 'mobile', 'mk' => (int) $id, 'kn' => $k ? $k['key_no'] : '', 'sn' => $k ? $k['card_serial'] : '',
            'rooms' => $k ? array_values(array_filter(explode(',', (string) $k['room_nums']))) : array(),
            'doors' => $k ? PulseKcService::doorCodes($k['doors']) : array(),
            'nbf' => strtotime($m['valid_from']), 'exp' => $exp, 'stay_exp' => strtotime($m['valid_to']),
            'db' => $k ? (int) $k['override_deadbolt'] : 0, 'dnd' => $k ? (int) $k['override_dnd'] : 0,
            'dev' => $fingerprint ? $fingerprint : $m['device_fingerprint'], 'nonce' => bin2hex(substr(hash('sha256', uniqid('n', true), true), 0, 8)), 'iat' => time());
        $cred = self::sign($claims);
        Db::getInstance()->update(self::T, array('credential_enc' => pSQL(PulseCoreService::encrypt($cred), true), 'credential_exp' => date('Y-m-d H:i:s', $exp),
            'status' => 'active', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_mobile_key='.(int) $id);
        $m['credential'] = $cred; $m['credential_exp'] = date('Y-m-d H:i:s', $exp); $m['status'] = 'active';
        return $m;
    }

    /**
     * Guest app / TV portal fetch. Binds the device on first use; afterwards a different device is refused
     * unless the rebind policy allows it (a guest who changed phone asks the desk to re-issue).
     */
    public static function fetch($token, $deviceId = null, $ua = '', $ip = null)
    {
        $m = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE token="'.pSQL($token).'"');
        if (!$m) { throw new PrestaShopException('Unknown mobile key', 404); }
        if ($m['status'] === 'revoked') { throw new PrestaShopException('This key has been revoked', 403); }
        if (strtotime($m['valid_to']) < time()) { self::setStatus((int) $m['id_pulse_kc_mobile_key'], 'expired'); throw new PrestaShopException('This key has expired', 403); }
        $fp = $deviceId ? self::fingerprint($deviceId, $ua) : null;
        // a bound key is only ever handed back to the device it is bound to — omitting device_id must not
        // skip the check, or the binding is worth nothing to anyone who has copied the delivery link
        if ($m['device_fingerprint'] && !PulseKcService::cfg('MOBILE_REBIND', 0) && (!$fp || !hash_equals($m['device_fingerprint'], $fp))) {
            PulseCoreService::audit('pulsekeycard', 'mobile_key_device_mismatch', array('id' => (int) $m['id_pulse_kc_mobile_key'], 'device_given' => $fp ? 1 : 0), self::T, (int) $m['id_pulse_kc_mobile_key']);
            throw new PrestaShopException('This key is bound to another device — ask the front desk to re-issue it', 403);
        }
        if (!$m['device_fingerprint'] && $fp) {
            Db::getInstance()->update(self::T, array('device_fingerprint' => pSQL($fp), 'device_bound_at' => date('Y-m-d H:i:s'), 'device_label' => pSQL(Tools::substr((string) $ua, 0, 96))), 'id_pulse_kc_mobile_key='.(int) $m['id_pulse_kc_mobile_key']);
            $m['device_fingerprint'] = $fp;
        }
        $out = self::mint((int) $m['id_pulse_kc_mobile_key'], $m['device_fingerprint']);
        Db::getInstance()->update(self::T, array('last_ip' => pSQL(Tools::substr((string) $ip, 0, 45)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_mobile_key='.(int) $m['id_pulse_kc_mobile_key']);
        return self::publicView($out);
    }

    /** Refresh before the short TTL lapses; requires the bound device and counts against a sane ceiling. */
    public static function refresh($token, $deviceId = null, $ua = '', $ip = null)
    {
        $m = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE token="'.pSQL($token).'"');
        if (!$m) { throw new PrestaShopException('Unknown mobile key', 404); }
        $max = (int) PulseKcService::cfg('MOBILE_MAX_REFRESH', 500);
        if ($max > 0 && (int) $m['refresh_count'] >= $max) { throw new PrestaShopException('Refresh limit reached — ask the front desk to re-issue the key', 429); }
        $out = self::fetch($token, $deviceId, $ua, $ip);
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET refresh_count=refresh_count+1, last_refresh_at=NOW() WHERE id_pulse_kc_mobile_key='.(int) $m['id_pulse_kc_mobile_key']);
        return $out;
    }

    /** What the app is allowed to see — never the stored ciphertext, never the token of another key. */
    protected static function publicView(array $m)
    {
        $k = PulseKcKey::get((int) $m['id_pulse_kc_key']);
        return array('id' => (int) $m['id_pulse_kc_mobile_key'], 'credential' => isset($m['credential']) ? $m['credential'] : null, 'credential_expires' => $m['credential_exp'],
            'valid_from' => $m['valid_from'], 'valid_to' => $m['valid_to'], 'channel' => $m['channel'], 'status' => $m['status'],
            'rooms' => $k ? array_values(array_filter(explode(',', (string) $k['room_nums']))) : array(), 'doors' => $k ? PulseKcService::doorCodes($k['doors']) : array(),
            'refresh_after' => max(60, (int) ((strtotime($m['credential_exp']) - time()) * 0.75)), 'qr_text' => isset($m['credential']) ? 'PULSEKEY:'.$m['credential'] : null);
    }

    /** Send the guest the deep link (and QR text) that opens the key in the app or TV portal. */
    public static function deliver($id)
    {
        $m = self::get($id);
        if (!$m) { return false; }
        $url = Context::getContext()->link->getModuleLink('pulsekeycard', 'api', array('resource' => 'mobile_key', 'token' => $m['token']));
        $via = 'none';
        if ($m['id_customer'] && class_exists('PulseComms')) {
            $c = new Customer((int) $m['id_customer']);
            if (Validate::isLoadedObject($c)) {
                $sent = PulseComms::send('mobile_key', $c, array('id_htl_booking' => $m['id_htl_booking'], 'url' => $url, 'valid_to' => $m['valid_to']));
                if ($sent) { $via = 'comms'; }
                else {
                    // Front Desk builds ship a fixed template list; fall back to a direct mail so the guest still gets the key.
                    $body = '<p>Your room key is ready. Open this link on the phone you will use to enter the room:</p><p><a href="'.$url.'">'.$url.'</a></p><p>It works until '.$m['valid_to'].'.</p>';
                    if (@Mail::Send((int) Context::getContext()->language->id, 'account', 'Your mobile room key — '.Configuration::get('PS_SHOP_NAME'),
                        array('{firstname}' => $c->firstname, '{lastname}' => $c->lastname, '{email}' => $c->email, '{passwd}' => '', '{html}' => $body), $c->email, $c->firstname.' '.$c->lastname)) { $via = 'email'; }
                }
            }
        }
        Db::getInstance()->update(self::T, array('delivered_via' => pSQL($via), 'delivered_at' => date('Y-m-d H:i:s')), 'id_pulse_kc_mobile_key='.(int) $id);
        PulseCoreService::audit('pulsekeycard', 'mobile_key_delivered', array('id' => (int) $id, 'via' => $via), self::T, $id);
        return array('via' => $via, 'url' => $url);
    }

    public static function get($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_kc_mobile_key='.(int) $id); }
    public static function setStatus($id, $status) { return Db::getInstance()->update(self::T, array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_mobile_key='.(int) $id); }

    public static function revoke($id, $reason = '')
    {
        $m = self::get($id);
        if (!$m) { return false; }
        Db::getInstance()->update(self::T, array('status' => 'revoked', 'credential_enc' => '', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_mobile_key='.(int) $id);
        PulseCoreService::audit('pulsekeycard', 'mobile_key_revoke', array('id' => (int) $id, 'reason' => $reason), self::T, $id);
        return true;
    }

    public static function revokeForKey($idKey, $reason = '')
    {
        $n = 0;
        foreach (Db::getInstance()->executeS('SELECT id_pulse_kc_mobile_key FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_kc_key='.(int) $idKey.' AND status IN ("issued","active")') as $m) { self::revoke((int) $m['id_pulse_kc_mobile_key'], $reason); $n++; }
        return $n;
    }
    public static function revokeForBooking($idBooking, $reason = '')
    {
        $n = 0;
        foreach (Db::getInstance()->executeS('SELECT id_pulse_kc_mobile_key FROM `'._DB_PREFIX_.self::T.'` WHERE id_htl_booking='.(int) $idBooking.' AND status IN ("issued","active")') as $m) { self::revoke((int) $m['id_pulse_kc_mobile_key'], $reason); $n++; }
        return $n;
    }
    public static function extendForKey($idKey, $validTo)
    {
        return Db::getInstance()->update(self::T, array('valid_to' => pSQL($validTo), 'status' => 'active', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_key='.(int) $idKey.' AND status IN ("issued","active")');
    }

    public static function forBooking($idBooking)
    {
        return Db::getInstance()->executeS('SELECT m.*, k.key_no, k.room_nums FROM `'._DB_PREFIX_.self::T.'` m INNER JOIN `'._DB_PREFIX_.'pulse_kc_key` k ON k.id_pulse_kc_key=m.id_pulse_kc_key WHERE m.id_htl_booking='.(int) $idBooking.' ORDER BY m.id_pulse_kc_mobile_key DESC');
    }
    public static function active()
    {
        return Db::getInstance()->executeS('SELECT m.*, k.key_no, k.room_nums, k.guest_name FROM `'._DB_PREFIX_.self::T.'` m INNER JOIN `'._DB_PREFIX_.'pulse_kc_key` k ON k.id_pulse_kc_key=m.id_pulse_kc_key
            WHERE m.status IN ("issued","active") AND m.valid_to>NOW() ORDER BY m.valid_to');
    }

    /** Cron: lapse anything whose stay window has closed. */
    public static function expireDue()
    {
        return (int) Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET status="expired", credential_enc="", date_upd=NOW() WHERE status IN ("issued","active") AND valid_to<NOW()') ? (int) Db::getInstance()->Affected_Rows() : 0;
    }
}
