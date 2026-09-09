<?php
/**
 * Demo data for Pulse HR — a 52-room property in Port Harcourt.
 * Creates the org structure (departments already seeded, plus sections and 30-odd positions with a budgeted
 * establishment), ~86 staff with Nigerian names and plausible naira salary structures by grade, effective-dated
 * contracts including six mid-year promotions so the versioning is visible, documents with a couple about to
 * lapse, leave balances part way through the year with taken and pending requests, a published roster for the
 * current week, a fortnight of mobile punches including two a supervisor should look at, three leavers, two
 * live onboarding checklists and one completed clearance, discipline cases, an appraisal cycle and training.
 * Idempotent: staff numbers are deterministic, so a second run updates rather than duplicates. Nothing is deleted.
 * Usage: php modules/pulsehr/seed/seed.php   (or in a browser with ?token=<PULSE_HR_CRON_TOKEN>)
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
if (php_sapi_name() !== 'cli') {
    $token = Tools::getValue('token');
    if (!hash_equals((string) Configuration::get('PULSE_HR_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
}
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$db = Db::getInstance();
$today = date('Y-m-d');
$year = (int) date('Y');
$summary = array();
mt_srand(52); // a fixed seed so two runs tell the same story

/* ---------- 0. lookups ---------- */
$dept = array(); foreach (PulseHrService::departments(false) as $d) { $dept[$d['code']] = (int) $d['id_pulse_hr_department']; }
$grade = array(); foreach (PulseHrService::grades(false) as $g) { $grade[$g['code']] = (int) $g['id_pulse_hr_grade']; }
$shift = array(); foreach (PulseHrService::shifts(false) as $s) { $shift[$s['code']] = (int) $s['id_pulse_hr_shift']; }
if (!$dept || !$grade) { die("Install the module first — the reference data is missing.\n"); }

/* ---------- 1. sections ---------- */
$sections = array(
    array('rooms', 'FO-DESK', 'Front desk'), array('rooms', 'FO-RES', 'Reservations'), array('rooms', 'FO-CON', 'Concierge & bell'),
    array('housekeeping', 'HK-ROOMS', 'Rooms'), array('housekeeping', 'HK-PA', 'Public areas'), array('housekeeping', 'HK-LINEN', 'Linen room'),
    array('fnb', 'FB-REST', 'Restaurant'), array('fnb', 'FB-BAR', 'Bar'), array('fnb', 'FB-KIT', 'Kitchen'), array('fnb', 'FB-STEW', 'Stewarding'),
    array('maintenance', 'MT-ENG', 'Engineering'), array('accounts', 'AC-INC', 'Income audit'), array('accounts', 'AC-STORE', 'Stores'),
);
$sec = array(); $newSections = 0;
foreach ($sections as $s) {
    $id = (int) $db->getValue('SELECT id_pulse_hr_section FROM `'._DB_PREFIX_.'pulse_hr_section` WHERE code="'.pSQL($s[1]).'"');
    if (!$id) { $id = PulseHrService::saveSection(array('id_pulse_hr_department' => $dept[$s[0]], 'code' => $s[1], 'name' => $s[2])); $newSections++; }
    $sec[$s[1]] = $id;
}
$summary[] = $newSections.' section(s) created ('.count($sec).' in place)';

/* ---------- 2. positions with the budgeted establishment ---------- */
// code, title, dept, section, grade, establishment, nights
$positions = array(
    array('GM', 'General Manager', 'admin', null, 'G8', 1, 0),
    array('HRM', 'Human Resources Manager', 'admin', null, 'G6', 1, 0),
    array('HRO', 'HR & Admin Officer', 'admin', null, 'G3', 1, 0),
    array('FOM', 'Front Office Manager', 'rooms', 'FO-DESK', 'G6', 1, 0),
    array('DM', 'Duty Manager', 'rooms', 'FO-DESK', 'G5', 2, 1),
    array('FDA', 'Front Desk Agent', 'rooms', 'FO-DESK', 'G2', 5, 1),
    array('RES', 'Reservations Officer', 'rooms', 'FO-RES', 'G3', 2, 0),
    array('CON', 'Concierge / Bell Attendant', 'rooms', 'FO-CON', 'G1', 3, 1),
    array('EHK', 'Executive Housekeeper', 'housekeeping', 'HK-ROOMS', 'G6', 1, 0),
    array('HKS', 'Housekeeping Supervisor', 'housekeeping', 'HK-ROOMS', 'G4', 3, 0),
    array('RA', 'Room Attendant', 'housekeeping', 'HK-ROOMS', 'G2', 12, 0),
    array('PA', 'Public Area Attendant', 'housekeeping', 'HK-PA', 'G1', 4, 1),
    array('LR', 'Linen Room Attendant', 'housekeeping', 'HK-LINEN', 'G2', 2, 0),
    array('FBM', 'Food & Beverage Manager', 'fnb', 'FB-REST', 'G6', 1, 0),
    array('RSUP', 'Restaurant Supervisor', 'fnb', 'FB-REST', 'G4', 2, 0),
    array('WTR', 'Waiter', 'fnb', 'FB-REST', 'G1', 7, 1),
    array('BAR', 'Bartender', 'fnb', 'FB-BAR', 'G2', 3, 1),
    array('CHEF', 'Head Chef', 'fnb', 'FB-KIT', 'G6', 1, 0),
    array('SCHEF', 'Sous Chef', 'fnb', 'FB-KIT', 'G4', 1, 0),
    array('COOK', 'Line Cook', 'fnb', 'FB-KIT', 'G2', 5, 1),
    array('STEW', 'Kitchen Steward', 'fnb', 'FB-STEW', 'G1', 4, 1),
    array('LSUP', 'Laundry Supervisor', 'laundry', null, 'G4', 1, 0),
    array('LAT', 'Laundry Attendant', 'laundry', null, 'G2', 4, 0),
    array('CENG', 'Chief Engineer', 'maintenance', 'MT-ENG', 'G6', 1, 0),
    array('TECH', 'Maintenance Technician', 'maintenance', 'MT-ENG', 'G3', 4, 1),
    array('SSUP', 'Security Supervisor', 'security', null, 'G4', 1, 0),
    array('SEC', 'Security Officer', 'security', null, 'G1', 6, 1),
    array('SM', 'Sales & Marketing Manager', 'sales', null, 'G6', 1, 0),
    array('SE', 'Sales Executive', 'sales', null, 'G3', 2, 0),
    array('FC', 'Financial Controller', 'accounts', null, 'G7', 1, 0),
    array('ACC', 'Accountant', 'accounts', null, 'G5', 1, 0),
    array('IA', 'Income Auditor / Cashier', 'accounts', 'AC-INC', 'G3', 2, 1),
    array('STK', 'Storekeeper', 'accounts', 'AC-STORE', 'G2', 1, 0),
);
$pos = array(); $newPositions = 0;
foreach ($positions as $p) {
    $id = (int) $db->getValue('SELECT id_pulse_hr_position FROM `'._DB_PREFIX_.'pulse_hr_position` WHERE code="'.pSQL($p[0]).'"');
    if (!$id) {
        $id = PulseHrService::savePosition(array('code' => $p[0], 'title' => $p[1], 'id_pulse_hr_department' => $dept[$p[2]],
            'id_pulse_hr_section' => $p[3] ? $sec[$p[3]] : null, 'id_pulse_hr_grade' => $grade[$p[4]], 'establishment' => $p[5], 'night_shift' => $p[6]));
        $newPositions++;
    }
    $pos[$p[0]] = array('id' => $id, 'dept' => $p[2], 'section' => $p[3], 'grade' => $p[4], 'nights' => $p[6]);
}
$summary[] = $newPositions.' position(s) created ('.count($pos).' in the establishment)';

