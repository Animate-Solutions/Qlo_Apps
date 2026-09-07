<?php
/** Requisitions (store issue or purchase) → purchase orders → goods received (GRN) → supplier invoice → expense ledger. */
class PulseInvPurchasing
{
    public static function createRequest($type, array $lines, array $d)
    {
        $no = PulseInvService::nextNo($type === 'purchase' ? 'PR' : 'SR');
        Db::getInstance()->insert('pulse_inv_request', array('req_no' => pSQL($no), 'type' => pSQL($type), 'id_store_from' => !empty($d['from']) ? (int) $d['from'] : null, 'id_store_to' => !empty($d['to']) ? (int) $d['to'] : null, 'department' => pSQL(isset($d['department']) ? $d['department'] : ''), 'note' => pSQL(isset($d['note']) ? $d['note'] : ''), 'requested_by' => PulseInvService::emp(), 'needed_by' => !empty($d['needed_by']) ? pSQL($d['needed_by']) : null, 'business_date' => PulseInvService::bd(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID(); $n = 0;
        foreach ($lines as $l) { if ((float) $l['qty'] <= 0) { continue; } Db::getInstance()->insert('pulse_inv_request_line', array('id_pulse_inv_request' => $id, 'id_pulse_inv_item' => (int) $l['id'], 'qty_requested' => (float) $l['qty'], 'note' => pSQL(isset($l['note']) ? $l['note'] : ''))); $n++; }
        if (!$n) { Db::getInstance()->delete('pulse_inv_request', 'id_pulse_inv_request='.$id); throw new PrestaShopException('No lines'); }
        PulseCoreService::audit('pulseinventory', 'request', $no, 'pulse_inv_request', $id); PulseCoreService::event('actionPulseInvRequest', array('id' => $id, 'type' => $type));
        return $id;
    }
    public static function approve($id, array $qtyApproved = array(), $reject = false)
    {
        Db::getInstance()->update('pulse_inv_request', array('status' => $reject ? 'rejected' : 'approved', 'approved_by' => PulseInvService::emp(), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_inv_request='.(int) $id);
        if (!$reject) { foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_request_line` WHERE id_pulse_inv_request='.(int) $id) as $l) { Db::getInstance()->update('pulse_inv_request_line', array('qty_approved' => isset($qtyApproved[$l['id_pulse_inv_request_line']]) ? (float) $qtyApproved[$l['id_pulse_inv_request_line']] : (float) $l['qty_requested']), 'id_pulse_inv_request_line='.(int) $l['id_pulse_inv_request_line']); } }
        return true;
    }
    /** Issue a store requisition: moves stock from → to. $issued = [line_id => qty]; missing = approved qty. */
    public static function issue($id, array $issued = array())
    {
        $r = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_request` WHERE id_pulse_inv_request='.(int) $id); if (!$r || $r['type'] !== 'store_issue') { throw new PrestaShopException('Not a store requisition'); }
        if (!in_array($r['status'], array('submitted', 'approved', 'partially_issued'))) { throw new PrestaShopException('Requisition is '.$r['status']); }
        $all = true;
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_request_line` WHERE id_pulse_inv_request='.(int) $id) as $l) {
            $target = $l['qty_approved'] !== null ? (float) $l['qty_approved'] : (float) $l['qty_requested']; $q = isset($issued[$l['id_pulse_inv_request_line']]) ? (float) $issued[$l['id_pulse_inv_request_line']] : max(0, $target - (float) $l['qty_issued']);
            $q = min($q, max(0, $target - (float) $l['qty_issued'])); if ($q <= 0) { if ((float) $l['qty_issued'] < $target) { $all = false; } continue; }
            PulseInvService::transfer($l['id_pulse_inv_item'], $r['id_store_from'], $r['id_store_to'], $q, $r['req_no'], $r['department']);
            Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_inv_request_line` SET qty_issued=qty_issued+'.$q.' WHERE id_pulse_inv_request_line='.(int) $l['id_pulse_inv_request_line']);
            if ((float) $l['qty_issued'] + $q < $target - 0.0005) { $all = false; }
        }
        Db::getInstance()->update('pulse_inv_request', array('status' => $all ? 'issued' : 'partially_issued', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_inv_request='.(int) $id);
        PulseCoreService::audit('pulseinventory', 'issue', $r['req_no'], 'pulse_inv_request', $id); return $all;
    }

    /* ---------- purchase orders ---------- */
    public static function createPo($idSupplier, array $lines, array $d = array())
    {
        $no = PulseInvService::nextNo('PO'); $sub = 0; $tax = 0;
        Db::getInstance()->insert('pulse_inv_po', array('po_no' => pSQL($no), 'id_pulse_inv_supplier' => (int) $idSupplier, 'id_pulse_inv_request' => !empty($d['id_request']) ? (int) $d['id_request'] : null, 'status' => 'draft', 'expected_date' => !empty($d['expected_date']) ? pSQL($d['expected_date']) : null, 'note' => pSQL(isset($d['note']) ? $d['note'] : ''), 'created_by' => PulseInvService::emp(), 'business_date' => PulseInvService::bd(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID();
        foreach ($lines as $l) { if ((float) $l['qty'] <= 0) { continue; } $price = isset($l['price']) && $l['price'] !== '' ? (float) $l['price'] : self::supplierPrice($idSupplier, $l['id']); $t = isset($l['tax_pct']) ? (float) $l['tax_pct'] : 0; Db::getInstance()->insert('pulse_inv_po_line', array('id_pulse_inv_po' => $id, 'id_pulse_inv_item' => (int) $l['id'], 'qty_ordered' => (float) $l['qty'], 'unit_price' => $price, 'tax_pct' => $t)); $sub += $l['qty'] * $price; $tax += $l['qty'] * $price * $t / 100; }
        Db::getInstance()->update('pulse_inv_po', array('subtotal' => round($sub, 2), 'tax' => round($tax, 2), 'total' => round($sub + $tax, 2)), 'id_pulse_inv_po='.$id);
        if (!empty($d['id_request'])) { Db::getInstance()->update('pulse_inv_request', array('status' => 'ordered', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_inv_request='.(int) $d['id_request']); }
        PulseCoreService::audit('pulseinventory', 'po_create', $no, 'pulse_inv_po', $id); return $id;
    }
    public static function supplierPrice($idSupplier, $idItem) { $p = Db::getInstance()->getValue('SELECT price FROM `'._DB_PREFIX_.'pulse_inv_supplier_price` WHERE id_pulse_inv_supplier='.(int) $idSupplier.' AND id_pulse_inv_item='.(int) $idItem); if ($p !== false && $p !== null) { return (float) $p; } $i = PulseInvService::item($idItem); return round((float) $i['last_cost'] * (float) $i['purchase_factor'], 2); }
    public static function setPoStatus($id, $status) { Db::getInstance()->update('pulse_inv_po', array('status' => pSQL($status), 'approved_by' => $status === 'sent' ? PulseInvService::emp() : null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_inv_po='.(int) $id); PulseCoreService::audit('pulseinventory', 'po_'.$status, '', 'pulse_inv_po', $id); }
    public static function po($id) { $p = Db::getInstance()->getRow('SELECT p.*, s.name supplier, s.email, s.phone FROM `'._DB_PREFIX_.'pulse_inv_po` p INNER JOIN `'._DB_PREFIX_.'pulse_inv_supplier` s ON s.id_pulse_inv_supplier=p.id_pulse_inv_supplier WHERE p.id_pulse_inv_po='.(int) $id); if ($p) { $p['lines'] = Db::getInstance()->executeS('SELECT l.*, i.name, i.sku, i.purchase_unit, i.unit, i.purchase_factor, i.track_expiry, i.track_batch FROM `'._DB_PREFIX_.'pulse_inv_po_line` l INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=l.id_pulse_inv_item WHERE l.id_pulse_inv_po='.(int) $id); } return $p; }

    /** Goods received: $lines = [[id_item, qty (purchase units), unit_price, tax_pct, batch_no, expiry, rejected_qty, reject_reason]]. Updates stock (stock units), average cost, PO progress, supplier price, expense ledger. */
    public static function receive($idSupplier, $idStore, array $lines, array $d = array())
    {
        $no = PulseInvService::nextNo('GRN'); $total = 0; $idPo = !empty($d['id_po']) ? (int) $d['id_po'] : null;
        Db::getInstance()->insert('pulse_inv_grn', array('grn_no' => pSQL($no), 'id_pulse_inv_po' => $idPo, 'id_pulse_inv_supplier' => (int) $idSupplier, 'id_pulse_inv_store' => (int) $idStore, 'invoice_no' => pSQL(isset($d['invoice_no']) ? $d['invoice_no'] : ''), 'invoice_date' => !empty($d['invoice_date']) ? pSQL($d['invoice_date']) : null, 'invoice_total' => isset($d['invoice_total']) && $d['invoice_total'] !== '' ? (float) $d['invoice_total'] : null, 'status' => !empty($d['invoice_no']) ? 'invoiced' : 'received', 'received_by' => PulseInvService::emp(), 'note' => pSQL(isset($d['note']) ? $d['note'] : ''), 'business_date' => PulseInvService::bd(), 'date_add' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID(); $expCode = null;
        foreach ($lines as $l) {
            $q = (float) $l['qty']; if ($q <= 0) { continue; } $item = PulseInvService::item($l['id']); $accepted = $q - (float) (isset($l['rejected_qty']) ? $l['rejected_qty'] : 0); $price = (float) $l['unit_price']; $tax = (float) (isset($l['tax_pct']) ? $l['tax_pct'] : 0);
            Db::getInstance()->insert('pulse_inv_grn_line', array('id_pulse_inv_grn' => $id, 'id_pulse_inv_item' => (int) $l['id'], 'qty' => $q, 'unit_price' => $price, 'tax_pct' => $tax, 'batch_no' => pSQL(isset($l['batch_no']) ? $l['batch_no'] : ''), 'expiry' => !empty($l['expiry']) ? pSQL($l['expiry']) : null, 'rejected_qty' => (float) (isset($l['rejected_qty']) ? $l['rejected_qty'] : 0), 'reject_reason' => pSQL(isset($l['reject_reason']) ? $l['reject_reason'] : '')));
            if ($accepted > 0) { $stockQty = $accepted * (float) $item['purchase_factor']; $unitCostStock = $price / max(0.000001, (float) $item['purchase_factor']); PulseInvService::move($item['id_pulse_inv_item'], $idStore, 'receive', $stockQty, $unitCostStock, array('reference' => $no, 'batch_no' => isset($l['batch_no']) ? $l['batch_no'] : '', 'expiry' => isset($l['expiry']) ? $l['expiry'] : null, 'reason' => 'GRN')); }
            $total += $accepted * $price * (1 + $tax / 100); $expCode = $expCode ?: $item['expense_code'];
            Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_inv_supplier_price` (id_pulse_inv_supplier,id_pulse_inv_item,price,date_upd) VALUES ('.(int) $idSupplier.','.(int) $l['id'].','.$price.',NOW()) ON DUPLICATE KEY UPDATE price=VALUES(price), date_upd=NOW()');
            if ($idPo) { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_inv_po_line` SET qty_received=qty_received+'.$accepted.' WHERE id_pulse_inv_po='.$idPo.' AND id_pulse_inv_item='.(int) $l['id']); }
        }
        Db::getInstance()->update('pulse_inv_grn', array('total' => round($total, 2)), 'id_pulse_inv_grn='.$id);
        if ($idPo) { $open = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_inv_po_line` WHERE id_pulse_inv_po='.$idPo.' AND qty_received<qty_ordered-0.0005'); self::setPoStatus($idPo, $open ? 'partially_received' : 'received'); }
        if (class_exists('PulseExpense') && $total > 0) { $sup = Db::getInstance()->getRow('SELECT name FROM `'._DB_PREFIX_.'pulse_inv_supplier` WHERE id_pulse_inv_supplier='.(int) $idSupplier); $idExp = PulseExpense::add(array('category' => $expCode ?: 'MISC', 'department' => isset($d['department']) ? $d['department'] : 'general', 'description' => 'Goods received '.$no.' — '.$sup['name'], 'payee' => $sup['name'], 'amount' => round($total, 2), 'payment_method' => 'credit', 'reference' => isset($d['invoice_no']) ? $d['invoice_no'] : $no, 'source' => 'inventory', 'source_ref' => $no)); if ($idExp) { Db::getInstance()->update('pulse_inv_grn', array('id_pulse_expense' => (int) $idExp), 'id_pulse_inv_grn='.$id); } }
        PulseCoreService::audit('pulseinventory', 'grn', $no.' '.round($total, 2), 'pulse_inv_grn', $id); PulseCoreService::event('actionPulseInvReceived', array('id' => $id, 'grn' => $no, 'total' => $total));
        return $id;
    }
    public static function supplierPerformance($from, $to) { return Db::getInstance()->executeS('SELECT s.name, COUNT(DISTINCT g.id_pulse_inv_grn) deliveries, ROUND(SUM(g.total),2) value, SUM(gl.rejected_qty>0) rejected_lines, ROUND(AVG(DATEDIFF(g.business_date, p.business_date)),1) avg_lead_days, SUM(p.expected_date IS NOT NULL AND g.business_date>p.expected_date) late FROM `'._DB_PREFIX_.'pulse_inv_grn` g INNER JOIN `'._DB_PREFIX_.'pulse_inv_supplier` s ON s.id_pulse_inv_supplier=g.id_pulse_inv_supplier LEFT JOIN `'._DB_PREFIX_.'pulse_inv_grn_line` gl ON gl.id_pulse_inv_grn=g.id_pulse_inv_grn LEFT JOIN `'._DB_PREFIX_.'pulse_inv_po` p ON p.id_pulse_inv_po=g.id_pulse_inv_po WHERE g.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY s.id_pulse_inv_supplier ORDER BY value DESC'); }
    public static function priceHistory($idItem) { return Db::getInstance()->executeS('SELECT g.business_date, s.name supplier, gl.unit_price, gl.qty FROM `'._DB_PREFIX_.'pulse_inv_grn_line` gl INNER JOIN `'._DB_PREFIX_.'pulse_inv_grn` g ON g.id_pulse_inv_grn=gl.id_pulse_inv_grn INNER JOIN `'._DB_PREFIX_.'pulse_inv_supplier` s ON s.id_pulse_inv_supplier=g.id_pulse_inv_supplier WHERE gl.id_pulse_inv_item='.(int) $idItem.' ORDER BY g.business_date DESC LIMIT 20'); }
}
