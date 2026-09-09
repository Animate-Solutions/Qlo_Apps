<?php
/** Corporate CRM: accounts, contacts, the activity log, the opportunity pipeline, contracted rates and production year on year. */
class AdminPulseCrmCorporateController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Corporate'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_account')) {
            $this->context->smarty->assign(array('a' => PulseCrmCorporate::account($id), 'employees' => Employee::getEmployees(),
                'room_types' => $this->roomTypes(), 'self_url' => $self));
            return $this->setTemplate('account.tpl');
        }
        $this->context->smarty->assign(array(
            'accounts' => PulseCrmCorporate::accounts(Tools::getValue('status') ?: null, Tools::getValue('q', '')),
            'companies' => PulseCrmService::tableExists('pulse_company') ? Db::getInstance()->executeS('SELECT id_pulse_company, name FROM `'._DB_PREFIX_.'pulse_company` WHERE active=1 ORDER BY name') : array(),
            'pipeline' => PulseCrmCorporate::pipeline(), 'opportunities' => PulseCrmCorporate::opportunities(),
            'production' => PulseCrmCorporate::productionReport(Tools::getValue('year', date('Y'))), 'year' => (int) Tools::getValue('year', date('Y')),
            'follow_ups' => PulseCrmCorporate::followUps(14), 'stale' => PulseCrmCorporate::stale(60),
            'employees' => Employee::getEmployees(), 'q' => Tools::getValue('q', ''), 'status' => Tools::getValue('status'), 'self_url' => $self,
        ));
        $this->setTemplate('corporate.tpl');
    }

    protected function roomTypes()
    {
        if (!PulseCrmService::tableExists('htl_room_type')) { return array(); }
        return Db::getInstance()->executeS('SELECT rt.id_product, pl.name FROM `'._DB_PREFIX_.'htl_room_type` rt INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON pl.id_product=rt.id_product AND pl.id_lang='.(int) $this->context->language->id.' ORDER BY pl.name');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveAccount')) {
                $id = PulseCrmCorporate::saveAccount(array('id_pulse_company' => Tools::getValue('id_pulse_company'), 'name' => Tools::getValue('name'),
                    'industry' => Tools::getValue('industry'), 'segment' => Tools::getValue('segment'), 'account_manager' => Tools::getValue('account_manager'),
                    'status' => Tools::getValue('status_a'), 'potential_nights' => Tools::getValue('potential_nights'), 'potential_value' => Tools::getValue('potential_value'),
                    'next_review' => Tools::getValue('next_review'), 'notes' => Tools::getValue('notes')), (int) Tools::getValue('id_account_a'));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_account='.$id.'&conf=3');
            }
            if (Tools::isSubmit('saveContact')) {
                PulseCrmCorporate::saveContact(array('id_pulse_crm_account' => (int) Tools::getValue('id_account_a'), 'name' => Tools::getValue('cname'), 'title' => Tools::getValue('ctitle'),
                    'email' => Tools::getValue('cemail'), 'phone' => Tools::getValue('cphone'), 'decision_role' => Tools::getValue('decision_role'),
                    'is_primary' => Tools::getValue('is_primary'), 'notes' => Tools::getValue('cnotes')), (int) Tools::getValue('id_contact'));
                $this->confirmations[] = $this->l('Contact saved');
            }
            if (Tools::isSubmit('logActivity')) {
                PulseCrmCorporate::logActivity(array('id_pulse_crm_account' => (int) Tools::getValue('id_account_a'), 'id_pulse_crm_contact' => Tools::getValue('id_contact'),
                    'type' => Tools::getValue('atype'), 'subject' => Tools::getValue('asubject'), 'notes' => Tools::getValue('anotes'), 'outcome' => Tools::getValue('aoutcome'),
                    'activity_date' => Tools::getValue('activity_date'), 'follow_up_at' => Tools::getValue('follow_up_at')));
                $this->confirmations[] = $this->l('Activity logged');
            }
            if (Tools::isSubmit('doneFollowUp')) { PulseCrmCorporate::completeFollowUp((int) Tools::getValue('id_activity')); $this->confirmations[] = $this->l('Follow-up cleared'); }
            if (Tools::isSubmit('saveOpportunity')) {
                PulseCrmCorporate::saveOpportunity(array('id_pulse_crm_account' => (int) Tools::getValue('id_account_a'), 'name' => Tools::getValue('oname'), 'stage' => Tools::getValue('stage'),
                    'expected_nights' => Tools::getValue('expected_nights'), 'expected_value' => Tools::getValue('expected_value'), 'probability' => Tools::getValue('probability'),
                    'close_date' => Tools::getValue('close_date'), 'owner' => Tools::getValue('owner'), 'lost_reason' => Tools::getValue('lost_reason'), 'notes' => Tools::getValue('onotes')), (int) Tools::getValue('id_opportunity'));
                $this->confirmations[] = $this->l('Opportunity saved');
            }
            if (Tools::isSubmit('saveRate')) {
                PulseCrmCorporate::saveRate(array('id_pulse_crm_account' => (int) Tools::getValue('id_account_a'), 'id_product' => Tools::getValue('id_product'),
                    'room_type_name' => Tools::getValue('room_type_name'), 'rate_tax_excl' => Tools::getValue('rate_tax_excl'), 'includes_breakfast' => Tools::getValue('includes_breakfast'),
                    'valid_from' => Tools::getValue('valid_from'), 'valid_to' => Tools::getValue('valid_to'), 'note' => Tools::getValue('rnote')), (int) Tools::getValue('id_rate'));
                $this->confirmations[] = $this->l('Contracted rate saved');
            }
            if (Tools::isSubmit('delRate')) { PulseCrmCorporate::removeRate((int) Tools::getValue('id_rate')); $this->confirmations[] = $this->l('Rate removed'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