/* ---------- 3. the people ---------- */
$first = array('Chinedu', 'Amaka', 'Ibrahim', 'Folake', 'Tamunoemi', 'Ngozi', 'Segun', 'Halima', 'Emeka', 'Blessing', 'Kelechi', 'Zainab', 'Preye', 'Chioma', 'Musa',
    'Temitope', 'Soibi', 'Adaeze', 'Bala', 'Efe', 'Uche', 'Yetunde', 'Dumo', 'Aisha', 'Obinna', 'Sarah', 'Gbenga', 'Rita', 'Nasir', 'Ijeoma', 'Tonye', 'Funmi',
    'Chika', 'Grace', 'Yakubu', 'Ebele', 'Ledum', 'Patience', 'Sadiq', 'Onyinye', 'Barine', 'Joy', 'Godwin', 'Mercy', 'Kingsley', 'Deborah', 'Victor', 'Peace',
    'Samuel', 'Esther', 'Daniel', 'Gloria', 'Felix', 'Comfort', 'Innocent', 'Precious', 'Solomon', 'Faith', 'Bright', 'Charity', 'Anthony', 'Stella', 'Peter',
    'Janet', 'Michael', 'Rose', 'Paul', 'Ruth', 'John', 'Hope', 'Emmanuel', 'Naomi', 'Isaac', 'Vivian', 'Franklin', 'Chidinma', 'Henry', 'Bimpe', 'Lucky',
    'Queen', 'Osita', 'Tari', 'Ade', 'Nkechi', 'Bassey', 'Ifeoma', 'Gift', 'Sunday');
$last = array('Okafor', 'Nwosu', 'Danladi', 'Adeyemi', 'Briggs', 'Eze', 'Balogun', 'Sani', 'Obi', 'Etim', 'Amadi', 'Yusuf', 'Ogbonna', 'Nnamdi', 'Abdullahi',
    'Ogundele', 'Wokoma', 'Uche', 'Mohammed', 'Ovie', 'Anyanwu', 'Fashola', 'Georgewill', 'Bello', 'Chukwu', 'Peterside', 'Alabi', 'Iheanacho', 'Garba',
    'Okonkwo', 'Amachree', 'Bakare', 'Madu', 'Effiong', 'Idris', 'Nwachukwu', 'Nwiado', 'Akpan', 'Aliyu', 'Kalu', 'Wike', 'Nsirim', 'Ajayi', 'Onwuka',
    'Ekene', 'Dike', 'Iyalla', 'Jaja', 'Pepple', 'Horsfall', 'Wonodi', 'Ngerebara', 'Okrika', 'Fubara', 'Diri', 'Igwe', 'Achike', 'Umeh', 'Oputa', 'Nweke');

// position code => how many to hire (mirrors the establishment, one short here and there like a real hotel)
$hire = array('GM' => 1, 'HRM' => 1, 'HRO' => 1, 'FOM' => 1, 'DM' => 2, 'FDA' => 5, 'RES' => 2, 'CON' => 3, 'EHK' => 1, 'HKS' => 3, 'RA' => 12, 'PA' => 4,
    'LR' => 2, 'FBM' => 1, 'RSUP' => 2, 'WTR' => 7, 'BAR' => 3, 'CHEF' => 1, 'SCHEF' => 1, 'COOK' => 5, 'STEW' => 4, 'LSUP' => 1, 'LAT' => 4, 'CENG' => 1,
    'TECH' => 4, 'SSUP' => 1, 'SEC' => 6, 'SM' => 1, 'SE' => 2, 'FC' => 1, 'ACC' => 1, 'IA' => 2, 'STK' => 1);
