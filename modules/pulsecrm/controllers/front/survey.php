<?php
/** /pulse/survey?t=<token> — the public, mobile-first survey page a guest gets by email, SMS or WhatsApp. */
require_once _PS_MODULE_DIR_.'pulsecrm/classes/autoload.php';

class PulseCrmSurveyModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    protected $resp;

    public function init()
    {
        parent::init();
        $this->resp = PulseCrmSurvey::byToken(Tools::getValue('t'));
    }

    public function postProcess()
    {
        if (!$this->resp || !Tools::isSubmit('submitSurvey')) { return; }
        try {
            $answers = array();
            foreach ($this->resp['questions'] as $q) {
                $v = Tools::getValue('q_'.$q['code']);
                if ($q['type'] === 'multi') { $v = Tools::getValue('q_'.$q['code'], array()); }
                if ($v !== false && $v !== '' && $v !== null) { $answers[$q['code']] = $v; }
            }
            $r = PulseCrmSurvey::submit($this->resp['token'], $answers, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);
            $this->context->smarty->assign(array('done' => true, 'nps' => $r['nps'], 'low' => ($r['nps'] !== null && (int) $r['nps'] <= (int) $this->resp['low_score_threshold'])));
        } catch (Exception $e) { $this->context->smarty->assign('error', $e->getMessage()); }
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array(
            'r' => $this->resp, 'hotel' => Configuration::get('PS_SHOP_NAME'),
            'invalid' => !$this->resp, 'expired' => $this->resp && $this->resp['expires_on'] && $this->resp['expires_on'] < date('Y-m-d'),
            'already' => $this->resp && $this->resp['status'] === 'completed',
            'action' => PulseCrmService::link('survey', array('t' => $this->resp ? $this->resp['token'] : '')),
        ));
        $this->setTemplate('survey.tpl');
    }
}
