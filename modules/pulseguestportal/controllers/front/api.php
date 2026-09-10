<?php
/**
 * /pulse/api/portal/{resource}/{id} — the in-room screens.
 *
 * Auth, in order of preference:
 *   X-Pulse-Device:  <64 hex>   the device token issued at pairing — says which ROOM the screen is in
 *   X-Pulse-Session: <sid.exp.sig>  the short-lived signed guest session — says which STAY it may see
 *   Authorization: Bearer <token>   a pulse_api_token with scope `portal`, for integrations and the desk
 * `pair` and `ping` are the only resources reachable without one, and every call is rate limited per device.
 * Nothing personal is served unless the room has a checked-in booking: PulseGpSession does that gate.
 */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulseguestportal/classes/autoload.php';

class PulseGuestPortalApiModuleFrontController extends PulseApiController
{
    protected $device = null; protected $session = null; protected $body = array(); protected $deviceAuth = false;
    protected $resources = array('ping' => 'ping', 'pair' => 'pair', 'session' => 'session', 'home' => 'home', 'folio' => 'folio', 'menu' => 'menu', 'order' => 'order',
        'order_status' => 'orderStatus', 'request' => 'request', 'requests' => 'requests', 'messages' => 'messages', 'message_send' => 'messageSend',
        'directory' => 'directory', 'channels' => 'channels', 'vod' => 'vod', 'vod_play' => 'vodPlay', 'wakeup' => 'wakeup', 'checkout' => 'checkout',
        'feedback' => 'feedback', 'heartbeat' => 'heartbeat', 'room_control' => 'roomControl', 'cast' => 'cast', 'cast_claim' => 'castClaim', 'language' => 'language');

