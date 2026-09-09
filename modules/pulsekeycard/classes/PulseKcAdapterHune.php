<?php
/**
 * Hune (Spanish RFID locks, widely fitted in West-African properties). The Hune desk application exposes a
 * small local JSON service on the front-desk PC; field names follow the vendor's Spanish schema.
 * Cards are per-room with an optional list of common doors ("puertas comunes"); audit is pulled with a
 * portable programmer and uploaded, so readAudit() reads what the desk app has collected.
 */
class PulseKcAdapterHune extends PulseKcAdapterBase implements PulseKcAdapterInterface
{
    protected $vendor = 'hune';
    protected $defaultPort = 9010;

    protected static $types = array('guest' => 'N', 'duplicate' => 'D', 'one_shot' => 'U', 'staff' => 'P', 'master' => 'M', 'common' => 'C', 'emergency' => 'E');

    protected function call($op, array $datos)
    {
        $body = json_encode(array('operacion' => $op, 'codificador' => $this->cfg['encoder_ref'], 'usuario' => $this->cred('user', 'pulse'),
            'clave' => $this->cred('password', ''), 'hotel' => $this->opt('hotel_code', $this->cred('hotel_code', '01')), 'pruebas' => $this->cfg['test_mode'] ? 1 : 0, 'datos' => $datos));
        $r = $this->http('POST', '', $body, array('Content-Type: application/json', 'Accept: application/json'));
        $ok = isset($r['resultado']) && in_array(Tools::strtoupper($r['resultado']), array('OK', 'CORRECTO'));
        if (!$ok) { $this->fail(isset($r['mensaje']) ? $r['mensaje'] : 'Hune returned '.(isset($r['resultado']) ? $r['resultado'] : 'no result'), PulseKcEncoderException::REJECTED); }
        return $r;
    }

    public function encode(array $key)
    {
        $rooms = array_values(array_filter((array) (isset($key['room_nums']) ? $key['room_nums'] : array())));
        $type = isset($key['type']) ? $key['type'] : 'guest';
        $datos = array(
            'habitacion' => isset($rooms[0]) ? $rooms[0] : '', 'habitaciones' => $rooms,
            'nombre' => $this->shortName(isset($key['guest_name']) ? $key['guest_name'] : '', 20),
            'f_inicio' => $this->fmt($key['valid_from'], 'd/m/Y H:i'), 'f_fin' => $this->fmt($key['valid_to'], 'd/m/Y H:i'),
            'tipo_llave' => isset(self::$types[$type]) ? self::$types[$type] : 'N',
            'puertas' => array_values((array) (isset($key['common_doors']) ? $key['common_doors'] : array())),
            'todas_habitaciones' => !empty($key['all_rooms']) ? 1 : 0,
            'anular_cerrojo' => !empty($key['override_deadbolt']) ? 1 : 0, 'anular_np' => !empty($key['override_dnd']) ? 1 : 0,
            'secuencia' => (int) (isset($key['sequence']) ? $key['sequence'] : 1), 'referencia' => isset($key['key_no']) ? $key['key_no'] : '',
        );
        $r = $this->call('grabar', $datos);
        return array('ok' => true, 'key_ref' => (string) (isset($r['referencia']) ? $r['referencia'] : $datos['referencia']),
            'card_serial' => (string) (isset($r['serie']) ? $r['serie'] : ''), 'sequence' => (int) (isset($r['secuencia']) ? $r['secuencia'] : $datos['secuencia']),
            'payload' => (string) (isset($r['datos_tarjeta']) ? $r['datos_tarjeta'] : ''), 'raw' => 'Hune grabar OK');
    }

    public function cancel($keyRef)
    {
        $r = $this->call('anular', array('referencia' => $keyRef));
        return array('ok' => true, 'raw' => (string) (isset($r['mensaje']) ? $r['mensaje'] : 'Hune anular OK'));
    }

    public function readCard($encoderId)
    {
        $r = $this->call('leer', array('codificador' => $encoderId ? $encoderId : $this->cfg['encoder_ref']));
        $d = isset($r['datos']) ? $r['datos'] : $r;
        $rooms = isset($d['habitaciones']) ? (array) $d['habitaciones'] : (isset($d['habitacion']) ? array($d['habitacion']) : array());
        return array('ok' => true, 'card_serial' => (string) (isset($d['serie']) ? $d['serie'] : ''), 'room_nums' => $rooms,
            'valid_from' => isset($d['f_inicio']) ? date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $d['f_inicio']))) : null,
            'valid_to' => isset($d['f_fin']) ? date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $d['f_fin']))) : null,
            'type' => (string) (isset($d['tipo_llave']) ? $d['tipo_llave'] : ''), 'raw' => 'Hune leer OK');
    }

    public function readAudit($lockId)
    {
        $r = $this->call('auditoria', array('cerradura' => $lockId, 'limite' => (int) $this->opt('audit_limit', 200)));
        $out = array();
        foreach ((array) (isset($r['eventos']) ? $r['eventos'] : array()) as $e) {
            $when = isset($e['fecha']) ? str_replace('/', '-', $e['fecha']) : 'now';
            $out[] = array('opened_at' => date('Y-m-d H:i:s', strtotime($when)), 'event' => $this->mapEvent(isset($e['evento']) ? $e['evento'] : ''),
                'result' => isset($e['acceso']) && (int) $e['acceso'] === 1 ? 'granted' : 'denied',
                'card_serial' => (string) (isset($e['serie']) ? $e['serie'] : ''), 'battery_pct' => isset($e['bateria']) ? (int) $e['bateria'] : null,
                'raw' => (string) (isset($e['evento']) ? $e['evento'] : ''));
        }
        return $out;
    }

    public function testEncoder($encoderId)
    {
        $r = $this->call('estado', array('codificador' => $encoderId ? $encoderId : $this->cfg['encoder_ref']));
        return array('ok' => true, 'encoder' => (string) (isset($r['codificador']) ? $r['codificador'] : $encoderId), 'firmware' => (string) (isset($r['version']) ? $r['version'] : 'Hune'),
            'message' => 'Hune desk service reachable');
    }

    public function capabilities()
    {
        return array('vendor' => 'Hune', 'encode' => true, 'cancel' => true, 'read_card' => true, 'audit' => true, 'battery' => true,
            'mobile' => false, 'blacklist' => false, 'common_doors' => true, 'multi_room' => true, 'one_shot' => true, 'local_only' => true, 'max_rooms' => (int) $this->opt('max_rooms', 4));
    }
}
