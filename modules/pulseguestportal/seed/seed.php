<?php
/**
 * Demo data for Pulse Guest Portal — The Carvington Hotel & Suites, Port Harcourt, 52 rooms.
 * Creates a Samsung HG50AU800 in every room (a realistic mix of online, offline and one still waiting to be
 * paired), the twelve DStv-style multicast channels the headend puts on the LAN, a small VOD catalogue with
 * two paid titles, the hotel directory in English with French and Pidgin on the pages guests actually read,
 * two dayparted promotions, radio and app tiles, room-control points, and a handful of live room-service
 * orders, requests, messages and stay ratings so every screen in the back office has something on it.
 * Idempotent — run it as often as you like; it never deletes anything.
 * Usage: php modules/pulseguestportal/seed/seed.php   (or in a browser with ?token=<PULSE_GP_CRON_TOKEN>)
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
if (php_sapi_name() !== 'cli') {
    if (!hash_equals((string) Configuration::get('PULSE_GP_CRON_TOKEN'), (string) Tools::getValue('token'))) { header('HTTP/1.1 403 Forbidden'); die('Invalid token'); }
}
$ctx = Context::getContext();
if (empty($ctx->employee) || !$ctx->employee->id) { $ctx->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1); }
$db = Db::getInstance();
$now = date('Y-m-d H:i:s');
$bd = PulseGpService::bd();
$summary = array();

/* ---------- 1. branding defaults ---------- */
foreach (array('PULSE_GP_HOTEL_NAME' => 'The Carvington Hotel & Suites', 'PULSE_GP_WIFI_SSID' => 'Carvington-Guest', 'PULSE_GP_WIFI_PASSWORD' => 'PortHarcourt2026',
    'PULSE_GP_WEATHER_CITY' => 'Port Harcourt', 'PULSE_GP_WEATHER_LAT' => '4.8156', 'PULSE_GP_WEATHER_LON' => '7.0498') as $k => $v) {
    if (!Configuration::get($k)) { Configuration::updateValue($k, $v); }
}
if (!Configuration::get('PULSE_GP_ADULT_PIN_HASH')) { Configuration::updateValue('PULSE_GP_ADULT_PIN_HASH', PulseGpService::hashPin('1974')); $summary[] = 'adult PIN set to 1974 (change it in Portal Settings)'; }

/* ---------- 2. one TV per room ---------- */
$rooms = $db->executeS('SELECT id id_room, room_num, floor FROM `'._DB_PREFIX_.'htl_room_information` ORDER BY room_num LIMIT 52');
$newDev = 0; $online = 0; $offline = 0; $pending = 0; $i = 0;
if (!$rooms) { echo "No rooms found in htl_room_information — devices will be registered unassigned.\n"; }
$fallback = array();
if (!$rooms) { for ($n = 101; $n <= 152; $n++) { $fallback[] = array('id_room' => 0, 'room_num' => (string) $n, 'floor' => Tools::substr((string) $n, 0, 1)); } $rooms = $fallback; }
foreach ($rooms as $r) {
    $i++;
    $mac = '00:16:6C:'.strtoupper(str_pad(dechex(0xA0 + (int) ($i / 100)), 2, '0', STR_PAD_LEFT)).':'.strtoupper(str_pad(dechex($i), 2, '0', STR_PAD_LEFT)).':'.strtoupper(str_pad(dechex((int) $r['room_num'] % 256), 2, '0', STR_PAD_LEFT));
    $uid = PulseGpDevice::uid($mac, '');
    $dev = PulseGpDevice::byUid($uid);
    // a believable estate: most screens up, a handful asleep or unplugged, one brand-new set waiting for the desk
    $state = ($i % 17 === 0) ? 'pending' : (($i % 9 === 0) ? 'offline' : 'online');
    $lastSeen = $state === 'online' ? date('Y-m-d H:i:s', time() - ($i % 4) * 40) : ($state === 'offline' ? date('Y-m-d H:i:s', time() - (3600 * (2 + $i % 30))) : null);
    if (!$dev) {
        $db->insert('pulse_gp_device', array(
            'uid' => pSQL($uid), 'mac' => pSQL(PulseGpDevice::normalise($mac)), 'serial' => pSQL('0AXH3PHC'.str_pad((string) $i, 5, '0', STR_PAD_LEFT)), 'type' => 'tv',
            'model' => 'HG50AU800', 'firmware' => 'T-HKMDEUC-1420.3', 'app_version' => '1.0.0', 'ip' => pSQL('10.20.'.(1 + (int) ($i / 60)).'.'.(10 + $i)),
            'label' => pSQL('Room '.$r['room_num']), 'id_room' => $r['id_room'] ? (int) $r['id_room'] : null, 'room_num' => pSQL($r['room_num']), 'floor' => pSQL($r['floor']),
            'token' => pSQL(PulseGpDevice::newToken()), 'locale' => 'en', 'status' => $state === 'pending' ? 'pending' : 'active',
            'last_seen' => $lastSeen, 'paired_at' => $state === 'pending' ? null : $now, 'boots' => 1 + ($i % 7), 'date_add' => $now, 'date_upd' => $now,
        ));
        $newDev++;
    } else {
        $db->update('pulse_gp_device', array('last_seen' => $lastSeen, 'date_upd' => $now), 'id_pulse_gp_device='.(int) $dev['id_pulse_gp_device']);
    }
    if ($state === 'online') { $online++; } elseif ($state === 'offline') { $offline++; } else { $pending++; }
}
$summary[] = $newDev.' new screen(s) registered ('.$online.' online, '.$offline.' offline, '.$pending.' waiting for the desk to pair)';

