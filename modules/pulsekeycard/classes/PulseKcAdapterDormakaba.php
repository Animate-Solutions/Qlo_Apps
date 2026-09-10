<?php
/**
 * Dormakaba Ambiance (Saflok / Ilco). The Ambiance server exposes a REST interface for PMS partners;
 * keys are made at a named encoder, rooms travel as a list, and options carry the Saflok flags
 * (new key invalidates earlier cards, deadbolt/latch override, do-not-disturb bypass).
 * Auth: API key header plus the property (site) id.
 */
class PulseKcAdapterDormakaba extends PulseKcAdapterBase implements PulseKcAdapterInterface
{
    protected $vendor = 'dormakaba';
    protected $defaultPort = 8443;

    protected static $types = array('guest' => 'Guest', 'duplicate' => 'Duplicate', 'one_shot' => 'OneShot', 'staff' => 'Staff', 'master' => 'Master', 'common' => 'CommonArea', 'emergency' => 'Emergency');

    protected function headers()
    {
        $h = array('Content-Type: application/json', 'Accept: application/json', 'X-Site-Id: '.$this->opt('site_id', $this->cred('site_id', '1')));
        if ($this->cred('api_key') !== '') { $h[] = 'Authorization: Bearer '.$this->cred('api_key'); }
        elseif ($this->cred('user') !== '') { $h[] = 'Authorization: Basic '.base64_encode($this->cred('user').':'.$this->cred('password')); }
        return $h;
    }

    protected function call($method, $path, array $body = null)
    {
        $r = $this->http($method, $path, $body === null ? null : json_encode($body), $this->headers());
        if (isset($r['Success']) && !$r['Success']) { $this->fail(isset($r['ErrorMessage']) ? $r['ErrorMessage'] : 'Ambiance rejected the request', PulseKcEncoderException::REJECTED); }
        if (isset($r['ErrorCode']) && (int) $r['ErrorCode'] !== 0) { $this->fail(isset($r['ErrorMessage']) ? $r['ErrorMessage'] : ('Ambiance error '.$r['ErrorCode']), PulseKcEncoderException::REJECTED); }
        return $r;
    }

    public function encode(array $key)
    {
        $rooms = array_values(array_filter((array) (isset($key['room_nums']) ? $key['room_nums'] : array())));
        $type = isset($key['type']) ? $key['type'] : 'guest';
        $body = array(
            'EncoderName' => $this->cfg['encoder_ref'], 'OperatorId' => $this->cred('user', 'PULSE'),
            'KeyType' => isset(self::$types[$type]) ? self::$types[$type] : 'Guest',
            'RoomList' => $rooms, 'AllGuestRooms' => !empty($key['all_rooms']),
            'GuestName' => $this->shortName(isset($key['guest_name']) ? $key['guest_name'] : '', 26),
            'CheckInDateTime' => $this->fmt($key['valid_from'], 'Y-m-d\TH:i:s'), 'CheckOutDateTime' => $this->fmt($key['valid_to'], 'Y-m-d\TH:i:s'),
            'CommonAreas' => array_values((array) (isset($key['common_doors']) ? $key['common_doors'] : array())),
            'KeyOptions' => array('NewKey' => $type !== 'duplicate', 'Deadbolt' => !empty($key['override_deadbolt']), 'DoNotDisturb' => !empty($key['override_dnd']),
                'SingleUse' => $type === 'one_shot', 'AuditEnabled' => true),
            'ReferenceNumber' => isset($key['key_no']) ? $key['key_no'] : '', 'TestMode' => (bool) $this->cfg['test_mode'],
        );
        $r = $this->call('POST', 'Keys', $body);
        $ref = isset($r['KeyId']) ? $r['KeyId'] : (isset($r['ReferenceNumber']) ? $r['ReferenceNumber'] : '');
        if ($ref === '') { $this->fail('Ambiance returned no KeyId', PulseKcEncoderException::BAD_RESPONSE); }
        return array('ok' => true, 'key_ref' => (string) $ref, 'card_serial' => (string) (isset($r['CardSerialNumber']) ? $r['CardSerialNumber'] : ''),
            'sequence' => (int) (isset($r['KeySequence']) ? $r['KeySequence'] : (isset($key['sequence']) ? $key['sequence'] : 1)),
            'payload' => (string) (isset($r['KeyTrack']) ? $r['KeyTrack'] : ''), 'raw' => 'Ambiance key made at '.$this->cfg['encoder_ref']);
    }

