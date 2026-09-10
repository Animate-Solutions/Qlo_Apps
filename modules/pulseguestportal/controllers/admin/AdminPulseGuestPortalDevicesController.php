<?php
/** Device registry: approve what boots, see what is online, and push reload / message / wipe to a screen. */
class AdminPulseGuestPortalDevicesController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Portal Devices'); }

    public function initContent()
    {
        parent::initContent();
        $rooms = Db::getInstance()->executeS('SELECT r.id id_room, r.room_num, r.floor, (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_gp_device` d WHERE d.id_room=r.id AND d.status<>"retired") devices FROM `'._DB_PREFIX_.'htl_room_information` r ORDER BY r.floor, r.room_num');
        $q = Tools::getValue('q', '');
        $where = 'd.status<>"retired"';
        if ($q !== '') { $where .= ' AND (d.uid LIKE "%'.pSQL($q).'%" OR d.mac LIKE "%'.pSQL($q).'%" OR d.serial LIKE "%'.pSQL($q).'%" OR d.room_num LIKE "%'.pSQL($q).'%" OR d.label LIKE "%'.pSQL($q).'%")'; }
        if (Tools::getValue('only') === 'pending') { $where .= ' AND d.status="pending"'; }
        if (Tools::getValue('only') === 'offline') { $where .= ' AND (d.last_seen IS NULL OR d.last_seen<DATE_SUB(NOW(), INTERVAL '.(int) PulseGpService::cfg('OFFLINE_MIN', 5).' MINUTE))'; }
        $rows = Db::getInstance()->executeS('SELECT d.*, r.room_num rn, r.floor fl FROM `'._DB_PREFIX_.'pulse_gp_device` d LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=d.id_room WHERE '.$where.' ORDER BY d.status="pending" DESC, COALESCE(r.floor,d.floor), COALESCE(r.room_num,d.room_num), d.id_pulse_gp_device LIMIT 400');
        foreach ($rows as &$d) { $d['online'] = PulseGpDevice::online($d) ? 1 : 0; $d['pair_code'] = PulseGpDevice::pairCode($d); $d['room_num'] = $d['rn'] ? $d['rn'] : $d['room_num']; }
        $this->context->smarty->assign(array(
            'devices' => $rows, 'rooms' => $rooms, 'counts' => PulseGpDevice::counts(), 'q' => $q, 'only' => Tools::getValue('only', ''),
            'commands' => Db::getInstance()->executeS('SELECT c.*, d.room_num, d.label FROM `'._DB_PREFIX_.'pulse_gp_command` c INNER JOIN `'._DB_PREFIX_.'pulse_gp_device` d ON d.id_pulse_gp_device=c.id_pulse_gp_device ORDER BY c.id_pulse_gp_command DESC LIMIT 30'),
            'portal_url' => $this->context->link->getModuleLink('pulseguestportal', 'portal', array(), true),
            'offline_min' => (int) PulseGpService::cfg('OFFLINE_MIN', 5), 'wipe_policy' => PulseGpService::cfg('WIPE_POLICY', 'wipe'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('devices.tpl');
    }

    public function postProcess()
    {
        try {
            $id = (int) Tools::getValue('id_device');
            if (Tools::isSubmit('approve')) { PulseGpDevice::approve($id, (int) Tools::getValue('id_room'), Tools::getValue('label'), Tools::getValue('type')); $this->confirmations[] = $this->l('Device paired'); }
            if (Tools::isSubmit('block')) { PulseGpDevice::setStatus($id, 'blocked'); $this->confirmations[] = $this->l('Device blocked'); }
            if (Tools::isSubmit('unblock')) { PulseGpDevice::setStatus($id, 'active'); PulseGpDevice::command($id, 'reload', array('reason' => 'unblocked')); $this->confirmations[] = $this->l('Device back in service'); }
            if (Tools::isSubmit('retire')) { PulseGpDevice::setStatus($id, 'retired'); $this->confirmations[] = $this->l('Device retired'); }
            if (Tools::isSubmit('reload')) { PulseGpDevice::command($id, 'reload', array('reason' => 'desk')); $this->confirmations[] = $this->l('Reload queued'); }
            if (Tools::isSubmit('wipe')) { $d = PulseGpDevice::byId($id); if ($d && $d['id_room']) { PulseGpDevice::wipeRoom((int) $d['id_room'], 'desk'); } else { PulseGpDevice::command($id, 'wipe', array('reason' => 'desk')); } $this->confirmations[] = $this->l('Wipe queued'); }
            if (Tools::isSubmit('pushMsg')) { PulseGpDevice::command($id, 'message', array('text' => Tools::getValue('text'))); $this->confirmations[] = $this->l('Message queued for the screen'); }
            if (Tools::isSubmit('rotate')) { PulseGpDevice::rotateToken($id); $this->confirmations[] = $this->l('Token rotated — the screen will re-pair on its next boot'); }
            if (Tools::isSubmit('addDevice')) {
                $d = PulseGpDevice::pair(array('mac' => Tools::getValue('mac'), 'serial' => Tools::getValue('serial'), 'type' => Tools::getValue('type'), 'model' => Tools::getValue('model'), 'room_num' => Tools::getValue('room_num')));
                if ((int) Tools::getValue('id_room')) { PulseGpDevice::approve((int) $d['id_device'], (int) Tools::getValue('id_room'), Tools::getValue('label'), Tools::getValue('type')); }
                $this->confirmations[] = $this->l('Device registered');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
