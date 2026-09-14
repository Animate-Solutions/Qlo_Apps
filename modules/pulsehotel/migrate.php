<?php
/**
 * Turn an existing single-property Pulse install into a multi-property one.
 *
 * Run this once, after the module files are in place and before pulsehotel is enabled. It is safe to
 * run again: every step checks whether it has already been done.
 *
 *   php modules/pulsehotel/migrate.php --check     what would change, nothing written
 *   php modules/pulsehotel/migrate.php --apply     do it
 *
 * What it does, in order:
 *   1. adds id_hotel to every Pulse table that needs it, with an index;
 *   2. widens every UNIQUE and natural PRIMARY key to include id_hotel, so two properties can each
 *      have a charge code called ROOM and each start their journal numbering at 1;
 *   3. fills id_hotel in on the rows that are already there, deriving it from the room, the booking,
 *      or the parent row, and falling back to the only hotel when there is only one;
 *   4. reports anything it could not place, rather than guessing.
 *
 * Take a backup first. This alters every Pulse table.
 */
define('_PS_ADMIN_DIR_', dirname(__FILE__).'/../../adminanimate');
require_once dirname(__FILE__).'/../../config/config.inc.php';
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseHotelScope.php';

$apply = in_array('--apply', $argv);
if (!$apply && !in_array('--check', $argv)) {
    fwrite(STDERR, "Usage: php modules/pulsehotel/migrate.php --check | --apply\n");
    exit(2);
}

$db = Db::getInstance();
$P = _DB_PREFIX_;
$scoped = array_keys(PulseHotelScope::tables());
$log = array();
$did = array('column' => 0, 'index' => 0, 'unique' => 0, 'primary' => 0, 'rows' => 0);

function q($sql, $apply, &$log)
{
    if (!$apply) { $log[] = $sql; return true; }
    if (!Db::getInstance()->execute($sql)) {
        $log[] = 'FAILED: '.$sql.' — '.Db::getInstance()->getMsgError();
        return false;
    }
    return true;
}

/* ---- 1 & 2: schema ---- */
$present = array();
foreach ($db->executeS('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()') as $r) {
    $present[$r['TABLE_NAME']] = true;
}

foreach ($scoped as $short) {
    $t = $P.$short;
    if (!isset($present[$t])) { continue; }

    $cols = array();
    foreach ($db->executeS('SHOW COLUMNS FROM `'.bqSQL($t).'`') as $c) { $cols[$c['Field']] = $c; }
    if (!isset($cols['id_hotel'])) {
        if (q('ALTER TABLE `'.bqSQL($t).'` ADD COLUMN `id_hotel` INT UNSIGNED NOT NULL DEFAULT 0', $apply, $log)) { ++$did['column']; }
    }

    $idx = $db->executeS('SHOW INDEX FROM `'.bqSQL($t).'`');
    $byName = array();
    foreach ($idx as $i) { $byName[$i['Key_name']][(int) $i['Seq_in_index']] = $i; }

    // Only add the index where nothing already leads with id_hotel: several of QloApps' own tables do,
    // and a second index on the same leading column costs write time and buys nothing.
    $hasHotelIndex = isset($byName['pulse_hotel']);
    foreach ($idx as $i) {
        if ((int) $i['Seq_in_index'] === 1 && $i['Column_name'] === 'id_hotel') { $hasHotelIndex = true; }
    }
    if (!$hasHotelIndex) {
        if (q('ALTER TABLE `'.bqSQL($t).'` ADD KEY `pulse_hotel` (`id_hotel`)', $apply, $log)) { ++$did['index']; }
    }

    foreach ($byName as $name => $parts) {
        ksort($parts);
        $first = reset($parts);
        if ((int) $first['Non_unique'] === 1) { continue; }
        $columns = array();
        foreach ($parts as $p) { $columns[] = $p['Column_name']; }
        if (in_array('id_hotel', $columns)) { continue; }
        $list = '`'.implode('`,`', array_map('bqSQL', $columns)).'`';
        if ($name === 'PRIMARY') {
            // Only a natural key needs this: an auto-increment key already separates the properties.
            $auto = false;
            foreach ($cols as $c) { if (strpos($c['Extra'], 'auto_increment') !== false) { $auto = true; } }
            if ($auto) { continue; }
            if (q('ALTER TABLE `'.bqSQL($t).'` DROP PRIMARY KEY, ADD PRIMARY KEY (`id_hotel`,'.$list.')', $apply, $log)) { ++$did['primary']; }
        } else {
            if (q('ALTER TABLE `'.bqSQL($t).'` DROP INDEX `'.bqSQL($name).'`, ADD UNIQUE KEY `'.bqSQL($name).'` (`id_hotel`,'.$list.')', $apply, $log)) { ++$did['unique']; }
        }
    }
}

