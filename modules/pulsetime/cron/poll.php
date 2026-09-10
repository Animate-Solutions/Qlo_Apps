<?php
/**
 * Every 5–10 minutes: poll pull-mode devices, health-check push devices, drain the provisioning queue and
 * tidy the push traffic log.
 *   php modules/pulsetime/cron/poll.php <token>
 *   or in a browser / crontab with ?token=<PULSE_TA_CRON_TOKEN>
 *
 * Nothing in here may throw past the loop: one dead reader must not stop the other four being read.
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if (!hash_equals((string) Configuration::get('PULSE_TA_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);

$t0 = microtime(true);
$poll = PulseTaDevice::pollAll(false);
$queue = PulseTaService::runQueue(50);
$purge = PulseTaAdms::purge();

// Devices nobody has heard from become an exception a supervisor sees, not a silent gap in the roster.
$stale = (int) PulseTaService::cfg('DEVICE_STALE_MIN', 60);
$flagged = 0;
foreach (Db::getInstance()->executeS('SELECT id_pulse_ta_device, name, location, last_seen_at FROM `'._DB_PREFIX_.'pulse_ta_device`
    WHERE status="active" AND (last_seen_at IS NULL OR last_seen_at<DATE_SUB(NOW(), INTERVAL '.max(5, $stale).' MINUTE))') as $d) {
    PulseTaExceptionQueue::raise(null, PulseTaService::bd(), 'device_offline', 'warn',
        'Clocking device "'.$d['name'].'" ('.$d['location'].') last reported '.($d['last_seen_at'] ? $d['last_seen_at'] : 'never').'.',
        0, null, null, 'dev'.(int) $d['id_pulse_ta_device']);
    $flagged++;
}

// Rebuild today's timesheets for anyone whose punches moved, so the Live Board and the exception queue are current.
$today = PulseTaService::bd();
$rebuilt = 0;
if ($poll['stored'] > 0) {
    foreach (Db::getInstance()->executeS('SELECT DISTINCT p.id_pulse_ta_staff, p.business_date FROM `'._DB_PREFIX_.'pulse_ta_punch` p
        WHERE p.id_pulse_ta_staff IS NOT NULL AND p.date_add>DATE_SUB(NOW(), INTERVAL 30 MINUTE) AND p.business_date>=DATE_SUB("'.pSQL($today).'", INTERVAL 3 DAY)') as $r) {
        try { PulseTaEngine::buildOne((int) $r['id_pulse_ta_staff'], $r['business_date']); $rebuilt++; }
        catch (Exception $e) { PulseCoreService::audit('pulsetime', 'cron_build_failed', array('staff' => (int) $r['id_pulse_ta_staff'], 'date' => $r['business_date'], 'error' => $e->getMessage())); }
    }
}

$ms = (int) round((microtime(true) - $t0) * 1000);
PulseCoreService::audit('pulsetime', 'cron_poll', array('devices' => $poll['devices'], 'pulled' => $poll['pulled'], 'stored' => $poll['stored'],
    'queue_done' => $queue['done'], 'queue_failed' => $queue['failed'], 'rebuilt' => $rebuilt, 'flagged' => $flagged, 'ms' => $ms));

echo 'Devices polled: '.$poll['devices'].', punches read: '.$poll['pulled'].', new: '.$poll['stored']
    .', timesheets rebuilt: '.$rebuilt.', queue done/retried/failed: '.$queue['done'].'/'.$queue['retried'].'/'.$queue['failed']
    .', devices flagged silent: '.$flagged.', traffic rows purged: '.$purge['log_rows'].', commands expired: '.$purge['commands_expired']
    .' ('.$ms."ms)\n";
if ($poll['errors']) { echo 'Device problems: '.implode(' | ', array_slice($poll['errors'], 0, 8))."\n"; }
if ($queue['errors']) { echo 'Queue problems: '.implode(' | ', array_slice($queue['errors'], 0, 5))."\n"; }
