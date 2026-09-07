<?php
/** Kitchen display at /pulse/kds?station=KITCHEN (large screen, auto-refresh). */
class PulsePosKdsModuleFrontController extends ModuleFrontController
{
    public function init() { parent::init(); $this->display_header = false; $this->display_footer = false; }
    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array('stations' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_station` WHERE active=1 AND kds=1'), 'station' => Tools::getValue('station', ''), 'pos_api' => $this->context->link->getModuleLink('pulsepos', 'api', array(), true), 'pos_css' => $this->module->getPathUri().'views/css/pos.css', 'late_min' => (int) Configuration::get('PULSE_POS_KDS_LATE_MIN')));
        $this->setTemplate('kds.tpl');
    }
    public function setMedia() { return true; }
}
