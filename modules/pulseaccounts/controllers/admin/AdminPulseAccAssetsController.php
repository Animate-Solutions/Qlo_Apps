<?php
/** Fixed assets: register linked to the engineering asset list, depreciation runs, disposals, WDV register, forecast and CAPEX vs budget. */
class AdminPulseAccAssetsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Fixed Assets'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_asset')) {
            $this->context->smarty->assign(array('a' => PulseAccAssets::asset($id), 'classes' => PulseAccAssets::classes(), 'self_url' => $self, 'currency' => $this->context->currency->sign,
                'accounts' => PulseAccService::accounts(null, true, true), 'banks' => PulseAccService::bankAccounts(), 'business_date' => PulseAccService::bd(),
                'link_journals' => $this->context->link->getAdminLink('AdminPulseAccJournals'), 'mnt' => PulseAccService::mnt()));
            return $this->setTemplate('asset.tpl');
        }
        if ($r = Tools::getValue('export')) {
            if ($r === 'wdv') { $w = PulseAccAssets::wdvRegister(); $rows = $w['rows']; }
            elseif ($r === 'forecast') { $rows = array(); foreach (PulseAccAssets::forecast(12) as $f) { $rows[] = array_merge(array('period' => $f['period'], 'total' => $f['total']), $f['by_class']); } }
            else { $rows = PulseAccAssets::capexVsBudget((int) Tools::getValue('year', date('Y'))); }
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="assets-'.$r.'.csv"');
            die(PulseAccService::toCsv($rows));
        }
        $period = Tools::getValue('period', date('Y-m', strtotime('-1 month')));
        $this->context->smarty->assign(array(
            'assets' => PulseAccAssets::assets(array('class' => Tools::getValue('class'), 'status' => Tools::getValue('status'), 'q' => Tools::getValue('q'), 'include_disposed' => Tools::getValue('include_disposed'))),
            'classes' => PulseAccAssets::classes(false), 'wdv' => PulseAccAssets::wdvRegister(), 'runs' => PulseAccAssets::runs(24),
            'preview' => Tools::getValue('preview') ? PulseAccAssets::runDepreciation($period, true) : null, 'period' => $period, 'periods' => PulseAccService::periods(24),
            'forecast' => PulseAccAssets::forecast(12), 'capex' => PulseAccAssets::capexVsBudget((int) Tools::getValue('year', date('Y'))), 'year' => (int) Tools::getValue('year', date('Y')),
            'unlinked' => PulseAccAssets::unlinkedEngineeringAssets(50), 'accounts' => PulseAccService::accounts(null, true, true),
            'rooms' => PulseAccService::fd() ? Db::getInstance()->executeS('SELECT id id_room, room_num FROM `'._DB_PREFIX_.'htl_room_information` ORDER BY room_num') : array(),
            'departments' => PulseAccService::maps('department'), 'self_url' => $self, 'currency' => $this->context->currency->sign,
            'business_date' => PulseAccService::bd(), 'mnt' => PulseAccService::mnt(),
        ));
        $this->setTemplate('assets.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('addAsset')) {
                $id = PulseAccAssets::addAsset(array(
                    'class_code' => Tools::getValue('class_code'), 'name' => Tools::getValue('name'), 'code' => Tools::getValue('code'),
                    'id_pulse_asset' => Tools::getValue('id_pulse_asset'), 'id_parent' => Tools::getValue('id_parent'), 'id_room' => Tools::getValue('id_room'),
                    'location' => Tools::getValue('location'), 'cost_centre' => Tools::getValue('cost_centre'), 'department' => Tools::getValue('cost_centre'),
                    'supplier' => Tools::getValue('supplier'), 'invoice_ref' => Tools::getValue('invoice_ref'), 'serial_no' => Tools::getValue('serial_no'),
                    'acquisition_date' => Tools::getValue('acquisition_date'), 'in_service_date' => Tools::getValue('in_service_date'),
                    'cost' => Tools::getValue('cost'), 'residual_value' => Tools::getValue('residual_value'), 'method' => Tools::getValue('method'),
                    'life_months' => Tools::getValue('life_months'), 'rate_pct' => Tools::getValue('rate_pct'), 'units_total' => Tools::getValue('units_total'),
                    'credit_account' => Tools::getValue('credit_account'), 'capex_budget_line' => Tools::getValue('capex_budget_line'), 'note' => Tools::getValue('note'),
                ));
                Tools::redirectAdmin(self::$currentIndex.'&token='.$this->token.'&id_asset='.(int) $id.'&conf=3');
            }
            if (Tools::isSubmit('updateAsset')) {
                PulseAccAssets::updateAsset((int) Tools::getValue('id_asset_s'), array(
                    'name' => Tools::getValue('name'), 'location' => Tools::getValue('location'), 'cost_centre' => Tools::getValue('cost_centre'),
                    'department' => Tools::getValue('cost_centre'), 'supplier' => Tools::getValue('supplier'), 'serial_no' => Tools::getValue('serial_no'),
                    'id_room' => Tools::getValue('id_room'), 'id_pulse_asset' => Tools::getValue('id_pulse_asset'), 'life_months' => Tools::getValue('life_months'),
                    'residual_value' => Tools::getValue('residual_value'), 'rate_pct' => Tools::getValue('rate_pct'), 'method' => Tools::getValue('method'),
                    'units_total' => Tools::getValue('units_total'), 'units_used' => Tools::getValue('units_used'), 'status' => Tools::getValue('status'), 'note' => Tools::getValue('note'),
                ));
                $this->confirmations[] = $this->l('Asset updated');
            }
            if (Tools::isSubmit('runDepreciation')) { $r = PulseAccAssets::runDepreciation(Tools::getValue('period_s')); $this->confirmations[] = sprintf($this->l('Depreciation for %s: %d assets, %s posted across %d journals'), $r['period'], $r['assets'], number_format($r['total'], 2), count($r['journals'])); }
            if (Tools::isSubmit('transferAsset')) { PulseAccAssets::transfer((int) Tools::getValue('id_asset_s'), Tools::getValue('cost_centre'), Tools::getValue('location'), (int) Tools::getValue('id_room') ?: null, Tools::getValue('note')); $this->confirmations[] = $this->l('Asset transferred'); }
            if (Tools::isSubmit('revalueAsset')) { PulseAccAssets::revalue((int) Tools::getValue('id_asset_s'), Tools::getValue('amount'), Tools::getValue('event_date'), Tools::getValue('note')); $this->confirmations[] = $this->l('Revaluation posted'); }
            if (Tools::isSubmit('impairAsset')) { PulseAccAssets::impair((int) Tools::getValue('id_asset_s'), Tools::getValue('amount'), Tools::getValue('event_date'), Tools::getValue('note')); $this->confirmations[] = $this->l('Impairment posted'); }
            if (Tools::isSubmit('disposeAsset')) { $r = PulseAccAssets::dispose((int) Tools::getValue('id_asset_s'), Tools::getValue('proceeds'), Tools::getValue('event_date'), Tools::getValue('note'), Tools::getValue('proceeds_account')); $this->confirmations[] = sprintf($this->l('Disposed — %s of %s on net book value'), $r['gain'] >= 0 ? $this->l('gain') : $this->l('loss'), number_format(abs($r['gain']), 2)); }
            if (Tools::isSubmit('writeOffAsset')) { PulseAccAssets::writeOff((int) Tools::getValue('id_asset_s'), Tools::getValue('event_date'), Tools::getValue('note')); $this->confirmations[] = $this->l('Asset written off'); }
            if (Tools::isSubmit('saveClass')) {
                PulseAccAssets::saveClass(array('id_pulse_acc_asset_class' => (int) Tools::getValue('id_class'), 'code' => Tools::getValue('c_code'), 'name' => Tools::getValue('c_name'),
                    'method' => Tools::getValue('c_method'), 'life_months' => Tools::getValue('c_life'), 'rate_pct' => Tools::getValue('c_rate'), 'residual_pct' => Tools::getValue('c_residual'),
                    'asset_account' => Tools::getValue('c_asset'), 'accum_account' => Tools::getValue('c_accum'), 'expense_account' => Tools::getValue('c_expense'),
                    'capital_allowance_note' => Tools::getValue('c_note'), 'active' => Tools::getValue('c_active', 1), 'sort' => Tools::getValue('c_sort')));
                $this->confirmations[] = $this->l('Asset class saved');
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