// monthly naira by grade, 2026 money for a Port Harcourt four-star
$pay = array('G1' => 95000, 'G2' => 150000, 'G3' => 210000, 'G4' => 310000, 'G5' => 470000, 'G6' => 720000, 'G7' => 1150000, 'G8' => 2250000);
$banks = array(array('Zenith Bank', '057'), array('Guaranty Trust Bank', '058'), array('Access Bank', '044'), array('First Bank of Nigeria', '011'),
    array('United Bank for Africa', '033'), array('Fidelity Bank', '070'), array('Union Bank', '032'), array('Sterling Bank', '232'));
$pfas = array('Stanbic IBTC Pension Managers', 'ARM Pension Managers', 'Premium Pension', 'Leadway Pensure', 'Trustfund Pensions', 'Access Pensions');
$states = array('Rivers', 'Rivers', 'Rivers', 'Bayelsa', 'Imo', 'Abia', 'Anambra', 'Delta', 'Akwa Ibom', 'Cross River', 'Lagos', 'Oyo', 'Kano', 'Kaduna', 'Enugu');
$phonePrefix = array('0803', '0806', '0813', '0703', '0906', '0810', '0817', '0708', '0902');

$people = array(); $n = 0; $newStaff = 0; $updStaff = 0;
foreach ($hire as $code => $count) {
    for ($k = 0; $k < $count; $k++) {
        $n++;
        $p = $pos[$code];
        $staffNo = 'PH'.str_pad($n, 4, '0', STR_PAD_LEFT);
        $fn = $first[($n * 7) % count($first)];
        $ln = $last[($n * 11) % count($last)];
        $female = in_array($fn, array('Amaka', 'Folake', 'Ngozi', 'Halima', 'Blessing', 'Zainab', 'Chioma', 'Temitope', 'Adaeze', 'Efe', 'Yetunde', 'Aisha', 'Sarah',
            'Rita', 'Ijeoma', 'Funmi', 'Grace', 'Ebele', 'Patience', 'Onyinye', 'Joy', 'Mercy', 'Deborah', 'Peace', 'Esther', 'Gloria', 'Comfort', 'Precious',
            'Faith', 'Charity', 'Stella', 'Janet', 'Rose', 'Ruth', 'Hope', 'Naomi', 'Vivian', 'Chidinma', 'Bimpe', 'Queen', 'Nkechi', 'Ifeoma', 'Gift'));
        // seniors joined years ago, line staff more recently; a handful joined in the last three months
        $senior = in_array($p['grade'], array('G5', 'G6', 'G7', 'G8'));
        $monthsAgo = $senior ? 24 + ($n % 40) : (($n % 9 === 0) ? 1 + ($n % 3) : 5 + ($n % 30));
        $hireDate = date('Y-m-d', strtotime('-'.$monthsAgo.' month'));
        $bank = $banks[$n % count($banks)];
        $existing = PulseHrEmployee::byStaffNo($staffNo);
        $data = array(
            'staff_no' => $staffNo, 'firstname' => $fn, 'lastname' => $ln, 'gender' => $female ? 'f' : 'm',
            'dob' => date('Y-m-d', strtotime('-'.(21 + ($n * 3) % 28).' year -'.(($n * 13) % 300).' day')),
            'marital' => ($n % 3 === 0) ? 'married' : 'single', 'nationality' => 'Nigerian', 'state_of_origin' => $states[$n % count($states)],
            'national_id' => str_pad((string) (12000000000 + $n * 137), 11, '0', STR_PAD_LEFT),
            'tin' => str_pad((string) (20000000 + $n * 91), 8, '0', STR_PAD_LEFT).'-0001',
            'rsa_pin' => 'PEN'.str_pad((string) (100000000000 + $n * 313), 12, '0', STR_PAD_LEFT),
            'pfa' => $pfas[$n % count($pfas)], 'nhf_no' => '', 'nhf_consent' => ($n % 17 === 0) ? 1 : 0,
            'nhf_consent_note' => ($n % 17 === 0) ? 'Signed NHF consent form on file (HR cabinet 2)' : '',
            'bank_name' => $bank[0], 'bank_code' => $bank[1], 'account_no' => str_pad((string) (2000000000 + $n * 7919), 10, '0', STR_PAD_LEFT),
            'account_name' => $fn.' '.$ln, 'nok_name' => $first[($n * 5) % count($first)].' '.$ln, 'nok_relationship' => ($n % 3 === 0) ? 'Spouse' : 'Sibling',
            'nok_phone' => $phonePrefix[($n + 3) % count($phonePrefix)].str_pad((string) (1000000 + $n * 613), 7, '0', STR_PAD_LEFT),
            'address' => (10 + $n % 80).' '.$last[($n * 3) % count($last)].' Street, '.(($n % 2) ? 'D-Line' : 'Rumuola'),
            'city' => 'Port Harcourt', 'phone' => $phonePrefix[$n % count($phonePrefix)].str_pad((string) (2000000 + $n * 811), 7, '0', STR_PAD_LEFT),
            'email' => Tools::strtolower($fn.'.'.$ln.$n).'@pulse-demo.ng',
            'hire_date' => $hireDate, 'status' => $monthsAgo < 6 ? 'probation' : 'active',
            'id_pulse_hr_department' => $dept[$p['dept']], 'id_pulse_hr_section' => $p['section'] ? $sec[$p['section']] : null,
            'id_pulse_hr_position' => $p['id'], 'id_pulse_hr_grade' => $grade[$p['grade']], 'skip_checklist' => 1,
        );
        if ($monthsAgo >= 6) { $data['confirmation_date'] = date('Y-m-d', strtotime($hireDate.' +6 month')); }
        if ($existing) { $id = (int) $existing['id_pulse_hr_employee']; PulseHrEmployee::save($data, $id); $updStaff++; }
        else { $id = PulseHrEmployee::save($data); $newStaff++; }
        $people[$staffNo] = array('id' => $id, 'code' => $code, 'grade' => $p['grade'], 'dept' => $p['dept'], 'name' => $fn.' '.$ln,
            'hire' => $hireDate, 'nights' => $p['nights'], 'female' => $female);
        // first contract, effective from the hire date
        if (!PulseHrContract::onDate($id, $hireDate)) {
            PulseHrContract::save(array('id_pulse_hr_employee' => $id, 'type' => in_array($code, array('STEW', 'PA')) && $k > 1 ? 'fixed_term' : 'permanent',
                'effective_from' => $hireDate, 'start_date' => $hireDate,
                'end_date' => (in_array($code, array('STEW', 'PA')) && $k > 1) ? date('Y-m-d', strtotime($hireDate.' +18 month')) : null,
                'id_pulse_hr_position' => $p['id'], 'id_pulse_hr_department' => $dept[$p['dept']], 'id_pulse_hr_section' => $p['section'] ? $sec[$p['section']] : null,
                'id_pulse_hr_grade' => $grade[$p['grade']], 'pay_basis' => 'monthly', 'pay_rate' => $pay[$p['grade']] + (($n % 5) * 2500),
                'hours_per_week' => 48, 'days_per_week' => 6, 'working_pattern' => $p['nights'] ? 'Rotating early / late / night' : '6 on, 1 off',
                'night_shift' => $p['nights'], 'notice_days' => $senior ? 60 : 30, 'probation_months' => 0, 'reason' => 'hire',
                'note' => 'Seeded demo contract'));
        }
    }
}
$summary[] = $newStaff.' employee(s) created and '.$updStaff.' refreshed — '.count($people).' on strength';

