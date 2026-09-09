<?php
/**
 * Demo data for Pulse Payroll — Rivers Crest Hotel, a 52-room property in Port Harcourt.
 *
 * Creates: ~85 staff across rooms, F&B, housekeeping, laundry, maintenance, security, accounts, sales,
 * admin and management, on plausible naira packages by grade; declarations and NHF consents for a
 * realistic minority; a completed and posted payroll run for the prior month with a payslip each; a
 * service-charge pool for the same month, distributed by points and approved; a weekly casual batch for
 * banqueting; two live staff loans and one salary advance; and the statutory remittance rows the run
 * raised.
 *
 * Idempotent: every row is keyed on a staff number, a period or a document number, and a second run
 * changes nothing. It never deletes anything.
 *
 * Run: php modules/pulsepayroll/seed/seed.php   (or in the browser with ?token=<PULSE_PR_CRON_TOKEN>)
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
if (php_sapi_name() !== 'cli') { $t = Tools::getValue('token'); if ($t !== Configuration::get('PULSE_PR_CRON_TOKEN')) { die('Invalid token'); } }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);

$D = Db::getInstance();
$made = array('employees' => 0, 'declarations' => 0, 'loans' => 0, 'runs' => 0, 'payslips' => 0, 'tronc' => 0, 'casual' => 0, 'timesheets' => 0);

Configuration::updateValue('PULSE_PR_EMPLOYER_STAFF_COUNT', 85);
Configuration::updateValue('PULSE_PR_EMPLOYER_TURNOVER', 1250000000);
Configuration::updateValue('PULSE_PR_TAX_STATE', 'Rivers State Internal Revenue Service');

$bd = PulsePrService::bd();
$period = date('Y-m', strtotime($bd.' -1 month'));
$periodFrom = PulsePrService::periodFrom($period);
$periodTo = PulsePrService::periodTo($period);

/* ---------- 1. the establishment ---------- */

$firstM = array('Chinedu', 'Emeka', 'Ifeanyi', 'Tamuno', 'Ebiere', 'Sopuruchi', 'Boma', 'Ikechukwu', 'Uche', 'Godwin', 'Sunday', 'Musa', 'Abdullahi', 'Segun', 'Tunde', 'Kelechi', 'Obinna', 'Datonye', 'Soibi', 'Preye', 'Bright', 'Victor', 'Daniel', 'Samuel', 'Peter', 'Joshua', 'Nnamdi', 'Ozioma', 'Chukwuma', 'Ayodele');
$firstF = array('Ngozi', 'Adaeze', 'Ibinabo', 'Tamunotonye', 'Blessing', 'Grace', 'Chioma', 'Ifeoma', 'Amaka', 'Funmilayo', 'Aisha', 'Halima', 'Ebiere', 'Gloria', 'Patience', 'Joy', 'Mercy', 'Chinyere', 'Onyinye', 'Bolanle', 'Kemi', 'Rita', 'Esther', 'Faith', 'Precious', 'Nkechi', 'Doris', 'Uzoma', 'Somtochukwu', 'Amarachi');
$last = array('Amadi', 'Okoro', 'Wokoma', 'Briggs', 'Jumbo', 'Pepple', 'Georgewill', 'Nwachukwu', 'Eze', 'Obi', 'Adeyemi', 'Bello', 'Ibrahim', 'Danjuma', 'Okonkwo', 'Uzoma', 'Harry', 'Fubara', 'Amaechi', 'Wike', 'Sokari', 'Iyalla', 'Dokubo', 'Alabo', 'Ndah', 'Oyibo', 'Ogbonna', 'Nnadi', 'Chukwu', 'Effiong');
$banks = array(array('Zenith Bank', '057'), array('Guaranty Trust Bank', '058'), array('Access Bank', '044'), array('United Bank for Africa', '033'), array('First Bank of Nigeria', '011'), array('Fidelity Bank', '070'), array('Union Bank of Nigeria', '032'), array('Sterling Bank', '232'), array('Moniepoint MFB', '50515'), array('Opay Digital Services', '999992'));
$pfas = array('Stanbic IBTC Pension Managers', 'ARM Pension Managers', 'Premium Pension', 'Leadway Pensure', 'Trustfund Pensions', 'Access Pensions');

/**
 * The establishment, grade by grade. Packages are the monthly contractual gross for a Port Harcourt
 * four-star property in 2026: an attendant on N150k, a supervisor on N450k, a duty manager on N1.2m,
 * the financial controller on N2.4m and the general manager on N4m.
 */
