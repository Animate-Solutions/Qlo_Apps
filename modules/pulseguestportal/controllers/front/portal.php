<?php
/**
 * /pulse/tv — the page the Samsung URL Launcher (or a tablet browser, or the Tizen wrapper) loads at boot.
 * It ships no guest data: everything personal is fetched over the API once the device has paired and a
 * session has been issued. The MAC arrives either as ?mac= from the launcher's macro or from the Tizen
 * wrapper's network API; the room is resolved server-side from the registry, never from the query string.
 */
class PulseGuestPortalPortalModuleFrontController extends ModuleFrontController
{
    protected $idHotel = 0;

    public function init()
    {
        parent::init();
        $this->display_header = false;
        $this->display_footer = false;
        // Which property before anything else: the branding, the sections and every API call the page
        // goes on to make belong to one hotel, and a screen we cannot place is a screen showing the
        // wrong hotel's building to a guest.
        $this->idHotel = PulseGpService::enterHotel();
    }

    public function setMedia() { return true; }

    public function initContent()
    {
        parent::initContent();
        if (!$this->idHotel) {
            PulseCoreService::refuseNoHotel('This screen has not been paired to a room yet, and its address does not name a property. Ask the front desk to pair it, or to check the address it was set up with.');
        }
        $theme = PulseGpService::theme();
        $boot = array(
            'api' => $this->context->link->getModuleLink('pulseguestportal', 'api', array(), true),
            'sw' => $this->module->getPathUri().'views/js/sw.js',
            'mac' => Tools::getValue('mac', Tools::getValue('macaddress', '')),
            'serial' => Tools::getValue('serial', Tools::getValue('duid', '')),
            'room_hint' => Tools::getValue('room', ''),
            'type' => Tools::getValue('type', 'tv'),
            'kiosk' => (int) Tools::getValue('kiosk', 0),
            'heartbeat' => (int) PulseGpService::cfg('HEARTBEAT_SEC', 60),
            'languages' => PulseGpService::langs(),
            'default_lang' => PulseGpService::defaultLang(),
            'sections' => PulseGpService::sections(),
            'theme' => $theme,
            'hotel' => $theme['hotel'],
        );
        $this->context->smarty->assign(array(
            'gp_boot' => json_encode($boot), 'gp_css' => $this->module->getPathUri().'views/css/portal.css', 'gp_js' => $this->module->getPathUri().'views/js/portal.js',
            'gp_theme' => $theme, 'gp_hotel' => $theme['hotel'], 'gp_logo' => $theme['logo'] ? __PS_BASE_URI__.$theme['logo'] : '',
        ));
        $this->setTemplate('portal.tpl');
    }
}
