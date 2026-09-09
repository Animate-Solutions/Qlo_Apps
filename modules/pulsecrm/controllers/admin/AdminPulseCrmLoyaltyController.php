<?php
/** Loyalty: the programme, its tiers, the member register, the points ledger and the liability the hotel is carrying. */
class AdminPulseCrmLoyaltyController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Loyalty'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        $p = PulseCrmLoyalty::program();
        if ($idm = (int) Tools::getValue('id_member')) {
            $this->context->smarty->assign(array('m' => PulseCrmLoyalty::member($idm), 'txns' => PulseCrmLoyalty::statement($idm, 100), 'tiers' => PulseCrmLoyalty::tiers(),
                'stays' => PulseCrmService::tableExists('htl_booking_detail') ? Db::getInstance()->executeS('SELECT b.id, b.date_from, b.date_to, b.room_num, b.total_price_tax_incl, b.id_status FROM `'._DB_PREFIX_.'htl_booking_detail` b WHERE b.id_customer=(SELECT id_customer FROM `'._DB_PREFIX_.'pulse_crm_member` WHERE id_pulse_crm_member='.$idm.') ORDER BY b.date_from DESC LIMIT 20') : array(),
                'self_url' => $self, 'fd' => PulseCrmService::fd()));
            return $this->setTemplate('member.tpl');
        }
        $this->context->smarty->assign(array(
            'p' => $p, 'tiers' => PulseCrmLoyalty::tiers(), 'rates' => $p ? json_decode($p['earn_rate_json'], true) : array(),
            'departments' => array('rooms', 'fnb', 'minibar', 'spa', 'laundry', 'telephone', 'business_centre', 'misc', 'default'),
            'members' => PulseCrmLoyalty::members(Tools::getValue('q', ''), 100), 'q' => Tools::getValue('q', ''),
            'liability' => PulseCrmLoyalty::liability(),
            'recent' => Db::getInstance()->executeS('SELECT t.*, m.member_no, CONCAT(c.firstname," ",c.lastname) guest FROM `'._DB_PREFIX_.'pulse_crm_points_txn` t
                INNER JOIN `'._DB_PREFIX_.'pulse_crm_member` m ON m.id_pulse_crm_member=t.id_pulse_crm_member
                LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=m.id_customer ORDER BY t.id_pulse_crm_points_txn DESC LIMIT 60'),
            'expiring' => Db::getInstance()->executeS('SELECT m.member_no, CONCAT(c.firstname," ",c.lastname) guest, SUM(t.points_remaining) points, MIN(t.expires_on) first_expiry
                FROM `'._DB_PREFIX_.'pulse_crm_points_txn` t INNER JOIN `'._DB_PREFIX_.'pulse_crm_member` m ON m.id_pulse_crm_member=t.id_pulse_crm_member
                LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=m.id_customer
                WHERE t.points_remaining>0 AND t.expires_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) GROUP BY m.id_pulse_crm_member ORDER BY first_expiry LIMIT 50'),
            'self_url' => $self, 'fd' => PulseCrmService::fd(),
        ));
        $this->setTemplate('loyalty.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveProgram')) {
                $rates = array();
                foreach ((array) Tools::getValue('rate') as $dept => $v) { $rates[preg_replace('/[^a-z_]/', '', $dept)] = (float) $v; }
                PulseCrmLoyalty::saveProgram(array('code' => Tools::getValue('code'), 'name' => Tools::getValue('name'), 'point_value' => Tools::getValue('point_value'),
                    'min_redeem_points' => Tools::getValue('min_redeem_points'), 'expiry_months' => Tools::getValue('expiry_months'),
                    'qualify_window_months' => Tools::getValue('qualify_window_months'), 'enrol_bonus' => Tools::getValue('enrol_bonus'),
                    'terms' => Tools::getValue('terms'), 'active' => (int) Tools::getValue('active', 1), 'earn_rate_json' => $rates), (int) Tools::getValue('id_program'));
                $this->confirmations[] = $this->l('Programme saved');
            }
            if (Tools::isSubmit('saveTier')) {
                PulseCrmLoyalty::saveTier(array('id_pulse_crm_loyalty_program' => (int) Tools::getValue('id_program'), 'code' => Tools::getValue('tcode'), 'name' => Tools::getValue('tname'),
                    'sort' => Tools::getValue('tsort'), 'min_nights' => Tools::getValue('min_nights'), 'min_stays' => Tools::getValue('min_stays'), 'min_spend' => Tools::getValue('min_spend'),
                    'earn_multiplier' => Tools::getValue('earn_multiplier'), 'benefits' => Tools::getValue('benefits'), 'colour' => Tools::getValue('colour')), (int) Tools::getValue('id_tier'));
                $this->confirmations[] = $this->l('Tier saved');
            }
            if (Tools::isSubmit('enrolGuest')) {
                $idc = (int) Tools::getValue('id_customer');
                if (!$idc && Tools::getValue('guest_email')) { $idc = (int) Db::getInstance()->getValue('SELECT id_customer FROM `'._DB_PREFIX_.'customer` WHERE email="'.pSQL(Tools::getValue('guest_email')).'" AND deleted=0'); }
                if (!$idc) { throw new PrestaShopException($this->l('Pick a guest first — search them on the Guests tab')); }
                $id = PulseCrmLoyalty::enrol($idc, 'desk');
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_member='.$id.'&conf=3');
            }
            if (Tools::isSubmit('adjustPoints')) { PulseCrmLoyalty::adjust((int) Tools::getValue('id_member_a'), (int) Tools::getValue('points'), Tools::getValue('reason')); $this->confirmations[] = $this->l('Points adjusted'); }
            if (Tools::isSubmit('redeemPoints')) { $r = PulseCrmLoyalty::redeem((int) Tools::getValue('id_member_a'), (int) Tools::getValue('points'), (int) Tools::getValue('id_htl_booking') ?: null, Tools::getValue('reason')); $this->confirmations[] = sprintf($this->l('%1$d points redeemed for %2$s'), $r['points'], Tools::displayPrice($r['value'])); }
            if (Tools::isSubmit('recalcTier')) { $t = PulseCrmLoyalty::recalcTier((int) Tools::getValue('id_member_a'), true); $this->confirmations[] = $t ? sprintf($this->l('Now %s'), $t) : $this->l('No tier change'); }
            if (Tools::isSubmit('recalcAll')) { $n = PulseCrmLoyalty::recalcTiersDue(500); $this->confirmations[] = sprintf($this->l('%d members re-tiered'), $n); }
            if (Tools::isSubmit('expireNow')) { $r = PulseCrmLoyalty::expirePoints(500); $this->confirmations[] = sprintf($this->l('%1$d points expired, %2$d members warned'), $r['expired'], $r['warned']); }
            if (Tools::isSubmit('setMemberStatus')) { Db::getInstance()->update('pulse_crm_member', array('status' => pSQL(Tools::getValue('status')), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_member='.(int) Tools::getValue('id_member_a')); $this->confirmations[] = $this->l('Membership updated'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
