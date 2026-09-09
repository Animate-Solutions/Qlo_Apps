<?php
/**
 * CRM layer on top of the Front Desk guest 360 (PulseGuestProfile): preferences taxonomy, special
 * occasions, relationships, per-channel consent with a timestamp and a source, tags and source of
 * business. It never duplicates PulseGuestProfile — it reads it and hangs the marketing side off it.
 */
class PulseCrmProfile
{
    /** Members of pulse_crm_preference.category — anything else is refused rather than written blank. */
    const CATEGORIES = array('room_position', 'floor', 'pillow', 'bed', 'allergy', 'newspaper', 'transport', 'dietary', 'amenity', 'housekeeping', 'other');

    /** The whole CRM tab for one guest: the FD profile plus everything this module adds. */
    public static function get($idCustomer)
    {
        $id = (int) $idCustomer; $db = Db::getInstance();
        $base = class_exists('PulseGuestProfile') ? PulseGuestProfile::get($id) : $db->getRow('SELECT c.firstname, c.lastname, c.email FROM `'._DB_PREFIX_.'customer` c WHERE c.id_customer='.$id);
        self::touch($id);
        $base['id_customer'] = $id;
        $base['ext'] = $db->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_profile_ext` WHERE id_customer='.$id);
        $base['crm_preferences'] = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_preference` WHERE id_customer='.$id.' AND active=1 ORDER BY is_service_note DESC, category, value');
        $base['occasions'] = $db->executeS('SELECT *, MOD(DAYOFYEAR(occasion_date)-DAYOFYEAR(CURDATE())+366,366) days_away FROM `'._DB_PREFIX_.'pulse_crm_occasion` WHERE id_customer='.$id.' AND active=1 ORDER BY days_away');
        $base['relationships'] = $db->executeS('SELECT r.*, CONCAT(c.firstname," ",c.lastname) related_name, c.email related_email FROM `'._DB_PREFIX_.'pulse_crm_relationship` r LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=r.id_related_customer WHERE r.id_customer='.$id);
        $base['consent'] = self::consent($id);
        $base['tags'] = $db->executeS('SELECT t.* FROM `'._DB_PREFIX_.'pulse_crm_customer_tag` ct INNER JOIN `'._DB_PREFIX_.'pulse_crm_tag` t ON t.id_pulse_crm_tag=ct.id_pulse_crm_tag WHERE ct.id_customer='.$id);
        $base['segments'] = $db->executeS('SELECT s.code, s.name FROM `'._DB_PREFIX_.'pulse_crm_segment_member` sm INNER JOIN `'._DB_PREFIX_.'pulse_crm_segment` s ON s.id_pulse_crm_segment=sm.id_pulse_crm_segment WHERE sm.id_customer='.$id);
        $base['member'] = PulseCrmLoyalty::memberOf($id);
        $base['points'] = $base['member'] ? PulseCrmLoyalty::statement((int) $base['member']['id_pulse_crm_member'], 20) : array();
        $base['responses'] = $db->executeS('SELECT r.*, s.name survey_name FROM `'._DB_PREFIX_.'pulse_crm_survey_response` r INNER JOIN `'._DB_PREFIX_.'pulse_crm_survey` s ON s.id_pulse_crm_survey=r.id_pulse_crm_survey WHERE r.id_customer='.$id.' AND r.status="completed" ORDER BY r.completed_at DESC LIMIT 10');
        $base['cases'] = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_case` WHERE id_customer='.$id.' ORDER BY opened_at DESC LIMIT 10');
        $base['sends'] = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_send_log` WHERE id_customer='.$id.' ORDER BY date_add DESC LIMIT 20');
        $base['journeys'] = $db->executeS('SELECT jr.*, j.name journey FROM `'._DB_PREFIX_.'pulse_crm_journey_run` jr INNER JOIN `'._DB_PREFIX_.'pulse_crm_journey` j ON j.id_pulse_crm_journey=jr.id_pulse_crm_journey WHERE jr.id_customer='.$id.' ORDER BY jr.date_upd DESC LIMIT 10');
        return $base;
    }

