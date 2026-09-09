<?php
/** Two-way guest ↔ front desk chat. Threads are keyed on the stay, so a new guest in the room starts clean. */
class PulseGpMessaging
{
    const MAX = 1000;

    /** Guest sends. Empty or oversized bodies are refused; the desk gets an unread badge and a trace. */
    public static function fromGuest(array $device, array $session, $body)
    {
        $body = trim((string) $body);
        if ($body === '') { throw new PrestaShopException('Message is empty', 400); }
        if (Tools::strlen($body) > self::MAX) { $body = Tools::substr($body, 0, self::MAX); }
        Db::getInstance()->insert('pulse_gp_message', array('id_pulse_gp_device' => (int) $device['id_pulse_gp_device'], 'id_room' => $device['id_room'] ? (int) $device['id_room'] : null,
            'room_num' => pSQL($device['room_num']), 'id_htl_booking' => (int) $session['id_htl_booking'], 'id_customer' => (int) $session['id_customer'],
            'direction' => 'guest', 'body' => pSQL($body, true), 'read_by_guest' => 1, 'locale' => pSQL($session['locale']), 'business_date' => pSQL(PulseGpService::bd()), 'date_add' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID();
        if (class_exists('PulseTrace')) { PulseTrace::add('guest_request', 'Portal message from room '.$device['room_num'].': '.Tools::substr($body, 0, 120), date('Y-m-d H:i:s'), (int) $session['id_htl_booking'], (int) $device['id_room'], null, 'frontdesk'); }
        PulseGpService::event('actionPulsePortalMessage', array('id_message' => $id, 'direction' => 'guest', 'id_room' => (int) $device['id_room'], 'id_htl_booking' => (int) $session['id_htl_booking']));
        return $id;
    }

    /** Desk replies. Pushes a notify command so the TV badges the message straight away. */
    public static function fromDesk($idBooking, $idRoom, $body, $idEmployee = null)
    {
        $body = trim((string) $body);
        if ($body === '') { throw new PrestaShopException('Message is empty'); }
        $room = $idRoom ? Db::getInstance()->getValue('SELECT room_num FROM `'._DB_PREFIX_.'htl_room_information` WHERE id='.(int) $idRoom) : '';
        $cust = $idBooking ? (int) Db::getInstance()->getValue('SELECT id_customer FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id='.(int) $idBooking) : 0;
        Db::getInstance()->insert('pulse_gp_message', array('id_room' => $idRoom ? (int) $idRoom : null, 'room_num' => pSQL($room), 'id_htl_booking' => $idBooking ? (int) $idBooking : null,
            'id_customer' => $cust ? $cust : null, 'direction' => 'desk', 'body' => pSQL(Tools::substr($body, 0, self::MAX), true), 'id_employee' => (int) ($idEmployee === null ? PulseGpService::emp() : $idEmployee),
            'read_by_desk' => 1, 'business_date' => pSQL(PulseGpService::bd()), 'date_add' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID();
        foreach (PulseGpDevice::byRoom((int) $idRoom) as $d) { PulseGpDevice::command((int) $d['id_pulse_gp_device'], 'notify', array('kind' => 'message', 'text' => Tools::substr($body, 0, 140))); }
        PulseGpService::event('actionPulsePortalMessage', array('id_message' => $id, 'direction' => 'desk', 'id_room' => (int) $idRoom, 'id_htl_booking' => (int) $idBooking));
        return $id;
    }

    /** Broadcast from the desk to every occupied room (weather warning, generator switchover, breakfast times). */
    public static function broadcast($body, $idEmployee = null)
    {
        $n = 0;
        foreach (Db::getInstance()->executeS('SELECT DISTINCT d.id_room, b.id id_htl_booking FROM `'._DB_PREFIX_.'pulse_gp_device` d INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id_room=d.id_room AND b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND b.is_cancelled=0 WHERE d.status="active" AND d.id_room IS NOT NULL') as $r) {
            self::fromDesk((int) $r['id_htl_booking'], (int) $r['id_room'], $body, $idEmployee); $n++;
        }
        return $n;
    }

    public static function thread($idBooking, $limit = 50)
    {
        $rows = Db::getInstance()->executeS('SELECT m.id_pulse_gp_message id, m.direction, m.body, m.date_add, m.read_by_desk, m.read_by_guest, CONCAT(e.firstname," ",LEFT(e.lastname,1)) staff
            FROM `'._DB_PREFIX_.'pulse_gp_message` m LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=m.id_employee
            WHERE m.id_htl_booking='.(int) $idBooking.' ORDER BY m.id_pulse_gp_message DESC LIMIT '.(int) $limit);
        return array_reverse($rows);
    }
    public static function markReadByGuest($idBooking) { return Db::getInstance()->update('pulse_gp_message', array('read_by_guest' => 1), 'id_htl_booking='.(int) $idBooking.' AND direction="desk" AND read_by_guest=0'); }
    public static function markReadByDesk($idBooking) { return Db::getInstance()->update('pulse_gp_message', array('read_by_desk' => 1), 'id_htl_booking='.(int) $idBooking.' AND direction="guest" AND read_by_desk=0'); }
    public static function unreadForGuest($idBooking) { return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_gp_message` WHERE id_htl_booking='.(int) $idBooking.' AND direction="desk" AND read_by_guest=0'); }
    public static function unreadForDesk() { return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_gp_message` WHERE direction="guest" AND read_by_desk=0'); }

    /** Desk inbox: one row per stay with the last message and the unread count. */
    public static function inbox($onlyUnread = false)
    {
        return Db::getInstance()->executeS('SELECT m.id_htl_booking, m.id_room, MAX(m.room_num) room_num, MAX(m.date_add) last_at,
                SUM(m.direction="guest" AND m.read_by_desk=0) unread, COUNT(*) messages,
                SUBSTRING_INDEX(GROUP_CONCAT(m.body ORDER BY m.id_pulse_gp_message DESC SEPARATOR "\\n"),"\\n",1) last_body,
                MAX(CONCAT(c.firstname," ",c.lastname)) guest
            FROM `'._DB_PREFIX_.'pulse_gp_message` m LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=m.id_customer
            WHERE m.id_htl_booking IS NOT NULL GROUP BY m.id_htl_booking, m.id_room'.($onlyUnread ? ' HAVING unread>0' : '').' ORDER BY unread DESC, last_at DESC LIMIT 100');
    }
}
