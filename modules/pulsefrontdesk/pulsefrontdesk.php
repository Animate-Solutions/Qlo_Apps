<?php
/**
 * Pulse Front Desk — PMS front-office layer for QloApps 1.7
 * Benchmarks: eZee Front Desk, Oracle OPERA Cloud (front-office scope)
 * @author Animate Solutions Limited
 */
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__).'/classes/autoload.php';

class PulseFrontDesk extends Module
{
    const VERSION = '1.1.0';

    /** Sub-menu under the Pulse tab: class => label */
    protected $tabs = array(
        'AdminPulseFdDashboard'    => 'Front Desk',
        'AdminPulseRoomBoard'      => 'Room Board',
        'AdminPulseTapeChart'      => 'Tape Chart',
        'AdminPulseWalkIn'         => 'New Reservation',
        'AdminPulseArrivals'       => 'Arrivals & Departures',
        'AdminPulseGroups'         => 'Groups & Blocks',
        'AdminPulseWaitlist'       => 'Waitlist & Overbooking',
        'AdminPulseTickets'        => 'Guest Services & Tickets',
        'AdminPulseFolio'          => 'Folios & Cashier',
        'AdminPulseHousekeeping'   => 'Housekeeping',
        'AdminPulseGuestProfile'   => 'Guest Profiles',
        'AdminPulseCompany'        => 'Companies / City Ledger',
        'AdminPulseNightAudit'     => 'Night Audit',
        'AdminPulseFdReports'      => 'Reports',
        'AdminPulseFdSettings'     => 'Front Desk Settings',
    );

    protected $hooks = array(
        'displayBackOfficeHeader', 'displayAdminOrderContentOrder', 'actionOrderStatusPostUpdate',
        'actionObjectHotelBookingDetailAddAfter', 'actionObjectHotelRoomInformationAddAfter',
        // raised by this module (registered so other modules can listen)
        'actionPulseRoomStatusChange', 'actionPulseFolioPost', 'actionPulseCheckIn', 'actionPulseCheckOut',
        'actionPulseRoomMove', 'actionPulseNoShow', 'actionPulseHousekeepingTask', 'actionPulseNightAuditClosed', 'actionPulseStayChanged', 'actionPulseTicketCreated', 'moduleRoutes',
    );

