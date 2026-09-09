<?php
/**
 * Typed staff documents with issue and expiry dates. A lapsed food-handler certificate closes a kitchen and a
 * lapsed work permit closes a career, so this nags early and keeps nagging: the cron refreshes statuses daily
 * and raises a Front Desk ticket for anything that actually expires.
 */
class PulseHrDocument
{
    const T = 'pulse_hr_document';

    /** The document types a hotel is inspected on, plus the ordinary ones. */
    public static function types()
    {
        return array(
            'employment_letter' => 'Employment letter', 'contract' => 'Signed contract', 'id_card' => 'Staff ID card', 'nin' => 'NIN slip',
            'passport' => 'Passport', 'work_permit' => 'Work permit / CERPAC', 'food_handler' => 'Food handler certificate', 'medical' => 'Medical / health screening',
            'driving_licence' => 'Driving licence', 'certificate' => 'Professional certificate', 'guarantor' => 'Guarantor form', 'reference' => 'Reference letter',
            'nysc' => 'NYSC certificate', 'other' => 'Other',
        );
    }
    /** Types that must be on file before someone works, by department. */
    public static function required($dept = null)
    {
        $base = array('employment_letter', 'nin', 'medical');
        if (in_array($dept, array('fnb', 'housekeeping', 'laundry'))) { $base[] = 'food_handler'; }
        return $base;
    }

