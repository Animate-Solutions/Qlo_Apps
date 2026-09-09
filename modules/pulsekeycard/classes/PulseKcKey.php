<?php
/**
 * Key lifecycle: issue, re-issue, duplicate, extend, cancel — for guest, staff, master, common-door,
 * one-shot and emergency cards. The encoder call is the only part that can fail; when it does the row is
 * kept as `failed`, a retry job is queued and the desk is told to hand over a mechanical key.
 */
class PulseKcKey
{
    const T = 'pulse_kc_key';

    /* ---------- reads ---------- */

    public static function get($id)
    {
        return Db::getInstance()->getRow('SELECT k.*, e.name encoder_name, g.name staff_group, CONCAT(iss.firstname," ",iss.lastname) issued_by_name, CONCAT(hol.firstname," ",hol.lastname) holder_name
            FROM `'._DB_PREFIX_.self::T.'` k
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_encoder` e ON e.id_pulse_kc_encoder=k.id_pulse_kc_encoder
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_staff_group` g ON g.id_pulse_kc_staff_group=k.id_pulse_kc_staff_group
            LEFT JOIN `'._DB_PREFIX_.'employee` iss ON iss.id_employee=k.issued_by
            LEFT JOIN `'._DB_PREFIX_.'employee` hol ON hol.id_employee=k.id_employee_holder
            WHERE k.id_pulse_kc_key='.(int) $id);
    }

    /** Filtered register. $f: status, type, id_room, id_htl_booking, q (key no / serial / guest), from, to. */
    public static function search(array $f = array(), $limit = 200)
    {
        $w = ' WHERE 1';
        if (!empty($f['status'])) { $w .= ' AND k.status IN ("'.implode('","', array_map('pSQL', explode(',', $f['status']))).'")'; }
        if (!empty($f['type'])) { $w .= ' AND k.type="'.pSQL($f['type']).'"'; }
        if (!empty($f['id_room'])) { $w .= ' AND (k.id_room='.(int) $f['id_room'].' OR FIND_IN_SET('.(int) $f['id_room'].', k.id_rooms))'; }
        if (!empty($f['id_htl_booking'])) { $w .= ' AND k.id_htl_booking='.(int) $f['id_htl_booking']; }
        if (!empty($f['id_employee_holder'])) { $w .= ' AND k.id_employee_holder='.(int) $f['id_employee_holder']; }
        if (!empty($f['from'])) { $w .= ' AND k.business_date>="'.pSQL($f['from']).'"'; }
        if (!empty($f['to'])) { $w .= ' AND k.business_date<="'.pSQL($f['to']).'"'; }
        if (!empty($f['q'])) { $q = pSQL($f['q']); $w .= ' AND (k.key_no LIKE "%'.$q.'%" OR k.card_serial LIKE "%'.$q.'%" OR k.guest_name LIKE "%'.$q.'%" OR k.room_nums LIKE "%'.$q.'%")'; }
        return Db::getInstance()->executeS('SELECT k.id_pulse_kc_key, k.key_no, k.type, k.status, k.room_nums, k.guest_name, k.card_serial, k.sequence, k.valid_from, k.valid_to,
                k.mobile, k.mechanical, k.adapter, k.id_htl_booking, k.id_room, k.issued_at, k.cancelled_at, k.cancel_reason, k.last_error, k.business_date,
                e.name encoder_name, g.name staff_group, CONCAT(emp.firstname," ",emp.lastname) issued_by_name, CONCAT(hol.firstname," ",hol.lastname) holder_name
            FROM `'._DB_PREFIX_.self::T.'` k
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_encoder` e ON e.id_pulse_kc_encoder=k.id_pulse_kc_encoder
            LEFT JOIN `'._DB_PREFIX_.'pulse_kc_staff_group` g ON g.id_pulse_kc_staff_group=k.id_pulse_kc_staff_group
            LEFT JOIN `'._DB_PREFIX_.'employee` emp ON emp.id_employee=k.issued_by
            LEFT JOIN `'._DB_PREFIX_.'employee` hol ON hol.id_employee=k.id_employee_holder'.$w.'
            ORDER BY k.id_pulse_kc_key DESC LIMIT '.(int) $limit);
    }

    public static function forBooking($idBooking, $status = 'issued,pending,failed')
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_htl_booking='.(int) $idBooking.' AND status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'") ORDER BY id_pulse_kc_key DESC');
    }
    public static function forRoom($idRoom, $status = 'issued')
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE (id_room='.(int) $idRoom.' OR FIND_IN_SET('.(int) $idRoom.', id_rooms)) AND status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'") ORDER BY id_pulse_kc_key DESC');
    }

    /* ---------- issue ---------- */

    /**
     * Cut a key. $d keys:
     *  type (guest|duplicate|one_shot|staff|master|common|emergency), id_htl_booking, id_customer, guest_name,
     *  rooms (array of id_room), doors (array of id_pulse_kc_door), valid_from, valid_to,
     *  override_deadbolt, override_dnd, id_encoder, id_employee_holder, id_pulse_kc_staff_group, all_rooms,
     *  mobile (bool — the credential is issued but no card is cut), note, id_parent_key.
     * Throws PulseKcEncoderException when the encoder is unreachable; the row survives as `failed` with a retry job.
     */
    public static function issue(array $d)
    {
        $type = isset($d['type']) ? $d['type'] : 'guest';
        $rooms = array_values(array_filter(array_map('intval', (array) (isset($d['rooms']) ? $d['rooms'] : array()))));
        $doors = array_values(array_filter(array_map('intval', (array) (isset($d['doors']) ? $d['doors'] : PulseKcService::defaultDoorIds()))));
        $allRooms = !empty($d['all_rooms']);
        if (!$rooms && !$doors && !$allRooms) { throw new PrestaShopException('A key must open at least one room or door'); }

        $booking = null;
        if (!empty($d['id_htl_booking']) && PulseKcService::fd()) { $booking = PulseFdService::booking((int) $d['id_htl_booking']); }
        if ($booking && !$rooms) { $rooms = array((int) $booking['id_room']); }
        if (empty($d['valid_from']) || empty($d['valid_to'])) {
            if ($booking) { list($d['valid_from'], $d['valid_to']) = PulseKcService::window($booking['date_from'], $booking['date_to']); }
            else { $d['valid_from'] = date('Y-m-d H:i:s'); $d['valid_to'] = date('Y-m-d H:i:s', time() + 12 * 3600); }
        }
        if (strtotime($d['valid_to']) <= strtotime($d['valid_from'])) { throw new PrestaShopException('Key expiry must be after its start'); }

        $roomNums = self::roomNumbers($rooms);
        $encoder = PulseKcEncoder::pick(isset($d['id_encoder']) ? (int) $d['id_encoder'] : null);
        $seq = PulseKcService::nextSequence($roomNums ? $roomNums : array('COMMON'));
        $keyNo = PulseKcService::nextNo($type === 'staff' || $type === 'master' ? 'S' : 'K');
        $guest = isset($d['guest_name']) ? $d['guest_name'] : ($booking ? $booking['guest'] : '');

        $row = array(
            'key_no' => pSQL($keyNo), 'type' => pSQL($type),
            'id_htl_booking' => $booking ? (int) $booking['id'] : (!empty($d['id_htl_booking']) ? (int) $d['id_htl_booking'] : null),
            'id_customer' => $booking ? (int) $booking['id_customer'] : (!empty($d['id_customer']) ? (int) $d['id_customer'] : null),
            'guest_name' => pSQL(Tools::substr((string) $guest, 0, 128)),
            'id_employee_holder' => !empty($d['id_employee_holder']) ? (int) $d['id_employee_holder'] : null,
            'id_pulse_kc_staff_group' => !empty($d['id_pulse_kc_staff_group']) ? (int) $d['id_pulse_kc_staff_group'] : null,
            'id_room' => isset($rooms[0]) ? (int) $rooms[0] : null, 'id_rooms' => pSQL(implode(',', $rooms)), 'room_nums' => pSQL(implode(',', $roomNums)), 'doors' => pSQL(implode(',', $doors)),
            'valid_from' => pSQL($d['valid_from']), 'valid_to' => pSQL($d['valid_to']),
            'override_deadbolt' => !empty($d['override_deadbolt']) ? 1 : 0, 'override_dnd' => !empty($d['override_dnd']) ? 1 : 0,
            'id_pulse_kc_encoder' => (int) $encoder['id_pulse_kc_encoder'], 'adapter' => pSQL($encoder['adapter']), 'sequence' => (int) $seq,
            'mobile' => !empty($d['mobile']) ? 1 : 0, 'status' => 'pending', 'id_parent_key' => !empty($d['id_parent_key']) ? (int) $d['id_parent_key'] : null,
            'issued_by' => PulseKcService::emp(), 'note' => pSQL(Tools::substr((string) (isset($d['note']) ? $d['note'] : ''), 0, 255)),
            'business_date' => PulseKcService::bd(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s'),
        );
        Db::getInstance()->insert(self::T, $row);
        $id = (int) Db::getInstance()->Insert_ID();

        try {
            $res = self::encodeOn($id, $encoder, $allRooms);
        } catch (PulseKcEncoderException $e) {
            Db::getInstance()->update(self::T, array('status' => 'failed', 'last_error' => pSQL(Tools::substr($e->getMessage(), 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_key='.$id);
            PulseKcEncoder::markSeen((int) $encoder['id_pulse_kc_encoder'], false, $e->getMessage());
            PulseKcService::queue('encode', array('all_rooms' => $allRooms ? 1 : 0), $id, (int) $encoder['id_pulse_kc_encoder'], null, $e->getMessage());
            PulseCoreService::audit('pulsekeycard', 'key_encode_failed', array('key_no' => $keyNo, 'encoder' => $encoder['name'], 'error' => $e->getMessage()), self::T, $id);
            PulseKcService::alert('Key '.$keyNo.' for '.implode(',', $roomNums).' could not be encoded — '.$e->userMessage(), $row['id_htl_booking'], $row['id_room']);
            throw $e;
        }
        PulseCoreService::audit('pulsekeycard', 'key_issue', array('key_no' => $keyNo, 'type' => $type, 'rooms' => $roomNums, 'valid_to' => $d['valid_to'],
            'encoder' => $encoder['name'], 'payload_hash' => $res['payload_hash']), self::T, $id);
        PulseCoreService::event('actionPulseKeyIssued', array('id_key' => $id, 'key_no' => $keyNo, 'type' => $type, 'id_room' => $row['id_room'], 'id_htl_booking' => $row['id_htl_booking']));
        return $id;
    }

    /** Send a pending/failed key row to its encoder and record the vendor's answer. Payload is stored encrypted. */
    protected static function encodeOn($id, $encoder, $allRooms = false)
    {
        $k = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_kc_key='.(int) $id);
        $adapter = PulseKcEncoder::adapter($encoder);
        $payloadIn = array(
            'type' => $k['type'], 'key_no' => $k['key_no'], 'guest_name' => $k['guest_name'],
            'room_nums' => array_filter(explode(',', (string) $k['room_nums'])), 'common_doors' => PulseKcService::doorCodes($k['doors']),
            'valid_from' => $k['valid_from'], 'valid_to' => $k['valid_to'],
            'override_deadbolt' => (int) $k['override_deadbolt'], 'override_dnd' => (int) $k['override_dnd'],
            'sequence' => (int) $k['sequence'], 'all_rooms' => $allRooms ? 1 : 0, 'mobile' => (int) $k['mobile'], 'new_key' => $k['type'] === 'guest' ? 1 : 0,
        );
        $res = $adapter->encode($payloadIn);
        $payload = isset($res['payload']) ? (string) $res['payload'] : '';
        $hash = $payload !== '' ? hash('sha256', $payload) : '';
        Db::getInstance()->update(self::T, array(
            'status' => 'issued', 'key_ref' => pSQL((string) $res['key_ref']), 'card_serial' => pSQL((string) $res['card_serial']),
            'sequence' => (int) (isset($res['sequence']) ? $res['sequence'] : $k['sequence']),
            'payload_enc' => $payload !== '' ? pSQL(PulseCoreService::encrypt($payload), true) : '', 'payload_hash' => pSQL($hash),
            'issued_at' => date('Y-m-d H:i:s'), 'last_error' => '', 'date_upd' => date('Y-m-d H:i:s'),
        ), 'id_pulse_kc_key='.(int) $id);
        PulseKcEncoder::markSeen((int) $encoder['id_pulse_kc_encoder'], true);
        PulseKcEncoder::countEncoded((int) $encoder['id_pulse_kc_encoder']);
        return array('payload_hash' => Tools::substr($hash, 0, 16), 'key_ref' => $res['key_ref'], 'card_serial' => $res['card_serial']);
    }

    /** Cron/queue path: try a failed key again on its encoder (or another one). */
    public static function retryEncode($id, $idEncoder = null)
    {
        $k = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_kc_key='.(int) $id);
        if (!$k) { throw new PrestaShopException('Key not found'); }
        if ($k['status'] === 'issued') { return true; }
        if (strtotime($k['valid_to']) < time()) { self::setStatus($id, 'expired'); return true; }
        $encoder = PulseKcEncoder::pick($idEncoder ? (int) $idEncoder : (int) $k['id_pulse_kc_encoder']);
        Db::getInstance()->update(self::T, array('id_pulse_kc_encoder' => (int) $encoder['id_pulse_kc_encoder'], 'adapter' => pSQL($encoder['adapter'])), 'id_pulse_kc_key='.(int) $id);
        self::encodeOn($id, $encoder, !empty($k['id_pulse_kc_staff_group']) && (int) Db::getInstance()->getValue('SELECT all_rooms FROM `'._DB_PREFIX_.'pulse_kc_staff_group` WHERE id_pulse_kc_staff_group='.(int) $k['id_pulse_kc_staff_group']));
        PulseCoreService::audit('pulsekeycard', 'key_retry_ok', array('key_no' => $k['key_no']), self::T, $id);
        return true;
    }

    /** Issue a guest key for an in-house booking with the stay's own validity window. */
    public static function issueForBooking($idBooking, array $o = array())
    {
        if (!PulseKcService::fd()) { throw new PrestaShopException('Front Desk is not installed — issue the key from the Key Desk by room instead'); }
        $b = PulseFdService::booking((int) $idBooking);
        if (!$b) { throw new PrestaShopException('Booking not found'); }
        $rooms = !empty($o['rooms']) ? $o['rooms'] : array((int) $b['id_room']);
        list($from, $to) = PulseKcService::window($b['date_from'], $b['date_to']);
        return self::issue(array_merge(array('type' => 'guest', 'id_htl_booking' => (int) $idBooking, 'rooms' => $rooms, 'valid_from' => $from, 'valid_to' => $to), $o));
    }

    /** A second card for the same stay: same rooms, same window, does not invalidate the first. */
    public static function duplicate($idKey, array $o = array())
    {
        $k = self::get($idKey);
        if (!$k) { throw new PrestaShopException('Key not found'); }
        // a duplicate of a dead card would hand out fresh access on a credential the desk has already killed
        if (!in_array($k['status'], array('issued', 'pending', 'failed'))) { throw new PrestaShopException('Key '.$k['key_no'].' is '.$k['status'].' — cut a new key instead of duplicating it'); }
        if (strtotime($k['valid_to']) <= time()) { throw new PrestaShopException('Key '.$k['key_no'].' has already expired — cut a new key instead'); }
        $max = (int) PulseKcService::cfg('MAX_DUPLICATES', 4);
        $n = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::T.'` WHERE (id_parent_key='.(int) $idKey.' OR id_pulse_kc_key='.(int) $idKey.') AND status="issued"');
        if ($max > 0 && $n >= $max) { throw new PrestaShopException('This stay already has '.$n.' active cards (limit '.$max.') — cancel one first or raise the limit in Settings'); }
        return self::issue(array_merge(array(
            'type' => 'duplicate', 'id_htl_booking' => $k['id_htl_booking'], 'id_customer' => $k['id_customer'], 'guest_name' => $k['guest_name'],
            'rooms' => array_filter(array_map('intval', explode(',', (string) $k['id_rooms']))), 'doors' => array_filter(array_map('intval', explode(',', (string) $k['doors']))),
            'valid_from' => $k['valid_from'], 'valid_to' => $k['valid_to'], 'override_deadbolt' => $k['override_deadbolt'], 'override_dnd' => $k['override_dnd'],
            'id_parent_key' => (int) $idKey, 'note' => 'Duplicate of '.$k['key_no'],
        ), $o));
    }

    /** Lost card: cancel the old one and cut a fresh sequence so the lost card stops working. */
    public static function reissue($idKey, $reason = 'Card lost')
    {
        $k = self::get($idKey);
        if (!$k) { throw new PrestaShopException('Key not found'); }
        self::cancel($idKey, $reason, false, 'lost');
        return self::issue(array(
            'type' => $k['type'] === 'duplicate' ? 'guest' : $k['type'], 'id_htl_booking' => $k['id_htl_booking'], 'id_customer' => $k['id_customer'], 'guest_name' => $k['guest_name'],
            'rooms' => array_filter(array_map('intval', explode(',', (string) $k['id_rooms']))), 'doors' => array_filter(array_map('intval', explode(',', (string) $k['doors']))),
            'valid_from' => date('Y-m-d H:i:s'), 'valid_to' => $k['valid_to'], 'override_deadbolt' => $k['override_deadbolt'], 'override_dnd' => $k['override_dnd'],
            'id_employee_holder' => $k['id_employee_holder'], 'id_pulse_kc_staff_group' => $k['id_pulse_kc_staff_group'], 'note' => 'Re-issued after '.$k['key_no'].' — '.$reason,
        ));
    }

    /**
     * Extend (or shorten) a key. Offline locks only learn the new date when the card is re-encoded, so the
     * card must go back on the encoder; the row keeps the same key_no and a fresh sequence.
     */
    public static function extend($idKey, $newValidTo)
    {
        $k = self::get($idKey);
        if (!$k) { throw new PrestaShopException('Key not found'); }
        if (in_array($k['status'], array('cancelled', 'lost', 'expired'))) { throw new PrestaShopException('Key '.$k['key_no'].' is '.$k['status'].' — issue a new one'); }
        $to = date('Y-m-d H:i:s', strtotime($newValidTo));
        if (strtotime($to) <= strtotime($k['valid_from'])) { throw new PrestaShopException('New expiry must be after the key start'); }
        if ($k['mechanical']) { // nothing to re-encode on a metal key — just move the collection date
            Db::getInstance()->update(self::T, array('valid_to' => pSQL($to), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_key='.(int) $idKey);
            PulseCoreService::audit('pulsekeycard', 'key_extend', array('key_no' => $k['key_no'], 'old_to' => $k['valid_to'], 'new_to' => $to, 'mechanical' => 1), self::T, $idKey);
            return true;
        }
        $encoder = PulseKcEncoder::pick((int) $k['id_pulse_kc_encoder']);
        $seq = PulseKcService::nextSequence(array_filter(explode(',', (string) $k['room_nums'])));
        Db::getInstance()->update(self::T, array('valid_to' => pSQL($to), 'sequence' => (int) $seq, 'status' => 'pending', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_key='.(int) $idKey);
        try {
            self::encodeOn($idKey, $encoder);
        } catch (PulseKcEncoderException $e) {
            Db::getInstance()->update(self::T, array('status' => 'failed', 'last_error' => pSQL(Tools::substr($e->getMessage(), 0, 255))), 'id_pulse_kc_key='.(int) $idKey);
            PulseKcEncoder::markSeen((int) $encoder['id_pulse_kc_encoder'], false, $e->getMessage());
            PulseKcService::queue('encode', array(), (int) $idKey, (int) $encoder['id_pulse_kc_encoder'], null, $e->getMessage());
            throw $e;
        }
        PulseKcMobileKey::extendForKey($idKey, $to);
        PulseCoreService::audit('pulsekeycard', 'key_extend', array('key_no' => $k['key_no'], 'old_to' => $k['valid_to'], 'new_to' => $to), self::T, $idKey);
        return true;
    }

    /**
     * Cancel a key. The vendor call is best-effort: if the encoder is down the row is still marked
     * cancelled (the guest must not keep access on paper) and a retry job pushes the revoke later.
     */
    public static function cancel($idKey, $reason = '', $quiet = false, $status = 'cancelled')
    {
        $k = self::get($idKey);
        if (!$k) { throw new PrestaShopException('Key not found'); }
        if (in_array($k['status'], array('cancelled', 'expired')) && $status === 'cancelled') { return true; }
        $err = null;
        if ($k['key_ref'] && $k['status'] === 'issued') {
            try {
                PulseKcEncoder::adapter((int) $k['id_pulse_kc_encoder'])->cancel($k['key_ref']);
                PulseKcEncoder::markSeen((int) $k['id_pulse_kc_encoder'], true);
            } catch (PulseKcEncoderException $e) {
                $err = $e->getMessage();
                PulseKcEncoder::markSeen((int) $k['id_pulse_kc_encoder'], false, $err);
                if (!$quiet) { PulseKcService::queue('cancel', array('reason' => $reason), (int) $idKey, (int) $k['id_pulse_kc_encoder'], null, $err); }
            } catch (Exception $e) { $err = $e->getMessage(); }
        }
        Db::getInstance()->update(self::T, array('status' => pSQL($status), 'cancelled_by' => PulseKcService::emp(), 'cancelled_at' => date('Y-m-d H:i:s'),
            'cancel_reason' => pSQL(Tools::substr((string) $reason, 0, 128)), 'last_error' => pSQL(Tools::substr((string) $err, 0, 255)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_key='.(int) $idKey);
        PulseKcMobileKey::revokeForKey($idKey, $reason ? $reason : 'key cancelled');
        PulseCoreService::audit('pulsekeycard', 'key_cancel', array('key_no' => $k['key_no'], 'reason' => $reason, 'status' => $status, 'vendor_error' => $err), self::T, $idKey);
        PulseCoreService::event('actionPulseKeyCancelled', array('id_key' => (int) $idKey, 'key_no' => $k['key_no'], 'id_room' => $k['id_room'], 'id_htl_booking' => $k['id_htl_booking'], 'reason' => $reason));
        if ($err) { PulseKcService::alert('Key '.$k['key_no'].' marked cancelled but the encoder did not confirm — revoke queued.', $k['id_htl_booking'], $k['id_room']); }
        return true;
    }

    /** Lost card at 2 a.m.: kill every active card for the room in one action, then cut a replacement. */
    public static function cancelForRoom($idRoom, $reason = 'Lost card — all keys cancelled', $status = 'cancelled')
    {
        $n = 0;
        foreach (self::forRoom($idRoom, 'issued,pending,failed') as $k) { self::cancel((int) $k['id_pulse_kc_key'], $reason, false, $status); $n++; }
        PulseCoreService::audit('pulsekeycard', 'room_keys_cancelled', array('id_room' => $idRoom, 'count' => $n, 'reason' => $reason), 'htl_room_information', $idRoom);
        return $n;
    }

    public static function cancelForBooking($idBooking, $reason = 'Check-out')
    {
        $n = 0;
        foreach (self::forBooking($idBooking, 'issued,pending,failed') as $k) { self::cancel((int) $k['id_pulse_kc_key'], $reason); $n++; }
        return $n;
    }

    /** Push the guest's new departure onto every live card of the stay (used by actionPulseStayChanged). */
    public static function extendForBooking($idBooking, $dateFrom, $dateTo)
    {
        list(, $to) = PulseKcService::window($dateFrom, $dateTo, false);
        $n = 0; $failed = 0;
        foreach (self::forBooking($idBooking, 'issued') as $k) {
            try { self::extend((int) $k['id_pulse_kc_key'], $to); $n++; }
            catch (Exception $e) { $failed++; }
        }
        return array('extended' => $n, 'failed' => $failed, 'valid_to' => $to);
    }

    public static function setStatus($idKey, $status)
    {
        return Db::getInstance()->update(self::T, array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_kc_key='.(int) $idKey);
    }

    /** Mechanical fallback: record that a metal key was handed over because the encoder was down. */
    public static function recordMechanical($idRoom, $idBooking, $note)
    {
        $roomNums = self::roomNumbers(array((int) $idRoom));
        $from = date('Y-m-d H:i:s'); $to = date('Y-m-d H:i:s', time() + 24 * 3600);
        if ($idBooking && PulseKcService::fd() && ($b = PulseFdService::booking((int) $idBooking))) { list($from, $to) = PulseKcService::window($b['date_from'], $b['date_to']); }
        Db::getInstance()->insert(self::T, array('key_no' => pSQL(PulseKcService::nextNo('M')), 'type' => 'guest', 'id_htl_booking' => $idBooking ? (int) $idBooking : null,
            'id_room' => (int) $idRoom, 'id_rooms' => (int) $idRoom, 'room_nums' => pSQL(implode(',', $roomNums)), 'valid_from' => pSQL($from), 'valid_to' => pSQL($to),
            'adapter' => 'mechanical', 'mechanical' => 1, 'status' => 'issued', 'issued_by' => PulseKcService::emp(), 'issued_at' => date('Y-m-d H:i:s'),
            'note' => pSQL(Tools::substr((string) $note, 0, 255)), 'business_date' => PulseKcService::bd(), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')));
        $id = (int) Db::getInstance()->Insert_ID();
        PulseCoreService::audit('pulsekeycard', 'mechanical_key', array('id_room' => $idRoom, 'note' => $note), self::T, $id);
        PulseKcService::alert('Mechanical key handed out for room '.implode(',', $roomNums).' — collect it at check-out.', $idBooking, $idRoom);
        return $id;
    }

    /** Blacklist a card serial on every adapter that supports it (Salto SVN and friends). */
    public static function blacklistSerial($cardSerial, $idEncoder = null)
    {
        if (!$cardSerial) { return false; }
        $done = 0;
        $encoders = $idEncoder ? array(PulseKcEncoder::get($idEncoder)) : PulseKcEncoder::all();
        foreach (array_filter($encoders) as $e) {
            try {
                $a = PulseKcEncoder::adapter($e);
                $caps = $a->capabilities();
                if (empty($caps['blacklist']) || !method_exists($a, 'blacklist')) { continue; }
                $a->blacklist($cardSerial);
                $done++;
            } catch (Exception $ex) { PulseCoreService::audit('pulsekeycard', 'blacklist_failed', array('serial' => $cardSerial, 'encoder' => $e['name'], 'error' => $ex->getMessage())); }
        }
        return $done;
    }

    /** Cron: everything past its validity becomes `expired`. */
    public static function expireDue()
    {
        $rows = Db::getInstance()->executeS('SELECT id_pulse_kc_key, key_no, key_ref, id_pulse_kc_encoder, status FROM `'._DB_PREFIX_.self::T.'` WHERE status IN ("issued","pending","failed") AND valid_to<NOW() LIMIT 500');
        foreach ($rows as $k) {
            if ($k['status'] === 'issued' && $k['key_ref']) {
                try { PulseKcEncoder::adapter((int) $k['id_pulse_kc_encoder'])->cancel($k['key_ref']); } catch (Exception $e) { /* offline locks expire on their own clock */ }
            }
            self::setStatus((int) $k['id_pulse_kc_key'], 'expired');
        }
        return count($rows);
    }

    /** id_room[] -> room numbers, in the order given. */
    public static function roomNumbers(array $ids)
    {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) { return array(); }
        $map = array();
        foreach (Db::getInstance()->executeS('SELECT id, room_num FROM `'._DB_PREFIX_.'htl_room_information` WHERE id IN ('.implode(',', $ids).')') as $r) { $map[(int) $r['id']] = $r['room_num']; }
        $out = array();
        foreach ($ids as $id) { if (isset($map[$id])) { $out[] = $map[$id]; } }
        return $out;
    }
}
