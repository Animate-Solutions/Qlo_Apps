<?php
/**
 * Static service facade for the Pulse suite.
 * Other modules call PulseCoreService::event(), ::audit(), ::setting().
 */
class PulseCoreService
{
    /** Raise a suite-wide event. Every Pulse module hooked to the event name receives $params. */
    public static function event($name, array $params = array())
    {
        $params['_event'] = $name;
        $params['_time'] = time();
        Hook::exec($name, $params);             // module-specific hook (e.g. actionPulseCheckIn)
        Hook::exec('actionPulseEvent', $params); // generic firehose
    }

    /** Append an audit row. $payload is JSON-encoded. */
    public static function audit($module, $event, $payload = null, $entity = null, $idEntity = null)
    {
        $ctx = Context::getContext();
        return Db::getInstance()->insert('pulse_audit', array(
            'module'      => pSQL($module),
            'event'       => pSQL($event),
            'id_employee' => isset($ctx->employee) ? (int) $ctx->employee->id : null,
            'entity'      => pSQL($entity),
            'id_entity'   => (int) $idEntity,
            'payload'     => pSQL(is_string($payload) ? $payload : json_encode($payload), true),
            'date_add'    => date('Y-m-d H:i:s'),
        ));
    }

    /** Get/set a per-module setting stored in pulse_setting. */
    public static function setting($module, $name, $value = null)
    {
        if ($value === null) {
            return Db::getInstance()->getValue('SELECT `value` FROM `'._DB_PREFIX_.'pulse_setting` WHERE `module`="'.pSQL($module).'" AND `name`="'.pSQL($name).'"');
        }
        return Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_setting` (`module`,`name`,`value`,`date_upd`) VALUES ("'.pSQL($module).'","'.pSQL($name).'","'.pSQL($value, true).'",NOW()) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `date_upd`=NOW()');
    }

    /** Current hotel business date (rolled by night audit); falls back to today. */
    public static function businessDate()
    {
        $d = self::setting('pulsefrontdesk', 'business_date');
        return $d ? $d : date('Y-m-d');
    }

    /** Symmetric encryption for gateway/lock credentials using the shop cookie key. */
    public static function encrypt($plain)
    {
        $c = new PhpEncryption(_NEW_COOKIE_KEY_);
        return $c->encrypt($plain);
    }

    public static function decrypt($cipher)
    {
        $c = new PhpEncryption(_NEW_COOKIE_KEY_);
        return $c->decrypt($cipher);
    }
}
