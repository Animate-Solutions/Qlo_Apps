<?php
/**
 * /pulse/hr — the staff self-service portal, opened on a member of staff's own phone.
 *
 * The page itself carries no personal data at all: it is a shell plus the API URL. Everything is fetched
 * after a staff number and PIN have been exchanged for a signed session, and the session — not the page —
 * decides whose data comes back. That way a shared phone, a cached page or a screenshot leaks nothing.
 */
class PulseHrEssModuleFrontController extends ModuleFrontController
{
    public function init() { parent::init(); $this->display_header = false; $this->display_footer = false; }
    public function setMedia() { return true; }

    public function initContent()
    {
        parent::initContent();
        $boot = array(
            'api' => $this->context->link->getModuleLink('pulsehr', 'api', array(), true),
            'hotel' => Configuration::get('PS_SHOP_NAME'),
            'enabled' => (int) PulseHrService::cfg('ESS_ENABLED', 1),
            'sections' => PulseHrEss::sections(),
            'ttl' => (int) PulseHrService::cfg('ESS_TTL_MIN', 30) * 60,
            'geo' => array('enforce' => (int) PulseHrService::cfg('GEO_ENFORCE', 1), 'radius' => (int) PulseHrService::cfg('GEO_RADIUS_M', 200), 'qr_waives' => (int) PulseHrService::cfg('GEO_QR_WAIVES', 1)),
            'business_date' => PulseHrService::bd(),
            'week_start' => (int) PulseHrService::cfg('WEEK_START', 1),
        );
        $this->context->smarty->assign(array(
            // JSON_HEX_TAG so a shop name containing </script> cannot close the inline block it is printed in.
            'hr_boot' => json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), 'hr_hotel' => Configuration::get('PS_SHOP_NAME'),
            'hr_css' => $this->module->getPathUri().'views/css/ess.css', 'hr_js' => $this->module->getPathUri().'views/js/ess.js',
            'hr_enabled' => (int) PulseHrService::cfg('ESS_ENABLED', 1),
        ));
        $this->setTemplate('ess.tpl');
    }
}
