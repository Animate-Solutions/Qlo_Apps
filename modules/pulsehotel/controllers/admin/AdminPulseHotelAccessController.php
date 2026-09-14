<?php
/** Who may work in which hotel. One row per employee, hotels ticked. */
class AdminPulseHotelAccessController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Hotel Access'); }

    public function initContent()
    {
        parent::initContent();
        $id = (int) Tools::getValue('id_employee');
        $this->context->smarty->assign(array(
            'employees' => PulseHotelAccess::employees(),
            'hotels' => PulseHotelContext::allHotels(),
            'edit_employee' => $id ? new Employee($id) : null,
            'edit_access' => $id ? PulseHotelAccess::forEmployee($id) : array(),
            'activity' => PulseHotelAccess::recentActivity(30),
            'profile_fallback' => PulseHotelContext::profileFallbackEnabled(),
            // A property with no charge codes and no accounts cannot take a booking, so say so here
            // rather than letting someone find out at the front desk.
            'unseeded' => PulseHotelSetup::unseeded(),
            'seed_preview' => Tools::getValue('seedPreview') ? $this->seedPreview() : null,
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('access.tpl');
    }

    /** What a copy would bring across, so the manifest can be read before anything is written. */
    protected function seedPreview()
    {
        $target = (int) Tools::getValue('target_hotel');
        $source = (int) Tools::getValue('source_hotel');
        if (!$target || !$source) { return null; }
        try {
            return array('target' => $target, 'source' => $source,
                'tables' => array_filter(PulseHotelSetup::cloneTo($target, $source, true)));
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            return null;
        }
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveAccess')) {
                $id = (int) Tools::getValue('id_employee');
                if (!$id) { throw new PrestaShopException('Choose an employee first'); }
                PulseHotelAccess::setAll($id, (array) Tools::getValue('hotels'), (int) Tools::getValue('default_hotel'), (int) Tools::getValue('can_switch', 1));
                $this->confirmations[] = $this->l('Hotel access saved');
            }
            if (Tools::isSubmit('revokeOne')) {
                PulseHotelAccess::revoke((int) Tools::getValue('id_employee'), (int) Tools::getValue('id_hotel'));
                $this->confirmations[] = $this->l('Access revoked');
            }
            if (Tools::isSubmit('seedHotel')) {
                $target = (int) Tools::getValue('target_hotel');
                $source = (int) Tools::getValue('source_hotel');
                $counts = array_filter(PulseHotelSetup::cloneTo($target, $source));
                $this->confirmations[] = sprintf($this->l('Copied %d rows across %d tables into that property'),
                    array_sum($counts), count($counts));
            }
            if (Tools::isSubmit('setFallback')) {
                Configuration::updateValue('PULSE_HOTEL_PROFILE_FALLBACK', (int) Tools::getValue('fallback'));
                $this->confirmations[] = $this->l('Setting saved');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