    protected function authenticate()
    {
        $res = Tools::getValue('resource', 'ping');
        $this->body = json_decode(Tools::file_get_contents('php://input'), true);
        if (!is_array($this->body)) { $this->body = array(); }
        if (file_exists(_PS_MODULE_DIR_.'pulselicense/classes/PulseLicenseService.php') && Module::isEnabled('pulselicense')) {
            require_once _PS_MODULE_DIR_.'pulselicense/classes/PulseLicenseService.php';
            PulseLicenseService::assertApi();
            if (!PulseLicenseService::entitled('pulseguestportal')) { throw new PrestaShopException('Guest Portal not licensed', 402); }
        }
        // hooks called downstream (tickets, traces, folio) expect an employee in context; the portal acts as the cron user
        $ctx = Context::getContext();
        if (empty($ctx->employee) || !$ctx->employee->id) { $ctx->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1); }
        $devTok = isset($_SERVER['HTTP_X_PULSE_DEVICE']) ? $_SERVER['HTTP_X_PULSE_DEVICE'] : Tools::getValue('device_token');
        if ($devTok) {
            $this->device = PulseGpDevice::byToken($devTok);
            if (!$this->device) { throw new PrestaShopException('Unknown device', 401); }
            $this->deviceAuth = true;
            PulseGpService::rateHit('dev'.(int) $this->device['id_pulse_gp_device']);
            if ($this->device['status'] === 'blocked') { throw new PrestaShopException('This screen has been taken out of service', 403); }
            if ($this->device['status'] !== 'active' && !in_array($res, array('ping', 'pair', 'heartbeat'))) { throw new PrestaShopException('Device is waiting for the front desk to pair it', 403); }
            $sessTok = isset($_SERVER['HTTP_X_PULSE_SESSION']) ? $_SERVER['HTTP_X_PULSE_SESSION'] : Tools::getValue('session_token');
            if ($sessTok) { $this->session = PulseGpSession::verify($sessTok, $this->device); }
            return;
        }
        if (in_array($res, array('ping', 'pair'))) { PulseGpService::rateHit('ip'.md5((string) Tools::getRemoteAddr()), (int) PulseGpService::cfg('RATE_PAIR_PER_MIN', 20)); return; }
        parent::authenticate();
        $this->requireScope('portal');
        PulseGpService::rateHit('tok'.(int) $this->token['id_pulse_api_token']);
        // an integration token may act on one device by id: /pulse/api/portal/home/12
        $id = (int) Tools::getValue('id');
        if ($id) { $this->device = PulseGpDevice::byId($id); }
    }

    /* ---------- helpers ---------- */
    protected function dev() { if (!$this->device) { throw new PrestaShopException('A device token is required', 401); } return $this->device; }
    /** Anything that touches one screen's own state needs that screen's token — the `portal` bearer is shared by the whole building. */
    protected function own() { if (!$this->deviceAuth) { throw new PrestaShopException('A device token is required for this call', 403); } return $this->dev(); }
    protected function guest() { $this->dev(); return PulseGpSession::requireGuest($this->session); }
    protected function lang() { $l = Tools::getValue('lang', isset($this->body['lang']) ? $this->body['lang'] : ''); if ($l) { return PulseGpService::lang($l); } return $this->session ? $this->session['locale'] : PulseGpService::lang($this->device ? $this->device['locale'] : ''); }
    protected function adultOk() { $pin = isset($this->body['pin']) ? $this->body['pin'] : Tools::getValue('pin'); return $pin !== '' && $pin !== false && PulseGpService::checkPin($pin); }

    /* ---------- resources ---------- */
    protected function ping() { return array('module' => 'pulseguestportal', 'version' => $this->module->version, 'business_date' => PulseGpService::bd(), 'server_time' => date('c'), 'languages' => PulseGpService::langs()); }

    /** The launcher's first call: MAC/serial in, pairing state out. */
    protected function pair($id, $b) { return PulseGpDevice::pair($b + array('mac' => Tools::getValue('mac'), 'serial' => Tools::getValue('serial'), 'model' => Tools::getValue('model'), 'firmware' => Tools::getValue('fw'))); }

    /**
     * Issue a signed session for this screen. A vacant room still gets one — with no booking attached.
     * Only the screen's own device token may mint one: the `portal` bearer token is shared by every TV in
     * the building, so allowing it here would let any screen cut a session (and read the folio) for any room.
     */
    protected function session($id, $b) { $s = PulseGpSession::issue($this->own(), isset($b['lang']) ? $b['lang'] : null); $s['sections'] = PulseGpService::sections(); $s['theme'] = PulseGpService::theme(); return $s; }

    protected function home() { return PulseGpService::home($this->dev(), $this->session, $this->lang()); }

    protected function folio()
    {
        $s = $this->guest();
        $f = PulseGpService::folioSummary((int) $s['id_htl_booking']);
        $f['express_checkout'] = (int) PulseGpService::sectionOn('checkout');
        $f['checkout_time'] = PulseGpService::cfg('CHECKOUT_TIME', '12:00');
        return $f;
    }

    protected function menu() { $this->dev(); return PulseGpDining::menu($this->lang()); }

    /** Place a room-service order. Idempotent on client_id so an offline replay cannot double-order. */
    protected function order($id, $b)
    {
        $s = $this->guest();
        $lines = isset($b['lines']) && is_array($b['lines']) ? $b['lines'] : array();
        return PulseGpDining::order($this->device, $s, $lines, isset($b['note']) ? $b['note'] : '', isset($b['client_id']) ? $b['client_id'] : '');
    }
    protected function orderStatus($id) { $s = $this->guest(); return $id ? array(PulseGpDining::get((int) $id, (int) $s['id_htl_booking'])) : PulseGpDining::roomOrders((int) $this->device['id_room'], 24, (int) $s['id_htl_booking']); }

    protected function request($id, $b)
    {
        $s = $this->guest();
        $type = isset($b['type']) ? $b['type'] : Tools::getValue('type');
        return PulseGpRequest::create($type, $this->device, $s, $b);
    }
    protected function requests() { $s = $this->guest(); return PulseGpRequest::recent((int) $this->device['id_room'], (int) $s['id_htl_booking']); }

    protected function messages()
    {
        $s = $this->guest();
        PulseGpMessaging::markReadByGuest((int) $s['id_htl_booking']);
        return array('thread' => PulseGpMessaging::thread((int) $s['id_htl_booking']), 'unread' => 0);
    }
    protected function messageSend($id, $b) { $s = $this->guest(); $idMsg = PulseGpMessaging::fromGuest($this->device, $s, isset($b['body']) ? $b['body'] : ''); return array('id' => $idMsg, 'thread' => PulseGpMessaging::thread((int) $s['id_htl_booking'])); }

    protected function directory()
    {
        $this->dev();
        $idProduct = $this->session && $this->session['id_htl_booking'] ? (int) Db::getInstance()->getValue('SELECT id_product FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id='.(int) $this->session['id_htl_booking']) : 0;
        return array('pages' => PulseGpContent::directory($this->lang(), $idProduct), 'categories' => PulseGpContent::categories(), 'promos' => PulseGpContent::promos('directory', $this->lang()));
    }

    protected function channels() { $this->dev(); return array('channels' => PulseGpEntertainment::channels($this->adultOk()), 'adult_locked' => PulseGpService::cfg('ADULT_PIN_HASH', '') ? 1 : 0, 'radio' => PulseGpEntertainment::radio(), 'apps' => PulseGpEntertainment::apps()); }
    protected function vod() { $this->dev(); return array('titles' => PulseGpEntertainment::vod($this->adultOk()), 'currency' => PulseGpService::theme(), 'in_house' => $this->session && $this->session['id_htl_booking'] ? 1 : 0); }
    protected function vodPlay($id, $b) { $this->dev(); return PulseGpEntertainment::play((int) ($id ? $id : (isset($b['id']) ? $b['id'] : 0)), $this->device, $this->session, isset($b['pin']) ? $b['pin'] : null); }

    protected function wakeup($id, $b) { $s = $this->guest(); return PulseGpRequest::create('wakeup', $this->device, $s, array('scheduled_for' => isset($b['at']) ? $b['at'] : (isset($b['scheduled_for']) ? $b['scheduled_for'] : ''), 'detail' => isset($b['detail']) ? $b['detail'] : '')); }

    /** Bill review + express check-out request in one call: GET reviews, POST asks the desk to close the stay. */
    protected function checkout($id, $b)
    {
        $s = $this->guest();
        $f = PulseGpService::folioSummary((int) $s['id_htl_booking']);
        $out = array('folio' => $f, 'checkout_time' => PulseGpService::cfg('CHECKOUT_TIME', '12:00'), 'late_until' => PulseGpService::cfg('LATE_CHECKOUT_UNTIL', '14:00'),
            'feedback_given' => PulseGpFeedback::given((int) $s['id_htl_booking']) ? 1 : 0, 'requested' => 0);
        if (!empty($b['confirm'])) { $r = PulseGpRequest::create('express_checkout', $this->device, $s, array('detail' => isset($b['note']) ? $b['note'] : '')); $out['requested'] = 1; $out['request'] = $r; }
        if (!empty($b['late_checkout'])) { $out['late_request'] = PulseGpRequest::create('late_checkout', $this->device, $s, array('scheduled_for' => isset($b['until']) ? $b['until'] : '', 'detail' => isset($b['note']) ? $b['note'] : '')); }
        return $out;
    }

    protected function feedback($id, $b) { $s = $this->guest(); return array('id' => PulseGpFeedback::save($this->device, $s, $b), 'thanks' => 1); }

    /** Heartbeat: keeps the device online, carries the command queue back and acks what the screen has done. */
    protected function heartbeat($id, $b)
    {
        $d = $this->own();
        if (!empty($b['ack']) && is_array($b['ack'])) { PulseGpDevice::ackCommands((int) $d['id_pulse_gp_device'], $b['ack']); }
        $cmds = PulseGpDevice::heartbeat((int) $d['id_pulse_gp_device'], $b);
        $fresh = PulseGpDevice::byId((int) $d['id_pulse_gp_device']);
        $out = array('commands' => $cmds, 'status' => $fresh ? $fresh['status'] : $d['status'],
            'server_time' => date('c'), 'business_date' => PulseGpService::bd(), 'heartbeat' => (int) PulseGpService::cfg('HEARTBEAT_SEC', 60), 'unread' => 0, 'orders' => array());
        if ($this->session && $this->session['id_htl_booking']) {
            $out['unread'] = PulseGpMessaging::unreadForGuest((int) $this->session['id_htl_booking']);
            $out['orders'] = PulseGpDining::roomOrders((int) $this->device['id_room'], 6, (int) $this->session['id_htl_booking']);
            $out['balance'] = PulseGpService::folioSummary((int) $this->session['id_htl_booking'], false);
        }
        return $out;
    }

    /** Room controls: GET lists the points, POST applies one action. */
    protected function roomControl($id, $b)
    {
        $s = $this->guest();
        $idRoom = (int) $this->device['id_room'];
        if (!empty($b['code']) && !empty($b['action'])) { return array('point' => PulseGpControl::apply($idRoom, $b['code'], $b['action'], isset($b['value']) ? $b['value'] : null), 'points' => PulseGpControl::points($idRoom)); }
        return array('enabled' => PulseGpControl::enabled() ? 1 : 0, 'adapter' => PulseGpService::cfg('CONTROL_ADAPTER', 'PulseGpControlSimulator'), 'points' => PulseGpControl::points($idRoom), 'stay' => (int) $s['id_htl_booking']);
    }

    protected function cast($id, $b) { $d = $this->own(); if (!empty($b['end'])) { PulseGpEntertainment::castEnd((int) $d['id_pulse_gp_device']); return array('status' => 'ended'); } return PulseGpEntertainment::castCode($d, isset($b['protocol']) ? $b['protocol'] : 'chromecast'); }
    /** Called by the guest's phone (scope portal), not by the TV. */
    protected function castClaim($id, $b) { return PulseGpEntertainment::castClaim(isset($b['code']) ? $b['code'] : '', isset($b['pin']) ? $b['pin'] : '', isset($b['device']) ? $b['device'] : 'guest device'); }

    protected function language($id, $b)
    {
        $d = $this->own(); $lang = PulseGpService::lang(isset($b['lang']) ? $b['lang'] : '');
        Db::getInstance()->update('pulse_gp_device', array('locale' => pSQL($lang), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_device='.(int) $d['id_pulse_gp_device']);
        if ($this->session) { PulseGpSession::setLocale((int) $this->session['id_pulse_gp_session'], $lang); }
        return array('lang' => $lang, 'rtl' => PulseGpService::rtl($lang) ? 1 : 0);
    }
}