    public static function get($id) { return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_hr_document='.(int) $id); }
    public static function forEmployee($idEmployee) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_hr_employee='.(int) $idEmployee.' ORDER BY (expires_on IS NULL), expires_on, type'); }

    public static function save(array $d, $id = 0)
    {
        $types = self::types();
        $row = array('id_pulse_hr_employee' => (int) $d['id_pulse_hr_employee'], 'type' => pSQL(isset($types[$d['type']]) ? $d['type'] : 'other'),
            'name' => pSQL(!empty($d['name']) ? $d['name'] : (isset($types[$d['type']]) ? $types[$d['type']] : 'Document')), 'number' => pSQL(isset($d['number']) ? $d['number'] : ''),
            'issuer' => pSQL(isset($d['issuer']) ? $d['issuer'] : ''), 'issued_on' => !empty($d['issued_on']) ? pSQL(date('Y-m-d', strtotime($d['issued_on']))) : null,
            'expires_on' => !empty($d['expires_on']) ? pSQL(date('Y-m-d', strtotime($d['expires_on']))) : null, 'file_path' => pSQL(isset($d['file_path']) ? $d['file_path'] : ''),
            'verified' => !empty($d['verified']) ? 1 : 0, 'remind_days' => (int) (isset($d['remind_days']) ? $d['remind_days'] : PulseHrService::cfg('DOC_REMIND_DAYS', 30)),
            'note' => pSQL(isset($d['note']) ? $d['note'] : ''), 'date_upd' => date('Y-m-d H:i:s'));
        if (!$row['id_pulse_hr_employee']) { throw new PrestaShopException('A document belongs to an employee'); }
        if ($row['issued_on'] && $row['expires_on'] && $row['expires_on'] < $row['issued_on']) { throw new PrestaShopException('The document expires before it was issued'); }
        if (!empty($row['verified'])) { $row['verified_by'] = PulseHrService::emp(); }
        $row['status'] = self::statusFor($row['expires_on'], $row['remind_days'], isset($d['status']) && $d['status'] === 'revoked');
        if ($id) { Db::getInstance()->update(self::T, $row, 'id_pulse_hr_document='.(int) $id, 0, true); }
        else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert(self::T, $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        PulseCoreService::audit('pulsehr', 'document_save', array('type' => $row['type'], 'expires_on' => $row['expires_on'], 'id_employee' => $row['id_pulse_hr_employee']), self::T, $id);
        return $id;
    }

    public static function remove($id) { $d = self::get($id); if ($d) { PulseCoreService::audit('pulsehr', 'document_delete', array('type' => $d['type'], 'id_employee' => $d['id_pulse_hr_employee']), self::T, (int) $id); } return Db::getInstance()->delete(self::T, 'id_pulse_hr_document='.(int) $id); }

    public static function statusFor($expiresOn, $remindDays = 30, $revoked = false)
    {
        if ($revoked) { return 'revoked'; }
        if (!$expiresOn) { return 'valid'; }
        $days = (int) floor((strtotime($expiresOn) - strtotime(date('Y-m-d'))) / 86400);
        if ($days < 0) { return 'expired'; }
        return $days <= (int) $remindDays ? 'expiring' : 'valid';
    }

    /** Documents lapsing inside N days (and anything already lapsed), newest problem first. */
    public static function expiring($days = 30, $includeExpired = true)
    {
        return Db::getInstance()->executeS('SELECT dc.*, e.staff_no, CONCAT(e.firstname," ",e.lastname) employee_name, e.phone, e.status employee_status, d.code dept_code, d.name dept_name,
                DATEDIFF(dc.expires_on, CURDATE()) days_left
            FROM `'._DB_PREFIX_.self::T.'` dc
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=dc.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE e.status<>"exited" AND dc.status<>"revoked" AND dc.expires_on IS NOT NULL
              AND dc.expires_on<=DATE_ADD(CURDATE(), INTERVAL '.(int) $days.' DAY)'.($includeExpired ? '' : ' AND dc.expires_on>=CURDATE()').'
            ORDER BY dc.expires_on');
    }

    /** Who is missing a document their department is inspected on. */
    public static function missing()
    {
        $out = array();
        foreach (PulseHrEmployee::search(array('limit' => 500)) as $e) {
            $have = array();
            foreach (self::forEmployee((int) $e['id_pulse_hr_employee']) as $d) { if ($d['status'] !== 'revoked') { $have[$d['type']] = 1; } }
            $gap = array();
            foreach (self::required($e['dept_code']) as $t) { if (empty($have[$t])) { $gap[] = $t; } }
            if ($gap) { $out[] = array('id_pulse_hr_employee' => (int) $e['id_pulse_hr_employee'], 'staff_no' => $e['staff_no'], 'employee_name' => $e['full_name'], 'dept_name' => $e['dept_name'], 'missing' => $gap); }
        }
        return $out;
    }

    /**
     * Daily pass: restamp every status, and for anything that has just lapsed raise one ticket (once) so it
     * lands in somebody's queue rather than on a report nobody opens.
     */
    public static function refreshStatuses()
    {
        $db = Db::getInstance();
        $db->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET status="valid" WHERE status<>"revoked" AND (expires_on IS NULL OR expires_on>DATE_ADD(CURDATE(), INTERVAL remind_days DAY))');
        $db->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET status="expiring" WHERE status<>"revoked" AND expires_on IS NOT NULL AND expires_on>=CURDATE() AND expires_on<=DATE_ADD(CURDATE(), INTERVAL remind_days DAY)');
        $db->execute('UPDATE `'._DB_PREFIX_.self::T.'` SET status="expired" WHERE status<>"revoked" AND expires_on IS NOT NULL AND expires_on<CURDATE()');
        $raised = 0;
        foreach (self::expiring((int) PulseHrService::cfg('DOC_REMIND_DAYS', 30)) as $d) {
            if ($d['last_reminded'] === date('Y-m-d')) { continue; }
            $db->update(self::T, array('last_reminded' => date('Y-m-d')), 'id_pulse_hr_document='.(int) $d['id_pulse_hr_document'], 0, true);
            if ((int) $d['days_left'] > 0 && (int) $d['days_left'] % 7 !== 0) { continue; } // weekly nag while it is only approaching
            if (class_exists('PulseTicket')) {
                // pulse_ticket's category/department are ENUMs owned by Front Desk — 'other' / 'management' are the HR-shaped members
                PulseTicket::create(array('category' => 'other', 'department' => 'management', 'priority' => (int) $d['days_left'] < 0 ? 'high' : 'normal',
                    'title' => ((int) $d['days_left'] < 0 ? 'EXPIRED: ' : 'Expiring: ').$d['name'].' — '.$d['employee_name'],
                    'description' => $d['employee_name'].' ('.$d['staff_no'].', '.$d['dept_name'].') — '.$d['name'].' expires '.$d['expires_on'].'. '
                        .((int) $d['days_left'] < 0 ? 'This document has lapsed; the member of staff should not be on the floor until it is renewed.' : 'Renew it before the date.'),
                    'source' => 'hr'));
                $raised++;
            }
        }
        return $raised;
    }
}
