<?php
/** Cron entry: php cron/night_audit.php <token>  or  https://site/modules/pulsefrontdesk/cron/night_audit.php?token=... */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../../pulsecore/classes/PulseCore.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_FD_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
try {
    $stats = (new PulseNightAudit())->run(false);
    echo "OK ".json_encode($stats);
} catch (Exception $e) {
    echo "BLOCKED: ".$e->getMessage();
}
