<?php
/**
 * Portal housekeeping — run every five minutes.
 *   php modules/pulseguestportal/cron/portal.php
 *   curl -s "https://hotel/modules/pulseguestportal/cron/portal.php?token=<PULSE_GP_CRON_TOKEN>"
 *
 * Rings due wake-up calls, tells the desk about screens that have gone quiet, expires casting codes and
 * stale sessions, and re-queues the commands a screen never acknowledged. Everything is idempotent.
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';

if (php_sapi_name() !== 'cli') {
    if (!hash_equals((string) Configuration::get('PULSE_GP_CRON_TOKEN'), (string) Tools::getValue('token'))) { header('HTTP/1.1 403 Forbidden'); die('Invalid token'); }
}
$ctx = Context::getContext();
if (empty($ctx->employee) || !$ctx->employee->id) { $ctx->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1); }
$db = Db::getInstance();
$out = array();

/* ---------- 1. wake-up calls that have come due ---------- */
$rung = 0;
foreach (PulseGpRequest::dueWakeups() as $r) {
    $done = false;
    if (class_exists('PulseComms') && $r['id_customer']) {
        try { PulseComms::send('wake_up', new Customer((int) $r['id_customer']), array('time' => date('H:i', strtotime($r['scheduled_for'])), 'room' => $r['room_num'])); $done = true; }
        catch (Exception $e) { PulseGpService::audit('wakeup_comms_failed', array('id' => $r['id_pulse_gp_request'], 'error' => $e->getMessage())); }
    }
    foreach (PulseGpDevice::byRoom((int) $r['id_room']) as $d) { PulseGpDevice::command((int) $d['id_pulse_gp_device'], 'notify', array('kind' => 'wakeup', 'text' => 'Good morning — this is your '.date('H:i', strtotime($r['scheduled_for'])).' wake-up call')); $done = true; }
    if (!$done && class_exists('PulseTrace')) { PulseTrace::add('alert', 'Wake-up call for room '.$r['room_num'].' could not be delivered automatically', date('Y-m-d H:i:s'), (int) $r['id_htl_booking'], (int) $r['id_room'], null, 'frontdesk'); }
    $db->update('pulse_gp_request', array('status' => 'done', 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_gp_request='.(int) $r['id_pulse_gp_request']);
    $rung++;
}
$out[] = $rung.' wake-up call(s) rung';

/* ---------- 2. screens that have gone quiet ---------- */
$mins = max(5, (int) PulseGpService::cfg('OFFLINE_MIN', 5)) * 3;
$quiet = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_device` WHERE status="active" AND (last_seen IS NULL OR last_seen<DATE_SUB(NOW(), INTERVAL '.(int) $mins.' MINUTE))');
$alerted = 0;
foreach ($quiet as $d) {
    $key = 'offline_alert_'.(int) $d['id_pulse_gp_device'];
    $last = (int) PulseCoreService::setting('pulseguestportal', $key);
    if ($last && (time() - $last) < 21600) { continue; } // one alert per screen per six hours
    PulseCoreService::setting('pulseguestportal', $key, (string) time());
    if (class_exists('PulseTicket') && (int) PulseGpService::cfg('OFFLINE_TICKET', 0)) {
        PulseTicket::create(array('category' => 'maintenance', 'department' => 'maintenance', 'priority' => 'normal',
            'title' => 'Guest TV offline — room '.$d['room_num'], 'description' => 'The in-room screen ('.$d['uid'].', '.$d['model'].') has not checked in since '.($d['last_seen'] ? $d['last_seen'] : 'never').'.',
            'id_room' => $d['id_room'], 'source' => 'portal'));
    } elseif (class_exists('PulseTrace')) {
        PulseTrace::add('alert', 'Guest TV in room '.$d['room_num'].' is offline since '.($d['last_seen'] ? $d['last_seen'] : 'never'), date('Y-m-d H:i:s'), null, $d['id_room'] ? (int) $d['id_room'] : null, null, 'maintenance');
    }
    $alerted++;
}
$out[] = count($quiet).' screen(s) quiet, '.$alerted.' alert(s) raised';

/* ---------- 3. expiries and queue hygiene ---------- */
PulseGpEntertainment::castExpire();
$db->update('pulse_gp_session', array('revoked' => 1, 'revoke_reason' => 'expired'), 'revoked=0 AND expires_at<NOW()');
PulseGpSession::purge(7);
$db->update('pulse_gp_command', array('status' => 'queued'), 'status="sent" AND date_sent<DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND date_add>DATE_SUB(NOW(), INTERVAL 1 DAY)');
$db->update('pulse_gp_command', array('status' => 'expired'), 'status IN ("queued","sent") AND date_add<DATE_SUB(NOW(), INTERVAL 1 DAY)');
$db->execute('DELETE FROM `'._DB_PREFIX_.'pulse_gp_rate` WHERE window_start<'.(int) (floor(time() / 60) - 120));
$out[] = 'sessions, casting codes and command queue tidied';

/* ---------- 4. stays that ended but whose screen was never wiped ---------- */
$stale = $db->executeS('SELECT DISTINCT s.id_room FROM `'._DB_PREFIX_.'pulse_gp_session` s
    LEFT JOIN `'._DB_PREFIX_.'htl_booking_detail` b ON b.id=s.id_htl_booking AND b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.'
    WHERE s.revoked=0 AND s.id_htl_booking IS NOT NULL AND b.id IS NULL');
foreach ($stale as $s) { PulseGpDevice::wipeRoom((int) $s['id_room'], 'stay_ended'); }
$out[] = count($stale).' room(s) wiped after a stay that ended without a check-out event';

PulseGpService::audit('cron', $out);
echo "Pulse Guest Portal cron — ".date('Y-m-d H:i:s')."\n";
foreach ($out as $line) { echo ' · '.$line."\n"; }
