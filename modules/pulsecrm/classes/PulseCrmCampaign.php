<?php
/**
 * Campaigns: a segment, a channel, a body with merge tags, a schedule, a throttle and quiet hours.
 * Queueing materialises the recipient list once (with the consent decision recorded per row) and the
 * send loop then only has to walk it, which is what makes a send survive a flaky link — it resumes
 * exactly where it stopped.
 */
class PulseCrmCampaign
{
    public static function all($status = null)
    {
        return Db::getInstance()->executeS('SELECT ca.*, s.name segment_name, s.member_count FROM `'._DB_PREFIX_.'pulse_crm_campaign` ca
            LEFT JOIN `'._DB_PREFIX_.'pulse_crm_segment` s ON s.id_pulse_crm_segment=ca.id_pulse_crm_segment
            '.($status ? 'WHERE ca.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'") ' : '').'ORDER BY ca.date_add DESC');
    }

    public static function get($id)
    {
        $c = Db::getInstance()->getRow('SELECT ca.*, s.name segment_name FROM `'._DB_PREFIX_.'pulse_crm_campaign` ca LEFT JOIN `'._DB_PREFIX_.'pulse_crm_segment` s ON s.id_pulse_crm_segment=ca.id_pulse_crm_segment WHERE ca.id_pulse_crm_campaign='.(int) $id);
        if ($c) { $c['stats'] = self::stats($id); }
        return $c;
    }

    public static function save(array $d, $id = 0)
    {
        $row = array(
            'name' => pSQL($d['name']), 'channel' => pSQL(isset($d['channel']) ? $d['channel'] : 'email'), 'id_pulse_crm_segment' => (int) $d['id_pulse_crm_segment'] ?: null,
            'subject' => pSQL(isset($d['subject']) ? $d['subject'] : ''), 'body' => pSQL(isset($d['body']) ? $d['body'] : '', true),
            'subject_b' => pSQL(isset($d['subject_b']) ? $d['subject_b'] : ''), 'body_b' => pSQL(isset($d['body_b']) ? $d['body_b'] : '', true),
            'ab_split_pct' => max(0, min(50, (int) (isset($d['ab_split_pct']) ? $d['ab_split_pct'] : 0))),
            'schedule_type' => pSQL(isset($d['schedule_type']) ? $d['schedule_type'] : 'manual'),
            'send_at' => !empty($d['send_at']) && Validate::isDate(substr($d['send_at'], 0, 10)) ? pSQL($d['send_at']) : null,
            'recur_dom' => max(1, min(28, (int) (isset($d['recur_dom']) ? $d['recur_dom'] : 1))), 'recur_dow' => max(0, min(6, (int) (isset($d['recur_dow']) ? $d['recur_dow'] : 1))),
            'throttle_per_run' => max(1, (int) (isset($d['throttle_per_run']) ? $d['throttle_per_run'] : 100)),
            'quiet_from' => pSQL(isset($d['quiet_from']) ? $d['quiet_from'] : '21:00'), 'quiet_to' => pSQL(isset($d['quiet_to']) ? $d['quiet_to'] : '08:00'),
            'date_upd' => date('Y-m-d H:i:s'),
        );
        if (isset($d['status'])) { $row['status'] = pSQL($d['status']); }
        if ($id) { Db::getInstance()->update('pulse_crm_campaign', $row, 'id_pulse_crm_campaign='.(int) $id); return (int) $id; }
        $row['id_employee'] = PulseCrmService::emp(); $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_crm_campaign', $row);
        return (int) Db::getInstance()->Insert_ID();
    }

    /**
     * Build the recipient list from the segment's materialised membership. Every exclusion is written
     * down as a skipped row with its reason, so "why did she not get it?" has an answer months later.
     */
    public static function queue($id)
    {
        $c = self::get($id); if (!$c) { throw new PrestaShopException('No such campaign'); }
        if (!$c['id_pulse_crm_segment']) { throw new PrestaShopException('Give the campaign a segment first'); }
        $db = Db::getInstance(); $now = date('Y-m-d H:i:s');
        $channel = $c['channel'] === 'email' ? 'email' : ($c['channel'] === 'whatsapp' ? 'whatsapp' : 'sms');
        $queued = 0; $skipped = 0; $split = (int) $c['ab_split_pct'];
        $gp = PulseCrmService::gp();
        $rows = $db->executeS('SELECT sm.id_customer, cu.email, '.($gp ? 'gp.phone' : 'NULL phone').' FROM `'._DB_PREFIX_.'pulse_crm_segment_member` sm
            INNER JOIN `'._DB_PREFIX_.'customer` cu ON cu.id_customer=sm.id_customer AND cu.deleted=0
            '.($gp ? 'LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=sm.id_customer' : '').'
            WHERE sm.id_pulse_crm_segment='.(int) $c['id_pulse_crm_segment'].'
            AND sm.id_customer NOT IN (SELECT id_customer FROM `'._DB_PREFIX_.'pulse_crm_campaign_recipient` WHERE id_pulse_crm_campaign='.(int) $id.')');
        foreach ($rows as $n => $r) {
            $idc = (int) $r['id_customer'];
            $to = $channel === 'email' ? $r['email'] : $r['phone'];
            $may = PulseCrmProfile::mayContact($idc, $channel);
            $reason = $may === true ? ($to ? '' : ($channel === 'email' ? 'no_email' : 'no_phone')) : $may;
            $db->insert('pulse_crm_campaign_recipient', array('id_pulse_crm_campaign' => (int) $id, 'id_customer' => $idc,
                'variant' => ($split > 0 && ($n % 100) < $split) ? 'b' : 'a', 'to_addr' => pSQL($to), 'token' => PulseCrmService::token(16),
                'status' => $reason ? 'skipped' : 'queued', 'skip_reason' => pSQL($reason), 'date_queued' => $now));
            if ($reason) { $skipped++; } else { $queued++; }
        }
        self::recount($id);
        $db->update('pulse_crm_campaign', array('status' => 'scheduled', 'date_upd' => $now), 'id_pulse_crm_campaign='.(int) $id);
        PulseCoreService::audit('pulsecrm', 'campaign_queue', array('queued' => $queued, 'skipped' => $skipped), 'pulse_crm_campaign', (int) $id);
        return array('queued' => $queued, 'skipped' => $skipped);
    }

    /**
     * Send up to the campaign's throttle. Stops itself inside quiet hours instead of waking a guest at
     * 02:00 — the cron simply picks it up again after the window closes.
     */
    public static function send($id, $limit = null)
    {
        $c = self::get($id); if (!$c) { throw new PrestaShopException('No such campaign'); }
        if (in_array($c['status'], array('paused', 'cancelled'))) { return array('sent' => 0, 'failed' => 0, 'stopped' => $c['status']); }
        if (PulseCrmService::inQuietHours($c['quiet_from'], $c['quiet_to'])) { return array('sent' => 0, 'failed' => 0, 'stopped' => 'quiet_hours', 'resume_at' => PulseCrmService::afterQuiet($c['quiet_from'], $c['quiet_to'])); }
        $db = Db::getInstance(); $limit = (int) ($limit ? $limit : $c['throttle_per_run']);
        $db->update('pulse_crm_campaign', array('status' => 'sending', 'last_run_at' => date('Y-m-d H:i:s')), 'id_pulse_crm_campaign='.(int) $id);
        $sent = 0; $failed = 0; $skipped = 0;
        $recips = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_campaign_recipient` WHERE id_pulse_crm_campaign='.(int) $id.' AND status="queued" ORDER BY id_pulse_crm_campaign_recipient LIMIT '.$limit);
        foreach ($recips as $r) {
            $res = self::sendOne($c, $r);
            if ($res['ok']) { $sent++; } elseif ($res['reason'] === 'send_failed' || $res['reason'] === 'error') { $failed++; } else { $skipped++; }
        }
        self::recount($id);
        $left = (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_campaign_recipient` WHERE id_pulse_crm_campaign='.(int) $id.' AND status="queued"');
        if (!$left) { $db->update('pulse_crm_campaign', array('status' => self::isRecurring($c) ? 'scheduled' : 'sent', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_campaign='.(int) $id); }
        return array('sent' => $sent, 'failed' => $failed, 'skipped' => $skipped, 'remaining' => $left);
    }

    /** One recipient: render the variant, hand it to PulseCrmComms, record the outcome. */
    protected static function sendOne(array $c, array $r)
    {
        $vars = PulseCrmService::mergeVars((int) $r['id_customer']);
        $subject = $r['variant'] === 'b' && $c['subject_b'] ? $c['subject_b'] : $c['subject'];
        $body = $r['variant'] === 'b' && $c['body_b'] ? $c['body_b'] : $c['body'];
        $unsub = PulseCrmService::link('track', array('a' => 'unsub', 't' => $r['token']));
        $vars['unsubscribe_url'] = $unsub; $vars['subject'] = PulseCrmService::render($subject, $vars);
        $text = PulseCrmService::render($body, $vars); $vars['text'] = $text;
        if ($c['channel'] === 'email') { $vars['html'] = PulseCrmComms::trackify(self::htmlBody($text), $r['token'], $unsub); }
        $res = PulseCrmComms::deliver((int) $r['id_customer'], $c['channel'], 'crm_campaign', $vars, array('kind' => 'campaign', 'reference' => 'campaign:'.$c['id_pulse_crm_campaign'], 'quiet_from' => $c['quiet_from'], 'quiet_to' => $c['quiet_to']));
        $u = array('date_sent' => date('Y-m-d H:i:s'));
        if ($res['ok']) { $u['status'] = 'sent'; }
        elseif (in_array($res['reason'], array('send_failed', 'error'))) { $u['status'] = 'failed'; $u['error'] = pSQL(isset($res['error']) ? $res['error'] : 'delivery failed'); }
        else { $u['status'] = 'skipped'; $u['skip_reason'] = pSQL($res['reason']); }
        Db::getInstance()->update('pulse_crm_campaign_recipient', $u, 'id_pulse_crm_campaign_recipient='.(int) $r['id_pulse_crm_campaign_recipient']);
        return $res;
    }

    /** Plain text bodies become simple, readable HTML — no template engine a marketer can break. */
    public static function htmlBody($text)
    {
        if (strpos($text, '<') !== false && strpos($text, '>') !== false) { return $text; }
        $out = '';
        foreach (preg_split('/\n{2,}/', trim($text)) as $p) { $out .= '<p style="margin:0 0 14px;font:15px/1.55 Helvetica,Arial,sans-serif;color:#222">'.nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8')).'</p>'; }
        return '<div style="max-width:560px;margin:0 auto">'.$out.'</div>';
    }

    public static function isRecurring(array $c) { return in_array($c['schedule_type'], array('daily', 'weekly', 'monthly')); }

    /** Campaigns whose schedule says they are due now. */
    public static function due()
    {
        $now = date('Y-m-d H:i:s'); $today = date('Y-m-d');
        $out = array();
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_campaign` WHERE status IN ("scheduled","sending")') as $c) {
            if ((int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_campaign_recipient` WHERE id_pulse_crm_campaign='.(int) $c['id_pulse_crm_campaign'].' AND status="queued"')) { $out[] = $c; continue; }
            if (!self::isRecurring($c)) { continue; }
            $last = $c['last_run_at'] ? substr($c['last_run_at'], 0, 10) : '';
            if ($last === $today) { continue; }
            if ($c['schedule_type'] === 'weekly' && (int) date('w') !== (int) $c['recur_dow']) { continue; }
            if ($c['schedule_type'] === 'monthly' && (int) date('j') !== (int) $c['recur_dom']) { continue; }
            if ($c['send_at'] && substr($c['send_at'], 11) > substr($now, 11)) { continue; }
            $out[] = $c;
        }
        return $out;
    }

    /** Cron entry point: refill recurring lists, then send a throttled slice of everything due. */
    public static function runDue($maxCampaigns = 10)
    {
        $r = array('campaigns' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0);
        foreach (array_slice(self::due(), 0, (int) $maxCampaigns) as $c) {
            $id = (int) $c['id_pulse_crm_campaign'];
            if (self::isRecurring($c)) { try { self::queue($id); } catch (Exception $e) { PulseCoreService::audit('pulsecrm', 'campaign_queue_failed', array('id' => $id, 'error' => $e->getMessage())); } }
            $x = self::send($id);
            $r['campaigns']++; $r['sent'] += $x['sent']; $r['failed'] += $x['failed']; $r['skipped'] += isset($x['skipped']) ? $x['skipped'] : 0;
        }
        return $r;
    }

    public static function recount($id)
    {
        $s = Db::getInstance()->getRow('SELECT SUM(status="queued") q, SUM(status IN ("sent","opened","clicked","unsubscribed")) s, SUM(status="failed") f,
                SUM(open_count>0) o, SUM(click_count>0) cl, SUM(status="unsubscribed") u, SUM(status="skipped") sk
            FROM `'._DB_PREFIX_.'pulse_crm_campaign_recipient` WHERE id_pulse_crm_campaign='.(int) $id);
        return Db::getInstance()->update('pulse_crm_campaign', array('count_queued' => (int) $s['q'], 'count_sent' => (int) $s['s'], 'count_failed' => (int) $s['f'],
            'count_opened' => (int) $s['o'], 'count_clicked' => (int) $s['cl'], 'count_unsub' => (int) $s['u'], 'count_skipped' => (int) $s['sk']), 'id_pulse_crm_campaign='.(int) $id);
    }

    public static function stats($id)
    {
        $rows = Db::getInstance()->executeS('SELECT variant, COUNT(*) total, SUM(status IN ("sent","opened","clicked","unsubscribed")) sent, SUM(open_count>0) opened, SUM(click_count>0) clicked, SUM(status="unsubscribed") unsub, SUM(status="failed") failed, SUM(status="skipped") skipped
            FROM `'._DB_PREFIX_.'pulse_crm_campaign_recipient` WHERE id_pulse_crm_campaign='.(int) $id.' GROUP BY variant');
        foreach ($rows as &$r) {
            $r['open_pct'] = $r['sent'] ? round($r['opened'] / $r['sent'] * 100, 1) : 0;
            $r['click_pct'] = $r['sent'] ? round($r['clicked'] / $r['sent'] * 100, 1) : 0;
            $r['unsub_pct'] = $r['sent'] ? round($r['unsub'] / $r['sent'] * 100, 1) : 0;
        }
        return $rows;
    }

    public static function recipients($id, $status = null, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT r.*, CONCAT(c.firstname," ",c.lastname) guest FROM `'._DB_PREFIX_.'pulse_crm_campaign_recipient` r
            INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=r.id_customer WHERE r.id_pulse_crm_campaign='.(int) $id.($status ? ' AND r.status="'.pSQL($status).'"' : '').'
            ORDER BY r.id_pulse_crm_campaign_recipient LIMIT '.(int) $limit);
    }

    /* ---------- tracking callbacks ---------- */

    public static function byToken($token) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_campaign_recipient` WHERE token="'.pSQL($token).'"'); }

    public static function markOpen($token)
    {
        $r = self::byToken($token); if (!$r) { return false; }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_campaign_recipient` SET open_count=open_count+1, date_opened=COALESCE(date_opened,NOW()), status=IF(status IN ("sent","opened"),"opened",status) WHERE id_pulse_crm_campaign_recipient='.(int) $r['id_pulse_crm_campaign_recipient']);
        self::recount((int) $r['id_pulse_crm_campaign']);
        return true;
    }

    public static function markClick($token)
    {
        $r = self::byToken($token); if (!$r) { return false; }
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_campaign_recipient` SET click_count=click_count+1, date_clicked=COALESCE(date_clicked,NOW()), open_count=GREATEST(open_count,1), date_opened=COALESCE(date_opened,NOW()), status=IF(status IN ("sent","opened","clicked"),"clicked",status) WHERE id_pulse_crm_campaign_recipient='.(int) $r['id_pulse_crm_campaign_recipient']);
        self::recount((int) $r['id_pulse_crm_campaign']);
        return true;
    }

    /** The unsubscribe link is a hard stop: opt the channel out, cancel anything still queued for them. */
    public static function unsubscribe($token, $reason = '')
    {
        $r = self::byToken($token); if (!$r) { return false; }
        $c = self::get((int) $r['id_pulse_crm_campaign']);
        $channel = $c && $c['channel'] !== 'email' ? $c['channel'] : 'email';
        PulseCrmProfile::setConsent((int) $r['id_customer'], $channel, 'opt_out', 'campaign_unsub', 'campaign:'.$r['id_pulse_crm_campaign'], $reason);
        Db::getInstance()->update('pulse_crm_campaign_recipient', array('status' => 'unsubscribed'), 'id_pulse_crm_campaign_recipient='.(int) $r['id_pulse_crm_campaign_recipient']);
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_campaign_recipient` SET status="skipped", skip_reason="opted_out" WHERE id_customer='.(int) $r['id_customer'].' AND status="queued"');
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_journey_run` SET status="cancelled", last_error="unsubscribed", date_upd=NOW() WHERE id_customer='.(int) $r['id_customer'].' AND status="active"');
        self::recount((int) $r['id_pulse_crm_campaign']);
        return $r;
    }

    public static function remove($id)
    {
        Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_campaign_recipient` WHERE id_pulse_crm_campaign='.(int) $id);
        return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_campaign` WHERE id_pulse_crm_campaign='.(int) $id);
    }
}
