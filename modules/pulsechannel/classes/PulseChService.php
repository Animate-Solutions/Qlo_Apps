<?php
/**
 * Channel registry, credentials, adapter factory, health and dashboard queries.
 * Everything cross-module is optional: the channel manager reads QloApps inventory directly and only
 * enriches with Front Desk data (blocks, OOO rooms, folios) when pulsefrontdesk is installed.
 */
class PulseChService
{
    /** Front Desk present? Group blocks, room status and overbooking limits only exist when it is. */
    public static function fd() { return Module::isEnabled('pulsefrontdesk') && class_exists('PulseFolio'); }
    public static function businessDate() { return class_exists('PulseCoreService') ? PulseCoreService::businessDate() : date('Y-m-d'); }
    public static function windowDays() { return max(1, (int) Configuration::get('PULSE_CH_ARI_DAYS')); }

    /* ---------- channels ---------- */

    public static function channels($enabledOnly = false)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_channel`'.($enabledOnly ? ' WHERE enabled=1 AND sync_mode<>"off"' : '').' ORDER BY enabled DESC, name');
    }

    public static function channel($id)
    {
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_channel` WHERE id_pulse_ch_channel='.(int) $id);
    }

    public static function channelByCode($code)
    {
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_channel` WHERE code="'.pSQL($code).'"');
    }

    /** Save a channel row. Credentials arrive as a plain array and are stored encrypted; an empty value keeps the stored one. */
    public static function saveChannel(array $d, array $credentials = array())
    {
        $id = isset($d['id_pulse_ch_channel']) ? (int) $d['id_pulse_ch_channel'] : 0;
        $row = array(
            'code' => pSQL(isset($d['code']) ? Tools::strtolower(preg_replace('/[^A-Za-z0-9_]/', '_', $d['code'])) : ''), 'name' => pSQL($d['name']), 'adapter' => pSQL($d['adapter']),
            'endpoint' => pSQL(isset($d['endpoint']) ? $d['endpoint'] : ''), 'pull_endpoint' => pSQL(isset($d['pull_endpoint']) ? $d['pull_endpoint'] : ''), 'ack_endpoint' => pSQL(isset($d['ack_endpoint']) ? $d['ack_endpoint'] : ''),
            'auth_type' => pSQL(isset($d['auth_type']) ? $d['auth_type'] : 'bearer'), 'auth_header' => pSQL(isset($d['auth_header']) && $d['auth_header'] ? $d['auth_header'] : 'Authorization'),
            'hotel_code' => pSQL(isset($d['hotel_code']) ? $d['hotel_code'] : ''), 'id_hotel' => !empty($d['id_hotel']) ? (int) $d['id_hotel'] : null,
            'currency_iso' => pSQL(isset($d['currency_iso']) && $d['currency_iso'] ? Tools::strtoupper($d['currency_iso']) : 'NGN'), 'commission_pct' => round((float) (isset($d['commission_pct']) ? $d['commission_pct'] : 0), 3),
            'payload_format' => pSQL(isset($d['payload_format']) ? $d['payload_format'] : 'json'), 'payload_template' => pSQL(isset($d['payload_template']) ? $d['payload_template'] : '', true),
            'sync_mode' => pSQL(isset($d['sync_mode']) ? $d['sync_mode'] : 'both'), 'push_window_days' => (int) (isset($d['push_window_days']) && $d['push_window_days'] ? $d['push_window_days'] : 365),
            'allotment' => (int) (isset($d['allotment']) ? $d['allotment'] : 0), 'oversell_buffer' => (int) (isset($d['oversell_buffer']) ? $d['oversell_buffer'] : 0), 'release_days' => (int) (isset($d['release_days']) ? $d['release_days'] : 0),
            'batch_size' => (int) (isset($d['batch_size']) && $d['batch_size'] ? $d['batch_size'] : 200), 'timeout_sec' => (int) (isset($d['timeout_sec']) && $d['timeout_sec'] ? $d['timeout_sec'] : 20),
            'csv_in_dir' => pSQL(isset($d['csv_in_dir']) ? $d['csv_in_dir'] : ''), 'csv_out_dir' => pSQL(isset($d['csv_out_dir']) ? $d['csv_out_dir'] : ''),
            'enabled' => !empty($d['enabled']) ? 1 : 0, 'test_mode' => !empty($d['test_mode']) ? 1 : 0, 'auto_deliver' => !empty($d['auto_deliver']) ? 1 : 0,
            'notes' => pSQL(isset($d['notes']) ? $d['notes'] : '', true), 'date_upd' => date('Y-m-d H:i:s'),
        );
        $creds = array(); foreach ($credentials as $k => $v) { if (is_string($v) && $v !== '') { $creds[$k] = $v; } }
        if ($creds) {
            $existing = $id ? self::credentials(self::channel($id)) : array();
            $row['credentials'] = pSQL(self::encrypt(json_encode(array_merge($existing, $creds))), true);
        }
        if ($id) { Db::getInstance()->update('pulse_ch_channel', $row, 'id_pulse_ch_channel='.$id, 0, true); } else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_ch_channel', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        PulseCoreService::audit('pulsechannel', 'channel_save', array('code' => $row['code'], 'enabled' => $row['enabled']), 'pulse_ch_channel', $id);
        return $id;
    }

    /** Credentials are never stored in clear. Falls back to base64 only if pulsecore's encryption is unavailable. */
    public static function encrypt($plain) { return class_exists('PulseCoreService') && defined('_NEW_COOKIE_KEY_') ? PulseCoreService::encrypt($plain) : base64_encode($plain); }
    public static function decrypt($cipher)
    {
        if (!$cipher) { return ''; }
        if (class_exists('PulseCoreService') && defined('_NEW_COOKIE_KEY_')) { $out = PulseCoreService::decrypt($cipher); if ($out !== false && $out !== null && $out !== '') { return $out; } }
        $b = base64_decode($cipher, true);
        return $b === false ? '' : $b;
    }

    public static function credentials($channel)
    {
        if (empty($channel['credentials'])) { return array(); }
        $j = json_decode(self::decrypt($channel['credentials']), true);
        return is_array($j) ? $j : array();
    }

    /** Build the adapter for a channel row. Unknown adapter names fall back to the documented generic contract. */
    public static function adapter($channel)
    {
        $cls = isset($channel['adapter']) && $channel['adapter'] ? $channel['adapter'] : 'PulseChAdapterGeneric';
        if (!class_exists($cls) || !in_array('PulseChAdapterInterface', class_implements($cls))) { $cls = 'PulseChAdapterGeneric'; }
        return new $cls($channel, self::credentials($channel));
    }

    /* ---------- health ---------- */

    /** Record the outcome of a channel call and flip health. Repeated failure past the alert window raises one alert. */
    public static function health($idChannel, $ok, $error = null)
    {
        $now = date('Y-m-d H:i:s');
        if ($ok) {
            Db::getInstance()->update('pulse_ch_channel', array('health' => 'ok', 'last_success' => $now, 'fail_since' => null, 'last_error' => null, 'alerted_at' => null, 'date_upd' => $now), 'id_pulse_ch_channel='.(int) $idChannel, 0, true);
            return true;
        }
        $c = self::channel($idChannel); if (!$c) { return false; }
        $since = $c['fail_since'] ? $c['fail_since'] : $now;
        $mins = (int) Configuration::get('PULSE_CH_ALERT_MINUTES');
        $down = (strtotime($now) - strtotime($since)) >= $mins * 60;
        Db::getInstance()->update('pulse_ch_channel', array('health' => $down ? 'down' : 'degraded', 'last_failure' => $now, 'fail_since' => $since, 'last_error' => pSQL(Tools::substr((string) $error, 0, 250)), 'date_upd' => $now), 'id_pulse_ch_channel='.(int) $idChannel);
        if ($down && !$c['alerted_at']) { self::alert($c, $error, $since); }
        return false;
    }

    /** One alert per outage: Pulse Comms if available (SMS to the duty manager), otherwise a plain mail. */
    public static function alert($channel, $error, $since)
    {
        $text = 'Pulse Channel Manager: '.$channel['name'].' has been failing since '.$since.'. Last error: '.Tools::substr((string) $error, 0, 160);
        $sent = false;
        if (class_exists('PulseComms') && method_exists('PulseComms', 'sendRaw') && (Configuration::get('PULSE_CH_ALERT_PHONE') || Configuration::get('PULSE_CH_ALERT_EMAIL'))) {
            $sent = (bool) PulseComms::sendRaw(Configuration::get('PULSE_CH_ALERT_EMAIL'), Configuration::get('PULSE_CH_ALERT_PHONE'), 'owner_snapshot', array('text' => $text, 'name' => 'Duty Manager'));
        }
        if (!$sent && Configuration::get('PULSE_CH_ALERT_EMAIL')) {
            $sent = (bool) Mail::Send((int) Configuration::get('PS_LANG_DEFAULT'), 'contact', 'Channel down: '.$channel['name'], array('{message}' => $text, '{email}' => Configuration::get('PS_SHOP_EMAIL'), '{firstname}' => 'Duty', '{lastname}' => 'Manager'), Configuration::get('PULSE_CH_ALERT_EMAIL'));
        }
        Db::getInstance()->update('pulse_ch_channel', array('alerted_at' => date('Y-m-d H:i:s')), 'id_pulse_ch_channel='.(int) $channel['id_pulse_ch_channel']);
        PulseChLog::write((int) $channel['id_pulse_ch_channel'], 'out', 'alert', null, 0, $text, $sent ? 'sent' : 'not sent', 0, $sent ? 'ok' : 'error', $sent ? null : 'Alert could not be delivered');
        PulseCoreService::event('actionPulseChannelHealth', array('id_channel' => (int) $channel['id_pulse_ch_channel'], 'health' => 'down', 'error' => $error));
        return $sent;
    }

    /** Dashboard: one row per channel with queue depth, error rate and last sync. */
    public static function dashboard()
    {
        $rows = Db::getInstance()->executeS('SELECT c.*,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_queue` q WHERE q.id_pulse_ch_channel=c.id_pulse_ch_channel AND q.status="pending") queue_pending,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_queue` q WHERE q.id_pulse_ch_channel=c.id_pulse_ch_channel AND q.status="failed") queue_failed,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_queue` q WHERE q.id_pulse_ch_channel=c.id_pulse_ch_channel AND q.status="poison") queue_poison,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_mapping` m WHERE m.id_pulse_ch_channel=c.id_pulse_ch_channel AND m.active=1) mappings,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_reservation` r WHERE r.id_pulse_ch_channel=c.id_pulse_ch_channel AND r.status IN ("delivered","modified") AND r.date_add>=DATE_SUB(NOW(), INTERVAL 30 DAY)) res_30d,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_reservation` r WHERE r.id_pulse_ch_channel=c.id_pulse_ch_channel AND r.status="failed") res_failed,
                (SELECT ROUND(COALESCE(SUM(r.amount_tax_incl),0),2) FROM `'._DB_PREFIX_.'pulse_ch_reservation` r WHERE r.id_pulse_ch_channel=c.id_pulse_ch_channel AND r.status IN ("delivered","modified") AND r.date_add>=DATE_SUB(NOW(), INTERVAL 30 DAY)) rev_30d,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_log` l WHERE l.id_pulse_ch_channel=c.id_pulse_ch_channel AND l.date_add>=DATE_SUB(NOW(), INTERVAL 24 HOUR)) calls_24h,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_log` l WHERE l.id_pulse_ch_channel=c.id_pulse_ch_channel AND l.status="error" AND l.date_add>=DATE_SUB(NOW(), INTERVAL 24 HOUR)) errors_24h
            FROM `'._DB_PREFIX_.'pulse_ch_channel` c ORDER BY c.enabled DESC, c.name');
        foreach ($rows as &$r) {
            $r['error_rate'] = (int) $r['calls_24h'] > 0 ? round(100 * $r['errors_24h'] / $r['calls_24h'], 1) : 0;
            $r['unmapped'] = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM ('.self::roomTypeSql().') rt WHERE rt.id_product NOT IN (SELECT id_product FROM `'._DB_PREFIX_.'pulse_ch_mapping` WHERE id_pulse_ch_channel='.(int) $r['id_pulse_ch_channel'].' AND active=1)');
            $r['minutes_since_success'] = $r['last_success'] ? (int) round((time() - strtotime($r['last_success'])) / 60) : null;
        }
        return $rows;
    }

    /** Every sellable room type with its physical room count — the spine of mapping and ARI. */
    public static function roomTypeSql()
    {
        return 'SELECT p.id_product, pl.name, COUNT(r.id) rooms, rt.adults, rt.max_adults, rt.max_children, rt.max_guests, rt.min_los, rt.max_los, rt.id_hotel
            FROM `'._DB_PREFIX_.'product` p
            INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=p.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' AND pl.id_shop='.(int) Context::getContext()->shop->id.'
            INNER JOIN `'._DB_PREFIX_.'htl_room_type` rt ON rt.id_product=p.id_product
            LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id_product=p.id_product AND r.id_status=1
            WHERE p.active=1 GROUP BY p.id_product';
    }

    public static function roomTypes() { return Db::getInstance()->executeS(self::roomTypeSql().' ORDER BY pl.name'); }
    public static function ratePlans($activeOnly = true) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_ch_rate_plan`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY sort, code'); }

    /** Prune old logs so a 52-room property does not carry a year of XML around. */
    public static function pruneLogs()
    {
        $days = max(1, (int) Configuration::get('PULSE_CH_LOG_KEEP_DAYS'));
        Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_ch_log` WHERE date_add < DATE_SUB(NOW(), INTERVAL '.(int) $days.' DAY)');
        return (int) Db::getInstance()->Affected_Rows();
    }

    /**
     * Group blocks have no suite event of their own, so the sync job fingerprints them and dirties
     * whatever moved since the last run. Works against an unmodified Front Desk.
     */
    public static function detectBlockChanges()
    {
        if (!self::fd()) { return 0; }
        $now = array();
        foreach (Db::getInstance()->executeS('SELECT b.id_pulse_group_block, b.date_from, b.date_to, b.status, a.id_product, a.blocked, a.picked_up FROM `'._DB_PREFIX_.'pulse_group_block` b LEFT JOIN `'._DB_PREFIX_.'pulse_group_block_allot` a ON a.id_pulse_group_block=b.id_pulse_group_block WHERE b.date_to>="'.pSQL(self::businessDate()).'"') as $r) {
            $k = (int) $r['id_pulse_group_block'].':'.(int) $r['id_product'];
            $now[$k] = array('s' => md5($r['date_from'].$r['date_to'].$r['status'].$r['blocked'].$r['picked_up']), 'p' => (int) $r['id_product'], 'f' => $r['date_from'], 't' => $r['date_to']);
        }
        $prevRaw = PulseCoreService::setting('pulsechannel', 'block_sig');
        $prev = $prevRaw ? json_decode($prevRaw, true) : array();
        if (!is_array($prev)) { $prev = array(); }
        $n = 0;
        foreach ($now as $k => $v) { if (!isset($prev[$k]) || $prev[$k]['s'] !== $v['s']) { if ($v['p']) { PulseChAri::markDirty($v['p'], $v['f'], $v['t'], 'group_block'); $n++; } } }
        foreach ($prev as $k => $v) { if (!isset($now[$k]) && !empty($v['p'])) { PulseChAri::markDirty((int) $v['p'], $v['f'], $v['t'], 'group_block_released'); $n++; } }
        PulseCoreService::setting('pulsechannel', 'block_sig', json_encode($now));
        return $n;
    }

    /**
     * Full sync pass used by cron/sync.php and the dashboard "Sync now" button:
     * detect block changes → drain the push queue → pull reservations → prune logs.
     */
    public static function syncAll($idChannel = 0)
    {
        $out = array('blocks' => self::detectBlockChanges(), 'pushed' => 0, 'cells' => 0, 'push_errors' => 0, 'pulled' => 0, 'delivered' => 0, 'failed' => 0);
        $push = PulseChAri::drainQueue($idChannel);
        $out['pushed'] = $push['sent']; $out['cells'] = $push['cells']; $out['push_errors'] = $push['errors'];
        $pull = PulseChReservation::pullAll($idChannel);
        $out['pulled'] = $pull['pulled']; $out['delivered'] = $pull['delivered']; $out['failed'] = $pull['failed'];
        $out['logs_pruned'] = self::pruneLogs();
        return $out;
    }
}
