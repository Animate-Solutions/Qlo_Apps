<?php
/** Device registry: one row per reader, the adapter factory, the poller, health tracking and claiming. */
class PulseTaDevice
{
    const T = 'pulse_ta_device';

    /** Adapter class => label shown in the Devices form. */
    public static function adapters()
    {
        return array(
            'PulseTaSimulator' => 'Simulator (no hardware) — default',
            'PulseTaZkTcp' => 'ZKTeco / eSSL / Granding / Timmy / Realtime — TCP or UDP 4370',
            'PulseTaZkPush' => 'ZKTeco ADMS push (device dials us) — /iclock',
            'PulseTaFingertec' => 'FingerTec Ingress (ZK-derived) — TCP 4370',
            'PulseTaHikvision' => 'Hikvision ISAPI (DS-K1T / MinMoe)',
            'PulseTaSuprema' => 'Suprema BioStar 2 (local REST)',
            'PulseTaAnviz' => 'Anviz CrossChex / OpenAPI',
            'PulseTaMatrix' => 'Matrix COSEC (REST)',
            'PulseTaDahua' => 'Dahua HTTP CGI (ASI / VTO)',
            'PulseTaIdemia' => 'IDEMIA / Morpho SIGMA (HTTP remote messaging)',
            'PulseTaCsv' => 'CSV / delimited file import',
        );
    }

    /** Sensible defaults the Devices form pre-fills when an adapter is chosen. */
    public static function adapterDefaults($adapter)
    {
        $d = array(
            'PulseTaSimulator' => array('brand' => 'simulator', 'mode' => 'pull', 'protocol' => 'local', 'port' => 0, 'endpoint' => ''),
            'PulseTaZkTcp' => array('brand' => 'zkteco', 'mode' => 'pull', 'protocol' => 'tcp', 'port' => 4370, 'endpoint' => ''),
            'PulseTaZkPush' => array('brand' => 'zkteco', 'mode' => 'push', 'protocol' => 'http', 'port' => 80, 'endpoint' => '/iclock'),
            'PulseTaFingertec' => array('brand' => 'fingertec', 'mode' => 'pull', 'protocol' => 'tcp', 'port' => 4370, 'endpoint' => ''),
            'PulseTaHikvision' => array('brand' => 'hikvision', 'mode' => 'pull', 'protocol' => 'http', 'port' => 80, 'endpoint' => '/'),
            'PulseTaSuprema' => array('brand' => 'suprema', 'mode' => 'pull', 'protocol' => 'https', 'port' => 443, 'endpoint' => '/'),
            'PulseTaAnviz' => array('brand' => 'anviz', 'mode' => 'pull', 'protocol' => 'https', 'port' => 443, 'endpoint' => '/'),
            'PulseTaMatrix' => array('brand' => 'matrix', 'mode' => 'pull', 'protocol' => 'http', 'port' => 80, 'endpoint' => 'cosec/api/v1'),
            'PulseTaDahua' => array('brand' => 'dahua', 'mode' => 'pull', 'protocol' => 'http', 'port' => 80, 'endpoint' => '/'),
            'PulseTaIdemia' => array('brand' => 'idemia', 'mode' => 'pull', 'protocol' => 'https', 'port' => 443, 'endpoint' => 'api/v1'),
            'PulseTaCsv' => array('brand' => 'csv', 'mode' => 'file', 'protocol' => 'file', 'port' => 0, 'endpoint' => ''),
        );
        return isset($d[$adapter]) ? $d[$adapter] : array('brand' => 'generic', 'mode' => 'pull', 'protocol' => 'http', 'port' => 80, 'endpoint' => '/');
    }

