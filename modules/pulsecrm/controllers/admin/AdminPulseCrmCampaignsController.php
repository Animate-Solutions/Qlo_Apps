<?php
/** Campaigns: compose, queue against a segment, send in throttled slices, and read what came back. */
class AdminPulseCrmCampaignsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Campaigns'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_campaign')) {
            $this->context->smarty->assign(array('c' => PulseCrmCampaign::get($id), 'segments' => PulseCrmSegment::all(true),
                'recipients' => PulseCrmCampaign::recipients($id, Tools::getValue('rstatus') ?: null, 200),
                'rstatus' => Tools::getValue('rstatus'), 'tags' => $this->mergeTags(), 'self_url' => $self, 'quiet' => PulseCrmService::inQuietHours()));
            return $this->setTemplate('campaign.tpl');
        }
        $this->context->smarty->assign(array('campaigns' => PulseCrmCampaign::all(), 'segments' => PulseCrmSegment::all(true),
            'tags' => $this->mergeTags(), 'self_url' => $self, 'quiet' => PulseCrmService::inQuietHours(),
            'quiet_from' => PulseCrmService::cfg('QUIET_FROM', '21:00'), 'quiet_to' => PulseCrmService::cfg('QUIET_TO', '08:00')));
        $this->setTemplate('campaigns.tpl');
    }

    protected function mergeTags() { return array('first_name', 'last_name', 'full_name', 'hotel', 'city', 'stays', 'nights', 'last_stay', 'tier', 'points', 'member_no', 'today', 'unsubscribe_url'); }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveCampaign')) {
                $id = PulseCrmCampaign::save(array('name' => Tools::getValue('name'), 'channel' => Tools::getValue('channel'), 'id_pulse_crm_segment' => Tools::getValue('id_pulse_crm_segment'),
                    'subject' => Tools::getValue('subject'), 'body' => Tools::getValue('body'), 'subject_b' => Tools::getValue('subject_b'), 'body_b' => Tools::getValue('body_b'),
                    'ab_split_pct' => Tools::getValue('ab_split_pct'), 'schedule_type' => Tools::getValue('schedule_type'), 'send_at' => Tools::getValue('send_at'),
                    'recur_dom' => Tools::getValue('recur_dom'), 'recur_dow' => Tools::getValue('recur_dow'), 'throttle_per_run' => Tools::getValue('throttle_per_run'),
                    'quiet_from' => Tools::getValue('quiet_from'), 'quiet_to' => Tools::getValue('quiet_to')), (int) Tools::getValue('id_campaign_a'));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_campaign='.$id.'&conf=3');
            }
            if (Tools::isSubmit('queueCampaign')) { $r = PulseCrmCampaign::queue((int) Tools::getValue('id_campaign_a')); $this->confirmations[] = sprintf($this->l('%1$d queued, %2$d skipped (no consent, no address or blacklisted)'), $r['queued'], $r['skipped']); }
            if (Tools::isSubmit('sendCampaign')) {
                $r = PulseCrmCampaign::send((int) Tools::getValue('id_campaign_a'), (int) Tools::getValue('slice') ?: null);
                if (!empty($r['stopped'])) { $this->warnings[] = $r['stopped'] === 'quiet_hours' ? sprintf($this->l('Held for quiet hours — the cron resumes at %s'), $r['resume_at']) : $this->l('Campaign is ').$r['stopped']; }
                else { $this->confirmations[] = sprintf($this->l('%1$d sent, %2$d failed, %3$d skipped, %4$d still queued'), $r['sent'], $r['failed'], $r['skipped'], $r['remaining']); }
            }
            if (Tools::isSubmit('testCampaign')) {
                $c = PulseCrmCampaign::get((int) Tools::getValue('id_campaign_a'));
                $idc = (int) Db::getInstance()->getValue('SELECT id_customer FROM `'._DB_PREFIX_.'customer` WHERE email="'.pSQL(Tools::getValue('test_email')).'" AND deleted=0');
                if (!$idc) { throw new PrestaShopException($this->l('No customer account with that email — send the test to a real guest record so the merge tags fill in')); }
                $vars = PulseCrmService::mergeVars($idc); $vars['subject'] = PulseCrmService::render($c['subject'], $vars);
                $vars['text'] = PulseCrmService::render($c['body'], $vars); $vars['html'] = PulseCrmCampaign::htmlBody($vars['text']);
                $r = PulseCrmComms::deliver($idc, $c['channel'], 'crm_campaign', $vars, array('kind' => 'transactional', 'transactional' => 1, 'ignore_quiet' => 1, 'reference' => 'test'));
                $this->confirmations[] = $r['ok'] ? $this->l('Test sent') : $this->l('Test not sent: ').$r['reason'];
            }
            if (Tools::isSubmit('setCampaignStatus')) { Db::getInstance()->update('pulse_crm_campaign', array('status' => pSQL(Tools::getValue('status')), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_campaign='.(int) Tools::getValue('id_campaign_a')); $this->confirmations[] = $this->l('Campaign updated'); }
            if (Tools::isSubmit('deleteCampaign')) { PulseCrmCampaign::remove((int) Tools::getValue('id_campaign_a')); Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&conf=1'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
