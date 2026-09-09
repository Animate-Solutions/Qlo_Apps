<?php
/** Exceptions — the supervisor's queue. Every fix here writes an approved adjustment, never an edited punch. */
class AdminPulseTaExceptionsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Exceptions'); }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to resolve exceptions')); }
    }

    public function initContent()
    {
        parent::initContent();
        if ($id = (int) Tools::getValue('id_exception')) {
            $e = PulseTaExceptionQueue::get($id);
            $ts = $e && $e['id_pulse_ta_timesheet'] ? PulseTaTimesheet::punches((int) $e['id_pulse_ta_timesheet']) : array();
            $this->context->smarty->assign(array('e' => $e, 'punches' => $ts,
                'trail' => $e && $e['id_pulse_ta_staff'] ? PulseTaExceptionQueue::adjustmentTrail((int) $e['id_pulse_ta_staff'], $e['business_date']) : array(),
                'pos' => $e && $e['id_pulse_ta_staff'] ? PulseTaPunch::posClock((int) $e['id_pulse_ta_staff'], $e['business_date'].' 00:00:00', date('Y-m-d', strtotime($e['business_date'].' +1 day')).' 12:00:00') : array(),
                'self_url' => self::$currentIndex.'&token='.$this->token));
            return $this->setTemplate('exception.tpl');
        }
        $f = array('status' => Tools::getValue('status', 'open'), 'type' => Tools::getValue('type', ''), 'severity' => Tools::getValue('severity', ''),
            'department' => Tools::getValue('department', ''), 'from' => Tools::getValue('from', date('Y-m-d', strtotime('-30 day'))), 'to' => Tools::getValue('to', PulseTaService::bd()));
        $this->context->smarty->assign(array(
            'rows' => PulseTaExceptionQueue::search($f, 400), 'f' => $f, 'counts' => PulseTaExceptionQueue::counts(),
            'types' => PulseTaExceptionQueue::types(), 'departments' => PulseTaService::departments(),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('exceptions.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('resolveException')) {
                $this->assertEdit();
                $how = Tools::getValue('how');
                $at = trim((string) Tools::getValue('adj_date').' '.(string) Tools::getValue('adj_time'));
                $r = PulseTaExceptionQueue::resolve((int) Tools::getValue('id_exception_r'), $how, array(
                    'reason' => Tools::getValue('reason'), 'punched_at' => trim($at) !== '' ? $at : null,
                    'direction' => Tools::getValue('adj_direction', 'unknown'), 'minutes' => (int) Tools::getValue('adj_minutes'),
                    'id_punch' => (int) Tools::getValue('adj_id_punch') ?: null));
                $this->confirmations[] = $this->l('Exception resolved, adjustment recorded and the timesheet rebuilt.').($r['id_adjustment'] ? ' (#'.$r['id_adjustment'].')' : '');
            }
            if (Tools::isSubmit('bulkWaive')) {
                $this->assertEdit();
                $reason = trim((string) Tools::getValue('bulk_reason'));
                if ($reason === '') { throw new PrestaShopException($this->l('A reason is required to waive exceptions')); }
                $n = 0;
                foreach ((array) Tools::getValue('ex', array()) as $idEx) {
                    try { PulseTaExceptionQueue::resolve((int) $idEx, 'waive', array('reason' => $reason)); $n++; }
                    catch (Exception $ex) { $this->errors[] = '#'.(int) $idEx.': '.$ex->getMessage(); }
                }
                $this->confirmations[] = sprintf($this->l('%d exception(s) waived.'), $n);
            }
            if (Tools::isSubmit('voidAdjustment')) {
                $this->assertEdit();
                PulseTaExceptionQueue::voidAdjustment((int) Tools::getValue('id_adjustment'), Tools::getValue('void_reason'));
                $this->confirmations[] = $this->l('Adjustment voided (it stays on file) and the day rebuilt.');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
