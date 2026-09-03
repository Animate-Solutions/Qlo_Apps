<?php
/** Guest service / maintenance / complaint tickets with SLA and department routing. */
class PulseTicket
{
    protected static $sla = array('urgent' => 30, 'high' => 120, 'normal' => 480, 'low' => 1440); // minutes

    public static function create(array $d)
    {
        $seq = (int) PulseCoreService::setting('pulsefrontdesk', 'ticket_seq') + 1; PulseCoreService::setting('pulsefrontdesk', 'ticket_seq', $seq);
        $prio = isset($d['priority']) ? $d['priority'] : 'normal';
        Db::getInstance()->insert('pulse_ticket', array(
            'ticket_no' => 'T'.date('ym').str_pad($seq, 4, '0', STR_PAD_LEFT), 'category' => pSQL($d['category']), 'department' => pSQL($d['department']),
            'id_room' => !empty($d['id_room']) ? (int) $d['id_room'] : null, 'id_htl_booking' => !empty($d['id_htl_booking']) ? (int) $d['id_htl_booking'] : null, 'id_customer' => !empty($d['id_customer']) ? (int) $d['id_customer'] : null,
            'title' => pSQL($d['title']), 'description' => pSQL(isset($d['description']) ? $d['description'] : '', true), 'priority' => pSQL($prio),
            'assigned_to' => !empty($d['assigned_to']) ? (int) $d['assigned_to'] : null, 'status' => !empty($d['assigned_to']) ? 'assigned' : 'open',
            'sla_due' => date('Y-m-d H:i:s', time() + self::$sla[$prio] * 60), 'source' => pSQL(isset($d['source']) ? $d['source'] : 'desk'),
            'id_employee' => isset(Context::getContext()->employee) ? (int) Context::getContext()->employee->id : 0, 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        if (in_array($d['category'], array('maintenance', 'housekeeping')) && !empty($d['id_room'])) { PulseHousekeeping::createTask($d['id_room'], $d['category'] === 'maintenance' ? 'maintenance' : 'clean', $prio === 'urgent' ? 1 : 4, 'Ticket '.$d['title'], !empty($d['assigned_to']) ? $d['assigned_to'] : null); }
        PulseCoreService::event('actionPulseTicketCreated', array('id_ticket' => $id, 'department' => $d['department'], 'priority' => $prio));
        return $id;
    }
    public static function update($id, array $d, $note = null)
    {
        $u = array('date_upd' => date('Y-m-d H:i:s'));
        foreach (array('status', 'priority', 'department', 'resolution') as $k) { if (isset($d[$k])) { $u[$k] = pSQL($d[$k]); } }
        if (isset($d['assigned_to'])) { $u['assigned_to'] = (int) $d['assigned_to'] ?: null; if ($d['assigned_to'] && empty($d['status'])) { $u['status'] = 'assigned'; } }
        if (isset($d['status']) && in_array($d['status'], array('resolved', 'closed'))) { $u['date_resolved'] = date('Y-m-d H:i:s'); }
        Db::getInstance()->update('pulse_ticket', $u, 'id_pulse_ticket='.(int) $id);
        if ($note) { Db::getInstance()->insert('pulse_ticket_note', array('id_pulse_ticket' => (int) $id, 'note' => pSQL($note, true), 'id_employee' => (int) Context::getContext()->employee->id, 'date_add' => date('Y-m-d H:i:s'))); }
        return true;
    }
    public static function list_($status = 'open,assigned,in_progress,reopened', $department = null, $assignedTo = null)
    {
        return Db::getInstance()->executeS('SELECT t.*, r.room_num, CONCAT(e.firstname," ",e.lastname) assignee, (t.sla_due<NOW() AND t.status NOT IN ("resolved","closed")) overdue FROM `'._DB_PREFIX_.'pulse_ticket` t LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=t.id_room LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=t.assigned_to
            WHERE t.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'")'.($department ? ' AND t.department="'.pSQL($department).'"' : '').($assignedTo ? ' AND t.assigned_to='.(int) $assignedTo : '').' ORDER BY FIELD(t.priority,"urgent","high","normal","low"), t.sla_due');
    }
    public static function notes($id) { return Db::getInstance()->executeS('SELECT n.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_ticket_note` n LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=n.id_employee WHERE n.id_pulse_ticket='.(int) $id.' ORDER BY n.date_add'); }
}