/* ---------- 4. reporting lines and heads of department ---------- */
$byCode = array();
foreach ($people as $sn => $p) { $byCode[$p['code']][] = $sn; }
$head = array('rooms' => 'FOM', 'housekeeping' => 'EHK', 'fnb' => 'FBM', 'laundry' => 'LSUP', 'maintenance' => 'CENG', 'security' => 'SSUP',
    'sales' => 'SM', 'accounts' => 'FC', 'admin' => 'HRM');
$gm = isset($byCode['GM'][0]) ? $people[$byCode['GM'][0]]['id'] : 0;
foreach ($head as $code => $posCode) {
    if (empty($byCode[$posCode][0])) { continue; }
    $idHead = $people[$byCode[$posCode][0]]['id'];
    $db->update('pulse_hr_department', array('id_head' => $idHead), 'id_pulse_hr_department='.(int) $dept[$code], 0, true);
    $db->update('pulse_hr_employee', array('id_manager' => $gm ? $gm : null, 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_employee='.(int) $idHead, 0, true);
}
// everyone else reports to their department head (supervisors report to the head; line staff to a supervisor where there is one)
$supervisor = array('rooms' => 'DM', 'housekeeping' => 'HKS', 'fnb' => 'RSUP', 'laundry' => 'LSUP', 'maintenance' => 'CENG', 'security' => 'SSUP',
    'sales' => 'SM', 'accounts' => 'ACC', 'admin' => 'HRM');
$linked = 0;
foreach ($people as $sn => $p) {
    if (in_array($p['code'], array('GM')) || in_array($p['code'], array_values($head))) { continue; }
    $sup = isset($supervisor[$p['dept']]) && !empty($byCode[$supervisor[$p['dept']]]) ? $supervisor[$p['dept']] : (isset($head[$p['dept']]) ? $head[$p['dept']] : null);
    if (!$sup || empty($byCode[$sup])) { continue; }
    $pick = $byCode[$sup][($p['id']) % count($byCode[$sup])];
    if ($people[$pick]['id'] === $p['id']) { continue; }
    $db->update('pulse_hr_employee', array('id_manager' => (int) $people[$pick]['id'], 'date_upd' => date('Y-m-d H:i:s')), 'id_pulse_hr_employee='.(int) $p['id'], 0, true);
    $db->execute('UPDATE `'._DB_PREFIX_.'pulse_hr_contract` SET id_manager='.(int) $people[$pick]['id'].' WHERE id_pulse_hr_employee='.(int) $p['id'].' AND effective_to IS NULL');
    $linked++;
}
$summary[] = 'reporting lines set for '.$linked.' staff, nine heads of department under the GM';

/* ---------- 5. six mid-year promotions — the reason contracts are versioned ---------- */
$promoted = 0;
$promotions = array(
    array('from' => 'RA', 'to' => 'HKS', 'when' => '-4 month', 'why' => 'Promoted to Housekeeping Supervisor'),
    array('from' => 'WTR', 'to' => 'RSUP', 'when' => '-3 month', 'why' => 'Promoted to Restaurant Supervisor'),
    array('from' => 'FDA', 'to' => 'DM', 'when' => '-2 month', 'why' => 'Promoted to Duty Manager'),
    array('from' => 'COOK', 'to' => 'SCHEF', 'when' => '-5 month', 'why' => 'Promoted to Sous Chef'),
    array('from' => 'SEC', 'to' => 'SSUP', 'when' => '-6 month', 'why' => 'Promoted to Security Supervisor'),
    array('from' => 'LAT', 'to' => 'LSUP', 'when' => '-8 month', 'why' => 'Promoted to Laundry Supervisor'),
);
foreach ($promotions as $i => $pr) {
    if (empty($byCode[$pr['from']])) { continue; }
    $sn = $byCode[$pr['from']][count($byCode[$pr['from']]) - 1];
    $p = $people[$sn];
    $eff = date('Y-m-16', strtotime($pr['when'])); // deliberately mid-month: last month's pay basis must not move
    if ($eff <= $p['hire']) { continue; }
    if ($db->getValue('SELECT id_pulse_hr_contract FROM `'._DB_PREFIX_.'pulse_hr_contract` WHERE id_pulse_hr_employee='.(int) $p['id'].' AND reason="promotion"')) { continue; }
    $np = $pos[$pr['to']];
    PulseHrContract::save(array('id_pulse_hr_employee' => $p['id'], 'type' => 'permanent', 'effective_from' => $eff,
        'start_date' => $p['hire'], 'id_pulse_hr_position' => $np['id'], 'id_pulse_hr_department' => $dept[$np['dept']],
        'id_pulse_hr_section' => $np['section'] ? $sec[$np['section']] : null, 'id_pulse_hr_grade' => $grade[$np['grade']],
        'pay_basis' => 'monthly', 'pay_rate' => $pay[$np['grade']], 'hours_per_week' => 48, 'days_per_week' => 6,
        'night_shift' => $np['nights'], 'notice_days' => 30, 'probation_months' => 0, 'reason' => 'promotion', 'note' => $pr['why']));
    $people[$sn]['code'] = $pr['to'];
    $promoted++;
}
$summary[] = $promoted.' mid-month promotion(s) written as new contract versions — the version before each one is closed the day before, so last month\'s pay basis is untouched';

/* ---------- 6. documents ---------- */
$newDocs = 0;
foreach ($people as $sn => $p) {
    $has = (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_hr_document` WHERE id_pulse_hr_employee='.(int) $p['id']);
    if ($has) { continue; }
    PulseHrDocument::save(array('id_pulse_hr_employee' => $p['id'], 'type' => 'employment_letter', 'name' => 'Employment letter',
        'number' => 'HR/'.$sn, 'issued_on' => $p['hire'], 'verified' => 1, 'note' => 'Signed original in HR cabinet 1')); $newDocs++;
    PulseHrDocument::save(array('id_pulse_hr_employee' => $p['id'], 'type' => 'nin', 'name' => 'NIN slip', 'number' => str_pad((string) (12000000000 + $p['id'] * 137), 11, '0', STR_PAD_LEFT),
        'issued_on' => date('Y-m-d', strtotime($p['hire'].' -1 year')), 'verified' => 1)); $newDocs++;
    // medical screening: annual, so a few are always close to the edge
    $medIssued = date('Y-m-d', strtotime($p['hire'].' +'.(($p['id'] % 6) * 30).' day'));
    $medExpiry = date('Y-m-d', strtotime($medIssued.' +1 year'));
    while ($medExpiry < date('Y-m-d', strtotime('-40 day'))) { $medIssued = date('Y-m-d', strtotime($medIssued.' +1 year')); $medExpiry = date('Y-m-d', strtotime($medExpiry.' +1 year')); }
    PulseHrDocument::save(array('id_pulse_hr_employee' => $p['id'], 'type' => 'medical', 'name' => 'Medical / health screening',
        'issuer' => 'Meridian Hospital, Port Harcourt', 'issued_on' => $medIssued, 'expires_on' => $medExpiry, 'verified' => 1, 'remind_days' => 30)); $newDocs++;
    if (in_array($p['dept'], array('fnb', 'housekeeping', 'laundry'))) {
        $fhIssued = date('Y-m-d', strtotime($medIssued.' +'.(($p['id'] % 5) * 20).' day'));
        $fhExpiry = date('Y-m-d', strtotime($fhIssued.' +1 year'));
        PulseHrDocument::save(array('id_pulse_hr_employee' => $p['id'], 'type' => 'food_handler', 'name' => 'Food handler certificate',
            'issuer' => 'Rivers State Ministry of Health', 'issued_on' => $fhIssued, 'expires_on' => $fhExpiry, 'verified' => 1, 'remind_days' => 45)); $newDocs++;
    }
}
// two certificates deliberately about to lapse, so the expiry dashboard has something real on it
$soon = array_slice(array_keys($people), 12, 2);
foreach ($soon as $i => $sn) {
    $id = (int) $db->getValue('SELECT id_pulse_hr_document FROM `'._DB_PREFIX_.'pulse_hr_document` WHERE id_pulse_hr_employee='.(int) $people[$sn]['id'].' AND type="medical"');
    if ($id) { $db->update('pulse_hr_document', array('expires_on' => date('Y-m-d', strtotime('+'.(3 + $i * 5).' day')), 'status' => 'expiring'), 'id_pulse_hr_document='.$id, 0, true); }
}
$cook = !empty($byCode['COOK']) ? $byCode['COOK'][0] : null;
if ($cook) {
    $id = (int) $db->getValue('SELECT id_pulse_hr_document FROM `'._DB_PREFIX_.'pulse_hr_document` WHERE id_pulse_hr_employee='.(int) $people[$cook]['id'].' AND type="food_handler"');
    if ($id) { $db->update('pulse_hr_document', array('expires_on' => date('Y-m-d', strtotime('-6 day')), 'status' => 'expired'), 'id_pulse_hr_document='.$id, 0, true); }
}
$summary[] = $newDocs.' document(s) filed; two medicals lapse this week and one food handler certificate has already lapsed';

/* ---------- 7. leave: balances, accrual, taken and pending ---------- */
$accrued = 0;
for ($m = 1; $m <= (int) date('n'); $m++) { $accrued += PulseHrLeave::accrueMonth(date('Y-m', mktime(0, 0, 0, $m, 1, $year))); }
$annual = PulseHrLeave::typeByCode('ANN'); $sick = PulseHrLeave::typeByCode('SICK'); $pat = PulseHrLeave::typeByCode('PAT');
$requests = 0; $i = 0;
foreach ($people as $sn => $p) {
    $i++;
    if ($i % 6 !== 0) { continue; }
    if ($db->getValue('SELECT id_pulse_hr_leave_request FROM `'._DB_PREFIX_.'pulse_hr_leave_request` WHERE id_pulse_hr_employee='.(int) $p['id'])) { continue; }
    $type = ($i % 18 === 0) ? $sick : $annual;
    $start = date('Y-m-d', strtotime('-'.(10 + ($i * 3) % 90).' day'));
    try {
        $r = PulseHrLeave::request(array('id_pulse_hr_employee' => $p['id'], 'id_pulse_hr_leave_type' => (int) $type['id_pulse_hr_leave_type'],
            'date_from' => $start, 'date_to' => date('Y-m-d', strtotime($start.' +'.(($i % 4) + 3).' day')), 'reason' => ($i % 18 === 0) ? 'Malaria — clinic note on file' : 'Annual leave', 'source' => 'admin'));
        PulseHrLeave::decide((int) $r['id'], 'approved', 'Approved by the department head');
        $requests++;
    } catch (Exception $e) { continue; }
}
// three requests still waiting on somebody's desk, including one that will bite the coverage warning
$pendingMade = 0; $i = 0;
foreach ($people as $sn => $p) {
    if ($p['dept'] !== 'housekeeping' && $p['dept'] !== 'fnb') { continue; }
    $i++;
    if ($i % 4 !== 0 || $pendingMade >= 3) { continue; }
    try {
        PulseHrLeave::request(array('id_pulse_hr_employee' => $p['id'], 'id_pulse_hr_leave_type' => (int) $annual['id_pulse_hr_leave_type'],
            'date_from' => date('Y-m-d', strtotime('+'.(4 + $pendingMade).' day')), 'date_to' => date('Y-m-d', strtotime('+'.(9 + $pendingMade).' day')),
            'reason' => 'Family event in Owerri', 'source' => 'ess'));
        $pendingMade++;
    } catch (Exception $e) { continue; }
}
$summary[] = $accrued.' monthly accrual(s) posted, '.$requests.' past leave request(s) approved and '.$pendingMade.' waiting for a decision';

/* ---------- 8. blackout over the December peak ---------- */
if (!$db->getValue('SELECT id_pulse_hr_blackout FROM `'._DB_PREFIX_.'pulse_hr_blackout` WHERE name="Christmas and New Year"')) {
    PulseHrLeave::saveBlackout(array('name' => 'Christmas and New Year', 'department' => '', 'date_from' => $year.'-12-20', 'date_to' => ($year + 1).'-01-03',
        'max_off' => 1, 'min_occupancy_pct' => 70, 'reason' => 'Peak season — one person off per department, and only below 70% occupancy'));
    $summary[] = 'December blackout period created';
}

/* ---------- 9. the roster for this week and next ---------- */
$week = PulseHrRoster::weekStart($today);
$rosterCells = 0;
$rotation = array('E', 'L', 'N');
foreach ($people as $sn => $p) {
    if (in_array($p['code'], array('GM', 'FC', 'SM', 'HRM', 'HRO', 'SE', 'ACC', 'RES', 'STK'))) { $pattern = 'G'; } else { $pattern = null; }
    for ($w = 0; $w < 2; $w++) {
        for ($d = 0; $d < 7; $d++) {
            $date = date('Y-m-d', strtotime($week.' +'.($w * 7 + $d).' day'));
            if ($date < $p['hire']) { continue; }
            $off = (($p['id'] + $w) % 7) === $d; // everyone gets one day off a week, staggered
            $code = $off ? null : ($pattern ? $pattern : ($p['nights'] ? $rotation[($p['id'] + $w) % 3] : ($d % 2 ? 'L' : 'E')));
            try { if (PulseHrRoster::set($p['id'], $date, $off ? 0 : $shift[$code], $off ? 1 : 0)) { $rosterCells++; } }
            catch (Exception $e) { continue; }
        }
    }
}
$published = PulseHrRoster::publish($week, date('Y-m-d', strtotime($week.' +6 day')));
$summary[] = $rosterCells.' roster cell(s) planned over two weeks, '.$published.' published for the current week';

/* ---------- 10. staff portal PINs and a fortnight of mobile punches ---------- */
$demoPin = '4821';
$pins = 0;
foreach (array_slice(array_keys($people), 0, 30) as $sn) {
    $p = $people[$sn];
    if ($db->getValue('SELECT pin_hash FROM `'._DB_PREFIX_.'pulse_hr_employee` WHERE id_pulse_hr_employee='.(int) $p['id'])) { continue; }
    try { PulseHrEmployee::setPin($p['id'], $demoPin); $pins++; } catch (Exception $e) { continue; }
}
$siteLat = (float) PulseHrService::cfg('GEO_LAT', 4.8156); $siteLng = (float) PulseHrService::cfg('GEO_LNG', 7.0498);
$punches = 0; $flagged = 0;
$shiftStart = array('E' => '06:00', 'L' => '14:00', 'N' => '22:00', 'G' => '08:00');
foreach (array_slice(array_keys($people), 0, 24) as $idx => $sn) {
    $p = $people[$sn];
    for ($back = 14; $back >= 1; $back--) {
        $date = date('Y-m-d', strtotime('-'.$back.' day'));
        $cell = $db->getRow('SELECT r.*, s.code shift_code, s.start_time, s.end_time FROM `'._DB_PREFIX_.'pulse_hr_roster` r
            LEFT JOIN `'._DB_PREFIX_.'pulse_hr_shift` s ON s.id_pulse_hr_shift=r.id_pulse_hr_shift
            WHERE r.id_pulse_hr_employee='.(int) $p['id'].' AND r.roster_date="'.pSQL($date).'" AND r.is_off=0');
        $start = $cell && $cell['start_time'] ? $cell['start_time'] : '08:00:00';
        $late = (($p['id'] + $back) % 9 === 0) ? (7 + ($back % 20)) : (-3 + ($back % 7)); // mostly on time, occasionally properly late
        $in = date('Y-m-d H:i:s', strtotime($date.' '.$start.' +'.$late.' minute'));
        $outAt = date('Y-m-d H:i:s', strtotime($in.' +'.(8 * 60 + (($p['id'] + $back) % 25)).' minute'));
        $miss = (($p['id'] * $back) % 61 === 0); // two missing out-punches across the fortnight
        $far = ($idx === 3 && $back === 5);
        $noLoc = ($idx === 7 && $back === 2);
        $lat = $far ? $siteLat + 0.085 : $siteLat + ((($p['id'] + $back) % 9) - 4) * 0.00035;
        $lng = $far ? $siteLng - 0.061 : $siteLng + ((($p['id'] * $back) % 9) - 4) * 0.00035;
        $rows = array(array($in, 'in'));
        if (!$miss) { $rows[] = array($outAt, 'out'); }
        foreach ($rows as $r) {
            if ($db->getValue('SELECT id_pulse_hr_punch FROM `'._DB_PREFIX_.'pulse_hr_punch` WHERE id_pulse_hr_employee='.(int) $p['id'].' AND punched_at="'.pSQL($r[0]).'" AND direction="'.$r[1].'"')) { continue; }
            $dist = $noLoc ? null : PulseHrService::distanceMetres($lat, $lng, $siteLat, $siteLng);
            $inside = $noLoc ? 0 : ($dist <= (float) PulseHrService::cfg('GEO_RADIUS_M', 200) ? 1 : 0);
            $status = $noLoc ? 'flagged' : ($inside ? 'accepted' : 'flagged');
            $db->insert('pulse_hr_punch', array('id_pulse_hr_employee' => (int) $p['id'], 'punched_at' => pSQL($r[0]), 'direction' => pSQL($r[1]),
                'source' => 'mobile', 'lat' => $noLoc ? null : $lat, 'lng' => $noLoc ? null : $lng, 'accuracy_m' => $noLoc ? null : (8 + (($p['id'] + $back) % 40)),
                'distance_m' => $dist, 'inside_geofence' => $inside, 'status' => $status,
                'flag_reason' => $noLoc ? 'no_location' : ($inside ? '' : 'outside_geofence'),
                'device' => 'Android', 'ip' => '105.112.'.(($p['id'] % 200) + 1).'.'.(($back % 200) + 1),
                'id_pulse_hr_roster' => $cell ? (int) $cell['id_pulse_hr_roster'] : null, 'synced' => 0,
                'business_date' => pSQL($date), 'date_add' => date('Y-m-d H:i:s')), true, true, Db::INSERT_IGNORE);
            $punches++;
            if ($status !== 'accepted') { $flagged++; }
        }
    }
}
$summary[] = $pins.' staff portal PIN(s) set (demo PIN '.$demoPin.' — change it before anyone real uses this), '.$punches.' mobile punch(es) over a fortnight, '.$flagged.' flagged for a supervisor';

/* ---------- 11. three leavers, two open onboardings, one completed clearance ---------- */
$leavers = 0;
$leaverSpecs = array(
    array('code' => 'RA', 'idx' => 0, 'when' => '-45 day', 'type' => 'resignation', 'why' => 'Left for a hotel in Lagos'),
    array('code' => 'STEW', 'idx' => 0, 'when' => '-20 day', 'type' => 'abscondment', 'why' => 'Stopped coming to work; letters returned', 'rehire' => 0),
    array('code' => 'WTR', 'idx' => 1, 'when' => '-8 day', 'type' => 'end_of_contract', 'why' => 'Fixed-term contract ran out and was not renewed'),
);
foreach ($leaverSpecs as $spec) {
    if (empty($byCode[$spec['code']][$spec['idx']])) { continue; }
    $sn = $byCode[$spec['code']][$spec['idx']];
    $p = $people[$sn];
    $e = PulseHrEmployee::get($p['id']);
    if (!$e || $e['status'] === 'exited') { continue; }
    try {
        $idChecklist = PulseHrEmployee::exitEmployee($p['id'], date('Y-m-d', strtotime($spec['when'])), $spec['type'], $spec['why'], isset($spec['rehire']) ? $spec['rehire'] : 1);
        $leavers++;
        if ($leavers === 1 && $idChecklist) {
            foreach ($db->executeS('SELECT id_pulse_hr_checklist_task FROM `'._DB_PREFIX_.'pulse_hr_checklist_task` WHERE id_pulse_hr_checklist='.(int) $idChecklist) as $t) {
                try { PulseHrLifecycle::completeTask((int) $t['id_pulse_hr_checklist_task'], 'Cleared'); }
                catch (Exception $ex) { PulseHrLifecycle::skipTask((int) $t['id_pulse_hr_checklist_task'], 'Module not installed on the demo — cleared by hand'); }
            }
        }
    } catch (Exception $e2) { continue; }
}
$onboards = 0;
foreach ($people as $sn => $p) {
    if (strtotime($p['hire']) < strtotime('-90 day')) { continue; }
    if ($db->getValue('SELECT id_pulse_hr_checklist FROM `'._DB_PREFIX_.'pulse_hr_checklist` WHERE id_pulse_hr_employee='.(int) $p['id'].' AND type="onboarding"')) { continue; }
    if ($onboards >= 2) { break; }
    $id = PulseHrLifecycle::open($p['id'], 'onboarding');
    if ($id) {
        $tasks = $db->executeS('SELECT id_pulse_hr_checklist_task FROM `'._DB_PREFIX_.'pulse_hr_checklist_task` WHERE id_pulse_hr_checklist='.(int) $id.' ORDER BY sort LIMIT 4');
        foreach ($tasks as $t) { try { PulseHrLifecycle::completeTask((int) $t['id_pulse_hr_checklist_task'], 'Done'); } catch (Exception $ex) { } }
        $onboards++;
    }
}
$summary[] = $leavers.' leaver(s) exited with clearance checklists, '.$onboards.' onboarding checklist(s) part done';

/* ---------- 12. discipline, appraisal and training ---------- */
$cases = 0;
$caseSpecs = array(
    array('code' => 'WTR', 'idx' => 2, 'type' => 'query', 'subject' => 'Late three times in one week', 'desc' => 'Clocked in at 14:31, 14:44 and 14:38 on the late shift. Explain in writing within 48 hours.'),
    array('code' => 'SEC', 'idx' => 1, 'type' => 'written_warning', 'subject' => 'Left the back gate unmanned', 'desc' => 'The service gate was found unmanned for 40 minutes on the night shift. This is a written warning; it stays on file for twelve months.'),
    array('code' => 'RA', 'idx' => 3, 'type' => 'commendation', 'subject' => 'Returned a guest\'s cash', 'desc' => 'Handed in ₦180,000 found in room 214 and left with the duty manager immediately. Noted with thanks by the GM.'),
);
foreach ($caseSpecs as $spec) {
    if (empty($byCode[$spec['code']][$spec['idx']])) { continue; }
    $p = $people[$byCode[$spec['code']][$spec['idx']]];
    if ($db->getValue('SELECT id_pulse_hr_case FROM `'._DB_PREFIX_.'pulse_hr_case` WHERE id_pulse_hr_employee='.(int) $p['id'].' AND subject="'.pSQL($spec['subject']).'"')) { continue; }
    PulseHrPerformance::saveCase(array('id_pulse_hr_employee' => $p['id'], 'type' => $spec['type'], 'subject' => $spec['subject'],
        'description' => $spec['desc'], 'incident_date' => date('Y-m-d', strtotime('-9 day')), 'issued_on' => date('Y-m-d', strtotime('-7 day'))));
    $cases++;
}
$cycleCode = $year.'-H1';
$idCycle = (int) $db->getValue('SELECT id_pulse_hr_appraisal_cycle FROM `'._DB_PREFIX_.'pulse_hr_appraisal_cycle` WHERE code="'.pSQL($cycleCode).'"');
if (!$idCycle) {
    $idCycle = PulseHrPerformance::saveCycle(array('code' => $cycleCode, 'name' => 'Half year review '.$year, 'period_from' => $year.'-01-01',
        'period_to' => $year.'-06-30', 'due_on' => $year.'-07-31', 'status' => 'in_progress'));
    PulseHrPerformance::openCycle($idCycle, 'rooms');
    $objectives = array(
        array('Guest satisfaction on the desk', 'Average review score at or above 8.5', 30, 4),
        array('Upsell revenue', '₦150,000 a month in room upgrades', 25, 3.5),
        array('Check-in time', 'Under four minutes at the desk', 25, 4.5),
        array('Grooming and punctuality', 'No lateness queries in the period', 20, 3),
    );
    foreach (PulseHrPerformance::appraisals($idCycle) as $j => $a) {
        if ($j >= 4) { break; }
        foreach ($objectives as $k => $o) {
            PulseHrPerformance::saveObjective(array('id_pulse_hr_appraisal' => (int) $a['id_pulse_hr_appraisal'], 'sort' => $k + 1, 'title' => $o[0],
                'target' => $o[1], 'weight' => $o[2], 'rating' => $o[3], 'result' => 'Met', 'comment' => ''));
        }
        PulseHrPerformance::saveAppraisal(array('status' => 'reviewer', 'reviewer_comment' => 'Steady half year. Needs to push the upsell.',
            'recommendation' => $j === 0 ? 'increment' : 'none', 'id_reviewer' => (int) $a['id_reviewer']), (int) $a['id_pulse_hr_appraisal']);
    }
}
$training = 0;
foreach (array_slice(array_keys($people), 0, 20) as $i => $sn) {
    $p = $people[$sn];
    if ($db->getValue('SELECT id_pulse_hr_training FROM `'._DB_PREFIX_.'pulse_hr_training` WHERE id_pulse_hr_employee='.(int) $p['id'])) { continue; }
    $course = in_array($p['dept'], array('fnb', 'housekeeping')) ? array('Food hygiene level 2', 'food_hygiene', 12) : array('Fire safety and evacuation', 'fire', 24);
    PulseHrPerformance::saveTraining(array('id_pulse_hr_employee' => $p['id'], 'course' => $course[0], 'provider' => 'Rivers Hospitality Skills Centre',
        'type' => $course[1], 'completed_on' => date('Y-m-d', strtotime('-'.(30 + $i * 9).' day')),
        'expires_on' => date('Y-m-d', strtotime('-'.(30 + $i * 9).' day +'.$course[2].' month')), 'cost' => 25000, 'certificate_no' => 'RHSC/'.$year.'/'.(1000 + $i)));
    $training++;
}
$summary[] = $cases.' discipline case(s), an appraisal cycle with weighted objectives for the front office, and '.$training.' training record(s)';

/* ---------- 13. restamp derived state ---------- */
PulseHrDocument::refreshStatuses();
PulseHrLeave::rollDay($today);

/* ---------- done ---------- */
echo "Pulse HR demo data — Port Harcourt, 52 rooms\n";
foreach ($summary as $s) { echo ' · '.$s."\n"; }
echo "\nOpen HR ▸ HR for the dashboard: headcount against establishment, coverage against occupancy, expiring documents and flagged mobile punches.\n";
echo "Employees ▸ open anyone and look at the Contracts tab — the promotions are separate versions, and Reports ▸ Pay basis in force shows what payroll reads for any date.\n";
echo "Staff portal: ".Context::getContext()->link->getModuleLink('pulsehr', 'ess', array(), true)." — sign in with a staff number (PH0001 …) and PIN ".$demoPin.".\n";
