<?php
/**
 * Demo data for Pulse Key Card — a 52-room property in Port Harcourt.
 * Creates four encoders (one simulator, three vendor entries in test mode), the property's common doors,
 * staff access groups with cards, guest keys for the rooms that are in house, and a few days of simulated
 * lock-audit events including one lock with a flat battery.
 * Idempotent: run it as often as you like. It never deletes anything.
 * Usage: php modules/pulsekeycard/seed/seed.php   (or in a browser with ?token=<PULSE_KC_CRON_TOKEN>)
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
if (php_sapi_name() !== 'cli') {
    $token = Tools::getValue('token');
    if (!hash_equals((string) Configuration::get('PULSE_KC_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
}
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$db = Db::getInstance();
$now = date('Y-m-d H:i:s');
$summary = array();

/* ---------- 1. encoders ---------- */
$encoders = array(
    array('name' => 'Front Desk 1', 'adapter' => 'PulseKcAdapterSimulator', 'location' => 'front_desk', 'protocol' => 'local', 'host' => '127.0.0.1', 'port' => 0, 'endpoint' => '', 'encoder_ref' => 'SIM-FD1', 'local_only' => 0, 'test_mode' => 0, 'creds' => array()),
    array('name' => 'Front Desk 2 (Onity)', 'adapter' => 'PulseKcAdapterOnity', 'location' => 'front_desk', 'protocol' => 'http', 'host' => '127.0.0.1', 'port' => 8080, 'endpoint' => '/onity/pms', 'encoder_ref' => 'ENC02', 'local_only' => 1, 'test_mode' => 1, 'creds' => array('user' => 'PULSE', 'password' => 'change-me', 'site_code' => 'PHC001')),
    array('name' => 'Back Office (Salto)', 'adapter' => 'PulseKcAdapterSalto', 'location' => 'back_office', 'protocol' => 'https', 'host' => 'salto.local', 'port' => 8100, 'endpoint' => '/api/v1', 'encoder_ref' => 'ENC-BO1', 'local_only' => 0, 'test_mode' => 1, 'creds' => array('user' => 'pms', 'password' => 'change-me', 'installation' => 'PORTHARCOURT')),
    array('name' => 'Housekeeping (Dormakaba)', 'adapter' => 'PulseKcAdapterDormakaba', 'location' => 'housekeeping', 'protocol' => 'https', 'host' => 'ambiance.local', 'port' => 8443, 'endpoint' => '/Ambiance/api', 'encoder_ref' => 'HK-ENC1', 'local_only' => 0, 'test_mode' => 1, 'creds' => array('api_key' => 'test-key-change-me', 'site_id' => '1')),
);
$encIds = array(); $newEnc = 0;
foreach ($encoders as $e) {
    $row = PulseKcEncoder::byName($e['name']);
    if ($row) { $encIds[$e['name']] = (int) $row['id_pulse_kc_encoder']; continue; }
    $encIds[$e['name']] = PulseKcEncoder::save(array('name' => $e['name'], 'adapter' => $e['adapter'], 'location' => $e['location'], 'protocol' => $e['protocol'],
        'host' => $e['host'], 'port' => $e['port'], 'endpoint' => $e['endpoint'], 'encoder_ref' => $e['encoder_ref'], 'local_only' => $e['local_only'],
        'test_mode' => $e['test_mode'], 'timeout_sec' => 8, 'active' => 1, 'credentials' => $e['creds'],
        'options_json' => $e['adapter'] === 'PulseKcAdapterOnity' ? '{"max_rooms":4,"multi_room":1}' : ''));
    $newEnc++;
}
$sim = $encIds['Front Desk 1'];
$db->update('pulse_kc_encoder', array('status' => 'online', 'last_seen' => $now), 'id_pulse_kc_encoder='.(int) $sim);
$summary[] = $newEnc.' encoder(s) added ('.count($encoders).' registered in total)';

