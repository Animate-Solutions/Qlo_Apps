<?php
/** Service recovery: the open glitches, what was given away to fix them, and what that cost by department. */
class AdminPulseCrmCasesController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Service Recovery'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        $from = Tools::getValue('from', date('Y-m-01')); $to = Tools::getValue('to', PulseCrmService::bd());
        if ($id = (int) Tools::getValue('id_case')) {
            $this->context->smarty->assign(array('c' => PulseCrmCase::get($id), 'employees' => Employee::getEmployees(), 'self_url' => $self, 'fd' => PulseCrmService::fd()));
            return $this->setTemplate('case.tpl');
        }
        $this->context->smarty->assign(array(
            'open' => PulseCrmCase::all('open,investigating,recovering,escalated', Tools::getValue('dept') ?: null),
            'closed' => PulseCrmCase::all('closed', Tools::getValue('dept') ?: null, $from, $to),
            'cost' => PulseCrmCase::costReport($from, $to), 'causes' => PulseCrmCase::rootCauses($from, $to),
            'overdue' => PulseCrmCase::overdue(), 'from' => $from, 'to' => $to, 'dept' => Tools::getValue('dept'),
            'employees' => Employee::getEmployees(), 'self_url' => $self, 'fd' => PulseCrmService::fd(),
        ));
        $this->setTemplate('cases.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('openCase')) {
                $idc = (int) Tools::getValue('id_customer');
                if (!$idc && Tools::getValue('guest_email')) { $idc = (int) Db::getInstance()->getValue('SELECT id_customer FROM `'._DB_PREFIX_.'customer` WHERE email="'.pSQL(Tools::getValue('guest_email')).'" AND deleted=0'); }
                $id = PulseCrmCase::open(array('source' => 'staff', 'severity' => Tools::getValue('severity', 'medium'), 'department' => Tools::getValue('department', 'frontdesk'),
                    'id_customer' => $idc ?: null, 'id_htl_booking' => (int) Tools::getValue('id_htl_booking') ?: null, 'id_room' => (int) Tools::getValue('id_room') ?: null,
                    'title' => Tools::getValue('title'), 'description' => Tools::getValue('description'), 'owner' => (int) Tools::getValue('owner') ?: null));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_case='.$id.'&conf=3');
            }
            if (Tools::isSubmit('updateCase')) {
                PulseCrmCase::update((int) Tools::getValue('id_case_a'), array('status' => Tools::getValue('status'), 'severity' => Tools::getValue('severity'),
                    'department' => Tools::getValue('department'), 'root_cause' => Tools::getValue('root_cause'), 'recovery_action' => Tools::getValue('recovery_action'),
                    'recovery_detail' => Tools::getValue('recovery_detail'), 'recovery_cost' => Tools::getValue('recovery_cost'), 'owner' => Tools::getValue('owner'),
                    'closing_note' => Tools::getValue('closing_note')));
                $this->confirmations[] = $this->l('Case updated');
            }
            if (Tools::isSubmit('postRecovery')) { $line = PulseCrmCase::postRecovery((int) Tools::getValue('id_case_a'), Tools::getValue('charge_code', 'ADJ')); $this->confirmations[] = $this->l('Posted to the folio as line ').$line; }
            if (Tools::isSubmit('apologise')) { $r = PulseCrmCase::apologise((int) Tools::getValue('id_case_a'), Tools::getValue('apology')); $this->confirmations[] = $r['ok'] ? $this->l('Apology sent') : $this->l('Not sent: ').$r['reason']; }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
