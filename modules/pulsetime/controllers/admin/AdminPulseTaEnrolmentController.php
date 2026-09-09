<?php
/**
 * Enrolment — the local roster and the map between a person and the id each reader knows them by.
 * With Pulse HR installed the staff list is mirrored from it; without HR this screen is where the roster lives.
 */
class AdminPulseTaEnrolmentController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Enrolment'); }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to change enrolments')); }
    }

    public function initContent()
    {
        parent::initContent();
        if ($id = (int) Tools::getValue('id_staff')) {
            $s = PulseTaService::staff($id);
            $this->context->smarty->assign(array('s' => $s, 'enrolments' => PulseTaEnrolment::forStaff($id), 'devices' => PulseTaDevice::all('active'),
                'shifts' => PulseTaService::shifts(), 'departments' => PulseTaService::departments(), 'hr' => PulseTaService::hr(),
                'recent' => PulseTaPunch::search(array('id_staff' => $id, 'from' => date('Y-m-d', strtotime('-14 day')), 'to' => date('Y-m-d')), 60),
                'self_url' => self::$currentIndex.'&token='.$this->token));
            return $this->setTemplate('staff.tpl');
        }
        $this->context->smarty->assign(array(
            'staff' => PulseTaService::staffList(Tools::getValue('department', ''), Tools::getValue('status', 'active'), Tools::getValue('q', '')),
            'problems' => PulseTaEnrolment::problems(), 'devices' => PulseTaDevice::all('active'), 'all_devices' => PulseTaDevice::all(),
            'departments' => PulseTaService::departments(), 'shifts' => PulseTaService::shifts(), 'hr' => PulseTaService::hr(),
            'department' => Tools::getValue('department', ''), 'status' => Tools::getValue('status', 'active'), 'q' => Tools::getValue('q', ''),
            'id_device' => (int) Tools::getValue('id_device'),
            'device_rows' => (int) Tools::getValue('id_device') ? PulseTaEnrolment::forDevice((int) Tools::getValue('id_device')) : array(),
            'punches_url' => $this->context->link->getAdminLink('AdminPulseTaPunches'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('enrolment.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveStaff')) {
                $this->assertEdit();
                $id = PulseTaService::saveStaff(array(
                    'staff_no' => Tools::getValue('staff_no'), 'firstname' => Tools::getValue('firstname'), 'lastname' => Tools::getValue('lastname'),
                    'department' => Tools::getValue('department_s'), 'section' => Tools::getValue('section'), 'position' => Tools::getValue('position'),
                    'id_employee' => (int) Tools::getValue('id_employee_link'), 'id_pos_staff' => (int) Tools::getValue('id_pos_staff'),
                    'id_pulse_ta_shift' => (int) Tools::getValue('id_shift'), 'pay_basis' => Tools::getValue('pay_basis', 'monthly'),
                    'hourly_rate' => (float) Tools::getValue('hourly_rate'), 'daily_rate' => (float) Tools::getValue('daily_rate'),
                    'ot_eligible' => (int) Tools::getValue('ot_eligible'), 'status' => Tools::getValue('staff_status', 'active'),
                    'exit_date' => Tools::getValue('exit_date') ?: null,
                ), (int) Tools::getValue('id_staff_save'));
                $this->confirmations[] = $this->l('Staff member saved').' (#'.$id.')';
            }
            if (Tools::isSubmit('syncHr')) {
                $r = PulseTaService::syncRoster();
                $this->confirmations[] = $r['hr'] ? sprintf($this->l('Mirrored %d employee(s) from Pulse HR.'), $r['synced']) : $this->l('Pulse HR is not installed — the local roster on this screen is the source of truth.');
            }
            if (Tools::isSubmit('provisionOne')) {
                $this->assertEdit();
                $r = PulseTaEnrolment::provisionAll((int) Tools::getValue('id_staff_act'), (int) Tools::getValue('only_device'));
                $this->confirmations[] = sprintf($this->l('%d device(s) written, %d queued on the device, %d failed and queued for retry.'), $r['pushed'], $r['deferred'], $r['queued']);
                foreach (array_slice($r['errors'], 0, 5) as $e) { $this->errors[] = $e; }
            }
            if (Tools::isSubmit('pushOne')) {
                $this->assertEdit();
                $r = PulseTaEnrolment::push((int) Tools::getValue('id_staff_act'), (int) Tools::getValue('id_device_act'), Tools::getValue('device_user_id', ''));
                if (!empty($r['ok'])) { $this->confirmations[] = $this->l('Enrolled as device user ').$r['device_user_id'].(isset($r['note']) && $r['note'] ? ' — '.$r['note'] : ''); }
                else { $this->errors[] = $r['error']; }
            }
            if (Tools::isSubmit('revokeOne')) {
                $this->assertEdit();
                $r = PulseTaEnrolment::revoke((int) Tools::getValue('id_staff_act'), (int) Tools::getValue('id_device_act'), Tools::getValue('revoke_reason', 'Revoked by an administrator'));
                if (!empty($r['ok'])) { $this->confirmations[] = $this->l('Removed from the device.'); } else { $this->errors[] = $r['error']; }
            }
            if (Tools::isSubmit('revokeAll')) {
                $this->assertEdit();
                $r = PulseTaEnrolment::revokeAll((int) Tools::getValue('id_staff_act'), Tools::getValue('revoke_reason', 'Employee exited'));
                $this->confirmations[] = sprintf($this->l('Removed from %d device(s); %d queued for retry.'), $r['removed'], $r['queued']);
            }
            if (Tools::isSubmit('mapRef')) {
                $this->assertEdit();
                $rows = PulseTaEnrolment::map((int) Tools::getValue('map_device'), Tools::getValue('map_ref'), (int) Tools::getValue('map_staff'));
                $this->confirmations[] = sprintf($this->l('Mapped — %d existing punch(es) re-attributed. Rebuild those days from Timesheets to update the totals.'), $rows);
            }
            if (Tools::isSubmit('saveMapping')) {
                $this->assertEdit();
                PulseTaEnrolment::saveMapping((int) Tools::getValue('id_staff_act'), (int) Tools::getValue('id_device_act'), Tools::getValue('device_user_id'),
                    array('card_no' => Tools::getValue('card_no'), 'privilege' => (int) Tools::getValue('privilege')));
                $this->confirmations[] = $this->l('Mapping saved (nothing was written to the device).');
            }
            if (Tools::isSubmit('reconcile')) {
                $this->assertEdit();
                $r = PulseTaEnrolment::reconcileDevice((int) Tools::getValue('id_device_act'));
                $this->confirmations[] = sprintf($this->l('%d user(s) on the device, %d newly discovered, %d unmapped, %d expected but missing.'), $r['on_device'], $r['discovered'], $r['unmapped'], count($r['missing_from_device']));
            }
        } catch (PulseTaDeviceException $e) { $this->errors[] = $e->userMessage(); }
        catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
