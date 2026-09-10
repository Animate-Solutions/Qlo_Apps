<?php
/** Lock Audit — door events, "who opened this door" per room, denials and the battery-low report. */
class AdminPulseKeycardAuditController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Lock Audit'); }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to change doors')); }
    }

    public function initContent()
    {
        parent::initContent();
        $f = array('id_door' => (int) Tools::getValue('id_door'), 'id_room' => (int) Tools::getValue('id_room'), 'event' => Tools::getValue('event', ''),
            'result' => Tools::getValue('result', ''), 'q' => Tools::getValue('q', ''), 'from' => Tools::getValue('from', date('Y-m-d', strtotime('-3 day'))), 'to' => Tools::getValue('to', date('Y-m-d')));
        $this->context->smarty->assign(array(
            'f' => $f, 'rows' => PulseKcAudit::search($f, 400), 'doors' => PulseKcService::doors(false), 'battery' => PulseKcAudit::batteryReport(),
            'denied' => PulseKcAudit::deniedSummary(24), 'events' => array('open', 'staff_open', 'denied', 'deadbolt', 'dnd_blocked', 'expired_card', 'battery_low', 'door_ajar', 'pass_used', 'emergency', 'unknown'),
            'rooms' => Db::getInstance()->executeS('SELECT id id_room, room_num FROM `'._DB_PREFIX_.'htl_room_information` ORDER BY room_num'),
            'encoders' => PulseKcEncoder::all(), 'battery_pct' => (int) PulseKcService::cfg('BATTERY_PCT', 20), 'retention' => (int) PulseKcService::cfg('AUDIT_RETENTION', 180),
            'maintenance' => class_exists('PulseTicket'), 'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('audit.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('pullDoor')) { $n = PulseKcAudit::pull((int) Tools::getValue('id_door')); $this->confirmations[] = sprintf($this->l('%d new event(s) pulled'), $n); }
            if (Tools::isSubmit('pullAll')) {
                $r = PulseKcAudit::pullAll();
                $this->confirmations[] = sprintf($this->l('%d new event(s) pulled'), $r['rows']);
                foreach ($r['errors'] as $e) { $this->warnings[] = $e; }
            }
            if (Tools::isSubmit('raiseBattery')) { $n = PulseKcAudit::raiseBatteryTickets(); $this->confirmations[] = $n ? sprintf($this->l('%d maintenance work order(s) raised'), $n) : $this->l('No new battery work orders needed'); }
            if (Tools::isSubmit('saveDoor')) {
                $this->assertEdit();
                PulseKcService::saveDoor(array('code' => Tools::getValue('code'), 'name' => Tools::getValue('name'), 'type' => Tools::getValue('type', 'common'),
                    'lock_id' => Tools::getValue('lock_id'), 'id_room' => (int) Tools::getValue('door_room'), 'floor' => Tools::getValue('floor'), 'zone' => Tools::getValue('zone'),
                    'is_default' => (int) Tools::getValue('is_default'), 'id_pulse_kc_encoder' => (int) Tools::getValue('id_encoder'), 'active' => (int) Tools::getValue('active', 1)), (int) Tools::getValue('id_door_edit'));
                $this->confirmations[] = $this->l('Door saved');
            }
            if (Tools::isSubmit('syncDoors')) { $this->assertEdit(); $n = PulseKcService::syncRoomDoors(); $this->confirmations[] = sprintf($this->l('%d room door(s) added'), $n); }
            if (Tools::isSubmit('purgeAudit')) { $this->assertEdit(); $n = PulseKcAudit::purge(); $this->confirmations[] = sprintf($this->l('%d old audit row(s) purged'), $n); }
        } catch (PulseKcEncoderException $e) { $this->errors[] = $e->userMessage(); }
        catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
