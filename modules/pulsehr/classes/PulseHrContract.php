<?php
/**
 * Effective-dated contracts. Every promotion, transfer, renewal or salary review is a NEW row with its own
 * effective_from; the row it replaces is closed the day before and marked superseded. Nothing is ever edited
 * in place, so a payroll re-run of a past period reads the terms that were actually in force then.
 *
 * Payroll must call onDate() or forPeriod() — never "the current contract".
 */
class PulseHrContract
{
    const T = 'pulse_hr_contract';

    protected static function select()
    {
        return 'SELECT c.*, d.code dept_code, d.name dept_name, s.name section_name, p.title position_title, g.code grade_code, g.name grade_name,
                CONCAT(m.firstname," ",m.lastname) manager_name, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no
            FROM `'._DB_PREFIX_.self::T.'` c
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=c.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=c.id_pulse_hr_department
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_section` s ON s.id_pulse_hr_section=c.id_pulse_hr_section
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_position` p ON p.id_pulse_hr_position=c.id_pulse_hr_position
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_grade` g ON g.id_pulse_hr_grade=c.id_pulse_hr_grade
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_employee` m ON m.id_pulse_hr_employee=c.id_manager ';
    }

    public static function get($id) { return Db::getInstance()->getRow(self::select().' WHERE c.id_pulse_hr_contract='.(int) $id); }
    public static function history($idEmployee) { return Db::getInstance()->executeS(self::select().' WHERE c.id_pulse_hr_employee='.(int) $idEmployee.' ORDER BY c.effective_from DESC, c.id_pulse_hr_contract DESC'); }