/* ---------- 2. common doors ---------- */
$doors = array(
    array('code' => 'MAIN', 'name' => 'Main entrance (Aba Road)', 'type' => 'common', 'lock_id' => 'LK-MAIN', 'is_default' => 1, 'zone' => 'lobby'),
    array('code' => 'LIFT', 'name' => 'Guest lift', 'type' => 'lift', 'lock_id' => 'LK-LIFT', 'is_default' => 1, 'zone' => 'lobby'),
    array('code' => 'POOL', 'name' => 'Pool & gym', 'type' => 'common', 'lock_id' => 'LK-POOL', 'is_default' => 0, 'zone' => 'leisure'),
    array('code' => 'CARPARK', 'name' => 'Car park gate', 'type' => 'gate', 'lock_id' => 'LK-GATE', 'is_default' => 1, 'zone' => 'perimeter'),
    array('code' => 'EXEC', 'name' => 'Executive lounge (4th floor)', 'type' => 'common', 'lock_id' => 'LK-EXEC', 'is_default' => 0, 'floor' => '4', 'zone' => 'leisure'),
    array('code' => 'BOH', 'name' => 'Back of house corridor', 'type' => 'back_of_house', 'lock_id' => 'LK-BOH', 'is_default' => 0, 'zone' => 'service'),
    array('code' => 'STORE', 'name' => 'Housekeeping store', 'type' => 'back_of_house', 'lock_id' => 'LK-STORE', 'is_default' => 0, 'zone' => 'service'),
    array('code' => 'LAUNDRY', 'name' => 'Laundry', 'type' => 'back_of_house', 'lock_id' => 'LK-LAUNDRY', 'is_default' => 0, 'zone' => 'service'),
    array('code' => 'GENSET', 'name' => 'Generator house', 'type' => 'back_of_house', 'lock_id' => 'LK-GEN', 'is_default' => 0, 'zone' => 'plant'),
    array('code' => 'CASHOFF', 'name' => 'Cash office', 'type' => 'safe', 'lock_id' => 'LK-CASH', 'is_default' => 0, 'zone' => 'admin'),
    array('code' => 'WR-LOBBY', 'name' => 'Lobby wall reader (SVN update point)', 'type' => 'wall_reader', 'lock_id' => 'LK-WR1', 'is_default' => 0, 'zone' => 'lobby'),
);
$doorIds = array(); $newDoors = 0;
foreach ($doors as $d) {
    $row = $db->getRow('SELECT id_pulse_kc_door FROM `'._DB_PREFIX_.'pulse_kc_door` WHERE code="'.pSQL($d['code']).'"');
    if ($row) { $doorIds[$d['code']] = (int) $row['id_pulse_kc_door']; continue; }
    $doorIds[$d['code']] = PulseKcService::saveDoor($d);
    $newDoors++;
}
$roomDoors = PulseKcService::syncRoomDoors();
$summary[] = $newDoors.' common door(s) added, '.$roomDoors.' room lock(s) registered';

