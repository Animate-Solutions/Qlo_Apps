<?php
/**
 * The one HR job. Run it nightly, after the night audit (the audit hook does the same work when Front Desk is
 * present; this is the belt to that braces, and the only mechanism when Front Desk is not installed).
 * Every step is independent and idempotent, so a run that dies halfway loses nothing.
 * Usage: php modules/pulsehr/cron/hr.php <token> [task]  (or in a browser with ?token=<PULSE_HR_CRON_TOKEN>&task=<task>)
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if (!hash_equals((string) Configuration::get('PULSE_HR_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$task = isset($argv[2]) ? $argv[2] : Tools::getValue('task', 'all');
$t0 = microtime(true);
$out = array();

if ($task === 'all' || $task === 'accrue') {
    $day = (int) PulseHrService::cfg('ACCRUAL_DAY', 1);
    if ((int) date('j') === $day || $task === 'accrue') {
        $n = PulseHrLeave::accrueMonth(date('Y-m'));
        $out[] = 'leave accrued on '.$n.' balance(s) for '.date('Y-m');
    } else {
        $out[] = 'leave accrual not due today (runs on day '.$day.')';
    }
}

if ($task === 'all' || $task === 'leave') {
    PulseHrLeave::rollDay(PulseHrService::bd());
    $pending = count(PulseHrLeave::pending());
    $out[] = 'leave rolled; '.$pending.' request(s) still waiting for a decision';
    // year end: on 1 January carry last year's balances forward, capped per type
    if (date('n-j') === '1-1') { $out[] = PulseHrLeave::carryOver((int) date('Y') - 1).' balance(s) carried into '.date('Y'); }
}

if ($task === 'all' || $task === 'documents') {
    $raised = PulseHrDocument::refreshStatuses();
    $expiring = count(PulseHrDocument::expiring((int) PulseHrService::cfg('DOC_REMIND_DAYS', 30)));
    $out[] = 'documents restamped; '.$expiring.' lapsing or lapsed, '.$raised.' ticket(s) raised';
}

if ($task === 'all' || $task === 'contracts') {
    $c = PulseHrContract::expiring((int) PulseHrService::cfg('CONTRACT_REMIND_DAYS', 30));
    $p = PulseHrEmployee::probationDue((int) PulseHrService::cfg('PROBATION_REMIND_DAYS', 30));
    if (($c || $p) && class_exists('PulseTrace')) {
        foreach ($c as $x) { PulseTrace::add('reminder', 'Contract '.$x['contract_no'].' for '.$x['employee_name'].' ends '.$x['end_date'], date('Y-m-d H:i:s'), null, null, null, 'management'); }
        foreach ($p as $x) { PulseTrace::add('reminder', 'Probation ends '.$x['probation_end'].' for '.$x['full_name'].' ('.$x['staff_no'].')', date('Y-m-d H:i:s'), null, null, null, 'management'); }
    }
    $out[] = count($c).' contract(s) and '.count($p).' probation(s) coming up';
}

if ($task === 'all' || $task === 'roster') {
    $gaps = PulseHrRoster::rollDay(PulseHrService::bd());
    $out[] = $gaps ? 'coverage gaps tomorrow: '.implode('; ', $gaps) : 'roster coverage for tomorrow looks covered';
}

if ($task === 'all' || $task === 'punches') {
    // anything the portal could not hand to Pulse Time at the time (module installed later, or a transient failure)
    $n = 0;
    foreach (Db::getInstance()->executeS('SELECT id_pulse_hr_punch FROM `'._DB_PREFIX_.'pulse_hr_punch` WHERE synced=0 AND status="accepted" AND business_date>=DATE_SUB(CURDATE(), INTERVAL 30 DAY) LIMIT 500') as $p) {
        if (PulseHrEss::handOver((int) $p['id_pulse_hr_punch'])) { $n++; }
    }
    $flagged = count(PulseHrEss::flaggedPunches(PulseHrService::bd(), 100));
    $out[] = PulseHrService::ta() ? $n.' punch(es) handed to Pulse Time; '.$flagged.' still flagged for a supervisor' : 'Pulse Time not installed; '.$flagged.' mobile punch(es) flagged for a supervisor';
}

if ($task === 'all' || $task === 'housekeeping') {
    PulseHrEss::purge(7);
    Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'pulse_hr_rate` WHERE window_start<'.(int) (floor(time() / 60) - 1440));
    $out[] = 'expired portal sessions, old sign-in logs and rate buckets purged';
}

$secs = round(microtime(true) - $t0, 1);
PulseCoreService::audit('pulsehr', 'cron', array('task' => $task, 'seconds' => $secs, 'lines' => $out));
echo 'Pulse HR cron ('.$task.') in '.$secs."s\n";
foreach ($out as $line) { echo ' · '.$line."\n"; }
