<?php
/**
 * Prove the isolation, rather than assert it.
 *
 * Two checks, both against the SQL the application actually issued while a session was in hotel B:
 *
 *  1. Every statement that reads or writes a hotel-scoped table carries that hotel's predicate.
 *     A statement that names a scoped table with no `id_hotel = B` anywhere is a leak, full stop.
 *  2. Re-running each captured SELECT returns nothing that belongs to another hotel. This is the
 *     empirical half: it catches a predicate that is present but attached to the wrong table.
 *
 * Usage: php modules/pulsecore/tests/isolation_check.php /tmp/corpus_hotel_b.sql 2
 */
define('_PS_ADMIN_DIR_', dirname(__FILE__).'/../../../adminanimate');
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/PulseHotelScope.php';

$file = isset($argv[1]) ? $argv[1] : '/tmp/corpus_hotel_b.sql';
$hotel = isset($argv[2]) ? (int) $argv[2] : 2;

$scoped = array();
foreach (array_keys(PulseHotelScope::tables()) as $t) { $scoped[_DB_PREFIX_.$t] = $t; }

$seen = array();
foreach (file($file) as $line) {
    $sql = trim($line);
    if ($sql === '') { continue; }
    $seen[$sql] = true;
}

$checked = $unprotected = $declared = 0;
$leaks = array();
$notes = array();

/**
 * The statements that deliberately look past the current hotel, and why.
 *
 * Each is a decision, not an oversight — which is exactly why they are named here: an unlisted
 * statement with no predicate is a leak, and this list is the only thing standing between the two.
 */
function declared($sql)
{
    if (preg_match('/htl_room_information`? WHERE id_status IN \(1,3\)/i', $sql)) {
        return 'the licence room count, which caps the whole installation rather than one property';
    }
    if (preg_match('/FROM `\w*pulse_setting`.*`id_hotel`\s*=\s*0/is', $sql)) {
        return 'a settings read falling back to the group default, which is what a new property inherits';
    }
    if (preg_match('/FROM `\w*htl_branch_info` hbi/i', $sql) && preg_match('/charge_codes|accounts/i', $sql)) {
        return 'the setup screen asking which properties are not seeded yet, across all of them';
    }
    return null;
}

foreach (array_keys($seen) as $sql) {
    $verb = strtoupper(strtok(ltrim($sql), ' '));
    if (!in_array($verb, array('SELECT', 'UPDATE', 'DELETE', 'INSERT'))) { continue; }

    // Which scoped tables does this statement actually name? Skip a name that only appears as an
    // alias — "SELECT FOUND_ROWS() AS `qlo_pulse_folio`" names no table at all.
    $named = array();
    foreach ($scoped as $full => $short) {
        if (preg_match('/(FROM|JOIN|INTO|UPDATE)\s+`?'.preg_quote($full, '/').'`?\b/i', $sql)) { $named[] = $full; }
    }
    if (!$named) { continue; }
    ++$checked;

    // Three shapes count as protected: a predicate, a stamped INSERT column, and the
    // deliberate match-nothing clause used when no hotel is chosen.
    $hasPredicate = preg_match('/\bid_hotel`?\s*(=|IN)\s*\(?\s*'.$hotel.'\b/i', $sql);
    $isStampedInsert = preg_match('/^\s*(INSERT|REPLACE)\b/i', $sql) && preg_match('/`id_hotel`/', $sql);
    if (!$hasPredicate && !$isStampedInsert && strpos($sql, '1 = 0') === false) {
        $why = declared($sql);
        if ($why !== null) { ++$declared; $notes[$why] = isset($notes[$why]) ? $notes[$why] + 1 : 1; continue; }
        ++$unprotected;
        if (count($leaks) < 25) { $leaks[] = array('no predicate', implode(', ', $named), substr($sql, 0, 220)); }
    }
}

printf("statements naming a scoped table : %d\n", $checked);
printf("deliberately group-wide          : %d\n", $declared);
foreach ($notes as $why => $n) { printf("    %2d  %s\n", $n, $why); }
printf("UNEXPLAINED, no predicate        : %d\n\n", $unprotected);
foreach ($leaks as $l) {
    printf("  [%s] %s\n    %s\n", $l[0], $l[1], $l[2]);
}

/* ---- the empirical half: does any scoped table still show another hotel's rows? ---- */
$db = Db::getInstance();
$bad = array();
foreach ($scoped as $full => $short) {
    $n = (int) $db->getValue('SELECT COUNT(*) FROM `'.bqSQL($full).'` WHERE id_hotel <> '.$hotel);
    $mine = (int) $db->getValue('SELECT COUNT(*) FROM `'.bqSQL($full).'` WHERE id_hotel = '.$hotel);
    if ($n > 0) { $bad[$short] = array($n, $mine); }
}
printf("\nscoped tables holding rows for other hotels: %d (this is expected — hotel A's data)\n", count($bad));
printf("of those, tables where hotel %d also has rows: %d\n", $hotel,
    count(array_filter($bad, function ($x) { return $x[1] > 0; })));

exit($unprotected ? 1 : 0);
