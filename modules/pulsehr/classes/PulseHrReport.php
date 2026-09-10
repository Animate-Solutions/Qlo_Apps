<?php
/**
 * HR reports. Every one returns a flat array ready for the template or for toCsv(), and every one degrades
 * politely when the module it would rather read from is not installed.
 */
class PulseHrReport
{
    /** Headcount and establishment by department, with the vacancies and the over-establishment both visible. */
    public static function headcount()
    {
        $rows = Db::getInstance()->executeS('SELECT d.code, d.name department,
                SUM(e.status<>"exited") headcount, SUM(e.status="probation") probation, SUM(e.status="active") confirmed,
                SUM(e.status="on_leave") on_leave, SUM(e.status="suspended") suspended, SUM(e.gender="f" AND e.status<>"exited") female,
                (SELECT COALESCE(SUM(p.establishment),0) FROM `'._DB_PREFIX_.'pulse_hr_position` p WHERE p.id_pulse_hr_department=d.id_pulse_hr_department AND p.active=1) establishment
            FROM `'._DB_PREFIX_.'pulse_hr_department` d
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_department=d.id_pulse_hr_department
            WHERE d.active=1 GROUP BY d.id_pulse_hr_department ORDER BY d.sort');
        foreach ($rows as &$r) {
            $r['headcount'] = (int) $r['headcount']; $r['establishment'] = (int) $r['establishment'];
            $r['vacancies'] = max(0, $r['establishment'] - $r['headcount']);
            $r['over'] = max(0, $r['headcount'] - $r['establishment']);
            $r['fill_pct'] = $r['establishment'] ? round($r['headcount'] / $r['establishment'] * 100, 1) : 0;
        }
        unset($r);
        return $rows;
    }

    /** Joiners, leavers and the turnover rate on the average headcount for the window. */
    public static function turnover($from, $to)
    {
        $db = Db::getInstance();
        $rows = $db->executeS('SELECT d.code, d.name department,
                SUM(e.hire_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'") joiners,
                SUM(e.exit_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'") leavers,
                SUM(e.exit_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND e.exit_type="resignation") resignations,
                SUM(e.exit_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND e.exit_type IN ("termination","abscondment")) involuntary,
                SUM(e.status<>"exited") headcount_now
            FROM `'._DB_PREFIX_.'pulse_hr_department` d
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_department=d.id_pulse_hr_department
            WHERE d.active=1 GROUP BY d.id_pulse_hr_department ORDER BY d.sort');
        foreach ($rows as &$r) {
            $start = (int) $r['headcount_now'] - (int) $r['joiners'] + (int) $r['leavers'];
            $avg = max(1, ((int) $r['headcount_now'] + $start) / 2);
            $r['turnover_pct'] = round((int) $r['leavers'] / $avg * 100, 1);
            $r['avg_headcount'] = round($avg, 1);
        }
        unset($r);
        return $rows;
    }

    /** Leavers in the window with how long they lasted — the "we keep losing room attendants at four months" report. */
    public static function leavers($from, $to)
    {
        return Db::getInstance()->executeS('SELECT e.staff_no, CONCAT(e.firstname," ",e.lastname) employee_name, d.name department, p.title position,
                e.hire_date, e.exit_date, e.exit_type, e.exit_reason, e.rehire_eligible, DATEDIFF(e.exit_date, e.hire_date) days_served
            FROM `'._DB_PREFIX_.'pulse_hr_employee` e
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_position` p ON p.id_pulse_hr_position=e.id_pulse_hr_position
            WHERE e.exit_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" ORDER BY e.exit_date DESC');
    }

    /** Absence: leave days taken by type and department, plus the unexplained gaps the roster shows. */
    public static function absence($from, $to)
    {
        $rows = Db::getInstance()->executeS('SELECT d.name department, t.code type_code, t.name type_name, t.paid,
                COUNT(*) requests, ROUND(SUM(r.days),2) days
            FROM `'._DB_PREFIX_.'pulse_hr_leave_request` r
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=r.id_pulse_hr_employee
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_leave_type` t ON t.id_pulse_hr_leave_type=r.id_pulse_hr_leave_type
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE r.status IN ("approved","taken") AND r.date_from<="'.pSQL($to).'" AND r.date_to>="'.pSQL($from).'"
            GROUP BY d.id_pulse_hr_department, t.id_pulse_hr_leave_type ORDER BY d.name, t.sort');
        $rostered = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_roster` WHERE roster_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND is_off=0 AND status IN ("planned","published")');
        $days = 0;
        foreach ($rows as $r) { $days += (float) $r['days']; }
        return array('rows' => $rows, 'total_days' => round($days, 2), 'rostered_shifts' => $rostered,
            'absence_pct' => $rostered ? round($days / max(1, $rostered + $days) * 100, 1) : 0);
    }

    /**
     * Labour hours per occupied room — the hospitality productivity number. Hours come from Pulse Time when it
     * is installed, otherwise from accepted mobile punches, otherwise from the published roster; the row says
     * which, because a rostered hour and a worked hour are not the same claim.
     */
    public static function labourPerOccupiedRoom($from, $to, $dept = null)
    {
        $out = array(); $totalHours = 0; $totalRooms = 0; $source = 'roster';
        for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
            $day = date('Y-m-d', $d);
            $hours = 0;
            if (PulseHrService::ta() && PulseHrService::tableExists('pulse_ta_timesheet')) {
                $hours = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(worked_minutes),0)/60 FROM `'._DB_PREFIX_.'pulse_ta_timesheet` WHERE business_date="'.pSQL($day).'"'.($dept ? ' AND department="'.pSQL($dept).'"' : ''));
                if ($hours > 0) { $source = 'timesheets'; }
            }
            if ($hours <= 0) {
                $hours = PulseHrEss::workedHours($day, $day);
                if ($hours > 0) { $source = 'mobile punches'; }
            }
            if ($hours <= 0) { $hours = PulseHrRoster::hours($day, $day, $dept); }
            $occ = PulseHrService::roomsOccupied($day);
            $totalHours += $hours; $totalRooms += (int) $occ['occupied'];
            $out[] = array('date' => $day, 'hours' => round($hours, 2), 'occupied' => (int) $occ['occupied'],
                'hours_per_room' => $occ['occupied'] ? round($hours / $occ['occupied'], 2) : 0, 'occupancy_pct' => $occ['total'] ? round($occ['occupied'] / max(1, $occ['total'] - $occ['ooo']) * 100, 1) : 0);
        }
        return array('rows' => $out, 'source' => $source, 'total_hours' => round($totalHours, 2), 'room_nights' => $totalRooms,
            'hours_per_room' => $totalRooms ? round($totalHours / $totalRooms, 2) : 0);
    }

    /** Everything about to lapse, in one list: documents, contracts, training and probation. */
    public static function expiryDashboard($days = 45)
    {
        $out = array();
        foreach (PulseHrDocument::expiring($days) as $d) { $out[] = array('kind' => 'Document', 'what' => $d['name'], 'who' => $d['employee_name'], 'staff_no' => $d['staff_no'], 'department' => $d['dept_name'], 'date' => $d['expires_on'], 'days_left' => (int) $d['days_left']); }
        foreach (PulseHrContract::expiring($days) as $c) { $out[] = array('kind' => 'Contract', 'what' => ucfirst(str_replace('_', ' ', $c['type'])).' '.$c['contract_no'], 'who' => $c['employee_name'], 'staff_no' => $c['staff_no'], 'department' => $c['dept_name'], 'date' => $c['end_date'], 'days_left' => (int) floor((strtotime($c['end_date']) - strtotime(date('Y-m-d'))) / 86400)); }
        foreach (PulseHrPerformance::trainingExpiring($days) as $t) { $out[] = array('kind' => 'Training', 'what' => $t['course'], 'who' => $t['employee_name'], 'staff_no' => $t['staff_no'], 'department' => $t['dept_name'], 'date' => $t['expires_on'], 'days_left' => (int) $t['days_left']); }
        foreach (PulseHrEmployee::probationDue($days) as $e) { $out[] = array('kind' => 'Probation', 'what' => 'Probation ends', 'who' => $e['full_name'], 'staff_no' => $e['staff_no'], 'department' => $e['dept_name'], 'date' => $e['probation_end'], 'days_left' => (int) floor((strtotime($e['probation_end']) - strtotime(date('Y-m-d'))) / 86400)); }
        usort($out, array('PulseHrReport', 'byDaysLeft'));
        return $out;
    }
    public static function byDaysLeft($a, $b) { return $a['days_left'] === $b['days_left'] ? 0 : ($a['days_left'] < $b['days_left'] ? -1 : 1); }

    /** Payroll-facing: the contract in force on a date for everyone, with its monthly equivalent. */
    public static function payBasisOn($date, $dept = null)
    {
        $rows = PulseHrContract::activeOn($date, $dept);
        foreach ($rows as &$r) { $r['monthly_equivalent'] = PulseHrContract::monthlyEquivalent($r); }
        unset($r);
        return $rows;
    }

    /** Length of service bands — who is new, who is staying. */
    public static function service()
    {
        $bands = array('Under 6 months' => 0, '6–12 months' => 0, '1–3 years' => 0, '3–5 years' => 0, 'Over 5 years' => 0);
        foreach (Db::getInstance()->executeS('SELECT hire_date FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE status<>"exited" AND hire_date IS NOT NULL') as $e) {
            $m = (int) floor((time() - strtotime($e['hire_date'])) / 2629800);
            if ($m < 6) { $bands['Under 6 months']++; } elseif ($m < 12) { $bands['6–12 months']++; } elseif ($m < 36) { $bands['1–3 years']++; } elseif ($m < 60) { $bands['3–5 years']++; } else { $bands['Over 5 years']++; }
        }
        $out = array();
        foreach ($bands as $k => $v) { $out[] = array('band' => $k, 'headcount' => $v); }
        return $out;
    }

    public static function toCsv(array $rows)
    {
        if (!$rows) { return ''; }
        $f = fopen('php://temp', 'r+');
        fputcsv($f, array_keys($rows[0]));
        foreach ($rows as $r) { fputcsv($f, array_map(array('PulseHrReport', 'flat'), $r)); }
        rewind($f); $csv = stream_get_contents($f); fclose($f);
        return $csv;
    }
    public static function flat($v) { return is_array($v) ? implode(' | ', $v) : $v; }
}