$plan = array(
    array('n' => 1, 'dept' => 'management', 'grade' => 'EXEC', 'pos' => 'General Manager', 'pay' => 4000000),
    array('n' => 1, 'dept' => 'accounts', 'grade' => 'SNRMGR', 'pos' => 'Financial Controller', 'pay' => 2400000),
    array('n' => 1, 'dept' => 'rooms', 'grade' => 'SNRMGR', 'pos' => 'Front Office Manager', 'pay' => 1500000),
    array('n' => 1, 'dept' => 'fnb', 'grade' => 'SNRMGR', 'pos' => 'Food & Beverage Manager', 'pay' => 1500000),
    array('n' => 1, 'dept' => 'housekeeping', 'grade' => 'SNRMGR', 'pos' => 'Executive Housekeeper', 'pay' => 1200000),
    array('n' => 1, 'dept' => 'sales', 'grade' => 'SNRMGR', 'pos' => 'Sales & Marketing Manager', 'pay' => 1200000),
    array('n' => 1, 'dept' => 'maintenance', 'grade' => 'SNRMGR', 'pos' => 'Chief Engineer', 'pay' => 1200000),
    array('n' => 3, 'dept' => 'rooms', 'grade' => 'MGR', 'pos' => 'Duty Manager', 'pay' => 1200000),
    array('n' => 1, 'dept' => 'fnb', 'grade' => 'MGR', 'pos' => 'Executive Chef', 'pay' => 1200000),
    array('n' => 1, 'dept' => 'admin', 'grade' => 'MGR', 'pos' => 'HR & Admin Manager', 'pay' => 1000000),
    array('n' => 1, 'dept' => 'security', 'grade' => 'MGR', 'pos' => 'Security Manager', 'pay' => 850000),
    array('n' => 2, 'dept' => 'accounts', 'grade' => 'SUP', 'pos' => 'Accountant', 'pay' => 650000),
    array('n' => 2, 'dept' => 'rooms', 'grade' => 'SUP', 'pos' => 'Front Office Supervisor', 'pay' => 450000),
    array('n' => 3, 'dept' => 'fnb', 'grade' => 'SUP', 'pos' => 'Restaurant Supervisor', 'pay' => 450000),
    array('n' => 3, 'dept' => 'housekeeping', 'grade' => 'SUP', 'pos' => 'Housekeeping Supervisor', 'pay' => 420000),
    array('n' => 2, 'dept' => 'fnb', 'grade' => 'SUP', 'pos' => 'Sous Chef', 'pay' => 520000),
    array('n' => 1, 'dept' => 'laundry', 'grade' => 'SUP', 'pos' => 'Laundry Supervisor', 'pay' => 380000),
    array('n' => 2, 'dept' => 'maintenance', 'grade' => 'SUP', 'pos' => 'Maintenance Technician', 'pay' => 380000),
    array('n' => 5, 'dept' => 'rooms', 'grade' => 'STAFF', 'pos' => 'Front Desk Agent', 'pay' => 230000),
    array('n' => 3, 'dept' => 'rooms', 'grade' => 'STAFF', 'pos' => 'Guest Relations Officer', 'pay' => 250000),
    array('n' => 3, 'dept' => 'rooms', 'grade' => 'JNR', 'pos' => 'Porter', 'pay' => 130000),
    array('n' => 12, 'dept' => 'housekeeping', 'grade' => 'JNR', 'pos' => 'Room Attendant', 'pay' => 150000),
    array('n' => 3, 'dept' => 'housekeeping', 'grade' => 'JNR', 'pos' => 'Public Area Cleaner', 'pay' => 130000),
    array('n' => 5, 'dept' => 'fnb', 'grade' => 'STAFF', 'pos' => 'Line Cook', 'pay' => 260000),
    array('n' => 6, 'dept' => 'fnb', 'grade' => 'JNR', 'pos' => 'Waiter', 'pay' => 160000),
    array('n' => 2, 'dept' => 'fnb', 'grade' => 'STAFF', 'pos' => 'Bartender', 'pay' => 200000),
    array('n' => 2, 'dept' => 'fnb', 'grade' => 'JNR', 'pos' => 'Kitchen Steward', 'pay' => 140000),
    array('n' => 3, 'dept' => 'laundry', 'grade' => 'JNR', 'pos' => 'Laundry Attendant', 'pay' => 145000),
    array('n' => 3, 'dept' => 'maintenance', 'grade' => 'JNR', 'pos' => 'Handyman', 'pay' => 160000),
    array('n' => 6, 'dept' => 'security', 'grade' => 'JNR', 'pos' => 'Security Officer', 'pay' => 140000),
    array('n' => 2, 'dept' => 'accounts', 'grade' => 'STAFF', 'pos' => 'Store Officer', 'pay' => 250000),
    array('n' => 2, 'dept' => 'admin', 'grade' => 'STAFF', 'pos' => 'Admin Officer', 'pay' => 240000),
    array('n' => 2, 'dept' => 'sales', 'grade' => 'STAFF', 'pos' => 'Sales Executive', 'pay' => 300000),
);

