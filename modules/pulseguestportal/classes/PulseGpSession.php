<?php
/**
 * Guest sessions. The device token says which ROOM the screen is in; the in-house booking says who the GUEST
 * is. A session is issued only when the room has a checked-in booking, is signed with the module secret, is
 * short-lived, and dies at check-out or on a room move.
 */
class PulseGpSession
{
    protected static function secret() { $s = Configuration::get('PULSE_GP_SECRET'); if (!$s) { $s = Tools::passwdGen(48); Configuration::updateValue('PULSE_GP_SECRET', $s); } return $s; }
    protected static function sign($sid, $idDevice, $idRoom, $idBooking, $exp) { return hash_hmac('sha256', $sid.'|'.(int) $idDevice.'|'.(int) $idRoom.'|'.(int) $idBooking.'|'.(int) $exp, self::secret()); }

    /** The in-house booking for a room, or null. This is the single gate on everything personal. */
    public static function inHouse($idRoom)
    {
        if (!$idRoom || !class_exists('HotelBookingDetail')) { return null; }
        return Db::getInstance()->getRow('SELECT b.id id_htl_booking, b.id_customer, b.id_room, b.date_from, b.date_to, r.room_num, CONCAT(c.firstname," ",c.lastname) guest
            FROM `'._DB_PREFIX_.'htl_booking_detail` b
            INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer
            LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room
            WHERE b.id_room='.(int) $idRoom.' AND b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND b.is_cancelled=0 AND b.is_refunded=0 ORDER BY b.date_from DESC');
    }

    /**
     * Issue a session for a device. Returns the signed token and what the screen may show.
     * A vacant room still gets a session row so the TV can browse the directory and the channel list —
     * it simply carries no booking, and every personal resource refuses it.
     */
    public static function issue(array $device, $lang = null)
    {
        if ($device['status'] !== 'active') { throw new PrestaShopException('Device is not paired', 403); }
        $g = self::inHouse((int) $device['id_room']);
        $ttl = max(5, (int) PulseGpService::cfg('SESSION_TTL_MIN', 120));
        $exp = time() + $ttl * 60;
        // a stay that ends today must not outlive the check-out hour
        if ($g) { $end = strtotime($g['date_to'].' '.PulseGpService::cfg('CHECKOUT_TIME', '12:00')) + 3600 * (int) PulseGpService::cfg('CHECKOUT_GRACE_HRS', 4); if ($end > time() && $end < $exp) { $exp = $end; } }
        $sid = Tools::substr(md5(uniqid('gps', true).Tools::passwdGen(12)), 0, 32);
        $sig = self::sign($sid, (int) $device['id_pulse_gp_device'], (int) $device['id_room'], $g ? (int) $g['id_htl_booking'] : 0, $exp);
        $token = $sid.'.'.$exp.'.'.$sig;
        $lang = $lang ? PulseGpService::lang($lang) : PulseGpService::lang($device['locale']);
        Db::getInstance()->insert('pulse_gp_session', array(
            'sid' => pSQL($sid), 'id_pulse_gp_device' => (int) $device['id_pulse_gp_device'], 'id_room' => $device['id_room'] ? (int) $device['id_room'] : null,
            'id_htl_booking' => $g ? (int) $g['id_htl_booking'] : null, 'id_customer' => $g ? (int) $g['id_customer'] : null, 'guest_name' => pSQL($g ? $g['guest'] : ''),
            'locale' => pSQL($lang), 'token_hash' => pSQL(hash('sha256', $token)), 'expires_at' => date('Y-m-d H:i:s', $exp),
            'ip' => pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 45)), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ));
        return array('token' => $token, 'expires_at' => date('c', $exp), 'ttl' => $exp - time(), 'in_house' => $g ? 1 : 0, 'lang' => $lang,
            'room_num' => $device['room_num'], 'guest' => $g ? $g['guest'] : null, 'id_session' => (int) Db::getInstance()->Insert_ID());
    }

    /** Verify a token: signature, expiry, live row, and that the device still owns it. */
    public static function verify($token, array $device = null)
    {
        $p = explode('.', (string) $token);
        if (count($p) !== 3 || !preg_match('/^[a-f0-9]{32}$/', $p[0]) || !ctype_digit($p[1])) { throw new PrestaShopException('Invalid session', 401); }
        if ((int) $p[1] < time()) { throw new PrestaShopException('Session expired', 401); }
        $s = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_session` WHERE sid="'.pSQL($p[0]).'"');
        if (!$s || (int) $s['revoked']) { throw new PrestaShopException('Session expired', 401); }
        if (!hash_equals(self::sign($p[0], $s['id_pulse_gp_device'], $s['id_room'], $s['id_htl_booking'], $p[1]), $p[2])) { throw new PrestaShopException('Invalid session', 401); }
        if (!hash_equals($s['token_hash'], hash('sha256', $token))) { throw new PrestaShopException('Invalid session', 401); }
        if (strtotime($s['expires_at']) < time()) { throw new PrestaShopException('Session expired', 401); }
        if ($device && (int) $device['id_pulse_gp_device'] !== (int) $s['id_pulse_gp_device']) { throw new PrestaShopException('Session belongs to another device', 403); }
        // the stay may have ended since the token was cut — re-check before anything personal is served
        if ($s['id_htl_booking'] && !self::stillCheckedIn((int) $s['id_htl_booking'], (int) $s['id_room'])) { self::revoke((int) $s['id_pulse_gp_session'], 'stay_ended'); throw new PrestaShopException('Session expired', 401); }
        Db::getInstance()->update('pulse_gp_session', array('date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_session='.(int) $s['id_pulse_gp_session']);
        return $s;
    }

    public static function stillCheckedIn($idBooking, $idRoom)
    {
        return (bool) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id='.(int) $idBooking.' AND id_room='.(int) $idRoom.' AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND is_cancelled=0 AND is_refunded=0');
    }

    /** Everything personal goes through this: no in-house booking, no data. */
    public static function requireGuest($session)
    {
        if (!$session || empty($session['id_htl_booking'])) { throw new PrestaShopException('No guest is checked into this room', 403); }
        return $session;
    }

    public static function revoke($idSession, $reason = 'manual') { Db::getInstance()->update('pulse_gp_session', array('revoked' => 1, 'revoke_reason' => pSQL($reason), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_session='.(int) $idSession); return true; }
    public static function revokeForDevice($idDevice, $reason = 'device') { return Db::getInstance()->update('pulse_gp_session', array('revoked' => 1, 'revoke_reason' => pSQL($reason), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_device='.(int) $idDevice.' AND revoked=0'); }
    public static function revokeForBooking($idBooking, $reason = 'checkout') { return Db::getInstance()->update('pulse_gp_session', array('revoked' => 1, 'revoke_reason' => pSQL($reason), 'date_upd' => date('Y-m-d H:i:s')), 'id_htl_booking='.(int) $idBooking.' AND revoked=0'); }
    public static function revokeForRoom($idRoom, $reason = 'room') { return Db::getInstance()->update('pulse_gp_session', array('revoked' => 1, 'revoke_reason' => pSQL($reason), 'date_upd' => date('Y-m-d H:i:s')), 'id_room='.(int) $idRoom.' AND revoked=0'); }
    public static function setLocale($idSession, $lang) { $lang = PulseGpService::lang($lang); Db::getInstance()->update('pulse_gp_session', array('locale' => pSQL($lang), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_session='.(int) $idSession); return $lang; }
    /** Housekeeping for the cron: expired rows are dropped, not kept forever. */
    public static function purge($days = 7) { return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_gp_session` WHERE expires_at<DATE_SUB(NOW(), INTERVAL '.(int) $days.' DAY)'); }
}
