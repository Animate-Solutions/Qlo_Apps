<?php
/** Payables: bill a GRN or key a standalone bill, ageing, payment runs, remittance advice. */
class AdminPulseAccApController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Payables'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_bill')) {
            $this->context->smarty->assign(array('bill' => PulseAccAp::billFull($id), 'self_url' => $self, 'currency' => $this->context->currency->sign, 'banks' => PulseAccService::bankAccounts()));
            return $this->setTemplate('bill.tpl');
        }
        if ($run = Tools::getValue('run_no')) {
            $this->context->smarty->assign(array('rem' => PulseAccAp::remittance($run), 'self_url' => $self, 'currency' => $this->context->currency->sign));
            return $this->setTemplate('remittance.tpl');
        }
        $asOf = Tools::getValue('as_of', date('Y-m-d'));
        if ($r = Tools::getValue('export')) {
            $rows = $r === 'ageing' ? PulseAccAp::ageing($asOf) : PulseAccAp::bills(array('open' => 1), 5000);
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="ap-'.$r.'-'.$asOf.'.csv"');
            die(PulseAccService::toCsv($rows));
        }
        $this->context->smarty->assign(array(
            'ageing' => PulseAccAp::ageing($asOf), 'summary' => PulseAccAp::ageingSummary($asOf), 'suppliers' => PulseAccAp::suppliers(),
            'unbilled' => PulseAccAp::unbilledGrns(100), 'bills' => PulseAccAp::bills(array('id_pulse_inv_supplier' => (int) Tools::getValue('id_supplier'), 'status' => Tools::getValue('status'), 'q' => Tools::getValue('q')), 200),
            'due' => PulseAccAp::bills(array('open' => 1, 'due_by' => Tools::getValue('due_by', date('Y-m-d', strtotime('+7 day')))), 200),
            'payments' => PulseAccAp::payments(array(), 100), 'banks' => PulseAccService::bankAccounts(),
            'accounts' => PulseAccService::accounts(null, true, true), 'wht_rules' => PulseAccService::maps('wht_category'),
            'due_by' => Tools::getValue('due_by', date('Y-m-d', strtotime('+7 day'))), 'as_of' => $asOf, 'self_url' => $self,
            'currency' => $this->context->currency->sign, 'inv' => PulseAccService::inv(), 'vat_pct' => PulseAccService::vatPct(),
            'departments' => PulseAccService::maps('department'), 'business_date' => PulseAccService::bd(),
        ));
        $this->setTemplate('ap.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('billGrn')) {
                $id = PulseAccAp::billFromGrn((int) Tools::getValue('id_grn'), array('supplier_invoice_no' => Tools::getValue('supplier_invoice_no'), 'bill_date' => Tools::getValue('bill_date') ?: null, 'wht_rate_pct' => Tools::getValue('wht_rate_pct'), 'wht_type' => Tools::getValue('wht_type')));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_bill='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('addBill')) {
                $lines = array();
                $qty = (array) Tools::getValue('b_qty'); $price = (array) Tools::getValue('b_price'); $tax = (array) Tools::getValue('b_tax');
                $acct = (array) Tools::getValue('b_account'); $dept = (array) Tools::getValue('b_dept');
                foreach ((array) Tools::getValue('b_desc') as $i => $desc) {
                    if (trim((string) $desc) === '') { continue; }
                    $lines[] = array('description' => $desc, 'qty' => isset($qty[$i]) ? $qty[$i] : 1, 'unit_price' => isset($price[$i]) ? $price[$i] : 0,
                        'tax_rate' => isset($tax[$i]) ? $tax[$i] : 0, 'account_code' => isset($acct[$i]) ? $acct[$i] : '7120', 'department' => isset($dept[$i]) ? $dept[$i] : 'general');
                }
                $id = PulseAccAp::bill(array(
                    'id_pulse_inv_supplier' => Tools::getValue('id_supplier_s'), 'supplier_name' => Tools::getValue('supplier_name'), 'tin' => Tools::getValue('tin'),
                    'supplier_invoice_no' => Tools::getValue('supplier_invoice_no'), 'bill_date' => Tools::getValue('bill_date'), 'terms_days' => Tools::getValue('terms_days'),
                    'wht_rate_pct' => Tools::getValue('wht_rate_pct'), 'wht_type' => Tools::getValue('wht_type'), 'note' => Tools::getValue('note'), 'lines' => $lines,
                ));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_bill='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('payRun')) {
                $ids = array(); foreach ((array) Tools::getValue('pay') as $idBill => $on) { if ($on) { $ids[] = (int) $idBill; } }
                if (!$ids) { throw new PrestaShopException($this->l('Tick the bills to pay')); }
                $r = PulseAccAp::paymentRun($ids, array('payment_date' => Tools::getValue('payment_date'), 'method' => Tools::getValue('method'), 'id_pulse_acc_bank_account' => Tools::getValue('id_bank'), 'reference' => Tools::getValue('reference')));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&run_no='.urlencode($r['run_no']).'&conf=3');
            }
            if (Tools::isSubmit('disputeBill')) { PulseAccAp::disputeBill((int) Tools::getValue('id_bill_s'), Tools::getValue('note')); $this->confirmations[] = $this->l('Bill marked disputed'); }
            if (Tools::isSubmit('cancelBill')) { PulseAccAp::cancelBill((int) Tools::getValue('id_bill_s'), Tools::getValue('reason')); $this->confirmations[] = $this->l('Bill cancelled and its journal reversed'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
