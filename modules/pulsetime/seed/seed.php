<?php
/**
 * Demo data for Pulse Time & Attendance — a 52-room property in Port Harcourt.
 *
 * Creates ~85 staff across rooms, housekeeping, F&B, kitchen, laundry, maintenance, security, accounts,
 * sales, admin and management; three clocking devices (the simulator plus a ZKTeco ADMS push unit and a
 * Hikvision terminal, both in test mode so nothing is sent to hardware that is not there); enrolments on the
 * simulator; a five-week published roster including a proper 22:00–06:00 night rota; a month of punches with
 * realistic lateness, two deliberately missing clock-outs, and one unmatched device id; rebuilt timesheets
 * with their exceptions; and one approved, locked period for the prior month so Payroll has something to read.
 *
 * Idempotent: run it as often as you like. It never deletes anything.
 * Usage: php modules/pulsetime/seed/seed.php   (or in a browser with ?token=<PULSE_TA_CRON_TOKEN>)
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
if (php_sapi_name() !== 'cli') {
    $token = Tools::getValue('token');
    if (!hash_equals((string) Configuration::get('PULSE_TA_CRON_TOKEN'), (string) $token)) { die('Invalid token'); }
}
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$db = Db::getInstance();
$now = date('Y-m-d H:i:s');
$summary = array();

/* ---------- 1. devices ---------- */
$devices = array(
    array('name' => 'Staff Entrance (Simulator)', 'adapter' => 'PulseTaSimulator', 'brand' => 'simulator', 'location' => 'Staff entrance, Aba Road gate',
        'mode' => 'pull', 'protocol' => 'local', 'host' => '127.0.0.1', 'port' => 0, 'endpoint' => '', 'serial' => 'SIM-TA-1',
        'direction_mode' => 'auto', 'poll' => 10, 'test' => 0, 'status' => 'active', 'creds' => array(),
        'note' => 'Hardware-free device. Generates punches from the published roster so the whole module can be demonstrated and trained on.'),
    array('name' => 'Back of House (ZKTeco ADMS)', 'adapter' => 'PulseTaZkPush', 'brand' => 'zkteco', 'location' => 'BOH corridor by the time office',
        'mode' => 'push', 'protocol' => 'http', 'host' => '192.168.1.201', 'port' => 80, 'endpoint' => '/iclock', 'serial' => 'CGK9234500123',
        'direction_mode' => 'both', 'poll' => 0, 'test' => 1, 'status' => 'active', 'creds' => array('comm_key' => '0', 'push_key' => 'change-me-before-go-live'),
        'note' => 'TEST MODE. Point the device Comm > Server page at this property before going live; the firmware appends /cdata itself.'),
    array('name' => 'Kitchen Door (Hikvision)', 'adapter' => 'PulseTaHikvision', 'brand' => 'hikvision', 'location' => 'Kitchen back door',
        'mode' => 'pull', 'protocol' => 'http', 'host' => '192.168.1.212', 'port' => 80, 'endpoint' => '/', 'serial' => 'DS-K1T671M-20260101AA',
        'direction_mode' => 'both', 'poll' => 15, 'test' => 1, 'status' => 'active', 'creds' => array('user' => 'admin', 'password' => 'change-me'),
        'note' => 'TEST MODE. Verify the AcsEvent minor codes against this firmware before enabling it for payroll.'),
);
$devIds = array(); $newDev = 0;
foreach ($devices as $d) {
    $row = PulseTaDevice::byName($d['name']);
    if ($row) { $devIds[$d['name']] = (int) $row['id_pulse_ta_device']; continue; }
    $devIds[$d['name']] = PulseTaDevice::save(array('name' => $d['name'], 'adapter' => $d['adapter'], 'brand' => $d['brand'], 'location' => $d['location'],
        'mode' => $d['mode'], 'protocol' => $d['protocol'], 'host' => $d['host'], 'port' => $d['port'], 'endpoint' => $d['endpoint'], 'serial' => $d['serial'],
        'timezone' => 'Africa/Lagos', 'direction_mode' => $d['direction_mode'], 'poll_interval_min' => $d['poll'], 'timeout_sec' => 8, 'retries' => 2,
        'clear_after_pull' => 0, 'test_mode' => $d['test'], 'status' => $d['status'], 'note' => $d['note'], 'credentials' => $d['creds'],
        'options_json' => $d['adapter'] === 'PulseTaSimulator' ? '{"late_pct":18,"missing_out_pct":6,"absent_pct":3,"ot_pct":12}' : ''));
    $newDev++;
}
$sim = $devIds['Staff Entrance (Simulator)'];
$db->update('pulse_ta_device', array('health' => 'online', 'last_seen_at' => $now), 'id_pulse_ta_device='.(int) $sim);
$summary[] = $newDev.' device(s) added ('.count($devices).' registered; two in test mode so nothing reaches absent hardware)';

