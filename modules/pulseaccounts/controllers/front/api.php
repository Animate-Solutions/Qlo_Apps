<?php
/** /pulse/api/accounts/{resource}/{id} — finance figures for owner dashboards, the mobile app and the group office. */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';
require_once _PS_MODULE_DIR_.'pulseaccounts/classes/autoload.php';
class PulseAccountsApiModuleFrontController extends PulseApiController
{
    protected $resources = array('ping' => 'ping', 'trial_balance' => 'trialBalance', 'pl' => 'pl', 'ar_ageing' => 'arAgeing', 'ap_ageing' => 'apAgeing', 'post_queue' => 'postQueue', 'journal' => 'journal');

    protected function ping() { return array('module' => 'pulseaccounts', 'business_date' => PulseAccService::bd(), 'period' => PulseAccService::period(PulseAccService::bd()), 'currency' => PulseAccService::currency()); }

    protected function trialBalance()
    {
        $this->requireScope('finance');
        $from = Tools::getValue('from', date('Y-m-01'));
        $to = Tools::getValue('to', PulseAccService::bd());
        return PulseAccReport::trialBalance($from, $to, (bool) Tools::getValue('include_zero'));
    }

    /** USALI departmental P&L down to GOP and EBITDA. */
    protected function pl()
    {
        $this->requireScope('finance');
        return PulseAccReport::usaliPl(Tools::getValue('from', date('Y-m-01')), Tools::getValue('to', PulseAccService::bd()), (bool) Tools::getValue('compare'));
    }

    protected function arAgeing() { $this->requireScope('finance'); $asOf = Tools::getValue('as_of', date('Y-m-d')); return array('as_of' => $asOf, 'summary' => PulseAccAr::ageingSummary($asOf), 'by_company' => PulseAccAr::ageing($asOf), 'stop_list' => PulseAccAr::stopList($asOf)); }

    protected function apAgeing() { $this->requireScope('finance'); $asOf = Tools::getValue('as_of', date('Y-m-d')); return array('as_of' => $asOf, 'summary' => PulseAccAp::ageingSummary($asOf), 'by_supplier' => PulseAccAp::ageing($asOf)); }

    /** What is waiting to post, and what failed — the health check a group office polls. */
    protected function postQueue($id, $body)
    {
        $this->requireScope('finance');
        if (Tools::getValue('drain') || !empty($body['drain'])) {
            $date = Tools::getValue('business_date', PulseAccService::bd());
            PulseAccPosting::sweep($date);
            return array_merge(PulseAccPosting::drain((int) Configuration::get('PULSE_ACC_QUEUE_BATCH')), array('counts' => PulseAccPosting::queueCounts()));
        }
        return array('counts' => PulseAccPosting::queueCounts(), 'pending' => PulseAccPosting::queue('pending', 50), 'failed' => PulseAccPosting::queue('failed', 50), 'unmapped' => PulseAccService::unmapped());
    }

    /** GET one journal with its lines, or POST a balanced manual journal. */
    protected function journal($id, $body)
    {
        $this->requireScope('finance');
        if ($id) { $j = PulseAccJournal::get($id); if (!$j) { throw new PrestaShopException('Unknown journal', 404); } return $j; }
        if (!empty($body['lines'])) {
            $idJ = PulseAccJournal::post(array(
                'type' => isset($body['type']) ? $body['type'] : 'general', 'source' => 'manual', 'source_ref' => isset($body['source_ref']) ? $body['source_ref'] : null,
                'business_date' => isset($body['business_date']) ? $body['business_date'] : PulseAccService::bd(),
                'reference' => isset($body['reference']) ? $body['reference'] : '', 'memo' => isset($body['memo']) ? $body['memo'] : '',
                'status' => !empty($body['draft']) ? 'draft' : 'posted', 'lines' => $body['lines'],
            ));
            return PulseAccJournal::get($idJ);
        }
        return PulseAccJournal::search(array('from' => Tools::getValue('from', date('Y-m-01')), 'to' => Tools::getValue('to', PulseAccService::bd()), 'source' => Tools::getValue('source'), 'status' => Tools::getValue('status')), 200);
    }
}
