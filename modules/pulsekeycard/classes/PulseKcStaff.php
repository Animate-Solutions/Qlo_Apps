<?php
/**
 * Staff access control: staff groups map a department onto a door group, a shift window and a card life.
 * Staff cards are ordinary pulse_kc_key rows (type staff/master) so they age, cancel and audit like guest keys.
 */
class PulseKcStaff
{
    const T = 'pulse_kc_staff_group';

    public static function groups($activeOnly = true)
    {
        return Db::getInstance()->executeS('SELECT g.*, (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_key` k WHERE k.id_pulse_kc_staff_group=g.id_pulse_kc_staff_group AND k.status="issued") active_cards
            FROM `'._DB_PREFIX_.self::T.'` g'.($activeOnly ? ' WHERE g.active=1' : '').' ORDER BY g.is_master DESC, g.department, g.name');
    }
    public static function group($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_kc_staff_group='.(int) $id); }

    public static function saveGroup(array $d, $id = 0)
    {
        $row = array('name' => pSQL($d['name']), 'department' => pSQL(isset($d['department']) ? $d['department'] : 'frontdesk'),
            'doors' => pSQL(implode(',', array_filter(array_map('intval', (array) (isset($d['doors']) ? $d['doors'] : array()))))),
            'all_rooms' => !empty($d['all_rooms']) ? 1 : 0, 'shift_start' => pSQL(isset($d['shift_start']) ? $d['shift_start'] : '00:00'), 'shift_end' => pSQL(isset($d['shift_end']) ? $d['shift_end'] : '23:59'),
            'days_mask' => (int) (isset($d['days_mask']) ? $d['days_mask'] : 127), 'card_days' => (int) (isset($d['card_days']) ? $d['card_days'] : PulseKcService::cfg('STAFF_CARD_DAYS', 90)) ?: 90,
            'override_deadbolt' => !empty($d['override_deadbolt']) ? 1 : 0, 'override_dnd' => !empty($d['override_dnd']) ? 1 : 0,
            'is_master' => !empty($d['is_master']) ? 1 : 0, 'active' => isset($d['active']) ? (int) $d['active'] : 1, 'date_upd' => date('Y-m-d H:i:s'));
        if ($id) { Db::getInstance()->update(self::T, $row, 'id_pulse_kc_staff_group='.(int) $id); }
        else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert(self::T, $row, false, true, Db::INSERT_IGNORE); $id = (int) Db::getInstance()->Insert_ID(); }
        PulseCoreService::audit('pulsekeycard', 'staff_group_save', array('id' => $id, 'name' => $d['name'], 'all_rooms' => !empty($d['all_rooms'])), self::T, $id);
        return $id;
    }

    /**
     * Cut a staff card. Validity is the group's card life; the shift window rides on the card as the
     * daily time band the lock enforces (adapters that cannot do time bands get a full-day card and the
     * shift is logged, so the audit trail still shows out-of-shift use).
     */
    public static function issueCard($idEmployee, $idGroup, array $o = array())
    {
        $g = self::group($idGroup);
        if (!$g) { throw new PrestaShopException('Staff access group not found'); }
        if (!$g['active']) { throw new PrestaShopException('Group '.$g['name'].' is inactive'); }
        $emp = new Employee((int) $idEmployee);
        if (!Validate::isLoadedObject($emp)) { throw new PrestaShopException('Employee not found'); }
        $days = isset($o['days']) ? (int) $o['days'] : (int) $g['card_days'];
        $from = isset($o['valid_from']) ? $o['valid_from'] : date('Y-m-d H:i:s');
        $to = isset($o['valid_to']) ? $o['valid_to'] : date('Y-m-d H:i:s', strtotime($from) + max(1, $days) * 86400);
        $id = PulseKcKey::issue(array(
            'type' => $g['is_master'] ? 'master' : 'staff', 'id_employee_holder' => (int) $idEmployee, 'id_pulse_kc_staff_group' => (int) $idGroup,
            'guest_name' => $emp->firstname.' '.$emp->lastname, 'rooms' => isset($o['rooms']) ? $o['rooms'] : array(),
            'doors' => array_filter(array_map('intval', explode(',', (string) $g['doors']))), 'all_rooms' => (int) $g['all_rooms'],
            'valid_from' => $from, 'valid_to' => $to, 'override_deadbolt' => (int) $g['override_deadbolt'], 'override_dnd' => (int) $g['override_dnd'],
            'id_encoder' => isset($o['id_encoder']) ? $o['id_encoder'] : null,
            'note' => $g['name'].' · shift '.Tools::substr($g['shift_start'], 0, 5).'–'.Tools::substr($g['shift_end'], 0, 5),
        ));
        PulseCoreService::audit('pulsekeycard', 'staff_card_issue', array('id_employee' => (int) $idEmployee, 'group' => $g['name'], 'valid_to' => $to), 'pulse_kc_key', $id);
        return $id;
    }

    /** Cards held by a group, newest first. */
    public static function cards($idGroup = null, $status = 'issued,pending,failed')
    {
        return Db::getInstance()->executeS('SELECT k.*, g.name group_name, g.department, g.shift_start, g.shift_end, CONCAT(e.firstname," ",e.lastname) holder
            FROM `'._DB_PREFIX_.'pulse_kc_key` k
            LEFT JOIN `'._DB_PREFIX_.self::T.'` g ON g.id_pulse_kc_staff_group=k.id_pulse_kc_staff_group
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=k.id_employee_holder
            WHERE k.type IN ("staff","master")'.($idGroup ? ' AND k.id_pulse_kc_staff_group='.(int) $idGroup : '').'
              AND k.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'") ORDER BY k.valid_to');
    }

    /**
     * Lost master: cancel every live card in the group (or the whole department) and cut replacements for
     * the same holders in one pass. Returns what happened per holder so the screen can show the failures.
     */
    public static function reissueGroup($idGroup, $reason = 'Master card lost — department re-issue', $department = null)
    {
        $where = $department ? ' AND g.department="'.pSQL($department).'"' : ' AND k.id_pulse_kc_staff_group='.(int) $idGroup;
        $cards = Db::getInstance()->executeS('SELECT k.id_pulse_kc_key, k.id_employee_holder, k.id_pulse_kc_staff_group, k.valid_to, k.card_serial
            FROM `'._DB_PREFIX_.'pulse_kc_key` k INNER JOIN `'._DB_PREFIX_.self::T.'` g ON g.id_pulse_kc_staff_group=k.id_pulse_kc_staff_group
            WHERE k.type IN ("staff","master") AND k.status IN ("issued","pending","failed")'.$where);
        $out = array('cancelled' => 0, 'issued' => 0, 'failed' => array());
        foreach ($cards as $c) {
            PulseKcKey::cancel((int) $c['id_pulse_kc_key'], $reason, false, 'lost');
            if ($c['card_serial']) { PulseKcKey::blacklistSerial($c['card_serial']); }
            $out['cancelled']++;
            if (!$c['id_employee_holder']) { continue; }
            try { PulseKcStaff::issueCard((int) $c['id_employee_holder'], (int) $c['id_pulse_kc_staff_group']); $out['issued']++; }
            catch (Exception $e) { $out['failed'][] = array('id_employee' => (int) $c['id_employee_holder'], 'error' => $e instanceof PulseKcEncoderException ? $e->userMessage() : $e->getMessage()); }
        }
        PulseCoreService::audit('pulsekeycard', 'staff_reissue', array('group' => $idGroup, 'department' => $department, 'cancelled' => $out['cancelled'], 'issued' => $out['issued'], 'reason' => $reason), self::T, $idGroup);
        return $out;
    }

    /** Cards lapsing inside N days — the "chase the staff before their card dies" list. */
    public static function expiring($days = 14)
    {
        return Db::getInstance()->executeS('SELECT k.id_pulse_kc_key, k.key_no, k.valid_to, g.name group_name, g.department, CONCAT(e.firstname," ",e.lastname) holder
            FROM `'._DB_PREFIX_.'pulse_kc_key` k LEFT JOIN `'._DB_PREFIX_.self::T.'` g ON g.id_pulse_kc_staff_group=k.id_pulse_kc_staff_group
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=k.id_employee_holder
            WHERE k.type IN ("staff","master") AND k.status="issued" AND k.valid_to BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL '.(int) $days.' DAY) ORDER BY k.valid_to');
    }

    /** Does this card's group allow entry at this moment? Used by the audit screen to flag out-of-shift opens. */
    public static function inShift($group, $when)
    {
        if (!$group || (empty($group['shift_start']) && empty($group['shift_end']))) { return true; }
        $ts = is_numeric($when) ? (int) $when : strtotime($when);
        $bit = (int) pow(2, ((int) date('N', $ts)) - 1);
        if (!((int) $group['days_mask'] & $bit)) { return false; }
        $t = date('H:i:s', $ts); $s = $group['shift_start']; $e = $group['shift_end'];
        return $s <= $e ? ($t >= $s && $t <= $e) : ($t >= $s || $t <= $e); // overnight shift wraps midnight
    }
}
