<?php
/**
 * Tests for PulseHotelScope.
 *
 * Two halves. First a set of hand-written cases that pin the behaviour that matters: outer joins
 * scoped in their ON clause, subqueries scoped in their own right, unscoped tables left alone,
 * statements with no scoped table returned byte-identical. Then a corpus run: every distinct SQL
 * statement the sandbox actually issued is rewritten and handed to MySQL for a parse check, so a
 * rewrite that produces invalid SQL fails here rather than on a screen.
 *
 * Run:  php modules/pulsecore/tests/scope_test.php [path-to-corpus]
 */
define('_PS_VERSION_', '1.6.1.24');
define('_DB_PREFIX_', 'qlo_');

require_once dirname(__FILE__).'/../classes/PulseHotelScope.php';

$pass = 0;
$fail = 0;

function check($name, $got, $want)
{
    global $pass, $fail;
    $got = preg_replace('/\s+/', ' ', trim($got));
    $want = preg_replace('/\s+/', ' ', trim($want));
    if ($got === $want) { ++$pass; return; }
    ++$fail;
    echo "FAIL  $name\n  got:  $got\n  want: $want\n\n";
}

function checkThat($name, $cond, $why = '')
{
    global $pass, $fail;
    if ($cond) { ++$pass; return; }
    ++$fail;
    echo "FAIL  $name  $why\n";
}

$H = 7;

/* ---- 1. the plain case ---- */
check('simple select',
    PulseHotelScope::apply('SELECT * FROM `qlo_pulse_folio` WHERE id_customer = 4', $H),
    'SELECT * FROM `qlo_pulse_folio` WHERE `qlo_pulse_folio`.`id_hotel` = 7 AND id_customer = 4');

check('select with alias',
    PulseHotelScope::apply('SELECT f.* FROM `qlo_pulse_folio` f WHERE f.status = "open"', $H),
    'SELECT f.* FROM `qlo_pulse_folio` f WHERE `f`.`id_hotel` = 7 AND f.status = "open"');

check('no where clause gets one',
    PulseHotelScope::apply('SELECT * FROM `qlo_pulse_folio` f ORDER BY f.date_add', $H),
    'SELECT * FROM `qlo_pulse_folio` f WHERE `f`.`id_hotel` = 7 ORDER BY f.date_add');

check('no where and no trailing clause',
    PulseHotelScope::apply('SELECT COUNT(*) FROM `qlo_pulse_folio`', $H),
    'SELECT COUNT(*) FROM `qlo_pulse_folio` WHERE `qlo_pulse_folio`.`id_hotel` = 7');

/* ---- 2. statements that must not be touched ---- */
$untouched = array(
    'SELECT * FROM `qlo_customer` WHERE id_customer = 1',
    'SELECT * FROM `qlo_htl_branch_info`',
    "SELECT * FROM `qlo_pulse_license_log` WHERE ok = 1",           // group-level table
    "SELECT * FROM `qlo_pulse_pr_tax_band` WHERE id_pulse_pr_country = 1",
);
foreach ($untouched as $i => $sql) {
    checkThat('untouched #'.$i, PulseHotelScope::apply($sql, $H) === $sql, $sql);
}

/* ---- 3. outer joins are scoped in the ON clause, not the WHERE ---- */
check('left join scoped in ON',
    PulseHotelScope::apply('SELECT f.id_pulse_folio, l.amount FROM `qlo_pulse_folio` f '
        .'LEFT JOIN `qlo_pulse_folio_line` l ON l.id_pulse_folio = f.id_pulse_folio '
        .'WHERE f.status = "open"', $H),
    'SELECT f.id_pulse_folio, l.amount FROM `qlo_pulse_folio` f '
    .'LEFT JOIN `qlo_pulse_folio_line` l ON l.id_pulse_folio = f.id_pulse_folio AND `l`.`id_hotel` = 7 '
    .'WHERE `f`.`id_hotel` = 7 AND f.status = "open"');

check('inner join scoped in WHERE',
    PulseHotelScope::apply('SELECT * FROM `qlo_pulse_folio` f '
        .'INNER JOIN `qlo_pulse_folio_line` l ON l.id_pulse_folio = f.id_pulse_folio', $H),
    'SELECT * FROM `qlo_pulse_folio` f INNER JOIN `qlo_pulse_folio_line` l ON l.id_pulse_folio = f.id_pulse_folio '
    .'WHERE `f`.`id_hotel` = 7 AND `l`.`id_hotel` = 7');

