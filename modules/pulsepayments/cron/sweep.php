<?php
/** Every 10 minutes: verify payments the webhook never delivered, expire stale links/holds/terminal jobs, retry queued captures. php cron/sweep.php <token> */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_PAY_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$r = PulsePayService::sweep((int) (isset($argv[2]) ? $argv[2] : Tools::getValue('hours', 72)));
PulseCoreService::audit('pulsepayments', 'sweep', $r);
echo 'Verified: '.$r['verified'].', newly settled: '.$r['settled'].', captures retried: '.$r['captures_retried'].', links expired: '.$r['links_expired'].', pre-auths expired: '.$r['preauths_expired'].', terminal requests expired: '.$r['terminal_expired'];
