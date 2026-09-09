<?php
/**
 * The one CRM job: refresh segments, fire the calendar triggers, advance journeys, send scheduled
 * campaigns, expire points, re-tier members and raise occasion reminders.
 * Every pass is chunked and stateless, so a shared host that kills the request halfway loses nothing —
 * the next run picks up exactly where this one stopped. Run it every fifteen minutes.
 * Usage: php cron/crm.php <token> [task]   (or in a browser with ?token=<PULSE_CRM_CRON_TOKEN>&task=<task>)
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if (!hash_equals((string) Configuration::get('PULSE_CRM_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$task = isset($argv[2]) ? $argv[2] : Tools::getValue('task', 'all');
$t0 = microtime(true);
$out = array();

if ($task === 'all' || $task === 'segments') {
    $r = PulseCrmSegment::refreshAll((int) PulseCrmService::cfg('SEGMENT_CHUNK', 20));
    $out[] = 'segments refreshed: '.$r['segments'].' ('.$r['members'].' memberships)';
}

if ($task === 'all' || $task === 'journeys') {
    $t = PulseCrmJourney::triggerScheduled();
    $out[] = 'triggered: '.$t['mid_stay'].' mid-stay, '.$t['lapsed'].' win-back, '.$t['birthday'].' birthday, '.$t['anniversary'].' anniversary';
    $a = PulseCrmJourney::advance((int) PulseCrmService::cfg('JOURNEY_CHUNK', 100));
    $out[] = 'journeys: '.$a['runs'].' runs, '.$a['steps'].' steps, '.$a['sent'].' sent, '.$a['skipped'].' skipped, '.$a['failed'].' failed, '.$a['finished'].' finished';
}

if ($task === 'all' || $task === 'campaigns') {
    $c = PulseCrmCampaign::runDue(10);
    $out[] = 'campaigns: '.$c['campaigns'].' run, '.$c['sent'].' sent, '.$c['failed'].' failed, '.$c['skipped'].' skipped';
}

if ($task === 'all' || $task === 'loyalty') {
    $e = PulseCrmLoyalty::expirePoints(500);
    $n = PulseCrmLoyalty::recalcTiersDue((int) PulseCrmService::cfg('JOURNEY_CHUNK', 100));
    $out[] = 'loyalty: '.$e['expired'].' points expired, '.$e['warned'].' warned, '.$n.' members re-tiered';
}

if ($task === 'all' || $task === 'occasions') {
    // Occasions the journeys did not pick up still deserve a trace on the arrivals desk.
    $traced = 0;
    foreach (PulseCrmProfile::occasionsDue() as $o) {
        if ($o['last_reminded'] === date('Y-m-d')) { continue; }
        if (class_exists('PulseTrace')) {
            PulseTrace::add('reminder', ucfirst($o['type']).' for '.$o['guest'].' on '.date('j F', strtotime($o['occasion_date'])).($o['note'] ? ' — '.$o['note'] : ''),
                date('Y-m-d H:i:s'), null, null, (int) $o['id_customer'], 'frontdesk');
            $traced++;
        }
        Db::getInstance()->update('pulse_crm_occasion', array('last_reminded' => date('Y-m-d')), 'id_pulse_crm_occasion='.(int) $o['id_pulse_crm_occasion']);
    }
    $out[] = 'occasion reminders raised: '.$traced;
}

if ($task === 'all' || $task === 'housekeeping') {
    // Expire survey links nobody used, and prune the suppression log — it only has to remember a few weeks.
    Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_crm_survey_response` SET status="expired" WHERE status IN ("pending","partial") AND expires_on IS NOT NULL AND expires_on<CURDATE()');
    Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_send_log` WHERE send_date<DATE_SUB(CURDATE(), INTERVAL 90 DAY)');
    Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_crm_journey_log` WHERE date_add<DATE_SUB(NOW(), INTERVAL 180 DAY)');
    $overdue = count(PulseCrmCase::overdue());
    $out[] = 'housekeeping done; recovery cases past SLA: '.$overdue;
}

$secs = round(microtime(true) - $t0, 1);
PulseCoreService::audit('pulsecrm', 'cron', array('task' => $task, 'seconds' => $secs, 'lines' => $out));
echo 'Pulse CRM cron ('.$task.') in '.$secs."s\n";
foreach ($out as $line) { echo ' · '.$line."\n"; }