/* ---------- 3. the DStv-to-IP line-up ---------- */
$channels = array(
    array(1, 'Channels TV', 'udp://@239.10.1.1:1234', 'news', 0, 1), array(2, 'Arise News', 'udp://@239.10.1.2:1234', 'news', 0, 0),
    array(3, 'NTA International', 'udp://@239.10.1.3:1234', 'general', 0, 0), array(4, 'Africa Magic Showcase', 'udp://@239.10.1.4:1234', 'series', 0, 1),
    array(5, 'Africa Magic Yoruba', 'udp://@239.10.1.5:1234', 'series', 0, 0), array(6, 'SuperSport Football', 'udp://@239.10.1.6:1234', 'sport', 0, 1),
    array(7, 'SuperSport Premier League', 'udp://@239.10.1.7:1234', 'sport', 0, 1), array(8, 'M-Net Movies 1', 'udp://@239.10.1.8:1234', 'movies', 0, 1),
    array(9, 'Cartoon Network', 'udp://@239.10.1.9:1234', 'kids', 0, 0), array(10, 'CNN International', 'udp://@239.10.1.10:1234', 'news', 0, 1),
    array(11, 'BBC World News', 'udp://@239.10.1.11:1234', 'news', 0, 1), array(12, 'Trace Naija', 'udp://@239.10.1.12:1234', 'music', 0, 0),
);
$newChan = 0;
foreach ($channels as $c) {
    if ($db->getValue('SELECT id_pulse_gp_channel FROM `'._DB_PREFIX_.'pulse_gp_channel` WHERE number='.(int) $c[0])) { continue; }
    $db->insert('pulse_gp_channel', array('number' => (int) $c[0], 'name' => pSQL($c[1]), 'url' => pSQL($c[2]), 'category' => pSQL($c[3]), 'source' => 'dstv',
        'adult' => (int) $c[4], 'hd' => (int) $c[5], 'sort' => (int) $c[0], 'active' => 1, 'date_add' => $now, 'date_upd' => $now));
    $newChan++;
}
$summary[] = $newChan.' multicast channel(s) added (239.10.1.x, the headend range)';

