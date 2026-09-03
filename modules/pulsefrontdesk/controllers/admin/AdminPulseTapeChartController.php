<?php
/** Rooms × dates grid. Drag a stay to another room (same type) or to new dates; drag its right edge to extend/shorten; click an empty cell to start a reservation. */
class AdminPulseTapeChartController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Tape Chart'); }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array('start' => Tools::getValue('start', PulseCoreService::businessDate()), 'days' => (int) Tools::getValue('days', 14), 'ajax_url' => $this->context->link->getAdminLink('AdminPulseTapeChart'),
            'board_url' => $this->context->link->getAdminLink('AdminPulseRoomBoard'), 'walkin_url' => $this->context->link->getAdminLink('AdminPulseWalkIn'), 'folio_url' => $this->context->link->getAdminLink('AdminPulseFolio')));
        $this->setTemplate('chart.tpl');
    }

    protected function json($d) { die(json_encode($d)); }

    public function ajaxProcessData()
    {
        $start = Tools::getValue('start', PulseCoreService::businessDate()); $days = max(7, min(31, (int) Tools::getValue('days', 14))); $end = date('Y-m-d', strtotime($start." +$days day"));
        $rooms = Db::getInstance()->executeS('SELECT r.id id_room, r.room_num, r.floor, r.id_product, pl.name room_type, s.hk_status FROM `'._DB_PREFIX_.'htl_room_information` r INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=r.id_product AND pl.id_lang='.(int) $this->context->language->id.' AND pl.id_shop='.(int) $this->context->shop->id.' LEFT JOIN `'._DB_PREFIX_.'pulse_room_status` s ON s.id_room=r.id WHERE r.id_status IN (1,3) ORDER BY r.id_product, r.floor, r.room_num');
        $stays = Db::getInstance()->executeS('SELECT b.id, b.id_room, b.id_product, b.date_from, b.date_to, b.id_status, CONCAT(c.firstname," ",c.lastname) guest, gp.vip_level, x.id_pulse_group_block, x.day_use, f.balance
            FROM `'._DB_PREFIX_.'htl_booking_detail` b LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=b.id_customer LEFT JOIN `'._DB_PREFIX_.'pulse_booking_ext` x ON x.id_htl_booking=b.id LEFT JOIN `'._DB_PREFIX_.'pulse_folio` f ON f.id_htl_booking=b.id AND f.status="open"
            WHERE b.is_refunded=0 AND b.is_cancelled=0 AND b.date_from<"'.pSQL($end).'" AND b.date_to>"'.pSQL($start).'"');
        $ooo = Db::getInstance()->executeS('SELECT id_room, hk_status, ooo_until FROM `'._DB_PREFIX_.'pulse_room_status` WHERE hk_status IN ("out_of_order","out_of_service")');
        $blocks = Db::getInstance()->executeS('SELECT b.code, b.name, b.date_from, b.date_to, a.id_product, a.blocked, a.picked_up FROM `'._DB_PREFIX_.'pulse_group_block` b INNER JOIN `'._DB_PREFIX_.'pulse_group_block_allot` a ON a.id_pulse_group_block=b.id_pulse_group_block WHERE b.status IN ("tentative","definite") AND b.date_from<"'.pSQL($end).'" AND b.date_to>"'.pSQL($start).'"');
        $this->json(array('ok' => true, 'start' => $start, 'days' => $days, 'business_date' => PulseCoreService::businessDate(), 'rooms' => $rooms, 'stays' => $stays, 'ooo' => $ooo, 'blocks' => $blocks));
    }

    /** Drop: move to another room and/or shift dates. */
    public function ajaxProcessMove()
    {
        try {
            $id = (int) Tools::getValue('id_booking'); $toRoom = (int) Tools::getValue('to_room'); $newFrom = Tools::getValue('new_from'); $b = PulseFdService::booking($id);
            if (!$b) { throw new PrestaShopException('Booking not found'); }
            $nights = (int) $b['nights']; $newTo = date('Y-m-d', strtotime($newFrom." +$nights day"));
            if ((int) $b['id_status'] === HotelBookingDetail::STATUS_CHECKED_IN && $newFrom !== $b['date_from']) { throw new PrestaShopException('In-house stays cannot change arrival date'); }
            if ((int) $b['id_status'] === HotelBookingDetail::STATUS_CHECKED_OUT) { throw new PrestaShopException('Departed stay'); }
            if ($newFrom !== $b['date_from']) { PulseReservation::changeDates($id, $newTo, $newFrom); }
            if ($toRoom && $toRoom !== (int) $b['id_room']) {
                $room = Db::getInstance()->getRow('SELECT id_product FROM `'._DB_PREFIX_.'htl_room_information` WHERE id='.$toRoom);
                if ((int) $room['id_product'] !== (int) $b['id_product']) { PulseReservation::changeRoomType($id, $toRoom, (float) Tools::getValue('diff', 0), 'Tape chart move'); }
                elseif ((int) $b['id_status'] === HotelBookingDetail::STATUS_CHECKED_IN) { PulseFdService::roomMove($id, $toRoom, 'Tape chart'); }
                else {
                    $free = array_map(function ($r) { return (int) $r['id_room']; }, PulseRoom::availableRooms($b['id_product'], $newFrom, $newTo, $id));
                    if (!in_array($toRoom, $free)) { throw new PrestaShopException('Target room not free for those dates'); }
                    $rm = new HotelRoomInformation($toRoom); Db::getInstance()->update('htl_booking_detail', array('id_room' => $toRoom, 'room_num' => pSQL($rm->room_num)), 'id='.$id);
                    PulseRoom::setFoStatus($b['id_room'], 'vacant', null); if ($newFrom === PulseCoreService::businessDate()) { PulseRoom::setFoStatus($toRoom, 'due_in', $id); }
                }
            }
            $this->json(array('ok' => true));
        } catch (Exception $e) { $this->json(array('ok' => false, 'error' => $e->getMessage())); }
    }

    public function ajaxProcessResize()
    {
        try { $n = PulseReservation::changeDates((int) Tools::getValue('id_booking'), Tools::getValue('new_to')); $this->json(array('ok' => true, 'nights' => $n)); }
        catch (Exception $e) { $this->json(array('ok' => false, 'error' => $e->getMessage())); }
    }
}
