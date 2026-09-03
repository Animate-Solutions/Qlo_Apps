<?php
/** Traces, guest messages, wake-up calls and alerts (OPERA "Traces" / eZee "Reminders"). */
class PulseTrace
{
    public static function add($type, $text, $dueAt, $idBooking = null, $idRoom = null, $idCustomer = null, $department = 'frontdesk')
    {
        Db::getInstance()->insert('pulse_trace', array(
            'type' => pSQL($type), 'text' => pSQL($text), 'due_at' => pSQL($dueAt), 'department' => pSQL($department),
            'id_htl_booking' => $idBooking ? (int) $idBooking : null, 'id_room' => $idRoom ? (int) $idRoom : null, 'id_customer' => $idCustomer ? (int) $idCustomer : null,
            'id_employee' => (int) Context::getContext()->employee->id, 'date_add' => date('Y-m-d H:i:s'),
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        if ($type === 'wake_up' && $idRoom && ($pabx = PulsePabx::driver()) && Configuration::get('PULSE_FD_PABX_URL')) { $pabx->setWakeUp(PulsePabx::extensionForRoom($idRoom), new DateTime($dueAt)); }
        return $id;
    }
    public static function resolve($id, $status = 'done')
    {
        return Db::getInstance()->update('pulse_trace', array('status' => pSQL($status), 'resolved_by' => (int) Context::getContext()->employee->id, 'date_resolved' => date('Y-m-d H:i:s')), 'id_pulse_trace='.(int) $id);
    }
    public static function due($hoursAhead = 24, $department = null)
    {
        return Db::getInstance()->executeS('SELECT t.*, r.room_num FROM `'._DB_PREFIX_.'pulse_trace` t LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=t.id_room
            WHERE t.status="open" AND t.due_at <= DATE_ADD(NOW(), INTERVAL '.(int) $hoursAhead.' HOUR)'.($department ? ' AND t.department="'.pSQL($department).'"' : '').' ORDER BY t.due_at');
    }
}
