<?php
/** Every few minutes: detect group-block changes, drain the ARI push queue, pull OTA reservations. php cron/sync.php <token> [id_channel] */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if (!hash_equals((string) Configuration::get('PULSE_CH_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$idChannel = (int) (isset($argv[2]) ? $argv[2] : Tools::getValue('id_channel'));
$t0 = microtime(true);
$r = PulseChService::syncAll($idChannel);
$secs = round(microtime(true) - $t0, 1);
PulseCoreService::audit('pulsechannel', 'cron_sync', $r);
echo 'Pulse Channel sync in '.$secs.'s — blocks dirtied: '.$r['blocks'].', batches pushed: '.$r['pushed'].' ('.$r['cells'].' cells), push errors: '.$r['push_errors']
    .', reservations pulled: '.$r['pulled'].', delivered: '.$r['delivered'].', failed: '.$r['failed'].', logs pruned: '.$r['logs_pruned']."\n";
