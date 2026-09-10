<?php
/**
 * Software encoder. Generates and validates a signed key payload locally so the whole module —
 * issue, duplicate, cancel, read-back, mobile keys, lock audit — demos and tests with no hardware.
 * This is the default adapter and the one the seed data uses.
 * Payload: base64url(json).base64url(hmac_sha256(json, secret)) — the same envelope the mobile credential uses.
 */
class PulseKcAdapterSimulator extends PulseKcAdapterBase implements PulseKcAdapterInterface
{
    protected $vendor = 'simulator';

    protected function secret()
    {
        $s = Configuration::get('PULSE_KC_SECRET');
        if (!$s) { $s = Tools::passwdGen(48); Configuration::updateValue('PULSE_KC_SECRET', $s); }
        return $s;
    }

    protected static function b64($raw) { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
    protected static function unb64($s) { $p = strlen($s) % 4; return base64_decode(strtr($s.str_repeat('=', $p ? 4 - $p : 0), '-_', '+/')); }

    /** Sign an array into the transport envelope. */
    public function sign(array $claims)
    {
        $json = json_encode($claims);
        return self::b64($json).'.'.self::b64(hash_hmac('sha256', $json, $this->secret(), true));
    }

    /** Verify an envelope and return its claims, or false. Used by readCard() and by the mobile-key API. */
    public function verify($payload)
    {
        $parts = explode('.', (string) $payload);
        if (count($parts) !== 2) { return false; }
        $json = self::unb64($parts[0]);
        $good = self::b64(hash_hmac('sha256', $json, $this->secret(), true));
        if (!hash_equals($good, $parts[1])) { return false; }
        $claims = json_decode($json, true);
        return is_array($claims) ? $claims : false;
    }

    public function encode(array $key)
    {
        $rooms = isset($key['room_nums']) ? array_values(array_filter((array) $key['room_nums'])) : array();
        if (!$rooms && empty($key['all_rooms']) && empty($key['common_doors'])) { $this->fail('Nothing to encode: no room and no common door', PulseKcEncoderException::REJECTED); }
        $seq = (int) (isset($key['sequence']) ? $key['sequence'] : 1);
        // substr(), not Tools::substr(): this slices raw binary, and mb_substr would mangle it
        $serial = strtoupper(bin2hex(substr(hash('sha256', implode(',', $rooms).'|'.$seq.'|'.microtime(true).'|'.Tools::passwdGen(8), true), 0, 4)));
        $claims = array(
            'v' => 1, 'iss' => 'pulse-sim', 'enc' => $this->cfg['encoder_ref'] ? $this->cfg['encoder_ref'] : 'SIM', 'kn' => isset($key['key_no']) ? $key['key_no'] : '',
            'typ' => isset($key['type']) ? $key['type'] : 'guest', 'rooms' => $rooms, 'all_rooms' => !empty($key['all_rooms']) ? 1 : 0,
            'doors' => isset($key['common_doors']) ? array_values((array) $key['common_doors']) : array(),
            'gn' => $this->shortName(isset($key['guest_name']) ? $key['guest_name'] : ''),
            'nbf' => strtotime($key['valid_from']), 'exp' => strtotime($key['valid_to']),
            'db' => !empty($key['override_deadbolt']) ? 1 : 0, 'dnd' => !empty($key['override_dnd']) ? 1 : 0,
            'seq' => $seq, 'sn' => $serial, 'one' => (isset($key['type']) && $key['type'] === 'one_shot') ? 1 : 0, 'iat' => time(),
        );
        $payload = $this->sign($claims);
        return array('ok' => true, 'key_ref' => 'SIM-'.$serial.'-'.$seq, 'card_serial' => $serial, 'sequence' => $seq, 'payload' => $payload, 'raw' => 'simulated encode of '.count($rooms).' room(s)');
    }

    /** Cancelling in the simulator means the sequence for those rooms moves on, so an older card no longer validates. */
    public function cancel($keyRef)
    {
        if (!$keyRef) { $this->fail('No key reference to cancel', PulseKcEncoderException::REJECTED); }
        return array('ok' => true, 'raw' => 'simulated cancel of '.$keyRef);
    }

    /** Reads back the last payload staged on this encoder (the Key Desk stages it when the clerk swipes "read card"). */
    public function readCard($encoderId)
    {
        $staged = PulseCoreService::setting('pulsekeycard', 'sim_card_'.$encoderId);
        if (!$staged) { return array('ok' => false, 'card_serial' => null, 'room_nums' => array(), 'valid_from' => null, 'valid_to' => null, 'type' => null, 'raw' => 'no card on the encoder'); }
        $c = $this->verify($staged);
        if (!$c) { return array('ok' => false, 'card_serial' => null, 'room_nums' => array(), 'valid_from' => null, 'valid_to' => null, 'type' => null, 'raw' => 'card signature invalid — foreign or tampered card'); }
        return array('ok' => true, 'card_serial' => $c['sn'], 'room_nums' => $c['rooms'], 'valid_from' => date('Y-m-d H:i:s', $c['nbf']), 'valid_to' => date('Y-m-d H:i:s', $c['exp']), 'type' => $c['typ'], 'raw' => 'sequence '.$c['seq']);
    }

    /** Deterministic pseudo-audit so the security screen has something to show without hardware. */
    public function readAudit($lockId)
    {
        $rows = array();
        $since = strtotime('-2 day');
        $seedBase = crc32((string) $lockId);
        for ($i = 0; $i < 6; $i++) {
            $t = $since + (($seedBase + $i * 7717) % 172800);
            $granted = (($seedBase + $i) % 5) !== 0;
            $rows[] = array('opened_at' => date('Y-m-d H:i:s', $t), 'event' => $granted ? 'open' : 'denied', 'result' => $granted ? 'granted' : 'denied',
                'card_serial' => strtoupper(dechex(($seedBase + $i * 31) & 0xFFFFFFFF)), 'battery_pct' => 100 - (($seedBase + $i) % 60), 'raw' => 'sim');
        }
        return $rows;
    }

    public function testEncoder($encoderId)
    {
        return array('ok' => true, 'encoder' => $encoderId ? $encoderId : 'SIM', 'firmware' => 'pulse-simulator 1.0', 'message' => 'Simulator ready — keys are signed locally, no hardware needed.');
    }

    public function capabilities()
    {
        return array('vendor' => 'Pulse Simulator', 'encode' => true, 'cancel' => true, 'read_card' => true, 'audit' => true, 'battery' => true,
            'mobile' => true, 'blacklist' => false, 'common_doors' => true, 'multi_room' => true, 'one_shot' => true, 'local_only' => false, 'max_rooms' => 8);
    }

    /** Test helper used by the Key Desk "read card" button and by seed/tests: stage a payload on an encoder. */
    public function stage($encoderId, $payload) { return PulseCoreService::setting('pulsekeycard', 'sim_card_'.$encoderId, $payload); }
}
