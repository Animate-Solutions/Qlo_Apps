<?php
/** /pulse/api/crm/{resource}/{id} — the in-room TV portal (feedback, points, redeem), the desk tablet and marketing tooling. */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulsecrm/classes/autoload.php';

class PulseCrmApiModuleFrontController extends PulseApiController
{
    protected $resources = array('ping' => 'ping', 'profile' => 'profile', 'preferences_save' => 'preferencesSave', 'enrol' => 'enrol', 'points' => 'points',
        'redeem' => 'redeem', 'survey_get' => 'surveyGet', 'survey_submit' => 'surveySubmit', 'feedback' => 'feedback', 'opt_out' => 'optOut');

    protected $portalSession = null;

    /** Any one of the listed scopes is enough — the desk tablet and the TV portal carry different tokens. */
    protected function requireAny(array $scopes)
    {
        $have = explode(',', $this->token['scopes']);
        foreach ($scopes as $s) { if (in_array($s, $have)) { return true; } }
        throw new PrestaShopException('Forbidden: needs one of '.implode(', ', $scopes), 403);
    }

    /** True when the caller carries only the portal scope — that token is shared by every screen in the building. */
    protected function portalOnly()
    {
        $have = explode(',', $this->token['scopes']);
        return !in_array('desk', $have) && !in_array('marketing', $have);
    }

    /**
     * Which guest this call is about. The portal token is shared by every TV, so a portal-only caller may
     * never name a guest, a customer id or a room: the stay comes from the screen's own signed device
     * session and nothing else. Desk and marketing tokens are trusted to address a guest directly.
     */
    protected function customerFor($id, array $body)
    {
        if ($this->portalOnly()) { $s = $this->portal(); return (int) $s['id_customer']; }
        if (!empty($body['id_customer'])) { return (int) $body['id_customer']; }
        if (!empty($body['id_room']) || (!$id && !empty($body['room_num']))) {
            $where = !empty($body['id_room']) ? 'b.id_room='.(int) $body['id_room'] : 'r.room_num="'.pSQL($body['room_num']).'"';
            $row = Db::getInstance()->getRow('SELECT b.id_customer, b.id id_htl_booking FROM `'._DB_PREFIX_.'htl_booking_detail` b
                INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room
                WHERE '.$where.' AND b.is_cancelled=0 AND b.is_refunded=0 AND b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' ORDER BY b.date_from DESC');
            if ($row) { return (int) $row['id_customer']; }
            throw new PrestaShopException('No guest is checked in to that room', 404);
        }
        if (!$id) { throw new PrestaShopException('Give an id_customer, an id_room or a room_num', 400); }
        return (int) $id;
    }

    /** The in-room screen's verified session: X-Pulse-Device says which room, X-Pulse-Session which stay. */
    protected function portal()
    {
        if ($this->portalSession) { return $this->portalSession; }
        $auto = _PS_MODULE_DIR_.'pulseguestportal/classes/autoload.php';
        if (!file_exists($auto) || !Module::isEnabled('pulseguestportal')) { throw new PrestaShopException('Forbidden: a desk token is required to name a guest', 403); }
        require_once $auto;
        $devTok = isset($_SERVER['HTTP_X_PULSE_DEVICE']) ? $_SERVER['HTTP_X_PULSE_DEVICE'] : Tools::getValue('device_token');
        $sesTok = isset($_SERVER['HTTP_X_PULSE_SESSION']) ? $_SERVER['HTTP_X_PULSE_SESSION'] : Tools::getValue('session_token');
        $dev = $devTok ? PulseGpDevice::byToken($devTok) : null;
        if (!$dev || $dev['status'] !== 'active' || !$sesTok) { throw new PrestaShopException('This screen has no guest session', 403); }
        $s = PulseGpSession::verify($sesTok, $dev);
        if (empty($s['id_customer']) || empty($s['id_htl_booking'])) { throw new PrestaShopException('No guest is checked into this room', 403); }
        $this->portalSession = $s;
        return $s;
    }

    /** The stay a call applies to: from the screen's session for a portal caller, from the body for the desk. */
    protected function bookingFor(array $body)
    {
        if ($this->portalOnly()) { $s = $this->portal(); return (int) $s['id_htl_booking']; }
        return !empty($body['id_htl_booking']) ? (int) $body['id_htl_booking'] : 0;
    }

    protected function ping() { return array('module' => 'pulsecrm', 'version' => $this->module->version, 'business_date' => PulseCrmService::bd(), 'front_desk' => PulseCrmService::fd()); }

    /** The guest 360 as the desk tablet needs it — profile, preferences, occasions, consent, loyalty, open cases. */
    protected function profile($id, $body)
    {
        $this->requireAny(array('desk', 'marketing'));
        $idc = $this->customerFor($id, $body);
        $p = PulseCrmProfile::get($idc);
        unset($p['identities']);
        return $p;
    }

