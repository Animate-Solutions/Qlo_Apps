<?php
class AdminPulseLaundryLinenController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Linen & Par Stock'); }
    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-m-01')); $to = Tools::getValue('to', PulseCoreService::businessDate());
        $this->context->smarty->assign(array('linen' => PulseLaundryService::linenStatus(), 'movements' => Db::getInstance()->executeS('SELECT m.*, t.name, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_linen_movement` m INNER JOIN `'._DB_PREFIX_.'pulse_linen_type` t ON t.id_pulse_linen_type=m.id_pulse_linen_type LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=m.id_employee ORDER BY m.id_pulse_linen_movement DESC LIMIT 40'),
            'loss' => PulseLaundryService::linenLoss($from, $to), 'batches' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_laundry_batch` ORDER BY id_pulse_laundry_batch DESC LIMIT 20'), 'from' => $from, 'to' => $to, 'self_url' => self::$currentIndex.'&token='.$this->token));
        $this->setTemplate('linen.tpl');
    }
    public function postProcess()
    {
        try {
            if (Tools::isSubmit('move')) { PulseLaundryService::linenMove((int) Tools::getValue('id_type'), Tools::getValue('mtype'), (int) Tools::getValue('qty'), (int) Tools::getValue('id_room') ?: null, Tools::getValue('reason')); $this->confirmations[] = $this->l('Movement recorded'); }
            if (Tools::isSubmit('saveType')) { $id = (int) Tools::getValue('id_type'); $d = array('name' => pSQL(Tools::getValue('name')), 'unit_cost' => (float) Tools::getValue('unit_cost'), 'par_per_room' => (float) Tools::getValue('par_per_room'), 'expected_washes' => (int) Tools::getValue('expected_washes')); $id ? Db::getInstance()->update('pulse_linen_type', $d, 'id_pulse_linen_type='.$id) : Db::getInstance()->insert('pulse_linen_type', $d); $this->confirmations[] = $this->l('Linen type saved'); }
            if (Tools::isSubmit('addBatch')) { Db::getInstance()->insert('pulse_laundry_batch', array('batch_no' => pSQL('B'.date('ymdHi')), 'machine' => pSQL(Tools::getValue('machine')), 'program' => pSQL(Tools::getValue('program')), 'kg' => (float) Tools::getValue('kg'), 'started_at' => date('Y-m-d H:i:s'), 'chemicals_cost' => (float) Tools::getValue('chem'), 'note' => pSQL(Tools::getValue('bnote')), 'id_employee' => (int) $this->context->employee->id, 'date_add' => date('Y-m-d H:i:s'))); $this->confirmations[] = $this->l('Wash batch logged'); }
            if (Tools::isSubmit('finishBatch')) { Db::getInstance()->update('pulse_laundry_batch', array('finished_at' => date('Y-m-d H:i:s')), 'id_pulse_laundry_batch='.(int) Tools::getValue('id_batch')); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