$seq = 0; $ids = array(); $roster = array();
foreach ($plan as $slot) {
    for ($i = 0; $i < $slot['n']; $i++) {
        $seq++;
        $staffNo = 'RCH'.str_pad($seq, 4, '0', STR_PAD_LEFT);
        $existing = PulsePrService::employeeByStaffNo($staffNo);
        $male = ($seq % 2) === 0;
        $first = $male ? $firstM[$seq % count($firstM)] : $firstF[$seq % count($firstF)];
        $surname = $last[($seq * 7) % count($last)];
        $bank = $banks[$seq % count($banks)];
        $hire = date('Y-m-d', strtotime($periodFrom.' -'.(180 + ($seq * 23) % 2200).' day'));
        $d = array(
            'id_pulse_pr_employee' => $existing ? (int) $existing['id_pulse_pr_employee'] : 0,
            'staff_no' => $staffNo, 'firstname' => $first, 'lastname' => $surname,
            'department' => $slot['dept'], 'position' => $slot['pos'], 'grade' => $slot['grade'], 'cost_centre' => $slot['dept'],
            'employment_type' => 'permanent', 'pay_basis' => 'monthly', 'pay_rate' => $slot['pay'],
            'country' => 'NG', 'currency' => 'NGN', 'hire_date' => $hire, 'status' => 'active',
            'tin' => '2'.str_pad((string) (10000000 + $seq * 137), 8, '0', STR_PAD_LEFT).'-0001',
            'tax_state' => 'Rivers', 'rsa_pin' => 'PEN'.str_pad((string) (100000000000 + $seq * 991), 12, '0', STR_PAD_LEFT),
            'pfa' => $pfas[$seq % count($pfas)], 'nin' => str_pad((string) (10000000000 + $seq * 4231), 11, '0', STR_PAD_LEFT),
            'bank_name' => $bank[0], 'bank_code' => $bank[1],
            'account_no' => str_pad((string) (2000000000 + $seq * 733), 10, '0', STR_PAD_LEFT),
            'account_name' => $first.' '.$surname,
            'email' => Tools::strtolower($first.'.'.$surname).'@riverscrest.ng', 'phone' => '080'.str_pad((string) (30000000 + $seq * 1471), 8, '0', STR_PAD_LEFT),
            'pay_method' => $slot['pay'] < 150000 ? 'cash' : 'bank',
        );
        $id = PulsePrService::saveEmployee($d);
        if (!$existing) { $made['employees']++; }
        $ids[] = $id;
        $roster[$id] = array_merge($d, array('id' => $id));
    }
}

/* a mid-month joiner and a leaver, so the proration and final-settlement paths have something real to chew on */
$joinerNo = 'RCH9001';
$joinerExisting = PulsePrService::employeeByStaffNo($joinerNo);
$idJoiner = PulsePrService::saveEmployee(array(
    'id_pulse_pr_employee' => $joinerExisting ? (int) $joinerExisting['id_pulse_pr_employee'] : 0,
    'staff_no' => $joinerNo, 'firstname' => 'Tamunoemi', 'lastname' => 'Wokoma', 'department' => 'housekeeping', 'position' => 'Room Attendant',
    'grade' => 'JNR', 'cost_centre' => 'housekeeping', 'pay_basis' => 'monthly', 'pay_rate' => 150000, 'country' => 'NG',
    'hire_date' => date('Y-m-16', strtotime($periodFrom)), 'status' => 'active', 'pay_method' => 'bank',
    'bank_name' => 'Access Bank', 'bank_code' => '044', 'account_no' => '0790123456', 'account_name' => 'Tamunoemi Wokoma',
    'email' => 'tamunoemi.wokoma@riverscrest.ng', 'phone' => '08037654321', 'rsa_pin' => 'PEN100000009001', 'pfa' => 'ARM Pension Managers',
));
if (!$joinerExisting) { $made['employees']++; }

