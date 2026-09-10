<?php
/**
 * /pulse/api/channel/{resource}/{id} — the partner-facing contract.
 *
 * Two ways to authenticate:
 *   1. Authorization: Bearer <64-char pulse_api_token>   (scope "channel")
 *   2. X-Pulse-Channel: <channel code> + X-Pulse-Timestamp: <unix> +
 *      X-Pulse-Signature: sha256=<hmac_sha256(timestamp . "." . raw_body, secret)>
 *      where secret is the channel's stored secret, falling back to PULSE_CH_API_SECRET.
 *      Timestamps more than 5 minutes old are rejected, so a captured request cannot be replayed.
 *
 * Resources: ping, ari, reservation, ack, health. See README.md for the exact JSON shapes.
 */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulsechannel/classes/autoload.php';

class PulseChannelApiModuleFrontController extends PulseApiController
{
    const SKEW = 300;

    protected $resources = array('ping' => 'ping', 'ari' => 'ari', 'reservation' => 'reservation', 'ack' => 'ack', 'health' => 'health');
    protected $channel = null;
    protected $rawBody = '';

    /** HMAC first (that is how partners call us), Bearer token second (that is how our own tools call us). */
    protected function authenticate()
    {
        $this->rawBody = (string) Tools::file_get_contents('php://input');
        $code = isset($_SERVER['HTTP_X_PULSE_CHANNEL']) ? $_SERVER['HTTP_X_PULSE_CHANNEL'] : '';
        $sig = isset($_SERVER['HTTP_X_PULSE_SIGNATURE']) ? $_SERVER['HTTP_X_PULSE_SIGNATURE'] : '';
        if ($code === '' || $sig === '') { return parent::authenticate(); }
        $ts = isset($_SERVER['HTTP_X_PULSE_TIMESTAMP']) ? (string) $_SERVER['HTTP_X_PULSE_TIMESTAMP'] : '';
        $channel = PulseChService::channelByCode($code);
        if (!$channel || !$channel['enabled']) { throw new PrestaShopException('Unknown or disabled channel', 401); }
        if (!ctype_digit($ts) || abs(time() - (int) $ts) > self::SKEW) { throw new PrestaShopException('Stale or missing X-Pulse-Timestamp', 401); }
        $creds = PulseChService::credentials($channel);
        $secret = !empty($creds['secret']) ? $creds['secret'] : Configuration::get('PULSE_CH_API_SECRET');
        // an empty secret would make every signature verifiable by anyone — refuse rather than trust the request
        if (!is_string($secret) || $secret === '') { throw new PrestaShopException('No shared secret is configured for this channel', 401); }
        $expected = 'sha256='.hash_hmac('sha256', $ts.'.'.$this->rawBody, $secret);
        $given = trim($sig);
        $ok = function_exists('hash_equals') ? hash_equals($expected, $given) : ($expected === $given);
        if (!$ok) {
            PulseChLog::write((int) $channel['id_pulse_ch_channel'], 'in', 'api_auth', null, 401, Tools::substr($this->rawBody, 0, 2000), '', 0, 'error', 'Bad HMAC signature');
            throw new PrestaShopException('Bad signature', 401);
        }
        $this->channel = $channel;
    }

    /** Bearer callers must carry the channel scope; HMAC callers are already bound to one channel. */
    protected function requireScope($scope)
    {
        if ($this->channel) { return true; }
        return parent::requireScope($scope);
    }

    protected function channelOr($idFromBody)
    {
        if ($this->channel) { return $this->channel; }
        $c = $idFromBody ? PulseChService::channel((int) $idFromBody) : null;
        if (!$c) { throw new PrestaShopException('Specify a channel (X-Pulse-Channel header or id_channel in the body)', 400); }
        return $c;
    }

    protected function ping()
    {
        return array('module' => 'pulsechannel', 'version' => PulseChAdapterBase::version(), 'business_date' => PulseChService::businessDate(),
            'channel' => $this->channel ? $this->channel['code'] : null, 'server_time' => date('c'));
    }

