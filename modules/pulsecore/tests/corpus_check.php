<?php
/**
 * Take every statement the sandbox actually issued, rewrite it for a hotel, and make MySQL judge it.
 *
 * A rewrite that produces invalid SQL, or that references a column that is not there, fails here —
 * against 1,300-odd real statements — rather than on a screen in front of a receptionist.
 *
 * Usage: php modules/pulsecore/tests/corpus_check.php /tmp/corpus_raw.sql [hotel]
 */
define('_PS_ADMIN_DIR_', dirname(__FILE__).'/../../../adminanimate');
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/PulseHotelScope.php';

$file = isset($argv[1]) ? $argv[1] : '/tmp/corpus_raw.sql';
$hotel = isset($argv[2]) ? (int) $argv[2] : 1;

$seen = array();
foreach (file($file) as $line) {
    $sql = trim($line);
    if ($sql === '' || stripos($sql, _DB_PREFIX_.'pulse') === false) { continue; }
    $seen[$sql] = true;
}
$stmts = array_keys($seen);
printf("%d distinct statements touching Pulse tables\n\n", count($stmts));

$db = Db::getInstance();
$unchanged = $rewritten = $badBefore = $badAfter = 0;
$failures = array();

foreach ($stmts as $sql) {
    $out = PulseHotelScope::apply($sql, $hotel);
    if ($out === $sql) { ++$unchanged; continue; }
    ++$rewritten;

    // Only judge a rewrite that MySQL could have judged before it. A statement that was already
    // invalid is somebody else's bug, and counting it here would hide ours.
    $okBefore = explainable($db, $sql);
    if ($okBefore !== true) { ++$badBefore; continue; }
    $okAfter = explainable($db, $out);
    if ($okAfter !== true) {
        ++$badAfter;
        $failures[] = array($sql, $out, $okAfter);
    }
}

function explainable($db, $sql)
{
    $v = strtoupper(substr(ltrim($sql), 0, 6));
    if (!in_array($v, array('SELECT', 'UPDATE', 'DELETE', 'INSERT'))) { return 'not explainable'; }
    try {
        @$db->execute('EXPLAIN '.$sql, false);
        $err = $db->getMsgError();
        return $err ? $err : true;
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

printf("rewritten          : %d\n", $rewritten);
printf("left alone         : %d\n", $unchanged);
printf("invalid beforehand : %d (not our doing, skipped)\n", $badBefore);
printf("BROKEN BY REWRITE  : %d\n", $badAfter);

foreach (array_slice($failures, 0, 12) as $f) {
    echo "\n--- ".$f[2]."\nbefore: ".substr($f[0], 0, 260)."\nafter : ".substr($f[1], 0, 260)."\n";
}

$skipped = PulseHotelScope::skipped();
printf("\ndeclined by the rewriter: %d\n", count($skipped));
foreach (array_slice($skipped, 0, 10) as $s) {
    echo '  ['.$s['why'].'] '.substr($s['sql'], 0, 160)."\n";
}
exit($badAfter ? 1 : 0);
