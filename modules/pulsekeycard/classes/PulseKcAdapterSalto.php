<?php
/**
 * Salto ProAccess SPACE. Server-side web service (so it works from the PMS host, not only the desk PC):
 * card encoding at a networked encoder, JustIN BLE mobile keys, SVN update points and the blacklist.
 * Auth is HTTP Basic against a ProAccess SPACE operator; every call carries the installation id.
 */
class PulseKcAdapterSalto extends PulseKcAdapterBase implements PulseKcAdapterInterface
{
    protected $vendor = 'salto';
    protected $defaultPort = 8100;

    protected function headers()
    {
        $h = array('Content-Type: application/json', 'Accept: application/json');
        $user = $this->cred('user'); $pass = $this->cred('password');
        if ($user !== '') { $h[] = 'Authorization: Basic '.base64_encode($user.':'.$pass); }
        if ($this->cred('api_key') !== '') { $h[] = 'X-Api-Key: '.$this->cred('api_key'); }
        $h[] = 'X-Installation: '.$this->opt('installation', $this->cred('installation', 'DEFAULT'));
        return $h;
    }

    protected function call($method, $path, array $body = null)
    {
        $r = $this->http($method, $path, $body === null ? null : json_encode($body), $this->headers());
        if (isset($r['success']) && !$r['success']) { $this->fail(isset($r['message']) ? $r['message'] : 'ProAccess SPACE rejected the request', PulseKcEncoderException::REJECTED); }
        if (isset($r['error']) && $r['error']) { $this->fail(is_string($r['error']) ? $r['error'] : json_encode($r['error']), PulseKcEncoderException::REJECTED); }
        return $r;
    }

    /** Salto thinks in "zones": guest rooms plus the common doors the card is entitled to. */
    protected function zones(array $key)
    {
        $z = array();
        foreach ((array) (isset($key['room_nums']) ? $key['room_nums'] : array()) as $r) { $z[] = array('type' => 'room', 'name' => (string) $r); }
        foreach ((array) (isset($key['common_doors']) ? $key['common_doors'] : array()) as $d) { $z[] = array('type' => 'zone', 'name' => (string) $d); }
        if (!empty($key['all_rooms'])) { $z[] = array('type' => 'zone', 'name' => $this->opt('all_rooms_zone', 'ALL_GUEST_ROOMS')); }
        return $z;
    }

    public function encode(array $key)
    {
        $mobile = !empty($key['mobile']);
        $body = array(
            'operation' => $mobile ? 'issueMobileKey' : 'encodeCard',
            'encoder' => $this->cfg['encoder_ref'], 'installation' => $this->opt('installation', 'DEFAULT'),
            'cardholder' => array('name' => $this->shortName(isset($key['guest_name']) ? $key['guest_name'] : '', 40), 'reference' => isset($key['key_no']) ? $key['key_no'] : '',
                'email' => isset($key['email']) ? $key['email'] : '', 'phone' => isset($key['phone']) ? $key['phone'] : ''),
            'zones' => $this->zones($key),
            'validity' => array('from' => $this->fmt($key['valid_from'], 'c'), 'to' => $this->fmt($key['valid_to'], 'c')),
            'options' => array('privacyOverride' => !empty($key['override_dnd']), 'deadboltOverride' => !empty($key['override_deadbolt']),
                'oneShot' => (isset($key['type']) && $key['type'] === 'one_shot'), 'copy' => (isset($key['type']) && $key['type'] === 'duplicate'),
                'audit' => true, 'testMode' => (bool) $this->cfg['test_mode']),
        );
        $r = $this->call('POST', $mobile ? 'mobile-keys' : 'keys', $body);
        $ref = isset($r['keyId']) ? $r['keyId'] : (isset($r['id']) ? $r['id'] : '');
        if ($ref === '') { $this->fail('ProAccess SPACE returned no key id', PulseKcEncoderException::BAD_RESPONSE); }
        return array('ok' => true, 'key_ref' => (string) $ref, 'card_serial' => (string) (isset($r['cardSerialNumber']) ? $r['cardSerialNumber'] : (isset($r['sam']) ? $r['sam'] : '')),
            'sequence' => (int) (isset($r['sequence']) ? $r['sequence'] : (isset($key['sequence']) ? $key['sequence'] : 1)),
            'payload' => (string) (isset($r['credential']) ? $r['credential'] : (isset($r['keyData']) ? $r['keyData'] : '')),
            'raw' => $mobile ? 'JustIN mobile key issued' : 'card encoded at '.$this->cfg['encoder_ref']);
    }

