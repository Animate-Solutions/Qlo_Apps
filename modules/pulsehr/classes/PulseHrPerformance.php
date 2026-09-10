<?php
/**
 * Discipline and performance: queries and warnings the member of staff must acknowledge (they can do that on
 * the staff portal, and the acknowledgement records the time and the address it came from), a simple appraisal
 * cycle with weighted objectives and two signatures, and training records that expire.
 */
class PulseHrPerformance
{
    const T = 'pulse_hr_case';

    public static function caseTypes() { return array('query' => 'Query', 'verbal_warning' => 'Verbal warning', 'written_warning' => 'Written warning', 'final_warning' => 'Final warning', 'suspension' => 'Suspension', 'commendation' => 'Commendation', 'grievance' => 'Grievance'); }

    public static function getCase($id)
    {
        return Db::getInstance()->getRow('SELECT c.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.name dept_name,
                CONCAT(iss.firstname," ",iss.lastname) issued_by_name
            FROM `'._DB_PREFIX_.self::T.'` c INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=c.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            LEFT JOIN `'._DB_PREFIX_.'employee` iss ON iss.id_employee=c.issued_by WHERE c.id_pulse_hr_case='.(int) $id);
    }

    public static function cases($status = null, $idEmployee = 0, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT c.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.name dept_name
            FROM `'._DB_PREFIX_.self::T.'` c INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=c.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE 1'.($status ? ' AND c.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'")' : '').($idEmployee ? ' AND c.id_pulse_hr_employee='.(int) $idEmployee : '')
            .' ORDER BY c.issued_on DESC, c.id_pulse_hr_case DESC LIMIT '.(int) $limit);
    }

    /** Live warnings: how many count against someone right now (expired ones stop counting). */
    public static function liveWarnings($idEmployee)
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_hr_employee='.(int) $idEmployee.'
            AND type IN ("verbal_warning","written_warning","final_warning") AND status<>"withdrawn" AND (expires_on IS NULL OR expires_on>=CURDATE())');
    }

    public static function saveCase(array $d, $id = 0)
    {
        $types = self::caseTypes();
        $row = array('id_pulse_hr_employee' => (int) $d['id_pulse_hr_employee'], 'type' => pSQL(isset($types[$d['type']]) ? $d['type'] : 'query'),
            'subject' => pSQL($d['subject']), 'description' => pSQL(isset($d['description']) ? $d['description'] : '', true),
            'incident_date' => !empty($d['incident_date']) ? pSQL(date('Y-m-d', strtotime($d['incident_date']))) : null,
            'issued_on' => pSQL(date('Y-m-d', strtotime(!empty($d['issued_on']) ? $d['issued_on'] : 'now'))), 'issued_by' => PulseHrService::emp(),
            'outcome' => pSQL(isset($d['outcome']) ? $d['outcome'] : ''), 'unpaid' => !empty($d['unpaid']) ? 1 : 0, 'date_upd' => date('Y-m-d H:i:s'));
        if (!$row['id_pulse_hr_employee'] || !$row['subject']) { throw new PrestaShopException('A case needs an employee and a subject'); }
        $months = (int) PulseHrService::cfg('WARNING_LIFE_MONTHS', 12);
        $row['expires_on'] = in_array($row['type'], array('verbal_warning', 'written_warning', 'final_warning')) ? pSQL(date('Y-m-d', strtotime($row['issued_on'].' +'.$months.' month'))) : null;
        if ($row['type'] === 'suspension') {
            $row['suspension_from'] = !empty($d['suspension_from']) ? pSQL(date('Y-m-d', strtotime($d['suspension_from']))) : $row['issued_on'];
            $row['suspension_to'] = !empty($d['suspension_to']) ? pSQL(date('Y-m-d', strtotime($d['suspension_to']))) : $row['suspension_from'];
            if ($row['suspension_to'] < $row['suspension_from']) { throw new PrestaShopException('The suspension ends before it starts'); }
        }
        if ($id) { Db::getInstance()->update(self::T, $row, 'id_pulse_hr_case='.(int) $id, 0, true); }
        else {
            $row['case_no'] = PulseHrService::nextNo('DC'); $row['status'] = 'open'; $row['date_add'] = date('Y-m-d H:i:s');
            Db::getInstance()->insert(self::T, $row, true); $id = (int) Db::getInstance()->Insert_ID();
        }
        if ($row['type'] === 'suspension' && !empty($row['suspension_from']) && $row['suspension_from'] <= date('Y-m-d')) { PulseHrEmployee::setStatus((int) $row['id_pulse_hr_employee'], 'suspended', 'Case '.$id); }
        PulseCoreService::audit('pulsehr', 'discipline_case', array('type' => $row['type'], 'subject' => $row['subject'], 'id_employee' => $row['id_pulse_hr_employee']), self::T, $id);
        return $id;
    }

    /** The member of staff acknowledges having seen it — from the portal, so the time and IP are theirs. */
    public static function acknowledge($idCase, $idEmployee, $response = '', $ip = null)
    {
        $c = self::getCase($idCase);
        if (!$c || (int) $c['id_pulse_hr_employee'] !== (int) $idEmployee) { throw new PrestaShopException('Case not found'); }
        if ($c['acknowledged_at']) { return true; }
        Db::getInstance()->update(self::T, array('acknowledged_at' => date('Y-m-d H:i:s'), 'ack_ip' => pSQL(Tools::substr((string) ($ip ? $ip : Tools::getRemoteAddr()), 0, 45)),
            'response' => pSQL($response, true), 'responded_at' => $response ? date('Y-m-d H:i:s') : null,
            'status' => $response ? 'responded' : 'acknowledged', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_case='.(int) $idCase, 0, true);
        PulseCoreService::audit('pulsehr', 'discipline_acknowledged', array('case_no' => $c['case_no'], 'responded' => $response ? 1 : 0), self::T, (int) $idCase);
        return true;
    }

    public static function closeCase($idCase, $outcome, $status = 'closed')
    {
        $c = self::getCase($idCase);
        if (!$c) { throw new PrestaShopException('Case not found'); }
        Db::getInstance()->update(self::T, array('status' => pSQL(in_array($status, array('closed', 'withdrawn')) ? $status : 'closed'), 'outcome' => pSQL($outcome), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_case='.(int) $idCase, 0, true);
        if ($c['type'] === 'suspension') {
            $e = PulseHrEmployee::get((int) $c['id_pulse_hr_employee']);
            if ($e && $e['status'] === 'suspended') { PulseHrEmployee::setStatus((int) $c['id_pulse_hr_employee'], 'active', 'Suspension closed'); }
        }
        return true;
    }

    /* ---------- appraisals ---------- */

    public static function cycles() { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_appraisal_cycle` ORDER BY period_from DESC'); }
    public static function saveCycle(array $d, $id = 0)
    {
        $row = array('code' => pSQL($d['code']), 'name' => pSQL($d['name']), 'period_from' => pSQL(date('Y-m-d', strtotime($d['period_from']))), 'period_to' => pSQL(date('Y-m-d', strtotime($d['period_to']))),
            'due_on' => !empty($d['due_on']) ? pSQL(date('Y-m-d', strtotime($d['due_on']))) : null, 'status' => pSQL(in_array(isset($d['status']) ? $d['status'] : '', array('open', 'in_progress', 'closed')) ? $d['status'] : 'open'));
        if ($row['period_to'] < $row['period_from']) { throw new PrestaShopException('The cycle ends before it starts'); }
        if ($id) { Db::getInstance()->update('pulse_hr_appraisal_cycle', $row, 'id_pulse_hr_appraisal_cycle='.(int) $id, 0, true); }
        else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_hr_appraisal_cycle', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    /** Open an appraisal for everyone confirmed and in post, each against their own manager. */
    public static function openCycle($idCycle, $dept = null)
    {
        $c = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_appraisal_cycle` WHERE id_pulse_hr_appraisal_cycle='.(int) $idCycle);
        if (!$c) { throw new PrestaShopException('Cycle not found'); }
        $n = 0;
        foreach (PulseHrEmployee::search(array('department' => $dept, 'status' => 'active,on_leave', 'limit' => 500)) as $e) {
            if (Db::getInstance()->getValue('SELECT id_pulse_hr_appraisal FROM `'._DB_PREFIX_.'pulse_hr_appraisal` WHERE id_pulse_hr_appraisal_cycle='.(int) $idCycle.' AND id_pulse_hr_employee='.(int) $e['id_pulse_hr_employee'])) { continue; }
            Db::getInstance()->insert('pulse_hr_appraisal', array('id_pulse_hr_appraisal_cycle' => (int) $idCycle, 'id_pulse_hr_employee' => (int) $e['id_pulse_hr_employee'],
                'id_reviewer' => $e['id_manager'] ? (int) $e['id_manager'] : null, 'status' => 'draft', 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), true, true, Db::INSERT_IGNORE);
            $n++;
        }
        Db::getInstance()->update('pulse_hr_appraisal_cycle', array('status' => 'in_progress'), 'id_pulse_hr_appraisal_cycle='.(int) $idCycle, 0, true);
        return $n;
    }

    public static function appraisals($idCycle = 0, $idEmployee = 0)
    {
        return Db::getInstance()->executeS('SELECT a.*, cy.name cycle_name, cy.period_from, cy.period_to, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no,
                d.name dept_name, CONCAT(r.firstname," ",r.lastname) reviewer_name
            FROM `'._DB_PREFIX_.'pulse_hr_appraisal` a
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_appraisal_cycle` cy ON cy.id_pulse_hr_appraisal_cycle=a.id_pulse_hr_appraisal_cycle
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=a.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_employee` r ON r.id_pulse_hr_employee=a.id_reviewer
            WHERE 1'.($idCycle ? ' AND a.id_pulse_hr_appraisal_cycle='.(int) $idCycle : '').($idEmployee ? ' AND a.id_pulse_hr_employee='.(int) $idEmployee : '').' ORDER BY d.sort, e.lastname');
    }

    public static function appraisal($id)
    {
        $a = Db::getInstance()->getRow('SELECT a.*, cy.name cycle_name, cy.period_from, cy.period_to, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no
            FROM `'._DB_PREFIX_.'pulse_hr_appraisal` a
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_appraisal_cycle` cy ON cy.id_pulse_hr_appraisal_cycle=a.id_pulse_hr_appraisal_cycle
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=a.id_pulse_hr_employee WHERE a.id_pulse_hr_appraisal='.(int) $id);
        if ($a) { $a['objectives'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_appraisal_objective` WHERE id_pulse_hr_appraisal='.(int) $id.' ORDER BY sort'); }
        return $a;
    }

    public static function saveObjective(array $d, $id = 0)
    {
        $row = array('id_pulse_hr_appraisal' => (int) $d['id_pulse_hr_appraisal'], 'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0), 'title' => pSQL($d['title']),
            'description' => pSQL(isset($d['description']) ? $d['description'] : ''), 'weight' => (float) (isset($d['weight']) ? $d['weight'] : 0),
            'target' => pSQL(isset($d['target']) ? $d['target'] : ''), 'result' => pSQL(isset($d['result']) ? $d['result'] : ''),
            'rating' => (float) (isset($d['rating']) ? $d['rating'] : 0), 'comment' => pSQL(isset($d['comment']) ? $d['comment'] : ''));
        if (!$row['title'] || !$row['id_pulse_hr_appraisal']) { throw new PrestaShopException('An objective needs an appraisal and a title'); }
        if ($id) { Db::getInstance()->update('pulse_hr_appraisal_objective', $row, 'id_pulse_hr_appraisal_objective='.(int) $id, 0, true); } else { Db::getInstance()->insert('pulse_hr_appraisal_objective', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        self::rate((int) $row['id_pulse_hr_appraisal']);
        return $id;
    }
    public static function removeObjective($id)
    {
        $o = Db::getInstance()->getRow('SELECT id_pulse_hr_appraisal FROM `'._DB_PREFIX_.'pulse_hr_appraisal_objective` WHERE id_pulse_hr_appraisal_objective='.(int) $id);
        Db::getInstance()->delete('pulse_hr_appraisal_objective', 'id_pulse_hr_appraisal_objective='.(int) $id);
        if ($o) { self::rate((int) $o['id_pulse_hr_appraisal']); }
        return true;
    }

    /** Weighted overall rating. Weights that do not sum to 100 are normalised rather than silently wrong. */
    public static function rate($idAppraisal)
    {
        $rows = Db::getInstance()->executeS('SELECT weight, rating FROM `'._DB_PREFIX_.'pulse_hr_appraisal_objective` WHERE id_pulse_hr_appraisal='.(int) $idAppraisal);
        $w = 0; $sum = 0;
        foreach ($rows as $r) { $w += (float) $r['weight']; $sum += (float) $r['weight'] * (float) $r['rating']; }
        $overall = $w > 0 ? round($sum / $w, 2) : 0;
        Db::getInstance()->update('pulse_hr_appraisal', array('overall_rating' => $overall, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_appraisal='.(int) $idAppraisal, 0, true);
        return $overall;
    }

    public static function saveAppraisal(array $d, $id)
    {
        $row = array('status' => pSQL(in_array(isset($d['status']) ? $d['status'] : '', array('draft', 'self_review', 'reviewer', 'signed', 'closed')) ? $d['status'] : 'draft'),
            'reviewer_comment' => pSQL(isset($d['reviewer_comment']) ? $d['reviewer_comment'] : '', true), 'employee_comment' => pSQL(isset($d['employee_comment']) ? $d['employee_comment'] : '', true),
            'recommendation' => pSQL(in_array(isset($d['recommendation']) ? $d['recommendation'] : '', array('none', 'confirm', 'promote', 'increment', 'training', 'pip', 'exit')) ? $d['recommendation'] : 'none'),
            'id_reviewer' => !empty($d['id_reviewer']) ? (int) $d['id_reviewer'] : null, 'date_upd' => date('Y-m-d H:i:s'));
        if (!empty($d['sign_reviewer'])) { $row['reviewer_signed_at'] = date('Y-m-d H:i:s'); }
        if (!empty($d['sign_employee'])) { $row['employee_signed_at'] = date('Y-m-d H:i:s'); }
        Db::getInstance()->update('pulse_hr_appraisal', $row, 'id_pulse_hr_appraisal='.(int) $id, 0, true);
        self::rate($id);
        return true;
    }

    /* ---------- training ---------- */

    public static function training($idEmployee = 0, $limit = 200)
    {
        return Db::getInstance()->executeS('SELECT t.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.name dept_name
            FROM `'._DB_PREFIX_.'pulse_hr_training` t INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=t.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE 1'.($idEmployee ? ' AND t.id_pulse_hr_employee='.(int) $idEmployee : '').' ORDER BY t.completed_on DESC LIMIT '.(int) $limit);
    }

    public static function saveTraining(array $d, $id = 0)
    {
        $row = array('id_pulse_hr_employee' => (int) $d['id_pulse_hr_employee'], 'course' => pSQL($d['course']), 'provider' => pSQL(isset($d['provider']) ? $d['provider'] : ''),
            'type' => pSQL(in_array(isset($d['type']) ? $d['type'] : '', array('induction', 'safety', 'food_hygiene', 'fire', 'first_aid', 'service', 'technical', 'compliance', 'other')) ? $d['type'] : 'other'),
            'completed_on' => !empty($d['completed_on']) ? pSQL(date('Y-m-d', strtotime($d['completed_on']))) : null,
            'expires_on' => !empty($d['expires_on']) ? pSQL(date('Y-m-d', strtotime($d['expires_on']))) : null,
            'cost' => round((float) (isset($d['cost']) ? $d['cost'] : 0), 2), 'certificate_no' => pSQL(isset($d['certificate_no']) ? $d['certificate_no'] : ''), 'note' => pSQL(isset($d['note']) ? $d['note'] : ''));
        if (!$row['id_pulse_hr_employee'] || !$row['course']) { throw new PrestaShopException('A training record needs an employee and a course'); }
        if ($id) { Db::getInstance()->update('pulse_hr_training', $row, 'id_pulse_hr_training='.(int) $id, 0, true); }
        else { $row['date_add'] = date('Y-m-d H:i:s'); Db::getInstance()->insert('pulse_hr_training', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    public static function trainingExpiring($days = 45)
    {
        return Db::getInstance()->executeS('SELECT t.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.name dept_name, DATEDIFF(t.expires_on, CURDATE()) days_left
            FROM `'._DB_PREFIX_.'pulse_hr_training` t INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=t.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE e.status<>"exited" AND t.expires_on IS NOT NULL AND t.expires_on<=DATE_ADD(CURDATE(), INTERVAL '.(int) $days.' DAY) ORDER BY t.expires_on');
    }
}