    public static function touch($idCustomer)
    {
        if (class_exists('PulseGuestProfile')) { PulseGuestProfile::touch((int) $idCustomer); }
        Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_crm_profile_ext` (id_customer, date_upd) VALUES ('.(int) $idCustomer.', NOW())');
    }

    /** Marketing extras that do not belong on the FD profile row. */
    public static function saveExt($idCustomer, array $d)
    {
        self::touch($idCustomer); $u = array('date_upd' => date('Y-m-d H:i:s'));
        foreach (array('source_of_business', 'market_segment', 'guest_type', 'preferred_language', 'preferred_channel') as $k) { if (isset($d[$k])) { $u[$k] = pSQL($d[$k]); } }
        return Db::getInstance()->update('pulse_crm_profile_ext', $u, 'id_customer='.(int) $idCustomer);
    }

    /* ---------- preferences ---------- */

    public static function options($category = null)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_preference_option` WHERE active=1'.($category ? ' AND category="'.pSQL($category).'"' : '').' ORDER BY category, sort, label');
    }

    /**
     * Add or update one preference. A managed code is looked up in the option list; a free-form value is
     * kept verbatim. Allergies and dietary notes are flagged as service notes so they reach the arrivals
     * list and the kitchen rather than sitting in a marketing field.
     */
    public static function savePreference($idCustomer, $category, $value, $code = null, $source = 'desk')
    {
        // category is an ENUM column and the value reaches here from the portal API — an unknown member
        // would be refused by MySQL in strict mode and silently blanked otherwise
        if (!in_array($category, self::CATEGORIES)) { throw new PrestaShopException('Unknown preference category'); }
        $id = (int) $idCustomer; $value = trim($value);
        if ($code && !$value) { $o = Db::getInstance()->getRow('SELECT label FROM `'._DB_PREFIX_.'pulse_crm_preference_option` WHERE category="'.pSQL($category).'" AND code="'.pSQL($code).'"'); $value = $o ? $o['label'] : $code; }
        if (!$value) { throw new PrestaShopException('A preference needs a value'); }
        $note = in_array($category, array('allergy', 'dietary')) ? 1 : 0;
        $exists = (int) Db::getInstance()->getValue('SELECT id_pulse_crm_preference FROM `'._DB_PREFIX_.'pulse_crm_preference` WHERE id_customer='.$id.' AND category="'.pSQL($category).'" AND value="'.pSQL($value).'"');
        if ($exists) { Db::getInstance()->update('pulse_crm_preference', array('active' => 1, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_preference='.$exists); return $exists; }
        self::touch($id);
        Db::getInstance()->insert('pulse_crm_preference', array('id_customer' => $id, 'category' => pSQL($category), 'code' => pSQL($code), 'value' => pSQL($value),
            'is_managed' => $code ? 1 : 0, 'is_service_note' => $note, 'source' => pSQL($source), 'id_employee' => PulseCrmService::emp(),
            'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
        $newId = (int) Db::getInstance()->Insert_ID();
        self::mirrorToFd($id);
        PulseCoreService::audit('pulsecrm', 'preference_add', array('category' => $category, 'value' => $value), 'customer', $id);
        return $newId;
    }

    public static function removePreference($idPreference)
    {
        $p = Db::getInstance()->getRow('SELECT id_customer FROM `'._DB_PREFIX_.'pulse_crm_preference` WHERE id_pulse_crm_preference='.(int) $idPreference);
        Db::getInstance()->update('pulse_crm_preference', array('active' => 0, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_preference='.(int) $idPreference);
        if ($p) { self::mirrorToFd((int) $p['id_customer']); }
        return true;
    }

    /** Keep the Front Desk profile's preferences JSON in step, so the check-in screen shows the same thing. */
    protected static function mirrorToFd($idCustomer)
    {
        if (!class_exists('PulseGuestProfile')) { return; }
        $out = array();
        foreach (Db::getInstance()->executeS('SELECT category, value FROM `'._DB_PREFIX_.'pulse_crm_preference` WHERE id_customer='.(int) $idCustomer.' AND active=1') as $r) { $out[$r['category']][] = $r['value']; }
        foreach ($out as $k => $v) { $out[$k] = implode(', ', $v); }
        PulseGuestProfile::save((int) $idCustomer, array('preferences' => $out));
    }

    /* ---------- occasions ---------- */

    public static function saveOccasion($idCustomer, $type, $date, $note = '', $remindDays = 7, $recurring = 1)
    {
        if (!Validate::isDate($date)) { throw new PrestaShopException('Occasion date is not a date'); }
        self::touch($idCustomer);
        $exists = (int) Db::getInstance()->getValue('SELECT id_pulse_crm_occasion FROM `'._DB_PREFIX_.'pulse_crm_occasion` WHERE id_customer='.(int) $idCustomer.' AND type="'.pSQL($type).'" AND occasion_date="'.pSQL($date).'"');
        $d = array('type' => pSQL($type), 'occasion_date' => pSQL($date), 'note' => pSQL($note), 'remind_days' => (int) $remindDays, 'recurring' => (int) $recurring, 'active' => 1, 'date_upd' => date('Y-m-d H:i:s'));
        if ($exists) { Db::getInstance()->update('pulse_crm_occasion', $d, 'id_pulse_crm_occasion='.$exists); return $exists; }
        $d['id_customer'] = (int) $idCustomer; $d['source'] = 'desk'; $d['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_crm_occasion', $d);
        return (int) Db::getInstance()->Insert_ID();
    }

    public static function removeOccasion($id) { return Db::getInstance()->update('pulse_crm_occasion', array('active' => 0, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_occasion='.(int) $id); }

    /** Occasions falling within $days of today for one guest (used to flag the arrivals list). */
    public static function occasionsNear($idCustomer, $days = 7)
    {
        return Db::getInstance()->executeS('SELECT * FROM (SELECT o.*, MOD(DAYOFYEAR(o.occasion_date)-DAYOFYEAR(CURDATE())+366,366) days_away FROM `'._DB_PREFIX_.'pulse_crm_occasion` o
            WHERE o.id_customer='.(int) $idCustomer.' AND o.active=1) x WHERE x.days_away<='.(int) $days.' ORDER BY x.days_away');
    }

    /** Every guest whose occasion is exactly remind_days away — the daily reminder feed. */
    public static function occasionsDue()
    {
        return Db::getInstance()->executeS('SELECT * FROM (SELECT o.*, CONCAT(c.firstname," ",c.lastname) guest, c.email, MOD(DAYOFYEAR(o.occasion_date)-DAYOFYEAR(CURDATE())+366,366) days_away
            FROM `'._DB_PREFIX_.'pulse_crm_occasion` o INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=o.id_customer
            WHERE o.active=1 AND c.deleted=0 AND (o.last_reminded IS NULL OR o.last_reminded<CURDATE())) x WHERE x.days_away<=x.remind_days ORDER BY x.days_away');
    }

    /* ---------- relationships ---------- */

    public static function relate($idCustomer, $idRelated, $type, $note = '', $reciprocal = true)
    {
        if ((int) $idCustomer === (int) $idRelated) { throw new PrestaShopException('A guest cannot be related to themselves'); }
        Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_crm_relationship` (id_customer, id_related_customer, type, note, date_add) VALUES ('.(int) $idCustomer.','.(int) $idRelated.',"'.pSQL($type).'","'.pSQL($note).'",NOW())');
        $mirror = array('travels_with' => 'travels_with', 'spouse' => 'spouse', 'partner' => 'partner', 'colleague' => 'colleague', 'same_company' => 'same_company', 'assistant_of' => 'reports_to', 'reports_to' => 'assistant_of');
        if ($reciprocal && isset($mirror[$type])) { Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_crm_relationship` (id_customer, id_related_customer, type, note, date_add) VALUES ('.(int) $idRelated.','.(int) $idCustomer.',"'.pSQL($mirror[$type]).'","'.pSQL($note).'",NOW())'); }
        return true;
    }
    public static function unrelate($id) { return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_relationship` WHERE id_pulse_crm_relationship='.(int) $id); }

    /* ---------- consent (NDPR) ---------- */

    public static function consent($idCustomer)
    {
        $out = array();
        foreach (array('email', 'sms', 'whatsapp', 'phone', 'post', 'profiling') as $ch) { $out[$ch] = array('channel' => $ch, 'state' => 'unknown', 'source' => '', 'date_consent' => null, 'unsub_reason' => null); }
        foreach (Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_consent` WHERE id_customer='.(int) $idCustomer) as $r) { $out[$r['channel']] = $r; }
        return $out;
    }

    /** Record a consent decision. Every change is stamped with when it happened and where it came from — this is the NDPR audit trail. */
    public static function setConsent($idCustomer, $channel, $state, $source = 'desk', $evidence = '', $reason = '')
    {
        if (!in_array($channel, array('email', 'sms', 'whatsapp', 'phone', 'post', 'profiling'))) { throw new PrestaShopException('Unknown consent channel'); }
        if (!in_array($state, array('opt_in', 'opt_out', 'unknown'))) { throw new PrestaShopException('Unknown consent state'); }
        self::touch($idCustomer);
        $ip = isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 45) : '';
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_crm_consent` (id_customer, channel, state, source, evidence, unsub_reason, ip, date_consent, date_upd)
            VALUES ('.(int) $idCustomer.',"'.pSQL($channel).'","'.pSQL($state).'","'.pSQL($source).'","'.pSQL($evidence).'","'.pSQL($reason).'","'.pSQL($ip).'",NOW(),NOW())
            ON DUPLICATE KEY UPDATE state=VALUES(state), source=VALUES(source), evidence=VALUES(evidence), unsub_reason=VALUES(unsub_reason), ip=VALUES(ip), date_consent=VALUES(date_consent), date_upd=NOW()');
        PulseCoreService::audit('pulsecrm', 'consent_'.$state, array('channel' => $channel, 'source' => $source, 'reason' => $reason), 'customer', (int) $idCustomer);
        return true;
    }

    /** May we send on this channel? Blacklisted guests and anyone not opted in are refused. */
    public static function mayContact($idCustomer, $channel)
    {
        $id = (int) $idCustomer;
        if (PulseCrmService::tableExists('pulse_guest_profile') && (int) Db::getInstance()->getValue('SELECT blacklisted FROM `'._DB_PREFIX_.'pulse_guest_profile` WHERE id_customer='.$id)) { return 'blacklisted'; }
        if ((int) Db::getInstance()->getValue('SELECT forgotten FROM `'._DB_PREFIX_.'pulse_crm_profile_ext` WHERE id_customer='.$id)) { return 'forgotten'; }
        $state = Db::getInstance()->getValue('SELECT state FROM `'._DB_PREFIX_.'pulse_crm_consent` WHERE id_customer='.$id.' AND channel="'.pSQL($channel).'"');
        if ($state === 'opt_out') { return 'opted_out'; }
        if ($state !== 'opt_in') { return PulseCrmService::cfg('IMPLIED_CONSENT', 0) ? true : 'no_consent'; }
        return true;
    }

    /* ---------- tags ---------- */

    public static function tag($idCustomer, $code, $source = 'journey')
    {
        $id = (int) Db::getInstance()->getValue('SELECT id_pulse_crm_tag FROM `'._DB_PREFIX_.'pulse_crm_tag` WHERE code="'.pSQL($code).'"');
        if (!$id) { Db::getInstance()->insert('pulse_crm_tag', array('code' => pSQL($code), 'name' => pSQL(ucfirst(str_replace('_', ' ', $code))), 'colour' => 'default')); $id = (int) Db::getInstance()->Insert_ID(); }
        return Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_crm_customer_tag` (id_customer, id_pulse_crm_tag, source, date_add) VALUES ('.(int) $idCustomer.','.$id.',"'.pSQL($source).'",NOW())');
    }
    public static function untag($idCustomer, $code)
    {
        return Db::getInstance()->execute('DELETE ct FROM `'._DB_PREFIX_.'pulse_crm_customer_tag` ct INNER JOIN `'._DB_PREFIX_.'pulse_crm_tag` t ON t.id_pulse_crm_tag=ct.id_pulse_crm_tag WHERE ct.id_customer='.(int) $idCustomer.' AND t.code="'.pSQL($code).'"');
    }

    /* ---------- search & right to be forgotten ---------- */

    public static function search($q, $limit = 50)
    {
        $q = pSQL(trim($q)); $gp = PulseCrmService::gp();
        return Db::getInstance()->executeS('SELECT c.id_customer, c.firstname, c.lastname, c.email, '
            .($gp ? 'gp.vip_level, gp.stays, gp.nights, gp.lifetime_revenue, gp.last_stay, gp.blacklisted' : '0 vip_level, 0 stays, 0 nights, 0 lifetime_revenue, NULL last_stay, 0 blacklisted').',
                px.nps_band, px.source_of_business, px.forgotten, m.member_no, t.name tier
            FROM `'._DB_PREFIX_.'customer` c
            '.($gp ? 'LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=c.id_customer' : '').'
            LEFT JOIN `'._DB_PREFIX_.'pulse_crm_profile_ext` px ON px.id_customer=c.id_customer
            LEFT JOIN `'._DB_PREFIX_.'pulse_crm_member` m ON m.id_customer=c.id_customer AND m.status="active"
            LEFT JOIN `'._DB_PREFIX_.'pulse_crm_tier` t ON t.id_pulse_crm_tier=m.id_pulse_crm_tier
            WHERE c.deleted=0'.($q ? ' AND (c.firstname LIKE "%'.$q.'%" OR c.lastname LIKE "%'.$q.'%" OR c.email LIKE "%'.$q.'%"'.($gp ? ' OR gp.phone LIKE "%'.$q.'%"' : '').' OR m.member_no LIKE "%'.$q.'%")' : '').'
            ORDER BY '.($gp ? 'gp.last_stay DESC, ' : '').'c.id_customer DESC LIMIT '.(int) $limit);
    }

    /**
     * Right to be forgotten (NDPR s.2.8): strip everything that exists to market to this guest and opt
     * every channel out with the reason on record. The accounting trail — folios, points transactions,
     * bookings, invoices — is deliberately untouched, because the hotel must still be able to prove what
     * it charged and what it owes.
     */
    public static function forget($idCustomer, $reason = 'Guest exercised the right to erasure')
    {
        $id = (int) $idCustomer; $db = Db::getInstance(); $now = date('Y-m-d H:i:s');
        self::touch($id);
        $db->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_preference` WHERE id_customer='.$id);
        $db->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_occasion` WHERE id_customer='.$id);
        $db->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_relationship` WHERE id_customer='.$id.' OR id_related_customer='.$id);
        $db->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_customer_tag` WHERE id_customer='.$id);
        $db->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_segment_member` WHERE id_customer='.$id);
        $db->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_journey_run` SET status="cancelled", last_error="forget request", date_upd="'.$now.'" WHERE id_customer='.$id.' AND status="active"');
        $db->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_campaign_recipient` SET to_addr="", status=IF(status="queued","skipped",status), skip_reason="forgotten" WHERE id_customer='.$id);
        $db->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_survey_response` SET comment="[removed at the guest\'s request]", ip=NULL WHERE id_customer='.$id);
        $db->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_review` SET author="[removed]" WHERE id_customer='.$id);
        foreach (array('email', 'sms', 'whatsapp', 'phone', 'post', 'profiling') as $ch) { self::setConsent($id, $ch, 'opt_out', 'forget_request', 'right to erasure', $reason); }
        $db->update('pulse_crm_profile_ext', array('forgotten' => 1, 'date_forgotten' => $now, 'source_of_business' => '', 'market_segment' => '', 'guest_type' => '', 'date_upd' => $now), 'id_customer='.$id);
        PulseCoreService::audit('pulsecrm', 'guest_forgotten', array('reason' => $reason), 'customer', $id);
        return true;
    }
}