/* ---------- 4. VOD catalogue ---------- */
$vods = array(
    array('The Wedding Party', 'A Lagos society wedding unravels one guest at a time.', 'http://10.20.0.9:8080/vod/wedding-party.m3u8', 'movie', 'PG', 2016, 110, 0, 2500),
    array('King of Boys', 'A businesswoman’s political ambition collides with her past.', 'http://10.20.0.9:8080/vod/king-of-boys.m3u8', 'movie', '15', 2018, 169, 0, 2500),
    array('Port Harcourt from the Air', 'A short film over the Garden City and the creeks.', 'http://10.20.0.9:8080/vod/phc-air.m3u8', 'documentary', 'PG', 2023, 24, 1, 0),
    array('Carvington — your stay', 'Two minutes on the hotel: the restaurant, the pool deck and the meeting rooms.', 'http://10.20.0.9:8080/vod/carvington.m3u8', 'hotel', 'PG', 2025, 3, 1, 0),
    array('Kids: Bino & Fino', 'Nigerian animation for younger guests.', 'http://10.20.0.9:8080/vod/bino-fino.m3u8', 'kids', 'U', 2021, 48, 1, 0),
);
$newVod = 0;
foreach ($vods as $v) {
    if ($db->getValue('SELECT id_pulse_gp_vod FROM `'._DB_PREFIX_.'pulse_gp_vod` WHERE title="'.pSQL($v[0]).'"')) { continue; }
    $db->insert('pulse_gp_vod', array('title' => pSQL($v[0]), 'synopsis' => pSQL($v[1], true), 'stream_url' => pSQL($v[2]), 'category' => pSQL($v[3]), 'rating' => pSQL($v[4]),
        'year' => (int) $v[5], 'duration_min' => (int) $v[6], 'language' => 'English', 'free' => (int) $v[7], 'price' => (float) $v[8], 'adult' => 0, 'sort' => ++$newVod, 'active' => 1, 'date_add' => $now, 'date_upd' => $now));
}
$summary[] = $newVod.' VOD title(s) added (two paid at ₦2,500, posted to the folio on play)';

