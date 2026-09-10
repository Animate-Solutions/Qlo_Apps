<?php
/** Encoder registry: one row per physical encoder / workstation, and the factory that turns a row into an adapter. */
class PulseKcEncoder
{
    const T = 'pulse_kc_encoder';

    public static function adapters()
    {
        return array('PulseKcAdapterSimulator' => 'Simulator (no hardware)', 'PulseKcAdapterOnity' => 'Onity HT / Advance', 'PulseKcAdapterSalto' => 'Salto ProAccess SPACE / BLE',
            'PulseKcAdapterHune' => 'Hune', 'PulseKcAdapterDormakaba' => 'Dormakaba Ambiance (Saflok / Ilco)');
    }

    public static function all($activeOnly = true)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.self::T.'`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY FIELD(location,"front_desk","back_office","housekeeping","security","engineering","mobile"), name');
    }

    public static function get($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_kc_encoder='.(int) $id); }
    public static function byName($name) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE name="'.pSQL($name).'"'); }

    /** The encoder a key should go to: explicit choice, else the clerk's saved workstation, else the first online front-desk unit. */
    public static function pick($id = null)
    {
        if ($id && ($e = self::get($id)) && $e['active']) { return $e; }
        $ctx = Context::getContext();
        if (isset($ctx->employee) && $ctx->employee->id) {
            $saved = PulseCoreService::setting('pulsekeycard', 'workstation_'.(int) $ctx->employee->id);
            if ($saved && ($e = self::get((int) $saved)) && $e['active']) { return $e; }
        }
        $e = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE active=1 AND status<>"disabled" ORDER BY FIELD(status,"online","unknown","offline"), FIELD(location,"front_desk","back_office"), id_pulse_kc_encoder');
        if (!$e) { throw new PulseKcEncoderException('No encoder is registered — add one in Key Card ▸ Encoders', PulseKcEncoderException::NOT_CONFIGURED); }
        return $e;
    }

    /** Remember which encoder this clerk is sitting at. */
    public static function setWorkstation($idEmployee, $idEncoder) { return PulseCoreService::setting('pulsekeycard', 'workstation_'.(int) $idEmployee, (int) $idEncoder); }

    /** Build the adapter for an encoder row, decrypting its credentials. */
    public static function adapter($encoder)
    {
        if (!is_array($encoder)) { $encoder = self::get($encoder); }
        if (!$encoder) { throw new PulseKcEncoderException('Encoder not found', PulseKcEncoderException::NOT_CONFIGURED); }
        $cls = $encoder['adapter'];
        if (!class_exists($cls)) { throw new PulseKcEncoderException('Unknown lock adapter '.$cls, PulseKcEncoderException::NOT_CONFIGURED, $encoder['name']); }
        $creds = array();
        if (!empty($encoder['credentials_enc'])) { $j = json_decode((string) PulseCoreService::decrypt($encoder['credentials_enc']), true); if (is_array($j)) { $creds = $j; } }
        $opts = json_decode((string) $encoder['options_json'], true);
        return new $cls(array('name' => $encoder['name'], 'protocol' => $encoder['protocol'], 'host' => $encoder['host'], 'port' => (int) $encoder['port'], 'endpoint' => $encoder['endpoint'],
            'encoder_ref' => $encoder['encoder_ref'], 'timeout' => (int) $encoder['timeout_sec'], 'test_mode' => (int) $encoder['test_mode'],
            'credentials' => $creds, 'options' => is_array($opts) ? $opts : array()));
    }

    /** Store credentials encrypted; $creds is an associative array (user, password, api_key, site_code…). */
    public static function save(array $d, $id = 0)
    {
        $row = array(
            'name' => pSQL($d['name']), 'adapter' => pSQL($d['adapter']), 'location' => pSQL(isset($d['location']) ? $d['location'] : 'front_desk'),
            'protocol' => pSQL(isset($d['protocol']) ? $d['protocol'] : 'http'), 'host' => pSQL(isset($d['host']) ? $d['host'] : ''), 'port' => (int) (isset($d['port']) ? $d['port'] : 0),
            'endpoint' => pSQL(isset($d['endpoint']) ? $d['endpoint'] : '/'), 'encoder_ref' => pSQL(isset($d['encoder_ref']) ? $d['encoder_ref'] : ''),
            'local_only' => !empty($d['local_only']) ? 1 : 0, 'test_mode' => !empty($d['test_mode']) ? 1 : 0, 'timeout_sec' => (int) (isset($d['timeout_sec']) ? $d['timeout_sec'] : 8) ?: 8,
            'active' => isset($d['active']) ? (int) $d['active'] : 1, 'date_upd' => date('Y-m-d H:i:s'),
        );
        if (isset($d['credentials']) && is_array($d['credentials'])) {
            $creds = array_filter($d['credentials'], function ($v) { return $v !== '' && $v !== null; });
            if ($creds) { $row['credentials_enc'] = pSQL(PulseCoreService::encrypt(json_encode($creds)), true); }
        }
        if (isset($d['options_json'])) { $row['options_json'] = pSQL($d['options_json'], true); }
        if ($id) { Db::getInstance()->update(self::T, $row, 'id_pulse_kc_encoder='.(int) $id); }
        else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert(self::T, $row); $id = (int) Db::getInstance()->Insert_ID(); }
        PulseCoreService::audit('pulsekeycard', 'encoder_save', array('id' => $id, 'name' => $d['name'], 'adapter' => $d['adapter']), self::T, $id);
        return $id;
    }

    /** Record the outcome of a call; a good call also refreshes last_seen so the stale-encoder cron leaves it alone. */
    public static function markSeen($id, $ok = true, $error = null)
    {
        $u = array('status' => $ok ? 'online' : 'offline', 'last_error' => pSQL(Tools::substr((string) $error, 0, 255)), 'date_upd' => date('Y-m-d H:i:s'));
        if ($ok) { $u['last_seen'] = date('Y-m-d H:i:s'); }
        return Db::getInstance()->update(self::T, $u, 'id_pulse_kc_encoder='.(int) $id);
    }

    public static function countEncoded($id) { return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET keys_encoded=keys_encoded+1 WHERE id_pulse_kc_encoder='.(int) $id); }

    /** Probe one encoder and record the outcome. Never throws — returns the status the UI shows. */
    public static function test($id)
    {
        $e = self::get($id);
        if (!$e) { return array('ok' => false, 'error' => 'Encoder not found'); }
        try {
            $res = self::adapter($e)->testEncoder($e['encoder_ref']);
            self::markSeen($id, true, null);
            return array_merge(array('ok' => true), $res);
        } catch (PulseKcEncoderException $ex) {
            self::markSeen($id, false, $ex->getMessage());
            return array('ok' => false, 'error' => $ex->userMessage(), 'code' => $ex->getCode());
        } catch (Exception $ex) {
            self::markSeen($id, false, $ex->getMessage());
            return array('ok' => false, 'error' => $ex->getMessage());
        }
    }

    /** Status of every encoder, with the capability matrix of its adapter — used by the desk and the API. */
    public static function statusAll()
    {
        $out = array();
        foreach (self::all(false) as $e) {
            $caps = array();
            try { $caps = self::adapter($e)->capabilities(); } catch (Exception $ex) { $caps = array('vendor' => $e['adapter'], 'error' => $ex->getMessage()); }
            $stale = (int) (Configuration::get('PULSE_KC_ENCODER_STALE_HRS') ?: 6);
            $e['stale'] = $e['last_seen'] && (strtotime($e['last_seen']) < time() - $stale * 3600) ? 1 : 0;
            $e['capabilities'] = $caps;
            unset($e['credentials_enc']);
            $out[] = $e;
        }
        return $out;
    }
}
