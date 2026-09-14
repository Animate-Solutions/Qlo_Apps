<?php
/** Touch POS single-page app at /pulse/pos (tablet / touchscreen). Auth by staff PIN via the API; device token optional. */
class PulsePosAppModuleFrontController extends ModuleFrontController
{
    protected $idHotel = 0;

    public function init()
    {
        parent::init();
        $this->display_header = false;
        $this->display_footer = false;
        // The outlet list below is this property's, and so is every check the app goes on to open, so
        // the till says which property it is before it is handed a single outlet name.
        $this->idHotel = PulsePosService::enterHotel();
    }

    public function initContent()
    {
        parent::initContent();
        if (!$this->idHotel) {
            PulseCoreService::refuseNoHotel('This till is not enrolled to a property. Enrol it in Pulse POS, Devices, or open the address your property was given.');
        }
        $outlets = PulseDb::executeS('SELECT id_pulse_pos_outlet id, code, name, type, require_covers, allow_room_charge, allow_tabs, service_charge_pct FROM `'._DB_PREFIX_.'pulse_pos_outlet` WHERE active=1 ORDER BY id_pulse_pos_outlet');
        $cur = $this->context->currency;
        $this->context->smarty->assign(array('pos_outlets' => json_encode($outlets), 'pos_api' => $this->context->link->getModuleLink('pulsepos', 'api', array(), true), 'pos_currency' => $cur ? $cur->sign : '₦', 'pos_hotel' => Configuration::get('PS_SHOP_NAME'), 'pos_css' => $this->module->getPathUri().'views/css/pos.css', 'pos_js' => $this->module->getPathUri().'views/js/pos.js', 'pos_sw' => $this->module->getPathUri().'views/js/sw.js', 'pos_cfg' => json_encode(array('autoLogout' => (int) Configuration::get('PULSE_POS_AUTO_LOGOUT_MIN'), 'tipOnCard' => (int) Configuration::get('PULSE_POS_TIP_ON_CARD'), 'roomGuestCheck' => (int) Configuration::get('PULSE_POS_ROOM_GUEST_CHECK'), 'defaultTender' => Configuration::get('PULSE_POS_DEFAULT_TENDER'), 'currencies' => Currency::getCurrencies(false, true)))));
        $this->setTemplate('app.tpl');
    }
    public function setMedia() { return true; }
}
