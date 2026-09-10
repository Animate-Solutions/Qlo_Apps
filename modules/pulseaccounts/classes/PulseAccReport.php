<?php
/**
 * Reporting: trial balance, general-ledger drill-down, USALI departmental P&L with GOP and EBITDA,
 * balance sheet, indirect cash flow, budget vs actual, revenue analysis and the daily revenue journal
 * that ties the night audit to the ledger.
 *
 * Every figure comes from an indexed SUM over pulse_acc_journal_line (which carries its own
 * business_date, period, usali_dept and posted flag) — no per-account loop, no N+1 query, so the
 * month-end reports still open on a shared host.
 */
class PulseAccReport
{
    /* ---------------- trial balance ---------------- */

    public static function trialBalance($from, $to, $includeZero = false)
    {
        $rows = Db::getInstance()->executeS('SELECT a.code, a.name, a.type, a.subtype, a.usali_dept, a.normal_balance,
            ROUND(COALESCE(ob.d,0)-COALESCE(ob.c,0),2) opening,
            ROUND(COALESCE(SUM(l.debit),0),2) debit, ROUND(COALESCE(SUM(l.credit),0),2) credit
            FROM `'._DB_PREFIX_.'pulse_acc_account` a
            LEFT JOIN `'._DB_PREFIX_.'pulse_acc_journal_line` l ON l.id_pulse_acc_account=a.id_pulse_acc_account AND l.posted=1 AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"
            LEFT JOIN (SELECT id_pulse_acc_account, SUM(debit) d, SUM(credit) c FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE posted=1 AND business_date<"'.pSQL($from).'" GROUP BY id_pulse_acc_account) ob ON ob.id_pulse_acc_account=a.id_pulse_acc_account
            WHERE a.is_header=0 GROUP BY a.id_pulse_acc_account ORDER BY a.code');
        $out = array(); $t = array('opening' => 0, 'debit' => 0, 'credit' => 0, 'closing_dr' => 0, 'closing_cr' => 0);
        foreach ($rows as $r) {
            $closing = round((float) $r['opening'] + (float) $r['debit'] - (float) $r['credit'], 2);
            if (!$includeZero && abs($closing) < 0.005 && abs((float) $r['debit']) < 0.005 && abs((float) $r['credit']) < 0.005) { continue; }
            $r['closing'] = $closing;
            $r['closing_dr'] = $closing > 0 ? $closing : 0;
            $r['closing_cr'] = $closing < 0 ? abs($closing) : 0;
            $out[] = $r;
            $t['opening'] += (float) $r['opening']; $t['debit'] += (float) $r['debit']; $t['credit'] += (float) $r['credit'];
            $t['closing_dr'] += $r['closing_dr']; $t['closing_cr'] += $r['closing_cr'];
        }
        foreach ($t as $k => $v) { $t[$k] = round($v, 2); }
        return array('from' => $from, 'to' => $to, 'rows' => $out, 'totals' => $t, 'balanced' => abs($t['closing_dr'] - $t['closing_cr']) < 0.05);
    }

    /* ---------------- general ledger drill-down ---------------- */

    /** Account → journals → source document: every line with its running balance and a drill link. */
    public static function generalLedger($accountCode, $from, $to, $limit = 2000)
    {
        $a = PulseAccService::account($accountCode);
        if (!$a) { return null; }
        $opening = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(debit-credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE posted=1 AND account_code="'.pSQL($accountCode).'" AND business_date<"'.pSQL($from).'"'), 2);
        $rows = Db::getInstance()->executeS('SELECT l.*, j.journal_no, j.source, j.source_ref, j.reference, j.type, j.status FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_journal` j ON j.id_pulse_acc_journal=l.id_pulse_acc_journal
            WHERE l.posted=1 AND l.account_code="'.pSQL($accountCode).'" AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"
            ORDER BY l.business_date, l.id_pulse_acc_journal, l.line_no LIMIT '.(int) $limit);
        $bal = $opening; $dr = 0; $cr = 0;
        foreach ($rows as &$r) { $bal = round($bal + (float) $r['debit'] - (float) $r['credit'], 2); $r['balance'] = $bal; $dr += (float) $r['debit']; $cr += (float) $r['credit']; }
        return array('account' => $a, 'from' => $from, 'to' => $to, 'opening' => $opening, 'rows' => $rows, 'debit' => round($dr, 2), 'credit' => round($cr, 2), 'closing' => $bal);
    }

    /** Where a journal line came from — the last hop of the drill-down. */
    public static function sourceDocument($entity, $idEntity)
    {
        switch ($entity) {
            case 'pulse_folio_line': return Db::getInstance()->getRow('SELECT l.*, f.folio_no, f.type folio_type FROM `'._DB_PREFIX_.'pulse_folio_line` l INNER JOIN `'._DB_PREFIX_.'pulse_folio` f ON f.id_pulse_folio=l.id_pulse_folio WHERE l.id_pulse_folio_line='.(int) $idEntity);
            case 'pulse_pos_check': return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_check` WHERE id_pulse_pos_check='.(int) $idEntity);
            case 'pulse_expense': return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_expense` WHERE id_pulse_expense='.(int) $idEntity);
            case 'pulse_inv_grn': return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_grn` WHERE id_pulse_inv_grn='.(int) $idEntity);
            case 'pulse_acc_bill': return PulseAccAp::billRow((int) $idEntity);
            case 'pulse_acc_invoice': return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_invoice` WHERE id_pulse_acc_invoice='.(int) $idEntity);
            case 'pulse_acc_receipt': return PulseAccAr::receiptRow((int) $idEntity);
            case 'pulse_acc_payment': return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_payment` WHERE id_pulse_acc_payment='.(int) $idEntity);
            case 'pulse_acc_asset': return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_asset` WHERE id_pulse_acc_asset='.(int) $idEntity);
            case 'pulse_acc_bank_line': return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_bank_line` WHERE id_pulse_acc_bank_line='.(int) $idEntity);
        }
        return null;
    }

    /* ---------------- USALI departmental P&L ---------------- */

    /**
     * The USALI summary operating statement: rooms, F&B and other operated departments each showing
     * revenue, cost of sales, payroll and other expense with a departmental profit; then undistributed
     * expenses to GOP, then fixed charges to EBITDA and net profit.
     */
    public static function usaliPl($from, $to, $comparePrior = false)
    {
        $rows = self::plRows($from, $to);
        $prior = array();
        if ($comparePrior) {
            foreach (self::plRows(date('Y-m-d', strtotime($from.' -1 year')), date('Y-m-d', strtotime($to.' -1 year'))) as $p) {
                $prior[$p['code']] = $p['type'] === 'revenue' ? round((float) $p['credit'] - (float) $p['debit'], 2) : round((float) $p['debit'] - (float) $p['credit'], 2);
            }
        }
        $dept = array(
            'rooms' => array('label' => 'Rooms', 'revenue' => 0, 'cost_of_sales' => 0, 'payroll' => 0, 'other' => 0, 'accounts' => array()),
            'fnb' => array('label' => 'Food & Beverage', 'revenue' => 0, 'cost_of_sales' => 0, 'payroll' => 0, 'other' => 0, 'accounts' => array()),
            'other_operated' => array('label' => 'Other operated departments', 'revenue' => 0, 'cost_of_sales' => 0, 'payroll' => 0, 'other' => 0, 'accounts' => array()),
        );
        $undistributed = array('label' => 'Undistributed operating expenses', 'total' => 0, 'groups' => array());
        $fixed = array('label' => 'Fixed charges', 'total' => 0, 'accounts' => array());
        $nonOperating = array('income' => 0, 'expense' => 0, 'accounts' => array());
        $tax = 0; $depreciation = 0; $interest = 0;
        foreach ($rows as $r) {
            $amount = $r['type'] === 'revenue' ? round((float) $r['credit'] - (float) $r['debit'], 2) : round((float) $r['debit'] - (float) $r['credit'], 2);
            if (abs($amount) < 0.005) { continue; }
            $r['amount'] = $amount;
            $r['prior'] = isset($prior[$r['code']]) ? $prior[$r['code']] : 0;
            $d = $r['usali_dept'];
            if (isset($dept[$d])) {
                if ($r['type'] === 'revenue') { $dept[$d]['revenue'] += $amount; }
                elseif ($r['subtype'] === 'cost_of_sales') { $dept[$d]['cost_of_sales'] += $amount; }
                elseif ($r['subtype'] === 'payroll') { $dept[$d]['payroll'] += $amount; }
                else { $dept[$d]['other'] += $amount; }
                $dept[$d]['accounts'][] = $r;
            } elseif ($d === 'undistributed') {
                $g = self::undistributedGroup($r['code']);
                if (!isset($undistributed['groups'][$g])) { $undistributed['groups'][$g] = array('label' => $g, 'total' => 0, 'accounts' => array()); }
                $undistributed['groups'][$g]['total'] += $amount;
                $undistributed['groups'][$g]['accounts'][] = $r;
                $undistributed['total'] += $amount;
            } elseif ($d === 'fixed_charges') {
                $fixed['total'] += $amount;
                $fixed['accounts'][] = $r;
                if ($r['subtype'] === 'depreciation') { $depreciation += $amount; }
            } elseif ($d === 'non_operating') {
                if ($r['type'] === 'revenue') { $nonOperating['income'] += $amount; } else { $nonOperating['expense'] += $amount; }
                if ($r['subtype'] === 'income_tax') { $tax += $amount; $nonOperating['expense'] -= $amount; }
                if ($r['code'] === '8600') { $interest += $amount; }
                $nonOperating['accounts'][] = $r;
            }
        }
        $totalRevenue = 0; $deptProfit = 0;
        foreach ($dept as $k => $d) {
            $dept[$k]['expense'] = round($d['cost_of_sales'] + $d['payroll'] + $d['other'], 2);
            $dept[$k]['profit'] = round($d['revenue'] - $dept[$k]['expense'], 2);
            $dept[$k]['margin_pct'] = $d['revenue'] > 0 ? round($dept[$k]['profit'] / $d['revenue'] * 100, 1) : null;
            foreach (array('revenue', 'cost_of_sales', 'payroll', 'other') as $f) { $dept[$k][$f] = round($dept[$k][$f], 2); }
            $totalRevenue += $dept[$k]['revenue']; $deptProfit += $dept[$k]['profit'];
        }
        $totalRevenue = round($totalRevenue, 2); $deptProfit = round($deptProfit, 2);
        $undistributed['total'] = round($undistributed['total'], 2);
        $fixed['total'] = round($fixed['total'], 2);
        $gop = round($deptProfit - $undistributed['total'], 2);
        $ebitda = round($gop - ($fixed['total'] - $depreciation - $interest), 2);
        $ebit = round($gop - $fixed['total'], 2);
        $net = round($ebit + $nonOperating['income'] - $nonOperating['expense'] - $tax, 2);
        return array(
            'from' => $from, 'to' => $to, 'departments' => $dept, 'undistributed' => $undistributed, 'fixed' => $fixed, 'non_operating' => $nonOperating,
            'total_revenue' => $totalRevenue, 'departmental_profit' => $deptProfit, 'gop' => $gop, 'gop_pct' => $totalRevenue > 0 ? round($gop / $totalRevenue * 100, 1) : null,
            'ebitda' => $ebitda, 'ebitda_pct' => $totalRevenue > 0 ? round($ebitda / $totalRevenue * 100, 1) : null,
            'depreciation' => round($depreciation, 2), 'interest' => round($interest, 2), 'ebit' => $ebit, 'tax' => round($tax, 2), 'net_profit' => $net,
            'stats' => self::operatingStats($from, $to, $dept['rooms']['revenue']),
        );
    }

    protected static function plRows($from, $to)
    {
        $rows = Db::getInstance()->executeS('SELECT a.code, a.name, a.type, a.subtype, a.usali_dept, ROUND(SUM(l.debit),2) debit, ROUND(SUM(l.credit),2) credit
            FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account
            WHERE l.posted=1 AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND a.type IN ("revenue","expense")
            GROUP BY a.id_pulse_acc_account ORDER BY a.code');
        return $rows;
    }

    protected static function undistributedGroup($code)
    {
        $p = Tools::substr((string) $code, 0, 2);
        $m = array('71' => 'Administrative & general', '72' => 'Sales & marketing', '73' => 'Property operations & maintenance', '74' => 'Utilities');
        return isset($m[$p]) ? $m[$p] : 'Other undistributed';
    }

    /** Occupancy, ADR and RevPAR from Front Desk so the P&L carries the statistics USALI expects. */
    public static function operatingStats($from, $to, $roomRevenue)
    {
        $nights = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
        $rooms = 0; $sold = 0;
        if (PulseAccService::fd()) {
            $rooms = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information`');
            // one ROOM posting per occupied room per night, which is exactly what the night audit writes
            $sold = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_folio_line` l INNER JOIN `'._DB_PREFIX_.'pulse_charge_code` c ON c.id_pulse_charge_code=l.id_pulse_charge_code WHERE c.code="ROOM" AND l.voided=0 AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"');
        }
        $available = $rooms * $nights;
        return array(
            'nights' => $nights, 'rooms' => $rooms, 'rooms_sold' => $sold, 'rooms_available' => $available,
            'occupancy_pct' => $available > 0 ? round($sold / $available * 100, 1) : null,
            'adr' => $sold > 0 ? round($roomRevenue / $sold, 2) : null,
            'revpar' => $available > 0 ? round($roomRevenue / $available, 2) : null,
        );
    }

    /* ---------------- balance sheet ---------------- */

    public static function balanceSheet($asOf)
    {
        $rows = Db::getInstance()->executeS('SELECT a.code, a.name, a.type, a.subtype, a.is_contra, ROUND(SUM(l.debit-l.credit),2) balance
            FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account
            WHERE l.posted=1 AND l.business_date<="'.pSQL($asOf).'" AND a.type IN ("asset","liability","equity") GROUP BY a.id_pulse_acc_account HAVING ABS(balance)>0.004 ORDER BY a.code');
        $groups = array(
            'current_assets' => array('label' => 'Current assets', 'rows' => array(), 'total' => 0),
            'fixed_assets' => array('label' => 'Property, plant and equipment', 'rows' => array(), 'total' => 0),
            'current_liabilities' => array('label' => 'Current liabilities', 'rows' => array(), 'total' => 0),
            'long_term' => array('label' => 'Non-current liabilities', 'rows' => array(), 'total' => 0),
            'equity' => array('label' => 'Equity', 'rows' => array(), 'total' => 0),
        );
        foreach ($rows as $r) {
            $bal = (float) $r['balance'];
            if ($r['type'] === 'asset') { $g = in_array($r['subtype'], array('fixed_asset', 'accum_depreciation')) ? 'fixed_assets' : 'current_assets'; $r['amount'] = round($bal, 2); }
            elseif ($r['type'] === 'liability') { $g = $r['subtype'] === 'borrowing' && strpos($r['code'], '243') === 0 ? 'long_term' : 'current_liabilities'; $r['amount'] = round(-$bal, 2); }
            else { $g = 'equity'; $r['amount'] = round(-$bal, 2); }
            $groups[$g]['rows'][] = $r;
            $groups[$g]['total'] = round($groups[$g]['total'] + $r['amount'], 2);
        }
        // the year's result is not in an equity account until the close, so it is shown separately
        $result = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.credit-l.debit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.business_date<="'.pSQL($asOf).'" AND l.business_date>="'.pSQL(Tools::substr($asOf, 0, 4).'-01-01').'" AND a.type IN ("revenue","expense")'), 2);
        $assets = round($groups['current_assets']['total'] + $groups['fixed_assets']['total'], 2);
        $liabilities = round($groups['current_liabilities']['total'] + $groups['long_term']['total'], 2);
        $equity = round($groups['equity']['total'] + $result, 2);
        // pre-split into the two columns the statement is read in, so the template needs no key gymnastics
        $left = array($groups['current_assets'], $groups['fixed_assets']);
        $right = array($groups['current_liabilities'], $groups['long_term'], $groups['equity']);
        $right[2]['is_equity'] = true;
        return array('as_of' => $asOf, 'groups' => $groups, 'left' => $left, 'right' => $right, 'result_for_year' => $result,
            'total_assets' => $assets, 'total_liabilities' => $liabilities, 'total_equity' => $equity, 'total_funding' => round($liabilities + $equity, 2),
            'difference' => round($assets - $liabilities - $equity, 2), 'balanced' => abs($assets - $liabilities - $equity) < 0.05);
    }

    /* ---------------- cash flow (indirect) ---------------- */

    public static function cashFlow($from, $to)
    {
        $profit = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(IF(a.type="revenue",l.credit-l.debit,-(l.debit-l.credit))),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND a.type IN ("revenue","expense")'), 2);
        $nonCash = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.debit-l.credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND a.subtype IN ("depreciation")'), 2);
        $movement = function ($subtypes, $sign) use ($from, $to) {
            $v = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.debit-l.credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND a.subtype IN ('.$subtypes.')');
            return round($sign * $v, 2);
        };
        $receivables = $movement('"receivable"', -1);
        $inventory = $movement('"inventory"', -1);
        $prepayments = $movement('"prepayment"', -1);
        $payables = $movement('"payable"', -1);
        $taxes = $movement('"tax"', -1);
        $deposits = $movement('"deposit"', -1);
        $operating = round($profit + $nonCash + $receivables + $inventory + $prepayments + $payables + $taxes + $deposits, 2);
        $investing = round(-1 * (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.debit-l.credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND a.subtype="fixed_asset"'), 2);
        $financing = round(-1 * (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.debit-l.credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND (a.subtype IN ("borrowing","capital","drawings","reserve","retained"))'), 2);
        $openCash = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.debit-l.credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.business_date<"'.pSQL($from).'" AND a.subtype="cash"'), 2);
        $moveCash = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(l.debit-l.credit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" AND a.subtype="cash"'), 2);
        return array(
            'from' => $from, 'to' => $to, 'profit' => $profit, 'depreciation' => $nonCash,
            'working_capital' => array('receivables' => $receivables, 'inventory' => $inventory, 'prepayments' => $prepayments, 'payables' => $payables, 'taxes' => $taxes, 'deposits' => $deposits),
            'operating' => $operating, 'investing' => $investing, 'financing' => $financing,
            'net_movement' => round($operating + $investing + $financing, 2), 'cash_movement_actual' => $moveCash,
            'opening_cash' => $openCash, 'closing_cash' => round($openCash + $moveCash, 2),
            'unexplained' => round($operating + $investing + $financing - $moveCash, 2),
        );
    }

    /* ---------------- budget vs actual ---------------- */

    /**
     * Budget lines live in pulse_budget (Pulse Reports): room_revenue, fnb_revenue, other_revenue,
     * expense:<category code> and capex:<class>. Actuals come from the ledger through the same rules
     * that post them, so the two sides always speak about the same money.
     */
    public static function budgetVsActual($year, $month = null)
    {
        $year = (int) $year;
        if (!PulseAccService::tableExists('pulse_budget')) { return array('rows' => array(), 'note' => 'Pulse Reports is not installed — there is no budget table to compare against'); }
        $from = $month ? sprintf('%04d-%02d-01', $year, $month) : $year.'-01-01';
        $to = $month ? date('Y-m-t', strtotime($from)) : $year.'-12-31';
        $budget = Db::getInstance()->executeS('SELECT `line`, ROUND(SUM(amount),2) amount FROM `'._DB_PREFIX_.'pulse_budget` WHERE `year`='.$year.($month ? ' AND `month`='.(int) $month : '').' GROUP BY `line`');
        $actualByDept = array();
        foreach (Db::getInstance()->executeS('SELECT a.usali_dept, ROUND(SUM(l.credit-l.debit),2) amount FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND a.type="revenue" AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY a.usali_dept') as $r) { $actualByDept[$r['usali_dept']] = (float) $r['amount']; }
        $actualByAccount = array();
        foreach (Db::getInstance()->executeS('SELECT l.account_code, ROUND(SUM(l.debit-l.credit),2) amount FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND a.type="expense" AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY l.account_code') as $r) { $actualByAccount[$r['account_code']] = (float) $r['amount']; }
        $out = array();
        foreach ($budget as $b) {
            $line = $b['line']; $amount = (float) $b['amount']; $actual = 0; $label = $line; $kind = 'other';
            if ($line === 'room_revenue') { $actual = isset($actualByDept['rooms']) ? $actualByDept['rooms'] : 0; $label = 'Rooms revenue'; $kind = 'revenue'; }
            elseif ($line === 'fnb_revenue') { $actual = isset($actualByDept['fnb']) ? $actualByDept['fnb'] : 0; $label = 'F&B revenue'; $kind = 'revenue'; }
            elseif ($line === 'other_revenue') { $actual = isset($actualByDept['other_operated']) ? $actualByDept['other_operated'] : 0; $label = 'Other operated revenue'; $kind = 'revenue'; }
            elseif (strpos($line, 'expense:') === 0) {
                $cat = Tools::substr($line, 8); $acct = PulseAccService::mapAccount('expense_category', $cat);
                $actual = $acct && isset($actualByAccount[$acct]) ? $actualByAccount[$acct] : 0;
                $m = PulseAccService::map('expense_category', $cat);
                $label = ($m && $m['label'] ? $m['label'] : $cat).($acct ? ' ('.$acct.')' : ''); $kind = 'expense';
            } elseif (strpos($line, 'capex:') === 0) {
                $cls = Tools::substr($line, 6);
                $actual = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(cost),0) FROM `'._DB_PREFIX_.'pulse_acc_asset` WHERE class_code="'.pSQL($cls).'" AND acquisition_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'), 2);
                $label = 'CAPEX — '.$cls; $kind = 'capex';
            } elseif (in_array($line, array('occupancy_pct', 'adr'))) { continue; }
            $variance = $kind === 'revenue' ? round($actual - $amount, 2) : round($amount - $actual, 2);
            $out[] = array('line' => $line, 'label' => $label, 'kind' => $kind, 'budget' => round($amount, 2), 'actual' => round($actual, 2), 'variance' => $variance, 'variance_pct' => $amount != 0 ? round($variance / abs($amount) * 100, 1) : null);
        }
        return array('year' => $year, 'month' => $month, 'from' => $from, 'to' => $to, 'rows' => $out);
    }

    /* ---------------- revenue analysis ---------------- */

    public static function revenueByDepartment($from, $to)
    {
        return Db::getInstance()->executeS('SELECT a.usali_dept department, ROUND(SUM(l.credit-l.debit),2) revenue, COUNT(DISTINCT l.id_pulse_acc_journal) journals
            FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account
            WHERE l.posted=1 AND a.type="revenue" AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY a.usali_dept ORDER BY revenue DESC');
    }

    public static function revenueByAccount($from, $to)
    {
        return Db::getInstance()->executeS('SELECT l.account_code, l.account_name, a.usali_dept, ROUND(SUM(l.credit-l.debit),2) revenue
            FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account
            WHERE l.posted=1 AND a.type="revenue" AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY l.account_code HAVING ABS(revenue)>0.004 ORDER BY revenue DESC');
    }

    /** Revenue by the charge code the desk actually used — the language the front office speaks. */
    public static function revenueByChargeCode($from, $to)
    {
        if (!PulseAccService::fd()) { return array(); }
        return Db::getInstance()->executeS('SELECT c.code, c.name, c.department, COUNT(*) postings, ROUND(SUM(l.amount_tax_incl),2) gross,
            ROUND(SUM(l.amount_tax_incl/(1+l.tax_rate/100)),2) net, ROUND(SUM(l.amount_tax_incl-(l.amount_tax_incl/(1+l.tax_rate/100))),2) tax
            FROM `'._DB_PREFIX_.'pulse_folio_line` l INNER JOIN `'._DB_PREFIX_.'pulse_charge_code` c ON c.id_pulse_charge_code=l.id_pulse_charge_code
            WHERE l.voided=0 AND l.is_payment=0 AND l.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY c.id_pulse_charge_code ORDER BY gross DESC');
    }

    /**
     * The daily revenue journal: what the night audit says the day produced next to what the ledger
     * posted, with the difference spelled out. This is the reconciliation an auditor opens first.
     */
    public static function dailyRevenueJournal($date)
    {
        $gl = Db::getInstance()->executeS('SELECT a.usali_dept, a.code, a.name, ROUND(SUM(l.credit-l.debit),2) amount
            FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account
            WHERE l.posted=1 AND l.business_date="'.pSQL($date).'" AND a.type="revenue" GROUP BY a.id_pulse_acc_account HAVING ABS(amount)>0.004 ORDER BY a.code');
        $glTotal = 0; $byDept = array();
        foreach ($gl as $g) { $glTotal += (float) $g['amount']; if (!isset($byDept[$g['usali_dept']])) { $byDept[$g['usali_dept']] = 0; } $byDept[$g['usali_dept']] = round($byDept[$g['usali_dept']] + (float) $g['amount'], 2); }
        $glTotal = round($glTotal, 2);
        $audit = PulseAccService::fd() ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_night_audit` WHERE business_date="'.pSQL($date).'"') : null;
        $folio = PulseAccService::fd() ? Db::getInstance()->getRow('SELECT ROUND(COALESCE(SUM(IF(is_payment=0,amount_tax_incl,0)),0),2) charges, ROUND(COALESCE(SUM(IF(is_payment=1,amount_tax_incl,0)),0),2) payments, ROUND(COALESCE(SUM(IF(is_payment=0,amount_tax_incl-(amount_tax_incl/(1+tax_rate/100)),0)),0),2) tax FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE voided=0 AND business_date="'.pSQL($date).'"') : null;
        $auditRevenue = $audit ? round((float) $audit['room_revenue'] + (float) $audit['fnb_revenue'] + (float) $audit['other_revenue'], 2) : null;
        $folioNet = $folio ? round((float) $folio['charges'] - (float) $folio['tax'], 2) : null;
        $queue = Db::getInstance()->getRow('SELECT SUM(status="pending") pending, SUM(status="failed") failed FROM `'._DB_PREFIX_.'pulse_acc_queue` WHERE business_date="'.pSQL($date).'"');
        $tax = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(credit-debit),0) FROM `'._DB_PREFIX_.'pulse_acc_journal_line` WHERE posted=1 AND business_date="'.pSQL($date).'" AND account_code IN ("2210","2240")'), 2);
        $settlements = Db::getInstance()->executeS('SELECT l.account_code, l.account_name, ROUND(SUM(l.debit-l.credit),2) amount FROM `'._DB_PREFIX_.'pulse_acc_journal_line` l INNER JOIN `'._DB_PREFIX_.'pulse_acc_account` a ON a.id_pulse_acc_account=l.id_pulse_acc_account WHERE l.posted=1 AND l.business_date="'.pSQL($date).'" AND a.subtype IN ("cash","receivable","deposit") GROUP BY l.account_code HAVING ABS(amount)>0.004 ORDER BY l.account_code');
        return array(
            'date' => $date, 'gl' => $gl, 'gl_total' => $glTotal, 'by_department' => $byDept, 'tax' => $tax,
            'night_audit' => $audit, 'audit_revenue' => $auditRevenue, 'folio' => $folio, 'folio_net' => $folioNet,
            'variance_vs_audit' => $auditRevenue !== null ? round($glTotal - $auditRevenue, 2) : null,
            'variance_vs_folio' => $folioNet !== null ? round($glTotal - $folioNet, 2) : null,
            'settlements' => $settlements, 'queue_pending' => (int) $queue['pending'], 'queue_failed' => (int) $queue['failed'],
        );
    }
}
