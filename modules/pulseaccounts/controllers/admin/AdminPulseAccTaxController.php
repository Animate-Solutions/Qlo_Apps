<?php
/** Tax: VAT register and return, consumption tax, WHT register and certificates, FIRS/NRS e-invoicing queue. */
class AdminPulseAccTaxController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Tax'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_wht')) {
            $this->context->smarty->assign(array('c' => PulseAccTax::whtCertificate($id), 'self_url' => $self, 'currency' => $this->context->currency->sign));
            return $this->setTemplate('certificate.tpl');
        }
        $period = Tools::getValue('period', PulseAccService::period(PulseAccService::bd()));
        if ($r = Tools::getValue('export')) {
            $rows = $r === 'wht' ? PulseAccTax::whtRegister($period) : PulseAccTax::vatRegister($period, Tools::getValue('direction') ?: null);
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="'.$r.'-register-'.$period.'.csv"');
            die(PulseAccService::toCsv($rows));
        }
        $ein = (int) Tools::getValue('id_einvoice');
        $this->context->smarty->assign(array(
            'period' => $period, 'periods' => PulseAccService::periods(24), 'ret' => PulseAccTax::vatReturn($period),
            'vat_out' => PulseAccTax::vatRegister($period, 'output', 500), 'vat_in' => PulseAccTax::vatRegister($period, 'input', 500),
            'wht' => PulseAccTax::whtRegister($period), 'wht_summary' => PulseAccTax::whtSummary($period),
            'einvoices' => PulseAccTax::einvoiceQueue(Tools::getValue('einv_status') ?: null, 200), 'einv_row' => $ein ? PulseAccTax::einvoiceRow($ein) : null,
            'einv_on' => (bool) Configuration::get('PULSE_ACC_EINV_ENABLED'), 'einv_endpoint' => Configuration::get('PULSE_ACC_EINV_ENDPOINT'),
            'banks' => PulseAccService::bankAccounts(), 'self_url' => $self, 'currency' => $this->context->currency->sign,
            'vat_pct' => PulseAccService::vatPct(), 'cons_pct' => PulseAccService::consumptionPct(),
            'link_settings' => $this->context->link->getAdminLink('AdminPulseAccSettings'),
        ));
        $this->setTemplate('tax.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('fileVat')) { PulseAccTax::fileVatReturn(Tools::getValue('period_s'), Tools::getValue('reference')); $this->confirmations[] = $this->l('VAT return filed and posted to 2220'); }
            if (Tools::isSubmit('remitWht')) { $r = PulseAccTax::remitWht(Tools::getValue('period_s'), Tools::getValue('reference'), (int) Tools::getValue('id_bank')); $this->confirmations[] = sprintf($this->l('Remitted %s across %d certificates'), number_format($r['total'], 2), $r['certificates']); }
            if (Tools::isSubmit('addWht')) {
                PulseAccTax::recordWht(array('direction' => Tools::getValue('direction'), 'party_type' => Tools::getValue('party_type'), 'party_name' => Tools::getValue('party_name'),
                    'tin' => Tools::getValue('tin'), 'wht_type' => Tools::getValue('wht_type'), 'base_amount' => Tools::getValue('base_amount'),
                    'rate_pct' => Tools::getValue('rate_pct'), 'amount' => round((float) Tools::getValue('base_amount') * (float) Tools::getValue('rate_pct') / 100, 2),
                    'source' => 'manual', 'doc_no' => Tools::getValue('doc_no'), 'business_date' => Tools::getValue('business_date')));
                $this->confirmations[] = $this->l('Certificate recorded');
            }
            if (Tools::isSubmit('sendEinvoice')) { $r = PulseAccTax::einvoiceSend((int) Tools::getValue('id_einvoice_s')); $this->confirmations[] = $r['status'] === 'accepted' ? $this->l('Accepted by the service') : sprintf($this->l('Status %s — %s'), $r['status'], isset($r['error']) ? $r['error'] : ''); }
            if (Tools::isSubmit('drainEinvoice')) { $r = PulseAccTax::einvoiceDrain(50); $this->confirmations[] = sprintf($this->l('Sent %d, still pending %d'), $r['sent'], $r['pending']); }
            if (Tools::isSubmit('requeueEinvoice')) { Db::getInstance()->update('pulse_acc_einvoice', array('status' => 'queued', 'attempts' => 0, 'last_error' => null, 'next_retry_at' => null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_acc_einvoice='.(int) Tools::getValue('id_einvoice_s')); $this->confirmations[] = $this->l('Back on the queue'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
