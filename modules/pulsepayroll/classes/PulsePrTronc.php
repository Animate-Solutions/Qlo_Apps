<?php
/**
 * Service charge / tronc.
 *
 * The pool is the 10% service charge the property actually collected — read from POS checks and folio
 * lines when those modules are present, keyed in by hand when they are not — less an agreed
 * administration percentage and less any breakage the property retains. What is left is distributed by
 * points or by hours worked, weighted by department, with a hard cap on the management share.
 *
 * The payroll treatment matters and is the reason this cannot be a spreadsheet: a tronc share is taxable
 * pay, but it is not part of basic + housing + transport, so it never enters the pension base. That is
 * carried by the TRONC element's flags (taxable=1, pensionable=0) rather than by anything in here.
 */
class PulsePrTronc
{
    public static function pools($limit = 60)
    {
        return Db::getInstance()->executeS('SELECT p.*, CONCAT(e.firstname," ",e.lastname) approver FROM `'._DB_PREFIX_.'pulse_pr_tronc_pool` p LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=p.approved_by ORDER BY p.period DESC, p.id_pulse_pr_tronc_pool DESC LIMIT '.(int) $limit);
    }

    public static function pool($id)
    {
        $p = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_tronc_pool` WHERE id_pulse_pr_tronc_pool='.(int) $id);
        if ($p) { $p['lines'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_tronc_line` WHERE id_pulse_pr_tronc_pool='.(int) $id.' ORDER BY department, employee_name'); }
        return $p;
    }

