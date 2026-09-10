<?php
/**
 * Fixed-asset register and depreciation engine.
 *
 * This register is the *financial* one: cost, depreciation, NBV, disposal. It links to the engineering
 * register in Pulse Maintenance by id_pulse_asset — that one keeps make, model, serial, warranty and
 * work orders, and it stays the master for those. One generator, two records, one link.
 *
 * The depreciation run is idempotent twice over: UNIQUE(asset, period) on the schedule rows and
 * UNIQUE(source, source_ref) on the journal, so running the month again changes nothing.
 */
class PulseAccAssets
{
    public static function classes($activeOnly = true) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_asset_class` WHERE 1'.($activeOnly ? ' AND active=1' : '').' ORDER BY sort, code'); }
    public static function assetClass($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_asset_class` WHERE id_pulse_acc_asset_class='.(int) $id); }
    public static function assetClassByCode($code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_asset_class` WHERE code="'.pSQL($code).'"'); }

    public static function saveClass(array $d)
    {
        if (empty($d['code']) || empty($d['name'])) { throw new PrestaShopException('Asset class needs a code and a name'); }
        foreach (array('asset_account', 'accum_account', 'expense_account') as $f) { if (empty($d[$f]) || !PulseAccService::account($d[$f])) { throw new PrestaShopException('Asset class needs a valid '.str_replace('_', ' ', $f)); } }
        $row = array(
            'code' => pSQL(Tools::substr($d['code'], 0, 16)), 'name' => pSQL(Tools::substr($d['name'], 0, 96)), 'method' => pSQL(isset($d['method']) ? $d['method'] : 'straight_line'),
            'life_months' => (int) (isset($d['life_months']) ? $d['life_months'] : 60), 'rate_pct' => round((float) (isset($d['rate_pct']) ? $d['rate_pct'] : 0), 3),
            'residual_pct' => round((float) (isset($d['residual_pct']) ? $d['residual_pct'] : 0), 3), 'asset_account' => pSQL($d['asset_account']),
            'accum_account' => pSQL($d['accum_account']), 'expense_account' => pSQL($d['expense_account']),
            'capital_allowance_note' => pSQL(Tools::substr(isset($d['capital_allowance_note']) ? $d['capital_allowance_note'] : '', 0, 255)),
            'active' => isset($d['active']) ? (int) (bool) $d['active'] : 1, 'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0),
        );
        if (!empty($d['id_pulse_acc_asset_class'])) { Db::getInstance()->update('pulse_acc_asset_class', $row, 'id_pulse_acc_asset_class='.(int) $d['id_pulse_acc_asset_class']); return (int) $d['id_pulse_acc_asset_class']; }
        Db::getInstance()->insert('pulse_acc_asset_class', $row, true);
        return (int) Db::getInstance()->Insert_ID();
    }

    /* ---------------- register ---------------- */

    public static function assets(array $f = array(), $limit = 500)
    {
        $w = array('1');
        if (!empty($f['class'])) { $w[] = 'a.class_code="'.pSQL($f['class']).'"'; }
        if (!empty($f['status'])) { $w[] = 'a.status="'.pSQL($f['status']).'"'; }
        elseif (empty($f['include_disposed'])) { $w[] = 'a.status NOT IN ("disposed","written_off")'; }
        if (!empty($f['cost_centre'])) { $w[] = 'a.cost_centre="'.pSQL($f['cost_centre']).'"'; }
        if (!empty($f['id_room'])) { $w[] = 'a.id_room='.(int) $f['id_room']; }
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w[] = '(a.code LIKE "%'.$q.'%" OR a.name LIKE "%'.$q.'%" OR a.serial_no LIKE "%'.$q.'%" OR a.location LIKE "%'.$q.'%")'; }
        return Db::getInstance()->executeS('SELECT a.*, c.name class_name, r.room_num, e.name eng_name, e.serial_no eng_serial, e.status eng_status
            FROM `'._DB_PREFIX_.'pulse_acc_asset` a INNER JOIN `'._DB_PREFIX_.'pulse_acc_asset_class` c ON c.id_pulse_acc_asset_class=a.id_pulse_acc_asset_class
            LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=a.id_room
            LEFT JOIN `'._DB_PREFIX_.'pulse_asset` e ON e.id_pulse_asset=a.id_pulse_asset
            WHERE '.implode(' AND ', $w).' ORDER BY a.class_code, a.code LIMIT '.(int) $limit);
    }

    public static function asset($id)
    {
        $a = Db::getInstance()->getRow('SELECT a.*, c.name class_name, c.asset_account, c.accum_account, c.expense_account, c.capital_allowance_note, r.room_num FROM `'._DB_PREFIX_.'pulse_acc_asset` a INNER JOIN `'._DB_PREFIX_.'pulse_acc_asset_class` c ON c.id_pulse_acc_asset_class=a.id_pulse_acc_asset_class LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=a.id_room WHERE a.id_pulse_acc_asset='.(int) $id);
        if (!$a) { return null; }
        $a['schedule'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_depreciation` WHERE id_pulse_acc_asset='.(int) $id.' ORDER BY period');
        $a['events'] = Db::getInstance()->executeS('SELECT ev.*, j.journal_no FROM `'._DB_PREFIX_.'pulse_acc_asset_event` ev LEFT JOIN `'._DB_PREFIX_.'pulse_acc_journal` j ON j.id_pulse_acc_journal=ev.id_pulse_acc_journal WHERE ev.id_pulse_acc_asset='.(int) $id.' ORDER BY ev.business_date, ev.id_pulse_acc_asset_event');
        $a['components'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_asset` WHERE id_parent='.(int) $id.' ORDER BY code');
        $a['engineering'] = $a['id_pulse_asset'] && PulseAccService::mnt() ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_asset` WHERE id_pulse_asset='.(int) $a['id_pulse_asset']) : null;
        return $a;
    }

    /** Engineering assets in Pulse Maintenance that have no financial record yet — the "capitalise me" list. */
    public static function unlinkedEngineeringAssets($limit = 200)
    {
        if (!PulseAccService::mnt()) { return array(); }
        return Db::getInstance()->executeS('SELECT e.*, r.room_num FROM `'._DB_PREFIX_.'pulse_asset` e LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=e.id_room WHERE e.status<>"retired" AND e.purchase_cost>0 AND NOT EXISTS (SELECT 1 FROM `'._DB_PREFIX_.'pulse_acc_asset` a WHERE a.id_pulse_asset=e.id_pulse_asset) ORDER BY e.purchase_cost DESC LIMIT '.(int) $limit);
    }

    /**
     * Capitalise an asset. Posts the addition journal (asset Dr, source Cr) unless $d['no_journal']
     * is set — a register built from history posts its opening cost through the opening balances instead.
     */
    public static function addAsset(array $d)
    {
        $cls = !empty($d['class_code']) ? self::assetClassByCode($d['class_code']) : (!empty($d['id_pulse_acc_asset_class']) ? self::assetClass((int) $d['id_pulse_acc_asset_class']) : null);
        if (!$cls) { throw new PrestaShopException('Pick an asset class'); }
        if (empty($d['name'])) { throw new PrestaShopException('An asset needs a name'); }
        $cost = round((float) (isset($d['cost']) ? $d['cost'] : 0), 2);
        if ($cost <= 0) { throw new PrestaShopException('An asset needs a cost'); }
        $code = !empty($d['code']) ? Tools::substr($d['code'], 0, 32) : self::nextAssetCode($cls['code']);
        if (Db::getInstance()->getValue('SELECT id_pulse_acc_asset FROM `'._DB_PREFIX_.'pulse_acc_asset` WHERE code="'.pSQL($code).'"')) { throw new PrestaShopException('Asset code '.$code.' already exists'); }
        $acq = !empty($d['acquisition_date']) ? Tools::substr($d['acquisition_date'], 0, 10) : PulseAccService::bd();
        $inService = !empty($d['in_service_date']) ? Tools::substr($d['in_service_date'], 0, 10) : $acq;
        $method = !empty($d['method']) ? $d['method'] : $cls['method'];
        $residual = isset($d['residual_value']) ? round((float) $d['residual_value'], 2) : round($cost * (float) $cls['residual_pct'] / 100, 2);
        Db::getInstance()->insert('pulse_acc_asset', array(
            'code' => pSQL($code), 'name' => pSQL(Tools::substr($d['name'], 0, 128)), 'id_pulse_acc_asset_class' => (int) $cls['id_pulse_acc_asset_class'], 'class_code' => pSQL($cls['code']),
            'id_pulse_asset' => !empty($d['id_pulse_asset']) ? (int) $d['id_pulse_asset'] : null, 'id_parent' => !empty($d['id_parent']) ? (int) $d['id_parent'] : null,
            'id_room' => !empty($d['id_room']) ? (int) $d['id_room'] : null, 'location' => pSQL(Tools::substr(isset($d['location']) ? $d['location'] : '', 0, 128)),
            'cost_centre' => pSQL(Tools::substr(isset($d['cost_centre']) ? $d['cost_centre'] : 'general', 0, 32)), 'department' => pSQL(Tools::substr(isset($d['department']) ? $d['department'] : 'general', 0, 32)),
            'supplier' => pSQL(Tools::substr(isset($d['supplier']) ? $d['supplier'] : '', 0, 128)), 'invoice_ref' => pSQL(Tools::substr(isset($d['invoice_ref']) ? $d['invoice_ref'] : '', 0, 64)),
            'serial_no' => pSQL(Tools::substr(isset($d['serial_no']) ? $d['serial_no'] : '', 0, 64)),
            'acquisition_date' => pSQL($acq), 'in_service_date' => pSQL($inService), 'cost' => $cost, 'residual_value' => $residual,
            'method' => pSQL($method), 'life_months' => (int) (isset($d['life_months']) && $d['life_months'] ? $d['life_months'] : $cls['life_months']),
            'rate_pct' => round((float) (isset($d['rate_pct']) && $d['rate_pct'] ? $d['rate_pct'] : $cls['rate_pct']), 3),
            'units_total' => !empty($d['units_total']) ? round((float) $d['units_total'], 2) : null, 'units_used' => round((float) (isset($d['units_used']) ? $d['units_used'] : 0), 2),
            'accum_depreciation' => round((float) (isset($d['accum_depreciation']) ? $d['accum_depreciation'] : 0), 2),
            'nbv' => round($cost - (float) (isset($d['accum_depreciation']) ? $d['accum_depreciation'] : 0), 2),
            'last_period' => !empty($d['last_period']) ? pSQL($d['last_period']) : null, 'status' => 'in_service',
            'capex_budget_line' => pSQL(Tools::substr(isset($d['capex_budget_line']) ? $d['capex_budget_line'] : '', 0, 32)),
            'note' => pSQL(Tools::substr(isset($d['note']) ? $d['note'] : '', 0, 255)), 'id_employee' => PulseAccService::emp(),
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        ), true);
        $id = (int) Db::getInstance()->Insert_ID();
        $idJ = null;
        if (empty($d['no_journal'])) {
            $credit = !empty($d['credit_account']) ? $d['credit_account'] : '2110';
            $idJ = PulseAccJournal::post(array('type' => 'general', 'source' => 'asset', 'source_ref' => 'add:'.$id, 'business_date' => $acq, 'reference' => $code, 'memo' => 'Asset addition '.$code.' — '.$d['name'], 'lines' => array(
                array('account' => $cls['asset_account'], 'debit' => $cost, 'memo' => $code.' '.$d['name'], 'cost_centre' => isset($d['cost_centre']) ? $d['cost_centre'] : 'general', 'entity' => 'pulse_acc_asset', 'id_entity' => $id),
                array('account' => $credit, 'credit' => $cost, 'memo' => 'Acquisition of '.$code, 'cost_centre' => isset($d['cost_centre']) ? $d['cost_centre'] : 'general', 'entity' => 'pulse_acc_asset', 'id_entity' => $id),
            )));
        }
        self::event($id, 'addition', $acq, $cost, 'Capitalised at '.number_format($cost, 2), $idJ);
        PulseCoreService::audit('pulseaccounts', 'asset_add', array('code' => $code, 'cost' => $cost, 'class' => $cls['code']), 'pulse_acc_asset', $id);
        return $id;
    }

    public static function nextAssetCode($classCode)
    {
        $n = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_acc_asset` WHERE class_code="'.pSQL($classCode).'"') + 1;
        do { $code = $classCode.'-'.str_pad($n, 4, '0', STR_PAD_LEFT); $n++; } while (Db::getInstance()->getValue('SELECT id_pulse_acc_asset FROM `'._DB_PREFIX_.'pulse_acc_asset` WHERE code="'.pSQL($code).'"'));
        return $code;
    }

    public static function updateAsset($id, array $d)
    {
        $a = self::asset($id);
        if (!$a) { throw new PrestaShopException('Unknown asset'); }
        $row = array('date_upd' => date('Y-m-d H:i:s'));
        foreach (array('name' => 128, 'location' => 128, 'cost_centre' => 32, 'department' => 32, 'supplier' => 128, 'invoice_ref' => 64, 'serial_no' => 64, 'note' => 255, 'capex_budget_line' => 32) as $f => $len) { if (isset($d[$f])) { $row[$f] = pSQL(Tools::substr((string) $d[$f], 0, $len)); } }
        foreach (array('life_months', 'id_room', 'id_pulse_asset', 'id_parent') as $f) { if (isset($d[$f])) { $row[$f] = (int) $d[$f] ? (int) $d[$f] : null; } }
        foreach (array('residual_value', 'rate_pct', 'units_total', 'units_used') as $f) { if (isset($d[$f])) { $row[$f] = round((float) $d[$f], 3); } }
        if (isset($d['method'])) { $row['method'] = pSQL($d['method']); }
        if (isset($d['status']) && in_array($d['status'], array('in_service', 'idle', 'under_repair', 'held_for_sale'))) { $row['status'] = pSQL($d['status']); }
        Db::getInstance()->update('pulse_acc_asset', $row, 'id_pulse_acc_asset='.(int) $id, 0, true);
        PulseCoreService::audit('pulseaccounts', 'asset_update', array('code' => $a['code']), 'pulse_acc_asset', (int) $id);
        return true;
    }

    protected static function event($idAsset, $type, $date, $amount, $note, $idJournal = null, $from = '', $to = '')
    {
        Db::getInstance()->insert('pulse_acc_asset_event', array(
            'id_pulse_acc_asset' => (int) $idAsset, 'type' => pSQL($type), 'business_date' => pSQL($date), 'amount' => round((float) $amount, 2),
            'from_value' => pSQL(Tools::substr($from, 0, 128)), 'to_value' => pSQL(Tools::substr($to, 0, 128)), 'note' => pSQL(Tools::substr($note, 0, 255)),
            'id_pulse_acc_journal' => $idJournal ? (int) $idJournal : null, 'id_employee' => PulseAccService::emp(), 'date_add' => date('Y-m-d H:i:s'),
        ), true);
        return (int) Db::getInstance()->Insert_ID();
    }

    /* ---------------- depreciation ---------------- */

    /**
     * One month's charge for one asset. Straight line and reducing balance both stop at the residual;
     * units of production needs units_used moved on before the run (a generator's hour meter, say).
     */
    public static function monthlyCharge(array $a, $period)
    {
        if ($a['method'] === 'none' || $a['status'] === 'disposed' || $a['status'] === 'written_off') { return 0; }
        $end = date('Y-m-t', strtotime($period.'-01'));
        if ($a['in_service_date'] > $end) { return 0; }
        // impairment is already inside accum_depreciation (impair() credits the accumulated account), so it is
        // deducted once here through $accum — subtracting the memo column as well would halt depreciation early.
        $base = round((float) $a['cost'] + (float) $a['revaluation'], 2);
        $residual = round((float) $a['residual_value'], 2);
        $accum = round((float) $a['accum_depreciation'], 2);
        $depreciable = round($base - $residual, 2);
        if ($depreciable <= 0.009 || $accum >= $depreciable - 0.009) { return 0; }
        $charge = 0;
        if ($a['method'] === 'straight_line') {
            $life = (int) $a['life_months'];
            if ($life <= 0) { return 0; }
            $charge = round($depreciable / $life, 2);
        } elseif ($a['method'] === 'reducing_balance') {
            $rate = (float) $a['rate_pct'];
            if ($rate <= 0) { return 0; }
            $charge = round(($base - $accum) * ($rate / 100) / 12, 2);
        } elseif ($a['method'] === 'units_of_production') {
            $totalUnits = (float) $a['units_total'];
            if ($totalUnits <= 0) { return 0; }
            $done = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(amount),0) FROM `'._DB_PREFIX_.'pulse_acc_depreciation` WHERE id_pulse_acc_asset='.(int) $a['id_pulse_acc_asset']), 2);
            $target = round($depreciable * min(1, (float) $a['units_used'] / $totalUnits), 2);
            $charge = round($target - $done, 2);
        }
        if ($charge < 0) { $charge = 0; }
        // never depreciate past the residual value
        return round(min($charge, $depreciable - $accum), 2);
    }

    /**
     * Month-end depreciation run: one schedule row per asset and one posted journal per asset class.
     * The schedule's UNIQUE(asset, period) is the single gate — the row is claimed with INSERT IGNORE
     * *before* anything is posted, so a second run of the same month charges nothing, and a run repeated
     * after an asset was back-dated into the month posts a top-up journal covering exactly the new assets
     * (a fixed source_ref would have handed back the first journal and left those assets out of the GL).
     */
    public static function runDepreciation($period, $dryRun = false)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) { throw new PrestaShopException('Period must look like 2026-08'); }
        $date = date('Y-m-t', strtotime($period.'-01'));
        if (!$dryRun) { PulseAccService::assertPeriodOpen($date); }
        $byClass = array(); $rows = array(); $total = 0;
        foreach (self::assets(array('include_disposed' => false), 5000) as $a) {
            if (Db::getInstance()->getValue('SELECT id_pulse_acc_depreciation FROM `'._DB_PREFIX_.'pulse_acc_depreciation` WHERE id_pulse_acc_asset='.(int) $a['id_pulse_acc_asset'].' AND period="'.pSQL($period).'"')) { continue; }
            if ($a['last_period'] && $a['last_period'] >= $period) { continue; }
            $charge = self::monthlyCharge($a, $period);
            if ($charge < 0.005) { continue; }
            $openNbv = round((float) $a['cost'] + (float) $a['revaluation'] - (float) $a['accum_depreciation'], 2);
            $rows[] = array('asset' => $a, 'charge' => $charge, 'opening_nbv' => $openNbv);
            if (!isset($byClass[$a['class_code']])) { $byClass[$a['class_code']] = 0; }
            $byClass[$a['class_code']] += $charge;
            $total += $charge;
        }
        if ($dryRun) { return array('period' => $period, 'assets' => count($rows), 'total' => round($total, 2), 'by_class' => $byClass, 'rows' => $rows, 'dry_run' => true); }
        // claim the schedule rows first: whatever this run actually inserted is what it may post
        $claimed = array(); $byClass = array(); $total = 0; $token = 0;
        foreach ($rows as $r) {
            $a = $r['asset'];
            $accum = round((float) $a['accum_depreciation'] + $r['charge'], 2);
            $closing = round((float) $a['cost'] + (float) $a['revaluation'] - $accum, 2);
            Db::getInstance()->insert('pulse_acc_depreciation', array(
                'id_pulse_acc_asset' => (int) $a['id_pulse_acc_asset'], 'class_code' => pSQL($a['class_code']), 'period' => pSQL($period), 'business_date' => pSQL($date),
                'method' => pSQL($a['method']), 'opening_nbv' => $r['opening_nbv'], 'amount' => $r['charge'], 'accum_after' => $accum, 'closing_nbv' => $closing,
                'date_add' => date('Y-m-d H:i:s'),
            ), true, true, Db::INSERT_IGNORE);
            if (!Db::getInstance()->Affected_Rows()) { continue; }
            $id = (int) Db::getInstance()->Insert_ID();
            if (!$token) { $token = $id; }
            $claimed[] = array('id' => $id, 'asset' => $a, 'accum' => $accum, 'closing' => $closing);
            if (!isset($byClass[$a['class_code']])) { $byClass[$a['class_code']] = 0; }
            $byClass[$a['class_code']] += $r['charge'];
            $total += $r['charge'];
        }
        if (!$claimed) { return array('period' => $period, 'assets' => 0, 'total' => 0, 'by_class' => array(), 'journals' => array()); }
        $journals = array();
        foreach ($byClass as $classCode => $amount) {
            $amount = round($amount, 2);
            if ($amount < 0.005) { continue; }
            $cls = self::assetClassByCode($classCode);
            $journals[$classCode] = PulseAccJournal::post(array(
                'type' => 'depreciation', 'source' => 'depreciation', 'source_ref' => 'dep:'.$period.':'.$classCode.':'.$token, 'business_date' => $date,
                'reference' => 'DEP '.$period.' '.$classCode, 'memo' => 'Depreciation '.$period.' — '.$cls['name'],
                'lines' => array(
                    array('account' => $cls['expense_account'], 'debit' => $amount, 'memo' => 'Depreciation '.$period.' '.$cls['name'], 'cost_centre' => 'general'),
                    array('account' => $cls['accum_account'], 'credit' => $amount, 'memo' => 'Depreciation '.$period.' '.$cls['name'], 'cost_centre' => 'general'),
                ),
            ));
        }
        foreach ($claimed as $c) {
            $a = $c['asset'];
            if (isset($journals[$a['class_code']])) { Db::getInstance()->update('pulse_acc_depreciation', array('id_pulse_acc_journal' => (int) $journals[$a['class_code']]), 'id_pulse_acc_depreciation='.(int) $c['id']); }
            Db::getInstance()->update('pulse_acc_asset', array('accum_depreciation' => $c['accum'], 'nbv' => $c['closing'], 'last_period' => pSQL($period), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_asset='.(int) $a['id_pulse_acc_asset']);
        }
        PulseCoreService::audit('pulseaccounts', 'depreciation_run', array('period' => $period, 'assets' => count($claimed), 'total' => round($total, 2)));
        PulseCoreService::event('actionPulseAccDepreciationRun', array('period' => $period, 'total' => round($total, 2), 'assets' => count($claimed)));
        return array('period' => $period, 'assets' => count($claimed), 'total' => round($total, 2), 'by_class' => $byClass, 'journals' => $journals);
    }

    public static function runs($limit = 24)
    {
        return Db::getInstance()->executeS('SELECT period, COUNT(*) assets, ROUND(SUM(amount),2) total, MIN(date_add) run_at FROM `'._DB_PREFIX_.'pulse_acc_depreciation` GROUP BY period ORDER BY period DESC LIMIT '.(int) $limit);
    }

    /* ---------------- transfers, revaluation, impairment ---------------- */

    public static function transfer($id, $costCentre, $location, $idRoom = null, $note = '')
    {
        $a = self::asset($id);
        if (!$a) { throw new PrestaShopException('Unknown asset'); }
        Db::getInstance()->update('pulse_acc_asset', array('cost_centre' => pSQL(Tools::substr($costCentre, 0, 32)), 'location' => pSQL(Tools::substr($location, 0, 128)), 'id_room' => $idRoom ? (int) $idRoom : null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_asset='.(int) $id);
        self::event($id, 'transfer', PulseAccService::bd(), 0, $note, null, $a['cost_centre'].' / '.$a['location'], $costCentre.' / '.$location);
        return true;
    }

    /** Revaluation: asset Dr, revaluation reserve Cr (or the reverse on a downward revaluation). */
    public static function revalue($id, $amount, $date, $note = '')
    {
        $a = self::asset($id);
        if (!$a) { throw new PrestaShopException('Unknown asset'); }
        $amount = round((float) $amount, 2);
        if (abs($amount) < 0.005) { throw new PrestaShopException('Revaluation amount cannot be zero'); }
        $date = $date ? $date : PulseAccService::bd();
        $idJ = PulseAccJournal::post(array('type' => 'general', 'source' => 'asset', 'source_ref' => 'reval:'.(int) $id.':'.$date, 'business_date' => $date, 'reference' => $a['code'], 'memo' => 'Revaluation '.$a['code'].' — '.$note, 'lines' => array(
            array('account' => $a['asset_account'], 'debit' => $amount > 0 ? $amount : 0, 'credit' => $amount < 0 ? abs($amount) : 0, 'memo' => 'Revaluation '.$a['code'], 'entity' => 'pulse_acc_asset', 'id_entity' => (int) $id),
            array('account' => '3300', 'debit' => $amount < 0 ? abs($amount) : 0, 'credit' => $amount > 0 ? $amount : 0, 'memo' => 'Revaluation reserve '.$a['code']),
        )));
        // MySQL evaluates SET assignments left to right, so nbv already sees the new revaluation — do not add it twice
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_acc_asset` SET revaluation=ROUND(revaluation+'.$amount.',2), nbv=ROUND(cost+revaluation-accum_depreciation,2), date_upd=NOW() WHERE id_pulse_acc_asset='.(int) $id);
        self::event($id, 'revaluation', $date, $amount, $note, $idJ);
        return $idJ;
    }

    /** Impairment: impairment expense Dr, accumulated depreciation Cr. */
    public static function impair($id, $amount, $date, $note = '')
    {
        $a = self::asset($id);
        if (!$a) { throw new PrestaShopException('Unknown asset'); }
        $amount = round(abs((float) $amount), 2);
        if ($amount < 0.005) { throw new PrestaShopException('Impairment amount cannot be zero'); }
        $date = $date ? $date : PulseAccService::bd();
        $idJ = PulseAccJournal::post(array('type' => 'general', 'source' => 'asset', 'source_ref' => 'impair:'.(int) $id.':'.$date, 'business_date' => $date, 'reference' => $a['code'], 'memo' => 'Impairment '.$a['code'].' — '.$note, 'lines' => array(
            array('account' => '8900', 'debit' => $amount, 'memo' => 'Impairment '.$a['code'].' '.$a['name'], 'cost_centre' => $a['cost_centre']),
            array('account' => $a['accum_account'], 'credit' => $amount, 'memo' => 'Impairment '.$a['code'], 'entity' => 'pulse_acc_asset', 'id_entity' => (int) $id),
        )));
        // impairment is a memo column; the write-down itself lives in accum_depreciation, so nbv counts it once
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_acc_asset` SET impairment=ROUND(impairment+'.$amount.',2), accum_depreciation=ROUND(accum_depreciation+'.$amount.',2), nbv=ROUND(cost+revaluation-accum_depreciation,2), date_upd=NOW() WHERE id_pulse_acc_asset='.(int) $id);
        self::event($id, 'impairment', $date, $amount, $note, $idJ);
        return $idJ;
    }

    /**
     * Disposal: remove cost and accumulated depreciation, bring in the proceeds and post the
     * gain (4920) or loss (8700) on the difference against net book value.
     */
    public static function dispose($id, $proceeds, $date, $note = '', $proceedsAccount = null)
    {
        $a = self::asset($id);
        if (!$a) { throw new PrestaShopException('Unknown asset'); }
        if (in_array($a['status'], array('disposed', 'written_off'))) { throw new PrestaShopException('Asset '.$a['code'].' is already '.$a['status']); }
        $date = $date ? $date : PulseAccService::bd();
        $proceeds = round((float) $proceeds, 2);
        $cost = round((float) $a['cost'] + (float) $a['revaluation'], 2);
        $accum = round((float) $a['accum_depreciation'], 2);
        $nbv = round($cost - $accum, 2);
        $gain = round($proceeds - $nbv, 2);
        $debitAccount = $proceedsAccount ? $proceedsAccount : Configuration::get('PULSE_ACC_DEFAULT_BANK');
        $lines = array(
            array('account' => $a['accum_account'], 'debit' => $accum, 'memo' => 'Disposal — accumulated depreciation '.$a['code'], 'entity' => 'pulse_acc_asset', 'id_entity' => (int) $id),
            array('account' => $a['asset_account'], 'credit' => $cost, 'memo' => 'Disposal — cost '.$a['code'], 'entity' => 'pulse_acc_asset', 'id_entity' => (int) $id),
        );
        if ($proceeds > 0.004) { $lines[] = array('account' => $debitAccount, 'debit' => $proceeds, 'memo' => 'Proceeds on disposal of '.$a['code']); }
        if (abs($gain) > 0.004) { $lines[] = array('account' => $gain > 0 ? '4920' : '8700', 'debit' => $gain < 0 ? abs($gain) : 0, 'credit' => $gain > 0 ? $gain : 0, 'memo' => ($gain > 0 ? 'Gain' : 'Loss').' on disposal of '.$a['code'], 'cost_centre' => $a['cost_centre']); }
        $idJ = PulseAccJournal::post(array('type' => 'general', 'source' => 'asset', 'source_ref' => 'disposal:'.(int) $id, 'business_date' => $date, 'reference' => $a['code'], 'memo' => 'Disposal of '.$a['code'].' — '.$a['name'].($note ? ' ('.$note.')' : ''), 'lines' => $lines));
        Db::getInstance()->update('pulse_acc_asset', array('status' => 'disposed', 'disposal_date' => pSQL($date), 'disposal_proceeds' => $proceeds, 'disposal_gain_loss' => $gain, 'disposal_note' => pSQL(Tools::substr($note, 0, 255)), 'nbv' => 0, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_asset='.(int) $id);
        self::event($id, 'disposal', $date, $proceeds, ($gain >= 0 ? 'Gain ' : 'Loss ').number_format(abs($gain), 2).' on NBV '.number_format($nbv, 2).($note ? ' — '.$note : ''), $idJ);
        PulseCoreService::audit('pulseaccounts', 'asset_disposal', array('code' => $a['code'], 'proceeds' => $proceeds, 'nbv' => $nbv, 'gain' => $gain), 'pulse_acc_asset', (int) $id);
        return array('id_journal' => $idJ, 'nbv' => $nbv, 'gain' => $gain);
    }

    /** Write-off is a disposal with no proceeds; the whole remaining NBV goes to 8700. */
    public static function writeOff($id, $date, $note = '')
    {
        $r = self::dispose($id, 0, $date, $note ? $note : 'Written off');
        Db::getInstance()->update('pulse_acc_asset', array('status' => 'written_off'), 'id_pulse_acc_asset='.(int) $id);
        return $r;
    }

    /* ---------------- registers and forecasts ---------------- */

    /** Written-down-value register: cost, accumulated depreciation, NBV by class. */
    public static function wdvRegister($asOf = null)
    {
        $rows = self::assets(array('include_disposed' => true), 5000);
        $out = array(); $tot = array('cost' => 0, 'accum' => 0, 'nbv' => 0);
        foreach ($rows as $a) {
            if (in_array($a['status'], array('disposed', 'written_off'))) { continue; }
            $cost = round((float) $a['cost'] + (float) $a['revaluation'], 2);
            $accum = round((float) $a['accum_depreciation'], 2);
            $out[] = array('code' => $a['code'], 'name' => $a['name'], 'class' => $a['class_code'], 'cost_centre' => $a['cost_centre'], 'location' => $a['location'] ? $a['location'] : $a['room_num'],
                'in_service' => $a['in_service_date'], 'method' => $a['method'], 'cost' => $cost, 'accumulated' => $accum, 'nbv' => round($cost - $accum, 2), 'status' => $a['status']);
            $tot['cost'] += $cost; $tot['accum'] += $accum; $tot['nbv'] += round($cost - $accum, 2);
        }
        return array('as_of' => $asOf ? $asOf : date('Y-m-d'), 'rows' => $out, 'totals' => array_map(function ($v) { return round($v, 2); }, $tot));
    }

    /** Forecast the next N months of depreciation, class by class — the number the budget needs. */
    public static function forecast($months = 12, $fromPeriod = null)
    {
        $from = $fromPeriod ? $fromPeriod : date('Y-m');
        $assets = self::assets(array(), 5000);
        $out = array();
        $state = array();
        foreach ($assets as $a) { $state[(int) $a['id_pulse_acc_asset']] = $a; }
        for ($i = 0; $i < (int) $months; $i++) {
            $p = date('Y-m', strtotime($from.'-01 +'.$i.' month'));
            $row = array('period' => $p, 'total' => 0, 'by_class' => array());
            foreach ($state as $idA => $a) {
                if ($a['last_period'] && $a['last_period'] >= $p) { continue; }
                $charge = self::monthlyCharge($a, $p);
                if ($charge < 0.005) { continue; }
                $state[$idA]['accum_depreciation'] = round((float) $a['accum_depreciation'] + $charge, 2);
                if (!isset($row['by_class'][$a['class_code']])) { $row['by_class'][$a['class_code']] = 0; }
                $row['by_class'][$a['class_code']] = round($row['by_class'][$a['class_code']] + $charge, 2);
                $row['total'] = round($row['total'] + $charge, 2);
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * CAPEX against budget. Budget lines live in pulse_budget as capex:<class code> (Pulse Reports),
     * actuals are the additions capitalised in the period.
     */
    public static function capexVsBudget($year)
    {
        $year = (int) $year;
        $actual = Db::getInstance()->executeS('SELECT class_code, DATE_FORMAT(acquisition_date,"%m") m, ROUND(SUM(cost),2) spend, COUNT(*) items FROM `'._DB_PREFIX_.'pulse_acc_asset` WHERE YEAR(acquisition_date)='.$year.' GROUP BY class_code, m');
        $budget = array();
        if (PulseAccService::tableExists('pulse_budget')) {
            foreach (Db::getInstance()->executeS('SELECT `line`, `month`, amount FROM `'._DB_PREFIX_.'pulse_budget` WHERE `year`='.$year.' AND `line` LIKE "capex:%"') as $b) {
                $cls = Tools::substr($b['line'], 6);
                if (!isset($budget[$cls])) { $budget[$cls] = 0; }
                $budget[$cls] += (float) $b['amount'];
            }
        }
        $byClass = array();
        foreach ($actual as $a) {
            $c = $a['class_code'];
            if (!isset($byClass[$c])) { $byClass[$c] = array('class' => $c, 'spend' => 0, 'items' => 0, 'budget' => isset($budget[$c]) ? round($budget[$c], 2) : 0); }
            $byClass[$c]['spend'] = round($byClass[$c]['spend'] + (float) $a['spend'], 2);
            $byClass[$c]['items'] += (int) $a['items'];
        }
        foreach ($budget as $c => $amt) { if (!isset($byClass[$c])) { $byClass[$c] = array('class' => $c, 'spend' => 0, 'items' => 0, 'budget' => round($amt, 2)); } }
        foreach ($byClass as &$b) { $b['variance'] = round($b['budget'] - $b['spend'], 2); $b['used_pct'] = $b['budget'] > 0 ? round($b['spend'] / $b['budget'] * 100, 1) : null; }
        return array_values($byClass);
    }
}
