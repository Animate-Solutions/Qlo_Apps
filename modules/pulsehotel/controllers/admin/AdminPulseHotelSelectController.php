<?php
/** The hotel picker: the first screen after sign-in, and where the top-bar switcher lands. */
class AdminPulseHotelSelectController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Choose Hotel'); }

    public function initContent()
    {
        parent::initContent();
        $rows = PulseHotelContext::allowed();
        if (!$rows) {
            // Nothing to work in. Say so plainly and end the session rather than showing an empty list.
            PulseHotelContext::log('no_access', null, null, 'picker');
            $this->context->employee->logout();
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin').'&pulse_no_hotel=1');
        }
        $this->context->smarty->assign(array(
            'hotels' => $rows,
            'current' => PulseHotelContext::id(),
            'back' => Tools::getValue('back', ''),
            'self_url' => self::$currentIndex.'&token='.$this->token,
            'employee_name' => trim($this->context->employee->firstname.' '.$this->context->employee->lastname),
            'business_date' => class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'),
        ));
        $this->setTemplate('select.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('chooseHotel')) {
            try {
                PulseHotelContext::set((int) Tools::getValue('id_hotel'), PulseHotelContext::id() ? 'switched' : 'selected');
                $back = Tools::getValue('back');
                $back = $back && preg_match('/^AdminPulse[A-Za-z]+$/', $back) ? $back : 'AdminPulseFdDashboard';
                if (!Tab::getIdFromClassName($back)) { $back = 'AdminPulseCore'; }
                Tools::redirectAdmin($this->context->link->getAdminLink($back));
            } catch (Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        if (Tools::isSubmit('signOut')) { $this->context->employee->logout(); Tools::redirectAdmin($this->context->link->getAdminLink('AdminLogin')); }
        return parent::postProcess();
    }

    /** The picker is the one Pulse screen that must render without a hotel already set. */
    public function viewAccess($disable = false) { return true; }
}
