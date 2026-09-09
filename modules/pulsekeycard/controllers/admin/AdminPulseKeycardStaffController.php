<?php
/** Staff Access — door groups per department, shift windows, card expiry, and the lost-master department re-issue. */
class AdminPulseKeycardStaffController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Staff Access'); }

    protected function assertEdit()
    {
        if (empty($this->tabAccess['edit']) || (int) $this->tabAccess['edit'] !== 1) { throw new PrestaShopException($this->l('You do not have permission to change staff access')); }
    }

    public function initContent()
    {
        parent::initContent();
        $idGroup = (int) Tools::getValue('id_group');
        $group = $idGroup ? PulseKcStaff::group($idGroup) : null;
        $this->context->smarty->assign(array(
            'groups' => PulseKcStaff::groups(false), 'group' => $group,
            'group_doors' => $group ? array_filter(array_map('intval', explode(',', (string) $group['doors']))) : array(),
            'days_list' => array(1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'),
            'days_checked' => self::daysChecked($group), // Smarty has no bit-shift operator, so resolve the mask here
            'cards' => PulseKcStaff::cards($idGroup ?: null), 'expiring' => PulseKcStaff::expiring(14),
            'doors' => PulseKcService::doors(true), 'employees' => Employee::getEmployees(),
            'departments' => array('frontdesk', 'housekeeping', 'maintenance', 'security', 'fnb', 'management', 'accounts'),
            'encoders' => PulseKcEncoder::all(), 'card_days' => (int) PulseKcService::cfg('STAFF_CARD_DAYS', 90),
            'self_url' => self::$currentIndex.'&token='.$this->token,
        ));
        $this->setTemplate('staff.tpl');
    }

    /** Day-of-week bitmask expanded to [1..7 => bool]; a new group defaults to every day. */
    protected static function daysChecked($group)
    {
        $mask = $group ? (int) $group['days_mask'] : 127; $out = array();
        for ($bit = 1; $bit <= 7; $bit++) { $out[$bit] = (bool) ($mask & (1 << ($bit - 1))); }
        return $out;
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveGroup')) {
                $this->assertEdit();
                $mask = 0;
                foreach ((array) Tools::getValue('days', array()) as $d) { $mask |= (int) pow(2, (int) $d - 1); }
                PulseKcStaff::saveGroup(array('name' => Tools::getValue('name'), 'department' => Tools::getValue('department', 'frontdesk'),
                    'doors' => (array) Tools::getValue('doors', array()), 'all_rooms' => (int) Tools::getValue('all_rooms'),
                    'shift_start' => Tools::getValue('shift_start', '00:00'), 'shift_end' => Tools::getValue('shift_end', '23:59'), 'days_mask' => $mask ?: 127,
                    'card_days' => (int) Tools::getValue('card_days'), 'override_deadbolt' => (int) Tools::getValue('override_deadbolt'), 'override_dnd' => (int) Tools::getValue('override_dnd'),
                    'is_master' => (int) Tools::getValue('is_master'), 'active' => (int) Tools::getValue('active', 1)), (int) Tools::getValue('id_group_edit'));
                $this->confirmations[] = $this->l('Access group saved');
            }
            if (Tools::isSubmit('issueStaffCard')) {
                $this->assertEdit();
                $id = PulseKcStaff::issueCard((int) Tools::getValue('id_employee'), (int) Tools::getValue('id_group_issue'), array('id_encoder' => (int) Tools::getValue('id_encoder') ?: null, 'days' => (int) Tools::getValue('days') ?: null));
                $k = PulseKcKey::get($id);
                $this->confirmations[] = sprintf($this->l('Staff card %s cut — valid to %s'), $k['key_no'], $k['valid_to']);
            }
            if (Tools::isSubmit('cancelStaffCard')) { $this->assertEdit(); PulseKcKey::cancel((int) Tools::getValue('id_key'), Tools::getValue('reason', 'Staff card withdrawn')); $this->confirmations[] = $this->l('Staff card cancelled'); }
            if (Tools::isSubmit('reissueGroup')) {
                $this->assertEdit();
                $r = PulseKcStaff::reissueGroup((int) Tools::getValue('id_group_reissue'), Tools::getValue('reason', 'Master card lost'), Tools::getValue('department_reissue') ?: null);
                $this->confirmations[] = sprintf($this->l('%d card(s) killed, %d re-cut'), $r['cancelled'], $r['issued']);
                foreach ($r['failed'] as $f) { $this->warnings[] = 'Employee #'.$f['id_employee'].': '.$f['error']; }
            }
        } catch (PulseKcEncoderException $e) { $this->errors[] = $e->userMessage(); }
        catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
