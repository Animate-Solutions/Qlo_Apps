<?php
/** Guest express check-out: /pulse/selfcheckout?t=<token> — review folio, settle (card on file / payment link), confirm departure. */
require_once _PS_MODULE_DIR_.'pulsefrontdesk/classes/autoload.php';

class PulseFrontDeskSelfcheckoutModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    protected $booking; protected $ext; protected $folio;

    public function init()
    {
        parent::init();
        $t = preg_replace('/[^a-f0-9]/', '', Tools::getValue('t'));
        $this->ext = $t ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_booking_ext` WHERE checkout_token="'.pSQL($t).'"') : null;
        $this->booking = $this->ext ? PulseFdService::booking($this->ext['id_htl_booking']) : null;
        $this->folio = $this->booking ? PulseFolio::openForBooking($this->booking['id']) : null;
    }

    public function postProcess()
    {
        if (!$this->booking || !$this->folio || Tools::getValue('submitCheckout') === false) { return; }
        try {
            if ((int) $this->booking['id_status'] !== HotelBookingDetail::STATUS_CHECKED_IN) { throw new PrestaShopException('This stay is not in-house'); }
            $opts = array('no_receipt' => false);
            if ($this->folio->balance > 0.009) {
                if ($this->ext['card_auth_ref']) { $opts['settle_method'] = 'CAPTURE'; }
                else { throw new PrestaShopException('There is a balance of '.number_format($this->folio->balance, 2).' on your bill. Please pay via the link below or at the front desk.'); }
            }
            PulseFdService::checkOut($this->booking['id'], $opts);
            PulseTrace::add('trace', 'Express check-out completed by guest — collect keys from room '.$this->booking['room_num'], date('Y-m-d H:i:s'), $this->booking['id'], $this->booking['id_room'], $this->booking['id_customer']);
            $this->context->smarty->assign('done', true);
        } catch (Exception $e) { $this->context->smarty->assign('error', $e->getMessage()); }
    }

    public function initContent()
    {
        parent::initContent();
        if (!$this->booking || !$this->folio) { $this->context->smarty->assign('invalid', true); }
        else {
            $this->folio = new PulseFolio($this->folio->id);
            $this->context->smarty->assign(array('b' => $this->booking, 'folio' => $this->folio, 'lines' => $this->folio->lines(), 'ext' => $this->ext, 'pay_url' => $this->folio->balance > 0.009 ? PulsePaymentBridge::paymentLink($this->booking['id'], $this->folio, $this->folio->balance) : null, 'hotel' => Configuration::get('PS_SHOP_NAME'), 'currency' => $this->context->currency->sign));
        }
        $this->setTemplate('selfcheckout.tpl');
    }
}
