<?php
/** CRM settings: quiet hours, consent policy, throttles, cron token and the managed preference list. */
class AdminPulseCrmSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('CRM Settings');
        $this->fields_options = array('crm' => array('title' => $this->l('CRM'), 'fields' => array(
            'PULSE_CRM_QUIET_FROM' => array('title' => $this->l('Quiet hours start (Africa/Lagos)'), 'type' => 'text', 'desc' => $this->l('No marketing message leaves the hotel between these two times.')),
            'PULSE_CRM_QUIET_TO' => array('title' => $this->l('Quiet hours end'), 'type' => 'text'),
            'PULSE_CRM_SUPPRESS_DAYS' => array('title' => $this->l('Suppression window (days)'), 'type' => 'text', 'desc' => $this->l('A guest never receives two automated messages inside this many days.')),
            'PULSE_CRM_IMPLIED_CONSENT' => array('title' => $this->l('Treat "unknown" consent as permission'), 'type' => 'bool', 'desc' => $this->l('Leave this off. Under the NDPR consent must be given, not assumed; turn it on only for a channel your legal counsel has cleared.')),
            'PULSE_CRM_THROTTLE' => array('title' => $this->l('Default messages per cron run'), 'type' => 'text'),
            'PULSE_CRM_SEGMENT_CHUNK' => array('title' => $this->l('Segments refreshed per cron run'), 'type' => 'text'),
            'PULSE_CRM_JOURNEY_CHUNK' => array('title' => $this->l('Journey steps per cron run'), 'type' => 'text'),
            'PULSE_CRM_NPS_THRESHOLD' => array('title' => $this->l('NPS at or below this opens a case'), 'type' => 'text'),
            'PULSE_CRM_SURVEY_EXPIRY_DAYS' => array('title' => $this->l('Survey link lifetime (days)'), 'type' => 'text'),
            'PULSE_CRM_REVIEW_URL' => array('title' => $this->l('Review link ({review_url})'), 'type' => 'text', 'desc' => $this->l('Your Google or TripAdvisor write-a-review link.')),
            'PULSE_CRM_PORTAL_URL' => array('title' => $this->l('Guest portal link ({portal_url})'), 'type' => 'text'),
            'PULSE_CRM_BASE_URL' => array('title' => $this->l('Public base URL for tracked links'), 'type' => 'text', 'desc' => $this->l('Leave empty to use the shop domain.')),
            'PULSE_CRM_MANAGER_EMAIL' => array('title' => $this->l('Duty manager email'), 'type' => 'text'),
            'PULSE_CRM_CRON_TOKEN' => array('title' => $this->l('Cron token'), 'type' => 'text'),
        ), 'submit' => array('title' => $this->l('Save'))));
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array(
            'options' => PulseCrmProfile::options(), 'tags' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_tag` ORDER BY name'),
            'consent_summary' => Db::getInstance()->executeS('SELECT channel, state, COUNT(*) n FROM `'._DB_PREFIX_.'pulse_crm_consent` GROUP BY channel, state ORDER BY channel, state'),
            'cron_token' => Configuration::get('PULSE_CRM_CRON_TOKEN'), 'cron_url' => PulseCrmService::baseUrl().'modules/pulsecrm/cron/crm.php?token='.Configuration::get('PULSE_CRM_CRON_TOKEN'),
            'quiet' => PulseCrmService::inQuietHours(), 'comms' => PulseCrmService::comms(), 'fd' => PulseCrmService::fd(),
            'sms_adapter' => Configuration::get('PULSE_FD_SMS_ADAPTER'), 'sms_key' => Configuration::get('PULSE_FD_SMS_API_KEY') ? true : false,
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->content .= $this->context->smarty->fetch($this->getTemplatePath().'pulse_crm_settings/settings.tpl');
        $this->context->smarty->assign('content', $this->content);
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveOption')) {
                $id = (int) Tools::getValue('id_option');
                $d = array('category' => pSQL(Tools::getValue('category')), 'code' => pSQL(Tools::str2url(Tools::getValue('code'))), 'label' => pSQL(Tools::getValue('label')),
                    'sort' => (int) Tools::getValue('sort'), 'active' => (int) Tools::getValue('active', 1));
                if (!$d['code'] || !$d['label']) { throw new PrestaShopException($this->l('A preference option needs a code and a label')); }
                $id ? Db::getInstance()->update('pulse_crm_preference_option', $d, 'id_pulse_crm_preference_option='.$id) : Db::getInstance()->insert('pulse_crm_preference_option', $d);
                $this->confirmations[] = $this->l('Preference option saved');
            }
            if (Tools::isSubmit('saveTagDef')) {
                $id = (int) Tools::getValue('id_tag');
                $d = array('code' => pSQL(Tools::str2url(Tools::getValue('tcode'))), 'name' => pSQL(Tools::getValue('tname')), 'colour' => pSQL(Tools::getValue('tcolour', 'default')));
                $id ? Db::getInstance()->update('pulse_crm_tag', $d, 'id_pulse_crm_tag='.$id) : Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_crm_tag` (code,name,colour) VALUES ("'.$d['code'].'","'.$d['name'].'","'.$d['colour'].'")');
                $this->confirmations[] = $this->l('Tag saved');
            }
            if (Tools::isSubmit('rollToken')) { Configuration::updateValue('PULSE_CRM_CRON_TOKEN', Tools::passwdGen(32)); $this->confirmations[] = $this->l('Cron token rolled — update your crontab'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
