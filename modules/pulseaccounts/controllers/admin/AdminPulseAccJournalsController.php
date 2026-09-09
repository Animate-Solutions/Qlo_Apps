<?php
/** Journals: browse and filter, open one with its lines and source document, key a manual entry, reverse a posted one. */
class AdminPulseAccJournalsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Journals'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_journal')) {
            $j = PulseAccJournal::get($id);
            $src = null;
            if ($j) { foreach ($j['lines'] as $l) { if ($l['entity'] && $l['id_entity']) { $src = array('entity' => $l['entity'], 'row' => PulseAccReport::sourceDocument($l['entity'], (int) $l['id_entity'])); break; } } }
            $this->context->smarty->assign(array('j' => $j, 'src' => $src, 'self_url' => $self, 'currency' => $this->context->currency->sign, 'reversal' => $j && $j['reversed_by'] ? PulseAccJournal::get((int) $j['reversed_by']) : null));
            return $this->setTemplate('journal.tpl');
        }
        $f = array('from' => Tools::getValue('from', date('Y-m-01')), 'to' => Tools::getValue('to', PulseAccService::bd()), 'source' => Tools::getValue('source'), 'status' => Tools::getValue('status'), 'q' => Tools::getValue('q'));
        if (Tools::getValue('export')) {
            $rows = array();
            foreach (PulseAccJournal::search($f, 5000) as $j) { $rows[] = array('journal_no' => $j['journal_no'], 'date' => $j['business_date'], 'period' => $j['period'], 'type' => $j['type'], 'source' => $j['source'], 'source_ref' => $j['source_ref'], 'reference' => $j['reference'], 'memo' => $j['memo'], 'debit' => $j['total_debit'], 'credit' => $j['total_credit'], 'status' => $j['status']); }
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="journals-'.$f['from'].'-'.$f['to'].'.csv"');
            die(PulseAccService::toCsv($rows));
        }
        $this->context->smarty->assign(array(
            'journals' => PulseAccJournal::search($f, 300), 'f' => $f, 'self_url' => $self, 'currency' => $this->context->currency->sign,
            'accounts' => PulseAccService::accounts(null, true, true), 'business_date' => PulseAccService::bd(), 'periods' => PulseAccService::periods(12),
            'sources' => array('folio', 'pos', 'expense', 'grn', 'bill', 'ap_payment', 'ar_receipt', 'inventory', 'payroll', 'depreciation', 'asset', 'manual', 'fx', 'opening', 'closing', 'bank', 'tax'),
            'departments' => PulseAccService::maps('department'),
        ));
        $this->setTemplate('journals.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveManual')) {
                $id = PulseAccJournal::manual(array(
                    'account' => Tools::getValue('account'), 'debit' => Tools::getValue('debit'), 'credit' => Tools::getValue('credit'),
                    'line_memo' => Tools::getValue('line_memo'), 'cost_centre' => Tools::getValue('cost_centre'), 'type' => Tools::getValue('type', 'general'),
                    'business_date' => Tools::getValue('business_date'), 'reference' => Tools::getValue('reference'), 'memo' => Tools::getValue('memo'),
                    'as_draft' => Tools::getValue('as_draft'),
                ));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_journal='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('postDraft')) { PulseAccJournal::postDraft((int) Tools::getValue('id_journal_s')); $this->confirmations[] = $this->l('Journal posted'); }
            if (Tools::isSubmit('deleteDraft')) { PulseAccJournal::deleteDraft((int) Tools::getValue('id_journal_s')); $this->confirmations[] = $this->l('Draft deleted'); }
            if (Tools::isSubmit('reverseJournal')) { $id = PulseAccJournal::reverse((int) Tools::getValue('id_journal_s'), Tools::getValue('reason'), Tools::getValue('reverse_date') ?: null); $this->confirmations[] = $this->l('Reversed with a contra journal'); Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_journal='.(int) $id.'&conf=4'); }
            if (Tools::isSubmit('postPayroll')) {
                $depts = array(); foreach ((array) Tools::getValue('payroll') as $dept => $amt) { if ((float) $amt > 0) { $depts[$dept] = (float) $amt; } }
                $ded = array('paye' => (float) Tools::getValue('paye'), 'pension' => (float) Tools::getValue('pension'), 'nsitf' => (float) Tools::getValue('nsitf'), 'other' => (float) Tools::getValue('ded_other'));
                $id = PulseAccPosting::payroll(Tools::getValue('payroll_period'), $depts, $ded);
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_journal='.(int) $id.'&conf=3');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
