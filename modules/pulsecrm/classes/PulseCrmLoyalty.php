<?php
/**
 * Loyalty: programme, tiers, members, points ledger. Points are earned off real folio postings by
 * department, held as FIFO lots so expiry is honest, and redeemed as a negative LOYR charge on the
 * guest folio — which keeps the discount inside the hotel's accounting rather than beside it.
 */
class PulseCrmLoyalty
{
    /* ---------- programme & tiers ---------- */

    public static function program($id = 0)
    {
        return $id
            ? Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'pulse_crm_loyalty_program` WHERE id_pulse_crm_loyalty_program=' . (int) $id)
            : Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'pulse_crm_loyalty_program` WHERE active=1 ORDER BY id_pulse_crm_loyalty_program ');
    }

    public static function saveProgram(array $d, $id = 0)
    {
        $rates = isset($d['earn_rate_json']) ? (is_array($d['earn_rate_json']) ? json_encode($d['earn_rate_json']) : $d['earn_rate_json']) : '{}';
        $row = array(
            'name' => pSQL($d['name']),
            'earn_rate_json' => pSQL($rates, true),
            'point_value' => (float) $d['point_value'],
            'min_redeem_points' => (int) $d['min_redeem_points'],
            'expiry_months' => (int) $d['expiry_months'],
            'qualify_window_months' => (int) (isset($d['qualify_window_months']) ? $d['qualify_window_months'] : 12),
            'enrol_bonus' => (int) (isset($d['enrol_bonus']) ? $d['enrol_bonus'] : 0),
            'terms' => pSQL(isset($d['terms']) ? $d['terms'] : '', true),
            'active' => isset($d['active']) ? (int) $d['active'] : 1,
            'date_upd' => date('Y-m-d H:i:s')
        );
        if ($id) {
            Db::getInstance()->update('pulse_crm_loyalty_program', $row, 'id_pulse_crm_loyalty_program=' . (int) $id);
            return (int) $id;
        }
        $row['code'] = pSQL(isset($d['code']) ? Tools::strtoupper(Tools::substr($d['code'], 0, 16)) : 'PULSE');
        $row['date_add'] = date('Y-m-d H:i:s');
        Db::getInstance()->insert('pulse_crm_loyalty_program', $row);
        return (int) Db::getInstance()->Insert_ID();
    }

    public static function tiers($idProgram = 0)
    {
        $p = $idProgram ? $idProgram : (int) self::programId();
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'pulse_crm_tier` WHERE id_pulse_crm_loyalty_program=' . (int) $p . ' ORDER BY sort, min_nights');
    }
    public static function programId()
    {
        $p = self::program();
        return $p ? (int) $p['id_pulse_crm_loyalty_program'] : 0;
    }

    public static function saveTier(array $d, $id = 0)
    {
        $row = array(
            'id_pulse_crm_loyalty_program' => (int) $d['id_pulse_crm_loyalty_program'],
            'name' => pSQL($d['name']),
            'sort' => (int) $d['sort'],
            'min_nights' => (int) $d['min_nights'],
            'min_stays' => (int) $d['min_stays'],
            'min_spend' => (float) $d['min_spend'],
            'earn_multiplier' => (float) $d['earn_multiplier'],
            'benefits' => pSQL(isset($d['benefits']) ? $d['benefits'] : '', true),
            'colour' => pSQL(isset($d['colour']) ? $d['colour'] : 'default')
        );
        if ($id) {
            Db::getInstance()->update('pulse_crm_tier', $row, 'id_pulse_crm_tier=' . (int) $id);
            return (int) $id;
        }
        $row['code'] = pSQL(Tools::strtoupper(Tools::substr(isset($d['code']) ? $d['code'] : $d['name'], 0, 16)));
        Db::getInstance()->insert('pulse_crm_tier', $row);
        return (int) Db::getInstance()->Insert_ID();
    }

    /* ---------- members ---------- */

    public static function memberOf($idCustomer)
    {
        return Db::getInstance()->getRow('SELECT m.*, t.name tier_name, t.code tier_code, t.colour tier_colour, t.earn_multiplier, p.name program_name, p.code program_code, p.point_value, p.min_redeem_points
            FROM `' . _DB_PREFIX_ . 'pulse_crm_member` m
            INNER JOIN `' . _DB_PREFIX_ . 'pulse_crm_loyalty_program` p ON p.id_pulse_crm_loyalty_program=m.id_pulse_crm_loyalty_program
            LEFT JOIN `' . _DB_PREFIX_ . 'pulse_crm_tier` t ON t.id_pulse_crm_tier=m.id_pulse_crm_tier
            WHERE m.id_customer=' . (int) $idCustomer . ' AND m.status="active"');
    }
    public static function member($id)
    {
        return Db::getInstance()->getRow('SELECT m.*, t.name tier_name, t.code tier_code, t.earn_multiplier, p.name program_name, p.point_value, p.min_redeem_points, p.expiry_months,
                CONCAT(c.firstname," ",c.lastname) guest, c.email FROM `' . _DB_PREFIX_ . 'pulse_crm_member` m
            INNER JOIN `' . _DB_PREFIX_ . 'pulse_crm_loyalty_program` p ON p.id_pulse_crm_loyalty_program=m.id_pulse_crm_loyalty_program
            LEFT JOIN `' . _DB_PREFIX_ . 'pulse_crm_tier` t ON t.id_pulse_crm_tier=m.id_pulse_crm_tier
            LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer=m.id_customer WHERE m.id_pulse_crm_member=' . (int) $id);
    }
    public static function byNumber($no)
    {
        return Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'pulse_crm_member` WHERE member_no="' . pSQL($no) . '"');
    }

    /** Enrol a guest. Idempotent — an existing membership is returned rather than duplicated. */
    public static function enrol($idCustomer, $source = 'desk', $idProgram = 0)
    {
        $idCustomer = (int) $idCustomer;
        $p = $idProgram ? self::program($idProgram) : self::program();
        if (!$p) {
            throw new PrestaShopException('No active loyalty programme');
        }
        if ($m = self::memberOf($idCustomer)) {
            return (int) $m['id_pulse_crm_member'];
        }
        $c = new Customer($idCustomer);
        if (!Validate::isLoadedObject($c)) {
            throw new PrestaShopException('No such guest');
        }
        PulseCrmProfile::touch($idCustomer);
        $tiers = self::tiers((int) $p['id_pulse_crm_loyalty_program']);
        $base = $tiers ? (int) $tiers[0]['id_pulse_crm_tier'] : null;
        $no = $p['code'] . date('y') . str_pad((int) PulseCoreService::setting('pulsecrm', 'seq_member') + 1, 5, '0', STR_PAD_LEFT);
        PulseCoreService::setting('pulsecrm', 'seq_member', (int) PulseCoreService::setting('pulsecrm', 'seq_member') + 1);
        Db::getInstance()->insert('pulse_crm_member', array(
            'id_customer' => $idCustomer,
            'id_pulse_crm_loyalty_program' => (int) $p['id_pulse_crm_loyalty_program'],
            'member_no' => pSQL($no),
            'card_no' => pSQL(self::cardNo($no)),
            'id_pulse_crm_tier' => $base,
            'tier_since' => date('Y-m-d'),
            'tier_review_date' => date('Y-m-d', strtotime('+' . (int) $p['qualify_window_months'] . ' month')),
            'join_date' => date('Y-m-d'),
            'enrol_source' => pSQL($source),
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s')
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        if ((int) $p['enrol_bonus'] > 0) {
            self::award($id, 'bonus', (int) $p['enrol_bonus'], 'enrolment', 'Welcome bonus', array());
        }
        self::recalcQualifiers($id);
        PulseCrmProfile::setConsent($idCustomer, 'email', 'opt_in', 'loyalty_enrolment', 'programme terms accepted at ' . $source);
        $m = self::member($id);
        PulseCrmComms::deliver($idCustomer, 'email', 'crm_loyalty_welcome', array('program' => $p['name'], 'member_no' => $no, 'tier' => $m['tier_name'], 'points' => (int) $m['points_balance']), array('kind' => 'transactional', 'transactional' => 1, 'ignore_quiet' => 1, 'reference' => 'enrol:' . $id));
        PulseCoreService::audit('pulsecrm', 'loyalty_enrol', array('member_no' => $no, 'source' => $source), 'pulse_crm_member', $id);
        PulseCoreService::event('actionPulseCrmMemberEnrolled', array('id_member' => $id, 'id_customer' => $idCustomer, 'member_no' => $no));
        return $id;
    }

    /** A printable card number derived from the member number, with a Luhn check digit so the desk can catch a typo. */
    public static function cardNo($memberNo)
    {
        $digits = preg_replace('/\D/', '', $memberNo);
        $digits = str_pad(substr($digits, -11), 11, '0', STR_PAD_LEFT);
        $sum = 0;
        $alt = true;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($alt) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $alt = !$alt;
        }
        return '9' . $digits . ((10 - $sum % 10) % 10);
    }

    public static function members($q = '', $limit = 100)
    {
        $q = pSQL(trim($q));
        return Db::getInstance()->executeS('SELECT m.*, t.name tier_name, t.colour tier_colour, CONCAT(c.firstname," ",c.lastname) guest, c.email
            FROM `' . _DB_PREFIX_ . 'pulse_crm_member` m LEFT JOIN `' . _DB_PREFIX_ . 'pulse_crm_tier` t ON t.id_pulse_crm_tier=m.id_pulse_crm_tier
            INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer=m.id_customer
            WHERE 1' . ($q ? ' AND (m.member_no LIKE "%' . $q . '%" OR m.card_no LIKE "%' . $q . '%" OR c.firstname LIKE "%' . $q . '%" OR c.lastname LIKE "%' . $q . '%" OR c.email LIKE "%' . $q . '%")' : '') . '
            ORDER BY m.points_balance DESC LIMIT ' . (int) $limit);
    }

    public static function statement($idMember, $limit = 50)
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'pulse_crm_points_txn` WHERE id_pulse_crm_member=' . (int) $idMember . ' ORDER BY id_pulse_crm_points_txn DESC LIMIT ' . (int) $limit);
    }

    /* ---------- points ---------- */

    /** Write one ledger row and move the balance. Earn rows carry their own expiry and remaining lot. */
    public static function award($idMember, $type, $points, $source, $description, array $extra = array())
    {
        $points = (int) $points;
        if (!$points) {
            return 0;
        }
        $m = self::member($idMember);
        if (!$m) {
            throw new PrestaShopException('No such member');
        }
        $signed = in_array($type, array('redeem', 'expire', 'transfer_out')) ? -abs($points) : ($type === 'adjust' ? $points : abs($points));
        $balance = (int) $m['points_balance'] + $signed;
        if ($balance < 0) {
            throw new PrestaShopException('That would take the balance below zero');
        }
        $isLot = in_array($type, array('earn', 'bonus', 'transfer_in')) || ($type === 'adjust' && $signed > 0);
        $expiry = isset($extra['expires_on']) ? $extra['expires_on'] : ((int) $m['expiry_months'] > 0 ? date('Y-m-d', strtotime('+' . (int) $m['expiry_months'] . ' month')) : null);
        Db::getInstance()->insert('pulse_crm_points_txn', array(
            'id_pulse_crm_member' => (int) $idMember,
            'type' => pSQL($type),
            'points' => $signed,
            'points_remaining' => $isLot ? abs($signed) : 0,
            'balance_after' => $balance,
            'source' => pSQL($source),
            'reference' => pSQL(isset($extra['reference']) ? $extra['reference'] : ''),
            'description' => pSQL($description),
            'id_htl_booking' => !empty($extra['id_htl_booking']) ? (int) $extra['id_htl_booking'] : null,
            'id_pulse_folio_line' => !empty($extra['id_pulse_folio_line']) ? (int) $extra['id_pulse_folio_line'] : null,
            'department' => pSQL(isset($extra['department']) ? $extra['department'] : ''),
            'amount_basis' => (float) (isset($extra['amount_basis']) ? $extra['amount_basis'] : 0),
            'expires_on' => $isLot ? pSQL($expiry) : null,
            'id_employee' => PulseCrmService::emp(),
            'business_date' => PulseCrmService::bd(),
            'date_add' => date('Y-m-d H:i:s')
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'pulse_crm_member` SET points_balance=' . $balance
            . ', points_earned_life=points_earned_life+' . ($signed > 0 ? $signed : 0)
            . ', points_redeemed_life=points_redeemed_life+' . ($type === 'redeem' ? abs($signed) : 0)
            . ', points_expired_life=points_expired_life+' . ($type === 'expire' ? abs($signed) : 0)
            . ', date_upd="' . date('Y-m-d H:i:s') . '" WHERE id_pulse_crm_member=' . (int) $idMember);
        if ($signed < 0 && $type !== 'expire') {
            self::consumeLots($idMember, abs($signed));
        }
        return $id;
    }

    /** FIFO: take points off the oldest unexpired lots first, so the expiry job is telling the truth. */
    protected static function consumeLots($idMember, $points)
    {
        $left = (int) $points;
        foreach (Db::getInstance()->executeS('SELECT id_pulse_crm_points_txn, points_remaining FROM `' . _DB_PREFIX_ . 'pulse_crm_points_txn` WHERE id_pulse_crm_member=' . (int) $idMember . ' AND points_remaining>0 ORDER BY COALESCE(expires_on,"2999-12-31"), id_pulse_crm_points_txn') as $lot) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, (int) $lot['points_remaining']);
            Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'pulse_crm_points_txn` SET points_remaining=points_remaining-' . $take . ' WHERE id_pulse_crm_points_txn=' . (int) $lot['id_pulse_crm_points_txn']);
            $left -= $take;
        }
        return $points - $left;
    }

    /** Points per 1000 naira for a folio department, before the tier multiplier. */
    public static function rateFor($program, $department)
    {
        $rates = json_decode((string) $program['earn_rate_json'], true);
        if (!is_array($rates)) {
            $rates = array();
        }
        if (isset($rates[$department])) {
            return (float) $rates[$department];
        }
        return isset($rates['default']) ? (float) $rates['default'] : 0;
    }

    /**
     * Earn against a folio line. Called from actionPulseFolioPost. Payments, taxes, adjustments and
     * the loyalty redemption line itself never earn, and a line is only ever counted once.
     */
    public static function earnFromFolioLine($idLine)
    {
        if (!PulseCrmService::fd() || !PulseCrmService::tableExists('pulse_folio_line')) {
            return 0;
        }
        $l = Db::getInstance()->getRow('SELECT fl.*, f.id_customer, f.id_htl_booking, cc.code FROM `' . _DB_PREFIX_ . 'pulse_folio_line` fl
            INNER JOIN `' . _DB_PREFIX_ . 'pulse_folio` f ON f.id_pulse_folio=fl.id_pulse_folio
            LEFT JOIN `' . _DB_PREFIX_ . 'pulse_charge_code` cc ON cc.id_pulse_charge_code=fl.id_pulse_charge_code
            WHERE fl.id_pulse_folio_line=' . (int) $idLine);
        if (!$l || (int) $l['is_payment'] || (int) $l['voided'] || !$l['id_customer']) {
            return 0;
        }
        if (in_array($l['code'], array('LOYR', 'ADJ')) || in_array($l['department'], array('payment', 'tax', 'adjustment'))) {
            return 0;
        }
        if ((float) $l['amount_tax_incl'] <= 0) {
            return 0;
        }
        if ((int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pulse_crm_points_txn` WHERE id_pulse_folio_line=' . (int) $idLine)) {
            return 0;
        }
        $m = self::memberOf((int) $l['id_customer']);
        if (!$m) {
            return 0;
        }
        $p = self::program((int) $m['id_pulse_crm_loyalty_program']);
        $rate = self::rateFor($p, $l['department']);
        if ($rate <= 0) {
            return 0;
        }
        $base = round((float) $l['amount_tax_incl'] / (1 + (float) $l['tax_rate'] / 100), 2); // points earn on net spend, not on VAT
        $points = (int) floor($base / 1000 * $rate * (float) ($m['earn_multiplier'] ? $m['earn_multiplier'] : 1));
        if ($points <= 0) {
            return 0;
        }
        self::award((int) $m['id_pulse_crm_member'], 'earn', $points, 'folio', 'Earned on ' . $l['description'], array(
            'id_pulse_folio_line' => (int) $idLine,
            'id_htl_booking' => (int) $l['id_htl_booking'],
            'department' => $l['department'],
            'amount_basis' => $base,
            'reference' => $l['code']
        ));
        return $points;
    }

    /**
     * Redeem points against a stay. Posts a negative LOYR charge so the guest's bill actually falls and
     * the reduction lands in the same ledger as every other line.
     */
    public static function redeem($idMember, $points, $idBooking = null, $note = '')
    {
        $m = self::member($idMember);
        if (!$m) {
            throw new PrestaShopException('No such member');
        }
        $points = (int) $points;
        if ($points < (int) $m['min_redeem_points']) {
            throw new PrestaShopException('Minimum redemption is ' . (int) $m['min_redeem_points'] . ' points');
        }
        if ($points > (int) $m['points_balance']) {
            throw new PrestaShopException('Balance is only ' . (int) $m['points_balance'] . ' points');
        }
        $value = round($points * (float) $m['point_value'], 2);
        $line = null;
        if ($idBooking && PulseCrmService::fd()) {
            $f = PulseFolio::openForBooking((int) $idBooking);
            if (!$f) {
                throw new PrestaShopException('That stay has no open folio to credit');
            }
            $line = $f->post('LOYR', 'Loyalty redemption — ' . $points . ' points' . ($note ? ' (' . $note . ')' : ''), 1, -$value, 0, false, null, 'crm', 'member:' . $m['member_no']);
        }
        $id = self::award($idMember, 'redeem', $points, 'redemption', 'Redeemed ' . $points . ' points' . ($idBooking ? ' on stay ' . (int) $idBooking : ''), array(
            'id_htl_booking' => $idBooking ? (int) $idBooking : null,
            'id_pulse_folio_line' => $line,
            'reference' => $note
        ));
        PulseCoreService::audit('pulsecrm', 'loyalty_redeem', array('points' => $points, 'value' => $value, 'line' => $line), 'pulse_crm_member', (int) $idMember);
        PulseCoreService::event('actionPulseCrmPointsRedeemed', array('id_member' => (int) $idMember, 'points' => $points, 'value' => $value, 'id_htl_booking' => $idBooking));
        return array('id_txn' => $id, 'points' => $points, 'value' => $value, 'id_folio_line' => $line, 'balance' => (int) $m['points_balance'] - $points);
    }

    public static function adjust($idMember, $points, $reason)
    {
        if (!trim($reason)) {
            throw new PrestaShopException('A manual adjustment needs a reason');
        }
        return self::award($idMember, 'adjust', (int) $points, 'manual', $reason, array());
    }

    /* ---------- qualifiers, tiers, expiry ---------- */

    /** Rolling-window nights, stays and spend from the booking history — the numbers tiers are judged on. */
    public static function recalcQualifiers($idMember)
    {
        $m = self::member($idMember);
        if (!$m) {
            return false;
        }
        $p = self::program((int) $m['id_pulse_crm_loyalty_program']);
        $months = max(1, (int) $p['qualify_window_months']);
        $r = array('nights' => 0, 'stays' => 0, 'spend' => 0);
        if (PulseCrmService::tableExists('htl_booking_detail')) {
            $q = Db::getInstance()->getRow('SELECT COUNT(*) stays, COALESCE(SUM(DATEDIFF(date_to,date_from)),0) nights, COALESCE(SUM(total_price_tax_incl),0) spend
                FROM `' . _DB_PREFIX_ . 'htl_booking_detail` WHERE id_customer=' . (int) $m['id_customer'] . ' AND is_cancelled=0 AND is_refunded=0 AND date_to>=DATE_SUB(CURDATE(), INTERVAL ' . $months . ' MONTH)');
            if ($q) {
                $r = array('nights' => (int) $q['nights'], 'stays' => (int) $q['stays'], 'spend' => (float) $q['spend']);
            }
        }
        Db::getInstance()->update('pulse_crm_member', array('qualifying_nights' => $r['nights'], 'qualifying_stays' => $r['stays'], 'qualifying_spend' => $r['spend'], 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_member=' . (int) $idMember);
        return $r;
    }

    /** Move the member to the highest tier they qualify for; a tier is never taken away inside its review window. */
    public static function recalcTier($idMember, $allowDowngrade = false)
    {
        self::recalcQualifiers($idMember);
        $m = self::member($idMember);
        if (!$m) {
            return false;
        }
        $tiers = self::tiers((int) $m['id_pulse_crm_loyalty_program']);
        $target = null;
        foreach ($tiers as $t) {
            if ((int) $m['qualifying_nights'] >= (int) $t['min_nights'] && (int) $m['qualifying_stays'] >= (int) $t['min_stays'] && (float) $m['qualifying_spend'] >= (float) $t['min_spend']) {
                $target = $t;
            }
        }
        if (!$target) {
            $target = $tiers ? $tiers[0] : null;
        }
        if (!$target) {
            return false;
        }
        $cur = (int) $m['id_pulse_crm_tier'];
        $curSort = (int) Db::getInstance()->getValue('SELECT sort FROM `' . _DB_PREFIX_ . 'pulse_crm_tier` WHERE id_pulse_crm_tier=' . $cur);
        $up = (int) $target['sort'] > $curSort;
        if ((int) $target['id_pulse_crm_tier'] === $cur) {
            return false;
        }
        if (!$up && !$allowDowngrade && $m['tier_review_date'] && $m['tier_review_date'] > date('Y-m-d')) {
            return false;
        }
        Db::getInstance()->update('pulse_crm_member', array(
            'id_pulse_crm_tier' => (int) $target['id_pulse_crm_tier'],
            'tier_since' => date('Y-m-d'),
            'tier_review_date' => date('Y-m-d', strtotime('+12 month')),
            'date_upd' => date('Y-m-d H:i:s')
        ), 'id_pulse_crm_member=' . (int) $idMember);
        if ($up) {
            PulseCrmComms::deliver((int) $m['id_customer'], 'email', 'crm_tier_up', array('program' => $m['program_name'], 'tier' => $target['name'], 'points' => (int) $m['points_balance']), array('kind' => 'transactional', 'transactional' => 1, 'reference' => 'tier:' . $idMember));
            PulseCrmProfile::tag((int) $m['id_customer'], 'vip', 'loyalty');
        }
        PulseCoreService::audit('pulsecrm', $up ? 'tier_up' : 'tier_down', array('from' => $cur, 'to' => (int) $target['id_pulse_crm_tier']), 'pulse_crm_member', (int) $idMember);
        return $target['name'];
    }

    /** Cron: re-tier members whose review date has passed or who have been active lately. */
    public static function recalcTiersDue($limit = 200)
    {
        $n = 0;
        foreach (Db::getInstance()->executeS('SELECT id_pulse_crm_member FROM `' . _DB_PREFIX_ . 'pulse_crm_member` WHERE status="active" AND (tier_review_date IS NULL OR tier_review_date<=CURDATE() OR date_upd<DATE_SUB(NOW(), INTERVAL 7 DAY)) ORDER BY COALESCE(tier_review_date,"1970-01-01") LIMIT ' . (int) $limit) as $m) {
            if (self::recalcTier((int) $m['id_pulse_crm_member'], true)) {
                $n++;
            }
        }
        return $n;
    }

    /** Cron: expire lots that have run out of time, and warn anyone whose points go in the next 30 days. */
    public static function expirePoints($limit = 500)
    {
        $expired = 0;
        $warned = 0;
        foreach (Db::getInstance()->executeS('SELECT id_pulse_crm_member, SUM(points_remaining) pts FROM `' . _DB_PREFIX_ . 'pulse_crm_points_txn`
            WHERE points_remaining>0 AND expires_on IS NOT NULL AND expires_on<CURDATE() GROUP BY id_pulse_crm_member LIMIT ' . (int) $limit) as $r) {
            $pts = (int) $r['pts'];
            if ($pts <= 0) {
                continue;
            }
            $m = self::member((int) $r['id_pulse_crm_member']);
            if (!$m) {
                continue;
            }
            $pts = min($pts, (int) $m['points_balance']);
            if ($pts > 0) {
                self::award((int) $r['id_pulse_crm_member'], 'expire', $pts, 'expiry', $pts . ' points expired', array());
                $expired += $pts;
            }
            Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'pulse_crm_points_txn` SET points_remaining=0 WHERE id_pulse_crm_member=' . (int) $r['id_pulse_crm_member'] . ' AND points_remaining>0 AND expires_on<CURDATE()');
        }
        foreach (Db::getInstance()->executeS('SELECT id_pulse_crm_member, SUM(points_remaining) pts, MIN(expires_on) first_expiry FROM `' . _DB_PREFIX_ . 'pulse_crm_points_txn`
            WHERE points_remaining>0 AND expires_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) GROUP BY id_pulse_crm_member LIMIT ' . (int) $limit) as $r) {
            $m = self::member((int) $r['id_pulse_crm_member']);
            if (!$m) {
                continue;
            }
            $res = PulseCrmComms::deliver((int) $m['id_customer'], 'email', 'crm_points_expiring', array('program' => $m['program_name'], 'points_expiring' => (int) $r['pts'], 'expiry_date' => $r['first_expiry'], 'points' => (int) $m['points_balance']), array('kind' => 'transactional', 'reference' => 'expiry:' . $m['id_pulse_crm_member'], 'suppress_days' => 30));
            if ($res['ok']) {
                $warned++;
            }
        }
        return array('expired' => $expired, 'warned' => $warned);
    }

    /** Outstanding points and what they would cost the hotel if every one were redeemed tomorrow. */
    public static function liability()
    {
        return Db::getInstance()->executeS('SELECT t.name tier, COUNT(*) members, SUM(m.points_balance) points, ROUND(SUM(m.points_balance*p.point_value),2) value
            FROM `' . _DB_PREFIX_ . 'pulse_crm_member` m INNER JOIN `' . _DB_PREFIX_ . 'pulse_crm_loyalty_program` p ON p.id_pulse_crm_loyalty_program=m.id_pulse_crm_loyalty_program
            LEFT JOIN `' . _DB_PREFIX_ . 'pulse_crm_tier` t ON t.id_pulse_crm_tier=m.id_pulse_crm_tier WHERE m.status="active" GROUP BY m.id_pulse_crm_tier ORDER BY t.sort');
    }
}
