<?php
class AdminPulseMaintenanceController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Maintenance'); }
    public function initContent()
    {
        parent::initContent();
        $common = array('self_url' => self::$currentIndex.'&token='.$this->token, 'techs' => Employee::getEmployees(), 'parts' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_part` WHERE active=1 ORDER BY name'), 'fd' => PulseMaintenanceService::fd());
        if ($id = (int) Tools::getValue('id_wo')) { $this->context->smarty->assign($common + array('w' => PulseMaintenanceService::wo($id))); return $this->setTemplate('wo.tpl'); }
        $from = Tools::getValue('from', date('Y-m-01')); $to = Tools::getValue('to', date('Y-m-d')); $mine = (bool) Tools::getValue('mine');
        $this->context->smarty->assign($common + array(
            'queue' => PulseMaintenanceService::queue('open,assigned,in_progress,on_hold', $mine ? (int) $this->context->employee->id : null), 'mine' => $mine,
            'recent' => PulseMaintenanceService::queue('completed,verified'), 'rooms' => Db::getInstance()->executeS('SELECT id id_room, room_num FROM `'._DB_PREFIX_.'htl_room_information` ORDER BY floor, room_num'), 'assets' => Db::getInstance()->executeS('SELECT id_pulse_asset, code, name FROM `'._DB_PREFIX_.'pulse_asset` WHERE status<>"retired" ORDER BY name'),
            'from' => $from, 'to' => $to, 'kpi' => PulseMaintenanceService::kpis($from, $to), 'by_cat' => PulseMaintenanceService::byCategory($from, $to), 'by_asset' => PulseMaintenanceService::byAsset($from, $to), 'by_room' => PulseMaintenanceService::byRoom($from, $to), 'by_tech' => PulseMaintenanceService::technicians($from, $to), 'low_stock' => PulseMaintenanceService::lowStock(),
            'categories' => array('hvac', 'electrical', 'plumbing', 'generator', 'kitchen', 'laundry', 'it', 'elevator', 'fire_safety', 'furniture', 'pool', 'vehicle', 'building', 'other'),
        ));
        $this->setTemplate('queue.tpl');
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('createWo')) { $id = PulseMaintenanceService::createWo(array('type' => Tools::getValue('type'), 'category' => Tools::getValue('category'), 'id_pulse_asset' => Tools::getValue('id_pulse_asset'), 'id_room' => Tools::getValue('id_room'), 'location' => Tools::getValue('location'), 'priority' => Tools::getValue('priority'), 'subject' => Tools::getValue('subject'), 'description' => Tools::getValue('description'), 'assigned_to' => Tools::getValue('assigned_to'), 'room_ooo' => Tools::getValue('room_ooo'), 'asset_oos' => Tools::getValue('asset_oos'))); Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_wo='.$id.'&conf=3'); }
            if (Tools::isSubmit('setStatus')) { PulseMaintenanceService::setStatus((int) Tools::getValue('id_wo_s'), Tools::getValue('status'), array('reason' => Tools::getValue('reason'), 'resolution' => Tools::getValue('resolution'), 'root_cause' => Tools::getValue('root_cause'), 'labour_minutes' => Tools::getValue('labour_minutes'), 'vendor_cost' => Tools::getValue('vendor_cost'))); $this->confirmations[] = $this->l('Work order updated'); }
            if (Tools::isSubmit('assignWo')) { PulseMaintenanceService::assign((int) Tools::getValue('id_wo_s'), (int) Tools::getValue('assigned_to')); $this->confirmations[] = $this->l('Assigned'); }
            if (Tools::isSubmit('bulkAssign')) { foreach ((array) Tools::getValue('wo_ids') as $i) { PulseMaintenanceService::assign((int) $i, (int) Tools::getValue('assigned_to')); } $this->confirmations[] = $this->l('Assigned'); }
            if (Tools::isSubmit('addNote')) { PulseMaintenanceService::note((int) Tools::getValue('id_wo_s'), Tools::getValue('note')); }
            if (Tools::isSubmit('issuePart')) { PulseMaintenanceService::issuePart((int) Tools::getValue('id_wo_s'), (int) Tools::getValue('id_part'), (float) Tools::getValue('qty')); $this->confirmations[] = $this->l('Part issued'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
