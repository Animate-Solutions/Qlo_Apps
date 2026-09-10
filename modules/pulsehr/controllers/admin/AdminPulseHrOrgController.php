<?php
/** Departments, sections, grades with salary bands, positions with the budgeted establishment, and the org chart. */
class AdminPulseHrOrgController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Org & Positions'); }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array(
            'departments' => PulseHrService::departments(false), 'sections' => PulseHrService::sections(), 'grades' => PulseHrService::grades(false),
            'positions' => PulseHrService::positions(), 'chart' => PulseHrEmployee::orgChart(), 'headcount' => PulseHrReport::headcount(),
            'staff' => PulseHrEmployee::search(array('limit' => 400)), 'shifts' => PulseHrService::shifts(false),
            'self_url' => self::$currentIndex.'&token='.$this->token,
            'employee_url' => $this->context->link->getAdminLink('AdminPulseHrEmployees'),
        ));
        $this->setTemplate('org.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveDept')) { PulseHrService::saveDepartment(array('code' => Tools::getValue('code'), 'name' => Tools::getValue('name'), 'cost_centre' => Tools::getValue('cost_centre'),
                'credit_minutes_per_room' => Tools::getValue('credit_minutes_per_room'), 'id_head' => Tools::getValue('id_head'), 'sort' => Tools::getValue('sort'), 'active' => Tools::getValue('active', 1)), (int) Tools::getValue('id_department')); $this->confirmations[] = $this->l('Department saved'); }
            if (Tools::isSubmit('saveSection')) { PulseHrService::saveSection(array('id_pulse_hr_department' => Tools::getValue('id_pulse_hr_department'), 'code' => Tools::getValue('scode'), 'name' => Tools::getValue('sname')), (int) Tools::getValue('id_section')); $this->confirmations[] = $this->l('Section saved'); }
            if (Tools::isSubmit('saveGrade')) { PulseHrService::saveGrade(array('code' => Tools::getValue('gcode'), 'name' => Tools::getValue('gname'), 'level' => Tools::getValue('level'),
                'salary_min' => Tools::getValue('salary_min'), 'salary_max' => Tools::getValue('salary_max'), 'annual_leave_days' => Tools::getValue('annual_leave_days'),
                'notice_days' => Tools::getValue('gnotice_days'), 'active' => Tools::getValue('gactive', 1)), (int) Tools::getValue('id_grade')); $this->confirmations[] = $this->l('Grade saved'); }
            if (Tools::isSubmit('savePosition')) { PulseHrService::savePosition(array('code' => Tools::getValue('pcode'), 'title' => Tools::getValue('title'), 'id_pulse_hr_department' => Tools::getValue('pdept'),
                'id_pulse_hr_section' => Tools::getValue('psection'), 'id_pulse_hr_grade' => Tools::getValue('pgrade'), 'establishment' => Tools::getValue('establishment'),
                'night_shift' => Tools::getValue('pnight'), 'active' => Tools::getValue('pactive', 1)), (int) Tools::getValue('id_position')); $this->confirmations[] = $this->l('Position saved'); }
            if (Tools::isSubmit('saveShift')) { PulseHrService::saveShift(array('code' => Tools::getValue('shcode'), 'name' => Tools::getValue('shname'), 'start_time' => Tools::getValue('start_time'),
                'end_time' => Tools::getValue('end_time'), 'break_minutes' => Tools::getValue('break_minutes'), 'paid_hours' => Tools::getValue('paid_hours'), 'night' => Tools::getValue('night'),
                'split' => Tools::getValue('split'), 'on_call' => Tools::getValue('on_call'), 'department' => Tools::getValue('shdept'), 'colour' => Tools::getValue('colour'),
                'sort' => Tools::getValue('shsort'), 'active' => Tools::getValue('shactive', 1)), (int) Tools::getValue('id_shift')); $this->confirmations[] = $this->l('Shift pattern saved'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
