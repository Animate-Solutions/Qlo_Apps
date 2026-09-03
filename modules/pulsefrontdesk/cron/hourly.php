<?php
/** Hourly cron: late-fee automation, express check-out links (07:00), pre-check-in links (09:00), waitlist offers. */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../../pulsecore/classes/PulseCore.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_FD_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$out = array('late_fees' => PulseFdService::automateCheckoutDay(), 'waitlist_offers' => PulseWaitlist::processOffers());
$h = (int) date('G');
if ($h === 7) { $out['express_links'] = PulseFdService::sendExpressCheckoutLinks(); }
if ($h === 9) { $out['precheckin_links'] = PulseFdService::sendPrecheckinLinks((int) (Configuration::get('PULSE_FD_PRECHECKIN_DAYS') ?: 2)); }
echo json_encode($out);
