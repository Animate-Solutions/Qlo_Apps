<?php
/** Stock counts: open a count sheet (system qty frozen), enter counted, post variances as count_adjust movements. */
class PulseInvCount
{
    public static function open($idStore, $type = 'full', array $itemIds = array(), $note = '')
    {
        if (Db::getInstance()->getValue('SELECT id_pulse_inv_count FROM `'._DB_PREFIX_.'pulse_inv_count` WHERE id_pulse_inv_store='.(int) $idStore.' AND status IN ("open","counted")')) { throw new PrestaShopException('A count is already open for this store'); }
        $no = PulseInvService::nextNo('CNT');
        Db::getInstance()->insert('pulse_inv_count', array('count_no' => pSQL($no), 'id_pulse_inv_store' => (int) $idStore, 'type' => pSQL($type), 'note' => pSQL($note), 'counted_by' => PulseInvService::emp(), 'business_date' => PulseInvService::bd(), 'date_add' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID();
        $where = $type === 'full' ? 'i.active=1' : 'i.id_pulse_inv_item IN ('.implode(',', array_map('intval', $itemIds) ?: array(0)).')';
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_inv_count_line` (id_pulse_inv_count,id_pulse_inv_item,system_qty,unit_cost) SELECT '.$id.', i.id_pulse_inv_item, COALESCE(s.qty,0), i.avg_cost FROM `'._DB_PREFIX_.'pulse_inv_item` i LEFT JOIN `'._DB_PREFIX_.'pulse_inv_stock` s ON s.id_pulse_inv_item=i.id_pulse_inv_item AND s.id_pulse_inv_store='.(int) $idStore.' WHERE '.$where.($type === 'full' ? ' AND (s.qty IS NOT NULL OR i.id_pulse_inv_item IN (SELECT id_pulse_inv_item FROM `'._DB_PREFIX_.'pulse_inv_movement` WHERE id_pulse_inv_store='.(int) $idStore.'))' : ''));
        return $id;
    }
    public static function enter($idCount, array $counted) { foreach ($counted as $idLine => $q) { if ($q === '' || $q === null) { continue; } Db::getInstance()->update('pulse_inv_count_line', array('counted_qty' => (float) $q), 'id_pulse_inv_count_line='.(int) $idLine.' AND id_pulse_inv_count='.(int) $idCount); } Db::getInstance()->update('pulse_inv_count', array('status' => 'counted'), 'id_pulse_inv_count='.(int) $idCount.' AND status="open"'); return true; }
    public static function post($idCount)
    {
        $c = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_count` WHERE id_pulse_inv_count='.(int) $idCount); if (!$c || $c['status'] === 'posted') { return false; }
        $val = 0;
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_inv_count_line` WHERE id_pulse_inv_count='.(int) $idCount.' AND counted_qty IS NOT NULL') as $l) {
            $cur = PulseInvService::qty($l['id_pulse_inv_item'], $c['id_pulse_inv_store']); $diff = round((float) $l['counted_qty'] - $cur, 3); // adjust against live qty, not the frozen figure, so sales during the count are respected
            if (abs($diff) < 0.0005) { continue; }
            PulseInvService::move($l['id_pulse_inv_item'], $c['id_pulse_inv_store'], 'count_adjust', $diff, null, array('reference' => $c['count_no'], 'reason' => 'stock count', 'force' => 1)); $val += $diff * (float) $l['unit_cost'];
        }
        Db::getInstance()->update('pulse_inv_count', array('status' => 'posted', 'approved_by' => PulseInvService::emp(), 'variance_value' => round($val, 2), 'date_posted' => date('Y-m-d H:i:s')), 'id_pulse_inv_count='.(int) $idCount);
        PulseCoreService::audit('pulseinventory', 'count_post', $c['count_no'].' variance '.round($val, 2), 'pulse_inv_count', $idCount); return round($val, 2);
    }
    public static function sheet($idCount) { $c = Db::getInstance()->getRow('SELECT c.*, st.name store FROM `'._DB_PREFIX_.'pulse_inv_count` c INNER JOIN `'._DB_PREFIX_.'pulse_inv_store` st ON st.id_pulse_inv_store=c.id_pulse_inv_store WHERE c.id_pulse_inv_count='.(int) $idCount); if ($c) { $c['lines'] = Db::getInstance()->executeS('SELECT l.*, i.sku, i.name, i.unit, (l.counted_qty-l.system_qty) variance, ROUND((l.counted_qty-l.system_qty)*l.unit_cost,2) variance_value FROM `'._DB_PREFIX_.'pulse_inv_count_line` l INNER JOIN `'._DB_PREFIX_.'pulse_inv_item` i ON i.id_pulse_inv_item=l.id_pulse_inv_item WHERE l.id_pulse_inv_count='.(int) $idCount.' ORDER BY i.name'); } return $c; }
}
