<?php
/** Receivables: city-ledger invoicing, ageing, statements, receipts and allocation, credit notes, stop list and dunning. */
class AdminPulseAccArController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Receivables'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_invoice')) {
            $this->context->smarty->assign(array('inv' => PulseAccAr::invoice($id), 'self_url' => $self, 'currency' => $this->context->currency->sign,
                'hotel' => array('name' => Configuration::get('PULSE_ACC_HOTEL_NAME'), 'tin' => Configuration::get('PULSE_ACC_HOTEL_TIN'), 'address' => Configuration::get('PULSE_ACC_HOTEL_ADDRESS'), 'email' => Configuration::get('PULSE_ACC_HOTEL_EMAIL'))));
            return $this->setTemplate('invoice.tpl');
        }
        if ($id = (int) Tools::getValue('id_dunning')) {
            $this->context->smarty->assign(array('letter' => PulseAccAr::dunningRow($id), 'self_url' => $self));
            return $this->setTemplate('letter.tpl');
        }
        $asOf = Tools::getValue('as_of', date('Y-m-d'));
        $idCompany = (int) Tools::getValue('id_pulse_company');
        if ($r = Tools::getValue('export')) {
            $rows = $r === 'ageing' ? PulseAccAr::ageing($asOf) : PulseAccAr::invoices(array('open' => 1), 5000);
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="ar-'.$r.'-'.$asOf.'.csv"');
            die(PulseAccService::toCsv($rows));
        }
        $this->context->smarty->assign(array(
            'ageing' => PulseAccAr::ageing($asOf), 'summary' => PulseAccAr::ageingSummary($asOf), 'companies' => PulseAccAr::companies(),
            'invoices' => PulseAccAr::invoices(array('id_pulse_company' => $idCompany, 'status' => Tools::getValue('status'), 'q' => Tools::getValue('q')), 200),
            'receipts' => PulseAccAr::receipts(array('id_pulse_company' => $idCompany), 100), 'unallocated' => PulseAccAr::receipts(array('unallocated' => 1), 50),
            'billable' => $idCompany ? PulseAccAr::billable($idCompany, $asOf) : array(), 'open_invoices' => $idCompany ? PulseAccAr::openInvoices($idCompany) : array(),
            'statement' => $idCompany ? PulseAccAr::statement($idCompany, Tools::getValue('from'), Tools::getValue('to')) : null,
            'stop_list' => PulseAccAr::stopList($asOf), 'dunning' => PulseAccAr::dunningCandidates($asOf), 'dunning_log' => PulseAccAr::dunningLog(30),
            'banks' => PulseAccService::bankAccounts(), 'id_pulse_company' => $idCompany, 'as_of' => $asOf, 'self_url' => $self,
            'currency' => $this->context->currency->sign, 'fd' => PulseAccService::fd(), 'einv' => (bool) Configuration::get('PULSE_ACC_EINV_ENABLED'),
        ));
        $this->setTemplate('ar.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('invoiceCompany')) {
                $lines = array(); foreach ((array) Tools::getValue('line') as $idLine => $on) { if ($on) { $lines[] = (int) $idLine; } }
                $id = PulseAccAr::invoiceCompany((int) Tools::getValue('id_company_s'), $lines, Tools::getValue('as_of'), Tools::getValue('note'));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_invoice='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('invoiceManual')) {
                $lines = array();
                $qty = (array) Tools::getValue('m_qty'); $price = (array) Tools::getValue('m_price'); $tax = (array) Tools::getValue('m_tax');
                $acct = (array) Tools::getValue('m_account'); $dept = (array) Tools::getValue('m_dept');
                foreach ((array) Tools::getValue('m_desc') as $i => $desc) {
                    if (trim((string) $desc) === '') { continue; }
                    $lines[] = array('description' => $desc, 'qty' => isset($qty[$i]) ? $qty[$i] : 1, 'unit_price' => isset($price[$i]) ? $price[$i] : 0,
                        'tax_rate' => isset($tax[$i]) ? $tax[$i] : PulseAccService::vatPct(), 'account_code' => isset($acct[$i]) ? $acct[$i] : '4400', 'department' => isset($dept[$i]) ? $dept[$i] : 'general');
                }
                $id = PulseAccAr::invoiceManual((int) Tools::getValue('id_company_s'), $lines, Tools::getValue('inv_date'), Tools::getValue('note'), Tools::getValue('customer_name'));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_invoice='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('addReceipt')) {
                $alloc = array(); foreach ((array) Tools::getValue('alloc') as $idInv => $amt) { if ((float) $amt > 0) { $alloc[$idInv] = (float) $amt; } }
                PulseAccAr::receipt(array(
                    'id_pulse_company' => Tools::getValue('id_company_s'), 'amount' => Tools::getValue('amount'), 'wht_amount' => Tools::getValue('wht_amount'),
                    'wht_cert_no' => Tools::getValue('wht_cert_no'), 'method' => Tools::getValue('method'), 'id_pulse_acc_bank_account' => Tools::getValue('id_bank'),
                    'receipt_date' => Tools::getValue('receipt_date'), 'reference' => Tools::getValue('reference'), 'note' => Tools::getValue('note'),
                    'allocate' => $alloc, 'auto_allocate' => Tools::getValue('auto_allocate'),
                ));
                $this->confirmations[] = $this->l('Receipt posted');
            }
            if (Tools::isSubmit('allocateReceipt')) { PulseAccAr::allocate((int) Tools::getValue('id_receipt'), (int) Tools::getValue('id_invoice_s'), (float) Tools::getValue('amount')); $this->confirmations[] = $this->l('Allocated'); }
            if (Tools::isSubmit('autoAllocate')) { $n = PulseAccAr::autoAllocate((int) Tools::getValue('id_receipt')); $this->confirmations[] = sprintf($this->l('Allocated across %d invoices'), $n); }
            if (Tools::isSubmit('unallocate')) { PulseAccAr::unallocate((int) Tools::getValue('id_allocation')); $this->confirmations[] = $this->l('Allocation removed'); }
            if (Tools::isSubmit('creditNote')) { $id = PulseAccAr::creditNote((int) Tools::getValue('id_invoice_s'), (float) Tools::getValue('amount'), Tools::getValue('reason'), Tools::getValue('cn_date')); Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_invoice='.(int) $id.'&conf=3'); }
            if (Tools::isSubmit('writeOff')) { PulseAccAr::writeOff((int) Tools::getValue('id_invoice_s'), Tools::getValue('reason')); $this->confirmations[] = $this->l('Written off to bad debts'); }
            if (Tools::isSubmit('makeLetter')) { $id = PulseAccAr::dunningLetter((int) Tools::getValue('id_company_s'), (int) Tools::getValue('level') ?: null, Tools::getValue('as_of')); Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_dunning='.(int) $id.'&conf=3'); }
            if (Tools::isSubmit('sendLetter')) { $sent = PulseAccAr::dunningSend((int) Tools::getValue('id_dunning_s')); $this->confirmations[] = $sent ? $this->l('Letter e-mailed and logged') : $this->l('Letter marked sent (no e-mail address, or mail is not configured — print it)'); }
            if (Tools::isSubmit('queueEinvoice')) { PulseAccTax::queueEinvoice((int) Tools::getValue('id_invoice_s')); $this->confirmations[] = $this->l('Queued for FIRS e-invoicing'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
