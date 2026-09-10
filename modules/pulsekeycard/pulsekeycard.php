<?php
/** Pulse Key Card — door keys, encoders, mobile keys, staff access and lock audit. Benchmarks: Onity HT/Advance, Salto ProAccess SPACE, Hune, Dormakaba Ambiance (Saflok/Ilco), ZKTeco. */
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once dirname(__FILE__) . '/classes/autoload.php';

class PulseKeycard extends Module
{
    const VERSION = '1.0.0';
    protected $tabs = array('AdminPulseKeycard' => 'Key Desk', 'AdminPulseKeycardKeys' => 'Keys', 'AdminPulseKeycardEncoders' => 'Encoders', 'AdminPulseKeycardAudit' => 'Lock Audit', 'AdminPulseKeycardStaff' => 'Staff Access', 'AdminPulseKeycardSettings' => 'Key Card Settings');
    protected $hooks = array('displayBackOfficeHeader', 'moduleRoutes', 'actionPulseCheckIn', 'actionPulseCheckOut', 'actionPulseRoomMove', 'actionPulseStayChanged', 'actionPulseKeyIssued', 'actionPulseKeyCancelled', 'actionPulseLockAudit');

    public function __construct()
    {
        $this->name = 'pulsekeycard';
        $this->tab = 'administration';
        $this->version = self::VERSION;
        $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->dependencies = array('pulsecore');
        $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Key Card');
        $this->description = $this->l('Door key encoding for Onity, Salto, Hune and Dormakaba locks with a hardware-free simulator: guest and duplicate keys, mobile BLE/QR keys, staff access groups, lock audit trail and battery alerts.');
        $this->confirmUninstall = $this->l('Uninstall Key Card? Encoders, issued keys, mobile keys and the lock audit trail will be dropped. Cards already in guests\' hands keep working until they expire.');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }
        foreach ($this->hooks as $h) {
            if (!$this->registerHook($h)) {
                return false;
            }
        }
        if (!$this->runSql('install')) {
            return false;
        }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore');
        $i = 90;
        foreach ($this->tabs as $c => $n) {
            $t = new Tab();
            $t->class_name = $c;
            $t->module = $this->name;
            $t->id_parent = $parent;
            $t->position = $i++;
            foreach (Language::getLanguages(true) as $l) {
                $t->name[$l['id_lang']] = $n;
            }
            if (!$t->add()) {
                return false;
            }
        }
        foreach (array(
            'PULSE_KC_ADAPTER' => 'PulseKcAdapterSimulator',
            'PULSE_KC_AUTO_ISSUE' => 1,
            'PULSE_KC_EARLY_GRACE_HRS' => 2,
            'PULSE_KC_LATE_GRACE_HRS' => 2,
            'PULSE_KC_MAX_DUPLICATES' => 4,
            'PULSE_KC_TIMEOUT' => 8,
            'PULSE_KC_MOBILE_ENABLED' => 1,
            'PULSE_KC_MOBILE_TTL_MIN' => 240,
            'PULSE_KC_MOBILE_REBIND' => 0,
            'PULSE_KC_MOBILE_MAX_REFRESH' => 500,
            'PULSE_KC_AUDIT_RETENTION' => 180,
            'PULSE_KC_ENCODER_STALE_HRS' => 6,
            'PULSE_KC_BATTERY_PCT' => 20,
            'PULSE_KC_STAFF_CARD_DAYS' => 90,
            'PULSE_KC_LOCAL_AGENT' => 1,
            'PULSE_KC_AGENT_PORT' => 7070,
            'PULSE_KC_SECRET' => Tools::passwdGen(48),
            'PULSE_KC_CRON_TOKEN' => Tools::passwdGen(32)
        ) as $k => $v) {
            Configuration::updateValue($k, $v);
        }
        if (class_exists('PulseKcService')) {
            PulseKcService::syncRoomDoors();
        }
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $c => $n) {
            if ($id = (int) Tab::getIdFromClassName($c)) {
                $t = new Tab($id);
                $t->delete();
            }
        }
        foreach (array('ADAPTER', 'AUTO_ISSUE', 'EARLY_GRACE_HRS', 'LATE_GRACE_HRS', 'MAX_DUPLICATES', 'TIMEOUT', 'MOBILE_ENABLED', 'MOBILE_TTL_MIN', 'MOBILE_REBIND', 'MOBILE_MAX_REFRESH', 'AUDIT_RETENTION', 'ENCODER_STALE_HRS', 'BATTERY_PCT', 'STAFF_CARD_DAYS', 'LOCAL_AGENT', 'AGENT_PORT', 'SECRET', 'CRON_TOKEN') as $k) {
            Configuration::deleteByName('PULSE_KC_' . $k);
        }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    protected function runSql($f)
    {
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__) . '/sql/' . $f . '.sql'));
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) {
            if (strpos($q, '--') !== 0 && !Db::getInstance()->execute($q)) {
                return false;
            }
        }
        return true;
    }

    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseKeycard'));
    }
    public function hookDisplayBackOfficeHeader()
    {
        if (strpos($this->context->controller->controller_name, 'AdminPulseKeycard') === 0) {
            $this->context->controller->addCSS($this->_path . 'views/css/keycard.css');
            $this->context->controller->addJS($this->_path . 'views/js/keycard.js');
        }
    }
    public function hookModuleRoutes()
    {
        return array('pulsekeycard-api' => array('controller' => 'api', 'rule' => 'pulse/api/keycard{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name)));
    }

    /**
     * Check-in: cut the guest key automatically when the setting says so. A dead encoder must never block a
     * check-in, so the failure is swallowed here — the key row stays `failed`, a retry job is queued and the
     * desk gets a trace telling the clerk to hand over a mechanical key.
     */
    public function hookActionPulseCheckIn($p)
    {
        if (empty($p['booking']['id']) || !Configuration::get('PULSE_KC_AUTO_ISSUE')) {
            return;
        }
        if (!empty($p['room_move'])) {
            return;
        } // the room-move hook already re-cut the key
        try {
            PulseKcKey::issueForBooking((int) $p['booking']['id']);
        } catch (PulseKcEncoderException $e) {
            PulseKcService::alert('Auto key for room ' . $p['booking']['room_num'] . ' failed — ' . $e->userMessage(), (int) $p['booking']['id'], (int) $p['id_room']);
        } catch (Exception $e) {
            PulseCoreService::audit('pulsekeycard', 'auto_issue_failed', array('booking' => (int) $p['booking']['id'], 'error' => $e->getMessage()));
        }
    }

    /** Room move: the old room's cards die, the new room's card is cut for the remaining stay. */
    public function hookActionPulseRoomMove($p)
    {
        if (empty($p['booking']['id'])) {
            return;
        }
        try {
            if (!empty($p['from_room'])) {
                PulseKcKey::cancelForRoom((int) $p['from_room'], 'Room move — guest left ' . (int) $p['from_room']);
            }
            if (Configuration::get('PULSE_KC_AUTO_ISSUE')) {
                PulseKcKey::issueForBooking((int) $p['booking']['id'], array('rooms' => array((int) $p['to_room'])));
            }
        } catch (PulseKcEncoderException $e) {
            PulseKcService::alert('Room move key for ' . $p['booking']['room_num'] . ' failed — ' . $e->userMessage(), (int) $p['booking']['id'], (int) $p['to_room']);
        } catch (Exception $e) {
            PulseCoreService::audit('pulsekeycard', 'room_move_key_failed', array('booking' => (int) $p['booking']['id'], 'error' => $e->getMessage()));
        }
    }

    /** Stay extended or shortened: push the new departure onto every live card of the booking. */
    public function hookActionPulseStayChanged($p)
    {
        if (empty($p['booking']['id'])) {
            return;
        }
        try {
            $r = PulseKcKey::extendForBooking((int) $p['booking']['id'], $p['booking']['date_from'], isset($p['new_to']) ? $p['new_to'] : $p['booking']['date_to']);
            if (!empty($r['failed'])) {
                PulseKcService::alert($r['failed'] . ' card(s) for room ' . $p['booking']['room_num'] . ' could not be re-encoded to ' . $r['valid_to'] . ' — ask the guest to bring the card to the desk.', (int) $p['booking']['id'], (int) $p['booking']['id_room']);
            }
        } catch (Exception $e) {
            PulseCoreService::audit('pulsekeycard', 'stay_change_key_failed', array('booking' => (int) $p['booking']['id'], 'error' => $e->getMessage()));
        }
    }

    /** Check-out: every card and mobile key for the stay is cancelled. Room moves are handled by their own hook. */
    public function hookActionPulseCheckOut($p)
    {
        if (empty($p['booking']['id']) || !empty($p['room_move'])) {
            return;
        }
        try {
            PulseKcKey::cancelForBooking((int) $p['booking']['id'], 'Check-out');
            PulseKcMobileKey::revokeForBooking((int) $p['booking']['id'], 'Check-out');
        } catch (Exception $e) {
            PulseCoreService::audit('pulsekeycard', 'checkout_cancel_failed', array('booking' => (int) $p['booking']['id'], 'error' => $e->getMessage()));
        }
    }

    public function hookActionPulseKeyIssued($p)
    {
    }
    public function hookActionPulseKeyCancelled($p)
    {
    }
    public function hookActionPulseLockAudit($p)
    {
    }
}
