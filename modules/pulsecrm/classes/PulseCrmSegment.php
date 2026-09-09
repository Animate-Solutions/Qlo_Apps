<?php
/**
 * Saved, re-evaluable segments. A rule set is JSON — {match:all|any, rules:[{field,op,value}]} — which
 * compiles to one SELECT over the guest 360 and is materialised into pulse_crm_segment_member so a
 * campaign send never has to evaluate rules row by row.
 */
class PulseCrmSegment
{
    /** field => SQL expression. Anything not in here is refused, so a rule set can never inject SQL. */
    public static function fields()
    {
        $chan = PulseCrmService::tableExists('pulse_ch_reservation')
            ? '(SELECT GROUP_CONCAT(DISTINCT chc.code) FROM `'._DB_PREFIX_.'pulse_ch_reservation` chr INNER JOIN `'._DB_PREFIX_.'pulse_ch_channel` chc ON chc.id_pulse_ch_channel=chr.id_pulse_ch_channel WHERE chr.id_customer=c.id_customer)'
            : 'px.source_of_business';
        // Without Front Desk there is no guest 360 to read; those fields resolve to a constant so a rule
        // set still compiles and simply matches nobody, instead of blowing up with a missing table.
        $gp = PulseCrmService::gp();
        $rooms = PulseCrmService::tableExists('htl_booking_detail')
            ? 'COALESCE((SELECT GROUP_CONCAT(DISTINCT b.room_type_name) FROM `'._DB_PREFIX_.'htl_booking_detail` b WHERE b.id_customer=c.id_customer AND b.is_cancelled=0),"")' : '""';
        return array(
            'stays' => array('sql' => $gp ? 'COALESCE(gp.stays,0)' : '0', 'type' => 'int', 'label' => 'Completed stays'),
            'nights' => array('sql' => $gp ? 'COALESCE(gp.nights,0)' : '0', 'type' => 'int', 'label' => 'Room nights'),
            'lifetime_revenue' => array('sql' => $gp ? 'COALESCE(gp.lifetime_revenue,0)' : '0', 'type' => 'float', 'label' => 'Lifetime revenue'),
            'last_stay_days' => array('sql' => $gp ? 'DATEDIFF(CURDATE(), gp.last_stay)' : 'NULL', 'type' => 'int', 'label' => 'Days since last stay'),
            'vip_level' => array('sql' => $gp ? 'COALESCE(gp.vip_level,0)' : '0', 'type' => 'int', 'label' => 'VIP level'),
            'blacklisted' => array('sql' => $gp ? 'COALESCE(gp.blacklisted,0)' : '0', 'type' => 'int', 'label' => 'Blacklisted'),
            'nationality' => array('sql' => $gp ? 'COALESCE(gp.nationality,"")' : '""', 'type' => 'string', 'label' => 'Nationality'),
            'has_company' => array('sql' => $gp ? 'IF(gp.id_pulse_company IS NULL,0,1)' : '0', 'type' => 'int', 'label' => 'Attached to a company'),
            'company' => array('sql' => ($gp && PulseCrmService::co()) ? 'COALESCE(comp.name,"")' : '""', 'type' => 'string', 'label' => 'Company name'),
            'source_of_business' => array('sql' => 'COALESCE(px.source_of_business,"")', 'type' => 'string', 'label' => 'Source of business'),
            'market_segment' => array('sql' => 'COALESCE(px.market_segment,"")', 'type' => 'string', 'label' => 'Market segment'),
            'channel' => array('sql' => 'COALESCE('.$chan.',"")', 'type' => 'string', 'label' => 'Booking channel'),
            'room_types' => array('sql' => $rooms, 'type' => 'string', 'label' => 'Room types booked'),
            'nps' => array('sql' => 'px.nps_last', 'type' => 'int', 'label' => 'Last NPS score'),
            'nps_band' => array('sql' => 'COALESCE(px.nps_band,"unknown")', 'type' => 'string', 'label' => 'NPS band'),
            'gss_avg' => array('sql' => 'COALESCE(px.gss_avg,0)', 'type' => 'float', 'label' => 'Average satisfaction'),
            'tier' => array('sql' => 'COALESCE(t.code,"")', 'type' => 'string', 'label' => 'Loyalty tier'),
            'points' => array('sql' => 'COALESCE(m.points_balance,0)', 'type' => 'int', 'label' => 'Points balance'),
            'member' => array('sql' => 'IF(m.id_pulse_crm_member IS NULL,0,1)', 'type' => 'int', 'label' => 'Loyalty member'),
            'opt_in_email' => array('sql' => 'IF(ce.state="opt_in",1,0)', 'type' => 'int', 'label' => 'Opted in to email'),
            'opt_in_sms' => array('sql' => 'IF(cs.state="opt_in",1,0)', 'type' => 'int', 'label' => 'Opted in to SMS'),
            'opt_out_email' => array('sql' => 'IF(ce.state="opt_out",1,0)', 'type' => 'int', 'label' => 'Opted out of email'),
            'forgotten' => array('sql' => 'COALESCE(px.forgotten,0)', 'type' => 'int', 'label' => 'Erasure requested'),
            'birthday_in_days' => array('sql' => '(SELECT MIN(MOD(DAYOFYEAR(o.occasion_date)-DAYOFYEAR(CURDATE())+366,366)) FROM `'._DB_PREFIX_.'pulse_crm_occasion` o WHERE o.id_customer=c.id_customer AND o.type="birthday" AND o.active=1)', 'type' => 'int', 'label' => 'Days to birthday'),
            'anniversary_in_days' => array('sql' => '(SELECT MIN(MOD(DAYOFYEAR(o.occasion_date)-DAYOFYEAR(CURDATE())+366,366)) FROM `'._DB_PREFIX_.'pulse_crm_occasion` o WHERE o.id_customer=c.id_customer AND o.type="anniversary" AND o.active=1)', 'type' => 'int', 'label' => 'Days to anniversary'),
            'tags' => array('sql' => 'COALESCE((SELECT GROUP_CONCAT(tg.code) FROM `'._DB_PREFIX_.'pulse_crm_customer_tag` ct INNER JOIN `'._DB_PREFIX_.'pulse_crm_tag` tg ON tg.id_pulse_crm_tag=ct.id_pulse_crm_tag WHERE ct.id_customer=c.id_customer),"")', 'type' => 'string', 'label' => 'Tags'),
            'open_cases' => array('sql' => '(SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_case` cs2 WHERE cs2.id_customer=c.id_customer AND cs2.status<>"closed")', 'type' => 'int', 'label' => 'Open recovery cases'),
        );
    }

