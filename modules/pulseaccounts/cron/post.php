<?php
/** Drain the posting queue and the e-invoice queue. php cron/post.php <token> [business_date] */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_ACC_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);

// An explicit date applies to every property; with none, each falls back to its own business date.
$given = isset($argv[2]) ? $argv[2] : Tools::getValue('business_date', '');
$batch = (int) Configuration::get('PULSE_ACC_QUEUE_BATCH');
$einvoices = (bool) Configuration::get('PULSE_ACC_EINV_ENABLED');
$run = PulseCoreService::forEachHotel(function ($idHotel, $hotel) use ($given, $batch, $einvoices) {
    $date = $given ? $given : PulseAccService::bd();
    PulseAccService::ensurePeriods($date, 2);
    $queued = PulseAccPosting::sweep($date);
    $r = PulseAccPosting::drain($batch);
    $e = $einvoices ? PulseAccTax::einvoiceDrain(50) : array('sent' => 0, 'pending' => 0);
    $counts = PulseAccPosting::queueCounts();
    PulseCoreService::audit('pulseaccounts', 'cron_post', array('date' => $date, 'queued' => $queued, 'posted' => $r['posted'], 'failed' => $r['failed']));
    echo '['.$hotel.'] Business date '.$date.': queued '.$queued.', posted '.$r['posted'].', skipped '.$r['skipped'].', failed '.$r['failed']
        .' | still pending '.$counts['pending'].', failed '.$counts['failed']
        .' | e-invoices sent '.$e['sent'].', pending '.$e['pending']."\n";
});
foreach ($run['results'] as $h) { if (!$h['ok']) { echo '['.$h['name'].'] FAILED: '.$h['error']."\n"; } }
