<?php
/**
 * Feedback and surveys: per-touchpoint question sets, an invite that creates a pending response with
 * its own unguessable token, a public mobile page to fill it in, and scoring — NPS, a guest
 * satisfaction score and department attribution — computed at submit. A low score opens a recovery case
 * there and then, because the value of in-stay feedback is entirely in how fast someone acts on it.
 */
class PulseCrmSurvey
{
    public static function all($activeOnly = false) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_survey`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY touchpoint, name'); }
    public static function byCode($code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_survey` WHERE code="'.pSQL($code).'" AND active=1'); }
    public static function get($id)
    {
        $s = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_survey` WHERE id_pulse_crm_survey='.(int) $id);
        if ($s) { $s['questions'] = self::questions($id); }
        return $s;
    }
    public static function questions($idSurvey)
    {
        $rows = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_survey_question` WHERE id_pulse_crm_survey='.(int) $idSurvey.' AND active=1 ORDER BY sort');
        foreach ($rows as &$r) { $r['options'] = $r['options_json'] ? json_decode($r['options_json'], true) : array(); if (!is_array($r['options'])) { $r['options'] = array(); } }
        return $rows;
    }

    public static function save(array $d, $id = 0)
    {
        $row = array('name' => pSQL($d['name']), 'touchpoint' => pSQL($d['touchpoint']), 'intro' => pSQL(isset($d['intro']) ? $d['intro'] : '', true),
            'thanks' => pSQL(isset($d['thanks']) ? $d['thanks'] : '', true), 'low_score_threshold' => (int) $d['low_score_threshold'],
            'expiry_days' => (int) (isset($d['expiry_days']) ? $d['expiry_days'] : 30), 'active' => isset($d['active']) ? (int) $d['active'] : 1, 'date_upd' => date('Y-m-d H:i:s'));
        if ($id) { Db::getInstance()->update('pulse_crm_survey', $row, 'id_pulse_crm_survey='.(int) $id); return (int) $id; }
        $row['code'] = pSQL(Tools::substr(Tools::str2url(isset($d['code']) && $d['code'] ? $d['code'] : $d['name']), 0, 30)); $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_crm_survey', $row);
        return (int) Db::getInstance()->Insert_ID();
    }

    public static function saveQuestion(array $d, $id = 0)
    {
        $opts = isset($d['options']) ? (is_array($d['options']) ? $d['options'] : array_values(array_filter(array_map('trim', explode('|', $d['options']))))) : array();
        $row = array('id_pulse_crm_survey' => (int) $d['id_pulse_crm_survey'], 'sort' => (int) $d['sort'], 'type' => pSQL($d['type']), 'label' => pSQL($d['label']),
            'options_json' => pSQL($opts ? json_encode($opts) : '', true), 'department' => pSQL(isset($d['department']) ? $d['department'] : ''),
            'required' => !empty($d['required']) ? 1 : 0, 'active' => isset($d['active']) ? (int) $d['active'] : 1);
        if ($id) { Db::getInstance()->update('pulse_crm_survey_question', $row, 'id_pulse_crm_survey_question='.(int) $id); return (int) $id; }
        $row['code'] = pSQL(Tools::substr(Tools::str2url(isset($d['code']) && $d['code'] ? $d['code'] : $d['label']), 0, 30));
        Db::getInstance()->insert('pulse_crm_survey_question', $row);
        return (int) Db::getInstance()->Insert_ID();
    }
    public static function removeQuestion($id) { return Db::getInstance()->update('pulse_crm_survey_question', array('active' => 0), 'id_pulse_crm_survey_question='.(int) $id); }

    /* ---------- invitations ---------- */

    /** Create the pending response (and therefore the link) for one guest. Reuses a live pending invite. */
    public static function invite($idSurvey, $idCustomer, $idBooking = null, $channel = 'email')
    {
        $s = self::get($idSurvey); if (!$s) { throw new PrestaShopException('No such survey'); }
        $open = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_survey_response` WHERE id_pulse_crm_survey='.(int) $idSurvey.' AND id_customer='.(int) $idCustomer
            .($idBooking ? ' AND id_htl_booking='.(int) $idBooking : '').' AND status IN ("pending","partial") AND (expires_on IS NULL OR expires_on>=CURDATE())');
        if ($open) { return $open; }
        $room = null;
        if ($idBooking && PulseCrmService::tableExists('htl_booking_detail')) { $room = (int) Db::getInstance()->getValue('SELECT id_room FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id='.(int) $idBooking); }
        Db::getInstance()->insert('pulse_crm_survey_response', array('id_pulse_crm_survey' => (int) $idSurvey, 'token' => PulseCrmService::token(16),
            'id_customer' => (int) $idCustomer, 'id_htl_booking' => $idBooking ? (int) $idBooking : null, 'id_room' => $room ? $room : null,
            'status' => 'pending', 'channel' => pSQL($channel), 'expires_on' => date('Y-m-d', strtotime('+'.(int) $s['expiry_days'].' day')),
            'sent_at' => date('Y-m-d H:i:s'), 'business_date' => PulseCrmService::bd(), 'date_add' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID();
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_survey_response` WHERE id_pulse_crm_survey_response='.$id);
    }

