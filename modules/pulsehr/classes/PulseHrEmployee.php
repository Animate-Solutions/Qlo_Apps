<?php
/**
 * The employee master. One row per person, whatever else they are in the suite: the PrestaShop `employee`
 * row (back office and POS) is LINKED through id_employee, never copied. Departments, positions and grades
 * on this row are a mirror of the contract in force — pulse_hr_contract is the authority payroll reads.
 */
class PulseHrEmployee
{
    const T = 'pulse_hr_employee';

    protected static function select()
    {
        return 'SELECT e.*, CONCAT(e.firstname," ",e.lastname) full_name, d.code dept_code, d.name dept_name, s.name section_name,
                p.title position_title, g.code grade_code, g.name grade_name, CONCAT(m.firstname," ",m.lastname) manager_name,
                CONCAT(pe.firstname," ",pe.lastname) bo_user
            FROM `'._DB_PREFIX_.self::T.'` e
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_section` s ON s.id_pulse_hr_section=e.id_pulse_hr_section
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_position` p ON p.id_pulse_hr_position=e.id_pulse_hr_position
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_grade` g ON g.id_pulse_hr_grade=e.id_pulse_hr_grade
            LEFT JOIN `'._DB_PREFIX_.self::T.'` m ON m.id_pulse_hr_employee=e.id_manager
            LEFT JOIN `'._DB_PREFIX_.'employee` pe ON pe.id_employee=e.id_employee ';
    }

    public static function get($id) { return Db::getInstance()->getRow(self::select().' WHERE e.id_pulse_hr_employee='.(int) $id); }
    public static function byStaffNo($no) { return Db::getInstance()->getRow(self::select().' WHERE e.staff_no="'.pSQL($no).'"'); }
    public static function byPsEmployee($idEmployee) { return $idEmployee ? Db::getInstance()->getRow(self::select().' WHERE e.id_employee='.(int) $idEmployee) : null; }

    /** The employee list, filtered the way the screen filters: free text, department, status, grade. */
    public static function search(array $f = array())
    {
        $w = ' WHERE 1';
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w .= ' AND (e.staff_no LIKE "%'.$q.'%" OR e.firstname LIKE "%'.$q.'%" OR e.lastname LIKE "%'.$q.'%" OR e.phone LIKE "%'.$q.'%" OR e.email LIKE "%'.$q.'%" OR e.national_id LIKE "%'.$q.'%")'; }
        if (!empty($f['department'])) { $w .= ' AND d.code="'.pSQL($f['department']).'"'; }
        if (!empty($f['id_department'])) { $w .= ' AND e.id_pulse_hr_department='.(int) $f['id_department']; }
        if (!empty($f['id_grade'])) { $w .= ' AND e.id_pulse_hr_grade='.(int) $f['id_grade']; }
        if (!empty($f['status'])) { $w .= ' AND e.status IN ("'.implode('","', array_map('pSQL', explode(',', $f['status']))).'")'; }
        elseif (empty($f['include_exited'])) { $w .= ' AND e.status<>"exited"'; }
        $limit = isset($f['limit']) ? max(1, min(500, (int) $f['limit'])) : 200;
        return Db::getInstance()->executeS(self::select().$w.' ORDER BY d.sort, e.lastname, e.firstname LIMIT '.$limit);
    }

    /** Next staff number in the configured series, e.g. PH0043. */
    public static function nextStaffNo()
    {
        $prefix = PulseHrService::cfg('STAFF_PREFIX', 'PH');
        for ($i = 0; $i < 50; $i++) {
            $n = (int) PulseCoreService::setting('pulsehr', 'seq_staff') + 1;
            PulseCoreService::setting('pulsehr', 'seq_staff', $n);
            $no = $prefix.str_pad($n, 4, '0', STR_PAD_LEFT);
            if (!Db::getInstance()->getValue('SELECT id_pulse_hr_employee FROM `'._DB_PREFIX_.self::T.'` WHERE staff_no="'.pSQL($no).'"')) { return $no; }
        }
        return $prefix.date('ymdHis');
    }

    /** Fields the ESS portal is allowed to ask to change, and the admin may edit freely. */
    public static function selfServiceFields() { return array('phone', 'phone_alt', 'email', 'address', 'city', 'nok_name', 'nok_relationship', 'nok_phone', 'nok_address', 'bank_name', 'bank_code', 'account_no', 'account_name', 'marital'); }

    /**
     * Create or update the person. A new row also gets its first contract version and an onboarding
     * checklist, and raises actionPulseHrEmployeeHired so Key Cards and POS can react.
     */
    public static function save(array $d, $id = 0)
    {
        $cols = array('firstname', 'lastname', 'othernames', 'photo', 'gender', 'marital', 'nationality', 'state_of_origin', 'lga', 'national_id', 'tin', 'rsa_pin', 'pfa',
            'nhf_no', 'bank_name', 'bank_code', 'account_no', 'account_name', 'nok_name', 'nok_relationship', 'nok_phone', 'nok_address',
            'address', 'city', 'phone', 'phone_alt', 'email', 'note', 'exit_reason');
        $row = array();
        foreach ($cols as $c) { if (array_key_exists($c, $d)) { $row[$c] = pSQL((string) $d[$c]); } }
        foreach (array('dob', 'hire_date', 'probation_end', 'confirmation_date', 'exit_date', 'nhf_consent_date') as $c) { if (array_key_exists($c, $d)) { $row[$c] = $d[$c] ? pSQL(date('Y-m-d', strtotime($d[$c]))) : null; } }
        foreach (array('id_employee', 'id_pulse_hr_department', 'id_pulse_hr_section', 'id_pulse_hr_position', 'id_pulse_hr_grade', 'id_manager') as $c) { if (array_key_exists($c, $d)) { $row[$c] = $d[$c] ? (int) $d[$c] : null; } }
        foreach (array('nhf_consent', 'ess_enabled', 'rehire_eligible') as $c) { if (array_key_exists($c, $d)) { $row[$c] = !empty($d[$c]) ? 1 : 0; } }
        if (array_key_exists('nhf_consent_note', $d)) { $row['nhf_consent_note'] = pSQL((string) $d['nhf_consent_note']); }
        if (array_key_exists('status', $d) && $d['status']) { $row['status'] = pSQL($d['status']); }
        if (array_key_exists('exit_type', $d) && $d['exit_type']) { $row['exit_type'] = pSQL($d['exit_type']); }
        if (!empty($row['email']) && !Validate::isEmail($row['email'])) { throw new PrestaShopException('That email address does not look right'); }
        if (!empty($row['id_employee'])) {
            $clash = (int) Db::getInstance()->getValue('SELECT id_pulse_hr_employee FROM `'._DB_PREFIX_.self::T.'` WHERE id_employee='.(int) $row['id_employee'].($id ? ' AND id_pulse_hr_employee<>'.(int) $id : ''));
            if ($clash) { throw new PrestaShopException('That back-office user is already linked to staff record #'.$clash); }
        }
        // NHF consent is a dated, evidenced decision — the date is stamped the moment the box is ticked, never back-filled silently
        if (!empty($row['nhf_consent']) && empty($row['nhf_consent_date'])) { $row['nhf_consent_date'] = date('Y-m-d'); }
        if (isset($row['nhf_consent']) && !$row['nhf_consent']) { $row['nhf_consent_date'] = null; }
        // an empty name would be written as NULL onto a NOT NULL column — refuse it rather than corrupt the row
        foreach (array('firstname', 'lastname') as $c) { if (array_key_exists($c, $row) && trim((string) $row[$c]) === '') { throw new PrestaShopException('A first name and a surname are required'); } }
        $row['date_upd'] = date('Y-m-d H:i:s');
        if ($id) {
            Db::getInstance()->update(self::T, $row, 'id_pulse_hr_employee='.(int) $id, 0, true);
            PulseCoreService::audit('pulsehr', 'employee_update', array('id' => (int) $id, 'fields' => array_keys($row)), self::T, $id);
            return (int) $id;
        }
        if (empty($row['firstname']) || empty($row['lastname'])) { throw new PrestaShopException('A first name and a surname are required'); }
        $row['staff_no'] = pSQL(!empty($d['staff_no']) ? $d['staff_no'] : self::nextStaffNo());
        if (Db::getInstance()->getValue('SELECT id_pulse_hr_employee FROM `'._DB_PREFIX_.self::T.'` WHERE staff_no="'.$row['staff_no'].'"')) { throw new PrestaShopException('Staff number '.$row['staff_no'].' is already in use'); }
        if (empty($row['hire_date'])) { $row['hire_date'] = date('Y-m-d'); }
        if (empty($row['status'])) { $row['status'] = 'probation'; }
        $months = (int) (isset($d['probation_months']) ? $d['probation_months'] : PulseHrService::cfg('PROBATION_MONTHS', 6));
        if (empty($row['probation_end']) && $months > 0) { $row['probation_end'] = date('Y-m-d', strtotime($row['hire_date'].' +'.$months.' month')); }
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert(self::T, $row, true);
        $id = (int) Db::getInstance()->Insert_ID();
        if (!empty($d['pay_rate']) || !empty($d['id_pulse_hr_position'])) {
            PulseHrContract::save(array('id_pulse_hr_employee' => $id, 'type' => isset($d['contract_type']) ? $d['contract_type'] : 'permanent', 'effective_from' => $row['hire_date'], 'start_date' => $row['hire_date'],
                'id_pulse_hr_position' => isset($d['id_pulse_hr_position']) ? $d['id_pulse_hr_position'] : null, 'id_pulse_hr_department' => isset($d['id_pulse_hr_department']) ? $d['id_pulse_hr_department'] : null,
                'id_pulse_hr_section' => isset($d['id_pulse_hr_section']) ? $d['id_pulse_hr_section'] : null, 'id_pulse_hr_grade' => isset($d['id_pulse_hr_grade']) ? $d['id_pulse_hr_grade'] : null,
                'id_manager' => isset($d['id_manager']) ? $d['id_manager'] : null, 'pay_basis' => isset($d['pay_basis']) ? $d['pay_basis'] : 'monthly', 'pay_rate' => isset($d['pay_rate']) ? $d['pay_rate'] : 0,
                'probation_months' => $months, 'reason' => 'hire', 'end_date' => isset($d['end_date']) ? $d['end_date'] : null, 'night_shift' => isset($d['night_shift']) ? $d['night_shift'] : 0));
        }
        PulseHrLeave::openBalances($id);
        if (empty($d['skip_checklist'])) { PulseHrLifecycle::open($id, 'onboarding'); }
        PulseCoreService::audit('pulsehr', 'employee_hire', array('staff_no' => $row['staff_no'], 'name' => $row['firstname'].' '.$row['lastname']), self::T, $id);
        PulseCoreService::event('actionPulseHrEmployeeHired', array('id_pulse_hr_employee' => $id, 'staff_no' => $row['staff_no'], 'id_employee' => isset($row['id_employee']) ? (int) $row['id_employee'] : 0, 'hire_date' => $row['hire_date']));
        return $id;
    }

    /** Mirror the contract in force onto the employee row so lists and filters stay fast. */
    public static function syncFromContract($idEmployee, array $c = null)
    {
        $c = $c ? $c : PulseHrContract::onDate($idEmployee, PulseHrService::bd());
        if (!$c) { return false; }
        return Db::getInstance()->update(self::T, array(
            'id_pulse_hr_department' => $c['id_pulse_hr_department'] ? (int) $c['id_pulse_hr_department'] : null,
            'id_pulse_hr_section' => $c['id_pulse_hr_section'] ? (int) $c['id_pulse_hr_section'] : null,
            'id_pulse_hr_position' => $c['id_pulse_hr_position'] ? (int) $c['id_pulse_hr_position'] : null,
            'id_pulse_hr_grade' => $c['id_pulse_hr_grade'] ? (int) $c['id_pulse_hr_grade'] : null,
            'id_manager' => $c['id_manager'] ? (int) $c['id_manager'] : null, 'date_upd' => date('Y-m-d H:i:s'),
        ), 'id_pulse_hr_employee='.(int) $idEmployee, 0, true);
    }

    public static function setStatus($id, $status, $note = '')
    {
        $e = self::get($id);
        if (!$e) { throw new PrestaShopException('Employee not found'); }
        if (!in_array($status, array('probation', 'active', 'suspended', 'on_leave', 'exited'))) { throw new PrestaShopException('Unknown status'); }
        Db::getInstance()->update(self::T, array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_employee='.(int) $id, 0, true);
        if ($status === 'suspended' || $status === 'exited') { PulseHrEss::revokeForEmployee((int) $id, $status); }
        PulseCoreService::audit('pulsehr', 'employee_status', array('from' => $e['status'], 'to' => $status, 'note' => $note), self::T, $id);
        return true;
    }

    /** Confirm someone off probation: a contract version with reason=confirmation, so the pay history stays honest. */
    public static function confirm($id, $date = null, $newRate = null)
    {
        $e = self::get($id);
        if (!$e) { throw new PrestaShopException('Employee not found'); }
        $date = $date ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
        $c = PulseHrContract::onDate($id, $date);
        Db::getInstance()->update(self::T, array('status' => 'active', 'confirmation_date' => pSQL($date), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_employee='.(int) $id, 0, true);
        if ($c) {
            $n = $c; $n['effective_from'] = $date; $n['reason'] = 'confirmation'; $n['note'] = 'Confirmed after probation';
            if ($newRate !== null && $newRate !== '') { $n['pay_rate'] = (float) $newRate; }
            unset($n['id_pulse_hr_contract']);
            PulseHrContract::save($n);
        }
        PulseCoreService::audit('pulsehr', 'employee_confirm', array('date' => $date), self::T, $id);
        return true;
    }

    /**
     * Exit the person: close the contract, stop the leave clock, open the offboarding checklist, kill their
     * portal sessions and raise actionPulseHrEmployeeExited. Key cards and POS are handled by the checklist
     * actions so a failed encoder does not block the exit.
     */
    public static function exitEmployee($id, $date, $type = 'resignation', $reason = '', $rehire = 1)
    {
        $e = self::get($id);
        if (!$e) { throw new PrestaShopException('Employee not found'); }
        if ($e['status'] === 'exited') { throw new PrestaShopException($e['firstname'].' has already been exited'); }
        $date = date('Y-m-d', strtotime($date ? $date : 'now'));
        Db::getInstance()->update(self::T, array('status' => 'exited', 'exit_date' => pSQL($date), 'exit_type' => pSQL($type), 'exit_reason' => pSQL($reason),
            'rehire_eligible' => (int) $rehire ? 1 : 0, 'ess_enabled' => 0, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_employee='.(int) $id, 0, true);
        PulseHrContract::endAll($id, $date, 'exit');
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_leave_request` SET status="cancelled", decision_note="Employee exited", date_upd=NOW() WHERE id_pulse_hr_employee='.(int) $id.' AND status="pending"');
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_roster` SET status="cancelled", date_upd=NOW() WHERE id_pulse_hr_employee='.(int) $id.' AND roster_date>"'.pSQL($date).'" AND status<>"cancelled"');
        PulseHrEss::revokeForEmployee((int) $id, 'exited');
        $idChecklist = PulseHrLifecycle::open($id, 'offboarding');
        $bal = PulseHrLeave::balances($id, (int) date('Y', strtotime($date)));
        PulseCoreService::audit('pulsehr', 'employee_exit', array('date' => $date, 'type' => $type, 'reason' => $reason), self::T, $id);
        PulseCoreService::event('actionPulseHrEmployeeExited', array('id_pulse_hr_employee' => (int) $id, 'staff_no' => $e['staff_no'], 'id_employee' => (int) $e['id_employee'],
            'exit_date' => $date, 'exit_type' => $type, 'id_checklist' => $idChecklist, 'leave_balance' => $bal));
        return $idChecklist;
    }

    /* ---------- credentials and links ---------- */

    /** Set the staff-portal PIN. Same hash as PulsePosService, so one PIN can be shared with the POS if the hotel wants that. */
    public static function setPin($id, $pin)
    {
        $pin = trim((string) $pin);
        $min = (int) PulseHrService::cfg('PIN_MIN_LENGTH', 4);
        if (!ctype_digit($pin) || strlen($pin) < $min) { throw new PrestaShopException('The PIN must be at least '.$min.' digits'); }
        if (preg_match('/^(\d)\1+$/', $pin) || in_array($pin, array('1234', '12345', '123456', '0000'))) { throw new PrestaShopException('That PIN is too easy to guess'); }
        Db::getInstance()->update(self::T, array('pin_hash' => PulseHrService::pinHash($pin), 'pin_set_at' => date('Y-m-d H:i:s'), 'ess_locked_until' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_employee='.(int) $id, 0, true);
        PulseCoreService::audit('pulsehr', 'ess_pin_set', array('id' => (int) $id), self::T, $id);
        return true;
    }
    public static function clearPin($id) { return Db::getInstance()->update(self::T, array('pin_hash' => null, 'pin_set_at' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_employee='.(int) $id, 0, true); }

    /** POS link goes through the PrestaShop employee row that pulse_pos_staff itself keys on. */
    public static function posStaff($idEmployee)
    {
        if (!$idEmployee || !PulseHrService::pos()) { return null; }
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_staff` WHERE id_employee='.(int) $idEmployee);
    }