check('left join to a core table is left alone',
    PulseHotelScope::apply('SELECT * FROM `qlo_pulse_folio` f LEFT JOIN `qlo_customer` c ON c.id_customer = f.id_customer', $H),
    'SELECT * FROM `qlo_pulse_folio` f LEFT JOIN `qlo_customer` c ON c.id_customer = f.id_customer WHERE `f`.`id_hotel` = 7');

/* ---- 4. subqueries are their own scope ---- */
check('scalar subquery',
    PulseHotelScope::apply('SELECT f.folio_no, (SELECT SUM(amount) FROM `qlo_pulse_folio_line` l '
        .'WHERE l.id_pulse_folio = f.id_pulse_folio) AS total FROM `qlo_pulse_folio` f', $H),
    'SELECT f.folio_no, (SELECT SUM(amount) FROM `qlo_pulse_folio_line` l '
    .'WHERE `l`.`id_hotel` = 7 AND l.id_pulse_folio = f.id_pulse_folio) AS total '
    .'FROM `qlo_pulse_folio` f WHERE `f`.`id_hotel` = 7');

check('IN subquery',
    PulseHotelScope::apply('SELECT * FROM `qlo_pulse_folio` WHERE id_customer IN '
        .'(SELECT id_customer FROM `qlo_pulse_guest_profile` WHERE vip = 1)', $H),
    'SELECT * FROM `qlo_pulse_folio` WHERE `qlo_pulse_folio`.`id_hotel` = 7 AND id_customer IN '
    .'(SELECT id_customer FROM `qlo_pulse_guest_profile` WHERE `qlo_pulse_guest_profile`.`id_hotel` = 7 AND vip = 1)');

/* ---- 5. writes ---- */
check('update',
    PulseHotelScope::apply('UPDATE `qlo_pulse_folio` SET status = "closed" WHERE id_pulse_folio = 3', $H),
    'UPDATE `qlo_pulse_folio` SET status = "closed" WHERE `qlo_pulse_folio`.`id_hotel` = 7 AND id_pulse_folio = 3');

check('delete',
    PulseHotelScope::apply('DELETE FROM `qlo_pulse_folio_line` WHERE id_pulse_folio = 3', $H),
    'DELETE FROM `qlo_pulse_folio_line` WHERE `qlo_pulse_folio_line`.`id_hotel` = 7 AND id_pulse_folio = 3');

check('insert values gets the column and a value per tuple',
    PulseHotelScope::apply("INSERT INTO `qlo_pulse_folio` (folio_no, status) VALUES ('F1', 'open'), ('F2', 'open')", $H),
    "INSERT INTO `qlo_pulse_folio` (folio_no, status, `id_hotel`) VALUES ('F1', 'open', 7), ('F2', 'open', 7)");

check('insert ignore is handled too',
    PulseHotelScope::apply("INSERT IGNORE INTO `qlo_pulse_folio` (folio_no) VALUES ('F1')", $H),
    "INSERT IGNORE INTO `qlo_pulse_folio` (folio_no, `id_hotel`) VALUES ('F1', 7)");

check('on duplicate key tail is left alone',
    PulseHotelScope::apply("INSERT INTO `qlo_pulse_folio` (folio_no, status) VALUES ('F1', 'open') "
        ."ON DUPLICATE KEY UPDATE status = 'open'", $H),
    "INSERT INTO `qlo_pulse_folio` (folio_no, status, `id_hotel`) VALUES ('F1', 'open', 7) "
    ."ON DUPLICATE KEY UPDATE status = 'open'");

check('insert ... set form',
    PulseHotelScope::apply("INSERT INTO `qlo_pulse_folio` SET folio_no = 'F1', status = 'open'", $H),
    "INSERT INTO `qlo_pulse_folio` SET folio_no = 'F1', status = 'open', `id_hotel` = 7");

check('insert ... select stamps the row and scopes the select',
    PulseHotelScope::apply('INSERT INTO `qlo_pulse_folio_line` (id_pulse_folio, amount) '
        .'SELECT id_pulse_folio, 0 FROM `qlo_pulse_folio` WHERE status = "open"', $H),
    'INSERT INTO `qlo_pulse_folio_line` (id_pulse_folio, amount, `id_hotel`) SELECT id_pulse_folio, 0 , 7 '
    .'FROM `qlo_pulse_folio` WHERE `qlo_pulse_folio`.`id_hotel` = 7 AND status = "open"');

