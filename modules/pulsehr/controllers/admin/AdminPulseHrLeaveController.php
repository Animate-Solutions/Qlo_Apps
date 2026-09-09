<?php
/** Leave: the approval queue, the departmental calendar, balances, types and entitlements, blackouts and the liability report. */
class AdminPulseHrLeaveController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Leave'); }

    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-m-01'));
        $to = Tools::getValue('to', date('Y-m-t', strtotime($from)));
        $dept = Tools::getValue('department');
        $cal = PulseHrLeave::calendar($from, $to, $dept);
        $this->context->smarty->assign(array(
            'pending' => PulseHrLeave::pending(), 'recent' => PulseHrLeave::requests('approved,rejected,cancelled,taken', $dept, 0, 60),
            'calendar' => $cal, 'cal_dates' => PulseHrRoster::rangeDates($from, $to), 'on_leave' => PulseHrLeave::onLeave(),
            'types' => PulseHrLeave::types(false), 'entitlements' => PulseHrLeave::entitlements(), 'grades' => PulseHrService::grades(),
            'blackouts' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_blackout` ORDER BY date_from DESC'),
            'liability' => PulseHrLeave::liability((int) Tools::getValue('year', date('Y'))), 'departments' => PulseHrService::departments(),
            'staff' => PulseHrEmployee::search(array('limit' => 400)), 'from' => $from, 'to' => $to, 'department' => $dept,
            'business_date' => PulseHrService::bd(), 'occupancy' => PulseHrService::occupancyPct(PulseHrService::bd()),
            'self_url' => self::$currentIndex.'&token='.$this->token,
            'employee_url' => $this->context->link->getAdminLink('AdminPulseHrEmployees'),
        ));
        $this->setTemplate('leave.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('newRequest')) {
                $r = PulseHrLeave::request(array('id_pulse_hr_employee' => (int) Tools::getValue('id_employee_hr'), 'id_pulse_hr_leave_type' => (int) Tools::getValue('id_leave_type'),
                    'date_from' => Tools::getValue('date_from'), 'date_to' => Tools::getValue('date_to'), 'half_day' => Tools::getValue('half_day'),
                    'reason' => Tools::getValue('reason'), 'id_relief' => (int) Tools::getValue('id_relief'), 'contact_phone' => Tools::getValue('contact_phone'),
                    'address_on_leave' => Tools::getValue('address_on_leave'), 'source' => 'admin'));
                $this->confirmations[] = $this->l('Request').' '.$r['request_no'].' — '.$r['days'].' '.$this->l('day(s)');
                foreach ($r['warnings'] as $w) { $this->warnings[] = $w; }
            }
            if (Tools::isSubmit('decide')) { $out = PulseHrLeave::decide((int) Tools::getValue('id_request'), Tools::getValue('decision'), Tools::getValue('comment')); $this->confirmations[] = $this->l('Request is now').' '.$out; }
            if (Tools::isSubmit('cancelRequest')) { PulseHrLeave::cancel((int) Tools::getValue('id_request'), Tools::getValue('comment')); $this->confirmations[] = $this->l('Request cancelled'); }
            if (Tools::isSubmit('saveType')) {
                PulseHrLeave::saveType(array('code' => Tools::getValue('code'), 'name' => Tools::getValue('name'), 'paid' => Tools::getValue('paid'), 'accrual' => Tools::getValue('accrual'),
                    'days_per_year' => Tools::getValue('days_per_year'), 'carry_over_cap' => Tools::getValue('carry_over_cap'), 'max_consecutive' => Tools::getValue('max_consecutive'),
                    'min_service_months' => Tools::getValue('min_service_months'), 'gender' => Tools::getValue('gender'), 'working_days_only' => Tools::getValue('working_days_only'),
                    'requires_document' => Tools::getValue('requires_document'), 'encashable' => Tools::getValue('encashable'), 'colour' => Tools::getValue('colour'),
                    'sort' => Tools::getValue('sort'), 'active' => Tools::getValue('active', 1)), (int) Tools::getValue('id_type'));
                $this->confirmations[] = $this->l('Leave type saved');
            }
            if (Tools::isSubmit('saveEntitlement')) { PulseHrLeave::saveEntitlement((int) Tools::getValue('ent_type'), (int) Tools::getValue('ent_grade'), (float) Tools::getValue('ent_days')); $this->confirmations[] = $this->l('Entitlement saved'); }
            if (Tools::isSubmit('saveBlackout')) {
                PulseHrLeave::saveBlackout(array('name' => Tools::getValue('bname'), 'department' => Tools::getValue('bdept'), 'date_from' => Tools::getValue('bfrom'), 'date_to' => Tools::getValue('bto'),
                    'max_off' => Tools::getValue('max_off'), 'min_occupancy_pct' => Tools::getValue('min_occupancy_pct'), 'reason' => Tools::getValue('breason'), 'active' => Tools::getValue('bactive', 1)), (int) Tools::getValue('id_blackout'));
                $this->confirmations[] = $this->l('Blackout saved');
            }
            if (Tools::isSubmit('encash')) { $r = PulseHrLeave::encash((int) Tools::getValue('id_employee_hr'), (int) Tools::getValue('id_leave_type'), (float) Tools::getValue('enc_days')); $this->confirmations[] = $this->l('Encashed').' '.$r['days'].' '.$this->l('day(s)'); }
            if (Tools::isSubmit('carryOver')) { $n = PulseHrLeave::carryOver((int) Tools::getValue('co_year')); $this->confirmations[] = $n.' '.$this->l('balance(s) carried forward'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
