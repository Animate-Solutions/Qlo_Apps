<?php
/** The weekly roster grid: plan, copy last week, publish to the staff portal, handle swaps and watch coverage against occupancy. */
class AdminPulseHrRosterController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Roster'); }

    public function initContent()
    {
        parent::initContent();
        $week = PulseHrRoster::weekStart(Tools::getValue('week', PulseHrService::bd()));
        $end = date('Y-m-d', strtotime($week.' +6 day'));
        $dept = Tools::getValue('department');
        $grid = PulseHrRoster::grid($week, $end, $dept);
        $cover = array();
        foreach ($grid['dates'] as $d) { $cover[$d] = PulseHrService::coverage($d, $dept); }
        $this->context->smarty->assign(array(
            'grid' => $grid, 'cover' => $cover, 'shifts' => PulseHrService::shifts(), 'departments' => PulseHrService::departments(),
            'department' => $dept, 'week' => $week, 'week_end' => $end,
            'prev_week' => date('Y-m-d', strtotime($week.' -7 day')), 'next_week' => date('Y-m-d', strtotime($week.' +7 day')),
            'swaps' => PulseHrRoster::swaps('pending,accepted'), 'business_date' => PulseHrService::bd(),
            'published' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_roster` WHERE roster_date BETWEEN "'.pSQL($week).'" AND "'.pSQL($end).'" AND status="published"'.($dept ? ' AND department="'.pSQL($dept).'"' : '')),
            'planned' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_roster` WHERE roster_date BETWEEN "'.pSQL($week).'" AND "'.pSQL($end).'" AND status="planned"'.($dept ? ' AND department="'.pSQL($dept).'"' : '')),
            'fd' => PulseHrService::fd(), 'self_url' => self::$currentIndex.'&token='.$this->token,
            'employee_url' => $this->context->link->getAdminLink('AdminPulseHrEmployees'),
        ));
        $this->setTemplate('roster.tpl');
    }

    public function postProcess()
    {
        $week = PulseHrRoster::weekStart(Tools::getValue('week', PulseHrService::bd()));
        $end = date('Y-m-d', strtotime($week.' +6 day'));
        try {
            if (Tools::isSubmit('saveGrid')) {
                $cells = Tools::getValue('cell'); $n = 0; $errs = array();
                if (is_array($cells)) {
                    foreach ($cells as $idEmployee => $days) {
                        foreach ((array) $days as $date => $val) {
                            try { PulseHrRoster::set((int) $idEmployee, $date, $val === 'off' ? 0 : (int) $val, $val === 'off' ? 1 : 0); $n++; }
                            catch (Exception $e) { $errs[] = $e->getMessage(); }
                        }
                    }
                }
                $this->confirmations[] = $n.' '.$this->l('roster cell(s) saved');
                foreach (array_unique($errs) as $e) { $this->warnings[] = $e; }
            }
            if (Tools::isSubmit('copyWeek')) { $n = PulseHrRoster::copyWeek(date('Y-m-d', strtotime($week.' -7 day')), $week, Tools::getValue('department')); $this->confirmations[] = $n.' '.$this->l('shift(s) copied from last week'); }
            if (Tools::isSubmit('clearWeek')) { PulseHrRoster::clearWeek($week, $end, Tools::getValue('department')); $this->confirmations[] = $this->l('Unpublished shifts cleared'); }
            if (Tools::isSubmit('publishWeek')) { $n = PulseHrRoster::publish($week, $end, Tools::getValue('department')); $this->confirmations[] = $n.' '.$this->l('shift(s) published — staff can see them on the portal'); }
            if (Tools::isSubmit('decideSwap')) { $out = PulseHrRoster::decideSwap((int) Tools::getValue('id_swap'), Tools::getValue('decision'), Tools::getValue('swap_note')); $this->confirmations[] = $this->l('Swap').' '.$out; }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