    public static function all($status = null)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.self::T.'`'.($status ? ' WHERE status="'.pSQL($status).'"' : '').' ORDER BY FIELD(status,"active","pending","blocked"), name');
    }

    public static function get($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_ta_device='.(int) $id); }
    public static function byName($n) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE name="'.pSQL($n).'"'); }
    public static function bySerial($s) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE serial="'.pSQL($s).'"'); }

    /** Decrypted credentials for a device row. Never returned to a template or the API. */
    public static function credentials($dev)
    {
        if (empty($dev['credentials_enc'])) { return array(); }
        $j = json_decode((string) PulseCoreService::decrypt($dev['credentials_enc']), true);
        return is_array($j) ? $j : array();
    }

    public static function options($dev) { $j = json_decode((string) $dev['options_json'], true); return is_array($j) ? $j : array(); }

    /** Build the adapter for a device row (or id), with credentials decrypted and options decoded. */
    public static function adapter($dev)
    {
        if (!is_array($dev)) { $dev = self::get($dev); }
        if (!$dev) { throw new PulseTaDeviceException('Device not found', PulseTaDeviceException::NOT_CONFIGURED); }
        $cls = $dev['adapter'];
        if (!class_exists($cls)) { throw new PulseTaDeviceException('Unknown device adapter '.$cls, PulseTaDeviceException::NOT_CONFIGURED, $dev['name']); }
        $cfg = $dev;
        $cfg['credentials'] = self::credentials($dev);
        $cfg['options'] = self::options($dev);
        unset($cfg['credentials_enc']);
        return new $cls($cfg);
    }

    /** Save a device. Credentials are encrypted; a blank credential field keeps the stored value. */
    public static function save(array $d, $id = 0)
    {
        $adapter = isset($d['adapter']) && $d['adapter'] !== '' ? $d['adapter'] : 'PulseTaSimulator';
        if (!array_key_exists($adapter, self::adapters())) { throw new PrestaShopException('Unknown adapter '.$adapter); }
        $def = self::adapterDefaults($adapter);
        $row = array(
            'name' => pSQL(Tools::substr(trim((string) $d['name']), 0, 64)), 'adapter' => pSQL($adapter),
            'brand' => pSQL(isset($d['brand']) && $d['brand'] !== '' ? $d['brand'] : $def['brand']),
            'location' => pSQL(Tools::substr((string) (isset($d['location']) ? $d['location'] : 'Staff entrance'), 0, 64)),
            'department' => pSQL(Tools::substr((string) (isset($d['department']) ? $d['department'] : ''), 0, 32)),
            'mode' => pSQL(isset($d['mode']) && in_array($d['mode'], array('pull', 'push', 'file'), true) ? $d['mode'] : $def['mode']),
            'protocol' => pSQL(isset($d['protocol']) && in_array($d['protocol'], array('tcp', 'udp', 'http', 'https', 'local', 'file'), true) ? $d['protocol'] : $def['protocol']),
            'host' => pSQL(Tools::substr((string) (isset($d['host']) ? $d['host'] : ''), 0, 128)),
            'port' => (int) (isset($d['port']) ? $d['port'] : $def['port']),
            'endpoint' => pSQL(Tools::substr((string) (isset($d['endpoint']) ? $d['endpoint'] : $def['endpoint']), 0, 160)),
            'serial' => pSQL(PulseTaAdms::cleanSerial(isset($d['serial']) ? $d['serial'] : '')),
            'timezone' => pSQL(Tools::substr((string) (isset($d['timezone']) && $d['timezone'] !== '' ? $d['timezone'] : PulseTaService::cfg('TZ', 'Africa/Lagos')), 0, 48)),
            'direction_mode' => pSQL(isset($d['direction_mode']) && in_array($d['direction_mode'], array('in', 'out', 'auto', 'both'), true) ? $d['direction_mode'] : 'both'),
            'poll_interval_min' => max(0, (int) (isset($d['poll_interval_min']) ? $d['poll_interval_min'] : 10)),
            'timeout_sec' => max(2, (int) (isset($d['timeout_sec']) ? $d['timeout_sec'] : 8)),
            'retries' => max(0, min(5, (int) (isset($d['retries']) ? $d['retries'] : 2))),
            'clear_after_pull' => !empty($d['clear_after_pull']) ? 1 : 0,
            'test_mode' => !empty($d['test_mode']) ? 1 : 0,
            'status' => pSQL(isset($d['status']) && in_array($d['status'], array('pending', 'active', 'blocked'), true) ? $d['status'] : 'pending'),
            'note' => pSQL(Tools::substr((string) (isset($d['note']) ? $d['note'] : ''), 0, 255)),
            'date_upd' => date('Y-m-d H:i:s'),
        );
        if ($row['name'] === '') { throw new PrestaShopException('A device name is required'); }
        if ($row['mode'] === 'push' && $row['serial'] === '') { throw new PrestaShopException('A push device is identified by its serial number — enter the SN printed on the device'); }
        if (isset($d['credentials']) && is_array($d['credentials'])) {
            $keep = $id ? self::credentials(self::get($id)) : array();
            $new = array_filter($d['credentials'], function ($v) { return $v !== '' && $v !== null; });
            $creds = array_merge($keep, $new);
            if ($creds) { $row['credentials_enc'] = pSQL(PulseCoreService::encrypt(json_encode($creds)), true); }
        }
        if (isset($d['options_json'])) {
            $o = trim((string) $d['options_json']);
            if ($o !== '' && json_decode($o, true) === null) { throw new PrestaShopException('Device options must be valid JSON'); }
            $row['options_json'] = pSQL($o, true);
        }
        if ($id) { Db::getInstance()->update(self::T, $row, 'id_pulse_ta_device='.(int) $id); }
        else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert(self::T, $row); $id = (int) Db::getInstance()->Insert_ID(); }
        PulseTaService::audit('device_save', array('id' => $id, 'name' => $row['name'], 'adapter' => $adapter, 'mode' => $row['mode']), self::T, $id);
        return $id;
    }

    /** Claim a pending device: this is the moment a serial becomes trusted, so it is audited with the employee. */
    public static function claim($id, $name = '', $location = '', $department = '')
    {
        $d = self::get($id);
        if (!$d) { throw new PrestaShopException('Device not found'); }
        $u = array('status' => 'active', 'claimed_by' => PulseTaService::emp(), 'claimed_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'));
        if ($name !== '') { $u['name'] = pSQL(Tools::substr($name, 0, 64)); }
        if ($location !== '') { $u['location'] = pSQL(Tools::substr($location, 0, 64)); }
        if ($department !== '') { $u['department'] = pSQL(Tools::substr($department, 0, 32)); }
        Db::getInstance()->update(self::T, $u, 'id_pulse_ta_device='.(int) $id);
        PulseTaService::audit('device_claim', array('id' => (int) $id, 'serial' => $d['serial'], 'ip' => $d['host']), self::T, (int) $id);
        PulseCoreService::event('actionPulseTaDeviceHealth', array('id_device' => (int) $id, 'serial' => $d['serial'], 'state' => 'claimed'));
        return true;
    }

    public static function block($id, $reason = '')
    {
        $d = self::get($id);
        Db::getInstance()->update(self::T, array('status' => 'blocked', 'note' => pSQL(Tools::substr('Blocked: '.$reason, 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_device='.(int) $id);
        PulseTaService::audit('device_block', array('id' => (int) $id, 'serial' => $d ? $d['serial'] : '', 'reason' => $reason), self::T, (int) $id);
        return true;
    }

    /** Record the outcome of a call. Three consecutive failures move a device from degraded to offline. */
    public static function markSeen($id, $ok, $error = null, $punchAt = null, $punches = 0)
    {
        $d = self::get($id);
        if (!$d) { return false; }
        $u = array('last_poll_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'));
        if ($ok) {
            $u['health'] = 'online'; $u['last_seen_at'] = date('Y-m-d H:i:s'); $u['error_count'] = 0; $u['last_error'] = '';
            if ($punches > 0) { $u['punch_count'] = (int) $d['punch_count'] + (int) $punches; }
            if ($punchAt) { $u['last_punch_at'] = pSQL($punchAt); }
            // A device that has come back closes its own "stopped reporting" exceptions, so the supervisor
            // queue only ever shows problems that are still problems.
            if ($d['health'] !== 'online') { PulseTaExceptionQueue::closeByKey('device_offline', 'dev'.(int) $id, 'Device "'.$d['name'].'" is reporting again'); }
        } else {
            $n = (int) $d['error_count'] + 1;
            $u['error_count'] = $n; $u['health'] = $n >= 3 ? 'offline' : 'degraded';
            $u['last_error'] = pSQL(Tools::substr((string) $error, 0, 255));
        }
        Db::getInstance()->update(self::T, $u, 'id_pulse_ta_device='.(int) $id);
        if (!$ok && (int) $d['error_count'] + 1 === 3) {
            PulseTaService::alert('Clocking device "'.$d['name'].'" ('.$d['location'].') has failed three polls in a row — '.Tools::substr((string) $error, 0, 160));
            PulseCoreService::event('actionPulseTaDeviceHealth', array('id_device' => (int) $id, 'serial' => $d['serial'], 'state' => 'offline', 'error' => $error));
        }
        return true;
    }

    /** Probe one device. Never throws — it returns what the Devices screen shows. */
    public static function test($id)
    {
        $d = self::get($id);
        if (!$d) { return array('ok' => false, 'error' => 'Device not found'); }
        try {
            $r = PulseTaService::runAdapter($d, 'testConnection');
            $u = array();
            if (!empty($r['firmware'])) { $u['firmware'] = pSQL(Tools::substr((string) $r['firmware'], 0, 64)); }
            if (!empty($r['serial']) && $d['serial'] === '') { $u['serial'] = pSQL(PulseTaAdms::cleanSerial($r['serial'])); }
            if (isset($r['users'])) { $u['device_users'] = (int) $r['users']; }
            if ($u) { Db::getInstance()->update(self::T, $u, 'id_pulse_ta_device='.(int) $id); }
            self::markSeen($id, true);
            return array_merge(array('ok' => true), $r);
        } catch (PulseTaDeviceException $e) {
            self::markSeen($id, false, $e->getMessage());
            return array('ok' => false, 'error' => $e->userMessage(), 'code' => $e->getCode(), 'offline' => $e->isOffline() ? 1 : 0);
        } catch (Exception $e) {
            self::markSeen($id, false, $e->getMessage());
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * Pull one device and store what came back. `clear_after_pull` only fires after the punches are committed,
     * and never when the pull returned an error — the device log is the only copy until we have it.
     * @return array [ok, pulled, stored, error]
     */
    public static function poll($id, $force = false)
    {
        $d = self::get($id);
        if (!$d) { return array('ok' => false, 'pulled' => 0, 'stored' => 0, 'error' => 'Device not found'); }
        if ($d['status'] !== 'active') { return array('ok' => false, 'pulled' => 0, 'stored' => 0, 'error' => 'Device is '.$d['status']); }
        if (!$force && (int) $d['poll_interval_min'] > 0 && $d['last_poll_at'] && strtotime($d['last_poll_at']) > time() - (int) $d['poll_interval_min'] * 60) {
            return array('ok' => true, 'pulled' => 0, 'stored' => 0, 'error' => null, 'skipped' => 1);
        }
        $since = $d['last_punch_at'] ? date('Y-m-d H:i:s', strtotime($d['last_punch_at']) - (int) PulseTaService::cfg('POLL_OVERLAP_MIN', 120) * 60) : date('Y-m-d H:i:s', strtotime('-7 day'));
        try {
            $punches = PulseTaService::runAdapter($d, 'pullPunches', array($since));
            $punches = is_array($punches) ? $punches : array();
            $stored = $punches ? PulseTaPunch::ingestMany($d, $punches) : 0;
            $latest = null;
            foreach ($punches as $p) { if ($latest === null || $p['punched_at'] > $latest) { $latest = $p['punched_at']; } }
            self::markSeen($id, true, null, $latest, $stored);
            if ((int) $d['clear_after_pull'] && $punches && $stored === count($punches)) {
                try { PulseTaService::runAdapter($d, 'clearLog'); PulseTaService::audit('device_log_cleared', array('device' => $d['name'], 'punches' => $stored), self::T, (int) $id); }
                catch (Exception $e) { PulseTaService::audit('device_clear_failed', array('device' => $d['name'], 'error' => $e->getMessage()), self::T, (int) $id); }
            }
            return array('ok' => true, 'pulled' => count($punches), 'stored' => $stored, 'error' => null);
        } catch (PulseTaDeviceException $e) {
            self::markSeen($id, false, $e->getMessage());
            return array('ok' => false, 'pulled' => 0, 'stored' => 0, 'error' => $e->userMessage(), 'code' => $e->getCode());
        } catch (Exception $e) {
            self::markSeen($id, false, $e->getMessage());
            return array('ok' => false, 'pulled' => 0, 'stored' => 0, 'error' => $e->getMessage());
        }
    }

    /** Poll every active pull-mode device. Used by cron/poll.php and the Devices screen's Poll all button. */
    public static function pollAll($force = false)
    {
        $out = array('devices' => 0, 'pulled' => 0, 'stored' => 0, 'errors' => array());
        foreach (self::all('active') as $d) {
            if ($d['mode'] === 'push' && $d['adapter'] === 'PulseTaZkPush' && !$force) {
                $r = self::poll((int) $d['id_pulse_ta_device'], true); // health check only — a push device has nothing to pull
                if (!$r['ok']) { $out['errors'][] = $d['name'].': '.$r['error']; }
                $out['devices']++;
                continue;
            }
            $r = self::poll((int) $d['id_pulse_ta_device'], $force);
            $out['devices']++; $out['pulled'] += (int) $r['pulled']; $out['stored'] += (int) $r['stored'];
            if (!$r['ok'] && $r['error']) { $out['errors'][] = $d['name'].': '.$r['error']; }
        }
        return $out;
    }

    /** Devices with their adapter capability matrix, for the UI and the API. Credentials are stripped. */
    public static function statusAll()
    {
        $out = array();
        $staleMin = (int) PulseTaService::cfg('DEVICE_STALE_MIN', 60);
        foreach (self::all() as $d) {
            $caps = array();
            try { $caps = self::adapter($d)->capabilities(); } catch (Exception $e) { $caps = array('vendor' => $d['adapter'], 'error' => $e->getMessage()); }
            $d['capabilities'] = $caps;
            $d['stale'] = ($d['status'] === 'active' && (!$d['last_seen_at'] || strtotime($d['last_seen_at']) < time() - $staleMin * 60)) ? 1 : 0;
            $d['silent_min'] = $d['last_seen_at'] ? (int) round((time() - strtotime($d['last_seen_at'])) / 60) : null;
            $d['queued_cmds'] = $d['mode'] === 'push' ? (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_device_cmd` WHERE id_pulse_ta_device='.(int) $d['id_pulse_ta_device'].' AND status IN ("queued","sent")') : 0;
            $d['enrolled'] = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_enrolment` WHERE id_pulse_ta_device='.(int) $d['id_pulse_ta_device'].' AND status<>"removed"');
            $d['has_credentials'] = empty($d['credentials_enc']) ? 0 : 1;
            unset($d['credentials_enc']);
            $out[] = $d;
        }
        return $out;
    }

    /** Recent inbound push traffic, for the Devices screen. Device-supplied text is escaped again by the template. */
    public static function traffic($idDevice = null, $limit = 60)
    {
        return Db::getInstance()->executeS('SELECT l.*, d.name device_name FROM `'._DB_PREFIX_.'pulse_ta_device_log` l
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=l.id_pulse_ta_device
            WHERE 1'.($idDevice ? ' AND l.id_pulse_ta_device='.(int) $idDevice : '').' ORDER BY l.id_pulse_ta_device_log DESC LIMIT '.(int) $limit);
    }

    public static function commands($idDevice, $limit = 40)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_device_cmd` WHERE id_pulse_ta_device='.(int) $idDevice.' ORDER BY id_pulse_ta_device_cmd DESC LIMIT '.(int) $limit);
    }
}