/* ---- 3: the rows ---- */
$hotels = $db->executeS('SELECT id FROM `'.$P.'htl_branch_info` ORDER BY id');
$only = count($hotels) === 1 ? (int) $hotels[0]['id'] : 0;

// Anchors first: anything that can reach a room or a booking knows its hotel outright.
$anchors = array(
    'id_room' => 'JOIN `'.$P.'htl_room_information` r ON r.id = t.id_room SET t.id_hotel = r.id_hotel',
    'id_htl_booking' => 'JOIN `'.$P.'htl_booking_detail` b ON b.id = t.id_htl_booking SET t.id_hotel = b.id_hotel',
);
$children = array();

foreach ($scoped as $short) {
    $t = $P.$short;
    if (!isset($present[$t])) { continue; }
    $cols = array();
    foreach ($db->executeS('SHOW COLUMNS FROM `'.bqSQL($t).'`') as $c) { $cols[$c['Field']] = true; }
    if (!isset($cols['id_hotel'])) { continue; }

    $done = false;
    foreach ($anchors as $col => $frag) {
        if (isset($cols[$col])) {
            q('UPDATE `'.bqSQL($t).'` t '.$frag.' WHERE t.id_hotel = 0', $apply, $log);
            $done = true;
            break;
        }
    }
    if ($done) { continue; }
    // Otherwise remember which parent it hangs off, and fill it in once the parents are done.
    foreach (array_keys($cols) as $c) {
        if (strpos($c, 'id_pulse_') !== 0) { continue; }
        $parent = substr($c, 3);
        if ($parent !== $short && isset($present[$P.$parent])) { $children[] = array($short, $c, $parent); break; }
    }
}

// Three passes, so a grandchild picks up what its parent has just been given.
for ($pass = 0; $pass < 3; ++$pass) {
    foreach ($children as $c) {
        list($t, $col, $parent) = $c;
        q('UPDATE `'.bqSQL($P.$t).'` t INNER JOIN `'.bqSQL($P.$parent).'` p ON p.`'.bqSQL($col).'` = t.`'.bqSQL($col).'` '
            .'SET t.id_hotel = p.id_hotel WHERE t.id_hotel = 0 AND p.id_hotel > 0', $apply, $log);
    }
}

// Whatever is left belongs to the only property there is. With more than one, it has to be looked at.
if ($only) {
    foreach ($scoped as $short) {
        if (!isset($present[$P.$short])) { continue; }
        q('UPDATE `'.bqSQL($P.$short).'` SET id_hotel = '.(int) $only.' WHERE id_hotel = 0', $apply, $log);
    }
}

/* ---- 4: what is left ---- */
printf("schema: %d columns added, %d indexes added, %d unique keys widened, %d primary keys widened\n",
    $did['column'], $did['index'], $did['unique'], $did['primary']);

if ($apply) {
    $stranded = array();
    foreach ($scoped as $short) {
        if (!isset($present[$P.$short])) { continue; }
        $n = (int) $db->getValue('SELECT COUNT(*) FROM `'.bqSQL($P.$short).'` WHERE id_hotel = 0');
        if ($n) { $stranded[$short] = $n; }
    }
    printf("\nrows still without a hotel: %d table(s)\n", count($stranded));
    foreach ($stranded as $t => $n) { printf("  %-44s %d\n", $t, $n); }
    if ($stranded) {
        echo "\nThese could not be traced to a property. Set them by hand, or delete them if they are\n"
            ."orphans, before anyone signs in — a row with no hotel is invisible on every screen.\n";
    }
} else {
    printf("\n%d statement(s) would run. First 40:\n\n", count($log));
    foreach (array_slice($log, 0, 40) as $s) { echo '  '.$s."\n"; }
}

$failed = array_filter($log, function ($l) { return strpos($l, 'FAILED:') === 0; });
if ($failed) {
    printf("\n%d statement(s) failed:\n", count($failed));
    foreach (array_slice($failed, 0, 20) as $f) { echo '  '.$f."\n"; }
    exit(1);
}
