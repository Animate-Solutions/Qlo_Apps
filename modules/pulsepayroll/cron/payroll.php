<?php
/**
 * Pulse Payroll scheduled work.
 *   php cron/payroll.php <token> [task]
 *   or in the browser: modules/pulsepayroll/cron/payroll.php?token=<PULSE_PR_CRON_TOKEN>&task=all
 *
 * Tasks: payslips (email the queue from approved runs) · accrue (ITF and the statutory remittance rows)
 *        · loans (expire and settle) · remind (flag overdue remittances) · selfcheck (the synthetic
 *        payroll assertions) · all (everything except selfcheck).
 *
 * Every task is idempotent and safe to run hourly; nothing here approves, pays or posts anything on its
 * own — money never moves without a person pressing a button.
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_PR_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);

$task = isset($argv[2]) ? $argv[2] : Tools::getValue('task', 'all');
$out = array();

if ($task === 'selfcheck') {
    $r = PulsePrSelfCheck::run();
    foreach ($r['cases'] as $c) { if (!$c['pass']) { echo 'FAIL  '.$c['case'].' — '.$c['metric'].': expected '.$c['expected'].', got '.$c['actual']."\n"; } }
    echo 'Self-check: '.$r['passed'].' passed, '.$r['failed']." failed\n";
    exit($r['ok'] ? 0 : 1);
}

/* ---- email the payslips queued by every approved run that still has some outstanding ---- */
if ($task === 'all' || $task === 'payslips') {
    $sent = 0; $failed = 0;
    if ((int) Configuration::get('PULSE_PR_PAYSLIP_EMAIL')) {
        foreach (Db::getInstance()->executeS('SELECT DISTINCT r.id_pulse_pr_run FROM `'._DB_PREFIX_.'pulse_pr_run` r INNER JOIN `'._DB_PREFIX_.'pulse_pr_payslip` p ON p.id_pulse_pr_run=r.id_pulse_pr_run WHERE r.status IN ("approved","paid","posted") AND p.emailed_at IS NULL LIMIT 20') as $r) {
            try { $x = PulsePrPayslip::emailRun((int) $r['id_pulse_pr_run'], 100); $sent += $x['sent']; $failed += $x['failed']; } catch (Exception $e) { $failed++; }
        }
    }
    $out[] = 'payslips emailed '.$sent.', failed '.$failed;
}

/* ---- keep the ITF accrual and the remittance rows current ---- */
if ($task === 'all' || $task === 'accrue') {
    $bd = PulsePrService::bd();
    PulsePrService::accrueDaily($bd);
    $year = (int) Tools::substr($bd, 0, 4);
    $itf = round((float) Db::getInstance()->getValue('SELECT COALESCE(SUM(p.itf_er),0) FROM `'._DB_PREFIX_.'pulse_pr_payslip` p INNER JOIN `'._DB_PREFIX_.'pulse_pr_run` r ON r.id_pulse_pr_run=p.id_pulse_pr_run WHERE p.period LIKE "'.pSQL((string) $year).'-%" AND r.status IN ("approved","paid","posted")'), 2);
    $out[] = 'ITF accrued to date for '.$year.': '.number_format($itf, 2);
}

/* ---- settle loans that are fully recovered, and move disbursed loans into repaying ---- */
if ($task === 'all' || $task === 'loans') {
    $n = 0;
    foreach (Db::getInstance()->executeS('SELECT id_pulse_pr_loan FROM `'._DB_PREFIX_.'pulse_pr_loan` WHERE status IN ("disbursed","repaying")') as $l) { PulsePrLoan::refreshLoan((int) $l['id_pulse_pr_loan']); $n++; }
    $settled = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pr_loan` WHERE status="settled" AND date_settled="'.pSQL(date('Y-m-d')).'"');
    $out[] = 'loans refreshed '.$n.', settled today '.$settled;
}

/* ---- flag statutory remittances that have gone past their due date ---- */
if ($task === 'all' || $task === 'remind') {
    Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'pulse_pr_remittance` SET status="overdue", date_upd=NOW() WHERE status IN ("due","part") AND due_date<"'.pSQL(date('Y-m-d')).'"');
    $overdue = Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'pulse_pr_remittance` WHERE status="overdue" ORDER BY due_date');
    foreach ($overdue as $r) {
        if (!class_exists('PulseTicket')) { continue; }
        $ref = 'remit:'.$r['scheme'].':'.$r['period'];
        if (PulseCoreService::setting('pulsepayroll', 'ticket_'.$ref)) { continue; }
        try {
            PulseTicket::create(array('category' => 'admin', 'department' => 'accounts', 'priority' => 'high',
                'title' => 'Statutory remittance overdue: '.Tools::strtoupper($r['scheme']).' '.$r['period'],
                'description' => number_format((float) $r['amount_due'], 2).' due to '.$r['authority'].' by '.$r['due_date'].'. Late remittance attracts a penalty.',
                'source' => 'payroll'));
            PulseCoreService::setting('pulsepayroll', 'ticket_'.$ref, date('Y-m-d'));
        } catch (Exception $e) { /* a ticket is a nicety; the overdue flag is the control */ }
    }
    $out[] = 'remittances overdue '.count($overdue);
}

PulseCoreService::audit('pulsepayroll', 'cron_payroll', array('task' => $task, 'result' => $out));
echo implode(' | ', $out)."\n";
