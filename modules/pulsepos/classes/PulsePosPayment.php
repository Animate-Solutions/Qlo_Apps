<?php
/** Settlement: split tenders, room charge to folio, city ledger, vouchers, foreign currency, comps; posts settled checks to the outlet's house folio so Reports/Cashiering reconcile. */
class PulsePosPayment
{
    public static function pay($idCheck, $method, $amount, $emp, array $x = array())
    {
        $c = PulsePosService::get($idCheck); if (!$c || in_array($c['status'], array('settled', 'void'))) { throw new PrestaShopException('Check is not open'); }
        $s = PulsePosService::staff($emp); if (!$s || !$s['can_settle']) { throw new PrestaShopException('Not allowed to settle'); }
        $due = round($c['total'] - $c['paid'], 2); $amount = round((float) $amount, 2); $tip = round((float) (isset($x['tip']) ? $x['tip'] : 0), 2); $tendered = null; $row = array('id_pulse_pos_check' => (int) $idCheck, 'method' => pSQL($method), 'id_pulse_pos_session' => (int) ($c['id_pulse_pos_session'] ?: PulsePosService::currentSession($c['id_pulse_pos_outlet'])), 'id_employee' => (int) $emp, 'business_date' => pSQL($c['business_date']), 'date_add' => date('Y-m-d H:i:s'), 'reference' => pSQL(isset($x['reference']) ? $x['reference'] : ''), 'tip' => $tip);
        switch ($method) {
            case 'cash': $tendered = round((float) (isset($x['tendered']) ? $x['tendered'] : $amount), 2); $amount = min($amount, $due); $row['tendered'] = $tendered; break;
            case 'foreign': $rate = (float) $x['exchange_rate']; if ($rate <= 0) { throw new PrestaShopException('Exchange rate required'); } $row['currency_iso'] = pSQL($x['currency_iso']); $row['foreign_amount'] = (float) $x['foreign_amount']; $row['exchange_rate'] = $rate; $amount = min(round($x['foreign_amount'] * $rate, 2), $due); $tendered = round($x['foreign_amount'] * $rate, 2); break;
            case 'room':
                if (!$c['allow_room_charge']) { throw new PrestaShopException('Room charge not allowed in this outlet'); }
                $room = !empty($x['booking']) ? $x['booking'] : (isset($x['room_num']) && $x['room_num'] !== '' ? PulsePosService::findRoom($x['room_num']) : ($c['id_room'] ? PulsePosService::roomGuest($c['id_room']) : null)); if (!$room) { throw new PrestaShopException('No in-house guest for that room'); }
                if (!empty($x['guest_check']) && stripos($room['guest'], $x['guest_check']) === false) { throw new PrestaShopException('Guest name does not match room '.$room['room_num']); }
                if (!PulsePosService::fdOn()) { throw new PrestaShopException('Front Desk module not available for room charge'); }
                $f = PulseFolio::openForBooking($room['id_htl_booking']); if (!$f) { throw new PrestaShopException('Guest has no open folio'); }
                $amount = min($amount, $due); $net = $amount; $rate = $c['total'] > 0 ? $c['tax_total'] / $c['total'] : 0; $taxPct = $rate > 0 ? round($rate / (1 - $rate) * 100, 3) : 0;
                $line = $f->post('REST', $c['outlet_name'].' check '.$c['check_no'].($c['covers'] > 1 ? ' ('.$c['covers'].' covers)' : ''), 1, round($amount / (1 + $taxPct / 100), 2), $taxPct, false, null, 'pos', $c['check_no']);
                if ($tip > 0) { $f->post('MISC', 'Gratuity — '.$c['check_no'], 1, $tip, 0, false, null, 'pos', $c['check_no'].':tip'); }
                $row['id_room'] = (int) $room['id_room']; $row['id_pulse_folio'] = (int) $f->id; $row['folio_line'] = (int) $line; $row['reference'] = pSQL('Rm '.$room['room_num'].' '.$room['guest']); if (!empty($x['signature'])) { $row['signature_path'] = pSQL(self::storeSignature($idCheck, $x['signature'])); }
                Db::getInstance()->update('pulse_pos_check', array('id_room' => (int) $room['id_room'], 'id_htl_booking' => (int) $room['id_htl_booking'], 'id_customer' => (int) $room['id_customer']), 'id_pulse_pos_check='.(int) $idCheck);
                PulseCoreService::event('actionPulsePosRoomCharge', array('id_check' => $idCheck, 'id_room' => $room['id_room'], 'amount' => $amount)); break;
            case 'package':
                $room = isset($x['room_num']) && $x['room_num'] !== '' ? PulsePosService::findRoom($x['room_num']) : ($c['id_room'] ? PulsePosService::roomGuest($c['id_room']) : null); if (!$room) { throw new PrestaShopException('No in-house guest for that room'); }
                $meal = isset($x['meal']) && $x['meal'] ? $x['meal'] : PulsePosMealPlan::mealNow(); $allow = PulsePosMealPlan::allowance($room['id_htl_booking'], $meal, $c['business_date'], $c['id_pulse_pos_outlet']);
                if (!$allow || $allow['remaining'] <= 0) { throw new PrestaShopException('No '.$meal.' allowance left on this guest meal plan'); }
                $amount = min($due, (float) $allow['remaining']); PulsePosMealPlan::consume($allow['id'], $meal, $c['business_date'], (int) $c['covers'], $amount, $idCheck);
                $row['id_room'] = (int) $room['id_room']; $row['id_meal_plan'] = (int) $allow['id']; $row['reference'] = pSQL($allow['plan'].' '.$meal.' Rm '.$room['room_num']);
                Db::getInstance()->update('pulse_pos_check', array('id_room' => (int) $room['id_room'], 'id_htl_booking' => (int) $room['id_htl_booking'], 'id_customer' => (int) $room['id_customer']), 'id_pulse_pos_check='.(int) $idCheck); break;
            case 'city_ledger': if (!class_exists('PulseCompany')) { throw new PrestaShopException('Front Desk required'); } $co = new PulseCompany((int) $x['id_company']); if (!Validate::isLoadedObject($co)) { throw new PrestaShopException('Company not found'); } $amount = min($amount, $due); if ($co->credit_limit > 0 && $co->ledger_balance + $amount > $co->credit_limit) { throw new PrestaShopException('Credit limit exceeded for '.$co->name); } $cf = $co->folio(); $cf->post('REST', $c['outlet_name'].' check '.$c['check_no'], 1, $amount, 0, false, null, 'pos', $c['check_no']); $co->ledger_balance += $amount; $co->update(); $row['id_pulse_company'] = (int) $co->id; $row['reference'] = pSQL($co->name); break;
            case 'voucher': $v = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_voucher` WHERE code="'.pSQL($x['code']).'" AND active=1'); if (!$v || ($v['expires'] && $v['expires'] < date('Y-m-d'))) { throw new PrestaShopException('Voucher invalid or expired'); } $amount = min($amount, $due, (float) $v['balance']); Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pos_voucher` SET balance=balance-'.$amount.' WHERE id_pulse_pos_voucher='.(int) $v['id_pulse_pos_voucher']); $row['reference'] = pSQL($v['code']); break;
            case 'comp': $a = isset($x['auth']) ? PulsePosService::staff($x['auth']) : $s; if (!$a || !$a['can_comp']) { throw new PrestaShopException('Manager authorisation required'); } $amount = $due; $row['reference'] = pSQL('COMP: '.(isset($x['reason']) ? $x['reason'] : '')); break;
            default: $amount = min($amount, $due); // card, transfer, mobile_money, online
        }
        Db::getInstance()->insert('pulse_pos_payment', array_merge($row, array('amount' => $amount)));
        $idPay = (int) Db::getInstance()->Insert_ID();
        PulsePosService::audit($idCheck, 'payment', $method.(isset($x['reference']) ? ' '.$x['reference'] : ''), $amount, $emp);
        $total = PulsePosService::recalc($idCheck); $c = PulsePosService::get($idCheck, false);
        $change = $tendered !== null ? round($tendered - $amount, 2) : 0;
        if (round($c['paid'], 2) >= round($c['total'], 2) - 0.009) { self::settle($idCheck, $emp, $change); }
        return array('id_payment' => $idPay, 'paid' => $c['paid'], 'due' => round($c['total'] - $c['paid'], 2), 'change' => $change, 'settled' => round($c['paid'], 2) >= round($c['total'], 2) - 0.009);
    }

