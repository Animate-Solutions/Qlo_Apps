<?php
/** Folio list + folio view (post, void, transfer, settle, close, print) + cashier shift open/close. */
class AdminPulseFolioController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; $this->table = 'pulse_folio'; $this->className = 'PulseFolio'; $this->identifier = 'id_pulse_folio';
        $this->list_no_link = false; $this->addRowAction('view'); $this->_orderBy = 'date_add'; $this->_orderWay = 'DESC';
        parent::__construct();
        $this->meta_title = $this->l('Folios & Cashier');
        $this->_select = 'r.room_num, CONCAT(c.firstname," ",c.lastname) guest, comp.name company';
        $this->_join = 'LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=a.id_room LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=a.id_customer LEFT JOIN `'._DB_PREFIX_.'pulse_company` comp ON comp.id_pulse_company=a.id_pulse_company';
        $this->fields_list = array(
            'folio_no' => array('title' => $this->l('Folio')), 'type' => array('title' => $this->l('Type')),
            'room_num' => array('title' => $this->l('Room'), 'havingFilter' => true), 'guest' => array('title' => $this->l('Guest'), 'havingFilter' => true), 'company' => array('title' => $this->l('Company'), 'havingFilter' => true),
            'total_charges' => array('title' => $this->l('Charges'), 'type' => 'price'), 'total_payments' => array('title' => $this->l('Payments'), 'type' => 'price'), 'balance' => array('title' => $this->l('Balance'), 'type' => 'price'),
            'status' => array('title' => $this->l('Status')), 'date_add' => array('title' => $this->l('Opened'), 'type' => 'datetime'),
        );
    }

    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
        $emp = (int) $this->context->employee->id;
        $this->toolbar_btn['cashier'] = array('href' => self::$currentIndex.'&token='.$this->token.'&cashier=1', 'desc' => PulseCashierSession::currentId($emp) ? $this->l('Close my shift') : $this->l('Open cashier shift'), 'icon' => 'process-icon-cogs');
    }

    public function initContent()
    {
        if (Tools::getValue('cashier')) { return $this->cashierScreen(); }
        if ($this->display === 'view') { return $this->folioView(); }
        parent::initContent();
    }

    protected function folioView()
    {
        $f = new PulseFolio((int) Tools::getValue('id_pulse_folio'));
        if (!Validate::isLoadedObject($f)) { $this->errors[] = 'Folio not found'; return parent::initContent(); }
        $b = $f->id_htl_booking ? PulseFdService::booking($f->id_htl_booking) : null;
        $this->context->smarty->assign(array(
            'folio' => $f, 'lines' => $f->lines(true), 'booking' => $b,
            'charge_codes' => PulseChargeCode::all(0), 'payment_codes' => PulseChargeCode::all(1),
            'open_folios' => Db::getInstance()->executeS('SELECT id_pulse_folio, folio_no, type FROM `'._DB_PREFIX_.'pulse_folio` WHERE status="open" AND id_pulse_folio<>'.(int) $f->id.' ORDER BY folio_no'),
            'companies' => Db::getInstance()->executeS('SELECT id_pulse_company, name, credit_limit, ledger_balance FROM `'._DB_PREFIX_.'pulse_company` WHERE active=1 ORDER BY name'),
            'ajax_url' => $this->context->link->getAdminLink('AdminPulseRoomBoard'), 'self_url' => self::$currentIndex.'&token='.$this->token.'&id_pulse_folio='.$f->id.'&viewpulse_folio',
            'currencies' => Currency::getCurrencies(false, true), 'rules' => $b ? PulseRouting::forBooking($b['id']) : array(), 'regcard' => $b ? PulseRegistrationCard::forBooking($b['id']) : null,
            'hotel' => $b ? $b['hotel_name'] : Configuration::get('PS_SHOP_NAME'), 'currency' => $this->context->currency->sign,
        ));
        $this->context->smarty->assign('content', $this->context->smarty->fetch($this->getTemplatePath().'pulse_folio/view.tpl'));
    }

    protected function cashierScreen()
    {
        $emp = (int) $this->context->employee->id; $id = PulseCashierSession::currentId($emp);
        $this->context->smarty->assign(array('session_id' => $id, 'totals' => $id ? PulseCashierSession::totals($id) : array(),
            'movements' => $id ? PulseCashierSession::movements($id) : array(), 'employees' => Employee::getEmployees(), 'currencies' => Currency::getCurrencies(false, true), 'session' => $id ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_cashier_session` WHERE id_pulse_cashier_session='.$id) : null,
            'self_url' => self::$currentIndex.'&token='.$this->token.'&cashier=1'));
        $this->context->smarty->assign('content', $this->context->smarty->fetch($this->getTemplatePath().'pulse_folio/cashier.tpl'));
    }

    public function postProcess()
    {
        $emp = (int) $this->context->employee->id;
        try {
            if (Tools::isSubmit('openShift')) { PulseCashierSession::open($emp, (float) Tools::getValue('opening_float')); $this->confirmations[] = $this->l('Shift opened'); }
            if (Tools::isSubmit('drawerMove')) { PulseCashierSession::movement(PulseCashierSession::currentId($emp), Tools::getValue('type'), (float) Tools::getValue('amount'), Tools::getValue('note'), (int) Tools::getValue('witness') ?: null); $this->confirmations[] = $this->l('Drawer movement recorded'); }
            if (Tools::isSubmit('postFx')) { $f = new PulseFolio((int) Tools::getValue('id_pulse_folio')); $f->postForeignPayment(Tools::getValue('code', 'CASH'), Tools::getValue('description', 'Foreign currency payment'), (float) Tools::getValue('amount_foreign'), Tools::getValue('iso'), 'cash_fx'); $this->confirmations[] = $this->l('Foreign currency payment posted'); }
            if (Tools::isSubmit('emailFolio')) { $f = new PulseFolio((int) Tools::getValue('id_pulse_folio')); PulseFdService::emailReceipt($f->id_htl_booking, $f); $this->confirmations[] = $this->l('Folio emailed'); }
            if (Tools::isSubmit('addBookingRule')) { $f = new PulseFolio((int) Tools::getValue('id_pulse_folio')); PulseRouting::add('booking', $f->id_htl_booking, Tools::getValue('department'), Tools::getValue('target'), (int) Tools::getValue('to_folio') ?: null); $this->confirmations[] = $this->l('Routing rule added'); }
            if (Tools::isSubmit('closeShift')) { PulseCashierSession::close(PulseCashierSession::currentId($emp), (float) Tools::getValue('counted_cash'), Tools::getValue('note')); $this->confirmations[] = $this->l('Shift closed'); }
            if (Tools::isSubmit('voidLine')) { $f = new PulseFolio((int) Tools::getValue('id_pulse_folio')); $f->voidLine((int) Tools::getValue('id_line'), Tools::getValue('reason')); $this->confirmations[] = $this->l('Line voided'); }
            if (Tools::isSubmit('transferLine')) { $f = new PulseFolio((int) Tools::getValue('id_pulse_folio')); $f->transferLine((int) Tools::getValue('id_line'), new PulseFolio((int) Tools::getValue('to_folio'))); $this->confirmations[] = $this->l('Line transferred'); }
            if (Tools::isSubmit('settleCL')) { $f = new PulseFolio((int) Tools::getValue('id_pulse_folio')); $f->settleToCityLedger(new PulseCompany((int) Tools::getValue('id_company'))); $this->confirmations[] = $this->l('Routed to city ledger'); }
            if (Tools::isSubmit('closeFolio')) { $f = new PulseFolio((int) Tools::getValue('id_pulse_folio')); $f->close(); $this->confirmations[] = $this->l('Folio closed'); }
            if (Tools::isSubmit('postLine')) {
                $f = new PulseFolio((int) Tools::getValue('id_pulse_folio'));
                $f->post(Tools::getValue('code'), Tools::getValue('description'), (float) Tools::getValue('qty', 1), (float) Tools::getValue('unit_price'), Tools::getValue('tax_rate') === '' ? null : (float) Tools::getValue('tax_rate'), null, Tools::getValue('payment_method'));
                $this->confirmations[] = $this->l('Posted');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
