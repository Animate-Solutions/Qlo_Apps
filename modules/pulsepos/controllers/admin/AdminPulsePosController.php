<?php
class AdminPulsePosController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('F&B POS'); }
    public function initContent()
    {
        parent::initContent();
        $bd = PulsePosService::bd();
        $this->context->smarty->assign(array(
            'outlets' => Db::getInstance()->executeS('SELECT o.*, (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pos_check` c WHERE c.id_pulse_pos_outlet=o.id_pulse_pos_outlet AND c.status IN ("open","printed","reopened")) open_checks, (SELECT ROUND(COALESCE(SUM(total),0),2) FROM `'._DB_PREFIX_.'pulse_pos_check` c WHERE c.id_pulse_pos_outlet=o.id_pulse_pos_outlet AND c.status="settled" AND c.business_date="'.pSQL($bd).'") sales_today, (SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pos_session` s WHERE s.id_pulse_pos_outlet=o.id_pulse_pos_outlet AND s.status="open") open_sessions FROM `'._DB_PREFIX_.'pulse_pos_outlet` o WHERE o.active=1'),
            'open' => Db::getInstance()->executeS('SELECT c.*, o.name outlet, CONCAT(e.firstname," ",e.lastname) server, r.room_num, TIMESTAMPDIFF(MINUTE,c.date_add,NOW()) minutes FROM `'._DB_PREFIX_.'pulse_pos_check` c INNER JOIN `'._DB_PREFIX_.'pulse_pos_outlet` o ON o.id_pulse_pos_outlet=c.id_pulse_pos_outlet LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=c.id_server LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=c.id_room WHERE c.status IN ("open","printed","reopened") ORDER BY c.date_add'),
            'summary' => PulsePosReport::summary($bd, $bd), 'sessions' => PulsePosReport::sessions($bd, $bd), 'clocked' => Db::getInstance()->executeS('SELECT c.*, CONCAT(e.firstname," ",e.lastname) name, o.name outlet FROM `'._DB_PREFIX_.'pulse_pos_clock` c INNER JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=c.id_employee LEFT JOIN `'._DB_PREFIX_.'pulse_pos_outlet` o ON o.id_pulse_pos_outlet=c.id_pulse_pos_outlet WHERE c.clock_out IS NULL'),
            'kds' => PulsePosKitchen::queue(), 'low' => PulsePosInventory::lowStock(), 'business_date' => $bd, 'meal_plans' => class_exists('HotelBookingDetail') ? PulsePosMealPlan::inHouse() : array(), 'fd' => PulsePosService::fdOn(),
            'pos_url' => $this->context->link->getModuleLink('pulsepos', 'app', array(), true), 'kds_url' => $this->context->link->getModuleLink('pulsepos', 'kds', array(), true), 'self_url' => self::$currentIndex.'&token='.$this->token,
            'links' => array('menu' => $this->context->link->getAdminLink('AdminPulsePosMenu'), 'inv' => $this->context->link->getAdminLink('AdminPulsePosInventory'), 'rep' => $this->context->link->getAdminLink('AdminPulsePosReports'), 'set' => $this->context->link->getAdminLink('AdminPulsePosSettings')),
            'cur' => $this->context->currency->sign));
        $this->setTemplate('dashboard.tpl');
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('forceClose')) { $emp = (int) $this->context->employee->id; PulsePosService::voidCheck((int) Tools::getValue('id_check'), 'Closed from back office', $emp, $emp); $this->confirmations[] = $this->l('Check voided'); }
            if (Tools::isSubmit('saveMealPlan')) { $r = PulsePosService::findRoom(Tools::getValue('mp_room')); if (!$r) { throw new PrestaShopException('No in-house guest in room '.Tools::getValue('mp_room')); } PulsePosMealPlan::save($r['id_htl_booking'], array('plan' => Tools::getValue('mp_plan'), 'persons' => Tools::getValue('mp_persons'), 'breakfast' => Tools::getValue('mp_b'), 'lunch' => Tools::getValue('mp_l'), 'dinner' => Tools::getValue('mp_d'), 'note' => Tools::getValue('mp_note'))); $this->confirmations[] = $this->l('Meal plan saved for room ').$r['room_num']; }
            if (Tools::isSubmit('forceSession')) { $s = (int) Tools::getValue('id_session'); PulsePosService::closeSession($s, (float) Tools::getValue('counted'), 'Closed from back office', (int) $this->context->employee->id); $this->confirmations[] = $this->l('Session closed'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
