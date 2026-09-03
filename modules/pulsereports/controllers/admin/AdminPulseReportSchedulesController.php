<?php
class AdminPulseReportSchedulesController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Scheduled Reports'); }
    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array('schedules' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_report_schedule` ORDER BY id_pulse_report_schedule'), 'log' => Db::getInstance()->executeS('SELECT id_pulse_report_log, report, business_date, recipients, status, error, date_add FROM `'._DB_PREFIX_.'pulse_report_log` ORDER BY id_pulse_report_log DESC LIMIT 30'),
            'self_url' => self::$currentIndex.'&token='.$this->token, 'cron_url' => Tools::getShopDomainSsl(true).__PS_BASE_URI__.'modules/pulsereports/cron/send.php?token='.Configuration::get('PULSE_RPT_CRON_TOKEN'), 'bd' => date('Y-m-d', strtotime(PulseCoreService::businessDate().' -1 day')), 'sms_ok' => class_exists('PulseComms')));
        if ($id = (int) Tools::getValue('view_log')) { $this->context->smarty->assign('log_html', Db::getInstance()->getValue('SELECT html FROM `'._DB_PREFIX_.'pulse_report_log` WHERE id_pulse_report_log='.$id)); }
        $this->setTemplate('schedules.tpl');
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveSchedule')) { $id = (int) Tools::getValue('id_schedule'); $d = array('name' => pSQL(Tools::getValue('name')), 'report' => pSQL(Tools::getValue('report')), 'recipients_email' => pSQL(Tools::getValue('recipients_email')), 'recipients_sms' => pSQL(Tools::getValue('recipients_sms')), 'send_time' => pSQL(Tools::getValue('send_time')), 'send_after_audit' => (int) Tools::getValue('send_after_audit'), 'weekday' => (int) Tools::getValue('weekday') ?: null, 'month_day' => (int) Tools::getValue('month_day') ?: null, 'active' => (int) Tools::getValue('active')); if ($id) { Db::getInstance()->update('pulse_report_schedule', $d, 'id_pulse_report_schedule='.$id); } else { $d['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_report_schedule', $d); } $this->confirmations[] = $this->l('Schedule saved'); }
            if (Tools::isSubmit('sendNow')) { $ok = PulseOwnerSnapshot::send((int) Tools::getValue('id_schedule'), Tools::getValue('bd'), true); $ok ? $this->confirmations[] = $this->l('Report sent') : $this->errors[] = $this->l('Send failed — see the log'); }
            if (Tools::isSubmit('preview')) { $s = Db::getInstance()->getRow('SELECT report FROM `'._DB_PREFIX_.'pulse_report_schedule` WHERE id_pulse_report_schedule='.(int) Tools::getValue('id_schedule')); $r = PulseOwnerSnapshot::build($s['report'], Tools::getValue('bd')); die($r['html']); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
