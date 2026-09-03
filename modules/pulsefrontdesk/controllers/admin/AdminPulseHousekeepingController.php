<?php
/** Housekeeping queue: assign, start, complete tasks; mark rooms OOO/OOS; attendant view (?mine=1). */
class AdminPulseHousekeepingController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Housekeeping'); }

    public function initContent()
    {
        parent::initContent();
        $mine = (bool) Tools::getValue('mine');
        $this->context->smarty->assign(array(
            'tasks' => PulseHousekeeping::queue(Tools::getValue('status', 'open,in_progress'), $mine ? (int) $this->context->employee->id : null),
            'done_today' => PulseHousekeeping::queue('done,skipped'),
            'board' => PulseRoom::board(), 'attendants' => Employee::getEmployees(), 'hk_statuses' => PulseRoom::HK_STATUSES,
            'business_date' => PulseCoreService::businessDate(), 'self_url' => self::$currentIndex.'&token='.$this->token, 'ajax_url' => $this->context->link->getAdminLink('AdminPulseRoomBoard'), 'mine' => $mine,
        ));
        $this->setTemplate('queue.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('addTask')) { PulseHousekeeping::createTask((int) Tools::getValue('id_room'), Tools::getValue('type'), (int) Tools::getValue('priority', 5), Tools::getValue('note'), (int) Tools::getValue('assigned_to') ?: null); $this->confirmations[] = $this->l('Task added'); }
            if (Tools::isSubmit('assign')) { Db::getInstance()->update('pulse_housekeeping_task', array('assigned_to' => (int) Tools::getValue('assigned_to')), 'id_pulse_housekeeping_task='.(int) Tools::getValue('id_task')); }
            if (Tools::isSubmit('setStatus')) { PulseHousekeeping::setStatus((int) Tools::getValue('id_task'), Tools::getValue('status')); }
            if (Tools::isSubmit('bulkAssign')) { foreach ((array) Tools::getValue('task_ids') as $id) { Db::getInstance()->update('pulse_housekeeping_task', array('assigned_to' => (int) Tools::getValue('assigned_to')), 'id_pulse_housekeeping_task='.(int) $id); } }
            if (Tools::isSubmit('setHk')) { PulseRoom::setHkStatus((int) Tools::getValue('id_room'), Tools::getValue('hk_status'), 'housekeeping', Tools::getValue('reason'), Tools::getValue('until')); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