    /**
     * THE accessor: the contract in force on $date. A promotion dated the 15th does not rewrite the terms that
     * applied on the 1st — ask for the date you are paying, not for "now".
     */
    public static function onDate($idEmployee, $date = null)
    {
        $d = pSQL($date ? date('Y-m-d', strtotime($date)) : PulseHrService::bd());
        return Db::getInstance()->getRow(self::select().' WHERE c.id_pulse_hr_employee='.(int) $idEmployee.' AND c.status<>"draft"
            AND c.effective_from<="'.$d.'" AND (c.effective_to IS NULL OR c.effective_to>="'.$d.'") ORDER BY c.effective_from DESC, c.id_pulse_hr_contract DESC');
    }

    /**
     * Every contract version that touches [$from,$to], each clipped to the period with its own day counts.
     * This is what payroll prorates on when someone is promoted mid-month: two segments, two pay bases,
     * and the day counts that split the month between them.
     */
    public static function forPeriod($idEmployee, $from, $to)
    {
        $f = pSQL(date('Y-m-d', strtotime($from))); $t = pSQL(date('Y-m-d', strtotime($to)));
        $rows = Db::getInstance()->executeS(self::select().' WHERE c.id_pulse_hr_employee='.(int) $idEmployee.' AND c.status<>"draft"
            AND c.effective_from<="'.$t.'" AND (c.effective_to IS NULL OR c.effective_to>="'.$f.'") ORDER BY c.effective_from');
        $out = array();
        foreach ($rows as $r) {
            $sFrom = max(strtotime($f), strtotime($r['effective_from']));
            $sTo = min(strtotime($t), $r['effective_to'] ? strtotime($r['effective_to']) : strtotime($t));
            if ($sTo < $sFrom) { continue; }
            $r['segment_from'] = date('Y-m-d', $sFrom); $r['segment_to'] = date('Y-m-d', $sTo);
            $r['calendar_days'] = PulseHrService::calendarDays($r['segment_from'], $r['segment_to']);
            $r['working_days'] = PulseHrService::workingDays($r['segment_from'], $r['segment_to']);
            $r['period_days'] = PulseHrService::calendarDays($f, $t);
            $r['proration'] = $r['period_days'] ? round($r['calendar_days'] / $r['period_days'], 6) : 0;
            $out[] = $r;
        }
        return $out;
    }

    /** Monthly-equivalent value of a pay basis — for headline figures and grade-band checks only, never for payroll. */
    public static function monthlyEquivalent(array $c)
    {
        $rate = (float) $c['pay_rate'];
        $weeks = 52 / 12;
        if ($c['pay_basis'] === 'monthly') { return round($rate, 2); }
        if ($c['pay_basis'] === 'hourly') { return round($rate * (float) $c['hours_per_week'] * $weeks, 2); }
        return round($rate * (float) $c['days_per_week'] * $weeks, 2); // daily and per-shift both count engagements per week
    }

    /**
     * Write a new version. The previous live version is closed the day before effective_from; an existing
     * version with the same effective_from is replaced (a correction on the same day is not a new history entry).
     */
    public static function save(array $d)
    {
        $idEmployee = (int) $d['id_pulse_hr_employee'];
        if (!$idEmployee || !PulseHrEmployee::get($idEmployee)) { throw new PrestaShopException('Employee not found'); }
        $from = date('Y-m-d', strtotime(!empty($d['effective_from']) ? $d['effective_from'] : 'now'));
        $row = array(
            'id_pulse_hr_employee' => $idEmployee, 'type' => pSQL(isset($d['type']) ? $d['type'] : 'permanent'),
            'id_pulse_hr_position' => !empty($d['id_pulse_hr_position']) ? (int) $d['id_pulse_hr_position'] : null,
            'id_pulse_hr_department' => !empty($d['id_pulse_hr_department']) ? (int) $d['id_pulse_hr_department'] : null,
            'id_pulse_hr_section' => !empty($d['id_pulse_hr_section']) ? (int) $d['id_pulse_hr_section'] : null,
            'id_pulse_hr_grade' => !empty($d['id_pulse_hr_grade']) ? (int) $d['id_pulse_hr_grade'] : null,
            'id_manager' => !empty($d['id_manager']) ? (int) $d['id_manager'] : null, 'cost_centre' => pSQL(isset($d['cost_centre']) ? $d['cost_centre'] : ''),
            'effective_from' => pSQL($from), 'effective_to' => !empty($d['effective_to']) ? pSQL(date('Y-m-d', strtotime($d['effective_to']))) : null,
            'start_date' => !empty($d['start_date']) ? pSQL(date('Y-m-d', strtotime($d['start_date']))) : null,
            'end_date' => !empty($d['end_date']) ? pSQL(date('Y-m-d', strtotime($d['end_date']))) : null,
            'pay_basis' => pSQL(isset($d['pay_basis']) ? $d['pay_basis'] : 'monthly'), 'pay_rate' => round((float) (isset($d['pay_rate']) ? $d['pay_rate'] : 0), 2),
            'currency' => pSQL(Tools::strtoupper(isset($d['currency']) && $d['currency'] ? $d['currency'] : 'NGN')),
            'hours_per_week' => (float) (isset($d['hours_per_week']) && $d['hours_per_week'] !== '' ? $d['hours_per_week'] : 48),
            'days_per_week' => (float) (isset($d['days_per_week']) && $d['days_per_week'] !== '' ? $d['days_per_week'] : 6),
            'working_pattern' => pSQL(isset($d['working_pattern']) ? $d['working_pattern'] : ''), 'night_shift' => !empty($d['night_shift']) ? 1 : 0,
            'notice_days' => (int) (isset($d['notice_days']) ? $d['notice_days'] : 30), 'probation_months' => (int) (isset($d['probation_months']) ? $d['probation_months'] : 0),
            'reason' => pSQL(isset($d['reason']) ? $d['reason'] : 'hire'), 'status' => pSQL(isset($d['status']) && $d['status'] === 'draft' ? 'draft' : 'active'),
            'note' => pSQL(isset($d['note']) ? $d['note'] : ''), 'id_employee_created' => PulseHrService::emp(), 'date_upd' => date('Y-m-d H:i:s'),
        );
        if ($row['pay_rate'] < 0) { throw new PrestaShopException('A pay rate cannot be negative'); }
        if ($row['end_date'] && $row['end_date'] < $row['effective_from']) { throw new PrestaShopException('The contract end date is before the version takes effect'); }
        if (in_array($row['type'], array('fixed_term', 'contract', 'intern')) && !$row['end_date']) { throw new PrestaShopException('A '.str_replace('_', ' ', $row['type']).' contract needs an end date'); }
        if ($row['probation_months'] > 0) { $row['probation_end'] = pSQL(date('Y-m-d', strtotime(($row['start_date'] ? $row['start_date'] : $from).' +'.$row['probation_months'].' month'))); }
        if ($row['id_pulse_hr_grade'] && $row['pay_basis'] === 'monthly' && $row['pay_rate'] > 0) {
            $g = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_grade` WHERE id_pulse_hr_grade='.(int) $row['id_pulse_hr_grade']);
            if ($g && (float) $g['salary_max'] > 0 && ($row['pay_rate'] < (float) $g['salary_min'] || $row['pay_rate'] > (float) $g['salary_max'])) {
                PulseCoreService::audit('pulsehr', 'contract_out_of_band', array('grade' => $g['code'], 'rate' => $row['pay_rate'], 'band' => array($g['salary_min'], $g['salary_max'])), self::T, 0);
            }
        }
        $same = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_hr_employee='.$idEmployee.' AND effective_from="'.pSQL($from).'"');
        if ($same) {
            $row['contract_no'] = $same['contract_no'];
            Db::getInstance()->update(self::T, $row, 'id_pulse_hr_contract='.(int) $same['id_pulse_hr_contract'], 0, true);
            $id = (int) $same['id_pulse_hr_contract'];
        } else {
            $row['contract_no'] = PulseHrService::nextNo('C'); $row['date_add'] = date('Y-m-d H:i:s');
            Db::getInstance()->insert(self::T, $row, true);
            $id = (int) Db::getInstance()->Insert_ID();
        }
        self::reseal($idEmployee);
        PulseHrEmployee::syncFromContract($idEmployee);
        PulseCoreService::audit('pulsehr', 'contract_version', array('id_employee' => $idEmployee, 'effective_from' => $from, 'reason' => $row['reason'],
            'pay_basis' => $row['pay_basis'], 'pay_rate' => $row['pay_rate'], 'contract_no' => $row['contract_no']), self::T, $id);
        return $id;
    }

    /**
     * Re-close the history so the versions tile without gaps or overlaps: each row runs to the day before the
     * next one starts, only the last row stays open, and anything superseded says so.
     */
    public static function reseal($idEmployee)
    {
        $rows = Db::getInstance()->executeS('SELECT id_pulse_hr_contract, effective_from, effective_to, status FROM `'._DB_PREFIX_.self::T.'`
            WHERE id_pulse_hr_employee='.(int) $idEmployee.' AND status<>"draft" ORDER BY effective_from, id_pulse_hr_contract');
        $n = count($rows);
        foreach ($rows as $i => $r) {
            $isLast = ($i === $n - 1);
            $to = $isLast ? null : date('Y-m-d', strtotime($rows[$i + 1]['effective_from'].' -1 day'));
            $status = $isLast ? ($r['status'] === 'ended' ? 'ended' : 'active') : 'superseded';
            if ($isLast && $r['effective_to']) { $to = $r['effective_to']; $status = 'ended'; }
            if ($r['effective_to'] === $to && $r['status'] === $status) { continue; }
            Db::getInstance()->update(self::T, array('effective_to' => $to ? pSQL($to) : null, 'status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_contract='.(int) $r['id_pulse_hr_contract'], 0, true);
        }
        return true;
    }

    /** Close every open version on exit. The history stays; it simply stops being in force. */
    public static function endAll($idEmployee, $date, $reason = 'exit')
    {
        $d = pSQL(date('Y-m-d', strtotime($date)));
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET effective_to="'.$d.'", status="ended", note=CONCAT(COALESCE(note,""), " · closed on '.$d.' ('.pSQL($reason).')"), date_upd=NOW()
            WHERE id_pulse_hr_employee='.(int) $idEmployee.' AND (effective_to IS NULL OR effective_to>"'.$d.'") AND status<>"draft"');
        return true;
    }

    /**
     * Remove a version. Only the newest one may go — deleting a middle version would silently rewrite what a
     * past payroll was calculated on. The version before it reopens.
     */
    public static function removeVersion($id)
    {
        $c = self::get($id);
        if (!$c) { throw new PrestaShopException('Contract version not found'); }
        $latest = (int) Db::getInstance()->getValue('SELECT id_pulse_hr_contract FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_hr_employee='.(int) $c['id_pulse_hr_employee'].' ORDER BY effective_from DESC, id_pulse_hr_contract DESC');
        if ($latest !== (int) $id) { throw new PrestaShopException('Only the most recent contract version can be removed — an earlier one is what a past payroll was calculated on'); }
        if ((int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_hr_employee='.(int) $c['id_pulse_hr_employee']) < 2) { throw new PrestaShopException('An employee must keep at least one contract version'); }
        Db::getInstance()->delete(self::T, 'id_pulse_hr_contract='.(int) $id);
        self::reseal((int) $c['id_pulse_hr_employee']);
        PulseHrEmployee::syncFromContract((int) $c['id_pulse_hr_employee']);
        PulseCoreService::audit('pulsehr', 'contract_version_removed', array('contract_no' => $c['contract_no'], 'effective_from' => $c['effective_from']), self::T, (int) $id);
        return true;
    }

    /** Fixed-term contracts running out — the list HR has to act on before somebody works without one. */
    public static function expiring($days = 30)
    {
        return Db::getInstance()->executeS(self::select().' WHERE c.status="active" AND c.end_date IS NOT NULL
            AND c.end_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL '.(int) $days.' DAY) AND e.status<>"exited" ORDER BY c.end_date');
    }

    /** Everyone with a contract in force on a date — payroll's starting population for a period. */
    public static function activeOn($date, $dept = null)
    {
        $d = pSQL(date('Y-m-d', strtotime($date)));
        return Db::getInstance()->executeS(self::select().' WHERE c.status<>"draft" AND c.effective_from<="'.$d.'" AND (c.effective_to IS NULL OR c.effective_to>="'.$d.'")'
            .($dept ? ' AND d.code="'.pSQL($dept).'"' : '').' ORDER BY d.sort, e.lastname');
    }
}