    /** Store a data-URL PNG signature under /upload/pulsepos/ and return its relative path. */
    public static function storeSignature($idCheck, $dataUrl)
    {
        if (!preg_match('#^data:image/png;base64,(.+)$#', $dataUrl, $m)) { return ''; }
        $dir = _PS_ROOT_DIR_.'/upload/pulsepos/'; if (!is_dir($dir)) { @mkdir($dir, 0755, true); @file_put_contents($dir.'index.php', '<?php header("Location: ../"); exit;'); }
        $name = 'sig_'.(int) $idCheck.'_'.time().'.png'; @file_put_contents($dir.$name, base64_decode($m[1]));
        return 'upload/pulsepos/'.$name;
    }

    protected static function settle($idCheck, $emp, $change)
    {
        $c = PulsePosService::get($idCheck);
        Db::getInstance()->update('pulse_pos_check', array('status' => 'settled', 'id_cashier' => (int) $emp, 'change_due' => (float) $change, 'date_settled' => date('Y-m-d H:i:s')), 'id_pulse_pos_check='.(int) $idCheck);
        if ($c['id_pulse_pos_table']) { Db::getInstance()->update('pulse_pos_table', array('status' => 'dirty', 'id_pulse_pos_check' => null), 'id_pulse_pos_table='.(int) $c['id_pulse_pos_table']); }
        foreach ($c['lines'] as $l) { if (!$l['voided'] && $l['kot_status'] === 'pending') { PulsePosInventory::consumeLine($l); } }
        self::postHouse($c);
        PulsePosService::audit($idCheck, 'settle', '', $c['total'], $emp);
        PulseCoreService::event('actionPulsePosBillSettled', array('id_check' => $idCheck, 'total' => $c['total'], 'outlet' => $c['outlet_code']));
    }