    public function cancel($keyRef)
    {
        $r = $this->call('POST', 'Keys/'.rawurlencode($keyRef).'/Cancel', array('Reason' => 'PMS cancel'));
        return array('ok' => true, 'raw' => (string) (isset($r['Message']) ? $r['Message'] : 'Ambiance key cancelled'));
    }

    public function readCard($encoderId)
    {
        $r = $this->call('POST', 'Encoders/'.rawurlencode($encoderId ? $encoderId : $this->cfg['encoder_ref']).'/ReadKey', array());
        return array('ok' => true, 'card_serial' => (string) (isset($r['CardSerialNumber']) ? $r['CardSerialNumber'] : ''),
            'room_nums' => (array) (isset($r['RoomList']) ? $r['RoomList'] : array()),
            'valid_from' => isset($r['CheckInDateTime']) ? date('Y-m-d H:i:s', strtotime($r['CheckInDateTime'])) : null,
            'valid_to' => isset($r['CheckOutDateTime']) ? date('Y-m-d H:i:s', strtotime($r['CheckOutDateTime'])) : null,
            'type' => (string) (isset($r['KeyType']) ? $r['KeyType'] : ''), 'raw' => 'Ambiance ReadKey OK');
    }

    public function readAudit($lockId)
    {
        $r = $this->call('GET', 'Locks/'.rawurlencode($lockId).'/Audit?count='.(int) $this->opt('audit_limit', 200), null);
        $out = array();
        foreach ((array) (isset($r['Events']) ? $r['Events'] : array()) as $e) {
            $out[] = array('opened_at' => date('Y-m-d H:i:s', strtotime(isset($e['EventTime']) ? $e['EventTime'] : 'now')),
                'event' => $this->mapEvent(isset($e['EventDescription']) ? $e['EventDescription'] : (isset($e['EventCode']) ? $e['EventCode'] : '')),
                'result' => !empty($e['AccessGranted']) ? 'granted' : 'denied', 'card_serial' => (string) (isset($e['CardId']) ? $e['CardId'] : ''),
                'battery_pct' => isset($e['BatteryLevel']) ? (int) $e['BatteryLevel'] : null, 'raw' => (string) (isset($e['EventCode']) ? $e['EventCode'] : ''));
        }
        return $out;
    }

    public function testEncoder($encoderId)
    {
        $r = $this->call('GET', 'Encoders/'.rawurlencode($encoderId ? $encoderId : $this->cfg['encoder_ref']), null);
        return array('ok' => true, 'encoder' => (string) (isset($r['Name']) ? $r['Name'] : $encoderId), 'firmware' => (string) (isset($r['FirmwareVersion']) ? $r['FirmwareVersion'] : 'Ambiance'),
            'message' => 'Ambiance server reachable'.(isset($r['Status']) ? ' — '.$r['Status'] : ''));
    }

    public function capabilities()
    {
        return array('vendor' => 'Dormakaba Ambiance (Saflok / Ilco)', 'encode' => true, 'cancel' => true, 'read_card' => true, 'audit' => true, 'battery' => true,
            'mobile' => (bool) $this->opt('mobile', 0), 'blacklist' => false, 'common_doors' => true, 'multi_room' => true, 'one_shot' => true,
            'local_only' => false, 'max_rooms' => (int) $this->opt('max_rooms', 8));
    }
}
