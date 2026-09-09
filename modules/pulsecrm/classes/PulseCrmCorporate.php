<?php
/**
 * The B2B half of CRM: accounts hanging off Front Desk's pulse_company, their contacts, the call and
 * visit log, an opportunity pipeline in room-nights as well as naira, contracted rates, and production
 * measured against last year — which is the only corporate number a Nigerian GM ever asks for.
 */
class PulseCrmCorporate
{
    public static function accounts($status = null, $q = '')
    {
        $q = pSQL(trim($q)); $co = PulseCrmService::co();
        return Db::getInstance()->executeS('SELECT a.*, '.($co ? 'co.name company_name, co.ledger_balance, co.credit_limit, co.discount_pct' : 'a.name company_name, 0 ledger_balance, 0 credit_limit, 0 discount_pct').', CONCAT(e.firstname," ",e.lastname) manager,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_contact` ct WHERE ct.id_pulse_crm_account=a.id_pulse_crm_account AND ct.active=1) contacts,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_crm_opportunity` o WHERE o.id_pulse_crm_account=a.id_pulse_crm_account AND o.stage NOT IN ("won","lost")) open_opps,
                (SELECT MAX(activity_date) FROM `'._DB_PREFIX_.'pulse_crm_activity` ac WHERE ac.id_pulse_crm_account=a.id_pulse_crm_account) last_touch
            FROM `'._DB_PREFIX_.'pulse_crm_account` a
            '.($co ? 'LEFT JOIN `'._DB_PREFIX_.'pulse_company` co ON co.id_pulse_company=a.id_pulse_company' : '').'
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=a.account_manager
            WHERE 1'.($status ? ' AND a.status="'.pSQL($status).'"' : '').($q ? ' AND (a.name LIKE "%'.$q.'%"'.($co ? ' OR co.name LIKE "%'.$q.'%"' : '').')' : '').'
            ORDER BY a.status, a.name');
    }

    public static function account($id)
    {
        $co = PulseCrmService::co();
        $a = Db::getInstance()->getRow('SELECT a.*, '.($co ? 'co.name company_name, co.ledger_balance, co.credit_limit, co.discount_pct, co.contact_name, co.email company_email, co.phone company_phone, co.tin'
                : 'a.name company_name, 0 ledger_balance, 0 credit_limit, 0 discount_pct, "" contact_name, "" company_email, "" company_phone, "" tin').'
            FROM `'._DB_PREFIX_.'pulse_crm_account` a '.($co ? 'LEFT JOIN `'._DB_PREFIX_.'pulse_company` co ON co.id_pulse_company=a.id_pulse_company' : '').' WHERE a.id_pulse_crm_account='.(int) $id);
        if (!$a) { return null; }
        $a['contacts'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_contact` WHERE id_pulse_crm_account='.(int) $id.' AND active=1 ORDER BY is_primary DESC, name');
        $a['activities'] = Db::getInstance()->executeS('SELECT ac.*, CONCAT(e.firstname," ",e.lastname) who, ct.name contact_name FROM `'._DB_PREFIX_.'pulse_crm_activity` ac
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=ac.id_employee LEFT JOIN `'._DB_PREFIX_.'pulse_crm_contact` ct ON ct.id_pulse_crm_contact=ac.id_pulse_crm_contact
            WHERE ac.id_pulse_crm_account='.(int) $id.' ORDER BY ac.activity_date DESC LIMIT 50');
        $a['opportunities'] = Db::getInstance()->executeS('SELECT o.*, CONCAT(e.firstname," ",e.lastname) owner_name FROM `'._DB_PREFIX_.'pulse_crm_opportunity` o
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=o.owner WHERE o.id_pulse_crm_account='.(int) $id.' ORDER BY FIELD(o.stage,"negotiation","proposal","qualified","lead","won","lost"), o.close_date');
        $a['rates'] = Db::getInstance()->executeS('SELECT r.*, pl.name product_name FROM `'._DB_PREFIX_.'pulse_crm_account_rate` r
            LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=r.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.'
            WHERE r.id_pulse_crm_account='.(int) $id.' ORDER BY r.valid_from DESC');
        $a['production'] = $a['id_pulse_company'] ? self::production((int) $a['id_pulse_company']) : array();
        $a['travellers'] = $a['id_pulse_company'] ? self::travellers((int) $a['id_pulse_company']) : array();
        return $a;
    }

    public static function saveAccount(array $d, $id = 0)
    {
        $row = array('id_pulse_company' => (int) $d['id_pulse_company'] ?: null, 'name' => pSQL($d['name']), 'industry' => pSQL(isset($d['industry']) ? $d['industry'] : ''),
            'segment' => pSQL(isset($d['segment']) ? $d['segment'] : 'corporate'), 'account_manager' => (int) (isset($d['account_manager']) ? $d['account_manager'] : 0) ?: null,
            'status' => pSQL(isset($d['status']) ? $d['status'] : 'prospect'), 'potential_nights' => (int) (isset($d['potential_nights']) ? $d['potential_nights'] : 0),
            'potential_value' => (float) (isset($d['potential_value']) ? $d['potential_value'] : 0),
            'next_review' => !empty($d['next_review']) && Validate::isDate($d['next_review']) ? pSQL($d['next_review']) : null,
            'notes' => pSQL(isset($d['notes']) ? $d['notes'] : '', true), 'date_upd' => date('Y-m-d H:i:s'));
        if ($id) { Db::getInstance()->update('pulse_crm_account', $row, 'id_pulse_crm_account='.(int) $id); return (int) $id; }
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_crm_account', $row);
        return (int) Db::getInstance()->Insert_ID();
    }

    public static function saveContact(array $d, $id = 0)
    {
        $row = array('id_pulse_crm_account' => (int) $d['id_pulse_crm_account'], 'id_customer' => (int) (isset($d['id_customer']) ? $d['id_customer'] : 0) ?: null,
            'name' => pSQL($d['name']), 'title' => pSQL(isset($d['title']) ? $d['title'] : ''), 'email' => pSQL(isset($d['email']) ? $d['email'] : ''),
            'phone' => pSQL(isset($d['phone']) ? $d['phone'] : ''), 'decision_role' => pSQL(isset($d['decision_role']) ? $d['decision_role'] : 'booker'),
            'is_primary' => !empty($d['is_primary']) ? 1 : 0, 'notes' => pSQL(isset($d['notes']) ? $d['notes'] : ''), 'active' => isset($d['active']) ? (int) $d['active'] : 1, 'date_upd' => date('Y-m-d H:i:s'));
        if (!empty($row['is_primary'])) { Db::getInstance()->update('pulse_crm_contact', array('is_primary' => 0), 'id_pulse_crm_account='.(int) $d['id_pulse_crm_account']); }
        if ($id) { Db::getInstance()->update('pulse_crm_contact', $row, 'id_pulse_crm_contact='.(int) $id); return (int) $id; }
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_crm_contact', $row);
        return (int) Db::getInstance()->Insert_ID();
    }

    /** Log a call, visit or email; an optional follow-up date puts it on the reminder list. */
    public static function logActivity(array $d)
    {
        Db::getInstance()->insert('pulse_crm_activity', array('id_pulse_crm_account' => (int) $d['id_pulse_crm_account'],
            'id_pulse_crm_contact' => (int) (isset($d['id_pulse_crm_contact']) ? $d['id_pulse_crm_contact'] : 0) ?: null,
            'type' => pSQL(isset($d['type']) ? $d['type'] : 'call'), 'subject' => pSQL(Tools::substr($d['subject'], 0, 190)),
            'notes' => pSQL(isset($d['notes']) ? $d['notes'] : '', true), 'outcome' => pSQL(isset($d['outcome']) ? $d['outcome'] : ''),
            'activity_date' => pSQL(!empty($d['activity_date']) ? $d['activity_date'] : date('Y-m-d H:i:s')),
            'follow_up_at' => !empty($d['follow_up_at']) ? pSQL($d['follow_up_at']) : null, 'follow_up_done' => 0,
            'id_employee' => PulseCrmService::emp() ?: null, 'date_add' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID();
        Db::getInstance()->update('pulse_crm_account', array('date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_account='.(int) $d['id_pulse_crm_account']);
        return $id;
    }
    public static function completeFollowUp($id) { return Db::getInstance()->update('pulse_crm_activity', array('follow_up_done' => 1), 'id_pulse_crm_activity='.(int) $id); }

    /** Follow-ups that have come due, oldest first — the sales manager's morning list. */
    public static function followUps($daysAhead = 7)
    {
        return Db::getInstance()->executeS('SELECT ac.*, a.name account, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_crm_activity` ac
            INNER JOIN `'._DB_PREFIX_.'pulse_crm_account` a ON a.id_pulse_crm_account=ac.id_pulse_crm_account
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=ac.id_employee
            WHERE ac.follow_up_done=0 AND ac.follow_up_at IS NOT NULL AND ac.follow_up_at<=DATE_ADD(NOW(), INTERVAL '.(int) $daysAhead.' DAY) ORDER BY ac.follow_up_at');
    }

    public static function saveOpportunity(array $d, $id = 0)
    {
        $row = array('id_pulse_crm_account' => (int) $d['id_pulse_crm_account'], 'name' => pSQL(Tools::substr($d['name'], 0, 190)), 'stage' => pSQL($d['stage']),
            'expected_nights' => (int) $d['expected_nights'], 'expected_value' => (float) $d['expected_value'], 'probability' => max(0, min(100, (int) $d['probability'])),
            'close_date' => !empty($d['close_date']) && Validate::isDate($d['close_date']) ? pSQL($d['close_date']) : null,
            'owner' => (int) (isset($d['owner']) ? $d['owner'] : 0) ?: (PulseCrmService::emp() ?: null),
            'lost_reason' => pSQL(isset($d['lost_reason']) ? $d['lost_reason'] : ''), 'notes' => pSQL(isset($d['notes']) ? $d['notes'] : '', true), 'date_upd' => date('Y-m-d H:i:s'));
        if ($row['stage'] === 'lost' && !$row['lost_reason']) { throw new PrestaShopException('Say why it was lost — that is the only useful part of a lost deal'); }
        if ($id) { Db::getInstance()->update('pulse_crm_opportunity', $row, 'id_pulse_crm_opportunity='.(int) $id); return (int) $id; }
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_crm_opportunity', $row);
        return (int) Db::getInstance()->Insert_ID();
    }

    public static function pipeline()
    {
        return Db::getInstance()->executeS('SELECT o.stage, COUNT(*) deals, SUM(o.expected_nights) nights, ROUND(SUM(o.expected_value),2) value,
                ROUND(SUM(o.expected_value*o.probability/100),2) weighted FROM `'._DB_PREFIX_.'pulse_crm_opportunity` o
            GROUP BY o.stage ORDER BY FIELD(o.stage,"lead","qualified","proposal","negotiation","won","lost")');
    }

    public static function opportunities($stage = null, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT o.*, a.name account, CONCAT(e.firstname," ",e.lastname) owner_name FROM `'._DB_PREFIX_.'pulse_crm_opportunity` o
            INNER JOIN `'._DB_PREFIX_.'pulse_crm_account` a ON a.id_pulse_crm_account=o.id_pulse_crm_account
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=o.owner'.($stage ? ' WHERE o.stage="'.pSQL($stage).'"' : ' WHERE o.stage NOT IN ("won","lost")')
            .' ORDER BY o.close_date, o.expected_value DESC LIMIT '.(int) $limit);
    }

    public static function saveRate(array $d, $id = 0)
    {
        $row = array('id_pulse_crm_account' => (int) $d['id_pulse_crm_account'], 'id_product' => (int) (isset($d['id_product']) ? $d['id_product'] : 0) ?: null,
            'room_type_name' => pSQL(isset($d['room_type_name']) ? $d['room_type_name'] : ''), 'rate_tax_excl' => (float) $d['rate_tax_excl'],
            'includes_breakfast' => !empty($d['includes_breakfast']) ? 1 : 0, 'valid_from' => pSQL($d['valid_from']), 'valid_to' => pSQL($d['valid_to']),
            'note' => pSQL(isset($d['note']) ? $d['note'] : ''));
        if ($row['valid_to'] < $row['valid_from']) { throw new PrestaShopException('The rate ends before it starts'); }
        if ($id) { Db::getInstance()->update('pulse_crm_account_rate', $row, 'id_pulse_crm_account_rate='.(int) $id); return (int) $id; }
        Db::getInstance()->insert('pulse_crm_account_rate', $row);
        return (int) Db::getInstance()->Insert_ID();
    }
    public static function removeRate($id) { return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_account_rate` WHERE id_pulse_crm_account_rate='.(int) $id); }

    /** The contracted rate to quote for a room type on a date — what the sales call actually needs. */
    public static function rateFor($idAccount, $idProduct, $date = null)
    {
        $d = $date ? $date : date('Y-m-d');
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_account_rate` WHERE id_pulse_crm_account='.(int) $idAccount
            .' AND (id_product='.(int) $idProduct.' OR id_product IS NULL) AND valid_from<="'.pSQL($d).'" AND valid_to>="'.pSQL($d).'" ORDER BY id_product IS NULL, rate_tax_excl LIMIT 1');
    }

    /** Room nights and revenue by month for one company, this year against last. */
    public static function production($idCompany, $months = 24)
    {
        if (!PulseCrmService::tableExists('htl_booking_detail') || !PulseCrmService::tableExists('pulse_guest_profile')) { return array(); }
        return Db::getInstance()->executeS('SELECT DATE_FORMAT(b.date_from,"%Y-%m") ym, COUNT(*) stays, SUM(DATEDIFF(b.date_to,b.date_from)) nights,
                ROUND(SUM(b.total_price_tax_incl),2) revenue, ROUND(SUM(b.total_price_tax_incl)/NULLIF(SUM(DATEDIFF(b.date_to,b.date_from)),0),2) adr
            FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=b.id_customer
            WHERE gp.id_pulse_company='.(int) $idCompany.' AND b.is_cancelled=0 AND b.is_refunded=0 AND b.date_from>=DATE_SUB(CURDATE(), INTERVAL '.(int) $months.' MONTH)
            GROUP BY ym ORDER BY ym DESC');
    }

    public static function travellers($idCompany, $limit = 50)
    {
        if (!PulseCrmService::tableExists('pulse_guest_profile')) { return array(); }
        return Db::getInstance()->executeS('SELECT c.id_customer, CONCAT(c.firstname," ",c.lastname) guest, c.email, gp.stays, gp.nights, gp.lifetime_revenue, gp.last_stay
            FROM `'._DB_PREFIX_.'pulse_guest_profile` gp INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=gp.id_customer AND c.deleted=0
            WHERE gp.id_pulse_company='.(int) $idCompany.' ORDER BY gp.lifetime_revenue DESC LIMIT '.(int) $limit);
    }

    /** Production league table: this year against last, by company. The board slide. */
    public static function productionReport($year = null)
    {
        $y = (int) ($year ? $year : date('Y'));
        if (!PulseCrmService::tableExists('htl_booking_detail') || !PulseCrmService::tableExists('pulse_company')) { return array(); }
        if (!PulseCrmService::gp()) { return array(); }
        $rows = Db::getInstance()->executeS('SELECT co.id_pulse_company, co.name, a.id_pulse_crm_account, a.status,
                SUM(IF(YEAR(b.date_from)='.$y.', DATEDIFF(b.date_to,b.date_from), 0)) nights_ty,
                SUM(IF(YEAR(b.date_from)='.($y - 1).', DATEDIFF(b.date_to,b.date_from), 0)) nights_ly,
                ROUND(SUM(IF(YEAR(b.date_from)='.$y.', b.total_price_tax_incl, 0)),2) revenue_ty,
                ROUND(SUM(IF(YEAR(b.date_from)='.($y - 1).', b.total_price_tax_incl, 0)),2) revenue_ly
            FROM `'._DB_PREFIX_.'pulse_company` co
            LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_pulse_company=co.id_pulse_company
            LEFT JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id_customer=gp.id_customer AND b.is_cancelled=0 AND b.is_refunded=0 AND YEAR(b.date_from) IN ('.$y.','.($y - 1).')
            LEFT JOIN `'._DB_PREFIX_.'pulse_crm_account` a ON a.id_pulse_company=co.id_pulse_company
            GROUP BY co.id_pulse_company ORDER BY revenue_ty DESC, nights_ty DESC');
        foreach ($rows as &$r) {
            $r['nights_var'] = (int) $r['nights_ty'] - (int) $r['nights_ly'];
            $r['revenue_var'] = round((float) $r['revenue_ty'] - (float) $r['revenue_ly'], 2);
            $r['revenue_var_pct'] = (float) $r['revenue_ly'] > 0 ? round(((float) $r['revenue_ty'] / (float) $r['revenue_ly'] - 1) * 100, 1) : null;
            $r['adr_ty'] = (int) $r['nights_ty'] > 0 ? round((float) $r['revenue_ty'] / (int) $r['nights_ty'], 2) : 0;
        }
        return $rows;
    }

    /** Accounts nobody has spoken to lately — dormancy is the quiet way corporate business is lost. */
    public static function stale($days = 60)
    {
        $co = PulseCrmService::co();
        return Db::getInstance()->executeS('SELECT a.*, '.($co ? 'co.name company_name' : 'a.name company_name').', (SELECT MAX(activity_date) FROM `'._DB_PREFIX_.'pulse_crm_activity` ac WHERE ac.id_pulse_crm_account=a.id_pulse_crm_account) last_touch
            FROM `'._DB_PREFIX_.'pulse_crm_account` a '.($co ? 'LEFT JOIN `'._DB_PREFIX_.'pulse_company` co ON co.id_pulse_company=a.id_pulse_company' : '').'
            WHERE a.status IN ("active","prospect")
            AND COALESCE((SELECT MAX(activity_date) FROM `'._DB_PREFIX_.'pulse_crm_activity` ac2 WHERE ac2.id_pulse_crm_account=a.id_pulse_crm_account), a.date_add) < DATE_SUB(NOW(), INTERVAL '.(int) $days.' DAY)
            ORDER BY last_touch');
    }
}
