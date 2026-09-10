<?php
/** CRM dashboard: today's arrivals with their flags, NPS trend, campaign performance, loyalty liability and the open cases. */
class AdminPulseCrmController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('CRM'); }

    public function initContent()
    {
        parent::initContent();
        $bd = PulseCrmService::bd();
        $from = Tools::getValue('from', date('Y-m-d', strtotime('-90 day'))); $to = Tools::getValue('to', $bd);
        $this->context->smarty->assign(array(
            'business_date' => $bd, 'from' => $from, 'to' => $to,
            'k' => PulseCrmService::kpis(90),
            'arrivals' => PulseCrmService::arrivalsBoard($bd),
            'nps_trend' => PulseCrmService::npsTrend(12),
            'dept' => PulseCrmSurvey::departmentScores($from, $to),
            'rate' => PulseCrmSurvey::responseRate($from, $to),
            'campaigns' => PulseCrmCampaign::all('sending,sent,scheduled'),
            'liability' => PulseCrmLoyalty::liability(),
            'cases' => PulseCrmCase::all('open,investigating,recovering,escalated'),
            'cost' => PulseCrmCase::costReport($from, $to),
            'reviews' => PulseCrmReview::bySource($from, $to),
            'needs_reply' => PulseCrmReview::needsReply(10),
            'occasions' => PulseCrmProfile::occasionsDue(),
            'follow_ups' => PulseCrmCorporate::followUps(7),
            'fd' => PulseCrmService::fd(),
            'self_url' => self::$currentIndex.'&token='.$this->token,
            'guests_url' => $this->context->link->getAdminLink('AdminPulseCrmGuests'),
            'cases_url' => $this->context->link->getAdminLink('AdminPulseCrmCases'),
            'reviews_url' => $this->context->link->getAdminLink('AdminPulseCrmReviews'),
            'campaigns_url' => $this->context->link->getAdminLink('AdminPulseCrmCampaigns'),
        ));
        $this->setTemplate('dashboard.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('refreshSegments')) { $r = PulseCrmSegment::refreshAll(); $this->confirmations[] = sprintf($this->l('%1$d segments refreshed, %2$d memberships'), $r['segments'], $r['members']); }
            if (Tools::isSubmit('advanceJourneys')) { $r = PulseCrmJourney::advance(100); $this->confirmations[] = sprintf($this->l('%1$d journey steps run, %2$d sent'), $r['steps'], $r['sent']); }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
