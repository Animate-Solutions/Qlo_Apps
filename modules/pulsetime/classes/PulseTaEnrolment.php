<?php
/**
 * The map between a person and the id each reader knows them by.
 *
 * Device user ids are not portable: a ZK terminal wants a numeric PIN under 65536, Hikvision wants an
 * employeeNo string, BioStar wants a user_id, and the same person is often a different number on the kitchen
 * door and the staff entrance. So the mapping is per (staff, device) rather than a single global number.
 *
 * Provisioning one person to the whole fleet is one action; a device that refuses or is unreachable goes on
 * the retry queue and turns amber on the Enrolment screen instead of silently not being enrolled.
 */
class PulseTaEnrolment
{
    const T = 'pulse_ta_enrolment';

    /** Device reference -> our staff id. The hot path on every single punch, so it is cached per request. */
    public static function resolve($idDevice, $ref)
    {
        static $cache = array();
        $k = (int) $idDevice.'|'.$ref;
        if (!isset($cache[$k])) {
            $id = (int) Db::getInstance()->getValue('SELECT id_pulse_ta_staff FROM `'._DB_PREFIX_.self::T.'`
                WHERE id_pulse_ta_device='.(int) $idDevice.' AND device_user_id="'.pSQL($ref).'" AND status<>"removed" AND id_pulse_ta_staff IS NOT NULL');
            if (!$id && (int) PulseTaService::cfg('REF_STAFFNO_FALLBACK', 1)) {
                // Fall back to the staff number: many properties enrol the reader with the payroll number,
                // and a device swapped for a spare then keeps working without re-mapping every finger.
                //
                // But a device user id that merely LOOKS like somebody's payroll number is how a punch ends up
                // on the wrong payslip, so the guess is only taken when nothing contradicts it:
                //   * no other reader already maps this same reference to a person — if one does, the number
                //     belongs to that device's own numbering scheme, not to a payroll number;
                //   * this device does not already know that person by a different id.
                // Anything ambiguous stays unmatched, which raises an `unmatched_ref` exception and is fixed
                // once, by hand, on the Enrolment screen.
                $claimed = (int) Db::getInstance()->getValue('SELECT COUNT(DISTINCT id_pulse_ta_staff) FROM `'._DB_PREFIX_.self::T.'`
                    WHERE device_user_id="'.pSQL($ref).'" AND status<>"removed" AND id_pulse_ta_staff IS NOT NULL');
                if (!$claimed) {
                    $cand = (int) Db::getInstance()->getValue('SELECT id_pulse_ta_staff FROM `'._DB_PREFIX_.'pulse_ta_staff` WHERE staff_no="'.pSQL($ref).'" AND status<>"exited"');
                    if ($cand && !Db::getInstance()->getValue('SELECT id_pulse_ta_enrolment FROM `'._DB_PREFIX_.self::T.'`
                        WHERE id_pulse_ta_device='.(int) $idDevice.' AND id_pulse_ta_staff='.$cand.' AND device_user_id<>"'.pSQL($ref).'" AND status<>"removed"')) { $id = $cand; }
                }
            }
            $cache[$k] = $id ? $id : 0;
        }
        return $cache[$k] ? $cache[$k] : null;
    }

    public static function get($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_ta_enrolment='.(int) $id); }
    public static function find($idStaff, $idDevice) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_ta_staff='.(int) $idStaff.' AND id_pulse_ta_device='.(int) $idDevice); }
    public static function findRef($idDevice, $ref) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_ta_device='.(int) $idDevice.' AND device_user_id="'.pSQL($ref).'"'); }

    public static function forStaff($idStaff)
    {
        return Db::getInstance()->executeS('SELECT e.*, d.name device_name_full, d.adapter, d.mode, d.health, d.status device_status
            FROM `'._DB_PREFIX_.self::T.'` e INNER JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=e.id_pulse_ta_device
            WHERE e.id_pulse_ta_staff='.(int) $idStaff.' ORDER BY d.name');
    }

    public static function forDevice($idDevice, $limit = 500)
    {
        return Db::getInstance()->executeS('SELECT e.*, s.staff_no, s.firstname, s.lastname, s.department FROM `'._DB_PREFIX_.self::T.'` e
            LEFT JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=e.id_pulse_ta_staff
            WHERE e.id_pulse_ta_device='.(int) $idDevice.' ORDER BY e.status="failed" DESC, s.lastname, e.device_user_id LIMIT '.(int) $limit);
    }

    /**
     * The device user id to use for a staff member on a device. An explicit choice wins; otherwise the staff
     * number when it is numeric (ZK-family readers only accept numbers), else a stable derived number.
     */
    public static function deviceUserId($staff, $dev, $explicit = '')
    {
        $explicit = trim((string) $explicit);
        if ($explicit !== '') { return $explicit; }
        $existing = self::find((int) $staff['id_pulse_ta_staff'], (int) $dev['id_pulse_ta_device']);
        if ($existing && $existing['device_user_id'] !== '') { return $existing['device_user_id']; }
        $numericOnly = in_array($dev['adapter'], array('PulseTaZkTcp', 'PulseTaZkPush', 'PulseTaFingertec'), true);
        $no = preg_replace('/[^0-9]/', '', (string) $staff['staff_no']);
        if (!$numericOnly && trim((string) $staff['staff_no']) !== '') { return (string) $staff['staff_no']; }
        if ($no !== '' && (int) $no > 0 && (int) $no < 65536) { return (string) (int) $no; }
        // Stable, collision-checked fallback inside the 1..65535 window ZK firmware allows.
        $seed = (int) $staff['id_pulse_ta_staff'];
        for ($i = 0; $i < 200; $i++) {
            $cand = (($seed + $i * 7) % 65000) + 100;
            if (!Db::getInstance()->getValue('SELECT id_pulse_ta_enrolment FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_ta_device='.(int) $dev['id_pulse_ta_device'].' AND device_user_id="'.(int) $cand.'"')) { return (string) $cand; }
        }
        throw new PrestaShopException('No free device user id left on '.$dev['name']);
    }

    /** Create or update the mapping row without touching the device. */
    public static function saveMapping($idStaff, $idDevice, $ref, array $d = array())
    {
        $row = array('id_pulse_ta_staff' => $idStaff ? (int) $idStaff : null, 'id_pulse_ta_device' => (int) $idDevice,
            'device_user_id' => pSQL(Tools::substr((string) $ref, 0, 32)),
            'device_name' => pSQL(Tools::substr((string) (isset($d['device_name']) ? $d['device_name'] : ''), 0, 64)),
            'card_no' => pSQL(Tools::substr((string) (isset($d['card_no']) ? $d['card_no'] : ''), 0, 32)),
            'privilege' => (int) (isset($d['privilege']) ? $d['privilege'] : 0),
            'date_upd' => date('Y-m-d H:i:s'));
        foreach (array('has_finger', 'has_face', 'has_palm', 'has_card', 'has_password') as $k) { if (isset($d[$k])) { $row[$k] = (int) $d[$k] ? 1 : 0; } }
        if (isset($d['status'])) { $row['status'] = pSQL($d['status']); }
        $ex = self::findRef($idDevice, $ref);
        if (!$ex && $idStaff) { $ex = self::find($idStaff, $idDevice); }
        if ($ex) { Db::getInstance()->update(self::T, PulseTaService::nulls($row), 'id_pulse_ta_enrolment='.(int) $ex['id_pulse_ta_enrolment']); return (int) $ex['id_pulse_ta_enrolment']; }
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert(self::T, PulseTaService::nulls($row), false, true, Db::INSERT_IGNORE);
        return (int) Db::getInstance()->Insert_ID();
    }

    /**
     * A device told us about a user id (ADMS OPERLOG, or a pullUsers sweep). Record it so the Enrolment screen
     * can offer it for mapping. Never invents a staff link.
     */
    public static function discover($idDevice, $ref, $name = '', $card = '', $privilege = 0)
    {
        $ref = trim((string) $ref);
        if ($ref === '') { return 0; }
        $ex = self::findRef($idDevice, $ref);
        if ($ex) {
            Db::getInstance()->update(self::T, array('device_name' => pSQL(Tools::substr((string) $name, 0, 64)), 'card_no' => pSQL(Tools::substr((string) $card, 0, 32)),
                'privilege' => (int) $privilege, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_enrolment='.(int) $ex['id_pulse_ta_enrolment']);
            return 0;
        }
        $idStaff = self::resolve($idDevice, $ref);
        return self::saveMapping($idStaff, $idDevice, $ref, array('device_name' => $name, 'card_no' => $card, 'privilege' => $privilege, 'status' => $idStaff ? 'pushed' : 'queued')) ? 1 : 0;
    }

    /**
     * Map an unmatched device reference onto a person and back-fill the punches that already arrived under it.
     * This is how "someone has been clocking as 1042 for a week" is fixed without editing a single punch.
     */
    public static function map($idDevice, $ref, $idStaff)
    {
        $s = PulseTaService::staff($idStaff);
        if (!$s) { throw new PrestaShopException('Staff member not found'); }
        $d = PulseTaDevice::get($idDevice);
        // If this reference was somebody else's and was revoked, only punches made after the revocation may be
        // re-attributed — a recycled reader PIN must not hand the leaver's attendance to the new holder.
        $prev = self::findRef($idDevice, $ref);
        $since = ($prev && $prev['status'] === 'removed' && (int) $prev['id_pulse_ta_staff'] && (int) $prev['id_pulse_ta_staff'] !== (int) $idStaff) ? $prev['date_upd'] : null;
        self::saveMapping((int) $idStaff, (int) $idDevice, $ref, array('status' => 'pushed'));
        $rows = PulseTaPunch::attachStaff((int) $idDevice, $ref, (int) $idStaff, $since);
        PulseTaExceptionQueue::closeByKey('unmatched_ref', ($d ? $d['serial'] : '').':'.$ref, 'Mapped to '.$s['staff_no'].' — '.$rows.' punch(es) re-attributed');
        PulseTaService::audit('enrolment_map', array('id_device' => (int) $idDevice, 'ref' => $ref, 'id_staff' => (int) $idStaff, 'punches' => $rows), self::T, (int) $idStaff);
        return $rows;
    }

    /**
     * Write one staff member onto one device. A push-mode device queues an ADMS command instead (the reply
     * flips the row to `pushed`); a pull-mode device is written to now and any failure is queued for retry.
     */
    public static function push($idStaff, $idDevice, $explicitRef = '')
    {
        $s = PulseTaService::staff($idStaff);
        $d = PulseTaDevice::get($idDevice);
        if (!$s || !$d) { throw new PrestaShopException('Staff member or device not found'); }
        $ref = self::deviceUserId($s, $d, $explicitRef);
        $idEnr = self::saveMapping((int) $idStaff, (int) $idDevice, $ref, array('status' => 'queued'));
        $payload = array('device_user_id' => $ref, 'name' => trim($s['firstname'].' '.$s['lastname']), 'privilege' => 0,
            'card_no' => '', 'password' => '', 'group' => '1', 'valid_from' => 'now', 'valid_to' => $s['exit_date'] ? $s['exit_date'].' 23:59:59' : '+10 year');
        $ex = self::get($idEnr);
        if ($ex && $ex['card_no'] !== '') { $payload['card_no'] = $ex['card_no']; }
        try {
            $r = PulseTaService::runAdapter($d, 'pushUser', array($payload));
            $deferred = !empty($r['deferred']);
            Db::getInstance()->update(self::T, PulseTaService::nulls(array('status' => $deferred ? 'queued' : 'pushed', 'pushed_at' => $deferred ? null : date('Y-m-d H:i:s'),
                'attempts' => 0, 'last_error' => '', 'date_upd' => date('Y-m-d H:i:s'))), 'id_pulse_ta_enrolment='.(int) $idEnr);
            PulseTaService::audit('enrolment_push', array('id_staff' => (int) $idStaff, 'device' => $d['name'], 'ref' => $ref, 'deferred' => $deferred ? 1 : 0), self::T, (int) $idEnr);
            return array('ok' => true, 'device_user_id' => $ref, 'deferred' => $deferred ? 1 : 0, 'note' => isset($r['note']) ? $r['note'] : '');
        } catch (Exception $e) {
            $msg = $e instanceof PulseTaDeviceException ? $e->userMessage() : $e->getMessage();
            Db::getInstance()->update(self::T, array('status' => 'failed', 'attempts' => (int) ($ex ? $ex['attempts'] : 0) + 1,
                'last_error' => pSQL(Tools::substr($msg, 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_enrolment='.(int) $idEnr);
            PulseTaService::queueJob('push_user', (int) $idDevice, (int) $idStaff, array('ref' => $ref), 120);
            return array('ok' => false, 'device_user_id' => $ref, 'error' => $msg, 'queued' => 1);
        }
    }

    /** One action, every active device. A device that fails is queued, not skipped. */
    public static function provisionAll($idStaff, $onlyDevice = 0)
    {
        $out = array('pushed' => 0, 'queued' => 0, 'deferred' => 0, 'errors' => array());
        foreach (PulseTaDevice::all('active') as $d) {
            if ($onlyDevice && (int) $d['id_pulse_ta_device'] !== (int) $onlyDevice) { continue; }
            $caps = array();
            try { $caps = PulseTaDevice::adapter($d)->capabilities(); } catch (Exception $e) { $caps = array(); }
            if (empty($caps['push_user'])) { continue; }
            $r = self::push($idStaff, (int) $d['id_pulse_ta_device']);
            if (!empty($r['ok'])) { if (!empty($r['deferred'])) { $out['deferred']++; } else { $out['pushed']++; } }
            else { $out['queued']++; $out['errors'][] = $d['name'].': '.$r['error']; }
        }
        return $out;
    }

    /** Remove one person from one device. The mapping row stays as `removed` so old punches still resolve. */
    public static function revoke($idStaff, $idDevice, $reason = '')
    {
        $e = self::find($idStaff, $idDevice);
        if (!$e) { return array('ok' => true, 'skipped' => 1); }
        $d = PulseTaDevice::get($idDevice);
        try {
            PulseTaService::runAdapter($d, 'deleteUser', array($e['device_user_id']));
            Db::getInstance()->update(self::T, array('status' => 'removed', 'last_error' => '', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_enrolment='.(int) $e['id_pulse_ta_enrolment']);
            PulseTaService::audit('enrolment_revoke', array('id_staff' => (int) $idStaff, 'device' => $d ? $d['name'] : $idDevice, 'reason' => $reason), self::T, (int) $e['id_pulse_ta_enrolment']);
            return array('ok' => true);
        } catch (Exception $ex) {
            $msg = $ex instanceof PulseTaDeviceException ? $ex->userMessage() : $ex->getMessage();
            Db::getInstance()->update(self::T, array('status' => 'failed', 'last_error' => pSQL(Tools::substr($msg, 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_enrolment='.(int) $e['id_pulse_ta_enrolment']);
            PulseTaService::queueJob('delete_user', (int) $idDevice, (int) $idStaff, array('reason' => $reason), 60);
            return array('ok' => false, 'error' => $msg, 'queued' => 1);
        }
    }

    /** A leaver who can still clock in is a payroll fraud waiting to happen, so this fails loudly, not quietly. */
    public static function revokeAll($idStaff, $reason = 'Revoked')
    {
        $out = array('removed' => 0, 'queued' => 0, 'errors' => array());
        foreach (self::forStaff($idStaff) as $e) {
            if ($e['status'] === 'removed') { continue; }
            $r = self::revoke($idStaff, (int) $e['id_pulse_ta_device'], $reason);
            if (!empty($r['ok'])) { $out['removed']++; } else { $out['queued']++; $out['errors'][] = $e['device_name_full'].': '.$r['error']; }
        }
        if ($out['queued']) { PulseTaService::alert('Could not remove a leaver from '.$out['queued'].' clocking device(s) — they are queued for retry. Check T&A ▸ Enrolment.'); }
        PulseTaService::audit('enrolment_revoke_all', array('id_staff' => (int) $idStaff, 'reason' => $reason, 'removed' => $out['removed'], 'queued' => $out['queued']), self::T, (int) $idStaff);
        return $out;
    }

    /** An ADMS command reported success — flip the enrolment it carried to `pushed`. */
    public static function markCommandDone($idCmd, $idDevice)
    {
        $cmd = Db::getInstance()->getValue('SELECT cmd FROM `'._DB_PREFIX_.'pulse_ta_device_cmd` WHERE id_pulse_ta_device_cmd='.(int) $idCmd);
        if (!$cmd || !preg_match('/PIN=([^\t\r\n ]+)/', (string) $cmd, $m)) { return 0; }
        $ref = $m[1];
        if (stripos((string) $cmd, 'DELETE USERINFO') !== false) {
            return Db::getInstance()->update(self::T, array('status' => 'removed', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_device='.(int) $idDevice.' AND device_user_id="'.pSQL($ref).'"');
        }
        return Db::getInstance()->update(self::T, array('status' => 'pushed', 'pushed_at' => date('Y-m-d H:i:s'), 'last_error' => '', 'date_upd' => date('Y-m-d H:i:s')),
            'id_pulse_ta_device='.(int) $idDevice.' AND device_user_id="'.pSQL($ref).'"');
    }

    /** Read the device's own user list and reconcile it against ours — who is on the reader that should not be. */
    public static function reconcileDevice($idDevice)
    {
        $d = PulseTaDevice::get($idDevice);
        if (!$d) { throw new PrestaShopException('Device not found'); }
        $users = PulseTaService::runAdapter($d, 'pullUsers');
        $users = is_array($users) ? $users : array();
        $known = array(); $discovered = 0;
        foreach (self::forDevice($idDevice) as $e) { $known[(string) $e['device_user_id']] = $e; }
        $onDevice = array();
        foreach ($users as $u) {
            $ref = (string) $u['device_user_id'];
            if ($ref === '') { continue; }
            $onDevice[$ref] = 1;
            if (!isset($known[$ref])) { self::discover($idDevice, $ref, isset($u['name']) ? $u['name'] : '', isset($u['card_no']) ? $u['card_no'] : '', isset($u['privilege']) ? $u['privilege'] : 0); $discovered++; }
            else {
                Db::getInstance()->update(self::T, array('has_finger' => !empty($u['has_finger']) ? 1 : 0, 'has_face' => !empty($u['has_face']) ? 1 : 0,
                    'has_card' => !empty($u['has_card']) ? 1 : 0, 'has_password' => !empty($u['has_password']) ? 1 : 0,
                    'device_name' => pSQL(Tools::substr((string) (isset($u['name']) ? $u['name'] : ''), 0, 64)), 'date_upd' => date('Y-m-d H:i:s')),
                    'id_pulse_ta_enrolment='.(int) $known[$ref]['id_pulse_ta_enrolment']);
            }
        }
        $missing = array();
        foreach ($known as $ref => $e) { if ($e['status'] === 'pushed' && !isset($onDevice[$ref])) { $missing[] = $ref; } }
        Db::getInstance()->update('pulse_ta_device', array('device_users' => count($users), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ta_device='.(int) $idDevice);
        PulseTaService::audit('enrolment_reconcile', array('device' => $d['name'], 'on_device' => count($users), 'discovered' => $discovered, 'missing' => count($missing)), self::T, (int) $idDevice);
        return array('on_device' => count($users), 'discovered' => $discovered, 'missing_from_device' => $missing,
            'unmapped' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_ta_device='.(int) $idDevice.' AND id_pulse_ta_staff IS NULL'));
    }

    /** Everything that needs a supervisor's attention on the Enrolment screen. */
    public static function problems()
    {
        return array(
            'failed' => Db::getInstance()->executeS('SELECT e.*, d.name device_name_full, s.staff_no, CONCAT(s.firstname," ",s.lastname) staff_name
                FROM `'._DB_PREFIX_.self::T.'` e INNER JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=e.id_pulse_ta_device
                LEFT JOIN `'._DB_PREFIX_.'pulse_ta_staff` s ON s.id_pulse_ta_staff=e.id_pulse_ta_staff WHERE e.status="failed" ORDER BY e.date_upd DESC LIMIT 100'),
            'unmapped' => Db::getInstance()->executeS('SELECT e.*, d.name device_name_full FROM `'._DB_PREFIX_.self::T.'` e
                INNER JOIN `'._DB_PREFIX_.'pulse_ta_device` d ON d.id_pulse_ta_device=e.id_pulse_ta_device WHERE e.id_pulse_ta_staff IS NULL ORDER BY e.date_upd DESC LIMIT 100'),
            'unmatched_punches' => PulseTaPunch::unmatched(30),
            'not_enrolled' => Db::getInstance()->executeS('SELECT s.* FROM `'._DB_PREFIX_.'pulse_ta_staff` s
                WHERE s.status="active" AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.self::T.'` e WHERE e.id_pulse_ta_staff=s.id_pulse_ta_staff AND e.status<>"removed")
                ORDER BY s.department, s.lastname LIMIT 200'),
        );
    }
}
