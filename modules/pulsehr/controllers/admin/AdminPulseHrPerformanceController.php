<?php
/** Discipline cases with acknowledgement, appraisal cycles with weighted objectives, and training records that expire. */
class AdminPulseHrPerformanceController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Discipline & Appraisal'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_appraisal')) {
            $a = PulseHrPerformance::appraisal($id);
            if (!$a) { $this->errors[] = $this->l('Appraisal not found'); return $this->setTemplate('performance.tpl'); }
            $this->context->smarty->assign(array('a' => $a, 'staff' => PulseHrEmployee::search(array('limit' => 400)), 'self_url' => $self));
            return $this->setTemplate('appraisal.tpl');
        }
        $idCycle = (int) Tools::getValue('id_cycle');
        $this->context->smarty->assign(array(
            'cases' => PulseHrPerformance::cases(Tools::getValue('status', 'open,acknowledged,responded')), 'closed' => PulseHrPerformance::cases('closed,withdrawn', 0, 40),
            'case_types' => PulseHrPerformance::caseTypes(), 'cycles' => PulseHrPerformance::cycles(), 'id_cycle' => $idCycle,
            'appraisals' => PulseHrPerformance::appraisals($idCycle), 'training' => PulseHrPerformance::training(), 'training_expiring' => PulseHrPerformance::trainingExpiring(60),
            'staff' => PulseHrEmployee::search(array('limit' => 400)), 'departments' => PulseHrService::departments(),
            'self_url' => $self, 'employee_url' => $this->context->link->getAdminLink('AdminPulseHrEmployees'),
        ));
        $this->setTemplate('performance.tpl');
    }

    public function postProcess()
    {
        $self = self::$currentIndex.'&token='.$this->token;
        try {
            if (Tools::isSubmit('saveCase')) {
                PulseHrPerformance::saveCase(array('id_pulse_hr_employee' => (int) Tools::getValue('id_employee_hr'), 'type' => Tools::getValue('type'), 'subject' => Tools::getValue('subject'),
                    'description' => Tools::getValue('description'), 'incident_date' => Tools::getValue('incident_date'), 'issued_on' => Tools::getValue('issued_on'),
                    'suspension_from' => Tools::getValue('suspension_from'), 'suspension_to' => Tools::getValue('suspension_to'), 'unpaid' => Tools::getValue('unpaid')), (int) Tools::getValue('id_case'));
                $this->confirmations[] = $this->l('Case saved — the member of staff will see it on the portal');
            }
            if (Tools::isSubmit('closeCase')) { PulseHrPerformance::closeCase((int) Tools::getValue('id_case'), Tools::getValue('outcome'), Tools::getValue('case_status')); $this->confirmations[] = $this->l('Case closed'); }
            if (Tools::isSubmit('saveCycle')) { $id = PulseHrPerformance::saveCycle(array('code' => Tools::getValue('code'), 'name' => Tools::getValue('name'), 'period_from' => Tools::getValue('period_from'),
                'period_to' => Tools::getValue('period_to'), 'due_on' => Tools::getValue('due_on'), 'status' => Tools::getValue('cstatus')), (int) Tools::getValue('id_cycle_save')); Tools::redirectAdmin($self.'&id_cycle='.$id.'&conf=3'); }
            if (Tools::isSubmit('openCycle')) { $n = PulseHrPerformance::openCycle((int) Tools::getValue('id_cycle'), Tools::getValue('department')); $this->confirmations[] = $n.' '.$this->l('appraisal(s) opened'); }
            if (Tools::isSubmit('saveAppraisal')) {
                PulseHrPerformance::saveAppraisal(array('status' => Tools::getValue('status'), 'reviewer_comment' => Tools::getValue('reviewer_comment'), 'employee_comment' => Tools::getValue('employee_comment'),
                    'recommendation' => Tools::getValue('recommendation'), 'id_reviewer' => Tools::getValue('id_reviewer'), 'sign_reviewer' => Tools::getValue('sign_reviewer'), 'sign_employee' => Tools::getValue('sign_employee')), (int) Tools::getValue('id_appraisal'));
                $this->confirmations[] = $this->l('Appraisal saved');
            }
            if (Tools::isSubmit('saveObjective')) {
                PulseHrPerformance::saveObjective(array('id_pulse_hr_appraisal' => (int) Tools::getValue('id_appraisal'), 'sort' => Tools::getValue('sort'), 'title' => Tools::getValue('title'),
                    'description' => Tools::getValue('description'), 'weight' => Tools::getValue('weight'), 'target' => Tools::getValue('target'), 'result' => Tools::getValue('result'),
                    'rating' => Tools::getValue('rating'), 'comment' => Tools::getValue('comment')), (int) Tools::getValue('id_objective'));
                $this->confirmations[] = $this->l('Objective saved');
            }
            if (Tools::isSubmit('removeObjective')) { PulseHrPerformance::removeObjective((int) Tools::getValue('id_objective')); $this->confirmations[] = $this->l('Objective removed'); }
            if (Tools::isSubmit('saveTraining')) {
                PulseHrPerformance::saveTraining(array('id_pulse_hr_employee' => (int) Tools::getValue('id_employee_hr'), 'course' => Tools::getValue('course'), 'provider' => Tools::getValue('provider'),
                    'type' => Tools::getValue('training_type'), 'completed_on' => Tools::getValue('completed_on'), 'expires_on' => Tools::getValue('expires_on'),
                    'cost' => Tools::getValue('cost'), 'certificate_no' => Tools::getValue('certificate_no'), 'note' => Tools::getValue('note')), (int) Tools::getValue('id_training'));
                $this->confirmations[] = $this->l('Training recorded');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
