<?php
/**
 * Event-triggered automation. A journey is a trigger plus an ordered list of steps; each step has a
 * delay, an optional condition and an action. A run is one guest walking that list — it holds its own
 * next_run_at so the cron only has to ask "what is due?", and it stops itself the moment the guest
 * unsubscribes, is blacklisted or has already been written to today.
 */
class PulseCrmJourney
{
    public static function all($activeOnly = false)
    {
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_journey`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY trigger_event, name');
        foreach ($rows as &$r) { $r['steps'] = self::steps((int) $r['id_pulse_crm_journey']); }
        return $rows;
    }
    public static function get($id)
    {
        $j = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_journey` WHERE id_pulse_crm_journey='.(int) $id);
        if ($j) { $j['steps'] = self::steps($id); }
        return $j;
    }
    public static function byCode($code) { $j = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_journey` WHERE code="'.pSQL($code).'"'); return $j ? self::get((int) $j['id_pulse_crm_journey']) : null; }
    public static function steps($idJourney) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_journey_step` WHERE id_pulse_crm_journey='.(int) $idJourney.' AND active=1 ORDER BY sort'); }

    public static function save(array $d, $id = 0)
    {
        $row = array('name' => pSQL($d['name']), 'description' => pSQL(isset($d['description']) ? $d['description'] : ''), 'trigger_event' => pSQL($d['trigger_event']),
            'active' => isset($d['active']) ? (int) $d['active'] : 1, 'quiet_from' => pSQL(isset($d['quiet_from']) ? $d['quiet_from'] : '21:00'),
            'quiet_to' => pSQL(isset($d['quiet_to']) ? $d['quiet_to'] : '08:00'), 'suppress_days' => (int) (isset($d['suppress_days']) ? $d['suppress_days'] : 1), 'date_upd' => date('Y-m-d H:i:s'));
        if ($id) { Db::getInstance()->update('pulse_crm_journey', $row, 'id_pulse_crm_journey='.(int) $id); return (int) $id; }
        $row['code'] = pSQL(Tools::substr(Tools::str2url(isset($d['code']) && $d['code'] ? $d['code'] : $d['name']), 0, 30)); $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_crm_journey', $row);
        return (int) Db::getInstance()->Insert_ID();
    }

    public static function saveStep(array $d, $id = 0)
    {
        $row = array('id_pulse_crm_journey' => (int) $d['id_pulse_crm_journey'], 'sort' => (int) $d['sort'], 'name' => pSQL(isset($d['name']) ? $d['name'] : ''),
            'delay_minutes' => (int) $d['delay_minutes'], 'condition_json' => pSQL(is_array(isset($d['condition_json']) ? $d['condition_json'] : '') ? json_encode($d['condition_json']) : (isset($d['condition_json']) ? $d['condition_json'] : ''), true),
            'action' => pSQL($d['action']), 'channel' => pSQL(isset($d['channel']) ? $d['channel'] : 'auto'), 'template_code' => pSQL(isset($d['template_code']) ? $d['template_code'] : ''),
            'subject' => pSQL(isset($d['subject']) ? $d['subject'] : ''), 'body' => pSQL(isset($d['body']) ? $d['body'] : '', true),
            'action_json' => pSQL(is_array(isset($d['action_json']) ? $d['action_json'] : '') ? json_encode($d['action_json']) : (isset($d['action_json']) ? $d['action_json'] : ''), true),
            'active' => isset($d['active']) ? (int) $d['active'] : 1);
        if ($id) { Db::getInstance()->update('pulse_crm_journey_step', $row, 'id_pulse_crm_journey_step='.(int) $id); return (int) $id; }
        Db::getInstance()->insert('pulse_crm_journey_step', $row);
        return (int) Db::getInstance()->Insert_ID();
    }
    public static function removeStep($id) { return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_journey_step` WHERE id_pulse_crm_journey_step='.(int) $id); }

    /* ---------- running ---------- */

    /** Start every active journey listening for $event. One live run per guest per journey per stay. */
    public static function trigger($event, array $ctx)
    {
        $started = 0;
        if (empty($ctx['id_customer'])) { return 0; }
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_journey` WHERE active=1 AND trigger_event="'.pSQL($event).'"') as $j) {
            if (self::start((int) $j['id_pulse_crm_journey'], $ctx)) { $started++; }
        }
        return $started;
    }