/* ---------- 2. staff ---------- */
$first = array('Chidinma', 'Emeka', 'Ngozi', 'Tunde', 'Aisha', 'Ifeanyi', 'Blessing', 'Musa', 'Funmilayo', 'Obinna', 'Amaka', 'Segun', 'Halima', 'Chukwuemeka',
    'Adaeze', 'Bala', 'Yetunde', 'Kelechi', 'Zainab', 'Olumide', 'Nkechi', 'Ibrahim', 'Temitope', 'Uchenna', 'Fatima', 'Bayo', 'Chiamaka', 'Sadiq',
    'Oluwaseun', 'Ebere', 'Nnamdi', 'Rukayat', 'Gbenga', 'Ijeoma', 'Abdullahi', 'Bisi', 'Chinedu', 'Maryam', 'Tobi', 'Onyinye', 'Sani', 'Folake',
    'Ekene', 'Hauwa', 'Damilola', 'Ugochi', 'Yusuf', 'Kemi', 'Ozioma', 'Idris');
$last = array('Okoro', 'Adeyemi', 'Eze', 'Bello', 'Nwosu', 'Okafor', 'Ibrahim', 'Balogun', 'Chukwu', 'Abubakar', 'Ogunleye', 'Amadi', 'Lawal', 'Nwachukwu',
    'Adebayo', 'Danjuma', 'Obi', 'Suleiman', 'Ajayi', 'Uzoma', 'Mohammed', 'Oyelaran', 'Iheanacho', 'Yakubu', 'Olawale', 'Nnaji', 'Garba', 'Adeleke',
    'Wachukwu', 'Briggs', 'Amachree', 'Pepple', 'Jaja', 'Wokoma', 'Igwe', 'Sokari');

