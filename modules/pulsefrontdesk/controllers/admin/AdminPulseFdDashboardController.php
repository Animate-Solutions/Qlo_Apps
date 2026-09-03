<?php
/** Front-desk landing page: KPIs, arrivals/departures today, due traces, HK summary. */
class AdminPulseFdDashboardController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Front Desk'); }

    public function initContent()
    {
        parent::initContent();
        $bd = PulseCoreService::businessDate();
        $board = PulseRoom::board();
        $k = array('rooms' => count($board), 'occupied' => 0, 'vacant_clean' => 0, 'vacant_dirty' => 0, 'ooo' => 0, 'due_in' => 0, 'due_out' => 0);
        foreach ($board as $r) {
            if ($r['fo_status'] === 'occupied') { $k['occupied']++; }
            if (in_array($r['hk_status'], array('vacant_clean', 'vacant_inspected'))) { $k['vacant_clean']++; }
            if ($r['hk_status'] === 'vacant_dirty') { $k['vacant_dirty']++; }
            if (in_array($r['hk_status'], array('out_of_order', 'out_of_service'))) { $k['ooo']++; }
        }
        $arrivals = PulseFdService::arrivals($bd); $departures = PulseFdService::departures($bd);
        $k['due_in'] = count($arrivals); $k['due_out'] = count($departures);
        $k['occupancy_pct'] = $k['rooms'] - $k['ooo'] ? round($k['occupied'] / ($k['rooms'] - $k['ooo']) * 100, 1) : 0;
        $this->context->smarty->assign(array(
            'business_date' => $bd, 'kpi' => $k, 'arrivals' => $arrivals, 'departures' => $departures,
            'traces' => PulseTrace::due(24), 'hk_open' => count(PulseHousekeeping::queue()),
            'forecast' => PulseFdReport::forecast(7), 'ledger' => PulseFdReport::guestLedger(),
            'links' => $this->links(),
        ));
        $this->setTemplate('dashboard.tpl');
    }

    protected function links()
    {
        $o = array();
        foreach (array('AdminPulseRoomBoard', 'AdminPulseArrivals', 'AdminPulseFolio', 'AdminPulseHousekeeping', 'AdminPulseNightAudit', 'AdminPulseFdReports', 'AdminPulseGuestProfile') as $c) { $o[$c] = $this->context->link->getAdminLink($c); }
        return $o;
    }
}