    /** Non-room revenue → outlet house folio (type house) as charge + payments, so every naira is in pulse_folio_line for reports and cashier reconciliation. */
    protected static function postHouse($c)
    {
        if (!PulsePosService::fdOn() || $c['posted_line']) { return; }
        $nonRoom = 0; foreach ($c['payments'] as $p) { if (!in_array($p['method'], array('room', 'city_ledger', 'package'))) { $nonRoom += (float) $p['amount']; } }
        if ($nonRoom <= 0) { return; }
        $id = (int) Db::getInstance()->getValue('SELECT id_pulse_folio FROM `'._DB_PREFIX_.'pulse_folio` WHERE type="house" AND status="open" AND folio_no="HOUSE-'.pSQL($c['outlet_code']).'"');
        if (!$id) { $f = new PulseFolio(); $f->folio_no = 'HOUSE-'.$c['outlet_code']; $f->type = 'house'; $f->add(); $id = (int) $f->id; } $f = new PulseFolio($id);
        $share = $c['total'] > 0 ? $nonRoom / $c['total'] : 1; $taxPart = $c['tax_total'] * $share; $net = $nonRoom - $taxPart; $taxPct = $net > 0 ? round($taxPart / $net * 100, 3) : 0;
        $line = $f->post('REST', $c['outlet_name'].' '.$c['check_no'].' ('.$c['order_type'].', '.$c['covers'].' cov)', 1, round($net, 2), $taxPct, false, null, 'pos', $c['check_no']);
        foreach ($c['payments'] as $p) { if (in_array($p['method'], array('room', 'city_ledger', 'package'))) { continue; } $code = array('cash' => 'CASH', 'card' => 'POS', 'transfer' => 'TRF', 'mobile_money' => 'TRF', 'online' => 'ONL', 'voucher' => 'ADJ', 'comp' => 'ADJ', 'foreign' => 'FX'); $cc = isset($code[$p['method']]) ? $code[$p['method']] : 'CASH'; if (!PulseChargeCode::byCode($cc)) { $cc = 'CASH'; } $f->post($cc, ucfirst($p['method']).' — '.$c['check_no'].($p['reference'] ? ' '.$p['reference'] : ''), 1, (float) $p['amount'], 0, true, $p['method'], 'pos', $c['check_no']); }
        Db::getInstance()->update('pulse_pos_check', array('posted_line' => (int) $line), 'id_pulse_pos_check='.(int) $c['id_pulse_pos_check']);
    }

    public static function reverse(array $p, $emp)
    {
        Db::getInstance()->update('pulse_pos_payment', array('voided' => 1), 'id_pulse_pos_payment='.(int) $p['id_pulse_pos_payment']);
        if ($p['method'] === 'room' && $p['id_pulse_folio'] && class_exists('PulseFolio')) { $f = new PulseFolio((int) $p['id_pulse_folio']); if ($f->status === 'open') { $f->voidLine((int) $p['folio_line'], 'POS check reopened'); } }
        if ($p['method'] === 'package') { Db::getInstance()->delete('pulse_pos_meal_plan_use', 'id_pulse_pos_check='.(int) $p['id_pulse_pos_check']); }
        if ($p['method'] === 'voucher') { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pos_voucher` SET balance=balance+'.(float) $p['amount'].' WHERE code="'.pSQL($p['reference']).'"'); }
        if ($p['method'] === 'city_ledger' && class_exists('PulseCompany')) { $co = new PulseCompany((int) $p['id_pulse_company']); $co->ledger_balance -= (float) $p['amount']; $co->update(); }
        PulsePosService::audit($p['id_pulse_pos_check'], 'payment_reverse', $p['method'], $p['amount'], $emp);
    }
    public static function refund($idCheck, $amount, $method, $reason, $emp, $auth) { $a = PulsePosService::staff($auth); if (!$a || !in_array($a['role'], array('manager', 'supervisor'))) { throw new PrestaShopException('Manager authorisation required for refunds'); } $c = PulsePosService::get($idCheck, false); Db::getInstance()->insert('pulse_pos_payment', array('id_pulse_pos_check' => (int) $idCheck, 'method' => pSQL($method), 'amount' => -(float) $amount, 'reference' => pSQL('REFUND: '.$reason), 'id_pulse_pos_session' => (int) PulsePosService::currentSession($c['id_pulse_pos_outlet']), 'id_employee' => (int) $emp, 'business_date' => PulsePosService::bd(), 'date_add' => date('Y-m-d H:i:s'))); PulsePosService::audit($idCheck, 'refund', $reason, $amount, $emp, $auth); if (PulsePosService::fdOn()) { $id = (int) Db::getInstance()->getValue('SELECT id_pulse_folio FROM `'._DB_PREFIX_.'pulse_folio` WHERE type="house" AND status="open" AND folio_no="HOUSE-'.pSQL($c['outlet_code']).'"'); if ($id) { $f = new PulseFolio($id); $f->post('ADJ', 'Refund '.$c['check_no'].' — '.$reason, 1, -(float) $amount, 0, false, null, 'pos', $c['check_no'].':refund'); } } return true; }
}
