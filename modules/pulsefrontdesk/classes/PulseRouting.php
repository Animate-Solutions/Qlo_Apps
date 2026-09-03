<?php
/** Charge routing rules: which folio a department's charges land on (guest / company / group master). */
class PulseRouting
{
    public static function add($scope, $idScope, $department, $target, $idTargetFolio = null)
    {
        return Db::getInstance()->insert('pulse_routing_rule', array('scope' => pSQL($scope), 'id_scope' => (int) $idScope, 'department' => pSQL($department), 'target' => pSQL($target), 'id_target_folio' => $idTargetFolio ? (int) $idTargetFolio : null, 'date_add' => date('Y-m-d H:i:s')));
    }

    /** Resolve the destination folio for a posting on a guest folio. Returns PulseFolio (may be the same one). */
    public static function resolve(PulseFolio $guestFolio, $department)
    {
        if ($guestFolio->type !== 'guest' || !$guestFolio->id_htl_booking) { return $guestFolio; }
        $b = Db::getInstance()->getRow('SELECT b.id_customer, x.id_pulse_group_block, gp.id_pulse_company FROM `'._DB_PREFIX_.'htl_booking_detail` b LEFT JOIN `'._DB_PREFIX_.'pulse_booking_ext` x ON x.id_htl_booking=b.id LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=b.id_customer WHERE b.id='.(int) $guestFolio->id_htl_booking);
        $where = '(scope="booking" AND id_scope='.(int) $guestFolio->id_htl_booking.')';
        if (!empty($b['id_pulse_group_block'])) { $where .= ' OR (scope="group" AND id_scope='.(int) $b['id_pulse_group_block'].')'; }
        if (!empty($b['id_pulse_company'])) { $where .= ' OR (scope="company" AND id_scope='.(int) $b['id_pulse_company'].')'; }
        $rule = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_routing_rule` WHERE active=1 AND ('.$where.') AND (department="'.pSQL($department).'" OR department="*") ORDER BY FIELD(scope,"booking","group","company"), FIELD(department,"'.pSQL($department).'","*") LIMIT 1');
        if (!$rule || $rule['target'] === 'guest') { return $guestFolio; }
        if ($rule['id_target_folio']) { $f = new PulseFolio((int) $rule['id_target_folio']); if ($f->status === 'open') { return $f; } }
        if ($rule['target'] === 'company' && !empty($b['id_pulse_company'])) { return (new PulseCompany((int) $b['id_pulse_company']))->folio(); }
        if ($rule['target'] === 'master' && !empty($b['id_pulse_group_block'])) { return (new PulseGroupBlock((int) $b['id_pulse_group_block']))->masterFolio(); }
        return $guestFolio;
    }

    public static function forBooking($idBooking) { return Db::getInstance()->executeS('SELECT r.*, f.folio_no FROM `'._DB_PREFIX_.'pulse_routing_rule` r LEFT JOIN `'._DB_PREFIX_.'pulse_folio` f ON f.id_pulse_folio=r.id_target_folio WHERE r.scope="booking" AND r.id_scope='.(int) $idBooking.' AND r.active=1'); }
}
