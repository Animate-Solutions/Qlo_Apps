<?php
/** Every 15 minutes: php cron/send.php <token>. Sends any scheduled report whose time has come and syncs cost feeds. */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_RPT_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$sent = PulseOwnerSnapshot::runDue(false);
echo $sent ? 'Sent: '.implode(', ', $sent) : 'Nothing due';