/* ---------- 5. the hotel directory ---------- */
$pages = array(
    array('code' => 'restaurant', 'category' => 'dining', 'opens' => '06:30 – 22:30', 'extension' => '241', 'location' => 'Ground floor',
        'en' => array('The Palm Terrace', 'Breakfast, lunch and dinner overlooking the pool', "Breakfast is served from 06:30 to 10:30, lunch from 12:30 and dinner until 22:30. The kitchen cooks jollof, pepper soup and grilled catfish alongside a European menu, and the chef will happily cook to a dietary requirement if you tell the front desk the evening before."),
        'fr' => array('La Terrasse Palm', 'Petit-déjeuner, déjeuner et dîner face à la piscine', "Le petit-déjeuner est servi de 06h30 à 10h30, le déjeuner à partir de 12h30 et le dîner jusqu'à 22h30."),
        'pcm' => array('Palm Terrace', 'Food dey from morning till night', "Breakfast na 06:30 to 10:30, lunch from 12:30, dinner till 22:30. We get jollof, pepper soup, grilled catfish and oyinbo food too.")),
    array('code' => 'room_service', 'category' => 'dining', 'opens' => '24 hours', 'extension' => '0', 'location' => 'In your room',
        'en' => array('Room service', 'Order from this screen at any hour', 'The full menu is on this screen day and night; after 23:00 the night menu is smaller but the kitchen never closes. Orders reach the kitchen the moment you press send, and the screen tells you when the tray leaves.')),
    array('code' => 'bar', 'category' => 'dining', 'opens' => '16:00 – 01:00', 'extension' => '243', 'location' => 'First floor',
        'en' => array('The Cellar Bar', 'Cocktails, Nigerian craft beer and small plates', 'Happy hour runs from 17:00 to 19:00 every day. Live highlife on Friday from 20:00.')),
    array('code' => 'spa', 'category' => 'spa', 'opens' => '09:00 – 21:00', 'extension' => '250', 'location' => 'Second floor',
        'en' => array('Carvington Spa', 'Massage, facials and a steam room', 'Book from the front desk or dial 250. A 60-minute deep-tissue massage is ₦25,000 and the couples suite takes two by appointment.')),
    array('code' => 'gym', 'category' => 'facility', 'opens' => '05:30 – 23:00', 'extension' => '251', 'location' => 'Second floor',
        'en' => array('Fitness centre', 'Cardio, free weights, towels provided', 'Open to guests with your room key. Towels and water are on the floor; a trainer is on site from 06:00 to 10:00 and 17:00 to 21:00.')),
    array('code' => 'pool', 'category' => 'facility', 'opens' => '07:00 – 21:00', 'extension' => '252', 'location' => 'Ground floor',
        'en' => array('Pool deck', 'Heated pool, loungers and pool-side service', 'Children must be with an adult. Pool-side food orders reach the same kitchen — dial 0 or order from this screen.')),
    array('code' => 'transport', 'category' => 'transport', 'opens' => '24 hours', 'extension' => '0', 'location' => 'Front desk',
        'en' => array('Airport transfer & taxi', 'Port Harcourt International is about 45 minutes away', 'A hotel car to Port Harcourt International Airport (PHC) is ₦35,000 one way and should be booked two hours ahead. The desk also arranges vetted taxis within the city.'),
        'pcm' => array('Airport and taxi', 'PHC airport na like 45 minutes', 'Hotel car to airport na ₦35,000 one way. Book am two hours before. We fit call taxi for town too.')),
    array('code' => 'business', 'category' => 'service', 'opens' => '08:00 – 20:00', 'extension' => '260', 'location' => 'Mezzanine',
        'en' => array('Business centre & meeting rooms', 'Printing, three meeting rooms, fibre and a standby generator', 'The Bonny and Okrika rooms seat twelve, the Kalabari boardroom seats twenty-four. Printing and scanning are at the desk on the mezzanine.')),
    array('code' => 'laundry', 'category' => 'service', 'opens' => 'Collection by 10:00', 'extension' => '245', 'location' => 'Housekeeping',
        'en' => array('Laundry & valet', 'Same-day service when we collect before 10:00', 'Ask for a pickup from the Requests screen and a valet will come up within the hour. Express and same-day services carry a surcharge shown on the price list.')),
    array('code' => 'attractions', 'category' => 'attraction', 'opens' => '', 'extension' => '0', 'location' => 'Port Harcourt',
        'en' => array('Around Port Harcourt', 'Pleasure Park, the Cultural Centre, Bonny Island', 'Port Harcourt Pleasure Park is fifteen minutes away and open until 22:00. The Rivers State Cultural Centre holds craft markets at the weekend, and the boat to Bonny Island leaves from the Marine Base — the concierge will arrange the whole day.')),
    array('code' => 'wifi', 'category' => 'wifi', 'opens' => '24 hours', 'extension' => '0', 'location' => 'Whole property',
        'en' => array('WiFi', 'Free high-speed internet in every room', 'Select the hotel network on your device and enter the password shown on this screen. One password covers the whole property, including the pool deck and the meeting rooms. If a device will not connect, dial 0 for the front desk.'),
        'fr' => array('WiFi', 'Internet haut débit gratuit', 'Sélectionnez le réseau de l’hôtel et saisissez le mot de passe affiché sur cet écran.'),
        'pcm' => array('WiFi', 'Free internet for everywhere', 'Pick the hotel network for your phone, then enter the password wey dey this screen. If e no gree connect, dial 0.')),
    array('code' => 'emergency', 'category' => 'emergency', 'opens' => '24 hours', 'extension' => '0', 'location' => 'Front desk',
        'en' => array('Emergency & safety', 'Dial 0 from the room phone at any hour', 'Dial 0 from the room telephone for the front desk at any hour. In a fire, leave by the nearest staircase — never the lift — and gather at the car park assembly point. A first-aid kit and a defibrillator are held at the front desk, and the duty manager is on the property overnight. The nearest hospital is the University of Port Harcourt Teaching Hospital.'),
        'pcm' => array('Emergency', 'Dial 0 any time', 'Dial 0 for front desk any hour. If fire happen, use staircase, no be lift, come meet for car park. First aid and duty manager dey ground floor all night.')),
    array('code' => 'house_rules', 'category' => 'rules', 'opens' => '', 'extension' => '0', 'location' => '',
        'en' => array('House rules', 'The short version', 'Check-out is at noon and check-in from 14:00. Smoking is not allowed in the rooms or corridors; the terrace is the place for it. Visitors must sign in at reception and leave by 22:00. Quiet hours run from 22:00 to 07:00. The generator carries the whole building, so a grid outage should only ever be a flicker.')),
);
$newPage = 0;
foreach ($pages as $p) {
    $texts = array();
    foreach (array('en', 'fr', 'pcm') as $l) { if (isset($p[$l])) { $texts[$l] = array('title' => $p[$l][0], 'summary' => $p[$l][1], 'body' => $p[$l][2]); } }
    $existing = (int) $db->getValue('SELECT id_pulse_gp_page FROM `'._DB_PREFIX_.'pulse_gp_page` WHERE code="'.pSQL($p['code']).'"');
    try {
        PulseGpContent::savePage($existing, array('code' => $p['code'], 'category' => $p['category'], 'icon' => $p['code'], 'phone' => '', 'extension' => $p['extension'],
            'opens' => $p['opens'], 'location' => $p['location'], 'sort' => ++$newPage, 'active' => 1), $texts);
    } catch (Exception $e) { echo 'Page '.$p['code'].' skipped: '.$e->getMessage()."\n"; }
}
$summary[] = count($pages).' directory page(s) written (English throughout, French and Pidgin where guests read most)';

