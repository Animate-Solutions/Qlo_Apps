<?php
/** Portal dashboard: what every screen in the building is doing right now, and what the guests have asked for. */
class AdminPulseGuestPortalController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Guest Portal'); }

    public function initContent()
    {
        parent::initContent();
        $bd = PulseGpService::bd();
        $from = Tools::getValue('from', date('Y-m-01')); $to = Tools::getValue('to', $bd);
        $this->context->smarty->assign(array(
            'counts' => PulseGpDevice::counts(), 'board' => PulseGpDevice::board(), 'business_date' => $bd,
            'orders' => PulseGpDining::today($bd), 'requests' => PulseGpRequest::queue('new,ack,in_progress'),
            'unread' => PulseGpMessaging::unreadForDesk(), 'inbox' => PulseGpMessaging::inbox(true),
            'feedback' => PulseGpFeedback::stats($from, $to), 'recent_feedback' => PulseGpFeedback::recent(10),
            'plays' => PulseGpEntertainment::plays($from, $to), 'control_log' => PulseGpControl::log(null, 15),
            'sections' => PulseGpService::sections(), 'langs' => PulseGpService::langs(), 'adapter' => PulseGpService::cfg('CONTROL_ADAPTER', 'PulseGpControlSimulator'),
            'portal_url' => $this->context->link->getModuleLink('pulseguestportal', 'portal', array(), true),
            'api_url' => $this->context->link->getModuleLink('pulseguestportal', 'api', array(), true),
            'from' => $from, 'to' => $to, 'pos_on' => PulseGpService::pos(), 'fd_on' => PulseGpService::fd(),
            'self_url' => self::$currentIndex.'&token='.$this->token,
            'devices_url' => $this->context->link->getAdminLink('AdminPulseGuestPortalDevices'),
            'messages_url' => $this->context->link->getAdminLink('AdminPulseGuestPortalMessages'),
        ));
        $this->setTemplate('dashboard.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('broadcast')) { $n = PulseGpMessaging::broadcast(Tools::getValue('body'), (int) $this->context->employee->id); $this->confirmations[] = sprintf($this->l('Message sent to %d occupied room(s)'), $n); }
            if (Tools::isSubmit('reloadAll')) { $n = PulseGpDevice::broadcast('reload', array('reason' => 'manual')); $this->confirmations[] = sprintf($this->l('Reload queued on %d screen(s)'), $n); }
            if (Tools::isSubmit('setRequest')) { PulseGpRequest::setStatus((int) Tools::getValue('id_request'), Tools::getValue('status')); $this->confirmations[] = $this->l('Request updated'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
