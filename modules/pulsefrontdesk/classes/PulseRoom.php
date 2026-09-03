<?php
/** Room status board queries and HK/FO status transitions. Wraps QloApps htl_room_information. */
class PulseRoom
{
    const HK_STATUSES = array('vacant_clean', 'vacant_dirty', 'vacant_inspected', 'occupied_clean', 'occupied_dirty', 'out_of_order', 'out_of_service');

    /** Make sure every QloApps room has a status row. */
    public static function syncRooms()
    {
        Db::getInstance()->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_room_status` (id_room, hk_status, fo_status, date_upd)
            SELECT id, "vacant_clean", "vacant", NOW() FROM `'._DB_PREFIX_.'htl_room_information`');
    }

    /** Full board: one row per room with type, floor, statuses, current/next booking. */
    public static function board($idHotel = null, $date = null)
    {
        $date = $date ? pSQL($date) : PulseCoreService::businessDate();
        self::syncRooms();
        return Db::getInstance()->executeS('
            SELECT r.id id_room, r.room_num, r.floor, r.id_hotel, r.id_product, pl.name room_type,
                   s.hk_status, s.fo_status, s.ooo_reason, s.ooo_until, s.note,
                   b.id id_htl_booking, b.id_order, b.id_customer, b.date_from, b.date_to, b.id_status booking_status, b.adults, b.children,
                   CONCAT(c.firstname," ",c.lastname) guest, gp.vip_level,
                   (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_housekeeping_task` t WHERE t.id_room=r.id AND t.status IN ("open","in_progress")) open_tasks,
                   (SELECT balance FROM `'._DB_PREFIX_.'pulse_folio` f WHERE f.id_htl_booking=b.id AND f.status="open" LIMIT 1) balance
            FROM `'._DB_PREFIX_.'htl_room_information` r
            INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=r.id_product AND pl.id_lang='.(int) Context::getContext()->language->id.' AND pl.id_shop='.(int) Context::getContext()->shop->id.'
            LEFT JOIN `'._DB_PREFIX_.'pulse_room_status` s ON s.id_room=r.id
            LEFT JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id_room=r.id AND b.is_refunded=0 AND b.is_cancelled=0
                 AND b.date_from<="'.$date.'" AND b.date_to>"'.$date.'" AND b.id_status<>'.(int) HotelBookingDetail::STATUS_CHECKED_OUT.'
            LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer
            LEFT JOIN `'._DB_PREFIX_.'pulse_guest_profile` gp ON gp.id_customer=b.id_customer
            WHERE r.id_status=1'.($idHotel ? ' AND r.id_hotel='.(int) $idHotel : '').'
            ORDER BY r.id_hotel, r.floor, r.room_num');
    }

    public static function setHkStatus($idRoom, $status, $source = 'manual', $reason = null, $until = null)
    {
        if (!in_array($status, self::HK_STATUSES)) { throw new PrestaShopException('Bad HK status '.$status); }
        $from = Db::getInstance()->getValue('SELECT hk_status FROM `'._DB_PREFIX_.'pulse_room_status` WHERE id_room='.(int) $idRoom);
        $ctx = Context::getContext(); $emp = isset($ctx->employee) ? (int) $ctx->employee->id : 0;
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_room_status` (id_room,hk_status,ooo_reason,ooo_until,id_employee,date_upd) VALUES ('.(int) $idRoom.',"'.pSQL($status).'",'.($reason ? '"'.pSQL($reason).'"' : 'NULL').','.($until ? '"'.pSQL($until).'"' : 'NULL').','.$emp.',NOW())
            ON DUPLICATE KEY UPDATE hk_status=VALUES(hk_status), ooo_reason=VALUES(ooo_reason), ooo_until=VALUES(ooo_until), id_employee=VALUES(id_employee), date_upd=NOW()');
        Db::getInstance()->insert('pulse_room_status_log', array('id_room' => (int) $idRoom, 'from_status' => pSQL($from), 'to_status' => pSQL($status), 'id_employee' => $emp, 'source' => pSQL($source), 'date_add' => date('Y-m-d H:i:s')));
        // OOO/OOS rooms should not be sellable in QloApps: mark temporarily inactive (id_status 3) and restore on return
        $qloStatus = in_array($status, array('out_of_order', 'out_of_service')) ? 3 : 1;
        Db::getInstance()->update('htl_room_information', array('id_status' => $qloStatus), 'id='.(int) $idRoom);
        PulseCoreService::event('actionPulseRoomStatusChange', array('id_room' => $idRoom, 'from' => $from, 'to' => $status));
        return true;
    }

    public static function setFoStatus($idRoom, $fo, $idBooking = null)
    {
        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_room_status` (id_room,fo_status,id_htl_booking,date_upd) VALUES ('.(int) $idRoom.',"'.pSQL($fo).'",'.($idBooking ? (int) $idBooking : 'NULL').',NOW())
            ON DUPLICATE KEY UPDATE fo_status=VALUES(fo_status), id_htl_booking=VALUES(id_htl_booking), date_upd=NOW()');
    }

    /**
     * Smart auto-assignment. Scores free rooms of the type for a guest:
     * inspected > clean; preferred floor / same room as last stay; VIP → higher floor; long stays avoid rooms with open maintenance; smoking pref.
     * Returns id_room or 0.
     */
    public static function autoAssign($idProduct, $from, $to, $idCustomer = 0, $excludeBooking = 0)
    {
        $free = self::availableRooms($idProduct, $from, $to, $excludeBooking);
        if (!$free) { return 0; }
        $prof = $idCustomer ? PulseGuestProfile::get($idCustomer) : array('preferences' => array(), 'vip_level' => 0, 'history' => array());
        $pref = $prof['preferences']; $lastRoom = null;
        foreach ($prof['history'] as $h) { if ($h['id_status'] == 3) { $lastRoom = $h['room_num']; break; } }
        $nights = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400));
        $maxFloor = (int) Db::getInstance()->getValue('SELECT MAX(floor) FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_product='.(int) $idProduct);
        $best = null; $bestScore = -1e9;
        foreach ($free as $r) {
            $score = 0;
            if ($r['hk_status'] === 'vacant_inspected') { $score += 30; } elseif ($r['hk_status'] === 'vacant_clean') { $score += 20; }
            if (!empty($pref['floor'])) { if (stripos($pref['floor'], 'high') !== false) { $score += (int) $r['floor'] * 3; } elseif (stripos($pref['floor'], 'low') !== false || stripos($pref['floor'], 'ground') !== false) { $score -= (int) $r['floor'] * 3; } elseif ((string) $r['floor'] === (string) $pref['floor']) { $score += 25; } }
            if ($prof['vip_level'] > 0) { $score += (int) $r['floor'] * 2 + $prof['vip_level'] * 5; }
            if ($lastRoom && $r['room_num'] == $lastRoom) { $score += 15; }
            $maint = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_housekeeping_task` WHERE id_room='.(int) $r['id_room'].' AND type="maintenance" AND status IN ("open","in_progress")');
            if ($maint) { $score -= $nights >= 3 ? 40 : 10; }
            if (!empty($pref['smoking']) && stripos($pref['smoking'], 'no') === false) { $score += 0; } // placeholder for smoking-floor attribute when rooms carry it
            // keep the highest floors for VIPs: penalise giving top floor to non-VIP on short stays
            if ($prof['vip_level'] == 0 && $maxFloor && (int) $r['floor'] == $maxFloor && $nights == 1) { $score -= 5; }
            if ($score > $bestScore) { $bestScore = $score; $best = (int) $r['id_room']; }
        }
        return $best;
    }

    /** Rooms of a type that are free (no overlapping booking) and clean for a date range — used for room assignment & moves. */
    public static function availableRooms($idProduct, $from, $to, $excludeBooking = 0)
    {
        return Db::getInstance()->executeS('SELECT r.id id_room, r.room_num, r.floor, s.hk_status FROM `'._DB_PREFIX_.'htl_room_information` r
            LEFT JOIN `'._DB_PREFIX_.'pulse_room_status` s ON s.id_room=r.id
            WHERE r.id_product='.(int) $idProduct.' AND r.id_status=1
              AND (s.hk_status IS NULL OR s.hk_status NOT IN ("out_of_order","out_of_service"))
              AND r.id NOT IN (SELECT id_room FROM `'._DB_PREFIX_.'htl_booking_detail` WHERE is_refunded=0 AND is_cancelled=0 AND id<>'.(int) $excludeBooking.'
                  AND id_status<>'.(int) HotelBookingDetail::STATUS_CHECKED_OUT.' AND date_from<"'.pSQL($to).'" AND date_to>"'.pSQL($from).'")
            ORDER BY FIELD(s.hk_status,"vacant_inspected","vacant_clean"), r.floor, r.room_num');
    }
}
