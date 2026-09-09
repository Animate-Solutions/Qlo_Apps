<?php
/** Banking: bank accounts, statement import and matching, unreconciled report, petty-cash imprest book. */
class AdminPulseAccBankController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Banking'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_statement')) {
            $this->context->smarty->assign(array(
                'rec' => PulseAccBank::reconciliation($id), 'lines' => PulseAccBank::lines($id, Tools::getValue('match_state') ?: null),
                'match_state' => Tools::getValue('match_state'), 'accounts' => PulseAccService::accounts(null, true, true), 'self_url' => $self, 'currency' => $this->context->currency->sign,
            ));
            return $this->setTemplate('statement.tpl');
        }
        $idBank = (int) Tools::getValue('id_bank', 0);
        $petty = $idBank ? $idBank : (int) Db::getInstance()->getValue('SELECT id_pulse_acc_bank_account FROM `'._DB_PREFIX_.'pulse_acc_bank_account` WHERE type="petty_cash" AND active=1 LIMIT 1');
        if (Tools::getValue('export')) {
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="unreconciled.csv"');
            die(PulseAccService::toCsv(PulseAccBank::unreconciled(5000)));
        }
        $this->context->smarty->assign(array(
            'banks' => PulseAccService::bankAccounts(false), 'statements' => PulseAccBank::statements($idBank ?: null),
            'unreconciled' => PulseAccBank::unreconciled(200), 'accounts' => PulseAccService::accounts(null, true, true),
            'petty_id' => $petty, 'petty' => $petty ? PulseAccBank::pettyBook($petty, Tools::getValue('from'), Tools::getValue('to')) : array(),
            'petty_balance' => $petty ? PulseAccBank::pettyBalance($petty) : 0, 'petty_account' => $petty ? PulseAccService::bankAccount($petty) : null,
            'sessions' => PulseAccBank::cashierSessions(Tools::getValue('from'), Tools::getValue('to')),
            'edit' => Tools::getValue('id_bank_edit') ? PulseAccService::bankAccount((int) Tools::getValue('id_bank_edit')) : null,
            'sample_csv' => _PS_MODULE_DIR_.'pulseaccounts/seed/bank_statement_sample.csv',
            'id_bank' => $idBank, 'self_url' => $self, 'currency' => $this->context->currency->sign, 'business_date' => PulseAccService::bd(), 'fd' => PulseAccService::fd(),
        ));
        $this->setTemplate('bank.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveBank')) {
                PulseAccService::saveBankAccount(array(
                    'id_pulse_acc_bank_account' => (int) Tools::getValue('id_pulse_acc_bank_account'), 'code' => Tools::getValue('code'), 'name' => Tools::getValue('name'),
                    'type' => Tools::getValue('type'), 'bank_name' => Tools::getValue('bank_name'), 'account_no' => Tools::getValue('account_no'),
                    'account_name' => Tools::getValue('account_name'), 'branch' => Tools::getValue('branch'), 'currency' => Tools::getValue('currency'),
                    'account_code' => Tools::getValue('account_code'), 'opening_balance' => Tools::getValue('opening_balance'), 'opening_date' => Tools::getValue('opening_date'),
                    'imprest_float' => Tools::getValue('imprest_float'), 'active' => Tools::getValue('active', 1), 'sort' => Tools::getValue('sort'), 'note' => Tools::getValue('note'),
                ));
                $this->confirmations[] = $this->l('Bank account saved');
            }
            if (Tools::isSubmit('importStatement')) {
                if (empty($_FILES['statement']['tmp_name']) || !is_uploaded_file($_FILES['statement']['tmp_name'])) { throw new PrestaShopException($this->l('Choose a CSV statement to upload')); }
                if ((int) $_FILES['statement']['size'] > 8388608) { throw new PrestaShopException($this->l('Statement is larger than 8 MB')); }
                $id = PulseAccBank::import((int) Tools::getValue('id_bank_s'), $_FILES['statement']['tmp_name'], $_FILES['statement']['name']);
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_statement='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('importPath')) { $p = trim(Tools::getValue('path')); if (!$p || !is_file($p)) { throw new PrestaShopException($this->l('No file at that path')); } $id = PulseAccBank::import((int) Tools::getValue('id_bank_s'), $p, basename($p)); Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_statement='.(int) $id.'&conf=3'); }
            if (Tools::isSubmit('rematch')) { $r = PulseAccBank::autoMatch((int) Tools::getValue('id_statement_s')); $this->confirmations[] = sprintf($this->l('Matched %d, still unmatched %d'), $r['matched'], $r['unmatched']); }
            if (Tools::isSubmit('matchLine')) { PulseAccBank::matchManual((int) Tools::getValue('id_line'), (int) Tools::getValue('id_journal')); $this->confirmations[] = $this->l('Matched'); }
            if (Tools::isSubmit('unmatchLine')) { PulseAccBank::unmatch((int) Tools::getValue('id_line')); $this->confirmations[] = $this->l('Match removed'); }
            if (Tools::isSubmit('ignoreLine')) { PulseAccBank::ignoreLine((int) Tools::getValue('id_line'), Tools::getValue('note')); $this->confirmations[] = $this->l('Line ignored'); }
            if (Tools::isSubmit('createFromLine')) { PulseAccBank::createFromLine((int) Tools::getValue('id_line'), Tools::getValue('account_code'), Tools::getValue('memo')); $this->confirmations[] = $this->l('Journal created and matched'); }
            if (Tools::isSubmit('deleteStatement')) { PulseAccBank::removeStatement((int) Tools::getValue('id_statement_s')); Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&conf=1'); }
            if (Tools::isSubmit('pettyMove')) { PulseAccBank::pettyMove((int) Tools::getValue('id_petty'), Tools::getValue('type'), Tools::getValue('amount'), Tools::getValue('description'), array('business_date' => Tools::getValue('business_date'), 'account_code' => Tools::getValue('account_code'), 'reference' => Tools::getValue('reference'), 'cost_centre' => Tools::getValue('cost_centre'))); $this->confirmations[] = $this->l('Petty cash movement posted'); }
            if (Tools::isSubmit('postVariance')) { PulseAccBank::postSessionVariance((int) Tools::getValue('id_session'), (int) Tools::getValue('id_petty')); $this->confirmations[] = $this->l('Cashier variance posted to the imprest book'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
