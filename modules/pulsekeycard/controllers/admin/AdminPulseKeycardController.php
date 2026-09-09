<?php
/** Key Desk — search a room or guest, then issue / duplicate / extend / cancel. Keyboard-first, big buttons. */
class AdminPulseKeycardController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Key Desk'); }

    /** Back-office guard: cutting or killing a key is an edit, not a view. */
    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to issue or cancel keys')); }
    }

    public function initContent()
    {
        parent::initContent();
        $q = Tools::getValue('q', '');
        $encoders = PulseKcEncoder::statusAll();
        $current = null;
        try { $current = PulseKcEncoder::pick((int) Tools::getValue('id_encoder') ?: null); } catch (Exception $e) { $this->warnings[] = $e->getMessage(); }
        $sel = (int) Tools::getValue('id_htl_booking');
        $this->context->smarty->assign(array(
            'q' => $q, 'results' => PulseKcService::search($q), 'arrivals' => PulseKcService::arrivalsWithoutKeys(),
            'encoders' => $encoders, 'current_encoder' => $current, 'doors' => PulseKcService::doors(true),
            'default_doors' => PulseKcService::defaultDoorIds(), 'kpi' => PulseKcService::dashboard(), 'jobs' => PulseKcService::jobs('queued,failed'),
            'booking' => $sel ? $this->bookingCard($sel) : null, 'fd' => PulseKcService::fd(),
            'mobile_enabled' => (int) PulseKcService::cfg('MOBILE_ENABLED', 1), 'business_date' => PulseKcService::bd(),
            'local_agent' => (int) PulseKcService::cfg('LOCAL_AGENT', 1), 'agent_port' => (int) PulseKcService::cfg('AGENT_PORT', 7070),
            'self_url' => self::$currentIndex.'&token='.$this->token, 'ajax_url' => $this->context->link->getAdminLink('AdminPulseKeycard'),
        ));
        $this->setTemplate('desk.tpl');
    }

    /** Everything the desk shows for one stay: keys, mobile keys, the room's recent door events. */
    protected function bookingCard($idBooking)
    {
        $b = PulseKcService::fd() ? PulseFdService::booking((int) $idBooking) : null;
        if (!$b) { return null; }
        list($from, $to) = PulseKcService::window($b['date_from'], $b['date_to']);
        $b['suggested_from'] = $from; $b['suggested_to'] = $to;
        $b['keys'] = PulseKcKey::search(array('id_htl_booking' => (int) $idBooking), 30);
        $b['mobile_keys'] = PulseKcMobileKey::forBooking((int) $idBooking);
        $b['audit'] = PulseKcAudit::forRoom((int) $b['id_room'], date('Y-m-d H:i:s', strtotime('-3 day')), null, 25);
        return $b;
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('setWorkstation')) { PulseKcEncoder::setWorkstation(PulseKcService::emp(), (int) Tools::getValue('id_encoder')); $this->confirmations[] = $this->l('Workstation encoder saved'); }
            if (Tools::isSubmit('issueKey')) {
                $this->assertEdit();
                $rooms = array_filter(array_map('intval', (array) Tools::getValue('rooms', array())));
                $id = PulseKcKey::issue(array('type' => Tools::getValue('type', 'guest'), 'id_htl_booking' => (int) Tools::getValue('id_htl_booking') ?: null,
                    'rooms' => $rooms, 'doors' => array_filter(array_map('intval', (array) Tools::getValue('doors', array()))),
                    'valid_from' => Tools::getValue('valid_from'), 'valid_to' => Tools::getValue('valid_to'),
                    'override_deadbolt' => (int) Tools::getValue('override_deadbolt'), 'override_dnd' => (int) Tools::getValue('override_dnd'),
                    'id_encoder' => (int) Tools::getValue('id_encoder') ?: null, 'guest_name' => Tools::getValue('guest_name'), 'note' => Tools::getValue('note')));
                $k = PulseKcKey::get($id);
                $this->confirmations[] = sprintf($this->l('Key %s cut — card %s, sequence %s'), $k['key_no'], $k['card_serial'] ?: '—', $k['sequence']);
            }
            if (Tools::isSubmit('duplicateKey')) { $this->assertEdit(); $id = PulseKcKey::duplicate((int) Tools::getValue('id_key')); $k = PulseKcKey::get($id); $this->confirmations[] = sprintf($this->l('Duplicate %s cut'), $k['key_no']); }
            if (Tools::isSubmit('reissueKey')) { $this->assertEdit(); $id = PulseKcKey::reissue((int) Tools::getValue('id_key'), Tools::getValue('reason', 'Card lost')); $k = PulseKcKey::get($id); $this->confirmations[] = sprintf($this->l('Old card killed, %s issued'), $k['key_no']); }
            if (Tools::isSubmit('extendKey')) { $this->assertEdit(); PulseKcKey::extend((int) Tools::getValue('id_key'), Tools::getValue('valid_to')); $this->confirmations[] = $this->l('Key extended — put the card back on the encoder to write the new date'); }
            if (Tools::isSubmit('cancelKey')) { $this->assertEdit(); PulseKcKey::cancel((int) Tools::getValue('id_key'), Tools::getValue('reason', 'Cancelled at the desk')); $this->confirmations[] = $this->l('Key cancelled'); }
            if (Tools::isSubmit('cancelRoomKeys')) { $this->assertEdit(); $n = PulseKcKey::cancelForRoom((int) Tools::getValue('id_room'), Tools::getValue('reason', 'Lost card')); $this->confirmations[] = sprintf($this->l('%d key(s) cancelled for the room'), $n); }
            if (Tools::isSubmit('retryKey')) { $this->assertEdit(); PulseKcKey::retryEncode((int) Tools::getValue('id_key'), (int) Tools::getValue('id_encoder') ?: null); $this->confirmations[] = $this->l('Key encoded'); }
            if (Tools::isSubmit('mechanicalKey')) { $this->assertEdit(); PulseKcKey::recordMechanical((int) Tools::getValue('id_room'), (int) Tools::getValue('id_htl_booking') ?: null, Tools::getValue('note', 'Encoder offline')); $this->confirmations[] = $this->l('Mechanical key recorded — collect it at check-out'); }
            if (Tools::isSubmit('issueMobile')) {
                $this->assertEdit();
                $id = PulseKcMobileKey::issue((int) Tools::getValue('id_htl_booking'), array('channel' => Tools::getValue('channel', 'both')));
                $m = PulseKcMobileKey::get($id);
                $this->confirmations[] = sprintf($this->l('Mobile key issued and sent (%s)'), $m['delivered_via'] ?: 'not delivered');
            }
            if (Tools::isSubmit('revokeMobile')) { $this->assertEdit(); PulseKcMobileKey::revoke((int) Tools::getValue('id_mobile'), Tools::getValue('reason', 'Revoked at the desk')); $this->confirmations[] = $this->l('Mobile key revoked'); }
        } catch (PulseKcEncoderException $e) { $this->errors[] = $e->userMessage(); }
        catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    protected function json($d) { die(json_encode($d)); }

    /** The clerk's browser reports what the local encoder agent said; a good report keeps the encoder green. */
    public function ajaxProcessAgentReport()
    {
        $id = (int) Tools::getValue('id_encoder');
        $ok = (int) Tools::getValue('ok') === 1;
        PulseKcEncoder::markSeen($id, $ok, Tools::getValue('error'));
        $this->json(array('ok' => true, 'status' => $ok ? 'online' : 'offline'));
    }

    /** Server-side probe, used when the adapter is not local-only or the browser agent is missing. */
    public function ajaxProcessTestEncoder() { $this->json(PulseKcEncoder::test((int) Tools::getValue('id_encoder'))); }

    /** Read whatever card is on the encoder — "whose key is this?" at the desk. */
    public function ajaxProcessReadCard()
    {
        try {
            $e = PulseKcEncoder::pick((int) Tools::getValue('id_encoder') ?: null);
            $res = PulseKcEncoder::adapter($e)->readCard($e['encoder_ref']);
            PulseKcEncoder::markSeen((int) $e['id_pulse_kc_encoder'], true);
            if (!empty($res['card_serial'])) {
                $res['key'] = Db::getInstance()->getRow('SELECT k.key_no, k.type, k.status, k.guest_name, k.room_nums, k.valid_to, k.id_htl_booking FROM `'._DB_PREFIX_.'pulse_kc_key` k WHERE k.card_serial="'.pSQL($res['card_serial']).'" ORDER BY k.id_pulse_kc_key DESC');
            }
            $this->json(array('ok' => true, 'card' => $res));
        } catch (PulseKcEncoderException $e) { $this->json(array('ok' => false, 'error' => $e->userMessage())); }
        catch (Exception $e) { $this->json(array('ok' => false, 'error' => $e->getMessage())); }
    }

    public function ajaxProcessSearch() { $this->json(array('ok' => true, 'rows' => PulseKcService::search(Tools::getValue('q')))); }
    public function ajaxProcessDashboard() { $this->json(array('ok' => true, 'kpi' => PulseKcService::dashboard(), 'encoders' => PulseKcEncoder::statusAll())); }
}