/* ---------- 6. promotions ---------- */
$promos = array(
    array('code' => 'happy_hour', 'placement' => 'home', 'target' => 'directory', 'day_start' => '15:00:00', 'day_end' => '19:30:00', 'sort' => 1,
        'en' => array('Happy hour at the Cellar Bar', 'Two for one on cocktails and Nigerian craft beer, 17:00 to 19:00', 'See the bar'),
        'pcm' => array('Happy hour for Cellar Bar', 'Buy one cocktail, collect two — 17:00 till 19:00', 'Check am')),
    array('code' => 'night_menu', 'placement' => 'home', 'target' => 'dining', 'day_start' => '22:00:00', 'day_end' => '05:30:00', 'sort' => 2,
        'en' => array('The kitchen is still open', 'Pepper soup, suya and club sandwiches until sunrise', 'Order now'),
        'fr' => array('La cuisine reste ouverte', 'Pepper soup, suya et club sandwiches jusqu’au matin', 'Commander')),
);
$newPromo = 0;
foreach ($promos as $p) {
    $texts = array();
    foreach (array('en', 'fr', 'pcm') as $l) { if (isset($p[$l])) { $texts[$l] = array('title' => $p[$l][0], 'body' => $p[$l][1], 'cta' => $p[$l][2]); } }
    $existing = (int) $db->getValue('SELECT id_pulse_gp_promo FROM `'._DB_PREFIX_.'pulse_gp_promo` WHERE code="'.pSQL($p['code']).'"');
    try { PulseGpContent::savePromo($existing, $p + array('date_from' => null, 'date_to' => null, 'active' => 1, 'psort' => $p['sort']), $texts); $newPromo++; }
    catch (Exception $e) { echo 'Promotion '.$p['code'].' skipped: '.$e->getMessage()."\n"; }
}
$summary[] = $newPromo.' dayparted promotion(s) (happy hour in the afternoon, the night menu after 22:00)';

/* ---------- 7. radio, apps and the welcome copy ---------- */
if (!PulseGpEntertainment::radio()) {
    PulseGpEntertainment::saveRadio(array(
        array('name' => 'Wazobia FM 95.1', 'url' => 'https://stream.wazobiafm.example/phc.mp3', 'genre' => 'Pidgin talk'),
        array('name' => 'Rhythm 93.7 PH', 'url' => 'https://stream.rhythmfm.example/phc.mp3', 'genre' => 'Afrobeats'),
        array('name' => 'Cool FM 95.9', 'url' => 'https://stream.coolfm.example/phc.mp3', 'genre' => 'Music'),
        array('name' => 'Carvington Lounge', 'url' => 'http://10.20.0.9:8080/radio/lounge.mp3', 'genre' => 'In-house'),
    ));
    $summary[] = '4 radio stream(s) added';
}
if (!PulseGpEntertainment::apps()) {
    PulseGpEntertainment::saveApps(array(
        array('name' => 'Sudoku', 'url' => 'http://10.20.0.9:8080/games/sudoku/', 'type' => 'game'),
        array('name' => 'Chess', 'url' => 'http://10.20.0.9:8080/games/chess/', 'type' => 'game'),
        array('name' => 'Ludo', 'url' => 'http://10.20.0.9:8080/games/ludo/', 'type' => 'game'),
        array('name' => 'Flight arrivals — PHC', 'url' => 'http://10.20.0.9:8080/apps/flights/', 'type' => 'app'),
    ));
    $summary[] = '4 game/app tile(s) added';
}
foreach (array('en', 'fr', 'pcm', 'ar') as $l) { if (!PulseCoreService::setting('pulseguestportal', 'welcome_'.$l)) { PulseGpContent::saveWelcome($l, PulseGpContent::defaultWelcome($l)); } }

