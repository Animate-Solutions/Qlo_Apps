<?php
/**
 * Room type + rate plan -> channel room code + rate code, with the derived-rate rules
 * (single supplement, extra adult, child) that OTAs expect per occupancy.
 * Unmapped inventory is the number one cause of overbooking, so unmapped() is surfaced loudly
 * on the dashboard and the mapping screen rather than hidden behind a filter.
 */
class PulseChMapping
{
    public static function all($idChannel = 0, $activeOnly = false)
    {
        return Db::getInstance()->executeS('SELECT m.*, pl.name room_type, rp.code rate_plan_code, rp.name rate_plan, rp.meal_plan, c.name channel, c.code channel_code, c.currency_iso,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` r WHERE r.id_product=m.id_product AND r.id_status=1) rooms
            FROM `'._DB_PREFIX_.'pulse_ch_mapping` m
            INNER JOIN `'._DB_PREFIX_.'pulse_ch_channel` c ON c.id_pulse_ch_channel=m.id_pulse_ch_channel
            LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=m.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' AND pl.id_shop='.(int) Context::getContext()->shop->id.'
            LEFT JOIN `'._DB_PREFIX_.'pulse_ch_rate_plan` rp ON rp.id_pulse_ch_rate_plan=m.id_pulse_ch_rate_plan
            WHERE 1'.($idChannel ? ' AND m.id_pulse_ch_channel='.(int) $idChannel : '').($activeOnly ? ' AND m.active=1' : '').'
            ORDER BY c.name, pl.name, rp.sort');
    }

    /** Active mappings a push actually uses: channel enabled, mapping active, codes filled in. */
    public static function pushable($idChannel)
    {
        return Db::getInstance()->executeS('SELECT m.*, rp.code rate_plan_code, rp.derive_from, rp.adjust_type rp_adjust_type, rp.adjust_value rp_adjust_value, rp.min_los rp_min_los, rp.max_los rp_max_los, rp.release_days rp_release_days
            FROM `'._DB_PREFIX_.'pulse_ch_mapping` m INNER JOIN `'._DB_PREFIX_.'pulse_ch_rate_plan` rp ON rp.id_pulse_ch_rate_plan=m.id_pulse_ch_rate_plan
            WHERE m.id_pulse_ch_channel='.(int) $idChannel.' AND m.active=1 AND rp.active=1 AND m.channel_room_code<>"" AND m.channel_rate_code<>"" ORDER BY m.id_product, rp.sort');
    }

    /** Room types with no active mapping on an enabled channel — the overbooking risk list. */
    public static function unmapped()
    {
        $out = array();
        foreach (PulseChService::channels(true) as $c) {
            foreach (Db::getInstance()->executeS(PulseChService::roomTypeSql()) as $rt) {
                $n = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_mapping` WHERE id_pulse_ch_channel='.(int) $c['id_pulse_ch_channel'].' AND id_product='.(int) $rt['id_product'].' AND active=1 AND channel_room_code<>""');
                if (!$n) { $out[] = array('id_pulse_ch_channel' => (int) $c['id_pulse_ch_channel'], 'channel' => $c['name'], 'id_product' => (int) $rt['id_product'], 'room_type' => $rt['name'], 'rooms' => (int) $rt['rooms']); }
            }
        }
        return $out;
    }

    /** Mappings whose codes are blank, or whose rate plan was deactivated — silently dead cells. */
    public static function broken()
    {
        return Db::getInstance()->executeS('SELECT m.*, c.name channel, pl.name room_type, rp.name rate_plan, rp.active rp_active
            FROM `'._DB_PREFIX_.'pulse_ch_mapping` m INNER JOIN `'._DB_PREFIX_.'pulse_ch_channel` c ON c.id_pulse_ch_channel=m.id_pulse_ch_channel
            LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=m.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' AND pl.id_shop='.(int) Context::getContext()->shop->id.'
            LEFT JOIN `'._DB_PREFIX_.'pulse_ch_rate_plan` rp ON rp.id_pulse_ch_rate_plan=m.id_pulse_ch_rate_plan
            WHERE m.active=1 AND (m.channel_room_code="" OR m.channel_rate_code="" OR rp.id_pulse_ch_rate_plan IS NULL OR rp.active=0 OR pl.id_product IS NULL)');
    }

    public static function save(array $d)
    {
        $id = (int) (isset($d['id_pulse_ch_mapping']) ? $d['id_pulse_ch_mapping'] : 0);
        $row = array(
            'id_pulse_ch_channel' => (int) $d['id_pulse_ch_channel'], 'id_product' => (int) $d['id_product'], 'id_pulse_ch_rate_plan' => (int) $d['id_pulse_ch_rate_plan'],
            'channel_room_code' => pSQL(trim($d['channel_room_code'])), 'channel_rate_code' => pSQL(trim($d['channel_rate_code'])),
            'base_occupancy' => max(1, (int) (isset($d['base_occupancy']) ? $d['base_occupancy'] : 2)), 'max_occupancy' => max(1, (int) (isset($d['max_occupancy']) ? $d['max_occupancy'] : 2)),
            'single_adj' => round((float) (isset($d['single_adj']) ? $d['single_adj'] : 0), 2), 'extra_adult_adj' => round((float) (isset($d['extra_adult_adj']) ? $d['extra_adult_adj'] : 0), 2), 'child_adj' => round((float) (isset($d['child_adj']) ? $d['child_adj'] : 0), 2),
            'rate_adjust_type' => pSQL(isset($d['rate_adjust_type']) ? $d['rate_adjust_type'] : 'none'), 'rate_adjust_value' => round((float) (isset($d['rate_adjust_value']) ? $d['rate_adjust_value'] : 0), 2),
            'allotment' => (int) (isset($d['allotment']) ? $d['allotment'] : 0), 'min_los' => (int) (isset($d['min_los']) ? $d['min_los'] : 0),
            'active' => !empty($d['active']) ? 1 : 0, 'date_upd' => date('Y-m-d H:i:s'),
        );
        if (!$row['id_pulse_ch_channel'] || !$row['id_product'] || !$row['id_pulse_ch_rate_plan']) { throw new PrestaShopException('Channel, room type and rate plan are all required'); }
        if ($id) { Db::getInstance()->update('pulse_ch_mapping', $row, 'id_pulse_ch_mapping='.$id); } else {
            $dup = (int) Db::getInstance()->getValue('SELECT id_pulse_ch_mapping FROM `'._DB_PREFIX_.'pulse_ch_mapping` WHERE id_pulse_ch_channel='.$row['id_pulse_ch_channel'].' AND id_product='.$row['id_product'].' AND id_pulse_ch_rate_plan='.$row['id_pulse_ch_rate_plan']);
            if ($dup) { Db::getInstance()->update('pulse_ch_mapping', $row, 'id_pulse_ch_mapping='.$dup); $id = $dup; } else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_ch_mapping', $row); $id = (int) Db::getInstance()->Insert_ID(); }
        }
        PulseChAri::markDirty($row['id_product'], PulseChService::businessDate(), date('Y-m-d', strtotime(PulseChService::businessDate().' +'.PulseChService::windowDays().' day')), 'mapping', $row['id_pulse_ch_channel']);
        PulseCoreService::audit('pulsechannel', 'mapping_save', $row, 'pulse_ch_mapping', $id);
        return $id;
    }

    public static function delete($id)
    {
        $m = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_mapping` WHERE id_pulse_ch_mapping='.(int) $id);
        if (!$m) { return false; }
        Db::getInstance()->delete('pulse_ch_ari', 'id_pulse_ch_channel='.(int) $m['id_pulse_ch_channel'].' AND id_product='.(int) $m['id_product'].' AND id_pulse_ch_rate_plan='.(int) $m['id_pulse_ch_rate_plan']);
        Db::getInstance()->delete('pulse_ch_mapping', 'id_pulse_ch_mapping='.(int) $id);
        PulseCoreService::audit('pulsechannel', 'mapping_delete', $m, 'pulse_ch_mapping', $id);
        return true;
    }

    /** Copy every mapping of one channel onto another (codes included) — how a second OTA gets set up in a minute. */
    public static function copyChannel($fromChannel, $toChannel)
    {
        $n = 0;
        foreach (self::all((int) $fromChannel) as $m) {
            $m['id_pulse_ch_channel'] = (int) $toChannel; unset($m['id_pulse_ch_mapping']);
            self::save($m); $n++;
        }
        return $n;
    }

    /** Resolve an inbound channel room/rate code back to our ids. Falls back to room code alone when the rate code is unknown. */
    public static function resolve($idChannel, $roomCode, $rateCode)
    {
        $m = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_mapping` WHERE id_pulse_ch_channel='.(int) $idChannel.' AND channel_room_code="'.pSQL(trim($roomCode)).'" AND channel_rate_code="'.pSQL(trim($rateCode)).'" AND active=1');
        if ($m) { return $m; }
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_mapping` WHERE id_pulse_ch_channel='.(int) $idChannel.' AND channel_room_code="'.pSQL(trim($roomCode)).'" AND active=1 ORDER BY id_pulse_ch_mapping LIMIT 1');
    }
}
