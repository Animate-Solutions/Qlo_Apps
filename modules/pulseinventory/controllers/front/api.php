<?php
/** /pulse/api/inventory/{resource}/{id} — HK phones (minibar posting, amenity issue), store keepers (requisition status), TV portal minibar menu. */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulseinventory/classes/autoload.php';
class PulseInventoryApiModuleFrontController extends PulseApiController
{
    protected $resources = array('ping' => 'ping', 'minibar_par' => 'minibarPar', 'minibar_post' => 'minibarPost', 'minibar_room' => 'minibarRoom', 'stock' => 'stock', 'request' => 'request', 'requests' => 'requests', 'issue' => 'issue');
    protected function ping() { return array('module' => 'pulseinventory'); }
    protected function minibarPar($id) { return PulseInvMinibar::parList($id ?: null); }
    /** HK attendant posts consumption for a room: body {lines:[{id,qty}], complimentary} */
    protected function minibarPost($idRoom, $b) { $this->requireScope('housekeeping'); return PulseInvMinibar::post($idRoom, (array) $b['lines'], !empty($b['complimentary']), (int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1); }
    protected function minibarRoom($idRoom) { return Db::getInstance()->executeS('SELECT lines, total, complimentary, date_add FROM `'._DB_PREFIX_.'pulse_inv_minibar_post` WHERE id_room='.(int) $idRoom.' AND business_date>=DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY id_pulse_inv_minibar_post DESC'); }
    protected function stock($idStore) { $this->requireScope('housekeeping'); return PulseInvService::stockList($idStore ?: null); }
    protected function request($id, $b) { $this->requireScope('housekeeping'); return array('id' => PulseInvPurchasing::createRequest('store_issue', (array) $b['lines'], array('from' => isset($b['from']) ? $b['from'] : PulseInvService::storeId('MAIN'), 'to' => $b['to'], 'department' => isset($b['department']) ? $b['department'] : '', 'note' => isset($b['note']) ? $b['note'] : ''))); }
    protected function requests() { $this->requireScope('housekeeping'); return Db::getInstance()->executeS('SELECT req_no, status, department, date_add FROM `'._DB_PREFIX_.'pulse_inv_request` WHERE status IN ("submitted","approved","partially_issued") ORDER BY id_pulse_inv_request DESC'); }
    protected function issue($id, $b) { $this->requireScope('frontdesk'); return array('complete' => PulseInvPurchasing::issue($id, isset($b['issued']) ? (array) $b['issued'] : array())); }
}
