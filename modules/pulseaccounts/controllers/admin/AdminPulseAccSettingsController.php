<?php
/** Posting rules & settings: the rule tables with an unmapped-items warning, period control, tax defaults, e-invoicing credentials and the cron token. */
class AdminPulseAccSettingsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Posting Rules & Settings'); }

    public function initContent()
    {
        parent::initContent();
        $keys = array('PULSE_ACC_VAT_PCT', 'PULSE_ACC_CONSUMPTION_PCT', 'PULSE_ACC_WHT_SERVICES_PCT', 'PULSE_ACC_WHT_RENT_PCT', 'PULSE_ACC_WHT_THRESHOLD',
            'PULSE_ACC_AR_TERMS_DAYS', 'PULSE_ACC_AP_TERMS_DAYS', 'PULSE_ACC_CURRENCY', 'PULSE_ACC_POST_MODE', 'PULSE_ACC_QUEUE_BATCH', 'PULSE_ACC_RETRY_MAX',
            'PULSE_ACC_DEFAULT_BANK', 'PULSE_ACC_RETAINED_EARNINGS', 'PULSE_ACC_PL_CLEARING', 'PULSE_ACC_HOTEL_NAME', 'PULSE_ACC_HOTEL_TIN', 'PULSE_ACC_HOTEL_ADDRESS',
            'PULSE_ACC_HOTEL_EMAIL', 'PULSE_ACC_EINV_ENABLED', 'PULSE_ACC_EINV_ENDPOINT', 'PULSE_ACC_EINV_BUSINESS_ID', 'PULSE_ACC_EINV_SERVICE_ID',
            'PULSE_ACC_EINV_TIMEOUT', 'PULSE_ACC_EINV_MIN_TOTAL', 'PULSE_ACC_DUNNING_DAYS', 'PULSE_ACC_STOP_LIST_DAYS', 'PULSE_ACC_BANK_MATCH_DAYS', 'PULSE_ACC_CRON_TOKEN');
        $cfg = array();
        foreach ($keys as $k) { $cfg[$k] = Configuration::get($k); }
        $this->context->smarty->assign(array(
            'cfg' => $cfg, 'rules' => PulseAccService::maps(), 'unmapped' => PulseAccService::unmapped(),
            'accounts' => PulseAccService::accounts(null, true, true), 'periods' => PulseAccService::periods(24),
            'counts' => PulseAccPosting::queueCounts(), 'self_url' => self::$currentIndex.'&token='.$this->token,
            'einv_key_set' => (bool) Configuration::get('PULSE_ACC_EINV_KEY'), 'einv_secret_set' => (bool) Configuration::get('PULSE_ACC_EINV_SECRET'),
            'cron_post' => 'php modules/pulseaccounts/cron/post.php '.Configuration::get('PULSE_ACC_CRON_TOKEN'),
            'cron_dep' => 'php modules/pulseaccounts/cron/depreciation.php '.Configuration::get('PULSE_ACC_CRON_TOKEN'),
            'map_types' => array('charge_code' => 'Charge code → revenue / cash account', 'expense_category' => 'Expense category → expense account', 'payment_method' => 'Payment method → cash / bank account',
                'payroll_department' => 'Department → payroll account', 'department' => 'Department → cost centre', 'inv_category' => 'Stock category → cost of sales / stock account',
                'pos_major_group' => 'POS major group → F&B revenue account', 'folio_type' => 'Folio type → ledger account', 'wht_category' => 'WHT type → rate'),
            'year' => (int) Tools::getValue('year', date('Y')),
        ));
        $this->setTemplate('settings.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveSettings')) {
                foreach (array('PULSE_ACC_VAT_PCT', 'PULSE_ACC_CONSUMPTION_PCT', 'PULSE_ACC_WHT_SERVICES_PCT', 'PULSE_ACC_WHT_RENT_PCT', 'PULSE_ACC_WHT_THRESHOLD', 'PULSE_ACC_EINV_MIN_TOTAL') as $k) { if (Tools::getValue($k) !== false) { Configuration::updateValue($k, (float) Tools::getValue($k)); } }
                foreach (array('PULSE_ACC_AR_TERMS_DAYS', 'PULSE_ACC_AP_TERMS_DAYS', 'PULSE_ACC_QUEUE_BATCH', 'PULSE_ACC_RETRY_MAX', 'PULSE_ACC_EINV_TIMEOUT', 'PULSE_ACC_STOP_LIST_DAYS', 'PULSE_ACC_BANK_MATCH_DAYS', 'PULSE_ACC_EINV_ENABLED') as $k) { if (Tools::getValue($k) !== false) { Configuration::updateValue($k, (int) Tools::getValue($k)); } }
                foreach (array('PULSE_ACC_CURRENCY', 'PULSE_ACC_POST_MODE', 'PULSE_ACC_DEFAULT_BANK', 'PULSE_ACC_RETAINED_EARNINGS', 'PULSE_ACC_PL_CLEARING', 'PULSE_ACC_HOTEL_NAME', 'PULSE_ACC_HOTEL_TIN', 'PULSE_ACC_HOTEL_ADDRESS', 'PULSE_ACC_HOTEL_EMAIL', 'PULSE_ACC_EINV_ENDPOINT', 'PULSE_ACC_EINV_BUSINESS_ID', 'PULSE_ACC_EINV_SERVICE_ID', 'PULSE_ACC_DUNNING_DAYS') as $k) { if (Tools::getValue($k) !== false) { Configuration::updateValue($k, Tools::getValue($k)); } }
                // credentials are encrypted at rest and never echoed back into the form
                if (($v = Tools::getValue('PULSE_ACC_EINV_KEY')) && strpos($v, '•') === false) { Configuration::updateValue('PULSE_ACC_EINV_KEY', $v); }
                if (($v = Tools::getValue('PULSE_ACC_EINV_SECRET')) && strpos($v, '•') === false) { Configuration::updateValue('PULSE_ACC_EINV_SECRET', PulseCoreService::encrypt($v)); }
                $this->confirmations[] = $this->l('Settings saved');
            }
            if (Tools::isSubmit('saveRule')) {
                PulseAccService::saveMap(array('map_type' => Tools::getValue('map_type'), 'key_value' => Tools::getValue('key_value'), 'label' => Tools::getValue('label'),
                    'account_code' => Tools::getValue('account_code'), 'tax_account_code' => Tools::getValue('tax_account_code'), 'contra_account_code' => Tools::getValue('contra_account_code'),
                    'cost_centre' => Tools::getValue('cost_centre'), 'wht_rate_pct' => Tools::getValue('wht_rate_pct'), 'note' => Tools::getValue('note'), 'active' => Tools::getValue('active', 1)));
                $this->confirmations[] = $this->l('Posting rule saved');
            }
            if (Tools::isSubmit('saveRules')) {
                $n = 0;
                foreach ((array) Tools::getValue('rule_account') as $idMap => $acct) {
                    $m = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_acc_map` WHERE id_pulse_acc_map='.(int) $idMap);
                    if (!$m) { continue; }
                    $tax = Tools::getValue('rule_tax'); $contra = Tools::getValue('rule_contra'); $cc = Tools::getValue('rule_cc');
                    PulseAccService::saveMap(array('map_type' => $m['map_type'], 'key_value' => $m['key_value'], 'label' => $m['label'], 'account_code' => $acct,
                        'tax_account_code' => isset($tax[$idMap]) ? $tax[$idMap] : $m['tax_account_code'], 'contra_account_code' => isset($contra[$idMap]) ? $contra[$idMap] : $m['contra_account_code'],
                        'cost_centre' => isset($cc[$idMap]) ? $cc[$idMap] : $m['cost_centre'], 'wht_rate_pct' => $m['wht_rate_pct'], 'note' => $m['note'], 'active' => 1));
                    $n++;
                }
                $this->confirmations[] = sprintf($this->l('%d rules updated'), $n);
            }
            if (Tools::isSubmit('deleteRule')) { Db::getInstance()->delete('pulse_acc_map', 'id_pulse_acc_map='.(int) Tools::getValue('id_map')); $this->confirmations[] = $this->l('Rule removed'); }
            if (Tools::isSubmit('makePeriods')) { PulseAccService::ensurePeriods(Tools::getValue('period_from', date('Y-m-d')), (int) Tools::getValue('months', 12)); $this->confirmations[] = $this->l('Periods created'); }
            if (Tools::isSubmit('closePeriod')) { $id = PulseAccService::closePeriod(Tools::getValue('period_code'), (bool) Tools::getValue('with_closing')); $this->confirmations[] = $id ? sprintf($this->l('Period closed with closing journal %d'), $id) : $this->l('Period closed'); }
            if (Tools::isSubmit('reopenPeriod')) { PulseAccService::reopenPeriod(Tools::getValue('period_code')); $this->confirmations[] = $this->l('Period reopened and its closing entry reversed'); }
            if (Tools::isSubmit('yearEnd')) { $r = PulseAccService::yearEnd((int) Tools::getValue('year_s')); $this->confirmations[] = sprintf($this->l('Year %d closed — result %s rolled to retained earnings'), $r['year'], number_format($r['result'], 2)); }
            if (Tools::isSubmit('newToken')) { Configuration::updateValue('PULSE_ACC_CRON_TOKEN', Tools::passwdGen(32)); $this->confirmations[] = $this->l('New cron token generated'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