/* ---------- 8. room-control points ---------- */
$pointsBefore = (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_gp_control_point`');
$provisioned = 0;
foreach ($db->executeS('SELECT id FROM `'._DB_PREFIX_.'htl_room_information` LIMIT 52') as $r) { $provisioned += PulseGpControl::provision((int) $r['id']); }
$summary[] = ($provisioned ? $provisioned.' control point(s) provisioned by the simulator adapter' : 'no rooms to provision control points for').($pointsBefore ? ' ('.$pointsBefore.' already existed)' : '');

/* ---------- 9. live traffic: orders, requests, messages ---------- */
$inHouse = $db->executeS('SELECT b.id id_htl_booking, b.id_customer, b.id_room, r.room_num, CONCAT(c.firstname," ",c.lastname) guest
    FROM `'._DB_PREFIX_.'htl_booking_detail` b INNER JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=b.id_customer
    LEFT JOIN `'._DB_PREFIX_.'htl_room_information` r ON r.id=b.id_room
    WHERE b.id_status='.(int) HotelBookingDetail::STATUS_CHECKED_IN.' AND b.is_cancelled=0 AND b.is_refunded=0 ORDER BY r.room_num LIMIT 8');
$orders = 0; $requests = 0; $messages = 0;
if (!$inHouse) { echo "Nobody is checked in — skipping orders, requests and messages (run the Front Desk seed first).\n"; }
$menuItems = PulseGpService::pos() ? $db->executeS('SELECT id_pulse_pos_item id, name, price1 price FROM `'._DB_PREFIX_.'pulse_pos_item` WHERE active=1 AND available=1 ORDER BY id_pulse_pos_item LIMIT 12') : array();
foreach ($inHouse as $ix => $b) {
    $dev = $db->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_gp_device` WHERE id_room='.(int) $b['id_room'].' AND status="active" LIMIT 1');
    if (!$dev) { continue; }
    $session = array('id_htl_booking' => (int) $b['id_htl_booking'], 'id_customer' => (int) $b['id_customer'], 'id_room' => (int) $b['id_room'], 'guest_name' => $b['guest'], 'locale' => 'en');

    // two rooms have food on the way
    if ($ix < 2 && $menuItems) {
        $clientId = 'seed-order-'.(int) $b['id_htl_booking'];
        if (!$db->getValue('SELECT id_pulse_gp_order FROM `'._DB_PREFIX_.'pulse_gp_order` WHERE client_id="'.pSQL($clientId).'"')) {
            $lines = array(array('id_item' => (int) $menuItems[$ix]['id'], 'qty' => 1 + $ix), array('id_item' => (int) $menuItems[($ix + 3) % count($menuItems)]['id'], 'qty' => 1));
            try { PulseGpDining::order($dev, $session, $lines, 'Seeded demo order — no cutlery please', $clientId); $orders++; }
            catch (Exception $e) {
                $db->insert('pulse_gp_order', array('client_id' => pSQL($clientId), 'id_pulse_gp_device' => (int) $dev['id_pulse_gp_device'], 'id_room' => (int) $b['id_room'], 'room_num' => pSQL($b['room_num']),
                    'id_htl_booking' => (int) $b['id_htl_booking'], 'id_customer' => (int) $b['id_customer'], 'status' => 'failed', 'items_json' => pSQL(json_encode($lines), true), 'items_count' => count($lines),
                    'total' => 0, 'note' => 'Seeded demo order', 'fail_reason' => pSQL(Tools::substr($e->getMessage(), 0, 255)), 'business_date' => pSQL($bd), 'date_add' => $now, 'date_upd' => $now));
                $orders++;
            }
        }
    }
    // a spread of requests across the first few rooms
    $wanted = array(0 => 'towels', 1 => 'maintenance', 2 => 'housekeeping', 3 => 'laundry', 4 => 'wakeup');
    if (isset($wanted[$ix]) && !$db->getValue('SELECT id_pulse_gp_request FROM `'._DB_PREFIX_.'pulse_gp_request` WHERE id_htl_booking='.(int) $b['id_htl_booking'].' AND type="'.pSQL($wanted[$ix]).'"')) {
        $detail = array('towels' => 'Two extra bath towels please', 'maintenance' => 'The air conditioning is rattling', 'housekeeping' => 'Please clean while we are at breakfast',
            'laundry' => 'Two shirts and a pair of trousers', 'wakeup' => '');
        try { PulseGpRequest::create($wanted[$ix], $dev, $session, array('detail' => $detail[$wanted[$ix]], 'scheduled_for' => $wanted[$ix] === 'wakeup' ? '06:30' : '')); $requests++; }
        catch (Exception $e) { echo 'Request '.$wanted[$ix].' skipped: '.$e->getMessage()."\n"; }
    }
    // one conversation in flight
    if ($ix === 0 && !$db->getValue('SELECT id_pulse_gp_message FROM `'._DB_PREFIX_.'pulse_gp_message` WHERE id_htl_booking='.(int) $b['id_htl_booking'])) {
        PulseGpMessaging::fromDesk((int) $b['id_htl_booking'], (int) $b['id_room'], 'Welcome to The Carvington, '.$b['guest'].'. Anything you need, message us here — the front desk answers day and night.', 0);
        try { PulseGpMessaging::fromGuest($dev, $session, 'Good evening. Can we get a late check-out on Friday? Our flight is at 16:00.'); } catch (Exception $e) { echo 'Guest message skipped: '.$e->getMessage()."\n"; }
        $messages += 2;
    }
}
$summary[] = $orders.' room-service order(s), '.$requests.' guest request(s) and '.$messages.' message(s) in flight';

