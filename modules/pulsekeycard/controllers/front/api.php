<?php
/**
 * /pulse/api/keycard/{resource}/{id} — desk terminals and tablets (scope `desk`), the guest app / TV portal
 * (scope `portal`, mobile keys) and the security console (scope `security`, lock audit).
 * Key payloads never leave this API in clear: only the short-lived mobile credential does, and only to the
 * device the key is bound to.
 */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulsekeycard/classes/autoload.php';

class PulseKeycardApiModuleFrontController extends PulseApiController
{
    protected $resources = array('ping' => 'ping', 'issue' => 'issue', 'duplicate' => 'duplicate', 'cancel' => 'cancel', 'extend' => 'extend',
        'mobile_key' => 'mobileKey', 'mobile_key_refresh' => 'mobileKeyRefresh', 'encoder_status' => 'encoderStatus', 'audit' => 'audit');

    protected function ping() { return array('module' => 'pulsekeycard', 'version' => '1.0.0', 'adapters' => array_keys(PulseKcEncoder::adapters()), 'mobile' => (int) PulseKcService::cfg('MOBILE_ENABLED', 1)); }

    /** The mobile-key handle is a bearer of its own — the portal calls it without a scope but with the token. */
    protected function needsToken($body)
    {
        $t = Tools::getValue('token') ? Tools::getValue('token') : (isset($body['token']) ? $body['token'] : '');
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $t)) { throw new PrestaShopException('A valid mobile key token is required', 400); }
        return $t;
    }

    /** Desk tablet cuts a key. $id is the booking; body may carry rooms, doors, type, validity and overrides. */
    protected function issue($id, $body)
    {
        $this->requireScope('desk');
        try {
            $idKey = $id && PulseKcService::fd()
                ? PulseKcKey::issueForBooking($id, array('rooms' => isset($body['rooms']) ? $body['rooms'] : null, 'doors' => isset($body['doors']) ? $body['doors'] : null, 'id_encoder' => isset($body['id_encoder']) ? (int) $body['id_encoder'] : null))
                : PulseKcKey::issue(array('type' => isset($body['type']) ? $body['type'] : 'guest', 'rooms' => isset($body['rooms']) ? $body['rooms'] : array(),
                    'doors' => isset($body['doors']) ? $body['doors'] : null, 'valid_from' => isset($body['valid_from']) ? $body['valid_from'] : null,
                    'valid_to' => isset($body['valid_to']) ? $body['valid_to'] : null, 'guest_name' => isset($body['guest_name']) ? $body['guest_name'] : '',
                    'override_deadbolt' => !empty($body['override_deadbolt']), 'override_dnd' => !empty($body['override_dnd']), 'id_encoder' => isset($body['id_encoder']) ? (int) $body['id_encoder'] : null));
        } catch (PulseKcEncoderException $e) {
            throw new PrestaShopException($e->userMessage(), 503);
        }
        return $this->keyView($idKey);
    }

    protected function duplicate($id, $body)
    {
        $this->requireScope('desk');
        try { return $this->keyView(PulseKcKey::duplicate($id, isset($body['id_encoder']) ? array('id_encoder' => (int) $body['id_encoder']) : array())); }
        catch (PulseKcEncoderException $e) { throw new PrestaShopException($e->userMessage(), 503); }
    }

    protected function cancel($id, $body)
    {
        $this->requireScope('desk');
        if (!empty($body['all_for_room'])) { return array('cancelled' => PulseKcKey::cancelForRoom((int) $body['all_for_room'], isset($body['reason']) ? $body['reason'] : 'Lost card')); }
        PulseKcKey::cancel($id, isset($body['reason']) ? $body['reason'] : 'Cancelled via API');
        return $this->keyView($id);
    }

    protected function extend($id, $body)
    {
        $this->requireScope('desk');
        if (empty($body['valid_to'])) { throw new PrestaShopException('valid_to is required', 400); }
        try { PulseKcKey::extend($id, $body['valid_to']); }
        catch (PulseKcEncoderException $e) { throw new PrestaShopException($e->userMessage(), 503); }
        return $this->keyView($id);
    }

    /**
     * Guest app / TV portal. With a token: fetch (and bind) the credential. Without one but with a booking id
     * and the desk scope: issue a mobile key and return its delivery handle.
     */
    protected function mobileKey($id, $body)
    {
        $t = Tools::getValue('token') ? Tools::getValue('token') : (isset($body['token']) ? $body['token'] : '');
        if (!$t) {
            $this->requireScope('desk');
            if (!$id) { throw new PrestaShopException('Booking id is required to issue a mobile key', 400); }
            $idM = PulseKcMobileKey::issue($id, array('channel' => isset($body['channel']) ? $body['channel'] : 'both', 'device_id' => isset($body['device_id']) ? $body['device_id'] : null,
                'device_label' => isset($body['device_label']) ? $body['device_label'] : '', 'deliver' => !isset($body['deliver']) || $body['deliver']));
            $m = PulseKcMobileKey::get($idM);
            return array('id' => $idM, 'token' => $m['token'], 'valid_from' => $m['valid_from'], 'valid_to' => $m['valid_to'], 'delivered_via' => $m['delivered_via'],
                'url' => Context::getContext()->link->getModuleLink('pulsekeycard', 'api', array('resource' => 'mobile_key', 'token' => $m['token'])));
        }
        $this->requireScope('portal');
        return PulseKcMobileKey::fetch($this->needsToken($body), isset($body['device_id']) ? $body['device_id'] : Tools::getValue('device_id'),
            isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', Tools::getRemoteAddr());
    }

    protected function mobileKeyRefresh($id, $body)
    {
        $this->requireScope('portal');
        return PulseKcMobileKey::refresh($this->needsToken($body), isset($body['device_id']) ? $body['device_id'] : Tools::getValue('device_id'),
            isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', Tools::getRemoteAddr());
    }

    /** Desk dashboards and the local encoder agent: which encoders are alive and what can they do. */
    protected function encoderStatus($id, $body)
    {
        $this->requireScope('desk');
        if ($id) { return PulseKcEncoder::test($id); }
        return array('encoders' => PulseKcEncoder::statusAll(), 'counters' => PulseKcService::dashboard());
    }

    /** Security console: door events, optionally scoped to a room, plus a fresh pull from one door. */
    protected function audit($id, $body)
    {
        $this->requireScope('security');
        if (!empty($body['pull']) && $id) { return array('pulled' => PulseKcAudit::pull($id)); }
        if (!empty($body['id_room'])) { return array('rows' => PulseKcAudit::forRoom((int) $body['id_room'], isset($body['from']) ? $body['from'] : null, isset($body['to']) ? $body['to'] : null)); }
        return array('rows' => PulseKcAudit::search(array('id_door' => $id ?: null, 'event' => isset($body['event']) ? $body['event'] : '', 'result' => isset($body['result']) ? $body['result'] : '',
            'from' => isset($body['from']) ? $body['from'] : '', 'to' => isset($body['to']) ? $body['to'] : ''), isset($body['limit']) ? (int) $body['limit'] : 200),
            'battery' => PulseKcAudit::batteryReport(), 'denied_24h' => PulseKcAudit::deniedSummary(24));
    }

    /** Key row as the API exposes it — metadata only, never payload_enc. */
    protected function keyView($idKey)
    {
        $k = PulseKcKey::get($idKey);
        if (!$k) { throw new PrestaShopException('Key not found', 404); }
        return array('id' => (int) $k['id_pulse_kc_key'], 'key_no' => $k['key_no'], 'type' => $k['type'], 'status' => $k['status'],
            'rooms' => array_values(array_filter(explode(',', (string) $k['room_nums']))), 'card_serial' => $k['card_serial'], 'sequence' => (int) $k['sequence'],
            'valid_from' => $k['valid_from'], 'valid_to' => $k['valid_to'], 'encoder' => $k['encoder_name'], 'adapter' => $k['adapter'],
            'mobile' => (int) $k['mobile'], 'mechanical' => (int) $k['mechanical'], 'last_error' => $k['last_error']);
    }
}
