<?php
/** HR settings: probation and leave rules, the staff portal, the geofence, the entrance QR and the cron token. */
class AdminPulseHrSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('HR Settings');
        $yn = array(array('id' => 1, 'name' => $this->l('Yes')), array('id' => 0, 'name' => $this->l('No')));
        $this->fields_options = array(
            'people' => array('title' => $this->l('People and contracts'), 'icon' => 'icon-user', 'fields' => array(
                'PULSE_HR_STAFF_PREFIX' => array('title' => $this->l('Staff number prefix'), 'type' => 'text'),
                'PULSE_HR_PROBATION_MONTHS' => array('title' => $this->l('Default probation (months)'), 'type' => 'text'),
                'PULSE_HR_PROBATION_REMIND_DAYS' => array('title' => $this->l('Warn me before probation ends (days)'), 'type' => 'text'),
                'PULSE_HR_CONTRACT_REMIND_DAYS' => array('title' => $this->l('Warn me before a fixed-term contract ends (days)'), 'type' => 'text'),
                'PULSE_HR_DOC_REMIND_DAYS' => array('title' => $this->l('Warn me before a document expires (days)'), 'type' => 'text'),
                'PULSE_HR_WARNING_LIFE_MONTHS' => array('title' => $this->l('A warning stops counting after (months)'), 'type' => 'text'),
            ), 'submit' => array('title' => $this->l('Save'))),
            'leave' => array('title' => $this->l('Leave and roster'), 'icon' => 'icon-calendar', 'fields' => array(
                'PULSE_HR_ANNUAL_LEAVE_DAYS' => array('title' => $this->l('Default annual leave (days)'), 'type' => 'text'),
                'PULSE_HR_ACCRUAL_DAY' => array('title' => $this->l('Accrue leave on this day of the month'), 'type' => 'text'),
                'PULSE_HR_LEAVE_DAY_DIVISOR' => array('title' => $this->l('Days a monthly salary divides by (encashment and liability)'), 'type' => 'text'),
                'PULSE_HR_LEAVE_CLASH_WARN' => array('title' => $this->l('Warn when this many are already off in a department'), 'type' => 'text'),
                'PULSE_HR_REST_DAY' => array('title' => $this->l('Weekly rest day (1 Mon … 7 Sun)'), 'type' => 'text'),
                'PULSE_HR_WEEK_START' => array('title' => $this->l('Roster week starts on (1 Mon … 7 Sun)'), 'type' => 'text'),
                'PULSE_HR_SHIFT_MINUTES' => array('title' => $this->l('Minutes in a coverage shift'), 'type' => 'text'),
            ), 'submit' => array('title' => $this->l('Save'))),
            'ess' => array('title' => $this->l('Staff portal'), 'icon' => 'icon-mobile-phone', 'fields' => array(
                'PULSE_HR_ESS_ENABLED' => array('title' => $this->l('Staff portal open'), 'type' => 'select', 'list' => $yn, 'identifier' => 'id'),
                'PULSE_HR_ESS_TTL_MIN' => array('title' => $this->l('Session lifetime (minutes)'), 'type' => 'text'),
                'PULSE_HR_ESS_CLOCK' => array('title' => $this->l('Allow mobile clock in/out'), 'type' => 'select', 'list' => $yn, 'identifier' => 'id'),
                'PULSE_HR_ESS_SELF_UPDATE' => array('title' => $this->l('Allow staff to request detail changes'), 'type' => 'select', 'list' => $yn, 'identifier' => 'id'),
                'PULSE_HR_ESS_SHOW_PAYSLIP' => array('title' => $this->l('Publish payslips to the portal'), 'type' => 'select', 'list' => $yn, 'identifier' => 'id'),
                'PULSE_HR_ESS_PAYSLIP_WINDOW_MIN' => array('title' => $this->l('Payslip stays visible for (minutes) after the PIN is re-entered'), 'type' => 'text'),
                'PULSE_HR_PIN_MIN_LENGTH' => array('title' => $this->l('Minimum PIN length'), 'type' => 'text'),
                'PULSE_HR_ESS_MAX_FAILS' => array('title' => $this->l('Failed sign-ins before a lockout'), 'type' => 'text'),
                'PULSE_HR_ESS_FAIL_WINDOW_MIN' => array('title' => $this->l('Lockout window (minutes)'), 'type' => 'text'),
                'PULSE_HR_ESS_LOGIN_PER_MIN' => array('title' => $this->l('Sign-in attempts per minute per address'), 'type' => 'text'),
                'PULSE_HR_ESS_RATE_PER_MIN' => array('title' => $this->l('Portal calls per minute per session'), 'type' => 'text'),
                'PULSE_HR_ESS_PUNCH_PER_MIN' => array('title' => $this->l('Clock attempts per minute per person'), 'type' => 'text'),
            ), 'submit' => array('title' => $this->l('Save'))),
            'geo' => array('title' => $this->l('Geofence and entrance QR'), 'icon' => 'icon-map-marker', 'fields' => array(
                'PULSE_HR_GEO_LAT' => array('title' => $this->l('Site latitude'), 'type' => 'text'),
                'PULSE_HR_GEO_LNG' => array('title' => $this->l('Site longitude'), 'type' => 'text'),
                'PULSE_HR_GEO_RADIUS_M' => array('title' => $this->l('Geofence radius (metres)'), 'type' => 'text'),
                'PULSE_HR_GEO_ENFORCE' => array('title' => $this->l('Refuse a punch from outside the fence'), 'type' => 'select', 'list' => $yn, 'identifier' => 'id', 'desc' => $this->l('Off: the punch is still recorded but flagged for a supervisor. Either way the coordinates and accuracy are stored.')),
                'PULSE_HR_GEO_MAX_ACCURACY_M' => array('title' => $this->l('Flag a punch when GPS accuracy is worse than (metres)'), 'type' => 'text'),
                'PULSE_HR_GEO_QR_WAIVES' => array('title' => $this->l('A valid entrance QR waives the geofence'), 'type' => 'select', 'list' => $yn, 'identifier' => 'id'),
                'PULSE_HR_ESS_QR_CODE' => array('title' => $this->l('Static entrance QR code'), 'type' => 'text'),
                'PULSE_HR_ESS_QR_ROTATE_MIN' => array('title' => $this->l('Rotate the QR every (minutes, 0 = printed static code)'), 'type' => 'text'),
                'PULSE_HR_PUNCH_DEDUPE_SEC' => array('title' => $this->l('Ignore a repeated punch within (seconds)'), 'type' => 'text'),
                'PULSE_HR_PUNCH_GRACE_MIN' => array('title' => $this->l('Match a punch to a shift within (minutes)'), 'type' => 'text'),
                'PULSE_HR_MAX_SHIFT_HOURS' => array('title' => $this->l('Longest believable shift (hours)'), 'type' => 'text'),
            ), 'submit' => array('title' => $this->l('Save'))),
        );
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array(
            'cron_token' => Configuration::get('PULSE_HR_CRON_TOKEN'),
            'cron_url' => Tools::getShopDomainSsl(true).__PS_BASE_URI__.'modules/pulsehr/cron/hr.php?token='.Configuration::get('PULSE_HR_CRON_TOKEN'),
            'ess_url' => $this->context->link->getModuleLink('pulsehr', 'ess', array(), true),
            'qr_code' => PulseHrEss::qrCode(), 'qr_rotates' => (int) PulseHrService::cfg('ESS_QR_ROTATE_MIN', 0),
            'sections' => PulseHrEss::sections(), 'kc' => PulseHrService::kc(), 'pos' => PulseHrService::pos(), 'ta' => PulseHrService::ta(), 'pr' => PulseHrService::pr(), 'fd' => PulseHrService::fd(),
            'sessions' => Db::getInstance()->executeS('SELECT s.*, e.staff_no, CONCAT(e.firstname," ",e.lastname) employee_name FROM `'._DB_PREFIX_.'pulse_hr_ess_session` s
                INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=s.id_pulse_hr_employee WHERE s.revoked=0 AND s.expires_at>NOW() ORDER BY s.date_upd DESC LIMIT 30'),
            'fails' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_ess_login` WHERE ok=0 AND date_add>DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY date_add DESC LIMIT 30'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->content .= $this->context->smarty->fetch($this->getTemplatePath().'pulse_hr_settings/settings.tpl');
        $this->context->smarty->assign('content', $this->content);
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('newCronToken')) { Configuration::updateValue('PULSE_HR_CRON_TOKEN', Tools::passwdGen(32)); $this->confirmations[] = $this->l('New cron token issued'); }
            if (Tools::isSubmit('newQr')) { Configuration::updateValue('PULSE_HR_ESS_QR_CODE', Tools::passwdGen(10)); $this->confirmations[] = $this->l('New entrance QR code issued — print and post it at the staff entrance'); }
            if (Tools::isSubmit('endSession')) { PulseHrEss::revoke((int) Tools::getValue('id_session'), 'admin'); $this->confirmations[] = $this->l('Session ended'); }
            if (Tools::isSubmit('endAllSessions')) { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_ess_session` SET revoked=1, revoke_reason="admin", date_upd=NOW() WHERE revoked=0'); $this->confirmations[] = $this->l('Every staff portal session has been ended'); }
            if (Tools::isSubmit('purgeSessions')) { PulseHrEss::purge(0); $this->confirmations[] = $this->l('Expired sessions and old sign-in logs purged'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
