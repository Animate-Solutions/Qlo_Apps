<?php
/** Delivered OTA reservations, the failed queue with one-click manual assign, and channel production. */
class AdminPulseChannelReservationsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Channel Reservations'); }

    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-m-01'));
        $to = Tools::getValue('to', PulseChService::businessDate());
        $idChannel = (int) Tools::getValue('id_channel');
        $this->context->smarty->assign(array(
            'channels' => PulseChService::channels(), 'id_channel' => $idChannel,
            'failed' => PulseChReservation::listing('failed', $idChannel, 100),
            'received' => PulseChReservation::listing('received', $idChannel, 100),
            'delivered' => PulseChReservation::listing('delivered,modified', $idChannel, 150),
            'cancelled' => PulseChReservation::listing('cancelled,ignored', $idChannel, 100),
            'detail' => Tools::getValue('id_res') ? PulseChReservation::one((int) Tools::getValue('id_res')) : null,
            'room_types' => PulseChService::roomTypes(), 'rate_plans' => PulseChService::ratePlans(),
            'production' => PulseChReservation::production($from, $to), 'from' => $from, 'to' => $to,
            'fd' => PulseChService::fd(), 'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('reservations.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('deliverRes')) { PulseChReservation::deliver((int) Tools::getValue('id_res_a')); $this->confirmations[] = $this->l('Booking created and on the tape chart.'); }
            if (Tools::isSubmit('retryRes')) { PulseChReservation::process((int) Tools::getValue('id_res_a')); $this->confirmations[] = $this->l('Retried.'); }
            if (Tools::isSubmit('cancelRes')) { PulseChReservation::cancel((int) Tools::getValue('id_res_a')); $this->confirmations[] = $this->l('Reservation cancelled.'); }
            if (Tools::isSubmit('ackRes')) { PulseChReservation::ack((int) Tools::getValue('id_res_a')) ? $this->confirmations[] = $this->l('Acknowledged to the channel.') : $this->warnings[] = $this->l('The channel did not accept the acknowledgement — see Logs.'); }
            if (Tools::isSubmit('assignRes')) {
                PulseChReservation::manualAssign((int) Tools::getValue('id_res_a'), array(
                    'id_product' => Tools::getValue('a_product'), 'id_pulse_ch_rate_plan' => Tools::getValue('a_plan'),
                    'date_from' => Tools::getValue('a_from'), 'date_to' => Tools::getValue('a_to'),
                    'rooms' => Tools::getValue('a_rooms'), 'adults' => Tools::getValue('a_adults'), 'children' => Tools::getValue('a_children'),
                    'guest_name' => Tools::getValue('a_guest'), 'email' => Tools::getValue('a_email'), 'amount_tax_incl' => Tools::getValue('a_amount'),
                    'notes' => Tools::getValue('a_notes'), 'ignore' => Tools::getValue('a_ignore'),
                ));
                $this->confirmations[] = $this->l('Reservation assigned and delivered.');
            }
            if (Tools::isSubmit('pasteRes')) {
                $body = json_decode(Tools::getValue('paste_json'), true);
                if (!is_array($body)) { throw new PrestaShopException($this->l('That is not valid JSON')); }
                $id = PulseChReservation::receive((int) Tools::getValue('paste_channel'), $body, 'manual');
                $this->confirmations[] = sprintf($this->l('Payload accepted as reservation #%d.'), $id);
            }
            if (Tools::isSubmit('pullNow')) { $r = PulseChReservation::pullAll((int) Tools::getValue('id_channel')); $this->confirmations[] = sprintf($this->l('%d reservation(s) pulled, %d delivered, %d failed.'), $r['pulled'], $r['delivered'], $r['failed']); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
