<?php
class AdminPulseLaundryController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Laundry'); }

    public function initContent()
    {
        parent::initContent();
        if ($id = (int) Tools::getValue('id_order')) {
            $this->context->smarty->assign(array('o' => PulseLaundryService::order($id), 'self_url' => self::$currentIndex.'&token='.$this->token, 'fd' => PulseLaundryService::fd()));
            return $this->setTemplate('order.tpl');
        }
        $rooms = class_exists('PulseRoom') ? PulseRoom::board() : Db::getInstance()->executeS('SELECT id id_room, room_num FROM `'._DB_PREFIX_.'htl_room_information` ORDER BY room_num');
        $this->context->smarty->assign(array(
            'queue' => PulseLaundryService::orders('requested,collected,washing,ready'), 'done' => PulseLaundryService::orders('delivered,cancelled', null, PulseCoreService::businessDate()),
            'items' => PulseLaundryService::items(), 'rooms' => $rooms, 'vendors' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_laundry_vendor` WHERE active=1'),
            'sur_express' => Configuration::get('PULSE_LDY_EXPRESS_PCT'), 'sur_sameday' => Configuration::get('PULSE_LDY_SAMEDAY_PCT'), 'cutoff' => Configuration::get('PULSE_LDY_CUTOFF'),
            'self_url' => self::$currentIndex.'&token='.$this->token, 'fd' => PulseLaundryService::fd(), 'business_date' => PulseCoreService::businessDate(),
            'from' => Tools::getValue('from', date('Y-m-01')), 'to' => Tools::getValue('to', PulseCoreService::businessDate()),
            'rev' => PulseLaundryService::revenue(Tools::getValue('from', date('Y-m-01')), Tools::getValue('to', PulseCoreService::businessDate())), 'claims' => PulseLaundryService::claims(Tools::getValue('from', date('Y-m-01')), Tools::getValue('to', PulseCoreService::businessDate())),
        ));
        $this->setTemplate('queue.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('createOrder')) {
                $lines = array(); foreach ((array) Tools::getValue('qty') as $idItem => $q) { if ((int) $q > 0) { $lines[] = array('id_item' => $idItem, 'process' => Tools::getValue('process')[$idItem], 'qty' => $q, 'condition_note' => isset(Tools::getValue('cond')[$idItem]) ? Tools::getValue('cond')[$idItem] : ''); } }
                $id = PulseLaundryService::createOrder(Tools::getValue('type', 'guest'), $lines, array('id_room' => Tools::getValue('id_room'), 'service' => Tools::getValue('service'), 'note' => Tools::getValue('note'), 'complimentary' => Tools::getValue('complimentary'), 'id_vendor' => Tools::getValue('id_vendor'), 'department' => Tools::getValue('department'), 'guest_name' => Tools::getValue('guest_name')));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_order='.$id.'&conf=3');
            }
            if (Tools::isSubmit('setStatus')) { PulseLaundryService::setStatus((int) Tools::getValue('id_order_s'), Tools::getValue('status')); $this->confirmations[] = $this->l('Status updated'); }
            if (Tools::isSubmit('postNow')) { PulseLaundryService::postToFolio((int) Tools::getValue('id_order_s')); $this->confirmations[] = $this->l('Posted to folio'); }
            if (Tools::isSubmit('addClaim')) { PulseLaundryService::claim((int) Tools::getValue('id_order_s'), Tools::getValue('ctype'), Tools::getValue('cdesc'), (float) Tools::getValue('camount'), (int) Tools::getValue('id_line') ?: null); $this->confirmations[] = $this->l('Claim logged'); }
            if (Tools::isSubmit('settleClaim')) { PulseLaundryService::settleClaim((int) Tools::getValue('id_claim'), Tools::getValue('cstatus'), (float) Tools::getValue('csettled'), Tools::getValue('chow')); $this->confirmations[] = $this->l('Claim updated'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
