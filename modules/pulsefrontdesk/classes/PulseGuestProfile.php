<?php
class PulseGuestProfile
{
    public static function touch($idCustomer)
    {
        Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_guest_profile` (id_customer, date_upd) VALUES ('.(int) $idCustomer.', NOW())');
    }
    public static function recordStay($idCustomer, $nights, $revenue, $lastStay)
    {
        self::touch($idCustomer);
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_guest_profile` SET stays=stays+1, nights=nights+'.(int) $nights.', lifetime_revenue=lifetime_revenue+'.(float) $revenue.', last_stay="'.pSQL($lastStay).'", date_upd=NOW() WHERE id_customer='.(int) $idCustomer);
    }
    public static function get($idCustomer)
    {
        self::touch($idCustomer);
        $p = Db::getInstance()->getRow('SELECT gp.*, c.firstname, c.lastname, c.email, comp.name company_name FROM `'._DB_PREFIX_.'pulse_guest_profile` gp INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=gp.id_customer LEFT JOIN `'._DB_PREFIX_.'pulse_company` comp ON comp.id_pulse_company=gp.id_pulse_company WHERE gp.id_customer='.(int) $idCustomer);
        $p['identities'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_guest_identity` WHERE id_customer='.(int) $idCustomer.' ORDER BY date_add DESC');
        $p['history'] = Db::getInstance()->executeS('SELECT b.id, b.date_from, b.date_to, b.room_num, b.room_type_name, b.total_price_tax_incl, b.id_status FROM `'._DB_PREFIX_.'htl_booking_detail` b WHERE b.id_customer='.(int) $idCustomer.' ORDER BY b.date_from DESC LIMIT 20');
        $p['preferences'] = $p['preferences'] ? json_decode($p['preferences'], true) : array();
        return $p;
    }
    public static function save($idCustomer, array $data)
    {
        self::touch($idCustomer);
        $upd = array();
        foreach (array('vip_level', 'id_pulse_company', 'blacklisted') as $k) { if (isset($data[$k])) { $upd[$k] = (int) $data[$k]; } }
        foreach (array('blacklist_reason', 'nationality', 'phone', 'address', 'notes') as $k) { if (isset($data[$k])) { $upd[$k] = pSQL($data[$k]); } }
        if (isset($data['preferences'])) { $upd['preferences'] = pSQL(json_encode($data['preferences']), true); }
        $upd['date_upd'] = date('Y-m-d H:i:s');
        return Db::getInstance()->update('pulse_guest_profile', $upd, 'id_customer='.(int) $idCustomer);
    }

    /** Candidate duplicate profiles: same email domain-insensitive, same phone digits, same ID number, or same full name. */
    public static function findDuplicates($limit = 200)
    {
        $sql = 'SELECT c1.id_customer a, c2.id_customer b, CONCAT(c1.firstname," ",c1.lastname) name_a, CONCAT(c2.firstname," ",c2.lastname) name_b, c1.email email_a, c2.email email_b,
                CASE WHEN LOWER(c1.email)=LOWER(c2.email) THEN "email" WHEN i1.id_number IS NOT NULL AND i1.id_number=i2.id_number THEN "id_number" WHEN p1.phone IS NOT NULL AND REPLACE(REPLACE(p1.phone," ",""),"+","")=REPLACE(REPLACE(p2.phone," ",""),"+","") THEN "phone" ELSE "name" END reason
            FROM `'._DB_PREFIX_.'customer` c1 INNER JOIN `'._DB_PREFIX_.'customer` c2 ON c2.id_customer>c1.id_customer
            LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` p1 ON p1.id_customer=c1.id_customer LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` p2 ON p2.id_customer=c2.id_customer
            LEFT JOIN `'._DB_PREFIX_.'pulse_guest_identity` i1 ON i1.id_customer=c1.id_customer LEFT JOIN `'._DB_PREFIX_.'pulse_guest_identity` i2 ON i2.id_customer=c2.id_customer
            WHERE c1.deleted=0 AND c2.deleted=0 AND (LOWER(c1.email)=LOWER(c2.email) OR (i1.id_number IS NOT NULL AND i1.id_number=i2.id_number) OR (p1.phone IS NOT NULL AND p1.phone<>"" AND REPLACE(REPLACE(p1.phone," ",""),"+","")=REPLACE(REPLACE(p2.phone," ",""),"+","")) OR (LOWER(c1.firstname)=LOWER(c2.firstname) AND LOWER(c1.lastname)=LOWER(c2.lastname)))
            GROUP BY c1.id_customer, c2.id_customer LIMIT '.(int) $limit;
        return Db::getInstance()->executeS($sql);
    }

    /** Merge $idMerge into $idKeep: repoint bookings, orders, folios, identities, tickets, waitlist; sum stats; soft-delete the merged customer. */
    public static function merge($idKeep, $idMerge)
    {
        if ((int) $idKeep === (int) $idMerge) { return false; }
        $k = (int) $idKeep; $m = (int) $idMerge; $db = Db::getInstance();
        foreach (array('htl_booking_detail', 'orders', 'cart', 'address', 'pulse_folio', 'pulse_guest_identity', 'pulse_ticket', 'pulse_waitlist', 'pulse_registration_card', 'pulse_comms_log', 'pulse_trace') as $t) {
            $db->execute('UPDATE `'._DB_PREFIX_.$t.'` SET id_customer='.$k.' WHERE id_customer='.$m);
        }
        self::touch($k); self::touch($m);
        $db->execute('UPDATE `'._DB_PREFIX_.'pulse_guest_profile` a INNER JOIN `'._DB_PREFIX_.'pulse_guest_profile` b ON b.id_customer='.$m.' SET a.stays=a.stays+b.stays, a.nights=a.nights+b.nights, a.lifetime_revenue=a.lifetime_revenue+b.lifetime_revenue, a.last_stay=GREATEST(COALESCE(a.last_stay,"1970-01-01"),COALESCE(b.last_stay,"1970-01-01")), a.vip_level=GREATEST(a.vip_level,b.vip_level), a.blacklisted=GREATEST(a.blacklisted,b.blacklisted), a.phone=COALESCE(NULLIF(a.phone,""),b.phone), a.nationality=COALESCE(NULLIF(a.nationality,""),b.nationality), a.notes=CONCAT_WS("\n",a.notes,b.notes) WHERE a.id_customer='.$k);
        $db->execute('DELETE FROM `'._DB_PREFIX_.'pulse_guest_profile` WHERE id_customer='.$m);
        $db->execute('UPDATE `'._DB_PREFIX_.'customer` SET deleted=1, email=CONCAT("merged-",id_customer,"-",email) WHERE id_customer='.$m);
        PulseCoreService::audit('pulsefrontdesk', 'profile_merge', array('kept' => $k, 'merged' => $m), 'customer', $k);
        return true;
    }
}
