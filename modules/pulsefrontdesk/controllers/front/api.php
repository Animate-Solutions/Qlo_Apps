<?php
/** /pulse/api/frontdesk/{resource}/{id} — for the housekeeping mobile view and the TV portal (folio lookup). */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulsefrontdesk/classes/autoload.php';

class PulseFrontDeskApiModuleFrontController extends PulseApiController
{
    protected $resources = array('ping' => 'ping', 'board' => 'board', 'hk_tasks' => 'hkTasks', 'hk_task' => 'hkTask', 'room_status' => 'roomStatus', 'folio' => 'folio', 'request' => 'request', 'pabx_cdr' => 'pabxCdr', 'pabx_code' => 'pabxCode', 'tickets' => 'tickets', 'ticket' => 'ticket', 'upsells' => 'upsells', 'accept_upsell' => 'acceptUpsell', 'extend' => 'extend', 'arrivals' => 'arrivals');

    protected function ping() { return array('module' => 'pulsefrontdesk', 'business_date' => PulseCoreService::businessDate()); }
    protected function board() { $this->requireScope('frontdesk'); return PulseRoom::board(); }
    protected function hkTasks() { $this->requireScope('housekeeping'); return PulseHousekeeping::queue('open,in_progress', (int) Tools::getValue('assigned_to') ?: null); }
    protected function hkTask($id, $body) { $this->requireScope('housekeeping'); PulseHousekeeping::setStatus($id, $body['status']); return array('id' => $id, 'status' => $body['status']); }
    protected function roomStatus($id, $body) { $this->requireScope('housekeeping'); PulseRoom::setHkStatus($id, $body['status'], 'mobile', isset($body['reason']) ? $body['reason'] : null); return array('id_room' => $id, 'status' => $body['status']); }
    /** Folio by room id — used by the TV guest portal (read-only, scope "portal"). */
    protected function folio($idRoom)
    {
        $this->requireScope('portal');
        $b = Db::getInstance()->getRow('SELECT id FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_room='.(int) $idRoom.' AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND is_cancelled=0');
        if (!$b) { throw new PrestaShopException('No in-house guest', 404); }
        $f = PulseFolio::openForBooking($b['id']);
        return $f ? array('folio_no' => $f->folio_no, 'balance' => $f->balance, 'lines' => array_map(function ($l) { return array('date' => $l['date_add'], 'description' => $l['description'], 'amount' => $l['amount_tax_incl'], 'is_payment' => $l['is_payment']); }, $f->lines())) : null;
    }
    /** Guest request from the TV portal → trace for the front desk. */
    protected function request($idRoom, $body)
    {
        $this->requireScope('portal');
        $id = PulseTrace::add('trace', '['.$body['type'].'] '.$body['text'], date('Y-m-d H:i:s'), null, $idRoom, null, isset($body['department']) ? $body['department'] : 'frontdesk');
        return array('id_trace' => $id);
    }

    /** PABX bridge posts call detail records: {ext, number, duration, cost} */
    protected function pabxCdr($id, $body) { $this->requireScope('pabx'); return array('id_log' => PulsePabx::callRecord($body['ext'], $body['number'], (int) $body['duration'], (float) $body['cost'])); }
    /** PABX bridge posts HK dial codes: {ext, code} */
    protected function pabxCode($id, $body) { $this->requireScope('pabx'); return array('status' => PulsePabx::statusCode($body['ext'], $body['code'])); }
    /** Engineering / HK mobile: tickets for my department */
    protected function tickets() { $this->requireScope('tickets'); return PulseTicket::list_('open,assigned,in_progress,reopened', Tools::getValue('department') ?: null, (int) Tools::getValue('assigned_to') ?: null); }
    protected function ticket($id, $body)
    {
        $this->requireScope('tickets');
        if (!$id) { return array('id_ticket' => PulseTicket::create(array_merge(array('source' => 'mobile'), $body))); }
        PulseTicket::update($id, $body, isset($body['note']) ? $body['note'] : null); return array('id' => $id);
    }
    /** TV portal: offers for the in-house guest of a room */
    protected function upsells($idRoom)
    {
        $this->requireScope('portal');
        $b = Db::getInstance()->getRow('SELECT id FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_room='.(int) $idRoom.' AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND is_cancelled=0');
        return $b ? PulseUpsell::offersFor($b['id']) : array();
    }
    protected function acceptUpsell($idRoom, $body)
    {
        $this->requireScope('portal');
        $b = Db::getInstance()->getRow('SELECT id FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_room='.(int) $idRoom.' AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND is_cancelled=0');
        if (!$b) { throw new PrestaShopException('No in-house guest', 404); }
        PulseUpsell::accept($b['id'], $body['offer'], 'instay'); return array('ok' => true);
    }
    protected function extend($idBooking, $body) { $this->requireScope('frontdesk'); return array('nights' => PulseReservation::changeDates($idBooking, $body['new_to'])); }
    protected function arrivals() { $this->requireScope('frontdesk'); return PulseFdService::arrivals(Tools::getValue('date') ?: null); }
}
