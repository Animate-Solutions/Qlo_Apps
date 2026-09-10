<?php
/**
 * Demo data for Pulse Channel Manager — a 52-room hotel in Port Harcourt.
 * Creates three channels (a generic-JSON partner in test mode, a CSV drop, one disabled OTA),
 * maps every room type, computes 30 days of ARI, and lands two delivered OTA bookings plus one
 * that cannot be mapped so the failed queue has something in it.
 *
 *   php modules/pulsechannel/seed/seed.php            (CLI)
 *   /modules/pulsechannel/seed/seed.php?token=<PULSE_CH_CRON_TOKEN>   (browser)
 *
 * Idempotent: re-running updates the same rows and never deletes anything.
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';

if (php_sapi_name() !== 'cli') {
    $token = Tools::getValue('token');
    if (!hash_equals((string) Configuration::get('PULSE_CH_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
}
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$db = Db::getInstance();
$out = array();
$bd = PulseChService::businessDate();

/* ---------- 1. channels ---------- */

$channels = array(
    array('code' => 'hotels_ng', 'name' => 'Hotels.ng', 'adapter' => 'PulseChAdapterGeneric', 'endpoint' => 'https://partner-sandbox.hotels.ng/pms/ari', 'pull_endpoint' => 'https://partner-sandbox.hotels.ng/pms/reservations',
        'ack_endpoint' => 'https://partner-sandbox.hotels.ng/pms/ack', 'auth_type' => 'bearer', 'payload_format' => 'json', 'sync_mode' => 'both', 'hotel_code' => 'PHC-GRA-52', 'commission_pct' => 12,
        'allotment' => 0, 'oversell_buffer' => 0, 'push_window_days' => 30, 'enabled' => 1, 'test_mode' => 1, 'auto_deliver' => 1,
        'notes' => 'Sandbox account for the Port Harcourt property. Generic JSON contract — see README.',
        'creds' => array('api_key' => 'sandbox-key-'.Tools::substr(sha1('hotels_ng'.__FILE__), 0, 24), 'secret' => Tools::substr(sha1('hng-secret'.__FILE__), 0, 32))),
    array('code' => 'jumia', 'name' => 'Jumia Travel (CSV drop)', 'adapter' => 'PulseChAdapterCsv', 'endpoint' => '', 'pull_endpoint' => '', 'ack_endpoint' => '',
        'auth_type' => 'none', 'payload_format' => 'csv', 'sync_mode' => 'both', 'hotel_code' => 'JUMIA-PHC-1121', 'commission_pct' => 12,
        'allotment' => 12, 'oversell_buffer' => 0, 'push_window_days' => 30, 'enabled' => 1, 'test_mode' => 1, 'auto_deliver' => 1,
        'csv_in_dir' => _PS_DOWNLOAD_DIR_.'pulsechannel/jumia/in', 'csv_out_dir' => _PS_DOWNLOAD_DIR_.'pulsechannel/jumia/out',
        'notes' => 'No API: ARI is written to the out folder and e-mailed to the account manager; reservation CSVs are dropped in the in folder.', 'creds' => array()),
    array('code' => 'booking_com', 'name' => 'Booking.com', 'adapter' => 'PulseChAdapterHttp', 'endpoint' => '', 'pull_endpoint' => '', 'ack_endpoint' => '',
        'auth_type' => 'basic', 'payload_format' => 'xml', 'sync_mode' => 'both', 'hotel_code' => '', 'commission_pct' => 15,
        'allotment' => 0, 'oversell_buffer' => 1, 'push_window_days' => 365, 'enabled' => 0, 'test_mode' => 1, 'auto_deliver' => 1,
        'notes' => 'Disabled until certification: enter the endpoint, machine account and property id issued by Booking.com, then press Test connection.', 'creds' => array()),
);
$ids = array();
foreach ($channels as $c) {
    $existing = PulseChService::channelByCode($c['code']);
    $creds = $c['creds']; unset($c['creds']);
    if ($existing) { $c['id_pulse_ch_channel'] = (int) $existing['id_pulse_ch_channel']; }
    $ids[$c['code']] = PulseChService::saveChannel($c, $creds);
    $out[] = ($existing ? 'updated' : 'created').' channel '.$c['name'];
}

/* ---------- 2. mappings for every room type ---------- */