/* ---------- 10. a few stay ratings for the CRM to read ---------- */
$fb = array(
    array('Chinedu Okafor', '204', 5, 5, 5, 4, 5, 10, 'Very good stay. The jollof at the Palm Terrace is the best in Port Harcourt.'),
    array('Aisha Bello', '311', 4, 4, 5, 4, 4, 9, 'Comfortable room, quick room service. WiFi dropped once in the afternoon.'),
    array('Tunde Adeyemi', '108', 3, 3, 3, 3, 4, 6, 'The air conditioning was noisy and nobody came the first time I asked.'),
    array('Grace Eze', '406', 5, 5, 5, 5, 5, 10, 'Staff were kind to my children and the pool was spotless.'),
    array('Ibrahim Musa', '215', 4, 4, 4, 5, 4, 8, 'Good value. Breakfast could start earlier for an early flight.'),
    array('Ngozi Uche', '502', 2, 2, 3, 2, 3, 3, 'Generator woke us twice and the shower ran cold in the morning.'),
);
$newFb = 0;
foreach ($fb as $ix => $f) {
    if ($db->getValue('SELECT id_pulse_gp_feedback FROM `'._DB_PREFIX_.'pulse_gp_feedback` WHERE guest_name="'.pSQL($f[0]).'" AND room_num="'.pSQL($f[1]).'"')) { continue; }
    $db->insert('pulse_gp_feedback', array('room_num' => pSQL($f[1]), 'guest_name' => pSQL($f[0]),
        'id_room' => (int) $db->getValue('SELECT id FROM `'._DB_PREFIX_.'htl_room_information` WHERE room_num="'.pSQL($f[1]).'"') ?: null,
        'rating_overall' => (int) $f[2], 'rating_room' => (int) $f[3], 'rating_service' => (int) $f[4], 'rating_fnb' => (int) $f[5], 'rating_cleanliness' => (int) $f[6],
        'nps' => (int) $f[7], 'would_return' => $f[2] >= 3 ? 1 : 0, 'comment' => pSQL($f[8], true), 'locale' => 'en', 'source' => 'portal',
        'business_date' => pSQL(date('Y-m-d', strtotime('-'.($ix + 1).' day', strtotime($bd)))), 'date_add' => date('Y-m-d H:i:s', strtotime('-'.($ix + 1).' day'))));
    $newFb++;
}
$summary[] = $newFb.' stay rating(s) written for the CRM to pick up (one 2/5 to work with)';

/* ---------- done ---------- */
echo "Pulse Guest Portal demo data — The Carvington Hotel & Suites, Port Harcourt, 52 rooms\n";
foreach ($summary as $s) { echo ' · '.$s."\n"; }
echo "Open Guest Portal ▸ Portal Devices to pair the screen that is waiting, then load ".Tools::getShopDomainSsl(true).__PS_BASE_URI__."pulse/tv?mac=00:16:6C:A0:01:65 in a browser to see a room's screen.\n";
