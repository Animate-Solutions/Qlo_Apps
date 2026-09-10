<?php
/**
 * ZKTeco ADMS / "push SDK" devices — the device dials us, which is the right answer for a hotel behind NAT
 * on a flaky link: no inbound port forwarding, no polling window to miss, and the reader buffers locally
 * when the internet drops and re-sends when it comes back.
 *
 * There is no outbound socket in this adapter. Punches arrive at the /iclock front controller and are
 * handled by PulseTaAdms; everything Pulse wants the device to do is queued as an ADMS command and collected
 * by the device on its next GET /iclock/getrequest. So testConnection() reports the health of the
 * conversation (has it called in, how recently, what is queued) rather than opening anything.
 *
 * Set the device's "Server / ADMS" page to this property's Pulse host, port and path — see the README for the
 * exact URL and for the rewrite rule needed when friendly URLs are off.
 */
class PulseTaZkPush extends PulseTaDeviceBase
{
    protected $vendor = 'zkteco-adms';

    protected function id() { return (int) $this->dev['id_pulse_ta_device']; }

    protected function stats()
    {
        $db = Db::getInstance();
        return array(
            'last_seen' => $db->getValue('SELECT last_seen_at FROM `'._DB_PREFIX_.'pulse_ta_device` WHERE id_pulse_ta_device='.$this->id()),
            'last_punch' => $db->getValue('SELECT last_punch_at FROM `'._DB_PREFIX_.'pulse_ta_device` WHERE id_pulse_ta_device='.$this->id()),
            'punches' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_punch` WHERE id_pulse_ta_device='.$this->id()),
            'punches_24h' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_punch` WHERE id_pulse_ta_device='.$this->id().' AND date_add>DATE_SUB(NOW(), INTERVAL 24 HOUR)'),
            'queued' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_device_cmd` WHERE id_pulse_ta_device='.$this->id().' AND status="queued"'),
            'sent' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_device_cmd` WHERE id_pulse_ta_device='.$this->id().' AND status="sent"'),
            'failed' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_device_cmd` WHERE id_pulse_ta_device='.$this->id().' AND status="failed"'),
            'requests_1h' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_device_log` WHERE id_pulse_ta_device='.$this->id().' AND date_add>DATE_SUB(NOW(), INTERVAL 1 HOUR)'),
            'users' => (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_enrolment` WHERE id_pulse_ta_device='.$this->id().' AND status<>"removed"'),
        );
    }

    public function testConnection()
    {
        if ($this->dev['serial'] === '') { $this->fail('this push device has no serial recorded — a push device is identified by its SN and cannot work without one', PulseTaDeviceException::NOT_CONFIGURED); }
        $s = $this->stats();
        $silentMin = $s['last_seen'] ? (int) round((time() - strtotime($s['last_seen'])) / 60) : null;
        $expect = max(1, (int) PulseTaAdms::cfg('PUSH_DELAY', 10));
        $ok = $silentMin !== null && $silentMin <= max(5, $expect * 3);
        if ($silentMin === null) {
            $msg = 'This device has never called in. Point its Comm ▸ Server (ADMS) settings at '.PulseTaService::pushUrl().' and check that the reader can reach this server.';
        } elseif (!$ok) {
            $msg = 'Silent for '.$silentMin.' minute(s) — it should call in about every '.$expect.'s. Check power, network and the ADMS server settings on the device.';
        } else {
            $msg = 'Healthy: last call-in '.$silentMin.' minute(s) ago, '.$s['punches_24h'].' punch(es) in 24 h, '.$s['queued'].' command(s) waiting to be collected.';
        }
        if (!$ok) { $this->fail($msg, PulseTaDeviceException::TIMED_OUT); }
        return array('ok' => true, 'firmware' => $this->dev['firmware'], 'model' => 'ZKTeco ADMS push', 'serial' => $this->dev['serial'],
            'users' => $s['users'], 'punches' => $s['punches'], 'device_time' => '', 'drift_sec' => null, 'message' => $msg);
    }

    /** The device's clock is set by a queued SET OPTIONS command, collected on its next getrequest. */
    public function syncTime()
    {
        $id = PulseTaAdms::queueSetTime($this->id(), $this->dev['timezone']);
        return array('ok' => true, 'before' => '', 'after' => $this->deviceNow(), 'timezone' => $this->dev['timezone'], 'queued_command' => $id,
            'note' => 'Queued — the device applies it the next time it asks for work (about every '.(int) PulseTaAdms::cfg('PUSH_DELAY', 10).'s).');
    }

    /**
     * Nothing to pull: a push device has already delivered everything it has. The poller still calls this so
     * every device goes through the same loop, and this is where a silent device is turned into an exception
     * a supervisor sees rather than a gap nobody notices.
     */
    public function pullPunches($since)
    {
        $s = $this->stats();
        $silentMin = $s['last_seen'] ? (int) round((time() - strtotime($s['last_seen'])) / 60) : 99999;
        $limit = max(15, (int) $this->opt('silent_alert_min', 30));
        if ($silentMin > $limit) { $this->fail('no ADMS call-in for '.($silentMin > 9999 ? 'ever' : $silentMin.' minute(s)'), PulseTaDeviceException::TIMED_OUT); }
        return array();
    }

    public function pushUser(array $employee)
    {
        $ref = trim((string) (isset($employee['device_user_id']) ? $employee['device_user_id'] : ''));
        if ($ref === '') { $this->fail('no device user id supplied', PulseTaDeviceException::REJECTED); }
        $id = PulseTaAdms::queueUser($this->id(), $employee);
        return array('ok' => true, 'device_user_id' => $ref, 'queued_command' => $id, 'deferred' => true,
            'note' => 'Queued as an ADMS command — the device applies it on its next call-in, and the enrolment turns green when it reports back.');
    }

    public function deleteUser($deviceUserId)
    {
        $id = PulseTaAdms::queueDeleteUser($this->id(), $deviceUserId);
        return array('ok' => true, 'queued_command' => $id, 'deferred' => true, 'note' => 'Queued as an ADMS command.');
    }

    /** Whatever the device has told us about itself through OPERLOG USER lines. */
    public function pullUsers()
    {
        $rows = Db::getInstance()->executeS('SELECT e.device_user_id, e.device_name, e.card_no, e.privilege, e.has_finger, e.has_face, e.has_card, e.has_password
            FROM `'._DB_PREFIX_.'pulse_ta_enrolment` e WHERE e.id_pulse_ta_device='.$this->id().' AND e.status<>"removed" ORDER BY e.device_user_id');
        $out = array();
        foreach ((array) $rows as $r) {
            $out[] = array('device_user_id' => $r['device_user_id'], 'name' => $r['device_name'], 'card_no' => $r['card_no'], 'privilege' => (int) $r['privilege'],
                'has_finger' => (int) $r['has_finger'], 'has_face' => (int) $r['has_face'], 'has_card' => (int) $r['has_card'], 'has_password' => (int) $r['has_password']);
        }
        return $out;
    }

    public function clearLog()
    {
        $id = PulseTaAdms::queueClearLog($this->id());
        return array('ok' => true, 'queued_command' => $id, 'deferred' => true, 'note' => 'CLEAR LOG queued — it runs on the device at its next call-in.');
    }

    public function deviceInfo()
    {
        $s = $this->stats();
        return array('vendor' => $this->vendor, 'name' => $this->dev['name'], 'serial' => $this->dev['serial'], 'firmware' => $this->dev['firmware'],
            'users' => $s['users'], 'punches' => $s['punches'], 'last_seen' => $s['last_seen'], 'last_punch' => $s['last_punch'],
            'queued_commands' => $s['queued'], 'failed_commands' => $s['failed'], 'requests_last_hour' => $s['requests_1h'],
            'push_url' => PulseTaService::pushUrl());
    }

    public function capabilities()
    {
        return array('vendor' => 'ZKTeco ADMS / push SDK (device dials us)', 'pull' => false, 'push_endpoint' => true, 'sync_time' => true, 'push_user' => true,
            'delete_user' => true, 'pull_users' => true, 'clear_log' => true, 'card' => true, 'face' => true, 'palm' => true, 'realtime' => true, 'work_codes' => true,
            'deferred_writes' => true, 'default_port' => 80,
            'verify_note' => 'The firmware path (/iclock/cdata, /iclock/getrequest, /iclock/devicecmd) is fixed in the device and cannot be changed — see the README for the route or web-server rewrite the property needs.');
    }
}
