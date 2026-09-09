<?php
/** Reconciliation: import a gateway settlement CSV, match it against the ledger, post the fees as a BANK expense. */
class AdminPulsePayReconController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Reconciliation'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        $id = (int) Tools::getValue('id_settlement');
        $this->context->smarty->assign(array(
            'settlements' => PulsePayRecon::settlements(), 'gateways' => PulsePayService::gateways(), 'self_url' => $self, 'rpt' => PulsePayService::rpt(),
            's' => $id ? PulsePayRecon::settlement($id) : null, 'lines' => $id ? PulsePayRecon::lines($id, Tools::getValue('match_state') ?: null) : array(),
            'missing' => $id ? PulsePayRecon::missing($id) : array(), 'match_state' => Tools::getValue('match_state'), 'currency' => $this->context->currency->sign,
            'seed_csv' => _PS_MODULE_DIR_.'pulsepayments/seed/settlement_sample.csv',
        ));
        $this->setTemplate('recon.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('importStatement')) {
                if (empty($_FILES['statement']['tmp_name']) || !is_uploaded_file($_FILES['statement']['tmp_name'])) { throw new PrestaShopException($this->l('Choose a CSV statement to upload')); }
                if ((int) $_FILES['statement']['size'] > 8388608) { throw new PrestaShopException($this->l('Statement is larger than 8 MB')); }
                $id = PulsePayRecon::import(Tools::getValue('gateway'), $_FILES['statement']['tmp_name'], $_FILES['statement']['name']);
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_settlement='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('importPath')) { $p = trim(Tools::getValue('path')); if (!$p || !is_file($p)) { throw new PrestaShopException($this->l('No file at that path')); } $id = PulsePayRecon::import(Tools::getValue('gateway'), $p, basename($p)); Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_settlement='.(int) $id.'&conf=3'); }
            if (Tools::isSubmit('rematch')) { $r = PulsePayRecon::match((int) Tools::getValue('id_settlement_s')); $this->confirmations[] = sprintf($this->l('Matched %d, variance %d, unmatched %d'), $r['matched'], $r['variance'], $r['unmatched']); }
            if (Tools::isSubmit('postFees')) { $e = PulsePayRecon::postFeeExpense((int) Tools::getValue('id_settlement_s')); $this->confirmations[] = $e ? $this->l('Gateway fees posted to the expense ledger') : $this->l('Fees were already posted'); }
            if (Tools::isSubmit('deleteStatement')) { PulsePayRecon::remove((int) Tools::getValue('id_settlement_s')); $this->confirmations[] = $this->l('Statement removed'); }
            if (Tools::isSubmit('linkLine')) {
                $tx = PulsePayService::tx(Tools::getValue('reference'));
                if (!$tx) { throw new PrestaShopException($this->l('No transaction with that reference')); }
                Db::getInstance()->update('pulse_pay_settlement_line', array('id_pulse_pay_transaction' => (int) $tx['id_pulse_pay_transaction'], 'match_state' => 'matched', 'note' => pSQL($this->l('Matched by hand'))), 'id_pulse_pay_settlement_line='.(int) Tools::getValue('id_line'));
                $this->confirmations[] = $this->l('Line matched');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
