<?php
/** Cron entry: php cron/night_audit.php <token>  or  https://site/modules/pulsefrontdesk/cron/night_audit.php?token=... */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../../pulsecore/classes/PulseCoreService.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_FD_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
// A blocked audit is one property's answer, not the group's: forEachHotel already records the
// exception per hotel and carries on, so the BLOCKED line is printed from the summary afterwards.
$run = PulseCoreService::forEachHotel(function ($idHotel, $hotel) {
    $stats = (new PulseNightAudit())->run(false);
    echo '['.$hotel.'] OK '.json_encode($stats)."\n";
});
foreach ($run['results'] as $h) { if (!$h['ok']) { echo '['.$h['name'].'] BLOCKED: '.$h['error']."\n"; } }
