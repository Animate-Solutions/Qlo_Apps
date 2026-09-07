<?php
/** Kitchen tickets: KDS queue + ESC/POS network printing per station. */
class PulsePosKitchen
{
    public static function ticket($idCheck, $kot, array $lines)
    {
        $c = PulsePosService::get($idCheck, false); $byStation = array(); foreach ($lines as $l) { $byStation[(int) $l['id_pulse_pos_station']][] = $l; }
        foreach ($byStation as $idSt => $ls) { $st = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_station` WHERE id_pulse_pos_station='.(int) $idSt); if ($st && $st['printer_host']) { PulsePosPrinter::kot($c, $kot, $ls, $st); } }
        return true;
    }
    public static function notify($idCheck, $event, array $l) { $st = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_station` WHERE id_pulse_pos_station='.(int) $l['id_pulse_pos_station']); if ($st && $st['printer_host']) { $c = PulsePosService::get($idCheck, false); PulsePosPrinter::raw($st['printer_host'], $st['printer_port'], PulsePosPrinter::esc("*** VOID ***\n".$c['check_no'].' '.($c['table_code'] ?: $c['order_type'])."\n".$l['qty'].' x '.$l['name']."\n".date('H:i')."\n", true)); } }
    /** KDS queue for a station (or all). */
    public static function queue($idStation = null)
    {
        $rows = Db::getInstance()->executeS('SELECT l.*, c.check_no, c.table_code, c.order_type, c.covers, c.guest_name, r.room_num, s.code station, TIMESTAMPDIFF(MINUTE, l.fired_at, NOW()) age, i.prep_minutes FROM `'._DB_PREFIX_.'pulse_pos_check_line` l INNER JOIN `'._DB_PREFIX_.'pulse_pos_check` c ON c.id_pulse_pos_check=l.id_pulse_pos_check LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=c.id_room LEFT JOIN `'._DB_PREFIX_.'pulse_pos_station` s ON s.id_pulse_pos_station=l.id_pulse_pos_station LEFT JOIN `'._DB_PREFIX_.'pulse_pos_item` i ON i.id_pulse_pos_item=l.id_pulse_pos_item WHERE l.voided=0 AND l.kot_status IN ("fired","preparing","ready")'.($idStation ? ' AND l.id_pulse_pos_station='.(int) $idStation : '').' ORDER BY l.kot_no, l.fired_at');
        $t = array(); foreach ($rows as $r) { $k = $r['kot_no'].'|'.$r['id_pulse_pos_check']; if (!isset($t[$k])) { $t[$k] = array('kot' => $r['kot_no'], 'check' => $r['check_no'], 'where' => $r['room_num'] ? 'Rm '.$r['room_num'] : ($r['table_code'] ?: $r['order_type']), 'covers' => $r['covers'], 'fired_at' => $r['fired_at'], 'age' => (int) $r['age'], 'late' => false, 'lines' => array()); } $r['modifiers'] = $r['modifiers'] ? json_decode($r['modifiers'], true) : array(); $t[$k]['lines'][] = $r; if ($r['age'] > $r['prep_minutes']) { $t[$k]['late'] = true; } }
        return array_values($t);
    }
    public static function bump($idLine, $status) { $u = array('kot_status' => pSQL($status)); if ($status === 'ready') { $u['ready_at'] = date('Y-m-d H:i:s'); } if ($status === 'served') { $u['served_at'] = date('Y-m-d H:i:s'); } Db::getInstance()->update('pulse_pos_check_line', $u, 'id_pulse_pos_check_line='.(int) $idLine); if ($status === 'ready') { $l = Db::getInstance()->getRow('SELECT id_pulse_pos_check FROM `'._DB_PREFIX_.'pulse_pos_check_line` WHERE id_pulse_pos_check_line='.(int) $idLine); PulseCoreService::event('actionPulsePosItemReady', array('id_check' => $l['id_pulse_pos_check'], 'id_line' => $idLine)); } return true; }
    public static function bumpTicket($kot, $idCheck, $status) { foreach (Db::getInstance()->executeS('SELECT id_pulse_pos_check_line FROM `'._DB_PREFIX_.'pulse_pos_check_line` WHERE kot_no="'.pSQL($kot).'" AND id_pulse_pos_check='.(int) $idCheck.' AND voided=0 AND kot_status IN ("fired","preparing","ready")') as $l) { self::bump($l['id_pulse_pos_check_line'], $status); } return true; }
}
