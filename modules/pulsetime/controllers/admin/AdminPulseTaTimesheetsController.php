<?php
/** Timesheets — the daily grid, per-person totals, the approval periods that lock a range for payroll. */
class AdminPulseTaTimesheetsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Timesheets'); }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to change timesheets')); }
    }

    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-m-01'));
        $to = Tools::getValue('to', PulseTaService::bd());
        $dept = Tools::getValue('department', '');
        if ($idStaff = (int) Tools::getValue('id_staff')) {
            $days = array();
            foreach (PulseTaTimesheet::grid($from, $to, '', '') as $r) { if ((int) $r['id_pulse_ta_staff'] === $idStaff) { $days[] = $r; } }
            $detail = array();
            foreach ($days as $d) { $detail[$d['business_date']] = array('punches' => PulseTaTimesheet::punches((int) $d['id_pulse_ta_timesheet']), 'trail' => PulseTaExceptionQueue::adjustmentTrail($idStaff, $d['business_date'])); }
            $this->context->smarty->assign(array('staff' => PulseTaService::staff($idStaff), 'days' => $days, 'detail' => $detail,
                'from' => $from, 'to' => $to, 'self_url' => self::$currentIndex.'&token='.$this->token));
            return $this->setTemplate('timesheet.tpl');
        }
        $this->context->smarty->assign(array(
            'grid' => PulseTaTimesheet::grid($from, $to, $dept, Tools::getValue('status', '')),
            'totals' => PulseTaTimesheet::totals($from, $to, $dept), 'by_dept' => PulseTaTimesheet::byDepartment($from, $to),
            'periods' => PulseTaTimesheet::periods(30), 'departments' => PulseTaService::departments(),
            'from' => $from, 'to' => $to, 'department' => $dept, 'status' => Tools::getValue('status', ''),
            'labour' => PulseTaService::labourPerOccupiedRoom($from, $to), 'counts' => PulseTaExceptionQueue::counts(),
            'extract' => PulseTaTimesheet::payrollExtract($from, $to, $dept),
            'statuses' => array('present', 'late', 'absent', 'incomplete', 'rest', 'leave', 'holiday', 'off_site'),
            'exceptions_url' => $this->context->link->getAdminLink('AdminPulseTaExceptions'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('timesheets.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('rebuildRange')) {
                $this->assertEdit();
                $r = PulseTaEngine::buildRange(Tools::getValue('from'), Tools::getValue('to'), Tools::getValue('department', ''));
                $this->confirmations[] = sprintf($this->l('%d day(s) rebuilt, %d timesheet(s), %d exception(s) open.'), $r['days'], $r['timesheets'], $r['exceptions']);
                foreach (array_slice($r['errors'], 0, 5) as $e) { $this->errors[] = $e; }
            }
            if (Tools::isSubmit('createPeriod')) {
                $this->assertEdit();
                $id = PulseTaTimesheet::createPeriod(Tools::getValue('p_name'), Tools::getValue('p_from'), Tools::getValue('p_to'), Tools::getValue('p_department', ''));
                $this->confirmations[] = $this->l('Period created').' (#'.$id.')';
            }
            if (Tools::isSubmit('rebuildPeriod')) { $this->assertEdit(); $r = PulseTaTimesheet::rebuildPeriod((int) Tools::getValue('id_period')); $this->confirmations[] = sprintf($this->l('Period rebuilt: %d day(s), %d timesheet(s).'), $r['days'], $r['timesheets']); }
            if (Tools::isSubmit('submitPeriod')) { $this->assertEdit(); PulseTaTimesheet::submitPeriod((int) Tools::getValue('id_period'), Tools::getValue('p_note', '')); $this->confirmations[] = $this->l('Period submitted for approval.'); }
            if (Tools::isSubmit('approvePeriod')) {
                $this->assertEdit();
                $r = PulseTaTimesheet::approvePeriod((int) Tools::getValue('id_period'), (int) Tools::getValue('force') === 1);
                $this->confirmations[] = sprintf($this->l('Period approved and locked — %d timesheet(s). Payroll may now read it.'), $r['timesheets']);
            }
            if (Tools::isSubmit('reopenPeriod')) { $this->assertEdit(); PulseTaTimesheet::reopenPeriod((int) Tools::getValue('id_period'), Tools::getValue('reopen_reason')); $this->confirmations[] = $this->l('Period reopened. The reason is on the audit trail.'); }
            if (Tools::isSubmit('lockDay')) { $this->assertEdit(); PulseTaTimesheet::lockDay((int) Tools::getValue('ld_staff'), Tools::getValue('ld_date'), (int) Tools::getValue('ld_lock') === 1); $this->confirmations[] = $this->l('Day updated.'); }
            if (Tools::isSubmit('exportCsv')) { $this->exportCsv(Tools::getValue('from'), Tools::getValue('to'), Tools::getValue('department', '')); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    /** Per-person totals as CSV — the file a payroll clerk hands the accountant. */
    protected function exportCsv($from, $to, $dept)
    {
        $rows = PulseTaTimesheet::totals($from, $to, $dept);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pulse-timesheets-'.$from.'-to-'.$to.'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('Staff no', 'Name', 'Department', 'Position', 'Pay basis', 'Days', 'Days worked', 'Days absent', 'Days on leave',
            'Worked hours', 'Overtime hours', 'Weighted OT hours', 'Night hours', 'Late minutes', 'Short minutes', 'All locked', 'Days with exceptions'));
        foreach ((array) $rows as $r) {
            fputcsv($out, array($r['staff_no'], $r['staff_name'], $r['department'], $r['position'], $r['pay_basis'], (int) $r['days'], (int) $r['days_worked'],
                (int) $r['days_absent'], (int) $r['days_leave'], round($r['worked_minutes'] / 60, 2), round($r['ot_minutes'] / 60, 2),
                round($r['ot_weighted_minutes'] / 60, 2), round($r['night_minutes'] / 60, 2), (int) $r['late_minutes'], (int) $r['short_minutes'],
                (int) $r['all_locked'] ? 'yes' : 'NO', (int) $r['with_exceptions']));
        }
        fclose($out);
        die();
    }
}