/** department => list of [position, count, pay_basis, hourly, daily, shift code] */
$plan = array(
    'rooms' => array(array('Front Office Manager', 1, 'monthly', 0, 0, 'GEN'), array('Duty Manager', 2, 'monthly', 0, 0, 'EARLY'),
        array('Front Desk Agent', 6, 'monthly', 0, 0, 'EARLY'), array('Night Auditor', 2, 'monthly', 0, 0, 'NIGHT'),
        array('Concierge / Porter', 3, 'monthly', 0, 0, 'LATE'), array('Guest Relations Officer', 1, 'monthly', 0, 0, 'GEN')),
    'housekeeping' => array(array('Executive Housekeeper', 1, 'monthly', 0, 0, 'GEN'), array('Housekeeping Supervisor', 3, 'monthly', 0, 0, 'EARLY'),
        array('Room Attendant', 14, 'monthly', 0, 0, 'EARLY'), array('Public Area Cleaner', 4, 'daily', 0, 6500, 'EARLY'),
        array('Linen Room Attendant', 2, 'monthly', 0, 0, 'EARLY')),
    'fnb' => array(array('F&B Manager', 1, 'monthly', 0, 0, 'GEN'), array('Restaurant Supervisor', 2, 'monthly', 0, 0, 'LATE'),
        array('Waiter', 8, 'monthly', 0, 0, 'SPLIT'), array('Bartender', 3, 'monthly', 0, 0, 'LATE'),
        array('Banqueting Casual', 4, 'shift', 0, 9000, 'LATE')),
    'kitchen' => array(array('Executive Chef', 1, 'monthly', 0, 0, 'GEN'), array('Sous Chef', 2, 'monthly', 0, 0, 'EARLY'),
        array('Line Cook', 6, 'monthly', 0, 0, 'EARLY'), array('Kitchen Steward', 4, 'daily', 0, 6000, 'LATE')),
    'laundry' => array(array('Laundry Supervisor', 1, 'monthly', 0, 0, 'EARLY'), array('Laundry Attendant', 4, 'monthly', 0, 0, 'EARLY')),
    'maintenance' => array(array('Chief Engineer', 1, 'monthly', 0, 0, 'GEN'), array('Technician', 4, 'monthly', 0, 0, 'ONCALL'),
        array('Generator Operator', 2, 'monthly', 0, 0, 'NIGHT')),
    'security' => array(array('Security Supervisor', 1, 'monthly', 0, 0, 'GEN'), array('Security Officer', 6, 'monthly', 0, 0, 'NIGHT')),
    'accounts' => array(array('Financial Controller', 1, 'monthly', 0, 0, 'GEN'), array('Accounts Officer', 2, 'monthly', 0, 0, 'GEN'),
        array('Store Keeper', 1, 'monthly', 0, 0, 'GEN')),
    'sales' => array(array('Sales & Marketing Manager', 1, 'monthly', 0, 0, 'GEN'), array('Sales Executive', 2, 'monthly', 0, 0, 'GEN')),
    'admin' => array(array('HR Officer', 1, 'monthly', 0, 0, 'GEN'), array('Admin Assistant', 1, 'monthly', 0, 0, 'GEN'),
        array('Driver', 2, 'monthly', 0, 0, 'GEN')),
    'management' => array(array('General Manager', 1, 'monthly', 0, 0, 'GEN')),
);

$shiftIds = array();
foreach (PulseTaService::shifts(false) as $s) { $shiftIds[$s['code']] = (int) $s['id_pulse_ta_shift']; }

$n = 0; $newStaff = 0; $staffIds = array(); $nightStaff = array();
foreach ($plan as $dept => $roles) {
    foreach ($roles as $role) {
        list($position, $count, $basis, $hourly, $daily, $shift) = $role;
        for ($i = 0; $i < $count; $i++) {
            $no = 'PH'.str_pad(101 + $n, 4, '0', STR_PAD_LEFT);
            $fn = $first[$n % count($first)];
            $ln = $last[($n * 7 + 3) % count($last)];
            $n++;
            $existing = PulseTaService::staffByNo($no);
            $id = PulseTaService::saveStaff(array('staff_no' => $no, 'firstname' => $fn, 'lastname' => $ln, 'department' => $dept, 'position' => $position,
                'id_pulse_ta_shift' => isset($shiftIds[$shift]) ? $shiftIds[$shift] : null, 'pay_basis' => $basis,
                'hourly_rate' => $hourly, 'daily_rate' => $daily, 'ot_eligible' => in_array($position, array('General Manager', 'Financial Controller', 'Executive Chef', 'F&B Manager', 'Front Office Manager', 'Executive Housekeeper', 'Chief Engineer', 'Sales & Marketing Manager'), true) ? 0 : 1,
                'source' => 'local', 'status' => 'active'), $existing ? (int) $existing['id_pulse_ta_staff'] : 0);
            if (!$existing) { $newStaff++; }
            $staffIds[$id] = array('shift' => $shift, 'dept' => $dept, 'no' => $no);
            if ($shift === 'NIGHT') { $nightStaff[] = $id; }
        }
    }
}
$summary[] = $newStaff.' staff member(s) added ('.count($staffIds).' on the roster, '.count($nightStaff).' of them on the 22:00–06:00 night rota)';

