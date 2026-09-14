<?php
/** Kitchen display at /pulse/kds?station=KITCHEN (large screen, auto-refresh). */
class PulsePosKdsModuleFrontController extends ModuleFrontController
{
    protected $idHotel = 0;

    public function init()
    {
        parent::init();
        $this->display_header = false;
        $this->display_footer = false;
        // Station codes repeat across properties — every kitchen has a KITCHEN — so the screen has to
        // establish its property before the station list is read, or it shows another hotel's kitchen.
        $this->idHotel = PulsePosService::enterHotel();
    }

    public function initContent()
    {
        parent::initContent();
        if (!$this->idHotel) {
            PulseCoreService::refuseNoHotel('This kitchen display is not enrolled to a property. Enrol it in Pulse POS, Devices, or open the address your property was given.');
        }
        $this->context->smarty->assign(array('stations' => PulseDb::executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_station` WHERE active=1 AND kds=1'), 'station' => Tools::getValue('station', ''), 'pos_api' => $this->context->link->getModuleLink('pulsepos', 'api', array(), true), 'pos_css' => $this->module->getPathUri().'views/css/pos.css', 'late_min' => (int) Configuration::get('PULSE_POS_KDS_LATE_MIN')));
        $this->setTemplate('kds.tpl');
    }
    public function setMedia() { return true; }
}