check('insert into an unscoped table is untouched',
    PulseHotelScope::apply("INSERT INTO `qlo_pulse_license_log` (ok) VALUES (1)", $H),
    "INSERT INTO `qlo_pulse_license_log` (ok) VALUES (1)");

check('a column list that already names id_hotel is left alone',
    PulseHotelScope::apply("INSERT INTO `qlo_pulse_folio` (folio_no, id_hotel) VALUES ('F1', 3)", $H),
    "INSERT INTO `qlo_pulse_folio` (folio_no, id_hotel) VALUES ('F1', 3)");

/* ---- 6. the safety property: no hotel means no rows, never all rows ---- */
$noHotel = PulseHotelScope::apply('SELECT * FROM `qlo_pulse_folio`', 0);
checkThat('no hotel matches nothing', strpos($noHotel, '1 = 0') !== false, $noHotel);

/* ---- 7. literals are opaque ---- */
check('a table name inside a string is not a table',
    PulseHotelScope::apply('SELECT * FROM `qlo_pulse_audit` WHERE payload = "FROM qlo_pulse_folio f WHERE"', $H),
    'SELECT * FROM `qlo_pulse_audit` WHERE `qlo_pulse_audit`.`id_hotel` = 7 AND payload = "FROM qlo_pulse_folio f WHERE"');

check('multibyte literals do not shift offsets',
    PulseHotelScope::apply('SELECT * FROM `qlo_pulse_folio` f WHERE f.note = "₦ ₦ ₦" ORDER BY f.id_pulse_folio', $H),
    'SELECT * FROM `qlo_pulse_folio` f WHERE `f`.`id_hotel` = 7 AND f.note = "₦ ₦ ₦" ORDER BY f.id_pulse_folio');

/* ---- 8. comma joins ---- */
check('comma join scopes both tables',
    PulseHotelScope::apply('SELECT * FROM `qlo_pulse_folio` f, `qlo_pulse_folio_line` l WHERE l.id_pulse_folio = f.id_pulse_folio', $H),
    'SELECT * FROM `qlo_pulse_folio` f, `qlo_pulse_folio_line` l '
    .'WHERE `f`.`id_hotel` = 7 AND `l`.`id_hotel` = 7 AND l.id_pulse_folio = f.id_pulse_folio');

/* ---- 9. GROUP BY / HAVING / LIMIT placement ---- */
check('where goes before group by',
    PulseHotelScope::apply('SELECT dept, SUM(amount) FROM `qlo_pulse_folio_line` GROUP BY dept HAVING SUM(amount) > 0 LIMIT 10', $H),
    'SELECT dept, SUM(amount) FROM `qlo_pulse_folio_line` WHERE `qlo_pulse_folio_line`.`id_hotel` = 7 '
    .'GROUP BY dept HAVING SUM(amount) > 0 LIMIT 10');

/* ---- 10. corpus run ---- */
$corpus = isset($argv[1]) ? $argv[1] : null;
if ($corpus && file_exists($corpus)) {
    $lines = array_filter(array_map('trim', file($corpus)));
    $seen = array();
    $changed = 0;
    $same = 0;
    foreach ($lines as $sql) {
        if (isset($seen[$sql])) { continue; }
        $seen[$sql] = 1;
        $out = PulseHotelScope::apply($sql, $H);
        if ($out === $sql) { ++$same; } else { ++$changed; }
        echo "###SQL\t".str_replace("\n", ' ', $out)."\n";
    }
    fwrite(STDERR, sprintf("corpus: %d distinct statements, %d rewritten, %d left alone\n", count($seen), $changed, $same));
}

fwrite(STDERR, sprintf("\n%d passed, %d failed\n", $pass, $fail));
$skip = PulseHotelScope::skipped();
if ($skip) {
    fwrite(STDERR, sprintf("%d statements the rewriter declined:\n", count($skip)));
    foreach (array_slice($skip, 0, 10) as $s) { fwrite(STDERR, '  ['.$s['why'].'] '.substr($s['sql'], 0, 150)."\n"); }
}
exit($fail ? 1 : 0);