    /* ---------- watch lists ---------- */

    public static function probationDue($days = 30)
    {
        return Db::getInstance()->executeS(self::select().' WHERE e.status="probation" AND e.probation_end IS NOT NULL AND e.probation_end<=DATE_ADD(CURDATE(), INTERVAL '.(int) $days.' DAY) ORDER BY e.probation_end');
    }
    public static function birthdays($days = 7)
    {
        return Db::getInstance()->executeS(self::select().' WHERE e.status<>"exited" AND e.dob IS NOT NULL
            AND (DAYOFYEAR(e.dob) - DAYOFYEAR(CURDATE()) BETWEEN 0 AND '.(int) $days.' OR DAYOFYEAR(e.dob) - DAYOFYEAR(CURDATE()) + 365 BETWEEN 0 AND '.(int) $days.') ORDER BY DAYOFYEAR(e.dob)');
    }
    public static function directReports($id) { return Db::getInstance()->executeS(self::select().' WHERE e.id_manager='.(int) $id.' AND e.status<>"exited" ORDER BY e.lastname'); }

    /** The org chart as a nested array from the people with no manager down. */
    public static function orgChart()
    {
        $all = Db::getInstance()->executeS(self::select().' WHERE e.status<>"exited" ORDER BY g.level DESC, e.lastname');
        $byParent = array();
        foreach ($all as $e) { $byParent[(int) $e['id_manager']][] = $e; }
        return self::branch($byParent, 0, 0);
    }
    protected static function branch(array $byParent, $parent, $depth)
    {
        $out = array();
        if ($depth > 8 || empty($byParent[$parent])) { return $out; }
        foreach ($byParent[$parent] as $e) {
            $e['depth'] = $depth;
            $out[] = $e;
            foreach (self::branch($byParent, (int) $e['id_pulse_hr_employee'], $depth + 1) as $child) { $out[] = $child; }
        }
        return $out;
    }
}
