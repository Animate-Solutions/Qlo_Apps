<?php
/** Core stock engine: weighted-average costing, per-store balances, batches/expiry, all movements, links to POS ingredients and Maintenance parts. */
class PulseInvService
{
    public static function bd() { return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    public static function emp() { $c = Context::getContext(); return isset($c->employee) && $c->employee ? (int) $c->employee->id : 0; }
    public static function nextNo($p) { $n = (int) PulseCoreService::setting('pulseinventory', 'seq_'.$p) + 1; PulseCoreService::setting('pulseinventory', 'seq_'.$p, $n); return $p.date('ym').str_pad($n % 100000, 5, '0', STR_PAD_LEFT); }
    public static function storeId($code) { return (int) Db::getInstance()->getValue('SELECT id_pulse_inv_store FROM `'._DB_PREFIX_.'pulse_inv_store` WHERE code="'.pSQL($code).'"'); }
    public static function item($id) { return Db::getInstance()->getRow('SELECT i.*, c.name category, c.group_name, c.expense_code FROM `'._DB_PREFIX_.'pulse_inv_item` i INNER JOIN `'._DB_PREFIX_.'pulse_inv_category` c ON c.id_pulse_inv_category=i.id_pulse_inv_category WHERE i.id_pulse_inv_item='.(int) $id); }
    public static function qty($idItem, $idStore) { return (float) Db::getInstance()->getValue('SELECT qty FROM `'._DB_PREFIX_.'pulse_inv_stock` WHERE id_pulse_inv_item='.(int) $idItem.' AND id_pulse_inv_store='.(int) $idStore); }
    public static function totalQty($idItem) { return (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(qty),0) FROM `'._DB_PREFIX_.'pulse_inv_stock` WHERE id_pulse_inv_item='.(int) $idItem); }

    /**
     * The single write path. $qty is signed (+in, -out) in STOCK units. $unitCost only for inbound (receive/opening/transfer_in/return); outbound uses avg cost.
     */
    public static function move($idItem, $idStore, $type, $qty, $unitCost = null, array $x = array())
    {
        $qty = round((float) $qty, 3); if ($qty == 0) { return false; }
        $item = self::item($idItem); if (!$item) { throw new PrestaShopException('Item not found'); }
        $store = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_store` WHERE id_pulse_inv_store='.(int) $idStore); if (!$store) { throw new PrestaShopException('Store not found'); }
        $cur = self::qty($idItem, $idStore);
        if ($qty < 0 && $cur + $qty < -0.0005 && !$store['allow_negative'] && empty($x['force'])) { throw new PrestaShopException('Insufficient stock of '.$item['name'].' in '.$store['name'].' ('.$cur.' '.$item['unit'].')'); }
        if ($qty > 0 && $unitCost !== null) { // weighted average across all stores
            $tot = self::totalQty($idItem); $newAvg = ($tot + $qty) > 0 ? (($tot * $item['avg_cost']) + ($qty * $unitCost)) / ($tot + $qty) : $unitCost;
            Db::getInstance()->update('pulse_inv_item', array('avg_cost' => round($newAvg, 6), 'last_cost' => (float) $unitCost, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_inv_item='.(int) $idItem);
            $cost = (float) $unitCost;
        } else { $cost = (float) $item['avg_cost']; }
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_inv_stock` (id_pulse_inv_item,id_pulse_inv_store,qty,date_upd) VALUES ('.(int) $idItem.','.(int) $idStore.','.$qty.',NOW()) ON DUPLICATE KEY UPDATE qty=qty+('.$qty.'), date_upd=NOW()');
        // batches: inbound creates, outbound consumes FEFO
        $idBatch = null;
        if ($item['track_batch'] || $item['track_expiry']) {
            if ($qty > 0) { Db::getInstance()->insert('pulse_inv_batch', array('id_pulse_inv_item' => (int) $idItem, 'id_pulse_inv_store' => (int) $idStore, 'batch_no' => pSQL(isset($x['batch_no']) ? $x['batch_no'] : ''), 'expiry' => !empty($x['expiry']) ? pSQL($x['expiry']) : null, 'qty' => $qty, 'unit_cost' => $cost, 'grn_no' => pSQL(isset($x['reference']) ? $x['reference'] : ''), 'date_add' => date('Y-m-d H:i:s'))); $idBatch = (int) Db::getInstance()->Insert_ID(); }
            else { $left = -$qty; foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_batch` WHERE id_pulse_inv_item='.(int) $idItem.' AND id_pulse_inv_store='.(int) $idStore.' AND qty>0 ORDER BY expiry IS NULL, expiry, id_pulse_inv_batch') as $b) { $take = min($left, (float) $b['qty']); Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_inv_batch` SET qty=qty-'.$take.' WHERE id_pulse_inv_batch='.(int) $b['id_pulse_inv_batch']); $idBatch = (int) $b['id_pulse_inv_batch']; $left -= $take; if ($left <= 0.0005) { break; } } }
        }
        Db::getInstance()->insert('pulse_inv_movement', array('id_pulse_inv_item' => (int) $idItem, 'id_pulse_inv_store' => (int) $idStore, 'type' => pSQL($type), 'qty' => $qty, 'unit_cost' => $cost, 'value' => round($qty * $cost, 4), 'department' => pSQL(isset($x['department']) ? $x['department'] : $store['department']), 'cost_centre' => pSQL(isset($x['cost_centre']) ? $x['cost_centre'] : ''), 'reference' => pSQL(isset($x['reference']) ? $x['reference'] : ''), 'reason' => pSQL(isset($x['reason']) ? $x['reason'] : ''), 'id_room' => !empty($x['id_room']) ? (int) $x['id_room'] : null, 'id_pulse_inv_batch' => $idBatch, 'id_employee' => isset($x['emp']) ? (int) $x['emp'] : self::emp(), 'business_date' => pSQL(isset($x['business_date']) ? $x['business_date'] : self::bd()), 'date_add' => date('Y-m-d H:i:s')));
        $idMove = (int) Db::getInstance()->Insert_ID();
        if (empty($x['no_sync'])) { self::syncLinked($item, $idStore, $type, $qty, $cost, isset($x['reference']) ? $x['reference'] : ''); }
        return $idMove;
    }

    /** Transfer between stores (two movements). Outbound uses avg cost; inbound carries the same cost so valuation is unchanged. */
    public static function transfer($idItem, $fromStore, $toStore, $qty, $reference = '', $reason = '')
    {
        if ((int) $fromStore === (int) $toStore) { throw new PrestaShopException('Same store'); }
        $item = self::item($idItem); $cost = (float) $item['avg_cost'];
        self::move($idItem, $fromStore, 'transfer_out', -abs($qty), null, array('reference' => $reference, 'reason' => $reason));
        self::move($idItem, $toStore, 'transfer_in', abs($qty), null, array('reference' => $reference, 'reason' => $reason, 'no_avg' => 1));
        return true;
    }

    /** Push movements to linked POS ingredient / Maintenance part so those modules see the same quantities. */
    protected static function syncLinked(array $item, $idStore, $type, $qty, $cost, $ref)
    {
        if (!$item['link_type'] || !$item['link_id']) { return; }
        if ($item['link_type'] === 'pos_ingredient' && Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_pos_ingredient"')) {
            // POS ingredient balance = sum of the F&B stores (kitchen + bar) so recipe deduction sees usable stock
            $fb = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(s.qty),0) FROM `'._DB_PREFIX_.'pulse_inv_stock` s INNER JOIN `'._DB_PREFIX_.'pulse_inv_store` st ON st.id_pulse_inv_store=s.id_pulse_inv_store WHERE s.id_pulse_inv_item='.(int) $item['id_pulse_inv_item'].' AND st.department="fnb"');
            Db::getInstance()->update('pulse_pos_ingredient', array('qty_on_hand' => $fb, 'unit_cost' => (float) $item['avg_cost']), 'id_pulse_pos_ingredient='.(int) $item['link_id']);
        }
        if ($item['link_type'] === 'maintenance_part' && Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_part"')) {
            Db::getInstance()->update('pulse_part', array('qty_on_hand' => self::totalQty($item['id_pulse_inv_item']), 'unit_cost' => (float) $item['avg_cost']), 'id_pulse_part='.(int) $item['link_id']);
        }
    }

    /** Pull: a POS recipe deduction / waste happened → mirror it against the item's F&B store (kitchen for food, bar for drinks). */
    public static function onPosStockMove(array $m)
    {
        $item = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_item` WHERE link_type="pos_ingredient" AND link_id='.(int) $m['id_ingredient']); if (!$item) { return; }
        if (in_array($m['type'], array('purchase', 'count', 'transfer'))) { return; } // those originate from inventory when linked
        $grp = Db::getInstance()->getValue('SELECT group_name FROM `'._DB_PREFIX_.'pulse_pos_ingredient` WHERE id_pulse_pos_ingredient='.(int) $m['id_ingredient']);
        $store = self::storeId(in_array($grp, array('beverage', 'liquor')) ? 'BAR' : 'KITCHEN') ?: self::storeId('MAIN');
        $type = $m['type'] === 'sale' ? 'consume' : ($m['type'] === 'waste' ? 'waste' : 'return');
        try { self::move($item['id_pulse_inv_item'], $store, $type, (float) $m['qty'], null, array('reference' => 'POS '.$m['reference'], 'reason' => isset($m['reason']) ? $m['reason'] : '', 'department' => 'fnb', 'no_sync' => 1, 'force' => 1)); } catch (Exception $e) { PulseCoreService::audit('pulseinventory', 'sync_error', $e->getMessage()); }
    }
    public static function onPartMove(array $m)
    {
        $item = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_item` WHERE link_type="maintenance_part" AND link_id='.(int) $m['id_part']); if (!$item) { return; }
        if ($m['type'] === 'receive' || $m['type'] === 'adjust') { return; }
        $store = self::storeId('ENG') ?: self::storeId('MAIN');
        try { self::move($item['id_pulse_inv_item'], $store, $m['type'] === 'issue' ? 'consume' : 'return', $m['type'] === 'issue' ? -abs($m['qty']) : abs($m['qty']), null, array('reference' => isset($m['reference']) ? $m['reference'] : 'WO', 'department' => 'maintenance', 'no_sync' => 1, 'force' => 1)); } catch (Exception $e) { PulseCoreService::audit('pulseinventory', 'sync_error', $e->getMessage()); }
    }

    /** Create/refresh linked inventory items from POS ingredients and Maintenance parts (idempotent). */
    public static function importLinked()
    {
        $n = 0;
        if (Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_pos_ingredient"')) {
            $cat = array('food' => 'FOOD', 'beverage' => 'BEV', 'liquor' => 'LIQ', 'consumable' => 'CLEAN');
            foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_ingredient`') as $g) {
                if (Db::getInstance()->getValue('SELECT id_pulse_inv_item FROM `'._DB_PREFIX_.'pulse_inv_item` WHERE link_type="pos_ingredient" AND link_id='.(int) $g['id_pulse_pos_ingredient'])) { continue; }
                $idCat = (int) Db::getInstance()->getValue('SELECT id_pulse_inv_category FROM `'._DB_PREFIX_.'pulse_inv_category` WHERE code="'.pSQL(isset($cat[$g['group_name']]) ? $cat[$g['group_name']] : 'FOOD').'"');
                Db::getInstance()->insert('pulse_inv_item', array('sku' => pSQL('FB-'.$g['sku']), 'name' => pSQL($g['name']), 'id_pulse_inv_category' => $idCat, 'unit' => pSQL($g['unit']), 'avg_cost' => (float) $g['unit_cost'], 'last_cost' => (float) $g['unit_cost'], 'reorder_level' => (float) $g['reorder_level'], 'link_type' => 'pos_ingredient', 'link_id' => (int) $g['id_pulse_pos_ingredient'], 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
                $id = (int) Db::getInstance()->Insert_ID(); $store = self::storeId(in_array($g['group_name'], array('beverage', 'liquor')) ? 'BAR' : 'KITCHEN');
                if ((float) $g['qty_on_hand'] > 0) { self::move($id, $store, 'opening', (float) $g['qty_on_hand'], (float) $g['unit_cost'], array('reference' => 'POS import', 'no_sync' => 1)); }
                $n++;
            }
        }
        if (Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_part"')) {
            $idCat = (int) Db::getInstance()->getValue('SELECT id_pulse_inv_category FROM `'._DB_PREFIX_.'pulse_inv_category` WHERE code="ENG"');
            foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_part`') as $p) {
                if (Db::getInstance()->getValue('SELECT id_pulse_inv_item FROM `'._DB_PREFIX_.'pulse_inv_item` WHERE link_type="maintenance_part" AND link_id='.(int) $p['id_pulse_part'])) { continue; }
                Db::getInstance()->insert('pulse_inv_item', array('sku' => pSQL('EN-'.$p['sku']), 'name' => pSQL($p['name']), 'id_pulse_inv_category' => $idCat, 'unit' => pSQL($p['unit']), 'avg_cost' => (float) $p['unit_cost'], 'last_cost' => (float) $p['unit_cost'], 'reorder_level' => (float) $p['reorder_level'], 'link_type' => 'maintenance_part', 'link_id' => (int) $p['id_pulse_part'], 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
                $id = (int) Db::getInstance()->Insert_ID(); if ((float) $p['qty_on_hand'] > 0) { self::move($id, self::storeId('ENG'), 'opening', (float) $p['qty_on_hand'], (float) $p['unit_cost'], array('reference' => 'Maintenance import', 'no_sync' => 1)); } $n++;
            }
        }
        return $n;
    }

    /* ---------- queries ---------- */
    public static function stockList($idStore = null, $idCat = null, $lowOnly = false)
    {
        return Db::getInstance()->executeS('SELECT i.*, c.name category, c.group_name, COALESCE(SUM(s.qty),0) qty, ROUND(COALESCE(SUM(s.qty),0)*i.avg_cost,2) value, GROUP_CONCAT(CONCAT(st.code,":",ROUND(s.qty,2)) ORDER BY st.code SEPARATOR " ") by_store, sup.name supplier
            FROM `'._DB_PREFIX_.'pulse_inv_item` i INNER JOIN `'._DB_PREFIX_.'pulse_inv_category` c ON c.id_pulse_inv_category=i.id_pulse_inv_category LEFT JOIN `'._DB_PREFIX_.'pulse_inv_stock` s ON s.id_pulse_inv_item=i.id_pulse_inv_item'.($idStore ? ' AND s.id_pulse_inv_store='.(int) $idStore : '').' LEFT JOIN `'._DB_PREFIX_.'pulse_inv_store` st ON st.id_pulse_inv_store=s.id_pulse_inv_store LEFT JOIN `'._DB_PREFIX_.'pulse_inv_supplier` sup ON sup.id_pulse_inv_supplier=i.id_supplier_pref
            WHERE i.active=1'.($idCat ? ' AND i.id_pulse_inv_category='.(int) $idCat : '').' GROUP BY i.id_pulse_inv_item'.($lowOnly ? ' HAVING qty<=i.reorder_level' : '').' ORDER BY c.group_name, i.name');
    }
    public static function reorderSuggestions()
    {
        $rows = self::stockList(null, null, true); $out = array();
        foreach ($rows as $r) { $onOrder = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM((l.qty_ordered-l.qty_received)*i.purchase_factor),0) FROM `'._DB_PREFIX_.'pulse_inv_po_line` l INNER JOIN `'._DB_PREFIX_.'pulse_inv_po` p ON p.id_pulse_inv_po=l.id_pulse_inv_po INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=l.id_pulse_inv_item WHERE l.id_pulse_inv_item='.(int) $r['id_pulse_inv_item'].' AND p.status IN ("sent","partially_received")'); $need = max((float) $r['reorder_qty'], ((float) ($r['max_level'] ?: $r['reorder_level'] * 2)) - $r['qty'] - $onOrder); if ($need > 0) { $r['on_order'] = $onOrder; $r['suggest_qty'] = round($need, 3); $r['suggest_purchase_units'] = ceil($need / max(0.000001, (float) $r['purchase_factor'])); $out[] = $r; } }
        return $out;
    }
    public static function expiring($days = 30) { return Db::getInstance()->executeS('SELECT b.*, i.name, i.unit, st.name store FROM `'._DB_PREFIX_.'pulse_inv_batch` b INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=b.id_pulse_inv_item INNER JOIN `'._DB_PREFIX_.'pulse_inv_store` st ON st.id_pulse_inv_store=b.id_pulse_inv_store WHERE b.qty>0 AND b.expiry IS NOT NULL AND b.expiry<=DATE_ADD(CURDATE(), INTERVAL '.(int) $days.' DAY) ORDER BY b.expiry'); }
    public static function movements($from, $to, $idStore = null, $idItem = null, $type = null) { return Db::getInstance()->executeS('SELECT m.*, i.name, i.unit, st.code store, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_inv_movement` m INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=m.id_pulse_inv_item INNER JOIN `'._DB_PREFIX_.'pulse_inv_store` st ON st.id_pulse_inv_store=m.id_pulse_inv_store LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=m.id_employee WHERE m.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'.($idStore ? ' AND m.id_pulse_inv_store='.(int) $idStore : '').($idItem ? ' AND m.id_pulse_inv_item='.(int) $idItem : '').($type ? ' AND m.type="'.pSQL($type).'"' : '').' ORDER BY m.id_pulse_inv_movement DESC LIMIT 500'); }
    public static function valuation() { return Db::getInstance()->executeS('SELECT c.group_name, st.name store, ROUND(SUM(s.qty*i.avg_cost),2) value, COUNT(DISTINCT i.id_pulse_inv_item) items FROM `'._DB_PREFIX_.'pulse_inv_stock` s INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=s.id_pulse_inv_item INNER JOIN `'._DB_PREFIX_.'pulse_inv_category` c ON c.id_pulse_inv_category=i.id_pulse_inv_category INNER JOIN `'._DB_PREFIX_.'pulse_inv_store` st ON st.id_pulse_inv_store=s.id_pulse_inv_store WHERE s.qty<>0 GROUP BY c.group_name, st.id_pulse_inv_store ORDER BY c.group_name, st.name'); }
}