/* ---------- 3. staff access groups ---------- */
$groups = array(
    array('name' => 'Duty Manager Master', 'department' => 'management', 'doors' => array('MAIN', 'LIFT', 'POOL', 'CARPARK', 'EXEC', 'BOH', 'STORE', 'LAUNDRY', 'GENSET', 'CASHOFF'), 'all_rooms' => 1, 'shift_start' => '00:00', 'shift_end' => '23:59', 'days_mask' => 127, 'card_days' => 180, 'override_deadbolt' => 1, 'override_dnd' => 1, 'is_master' => 1),
    array('name' => 'Housekeeping Floor Master', 'department' => 'housekeeping', 'doors' => array('MAIN', 'LIFT', 'BOH', 'STORE', 'LAUNDRY'), 'all_rooms' => 1, 'shift_start' => '06:00', 'shift_end' => '16:00', 'days_mask' => 127, 'card_days' => 90, 'override_deadbolt' => 0, 'override_dnd' => 0, 'is_master' => 0),
    array('name' => 'Front Desk Shift', 'department' => 'frontdesk', 'doors' => array('MAIN', 'LIFT', 'CARPARK', 'EXEC', 'BOH', 'CASHOFF'), 'all_rooms' => 0, 'shift_start' => '06:00', 'shift_end' => '22:00', 'days_mask' => 127, 'card_days' => 90, 'override_deadbolt' => 0, 'override_dnd' => 0, 'is_master' => 0),
    array('name' => 'Maintenance', 'department' => 'maintenance', 'doors' => array('MAIN', 'LIFT', 'BOH', 'STORE', 'GENSET', 'LAUNDRY'), 'all_rooms' => 1, 'shift_start' => '07:00', 'shift_end' => '19:00', 'days_mask' => 127, 'card_days' => 120, 'override_deadbolt' => 1, 'override_dnd' => 0, 'is_master' => 0),
    array('name' => 'Night Security', 'department' => 'security', 'doors' => array('MAIN', 'LIFT', 'CARPARK', 'BOH', 'GENSET'), 'all_rooms' => 0, 'shift_start' => '19:00', 'shift_end' => '07:00', 'days_mask' => 127, 'card_days' => 90, 'override_deadbolt' => 1, 'override_dnd' => 0, 'is_master' => 0),
);
$groupIds = array(); $newGroups = 0;
foreach ($groups as $g) {
    $row = $db->getRow('SELECT id_pulse_kc_staff_group FROM `'._DB_PREFIX_.'pulse_kc_staff_group` WHERE name="'.pSQL($g['name']).'"');
    if ($row) { $groupIds[$g['name']] = (int) $row['id_pulse_kc_staff_group']; continue; }
    $ids = array();
    foreach ($g['doors'] as $c) { if (isset($doorIds[$c])) { $ids[] = $doorIds[$c]; } }
    $g['doors'] = $ids;
    $groupIds[$g['name']] = PulseKcStaff::saveGroup($g);
    $newGroups++;
}
$summary[] = $newGroups.' staff access group(s) added';