    /** Portal or desk: save preferences the guest states. Free-form values are accepted; the category is not. */
    protected function preferencesSave($id, $body)
    {
        $this->requireAny(array('portal', 'desk'));
        $idc = $this->customerFor($id, $body);
        $saved = 0;
        foreach (isset($body['preferences']) && is_array($body['preferences']) ? $body['preferences'] : array() as $p) {
            if (empty($p['category'])) { continue; }
            PulseCrmProfile::savePreference($idc, $p['category'], isset($p['value']) ? $p['value'] : '', isset($p['code']) ? $p['code'] : null, isset($body['source']) ? $body['source'] : 'portal');
            $saved++;
        }
        if (!empty($body['occasions']) && is_array($body['occasions'])) {
            foreach ($body['occasions'] as $o) { if (!empty($o['date'])) { PulseCrmProfile::saveOccasion($idc, isset($o['type']) ? $o['type'] : 'birthday', $o['date'], isset($o['note']) ? $o['note'] : ''); $saved++; } }
        }
        if (isset($body['preferred_channel'])) { PulseCrmProfile::saveExt($idc, array('preferred_channel' => $body['preferred_channel'])); }
        return array('saved' => $saved, 'preferences' => PulseCrmProfile::get($idc)['crm_preferences']);
    }

    /** Enrol into the loyalty programme from the desk or the in-room portal. */
    protected function enrol($id, $body)
    {
        $this->requireAny(array('portal', 'desk'));
        $idc = $this->customerFor($id, $body);
        $idMember = PulseCrmLoyalty::enrol($idc, isset($body['source']) ? $body['source'] : 'portal');
        $m = PulseCrmLoyalty::member($idMember);
        return array('id_member' => $idMember, 'member_no' => $m['member_no'], 'card_no' => $m['card_no'], 'tier' => $m['tier_name'], 'points' => (int) $m['points_balance']);
    }

    /** Balance, tier, what the balance is worth and the recent ledger — the TV portal's loyalty screen. */
    protected function points($id, $body)
    {
        $this->requireAny(array('portal', 'desk'));
        $idc = $this->customerFor($id, $body);
        $m = PulseCrmLoyalty::memberOf($idc);
        if (!$m) { return array('member' => false, 'enrol_url' => PulseCrmService::baseUrl()); }
        $next = null;
        foreach (PulseCrmLoyalty::tiers((int) $m['id_pulse_crm_loyalty_program']) as $t) {
            if ((int) $t['min_nights'] > (int) $m['qualifying_nights']) { $next = array('tier' => $t['name'], 'nights_needed' => (int) $t['min_nights'] - (int) $m['qualifying_nights']); break; }
        }
        return array('member' => true, 'member_no' => $m['member_no'], 'card_no' => $m['card_no'], 'tier' => $m['tier_name'], 'points' => (int) $m['points_balance'],
            'value' => round((int) $m['points_balance'] * (float) $m['point_value'], 2), 'min_redeem' => (int) $m['min_redeem_points'],
            'qualifying_nights' => (int) $m['qualifying_nights'], 'next_tier' => $next,
            'statement' => PulseCrmLoyalty::statement((int) $m['id_pulse_crm_member'], 10));
    }