    /**
     * GET-style pull of current ARI. Query/body: from, to, room_code (optional), rate_code (optional), changed_only.
     * Returns the same row shape adapters push, so an intermediary can mirror us without a translation layer.
     */
    protected function ari($id, $body)
    {
        $this->requireScope('channel');
        $c = $this->channelOr(isset($body['id_channel']) ? $body['id_channel'] : Tools::getValue('id_channel'));
        $from = Tools::getValue('from', isset($body['from']) ? $body['from'] : PulseChService::businessDate());
        $days = (int) Tools::getValue('days', isset($body['days']) ? $body['days'] : 30);
        $to = Tools::getValue('to', isset($body['to']) ? $body['to'] : date('Y-m-d', strtotime($from.' +'.max(1, $days).' day')));
        $changed = (int) Tools::getValue('changed_only', isset($body['changed_only']) ? $body['changed_only'] : 0);
        $rows = PulseChAri::rowsFor($c, (int) Tools::getValue('id_product', 0), $from, $to, (bool) $changed, 5000);
        $out = array();
        foreach ($rows as $r) {
            if (($rc = Tools::getValue('room_code')) && $r['room_code'] !== $rc) { continue; }
            unset($r['id_pulse_ch_ari'], $r['cell_hash'], $r['id_product'], $r['id_pulse_ch_rate_plan']);
            $out[] = $r;
        }
        PulseChLog::write((int) $c['id_pulse_ch_channel'], 'in', 'api_ari', $from.'..'.$to, 200, json_encode(array('from' => $from, 'to' => $to, 'changed_only' => $changed)), count($out).' rows', 0, 'ok', null);
        return array('hotel_code' => $c['hotel_code'], 'currency' => $c['currency_iso'], 'from' => $from, 'to' => $to, 'count' => count($out), 'rows' => $out);
    }

    /**
     * The inbound webhook: a partner pushes one booking (or a list) in.
     * Body: {"reservations":[ {...} ]} or a single reservation object. Each object needs at minimum
     * reference, status (new|modify|cancel), room_code, arrival, departure; see README.md.
     */
    protected function reservation($id, $body)
    {
        $this->requireScope('channel');
        $c = $this->channelOr(isset($body['id_channel']) ? $body['id_channel'] : Tools::getValue('id_channel'));
        $list = isset($body['reservations']) && is_array($body['reservations']) ? $body['reservations'] : array($body);
        $out = array();
        foreach ($list as $raw) {
            if (!is_array($raw) || !$raw) { continue; }
            try {
                $idRes = PulseChReservation::receive((int) $c['id_pulse_ch_channel'], $raw, 'webhook');
                $r = PulseChReservation::one($idRes);
                $out[] = array('reference' => $r['channel_ref'], 'accepted' => true, 'id' => (int) $idRes, 'status' => $r['status'], 'id_order' => $r['id_order'] ? (int) $r['id_order'] : null, 'error' => $r['error']);
            } catch (Exception $e) {
                $out[] = array('reference' => isset($raw['reference']) ? $raw['reference'] : null, 'accepted' => false, 'error' => $e->getMessage());
            }
        }
        if (!$out) { throw new PrestaShopException('No reservation object in the request body', 400); }
        return array('received' => count($out), 'results' => $out);
    }

    /** A partner confirms it has consumed something of ours, or asks us to mark a reservation acknowledged. */
    protected function ack($id, $body)
    {
        $this->requireScope('channel');
        $c = $this->channelOr(isset($body['id_channel']) ? $body['id_channel'] : Tools::getValue('id_channel'));
        $ref = isset($body['reference']) ? $body['reference'] : Tools::getValue('reference');
        if (!$ref) { throw new PrestaShopException('reference is required', 400); }
        $r = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_reservation` WHERE id_pulse_ch_channel='.(int) $c['id_pulse_ch_channel'].' AND channel_ref="'.pSQL($ref).'"');
        if (!$r) { throw new PrestaShopException('Unknown reference '.$ref, 404); }
        Db::getInstance()->update('pulse_ch_reservation', array('acked' => 1, 'acked_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_reservation='.(int) $r['id_pulse_ch_reservation']);
        PulseChLog::write((int) $c['id_pulse_ch_channel'], 'in', 'ack', $ref, 200, json_encode($body), 'acked', 0, 'ok', null);
        return array('reference' => $ref, 'acked' => true, 'status' => $r['status']);
    }

    /** Queue depth, last sync and error rate — what an integrator polls to know we are alive. */
    protected function health($id, $body)
    {
        $this->requireScope('channel');
        $rows = array();
        foreach (PulseChService::dashboard() as $c) {
            if ($this->channel && (int) $c['id_pulse_ch_channel'] !== (int) $this->channel['id_pulse_ch_channel']) { continue; }
            $rows[] = array('code' => $c['code'], 'name' => $c['name'], 'enabled' => (bool) $c['enabled'], 'health' => $c['health'],
                'last_success' => $c['last_success'], 'last_failure' => $c['last_failure'], 'last_error' => $c['last_error'],
                'queue_pending' => (int) $c['queue_pending'], 'queue_failed' => (int) $c['queue_failed'], 'queue_poison' => (int) $c['queue_poison'],
                'error_rate_24h' => (float) $c['error_rate'], 'unmapped_room_types' => (int) $c['unmapped'], 'failed_reservations' => (int) $c['res_failed']);
        }
        return array('business_date' => PulseChService::businessDate(), 'channels' => $rows);
    }
}