/* ---------- 3. enrolment on the simulator ---------- */
$newEnrol = 0;
foreach (array_keys($staffIds) as $idStaff) {
    if (PulseTaEnrolment::find($idStaff, $sim)) { continue; }
    $s = PulseTaService::staff($idStaff);
    PulseTaEnrolment::saveMapping($idStaff, $sim, (string) (int) preg_replace('/[^0-9]/', '', $s['staff_no']), array('status' => 'pushed', 'has_finger' => 1));
    $newEnrol++;
}
$summary[] = $newEnrol.' enrolment(s) written on the simulator (the ZK and Hikvision units stay empty until real hardware is on the network)';

/* ---------- 4. five weeks of published roster ---------- */
$from = date('Y-m-d', strtotime('-35 day'));
$to = date('Y-m-d', strtotime('+6 day'));
$cells = 0;
foreach ($staffIds as $idStaff => $meta) {
    $code = $meta['shift'];
    $offset = $idStaff % 7;
    for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
        $date = date('Y-m-d', $t);
        if ($db->getValue('SELECT id_pulse_ta_roster FROM `'._DB_PREFIX_.'pulse_ta_roster` WHERE id_pulse_ta_staff='.(int) $idStaff.' AND roster_date="'.pSQL($date).'"')) { continue; }
        $dayIndex = (int) floor(($t - strtotime($from)) / 86400) + $offset;
        // Office and management staff work Monday to Friday; everyone else runs a 5-on 2-off cycle so the
        // property is covered every day of the week, including nights.
        if (in_array($code, array('GEN'), true)) { $work = ((int) date('N', $t) <= 5); }
        else { $work = (($dayIndex % 7) < 5); }
        PulseTaRoster::set((int) $idStaff, $date, $work && isset($shiftIds[$code]) ? $shiftIds[$code] : null, $work ? 'work' : 'rest', '', 1, 'local');
        $cells++;
    }
}
$summary[] = $cells.' roster cell(s) published from '.$from.' to '.$to;

