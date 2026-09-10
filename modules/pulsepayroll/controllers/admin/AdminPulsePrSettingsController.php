<?php
/** Payroll settings, the bank register, the payment-file column mapper, the cron token and the synthetic self-check. */
class AdminPulsePrSettingsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Payroll Settings'); }

    public function initContent()
    {
        parent::initContent();
        $keys = array('COUNTRY', 'CURRENCY', 'PAY_DAY', 'PRORATION', 'PERIODS_PER_YEAR', 'MIN_NET_PCT', 'LEAVER_REFUND', 'VARIANCE_PCT', 'ROUND_DP',
            'WORKING_DAYS', 'MONTH_HOURS', 'OT_MULTIPLIER', 'OT_REST_MULTIPLIER', 'OT_HOLIDAY_MULTIPLIER', 'NIGHT_ALLOWANCE', 'TRONC_PCT',
            'TRONC_ADMIN_PCT', 'TRONC_MGMT_CAP_PCT', 'TRONC_BASIS', 'CASUAL_TAX_PCT', 'CASUAL_DAY_RATE', 'EMPLOYER_STAFF_COUNT',
            'EMPLOYER_TURNOVER', 'TAX_STATE', 'PFA_DEFAULT', 'BANK_ACCOUNT', 'BANK_NAME', 'BANK_CODE', 'BANK_FILE_TEMPLATE',
            'PAYSLIP_PIN_MODE', 'PAYSLIP_EMAIL', 'PAYSLIP_TOKEN_DAYS', 'POST_GL', 'AUTO_APPROVE', 'CRON_TOKEN');
        $cfg = array();
        foreach ($keys as $k) { $cfg[$k] = Configuration::get('PULSE_PR_'.$k); }
        $this->context->smarty->assign(array(
            'cfg' => $cfg, 'countries' => PulsePrService::countries(false), 'banks' => PulsePrService::banks(false),
            'bank_columns' => PulseCoreService::setting('pulsepayroll', 'bank_columns'), 'nibss_columns' => PulsePrBankFile::nibssColumns(),
            'self_check' => json_decode((string) PulseCoreService::setting('pulsepayroll', 'self_check'), true),
            'cron_url' => $this->context->shop->getBaseURL(true).'modules/pulsepayroll/cron/payroll.php?token='.Configuration::get('PULSE_PR_CRON_TOKEN'),
            'self_url' => self::$currentIndex.'&token='.$this->token,
            'env' => array('hr' => PulsePrService::hr(), 'ta' => PulsePrService::ta(), 'acc' => PulsePrService::acc(), 'fd' => PulsePrService::fd(), 'pos' => PulsePrService::pos(), 'comms' => PulsePrService::comms()),
        ));
        $this->setTemplate('settings.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveSettings')) {
                foreach ((array) Tools::getValue('cfg') as $k => $v) {
                    if (!preg_match('/^[A-Z_]+$/', $k)) { continue; }
                    Configuration::updateValue('PULSE_PR_'.$k, is_string($v) ? Tools::substr($v, 0, 255) : $v);
                }
                PulsePrStatutory::reset();
                PulsePrService::log(null, 'settings_save', 'settings', array_keys((array) Tools::getValue('cfg')));
                $this->confirmations[] = $this->l('Settings saved');
            }
            if (Tools::isSubmit('saveBankColumns')) { PulseCoreService::setting('pulsepayroll', 'bank_columns', Tools::getValue('bank_columns')); $this->confirmations[] = $this->l('Payment-file column map saved'); }
            if (Tools::isSubmit('saveBank')) { $this->saveBank(); $this->confirmations[] = $this->l('Bank saved'); }
            if (Tools::isSubmit('newToken')) { Configuration::updateValue('PULSE_PR_CRON_TOKEN', Tools::passwdGen(32)); $this->confirmations[] = $this->l('New cron token generated — update your scheduler'); }
            if (Tools::isSubmit('runSelfCheck')) {
                $r = PulsePrSelfCheck::run();
                PulseCoreService::setting('pulsepayroll', 'self_check', json_encode($r));
                if ($r['ok']) { $this->confirmations[] = sprintf($this->l('Self-check passed: %d assertions.'), $r['passed']); }
                else { $this->errors[] = sprintf($this->l('Self-check FAILED: %1$d passed, %2$d failed. See the table below — do not run a live payroll until this is clean.'), $r['passed'], $r['failed']); }
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    protected function saveBank()
    {
        $name = trim((string) Tools::getValue('bname'));
        if ($name === '') { throw new PrestaShopException('A bank needs a name'); }
        $row = array('name' => pSQL(Tools::substr($name, 0, 96)), 'nibss_code' => pSQL(Tools::substr((string) Tools::getValue('nibss_code'), 0, 16)),
            'sort_code' => pSQL(Tools::substr((string) Tools::getValue('sort_code'), 0, 16)), 'swift' => pSQL(Tools::substr((string) Tools::getValue('swift'), 0, 16)),
            'country' => pSQL(Tools::substr((string) Tools::getValue('bcountry', 'NG'), 0, 2)), 'template' => pSQL(Tools::getValue('btemplate', 'nibss')),
            'active' => (int) (bool) Tools::getValue('bactive', 1), 'sort' => (int) Tools::getValue('bsort'));
        $ex = (int) Db::getInstance()->getValue('SELECT id_pulse_pr_bank FROM `'._DB_PREFIX_.'pulse_pr_bank` WHERE name="'.$row['name'].'"');
        if ($ex) { return Db::getInstance()->update('pulse_pr_bank', $row, 'id_pulse_pr_bank='.$ex, 0, true); }
        return Db::getInstance()->insert('pulse_pr_bank', $row, true);
    }
}
