<?php
/** Granting and revoking an employee's hotels. Thin on purpose — the rules live in PulseHotelContext. */
class PulseHotelAccess
{
    public static function forEmployee($idEmployee)
    {
        return Db::getInstance()->executeS('SELECT a.*, hbl.hotel_name FROM `'._DB_PREFIX_.'pulse_hotel_access` a
            LEFT JOIN `'._DB_PREFIX_.'htl_branch_info_lang` hbl ON hbl.id = a.id_hotel AND hbl.id_lang = '.(int) Context::getContext()->language->id.'
            WHERE a.id_employee = '.(int) $idEmployee.' ORDER BY a.is_default DESC, hbl.hotel_name');
    }

    /** Everyone who can sign in, with a count of the hotels they hold. */
    public static function employees()
    {
        return Db::getInstance()->executeS('SELECT e.id_employee, e.firstname, e.lastname, e.email, e.active, p.name AS profile,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hotel_access` a WHERE a.id_employee = e.id_employee) AS hotels
            FROM `'._DB_PREFIX_.'employee` e
            LEFT JOIN `'._DB_PREFIX_.'profile_lang` p ON p.id_profile = e.id_profile AND p.id_lang = '.(int) Context::getContext()->language->id.'
            ORDER BY e.active DESC, e.lastname, e.firstname');
    }

    public static function grant($idEmployee, $idHotel, $isDefault = 0, $canSwitch = 1)
    {
        $now = date('Y-m-d H:i:s');
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_hotel_access`
            (id_employee, id_hotel, is_default, can_switch, granted_by, date_add, date_upd) VALUES
            ('.(int) $idEmployee.','.(int) $idHotel.','.(int) $isDefault.','.(int) $canSwitch.','.(int) PulseHotelContext::emp().',"'.pSQL($now).'","'.pSQL($now).'")
            ON DUPLICATE KEY UPDATE is_default = VALUES(is_default), can_switch = VALUES(can_switch), date_upd = VALUES(date_upd)');
        if ($isDefault) { self::clearOtherDefaults($idEmployee, $idHotel); }
        PulseCoreService::audit('pulsehotel', 'access_grant', array('id_employee' => (int) $idEmployee, 'id_hotel' => (int) $idHotel), 'employee', (int) $idEmployee);
        PulseHotelContext::reset();
        return true;
    }

    public static function revoke($idEmployee, $idHotel)
    {
        Db::getInstance()->delete('pulse_hotel_access', 'id_employee = '.(int) $idEmployee.' AND id_hotel = '.(int) $idHotel);
        PulseCoreService::audit('pulsehotel', 'access_revoke', array('id_employee' => (int) $idEmployee, 'id_hotel' => (int) $idHotel), 'employee', (int) $idEmployee);
        PulseHotelContext::reset();
        return true;
    }

    protected static function clearOtherDefaults($idEmployee, $keep)
    {
        return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hotel_access` SET is_default = 0
            WHERE id_employee = '.(int) $idEmployee.' AND id_hotel <> '.(int) $keep);
    }

    /** Give one employee exactly this set of hotels, in one action. */
    public static function setAll($idEmployee, array $idHotels, $default = 0, $canSwitch = 1)
    {
        $idHotels = array_values(array_unique(array_map('intval', $idHotels)));
        Db::getInstance()->delete('pulse_hotel_access', 'id_employee = '.(int) $idEmployee
            .($idHotels ? ' AND id_hotel NOT IN ('.implode(',', $idHotels).')' : ''));
        foreach ($idHotels as $h) { self::grant($idEmployee, $h, (int) $default === $h ? 1 : 0, $canSwitch); }
        return true;
    }

    public static function recentActivity($limit = 40)
    {
        return Db::getInstance()->executeS('SELECT s.*, CONCAT(e.firstname," ",e.lastname) AS who, hbl.hotel_name
            FROM `'._DB_PREFIX_.'pulse_hotel_session` s
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee = s.id_employee
            LEFT JOIN `'._DB_PREFIX_.'htl_branch_info_lang` hbl ON hbl.id = s.id_hotel AND hbl.id_lang = '.(int) Context::getContext()->language->id.'
            ORDER BY s.id_pulse_hotel_session DESC LIMIT '.(int) $limit);
    }
}
