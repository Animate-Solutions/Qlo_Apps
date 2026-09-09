<?php
/** Overtime & Roster — shift definitions, the weekly roster grid, overtime rules and the public holiday calendar. */
class AdminPulseTaOvertimeController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Overtime & Roster'); }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to change the roster')); }
    }

    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('week_from', PulseTaEngine::weekStart(date('Y-m-d')));
        $days = (int) Tools::getValue('days', 7);
        $dept = Tools::getValue('department', '');
        $repFrom = Tools::getValue('from', date('Y-m-01'));
        $repTo = Tools::getValue('to', PulseTaService::bd());
        $this->context->smarty->assign(array(
            'week' => PulseTaRoster::week($from, $days, $dept), 'coverage' => PulseTaRoster::coverage($from, $days, $dept),
            'holidays' => PulseTaRoster::holidays($from, date('Y-m-d', strtotime($from.' +'.$days.' day'))),
            'all_holidays' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_holiday` WHERE holiday_date>=DATE_SUB(CURDATE(), INTERVAL 90 DAY) ORDER BY holiday_date LIMIT 80'),
            'shifts' => PulseTaService::shifts(false), 'rules' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_ot_rule` ORDER BY sort, code'),
            'overtime' => PulseTaTimesheet::overtime($repFrom, $repTo, $dept), 'departments' => PulseTaService::departments(),
            'week_from' => $from, 'days' => $days, 'department' => $dept, 'from' => $repFrom, 'to' => $repTo,
            'staff' => PulseTaService::staffList($dept), 'edit_shift' => (int) Tools::getValue('id_shift') ? PulseTaService::shift((int) Tools::getValue('id_shift')) : null,
            'ot_approval' => (int) PulseTaService::cfg('OT_APPROVAL', 1),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('overtime.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveShift')) {
                $this->assertEdit();
                $d = array('code' => pSQL(Tools::strtoupper(Tools::substr(trim((string) Tools::getValue('code')), 0, 16))), 'name' => pSQL(Tools::getValue('sname')),
                    'department' => pSQL(Tools::getValue('sdepartment', '')), 'start_time' => pSQL(Tools::getValue('start_time', '08:00').':00'),
                    'end_time' => pSQL(Tools::getValue('end_time', '17:00').':00'), 'crosses_midnight' => (int) Tools::getValue('crosses_midnight'),
                    'is_night' => (int) Tools::getValue('is_night'), 'break_minutes' => (int) Tools::getValue('break_minutes'),
                    'break_paid' => (int) Tools::getValue('break_paid'), 'break_punched' => (int) Tools::getValue('break_punched'),
                    'grace_in_min' => (int) Tools::getValue('grace_in_min'), 'grace_out_min' => (int) Tools::getValue('grace_out_min'),
                    'window_before_min' => (int) Tools::getValue('window_before_min'), 'window_after_min' => (int) Tools::getValue('window_after_min'),
                    'min_shift_min' => (int) Tools::getValue('min_shift_min'), 'max_shift_min' => (int) Tools::getValue('max_shift_min'),
                    'paid_minutes' => (int) Tools::getValue('paid_minutes'), 'colour' => pSQL(Tools::substr((string) Tools::getValue('colour', '#2e86c1'), 0, 7)),
                    'active' => (int) Tools::getValue('sactive', 1), 'sort' => (int) Tools::getValue('sort'), 'date_upd' => date('Y-m-d H:i:s'));
                if ($d['code'] === '' || $d['name'] === '') { throw new PrestaShopException($this->l('A shift needs a code and a name')); }
                if ($d['crosses_midnight'] && strtotime('2000-01-01 '.$d['end_time']) > strtotime('2000-01-01 '.$d['start_time'])) { $this->warnings[] = $this->l('This shift is flagged as crossing midnight but its end time is later in the day than its start — check it.'); }
                if ($id = (int) Tools::getValue('id_shift_save')) { Db::getInstance()->update('pulse_ta_shift', $d, 'id_pulse_ta_shift='.$id); }
                else { $d['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_ta_shift', $d); $id = (int) Db::getInstance()->Insert_ID(); }
                PulseTaService::audit('shift_save', array('id' => $id, 'code' => $d['code']), 'pulse_ta_shift', $id);
                $this->confirmations[] = $this->l('Shift saved').' ('.$d['code'].')';
            }
            if (Tools::isSubmit('setRoster')) {
                $this->assertEdit();
                $n = 0;
                foreach ((array) Tools::getValue('cell', array()) as $idStaff => $dates) {
                    foreach ((array) $dates as $date => $val) {
                        if ($val === '' || !strtotime($date)) { continue; }
                        if ($val === 'REST') { PulseTaRoster::set((int) $idStaff, $date, null, 'rest'); }
                        elseif ($val === 'LEAVE') { PulseTaRoster::set((int) $idStaff, $date, null, 'leave'); }
                        elseif ($val === 'CLEAR') { Db::getInstance()->delete('pulse_ta_roster', 'id_pulse_ta_staff='.(int) $idStaff.' AND roster_date="'.pSQL($date).'"'); }
                        else { PulseTaRoster::set((int) $idStaff, $date, (int) $val, 'work'); }
                        $n++;
                    }
                }
                PulseTaService::audit('roster_save', array('cells' => $n));
                PulseCoreService::event('actionPulseHrRosterPublished', array('date_from' => Tools::getValue('week_from'), 'date_to' => date('Y-m-d', strtotime(Tools::getValue('week_from').' +6 day')), 'source' => 'pulsetime'));
                $this->confirmations[] = sprintf($this->l('%d roster cell(s) saved.'), $n);
            }
            if (Tools::isSubmit('applyPattern')) {
                $this->assertEdit();
                $pattern = array_filter(array_map('trim', explode(',', (string) Tools::getValue('pattern'))));
                $staffIds = array_map('intval', (array) Tools::getValue('pstaff', array()));
                if (!$staffIds) { throw new PrestaShopException($this->l('Select at least one staff member')); }
                $n = PulseTaRoster::applyPattern($staffIds, Tools::getValue('pat_from'), Tools::getValue('pat_to'), $pattern, (int) Tools::getValue('publish', 1));
                $this->confirmations[] = sprintf($this->l('%d roster cell(s) written from the pattern.'), $n);
            }
            if (Tools::isSubmit('importHrRoster')) {
                $n = PulseTaRoster::importFromHr(Tools::getValue('week_from'), date('Y-m-d', strtotime(Tools::getValue('week_from').' +30 day')));
                $this->confirmations[] = PulseTaService::hr() ? sprintf($this->l('%d roster row(s) mirrored from Pulse HR.'), $n) : $this->l('Pulse HR is not installed — the grid on this screen is the roster.');
            }
            if (Tools::isSubmit('saveRule')) {
                $this->assertEdit();
                $d = array('code' => pSQL(Tools::strtoupper(Tools::substr(trim((string) Tools::getValue('rcode')), 0, 24))), 'name' => pSQL(Tools::getValue('rname')),
                    'scope' => pSQL(Tools::getValue('scope', 'daily')), 'department' => pSQL(Tools::getValue('rdepartment', '')),
                    'threshold_minutes' => (int) Tools::getValue('threshold_minutes'), 'multiplier' => round((float) Tools::getValue('multiplier'), 3),
                    'cap_minutes' => (int) Tools::getValue('cap_minutes'), 'requires_approval' => (int) Tools::getValue('requires_approval'),
                    'effective_from' => pSQL(Tools::getValue('effective_from', '2000-01-01')),
                    'effective_to' => Tools::getValue('effective_to') ? pSQL(Tools::getValue('effective_to')) : null,
                    'sort' => (int) Tools::getValue('rsort'), 'active' => (int) Tools::getValue('ractive', 1));
                if ($d['code'] === '') { throw new PrestaShopException($this->l('An overtime rule needs a code')); }
                if ($d['multiplier'] <= 0) { throw new PrestaShopException($this->l('The multiplier must be greater than zero')); }
                if ($id = (int) Tools::getValue('id_rule')) { Db::getInstance()->update('pulse_ta_ot_rule', PulseTaService::nulls($d), 'id_pulse_ta_ot_rule='.$id); }
                else { Db::getInstance()->insert('pulse_ta_ot_rule', PulseTaService::nulls($d)); $id = (int) Db::getInstance()->Insert_ID(); }
                PulseTaService::audit('ot_rule_save', $d, 'pulse_ta_ot_rule', $id);
                $this->confirmations[] = $this->l('Overtime rule saved. Rebuild the affected days to apply it — an effective date change is a data edit, never a code release.');
            }
            if (Tools::isSubmit('saveHoliday')) {
                $this->assertEdit();
                $d = array('holiday_date' => pSQL(Tools::getValue('holiday_date')), 'name' => pSQL(Tools::substr((string) Tools::getValue('hname'), 0, 96)),
                    'country' => pSQL(Tools::substr((string) Tools::getValue('country', 'NG'), 0, 2)), 'type' => pSQL(Tools::getValue('htype', 'public')),
                    'multiplier' => round((float) Tools::getValue('hmultiplier', 2), 3), 'confirmed' => (int) Tools::getValue('confirmed'),
                    'note' => pSQL(Tools::substr((string) Tools::getValue('hnote'), 0, 160)), 'active' => (int) Tools::getValue('hactive', 1));
                if (!strtotime($d['holiday_date'])) { throw new PrestaShopException($this->l('Choose a valid date')); }
                if ($id = (int) Tools::getValue('id_holiday')) { Db::getInstance()->update('pulse_ta_holiday', $d, 'id_pulse_ta_holiday='.$id); }
                else { Db::getInstance()->insert('pulse_ta_holiday', $d, false, true, Db::INSERT_IGNORE); $id = (int) Db::getInstance()->Insert_ID(); }
                PulseTaService::audit('holiday_save', $d, 'pulse_ta_holiday', $id);
                $this->confirmations[] = $this->l('Holiday saved.');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
