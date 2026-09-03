<?php
/** Back-office controller for Pulse POS. */
class AdminPulsePosController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->l('F&B POS');
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array(
            'module_name' => $this->module->name,
            'benchmark'   => 'eZee Burrp',
            'parity'      => array('Multiple outlets per hotel with own menus, service charge and tax', 'Table map, covers, merge/split bills, void with reason', 'KOT routed by kitchen station (kitchen/bar) with status feedback', 'Settle by cash/card/transfer, or post-to-room (writes a pulse_folio_line via actionPulseFolioPost)', 'Happy-hour / time-based pricing, modifiers, combos', 'Touch-friendly HTML5 POS front end served at /pulse-pos (tablet)', 'Day-end sales by outlet, item-wise, cashier-wise reports', 'Stock/recipe consumption hook (future: inventory module)'),
        ));
        $this->setTemplate('dashboard.tpl');
    }
}
