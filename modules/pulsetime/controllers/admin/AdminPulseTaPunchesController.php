<?php
/** Punches — the append-only register, its filters, one punch's evidence, and the manual-entry form. */
class AdminPulseTaPunchesController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Punches'); }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to record punches')); }
    }

    public function initContent()
    {
        parent::initContent();
        if ($id = (int) Tools::getValue('id_punch')) {
            $p = PulseTaPunch::get($id);
            $this->context->smarty->assign(array('p' => $p, 'self_url' => self::$currentIndex.'&token='.$this->token,
                'timesheet' => $p && $p['id_pulse_ta_staff'] ? PulseTaTimesheet::get((int) $p['id_pulse_ta_staff'], $p['business_date']) : null));
            return $this->setTemplate('punch.tpl');
        }
        $f = array('from' => Tools::getValue('from', date('Y-m-d', strtotime('-2 day'))), 'to' => Tools::getValue('to', PulseTaService::bd()),
            'id_staff' => (int) Tools::getValue('id_staff'), 'id_device' => (int) Tools::getValue('id_device'),
            'department' => Tools::getValue('department', ''), 'source' => Tools::getValue('source', ''),
            'unmatched' => (int) Tools::getValue('unmatched'), 'q' => Tools::getValue('q', ''));
        $this->context->smarty->assign(array(
            'rows' => PulseTaPunch::search($f, (int) Tools::getValue('limit', 300)), 'f' => $f,
            'devices' => PulseTaDevice::all(), 'staff' => PulseTaService::staffList(), 'departments' => PulseTaService::departments(),
            'sources' => array('device' => 'Biometric device', 'mobile' => 'Mobile / portal', 'manual' => 'Keyed by a supervisor', 'import' => 'File import', 'pos' => 'POS clock', 'adjustment' => 'Approved adjustment'),
            'unmatched_list' => PulseTaPunch::unmatched(30), 'business_date' => PulseTaService::bd(),
            'enrolment_url' => $this->context->link->getAdminLink('AdminPulseTaEnrolment'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('punches.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('addPunch')) {
                $this->assertEdit();
                $at = trim((string) Tools::getValue('punch_date').' '.(string) Tools::getValue('punch_time'));
                $id = PulseTaPunch::manual((int) Tools::getValue('id_staff_new'), $at, Tools::getValue('direction', 'unknown'), 'manual');
                if (!$id) { throw new PrestaShopException($this->l('That punch already exists — punches are de-duplicated on device, person and time.')); }
                PulseTaEngine::buildOne((int) Tools::getValue('id_staff_new'), date('Y-m-d', strtotime($at)));
                $this->confirmations[] = $this->l('Punch recorded (source: manual) and the timesheet rebuilt.');
            }
            if (Tools::isSubmit('mapRef')) {
                $this->assertEdit();
                $rows = PulseTaEnrolment::map((int) Tools::getValue('map_device'), Tools::getValue('map_ref'), (int) Tools::getValue('map_staff'));
                $this->confirmations[] = sprintf($this->l('Mapped — %d punch(es) re-attributed. Rebuild the affected days from Timesheets.'), $rows);
            }
            if (Tools::isSubmit('rebuildFor')) {
                $r = PulseTaEngine::buildOne((int) Tools::getValue('rb_staff'), Tools::getValue('rb_date'));
                $this->confirmations[] = isset($r['skipped']) ? $this->l('Not rebuilt: ').$r['skipped'] : $this->l('Timesheet rebuilt.');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