$roomTypes = PulseChService::roomTypes();
if (!$roomTypes) {
    echo "No room types found — install the QloApps demo hotel (or create room types) before seeding the channel manager.\n";
    exit;
}
$plans = array();
foreach (PulseChService::ratePlans(false) as $rp) { $plans[$rp['code']] = (int) $rp['id_pulse_ch_rate_plan']; }
$codeFor = function ($name) {
    $slug = Tools::strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', trim($name)));
    return trim(Tools::substr($slug, 0, 20), '-');
};
$mapped = 0;
foreach (array('hotels_ng', 'jumia', 'booking_com') as $code) {
    foreach ($roomTypes as $i => $rt) {
        foreach (array('BAR', 'BB', 'NONREF') as $planCode) {
            if (empty($plans[$planCode])) { continue; }
            PulseChMapping::save(array(
                'id_pulse_ch_channel' => $ids[$code], 'id_product' => (int) $rt['id_product'], 'id_pulse_ch_rate_plan' => $plans[$planCode],
                'channel_room_code' => $codeFor($rt['name']), 'channel_rate_code' => $codeFor($rt['name']).'-'.$planCode,
                'base_occupancy' => max(1, (int) $rt['adults']), 'max_occupancy' => max(1, (int) $rt['max_guests']),
                'single_adj' => -5000, 'extra_adult_adj' => 12000, 'child_adj' => 6000,
                'rate_adjust_type' => $code === 'booking_com' ? 'percent' : 'none', 'rate_adjust_value' => $code === 'booking_com' ? 0 : 0,
                'allotment' => ($code === 'jumia' && $i === 0) ? 6 : 0, 'min_los' => 0, 'active' => 1,
            ));
            $mapped++;
        }
    }
}
$out[] = $mapped.' mapping(s) across '.count($roomTypes).' room type(s)';

/* ---------- 3. 30 days of ARI ---------- */

