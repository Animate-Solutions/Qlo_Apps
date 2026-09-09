<?php
/**
 * Service recovery — "glitch tracking" in OPERA's language. A case is opened by a survey, a complaint
 * ticket, a review or a member of staff, and it is not closed until someone has written down the root
 * cause, what was given away and what that cost. The cost-of-recovery report is the point of the whole
 * table: it is the number that changes behaviour at the morning stand-up.
 */
class PulseCrmCase
{
    protected static $sla = array('critical' => 60, 'high' => 240, 'medium' => 1440, 'low' => 4320); // minutes
    const SOURCES = array('survey', 'ticket', 'review', 'staff', 'guest', 'audit');
    const DEPARTMENTS = array('frontdesk', 'housekeeping', 'engineering', 'fnb', 'security', 'management', 'other');

    /** The three ENUM columns are fed by other modules and by survey question metadata — keep them inside the members. */
    public static function open(array $d)
    {
        $sev = isset($d['severity']) && isset(self::$sla[$d['severity']]) ? $d['severity'] : 'medium';
        $src = isset($d['source']) && in_array($d['source'], self::SOURCES) ? $d['source'] : 'staff';
        $dept = isset($d['department']) && in_array($d['department'], self::DEPARTMENTS) ? $d['department'] : 'frontdesk';
        $no = PulseCrmService::nextNo('SR');
        Db::getInstance()->insert('pulse_crm_case', array(
            'case_no' => pSQL($no), 'source' => pSQL($src), 'severity' => pSQL($sev),
            'department' => pSQL($dept),
            'id_customer' => !empty($d['id_customer']) ? (int) $d['id_customer'] : null, 'id_htl_booking' => !empty($d['id_htl_booking']) ? (int) $d['id_htl_booking'] : null,
            'id_room' => !empty($d['id_room']) ? (int) $d['id_room'] : null, 'id_pulse_ticket' => !empty($d['id_pulse_ticket']) ? (int) $d['id_pulse_ticket'] : null,
            'id_pulse_crm_survey_response' => !empty($d['id_pulse_crm_survey_response']) ? (int) $d['id_pulse_crm_survey_response'] : null,
            'id_pulse_crm_review' => !empty($d['id_pulse_crm_review']) ? (int) $d['id_pulse_crm_review'] : null,
            'title' => pSQL(Tools::substr($d['title'], 0, 190)), 'description' => pSQL(isset($d['description']) ? $d['description'] : '', true),
            'owner' => !empty($d['owner']) ? (int) $d['owner'] : (PulseCrmService::emp() ?: null),
            'sla_due' => date('Y-m-d H:i:s', time() + self::$sla[isset(self::$sla[$sev]) ? $sev : 'medium'] * 60),
            'status' => 'open', 'opened_at' => date('Y-m-d H:i:s'), 'business_date' => PulseCrmService::bd(), 'date_upd' => date('Y-m-d H:i:s'),
        ));
        $id = (int) Db::getInstance()->Insert_ID();
        if (!empty($d['id_customer'])) { PulseCrmProfile::tag((int) $d['id_customer'], 'detractor', 'case'); }
        PulseCoreService::audit('pulsecrm', 'case_open', array('case_no' => $no, 'source' => $src, 'severity' => $sev), 'pulse_crm_case', $id);
        PulseCoreService::event('actionPulseCrmCaseOpened', array('id_case' => $id, 'case_no' => $no, 'severity' => $sev, 'department' => $dept));
        return $id;
    }

