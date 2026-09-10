<?php
/** Surveys: question sets per touchpoint, the responses that came back, NPS and the department scoreboard. */
class AdminPulseCrmSurveysController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Surveys'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        $from = Tools::getValue('from', date('Y-m-d', strtotime('-90 day'))); $to = Tools::getValue('to', PulseCrmService::bd());
        $id = (int) Tools::getValue('id_survey');
        $this->context->smarty->assign(array(
            'surveys' => PulseCrmSurvey::all(), 's' => $id ? PulseCrmSurvey::get($id) : null,
            'from' => $from, 'to' => $to, 'nps' => PulseCrmService::npsFor($from, $to), 'trend' => PulseCrmService::npsTrend(12),
            'dept' => PulseCrmSurvey::departmentScores($from, $to), 'rate' => PulseCrmSurvey::responseRate($from, $to),
            'responses' => PulseCrmSurvey::responses($from, $to, $id, Tools::getValue('band') ?: null, 200), 'band' => Tools::getValue('band'),
            'detail' => Tools::getValue('id_response') ? $this->responseDetail((int) Tools::getValue('id_response')) : null,
            'self_url' => $self, 'survey_base' => PulseCrmService::link('survey', array('t' => '')),
        ));
        $this->setTemplate('surveys.tpl');
    }

    protected function responseDetail($id)
    {
        $r = Db::getInstance()->getRow('SELECT r.*, s.name survey_name, CONCAT(c.firstname," ",c.lastname) guest FROM `'._DB_PREFIX_.'pulse_crm_survey_response` r
            INNER JOIN `'._DB_PREFIX_.'pulse_crm_survey` s ON s.id_pulse_crm_survey=r.id_pulse_crm_survey
            LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=r.id_customer WHERE r.id_pulse_crm_survey_response='.(int) $id);
        if ($r) { $r['answers'] = Db::getInstance()->executeS('SELECT a.*, q.label, q.type FROM `'._DB_PREFIX_.'pulse_crm_survey_answer` a INNER JOIN `'._DB_PREFIX_.'pulse_crm_survey_question` q ON q.id_pulse_crm_survey_question=a.id_pulse_crm_survey_question WHERE a.id_pulse_crm_survey_response='.(int) $id.' ORDER BY q.sort'); }
        return $r;
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveSurvey')) {
                $id = PulseCrmSurvey::save(array('name' => Tools::getValue('name'), 'code' => Tools::getValue('code'), 'touchpoint' => Tools::getValue('touchpoint'),
                    'intro' => Tools::getValue('intro'), 'thanks' => Tools::getValue('thanks'), 'low_score_threshold' => Tools::getValue('low_score_threshold'),
                    'expiry_days' => Tools::getValue('expiry_days'), 'active' => (int) Tools::getValue('active', 1)), (int) Tools::getValue('id_survey_a'));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_survey='.$id.'&conf=3');
            }
            if (Tools::isSubmit('saveQuestion')) {
                PulseCrmSurvey::saveQuestion(array('id_pulse_crm_survey' => (int) Tools::getValue('id_survey_a'), 'sort' => (int) Tools::getValue('sort'), 'code' => Tools::getValue('qcode'),
                    'type' => Tools::getValue('type'), 'label' => Tools::getValue('label'), 'options' => Tools::getValue('options'), 'department' => Tools::getValue('department'),
                    'required' => (int) Tools::getValue('required')), (int) Tools::getValue('id_question'));
                $this->confirmations[] = $this->l('Question saved');
            }
            if (Tools::isSubmit('delQuestion')) { PulseCrmSurvey::removeQuestion((int) Tools::getValue('id_question')); $this->confirmations[] = $this->l('Question removed'); }
            if (Tools::isSubmit('sendInvite')) {
                $idc = (int) Db::getInstance()->getValue('SELECT id_customer FROM `'._DB_PREFIX_.'customer` WHERE email="'.pSQL(Tools::getValue('invite_email')).'" AND deleted=0');
                if (!$idc) { throw new PrestaShopException($this->l('No guest with that email')); }
                $r = PulseCrmSurvey::inviteAndSend((int) Tools::getValue('id_survey_a'), $idc, null, Tools::getValue('invite_channel', 'email'));
                $this->confirmations[] = $r['ok'] ? $this->l('Invitation sent') : $this->l('Not sent: ').$r['reason'].' — '.$this->l('the link is ').PulseCrmSurvey::url($r['token']);
            }
            if (Tools::isSubmit('openCaseFor')) {
                $r = $this->responseDetail((int) Tools::getValue('id_response_a'));
                if (!$r) { throw new PrestaShopException($this->l('No such response')); }
                $id = PulseCrmCase::open(array('source' => 'survey', 'severity' => 'medium', 'department' => $r['department_low'] ? $r['department_low'] : 'frontdesk',
                    'id_customer' => $r['id_customer'], 'id_htl_booking' => $r['id_htl_booking'], 'id_room' => $r['id_room'], 'id_pulse_crm_survey_response' => $r['id_pulse_crm_survey_response'],
                    'title' => 'Follow-up on '.$r['survey_name'], 'description' => $r['comment']));
                $this->confirmations[] = $this->l('Recovery case opened (#').$id.')';
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
