<?php
/** Journeys: the automation the hotel does not have to remember — triggers, steps, live runs and their logs. */
class AdminPulseCrmJourneysController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Journeys'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        $id = (int) Tools::getValue('id_journey');
        $this->context->smarty->assign(array(
            'journeys' => PulseCrmJourney::all(), 'j' => $id ? PulseCrmJourney::get($id) : null,
            'runs' => PulseCrmJourney::runs($id, Tools::getValue('rstatus') ?: null, 100),
            'logs' => Tools::getValue('id_run') ? PulseCrmJourney::logs((int) Tools::getValue('id_run')) : array(),
            'id_run' => (int) Tools::getValue('id_run'), 'rstatus' => Tools::getValue('rstatus'),
            'surveys' => PulseCrmSurvey::all(true), 'tags' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_tag` ORDER BY name'),
            'triggers' => array('booking_confirmed' => 'Booking confirmed', 'check_in' => 'Check-in', 'mid_stay' => 'Mid-stay (night 2)', 'check_out' => 'Check-out',
                'post_stay' => 'Post-stay', 'lapsed' => 'Lapsed 90 days', 'birthday' => 'Birthday', 'anniversary' => 'Anniversary', 'manual' => 'Manual only'),
            'actions' => array('send_template' => 'Send a message', 'create_ticket' => 'Open a ticket', 'open_case' => 'Open a recovery case', 'add_tag' => 'Add a tag',
                'add_points' => 'Add loyalty points', 'notify_manager' => 'Notify a manager', 'stop' => 'Stop the journey'),
            'self_url' => $self,
        ));
        $this->setTemplate('journeys.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveJourney')) {
                $id = PulseCrmJourney::save(array('name' => Tools::getValue('name'), 'code' => Tools::getValue('code'), 'description' => Tools::getValue('description'),
                    'trigger_event' => Tools::getValue('trigger_event'), 'active' => (int) Tools::getValue('active', 1), 'quiet_from' => Tools::getValue('quiet_from'),
                    'quiet_to' => Tools::getValue('quiet_to'), 'suppress_days' => Tools::getValue('suppress_days')), (int) Tools::getValue('id_journey_a'));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_journey='.$id.'&conf=3');
            }
            if (Tools::isSubmit('saveStep')) {
                $cond = Tools::getValue('cond_field') ? array('match' => 'all', 'rules' => array(array('field' => Tools::getValue('cond_field'), 'op' => Tools::getValue('cond_op', 'eq'), 'value' => Tools::getValue('cond_value')))) : '';
                $action = array();
                foreach (array('survey', 'tag', 'points', 'reason', 'department', 'priority', 'category', 'title', 'severity', 'email') as $k) { if (Tools::getValue('act_'.$k) !== false && Tools::getValue('act_'.$k) !== '') { $action[$k] = Tools::getValue('act_'.$k); } }
                PulseCrmJourney::saveStep(array('id_pulse_crm_journey' => (int) Tools::getValue('id_journey_a'), 'sort' => (int) Tools::getValue('sort'), 'name' => Tools::getValue('sname'),
                    'delay_minutes' => (int) Tools::getValue('delay_minutes'), 'condition_json' => $cond, 'action' => Tools::getValue('action'), 'channel' => Tools::getValue('channel'),
                    'template_code' => Tools::getValue('template_code'), 'subject' => Tools::getValue('subject'), 'body' => Tools::getValue('body'), 'action_json' => $action,
                    'active' => (int) Tools::getValue('sactive', 1)), (int) Tools::getValue('id_step'));
                $this->confirmations[] = $this->l('Step saved');
            }
            if (Tools::isSubmit('delStep')) { PulseCrmJourney::removeStep((int) Tools::getValue('id_step')); $this->confirmations[] = $this->l('Step removed'); }
            if (Tools::isSubmit('toggleJourney')) { Db::getInstance()->update('pulse_crm_journey', array('active' => (int) Tools::getValue('active'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_journey='.(int) Tools::getValue('id_journey_a')); $this->confirmations[] = $this->l('Journey updated'); }
            if (Tools::isSubmit('advanceNow')) { $r = PulseCrmJourney::advance(100); $this->confirmations[] = sprintf($this->l('%1$d runs touched, %2$d steps, %3$d sent, %4$d skipped, %5$d finished'), $r['runs'], $r['steps'], $r['sent'], $r['skipped'], $r['finished']); }
            if (Tools::isSubmit('triggerScheduled')) { $r = PulseCrmJourney::triggerScheduled(); $this->confirmations[] = sprintf($this->l('Started: %1$d mid-stay, %2$d win-back, %3$d birthday, %4$d anniversary'), $r['mid_stay'], $r['lapsed'], $r['birthday'], $r['anniversary']); }
            if (Tools::isSubmit('cancelRun')) { Db::getInstance()->update('pulse_crm_journey_run', array('status' => 'cancelled', 'last_error' => 'cancelled at the desk', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_journey_run='.(int) Tools::getValue('id_run_a')); $this->confirmations[] = $this->l('Run cancelled'); }
            if (Tools::isSubmit('startRun')) { $n = PulseCrmJourney::start((int) Tools::getValue('id_journey_a'), array('id_customer' => (int) Tools::getValue('id_customer'))); $this->confirmations[] = $n ? $this->l('Journey started for that guest') : $this->l('That guest is already on this journey'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