    public static function ops() { return array('eq' => '=', 'ne' => '<>', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<=', 'in' => 'IN', 'not_in' => 'NOT IN', 'contains' => 'LIKE', 'not_contains' => 'NOT LIKE', 'is_set' => 'IS NOT NULL', 'is_null' => 'IS NULL'); }

    protected static function from()
    {
        $gp = PulseCrmService::gp();
        return ' FROM `'._DB_PREFIX_.'customer` c
            '.($gp ? 'LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=c.id_customer' : '').'
            LEFT JOIN `'._DB_PREFIX_.'pulse_crm_profile_ext` px ON px.id_customer=c.id_customer
            '.(($gp && PulseCrmService::co()) ? 'LEFT JOIN `'._DB_PREFIX_.'pulse_company` comp ON comp.id_pulse_company=gp.id_pulse_company' : '').'
            LEFT JOIN `'._DB_PREFIX_.'pulse_crm_member` m ON m.id_customer=c.id_customer AND m.status="active"
            LEFT JOIN `'._DB_PREFIX_.'pulse_crm_tier` t ON t.id_pulse_crm_tier=m.id_pulse_crm_tier
            LEFT JOIN `'._DB_PREFIX_.'pulse_crm_consent` ce ON ce.id_customer=c.id_customer AND ce.channel="email"
            LEFT JOIN `'._DB_PREFIX_.'pulse_crm_consent` cs ON cs.id_customer=c.id_customer AND cs.channel="sms"
            WHERE c.deleted=0 AND c.email<>"" ';
    }

    /** Compile one rule to a SQL predicate. Values are cast or pSQL'd by the field's declared type. */
    protected static function predicate(array $rule, array $fields)
    {
        if (empty($rule['field']) || !isset($fields[$rule['field']])) { throw new PrestaShopException('Unknown segment field '.(isset($rule['field']) ? $rule['field'] : '?')); }
        $f = $fields[$rule['field']]; $op = isset($rule['op']) ? $rule['op'] : 'eq'; $ops = self::ops();
        if (!isset($ops[$op])) { throw new PrestaShopException('Unknown operator '.$op); }
        $v = isset($rule['value']) ? $rule['value'] : '';
        if ($op === 'is_set') { return '('.$f['sql'].' IS NOT NULL AND '.$f['sql'].'<>"")'; }
        if ($op === 'is_null') { return '('.$f['sql'].' IS NULL OR '.$f['sql'].'="")'; }
        if ($op === 'in' || $op === 'not_in') {
            $vals = is_array($v) ? $v : explode(',', (string) $v); $out = array();
            foreach ($vals as $x) { $x = trim($x); $out[] = $f['type'] === 'string' ? '"'.pSQL($x).'"' : (float) $x; }
            if (!$out) { return $op === 'in' ? '0' : '1'; }
            return $f['sql'].' '.$ops[$op].' ('.implode(',', $out).')';
        }
        if ($op === 'contains' || $op === 'not_contains') { return $f['sql'].' '.$ops[$op].' "%'.pSQL((string) $v).'%"'; }
        $lit = $f['type'] === 'string' ? '"'.pSQL((string) $v).'"' : ($f['type'] === 'int' ? (int) $v : (float) $v);
        return $f['sql'].' '.$ops[$op].' '.$lit;
    }

    /** Turn a rule set into the WHERE fragment. An empty rule set matches nobody, never everybody. */
    public static function compile($rules)
    {
        $r = is_array($rules) ? $rules : json_decode((string) $rules, true);
        if (!is_array($r) || empty($r['rules']) || !is_array($r['rules'])) { return '0'; }
        $fields = self::fields(); $parts = array();
        foreach ($r['rules'] as $rule) { if (is_array($rule)) { $parts[] = self::predicate($rule, $fields); } }
        if (!$parts) { return '0'; }
        $glue = (isset($r['match']) && $r['match'] === 'any') ? ' OR ' : ' AND ';
        return '('.implode($glue, $parts).')';
    }

    public static function all($activeOnly = false) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_segment`'.($activeOnly ? ' WHERE active=1' : '').' ORDER BY is_system DESC, name'); }
    public static function get($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_segment` WHERE id_pulse_crm_segment='.(int) $id); }
    public static function byCode($code) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_segment` WHERE code="'.pSQL($code).'"'); }

    public static function save(array $d, $id = 0)
    {
        $rules = is_array($d['rules_json']) ? json_encode($d['rules_json']) : $d['rules_json'];
        self::compile($rules); // validate before we store anything
        $row = array('name' => pSQL($d['name']), 'description' => pSQL(isset($d['description']) ? $d['description'] : ''), 'rules_json' => pSQL($rules, true),
            'active' => isset($d['active']) ? (int) $d['active'] : 1, 'date_upd' => date('Y-m-d H:i:s'));
        if ($id) { Db::getInstance()->update('pulse_crm_segment', $row, 'id_pulse_crm_segment='.(int) $id); return (int) $id; }
        $row['code'] = pSQL(isset($d['code']) && $d['code'] ? Tools::str2url($d['code']) : Tools::substr(Tools::str2url($d['name']), 0, 30));
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_crm_segment', $row);
        return (int) Db::getInstance()->Insert_ID();
    }

    /** Count without materialising — the preview button on the segment editor. */
    public static function preview($rules, $limit = 25)
    {
        $where = self::compile($rules);
        $count = (int) Db::getInstance()->getValue('SELECT COUNT(DISTINCT c.id_customer)'.self::from().' AND '.$where);
        $gp = PulseCrmService::gp();
        $cols = $gp ? 'gp.stays, gp.lifetime_revenue, gp.last_stay' : '0 stays, 0 lifetime_revenue, NULL last_stay';
        $rows = Db::getInstance()->executeS('SELECT DISTINCT c.id_customer, c.firstname, c.lastname, c.email, '.$cols.self::from().' AND '.$where.($gp ? ' ORDER BY gp.lifetime_revenue DESC' : '').' LIMIT '.(int) $limit);
        return array('count' => $count, 'rows' => $rows);
    }

    /** Rebuild the materialised membership of one segment. Returns the member count. */
    public static function refresh($id)
    {
        $s = self::get($id); if (!$s) { return 0; }
        $t0 = microtime(true); $db = Db::getInstance();
        try { $where = self::compile($s['rules_json']); }
        catch (Exception $e) { $db->update('pulse_crm_segment', array('last_error' => pSQL($e->getMessage()), 'last_refresh' => date('Y-m-d H:i:s')), 'id_pulse_crm_segment='.(int) $id); return 0; }
        $db->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_segment_member` WHERE id_pulse_crm_segment='.(int) $id);
        $db->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_crm_segment_member` (id_pulse_crm_segment, id_customer, date_add) SELECT '.(int) $id.', c.id_customer, NOW()'.self::from().' AND '.$where.' GROUP BY c.id_customer');
        $n = (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_segment_member` WHERE id_pulse_crm_segment='.(int) $id);
        $db->update('pulse_crm_segment', array('member_count' => $n, 'last_refresh' => date('Y-m-d H:i:s'), 'refresh_ms' => (int) round((microtime(true) - $t0) * 1000), 'last_error' => ''), 'id_pulse_crm_segment='.(int) $id);
        return $n;
    }

    /** Refresh every active segment; used by cron. Chunked by segment so a shared host can stop between them. */
    public static function refreshAll($limit = 50)
    {
        $out = array('segments' => 0, 'members' => 0);
        foreach (Db::getInstance()->executeS('SELECT id_pulse_crm_segment FROM `'._DB_PREFIX_.'pulse_crm_segment` WHERE active=1 ORDER BY COALESCE(last_refresh,"1970-01-01") LIMIT '.(int) $limit) as $s) {
            $out['members'] += self::refresh((int) $s['id_pulse_crm_segment']); $out['segments']++;
        }
        return $out;
    }

    public static function members($id, $limit = 200, $offset = 0)
    {
        $gp = PulseCrmService::gp();
        return Db::getInstance()->executeS('SELECT c.id_customer, c.firstname, c.lastname, c.email, '.($gp ? 'gp.stays, gp.lifetime_revenue, gp.last_stay' : '0 stays, 0 lifetime_revenue, NULL last_stay').'
            FROM `'._DB_PREFIX_.'pulse_crm_segment_member` sm INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=sm.id_customer
            '.($gp ? 'LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=c.id_customer' : '').'
            WHERE sm.id_pulse_crm_segment='.(int) $id.($gp ? ' ORDER BY gp.lifetime_revenue DESC' : '').' LIMIT '.(int) $offset.','.(int) $limit);
    }

    public static function remove($id)
    {
        Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_segment_member` WHERE id_pulse_crm_segment='.(int) $id);
        return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_segment` WHERE id_pulse_crm_segment='.(int) $id.' AND is_system=0');
    }
}
