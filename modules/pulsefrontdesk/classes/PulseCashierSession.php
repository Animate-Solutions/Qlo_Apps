<?php
class PulseCashierSession
{
    public static function currentId($idEmployee)
    {
        return (int) Db::getInstance()->getValue('SELECT id_pulse_cashier_session FROM `'._DB_PREFIX_.'pulse_cashier_session` WHERE id_employee='.(int) $idEmployee.' AND status="open"');
    }
    public static function open($idEmployee, $float)
    {
        if (self::currentId($idEmployee)) { throw new PrestaShopException('A cashier session is already open for this user'); }
        Db::getInstance()->insert('pulse_cashier_session', array('id_employee' => (int) $idEmployee, 'opening_float' => (float) $float, 'business_date' => PulseCoreService::businessDate(), 'date_open' => date('Y-m-d H:i:s')));
        return (int) Db::getInstance()->Insert_ID();
    }
    /** Totals by payment method for a session. */
    public static function totals($idSession)
    {
        return Db::getInstance()->executeS('SELECT payment_method, SUM(amount_tax_incl) total, COUNT(*) n FROM `'._DB_PREFIX_.'pulse_folio_line` WHERE is_payment=1 AND voided=0 AND id_cashier_session='.(int) $idSession.' GROUP BY payment_method');
    }
    public static function close($idSession, $countedCash, $note = '')
    {
        $s = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_cashier_session` WHERE id_pulse_cashier_session='.(int) $idSession);
        if (!$s || $s['status'] !== 'open') { return false; }
        $cash = 0;
        foreach (self::totals($idSession) as $t) { if ($t['payment_method'] === 'cash') { $cash = (float) $t['total']; } }
        $mv = Db::getInstance()->getRow('SELECT COALESCE(SUM(IF(type IN ("blind_drop","paid_out","float_out"),amount,0)),0) outs, COALESCE(SUM(IF(type IN ("float_in","correction"),amount,0)),0) ins FROM `'._DB_PREFIX_.'pulse_drawer_movement` WHERE id_pulse_cashier_session='.(int) $idSession);
        $expected = (float) $s['opening_float'] + $cash - (float) $mv['outs'] + (float) $mv['ins'];
        Db::getInstance()->update('pulse_cashier_session', array(
            'expected_cash' => $expected, 'counted_cash' => (float) $countedCash, 'variance' => round($countedCash - $expected, 2),
            'status' => 'closed', 'note' => pSQL($note), 'date_close' => date('Y-m-d H:i:s'),
        ), 'id_pulse_cashier_session='.(int) $idSession);
        PulseCoreService::audit('pulsefrontdesk', 'cashier_close', array('session' => $idSession, 'expected' => $expected, 'counted' => $countedCash));
        return true;
    }

    /** Blind drop / paid-out / float movement with optional witness (second employee id). */
    public static function movement($idSession, $type, $amount, $note = '', $witness = null)
    {
        if (!in_array($type, array('blind_drop', 'paid_out', 'float_in', 'float_out', 'correction'))) { throw new PrestaShopException('Bad movement type'); }
        Db::getInstance()->insert('pulse_drawer_movement', array('id_pulse_cashier_session' => (int) $idSession, 'type' => pSQL($type), 'amount' => (float) $amount, 'note' => pSQL($note), 'id_employee' => (int) Context::getContext()->employee->id, 'witness' => $witness ? (int) $witness : null, 'date_add' => date('Y-m-d H:i:s')));
        PulseCoreService::audit('pulsefrontdesk', 'drawer_'.$type, array('session' => $idSession, 'amount' => $amount, 'witness' => $witness));
        return (int) Db::getInstance()->Insert_ID();
    }
    public static function movements($idSession) { return Db::getInstance()->executeS('SELECT m.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_drawer_movement` m LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=m.id_employee WHERE m.id_pulse_cashier_session='.(int) $idSession.' ORDER BY m.date_add'); }
}