    public static function weights()
    {
        $out = array();
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_tronc_weight` WHERE active=1 ORDER BY department') as $w) { $out[$w['department']] = $w; }
        return $out;
    }

    public static function saveWeight(array $d)
    {
        if (empty($d['department'])) { throw new PrestaShopException('A weighting needs a department'); }
        $row = array('department' => pSQL(Tools::substr($d['department'], 0, 32)), 'weight' => (float) (isset($d['weight']) ? $d['weight'] : 1),
            'default_points' => (float) (isset($d['default_points']) ? $d['default_points'] : 10), 'is_management' => !empty($d['is_management']) ? 1 : 0,
            'active' => isset($d['active']) ? (int) (bool) $d['active'] : 1);
        $ex = (int) Db::getInstance()->getValue('SELECT id_pulse_pr_tronc_weight FROM `'._DB_PREFIX_.'pulse_pr_tronc_weight` WHERE department="'.$row['department'].'"');
        if ($ex) { Db::getInstance()->update('pulse_pr_tronc_weight', $row, 'id_pulse_pr_tronc_weight='.$ex, 0, true); return $ex; }
        Db::getInstance()->insert('pulse_pr_tronc_weight', $row, true);
        return (int) Db::getInstance()->Insert_ID();
    }

    /**
     * What the property collected in service charge over a period. POS checks carry the figure explicitly;
     * rooms service charge is read from the folio lines whose charge code is flagged as service charge.
     * Both are best-effort: a property with neither module keys the pool in on the screen.
     */
    public static function collected($from, $to)
    {
        $fnb = 0; $rooms = 0;
        if (PulsePrService::pos()) {
            $fnb = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(service_charge),0) FROM `'._DB_PREFIX_.'pulse_pos_check` WHERE status="settled" AND business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'), 2);
        }
        if (PulsePrService::fd() && PulsePrService::tableExists('pulse_folio_line')) {
            $pct = (float) PulsePrService::cfg('TRONC_PCT', 10);
            $rooms = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.amount_tax_incl),0) FROM `'._DB_PREFIX_.'pulse_folio_line` l INNER JOIN `'._DB_PREFIX_.'pulse_charge_code` c ON c.id_pulse_charge_code=l.id_pulse_charge_code WHERE l.voided=0 AND l.is_payment=0 AND c.code="SVC" AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'), 2);
            if ($rooms <= 0 && $pct > 0) {
                // no explicit service-charge code in use: derive it from room revenue at the configured rate
                $roomRev = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.amount_tax_incl),0) FROM `'._DB_PREFIX_.'pulse_folio_line` l INNER JOIN `'._DB_PREFIX_.'pulse_charge_code` c ON c.id_pulse_charge_code=l.id_pulse_charge_code WHERE l.voided=0 AND l.is_payment=0 AND c.code="ROOM" AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'), 2);
                $rooms = round($roomRev * $pct / (100 + $pct), 2);
            }
        }
        return array('fnb' => $fnb, 'rooms' => $rooms, 'total' => round($fnb + $rooms, 2));
    }

    /** Open a pool for a period, seeded with whatever the operational modules can tell us. */
    public static function createPool(array $d)
    {
        $period = Tools::substr((string) (isset($d['period']) ? $d['period'] : date('Y-m')), 0, 7);
        if (!preg_match('/^[0-9]{4}-[0-9]{2}$/', $period)) { throw new PrestaShopException('The period must look like 2026-08'); }
        $from = !empty($d['period_from']) ? $d['period_from'] : PulsePrService::periodFrom($period);
        $to = !empty($d['period_to']) ? $d['period_to'] : PulsePrService::periodTo($period);
        $c = self::collected($from, $to);
        $manual = round((float) (isset($d['collected_manual']) ? $d['collected_manual'] : 0), 2);
        $row = array(
            'pool_no' => pSQL(PulsePrService::nextNo('SC', 5)), 'period' => pSQL($period), 'period_from' => pSQL($from), 'period_to' => pSQL($to),
            'basis' => pSQL(in_array(isset($d['basis']) ? $d['basis'] : '', array('points', 'hours', 'equal')) ? $d['basis'] : PulsePrService::cfg('TRONC_BASIS', 'points')),
            'collected_fnb' => $c['fnb'], 'collected_rooms' => $c['rooms'], 'collected_manual' => $manual,
            'gross_pool' => round($c['total'] + $manual, 2),
            'admin_pct' => (float) (isset($d['admin_pct']) ? $d['admin_pct'] : PulsePrService::cfg('TRONC_ADMIN_PCT', 0)),
            'breakage_amount' => round((float) (isset($d['breakage_amount']) ? $d['breakage_amount'] : 0), 2),
            'management_cap_pct' => (float) (isset($d['management_cap_pct']) ? $d['management_cap_pct'] : PulsePrService::cfg('TRONC_MGMT_CAP_PCT', 10)),
            'status' => 'draft', 'source_note' => pSQL(Tools::substr('F&B '.number_format($c['fnb'], 2).', rooms '.number_format($c['rooms'], 2).', keyed in '.number_format($manual, 2), 0, 255)),
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 255)), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        );
        Db::getInstance()->insert('pulse_pr_tronc_pool', $row, true);
        $id = (int) Db::getInstance()->Insert_ID();
        PulsePrService::log(null, 'tronc_create', 'tronc', $row, $id);
        return $id;
    }

    public static function updatePool($id, array $d)
    {
        $p = self::pool($id);
        if (!$p) { throw new PrestaShopException('Unknown pool'); }
        if (in_array($p['status'], array('approved', 'paid'))) { throw new PrestaShopException('Pool '.$p['pool_no'].' is '.$p['status'].' and can no longer be edited'); }
        $upd = array('date_upd' => date('Y-m-d H:i:s'));
        foreach (array('collected_fnb', 'collected_rooms', 'collected_manual', 'admin_pct', 'breakage_amount', 'management_cap_pct') as $k) { if (isset($d[$k])) { $upd[$k] = round((float) $d[$k], 3); } }
        if (isset($d['basis']) && in_array($d['basis'], array('points', 'hours', 'equal'))) { $upd['basis'] = pSQL($d['basis']); }
        if (isset($d['note'])) { $upd['note'] = pSQL(Tools::substr($d['note'], 0, 255)); }
        $upd['gross_pool'] = round((isset($upd['collected_fnb']) ? $upd['collected_fnb'] : (float) $p['collected_fnb']) + (isset($upd['collected_rooms']) ? $upd['collected_rooms'] : (float) $p['collected_rooms']) + (isset($upd['collected_manual']) ? $upd['collected_manual'] : (float) $p['collected_manual']), 2);
        Db::getInstance()->update('pulse_pr_tronc_pool', $upd, 'id_pulse_pr_tronc_pool='.(int) $id, 0, true);
        return true;
    }

    /**
     * Distribute the pool.
     *
     * points  — every participant carries points (grade or department default, overridable per person),
     *           multiplied by the department weighting;
     * hours   — the same, but the unit is hours actually worked from the approved timesheet;
     * equal   — one share each, still weighted by department.
     *
     * The management cap is applied after the raw shares are computed: if the people flagged as management
     * would take more than the cap, their shares are scaled back and the difference is redistributed among
     * everyone else in proportion to their own units. Rounding crumbs land on the largest share so the
     * distributed total equals the distributable pool exactly.
     *
     * @param array $participants  [[id_pulse_pr_employee, points, hours, exclude], ...] — empty means everybody
     */
    public static function distribute($id, array $participants = array())
    {
        $p = self::pool($id);
        if (!$p) { throw new PrestaShopException('Unknown pool'); }
        if (in_array($p['status'], array('approved', 'paid'))) { throw new PrestaShopException('Pool '.$p['pool_no'].' is '.$p['status'].' and can no longer be redistributed'); }
        $gross = round((float) $p['gross_pool'], 2);
        $admin = round($gross * (float) $p['admin_pct'] / 100, 2);
        $distributable = round($gross - $admin - (float) $p['breakage_amount'], 2);
        if ($distributable <= 0) { throw new PrestaShopException('There is nothing to distribute once the administration share and breakage are taken off'); }

        $weights = self::weights();
        $override = array();
        foreach ($participants as $x) { if (!empty($x['id_pulse_pr_employee'])) { $override[(int) $x['id_pulse_pr_employee']] = $x; } }
        $people = PulsePrService::employees(array('status' => 'active,probation,on_leave'));
        $rows = array(); $totalUnits = 0; $mgmtUnits = 0;
        foreach ($people as $e) {
            $id_e = (int) $e['id_pulse_pr_employee'];
            $o = isset($override[$id_e]) ? $override[$id_e] : null;
            if ($o && !empty($o['exclude'])) { continue; }
            if ($participants && !$o) { continue; }
            $w = isset($weights[$e['department']]) ? $weights[$e['department']] : array('weight' => 1, 'default_points' => 10, 'is_management' => 0);
            $isMgmt = (int) $w['is_management'] || Tools::strtolower((string) $e['grade']) === 'management';
            $points = $o && isset($o['points']) && $o['points'] !== '' ? (float) $o['points'] : (float) $w['default_points'];
            $hours = $o && isset($o['hours']) && $o['hours'] !== '' ? (float) $o['hours'] : self::hoursFor($e, $p['period']);
            $unit = $p['basis'] === 'hours' ? $hours : ($p['basis'] === 'equal' ? 1 : $points);
            $weighted = round($unit * (float) $w['weight'], 4);
            if ($weighted <= 0) { continue; }
            $rows[] = array('id_pulse_pr_employee' => $id_e, 'staff_no' => $e['staff_no'], 'employee_name' => trim($e['firstname'].' '.$e['lastname']),
                'department' => $e['department'], 'is_management' => $isMgmt ? 1 : 0, 'points' => $points, 'hours' => $hours,
                'dept_weight' => (float) $w['weight'], 'weighted_units' => $weighted);
            $totalUnits = round($totalUnits + $weighted, 4);
            if ($isMgmt) { $mgmtUnits = round($mgmtUnits + $weighted, 4); }
        }
        if (!$rows || $totalUnits <= 0) { throw new PrestaShopException('Nobody qualifies for a share — check the department weightings and the participant list'); }

        /* raw shares, then the management cap */
        $capPct = (float) $p['management_cap_pct'];
        $mgmtRaw = round($distributable * $mgmtUnits / $totalUnits, 2);
        $mgmtCap = round($distributable * $capPct / 100, 2);
        $capped = $capPct > 0 && $mgmtRaw > $mgmtCap;
        $mgmtPool = $capped ? $mgmtCap : $mgmtRaw;
        $staffPool = round($distributable - $mgmtPool, 2);
        $staffUnits = round($totalUnits - $mgmtUnits, 4);
        $cappedAmount = $capped ? round($mgmtRaw - $mgmtCap, 2) : 0.0;
        if ($staffUnits <= 0 && $staffPool > 0) { $mgmtPool = $distributable; $staffPool = 0; $cappedAmount = 0; $capped = false; }

        $allocated = 0; $largest = 0;
        foreach ($rows as $i => $r) {
            $poolFor = $r['is_management'] ? $mgmtPool : $staffPool;
            $unitsFor = $r['is_management'] ? $mgmtUnits : $staffUnits;
            $amount = $unitsFor > 0 ? round($poolFor * $r['weighted_units'] / $unitsFor, 2) : 0.0;
            $rows[$i]['amount'] = $amount;
            $rows[$i]['share_pct'] = $distributable > 0 ? round($amount / $distributable * 100, 5) : 0;
            $rows[$i]['capped'] = $r['is_management'] && $capped ? 1 : 0;
            $allocated = round($allocated + $amount, 2);
            if ($amount > $rows[$largest]['amount']) { $largest = $i; }
        }
        $residue = round($distributable - $allocated, 2);
        if (abs($residue) >= 0.005) { $rows[$largest]['amount'] = round($rows[$largest]['amount'] + $residue, 2); $rows[$largest]['note'] = 'Carries the '.number_format($residue, 2).' rounding residue'; }

        Db::getInstance()->delete('pulse_pr_tronc_line', 'id_pulse_pr_tronc_pool='.(int) $id);
        $distributed = 0;
        foreach ($rows as $r) {
            $r['id_pulse_pr_tronc_pool'] = (int) $id;
            foreach ($r as $k => $v) { if (is_string($v)) { $r[$k] = pSQL($v); } }
            Db::getInstance()->insert('pulse_pr_tronc_line', $r, true);
            $distributed = round($distributed + (float) $r['amount'], 2);
        }
        Db::getInstance()->update('pulse_pr_tronc_pool', array(
            'admin_amount' => $admin, 'distributable' => $distributable, 'distributed' => $distributed, 'participants' => count($rows),
            'management_capped_amount' => $cappedAmount, 'rounding_residue' => $residue, 'status' => 'distributed', 'date_upd' => date('Y-m-d H:i:s'),
        ), 'id_pulse_pr_tronc_pool='.(int) $id);
        PulsePrService::log(null, 'tronc_distribute', 'tronc', array('pool' => $p['pool_no'], 'distributable' => $distributable, 'distributed' => $distributed, 'participants' => count($rows), 'management_capped' => $cappedAmount), (int) $id);
        PulseCoreService::event('actionPulsePayrollTroncDistributed', array('id_pool' => (int) $id, 'period' => $p['period'], 'amount' => $distributed, 'participants' => count($rows)));
        return array('distributable' => $distributable, 'distributed' => $distributed, 'participants' => count($rows), 'capped' => $cappedAmount, 'residue' => $residue);
    }

    /** Hours worked in the period, from the approved timesheet; falls back to the standard month. */
    protected static function hoursFor(array $e, $period)
    {
        $ts = PulsePrService::timesheet((int) $e['id_pulse_pr_employee'], $period, $e['id_hr_employee']);
        if ($ts && (float) $ts['hours_worked'] > 0) { return (float) $ts['hours_worked']; }
        return (float) PulsePrService::cfg('MONTH_HOURS', 208);
    }

    public static function approve($id)
    {
        $p = self::pool($id);
        if (!$p) { throw new PrestaShopException('Unknown pool'); }
        if ($p['status'] !== 'distributed') { throw new PrestaShopException('Pool '.$p['pool_no'].' is '.$p['status'].' — distribute it first'); }
        $sum = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(amount),0) FROM `'._DB_PREFIX_.'pulse_pr_tronc_line` WHERE id_pulse_pr_tronc_pool='.(int) $id), 2);
        if (abs($sum - (float) $p['distributable']) > 0.009) { throw new PrestaShopException('The shares total '.number_format($sum, 2).' against a distributable pool of '.number_format((float) $p['distributable'], 2).' — redistribute before approving'); }
        Db::getInstance()->update('pulse_pr_tronc_pool', array('status' => 'approved', 'approved_by' => PulsePrService::emp(), 'date_approved' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_tronc_pool='.(int) $id);
        PulsePrService::log(null, 'tronc_approve', 'tronc', array('pool' => $p['pool_no'], 'total' => $sum), (int) $id);
        return true;
    }

    /** Per-employee statement — what a waiter actually asks for at the end of the month. */
    public static function statement($idEmployee, $from = null, $to = null)
    {
        $from = $from ? $from : date('Y-01'); $to = $to ? $to : date('Y-m');
        return Db::getInstance()->executeS('SELECT p.period, p.pool_no, p.basis, p.gross_pool, p.distributable, p.participants, l.points, l.hours, l.dept_weight, l.weighted_units, l.share_pct, l.amount, l.capped, p.status
            FROM `'._DB_PREFIX_.'pulse_pr_tronc_line` l INNER JOIN `'._DB_PREFIX_.'pulse_pr_tronc_pool` p ON p.id_pulse_pr_tronc_pool=l.id_pulse_pr_tronc_pool
            WHERE l.id_pulse_pr_employee='.(int) $idEmployee.' AND p.period BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND p.status<>"cancelled" ORDER BY p.period DESC');
    }

    /** Mark the shares as paid through a payroll run so they are not distributed twice. */
    public static function markPaidInRun($idPool, $idRun)
    {
        Db::getInstance()->update('pulse_pr_tronc_line', array('paid_in_run' => (int) $idRun), 'id_pulse_pr_tronc_pool='.(int) $idPool);
        Db::getInstance()->update('pulse_pr_tronc_pool', array('status' => 'paid', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_pr_tronc_pool='.(int) $idPool);
        return true;
    }
}
