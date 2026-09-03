<?php
/** Companies / travel agents with city-ledger balance. Standard HelperList + HelperForm CRUD plus "receive payment". */
class AdminPulseCompanyController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true; $this->table = 'pulse_company'; $this->className = 'PulseCompany'; $this->identifier = 'id_pulse_company';
        $this->addRowAction('edit'); $this->addRowAction('delete'); $this->allow_export = true;
        parent::__construct(); $this->meta_title = $this->l('Companies / City Ledger');
        $this->fields_list = array(
            'id_pulse_company' => array('title' => 'ID'), 'name' => array('title' => $this->l('Name')), 'type' => array('title' => $this->l('Type')), 'contact_name' => array('title' => $this->l('Contact')), 'phone' => array('title' => $this->l('Phone')),
            'credit_limit' => array('title' => $this->l('Credit limit'), 'type' => 'price'), 'ledger_balance' => array('title' => $this->l('Ledger balance'), 'type' => 'price'), 'discount_pct' => array('title' => $this->l('Discount %')), 'active' => array('title' => $this->l('Active'), 'type' => 'bool', 'active' => 'status'),
        );
        $this->fields_form = array('legend' => array('title' => $this->l('Company')), 'input' => array(
            array('type' => 'text', 'label' => $this->l('Name'), 'name' => 'name', 'required' => true),
            array('type' => 'select', 'label' => $this->l('Type'), 'name' => 'type', 'options' => array('query' => array(array('id' => 'corporate', 'name' => 'Corporate'), array('id' => 'travel_agent', 'name' => 'Travel agent'), array('id' => 'government', 'name' => 'Government'), array('id' => 'group', 'name' => 'Group'), array('id' => 'other', 'name' => 'Other')), 'id' => 'id', 'name' => 'name')),
            array('type' => 'text', 'label' => $this->l('Contact name'), 'name' => 'contact_name'), array('type' => 'text', 'label' => 'Email', 'name' => 'email'), array('type' => 'text', 'label' => $this->l('Phone'), 'name' => 'phone'),
            array('type' => 'text', 'label' => $this->l('Address'), 'name' => 'address'), array('type' => 'text', 'label' => 'TIN', 'name' => 'tin'),
            array('type' => 'text', 'label' => $this->l('Credit limit'), 'name' => 'credit_limit', 'suffix' => $this->context->currency->sign), array('type' => 'text', 'label' => $this->l('Discount %'), 'name' => 'discount_pct'),
            array('type' => 'switch', 'label' => $this->l('Active'), 'name' => 'active', 'values' => array(array('id' => 'on', 'value' => 1), array('id' => 'off', 'value' => 0))),
            array('type' => 'text', 'label' => $this->l('Receive ledger payment'), 'name' => 'receive_amount', 'desc' => $this->l('Amount received now (posted to the company folio)')),
            array('type' => 'select', 'label' => $this->l('Payment method'), 'name' => 'receive_method', 'options' => array('query' => PulseChargeCode::all(1), 'id' => 'code', 'name' => 'name')),
            array('type' => 'text', 'label' => $this->l('Payment reference'), 'name' => 'receive_ref'),
        ), 'submit' => array('title' => $this->l('Save')));
    }

    public function initContent()
    {
        if (Tools::getValue('statement')) {
            $st = PulseAr::statement((int) Tools::getValue('statement'), Tools::getValue('from', date('Y-m-01')), Tools::getValue('to', PulseCoreService::businessDate()));
            $this->context->smarty->assign(array('st' => $st, 'hotel' => Configuration::get('PS_SHOP_NAME'), 'self_url' => self::$currentIndex.'&token='.$this->token));
            $this->context->smarty->assign('content', $this->context->smarty->fetch($this->getTemplatePath().'pulse_company/statement.tpl')); return;
        }
        if (Tools::getValue('ageing')) {
            $this->context->smarty->assign(array('rows' => PulseAr::ageing(), 'self_url' => self::$currentIndex.'&token='.$this->token));
            $this->context->smarty->assign('content', $this->context->smarty->fetch($this->getTemplatePath().'pulse_company/ageing.tpl')); return;
        }
        parent::initContent();
        $this->context->smarty->assign(array('self_url' => self::$currentIndex.'&token='.$this->token, 'companies' => Db::getInstance()->executeS('SELECT id_pulse_company, name FROM `'._DB_PREFIX_.'pulse_company` WHERE active=1 ORDER BY name')));
        $this->context->smarty->assign('content', $this->context->smarty->fetch($this->getTemplatePath().'pulse_company/tools.tpl').$this->content);
    }

    public function postProcess()
    {
        $r = parent::postProcess();
        if (Tools::isSubmit('addRule')) { PulseRouting::add('company', (int) Tools::getValue('id_pulse_company'), Tools::getValue('department'), 'company'); $this->confirmations[] = $this->l('Routing rule added'); }
        if ((Tools::isSubmit('submitAddpulse_company') || Tools::isSubmit('submitAddpulse_companyAndStay')) && (float) Tools::getValue('receive_amount') > 0 && empty($this->errors)) {
            $id = (int) Tools::getValue('id_pulse_company') ?: (int) Db::getInstance()->Insert_ID();
            if ($id) { $c = new PulseCompany($id); $c->receivePayment((float) Tools::getValue('receive_amount'), Tools::getValue('receive_method'), Tools::getValue('receive_ref')); $this->confirmations[] = $this->l('Ledger payment recorded'); }
        }
        return $r;
    }
}
