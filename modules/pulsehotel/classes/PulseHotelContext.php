<?php
/**
 * The hotel a back-office session is working in, and the gate that puts it there.
 *
 * Every Pulse screen, service and query runs inside exactly one hotel. The id lives in the employee's
 * cookie, but the cookie is never trusted on its own: the access table is re-checked on every request,
 * so revoking a person's access takes effect on their next click rather than at their next login.
 *
 * Access is granted per employee (`pulse_hotel_access`). QloApps' own profile→hotel table
 * (`htl_access`) is still honoured as a fallback for a property that has not migrated yet, so an
 * existing install keeps working the moment this module is enabled.
 */
class PulseHotelContext
{
    const COOKIE = 'pulse_id_hotel';
    protected static $cache = null;
    protected static $allowed = null;

    /* ---------------- who may see what ---------------- */

    /** Hotels this employee may work in, newest grant first. Empty array means "no access at all". */
    public static function allowed($idEmployee = null)
    {
        $idEmployee = (int) ($idEmployee ? $idEmployee : self::emp());
        if (!$idEmployee) { return array(); }
        if (self::$allowed !== null && self::$allowed['emp'] === $idEmployee) { return self::$allowed['rows']; }
        $rows = Db::getInstance()->executeS('SELECT a.id_hotel, a.is_default, a.can_switch, hbl.hotel_name, hbi.active
            FROM `'._DB_PREFIX_.'pulse_hotel_access` a
            INNER JOIN `'._DB_PREFIX_.'htl_branch_info` hbi ON hbi.id = a.id_hotel
            LEFT JOIN `'._DB_PREFIX_.'htl_branch_info_lang` hbl ON hbl.id = hbi.id AND hbl.id_lang = '.(int) Context::getContext()->language->id.'
            WHERE a.id_employee = '.$idEmployee.' AND hbi.active = 1 ORDER BY a.is_default DESC, hbl.hotel_name');
        $rows = $rows ? $rows : array();
        // Nothing granted yet: fall back to the profile-level table QloApps already ships, so an
        // install that has never used this screen is not locked out the day the module goes on.
        if (!$rows && self::profileFallbackEnabled()) {
            $e = new Employee($idEmployee);
            if (Validate::isLoadedObject($e) && class_exists('HotelBranchInformation')) {
                foreach ((array) HotelBranchInformation::getProfileAccessedHotels((int) $e->id_profile, 1, 0) as $h) {
                    if (!empty($h['active'])) {
                        $rows[] = array('id_hotel' => (int) $h['id_hotel'], 'is_default' => 0, 'can_switch' => 1,
                            'hotel_name' => isset($h['hotel_name']) ? $h['hotel_name'] : ('Hotel '.(int) $h['id_hotel']), 'active' => 1);
                    }
                }
            }
        }
        self::$allowed = array('emp' => $idEmployee, 'rows' => $rows);
        return $rows;
    }

    public static function profileFallbackEnabled() { return (int) Configuration::get('PULSE_HOTEL_PROFILE_FALLBACK') === 1; }

    public static function mayUse($idHotel, $idEmployee = null)
    {
        $idHotel = (int) $idHotel;
        foreach (self::allowed($idEmployee) as $h) { if ((int) $h['id_hotel'] === $idHotel) { return $h; } }
        return false;
    }

    /** A person pinned to one hotel (can_switch = 0 on their only grant) gets no switcher. */
    public static function maySwitch($idEmployee = null)
    {
        $rows = self::allowed($idEmployee);
        if (count($rows) < 2) { return false; }
        foreach ($rows as $h) { if (!(int) $h['can_switch']) { return false; } }
        return true;
    }

    /* ---------------- the current hotel ---------------- */

    /**
     * The hotel this request runs in, or 0 when none has been chosen yet.
     * Re-validated against the access table on every call, so a revoked grant bites immediately.
     */
    public static function id()
    {
        if (self::$cache !== null) { return self::$cache; }
        $c = Context::getContext()->cookie;
        $id = $c && isset($c->{self::COOKIE}) ? (int) $c->{self::COOKIE} : 0;
        if ($id && !self::mayUse($id)) { self::clear(); $id = 0; }
        return self::$cache = $id;
    }

    public static function current()
    {
        $id = self::id();
        if (!$id) { return null; }
        foreach (self::allowed() as $h) { if ((int) $h['id_hotel'] === $id) { return $h; } }
        return null;
    }

    public static function name()
    {
        $h = self::current();
        return $h && $h['hotel_name'] !== '' ? $h['hotel_name'] : ($h ? 'Hotel '.(int) $h['id_hotel'] : '');
    }

    /** Put the session into a hotel. Refuses — and says so — when the employee has no grant for it. */
    public static function set($idHotel, $event = 'selected')
    {
        $idHotel = (int) $idHotel;
        $from = self::id();
        if (!self::mayUse($idHotel)) {
            self::log('refused', $idHotel, $from, 'no grant for this hotel');
            throw new PrestaShopException('You do not have access to that hotel.');
        }
        $c = Context::getContext()->cookie;
        $c->{self::COOKIE} = $idHotel;
        $c->write();
        self::$cache = $idHotel;
        self::log($event, $idHotel, $from, '');
        PulseCoreService::audit('pulsehotel', $event, array('id_hotel' => $idHotel, 'from' => $from), 'htl_branch_info', $idHotel);
        PulseCoreService::event('actionPulseHotelChanged', array('id_hotel' => $idHotel, 'from_hotel' => $from, 'id_employee' => self::emp()));
        return true;
    }

    /**
     * Put this request into a hotel without touching the cookie and without checking a grant.
     *
     * For the surfaces that have no back-office session and therefore no cookie to read: an API call
     * authenticated by a token that belongs to a property, a cron job working through the properties
     * one at a time, a guest portal request identified by the device in a room. In each of those the
     * caller has already established which hotel it is allowed to act for; this records that decision
     * for the rest of the request so every query is scoped to it.
     */
    public static function assume($idHotel)
    {
        self::$cache = (int) $idHotel;
        return self::$cache;
    }

    public static function clear()
    {
        $c = Context::getContext()->cookie;
        if ($c && isset($c->{self::COOKIE})) { unset($c->{self::COOKIE}); $c->write(); }
        self::$cache = null;
    }

    /** Forget everything cached for this request — used after a grant is changed in the same request. */
    public static function reset() { self::$cache = null; self::$allowed = null; }

    /* ---------------- query scoping ---------------- */

    /**
     * The AND fragment every hotel-scoped query appends. Returns a clause that matches nothing when no
     * hotel is set, because "no hotel chosen" must never mean "show me everything".
     *
     *     $sql .= PulseHotelContext::sql('f');      //  AND f.`id_hotel` = 3
     */
    public static function sql($alias = '', $column = 'id_hotel')
    {
        $id = self::id();
        $p = $alias !== '' ? '`'.bqSQL($alias).'`.' : '';
        return ' AND '.$p.'`'.bqSQL($column).'` = '.($id ? (int) $id : 0);
    }

    /** Same clause without the leading AND, for a query that has no WHERE yet. */
    public static function where($alias = '', $column = 'id_hotel')
    {
        return Tools::substr(self::sql($alias, $column), 5);
    }

    /** Stamp a row on the way in. Every Pulse insert into a scoped table goes through this. */
    public static function stamp(array $row, $column = 'id_hotel')
    {
        if (!isset($row[$column]) || !$row[$column]) { $row[$column] = (int) self::id(); }
        return $row;
    }

    /** The hotel a room belongs to — the bridge used when back-filling and when a room is the only clue. */
    public static function ofRoom($idRoom)
    {
        return (int) Db::getInstance()->getValue('SELECT id_hotel FROM `'._DB_PREFIX_.'htl_room_information` WHERE id = '.(int) $idRoom);
    }

    public static function ofBooking($idBooking)
    {
        return (int) Db::getInstance()->getValue('SELECT id_hotel FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id = '.(int) $idBooking);
    }

    /* ---------------- the gate ---------------- */

    /**
     * Called on every back-office request by the module's hook. Returns a URL to redirect to, or null.
     *  - no grants at all      → signed out with an error, because there is nothing they could work on
     *  - grants but none chosen→ the hotel picker
     *  - chosen hotel revoked  → back to the picker
     */
    public static function gate($controllerName)
    {
        if (!self::enforced($controllerName)) { return null; }
        $link = Context::getContext()->link;
        if (!self::allowed()) {
            self::log('no_access', null, null, $controllerName);
            return 'SIGNOUT';
        }
        if (!self::id()) { return $link->getAdminLink('AdminPulseHotelSelect').'&back='.urlencode($controllerName); }
        return null;
    }

    /** Which controllers the gate applies to: every Pulse screen except the picker and the licence page. */
    public static function enforced($controllerName)
    {
        if (strpos($controllerName, 'AdminPulse') !== 0) { return false; }
        return !in_array($controllerName, array('AdminPulseHotelSelect', 'AdminPulseLicense'));
    }

    /* ---------------- plumbing ---------------- */

    public static function emp()
    {
        $c = Context::getContext();
        return isset($c->employee) && $c->employee ? (int) $c->employee->id : 0;
    }

    public static function log($event, $idHotel, $from = null, $detail = '')
    {
        return Db::getInstance()->insert('pulse_hotel_session', array(
            'id_employee' => (int) self::emp(), 'id_hotel' => $idHotel ? (int) $idHotel : null, 'event' => pSQL((string) $event) ?: '',
            'from_hotel' => $from ? (int) $from : null,
            'controller' => pSQL((string) Tools::substr((string) Tools::getValue('controller'), 0, 64)) ?: '',
            'ip' => pSQL((string) Tools::substr((string) Tools::getRemoteAddr(), 0, 45)) ?: '',
            'detail' => pSQL((string) Tools::substr((string) $detail, 0, 255)) ?: '', 'date_add' => date('Y-m-d H:i:s'),
        ), false);
    }

    /** Every active hotel, for the access-granting screen. */
    public static function allHotels()
    {
        return Db::getInstance()->executeS('SELECT hbi.id AS id_hotel, hbl.hotel_name, hbi.active
            FROM `'._DB_PREFIX_.'htl_branch_info` hbi
            LEFT JOIN `'._DB_PREFIX_.'htl_branch_info_lang` hbl ON hbl.id = hbi.id AND hbl.id_lang = '.(int) Context::getContext()->language->id.'
            ORDER BY hbi.active DESC, hbl.hotel_name');
    }
}
