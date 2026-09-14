<?php
/**
 * Put the sandbox into a state where multi-property can actually be tested:
 * install pulsehotel, make sure there is a second hotel, and give two employees different access.
 *
 * Without a second hotel, every isolation test passes for the wrong reason.
 *
 * Usage: php modules/pulsecore/tests/setup_multiproperty.php
 */
define('_PS_ADMIN_DIR_', dirname(__FILE__).'/../../../adminanimate');
require_once dirname(__FILE__).'/../../../config/config.inc.php';

$db = Db::getInstance();
$now = date('Y-m-d H:i:s');

// Tab::initAccess() needs an employee in context, so sign in before installing anything.
$bootId = (int) $db->getValue('SELECT id_employee FROM `'._DB_PREFIX_.'employee` WHERE active = 1 ORDER BY id_employee');
Context::getContext()->employee = new Employee($bootId);

/* ---- 1. the module ---- */
$module = Module::getInstanceByName('pulsehotel');
if (!$module) { fwrite(STDERR, "pulsehotel module not found\n"); exit(2); }
if (!Module::isInstalled('pulsehotel')) {
    if (!$module->install()) {
        fwrite(STDERR, "install failed: ".implode('; ', (array) $module->getErrors())."\n");
        exit(2);
    }
    echo "pulsehotel installed\n";
} else {
    echo "pulsehotel already installed\n";
}

/* ---- 2. a second hotel ---- */
$hotels = $db->executeS('SELECT id FROM `'._DB_PREFIX_.'htl_branch_info` ORDER BY id');
if (count($hotels) < 2) {
    $first = (int) $hotels[0]['id'];
    // Db::insert() does not escape, and the hotel copy contains apostrophes, so escape every string.
    $esc = function ($row) {
        foreach ($row as $k => $v) { $row[$k] = is_string($v) ? pSQL($v, true) : $v; }
        return $row;
    };
    $row = $db->getRow('SELECT * FROM `'._DB_PREFIX_.'htl_branch_info` WHERE id = '.$first);
    unset($row['id']);
    $row['active'] = 1;
    $row['date_add'] = $now;
    $row['date_upd'] = $now;
    $db->insert('htl_branch_info', $esc($row), true);
    $second = (int) $db->Insert_ID();

    foreach ($db->executeS('SELECT * FROM `'._DB_PREFIX_.'htl_branch_info_lang` WHERE id = '.$first) as $l) {
        $l['id'] = $second;
        $l['hotel_name'] = 'Pulse Test Hotel Two';
        $db->insert('htl_branch_info_lang', $esc($l), true);
    }
    echo "second hotel created: id $second\n";
} else {
    $second = (int) $hotels[1]['id'];
    echo "second hotel already present: id $second\n";
}
$first = (int) $hotels[0]['id'];

/* ---- 3. two employees with different access ---- */
$employees = $db->executeS('SELECT id_employee, email FROM `'._DB_PREFIX_.'employee` WHERE active = 1 ORDER BY id_employee');
$both = (int) $employees[0]['id_employee'];

// A second employee who may only see hotel two — the one that proves refusal works.
$onlyTwoEmail = 'hotel.two@pulse.test';
$onlyTwo = (int) $db->getValue('SELECT id_employee FROM `'._DB_PREFIX_.'employee` WHERE email = "'.pSQL($onlyTwoEmail).'"');
if (!$onlyTwo) {
    $src = new Employee($both);
    $e = new Employee();
    $e->id_profile = $src->id_profile;
    $e->id_lang = $src->id_lang;
    $e->lastname = 'Two';
    $e->firstname = 'HotelOnly';
    $e->email = $onlyTwoEmail;
    $e->passwd = Tools::encrypt('PulseTest#2026');
    $e->active = 1;
    $e->add();
    $onlyTwo = (int) $e->id;
    echo "second employee created: id $onlyTwo ($onlyTwoEmail)\n";
} else {
    echo "second employee already present: id $onlyTwo\n";
}

// A third with no grants at all — the one that proves the sign-out path.
$noneEmail = 'hotel.none@pulse.test';
$none = (int) $db->getValue('SELECT id_employee FROM `'._DB_PREFIX_.'employee` WHERE email = "'.pSQL($noneEmail).'"');
if (!$none) {
    $src = new Employee($both);
    $e = new Employee();
    $e->id_profile = $src->id_profile;
    $e->id_lang = $src->id_lang;
    $e->lastname = 'None';
    $e->firstname = 'NoHotel';
    $e->email = $noneEmail;
    $e->passwd = Tools::encrypt('PulseTest#2026');
    $e->active = 1;
    $e->add();
    $none = (int) $e->id;
    echo "third employee created: id $none ($noneEmail)\n";
}

/* ---- 4. the grants ---- */
$db->delete('pulse_hotel_access', 'id_employee IN ('.$both.', '.$onlyTwo.', '.$none.')');
foreach (array(array($both, $first, 1), array($both, $second, 0), array($onlyTwo, $second, 1)) as $g) {
    $db->insert('pulse_hotel_access', array(
        'id_employee' => (int) $g[0], 'id_hotel' => (int) $g[1], 'is_default' => (int) $g[2],
        'can_switch' => 1, 'date_add' => $now, 'date_upd' => $now,
    ));
}
// The employee with no grants must also be invisible to the profile-level fallback, or the test lies.
Configuration::updateValue('PULSE_HOTEL_PROFILE_FALLBACK', 0);

printf("\ngrants now:\n");
foreach ($db->executeS('SELECT a.id_employee, e.email, a.id_hotel, a.is_default FROM `'._DB_PREFIX_.'pulse_hotel_access` a '
    .'INNER JOIN `'._DB_PREFIX_.'employee` e ON e.id_employee = a.id_employee ORDER BY a.id_employee, a.id_hotel') as $r) {
    printf("  employee %-3d %-26s hotel %d%s\n", $r['id_employee'], $r['email'], $r['id_hotel'], $r['is_default'] ? ' (default)' : '');
}
printf("\nhotel A = %d, hotel B = %d\nboth-hotels employee = %d, hotel-B-only = %d, no-access = %d\n",
    $first, $second, $both, $onlyTwo, $none);

file_put_contents('/tmp/mp_ids.json', json_encode(array(
    'hotel_a' => $first, 'hotel_b' => $second, 'emp_both' => $both, 'emp_b_only' => $onlyTwo, 'emp_none' => $none,
)));
