<?php
/** Single-screen walk-in / phone / day-use reservation with live availability and rate quote. */
class AdminPulseWalkInController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('New Reservation'); }

    public function initContent()
    {
        parent::initContent();
        $types = Db::getInstance()->executeS('SELECT rt.id_product, pl.name, rt.adults, rt.children, bl.hotel_name FROM `'._DB_PREFIX_.'htl_room_type` rt INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=rt.id_product AND pl.id_lang='.(int) $this->context->language->id.' AND pl.id_shop='.(int) $this->context->shop->id.' LEFT JOIN `'._DB_PREFIX_.'htl_branch_info_lang` bl ON bl.id=rt.id_hotel AND bl.id_lang='.(int) $this->context->language->id.' ORDER BY bl.hotel_name, pl.name');
        $pms = array(); foreach (PaymentModule::getInstalledPaymentModules() as $m) { $pms[] = $m['name']; }
        $this->context->smarty->assign(array('types' => $types, 'business_date' => PulseCoreService::businessDate(), 'payment_codes' => PulseChargeCode::all(1), 'ajax_url' => $this->context->link->getAdminLink('AdminPulseWalkIn'), 'arrivals_url' => $this->context->link->getAdminLink('AdminPulseArrivals'),
            'companies' => Db::getInstance()->executeS('SELECT id_pulse_company, name, discount_pct FROM `'._DB_PREFIX_.'pulse_company` WHERE active=1 ORDER BY name'), 'walkin_module' => Configuration::get('PULSE_FD_WALKIN_PAYMENT_MODULE'), 'payment_modules' => $pms, 'prefill' => array('id_product' => (int) Tools::getValue('id_product'), 'from' => Tools::getValue('from'), 'to' => Tools::getValue('to'), 'id_room' => (int) Tools::getValue('id_room'))));
        $this->setTemplate('walkin.tpl');
    }

    protected function json($d) { die(json_encode($d)); }

    public function ajaxProcessQuote()
    {
        $from = Tools::getValue('from'); $to = Tools::getValue('to'); $out = array();
        foreach (Db::getInstance()->executeS('SELECT rt.id_product, pl.name FROM `'._DB_PREFIX_.'htl_room_type` rt INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=rt.id_product AND pl.id_lang='.(int) $this->context->language->id.' AND pl.id_shop='.(int) $this->context->shop->id) as $t) {
            $av = PulseReservation::availability($t['id_product'], $from, $to); $q = PulseReservation::quote($t['id_product'], $from, $to);
            $rooms = PulseRoom::availableRooms($t['id_product'], $from, $to);
            $out[] = array_merge($t, $av, $q, array('rooms' => $rooms));
        }
        $this->json(array('ok' => true, 'types' => $out));
    }

    public function ajaxProcessCustomerLookup()
    {
        $q = pSQL(Tools::getValue('q'));
        $this->json(array('ok' => true, 'customers' => Db::getInstance()->executeS('SELECT c.id_customer, c.firstname, c.lastname, c.email, gp.phone, gp.vip_level, gp.blacklisted, gp.stays FROM `'._DB_PREFIX_.'customer` c LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=c.id_customer WHERE c.deleted=0 AND (c.email LIKE "%'.$q.'%" OR c.lastname LIKE "%'.$q.'%" OR gp.phone LIKE "%'.$q.'%") LIMIT 10')));
    }

    public function ajaxProcessCreate()
    {
        try {
            $rooms = json_decode(Tools::getValue('rooms'), true); if (!$rooms) { throw new PrestaShopException('Select at least one room'); }
            $dayUse = (bool) Tools::getValue('day_use'); $from = Tools::getValue('from'); $to = $dayUse ? date('Y-m-d', strtotime($from.' +1 day')) : Tools::getValue('to');
            if ($dayUse) { foreach ($rooms as &$r) { $r['rate_override'] = (float) Tools::getValue('day_use_rate'); } }
            $res = PulseReservation::create(array('firstname' => Tools::getValue('firstname'), 'lastname' => Tools::getValue('lastname'), 'email' => Tools::getValue('email'), 'phone' => Tools::getValue('phone'), 'nationality' => Tools::getValue('nationality')), $from, $to, $rooms,
                array('source' => Tools::getValue('source', 'walkin'), 'day_use' => $dayUse, 'day_use_until' => Tools::getValue('day_use_until'), 'comment' => Tools::getValue('comment'), 'deposit' => (float) Tools::getValue('deposit'), 'deposit_method' => Tools::getValue('deposit_method', 'CASH')));
            if (Tools::getValue('id_company')) { $b = PulseFdService::booking($res['bookings'][0]); PulseGuestProfile::save($b['id_customer'], array('id_pulse_company' => (int) Tools::getValue('id_company'))); if (Tools::getValue('bill_to_company')) { PulseRouting::add('booking', $res['bookings'][0], 'rooms', 'company'); } }
            if (Tools::getValue('checkin_now')) { foreach ($res['bookings'] as $id) { PulseFdService::checkIn($id, array('id_type' => Tools::getValue('id_type'), 'id_number' => Tools::getValue('id_number'), 'issuing_country' => Tools::getValue('issuing_country')), null, array('early' => true)); } }
            $this->json(array('ok' => true, 'id_order' => $res['id_order'], 'bookings' => $res['bookings']));
        } catch (Exception $e) { $this->json(array('ok' => false, 'error' => $e->getMessage())); }
    }
}