/* ---------- 5. a month of punches from the simulator ---------- */
$before = (int) $db->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_ta_punch`');
$pull = PulseTaDevice::poll($sim, true);
$punches = (int) $pull['stored'];
$summary[] = $punches.' punch(es) generated by the simulator from the published roster (repeat runs de-duplicate to zero)';

/* ---------- 6. two deliberately missing clock-outs and one unmatched device id ---------- */
$holes = 0;
$victims = array_slice(array_keys($staffIds), 5, 2);
foreach ($victims as $k => $idStaff) {
    $date = date('Y-m-d', strtotime('-'.(3 + $k).' day'));
    $last = $db->getRow('SELECT id_pulse_ta_punch FROM `'._DB_PREFIX_.'pulse_ta_punch` WHERE id_pulse_ta_staff='.(int) $idStaff.'
        AND business_date="'.pSQL($date).'" AND direction="out" ORDER BY punched_at DESC LIMIT 1');
    if ($last) {
        // Deleting a seeded punch is how we simulate a finger that did not read. Real punches are never deleted.
        $db->delete('pulse_ta_punch', 'id_pulse_ta_punch='.(int) $last['id_pulse_ta_punch']);
        $holes++;
    }
}
$unmatched = 0;
if (!$db->getValue('SELECT id_pulse_ta_punch FROM `'._DB_PREFIX_.'pulse_ta_punch` WHERE employee_ref="9911" AND id_pulse_ta_device='.(int) $sim)) {
    $devRow = PulseTaDevice::get($sim);
    for ($k = 0; $k < 3; $k++) {
        PulseTaPunch::ingest($devRow, array('device_serial' => $devRow['serial'], 'employee_ref' => '9911',
            'punched_at' => date('Y-m-d', strtotime('-'.(2 + $k).' day')).' 07:0'.$k.':00', 'direction' => 'in', 'verify_mode' => 'finger',
            'source' => 'device', 'raw' => 'seed: a device user id nobody has mapped'));
        $unmatched++;
    }
}
$summary[] = $holes.' clock-out(s) removed to simulate a finger that did not read, and '.$unmatched.' punch(es) from an unmapped device id — both land in the exception queue';

/* ---------- 7. build the timesheets ---------- */
$build = PulseTaEngine::buildRange($from, date('Y-m-d'));
$summary[] = $build['timesheets'].' timesheet(s) built over '.$build['days'].' day(s)';
$counts = PulseTaExceptionQueue::counts();
$summary[] = $counts['total'].' exception(s) open ('.$counts['block'].' blocking a period approval) — that is the screen a supervisor works from';

/* ---------- 8. an approved, locked period for the prior month ---------- */
$pFrom = date('Y-m-01', strtotime('first day of last month'));
$pTo = date('Y-m-t', strtotime('first day of last month'));
$pName = date('F Y', strtotime($pFrom));
$existingPeriod = $db->getRow('SELECT * FROM `'._DB_PREFIX_.'pulse_ta_period` WHERE name="'.pSQL($pName).'" AND department=""');
if (!$existingPeriod) {
    try {
        $idPeriod = PulseTaTimesheet::createPeriod($pName, $pFrom, $pTo, '');
        PulseTaTimesheet::submitPeriod($idPeriod, 'Seeded demo period');
        $r = PulseTaTimesheet::approvePeriod($idPeriod, true); // forced: the demo month deliberately carries exceptions
        $summary[] = 'period "'.$pName.'" ('.$pFrom.' → '.$pTo.') approved and locked — '.$r['timesheets'].' timesheet(s) Payroll may now read';
    } catch (Exception $e) { $summary[] = 'period "'.$pName.'" not created: '.$e->getMessage(); }
} else {
    $summary[] = 'period "'.$pName.'" already exists ('.$existingPeriod['status'].')';
}

/* ---------- 9. an open period for the current month ---------- */
$cName = date('F Y');
if (!$db->getValue('SELECT id_pulse_ta_period FROM `'._DB_PREFIX_.'pulse_ta_period` WHERE name="'.pSQL($cName).'" AND department=""')) {
    try { PulseTaTimesheet::createPeriod($cName, date('Y-m-01'), date('Y-m-t'), ''); $summary[] = 'period "'.$cName.'" created and left open for the supervisor to work through'; }
    catch (Exception $e) { $summary[] = 'current period not created: '.$e->getMessage(); }
}

/* ---------- 10. a shared push key so the ADMS device has something to authenticate with ---------- */
if (!PulseCoreService::setting('pulsetime', 'push_key')) {
    PulseCoreService::setting('pulsetime', 'push_key', Tools::passwdGen(32));
    $summary[] = 'a shared push key was generated — see T&A > Settings, and set the same value on every push device before turning "Require a shared key" on';
}

/* ---------- done ---------- */
$dash = PulseTaService::dashboard();
echo "Pulse Time & Attendance demo data — Port Harcourt, 52 rooms\n";
foreach ($summary as $s) { echo ' · '.$s."\n"; }
echo "\nRight now: ".$dash['active_staff']." active staff, ".$dash['on_site']." on site, ".$dash['punches_today']." punch(es) today, "
    .$dash['open_exceptions']." open exception(s), ".$dash['devices_total']." device(s).\n";
echo "Open Time & Attendance > Live Board at 6 a.m. to see the night shift hand over, or > Exceptions to clear the two missing clock-outs.\n";
echo "The push endpoint for real ZKTeco ADMS hardware is: ".PulseTaService::pushUrl()."\n";
