<?php
/** /pulse/api/maintenance/{resource}/{id} — technician phones (scope engineering) and TV portal (scope portal). */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulsemaintenance/classes/autoload.php';
class PulseMaintenanceApiModuleFrontController extends PulseApiController
{
    protected $resources = array('ping' => 'ping', 'my_orders' => 'myOrders', 'order' => 'order', 'status' => 'status', 'note' => 'note', 'part' => 'part', 'report' => 'report', 'meter' => 'meter');
    protected function ping() { return array('module' => 'pulsemaintenance'); }
    protected function myOrders() { $this->requireScope('engineering'); return PulseMaintenanceService::queue('open,assigned,in_progress,on_hold', (int) Tools::getValue('tech') ?: null); }
    protected function order($id) { $this->requireScope('engineering'); return PulseMaintenanceService::wo($id); }
    protected function status($id, $b) { $this->requireScope('engineering'); PulseMaintenanceService::setStatus($id, $b['status'], $b); return array('id' => $id, 'status' => $b['status']); }
    protected function note($id, $b) { $this->requireScope('engineering'); PulseMaintenanceService::note($id, $b['note'], isset($b['photo']) ? $b['photo'] : null); return array('ok' => 1); }
    protected function part($id, $b) { $this->requireScope('engineering'); PulseMaintenanceService::issuePart($id, (int) $b['id_part'], (float) $b['qty']); return array('ok' => 1); }
    /** Guest/TV portal or staff phone reports a fault in a room. */
    protected function report($idRoom, $b) { $id = PulseMaintenanceService::createWo(array('id_room' => $idRoom, 'priority' => isset($b['priority']) ? $b['priority'] : 'normal', 'category' => isset($b['category']) ? $b['category'] : 'other', 'subject' => $b['subject'], 'description' => isset($b['body']) ? $b['body'] : '', 'source' => 'portal')); return array('id_wo' => $id); }
    protected function meter($id, $b) { $this->requireScope('engineering'); Db::getInstance()->insert('pulse_meter_reading', array('meter' => pSQL($b['meter']), 'id_pulse_asset' => $id ?: null, 'reading' => (float) $b['reading'], 'cost' => isset($b['cost']) ? (float) $b['cost'] : null, 'read_at' => date('Y-m-d H:i:s'))); return array('ok' => 1); }
}
