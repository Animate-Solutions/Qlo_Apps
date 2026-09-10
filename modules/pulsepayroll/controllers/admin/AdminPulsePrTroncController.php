<?php
/** Service charge: the pool, the distribution by points or hours with department weightings and the management cap, and per-employee statements. */
class AdminPulsePrTroncController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Service Charge'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        if ($id = (int) Tools::getValue('id_pool')) {
            $p = PulsePrTronc::pool($id);
            if (!$p) { $this->errors[] = $this->l('Unknown pool'); return $this->setTemplate('tronc.tpl'); }
            if (Tools::getValue('export')) {
                header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="service-charge-'.$p['pool_no'].'.csv"');
                die(PulsePrService::toCsv($p['lines']));
            }
            $this->context->smarty->assign(array('pool' => $p, 'weights' => PulsePrTronc::weights(), 'employees' => PulsePrService::employees(array('status' => 'active,probation,on_leave')), 'self_url' => $self, 'currency' => $this->context->currency->sign));
            return $this->setTemplate('pool.tpl');
        }
        $this->context->smarty->assign(array(
            'pools' => PulsePrTronc::pools(), 'weights' => PulsePrTronc::weights(), 'departments' => PulsePrService::departments(),
            'period' => Tools::getValue('period', date('Y-m')), 'self_url' => $self, 'currency' => $this->context->currency->sign,
            'collected' => PulsePrTronc::collected(PulsePrService::periodFrom(Tools::getValue('period', date('Y-m'))), PulsePrService::periodTo(Tools::getValue('period', date('Y-m')))),
            'pos' => PulsePrService::pos(), 'fd' => PulsePrService::fd(),
            'history' => PulsePrReport::troncHistory((int) date('Y')),
            'default_admin' => PulsePrService::cfg('TRONC_ADMIN_PCT', 0), 'default_cap' => PulsePrService::cfg('TRONC_MGMT_CAP_PCT', 10), 'default_basis' => PulsePrService::cfg('TRONC_BASIS', 'points'),
        ));
        $this->setTemplate('tronc.tpl');
    }

    public function postProcess()
    {
        $self = self::$currentIndex.'&token='.$this->token;
        try {
            if (Tools::isSubmit('createPool')) {
                $id = PulsePrTronc::createPool(array('period' => Tools::getValue('period'), 'basis' => Tools::getValue('basis'), 'collected_manual' => Tools::getValue('collected_manual'), 'admin_pct' => Tools::getValue('admin_pct'), 'breakage_amount' => Tools::getValue('breakage_amount'), 'management_cap_pct' => Tools::getValue('management_cap_pct'), 'note' => Tools::getValue('note')));
                Tools::redirectAdmin($self.'&id_pool='.$id.'&conf=3');
            }
            if (Tools::isSubmit('updatePool')) { PulsePrTronc::updatePool((int) Tools::getValue('id_pool_a'), array('collected_fnb' => Tools::getValue('collected_fnb'), 'collected_rooms' => Tools::getValue('collected_rooms'), 'collected_manual' => Tools::getValue('collected_manual'), 'admin_pct' => Tools::getValue('admin_pct'), 'breakage_amount' => Tools::getValue('breakage_amount'), 'management_cap_pct' => Tools::getValue('management_cap_pct'), 'basis' => Tools::getValue('basis'), 'note' => Tools::getValue('note'))); $this->confirmations[] = $this->l('Pool updated'); }
            if (Tools::isSubmit('distributePool')) {
                $participants = array();
                foreach ((array) Tools::getValue('points') as $idEmp => $pts) {
                    $ex = Tools::getValue('exclude'); $hours = Tools::getValue('hours');
                    $participants[] = array('id_pulse_pr_employee' => (int) $idEmp, 'points' => $pts, 'hours' => isset($hours[$idEmp]) ? $hours[$idEmp] : '', 'exclude' => isset($ex[$idEmp]) ? 1 : 0);
                }
                $r = PulsePrTronc::distribute((int) Tools::getValue('id_pool_a'), $participants);
                $this->confirmations[] = sprintf($this->l('Distributed %1$s across %2$d participants; management capped by %3$s; rounding residue %4$s'), number_format($r['distributed'], 2), $r['participants'], number_format($r['capped'], 2), number_format($r['residue'], 2));
            }
            if (Tools::isSubmit('approvePool')) { PulsePrTronc::approve((int) Tools::getValue('id_pool_a')); $this->confirmations[] = $this->l('Pool approved — the shares will be picked up by the next payroll run for that period'); }
            if (Tools::isSubmit('saveWeight')) { PulsePrTronc::saveWeight(array('department' => Tools::getValue('wdepartment'), 'weight' => Tools::getValue('weight'), 'default_points' => Tools::getValue('default_points'), 'is_management' => Tools::getValue('is_management'), 'active' => 1)); $this->confirmations[] = $this->l('Department weighting saved'); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
