<?php
/** Visual room board (tape chart-lite): every room by floor, HK/FO colour, click for actions. All actions are AJAX. */
class AdminPulseRoomBoardController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Room Board'); }

    public function initContent()
    {
        parent::initContent();
        $hotels = Db::getInstance()->executeS('SELECT b.id, bl.hotel_name FROM `'._DB_PREFIX_.'htl_branch_info` b INNER JOIN `'._DB_PREFIX_.'htl_branch_info_lang` bl ON bl.id=b.id AND bl.id_lang='.(int) $this->context->language->id);
        $this->context->smarty->assign(array(
            'hotels' => $hotels, 'business_date' => PulseCoreService::businessDate(),
            'hk_statuses' => PulseRoom::HK_STATUSES, 'payment_codes' => PulseChargeCode::all(1),
            'ajax_url' => $this->context->link->getAdminLink('AdminPulseRoomBoard'), 'folio_url' => $this->context->link->getAdminLink('AdminPulseFolio'),
            'attendants' => Employee::getEmployees(),
            'companies' => Db::getInstance()->executeS('SELECT id_pulse_company, name FROM `'._DB_PREFIX_.'pulse_company` WHERE active=1 ORDER BY name'), 'currencies' => Currency::getCurrencies(false, true),
        ));
        $this->setTemplate('board.tpl');
    }

    protected function json($data) { die(json_encode($data)); }
    protected function fail($e) { $this->json(array('ok' => false, 'error' => $e instanceof Exception ? $e->getMessage() : $e)); }

    public function ajaxProcessBoard()
    {
        $this->json(array('ok' => true, 'rooms' => PulseRoom::board((int) Tools::getValue('id_hotel') ?: null, Tools::getValue('date') ?: null), 'business_date' => PulseCoreService::businessDate()));
    }

    public function ajaxProcessSetHk()
    {
        try { PulseRoom::setHkStatus((int) Tools::getValue('id_room'), Tools::getValue('status'), 'board', Tools::getValue('reason'), Tools::getValue('until')); $this->json(array('ok' => true)); }
        catch (Exception $e) { $this->fail($e); }
    }

    public function ajaxProcessCheckIn()
    {
        try {
            $identity = array('id_type' => Tools::getValue('id_type'), 'id_number' => Tools::getValue('id_number'), 'issuing_country' => Tools::getValue('issuing_country'), 'expiry' => Tools::getValue('expiry'));
            $opts = array('early' => (bool) Tools::getValue('early'), 'override_dirty' => (bool) Tools::getValue('override_dirty'), 'override_blacklist' => (bool) Tools::getValue('override_blacklist'),
                'deposit' => (float) Tools::getValue('deposit'), 'deposit_method' => Tools::getValue('deposit_method', 'CASH'),
                'signature' => Tools::getValue('signature'), 'signed_name' => Tools::getValue('signed_name'), 'preauth' => (float) Tools::getValue('preauth'), 'upsells' => json_decode(Tools::getValue('upsells', '[]'), true));
            $f = PulseFdService::checkIn((int) Tools::getValue('id_booking'), $identity, (int) Tools::getValue('id_room') ?: null, $opts);
            $this->json(array('ok' => true, 'folio' => $f->folio_no));
        } catch (Exception $e) { $this->fail($e); }
    }

    public function ajaxProcessCheckOut()
    {
        try {
            $f = PulseFdService::checkOut((int) Tools::getValue('id_booking'), array('settle_method' => Tools::getValue('settle_method'), 'id_company' => (int) Tools::getValue('id_company'), 'late_fee' => (float) Tools::getValue('late_fee'), 'refund_method' => Tools::getValue('refund_method'), 'fx_iso' => Tools::getValue('fx_iso'), 'fx_amount' => (float) Tools::getValue('fx_amount')));
            $this->json(array('ok' => true, 'folio' => $f->folio_no));
        } catch (Exception $e) { $this->fail($e); }
    }

    public function ajaxProcessRoomMove()
    {
        try { PulseFdService::roomMove((int) Tools::getValue('id_booking'), (int) Tools::getValue('to_room'), Tools::getValue('reason')); $this->json(array('ok' => true)); }
        catch (Exception $e) { $this->fail($e); }
    }

    public function ajaxProcessAvailableRooms()
    {
        $b = PulseFdService::booking((int) Tools::getValue('id_booking'));
        $this->json(array('ok' => true, 'rooms' => $b ? PulseRoom::availableRooms($b['id_product'], max($b['date_from'], PulseCoreService::businessDate()), $b['date_to'], $b['id']) : array()));
    }

    public function ajaxProcessAddTask()
    {
        PulseHousekeeping::createTask((int) Tools::getValue('id_room'), Tools::getValue('type', 'clean'), (int) Tools::getValue('priority', 5), Tools::getValue('note'), (int) Tools::getValue('assigned_to') ?: null);
        $this->json(array('ok' => true));
    }

    public function ajaxProcessPostCharge()
    {
        try {
            $f = new PulseFolio((int) Tools::getValue('id_folio'));
            if (!Validate::isLoadedObject($f)) { throw new PrestaShopException('Folio not found'); }
            $id = $f->post(Tools::getValue('code'), Tools::getValue('description'), (float) Tools::getValue('qty', 1), (float) Tools::getValue('unit_price'), Tools::getValue('tax_rate') === '' ? null : (float) Tools::getValue('tax_rate'), null, Tools::getValue('payment_method'));
            $this->json(array('ok' => true, 'id_line' => $id, 'balance' => $f->balance));
        } catch (Exception $e) { $this->fail($e); }
    }

    public function ajaxProcessUpsells() { $this->json(array('ok' => true, 'offers' => PulseUpsell::offersFor((int) Tools::getValue('id_booking')), 'terms' => PulseRegistrationCard::terms(), 'snapshot' => PulseRegistrationCard::snapshot((int) Tools::getValue('id_booking')), 'preauth_available' => PulsePaymentBridge::available(), 'suggested_room' => PulseRoom::autoAssign(PulseFdService::booking((int) Tools::getValue('id_booking'))['id_product'], PulseCoreService::businessDate(), PulseFdService::booking((int) Tools::getValue('id_booking'))['date_to'], PulseFdService::booking((int) Tools::getValue('id_booking'))['id_customer'], (int) Tools::getValue('id_booking')))); }

    public function ajaxProcessExtend()
    {
        try { $n = PulseReservation::changeDates((int) Tools::getValue('id_booking'), Tools::getValue('new_to')); $this->json(array('ok' => true, 'nights' => $n)); }
        catch (Exception $e) { $this->fail($e); }
    }

    public function ajaxProcessPreauth()
    {
        try { $this->json(array('ok' => true, 'auth' => PulsePaymentBridge::preAuthorize((int) Tools::getValue('id_booking'), (float) Tools::getValue('amount')))); }
        catch (Exception $e) { $this->fail($e); }
    }

    public function ajaxProcessAcceptUpsell()
    {
        try { PulseUpsell::accept((int) Tools::getValue('id_booking'), json_decode(Tools::getValue('offer'), true), 'instay'); $this->json(array('ok' => true)); }
        catch (Exception $e) { $this->fail($e); }
    }

    public function ajaxProcessBookingCard()
    {
        $b = PulseFdService::booking((int) Tools::getValue('id_booking'));
        if (!$b) { $this->fail('Not found'); }
        $f = PulseFolio::openForBooking($b['id']);
        $this->json(array('ok' => true, 'booking' => $b, 'folio' => $f ? array('id' => $f->id, 'no' => $f->folio_no, 'balance' => $f->balance, 'lines' => $f->lines()) : null,
            'profile' => PulseGuestProfile::get($b['id_customer']), 'traces' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_trace` WHERE status="open" AND id_htl_booking='.(int) $b['id'])));
    }

    public function ajaxProcessAddTrace()
    {
        PulseTrace::add(Tools::getValue('type', 'trace'), Tools::getValue('text'), Tools::getValue('due_at'), (int) Tools::getValue('id_booking') ?: null, (int) Tools::getValue('id_room') ?: null, (int) Tools::getValue('id_customer') ?: null, Tools::getValue('department', 'frontdesk'));
        $this->json(array('ok' => true));
    }
}