$leaverNo = 'RCH9002';
$leaverExisting = PulsePrService::employeeByStaffNo($leaverNo);
$idLeaver = PulsePrService::saveEmployee(array(
    'id_pulse_pr_employee' => $leaverExisting ? (int) $leaverExisting['id_pulse_pr_employee'] : 0,
    'staff_no' => $leaverNo, 'firstname' => 'Ibinabo', 'lastname' => 'Briggs', 'department' => 'fnb', 'position' => 'Restaurant Supervisor',
    'grade' => 'SUP', 'cost_centre' => 'fnb', 'pay_basis' => 'monthly', 'pay_rate' => 450000, 'country' => 'NG',
    'hire_date' => date('Y-m-d', strtotime($periodFrom.' -1400 day')), 'exit_date' => date('Y-m-10', strtotime($periodFrom)), 'status' => 'exited',
    'pay_method' => 'bank', 'bank_name' => 'Zenith Bank', 'bank_code' => '057', 'account_no' => '1012345678', 'account_name' => 'Ibinabo Briggs',
    'email' => 'ibinabo.briggs@riverscrest.ng', 'phone' => '08029876543', 'rsa_pin' => 'PEN100000009002', 'pfa' => 'Leadway Pensure',
));
if (!$leaverExisting) { $made['employees']++; }

/* a handful of casual and banqueting extras, paid weekly */
$casualIds = array();
foreach (array(
    array('RCH7001', 'Sopuruchi', 'Nnadi', 'fnb', 'Banqueting waiter', 9000),
    array('RCH7002', 'Preye', 'Sokari', 'fnb', 'Banqueting waiter', 9000),
    array('RCH7003', 'Blessing', 'Ndah', 'housekeeping', 'Casual room attendant', 8000),
    array('RCH7004', 'Godwin', 'Dokubo', 'laundry', 'Laundry casual', 7500),
    array('RCH7005', 'Bright', 'Alabo', 'maintenance', 'Casual painter', 10000),
    array('RCH7006', 'Doris', 'Oyibo', 'fnb', 'Kitchen casual', 8000),
) as $c) {
    $ex = PulsePrService::employeeByStaffNo($c[0]);
    $casualIds[] = PulsePrService::saveEmployee(array(
        'id_pulse_pr_employee' => $ex ? (int) $ex['id_pulse_pr_employee'] : 0, 'staff_no' => $c[0], 'firstname' => $c[1], 'lastname' => $c[2],
        'department' => $c[3], 'position' => $c[4], 'grade' => 'CASUAL', 'employment_type' => 'casual', 'pay_basis' => 'daily', 'pay_rate' => $c[5],
        'country' => 'NG', 'status' => 'active', 'pay_method' => 'cash', 'hire_date' => date('Y-m-d', strtotime($periodFrom.' -90 day')),
        'phone' => '0807'.str_pad((string) (1000000 + strlen($c[1]) * 77777), 7, '0', STR_PAD_LEFT),
    ));
    if (!$ex) { $made['employees']++; }
}

/* ---------- 2. declarations, consents and evidence ---------- */

/* about one in seven staff has consented to NHF; three senior people have evidenced rent */
foreach ($ids as $k => $id) {
    if ($k % 7 !== 0) { continue; }
    if ($D->getValue('SELECT id_pulse_pr_declaration FROM `'._DB_PREFIX_.'pulse_pr_declaration` WHERE id_pulse_pr_employee='.(int) $id.' AND code="NHF_CONSENT"')) { continue; }
    PulsePrService::saveDeclaration(array('id_pulse_pr_employee' => $id, 'code' => 'NHF_CONSENT', 'consented' => 1,
        'consent_date' => date('Y-01-15'), 'consent_channel' => 'signed form', 'evidence_verified' => 1,
        'date_from' => date('Y-01-15'), 'note' => 'Signed NHF consent on file with HR'));
    $made['declarations']++;
}
foreach (array(array($ids[0], 7200000, 'Tenancy agreement, 14 Aba Road, GRA Phase II'), array($ids[1], 4800000, 'Tenancy agreement, Woji'), array($ids[2], 3600000, 'Tenancy agreement, Rumuola')) as $r) {
    if ($D->getValue('SELECT id_pulse_pr_declaration FROM `'._DB_PREFIX_.'pulse_pr_declaration` WHERE id_pulse_pr_employee='.(int) $r[0].' AND code="RENT"')) { continue; }
    PulsePrService::saveDeclaration(array('id_pulse_pr_employee' => $r[0], 'code' => 'RENT', 'annual_value' => $r[1],
        'evidence_ref' => $r[2], 'evidence_verified' => 1, 'date_from' => date('Y-01-01'), 'note' => 'Rent relief: 20% capped at N500,000'));
    $made['declarations']++;
}
/* one declared without evidence, so the withheld-relief case is visible on a real screen */
if (!$D->getValue('SELECT id_pulse_pr_declaration FROM `'._DB_PREFIX_.'pulse_pr_declaration` WHERE id_pulse_pr_employee='.(int) $ids[3].' AND code="RENT"')) {
    PulsePrService::saveDeclaration(array('id_pulse_pr_employee' => $ids[3], 'code' => 'RENT', 'annual_value' => 2400000,
        'evidence_ref' => 'Awaiting the tenancy agreement', 'evidence_verified' => 0, 'date_from' => date('Y-01-01'),
        'note' => 'Relief withheld until the agreement is produced'));
    $made['declarations']++;
}

