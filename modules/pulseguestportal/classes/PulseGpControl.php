<?php
/**
 * Room-control façade: picks the configured adapter, keeps the point register per room, applies actions and
 * logs every one of them. When the adapter cannot reach the hardware the guest is told plainly and the desk
 * gets the failure — the portal never claims a light went on when it did not.
 */
class PulseGpControl
{
    public static function adapters() { return array('PulseGpControlSimulator' => 'Simulator (no hardware)', 'PulseGpControlHttp' => 'Generic HTTP / JSON room controller'); }

    /** @return PulseGpControlInterface */
    public static function adapter()
    {
        $name = PulseGpService::cfg('CONTROL_ADAPTER', 'PulseGpControlSimulator');
        if (!array_key_exists($name, self::adapters()) || !class_exists($name)) { $name = 'PulseGpControlSimulator'; }
        $key = PulseGpService::cfg('CONTROL_KEY', '');
        if ($key && class_exists('PulseCoreService')) { try { $key = PulseCoreService::decrypt($key); } catch (Exception $e) { $key = ''; } }
        return new $name(array('endpoint' => PulseGpService::cfg('CONTROL_ENDPOINT', ''), 'key' => $key, 'timeout' => (int) PulseGpService::cfg('CONTROL_TIMEOUT', 4), 'insecure' => (int) PulseGpService::cfg('CONTROL_INSECURE', 0)));
    }
    public static function enabled() { return PulseGpService::sectionOn('controls'); }

    public static function points($idRoom, $autoProvision = true)
    {
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_control_point` WHERE id_room='.(int) $idRoom.' AND active=1 ORDER BY sort, code');
        if (!$rows && $autoProvision) { self::provision($idRoom); $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_control_point` WHERE id_room='.(int) $idRoom.' AND active=1 ORDER BY sort, code'); }
        $out = array();
        foreach ($rows as $r) {
            $out[] = array('code' => $r['code'], 'type' => $r['type'], 'label' => $r['label'], 'state' => $r['state'], 'value' => (float) $r['value'],
                'min' => (float) $r['min_value'], 'max' => (float) $r['max_value'], 'online' => (int) $r['online']);
        }
        return $out;
    }
    public static function point($idRoom, $code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_control_point` WHERE id_room='.(int) $idRoom.' AND code="'.pSQL($code).'" AND active=1'); }

    /** Ask the adapter what the room exposes and write the register. Safe to re-run — it upserts. */
    public static function provision($idRoom)
    {
        $n = 0;
        foreach (self::adapter()->discover((int) $idRoom) as $p) {
            Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_gp_control_point` (id_room,code,type,label,endpoint,state,value,min_value,max_value,sort,date_upd)
                VALUES ('.(int) $idRoom.',"'.pSQL($p['code']).'","'.pSQL($p['type']).'","'.pSQL($p['label']).'","'.pSQL(isset($p['endpoint']) ? $p['endpoint'] : '').'","'.pSQL($p['state']).'",'.(float) $p['value'].','.(float) $p['min_value'].','.(float) $p['max_value'].','.(int) $p['sort'].',NOW())
                ON DUPLICATE KEY UPDATE type=VALUES(type), label=VALUES(label), endpoint=VALUES(endpoint), min_value=VALUES(min_value), max_value=VALUES(max_value), sort=VALUES(sort), date_upd=NOW()');
            $n++;
        }
        return $n;
    }
    public static function provisionAll()
    {
        $n = 0;
        foreach (Db::getInstance()->executeS('SELECT id FROM `'._DB_PREFIX_.'htl_room_information`') as $r) { $n += self::provision((int) $r['id']); }
        return $n;
    }

    /** Apply one action. Returns the point as the guest should now see it. */
    public static function apply($idRoom, $code, $action, $value = null, $source = 'portal')
    {
        if (!self::enabled()) { throw new PrestaShopException('Room controls are switched off', 403); }
        $p = self::point($idRoom, $code);
        if (!$p) { throw new PrestaShopException('Unknown control', 404); }
        if (!in_array($action, array('on', 'off', 'toggle', 'open', 'close', 'set', 'scene'))) { throw new PrestaShopException('Unsupported action', 400); }
        $adapter = self::adapter();
        $r = $adapter->apply((int) $idRoom, $p, $action, $value);
        $ok = !empty($r['ok']);
        // state and value both come off the wire (or off the guest's screen) — both columns are short
        Db::getInstance()->update('pulse_gp_control_point', array('state' => pSQL(Tools::substr((string) ($ok ? $r['state'] : $p['state']), 0, 32)), 'value' => $ok ? (float) $r['value'] : (float) $p['value'],
            'online' => $ok ? 1 : 0, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_control_point='.(int) $p['id_pulse_gp_control_point']);
        Db::getInstance()->insert('pulse_gp_control_log', array('id_room' => (int) $idRoom, 'code' => pSQL($code), 'action' => pSQL($action), 'value' => pSQL(Tools::substr((string) $value, 0, 32)),
            'adapter' => pSQL(PulseGpService::cfg('CONTROL_ADAPTER', 'PulseGpControlSimulator')), 'result' => $ok ? 'ok' : 'failed',
            'message' => pSQL(Tools::substr(isset($r['message']) ? $r['message'] : '', 0, 255)), 'source' => pSQL($source), 'date_add' => date('Y-m-d H:i:s')));
        if (!$ok) { throw new PrestaShopException('Could not reach the room controller — please call the front desk ('.(isset($r['message']) ? $r['message'] : 'no answer').')', 503); }
        return array('code' => $code, 'type' => $p['type'], 'label' => $p['label'], 'state' => $r['state'], 'value' => (float) $r['value'], 'min' => (float) $p['min_value'], 'max' => (float) $p['max_value'], 'online' => 1);
    }

    public static function log($idRoom = null, $limit = 50) { return Db::getInstance()->executeS('SELECT l.*, r.room_num FROM `'._DB_PREFIX_.'pulse_gp_control_log` l LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=l.id_room'.($idRoom ? ' WHERE l.id_room='.(int) $idRoom : '').' ORDER BY l.id_pulse_gp_control_log DESC LIMIT '.(int) $limit); }
    public static function test() { $r = self::adapter()->test(); return $r; }
}