    /**
     * Revoke: the credential is cancelled server-side and the card serial is pushed to the blacklist so
     * offline locks learn about it at the next wall reader / SVN update point.
     */
    public function cancel($keyRef)
    {
        $r = $this->call('DELETE', 'keys/'.rawurlencode($keyRef), null);
        $serial = isset($r['cardSerialNumber']) ? $r['cardSerialNumber'] : null;
        if ($serial && $this->opt('blacklist', 1)) { $this->blacklist($serial); }
        return array('ok' => true, 'raw' => 'key '.$keyRef.' revoked'.($serial ? ' and blacklisted' : ''));
    }

    /** Push a serial onto the SVN blacklist (propagated by wall readers and update points). */
    public function blacklist($cardSerial)
    {
        return $this->call('POST', 'blacklist', array('cardSerialNumber' => (string) $cardSerial, 'reason' => 'PMS revoke', 'installation' => $this->opt('installation', 'DEFAULT')));
    }

    /** Update points the guest can touch to refresh an offline card (shown on the desk when a stay is extended). */
    public function updatePoints()
    {
        $r = $this->call('GET', 'svn/update-points', null);
        return isset($r['updatePoints']) ? $r['updatePoints'] : (isset($r['items']) ? $r['items'] : array());
    }

    public function readCard($encoderId)
    {
        $r = $this->call('POST', 'encoders/'.rawurlencode($encoderId ? $encoderId : $this->cfg['encoder_ref']).'/read', array());
        $rooms = array();
        foreach ((array) (isset($r['zones']) ? $r['zones'] : array()) as $z) { if (isset($z['name'])) { $rooms[] = $z['name']; } }
        return array('ok' => true, 'card_serial' => (string) (isset($r['cardSerialNumber']) ? $r['cardSerialNumber'] : ''), 'room_nums' => $rooms,
            'valid_from' => isset($r['validity']['from']) ? date('Y-m-d H:i:s', strtotime($r['validity']['from'])) : null,
            'valid_to' => isset($r['validity']['to']) ? date('Y-m-d H:i:s', strtotime($r['validity']['to'])) : null,
            'type' => (string) (isset($r['keyType']) ? $r['keyType'] : ''), 'raw' => 'ProAccess read OK');
    }

    public function readAudit($lockId)
    {
        $r = $this->call('GET', 'locks/'.rawurlencode($lockId).'/audit-trail?limit='.(int) $this->opt('audit_limit', 200), null);
        $rows = isset($r['events']) ? $r['events'] : (isset($r['items']) ? $r['items'] : array());
        $out = array();
        foreach ((array) $rows as $e) {
            $out[] = array('opened_at' => date('Y-m-d H:i:s', strtotime(isset($e['timestamp']) ? $e['timestamp'] : (isset($e['dateTime']) ? $e['dateTime'] : 'now'))),
                'event' => $this->mapEvent(isset($e['eventType']) ? $e['eventType'] : (isset($e['operation']) ? $e['operation'] : '')),
                'result' => !empty($e['granted']) || (isset($e['result']) && Tools::strtolower($e['result']) === 'ok') ? 'granted' : 'denied',
                'card_serial' => (string) (isset($e['cardSerialNumber']) ? $e['cardSerialNumber'] : ''),
                'battery_pct' => isset($e['batteryLevel']) ? (int) $e['batteryLevel'] : null, 'raw' => isset($e['eventType']) ? $e['eventType'] : '');
        }
        return $out;
    }

    public function testEncoder($encoderId)
    {
        $r = $this->call('GET', 'encoders/'.rawurlencode($encoderId ? $encoderId : $this->cfg['encoder_ref']), null);
        return array('ok' => true, 'encoder' => (string) (isset($r['name']) ? $r['name'] : $encoderId), 'firmware' => (string) (isset($r['firmware']) ? $r['firmware'] : 'ProAccess SPACE'),
            'message' => 'ProAccess SPACE reachable'.(isset($r['status']) ? ' — '.$r['status'] : ''));
    }

    public function capabilities()
    {
        return array('vendor' => 'Salto ProAccess SPACE / JustIN BLE', 'encode' => true, 'cancel' => true, 'read_card' => true, 'audit' => true, 'battery' => true,
            'mobile' => true, 'blacklist' => true, 'common_doors' => true, 'multi_room' => true, 'one_shot' => true, 'local_only' => false, 'max_rooms' => (int) $this->opt('max_rooms', 16));
    }
}
