<?php
/** Weekly casual and per-shift pay: build the week from approved timesheets, adjust, approve, print the pay-out sheet. */
class AdminPulsePrCasualController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Casual & Weekly Pay'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_batch')) {
            $b = PulsePrCasual::batch($id);
            if (!$b) { $this->errors[] = $this->l('Unknown batch'); return $this->setTemplate('casual.tpl'); }
            if (Tools::getValue('export')) {
                header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="casual-'.$b['batch_no'].'.csv"');
                die(PulsePrService::toCsv(PulsePrCasual::payoutSheet($id)));
            }
            $this->context->smarty->assign(array(
                'b' => $b, 'sheet' => PulsePrCasual::payoutSheet($id), 'departments' => PulsePrService::departments(),
                'casuals' => PulsePrService::employees(array('employment_type' => 'casual,service', 'status' => 'active,probation')),
                'files' => Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_bank_file` WHERE id_pulse_pr_casual_batch='.(int) $id.' ORDER BY id_pulse_pr_bank_file DESC'),
                'tax_pct' => PulsePrService::cfg('CASUAL_TAX_PCT', 0), 'day_rate' => PulsePrService::cfg('CASUAL_DAY_RATE', 7500),
                'ta' => PulsePrService::ta(), 'acc' => PulsePrService::acc(), 'self_url' => $self, 'currency' => $this->context->currency->sign,
            ));
            return $this->setTemplate('batch.tpl');
        }
        $this->context->smarty->assign(array(
            'batches' => PulsePrCasual::batches(), 'departments' => PulsePrService::departments(), 'self_url' => $self,
            'week_start' => Tools::getValue('week_start', date('Y-m-d', strtotime('monday this week'))),
            'ta' => PulsePrService::ta(), 'currency' => $this->context->currency->sign,
        ));
        $this->setTemplate('casual.tpl');
    }

    public function postProcess()
    {
        $self = self::$currentIndex.'&token='.$this->token;
        try {
            if (Tools::isSubmit('createBatch')) {
                $id = PulsePrCasual::createBatch(array('week_start' => Tools::getValue('week_start'), 'week_end' => Tools::getValue('week_end'), 'department' => Tools::getValue('department'), 'pay_method' => Tools::getValue('pay_method'), 'pay_date' => Tools::getValue('pay_date'), 'note' => Tools::getValue('note')));
                Tools::redirectAdmin($self.'&id_batch='.$id.'&conf=3');
            }
            if (Tools::isSubmit('pullBatch')) { $n = PulsePrCasual::pullFromTimesheets((int) Tools::getValue('id_batch_a')); $this->confirmations[] = sprintf($this->l('%d casual workers added from the roster'), $n); }
            if (Tools::isSubmit('saveLine')) { PulsePrCasual::saveLine(array('id_pulse_pr_casual_batch' => (int) Tools::getValue('id_batch_a'), 'id_pulse_pr_casual_line' => (int) Tools::getValue('id_line'), 'id_pulse_pr_employee' => Tools::getValue('id_pulse_pr_employee'), 'staff_no' => Tools::getValue('staff_no'), 'name' => Tools::getValue('name'), 'phone' => Tools::getValue('phone'), 'department' => Tools::getValue('cdepartment'), 'role' => Tools::getValue('role'), 'basis' => Tools::getValue('basis'), 'units' => Tools::getValue('units'), 'rate' => Tools::getValue('rate'), 'other_deduction' => Tools::getValue('other_deduction'), 'bank_code' => Tools::getValue('bank_code'), 'account_no' => Tools::getValue('account_no'), 'note' => Tools::getValue('lnote'))); $this->confirmations[] = $this->l('Line saved'); }
            if (Tools::isSubmit('bulkUnits')) { $this->bulkUnits((int) Tools::getValue('id_batch_a')); $this->confirmations[] = $this->l('Days and rates updated'); }
            if (Tools::isSubmit('deleteLine')) { PulsePrCasual::deleteLine((int) (Tools::getValue('deleteLine') ?: Tools::getValue('id_line'))); $this->confirmations[] = $this->l('Line removed'); }
            if (Tools::isSubmit('approveBatch')) { PulsePrCasual::approve((int) Tools::getValue('id_batch_a')); $this->confirmations[] = $this->l('Batch approved'); }
            if (Tools::isSubmit('payBatch')) { PulsePrCasual::markPaid((int) Tools::getValue('id_batch_a')); $this->confirmations[] = $this->l('Batch marked as paid'); }
            if (Tools::isSubmit('postBatch')) { $j = PulsePrCasual::post((int) Tools::getValue('id_batch_a')); $this->confirmations[] = $j ? sprintf($this->l('Posted to the general ledger (journal %d)'), $j) : $this->l('Pulse Accounts is not installed — nothing was posted'); }
            if (Tools::isSubmit('batchBankFile')) { $ids = PulsePrBankFile::generateForBatch((int) Tools::getValue('id_batch_a'), Tools::getValue('split', 'bank')); $this->confirmations[] = sprintf($this->l('%d payment file(s) generated'), count($ids)); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }

    /** The fast path: one grid, key the days for the whole week, save once. */
    protected function bulkUnits($idBatch)
    {
        $units = (array) Tools::getValue('u'); $rates = (array) Tools::getValue('r');
        foreach ($units as $idLine => $u) {
            $l = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_casual_line` WHERE id_pulse_pr_casual_line='.(int) $idLine);
            if (!$l || (int) $l['id_pulse_pr_casual_batch'] !== (int) $idBatch) { continue; }
            PulsePrCasual::saveLine(array_merge($l, array('id_pulse_pr_casual_line' => (int) $idLine, 'units' => $u, 'rate' => isset($rates[$idLine]) ? $rates[$idLine] : $l['rate'])));
        }
        return true;
    }
}
