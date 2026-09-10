<?php
/**
 * Onboarding and offboarding as checklists with owners, due dates and — where the suite can do the work —
 * a button that actually does it: issue a key card through Pulse Key Card, set a POS PIN, set the portal PIN,
 * and on exit revoke every card and disable both logins. Where a module is absent the task stays a manual
 * tick with an honest explanation, never a silent no-op.
 */
class PulseHrLifecycle
{
    const T = 'pulse_hr_checklist';

    public static function templates($type = null) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_checklist_template` WHERE active=1'.($type ? ' AND type="'.pSQL($type).'"' : '').' ORDER BY type, name'); }
    public static function templateTasks($idTemplate) { return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_checklist_task_template` WHERE id_pulse_hr_checklist_template='.(int) $idTemplate.' ORDER BY sort'); }

    public static function saveTemplate(array $d, $id = 0)
    {
        $row = array('code' => pSQL($d['code']), 'name' => pSQL($d['name']), 'type' => pSQL($d['type'] === 'offboarding' ? 'offboarding' : 'onboarding'),
            'department' => !empty($d['department']) ? pSQL($d['department']) : null, 'active' => isset($d['active']) ? (int) $d['active'] : 1);
        if (!$row['code'] || !$row['name']) { throw new PrestaShopException('A checklist template needs a code and a name'); }
        if ($id) { Db::getInstance()->update('pulse_hr_checklist_template', $row, 'id_pulse_hr_checklist_template='.(int) $id, 0, true); } else { Db::getInstance()->insert('pulse_hr_checklist_template', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }

    public static function saveTemplateTask(array $d, $id = 0)
    {
        $row = array('id_pulse_hr_checklist_template' => (int) $d['id_pulse_hr_checklist_template'], 'sort' => (int) (isset($d['sort']) ? $d['sort'] : 0),
            'title' => pSQL($d['title']), 'owner_department' => pSQL(isset($d['owner_department']) ? $d['owner_department'] : 'admin'),
            'due_offset_days' => (int) (isset($d['due_offset_days']) ? $d['due_offset_days'] : 0), 'action' => pSQL(isset($d['action']) ? $d['action'] : 'none'),
            'mandatory' => !empty($d['mandatory']) ? 1 : 0);
        if (!$row['title'] || !$row['id_pulse_hr_checklist_template']) { throw new PrestaShopException('A checklist task needs a template and a title'); }
        if ($id) { Db::getInstance()->update('pulse_hr_checklist_task_template', $row, 'id_pulse_hr_checklist_task_template='.(int) $id, 0, true); } else { Db::getInstance()->insert('pulse_hr_checklist_task_template', $row, true); $id = (int) Db::getInstance()->Insert_ID(); }
        return $id;
    }
    public static function removeTemplateTask($id) { return Db::getInstance()->delete('pulse_hr_checklist_task_template', 'id_pulse_hr_checklist_task_template='.(int) $id); }

    /** Open a checklist for a person from the department template, or the general one. */
    public static function open($idEmployee, $type = 'onboarding', $idTemplate = 0)
    {
        $e = PulseHrEmployee::get($idEmployee);
        if (!$e) { throw new PrestaShopException('Employee not found'); }
        if (!$idTemplate) {
            $idTemplate = (int) Db::getInstance()->getValue('SELECT id_pulse_hr_checklist_template FROM `'._DB_PREFIX_.'pulse_hr_checklist_template`
                WHERE active=1 AND type="'.pSQL($type).'" AND (department="'.pSQL($e['dept_code']).'" OR department IS NULL OR department="") ORDER BY (department IS NULL), department DESC LIMIT 1');
        }
        if (!$idTemplate) { return 0; }
        $base = $type === 'offboarding' ? ($e['exit_date'] ? $e['exit_date'] : date('Y-m-d')) : ($e['hire_date'] ? $e['hire_date'] : date('Y-m-d'));
        $tasks = self::templateTasks($idTemplate);
        $due = $base;
        foreach ($tasks as $t) { $d = date('Y-m-d', strtotime($base.' +'.(int) $t['due_offset_days'].' day')); if ($d > $due) { $due = $d; } }
        Db::getInstance()->insert(self::T, array('id_pulse_hr_employee' => (int) $idEmployee, 'type' => pSQL($type), 'id_pulse_hr_checklist_template' => (int) $idTemplate,
            'status' => 'open', 'opened_on' => pSQL(date('Y-m-d')), 'due_on' => pSQL($due), 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), true);
        $id = (int) Db::getInstance()->Insert_ID();
        foreach ($tasks as $t) {
            Db::getInstance()->insert('pulse_hr_checklist_task', array('id_pulse_hr_checklist' => $id, 'sort' => (int) $t['sort'], 'title' => pSQL($t['title']),
                'owner_department' => pSQL($t['owner_department']), 'due_on' => pSQL(date('Y-m-d', strtotime($base.' +'.(int) $t['due_offset_days'].' day'))),
                'status' => 'pending', 'action' => pSQL($t['action']), 'mandatory' => (int) $t['mandatory'], 'date_add' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), true);
        }
        PulseCoreService::audit('pulsehr', 'checklist_open', array('type' => $type, 'id_employee' => (int) $idEmployee, 'tasks' => count($tasks)), self::T, $id);
        return $id;
    }

    public static function get($id)
    {
        $c = Db::getInstance()->getRow('SELECT c.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, e.id_employee, e.dob, d.name dept_name, d.code dept_code
            FROM `'._DB_PREFIX_.self::T.'` c INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=c.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department WHERE c.id_pulse_hr_checklist='.(int) $id);
        if ($c) { $c['tasks'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_checklist_task` WHERE id_pulse_hr_checklist='.(int) $id.' ORDER BY sort, id_pulse_hr_checklist_task'); }
        return $c;
    }

    public static function forEmployee($idEmployee)
    {
        $out = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.self::T.'` WHERE id_pulse_hr_employee='.(int) $idEmployee.' ORDER BY id_pulse_hr_checklist DESC');
        foreach ($out as &$c) { $c['tasks'] = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_checklist_task` WHERE id_pulse_hr_checklist='.(int) $c['id_pulse_hr_checklist'].' ORDER BY sort'); }
        unset($c);
        return $out;
    }

    public static function checklists($status = 'open', $type = null)
    {
        return Db::getInstance()->executeS('SELECT c.*, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no, d.name dept_name,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_checklist_task` t WHERE t.id_pulse_hr_checklist=c.id_pulse_hr_checklist) tasks,
                (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_checklist_task` t WHERE t.id_pulse_hr_checklist=c.id_pulse_hr_checklist AND t.status IN ("done","na")) done
            FROM `'._DB_PREFIX_.self::T.'` c INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=c.id_pulse_hr_employee
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_department` d ON d.id_pulse_hr_department=e.id_pulse_hr_department
            WHERE 1'.($status ? ' AND c.status="'.pSQL($status).'"' : '').($type ? ' AND c.type="'.pSQL($type).'"' : '').' ORDER BY c.due_on, c.id_pulse_hr_checklist DESC');
    }

    /** Tasks that are open and due — the "somebody has to do this today" list on the dashboard. */
    public static function openTasks($limit = 20)
    {
        return Db::getInstance()->executeS('SELECT t.*, c.type, CONCAT(e.firstname," ",e.lastname) employee_name, e.staff_no
            FROM `'._DB_PREFIX_.'pulse_hr_checklist_task` t
            INNER JOIN `'._DB_PREFIX_.self::T.'` c ON c.id_pulse_hr_checklist=t.id_pulse_hr_checklist AND c.status="open"
            INNER JOIN `'._DB_PREFIX_.'pulse_hr_employee` e ON e.id_pulse_hr_employee=c.id_pulse_hr_employee
            WHERE t.status IN ("pending","failed") ORDER BY (t.due_on IS NULL), t.due_on LIMIT '.(int) $limit);
    }

    /**
     * Tick a task. When it carries an action and the module that performs it is installed, we perform it and
     * record the reference; when the module is missing we say so and leave the task for a human.
     */
    public static function completeTask($idTask, $note = '', array $params = array())
    {
        $t = Db::getInstance()->getRow('SELECT t.*, c.id_pulse_hr_employee, c.type FROM `'._DB_PREFIX_.'pulse_hr_checklist_task` t
            INNER JOIN `'._DB_PREFIX_.self::T.'` c ON c.id_pulse_hr_checklist=t.id_pulse_hr_checklist WHERE t.id_pulse_hr_checklist_task='.(int) $idTask);
        if (!$t) { throw new PrestaShopException('Task not found'); }
        if ($t['status'] === 'done') { return $t['action_ref']; }
        $ref = '';
        if ($t['action'] !== 'none') {
            try { $ref = self::runAction($t['action'], (int) $t['id_pulse_hr_employee'], $params); }
            catch (Exception $e) {
                Db::getInstance()->update('pulse_hr_checklist_task', array('status' => 'failed', 'note' => pSQL(Tools::substr($e->getMessage(), 0, 250)), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_checklist_task='.(int) $idTask, 0, true);
                throw $e;
            }
        }
        Db::getInstance()->update('pulse_hr_checklist_task', array('status' => 'done', 'action_ref' => pSQL($ref), 'note' => pSQL($note ? $note : ($ref ? $ref : '')),
            'done_by' => PulseHrService::emp(), 'done_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_checklist_task='.(int) $idTask, 0, true);
        self::closeIfDone((int) $t['id_pulse_hr_checklist']);
        PulseCoreService::audit('pulsehr', 'checklist_task_done', array('title' => $t['title'], 'action' => $t['action'], 'ref' => $ref), 'pulse_hr_checklist_task', (int) $idTask);
        return $ref;
    }

    public static function skipTask($idTask, $note = '')
    {
        $t = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_hr_checklist_task` WHERE id_pulse_hr_checklist_task='.(int) $idTask);
        if (!$t) { throw new PrestaShopException('Task not found'); }
        if ((int) $t['mandatory'] && !$note) { throw new PrestaShopException('That task is mandatory — say why it does not apply'); }
        Db::getInstance()->update('pulse_hr_checklist_task', array('status' => 'na', 'note' => pSQL($note), 'done_by' => PulseHrService::emp(), 'done_at' => date('Y-m-d H:i:s'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_checklist_task='.(int) $idTask, 0, true);
        self::closeIfDone((int) $t['id_pulse_hr_checklist']);
        return true;
    }

    protected static function closeIfDone($idChecklist)
    {
        $left = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_checklist_task` WHERE id_pulse_hr_checklist='.(int) $idChecklist.' AND status IN ("pending","failed")');
        if ($left > 0) { return false; }
        Db::getInstance()->update(self::T, array('status' => 'completed', 'completed_on' => date('Y-m-d'), 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_checklist='.(int) $idChecklist, 0, true);
        return true;
    }

    /**
     * The actions a checklist task can actually perform. Every branch either does the work through the owning
     * module or throws a message the person on the screen can act on.
     */
    public static function runAction($action, $idEmployee, array $p = array())
    {
        $e = PulseHrEmployee::get($idEmployee);
        if (!$e) { throw new PrestaShopException('Employee not found'); }
        switch ($action) {
            case 'keycard_issue':
                if (!PulseHrService::kc()) { throw new PrestaShopException('Pulse Key Card is not installed — cut the card on the encoder and tick this task with the card number'); }
                if (!$e['id_employee']) { throw new PrestaShopException('Key cards are held against a back-office user — link '.$e['firstname'].' to an employee login first'); }
                $idGroup = (int) (isset($p['id_group']) ? $p['id_group'] : PulseHrService::cfg('KC_GROUP_'.Tools::strtoupper((string) $e['dept_code']), 0));
                if (!$idGroup) {
                    $idGroup = (int) Db::getInstance()->getValue('SELECT id_pulse_kc_staff_group FROM `'._DB_PREFIX_.'pulse_kc_staff_group` WHERE active=1 AND department="'.pSQL((string) $e['dept_code']).'" ORDER BY is_master');
                }
                if (!$idGroup) { throw new PrestaShopException('No key card access group matches '.$e['dept_name'].' — create one in Key Cards ▸ Staff first'); }
                return 'key:'.(int) PulseKcStaff::issueCard((int) $e['id_employee'], $idGroup);
            case 'keycard_revoke':
                if (!PulseHrService::kc()) { throw new PrestaShopException('Pulse Key Card is not installed — collect the card and tick this task'); }
                if (!$e['id_employee']) { return 'no linked login — nothing to revoke'; }
                $n = 0;
                foreach (Db::getInstance()->executeS('SELECT id_pulse_kc_key, card_serial FROM `'._DB_PREFIX_.'pulse_kc_key` WHERE id_employee_holder='.(int) $e['id_employee'].' AND status IN ("issued","pending","failed")') as $k) {
                    PulseKcKey::cancel((int) $k['id_pulse_kc_key'], 'Staff exit — '.$e['staff_no'], false, 'exit');
                    if (!empty($k['card_serial']) && method_exists('PulseKcKey', 'blacklistSerial')) { PulseKcKey::blacklistSerial($k['card_serial']); }
                    $n++;
                }
                return $n.' card(s) cancelled';
            case 'pos_pin':
                if (!PulseHrService::pos()) { throw new PrestaShopException('Pulse POS is not installed'); }
                if (!$e['id_employee']) { throw new PrestaShopException('A POS login needs a back-office user — link '.$e['firstname'].' to one first'); }
                $pin = isset($p['pin']) ? trim((string) $p['pin']) : '';
                if (!ctype_digit($pin) || strlen($pin) < 4) { throw new PrestaShopException('Enter a POS PIN of at least four digits'); }
                Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'pulse_pos_staff` (id_employee,pin_hash,role,active) VALUES ('.(int) $e['id_employee'].',"'.pSQL(PulseHrService::pinHash($pin)).'","'.pSQL(isset($p['role']) ? $p['role'] : 'waiter').'",1)
                    ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash), active=1');
                return 'POS login active';
            case 'pos_disable':
                if (!PulseHrService::pos() || !$e['id_employee']) { return 'no POS login'; }
                Db::getInstance()->update('pulse_pos_staff', array('active' => 0), 'id_employee='.(int) $e['id_employee'], 0, true);
                return 'POS login disabled';
            case 'ess_pin':
                $pin = isset($p['pin']) ? trim((string) $p['pin']) : '';
                if ($pin === '') { throw new PrestaShopException('Enter the staff portal PIN to set'); }
                PulseHrEmployee::setPin($idEmployee, $pin);
                return 'portal PIN set';
            case 'ess_disable':
                Db::getInstance()->update('pulse_hr_employee', array('ess_enabled' => 0, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_employee='.(int) $idEmployee, 0, true);
                PulseHrEss::revokeForEmployee($idEmployee, 'offboarding');
                return 'portal access closed';
            case 'final_settlement':
                $bal = PulseHrLeave::balances($idEmployee, (int) date('Y'));
                $days = 0;
                foreach ($bal as $b) { if ($b['code'] === 'ANN') { $days = (float) $b['available']; } }
                return 'leave owed '.$days.' day(s) ≈ '.round($days * PulseHrLeave::dailyRate($idEmployee), 2).' '.(PulseHrContract::onDate($idEmployee) ? 'NGN' : '');
            default:
                return isset($p['ref']) ? (string) $p['ref'] : '';
        }
    }
}
