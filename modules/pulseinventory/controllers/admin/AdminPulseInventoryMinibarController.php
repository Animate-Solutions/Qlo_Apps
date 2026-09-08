<?php
class AdminPulseInventoryMinibarController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Minibar & Amenities'); }
    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-m-01')); $to = Tools::getValue('to', PulseInvService::bd());
        $this->context->smarty->assign(array('par' => PulseInvMinibar::parList(), 'rooms' => Db::getInstance()->executeS('SELECT r.id id_room, r.room_num, b.id id_htl_booking, CONCAT(c.firstname," ",c.lastname) guest FROM `'._DB_PREFIX_.'htl_room_information` r LEFT JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id_room=r.id AND b.id_status=2 AND b.is_cancelled=0 LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer WHERE r.id_status=1 ORDER BY r.floor, r.room_num'),
            'posts' => Db::getInstance()->executeS('SELECT p.*, r.room_num, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_inv_minibar_post` p INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=p.id_room LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=p.id_employee ORDER BY p.id_pulse_inv_minibar_post DESC LIMIT 40'), 'refill' => PulseInvMinibar::refillSuggestion(), 'consumption' => PulseInvMinibar::consumptionReport($from, $to),
            'amenities' => PulseInvMinibar::amenityList(), 'cpor' => PulseInvMinibar::costPerOccupiedRoom($from, $to), 'items' => Db::getInstance()->executeS('SELECT id_pulse_inv_item, name, sale_price FROM `'._DB_PREFIX_.'pulse_inv_item` WHERE active=1 ORDER BY name'), 'room_types' => Db::getInstance()->executeS('SELECT p.id_product, pl.name FROM `'._DB_PREFIX_.'htl_room_type` p INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=p.id_product AND pl.id_lang='.(int) $this->context->language->id),
            'from' => $from, 'to' => $to, 'self_url' => self::$currentIndex.'&token='.$this->token, 'cur' => $this->context->currency->sign, 'fd' => class_exists('PulseFolio')));
        $this->setTemplate('minibar.tpl');
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('postMinibar')) { $lines = array(); foreach ((array) Tools::getValue('mb_qty') as $id => $q) { if ((float) $q > 0) { $lines[] = array('id' => (int) $id, 'qty' => (float) $q); } } $r = PulseInvMinibar::post((int) Tools::getValue('id_room'), $lines, (bool) Tools::getValue('complimentary')); $this->confirmations[] = sprintf($this->l('Minibar posted: %s%s'), $r['charged'] ? $this->l('charged to folio ') : $this->l('stock only '), $r['total']); }
            if (Tools::isSubmit('refill')) { $main = PulseInvService::storeId('MAIN'); $mb = PulseInvService::storeId('MINIBAR'); $n = 0; foreach ((array) Tools::getValue('rf_qty') as $id => $q) { if ((float) $q > 0) { PulseInvService::transfer((int) $id, $main, $mb, (float) $q, 'MB-REFILL', 'minibar refill'); $n++; } } $this->confirmations[] = sprintf($this->l('%d item(s) moved to minibar stock'), $n); }
            if (Tools::isSubmit('saveParItem')) { Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_inv_minibar_item` (id_pulse_inv_item,id_product,par,sort) VALUES ('.(int) Tools::getValue('mb_item').','.((int) Tools::getValue('mb_type') ?: 'NULL').','.(float) Tools::getValue('mb_par').','.(int) Tools::getValue('mb_sort').') ON DUPLICATE KEY UPDATE par=VALUES(par), id_product=VALUES(id_product), sort=VALUES(sort)'); if (Tools::getValue('mb_price') !== '') { Db::getInstance()->update('pulse_inv_item', array('sale_price' => (float) Tools::getValue('mb_price')), 'id_pulse_inv_item='.(int) Tools::getValue('mb_item')); } $this->confirmations[] = $this->l('Minibar item saved'); }
            if (Tools::isSubmit('removeParItem')) { Db::getInstance()->delete('pulse_inv_minibar_item', 'id_pulse_inv_item='.(int) Tools::getValue('mb_item_r')); }
            if (Tools::isSubmit('saveAmenity')) { Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_inv_amenity` (id_pulse_inv_item,id_product,par_per_room,frequency) VALUES ('.(int) Tools::getValue('am_item').','.((int) Tools::getValue('am_type') ?: 'NULL').','.(float) Tools::getValue('am_par').',"'.pSQL(Tools::getValue('am_freq')).'") ON DUPLICATE KEY UPDATE par_per_room=VALUES(par_per_room), frequency=VALUES(frequency), id_product=VALUES(id_product)'); $this->confirmations[] = $this->l('Amenity saved'); }
            if (Tools::isSubmit('removeAmenity')) { Db::getInstance()->delete('pulse_inv_amenity', 'id_pulse_inv_item='.(int) Tools::getValue('am_item_r')); }
            if (Tools::isSubmit('postAmenities')) { $n = PulseInvMinibar::postDailyAmenities(Tools::getValue('am_date', PulseInvService::bd())); $this->confirmations[] = sprintf($this->l('%d amenity consumption line(s) posted'), $n); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
