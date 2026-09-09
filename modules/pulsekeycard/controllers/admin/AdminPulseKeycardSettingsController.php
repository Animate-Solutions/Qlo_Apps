<?php
/** Key Card Settings — default adapter, grace hours, auto-issue, common doors, mobile-key policy, cron token. */
class AdminPulseKeycardSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Key Card Settings');
        $adapters = array();
        foreach (PulseKcEncoder::adapters() as $c => $n) { $adapters[] = array('id' => $c, 'name' => $n); }
        $this->fields_options = array(
            'keys' => array('title' => $this->l('Keys'), 'fields' => array(
                'PULSE_KC_ADAPTER' => array('title' => $this->l('Default lock adapter for new encoders'), 'type' => 'select', 'list' => $adapters, 'identifier' => 'id'),
                'PULSE_KC_AUTO_ISSUE' => array('title' => $this->l('Cut the guest key automatically at check-in'), 'type' => 'bool'),
                'PULSE_KC_EARLY_GRACE_HRS' => array('title' => $this->l('Key valid this many hours before check-in time'), 'type' => 'text'),
                'PULSE_KC_LATE_GRACE_HRS' => array('title' => $this->l('Key valid this many hours after check-out time'), 'type' => 'text'),
                'PULSE_KC_MAX_DUPLICATES' => array('title' => $this->l('Maximum live cards per stay (0 = no limit)'), 'type' => 'text'),
                'PULSE_KC_TIMEOUT' => array('title' => $this->l('Default encoder timeout (seconds)'), 'type' => 'text'),
                'PULSE_KC_STAFF_CARD_DAYS' => array('title' => $this->l('Default staff card life (days)'), 'type' => 'text'),
            ), 'submit' => array('title' => $this->l('Save'))),
            'mobile' => array('title' => $this->l('Mobile key'), 'fields' => array(
                'PULSE_KC_MOBILE_ENABLED' => array('title' => $this->l('Issue BLE / QR mobile keys'), 'type' => 'bool'),
                'PULSE_KC_MOBILE_TTL_MIN' => array('title' => $this->l('Credential lifetime before the app must refresh (minutes)'), 'type' => 'text'),
                'PULSE_KC_MOBILE_MAX_REFRESH' => array('title' => $this->l('Maximum refreshes per key (0 = no limit)'), 'type' => 'text'),
                'PULSE_KC_MOBILE_REBIND' => array('title' => $this->l('Allow a mobile key to move to another device'), 'type' => 'bool'),
            ), 'submit' => array('title' => $this->l('Save'))),
            'locks' => array('title' => $this->l('Locks & audit'), 'fields' => array(
                'PULSE_KC_BATTERY_PCT' => array('title' => $this->l('Battery-low threshold (%)'), 'type' => 'text'),
                'PULSE_KC_AUDIT_RETENTION' => array('title' => $this->l('Keep lock audit rows for (days)'), 'type' => 'text'),
                'PULSE_KC_ENCODER_STALE_HRS' => array('title' => $this->l('Flag an encoder not seen for (hours)'), 'type' => 'text'),
                'PULSE_KC_LOCAL_AGENT' => array('title' => $this->l('Let the clerk browser talk to a local encoder agent'), 'type' => 'bool'),
                'PULSE_KC_AGENT_PORT' => array('title' => $this->l('Local encoder agent port'), 'type' => 'text'),
                'PULSE_KC_CRON_TOKEN' => array('title' => $this->l('Cron token'), 'type' => 'text'),
            ), 'submit' => array('title' => $this->l('Save'))),
        );
    }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to change settings')); }
    }

    public function initContent()
    {
        parent::initContent();
        $shop = Tools::getShopDomainSsl(true).__PS_BASE_URI__;
        $this->context->smarty->assign(array(
            'doors' => PulseKcService::doors(false), 'encoders' => PulseKcEncoder::all(false),
            'rooms' => Db::getInstance()->executeS('SELECT id id_room, room_num FROM `'._DB_PREFIX_.'htl_room_information` ORDER BY room_num'),
            'door_types' => array('common', 'lift', 'gate', 'back_of_house', 'wall_reader', 'safe', 'room'),
            'cron_url' => $shop.'modules/pulsekeycard/cron/expire.php?token='.Configuration::get('PULSE_KC_CRON_TOKEN'),
            'api_url' => $shop.'pulse/api/keycard/ping', 'fd' => PulseKcService::fd(), 'maintenance' => class_exists('PulseTicket'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->content .= $this->context->smarty->fetch($this->getTemplatePath().'pulse_keycard_settings/doors.tpl');
        $this->context->smarty->assign('content', $this->content);
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveDoor')) {
                $this->assertEdit();
                PulseKcService::saveDoor(array('code' => Tools::getValue('code'), 'name' => Tools::getValue('name'), 'type' => Tools::getValue('type', 'common'),
                    'lock_id' => Tools::getValue('lock_id'), 'id_room' => (int) Tools::getValue('id_room'), 'floor' => Tools::getValue('floor'), 'zone' => Tools::getValue('zone'),
                    'is_default' => (int) Tools::getValue('is_default'), 'id_pulse_kc_encoder' => (int) Tools::getValue('id_encoder'), 'active' => (int) Tools::getValue('active', 1)), (int) Tools::getValue('id_door'));
                $this->confirmations[] = $this->l('Door saved');
            }
            if (Tools::isSubmit('syncDoors')) { $this->assertEdit(); $n = PulseKcService::syncRoomDoors(); $this->confirmations[] = sprintf($this->l('%d room door(s) added'), $n); }
            if (Tools::isSubmit('rotateToken')) { $this->assertEdit(); Configuration::updateValue('PULSE_KC_CRON_TOKEN', Tools::passwdGen(32)); $this->confirmations[] = $this->l('Cron token rotated'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