/* the leaver has already been paid this tax year, so their final settlement closes the year properly
   instead of pretending they started in the month they left */
$monthsBefore = (int) Tools::substr($period, 5, 2) - 1;
if ($monthsBefore > 0 && !$D->getValue('SELECT id_pulse_pr_opening FROM `'._DB_PREFIX_.'pulse_pr_opening` WHERE id_pulse_pr_employee='.(int) $idLeaver)) {
    $paye = round(699792 / 12, 2) * $monthsBefore;
    $D->insert('pulse_pr_opening', array(
        'id_pulse_pr_employee' => (int) $idLeaver, 'tax_year' => (int) Tools::substr($period, 0, 4), 'periods' => $monthsBefore,
        'gross' => 450000 * $monthsBefore, 'taxable' => 450000 * $monthsBefore, 'paye' => $paye,
        'pension_ee' => 28800 * $monthsBefore, 'pension_er' => 36000 * $monthsBefore, 'nhf' => 0,
        'net' => round((450000 - 28800) * $monthsBefore - $paye, 2), 'note' => 'Seeded: paid January to '.date('F', strtotime($periodFrom.' -1 month')),
        'date_add' => date('Y-m-d H:i:s'),
    ), true);
}

/* ---------- 3. timesheets for the period (overtime and a couple of unpaid days) ---------- */

foreach ($ids as $k => $id) {
    if ($D->getValue('SELECT id_pulse_pr_timesheet FROM `'._DB_PREFIX_.'pulse_pr_timesheet` WHERE id_pulse_pr_employee='.(int) $id.' AND period="'.pSQL($period).'"')) { continue; }
    PulsePrService::saveTimesheet(array(
        'id_pulse_pr_employee' => $id, 'period' => $period, 'days_worked' => 26, 'hours_worked' => 208,
        'ot_hours' => $k % 5 === 0 ? 12 : 0, 'night_shifts' => $k % 4 === 0 ? 8 : 0,
        'unpaid_days' => $k === 11 ? 2 : 0, 'approved' => 1, 'note' => 'Seeded demo timesheet',
    ));
    $made['timesheets']++;
}

/* ---------- 4. loans and an advance ---------- */

foreach (array(
    array($ids[20], 'loan', 600000, 0, 6, 'School fees'),
    array($ids[35], 'loan', 250000, 5, 5, 'Medical'),
    array($ids[42], 'salary_advance', 80000, 0, 2, 'Salary advance'),
) as $l) {
    if ($D->getValue('SELECT id_pulse_pr_loan FROM `'._DB_PREFIX_.'pulse_pr_loan` WHERE id_pulse_pr_employee='.(int) $l[0].' AND purpose="'.pSQL($l[5]).'"')) { continue; }
    $idLoan = PulsePrLoan::apply(array('id_pulse_pr_employee' => $l[0], 'type' => $l[1], 'principal' => $l[2], 'interest_pct' => $l[3],
        'instalments' => $l[4], 'first_period' => $period, 'purpose' => $l[5], 'date_applied' => date('Y-m-d', strtotime($periodFrom.' -20 day'))));
    PulsePrLoan::setStatus($idLoan, 'approved');
    PulsePrLoan::setStatus($idLoan, 'disbursed');
    $made['loans']++;
}

/* ---------- 5. the service charge pool for the period ---------- */