    public static function url($token) { return PulseCrmService::link('survey', array('t' => $token)); }

    /** Invite and send the link in one go; returns the delivery result so a journey can log it. */
    public static function inviteAndSend($idSurvey, $idCustomer, $idBooking = null, $channel = 'email', $template = 'crm_survey_invite', array $opt = array())
    {
        $r = self::invite($idSurvey, $idCustomer, $idBooking, $channel);
        $s = self::get($idSurvey);
        $vars = PulseCrmService::mergeVars($idCustomer, array('id_htl_booking' => $idBooking, 'survey_url' => self::url($r['token'])));
        $vars['subject'] = $s['name'].' — '.Configuration::get('PS_SHOP_NAME');
        $vars['text'] = PulseCrmService::render($s['intro'], $vars)."\n\n".self::url($r['token']);
        if ($channel === 'email') { $vars['html'] = PulseCrmCampaign::htmlBody($vars['text']); }
        $res = PulseCrmComms::deliver($idCustomer, $channel, $template, $vars, array_merge(array('kind' => 'survey', 'reference' => 'survey:'.$idSurvey), $opt));
        $res['token'] = $r['token'];
        return $res;
    }

    /* ---------- responses ---------- */

    public static function byToken($token)
    {
        $r = Db::getInstance()->getRow('SELECT r.*, s.name survey_name, s.intro, s.thanks, s.code survey_code, s.low_score_threshold FROM `'._DB_PREFIX_.'pulse_crm_survey_response` r
            INNER JOIN `'._DB_PREFIX_.'pulse_crm_survey` s ON s.id_pulse_crm_survey=r.id_pulse_crm_survey WHERE r.token="'.pSQL($token).'"');
        if ($r) { $r['questions'] = self::questions((int) $r['id_pulse_crm_survey']); }
        return $r;
    }

    /**
     * Score and store a submission. $answers is code => value (an array for multi-choice).
     * NPS drives the band; the 1-5 questions average into a GSS out of 100; the weakest department is
     * recorded so the recovery case lands on the right desk.
     */
    public static function submit($token, array $answers, $ip = null)
    {
        $r = self::byToken($token);
        if (!$r) { throw new PrestaShopException('That survey link is not valid'); }
        if ($r['status'] === 'completed') { throw new PrestaShopException('This survey has already been submitted — thank you'); }
        if ($r['expires_on'] && $r['expires_on'] < date('Y-m-d')) { Db::getInstance()->update('pulse_crm_survey_response', array('status' => 'expired'), 'id_pulse_crm_survey_response='.(int) $r['id_pulse_crm_survey_response']); throw new PrestaShopException('That survey link has expired'); }
        $db = Db::getInstance(); $now = date('Y-m-d H:i:s'); $idResp = (int) $r['id_pulse_crm_survey_response'];
        $db->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_survey_answer` WHERE id_pulse_crm_survey_response='.$idResp);
        $nps = null; $scales = array(); $deptScores = array(); $comments = array(); $missing = array();
        foreach ($r['questions'] as $q) {
            $v = isset($answers[$q['code']]) ? $answers[$q['code']] : null;
            if (($v === null || $v === '' || $v === array()) && (int) $q['required']) { $missing[] = $q['label']; continue; }
            if ($v === null || $v === '' || $v === array()) { continue; }
            $num = null; $text = null;
            if ($q['type'] === 'nps') { $num = max(0, min(10, (int) $v)); $nps = $num; }
            elseif ($q['type'] === 'scale5') { $num = max(1, min(5, (int) $v)); $scales[] = $num; if ($q['department']) { $deptScores[$q['department']][] = $num; } }
            elseif ($q['type'] === 'bool') { $num = $v ? 1 : 0; }
            elseif ($q['type'] === 'multi') { $text = implode(', ', array_map('strval', (array) $v)); }
            else { $text = (string) $v; if ($q['type'] === 'text') { $comments[] = $text; } }
            $db->insert('pulse_crm_survey_answer', array('id_pulse_crm_survey_response' => $idResp, 'id_pulse_crm_survey_question' => (int) $q['id_pulse_crm_survey_question'],
                'code' => pSQL($q['code']), 'department' => pSQL($q['department']), 'value_num' => $num === null ? null : (float) $num, 'value_text' => pSQL($text, true), 'date_add' => $now));
        }
        if ($missing) { throw new PrestaShopException('Please answer: '.implode(', ', $missing)); }
        $gss = $scales ? round(array_sum($scales) / count($scales) / 5 * 100, 1) : null;
        $band = $nps === null ? 'unknown' : ($nps >= 9 ? 'promoter' : ($nps >= 7 ? 'passive' : 'detractor'));
        $low = null; $lowAvg = 6;
        foreach ($deptScores as $dept => $vals) { $avg = array_sum($vals) / count($vals); if ($avg < $lowAvg) { $lowAvg = $avg; $low = $dept; } }
        $comment = implode("\n", $comments);
        $sentiment = self::sentiment($comment, $nps, $gss);
        $db->update('pulse_crm_survey_response', array('status' => 'completed', 'nps' => $nps === null ? null : (int) $nps, 'nps_band' => pSQL($band),
            'gss' => $gss === null ? null : (float) $gss, 'department_low' => pSQL($low), 'sentiment' => pSQL($sentiment), 'comment' => pSQL($comment, true),
            'ip' => pSQL($ip ? substr($ip, 0, 45) : ''), 'completed_at' => $now, 'business_date' => PulseCrmService::bd()), 'id_pulse_crm_survey_response='.$idResp);
        if ($r['id_customer']) { self::stampProfile((int) $r['id_customer'], $nps, $band, $gss); }
        $resp = $db->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_survey_response` WHERE id_pulse_crm_survey_response='.$idResp);
        $threshold = (int) $r['low_score_threshold'];
        if (($nps !== null && $nps <= $threshold) || ($gss !== null && $gss < 60)) { self::escalate($resp, $r, $lowAvg < 6 ? $low : null); }
        PulseCoreService::event('actionPulseCrmSurveyCompleted', array('id_response' => $idResp, 'nps' => $nps, 'gss' => $gss, 'id_customer' => (int) $r['id_customer'], 'band' => $band));
        return $resp;
    }

    /** Keep the CRM profile's headline NPS current so segments can target detractors without a join. */
    protected static function stampProfile($idCustomer, $nps, $band, $gss)
    {
        PulseCrmProfile::touch($idCustomer);
        Db::getInstance()->update('pulse_crm_profile_ext', array('nps_last' => $nps === null ? null : (int) $nps, 'nps_band' => pSQL($band),
            'nps_date' => date('Y-m-d'), 'gss_avg' => $gss === null ? 0 : (float) $gss, 'date_upd' => date('Y-m-d H:i:s')), 'id_customer='.(int) $idCustomer);
        if ($band === 'detractor') { PulseCrmProfile::tag($idCustomer, 'detractor', 'survey'); PulseCrmProfile::untag($idCustomer, 'promoter'); }
        if ($band === 'promoter') { PulseCrmProfile::tag($idCustomer, 'promoter', 'survey'); PulseCrmProfile::untag($idCustomer, 'detractor'); }
    }

    /** A poor score becomes a recovery case, and — while the guest is still in the building — a ticket too. */
    protected static function escalate(array $resp, array $survey, $department)
    {
        $inHouse = false;
        if ($resp['id_htl_booking'] && PulseCrmService::tableExists('htl_booking_detail') && class_exists('HotelBookingDetail')) {
            $inHouse = (int) Db::getInstance()->getValue('SELECT id_status FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id='.(int) $resp['id_htl_booking']) === (int) HotelBookingDetail::STATUS_CHECKED_IN;
        }
        $dept = $department ? $department : 'frontdesk';
        $title = 'Low score on '.$survey['survey_name'].($resp['nps'] !== null ? ' (NPS '.(int) $resp['nps'].')' : '');
        $idTicket = null;
        if ($inHouse && class_exists('PulseTicket')) {
            $idTicket = PulseTicket::create(array('category' => 'complaint', 'department' => in_array($dept, array('frontdesk', 'housekeeping', 'engineering', 'fnb', 'security', 'management')) ? $dept : 'frontdesk',
                'priority' => 'high', 'title' => $title, 'description' => $resp['comment'], 'id_room' => $resp['id_room'], 'id_htl_booking' => $resp['id_htl_booking'],
                'id_customer' => $resp['id_customer'], 'source' => 'survey'));
        }
        return PulseCrmCase::open(array('source' => 'survey', 'severity' => ($resp['nps'] !== null && $resp['nps'] <= 3) ? 'high' : 'medium', 'department' => $dept,
            'id_customer' => $resp['id_customer'], 'id_htl_booking' => $resp['id_htl_booking'], 'id_room' => $resp['id_room'],
            'id_pulse_crm_survey_response' => $resp['id_pulse_crm_survey_response'], 'id_pulse_ticket' => $idTicket,
            'title' => $title, 'description' => $resp['comment'] ? $resp['comment'] : 'No comment left — call the guest.'));
    }

    /**
     * Sentiment without a cloud service: a Nigerian-English keyword lexicon over the free text, falling
     * back to the numeric score. Crude on purpose — it must work at 2 a.m. with the link down.
     */
    public static function sentiment($text, $nps = null, $gss = null)
    {
        $t = Tools::strtolower((string) $text);
        $bad = array('dirty', 'rude', 'slow', 'no water', 'no light', 'power', 'noisy', 'noise', 'smell', 'cold food', 'terrible', 'awful', 'poor', 'worst', 'disappoint', 'never again', 'wahala', 'nonsense', 'broken', 'leaking', 'mosquito', 'ac not', 'generator', 'wifi not', 'delay');
        $good = array('excellent', 'wonderful', 'great', 'clean', 'friendly', 'helpful', 'lovely', 'comfortable', 'best', 'perfect', 'amazing', 'well done', 'kudos', 'sharp', 'prompt', 'delicious');
        $score = 0;
        foreach ($bad as $w) { if (strpos($t, $w) !== false) { $score--; } }
        foreach ($good as $w) { if (strpos($t, $w) !== false) { $score++; } }
        if ($score === 0) {
            if ($nps !== null) { return $nps >= 9 ? 'positive' : ($nps <= 6 ? 'negative' : 'neutral'); }
            if ($gss !== null) { return $gss >= 80 ? 'positive' : ($gss < 60 ? 'negative' : 'neutral'); }
            return $t === '' ? 'unknown' : 'neutral';
        }
        return $score > 0 ? 'positive' : 'negative';
    }

    /* ---------- reporting ---------- */

    public static function responses($from, $to, $idSurvey = 0, $band = null, $limit = 300)
    {
        return Db::getInstance()->executeS('SELECT r.*, s.name survey_name, CONCAT(c.firstname," ",c.lastname) guest, ro.room_num
            FROM `'._DB_PREFIX_.'pulse_crm_survey_response` r INNER JOIN `'._DB_PREFIX_.'pulse_crm_survey` s ON s.id_pulse_crm_survey=r.id_pulse_crm_survey
            LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=r.id_customer LEFT JOIN `'._DB_PREFIX_.'htl_room_information` ro ON ro.id=r.id_room
            WHERE r.status="completed" AND r.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"'
            .($idSurvey ? ' AND r.id_pulse_crm_survey='.(int) $idSurvey : '').($band ? ' AND r.nps_band="'.pSQL($band).'"' : '')
            .' ORDER BY r.completed_at DESC LIMIT '.(int) $limit);
    }

    /** Mean score by department across the window — the table that tells you where to spend money. */
    public static function departmentScores($from, $to)
    {
        return Db::getInstance()->executeS('SELECT a.department, COUNT(*) answers, ROUND(AVG(a.value_num),2) avg_score, ROUND(AVG(a.value_num)/5*100,1) pct,
                SUM(a.value_num<=2) poor FROM `'._DB_PREFIX_.'pulse_crm_survey_answer` a
            INNER JOIN `'._DB_PREFIX_.'pulse_crm_survey_response` r ON r.id_pulse_crm_survey_response=a.id_pulse_crm_survey_response
            WHERE a.value_num IS NOT NULL AND a.department<>"" AND r.status="completed" AND r.business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"
            GROUP BY a.department ORDER BY avg_score');
    }

    /** Response rate: how many invites came back completed. */
    public static function responseRate($from, $to)
    {
        $r = Db::getInstance()->getRow('SELECT COUNT(*) invited, SUM(status="completed") completed FROM `'._DB_PREFIX_.'pulse_crm_survey_response` WHERE business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'"');
        return array('invited' => (int) $r['invited'], 'completed' => (int) $r['completed'], 'pct' => (int) $r['invited'] ? round($r['completed'] / $r['invited'] * 100, 1) : 0);
    }
}
