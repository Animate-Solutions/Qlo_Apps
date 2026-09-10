<?php
/**
 * Onity HT24W / Advance. The encoder is driven by the Onity front-desk service running on the desk PC;
 * Pulse talks to it over the documented XML frame — either wrapped in HTTP POST (Advance / OnPortal service)
 * or straight down a TCP socket (classic HT24W serial-to-TCP bridge), chosen by the encoder's `protocol`.
 * Key types map to Onity's standard / new / duplicate / one-shot; common doors ride on the same card.
 */
class PulseKcAdapterOnity extends PulseKcAdapterBase implements PulseKcAdapterInterface
{
    protected $vendor = 'onity';
    protected $defaultPort = 8080;

    protected static $types = array('guest' => 'STANDARD', 'duplicate' => 'DUPLICATE', 'one_shot' => 'ONESHOT', 'staff' => 'STAFF', 'master' => 'MASTER', 'common' => 'COMMON', 'emergency' => 'EMERGENCY');

    /** Onity frames are <PMSMessage><Command>..</Command>…</PMSMessage>; site code and operator identify the property. */
    protected function frame($command, array $fields)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><PMSMessage><Command>'.$command.'</Command>'
            .'<SiteCode>'.htmlspecialchars($this->cred('site_code', $this->opt('site_code', '')), ENT_QUOTES, 'UTF-8').'</SiteCode>'
            .'<Operator>'.htmlspecialchars($this->cred('user', 'PULSE'), ENT_QUOTES, 'UTF-8').'</Operator>'
            .'<Password>'.htmlspecialchars($this->cred('password', ''), ENT_QUOTES, 'UTF-8').'</Password>'
            .'<Encoder>'.htmlspecialchars($this->cfg['encoder_ref'], ENT_QUOTES, 'UTF-8').'</Encoder>';
        foreach ($fields as $k => $v) {
            if (is_array($v)) { $xml .= '<'.$k.'>'; foreach ($v as $item) { $xml .= '<Item>'.htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8').'</Item>'; } $xml .= '</'.$k.'>'; }
            else { $xml .= '<'.$k.'>'.htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8').'</'.$k.'>'; }
        }
        return $xml.'</PMSMessage>';
    }

    /** Send a frame over HTTP or TCP and hand back a SimpleXMLElement. */
    protected function send($command, array $fields)
    {
        $frame = $this->frame($command, $fields);
        if ($this->cfg['protocol'] === 'tcp') { $raw = $this->tcp($frame, "\x03"); }
        else { $r = $this->http('POST', '', $frame, array('Content-Type: text/xml; charset=utf-8', 'Content-Length: '.strlen($frame)), false); $raw = $r['body']; }
        $prev = libxml_use_internal_errors(true);
        $x = simplexml_load_string(trim($raw));
        libxml_use_internal_errors($prev);
        if (!$x) { $this->fail('unparseable XML: '.Tools::substr((string) $raw, 0, 120), PulseKcEncoderException::BAD_RESPONSE); }
        $result = isset($x->Result) ? Tools::strtoupper((string) $x->Result) : '';
        if ($result !== 'OK' && $result !== 'SUCCESS') { $this->fail(isset($x->ErrorText) ? (string) $x->ErrorText : ('Onity returned '.($result ?: 'no result')), PulseKcEncoderException::REJECTED); }
        return $x;
    }

    public function encode(array $key)
    {
        $rooms = array_values(array_filter((array) (isset($key['room_nums']) ? $key['room_nums'] : array())));
        $type = isset($key['type']) ? $key['type'] : 'guest';
        if (count($rooms) > 1 && !$this->opt('multi_room', 1)) { $this->fail('This Onity site is not licensed for multi-room keys', PulseKcEncoderException::UNSUPPORTED); }
        $fields = array(
            'KeyType' => isset(self::$types[$type]) ? self::$types[$type] : 'STANDARD',
            'NewKey' => (!empty($key['new_key']) || $type === 'guest') && $type !== 'duplicate' ? 'Y' : 'N',
            'RoomNumber' => isset($rooms[0]) ? $rooms[0] : '',
            'AdditionalRooms' => array_slice($rooms, 1),
            'GuestName' => $this->shortName(isset($key['guest_name']) ? $key['guest_name'] : '', 20),
            'ValidFrom' => $this->fmt($key['valid_from'], 'Y-m-d H:i'), 'ValidTo' => $this->fmt($key['valid_to'], 'Y-m-d H:i'),
            'DeadboltOverride' => !empty($key['override_deadbolt']) ? 'Y' : 'N', 'PrivacyOverride' => !empty($key['override_dnd']) ? 'Y' : 'N',
            'CommonDoors' => isset($key['common_doors']) ? array_values((array) $key['common_doors']) : array(),
            'AllRooms' => !empty($key['all_rooms']) ? 'Y' : 'N',
            'Sequence' => (int) (isset($key['sequence']) ? $key['sequence'] : 1),
            'Reference' => isset($key['key_no']) ? $key['key_no'] : '',
            'TestMode' => $this->cfg['test_mode'] ? 'Y' : 'N',
        );
        $x = $this->send('MakeKey', $fields);
        return array('ok' => true, 'key_ref' => (string) (isset($x->KeyRef) ? $x->KeyRef : $fields['Reference']), 'card_serial' => (string) (isset($x->CardSerial) ? $x->CardSerial : ''),
            'sequence' => (int) (isset($x->Sequence) ? $x->Sequence : $fields['Sequence']), 'payload' => (string) (isset($x->KeyData) ? $x->KeyData : ''), 'raw' => 'Onity MakeKey OK');
    }

    public function cancel($keyRef)
    {
        $x = $this->send('CancelKey', array('KeyRef' => $keyRef, 'TestMode' => $this->cfg['test_mode'] ? 'Y' : 'N'));
        return array('ok' => true, 'raw' => (string) (isset($x->ErrorText) ? $x->ErrorText : 'Onity CancelKey OK'));
    }

    public function readCard($encoderId)
    {
        $x = $this->send('ReadCard', array('Encoder' => $encoderId ? $encoderId : $this->cfg['encoder_ref']));
        $rooms = array();
        if (isset($x->Rooms->Item)) { foreach ($x->Rooms->Item as $r) { $rooms[] = (string) $r; } }
        elseif (isset($x->RoomNumber)) { $rooms[] = (string) $x->RoomNumber; }
        return array('ok' => true, 'card_serial' => (string) (isset($x->CardSerial) ? $x->CardSerial : ''), 'room_nums' => $rooms,
            'valid_from' => isset($x->ValidFrom) ? date('Y-m-d H:i:s', strtotime((string) $x->ValidFrom)) : null,
            'valid_to' => isset($x->ValidTo) ? date('Y-m-d H:i:s', strtotime((string) $x->ValidTo)) : null,
            'type' => (string) (isset($x->KeyType) ? $x->KeyType : ''), 'raw' => 'Onity ReadCard OK');
    }

    /** HT locks are read with a portable programmer; the Advance service exposes what has been uploaded. */
    public function readAudit($lockId)
    {
        $x = $this->send('ReadAudit', array('LockId' => $lockId));
        $out = array();
        if (isset($x->Events->Event)) {
            foreach ($x->Events->Event as $e) {
                $out[] = array('opened_at' => date('Y-m-d H:i:s', strtotime((string) $e->Time)), 'event' => $this->mapEvent((string) $e->Code),
                    'result' => Tools::strtoupper((string) (isset($e->Result) ? $e->Result : 'OK')) === 'OK' ? 'granted' : 'denied',
                    'card_serial' => (string) (isset($e->CardSerial) ? $e->CardSerial : ''), 'battery_pct' => isset($e->Battery) ? (int) $e->Battery : null, 'raw' => (string) $e->Code);
            }
        }
        return $out;
    }

    public function testEncoder($encoderId)
    {
        $x = $this->send('Ping', array('Encoder' => $encoderId ? $encoderId : $this->cfg['encoder_ref']));
        return array('ok' => true, 'encoder' => (string) (isset($x->Encoder) ? $x->Encoder : $encoderId), 'firmware' => (string) (isset($x->Version) ? $x->Version : 'Onity'), 'message' => 'Onity encoder service reachable');
    }

    public function capabilities()
    {
        return array('vendor' => 'Onity HT / Advance', 'encode' => true, 'cancel' => true, 'read_card' => true, 'audit' => true, 'battery' => true,
            'mobile' => false, 'blacklist' => false, 'common_doors' => true, 'multi_room' => (bool) $this->opt('multi_room', 1), 'one_shot' => true,
            'local_only' => true, 'max_rooms' => (int) $this->opt('max_rooms', 4));
    }
}
