<?php
class AdminPulseWaitlistController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Waitlist & Overbooking'); }
    public function initContent()
    {
        parent::initContent();
        $types = Db::getInstance()->executeS('SELECT rt.id_product, pl.name, COALESCE(o.max_over,0) max_over FROM `'._DB_PREFIX_.'htl_room_type` rt INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=rt.id_product AND pl.id_lang='.(int) $this->context->language->id.' AND pl.id_shop='.(int) $this->context->shop->id.' LEFT JOIN `'._DB_PREFIX_.'pulse_overbooking` o ON o.id_product=rt.id_product');
        $this->context->smarty->assign(array('queue' => PulseWaitlist::queue('waiting,offered'), 'done' => PulseWaitlist::queue('booked,expired,cancelled'), 'types' => $types, 'self_url' => self::$currentIndex.'&token='.$this->token));
        $this->setTemplate('waitlist.tpl');
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('addWait')) { PulseWaitlist::add(array('id_customer' => 0, 'guest_name' => Tools::getValue('guest_name'), 'phone' => Tools::getValue('phone'), 'email' => Tools::getValue('email'), 'id_product' => Tools::getValue('id_product'), 'date_from' => Tools::getValue('date_from'), 'date_to' => Tools::getValue('date_to'), 'rooms' => Tools::getValue('rooms'), 'priority' => Tools::getValue('priority'), 'note' => Tools::getValue('note'))); $this->confirmations[] = $this->l('Added to waitlist'); }
            if (Tools::isSubmit('convert')) { $r = PulseWaitlist::convert((int) Tools::getValue('id')); $this->confirmations[] = $this->l('Reservation created, order #').$r['id_order']; }
            if (Tools::isSubmit('cancelWait')) { Db::getInstance()->update('pulse_waitlist', array('status' => 'cancelled'), 'id_pulse_waitlist='.(int) Tools::getValue('id')); }
            if (Tools::isSubmit('runOffers')) { $this->confirmations[] = PulseWaitlist::processOffers().' '.$this->l('offer(s) sent'); }
            if (Tools::isSubmit('saveOver')) { foreach ((array) Tools::getValue('max_over') as $idp => $n) { Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_overbooking` (id_product,max_over) VALUES ('.(int) $idp.','.(int) $n.') ON DUPLICATE KEY UPDATE max_over=VALUES(max_over)'); } $this->confirmations[] = $this->l('Overbooking limits saved'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
