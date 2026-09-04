<?php
/** Work orders, assets, preventive maintenance, parts, meters. Integrates with Front Desk tickets/OOO when present. */
class PulseMaintenanceService
{
    const SLA = array('emergency' => 2, 'high' => 8, 'normal' => 24, 'low' => 72);
    protected static function emp() { $c = Context::getContext(); return isset($c->employee) ? (int) $c->employee->id : 0; }
    protected static function nextNo($p) { $n = (int) PulseCoreService::setting('pulsemaintenance', 'seq_'.$p) + 1; PulseCoreService::setting('pulsemaintenance', 'seq_'.$p, $n); return $p.date('ym').str_pad($n % 100000, 5, '0', STR_PAD_LEFT); }
    public static function fd() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseRoom'); }

    /* ---------- work orders ---------- */
    public static function createWo(array $d)
    {
        $prio = isset($d['priority']) ? $d['priority'] : 'normal'; $sla = isset($d['sla_hours']) ? (int) $d['sla_hours'] : self::SLA[$prio];
        if (!empty($d['id_pulse_asset']) && empty($d['id_room'])) { $d['id_room'] = Db::getInstance()->getValue('SELECT id_room FROM `'._DB_PREFIX_.'pulse_asset` WHERE id_pulse_asset='.(int) $d['id_pulse_asset']); }
        Db::getInstance()->insert('pulse_work_order', array(
            'wo_no' => self::nextNo('WO'), 'type' => pSQL(isset($d['type']) ? $d['type'] : 'corrective'), 'category' => pSQL(isset($d['category']) ? $d['category'] : 'other'),
            'id_pulse_asset' => !empty($d['id_pulse_asset']) ? (int) $d['id_pulse_asset'] : null, 'id_room' => !empty($d['id_room']) ? (int) $d['id_room'] : null, 'location' => pSQL(isset($d['location']) ? $d['location'] : ''),
            'priority' => pSQL($prio), 'sla_hours' => $sla, 'due_at' => date('Y-m-d H:i:s', time() + $sla * 3600), 'subject' => pSQL($d['subject']), 'description' => pSQL(isset($d['description']) ? $d['description'] : '', true),
            'status' => !empty($d['assigned_to']) ? 'assigned' : 'open', 'assigned_to' => !empty($d['assigned_to']) ? (int) $d['assigned_to'] : null, 'room_ooo' => !empty($d['room_ooo']) ? 1 : 0,
            'source' => pSQL(isset($d['source']) ? $d['source'] : 'manual'), 'source_ref' => pSQL(isset($d['source_ref']) ? $d['source_ref'] : ''), 'id_pulse_ticket' => !empty($d['id_pulse_ticket']) ? (int) $d['id_pulse_ticket'] : null, 'id_pulse_pm_schedule' => !empty($d['id_pulse_pm_schedule']) ? (int) $d['id_pulse_pm_schedule'] : null,
            'reported_by' => self::emp(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        if (!empty($d['room_ooo']) && !empty($d['id_room']) && self::fd()) { PulseRoom::setHkStatus((int) $d['id_room'], 'out_of_order', 'maintenance', 'WO '.$id.': '.$d['subject'], date('Y-m-d', time() + $sla * 3600)); }
        if (!empty($d['id_pulse_asset']) && !empty($d['asset_oos'])) { Db::getInstance()->update('pulse_asset', array('status' => 'out_of_service'), 'id_pulse_asset='.(int) $d['id_pulse_asset']); }
        PulseCoreService::audit('pulsemaintenance', 'wo_create', array('subject' => $d['subject'], 'priority' => $prio), 'pulse_work_order', $id);
        PulseCoreService::event('actionPulseWorkOrder', array('id_wo' => $id, 'priority' => $prio, 'id_room' => isset($d['id_room']) ? $d['id_room'] : null));
        if ($prio === 'emergency' && class_exists('PulseComms') && Configuration::get('PULSE_MNT_ALERT_PHONE')) { PulseComms::sendRaw(Configuration::get('PULSE_MNT_ALERT_EMAIL'), Configuration::get('PULSE_MNT_ALERT_PHONE'), 'maintenance_emergency', array('subject' => $d['subject'])); }
        return $id;
    }

    public static function setStatus($id, $status, array $x = array())
    {
        $wo = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_work_order` WHERE id_pulse_work_order='.(int) $id);
        if (!$wo) { return false; }
        $u = array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s'));
        if ($status === 'in_progress' && !$wo['date_started']) { $u['date_started'] = date('Y-m-d H:i:s'); }
        if ($status === 'on_hold') { $u['hold_reason'] = pSQL(isset($x['reason']) ? $x['reason'] : ''); }
        if ($status === 'completed') { $u['date_completed'] = date('Y-m-d H:i:s'); $u['resolution'] = pSQL(isset($x['resolution']) ? $x['resolution'] : '', true); $u['root_cause'] = pSQL(isset($x['root_cause']) ? $x['root_cause'] : ''); $u['labour_minutes'] = (int) (isset($x['labour_minutes']) ? $x['labour_minutes'] : 0); $u['labour_cost'] = round(((int) $u['labour_minutes'] / 60) * (float) Configuration::get('PULSE_MNT_LABOUR_RATE'), 2); $u['vendor_cost'] = (float) (isset($x['vendor_cost']) ? $x['vendor_cost'] : 0); }
        if ($status === 'verified') { $u['verified_by'] = self::emp(); }
        Db::getInstance()->update('pulse_work_order', $u, 'id_pulse_work_order='.(int) $id);
        $releaseAt = Configuration::get('PULSE_MNT_RELEASE_ROOM_AT') ?: 'completed';
        if ($status === $releaseAt && $wo['room_ooo'] && $wo['id_room'] && self::fd()) { PulseRoom::setHkStatus((int) $wo['id_room'], 'vacant_dirty', 'maintenance'); if (class_exists('PulseHousekeeping')) { PulseHousekeeping::createTask((int) $wo['id_room'], 'clean', 3, 'Post-maintenance clean & inspect'); } }
        if (in_array($status, array('completed', 'verified')) && $wo['id_pulse_asset']) { Db::getInstance()->update('pulse_asset', array('status' => 'in_service', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_asset='.(int) $wo['id_pulse_asset'].' AND status="out_of_service"'); }
        if ($status === 'completed' && $wo['id_pulse_ticket'] && class_exists('PulseTicket')) { PulseTicket::update((int) $wo['id_pulse_ticket'], array('status' => 'resolved', 'resolution' => 'Work order '.$wo['wo_no'].' completed'), isset($x['resolution']) ? $x['resolution'] : 'Work order completed'); }
        if ($status === 'completed' && $wo['id_pulse_pm_schedule']) { self::pmAdvance((int) $wo['id_pulse_pm_schedule'], (int) $wo['id_room']); }
        if ($status === 'completed' && $wo['id_room'] && $wo['id_pulse_asset'] === null && class_exists('PulseTrace') && $wo['source'] === 'portal') { PulseTrace::add('message', 'Maintenance completed in your room: '.$wo['subject'], date('Y-m-d H:i:s'), null, (int) $wo['id_room'], null, 'frontdesk'); }
        PulseCoreService::event('actionPulseWorkOrderStatus', array('id_wo' => $id, 'status' => $status, 'id_room' => $wo['id_room']));
        return true;
    }

    public static function assign($id, $idEmployee) { return Db::getInstance()->update('pulse_work_order', array('assigned_to' => (int) $idEmployee, 'status' => 'assigned', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_work_order='.(int) $id.' AND status IN ("open","assigned")'); }
    public static function note($id, $text, $photo = null) { Db::getInstance()->insert('pulse_work_order_note', array('id_pulse_work_order' => (int) $id, 'note' => pSQL($text, true), 'photo_path' => pSQL($photo), 'id_employee' => self::emp(), 'date_add' => date('Y-m-d H:i:s'))); }

    public static function issuePart($idWo, $idPart, $qty)
    {
        $p = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_part` WHERE id_pulse_part='.(int) $idPart);
        if (!$p || $p['qty_on_hand'] < $qty) { throw new PrestaShopException('Insufficient stock for '.($p ? $p['name'] : 'part')); }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_part` SET qty_on_hand=qty_on_hand-'.(float) $qty.' WHERE id_pulse_part='.(int) $idPart);
        Db::getInstance()->insert('pulse_part_movement', array('id_pulse_part' => (int) $idPart, 'type' => 'issue', 'qty' => (float) $qty, 'id_pulse_work_order' => (int) $idWo, 'unit_cost' => (float) $p['unit_cost'], 'id_employee' => self::emp(), 'date_add' => date('Y-m-d H:i:s')));
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_work_order` SET parts_cost=parts_cost+'.round($qty * $p['unit_cost'], 2).' WHERE id_pulse_work_order='.(int) $idWo);
        return true;
    }
    public static function partMove($idPart, $type, $qty, $note = '', $unitCost = null)
    {
        $sign = in_array($type, array('receive', 'return')) ? 1 : ($type === 'adjust' ? 0 : -1);
        if ($type === 'adjust') { Db::getInstance()->update('pulse_part', array('qty_on_hand' => (float) $qty), 'id_pulse_part='.(int) $idPart); } else { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_part` SET qty_on_hand=qty_on_hand+'.($sign * (float) $qty).($unitCost !== null && $type === 'receive' ? ', unit_cost='.(float) $unitCost : '').' WHERE id_pulse_part='.(int) $idPart); }
        Db::getInstance()->insert('pulse_part_movement', array('id_pulse_part' => (int) $idPart, 'type' => pSQL($type), 'qty' => (float) $qty, 'unit_cost' => $unitCost !== null ? (float) $unitCost : null, 'note' => pSQL($note), 'id_employee' => self::emp(), 'date_add' => date('Y-m-d H:i:s')));
    }

    public static function queue($status = 'open,assigned,in_progress,on_hold', $tech = null)
    {
        return Db::getInstance()->executeS('SELECT w.*, r.room_num, a.name asset_name, a.code asset_code, CONCAT(e.firstname," ",e.lastname) technician, (w.due_at<NOW() AND w.status NOT IN ("completed","verified","cancelled")) overdue
            FROM `'._DB_PREFIX_.'pulse_work_order` w LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=w.id_room LEFT JOIN `'._DB_PREFIX_.'pulse_asset` a ON a.id_pulse_asset=w.id_pulse_asset LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=w.assigned_to
            WHERE w.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'")'.($tech ? ' AND w.assigned_to='.(int) $tech : '').' ORDER BY FIELD(w.priority,"emergency","high","normal","low"), w.due_at');
    }
    public static function wo($id)
    {
        $w = Db::getInstance()->getRow('SELECT w.*, r.room_num, a.name asset_name, a.code asset_code, CONCAT(e.firstname," ",e.lastname) technician, CONCAT(rp.firstname," ",rp.lastname) reporter, s.checklist FROM `'._DB_PREFIX_.'pulse_work_order` w LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=w.id_room LEFT JOIN `'._DB_PREFIX_.'pulse_asset` a ON a.id_pulse_asset=w.id_pulse_asset LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=w.assigned_to LEFT JOIN `'._DB_PREFIX_.'employee` rp ON rp.id_employee=w.reported_by LEFT JOIN `'._DB_PREFIX_.'pulse_pm_schedule` s ON s.id_pulse_pm_schedule=w.id_pulse_pm_schedule WHERE w.id_pulse_work_order='.(int) $id);
        if ($w) { $w['notes'] = Db::getInstance()->executeS('SELECT n.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_work_order_note` n LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=n.id_employee WHERE n.id_pulse_work_order='.(int) $id.' ORDER BY n.date_add'); $w['parts'] = Db::getInstance()->executeS('SELECT m.*, p.name, p.sku FROM `'._DB_PREFIX_.'pulse_part_movement` m INNER JOIN `'._DB_PREFIX_.'pulse_part` p ON p.id_pulse_part=m.id_pulse_part WHERE m.id_pulse_work_order='.(int) $id); }
        return $w;
    }

    /* ---------- preventive maintenance ---------- */
    /** Generate today's PM work orders. Run daily (cron or from the screen). */
    public static function pmGenerate($date = null)
    {
        $date = $date ?: date('Y-m-d'); $n = 0;
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pm_schedule` WHERE active=1 AND next_due<="'.pSQL($date).'"') as $s) {
            if ($s['scope'] === 'all_rooms' || $s['scope'] === 'room_type') {
                // rolling programme: pick the rooms least recently done, skipping occupied rooms when Front Desk knows
                $rooms = Db::getInstance()->executeS('SELECT r.id, r.room_num FROM `'._DB_PREFIX_.'htl_room_information` r LEFT JOIN `'._DB_PREFIX_.'pulse_pm_room_cursor` c ON c.id_pulse_pm_schedule='.(int) $s['id_pulse_pm_schedule'].' AND c.id_room=r.id '.(self::fd() ? 'LEFT JOIN `'._DB_PREFIX_.'pulse_room_status` st ON st.id_room=r.id ' : '').'
                    WHERE r.id_status=1'.($s['scope'] === 'room_type' ? ' AND r.id_product='.(int) $s['id_product'] : '').(self::fd() ? ' AND (st.fo_status IS NULL OR st.fo_status="vacant")' : '').' AND (c.last_done IS NULL OR c.last_done<DATE_SUB("'.pSQL($date).'", INTERVAL '.(int) $s['interval_days'].' DAY))
                      AND r.id NOT IN (SELECT id_room FROM `'._DB_PREFIX_.'pulse_work_order` WHERE id_pulse_pm_schedule='.(int) $s['id_pulse_pm_schedule'].' AND status NOT IN ("completed","verified","cancelled") AND id_room IS NOT NULL)
                    ORDER BY c.last_done IS NOT NULL, c.last_done LIMIT '.(int) $s['rooms_per_run']);
                foreach ($rooms as $r) { self::createWo(array('type' => 'preventive', 'category' => $s['category'], 'id_room' => $r['id'], 'priority' => $s['priority'], 'subject' => $s['name'].' — Room '.$r['room_num'], 'description' => $s['checklist'], 'assigned_to' => $s['assigned_to'], 'source' => 'pm', 'id_pulse_pm_schedule' => $s['id_pulse_pm_schedule'])); $n++; }
                Db::getInstance()->update('pulse_pm_schedule', array('next_due' => date('Y-m-d', strtotime($date.' +1 day')), 'last_run' => pSQL($date)), 'id_pulse_pm_schedule='.(int) $s['id_pulse_pm_schedule']);
            } else {
                $open = Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_work_order` WHERE id_pulse_pm_schedule='.(int) $s['id_pulse_pm_schedule'].' AND status NOT IN ("completed","verified","cancelled")');
                if (!$open) { self::createWo(array('type' => 'preventive', 'category' => $s['category'], 'id_pulse_asset' => $s['id_pulse_asset'], 'location' => $s['location'], 'priority' => $s['priority'], 'subject' => $s['name'], 'description' => $s['checklist'], 'assigned_to' => $s['assigned_to'], 'source' => 'pm', 'id_pulse_pm_schedule' => $s['id_pulse_pm_schedule'])); $n++; }
                Db::getInstance()->update('pulse_pm_schedule', array('next_due' => date('Y-m-d', strtotime($date.' +'.(int) $s['interval_days'].' days')), 'last_run' => pSQL($date)), 'id_pulse_pm_schedule='.(int) $s['id_pulse_pm_schedule']);
            }
        }
        return $n;
    }
    protected static function pmAdvance($idSchedule, $idRoom)
    {
        if ($idRoom) { Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_pm_room_cursor` (id_pulse_pm_schedule,id_room,last_done) VALUES ('.(int) $idSchedule.','.(int) $idRoom.',CURDATE()) ON DUPLICATE KEY UPDATE last_done=CURDATE()'); }
    }

    /* ---------- reports ---------- */
    public static function kpis($from, $to)
    {
        return Db::getInstance()->getRow('SELECT COUNT(*) total, SUM(status IN ("completed","verified")) closed, SUM(status NOT IN ("completed","verified","cancelled")) open_now, SUM(type="preventive") preventive, SUM(type="corrective") corrective,
            ROUND(AVG(IF(date_completed IS NOT NULL, TIMESTAMPDIFF(MINUTE,date_add,date_completed),NULL))/60,1) mttr_hours, SUM(IF(date_completed>due_at,1,0)) sla_breached, ROUND(SUM(labour_cost+parts_cost+vendor_cost),2) cost
            FROM `'._DB_PREFIX_.'pulse_work_order` WHERE date_add BETWEEN "'.pSQL($from).' 00:00:00" AND "'.pSQL($to).' 23:59:59"');
    }
    public static function byCategory($from, $to) { return Db::getInstance()->executeS('SELECT category, COUNT(*) n, ROUND(AVG(IF(date_completed IS NOT NULL, TIMESTAMPDIFF(MINUTE,date_add,date_completed),NULL))/60,1) mttr_h, ROUND(SUM(labour_cost+parts_cost+vendor_cost),2) cost FROM `'._DB_PREFIX_.'pulse_work_order` WHERE date_add BETWEEN "'.pSQL($from).' 00:00:00" AND "'.pSQL($to).' 23:59:59" GROUP BY category ORDER BY n DESC'); }
    public static function byAsset($from, $to) { return Db::getInstance()->executeS('SELECT a.code, a.name, a.category, COUNT(w.id_pulse_work_order) n, ROUND(SUM(w.labour_cost+w.parts_cost+w.vendor_cost),2) cost FROM `'._DB_PREFIX_.'pulse_work_order` w INNER JOIN `'._DB_PREFIX_.'pulse_asset` a ON a.id_pulse_asset=w.id_pulse_asset WHERE w.date_add BETWEEN "'.pSQL($from).' 00:00:00" AND "'.pSQL($to).' 23:59:59" GROUP BY a.id_pulse_asset ORDER BY n DESC LIMIT 20'); }
    public static function byRoom($from, $to) { return Db::getInstance()->executeS('SELECT r.room_num, COUNT(*) n, SUM(w.room_ooo) ooo_events, ROUND(SUM(w.labour_cost+w.parts_cost+w.vendor_cost),2) cost FROM `'._DB_PREFIX_.'pulse_work_order` w INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=w.id_room WHERE w.date_add BETWEEN "'.pSQL($from).' 00:00:00" AND "'.pSQL($to).' 23:59:59" GROUP BY w.id_room ORDER BY n DESC LIMIT 20'); }
    public static function technicians($from, $to) { return Db::getInstance()->executeS('SELECT CONCAT(e.firstname," ",e.lastname) technician, COUNT(*) n, SUM(w.status IN ("completed","verified")) done, ROUND(AVG(IF(w.date_completed IS NOT NULL, TIMESTAMPDIFF(MINUTE,w.date_add,w.date_completed),NULL))/60,1) mttr_h, SUM(w.labour_minutes) minutes FROM `'._DB_PREFIX_.'pulse_work_order` w INNER JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=w.assigned_to WHERE w.date_add BETWEEN "'.pSQL($from).' 00:00:00" AND "'.pSQL($to).' 23:59:59" GROUP BY w.assigned_to ORDER BY n DESC'); }
    public static function lowStock() { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_part` WHERE active=1 AND qty_on_hand<=reorder_level ORDER BY name'); }
    public static function meters($meter, $days = 30) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_meter_reading` WHERE meter="'.pSQL($meter).'" AND read_at>=DATE_SUB(NOW(), INTERVAL '.(int) $days.' DAY) ORDER BY read_at'); }
}
