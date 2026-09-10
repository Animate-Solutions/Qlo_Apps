<?php
/**
 * The ARI engine: availability, rates and restrictions per date x room type x rate plan x channel.
 *
 * Availability  = physical rooms - out of order - confirmed bookings - group blocks,
 *                 then capped by the channel/mapping allotment and widened by the oversell buffer.
 * Rate          = QloApps feature pricing for the day -> rate plan derivation -> channel adjustment ->
 *                 occupancy derivation (single supplement, extra adult, child).
 * Restrictions  = min/max LOS from the room type restriction ranges (or the rate plan), CTA/CTD, stop-sell,
 *                 release days; a desk override (manual_rate / manual_stop_sell) always wins.
 *
 * Hooks only ever call markDirty(): it is a cheap queue write. The recompute and the push happen in
 * drainQueue() from cron, so a check-in at 2 a.m. never waits on an OTA over a bad link.
 */
class PulseChAri
{
    /* ---------- dirty queue ---------- */

    /**
     * Flag a date range of a room type as needing recompute + push. Overlapping pending rows are widened
     * rather than duplicated, so a busy morning cannot grow the queue without bound.
     */
    public static function markDirty($idProduct, $from, $to, $reason = 'change', $idChannel = 0)
    {
        $bd = PulseChService::businessDate();
        $from = max($from, $bd); $to = max($to, $from);
        $limit = date('Y-m-d', strtotime($bd.' +'.PulseChService::windowDays().' day'));
        if ($from > $limit) { return 0; }
        if ($to > $limit) { $to = $limit; }
        $n = 0;
        foreach (PulseChService::channels(true) as $c) {
            if ($idChannel && (int) $c['id_pulse_ch_channel'] !== (int) $idChannel) { continue; }
            if (!in_array($c['sync_mode'], array('push', 'both'))) { continue; }
            if ($idProduct && !Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_mapping` WHERE id_pulse_ch_channel='.(int) $c['id_pulse_ch_channel'].' AND id_product='.(int) $idProduct.' AND active=1')) { continue; }
            $existing = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_queue` WHERE id_pulse_ch_channel='.(int) $c['id_pulse_ch_channel'].' AND id_product='.(int) $idProduct.' AND status="pending" AND date_from<="'.pSQL(date('Y-m-d', strtotime($to.' +1 day'))).'" AND date_to>="'.pSQL(date('Y-m-d', strtotime($from.' -1 day'))).'" ORDER BY id_pulse_ch_queue LIMIT 1');
            if ($existing) {
                Db::getInstance()->update('pulse_ch_queue', array('date_from' => pSQL(min($existing['date_from'], $from)), 'date_to' => pSQL(max($existing['date_to'], $to)), 'reason' => pSQL($reason), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_queue='.(int) $existing['id_pulse_ch_queue']);
            } else {
                Db::getInstance()->insert('pulse_ch_queue', array('id_pulse_ch_channel' => (int) $c['id_pulse_ch_channel'], 'id_product' => (int) $idProduct, 'id_pulse_ch_rate_plan' => 0,
                    'date_from' => pSQL($from), 'date_to' => pSQL($to), 'type' => 'ari', 'reason' => pSQL($reason), 'status' => 'pending', 'next_attempt_at' => date('Y-m-d H:i:s'),
                    'business_date' => pSQL($bd), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
            }
            $n++;
        }
        return $n;
    }

    public static function queue($status = null, $idChannel = 0, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT q.*, c.name channel, pl.name room_type FROM `'._DB_PREFIX_.'pulse_ch_queue` q
            LEFT JOIN `'._DB_PREFIX_.'pulse_ch_channel` c ON c.id_pulse_ch_channel=q.id_pulse_ch_channel
            LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=q.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' AND pl.id_shop='.(int) Context::getContext()->shop->id.'
            WHERE 1'.($status ? ' AND q.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'")' : '').($idChannel ? ' AND q.id_pulse_ch_channel='.(int) $idChannel : '').'
            ORDER BY FIELD(q.status,"poison","failed","sending","pending","sent"), q.id_pulse_ch_queue DESC LIMIT '.(int) $limit);
    }

    /** Put a failed or poisoned batch back at the front of the queue (the "retry now" button). */
    public static function requeue($idQueue)
    {
        Db::getInstance()->update('pulse_ch_queue', array('status' => 'pending', 'attempts' => 0, 'last_error' => null, 'next_attempt_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_queue='.(int) $idQueue);
        return true;
    }

    /* ---------- computation ---------- */

    /**
     * Physical / OOO / booked / blocked per date for one room type over a range, in three queries.
     * Group blocks and OOO flags only exist when Front Desk is installed; without it the numbers still add up.
     */
    public static function baseGrid($idProduct, $from, $to)
    {
        $fd = PulseChService::fd();
        $total = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_product='.(int) $idProduct);
        $oooRooms = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` r'.($fd ? ' LEFT JOIN `'._DB_PREFIX_.'pulse_room_status` s ON s.id_room=r.id' : '').'
            WHERE r.id_product='.(int) $idProduct.' AND (r.id_status<>1'.($fd ? ' OR s.hk_status IN ("out_of_order","out_of_service")' : '').')');
        $grid = array();
        for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) { $grid[date('Y-m-d', $d)] = array('physical' => max(0, $total - $oooRooms), 'ooo' => $oooRooms, 'booked' => 0, 'blocked' => 0); }
        foreach (Db::getInstance()->executeS('SELECT date_from, date_to FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_product='.(int) $idProduct.' AND is_refunded=0 AND is_cancelled=0 AND id_status<>'.(int) HotelBookingDetail::STATUS_CHECKED_OUT.' AND date_from<="'.pSQL($to).'" AND date_to>"'.pSQL($from).'"') as $b) {
            for ($d = max(strtotime($from), strtotime($b['date_from'])); $d < min(strtotime($to) + 86400, strtotime($b['date_to'])); $d += 86400) { $k = date('Y-m-d', $d); if (isset($grid[$k])) { $grid[$k]['booked']++; } }
        }
        if ($fd) {
            foreach (Db::getInstance()->executeS('SELECT b.date_from, b.date_to, GREATEST(a.blocked-a.picked_up,0) held FROM `'._DB_PREFIX_.'pulse_group_block_allot` a INNER JOIN `'._DB_PREFIX_.'pulse_group_block` b ON b.id_pulse_group_block=a.id_pulse_group_block
                WHERE a.id_product='.(int) $idProduct.' AND b.status IN ("tentative","definite") AND b.cutoff_date>="'.pSQL(PulseChService::businessDate()).'" AND b.date_from<="'.pSQL($to).'" AND b.date_to>"'.pSQL($from).'"') as $g) {
                for ($d = max(strtotime($from), strtotime($g['date_from'])); $d < min(strtotime($to) + 86400, strtotime($g['date_to'])); $d += 86400) { $k = date('Y-m-d', $d); if (isset($grid[$k])) { $grid[$k]['blocked'] += (int) $g['held']; } }
            }
        }
        foreach ($grid as $k => $v) { $grid[$k]['available'] = max(0, $v['physical'] - $v['booked'] - $v['blocked']); }
        return $grid;
    }

    /** Nightly rack rate (tax incl) for one date, straight from QloApps feature pricing. */
    public static function baseRate($idProduct, $date)
    {
        static $cache = array();
        $k = (int) $idProduct.'|'.$date;
        if (isset($cache[$k])) { return $cache[$k]; }
        $next = date('Y-m-d', strtotime($date.' +1 day'));
        $p = 0;
        if (class_exists('HotelRoomTypeFeaturePricing') && method_exists('HotelRoomTypeFeaturePricing', 'getRoomTypeFeaturePricesPerDay')) {
            $p = (float) HotelRoomTypeFeaturePricing::getRoomTypeFeaturePricesPerDay($idProduct, $date, $next, true);
        }
        if ($p <= 0) { $p = (float) Product::getPriceStatic($idProduct, true); }
        return $cache[$k] = round($p, 2);
    }

    /** min/max LOS for a date: room type restriction ranges win, then the room type default. */
    public static function baseLos($idProduct, $date)
    {
        static $cache = array();
        if (!isset($cache[(int) $idProduct])) {
            $cache[(int) $idProduct] = array(
                'ranges' => Db::getInstance()->executeS('SELECT date_from, date_to, min_los, max_los FROM `'._DB_PREFIX_.'htl_room_type_restriction_date_range` WHERE id_product='.(int) $idProduct),
                'default' => Db::getInstance()->getRow('SELECT min_los, max_los FROM `'._DB_PREFIX_.'htl_room_type` WHERE id_product='.(int) $idProduct),
            );
        }
        $c = $cache[(int) $idProduct];
        foreach ((array) $c['ranges'] as $r) { if ($date >= Tools::substr($r['date_from'], 0, 10) && $date <= Tools::substr($r['date_to'], 0, 10)) { return array('min_los' => max(1, (int) $r['min_los']), 'max_los' => (int) $r['max_los']); } }
        return array('min_los' => max(1, (int) (isset($c['default']['min_los']) ? $c['default']['min_los'] : Configuration::get('PULSE_CH_MIN_LOS'))), 'max_los' => (int) (isset($c['default']['max_los']) ? $c['default']['max_los'] : 0));
    }

    /** Apply a rate plan chain (derive_from) then the channel mapping adjustment. */
    public static function derive($base, array $mapping)
    {
        $r = (float) $base;
        if ($mapping['rp_adjust_type'] === 'percent') { $r = $r * (1 + (float) $mapping['rp_adjust_value'] / 100); } elseif ($mapping['rp_adjust_type'] === 'amount') { $r = $r + (float) $mapping['rp_adjust_value']; }
        if ($mapping['rate_adjust_type'] === 'percent') { $r = $r * (1 + (float) $mapping['rate_adjust_value'] / 100); } elseif ($mapping['rate_adjust_type'] === 'amount') { $r = $r + (float) $mapping['rate_adjust_value']; }
        return round(max(0, $r), 2);
    }

    /**
     * Recompute every cell of a channel over a range (optionally one room type) and store it.
     * Returns the number of cells whose value actually changed.
     */
    public static function recompute($channel, $from, $to, $idProduct = 0)
    {
        $maps = PulseChMapping::pushable((int) $channel['id_pulse_ch_channel']);
        if (!$maps) { return 0; }
        $globalBuffer = (int) Configuration::get('PULSE_CH_OVERSELL_BUFFER');
        $byProduct = array(); foreach ($maps as $m) { if ($idProduct && (int) $m['id_product'] !== (int) $idProduct) { continue; } $byProduct[(int) $m['id_product']][] = $m; }
        $changed = 0; $now = date('Y-m-d H:i:s');
        foreach ($byProduct as $pid => $rows) {
            $grid = self::baseGrid($pid, $from, $to);
            // the room type's overbooking allowance caps the buffer when Front Desk defines one; otherwise the buffer stands alone
            $cap = PulseChService::fd() ? Db::getInstance()->getValue('SELECT max_over FROM `'._DB_PREFIX_.'pulse_overbooking` WHERE id_product='.(int) $pid) : false;
            foreach ($rows as $m) {
                $allot = (int) $m['allotment'] ?: (int) $channel['allotment'];
                $want = (int) $channel['oversell_buffer'] + $globalBuffer;
                $buffer = ($cap === false || $cap === null) ? max(0, $want) : max(0, min($want, (int) $cap));
                foreach ($grid as $date => $g) {
                    $avail = $g['available'];
                    if ($allot > 0) { $avail = min($avail, $allot); }
                    $avail = max(0, $avail + $buffer);
                    $los = self::baseLos($pid, $date);
                    $minLos = (int) $m['min_los'] ?: max((int) $los['min_los'], (int) $m['rp_min_los']);
                    $maxLos = (int) $m['rp_max_los'] ?: (int) $los['max_los'];
                    $rate = self::derive(self::baseRate($pid, $date), $m);
                    $prev = Db::getInstance()->getRow('SELECT id_pulse_ch_ari, manual_rate, manual_stop_sell, manual_min_los, manual_max_los, cell_hash, pushed_hash FROM `'._DB_PREFIX_.'pulse_ch_ari` WHERE id_pulse_ch_channel='.(int) $channel['id_pulse_ch_channel'].' AND id_product='.(int) $pid.' AND id_pulse_ch_rate_plan='.(int) $m['id_pulse_ch_rate_plan'].' AND ari_date="'.pSQL($date).'"');
                    if ($prev && $prev['manual_rate'] !== null && (float) $prev['manual_rate'] > 0) { $rate = round((float) $prev['manual_rate'], 2); }
                    if ($prev && $prev['manual_min_los'] !== null) { $minLos = (int) $prev['manual_min_los']; }
                    if ($prev && $prev['manual_max_los'] !== null) { $maxLos = (int) $prev['manual_max_los']; }
                    $stop = $avail <= 0 ? 1 : 0;
                    if ($prev && $prev['manual_stop_sell'] !== null) { $stop = (int) $prev['manual_stop_sell'] ? 1 : $stop; }
                    $cell = array(
                        'id_pulse_ch_channel' => (int) $channel['id_pulse_ch_channel'], 'id_product' => (int) $pid, 'id_pulse_ch_rate_plan' => (int) $m['id_pulse_ch_rate_plan'], 'ari_date' => pSQL($date),
                        'physical' => (int) $g['physical'], 'booked' => (int) $g['booked'], 'blocked' => (int) $g['blocked'], 'ooo' => (int) $g['ooo'], 'available' => (int) $avail,
                        'rate' => $rate, 'rate_single' => round(max(0, $rate + (float) $m['single_adj']), 2), 'rate_extra_adult' => round(max(0, (float) $m['extra_adult_adj']), 2), 'rate_child' => round(max(0, (float) $m['child_adj']), 2),
                        'min_los' => max(1, $minLos), 'max_los' => max(0, $maxLos), 'stop_sell' => $stop,
                        'release_days' => (int) ($m['rp_release_days'] ?: $channel['release_days']), 'date_upd' => $now,
                    );
                    $hash = md5(implode('|', array($cell['available'], $cell['rate'], $cell['rate_single'], $cell['rate_extra_adult'], $cell['rate_child'], $cell['min_los'], $cell['max_los'], $cell['stop_sell'], $cell['release_days'])));
                    $cell['cell_hash'] = $hash;
                    if ($prev) {
                        if ($prev['cell_hash'] !== $hash) { $changed++; }
                        Db::getInstance()->update('pulse_ch_ari', $cell, 'id_pulse_ch_ari='.(int) $prev['id_pulse_ch_ari']);
                    } else { $cell['cta'] = 0; $cell['ctd'] = 0; Db::getInstance()->insert('pulse_ch_ari', $cell); $changed++; }
                }
            }
        }
        return $changed;
    }

    /** Full recompute of the whole window for every enabled channel, then one push batch each. Run after night audit. */
    public static function rebuild($idChannel = 0)
    {
        $bd = PulseChService::businessDate();
        $to = date('Y-m-d', strtotime($bd.' +'.PulseChService::windowDays().' day'));
        $out = array('channels' => 0, 'cells_changed' => 0, 'queued' => 0);
        foreach (PulseChService::channels(true) as $c) {
            if ($idChannel && (int) $c['id_pulse_ch_channel'] !== (int) $idChannel) { continue; }
            if (!in_array($c['sync_mode'], array('push', 'both'))) { continue; }
            $window = min((int) $c['push_window_days'], PulseChService::windowDays());
            $chanTo = date('Y-m-d', strtotime($bd.' +'.$window.' day'));
            $out['cells_changed'] += self::recompute($c, $bd, $chanTo);
            $out['channels']++;
            Db::getInstance()->delete('pulse_ch_ari', 'id_pulse_ch_channel='.(int) $c['id_pulse_ch_channel'].' AND ari_date<"'.pSQL($bd).'"');
            $out['queued'] += self::markDirty(0, $bd, $chanTo, 'rebuild', (int) $c['id_pulse_ch_channel']);
        }
        PulseCoreService::audit('pulsechannel', 'ari_rebuild', $out);
        return $out;
    }

    /* ---------- push ---------- */

    /** The neutral row shape every adapter receives. */
    public static function rowsFor($channel, $idProduct, $from, $to, $onlyChanged = true, $limit = 5000)
    {
        return Db::getInstance()->executeS('SELECT a.ari_date `date`, m.channel_room_code room_code, m.channel_rate_code rate_code, m.base_occupancy, m.max_occupancy,
                a.available, a.rate, a.rate_single, a.rate_extra_adult, a.rate_child, a.min_los, a.max_los, a.cta, a.ctd, a.stop_sell, a.release_days,
                a.id_pulse_ch_ari, a.cell_hash, "'.pSQL($channel['currency_iso']).'" currency, a.id_product, a.id_pulse_ch_rate_plan
            FROM `'._DB_PREFIX_.'pulse_ch_ari` a
            INNER JOIN `'._DB_PREFIX_.'pulse_ch_mapping` m ON m.id_pulse_ch_channel=a.id_pulse_ch_channel AND m.id_product=a.id_product AND m.id_pulse_ch_rate_plan=a.id_pulse_ch_rate_plan AND m.active=1
            WHERE a.id_pulse_ch_channel='.(int) $channel['id_pulse_ch_channel'].' AND a.ari_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'
            .($idProduct ? ' AND a.id_product='.(int) $idProduct : '')
            .($onlyChanged ? ' AND (a.pushed_hash IS NULL OR a.pushed_hash<>a.cell_hash)' : '').'
            ORDER BY a.id_product, a.id_pulse_ch_rate_plan, a.ari_date LIMIT '.(int) $limit);
    }

    /**
     * Drain the push queue: recompute each pending batch, push only the cells that actually changed,
     * and apply exponential backoff with a poison cap so one broken channel cannot spin forever.
     */
    public static function drainQueue($idChannel = 0, $maxBatches = 40)
    {
        $out = array('sent' => 0, 'errors' => 0, 'cells' => 0, 'skipped' => 0);
        $maxAttempts = max(1, (int) Configuration::get('PULSE_CH_MAX_ATTEMPTS'));
        $backoff = max(2, (int) Configuration::get('PULSE_CH_BACKOFF_BASE'));
        $items = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_queue` WHERE status IN ("pending","failed") AND next_attempt_at<="'.pSQL(date('Y-m-d H:i:s')).'"'.($idChannel ? ' AND id_pulse_ch_channel='.(int) $idChannel : '').' ORDER BY id_pulse_ch_queue LIMIT '.(int) $maxBatches);
        foreach ($items as $q) {
            $c = PulseChService::channel((int) $q['id_pulse_ch_channel']);
            if (!$c || !$c['enabled'] || !in_array($c['sync_mode'], array('push', 'both'))) { Db::getInstance()->update('pulse_ch_queue', array('status' => 'cancelled', 'last_error' => 'Channel disabled', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_queue='.(int) $q['id_pulse_ch_queue']); $out['skipped']++; continue; }
            Db::getInstance()->update('pulse_ch_queue', array('status' => 'sending', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_queue='.(int) $q['id_pulse_ch_queue']);
            try {
                self::recompute($c, $q['date_from'], $q['date_to'], (int) $q['id_product']);
                $rows = self::rowsFor($c, (int) $q['id_product'], $q['date_from'], $q['date_to'], true);
            } catch (Exception $e) {
                self::failBatch($q, $c, 'Recompute failed: '.$e->getMessage(), $maxAttempts, $backoff); $out['errors']++; continue;
            }
            if (!$rows) { Db::getInstance()->update('pulse_ch_queue', array('status' => 'sent', 'cells' => 0, 'last_error' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_queue='.(int) $q['id_pulse_ch_queue']); continue; }
            $adapter = PulseChService::adapter($c);
            $size = max(1, (int) $c['batch_size']); $ok = true; $err = null; $done = 0;
            foreach (array_chunk($rows, $size) as $chunk) {
                $payload = array(); foreach ($chunk as $r) { $payload[] = self::stripInternal($r); }
                try { $res = $adapter->pushAri($payload); } catch (Exception $e) { $res = array('ok' => false, 'error' => $e->getMessage()); }
                if (empty($res['ok'])) { $ok = false; $err = isset($res['error']) ? $res['error'] : 'Unknown adapter failure'; break; }
                $ids = array(); foreach ($chunk as $r) { $ids[] = (int) $r['id_pulse_ch_ari']; }
                Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_ch_ari` SET pushed_hash=cell_hash, pushed_rate=rate, pushed_available=available, pushed_at=NOW() WHERE id_pulse_ch_ari IN ('.implode(',', $ids).')');
                $done += count($chunk);
            }
            if ($ok) {
                Db::getInstance()->update('pulse_ch_queue', array('status' => 'sent', 'cells' => (int) $done, 'last_error' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_queue='.(int) $q['id_pulse_ch_queue']);
                PulseChService::health((int) $c['id_pulse_ch_channel'], true);
                PulseCoreService::event('actionPulseChannelAriPushed', array('id_channel' => (int) $c['id_pulse_ch_channel'], 'cells' => $done, 'from' => $q['date_from'], 'to' => $q['date_to']));
                $out['sent']++; $out['cells'] += $done;
            } else { self::failBatch($q, $c, $err, $maxAttempts, $backoff); $out['errors']++; }
        }
        return $out;
    }

    /** Adapters get only the wire fields; our row ids stay behind. */
    protected static function stripInternal(array $r) { unset($r['id_pulse_ch_ari'], $r['cell_hash'], $r['id_product'], $r['id_pulse_ch_rate_plan']); return $r; }

    /** Exponential backoff, then poison so an operator sees it instead of the queue grinding all night. */
    protected static function failBatch($q, $c, $error, $maxAttempts, $backoff)
    {
        $attempts = (int) $q['attempts'] + 1;
        $poison = $attempts >= $maxAttempts;
        $wait = min(240, (int) pow($backoff, min($attempts, 8)));
        Db::getInstance()->update('pulse_ch_queue', array('status' => $poison ? 'poison' : 'failed', 'attempts' => $attempts, 'last_error' => pSQL(Tools::substr((string) $error, 0, 250)),
            'next_attempt_at' => date('Y-m-d H:i:s', time() + $wait * 60), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_ch_queue='.(int) $q['id_pulse_ch_queue']);
        PulseChService::health((int) $c['id_pulse_ch_channel'], false, $error);
        if ($poison) { PulseCoreService::audit('pulsechannel', 'queue_poison', array('channel' => $c['code'], 'error' => $error, 'from' => $q['date_from'], 'to' => $q['date_to']), 'pulse_ch_queue', (int) $q['id_pulse_ch_queue']); }
        return $poison;
    }

    /* ---------- screens ---------- */

    /** Calendar grid: one row per room type x rate plan, one cell per date. */
    public static function grid($idChannel, $from, $days = 30, $idRatePlan = 0)
    {
        $dates = array(); for ($i = 0; $i < (int) $days; $i++) { $dates[] = date('Y-m-d', strtotime($from.' +'.$i.' day')); }
        $to = end($dates);
        $rows = Db::getInstance()->executeS('SELECT a.*, pl.name room_type, rp.code rate_plan_code, rp.name rate_plan, m.channel_room_code, m.channel_rate_code
            FROM `'._DB_PREFIX_.'pulse_ch_ari` a
            LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=a.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' AND pl.id_shop='.(int) Context::getContext()->shop->id.'
            LEFT JOIN `'._DB_PREFIX_.'pulse_ch_rate_plan` rp ON rp.id_pulse_ch_rate_plan=a.id_pulse_ch_rate_plan
            LEFT JOIN `'._DB_PREFIX_.'pulse_ch_mapping` m ON m.id_pulse_ch_channel=a.id_pulse_ch_channel AND m.id_product=a.id_product AND m.id_pulse_ch_rate_plan=a.id_pulse_ch_rate_plan
            WHERE a.id_pulse_ch_channel='.(int) $idChannel.' AND a.ari_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'.($idRatePlan ? ' AND a.id_pulse_ch_rate_plan='.(int) $idRatePlan : '').'
            ORDER BY pl.name, rp.sort, a.ari_date');
        $out = array();
        foreach ($rows as $r) {
            $k = (int) $r['id_product'].'-'.(int) $r['id_pulse_ch_rate_plan'];
            if (!isset($out[$k])) { $out[$k] = array('id_product' => (int) $r['id_product'], 'id_pulse_ch_rate_plan' => (int) $r['id_pulse_ch_rate_plan'], 'room_type' => $r['room_type'], 'rate_plan' => $r['rate_plan'], 'rate_plan_code' => $r['rate_plan_code'], 'room_code' => $r['channel_room_code'], 'rate_code' => $r['channel_rate_code'], 'cells' => array()); }
            $r['dirty'] = ($r['pushed_hash'] === null || $r['pushed_hash'] !== $r['cell_hash']) ? 1 : 0;
            $out[$k]['cells'][$r['ari_date']] = $r;
        }
        return array('dates' => $dates, 'rows' => array_values($out));
    }

    /**
     * Bulk rate / restriction update across a date range, room types, rate plans and channels.
     * $f keys: rate, min_los, max_los, cta, ctd, stop_sell, release_days, clear_manual, dow (array of 0-6).
     */
    public static function bulkUpdate(array $channels, array $products, array $ratePlans, $from, $to, array $f)
    {
        if (!$channels || $from > $to) { throw new PrestaShopException('Pick at least one channel and a valid date range'); }
        $set = array();
        if (isset($f['clear_manual']) && $f['clear_manual']) { $set[] = 'manual_rate=NULL'; $set[] = 'manual_stop_sell=NULL'; $set[] = 'manual_min_los=NULL'; $set[] = 'manual_max_los=NULL'; }
        if (isset($f['rate']) && $f['rate'] !== '' && $f['rate'] !== null) { $set[] = 'manual_rate='.(float) $f['rate']; $set[] = 'rate='.(float) $f['rate']; }
        foreach (array('min_los', 'max_los') as $k) { if (isset($f[$k]) && $f[$k] !== '' && $f[$k] !== null) { $set[] = '`'.bqSQL($k).'`='.(int) $f[$k]; $set[] = '`manual_'.bqSQL($k).'`='.(int) $f[$k]; } }
        if (isset($f['release_days']) && $f['release_days'] !== '' && $f['release_days'] !== null) { $set[] = 'release_days='.(int) $f['release_days']; }
        foreach (array('cta', 'ctd') as $k) { if (isset($f[$k]) && $f[$k] !== '' && $f[$k] !== null) { $set[] = '`'.bqSQL($k).'`='.((int) $f[$k] ? 1 : 0); } }
        if (isset($f['stop_sell']) && $f['stop_sell'] !== '' && $f['stop_sell'] !== null) { $set[] = 'manual_stop_sell='.((int) $f['stop_sell'] ? 1 : 0); $set[] = 'stop_sell='.((int) $f['stop_sell'] ? 1 : 0); }
        if (!$set) { throw new PrestaShopException('Nothing to change — set at least one value'); }
        $set[] = 'cell_hash=""'; $set[] = 'date_upd=NOW()';
        $where = 'id_pulse_ch_channel IN ('.implode(',', array_map('intval', $channels)).') AND ari_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"';
        if ($products) { $where .= ' AND id_product IN ('.implode(',', array_map('intval', $products)).')'; }
        if ($ratePlans) { $where .= ' AND id_pulse_ch_rate_plan IN ('.implode(',', array_map('intval', $ratePlans)).')'; }
        if (!empty($f['dow']) && is_array($f['dow'])) { $where .= ' AND DAYOFWEEK(ari_date)-1 IN ('.implode(',', array_map('intval', $f['dow'])).')'; }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_ch_ari` SET '.implode(', ', $set).' WHERE '.$where);
        $n = (int) Db::getInstance()->Affected_Rows();
        // cell_hash was blanked, so the next recompute rewrites it and the queue picks the cells up as changed
        foreach ($channels as $idc) { foreach (($products ? $products : array(0)) as $pid) { self::markDirty((int) $pid, $from, $to, 'bulk', (int) $idc); } }
        PulseCoreService::audit('pulsechannel', 'ari_bulk', array('cells' => $n, 'from' => $from, 'to' => $to, 'fields' => $f));
        return $n;
    }

    /** The panic button: stop-sell everything on every (or one) channel for a date range. */
    public static function closeOut($from, $to, $idChannel = 0, $open = false)
    {
        $channels = array();
        foreach (PulseChService::channels(true) as $c) { if (!$idChannel || (int) $c['id_pulse_ch_channel'] === (int) $idChannel) { $channels[] = (int) $c['id_pulse_ch_channel']; } }
        if (!$channels) { throw new PrestaShopException('No enabled channel to close out'); }
        return self::bulkUpdate($channels, array(), array(), $from, $to, $open ? array('clear_manual' => 1, 'stop_sell' => 0) : array('stop_sell' => 1));
    }

    /**
     * Rate parity: our computed rate against what each channel was last told, for the next N days.
     * Drift beyond the tolerance percentage, or a cell never pushed, is what the screen highlights.
     */
    public static function parity($days = 30, $idChannel = 0)
    {
        $bd = PulseChService::businessDate();
        $to = date('Y-m-d', strtotime($bd.' +'.(int) $days.' day'));
        $tol = (float) Configuration::get('PULSE_CH_PARITY_TOLERANCE');
        $rows = Db::getInstance()->executeS('SELECT a.ari_date, a.id_product, a.id_pulse_ch_channel, c.name channel, pl.name room_type, rp.code rate_plan, a.rate, a.pushed_rate, a.available, a.pushed_available, a.stop_sell, a.pushed_at, a.cell_hash, a.pushed_hash
            FROM `'._DB_PREFIX_.'pulse_ch_ari` a
            INNER JOIN `'._DB_PREFIX_.'pulse_ch_channel` c ON c.id_pulse_ch_channel=a.id_pulse_ch_channel AND c.enabled=1
            LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=a.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' AND pl.id_shop='.(int) Context::getContext()->shop->id.'
            LEFT JOIN `'._DB_PREFIX_.'pulse_ch_rate_plan` rp ON rp.id_pulse_ch_rate_plan=a.id_pulse_ch_rate_plan
            WHERE a.ari_date BETWEEN "'.pSQL($bd).'" AND "'.pSQL($to).'"'.($idChannel ? ' AND a.id_pulse_ch_channel='.(int) $idChannel : '').'
            ORDER BY a.ari_date, pl.name, c.name');
        $out = array();
        foreach ($rows as $r) {
            $pushed = $r['pushed_rate'] === null ? null : (float) $r['pushed_rate'];
            $drift = $pushed === null ? null : round((float) $r['rate'] - $pushed, 2);
            $pct = ($pushed !== null && $pushed > 0.009) ? round(100 * $drift / $pushed, 2) : null;
            $r['drift'] = $drift; $r['drift_pct'] = $pct;
            $r['flag'] = $pushed === null ? 'never_pushed' : ($pct !== null && abs($pct) > $tol ? 'drift' : ($r['pushed_hash'] !== $r['cell_hash'] ? 'pending' : 'ok'));
            $out[] = $r;
        }
        return $out;
    }

    /** Compact parity summary per channel for the dashboard. */
    public static function paritySummary($days = 30)
    {
        $sum = array();
        foreach (self::parity($days) as $r) {
            $k = (int) $r['id_pulse_ch_channel'];
            if (!isset($sum[$k])) { $sum[$k] = array('channel' => $r['channel'], 'ok' => 0, 'drift' => 0, 'pending' => 0, 'never_pushed' => 0, 'worst' => 0); }
            $sum[$k][$r['flag']]++;
            if ($r['drift'] !== null && abs($r['drift']) > abs($sum[$k]['worst'])) { $sum[$k]['worst'] = $r['drift']; }
        }
        return $sum;
    }
}
