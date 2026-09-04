<?php
class AdminPulseMaintenancePmController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Preventive Maintenance');
        $this->fields_options = array('mnt' => array('title' => $this->l('Maintenance settings'), 'fields' => array(
            'PULSE_MNT_LABOUR_RATE' => array('title' => $this->l('Labour rate per hour'), 'type' => 'text'), 'PULSE_MNT_RELEASE_ROOM_AT' => array('title' => $this->l('Return OOO room to housekeeping when work order is'), 'type' => 'select', 'list' => array(array('id' => 'completed', 'name' => 'Completed'), array('id' => 'verified', 'name' => 'Verified by supervisor')), 'identifier' => 'id'),
            'PULSE_MNT_AUTO_WO_FROM_TICKET' => array('title' => $this->l('Create work orders from Front Desk maintenance tickets'), 'type' => 'bool'), 'PULSE_MNT_AUTO_WO_FROM_HK' => array('title' => $this->l('Create work orders from housekeeping maintenance tasks'), 'type' => 'bool'),
            'PULSE_MNT_ALERT_PHONE' => array('title' => $this->l('Emergency alert phone (SMS)'), 'type' => 'text'), 'PULSE_MNT_ALERT_EMAIL' => array('title' => $this->l('Emergency alert email'), 'type' => 'text'),
        ), 'submit' => array('title' => $this->l('Save'))));
    }
    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array('schedules' => Db::getInstance()->executeS('SELECT s.*, a.name asset_name, CONCAT(e.firstname," ",e.lastname) tech, (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_work_order` w WHERE w.id_pulse_pm_schedule=s.id_pulse_pm_schedule AND w.status NOT IN ("completed","verified","cancelled")) open_wo, (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pm_room_cursor` c WHERE c.id_pulse_pm_schedule=s.id_pulse_pm_schedule AND c.last_done>=DATE_SUB(CURDATE(), INTERVAL s.interval_days DAY)) rooms_current FROM `'._DB_PREFIX_.'pulse_pm_schedule` s LEFT JOIN `'._DB_PREFIX_.'pulse_asset` a ON a.id_pulse_asset=s.id_pulse_asset LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=s.assigned_to ORDER BY s.active DESC, s.next_due'),
            'assets' => Db::getInstance()->executeS('SELECT id_pulse_asset, code, name FROM `'._DB_PREFIX_.'pulse_asset` WHERE status<>"retired" ORDER BY name'), 'room_types' => Db::getInstance()->executeS('SELECT p.id_product, pl.name FROM `'._DB_PREFIX_.'htl_room_type` p INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=p.id_product AND pl.id_lang='.(int) $this->context->language->id), 'techs' => Employee::getEmployees(),
            'rooms_total' => (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'htl_room_information` WHERE id_status IN (1,3)'), 'self_url' => self::$currentIndex.'&token='.$this->token, 'cron_url' => Tools::getShopDomainSsl(true).__PS_BASE_URI__.'modules/pulsemaintenance/cron/pm.php?token='.Configuration::get('PULSE_MNT_CRON_TOKEN'),
            'categories' => array('hvac', 'electrical', 'plumbing', 'generator', 'kitchen', 'laundry', 'it', 'elevator', 'fire_safety', 'furniture', 'pool', 'vehicle', 'building', 'other')));
        $this->content .= $this->context->smarty->fetch($this->getTemplatePath().'pulse_maintenance_pm/pm.tpl');
        $this->context->smarty->assign('content', $this->content);
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('generatePm')) { $n = PulseMaintenanceService::pmGenerate(); $this->confirmations[] = sprintf($this->l('%d preventive work order(s) generated'), $n); }
            if (Tools::isSubmit('saveSchedule')) { $id = (int) Tools::getValue('id_schedule'); $d = array('name' => pSQL(Tools::getValue('name')), 'category' => pSQL(Tools::getValue('category')), 'scope' => pSQL(Tools::getValue('scope')), 'id_pulse_asset' => (int) Tools::getValue('id_pulse_asset') ?: null, 'id_product' => (int) Tools::getValue('id_product') ?: null, 'location' => pSQL(Tools::getValue('location')), 'interval_days' => (int) Tools::getValue('interval_days'), 'checklist' => pSQL(Tools::getValue('checklist'), true), 'est_minutes' => (int) Tools::getValue('est_minutes'), 'assigned_to' => (int) Tools::getValue('assigned_to') ?: null, 'priority' => pSQL(Tools::getValue('priority')), 'rooms_per_run' => (int) Tools::getValue('rooms_per_run', 4), 'next_due' => pSQL(Tools::getValue('next_due') ?: date('Y-m-d')), 'active' => (int) Tools::getValue('active', 1)); $id ? Db::getInstance()->update('pulse_pm_schedule', $d, 'id_pulse_pm_schedule='.$id) : Db::getInstance()->insert('pulse_pm_schedule', $d); $this->confirmations[] = $this->l('Schedule saved'); }
            if (Tools::isSubmit('toggleSchedule')) { Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pm_schedule` SET active=1-active WHERE id_pulse_pm_schedule='.(int) Tools::getValue('id_schedule')); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
