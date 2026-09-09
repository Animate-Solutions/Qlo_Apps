<?php
/**
 * Employee self-service: sessions, the geofenced mobile punch, personal-detail change requests and the
 * payslip gate.
 *
 * The portal runs on a phone the hotel does not own, on a network it does not control, so it is treated as
 * hostile: the staff number and PIN are the only things a client may assert, the PIN is hashed with
 * _COOKIE_KEY_ exactly as the POS does, failures are rate limited per staff number AND per address and end in
 * a timed lockout, the session is a signed short-lived token whose subject is resolved server-side on every
 * call, and no resource ever accepts an employee id from the request. Salary is served only inside a short
 * window opened by re-entering the PIN, so a colleague looking over a shoulder sees a roster, not a wage.
 */
class PulseHrEss
{
    const S = 'pulse_hr_ess_session';

    protected static function secret()
    {
        $s = Configuration::get('PULSE_HR_ESS_SECRET');
        if (!$s) { $s = Tools::passwdGen(48); Configuration::updateValue('PULSE_HR_ESS_SECRET', $s); }
        return $s;
    }
    protected static function sign($sid, $idEmployee, $exp) { return hash_hmac('sha256', $sid.'|'.(int) $idEmployee.'|'.(int) $exp, self::secret()); }

    /* ---------- login ---------- */