/* ---------- 4. staff cards ---------- */
$employees = Employee::getEmployees();
$assign = array('Duty Manager Master', 'Front Desk Shift', 'Housekeeping Floor Master', 'Maintenance', 'Night Security');
$newCards = 0; $i = 0;
foreach ($employees as $e) {
    if ($i >= count($assign)) { break; }
    $gname = $assign[$i]; $i++;
    if (!isset($groupIds[$gname])) { continue; }
    if ((int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_key` WHERE id_employee_holder='.(int) $e['id_employee'].' AND id_pulse_kc_staff_group='.(int) $groupIds[$gname].' AND status="issued"')) { continue; }
    try { PulseKcStaff::issueCard((int) $e['id_employee'], (int) $groupIds[$gname], array('id_encoder' => $sim)); $newCards++; }
    catch (Exception $ex) { echo 'Staff card for '.$e['firstname'].' '.$e['lastname'].' skipped: '.$ex->getMessage()."\n"; }
}
$summary[] = $newCards.' staff card(s) cut on the simulator';

/* ---------- 5. guest keys for the rooms that are in house ---------- */
$newKeys = 0; $mobileKeys = 0; $inHouse = array();
if (class_exists('HotelBookingDetail')) {
    $inHouse = $db->executeS('SELECT b.id, b.id_room, b.id_customer, b.date_from, b.date_to, r.room_num, CONCAT(c.firstname," ",c.lastname) guest
        FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room
        LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer
        WHERE b.is_cancelled=0 AND b.is_refunded=0 AND b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' ORDER BY r.room_num LIMIT 60');
}
$defaultDoors = PulseKcService::defaultDoorIds();
foreach ($inHouse as $n => $b) {
    if ((int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_kc_key` WHERE id_htl_booking='.(int) $b['id'].' AND status="issued"')) { continue; }
    list($from, $to) = PulseKcService::window($b['date_from'], $b['date_to']);
    try {
        $idKey = PulseKcKey::issue(array('type' => 'guest', 'id_htl_booking' => (int) $b['id'], 'rooms' => array((int) $b['id_room']), 'doors' => $defaultDoors,
            'valid_from' => $from, 'valid_to' => $to, 'id_encoder' => $sim, 'guest_name' => $b['guest'], 'note' => 'Seeded demo key'));
        $newKeys++;
        // every third stay also gets a second card, and every fifth a mobile key
        if ($n % 3 === 0) { PulseKcKey::duplicate($idKey); $newKeys++; }
        if ($n % 5 === 0 && PulseKcService::cfg('MOBILE_ENABLED', 1)) {
            try { PulseKcMobileKey::issue((int) $b['id'], array('channel' => 'both', 'deliver' => false)); $mobileKeys++; }
            catch (Exception $ex) { /* mobile keys are optional demo colour */ }
        }
    } catch (Exception $ex) { echo 'Key for room '.$b['room_num'].' skipped: '.$ex->getMessage()."\n"; }
}
$summary[] = $newKeys.' guest card(s) and '.$mobileKeys.' mobile key(s) issued for '.count($inHouse).' in-house room(s)';

/* ---------- 6. three days of lock audit, including one flat battery ---------- */
$auditDoors = $db->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_kc_door` WHERE active=1 AND lock_id<>"" ORDER BY type="room", id_pulse_kc_door LIMIT 30');
$liveKeys = $db->executeS('SELECT id_pulse_kc_key, card_serial, guest_name, id_room, type FROM `'._DB_PREFIX_.'pulse_kc_key` WHERE status="issued" AND card_serial<>"" LIMIT 60');
$events = 0;
if ($auditDoors && $liveKeys) {
    foreach ($auditDoors as $di => $d) {
        for ($day = 3; $day >= 1; $day--) {
            $opens = 2 + (($di + $day) % 4);
            for ($o = 0; $o < $opens; $o++) {
                $k = $liveKeys[($di * 7 + $day * 3 + $o) % count($liveKeys)];
                if ($d['type'] === 'room' && (int) $d['id_room'] && (int) $k['id_room'] && (int) $d['id_room'] !== (int) $k['id_room'] && $k['type'] === 'guest') { continue; }
                $hour = 6 + (($di + $o * 5) % 16);
                $when = date('Y-m-d H:i:s', strtotime('-'.$day.' day', strtotime(date('Y-m-d').' '.str_pad($hour, 2, '0', STR_PAD_LEFT).':'.str_pad(($o * 13) % 60, 2, '0', STR_PAD_LEFT).':00')));
                $denied = (($di + $o) % 11) === 0;
                if (PulseKcAudit::ingest($d, array('opened_at' => $when, 'event' => $denied ? 'denied' : 'open', 'result' => $denied ? 'denied' : 'granted',
                    'card_serial' => $k['card_serial'], 'battery_pct' => 100 - (($di * 3 + $day) % 40), 'raw' => 'seed'), 'lock')) { $events++; }
            }
        }
    }
    // one lock on its last legs — this is what raises the maintenance work order
    $flat = $auditDoors[count($auditDoors) > 6 ? 6 : 0];
    $db->update('pulse_kc_door', array('battery_pct' => 8, 'battery_checked_at' => $now, 'last_audit_at' => $now, 'date_upd' => $now), 'id_pulse_kc_door='.(int) $flat['id_pulse_kc_door']);
    PulseKcAudit::ingest($flat, array('opened_at' => date('Y-m-d H:i:s', strtotime('-2 hour')), 'event' => 'battery_low', 'result' => 'info', 'card_serial' => '', 'battery_pct' => 8, 'raw' => 'seed battery warning'), 'lock');
    $events++;
    $summary[] = 'lock "'.$flat['name'].'" left at 8% battery so the Lock Audit screen has a work order to raise';
}
$summary[] = $events.' lock audit event(s) written across '.count($auditDoors).' lock(s)';

/* ---------- done ---------- */
echo "Pulse Key Card demo data — Port Harcourt, 52 rooms\n";
foreach ($summary as $s) { echo ' · '.$s."\n"; }
echo "Open Key Card ▸ Key Desk and search a room number, or Key Card ▸ Lock Audit ▸ Battery to raise the work order.\n";
