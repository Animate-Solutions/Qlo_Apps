<?php
/** Guest meal plans (BB/HB/FB/AI): allowance per meal per person per day, consumed at settlement with the "package" tender. */
class PulsePosMealPlan
{
    public static function mealNow() { $h = (int) date('G'); return $h < 11 ? 'breakfast' : ($h < 16 ? 'lunch' : 'dinner'); }
    public static function forBooking($idBooking) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_meal_plan` WHERE active=1 AND id_htl_booking='.(int) $idBooking); }
    public static function allowance($idBooking, $meal, $bd, $idOutlet = null)
    {
        $p = self::forBooking($idBooking); if (!$p || !in_array($meal, array('breakfast', 'lunch', 'dinner'))) { return null; }
        if ($p['outlets'] && $idOutlet && !in_array((int) $idOutlet, array_map('intval', explode(',', $p['outlets'])))) { return null; }
        $per = (float) $p[$meal]; if ($per <= 0) { return null; }
        $used = (float) Db::getInstance()->getValue('SELECT COALESCE(SUM(amount),0) FROM `'._DB_PREFIX_.'pulse_pos_meal_plan_use` WHERE id_pulse_pos_meal_plan='.(int) $p['id_pulse_pos_meal_plan'].' AND meal="'.pSQL($meal).'" AND business_date="'.pSQL($bd).'"');
        return array('id' => (int) $p['id_pulse_pos_meal_plan'], 'plan' => $p['plan'], 'meal' => $meal, 'per_person' => $per, 'persons' => (int) $p['persons'], 'total' => $per * $p['persons'], 'used' => $used, 'remaining' => max(0, $per * $p['persons'] - $used));
    }
    public static function consume($idPlan, $meal, $bd, $persons, $amount, $idCheck) { Db::getInstance()->insert('pulse_pos_meal_plan_use', array('id_pulse_pos_meal_plan' => (int) $idPlan, 'meal' => pSQL($meal), 'business_date' => pSQL($bd), 'persons' => (int) $persons, 'amount' => (float) $amount, 'id_pulse_pos_check' => (int) $idCheck)); }
    public static function save($idBooking, array $d)
    {
        Db::getInstance()->update('pulse_pos_meal_plan', array('active' => 0), 'id_htl_booking='.(int) $idBooking);
        $pre = array('BB' => array(5000, 0, 0), 'HB' => array(5000, 0, 8000), 'FB' => array(5000, 7000, 8000), 'AI' => array(5000, 7000, 8000)); $v = isset($pre[$d['plan']]) ? $pre[$d['plan']] : array(0, 0, 0);
        return Db::getInstance()->insert('pulse_pos_meal_plan', array('id_htl_booking' => (int) $idBooking, 'plan' => pSQL($d['plan']), 'persons' => max(1, (int) $d['persons']), 'breakfast' => isset($d['breakfast']) && $d['breakfast'] !== '' ? (float) $d['breakfast'] : $v[0], 'lunch' => isset($d['lunch']) && $d['lunch'] !== '' ? (float) $d['lunch'] : $v[1], 'dinner' => isset($d['dinner']) && $d['dinner'] !== '' ? (float) $d['dinner'] : $v[2], 'outlets' => !empty($d['outlets']) ? pSQL(implode(',', (array) $d['outlets'])) : null, 'note' => pSQL(isset($d['note']) ? $d['note'] : ''), 'date_add' => date('Y-m-d H:i:s')));
    }
    public static function inHouse() { return Db::getInstance()->executeS('SELECT p.*, b.room_num, CONCAT(c.firstname," ",c.lastname) guest, b.date_from, b.date_to FROM `'._DB_PREFIX_.'pulse_pos_meal_plan` p INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=p.id_htl_booking INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer WHERE p.active=1 AND b.id_status<>3 AND b.is_cancelled=0 ORDER BY b.room_num'); }
}
