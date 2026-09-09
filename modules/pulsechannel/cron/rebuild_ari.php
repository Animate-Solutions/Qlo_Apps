<?php
/** Nightly, after the night audit: recompute the whole ARI window from scratch and queue the differences. php cron/rebuild_ari.php <token> [id_channel] */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if (!hash_equals((string) Configuration::get('PULSE_CH_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$idChannel = (int) (isset($argv[2]) ? $argv[2] : Tools::getValue('id_channel'));
$t0 = microtime(true);
PulseChService::detectBlockChanges();
$r = PulseChAri::rebuild($idChannel);
$push = PulseChAri::drainQueue($idChannel);
$secs = round(microtime(true) - $t0, 1);
echo 'Pulse Channel ARI rebuild in '.$secs.'s — channels: '.$r['channels'].', cells changed: '.$r['cells_changed'].', batches queued: '.$r['queued']
    .', pushed now: '.$push['sent'].' ('.$push['cells'].' cells), errors: '.$push['errors']."\n";
