<?php
class AdminPulsePosInventoryController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('F&B Inventory'); }
    public function initContent()
    {
        parent::initContent();
        $from = Tools::getValue('from', date('Y-m-01')); $to = Tools::getValue('to', PulsePosService::bd());
        $this->context->smarty->assign(array('ingredients' => Db::getInstance()->executeS('SELECT i.*, ROUND(i.qty_on_hand*i.unit_cost,2) value FROM `'._DB_PREFIX_.'pulse_pos_ingredient` i ORDER BY i.group_name, i.name'), 'moves' => Db::getInstance()->executeS('SELECT m.*, i.name, i.unit, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_pos_stock_movement` m INNER JOIN `'._DB_PREFIX_.'pulse_pos_ingredient` i ON i.id_pulse_pos_ingredient=m.id_pulse_pos_ingredient LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=m.id_employee WHERE m.type<>"sale" ORDER BY m.id_pulse_pos_stock_movement DESC LIMIT 40'), 'purchases' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pos_purchase` ORDER BY id_pulse_pos_purchase DESC LIMIT 20'), 'cogs' => PulsePosInventory::cogs($from, $to), 'waste' => PulsePosInventory::waste($from, $to), 'inv_master' => Module::isEnabled('pulseinventory'), 'low' => PulsePosInventory::lowStock(), 'requisitions' => Db::getInstance()->executeS('SELECT r.*, CONCAT(e.firstname," ",e.lastname) who FROM `'._DB_PREFIX_.'pulse_pos_requisition` r LEFT JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee=r.requested_by ORDER BY r.id_pulse_pos_requisition DESC LIMIT 20'), 'store_stock' => PulsePosInventory::storeStock(), 'variance' => PulsePosInventory::variance($from, $to), 'stores' => Db::getInstance()->executeS('SELECT DISTINCT store FROM `'._DB_PREFIX_.'pulse_pos_outlet` UNION SELECT "main"'), 'from' => $from, 'to' => $to, 'self_url' => self::$currentIndex.'&token='.$this->token, 'waste_reasons' => Db::getInstance()->executeS('SELECT name FROM `'._DB_PREFIX_.'pulse_pos_reason` WHERE type="waste"')));
        $this->setTemplate('inventory.tpl');
    }
    public function postProcess()
    {
        $emp = (int) $this->context->employee->id;
        try {
            if (Tools::isSubmit('saveIngredient')) { $id = (int) Tools::getValue('id_ing'); $d = array('sku' => pSQL(Tools::getValue('sku')), 'name' => pSQL(Tools::getValue('name')), 'group_name' => pSQL(Tools::getValue('group_name')), 'unit' => pSQL(Tools::getValue('unit')), 'reorder_level' => (float) Tools::getValue('reorder_level'), 'unit_cost' => (float) Tools::getValue('unit_cost'), 'supplier' => pSQL(Tools::getValue('supplier')), 'store' => pSQL(Tools::getValue('store', 'main'))); $id ? Db::getInstance()->update('pulse_pos_ingredient', $d, 'id_pulse_pos_ingredient='.$id) : Db::getInstance()->insert('pulse_pos_ingredient', $d); $this->confirmations[] = $this->l('Ingredient saved'); }
            if (Tools::isSubmit('purchase')) { $lines = array(); foreach ((array) Tools::getValue('p_ing') as $k => $ing) { $q = (float) Tools::getValue('p_qty')[$k]; if ($ing && $q > 0) { $lines[] = array('id' => (int) $ing, 'qty' => $q, 'unit_cost' => (float) Tools::getValue('p_cost')[$k]); } } if (!$lines) { throw new PrestaShopException('No purchase lines'); } $no = PulsePosInventory::purchase(Tools::getValue('supplier'), Tools::getValue('invoice'), $lines, $emp); $this->confirmations[] = $this->l('Purchase recorded ').$no; }
            if (Tools::isSubmit('waste')) { PulsePosInventory::move((int) Tools::getValue('w_ing'), 'waste', -abs((float) Tools::getValue('w_qty')), null, '', Tools::getValue('w_reason'), null, $emp); $this->confirmations[] = $this->l('Waste recorded'); }
            if (Tools::isSubmit('requisition')) { $lines = array(); foreach ((array) Tools::getValue('q_ing') as $k => $ing) { $q = (float) Tools::getValue('q_qty')[$k]; if ($ing && $q > 0) { $lines[] = array('id' => (int) $ing, 'qty' => $q); } } if (!$lines) { throw new PrestaShopException('No lines'); } $no = PulsePosInventory::requisition(Tools::getValue('q_from', 'main'), Tools::getValue('q_to'), $lines, Tools::getValue('q_note'), $emp); $this->confirmations[] = $this->l('Requisition ').$no; }
            if (Tools::isSubmit('issueReq')) { PulsePosInventory::issueRequisition((int) Tools::getValue('id_req'), (array) Tools::getValue('issued'), $emp); $this->confirmations[] = $this->l('Stock issued'); }
            if (Tools::isSubmit('count')) { foreach ((array) Tools::getValue('cnt') as $id => $q) { if ($q !== '') { PulsePosInventory::move((int) $id, 'count', (float) $q, null, 'stock take', '', null, $emp); } } $this->confirmations[] = $this->l('Stock count applied'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
