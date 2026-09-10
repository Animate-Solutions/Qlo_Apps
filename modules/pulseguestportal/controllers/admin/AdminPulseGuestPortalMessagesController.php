<?php
/** Desk inbox for the in-room chat: threads by stay, replies, and a broadcast to every occupied room. */
class AdminPulseGuestPortalMessagesController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Guest Messages'); }

    public function initContent()
    {
        parent::initContent();
        $idBooking = (int) Tools::getValue('id_htl_booking');
        $thread = array(); $stay = null;
        if ($idBooking) { PulseGpMessaging::markReadByDesk($idBooking); $thread = PulseGpMessaging::thread($idBooking, 200); $stay = PulseGpService::booking($idBooking); }
        $this->context->smarty->assign(array(
            'inbox' => PulseGpMessaging::inbox(Tools::getValue('only') === 'unread'), 'thread' => $thread, 'stay' => $stay, 'id_htl_booking' => $idBooking,
            'unread' => PulseGpMessaging::unreadForDesk(), 'only' => Tools::getValue('only', ''),
            'in_house' => Db::getInstance()->executeS('SELECT b.id, r.room_num, CONCAT(c.firstname," ",c.lastname) guest FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room WHERE b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND b.is_cancelled=0 ORDER BY r.room_num'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('messages.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('reply')) {
                $idBooking = (int) Tools::getValue('id_htl_booking');
                $idRoom = (int) Db::getInstance()->getValue('SELECT id_room FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id='.$idBooking);
                PulseGpMessaging::fromDesk($idBooking, $idRoom, Tools::getValue('body'), (int) $this->context->employee->id);
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_htl_booking='.$idBooking.'&conf=3');
            }
            if (Tools::isSubmit('broadcast')) { $n = PulseGpMessaging::broadcast(Tools::getValue('bbody'), (int) $this->context->employee->id); $this->confirmations[] = sprintf($this->l('Sent to %d room(s)'), $n); }
            if (Tools::isSubmit('markRead')) { PulseGpMessaging::markReadByDesk((int) Tools::getValue('id_htl_booking')); $this->confirmations[] = $this->l('Marked read'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