$rebuild = PulseChAri::rebuild();
$out[] = 'ARI computed for '.$rebuild['channels'].' channel(s), '.$rebuild['cells_changed'].' cell(s)';
$cells = (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_ari`');
$out[] = $cells.' ARI cell(s) in the table';

/* a Friday/Saturday weekend uplift and a stop-sell on the busiest night, so the grid is not flat */
$fri = date('Y-m-d', strtotime($bd.' +'.((5 - (int) date('w', strtotime($bd)) + 7) % 7).' day'));
$sat = date('Y-m-d', strtotime($fri.' +1 day'));
$db->execute('UPDATE `'._DB_PREFIX_.'pulse_ch_ari` SET manual_rate=ROUND(rate*1.20,2), rate=ROUND(rate*1.20,2), min_los=2, cell_hash="" WHERE ari_date IN ("'.pSQL($fri).'","'.pSQL($sat).'")');
$out[] = 'weekend uplift (+20%, min LOS 2) applied to '.$fri.' and '.$sat;

/* ---------- 4. two delivered reservations and one that cannot be mapped ---------- */

$first = $roomTypes[0];
$second = isset($roomTypes[1]) ? $roomTypes[1] : $roomTypes[0];
$arr1 = date('Y-m-d', strtotime($bd.' +3 day')); $dep1 = date('Y-m-d', strtotime($bd.' +6 day'));
$arr2 = date('Y-m-d', strtotime($bd.' +8 day')); $dep2 = date('Y-m-d', strtotime($bd.' +10 day'));
$rate1 = PulseChAri::baseRate((int) $first['id_product'], $arr1);
$rate2 = PulseChAri::baseRate((int) $second['id_product'], $arr2);

$payloads = array(
    array('channel' => 'hotels_ng', 'body' => array(
        'reference' => 'HNG-PHC-880431', 'status' => 'new', 'firstname' => 'Chinedu', 'lastname' => 'Okafor',
        'email' => 'chinedu.okafor@example.ng', 'phone' => '+2348031122334', 'country' => 'NG',
        'room_code' => $codeFor($first['name']), 'rate_code' => $codeFor($first['name']).'-BAR',
        'arrival' => $arr1, 'departure' => $dep1, 'rooms' => 1, 'adults' => 2, 'children' => 0,
        'amount' => round($rate1 * 3, 2), 'currency' => 'NGN', 'commission_pct' => 12, 'payment_type' => 'hotel_collect')),
    array('channel' => 'jumia', 'body' => array(
        'reference' => 'JMT-2211-77104', 'status' => 'new', 'guest_name' => 'Amaka Eze',
        'email' => 'amaka.eze@example.ng', 'phone' => '+2347066554433', 'country' => 'NG',
        'room_code' => $codeFor($second['name']), 'rate_code' => $codeFor($second['name']).'-BB',
        'arrival' => $arr2, 'departure' => $dep2, 'rooms' => 1, 'adults' => 1, 'children' => 1,
        'amount' => round($rate2 * 2 + 7500 * 2, 2), 'currency' => 'NGN', 'commission_pct' => 12, 'payment_type' => 'channel_collect')),
);
$delivered = 0; $pending = 0;
foreach ($payloads as $p) {
    $ref = $p['body']['reference'];
    $already = $db->getRow('SELECT id_pulse_ch_reservation, status FROM `'._DB_PREFIX_.'pulse_ch_reservation` WHERE id_pulse_ch_channel='.(int) $ids[$p['channel']].' AND channel_ref="'.pSQL($ref).'"');
    if ($already && in_array($already['status'], array('delivered', 'modified'))) { $out[] = 'reservation '.$ref.' already delivered'; $delivered++; continue; }
    try {
        $idRes = PulseChReservation::receive($ids[$p['channel']], $p['body'], 'seed');
        $st = $db->getValue('SELECT status FROM `'._DB_PREFIX_.'pulse_ch_reservation` WHERE id_pulse_ch_reservation='.(int) $idRes);
        if (in_array($st, array('delivered', 'modified'))) { $delivered++; $out[] = 'delivered OTA booking '.$ref.' ('.$p['body']['arrival'].' → '.$p['body']['departure'].')'; }
        else { $pending++; $out[] = 'reservation '.$ref.' stored as "'.$st.'" — '.(string) $db->getValue('SELECT error FROM `'._DB_PREFIX_.'pulse_ch_reservation` WHERE id_pulse_ch_reservation='.(int) $idRes); }
    } catch (Exception $e) { $pending++; $out[] = 'reservation '.$ref.' could not be delivered: '.$e->getMessage(); }
}

/* one that cannot be mapped: an OTA room code nobody ever configured — exactly what the failed queue is for */
$failRef = 'HNG-PHC-880992';
if (!$db->getValue('SELECT id_pulse_ch_reservation FROM `'._DB_PREFIX_.'pulse_ch_reservation` WHERE channel_ref="'.pSQL($failRef).'"')) {
    try {
        PulseChReservation::receive($ids['hotels_ng'], array(
            'reference' => $failRef, 'status' => 'new', 'firstname' => 'Ibrahim', 'lastname' => 'Bello',
            'email' => 'ibrahim.bello@example.ng', 'phone' => '+2349088776655', 'country' => 'NG',
            'room_code' => 'PRESIDENTIAL-VILLA', 'rate_code' => 'PRESIDENTIAL-VILLA-BAR',
            'arrival' => date('Y-m-d', strtotime($bd.' +5 day')), 'departure' => date('Y-m-d', strtotime($bd.' +7 day')),
            'rooms' => 1, 'adults' => 2, 'children' => 2, 'amount' => 480000, 'currency' => 'NGN', 'commission_pct' => 12, 'payment_type' => 'hotel_collect',
        ), 'seed');
    } catch (Exception $e) { $out[] = 'failed-queue payload rejected: '.$e->getMessage(); }
}
$failedCount = (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_reservation` WHERE status="failed"');
$out[] = $failedCount.' reservation(s) sitting in the failed queue awaiting manual assignment';

/* ---------- 5. summary ---------- */

$queue = (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ch_queue` WHERE status IN ("pending","failed")');
$out[] = $queue.' push batch(es) waiting in the queue (drain with cron/sync.php)';
$out[] = 'unmapped room types on enabled channels: '.count(PulseChMapping::unmapped());

echo "Pulse Channel Manager demo data — ".Configuration::get('PS_SHOP_NAME').", business date $bd\n";
foreach ($out as $line) { echo '  · '.$line."\n"; }
echo "  · delivered: $delivered, awaiting delivery: $pending\n";
echo "Open Channel Manager in the back office to see the dashboard.\n";
