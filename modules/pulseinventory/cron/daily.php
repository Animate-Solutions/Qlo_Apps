<?php
/** Daily (after night audit): amenity consumption, expiry & reorder alerts. Redundant with the night-audit hook — use when Front Desk is not installed. */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_INV_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$date = date('Y-m-d', strtotime('-1 day'));
$amenities = (bool) Configuration::get('PULSE_INV_AUTO_AMENITY');
$expiryDays = (int) Configuration::get('PULSE_INV_EXPIRY_DAYS');
$run = PulseCoreService::forEachHotel(function ($idHotel, $hotel) use ($date, $amenities, $expiryDays) {
    $n = $amenities ? PulseInvMinibar::postDailyAmenities($date) : 0;
    echo '['.$hotel.'] Amenity lines: '.$n.'; expiring: '.count(PulseInvService::expiring($expiryDays)).'; reorder: '.count(PulseInvService::reorderSuggestions())."\n";
});
foreach ($run['results'] as $h) { if (!$h['ok']) { echo '['.$h['name'].'] FAILED: '.$h['error']."\n"; } }
