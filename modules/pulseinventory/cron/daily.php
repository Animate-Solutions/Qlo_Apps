<?php
/** Daily (after night audit): amenity consumption, expiry & reorder alerts. Redundant with the night-audit hook — use when Front Desk is not installed. */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_INV_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$n = Configuration::get('PULSE_INV_AUTO_AMENITY') ? PulseInvMinibar::postDailyAmenities(date('Y-m-d', strtotime('-1 day'))) : 0;
echo 'Amenity lines: '.$n.'; expiring: '.count(PulseInvService::expiring((int) Configuration::get('PULSE_INV_EXPIRY_DAYS'))).'; reorder: '.count(PulseInvService::reorderSuggestions());
