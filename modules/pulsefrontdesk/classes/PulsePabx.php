<?php
class PulsePabx
{
    public static function driver()
    {
        $cls = Configuration::get('PULSE_FD_PABX_DRIVER') ?: 'PulsePabxGeneric';
        return class_exists($cls) ? new $cls(array('bridge_url' => Configuration::get('PULSE_FD_PABX_URL'), 'key' => Configuration::get('PULSE_FD_PABX_KEY'))) : null;
    }
    public static function extensionForRoom($idRoom)
    {
        $map = json_decode(Configuration::get('PULSE_FD_PABX_MAP') ?: '{}', true);
        $num = Db::getInstance()->getValue('SELECT room_num FROM `'._DB_PREFIX_.'htl_room_information` WHERE id='.(int) $idRoom);
        return isset($map[$num]) ? $map[$num] : $num; // default: extension == room number
    }
    public static function roomForExtension($ext)
    {
        $map = json_decode(Configuration::get('PULSE_FD_PABX_MAP') ?: '{}', true);
        $num = ($k = array_search($ext, $map)) !== false ? $k : $ext;
        return (int) Db::getInstance()->getValue('SELECT id FROM `'._DB_PREFIX_.'htl_room_information` WHERE room_num="'.pSQL($num).'"');
    }
    /** Inbound CDR: post telephone charge to the in-house folio. $cost in shop currency tax-excl. */
    public static function callRecord($ext, $number, $durationSec, $cost)
    {
        $idRoom = self::roomForExtension($ext);
        Db::getInstance()->insert('pulse_pabx_log', array('extension' => pSQL($ext), 'id_room' => $idRoom ?: null, 'event' => 'call', 'payload' => pSQL($number), 'duration_sec' => (int) $durationSec, 'cost' => (float) $cost, 'date_add' => date('Y-m-d H:i:s')));
        $idLog = (int) Db::getInstance()->Insert_ID();
        if ($idRoom && $cost > 0) {
            $b = Db::getInstance()->getRow('SELECT id FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE id_room='.$idRoom.' AND id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND is_cancelled=0');
            if ($b) { $f = PulseFolio::ensureForBooking(PulseFdService::booking($b['id'])); $f->post('TELE', 'Call to '.$number.' ('.gmdate('i:s', $durationSec).')', 1, (float) $cost, null, false, null, 'pabx', 'call:'.$idLog); Db::getInstance()->update('pulse_pabx_log', array('posted' => 1), 'id_pulse_pabx_log='.$idLog); }
        }
        return $idLog;
    }
    /** Inbound HK dial code: e.g. *1 clean, *2 dirty, *3 inspected, *4 OOO. Configurable via PULSE_FD_PABX_CODES JSON. */
    public static function statusCode($ext, $code)
    {
        $codes = json_decode(Configuration::get('PULSE_FD_PABX_CODES') ?: '{"1":"vacant_clean","2":"vacant_dirty","3":"vacant_inspected","4":"out_of_order","5":"occupied_clean","6":"occupied_dirty"}', true);
        $idRoom = self::roomForExtension($ext);
        Db::getInstance()->insert('pulse_pabx_log', array('extension' => pSQL($ext), 'id_room' => $idRoom ?: null, 'event' => 'status_code', 'payload' => pSQL($code), 'date_add' => date('Y-m-d H:i:s')));
        if ($idRoom && isset($codes[$code])) { PulseRoom::setHkStatus($idRoom, $codes[$code], 'pabx'); return $codes[$code]; }
        return null;
    }
}
