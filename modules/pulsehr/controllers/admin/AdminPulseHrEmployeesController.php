<?php
/** The employee list and the employee file: personal details, contract history, documents, leave, roster, checklists, discipline and the portal PIN. */
class AdminPulseHrEmployeesController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Employees'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_employee_hr')) {
            $e = PulseHrEmployee::get($id);
            if (!$e) { $this->errors[] = $this->l('Employee not found'); return $this->setTemplate('employees.tpl'); }
            $year = (int) Tools::getValue('year', date('Y'));
            $this->context->smarty->assign(array(
                'e' => $e, 'contracts' => PulseHrContract::history($id), 'contract_now' => PulseHrContract::onDate($id),
                'documents' => PulseHrDocument::forEmployee($id), 'doc_types' => PulseHrDocument::types(),
                'balances' => PulseHrLeave::balances($id, $year), 'leave' => PulseHrLeave::requests(null, null, $id, 40),
                'roster' => PulseHrRoster::forEmployee($id, date('Y-m-d', strtotime('-7 day')), date('Y-m-d', strtotime('+21 day')), false),
                'checklists' => PulseHrLifecycle::forEmployee($id), 'cases' => PulseHrPerformance::cases(null, $id),
                'appraisals' => PulseHrPerformance::appraisals(0, $id), 'training' => PulseHrPerformance::training($id),
                'punches' => PulseHrEss::punches(date('Y-m-d', strtotime('-30 day')), date('Y-m-d'), null, $id, 60),
                'reports_to' => PulseHrEmployee::directReports($id), 'pos_staff' => PulseHrEmployee::posStaff((int) $e['id_employee']),
                'live_warnings' => PulseHrPerformance::liveWarnings($id), 'year' => $year,
                'departments' => PulseHrService::departments(), 'sections' => PulseHrService::sections(), 'grades' => PulseHrService::grades(), 'positions' => PulseHrService::positions(),
                'managers' => PulseHrEmployee::search(array('limit' => 400)), 'leave_types' => PulseHrLeave::types(),
                'bo_users' => Db::getInstance()->executeS('SELECT id_employee, firstname, lastname, email FROM `'._DB_PREFIX_.'employee` WHERE active=1 ORDER BY lastname'),
                'kc' => PulseHrService::kc(), 'pos' => PulseHrService::pos(), 'pr' => PulseHrService::pr(),
                'self_url' => $self, 'lifecycle_url' => $this->context->link->getAdminLink('AdminPulseHrLifecycle'),
            ));
            return $this->setTemplate('employee.tpl');
        }
        $f = array('q' => Tools::getValue('q'), 'department' => Tools::getValue('department'), 'status' => Tools::getValue('status'),
            'id_grade' => (int) Tools::getValue('id_grade'), 'include_exited' => (int) Tools::getValue('include_exited'));
        $this->context->smarty->assign(array(
            'rows' => PulseHrEmployee::search($f), 'f' => $f, 'departments' => PulseHrService::departments(), 'grades' => PulseHrService::grades(),
            'positions' => PulseHrService::positions(), 'sections' => PulseHrService::sections(), 'managers' => PulseHrEmployee::search(array('limit' => 400)),
            'change_requests' => PulseHrEss::changeRequests('pending'), 'self_url' => $self,
        ));
        $this->setTemplate('employees.tpl');
    }

    public function postProcess()
    {
        $back = self::$currentIndex.'&token='.$this->token;
        try {
            if (Tools::isSubmit('saveEmployee')) {
                $d = array();
                foreach (array('staff_no', 'firstname', 'lastname', 'othernames', 'gender', 'dob', 'marital', 'nationality', 'state_of_origin', 'lga', 'national_id', 'tin',
                    'rsa_pin', 'pfa', 'nhf_no', 'nhf_consent_note', 'bank_name', 'bank_code', 'account_no', 'account_name', 'nok_name', 'nok_relationship', 'nok_phone',
                    'nok_address', 'address', 'city', 'phone', 'phone_alt', 'email', 'photo', 'note', 'hire_date', 'probation_end', 'confirmation_date', 'status',
                    'id_employee', 'id_pulse_hr_department', 'id_pulse_hr_section', 'id_pulse_hr_position', 'id_pulse_hr_grade', 'id_manager',
                    'contract_type', 'pay_basis', 'pay_rate', 'end_date', 'probation_months', 'night_shift') as $k) { if (Tools::getValue($k) !== false) { $d[$k] = Tools::getValue($k); } }
                $d['nhf_consent'] = (int) Tools::getValue('nhf_consent');
                $d['ess_enabled'] = (int) Tools::getValue('ess_enabled', 1);
                $id = (int) Tools::getValue('id_employee_hr');
                $id = PulseHrEmployee::save($d, $id);
                if (Tools::getValue('pin')) { PulseHrEmployee::setPin($id, Tools::getValue('pin')); }
                Tools::redirectAdmin($back.'&id_employee_hr='.$id.'&conf=3');
            }
            if (Tools::isSubmit('saveContract')) {
                $d = array('id_pulse_hr_employee' => (int) Tools::getValue('id_employee_hr'));
                foreach (array('type', 'effective_from', 'start_date', 'end_date', 'pay_basis', 'pay_rate', 'currency', 'hours_per_week', 'days_per_week', 'working_pattern',
                    'notice_days', 'probation_months', 'reason', 'note', 'cost_centre', 'id_pulse_hr_position', 'id_pulse_hr_department', 'id_pulse_hr_section',
                    'id_pulse_hr_grade', 'id_manager') as $k) { $d[$k] = Tools::getValue($k); }
                $d['night_shift'] = (int) Tools::getValue('night_shift');
                PulseHrContract::save($d);
                $this->confirmations[] = $this->l('Contract version saved — the previous one has been closed the day before it starts');
            }
            if (Tools::isSubmit('removeContract')) { PulseHrContract::removeVersion((int) Tools::getValue('id_contract')); $this->confirmations[] = $this->l('Contract version removed'); }
            if (Tools::isSubmit('saveDocument')) {
                PulseHrDocument::save(array('id_pulse_hr_employee' => (int) Tools::getValue('id_employee_hr'), 'type' => Tools::getValue('doc_type'), 'name' => Tools::getValue('doc_name'),
                    'number' => Tools::getValue('doc_number'), 'issuer' => Tools::getValue('doc_issuer'), 'issued_on' => Tools::getValue('issued_on'), 'expires_on' => Tools::getValue('expires_on'),
                    'file_path' => Tools::getValue('file_path'), 'verified' => (int) Tools::getValue('verified'), 'remind_days' => (int) Tools::getValue('remind_days'), 'note' => Tools::getValue('doc_note')), (int) Tools::getValue('id_document'));
                $this->confirmations[] = $this->l('Document saved');
            }
            if (Tools::isSubmit('deleteDocument')) { PulseHrDocument::remove((int) Tools::getValue('id_document')); $this->confirmations[] = $this->l('Document removed'); }
            if (Tools::isSubmit('setStatus')) { PulseHrEmployee::setStatus((int) Tools::getValue('id_employee_hr'), Tools::getValue('new_status'), Tools::getValue('status_note')); $this->confirmations[] = $this->l('Status updated'); }
            if (Tools::isSubmit('confirmStaff')) { PulseHrEmployee::confirm((int) Tools::getValue('id_employee_hr'), Tools::getValue('confirm_date'), Tools::getValue('confirm_rate')); $this->confirmations[] = $this->l('Confirmed off probation'); }
            if (Tools::isSubmit('exitStaff')) {
                $idc = PulseHrEmployee::exitEmployee((int) Tools::getValue('id_employee_hr'), Tools::getValue('exit_date'), Tools::getValue('exit_type'), Tools::getValue('exit_reason'), (int) Tools::getValue('rehire_eligible'));
                $this->confirmations[] = $this->l('Exit recorded — the clearance checklist is open').($idc ? ' (#'.$idc.')' : '');
            }
            if (Tools::isSubmit('setPin')) { PulseHrEmployee::setPin((int) Tools::getValue('id_employee_hr'), Tools::getValue('pin')); $this->confirmations[] = $this->l('Staff portal PIN set'); }
            if (Tools::isSubmit('clearPin')) { PulseHrEmployee::clearPin((int) Tools::getValue('id_employee_hr')); PulseHrEss::revokeForEmployee((int) Tools::getValue('id_employee_hr'), 'pin cleared'); $this->confirmations[] = $this->l('PIN cleared and sessions ended'); }
            if (Tools::isSubmit('adjustLeave')) { PulseHrLeave::adjust((int) Tools::getValue('id_employee_hr'), (int) Tools::getValue('id_leave_type'), (float) Tools::getValue('adj_days'), Tools::getValue('adj_reason'), (int) Tools::getValue('year')); $this->confirmations[] = $this->l('Leave balance adjusted'); }
            if (Tools::isSubmit('decideChange')) { PulseHrEss::decideChange((int) Tools::getValue('id_change'), (int) Tools::getValue('approve'), Tools::getValue('change_note')); $this->confirmations[] = $this->l('Change request handled'); }
            if (Tools::isSubmit('addTraining')) {
                PulseHrPerformance::saveTraining(array('id_pulse_hr_employee' => (int) Tools::getValue('id_employee_hr'), 'course' => Tools::getValue('course'), 'provider' => Tools::getValue('provider'),
                    'type' => Tools::getValue('training_type'), 'completed_on' => Tools::getValue('completed_on'), 'expires_on' => Tools::getValue('training_expires'),
                    'cost' => (float) Tools::getValue('cost'), 'certificate_no' => Tools::getValue('certificate_no')));
                $this->confirmations[] = $this->l('Training recorded');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