    /** Redeem points onto the open folio for the room the portal is sitting in. */
    protected function redeem($id, $body)
    {
        $this->requireAny(array('portal', 'desk'));
        $idc = $this->customerFor($id, $body);
        $m = PulseCrmLoyalty::memberOf($idc);
        if (!$m) { throw new PrestaShopException('Not a loyalty member', 400); }
        $idBooking = $this->bookingFor($body);
        if (!$idBooking) { $idBooking = (int) Db::getInstance()->getValue('SELECT id FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_customer='.(int) $idc.' AND is_cancelled=0 AND is_refunded=0 AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' ORDER BY date_from DESC'); }
        return PulseCrmLoyalty::redeem((int) $m['id_pulse_crm_member'], isset($body['points']) ? (int) $body['points'] : 0, $idBooking ? $idBooking : null, isset($body['note']) ? $body['note'] : 'Portal redemption');
    }

    /** Fetch a survey by its signed token so the portal can render it in its own skin. */
    protected function surveyGet($id, $body)
    {
        $this->requireAny(array('portal', 'desk'));
        $token = isset($body['token']) ? $body['token'] : Tools::getValue('token');
        if ($token) {
            $r = PulseCrmSurvey::byToken($token);
            if (!$r) { throw new PrestaShopException('Unknown survey link', 404); }
            return array('token' => $r['token'], 'name' => $r['survey_name'], 'intro' => $r['intro'], 'status' => $r['status'], 'questions' => $r['questions']);
        }
        $code = isset($body['code']) ? $body['code'] : 'in_stay';
        $s = PulseCrmSurvey::byCode($code);
        if (!$s) { throw new PrestaShopException('Unknown survey', 404); }
        $idc = $this->customerFor($id, $body);
        $idBooking = $this->bookingFor($body);
        $inv = PulseCrmSurvey::invite((int) $s['id_pulse_crm_survey'], $idc, $idBooking ? $idBooking : null, 'portal');
        return array('token' => $inv['token'], 'name' => $s['name'], 'intro' => $s['intro'], 'status' => $inv['status'], 'questions' => PulseCrmSurvey::questions((int) $s['id_pulse_crm_survey']));
    }

    protected function surveySubmit($id, $body)
    {
        $this->requireAny(array('portal', 'desk'));
        $token = isset($body['token']) ? $body['token'] : Tools::getValue('token');
        $answers = isset($body['answers']) && is_array($body['answers']) ? $body['answers'] : array();
        $r = PulseCrmSurvey::submit($token, $answers, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);
        return array('ok' => true, 'nps' => $r['nps'] === null ? null : (int) $r['nps'], 'gss' => $r['gss'], 'thanks' => PulseCrmSurvey::byToken($token) ? PulseCrmSurvey::byToken($token)['thanks'] : 'Thank you');
    }

    /**
     * One-tap feedback from the in-room TV: a score and, optionally, a sentence. Anything at or below
     * the threshold opens a recovery case immediately — that is the whole point of a button on the TV.
     */
    protected function feedback($id, $body)
    {
        $this->requireAny(array('portal', 'desk'));
        $idc = $this->customerFor($id, $body);
        $score = isset($body['score']) ? (int) $body['score'] : null;
        $comment = isset($body['comment']) ? trim((string) $body['comment']) : '';
        if ($score === null && $comment === '') { throw new PrestaShopException('Send a score, a comment, or both', 400); }
        $s = PulseCrmSurvey::byCode(isset($body['survey']) ? $body['survey'] : 'in_stay');
        if (!$s) { throw new PrestaShopException('No in-stay survey is configured', 400); }
        $idBooking = $this->bookingFor($body);
        if (!$idBooking) { $idBooking = (int) Db::getInstance()->getValue('SELECT id FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_customer='.(int) $idc.' AND is_cancelled=0 AND is_refunded=0 AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' ORDER BY date_from DESC'); }
        $inv = PulseCrmSurvey::invite((int) $s['id_pulse_crm_survey'], $idc, $idBooking ? $idBooking : null, 'portal');
        $answers = array();
        foreach (PulseCrmSurvey::questions((int) $s['id_pulse_crm_survey']) as $q) {
            if ($q['type'] === 'nps' && $score !== null) { $answers[$q['code']] = max(0, min(10, $score)); }
            elseif ($q['type'] === 'text' && $comment !== '') { $answers[$q['code']] = $comment; }
            elseif ((int) $q['required']) { $answers[$q['code']] = $q['type'] === 'scale5' ? max(1, min(5, (int) round(($score === null ? 8 : $score) / 2))) : ($q['type'] === 'nps' ? ($score === null ? 8 : $score) : 'n/a'); }
        }
        $r = PulseCrmSurvey::submit($inv['token'], $answers, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);
        $case = (int) Db::getInstance()->getValue('SELECT id_pulse_crm_case FROM `'._DB_PREFIX_.'pulse_crm_case` WHERE id_pulse_crm_survey_response='.(int) $r['id_pulse_crm_survey_response']);
        return array('ok' => true, 'nps' => $r['nps'] === null ? null : (int) $r['nps'], 'sentiment' => $r['sentiment'], 'case_opened' => $case ? true : false, 'thanks' => $s['thanks']);
    }

    /** Opt a guest out of one channel, or all of them, with the reason on record. */
    protected function optOut($id, $body)
    {
        $this->requireAny(array('portal', 'marketing', 'desk'));
        $idc = $this->customerFor($id, $body);
        $channels = isset($body['channel']) ? array($body['channel']) : array('email', 'sms', 'whatsapp');
        $reason = isset($body['reason']) ? $body['reason'] : '';
        foreach ($channels as $ch) { PulseCrmProfile::setConsent($idc, $ch, 'opt_out', isset($body['source']) ? $body['source'] : 'api', 'API opt-out', $reason); }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_campaign_recipient` SET status="skipped", skip_reason="opted_out" WHERE id_customer='.(int) $idc.' AND status="queued"');
        PulseCrmJourney::cancelFor($idc, null, 'opted out');
        return array('ok' => true, 'channels' => $channels, 'consent' => PulseCrmProfile::consent($idc));
    }
}
