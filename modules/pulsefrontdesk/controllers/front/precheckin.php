<?php
/** Guest pre-arrival check-in: /pulse/precheckin?t=<token> — verify details, capture ID, accept terms, sign, pick upsells, optional card hold. */
require_once _PS_MODULE_DIR_.'pulsefrontdesk/classes/autoload.php';

class PulseFrontDeskPrecheckinModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    protected $booking; protected $ext;

    public function init()
    {
        parent::init();
        $t = preg_replace('/[^a-f0-9]/', '', Tools::getValue('t'));
        $this->ext = $t ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_booking_ext` WHERE precheckin_token="'.pSQL($t).'"') : null;
        $this->booking = $this->ext ? PulseFdService::booking($this->ext['id_htl_booking']) : null;
    }

    public function postProcess()
    {
        if (!$this->booking || Tools::getValue('submitPrecheckin') === false) { return; }
        try {
            $id = (int) $this->booking['id']; $cust = (int) $this->booking['id_customer'];
            PulseGuestProfile::save($cust, array('phone' => Tools::getValue('phone'), 'nationality' => Tools::getValue('nationality'), 'address' => Tools::getValue('address'), 'preferences' => array('pillow' => Tools::getValue('pref_pillow'), 'floor' => Tools::getValue('pref_floor'), 'dietary' => Tools::getValue('pref_dietary'), 'other' => Tools::getValue('pref_other'))));
            if (Tools::getValue('id_number')) {
                $scan = '';
                if (isset($_FILES['id_scan']) && $_FILES['id_scan']['tmp_name'] && in_array($_FILES['id_scan']['type'], array('image/jpeg', 'image/png', 'application/pdf')) && $_FILES['id_scan']['size'] < 5000000) {
                    $dir = _PS_MODULE_DIR_.'pulsefrontdesk/uploads/'; if (!is_dir($dir)) { mkdir($dir, 0755, true); file_put_contents($dir.'.htaccess', "Deny from all\n"); }
                    $scan = 'uploads/'.sha1($id.time()).'.'.pathinfo($_FILES['id_scan']['name'], PATHINFO_EXTENSION); move_uploaded_file($_FILES['id_scan']['tmp_name'], _PS_MODULE_DIR_.'pulsefrontdesk/'.$scan);
                }
                Db::getInstance()->insert('pulse_guest_identity', array('id_customer' => $cust, 'id_htl_booking' => $id, 'id_type' => pSQL(Tools::getValue('id_type')), 'id_number' => pSQL(Tools::getValue('id_number')), 'issuing_country' => pSQL(Tools::getValue('issuing_country')), 'expiry' => Tools::getValue('expiry') ?: null, 'scan_path' => pSQL($scan), 'id_employee' => 0, 'date_add' => date('Y-m-d H:i:s')));
            }
            if (!Tools::getValue('accept_terms') || !Tools::getValue('signature')) { throw new PrestaShopException('Please accept the terms and sign'); }
            PulseRegistrationCard::sign($id, Tools::getValue('signature'), Tools::getValue('signed_name') ?: $this->booking['guest'], 'precheckin');
            foreach ((array) Tools::getValue('upsell') as $json) { $o = json_decode($json, true); if ($o) { PulseUpsell::accept($id, $o, 'precheckin'); } }
            if ((float) Tools::getValue('preauth') > 0) { PulsePaymentBridge::preAuthorize($id, (float) Tools::getValue('preauth')); }
            Db::getInstance()->update('pulse_booking_ext', array('precheckin_done' => 1), 'id_htl_booking='.$id);
            PulseTrace::add('trace', 'Pre-check-in completed online by '.$this->booking['guest'].' — key can be pre-cut', date('Y-m-d H:i:s', strtotime($this->booking['date_from'].' 08:00')), $id, $this->booking['id_room'], $cust);
            $this->context->smarty->assign('done', true);
        } catch (Exception $e) { $this->context->smarty->assign('error', $e->getMessage()); }
    }

    public function initContent()
    {
        parent::initContent();
        if (!$this->booking) { $this->context->smarty->assign('invalid', true); }
        else {
            $this->context->smarty->assign(array('b' => $this->booking, 'ext' => $this->ext, 'profile' => PulseGuestProfile::get($this->booking['id_customer']), 'terms' => PulseRegistrationCard::terms(), 'offers' => PulseUpsell::offersFor($this->booking['id']), 'preauth_available' => PulsePaymentBridge::available(), 'hotel' => Configuration::get('PS_SHOP_NAME'), 'currency' => $this->context->currency->sign, 'module_dir' => $this->module->getPathUri()));
        }
        $this->setTemplate('precheckin.tpl');
    }
}
