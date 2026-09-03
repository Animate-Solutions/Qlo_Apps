<?php
class PulseHousekeeping
{
    public static function createTask($idRoom, $type = 'clean', $priority = 5, $note = '', $assignedTo = null)
    {
        Db::getInstance()->insert('pulse_housekeeping_task', array(
            'id_room' => (int) $idRoom, 'type' => pSQL($type), 'priority' => (int) $priority, 'note' => pSQL($note),
            'assigned_to' => $assignedTo ? (int) $assignedTo : null, 'business_date' => PulseCoreService::businessDate(), 'date_add' => date('Y-m-d H:i:s'),
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::event('actionPulseHousekeepingTask', array('id_task' => $id, 'id_room' => $idRoom, 'type' => $type));
        return $id;
    }

    public static function setStatus($idTask, $status)
    {
        $t = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_housekeeping_task` WHERE id_pulse_housekeeping_task='.(int) $idTask);
        if (!$t) { return false; }
        Db::getInstance()->update('pulse_housekeeping_task', array('status' => pSQL($status), 'date_done' => in_array($status, array('done', 'skipped')) ? date('Y-m-d H:i:s') : null), 'id_pulse_housekeeping_task='.(int) $idTask);
        if ($status === 'done' && in_array($t['type'], array('clean', 'deep_clean'))) {
            $occupied = (bool) Db::getInstance()->getValue('SELECT fo_status="occupied" FROM `'._DB_PREFIX_.'pulse_room_status` WHERE id_room='.(int) $t['id_room']);
            PulseRoom::setHkStatus($t['id_room'], $occupied ? 'occupied_clean' : 'vacant_clean', 'housekeeping');
            if (!$occupied && PulseCoreService::setting('pulsefrontdesk', 'require_inspection') === '1') {
                self::createTask($t['id_room'], 'inspect', 4, 'Post-clean inspection');
            }
        }
        if ($status === 'done' && $t['type'] === 'inspect') {
            PulseRoom::setHkStatus($t['id_room'], 'vacant_inspected', 'housekeeping');
        }
        return true;
    }

    /** Nightly: every occupied room gets a daily clean task; stayovers get turndown if enabled. */
    public static function generateDailyTasks($businessDate)
    {
        $n = 0;
        foreach (PulseFdService::inHouse() as $b) {
            if (!Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_housekeeping_task` WHERE id_room='.(int) $b['id_room'].' AND type="clean" AND business_date="'.pSQL($businessDate).'"')) {
                self::createTask($b['id_room'], 'clean', $b['date_to'] === $businessDate ? 3 : 5, $b['date_to'] === $businessDate ? 'Departure today' : 'Stayover');
                $n++;
            }
        }
        return $n;
    }

    public static function queue($status = 'open,in_progress', $assignedTo = null)
    {
        $st = "'".implode("','", array_map('pSQL', explode(',', $status)))."'";
        return Db::getInstance()->executeS('SELECT t.*, r.room_num, r.floor, s.hk_status, s.fo_status, CONCAT(e.firstname," ",e.lastname) assignee
            FROM `'._DB_PREFIX_.'pulse_housekeeping_task` t
            INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=t.id_room
            LEFT JOIN `'._DB_PREFIX_.'pulse_room_status` s ON s.id_room=t.id_room
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=t.assigned_to
            WHERE t.status IN ('.$st.')'.($assignedTo ? ' AND t.assigned_to='.(int) $assignedTo : '').'
            ORDER BY t.priority, r.floor, r.room_num');
    }
}