    protected static function log($staffNo, $ok, $reason)
    {
        Db::getInstance()->insert('pulse_hr_ess_login', array('staff_no' => pSQL(Tools::substr((string) $staffNo, 0, 32)), 'ip' => pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 45)),
            'ok' => $ok ? 1 : 0, 'reason' => pSQL($reason), 'user_agent' => pSQL(Tools::substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 250)), 'date_add' => date('Y-m-d H:i:s')), true);
    }
    protected static function recentFails($staffNo)
    {
        $mins = max(1, (int) PulseHrService::cfg('ESS_FAIL_WINDOW_MIN', 15));
        // A refusal that was itself caused by the lockout does not count towards the lockout, or anyone who
        // knows a staff number could keep a colleague locked out for as long as they cared to keep typing.
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_ess_login` WHERE ok=0 AND reason<>"throttled" AND date_add>DATE_SUB(NOW(), INTERVAL '.$mins.' MINUTE)
            AND (staff_no="'.pSQL((string) $staffNo).'" OR ip="'.pSQL((string) Tools::getRemoteAddr()).'")');
    }

    /**
     * Staff number + PIN in, signed session out. Every failure looks and costs the same to the caller — the
     * message never says whether the staff number exists.
     */
    public static function login($staffNo, $pin)
    {
        PulseHrService::rateHit('esslogin:'.md5((string) Tools::getRemoteAddr()), (int) PulseHrService::cfg('ESS_LOGIN_PER_MIN', 10));
        $staffNo = trim((string) $staffNo); $pin = trim((string) $pin);
        $bad = 'Staff number or PIN not recognised';
        if (!PulseHrService::cfg('ESS_ENABLED', 1)) { throw new PrestaShopException('The staff portal is switched off', 403); }
        if ($staffNo === '' || $pin === '') { throw new PrestaShopException($bad, 401); }
        $max = (int) PulseHrService::cfg('ESS_MAX_FAILS', 5);
        if ($max > 0 && self::recentFails($staffNo) >= $max) { self::log($staffNo, 0, 'throttled'); throw new PrestaShopException('Too many attempts. Wait '.(int) PulseHrService::cfg('ESS_FAIL_WINDOW_MIN', 15).' minutes or see HR', 429); }
        $e = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE staff_no="'.pSQL($staffNo).'"');
        if (!$e || !$e['pin_hash']) { self::log($staffNo, 0, 'unknown'); throw new PrestaShopException($bad, 401); }
        if (!hash_equals((string) $e['pin_hash'], PulseHrService::pinHash($pin))) { self::log($staffNo, 0, 'bad_pin'); throw new PrestaShopException($bad, 401); }
        if (!$e['ess_enabled']) { self::log($staffNo, 0, 'disabled'); throw new PrestaShopException('Your portal access is closed — please see HR', 403); }
        if ($e['status'] === 'exited') { self::log($staffNo, 0, 'exited'); throw new PrestaShopException('Your portal access is closed — please see HR', 403); }
        if ($e['ess_locked_until'] && strtotime($e['ess_locked_until']) > time()) { self::log($staffNo, 0, 'locked'); throw new PrestaShopException('This account is locked until '.date('H:i', strtotime($e['ess_locked_until'])), 403); }
        self::log($staffNo, 1, 'ok');
        return self::issue($e);
    }

    public static function issue(array $e)
    {
        $ttl = max(5, (int) PulseHrService::cfg('ESS_TTL_MIN', 30));
        $exp = time() + $ttl * 60;
        $sid = Tools::substr(md5(uniqid('ess', true).Tools::passwdGen(12)), 0, 32);
        $token = $sid.'.'.$exp.'.'.self::sign($sid, (int) $e['id_pulse_hr_employee'], $exp);
        Db::getInstance()->insert(self::S, array('sid' => pSQL($sid), 'id_pulse_hr_employee' => (int) $e['id_pulse_hr_employee'], 'token_hash' => pSQL(hash('sha256', $token)),
            'expires_at' => date('Y-m-d H:i:s', $exp), 'ip' => pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 45)),
            'user_agent' => pSQL(Tools::substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 250)),
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), true);
        PulseCoreService::audit('pulsehr', 'ess_login', array('staff_no' => $e['staff_no']), 'pulse_hr_employee', (int) $e['id_pulse_hr_employee']);
        return array('token' => $token, 'expires_at' => date('c', $exp), 'ttl' => $exp - time(), 'staff_no' => $e['staff_no'],
            'name' => $e['firstname'].' '.$e['lastname'], 'sections' => self::sections());
    }

    /** Verify a token completely: shape, signature, stored hash, expiry, revocation and the person's standing. */
    public static function verify($token)
    {
        $p = explode('.', (string) $token);
        if (count($p) !== 3 || !preg_match('/^[a-f0-9]{32}$/', $p[0]) || !ctype_digit($p[1])) { throw new PrestaShopException('Please sign in again', 401); }
        if ((int) $p[1] < time()) { throw new PrestaShopException('Your session has timed out', 401); }
        $s = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::S.'` WHERE sid="'.pSQL($p[0]).'"');
        if (!$s || (int) $s['revoked']) { throw new PrestaShopException('Please sign in again', 401); }
        if (!hash_equals(self::sign($p[0], (int) $s['id_pulse_hr_employee'], (int) $p[1]), $p[2])) { throw new PrestaShopException('Please sign in again', 401); }
        if (!hash_equals($s['token_hash'], hash('sha256', $token))) { throw new PrestaShopException('Please sign in again', 401); }
        if (strtotime($s['expires_at']) < time()) { throw new PrestaShopException('Your session has timed out', 401); }
        $e = Db::getInstance()->getRow('SELECT id_pulse_hr_employee, status, ess_enabled FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE id_pulse_hr_employee='.(int) $s['id_pulse_hr_employee']);
        if (!$e || !$e['ess_enabled'] || $e['status'] === 'exited') { self::revoke((int) $s['id_pulse_hr_ess_session'], 'closed'); throw new PrestaShopException('Your portal access is closed — please see HR', 403); }
        Db::getInstance()->update(self::S, array('date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_ess_session='.(int) $s['id_pulse_hr_ess_session'], 0, true);
        return $s;
    }

    public static function revoke($idSession, $reason = 'signed out') { return Db::getInstance()->update(self::S, array('revoked' => 1, 'revoke_reason' => pSQL($reason), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_ess_session='.(int) $idSession, 0, true); }
    public static function revokeForEmployee($idEmployee, $reason = 'admin') { return Db::getInstance()->update(self::S, array('revoked' => 1, 'revoke_reason' => pSQL($reason), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_employee='.(int) $idEmployee.' AND revoked=0', 0, true); }
    public static function purge($days = 7)
    {
        Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.self::S.'` WHERE expires_at<DATE_SUB(NOW(), INTERVAL '.(int) $days.' DAY)');
        return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_hr_ess_login` WHERE date_add<DATE_SUB(NOW(), INTERVAL 90 DAY)');
    }

    /** Which sections the portal may show, and why the missing ones are missing. */
    public static function sections()
    {
        return array(
            'roster' => array('on' => 1, 'why' => ''),
            'leave' => array('on' => 1, 'why' => ''),
            'documents' => array('on' => 1, 'why' => ''),
            'profile' => array('on' => 1, 'why' => ''),
            'cases' => array('on' => 1, 'why' => ''),
            'clock' => array('on' => (int) PulseHrService::cfg('ESS_CLOCK', 1), 'why' => PulseHrService::cfg('ESS_CLOCK', 1) ? '' : 'Mobile clocking is switched off for this property'),
            'attendance' => array('on' => PulseHrService::ta() ? 1 : 0, 'why' => PulseHrService::ta() ? '' : 'Attendance history needs Pulse Time, which is not installed'),
            'payslips' => array('on' => (PulseHrService::pr() && PulseHrService::cfg('ESS_SHOW_PAYSLIP', 1)) ? 1 : 0,
                'why' => !PulseHrService::pr() ? 'Payslips need Pulse Payroll, which is not installed' : (PulseHrService::cfg('ESS_SHOW_PAYSLIP', 1) ? '' : 'Payslips are not published to the portal at this property')),
        );
    }

    /** The portal's view of a person. No pay, no salary, no bank account — those need the payslip gate. */
    public static function me($idEmployee)
    {
        $e = PulseHrEmployee::get($idEmployee);
        if (!$e) { throw new PrestaShopException('Employee not found', 404); }
        $c = PulseHrContract::onDate($idEmployee);
        return array(
            'staff_no' => $e['staff_no'], 'name' => $e['full_name'], 'photo' => $e['photo'], 'department' => $e['dept_name'], 'section' => $e['section_name'],
            'position' => $e['position_title'], 'grade' => $e['grade_name'], 'manager' => $e['manager_name'], 'status' => $e['status'],
            'hire_date' => $e['hire_date'], 'confirmation_date' => $e['confirmation_date'], 'probation_end' => $e['probation_end'],
            'contract_type' => $c ? $c['type'] : null, 'contract_from' => $c ? $c['effective_from'] : null, 'contract_to' => $c ? $c['end_date'] : null,
            'phone' => $e['phone'], 'email' => $e['email'], 'address' => $e['address'], 'city' => $e['city'],
            'nok_name' => $e['nok_name'], 'nok_relationship' => $e['nok_relationship'], 'nok_phone' => $e['nok_phone'],
            'bank_name' => $e['bank_name'], 'account_masked' => $e['account_no'] ? str_repeat('•', max(0, Tools::strlen($e['account_no']) - 4)).Tools::substr($e['account_no'], -4) : '',
            'nhf_consent' => (int) $e['nhf_consent'], 'nhf_consent_date' => $e['nhf_consent_date'],
            'rsa_pin' => $e['rsa_pin'] ? '••••'.Tools::substr($e['rsa_pin'], -4) : '', 'pfa' => $e['pfa'],
        );
    }

    /** Re-enter the PIN to open a short window in which salary may be served. */
    public static function confirmPin($session, $pin)
    {
        $e = Db::getInstance()->getRow('SELECT staff_no, pin_hash FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE id_pulse_hr_employee='.(int) $session['id_pulse_hr_employee']);
        PulseHrService::rateHit('esspin:'.(int) $session['id_pulse_hr_employee'], 6);
        if (!$e || !$e['pin_hash'] || !hash_equals((string) $e['pin_hash'], PulseHrService::pinHash(trim((string) $pin)))) { self::log($e ? $e['staff_no'] : '', 0, 'bad_pin_reveal'); throw new PrestaShopException('That PIN did not match', 401); }
        $mins = max(1, (int) PulseHrService::cfg('ESS_PAYSLIP_WINDOW_MIN', 5));
        Db::getInstance()->update(self::S, array('payslip_until' => date('Y-m-d H:i:s', time() + $mins * 60), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_ess_session='.(int) $session['id_pulse_hr_ess_session'], 0, true);
        return array('ok' => 1, 'until' => date('c', time() + $mins * 60), 'seconds' => $mins * 60);
    }
    public static function payslipWindowOpen($session)
    {
        $s = Db::getInstance()->getRow('SELECT payslip_until FROM `'._DB_PREFIX_.self::S.'` WHERE id_pulse_hr_ess_session='.(int) $session['id_pulse_hr_ess_session']);
        return $s && $s['payslip_until'] && strtotime($s['payslip_until']) > time();
    }

    /**
     * Payslips come from Pulse Payroll. Without it the portal shows the section as unavailable and says why —
     * it never fabricates a figure and never fatals.
     */
    public static function payslips($idEmployee, $limit = 12)
    {
        if (!PulseHrService::pr()) { return array('available' => 0, 'why' => 'Payslips need Pulse Payroll, which is not installed', 'rows' => array()); }
        if (!PulseHrService::cfg('ESS_SHOW_PAYSLIP', 1)) { return array('available' => 0, 'why' => 'Payslips are not published to the portal at this property', 'rows' => array()); }
        if (class_exists('PulsePrPayslip') && method_exists('PulsePrPayslip', 'forEmployee')) {
            return array('available' => 1, 'why' => '', 'rows' => PulsePrPayslip::forEmployee((int) $idEmployee, (int) $limit));
        }
        // no class to call, but the table is there: read the columns every payslip must have
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_payslip` WHERE id_pulse_hr_employee='.(int) $idEmployee.' ORDER BY id_pulse_pr_payslip DESC LIMIT '.(int) $limit);
        return array('available' => 1, 'why' => '', 'rows' => $rows ? $rows : array());
    }

    /* ---------- change requests ---------- */

    /** A member of staff asks for a detail to be changed; HR approves it. Nothing self-writes to the master. */
    public static function requestChange($idEmployee, $field, $value)
    {
        $allowed = PulseHrEmployee::selfServiceFields();
        if (!in_array($field, $allowed)) { throw new PrestaShopException('That detail cannot be changed from the portal — see HR', 400); }
        if (!PulseHrService::cfg('ESS_SELF_UPDATE', 1)) { throw new PrestaShopException('Detail changes are handled by HR at this property', 403); }
        $e = PulseHrEmployee::get($idEmployee);
        $value = Tools::substr(trim((string) $value), 0, 250);
        if ($field === 'email' && $value !== '' && !Validate::isEmail($value)) { throw new PrestaShopException('That email address does not look right', 400); }
        if (in_array($field, array('phone', 'phone_alt', 'nok_phone')) && $value !== '' && !preg_match('/^[0-9 +()-]{7,20}$/', $value)) { throw new PrestaShopException('That phone number does not look right', 400); }
        if (in_array($field, array('account_no')) && $value !== '' && !preg_match('/^[0-9]{10}$/', $value)) { throw new PrestaShopException('A Nigerian account number is ten digits', 400); }
        if ((string) $e[$field] === $value) { throw new PrestaShopException('That is what we already have on file', 400); }
        if (Db::getInstance()->getValue('SELECT id_pulse_hr_change_request FROM `'._DB_PREFIX_.'pulse_hr_change_request` WHERE id_pulse_hr_employee='.(int) $idEmployee.' AND field="'.pSQL($field).'" AND status="pending"')) { throw new PrestaShopException('You already have a change to that detail waiting with HR', 400); }
        Db::getInstance()->insert('pulse_hr_change_request', array('id_pulse_hr_employee' => (int) $idEmployee, 'field' => pSQL($field), 'old_value' => pSQL((string) $e[$field]),
            'new_value' => pSQL($value), 'status' => 'pending', 'date_add' => date('Y-m-d H:i:s')), true);
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::audit('pulsehr', 'ess_change_request', array('field' => $field, 'staff_no' => $e['staff_no']), 'pulse_hr_change_request', $id);
        return array('id' => $id, 'field' => $field, 'status' => 'pending');
    }

    public static function changeRequests($status = 'pending')
    {
        return Db::getInstance()->executeS('SELECT cr.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.name dept_name
            FROM `'._DB_PREFIX_.'pulse_hr_change_request` cr INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=cr.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE 1'.($status ? ' AND cr.status="'.pSQL($status).'"' : '').' ORDER BY cr.date_add DESC LIMIT 200');
    }

    public static function decideChange($id, $approve, $note = '')
    {
        $cr = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_change_request` WHERE id_pulse_hr_change_request='.(int) $id);
        if (!$cr || $cr['status'] !== 'pending') { throw new PrestaShopException('Change request not found'); }
        if (!in_array($cr['field'], PulseHrEmployee::selfServiceFields())) { throw new PrestaShopException('That field is not self-service'); }
        if ($approve) { PulseHrEmployee::save(array($cr['field'] => $cr['new_value']), (int) $cr['id_pulse_hr_employee']); }
        Db::getInstance()->update('pulse_hr_change_request', array('status' => $approve ? 'approved' : 'rejected', 'note' => pSQL($note),
            'decided_by' => PulseHrService::emp(), 'decided_at' => date('Y-m-d H:i:s')), 'id_pulse_hr_change_request='.(int) $id, 0, true);
        PulseCoreService::audit('pulsehr', 'ess_change_'.($approve ? 'approved' : 'rejected'), array('field' => $cr['field'], 'value' => $cr['new_value']), 'pulse_hr_change_request', (int) $id);
        return true;
    }

    /* ---------- the geofenced mobile punch ---------- */

    /** The staff-entrance QR. Rotating when a rotation window is configured, otherwise a printed static code. */
    public static function qrCode()
    {
        $rotate = (int) PulseHrService::cfg('ESS_QR_ROTATE_MIN', 0);
        if ($rotate <= 0) { return (string) PulseHrService::cfg('ESS_QR_CODE', ''); }
        return Tools::substr(hash_hmac('sha256', 'qr|'.(int) floor(time() / ($rotate * 60)), self::secret()), 0, 12);
    }
    public static function qrValid($code)
    {
        $code = trim((string) $code);
        if ($code === '') { return false; }
        $rotate = (int) PulseHrService::cfg('ESS_QR_ROTATE_MIN', 0);
        if ($rotate <= 0) { $set = (string) PulseHrService::cfg('ESS_QR_CODE', ''); return $set !== '' && hash_equals($set, $code); }
        $w = (int) floor(time() / ($rotate * 60));
        foreach (array($w, $w - 1) as $win) { if (hash_equals(Tools::substr(hash_hmac('sha256', 'qr|'.$win, self::secret()), 0, 12), $code)) { return true; } }
        return false;
    }

    /**
     * Record a punch from a phone. The coordinates and their accuracy are always stored, whatever the outcome,
     * so a supervisor can see a punch that came from the wrong side of town. A punch outside the fence is
     * rejected when enforcement is on and flagged when it is not — it is never quietly accepted, and it is
     * never quietly discarded either.
     */
    public static function clock($idEmployee, array $p = array())
    {
        $e = PulseHrEmployee::get($idEmployee);
        if (!$e) { throw new PrestaShopException('Employee not found', 404); }
        if (!PulseHrService::cfg('ESS_CLOCK', 1)) { throw new PrestaShopException('Mobile clocking is switched off for this property', 403); }
        if (in_array($e['status'], array('exited', 'suspended'))) { throw new PrestaShopException('Clocking is not available on your account — see HR', 403); }
        PulseHrService::rateHit('esspunch:'.(int) $idEmployee, (int) PulseHrService::cfg('ESS_PUNCH_PER_MIN', 6));

        $at = date('Y-m-d H:i:s');
        $lat = isset($p['lat']) && $p['lat'] !== '' ? (float) $p['lat'] : null;
        $lng = isset($p['lng']) && $p['lng'] !== '' ? (float) $p['lng'] : null;
        $acc = isset($p['accuracy']) && $p['accuracy'] !== '' ? round((float) $p['accuracy'], 2) : null;
        $qr = isset($p['qr']) ? trim((string) $p['qr']) : '';
        $viaQr = $qr !== '' && self::qrValid($qr);
        $device = Tools::substr(isset($p['device']) ? (string) $p['device'] : (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''), 0, 64);

        $direction = isset($p['direction']) && in_array($p['direction'], array('in', 'out')) ? $p['direction'] : self::nextDirection($idEmployee);
        $dedupe = (int) PulseHrService::cfg('PUNCH_DEDUPE_SEC', 120);
        $last = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_punch` WHERE id_pulse_hr_employee='.(int) $idEmployee.' AND direction="'.pSQL($direction).'"
            AND punched_at>DATE_SUB(NOW(), INTERVAL '.max(1, $dedupe).' SECOND) ORDER BY punched_at DESC');
        if ($last) { return array('id' => (int) $last['id_pulse_hr_punch'], 'duplicate' => 1, 'direction' => $last['direction'], 'punched_at' => $last['punched_at'], 'status' => $last['status'], 'message' => 'That punch is already recorded'); }

        $siteLat = PulseHrService::cfg('GEO_LAT', ''); $siteLng = PulseHrService::cfg('GEO_LNG', '');
        $radius = (float) PulseHrService::cfg('GEO_RADIUS_M', 200);
        $enforce = (int) PulseHrService::cfg('GEO_ENFORCE', 1);
        $maxAcc = (float) PulseHrService::cfg('GEO_MAX_ACCURACY_M', 100);
        $distance = null; $inside = 0; $flag = '';
        if ($siteLat === '' || $siteLng === '') { $inside = 1; $flag = 'no_site_geofence'; }
        elseif ($lat === null || $lng === null) { $flag = 'no_location'; }
        else {
            $distance = PulseHrService::distanceMetres($lat, $lng, (float) $siteLat, (float) $siteLng);
            $inside = $distance <= $radius ? 1 : 0;
            if (!$inside) { $flag = 'outside_geofence'; }
            elseif ($acc !== null && $maxAcc > 0 && $acc > $maxAcc) { $flag = 'poor_accuracy'; }
        }
        if ($viaQr && !$inside && (int) PulseHrService::cfg('GEO_QR_WAIVES', 1)) { $inside = 1; $flag = $flag ? $flag.'_qr_waived' : ''; }
        $status = 'accepted';
        if ($flag === 'outside_geofence' || $flag === 'no_location') { $status = $enforce ? 'rejected' : 'flagged'; }
        elseif ($flag && $flag !== 'no_site_geofence') { $status = 'flagged'; }

        $cell = PulseHrRoster::cellForPunch($idEmployee, $at);
        Db::getInstance()->insert('pulse_hr_punch', array(
            'id_pulse_hr_employee' => (int) $idEmployee, 'punched_at' => pSQL($at), 'direction' => pSQL($direction), 'source' => $viaQr ? 'qr' : 'mobile',
            'lat' => $lat === null ? null : $lat, 'lng' => $lng === null ? null : $lng, 'accuracy_m' => $acc === null ? null : $acc, 'distance_m' => $distance === null ? null : $distance,
            'inside_geofence' => $inside ? 1 : 0, 'status' => pSQL($status), 'flag_reason' => pSQL($flag), 'device' => pSQL($device),
            'ip' => pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 45)), 'id_pulse_hr_roster' => $cell ? (int) $cell['id_pulse_hr_roster'] : null,
            'note' => pSQL(isset($p['note']) ? Tools::substr((string) $p['note'], 0, 250) : ''), 'business_date' => pSQL(PulseHrService::bd()), 'date_add' => date('Y-m-d H:i:s'),
        ), true, true, Db::INSERT_IGNORE);
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::audit('pulsehr', 'ess_punch', array('staff_no' => $e['staff_no'], 'direction' => $direction, 'status' => $status, 'flag' => $flag,
            'distance_m' => $distance, 'accuracy_m' => $acc, 'via_qr' => $viaQr ? 1 : 0), 'pulse_hr_punch', $id);
        if ($status !== 'rejected') { self::handOver($id); }
        PulseCoreService::event('actionPulseHrMobilePunch', array('id_punch' => $id, 'id_pulse_hr_employee' => (int) $idEmployee, 'staff_no' => $e['staff_no'],
            'punched_at' => $at, 'direction' => $direction, 'source' => $viaQr ? 'qr' : 'mobile', 'status' => $status, 'lat' => $lat, 'lng' => $lng, 'accuracy_m' => $acc, 'distance_m' => $distance));
        if ($status === 'flagged' || $status === 'rejected') {
            $where = $distance === null ? 'with no location' : 'about '.(int) round($distance).' m from the hotel';
            if (class_exists('PulseTrace')) { PulseTrace::add('alert', 'Mobile clock-'.$direction.' by '.$e['full_name'].' ('.$e['staff_no'].') '.$where.' — '.$status, date('Y-m-d H:i:s'), null, null, null, 'management'); }
        }
        $msg = $status === 'rejected' ? 'You are too far from the hotel to clock '.$direction.'. Your supervisor has been notified.'
            : ($status === 'flagged' ? 'Clocked '.$direction.', but your location could not be confirmed — your supervisor will check it.' : 'Clocked '.$direction.' at '.date('H:i', strtotime($at)));
        return array('id' => $id, 'direction' => $direction, 'punched_at' => $at, 'status' => $status, 'inside_geofence' => $inside, 'distance_m' => $distance,
            'accuracy_m' => $acc, 'via_qr' => $viaQr ? 1 : 0, 'shift' => $cell ? array('date' => $cell['roster_date'], 'start' => $cell['start_time'], 'end' => $cell['end_time']) : null, 'message' => $msg);
    }

    /** In or out? The last punch of the day decides, so nobody has to remember which button they pressed. */
    public static function nextDirection($idEmployee)
    {
        $last = Db::getInstance()->getValue('SELECT direction FROM `'._DB_PREFIX_.'pulse_hr_punch` WHERE id_pulse_hr_employee='.(int) $idEmployee.'
            AND status<>"rejected" AND punched_at>DATE_SUB(NOW(), INTERVAL 18 HOUR) ORDER BY punched_at DESC LIMIT 1');
        return $last === 'in' ? 'out' : 'in';
    }

    /**
     * Hand a punch to Pulse Time when it is installed. Pulse Time owns the punch store and the attendance
     * engine; we call whatever ingestion point it publishes rather than writing into its tables blind.
     */
    public static function handOver($idPunch)
    {
        if (!PulseHrService::ta()) { return false; }
        $p = Db::getInstance()->getRow('SELECT p.*, e.staff_no FROM `'._DB_PREFIX_.'pulse_hr_punch` p INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=p.id_pulse_hr_employee WHERE p.id_pulse_hr_punch='.(int) $idPunch);
        if (!$p || (int) $p['synced']) { return false; }
        if (!class_exists('PulseTaPunch') || !class_exists('PulseTaService') || !method_exists('PulseTaPunch', 'manual')) { return false; }
        // Pulse Time keys punches on its own staff row: prefer the HR link, fall back to the staff number.
        $s = method_exists('PulseTaService', 'staffByHr') ? PulseTaService::staffByHr((int) $p['id_pulse_hr_employee']) : null;
        if (!$s && method_exists('PulseTaService', 'staffByNo')) { $s = PulseTaService::staffByNo($p['staff_no']); }
        if (!$s) { PulseCoreService::audit('pulsehr', 'punch_handover_unmapped', array('id' => (int) $idPunch, 'staff_no' => $p['staff_no'])); return false; }
        $extra = array('device_serial' => 'MOBILE', 'raw' => json_encode($p),
            'latitude' => $p['lat'] !== null && $p['lat'] !== '' ? (float) $p['lat'] : '', 'longitude' => $p['lng'] !== null && $p['lng'] !== '' ? (float) $p['lng'] : '',
            'accuracy_m' => $p['accuracy_m'] !== null && $p['accuracy_m'] !== '' ? (int) $p['accuracy_m'] : '');
        try {
            PulseTaPunch::manual((int) $s['id_pulse_ta_staff'], $p['punched_at'], $p['direction'], $p['source'] === 'qr' ? 'mobile' : $p['source'], $extra);
            Db::getInstance()->update('pulse_hr_punch', array('synced' => 1), 'id_pulse_hr_punch='.(int) $idPunch, 0, true);
            return true;
        } catch (Exception $e) {
            PulseCoreService::audit('pulsehr', 'punch_handover_failed', array('id' => (int) $idPunch, 'error' => $e->getMessage()));
            return false;
        }
    }

    /* ---------- supervisor views ---------- */

    public static function punches($from, $to, $status = null, $idEmployee = 0, $limit = 300)
    {
        return Db::getInstance()->executeS('SELECT p.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.name dept_name
            FROM `'._DB_PREFIX_.'pulse_hr_punch` p INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=p.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE p.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'
            .($status ? ' AND p.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'")' : '')
            .($idEmployee ? ' AND p.id_pulse_hr_employee='.(int) $idEmployee : '').' ORDER BY p.punched_at DESC LIMIT '.(int) $limit);
    }
    public static function flaggedPunches($date = null, $limit = 20)
    {
        $d = pSQL($date ? $date : PulseHrService::bd());
        return Db::getInstance()->executeS('SELECT p.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.name dept_name
            FROM `'._DB_PREFIX_.'pulse_hr_punch` p INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=p.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE p.status IN ("flagged","rejected") AND p.reviewed_at IS NULL AND p.business_date>=DATE_SUB("'.$d.'", INTERVAL 7 DAY) ORDER BY p.punched_at DESC LIMIT '.(int) $limit);
    }
    /** A supervisor accepts or dismisses a flagged punch; an accepted one is then handed to Pulse Time. */
    public static function reviewPunch($idPunch, $accept, $note = '')
    {
        Db::getInstance()->update('pulse_hr_punch', array('status' => $accept ? 'accepted' : 'rejected', 'reviewed_by' => PulseHrService::emp(),
            'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => pSQL($note)), 'id_pulse_hr_punch='.(int) $idPunch, 0, true);
        if ($accept) { self::handOver($idPunch); }
        PulseCoreService::audit('pulsehr', 'punch_review', array('accepted' => $accept ? 1 : 0, 'note' => $note), 'pulse_hr_punch', (int) $idPunch);
        return true;
    }

    /** Hours worked from paired punches — used when Pulse Time is not installed. */
    public static function workedHours($from, $to, $idEmployee = 0)
    {
        $rows = Db::getInstance()->executeS('SELECT id_pulse_hr_employee, punched_at, direction FROM `'._DB_PREFIX_.'pulse_hr_punch`
            WHERE status="accepted" AND business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'.($idEmployee ? ' AND id_pulse_hr_employee='.(int) $idEmployee : '').'
            ORDER BY id_pulse_hr_employee, punched_at');
        $open = array(); $hours = 0; $maxShift = (float) PulseHrService::cfg('MAX_SHIFT_HOURS', 16);
        foreach ($rows as $r) {
            $k = (int) $r['id_pulse_hr_employee'];
            if ($r['direction'] === 'in') { $open[$k] = strtotime($r['punched_at']); continue; }
            if (!isset($open[$k])) { continue; } // an out with no in is an exception, not a negative shift
            $h = (strtotime($r['punched_at']) - $open[$k]) / 3600;
            unset($open[$k]);
            if ($h > 0 && $h <= $maxShift) { $hours += $h; }
        }
        return round($hours, 2);
    }
}
