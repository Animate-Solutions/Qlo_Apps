<?php
/** /pulse/api/laundry/{resource}/{id} — TV portal (price list, request pickup, order status) and valet phones (status updates). */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulselaundry/classes/autoload.php';
class PulseLaundryApiModuleFrontController extends PulseApiController
{
    protected $resources = array('ping' => 'ping', 'pricelist' => 'pricelist', 'request' => 'request', 'orders' => 'orders', 'status' => 'status', 'room_orders' => 'roomOrders');
    protected function ping() { return array('module' => 'pulselaundry'); }
    protected function pricelist() { return PulseLaundryService::items(); }
    /** TV/mobile: guest requests pickup — lines optional (valet itemises on collection). */
    protected function request($idRoom, $body) { $this->requireScope('portal'); $lines = isset($body['lines']) ? $body['lines'] : array(); if (!$lines) { $any = PulseLaundryService::items(); $lines = array(array('id_item' => $any[0]['id_pulse_laundry_item'], 'process' => 'wash', 'qty' => 1)); } $id = PulseLaundryService::createOrder('guest', $lines, array('id_room' => $idRoom, 'service' => isset($body['service']) ? $body['service'] : 'normal', 'note' => isset($body['note']) ? 'Portal request: '.$body['note'] : 'Portal request')); return array('id_order' => $id); }
    protected function roomOrders($idRoom) { $this->requireScope('portal'); return Db::getInstance()->executeS('SELECT order_no, status, pieces, total_tax_incl, promised_at FROM `'._DB_PREFIX_.'pulse_laundry_order` WHERE id_room='.(int) $idRoom.' AND business_date>=DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY date_add DESC'); }
    protected function orders() { $this->requireScope('housekeeping'); return PulseLaundryService::orders('requested,collected,washing,ready'); }
    protected function status($id, $body) { $this->requireScope('housekeeping'); PulseLaundryService::setStatus($id, $body['status']); return array('id' => $id, 'status' => $body['status']); }
}