    public static function start($idJourney, array $ctx)
    {
        $j = self::get($idJourney); if (!$j || !$j['active'] || !$j['steps']) { return false; }
        $idc = (int) $ctx['id_customer']; $idb = !empty($ctx['id_htl_booking']) ? (int) $ctx['id_htl_booking'] : 0;
        $dup = Db::getInstance()->getValue('SELECT id_pulse_crm_journey_run FROM `'._DB_PREFIX_.'pulse_crm_journey_run` WHERE id_pulse_crm_journey='.(int) $idJourney
            .' AND id_customer='.$idc.($idb ? ' AND id_htl_booking='.$idb : ' AND date_add>DATE_SUB(NOW(), INTERVAL 7 DAY)').' AND status="active"');
        if ($dup) { return false; }
        $first = $j['steps'][0];
        Db::getInstance()->insert('pulse_crm_journey_run', array('id_pulse_crm_journey' => (int) $idJourney, 'id_customer' => $idc,
            'id_htl_booking' => $idb ? $idb : null, 'id_room' => !empty($ctx['id_room']) ? (int) $ctx['id_room'] : null, 'step_index' => 0, 'status' => 'active',
            'next_run_at' => date('Y-m-d H:i:s', time() + (int) $first['delay_minutes'] * 60), 'context_json' => pSQL(json_encode($ctx), true),
            'business_date' => PulseCrmService::bd(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_journey` SET count_started=count_started+1 WHERE id_pulse_crm_journey='.(int) $idJourney);
        return (int) Db::getInstance()->Insert_ID();
    }

    /** Cancel every live run for a stay — used on cancellation, no-show and early departure. */
    public static function cancelFor($idCustomer, $idBooking = null, $reason = 'cancelled')
    {
        return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_journey_run` SET status="cancelled", last_error="'.pSQL($reason).'", date_upd=NOW()
            WHERE status="active" AND id_customer='.(int) $idCustomer.($idBooking ? ' AND id_htl_booking='.(int) $idBooking : ''));
    }

    /** Cron: advance every run that is due. Chunked so a shared host never times out mid-journey. */
    public static function advance($limit = 100)
    {
        $out = array('runs' => 0, 'steps' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'finished' => 0);
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_journey_run` WHERE status="active" AND next_run_at<=NOW() ORDER BY next_run_at LIMIT '.(int) $limit) as $run) {
            $out['runs']++;
            $r = self::step($run);
            $out['steps'] += $r['steps']; $out['sent'] += $r['sent']; $out['skipped'] += $r['skipped']; $out['failed'] += $r['failed'];
            if ($r['finished']) { $out['finished']++; }
        }
        return $out;
    }

    /** Execute the current step of one run and schedule the next. */
    public static function step(array $run)
    {
        $out = array('steps' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'finished' => false);
        $j = self::get((int) $run['id_pulse_crm_journey']);
        $db = Db::getInstance(); $idRun = (int) $run['id_pulse_crm_journey_run'];
        if (!$j || !$j['active']) { $db->update('pulse_crm_journey_run', array('status' => 'cancelled', 'last_error' => 'journey disabled', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_journey_run='.$idRun); return $out; }
        $idx = (int) $run['step_index'];
        if (!isset($j['steps'][$idx])) { return self::finish($idRun, $j, $out); }
        $s = $j['steps'][$idx];
        $ctx = self::context($run);
        $out['steps']++;
        if (!self::conditionMet($s['condition_json'], $ctx)) {
            self::log($idRun, (int) $s['id_pulse_crm_journey_step'], $s['action'], 'skipped', 'condition not met');
            $out['skipped']++;
            return self::next($run, $j, $idx, $out);
        }
        try {
            $r = self::act($s, $run, $ctx, $j);
            self::log($idRun, (int) $s['id_pulse_crm_journey_step'], $s['action'], $r['ok'] ? 'done' : 'skipped', $r['message']);
            if ($r['ok'] && $s['action'] === 'send_template') { $out['sent']++; } elseif (!$r['ok']) { $out['skipped']++; }
            if ($s['action'] === 'stop' || !empty($r['stop'])) { return self::finish($idRun, $j, $out); }
        } catch (Exception $e) {
            self::log($idRun, (int) $s['id_pulse_crm_journey_step'], $s['action'], 'failed', Tools::substr($e->getMessage(), 0, 255));
            $db->update('pulse_crm_journey_run', array('last_error' => pSQL(Tools::substr($e->getMessage(), 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_journey_run='.$idRun);
            $out['failed']++;
        }
        return self::next($run, $j, $idx, $out);
    }

    protected static function next(array $run, array $j, $idx, array $out)
    {
        $idRun = (int) $run['id_pulse_crm_journey_run'];
        if (!isset($j['steps'][$idx + 1])) { return self::finish($idRun, $j, $out); }
        $delay = (int) $j['steps'][$idx + 1]['delay_minutes'];
        Db::getInstance()->update('pulse_crm_journey_run', array('step_index' => $idx + 1, 'next_run_at' => date('Y-m-d H:i:s', time() + $delay * 60), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_journey_run='.$idRun);
        return $out;
    }

    protected static function finish($idRun, array $j, array $out)
    {
        Db::getInstance()->update('pulse_crm_journey_run', array('status' => 'done', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_journey_run='.(int) $idRun);
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_journey` SET count_done=count_done+1 WHERE id_pulse_crm_journey='.(int) $j['id_pulse_crm_journey']);
        $out['finished'] = true;
        return $out;
    }

    /** Everything a condition or a merge tag can see about this run. */
    public static function context(array $run)
    {
        $ctx = $run['context_json'] ? json_decode($run['context_json'], true) : array();
        if (!is_array($ctx)) { $ctx = array(); }
        $id = (int) $run['id_customer'];
        $ctx = array_merge($ctx, PulseCrmService::mergeVars($id, array('id_htl_booking' => $run['id_htl_booking'])));
        $px = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_profile_ext` WHERE id_customer='.$id);
        $gp = PulseCrmService::gp() ? Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_guest_profile` WHERE id_customer='.$id) : array();
        $ctx['nps'] = $px && $px['nps_last'] !== null ? (int) $px['nps_last'] : null;
        $ctx['nps_band'] = $px ? $px['nps_band'] : 'unknown';
        $ctx['gss'] = $px ? (float) $px['gss_avg'] : 0;
        $ctx['stays'] = $gp ? (int) $gp['stays'] : 0;
        $ctx['nights'] = $gp ? (int) $gp['nights'] : 0;
        $ctx['lifetime_revenue'] = $gp ? (float) $gp['lifetime_revenue'] : 0;
        $ctx['last_stay_days'] = ($gp && $gp['last_stay']) ? (int) floor((time() - strtotime($gp['last_stay'])) / 86400) : null;
        $ctx['is_member'] = PulseCrmLoyalty::memberOf($id) ? 1 : 0;
        $ctx['id_customer'] = $id; $ctx['id_htl_booking'] = $run['id_htl_booking']; $ctx['id_room'] = $run['id_room'];
        return $ctx;
    }

    /** A step condition is the same shape as a segment rule, evaluated in PHP against the run context. */
    public static function conditionMet($json, array $ctx)
    {
        if (!$json) { return true; }
        $c = is_array($json) ? $json : json_decode($json, true);
        if (!is_array($c) || empty($c['rules'])) { return true; }
        $any = isset($c['match']) && $c['match'] === 'any';
        $result = !$any;
        foreach ($c['rules'] as $rule) {
            $f = isset($rule['field']) ? $rule['field'] : ''; $op = isset($rule['op']) ? $rule['op'] : 'eq'; $v = isset($rule['value']) ? $rule['value'] : '';
            $actual = isset($ctx[$f]) ? $ctx[$f] : null;
            switch ($op) {
                case 'ne': $ok = $actual != $v; break;
                case 'gt': $ok = $actual !== null && (float) $actual > (float) $v; break;
                case 'gte': $ok = $actual !== null && (float) $actual >= (float) $v; break;
                case 'lt': $ok = $actual !== null && (float) $actual < (float) $v; break;
                case 'lte': $ok = $actual !== null && (float) $actual <= (float) $v; break;
                case 'in': $ok = in_array((string) $actual, array_map('trim', is_array($v) ? $v : explode(',', (string) $v))); break;
                case 'not_in': $ok = !in_array((string) $actual, array_map('trim', is_array($v) ? $v : explode(',', (string) $v))); break;
                case 'contains': $ok = $actual !== null && stripos((string) $actual, (string) $v) !== false; break;
                case 'is_set': $ok = $actual !== null && $actual !== ''; break;
                case 'is_null': $ok = $actual === null || $actual === ''; break;
                default: $ok = (string) $actual === (string) $v;
            }
            if ($any && $ok) { return true; }
            if (!$any && !$ok) { return false; }
        }
        return $result;
    }

    /** Perform one step's action. Returns array(ok, message[, stop]). */
    protected static function act(array $s, array $run, array $ctx, array $j)
    {
        $idc = (int) $run['id_customer'];
        $extra = $s['action_json'] ? json_decode($s['action_json'], true) : array();
        if (!is_array($extra)) { $extra = array(); }
        switch ($s['action']) {
            case 'send_template':
                $channel = $s['channel'] === 'auto' ? self::preferredChannel($idc) : $s['channel'];
                $vars = $ctx;
                if (!empty($extra['survey'])) {
                    $sv = PulseCrmSurvey::byCode($extra['survey']);
                    if ($sv) { $inv = PulseCrmSurvey::invite((int) $sv['id_pulse_crm_survey'], $idc, $run['id_htl_booking'], $channel); $vars['survey_url'] = PulseCrmSurvey::url($inv['token']); }
                }
                $vars['portal_url'] = PulseCrmService::cfg('PORTAL_URL', PulseCrmService::baseUrl());
                $vars['review_url'] = PulseCrmService::cfg('REVIEW_URL', PulseCrmService::baseUrl());
                $vars['book_url'] = PulseCrmService::baseUrl();
                $template = $s['template_code'] ? $s['template_code'] : 'crm_campaign';
                if ($s['body']) {
                    $vars['subject'] = PulseCrmService::render($s['subject'], $vars);
                    $vars['text'] = PulseCrmService::render($s['body'], $vars);
                    if ($channel === 'email') { $vars['html'] = PulseCrmCampaign::htmlBody($vars['text']); }
                    $template = PulseCrmComms::registerAdHoc('crm_journey_'.(int) $s['id_pulse_crm_journey_step'], $vars['subject'], $vars['text']);
                }
                $r = PulseCrmComms::deliver($idc, $channel, $template, $vars, array('kind' => 'journey', 'reference' => 'journey:'.$j['code'],
                    'quiet_from' => $j['quiet_from'], 'quiet_to' => $j['quiet_to'], 'suppress_days' => (int) $j['suppress_days']));
                return array('ok' => $r['ok'], 'message' => $channel.': '.$r['reason']);
            case 'create_ticket':
                if (!class_exists('PulseTicket')) { return array('ok' => false, 'message' => 'Front Desk not installed'); }
                $id = PulseTicket::create(array('category' => isset($extra['category']) ? $extra['category'] : 'concierge', 'department' => isset($extra['department']) ? $extra['department'] : 'frontdesk',
                    'priority' => isset($extra['priority']) ? $extra['priority'] : 'normal', 'title' => PulseCrmService::render(isset($extra['title']) ? $extra['title'] : $s['name'], $ctx),
                    'description' => PulseCrmService::render($s['body'], $ctx), 'id_room' => $run['id_room'], 'id_htl_booking' => $run['id_htl_booking'], 'id_customer' => $idc, 'source' => 'crm'));
                return array('ok' => true, 'message' => 'ticket '.$id);
            case 'open_case':
                $id = PulseCrmCase::open(array('source' => 'audit', 'severity' => isset($extra['severity']) ? $extra['severity'] : 'medium', 'department' => isset($extra['department']) ? $extra['department'] : 'frontdesk',
                    'id_customer' => $idc, 'id_htl_booking' => $run['id_htl_booking'], 'id_room' => $run['id_room'],
                    'title' => PulseCrmService::render(isset($extra['title']) ? $extra['title'] : $s['name'], $ctx), 'description' => PulseCrmService::render($s['body'], $ctx)));
                return array('ok' => true, 'message' => 'case '.$id);
            case 'add_tag':
                PulseCrmProfile::tag($idc, isset($extra['tag']) ? $extra['tag'] : 'journey', 'journey');
                return array('ok' => true, 'message' => 'tagged '.(isset($extra['tag']) ? $extra['tag'] : 'journey'));
            case 'add_points':
                $m = PulseCrmLoyalty::memberOf($idc);
                if (!$m) { return array('ok' => false, 'message' => 'not a member'); }
                PulseCrmLoyalty::award((int) $m['id_pulse_crm_member'], 'bonus', (int) (isset($extra['points']) ? $extra['points'] : 0), 'journey', isset($extra['reason']) ? $extra['reason'] : $j['name'], array('reference' => $j['code']));
                return array('ok' => true, 'message' => (int) (isset($extra['points']) ? $extra['points'] : 0).' points');
            case 'notify_manager':
                $to = isset($extra['email']) ? $extra['email'] : PulseCrmService::cfg('MANAGER_EMAIL', Configuration::get('PS_SHOP_EMAIL'));
                $body = PulseCrmService::render($s['body'] ? $s['body'] : $s['name'], $ctx);
                if (class_exists('PulseTrace')) { PulseTrace::add('alert', Tools::substr($body, 0, 250), date('Y-m-d H:i:s'), $run['id_htl_booking'], $run['id_room'], $idc, 'frontdesk'); }
                $ok = $to && Validate::isEmail($to) ? (bool) Mail::Send((int) Context::getContext()->language->id, 'crm_generic', PulseCrmService::render($s['subject'] ? $s['subject'] : $j['name'], $ctx),
                    array('{message}' => PulseCrmCampaign::htmlBody($body), '{title}' => $j['name']), $to, null, null, null, null, null, _PS_MODULE_DIR_.'pulsecrm/mails/') : false;
                return array('ok' => true, 'message' => $ok ? 'manager emailed' : 'trace raised');
            case 'stop':
            default:
                return array('ok' => true, 'message' => 'stop', 'stop' => true);
        }
    }

    /** Which channel this guest actually wants — their stated preference, falling back to what we can reach. */
    public static function preferredChannel($idCustomer)
    {
        $p = Db::getInstance()->getValue('SELECT preferred_channel FROM `'._DB_PREFIX_.'pulse_crm_profile_ext` WHERE id_customer='.(int) $idCustomer);
        if ($p && $p !== 'none' && PulseCrmProfile::mayContact($idCustomer, $p) === true) { return $p; }
        foreach (array('email', 'whatsapp', 'sms') as $ch) { if (PulseCrmProfile::mayContact($idCustomer, $ch) === true) { return $ch; } }
        return 'email';
    }

    protected static function log($idRun, $idStep, $action, $result, $message)
    {
        return Db::getInstance()->insert('pulse_crm_journey_log', array('id_pulse_crm_journey_run' => (int) $idRun, 'id_pulse_crm_journey_step' => (int) $idStep ?: null,
            'action' => pSQL($action), 'result' => pSQL($result), 'message' => pSQL(Tools::substr((string) $message, 0, 250)), 'date_add' => date('Y-m-d H:i:s')));
    }

    public static function runs($idJourney = 0, $status = null, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT r.*, j.name journey, CONCAT(c.firstname," ",c.lastname) guest FROM `'._DB_PREFIX_.'pulse_crm_journey_run` r
            INNER JOIN `'._DB_PREFIX_.'pulse_crm_journey` j ON j.id_pulse_crm_journey=r.id_pulse_crm_journey
            LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=r.id_customer
            WHERE 1'.($idJourney ? ' AND r.id_pulse_crm_journey='.(int) $idJourney : '').($status ? ' AND r.status="'.pSQL($status).'"' : '').'
            ORDER BY r.next_run_at DESC LIMIT '.(int) $limit);
    }
    public static function logs($idRun) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_journey_log` WHERE id_pulse_crm_journey_run='.(int) $idRun.' ORDER BY id_pulse_crm_journey_log'); }

    /**
     * Journeys that watch the calendar rather than an event: the mid-stay ping on night two, the T+90
     * win-back and the birthday/anniversary greeting. Called once a day by cron.
     */
    public static function triggerScheduled()
    {
        $out = array('mid_stay' => 0, 'lapsed' => 0, 'birthday' => 0, 'anniversary' => 0);
        if (PulseCrmService::tableExists('htl_booking_detail') && class_exists('HotelBookingDetail')) {
            foreach (Db::getInstance()->executeS('SELECT id, id_customer, id_room FROM `'._DB_PREFIX_.'htl_booking_detail`
                WHERE is_cancelled=0 AND is_refunded=0 AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND DATEDIFF(CURDATE(), date_from)=2 AND DATEDIFF(date_to, CURDATE())>=1 LIMIT 200') as $b) {
                $out['mid_stay'] += self::trigger('mid_stay', array('id_customer' => (int) $b['id_customer'], 'id_htl_booking' => (int) $b['id'], 'id_room' => (int) $b['id_room']));
            }
        }
        if (PulseCrmService::gp()) {
            foreach (Db::getInstance()->executeS('SELECT gp.id_customer FROM `'._DB_PREFIX_.'pulse_guest_profile` gp INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=gp.id_customer AND c.deleted=0
                WHERE gp.last_stay IS NOT NULL AND DATEDIFF(CURDATE(), gp.last_stay)=90 AND gp.blacklisted=0 LIMIT 200') as $g) {
                $out['lapsed'] += self::trigger('lapsed', array('id_customer' => (int) $g['id_customer']));
            }
        }
        foreach (PulseCrmProfile::occasionsDue() as $o) {
            $ev = $o['type'] === 'anniversary' ? 'anniversary' : ($o['type'] === 'birthday' ? 'birthday' : null);
            if (!$ev) { continue; }
            $n = self::trigger($ev, array('id_customer' => (int) $o['id_customer'], 'occasion' => $o['type'], 'occasion_note' => $o['note']));
            if ($n) { Db::getInstance()->update('pulse_crm_occasion', array('last_reminded' => date('Y-m-d')), 'id_pulse_crm_occasion='.(int) $o['id_pulse_crm_occasion']); }
            $out[$ev] += $n;
        }
        return $out;
    }
}