    public function __construct()
    {
        $this->name = 'pulsefrontdesk';
        $this->tab = 'administration';
        $this->version = self::VERSION;
        $this->author = 'Animate Solutions Limited';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->dependencies = array('pulsecore');
        $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => '1.6.99');
        parent::__construct();
        $this->displayName = $this->l('Pulse Front Desk');
        $this->description = $this->l('Room board, check-in/out, folios, housekeeping, night audit and reports for QloApps.');
        $this->confirmUninstall = $this->l('Uninstall Front Desk? All folio, housekeeping and audit data will be dropped.');
    }

    public function install()
    {
        if (!parent::install()) { return false; }
        foreach ($this->hooks as $h) { if (!$this->registerHook($h)) { return false; } }
        if (!$this->runSql('install')) { return false; }
        $parent = (int) Tab::getIdFromClassName('AdminPulseCore');
        $i = 0;
        foreach ($this->tabs as $class => $label) {
            $t = new Tab(); $t->class_name = $class; $t->module = $this->name; $t->id_parent = $parent; $t->position = $i++;
            foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $label; }
            if (!$t->add()) { return false; }
        }
        Configuration::updateValue('PULSE_FD_CHECKIN_TIME', '14:00');
        Configuration::updateValue('PULSE_FD_CHECKOUT_TIME', '12:00');
        Configuration::updateValue('PULSE_FD_REQUIRE_ID', 1);
        Configuration::updateValue('PULSE_FD_REQUIRE_INSPECTION', 0);
        Configuration::updateValue('PULSE_FD_NO_SHOW_CHARGE', 1);
        Configuration::updateValue('PULSE_FD_LATE_FEE', 0);
        Configuration::updateValue('PULSE_FD_CRON_TOKEN', Tools::passwdGen(32));
        Configuration::updateValue('PULSE_FD_LATE_GRACE', 60);
        Configuration::updateValue('PULSE_FD_PRECHECKIN_DAYS', 2);
        Configuration::updateValue('PULSE_FD_TERMS_VERSION', '1.0');
        Configuration::updateValue('PULSE_FD_SMS_CHANNEL', 'sms');
        Configuration::updateValue('PULSE_FD_WALKIN_PAYMENT_MODULE', 'bankwire');
        Configuration::updateValue('PULSE_FD_WALKIN_ORDER_STATE', (int) Configuration::get('PS_OS_PAYMENT'));
        Configuration::updateValue('PULSE_FD_VERSION', self::VERSION);
        PulseCoreService::setting($this->name, 'business_date', date('Y-m-d'));
        PulseCoreService::setting($this->name, 'require_id', '1');
        PulseCoreService::setting($this->name, 'checkout_time', '12:00');
        PulseRoom::syncRooms();
        return true;
    }

    public function uninstall()
    {
        foreach ($this->tabs as $class => $label) {
            if ($id = (int) Tab::getIdFromClassName($class)) { $t = new Tab($id); $t->delete(); }
        }
        foreach (array('CHECKIN_TIME', 'CHECKOUT_TIME', 'REQUIRE_ID', 'REQUIRE_INSPECTION', 'NO_SHOW_CHARGE', 'LATE_FEE', 'LATE_GRACE', 'CRON_TOKEN', 'OS_CHECKIN', 'OS_CHECKOUT', 'PRECHECKIN_DAYS', 'TERMS_VERSION', 'TERMS_TEXT', 'SMS_ADAPTER', 'SMS_API_KEY', 'SMS_SENDER', 'SMS_CHANNEL', 'WALKIN_PAYMENT_MODULE', 'WALKIN_ORDER_STATE', 'PABX_DRIVER', 'PABX_URL', 'PABX_KEY', 'PABX_MAP', 'PABX_CODES', 'VERSION') as $k) { Configuration::deleteByName('PULSE_FD_'.$k); }
        return $this->runSql('uninstall') && parent::uninstall();
    }

    protected function runSql($file)
    {
        $sql = Tools::file_get_contents(dirname(__FILE__).'/sql/'.$file.'.sql');
        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), $sql);
        foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) {
            if (strpos($q, '--') === 0) { continue; }
            if (!Db::getInstance()->execute($q)) { return false; }
        }
        return true;
    }

    public function getContent() { Tools::redirectAdmin($this->context->link->getAdminLink('AdminPulseFdSettings')); }

    /** Pretty URLs for guest-facing pages and the API. */
    public function hookModuleRoutes()
    {
        return array(
            'pulsefrontdesk-precheckin' => array('controller' => 'precheckin', 'rule' => 'pulse/precheckin', 'keywords' => array(), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsefrontdesk-selfcheckout' => array('controller' => 'selfcheckout', 'rule' => 'pulse/checkout', 'keywords' => array(), 'params' => array('fc' => 'module', 'module' => $this->name)),
            'pulsefrontdesk-api' => array('controller' => 'api', 'rule' => 'pulse/api/frontdesk{/:resource}{/:id}', 'keywords' => array('resource' => array('regexp' => '[a-z_]+', 'param' => 'resource'), 'id' => array('regexp' => '[0-9]+', 'param' => 'id')), 'params' => array('fc' => 'module', 'module' => $this->name)),
        );
    }

    /* ---------------- hooks ---------------- */

    public function hookDisplayBackOfficeHeader()
    {
        if (strpos($this->context->controller->controller_name, 'AdminPulse') === 0) {
            $this->context->controller->addCSS($this->_path.'views/css/frontdesk.css');
            $this->context->controller->addJS($this->_path.'views/js/frontdesk.js');
            $this->context->controller->addJS($this->_path.'views/js/tapechart.js');
            $this->context->controller->addJS($this->_path.'views/js/signature.js');
        }
    }

    /** Front-desk panel on the QloApps order page: per-room status, folio balance, quick actions. */
    public function hookDisplayAdminOrderContentOrder($params)
    {
        $rows = Db::getInstance()->executeS('SELECT b.id, b.room_num, b.room_type_name, b.date_from, b.date_to, b.id_status, f.folio_no, f.balance, f.id_pulse_folio
            FROM `'._DB_PREFIX_.'htl_booking_detail` b LEFT JOIN `'._DB_PREFIX_.'pulse_folio` f ON f.id_htl_booking=b.id AND f.status="open"
            WHERE b.id_order='.(int) $params['order']->id);
        $this->context->smarty->assign(array('rooms' => $rows, 'link_arrivals' => $this->context->link->getAdminLink('AdminPulseArrivals'), 'link_folio' => $this->context->link->getAdminLink('AdminPulseFolio')));
        return $this->display(__FILE__, 'views/templates/hook/order_panel.tpl');
    }

    /** Cancelled/refunded orders free the room on the board. */
    public function hookActionOrderStatusPostUpdate($params)
    {
        $cancelStates = array((int) Configuration::get('PS_OS_CANCELED'), (int) Configuration::get('PS_OS_REFUND'));
        if (in_array((int) $params['newOrderStatus']->id, $cancelStates)) {
            foreach (Db::getInstance()->executeS('SELECT id_room FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_order='.(int) $params['id_order']) as $r) {
                PulseRoom::setFoStatus($r['id_room'], 'vacant', null);
            }
        }
    }

    public function hookActionObjectHotelBookingDetailAddAfter($params)
    {
        $b = $params['object'];
        PulseGuestProfile::touch($b->id_customer);
        if ($b->date_from === PulseCoreService::businessDate()) { PulseRoom::setFoStatus($b->id_room, 'due_in', $b->id); }
    }

    public function hookActionObjectHotelRoomInformationAddAfter($params) { PulseRoom::syncRooms(); }

    // Event hooks raised by this module — empty here, other modules implement listeners.
    public function hookActionPulseRoomStatusChange($p) {}
    public function hookActionPulseFolioPost($p) {}
    public function hookActionPulseCheckIn($p) {}
    public function hookActionPulseCheckOut($p) {}
    public function hookActionPulseRoomMove($p) {}
    public function hookActionPulseNoShow($p) {}
    public function hookActionPulseHousekeepingTask($p) {}
    public function hookActionPulseNightAuditClosed($p) {}
    public function hookActionPulseStayChanged($p) {}
    public function hookActionPulseTicketCreated($p) {}
}