$idPool = (int) $D->getValue('SELECT id_pulse_pr_tronc_pool FROM `'._DB_PREFIX_.'pulse_pr_tronc_pool` WHERE period="'.pSQL($period).'"');
if (!$idPool) {
    $idPool = PulsePrTronc::createPool(array('period' => $period, 'basis' => 'points', 'collected_manual' => 4850000,
        'admin_pct' => 0, 'management_cap_pct' => 10, 'note' => 'Seeded: 10% service charge collected on F&B and rooms for '.$period));
    PulsePrTronc::distribute($idPool);
    PulsePrTronc::approve($idPool);
    $made['tronc']++;
}

/* ---------- 6. the payroll run for the period ---------- */

$idRun = (int) $D->getValue('SELECT id_pulse_pr_run FROM `'._DB_PREFIX_.'pulse_pr_run` WHERE period="'.pSQL($period).'" AND run_type="regular" AND status<>"cancelled"');
if (!$idRun) {
    $idRun = PulsePrRun::create(array('period' => $period, 'run_type' => 'regular', 'country' => 'NG', 'note' => 'Seeded demo run'));
    $r = PulsePrRun::calculate($idRun);
    $made['runs']++; $made['payslips'] = $r['headcount'];
    if (!$r['errors']) {
        try {
            PulsePrRun::approve($idRun);
            PulsePrRun::markPaid($idRun, 'Seeded: paid by transfer');
            if (PulsePrService::acc()) { PulsePrRun::post($idRun); }
            PulsePrTronc::markPaidInRun($idPool, $idRun);
            PulsePrBankFile::generateForRun($idRun, 'bank');
        } catch (Exception $e) { echo 'Run approval stopped: '.$e->getMessage()."\n"; }
    } else {
        echo count($r['errors'])." employees could not be calculated:\n  ".implode("\n  ", array_slice($r['errors'], 0, 10))."\n";
    }
}

/* ---------- 7. a weekly casual batch ---------- */

$weekStart = date('Y-m-d', strtotime($periodTo.' -6 day'));
$idBatch = (int) $D->getValue('SELECT id_pulse_pr_casual_batch FROM `'._DB_PREFIX_.'pulse_pr_casual_batch` WHERE week_start="'.pSQL($weekStart).'"');
if (!$idBatch) {
    $idBatch = PulsePrCasual::createBatch(array('week_start' => $weekStart, 'pay_method' => 'cash', 'note' => 'Seeded: banqueting week — two weddings and a conference'));
    PulsePrCasual::pullFromTimesheets($idBatch);
    $days = array(5, 6, 4, 3, 2, 5);
    $b = PulsePrCasual::batch($idBatch);
    foreach ($b['lines'] as $i => $line) {
        PulsePrCasual::saveLine(array_merge($line, array('id_pulse_pr_casual_line' => (int) $line['id_pulse_pr_casual_line'], 'units' => $days[$i % count($days)])));
    }
    PulsePrCasual::approve($idBatch);
    PulsePrCasual::markPaid($idBatch);
    $made['casual']++;
}

/* ---------- summary ---------- */

$run = $idRun ? PulsePrRun::get($idRun) : null;
echo "Pulse Payroll demo data for Rivers Crest Hotel, Port Harcourt\n";
echo '  employees created ....... '.$made['employees']."\n";
echo '  declarations/consents ... '.$made['declarations']."\n";
echo '  timesheets .............. '.$made['timesheets']."\n";
echo '  loans and advances ...... '.$made['loans']."\n";
echo '  service charge pools .... '.$made['tronc']."\n";
echo '  casual weeks ............ '.$made['casual']."\n";
if ($run) {
    echo '  payroll run ............. '.$run['run_no'].' for '.$run['period'].', status '.$run['status']."\n";
    echo '    headcount ............. '.$run['headcount']."\n";
    echo '    gross ................. '.number_format((float) $run['total_gross'], 2)."\n";
    echo '    PAYE .................. '.number_format((float) $run['total_paye'], 2)."\n";
    echo '    pension (ee + er) ..... '.number_format((float) $run['total_pension_ee'] + (float) $run['total_pension_er'], 2)."\n";
    echo '    NSITF / ITF ........... '.number_format((float) $run['total_nsitf'], 2).' / '.number_format((float) $run['total_itf'], 2)."\n";
    echo '    net pay ............... '.number_format((float) $run['total_net'], 2)."\n";
    echo '    total employer cost ... '.number_format((float) $run['total_gross'] + (float) $run['total_employer_cost'], 2)."\n";
    echo '    result hash ........... '.$run['result_hash']."\n";
}
echo "Nothing was deleted. Run it again and it changes nothing.\n";
