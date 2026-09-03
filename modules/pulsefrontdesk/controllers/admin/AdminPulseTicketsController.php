<?php
class AdminPulseTicketsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Guest Services & Tickets'); }
    public function initContent()
    {
        parent::initContent();
        $view = (int) Tools::getValue('id_ticket');
        $this->context->smarty->assign(array('tickets' => PulseTicket::list_(Tools::getValue('status', 'open,assigned,in_progress,reopened'), Tools::getValue('department') ?: null, Tools::getValue('mine') ? (int) $this->context->employee->id : null),
            'closed' => PulseTicket::list_('resolved,closed'), 'employees' => Employee::getEmployees(), 'rooms' => Db::getInstance()->executeS('SELECT id, room_num FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_status IN (1,3) ORDER BY room_num'),
            'ticket' => $view ? Db::getInstance()->getRow('SELECT t.*, r.room_num FROM `'._DB_PREFIX_.'pulse_ticket` t LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=t.id_room WHERE t.id_pulse_ticket='.$view) : null, 'notes' => $view ? PulseTicket::notes($view) : array(),
            'self_url' => self::$currentIndex.'&token='.$this->token, 'mine' => (bool) Tools::getValue('mine')));
        $this->setTemplate('tickets.tpl');
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('addTicket')) { $id = PulseTicket::create(array('category' => Tools::getValue('category'), 'department' => Tools::getValue('department'), 'id_room' => Tools::getValue('id_room'), 'title' => Tools::getValue('title'), 'description' => Tools::getValue('description'), 'priority' => Tools::getValue('priority'), 'assigned_to' => Tools::getValue('assigned_to'))); $this->confirmations[] = $this->l('Ticket created'); }
            if (Tools::isSubmit('updTicket')) {
                $id = (int) Tools::getValue('id_ticket'); PulseTicket::update($id, array('status' => Tools::getValue('status'), 'priority' => Tools::getValue('priority'), 'department' => Tools::getValue('department'), 'assigned_to' => Tools::getValue('assigned_to'), 'resolution' => Tools::getValue('resolution')), Tools::getValue('note'));
                $t = Db::getInstance()->getRow('SELECT id_customer, id_htl_booking, title, status FROM `'._DB_PREFIX_.'pulse_ticket` WHERE id_pulse_ticket='.$id);
                if ($t['id_customer'] && Tools::getValue('notify')) { PulseComms::send('ticket_update', new Customer((int) $t['id_customer']), array('id_htl_booking' => $t['id_htl_booking'], 'title' => $t['title'], 'status' => $t['status'])); }
                $this->confirmations[] = $this->l('Ticket updated');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
