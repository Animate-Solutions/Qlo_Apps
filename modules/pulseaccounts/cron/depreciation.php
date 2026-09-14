<?php
/**
 * Month-end depreciation run. php cron/depreciation.php <token> [YYYY-MM]
 * With no period it runs the month that has just ended, and only once the month is over —
 * running it on the 3rd for last month is exactly what a monthly cron on day 1..5 does.
 * Idempotent: a period already run is skipped, never doubled.
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_ACC_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);

$period = isset($argv[2]) ? $argv[2] : Tools::getValue('period', date('Y-m', strtotime('first day of last month')));
if (!preg_match('/^\d{4}-\d{2}$/', $period)) { die('Period must look like 2026-08'); }
if ($period >= date('Y-m')) { die('Period '.$period." is not finished yet\n"); }
// The run is per property — each keeps its own asset register and its own journals — but the period
// itself is the calendar's, so it is validated once above rather than once per hotel. The existing
// catch stays inside the loop, because the audit row it writes belongs to the hotel that failed.
PulseCoreService::forEachHotel(function ($idHotel, $hotel) use ($period) {
    PulseAccService::ensurePeriods($period.'-01', 2);
    try {
        $r = PulseAccAssets::runDepreciation($period);
        $line = '['.$hotel.'] Depreciation '.$r['period'].': '.$r['assets'].' assets, '.number_format($r['total'], 2).' across '.count($r['journals']).' journals';
        foreach ($r['by_class'] as $cls => $amt) { $line .= "\n  ".$cls.': '.number_format($amt, 2); }
        echo $line."\n";
    } catch (Exception $e) {
        PulseCoreService::audit('pulseaccounts', 'cron_depreciation_failed', array('period' => $period, 'error' => $e->getMessage()));
        echo '['.$hotel.'] Depreciation '.$period.' failed: '.$e->getMessage()."\n";
    }
});
