<?php
/** Payment links: mint, copy, email, cancel — pre-arrival deposits, self check-out balances and city-ledger invoices. */
class AdminPulsePayLinksController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Payment Links'); }

    public function initContent()
    {
        parent::initContent();
        $rows = PulsePayLink::all(Tools::getValue('status') ?: null);
        foreach ($rows as &$r) { $r['url'] = PulsePayLink::url($r); $r['short_url'] = PulsePayLink::shortUrl($r); }
        $this->context->smarty->assign(array(
            'rows' => $rows, 'status' => Tools::getValue('status'), 'self_url' => self::$currentIndex.'&token='.$this->token,
            'inhouse' => PulsePayService::fd() ? Db::getInstance()->executeS('SELECT f.id_pulse_folio, f.folio_no, f.balance, f.id_htl_booking, r.room_num, CONCAT(c.firstname," ",c.lastname) guest FROM `'._DB_PREFIX_.'pulse_folio` f LEFT JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=f.id_htl_booking LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=f.id_customer WHERE f.status="open" AND f.type="guest" ORDER BY r.room_num') : array(),
            'gateways' => PulsePayService::gateways(true), 'comms' => class_exists('PulseComms'), 'currency' => $this->context->currency->sign, 'default_hours' => (int) Configuration::get('PULSE_PAY_LINK_HOURS'),
        ));
        $this->setTemplate('links.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('createLink')) {
                $l = PulsePayLink::create(array(
                    'amount' => (float) Tools::getValue('amount'), 'purpose' => Tools::getValue('purpose', 'folio'), 'gateway' => Tools::getValue('gateway'),
                    'id_pulse_folio' => (int) Tools::getValue('id_pulse_folio') ?: null, 'id_htl_booking' => (int) Tools::getValue('id_htl_booking') ?: null,
                    'customer_name' => Tools::getValue('customer_name'), 'customer_email' => Tools::getValue('customer_email'), 'customer_phone' => Tools::getValue('customer_phone'),
                    'title' => Tools::getValue('title'), 'note' => Tools::getValue('note'), 'expires_hours' => (int) Tools::getValue('expires_hours'),
                    'max_uses' => (int) Tools::getValue('max_uses', 1), 'amount_locked' => (int) Tools::getValue('amount_locked', 1), 'min_amount' => (float) Tools::getValue('min_amount'),
                ));
                if (Tools::getValue('send_now')) { PulsePayLink::send($l); }
                $this->confirmations[] = $this->l('Link created: ').PulsePayLink::url($l);
            }
            if (Tools::isSubmit('sendLink')) { $l = PulsePayLink::byToken(Tools::getValue('token_ref')); if ($l) { PulsePayLink::send($l); } $this->confirmations[] = $this->l('Link sent'); }
            if (Tools::isSubmit('cancelLink')) { PulsePayLink::cancel((int) Tools::getValue('id_link')); $this->confirmations[] = $this->l('Link cancelled'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
