<?php
/**
 * Once a night, after the night audit: rebuild yesterday's timesheets and raise the exceptions a supervisor
 * clears in the morning.
 *   php modules/pulsetime/cron/build.php <token> [days-back] [YYYY-MM-DD]
 *   or with ?token=<PULSE_TA_CRON_TOKEN>&days=2
 *
 * Days are rebuilt oldest first, because a night shift claims punches across midnight and the order matters.
 * A period that is already approved and locked is skipped, never silently recomputed.
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if (!hash_equals((string) Configuration::get('PULSE_TA_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);

$days = isset($argv[2]) ? (int) $argv[2] : (int) Tools::getValue('days', 2);
$days = max(1, min(60, $days));
$to = isset($argv[3]) ? $argv[3] : Tools::getValue('date', '');
$to = ($to && strtotime($to)) ? date('Y-m-d', strtotime($to)) : PulseTaService::bd();
$from = date('Y-m-d', strtotime($to.' -'.($days - 1).' day'));

$t0 = microtime(true);
$r = PulseTaEngine::buildRange($from, $to);

// Keep every open approval period's counters honest so the Timesheets screen tells the truth in the morning.
$periods = 0;
foreach (Db::getInstance()->executeS('SELECT id_pulse_ta_period FROM `'._DB_PREFIX_.'pulse_ta_period` WHERE status IN ("open","submitted","reopened")') as $p) {
    PulseTaTimesheet::refreshPeriod((int) $p['id_pulse_ta_period']);
    $periods++;
}

$purged = PulseTaPunch::purge();
$counts = PulseTaExceptionQueue::counts();
$ms = (int) round((microtime(true) - $t0) * 1000);

if ($counts['block'] > 0) {
    PulseTaService::alert($counts['block'].' blocking timesheet exception(s) are open — a payroll period cannot be approved until they are cleared.');
}

PulseCoreService::audit('pulsetime', 'cron_build', array('from' => $from, 'to' => $to, 'days' => $r['days'], 'timesheets' => $r['timesheets'],
    'exceptions' => $r['exceptions'], 'open' => $counts['total'], 'blocking' => $counts['block'], 'periods' => $periods, 'ms' => $ms));

echo 'Rebuilt '.$from.' → '.$to.': '.$r['days'].' day(s), '.$r['timesheets'].' timesheet(s), '.$r['exceptions']." exception(s) raised.\n";
echo 'Queue now: '.$counts['total'].' open, '.$counts['block']." blocking approval.\n";
echo 'Periods refreshed: '.$periods.', punches purged by retention: '.$purged.' ('.$ms."ms)\n";
if ($r['errors']) { echo 'Problems: '.implode(' | ', array_slice($r['errors'], 0, 8))."\n"; }
