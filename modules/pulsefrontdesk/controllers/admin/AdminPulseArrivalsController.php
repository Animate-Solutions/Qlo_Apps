<?php
/** Arrivals / departures / in-house lists for a date, with the same AJAX actions as the board (shared JS). */
class AdminPulseArrivalsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Arrivals & Departures'); }

    public function initContent()
    {
        parent::initContent();
        $date = Tools::getValue('date', PulseCoreService::businessDate());
        $this->context->smarty->assign(array(
            'date' => $date, 'business_date' => PulseCoreService::businessDate(),
            'arrivals' => PulseFdService::arrivals($date), 'departures' => PulseFdService::departures($date), 'inhouse' => PulseFdService::inHouse(),
            'ajax_url' => $this->context->link->getAdminLink('AdminPulseRoomBoard'), 'folio_url' => $this->context->link->getAdminLink('AdminPulseFolio'),
            'payment_codes' => PulseChargeCode::all(1), 'companies' => Db::getInstance()->executeS('SELECT id_pulse_company, name FROM `'._DB_PREFIX_.'pulse_company` WHERE active=1 ORDER BY name'),
            'currencies' => Currency::getCurrencies(false, true),
            'checkin_time' => Configuration::get('PULSE_FD_CHECKIN_TIME'), 'checkout_time' => Configuration::get('PULSE_FD_CHECKOUT_TIME'),
        ));
        $this->setTemplate('arrivals.tpl');
    }
}
