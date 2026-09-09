<?php
/** Hourly: expire keys past checkout, drain the offline encode/cancel queue, purge old audit rows, flag stale encoders and flat locks. php cron/expire.php <token> */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if (!hash_equals((string) Configuration::get('PULSE_KC_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);

$expired = PulseKcKey::expireDue();
$mobileExpired = PulseKcMobileKey::expireDue();
$queue = PulseKcService::runQueue();
$purged = PulseKcAudit::purge();

// encoders nobody has heard from for N hours are marked offline so the Key Desk stops routing keys to them
$stale = (int) (Configuration::get('PULSE_KC_ENCODER_STALE_HRS') ?: 6);
$flagged = 0;
foreach (Db::getInstance()->executeS('SELECT id_pulse_kc_encoder, name, last_seen FROM `'._DB_PREFIX_.'pulse_kc_encoder` WHERE active=1 AND status<>"disabled" AND (last_seen IS NULL OR last_seen<DATE_SUB(NOW(), INTERVAL '.$stale.' HOUR))') as $e) {
    $probe = PulseKcEncoder::test((int) $e['id_pulse_kc_encoder']);
    if (empty($probe['ok'])) {
        PulseCoreService::audit('pulsekeycard', 'encoder_stale', array('encoder' => $e['name'], 'last_seen' => $e['last_seen'], 'error' => isset($probe['error']) ? $probe['error'] : ''), 'pulse_kc_encoder', (int) $e['id_pulse_kc_encoder']);
        PulseKcService::alert('Encoder "'.$e['name'].'" has not answered for '.$stale.'h — check the desk PC before the next arrival.');
        $flagged++;
    }
}

// locks that report a battery level: pull what is new, then raise one maintenance work order per flat lock
$pull = PulseKcAudit::pullAll(60);
$tickets = PulseKcAudit::raiseBatteryTickets();

echo 'Keys expired: '.$expired.', mobile keys expired: '.$mobileExpired.', queue done/failed: '.$queue['done'].'/'.$queue['failed']
    .', audit rows purged: '.$purged.', audit rows pulled: '.$pull['rows'].', encoders flagged offline: '.$flagged.', battery work orders: '.$tickets."\n";
if ($pull['errors']) { echo 'Audit pull problems: '.implode(' | ', array_slice($pull['errors'], 0, 5))."\n"; }
