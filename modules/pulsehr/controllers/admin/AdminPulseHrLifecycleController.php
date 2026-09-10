<?php
/** Onboarding and exit checklists, their templates, and the buttons that actually issue or revoke a key card, a POS login and the portal PIN. */
class AdminPulseHrLifecycleController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Onboarding & Exit'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_checklist')) {
            $c = PulseHrLifecycle::get($id);
            if (!$c) { $this->errors[] = $this->l('Checklist not found'); return $this->setTemplate('lifecycle.tpl'); }
            $this->context->smarty->assign(array('c' => $c, 'self_url' => $self, 'kc' => PulseHrService::kc(), 'pos' => PulseHrService::pos(),
                'kc_groups' => PulseHrService::kc() ? Db::getInstance()->executeS('SELECT id_pulse_kc_staff_group, name, department FROM `'._DB_PREFIX_.'pulse_kc_staff_group` WHERE active=1 ORDER BY department, name') : array(),
                'employee_url' => $this->context->link->getAdminLink('AdminPulseHrEmployees')));
            return $this->setTemplate('checklist.tpl');
        }
        $idTpl = (int) Tools::getValue('id_template');
        $this->context->smarty->assign(array(
            'open' => PulseHrLifecycle::checklists('open'), 'done' => PulseHrLifecycle::checklists('completed'),
            'templates' => PulseHrLifecycle::templates(), 'tpl' => $idTpl ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_checklist_template` WHERE id_pulse_hr_checklist_template='.$idTpl) : null,
            'tpl_tasks' => $idTpl ? PulseHrLifecycle::templateTasks($idTpl) : array(), 'departments' => PulseHrService::departments(),
            'staff' => PulseHrEmployee::search(array('limit' => 400)), 'tasks_due' => PulseHrLifecycle::openTasks(40),
            'actions' => array('none' => 'Manual tick', 'keycard_issue' => 'Issue key card', 'keycard_revoke' => 'Revoke key cards', 'pos_pin' => 'Set POS PIN', 'pos_disable' => 'Disable POS login',
                'ess_pin' => 'Set staff portal PIN', 'ess_disable' => 'Close staff portal access', 'document' => 'Collect a document', 'asset_issue' => 'Issue an asset',
                'asset_return' => 'Collect an asset', 'induction' => 'Induction', 'exit_interview' => 'Exit interview', 'final_settlement' => 'Final entitlements'),
            'self_url' => $self, 'employee_url' => $this->context->link->getAdminLink('AdminPulseHrEmployees'),
        ));
        $this->setTemplate('lifecycle.tpl');
    }

    public function postProcess()
    {
        $self = self::$currentIndex.'&token='.$this->token;
        try {
            if (Tools::isSubmit('openChecklist')) { $id = PulseHrLifecycle::open((int) Tools::getValue('id_employee_hr'), Tools::getValue('type'), (int) Tools::getValue('id_template')); if (!$id) { throw new PrestaShopException('No active template of that kind — create one first'); } Tools::redirectAdmin($self.'&id_checklist='.$id.'&conf=3'); }
            if (Tools::isSubmit('doTask')) {
                $ref = PulseHrLifecycle::completeTask((int) Tools::getValue('id_task'), Tools::getValue('task_note'), array('pin' => Tools::getValue('task_pin'), 'id_group' => (int) Tools::getValue('id_group'), 'role' => Tools::getValue('pos_role'), 'ref' => Tools::getValue('task_ref')));
                $this->confirmations[] = $this->l('Task done').($ref ? ' — '.$ref : '');
            }
            if (Tools::isSubmit('skipTask')) { PulseHrLifecycle::skipTask((int) Tools::getValue('id_task'), Tools::getValue('task_note')); $this->confirmations[] = $this->l('Task marked not applicable'); }
            if (Tools::isSubmit('saveTemplate')) { $id = PulseHrLifecycle::saveTemplate(array('code' => Tools::getValue('code'), 'name' => Tools::getValue('name'), 'type' => Tools::getValue('type'), 'department' => Tools::getValue('department'), 'active' => Tools::getValue('active', 1)), (int) Tools::getValue('id_template')); Tools::redirectAdmin($self.'&id_template='.$id.'&conf=3'); }
            if (Tools::isSubmit('saveTemplateTask')) { PulseHrLifecycle::saveTemplateTask(array('id_pulse_hr_checklist_template' => (int) Tools::getValue('id_template'), 'sort' => Tools::getValue('sort'), 'title' => Tools::getValue('title'),
                'owner_department' => Tools::getValue('owner_department'), 'due_offset_days' => Tools::getValue('due_offset_days'), 'action' => Tools::getValue('action'), 'mandatory' => Tools::getValue('mandatory')), (int) Tools::getValue('id_task_template')); $this->confirmations[] = $this->l('Template task saved'); }
            if (Tools::isSubmit('removeTemplateTask')) { PulseHrLifecycle::removeTemplateTask((int) Tools::getValue('id_task_template')); $this->confirmations[] = $this->l('Template task removed'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