    public static function get($id)
    {
        $c = Db::getInstance()->getRow('SELECT c.*, CONCAT(cu.firstname," ",cu.lastname) guest, cu.email, r.room_num, CONCAT(e.firstname," ",e.lastname) owner_name,
                (c.sla_due<NOW() AND c.status<>"closed") overdue FROM `'._DB_PREFIX_.'pulse_crm_case` c
            LEFT JOIN `'._DB_PREFIX_.'customer` cu ON cu.id_customer=c.id_customer
            LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=c.id_room
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=c.owner WHERE c.id_pulse_crm_case='.(int) $id);
        if ($c && $c['id_pulse_crm_survey_response']) { $c['response'] = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_survey_response` WHERE id_pulse_crm_survey_response='.(int) $c['id_pulse_crm_survey_response']); }
        return $c;
    }

    public static function all($status = 'open,investigating,recovering,escalated', $department = null, $from = null, $to = null)
    {
        return Db::getInstance()->executeS('SELECT c.*, CONCAT(cu.firstname," ",cu.lastname) guest, r.room_num, CONCAT(e.firstname," ",e.lastname) owner_name,
                (c.sla_due<NOW() AND c.status<>"closed") overdue FROM `'._DB_PREFIX_.'pulse_crm_case` c
            LEFT JOIN `'._DB_PREFIX_.'customer` cu ON cu.id_customer=c.id_customer
            LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=c.id_room
            LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=c.owner
            WHERE c.status IN ("'.implode('","', array_map('pSQL', explode(',', $status))).'")'
            .($department ? ' AND c.department="'.pSQL($department).'"' : '')
            .($from ? ' AND c.business_date>="'.pSQL($from).'"' : '').($to ? ' AND c.business_date<="'.pSQL($to).'"' : '')
            .' ORDER BY FIELD(c.severity,"critical","high","medium","low"), c.sla_due');
    }

    /** Update a case. Closing needs a root cause and a closing note — an empty close teaches nobody anything. */
    public static function update($id, array $d)
    {
        $c = self::get($id); if (!$c) { throw new PrestaShopException('No such case'); }
        $u = array('date_upd' => date('Y-m-d H:i:s'));
        foreach (array('status', 'severity', 'department', 'root_cause', 'recovery_action', 'recovery_detail', 'closing_note') as $k) { if (isset($d[$k])) { $u[$k] = pSQL($d[$k], $k === 'closing_note'); } }
        if (isset($d['recovery_cost'])) { $u['recovery_cost'] = round((float) $d['recovery_cost'], 2); }
        if (isset($d['owner'])) { $u['owner'] = (int) $d['owner'] ?: null; }
        if (isset($d['status']) && $d['status'] === 'closed') {
            $root = isset($d['root_cause']) ? $d['root_cause'] : $c['root_cause'];
            $note = isset($d['closing_note']) ? $d['closing_note'] : $c['closing_note'];
            if (!trim($root) || !trim($note)) { throw new PrestaShopException('A case cannot be closed without a root cause and a closing note'); }
            $u['closed_at'] = date('Y-m-d H:i:s');
            if ($c['id_customer']) { PulseCrmProfile::tag((int) $c['id_customer'], 'recovered', 'case'); }
        }
        Db::getInstance()->update('pulse_crm_case', $u, 'id_pulse_crm_case='.(int) $id);
        PulseCoreService::audit('pulsecrm', 'case_update', $u, 'pulse_crm_case', (int) $id);
        return true;
    }

    /**
     * Post the cost of a recovery to the guest's folio when it is a real give-away (a comp or a discount)
     * so the P&L sees it, and record the folio line on the case. A gift or a letter costs money too, but
     * not on this folio — those are recorded on the case only.
     */
    public static function postRecovery($id, $code = 'ADJ')
    {
        $c = self::get($id); if (!$c) { throw new PrestaShopException('No such case'); }
        if ($c['recovery_posted_line']) { throw new PrestaShopException('Already posted as folio line '.(int) $c['recovery_posted_line']); }
        if ((float) $c['recovery_cost'] <= 0) { throw new PrestaShopException('Set the cost of the recovery first'); }
        if (!in_array($c['recovery_action'], array('comp', 'discount', 'refund'))) { throw new PrestaShopException('Only a comp, discount or refund posts to the folio'); }
        if (!PulseCrmService::fd() || !$c['id_htl_booking']) { throw new PrestaShopException('No open guest folio to credit'); }
        $f = PulseFolio::openForBooking((int) $c['id_htl_booking']);
        if (!$f) { throw new PrestaShopException('That stay has no open folio — raise a credit note instead'); }
        $line = $f->post($code, 'Service recovery '.$c['case_no'].' — '.$c['recovery_action'], 1, -1 * (float) $c['recovery_cost'], 0, false, null, 'crm', $c['case_no']);
        Db::getInstance()->update('pulse_crm_case', array('recovery_posted_line' => (int) $line, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_case='.(int) $id);
        return $line;
    }

    /** Send the guest the apology that goes with the recovery. */
    public static function apologise($id, $message = '')
    {
        $c = self::get($id); if (!$c || !$c['id_customer']) { throw new PrestaShopException('No guest on this case'); }
        $detail = $message ? $message : ($c['recovery_detail'] ? $c['recovery_detail'] : 'We have taken it up with the department concerned.');
        $vars = PulseCrmService::mergeVars((int) $c['id_customer'], array('id_htl_booking' => $c['id_htl_booking'], 'recovery_detail' => $detail));
        $vars['html'] = PulseCrmCampaign::htmlBody($detail);
        $r = PulseCrmComms::deliver((int) $c['id_customer'], 'email', 'crm_case_apology', $vars, array('kind' => 'transactional', 'transactional' => 1, 'ignore_quiet' => 1, 'reference' => $c['case_no']));
        if ($r['ok']) { Db::getInstance()->update('pulse_crm_case', array('status' => $c['status'] === 'open' ? 'recovering' : $c['status'], 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_crm_case='.(int) $id); }
        return $r;
    }

    /** Cost of recovery by department — cases, average time to close, what was given away and what it cost. */
    public static function costReport($from, $to)
    {
        return Db::getInstance()->executeS('SELECT department, COUNT(*) cases, SUM(status="closed") closed, SUM(status<>"closed") open,
                ROUND(SUM(recovery_cost),2) cost, ROUND(AVG(NULLIF(recovery_cost,0)),2) avg_cost,
                ROUND(AVG(TIMESTAMPDIFF(HOUR, opened_at, COALESCE(closed_at,NOW()))),1) avg_hours,
                SUM(recovery_action="comp") comps, SUM(recovery_action="discount") discounts, SUM(recovery_action="upgrade") upgrades, SUM(recovery_action="gift") gifts
            FROM `'._DB_PREFIX_.'pulse_crm_case` WHERE business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY department ORDER BY cost DESC');
    }

    /** Root causes ranked — the second most useful report, and the one that survives a manager leaving. */
    public static function rootCauses($from, $to, $limit = 20)
    {
        return Db::getInstance()->executeS('SELECT root_cause, COUNT(*) cases, ROUND(SUM(recovery_cost),2) cost FROM `'._DB_PREFIX_.'pulse_crm_case`
            WHERE root_cause IS NOT NULL AND root_cause<>"" AND business_date BETWEEN "'.pSQL($from).'" AND "'.pSQL($to).'" GROUP BY root_cause ORDER BY cases DESC LIMIT '.(int) $limit);
    }

    /** Cases whose SLA has run out; the cron nudges the duty manager. */
    public static function overdue() { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_crm_case` WHERE status<>"closed" AND sla_due<NOW() ORDER BY sla_due'); }
}
