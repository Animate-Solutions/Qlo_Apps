<?php
/**
 * Exercise as much of the Pulse suite as possible and capture the SQL it issues.
 *
 * Four phases, each selectable on its own:
 *   posts  every Tools::isSubmit()/ajaxProcess branch of every AdminPulse* controller
 *   api    every resource of every Pulse controllers/front/api.php
 *   front  every public front controller
 *   cron   every cron entry point
 *
 * Usage:
 *   php modules/pulsecore/tests/exercise.php --phase=all --sql=/tmp/corpus_wide.sql
 *   php modules/pulsecore/tests/exercise.php --phase=posts --only=Waitlist
 *   php modules/pulsecore/tests/exercise.php --phase=all --list        # inventory only, runs nothing
 *
 * ---- how each item is run ----
 * Every item runs in a forked child of the one bootstrapped process, not over HTTP. Pulse code ends
 * a request the way PrestaShop does — Tools::redirectAdmin(), die(csv), PulseApiController::respond()
 * — so running items inline would let the first CSV export end the whole run. A fork per item contains
 * exit(), die() and fatals, and gives every item the same pristine context. SQL capture is switched on
 * inside the child, so the corpus holds what the items issued and nothing the harness did.
 *
 * ---- how the sandbox data is protected ----
 * mysqldump of the whole schema before the run, restored after it (in a shutdown handler, so a die()
 * or a fatal still restores). NOT a transaction: each forked child opens its own connection, so a
 * transaction held by the parent could not cover their writes, and the children commit for real.
 * The dump is the whole database rather than just qlo_pulse%, because night audit, payments and the
 * crons also write orders, bookings, configuration and log rows.
 *
 * Output is one line per item: phase, item, outcome, SQL statements captured, and for a failure the
 * message and file:line. Ends with per-phase counts and the most common exceptions.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 0);      // notices about headers already sent are the harness's own doing; fatals still come back via error_get_last()
set_time_limit(0);

$opt = array('phase' => 'all', 'sql' => '', 'only' => '', 'hotel' => 0, 'access' => 0, 'timeout' => 25,
    'dump' => '/tmp/pulse_exercise_restore.sql', 'list' => 0, 'no-restore' => 0);
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) { $opt[$m[1]] = isset($m[2]) ? $m[2] : 1; }
}
$phases = $opt['phase'] === 'all' ? array('posts', 'api', 'front', 'cron') : explode(',', $opt['phase']);

define('_PS_ADMIN_DIR_', dirname(__FILE__).'/../../../adminanimate');
require_once dirname(__FILE__).'/../../../config/config.inc.php';

$MAIN_PID = getmypid();
$SQL_FILE = $opt['sql'] ? $opt['sql'] : '';
if ($SQL_FILE) { @unlink($SQL_FILE); }

/* ---------------------------------------------------------------- context ---- */

$idEmployee = (int) Db::getInstance()->getValue('SELECT id_employee FROM `'._DB_PREFIX_.'employee` WHERE active = 1 ORDER BY id_employee');
if (!$idEmployee) { fwrite(STDERR, "no active employee\n"); exit(2); }
$employee = new Employee($idEmployee);
$context = Context::getContext();
$context->employee = $employee;
$context->cookie->id_employee = $employee->id;
$context->cookie->passwd = $employee->passwd;
$context->cookie->email = $employee->email;
$context->cookie->profile = $employee->id_profile;
$context->cookie->id_lang = (int) $employee->id_lang;
$context->language = new Language((int) $employee->id_lang);
$context->currency = Currency::getDefaultCurrency();
$context->shop = new Shop((int) Configuration::get('PS_SHOP_DEFAULT'));
Shop::setContext(Shop::CONTEXT_SHOP, $context->shop->id);
if (!Validate::isLoadedObject($context->customer)) { $context->customer = new Customer(); }

// The module autoloaders have not run yet, so load the scoping classes before choosing a hotel —
// otherwise --hotel silently does nothing and the isolation run proves nothing.
foreach (array('pulsecore/classes/PulseHotelScope.php', 'pulsecore/classes/PulseDb.php',
               'pulsecore/classes/PulseDbHandle.php', 'pulsehotel/classes/PulseHotelContext.php') as $__sc) {
    if (file_exists(_PS_MODULE_DIR_.$__sc)) { require_once _PS_MODULE_DIR_.$__sc; }
}
if ((int) $opt['hotel'] && class_exists('PulseHotelContext')) {
    $context->cookie->{PulseHotelContext::COOKIE} = (int) $opt['hotel'];
    PulseHotelContext::reset();
}

/* ------------------------------------------------------------ plausibles ---- */

/** A real id for a field name, so branches join against rows that exist instead of id 1. */
function pulse_id($field)
{
    static $cache = array();
    if (!preg_match('/^id_[a-z0-9_]+$/', $field)) { return 1; }
    if (isset($cache[$field])) { return $cache[$field]; }
    $cache[$field] = 1;
    $tables = Db::getInstance()->executeS('SELECT TABLE_NAME t FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = "'.pSQL($field).'" AND TABLE_NAME LIKE "'.pSQL(_DB_PREFIX_).'%"
        ORDER BY (TABLE_NAME LIKE "'.pSQL(_DB_PREFIX_).'pulse%") DESC LIMIT 6');
    foreach ((array) $tables as $t) {
        $v = (int) Db::getInstance()->getValue('SELECT MAX(`'.bqSQL($field).'`) FROM `'.bqSQL($t['t']).'`');
        if ($v > 0) { $cache[$field] = $v; break; }
    }
    return $cache[$field];
}

/** A plausible value for a form field, chosen from its name. */
function pulse_val($field)
{
    $known = array(
        'token' => null, 'action' => null, 'ajax' => null, 'controller' => null,
        'status' => 'open', 'department' => 'frontdesk', 'category' => 'admin', 'priority' => 'normal',
        'type' => 'other', 'source' => 'desk', 'channel' => 'email', 'scope' => 'hotel',
        'email' => 'qa.exercise@example.com', 'phone' => '+2348000000000', 'mobile' => '+2348000000001',
        'period' => date('Y-m', strtotime('first day of last month')),
        'business_date' => date('Y-m-d'), 'as_of' => date('Y-m-d'),
        'year' => date('Y'), 'month' => date('n'), 'week' => date('W'),
        'q' => 'a', 'sort' => 'date_add', 'export' => 0, 'report' => 'summary',
        'lang' => 'en', 'scopes' => 'frontdesk', 'code' => 'QA1', 'reference' => 'QA-REF-1',
        'reason' => 'exercise harness', 'note' => 'exercise harness', 'description' => 'exercise harness',
        'name' => 'QA Exercise', 'title' => 'QA Exercise', 'label' => 'QA Exercise',
        'lines' => array(), 'items' => array(), 'rows' => array(), 'offer' => array(),    // API bodies that are lists
        'location' => 'Store', 'supplier' => 'QA Supplier', 'account_code' => '1000',
        'cost_centre' => 'GEN', 'guest_name' => 'QA Guest', 'sname' => 'QA',
    );
    if (array_key_exists($field, $known)) { return $known[$field]; }
    if (preg_match('/^id_/', $field) || $field === 'id') { return pulse_id($field); }
    if (preg_match('/(^|_)(date|from|to|day|start|end|due|when)(_|$)/', $field)) {
        return preg_match('/(^|_)(to|end|due)(_|$)/', $field) ? date('Y-m-d', strtotime('+7 days')) : date('Y-m-d');
    }
    if (preg_match('/(^|_)(active|enabled|mandatory|approved|is_[a-z]+|confirm)(_|$)/', $field)) { return 1; }
    if (preg_match('/(amount|price|cost|total|rate|pct|tax|qty|days|minutes|hours|nights|rooms|count|max|min|level|points|n)$/', $field)) { return 1; }
    return 'QA';
}

/** Field names the source reads, split by the shape the code expects them in. */
function pulse_fields($src)
{
    $out = array('plain' => array(), 'array' => array(), 'json' => array());
    preg_match_all('/Tools::getValue\(\s*\'([A-Za-z0-9_]+)\'/', $src, $m);
    $out['plain'] = array_values(array_unique($m[1]));
    preg_match_all('/\(array\)\s*Tools::getValue\(\s*\'([A-Za-z0-9_]+)\'/', $src, $m);
    $out['array'] = array_values(array_unique($m[1]));
    preg_match_all('/json_decode\(\s*Tools::getValue\(\s*\'([A-Za-z0-9_]+)\'/', $src, $m);
    $out['json'] = array_values(array_unique($m[1]));
    return $out;
}

/** Build the $_POST/$_GET payload a branch needs: its own key, plus a value for every field it reads. */
function pulse_payload($fields, $skip = array())
{
    $p = array();
    foreach ($fields['plain'] as $f) {
        if (in_array($f, $skip)) { continue; }
        $v = pulse_val($f);
        if ($v !== null) { $p[$f] = $v; }
    }
    foreach ($fields['array'] as $f) { $p[$f] = array(1 => 1); }
    foreach ($fields['json'] as $f) { $p[$f] = '[]'; }
    return $p;
}

/* ------------------------------------------------------------- isolation ---- */

$RESULTS = array();

/**
 * Run one item in a forked child and record what happened. The child reconnects to MySQL (the parent
 * drops its link first so the two never share a socket) and switches SQL capture on for its own work.
 */
function pulse_run($phase, $item, $fn, $where = '')
{
    global $RESULTS, $MAIN_PID, $SQL_FILE, $opt;
    $rf = tempnam(sys_get_temp_dir(), 'pulsex');
    $before = $SQL_FILE && file_exists($SQL_FILE) ? filesize($SQL_FILE) : 0;

    Db::getInstance()->disconnect();
    $pid = pcntl_fork();
    if ($pid === -1) {
        $RESULTS[] = array($phase, $item, 'SKIPPED', 'fork failed', 0);
        Db::getInstance()->connect();
        return;
    }

    if ($pid === 0) {
        $rec = array('status' => 'OK', 'msg' => '', 'where' => '', 'done' => false);
        register_shutdown_function(function () use ($rf, &$rec, $MAIN_PID) {
            if (getmypid() === $MAIN_PID) { return; }
            if (!$rec['done']) {
                $e = error_get_last();
                if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR))) {
                    $rec['status'] = 'EXCEPTION';
                    $rec['msg'] = preg_replace('/\s+/', ' ', $e['message']);
                    $rec['where'] = str_replace(_PS_ROOT_DIR_.'/', '', $e['file']).':'.$e['line'];
                } else {
                    // die()/exit(): a CSV download, a redirect or a JSON response — the branch did run.
                    $rec['status'] = 'OK';
                    $rec['msg'] = 'ended the request (die/exit)';
                }
            }
            while (ob_get_level()) { ob_end_clean(); }
            @file_put_contents($rf, json_encode($rec));
        });
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, function () { exit(0); });
        pcntl_alarm((int) $opt['timeout']);

        Db::getInstance()->connect();
        if ($SQL_FILE) { putenv('PULSE_SQL_CAPTURE='.$SQL_FILE); }
        ob_start();
        try {
            // Most Pulse controllers catch their own exceptions into $this->errors; a callable returns
            // that list so a branch that failed quietly is still reported as a failure.
            $errs = $fn();
            if (is_array($errs) && $errs) {
                $rec['status'] = 'EXCEPTION';
                $rec['msg'] = 'controller error: '.preg_replace('/\s+/', ' ', implode(' | ', array_slice($errs, 0, 3)));
                $rec['where'] = $where;
            }
        } catch (Throwable $e) {
            $rec['status'] = 'EXCEPTION';
            $rec['msg'] = preg_replace('/\s+/', ' ', $e->getMessage());
            $rec['where'] = str_replace(_PS_ROOT_DIR_.'/', '', $e->getFile()).':'.$e->getLine();
        }
        $rec['done'] = true;
        exit(0);
    }

    $deadline = time() + (int) $opt['timeout'] + 5;
    do {
        $done = pcntl_waitpid($pid, $st, WNOHANG);
        if ($done === 0) { usleep(20000); }
    } while ($done === 0 && time() < $deadline);
    if ($done === 0) { posix_kill($pid, SIGKILL); pcntl_waitpid($pid, $st); }
    Db::getInstance()->connect();

    $rec = @json_decode((string) @file_get_contents($rf), true);
    @unlink($rf);
    if (!is_array($rec)) { $rec = array('status' => 'EXCEPTION', 'msg' => 'child died without a result (timeout or signal)', 'where' => ''); }
    $n = 0;
    if ($SQL_FILE && ($fh = @fopen($SQL_FILE, 'r'))) {
        fseek($fh, $before);
        while (fgets($fh) !== false) { ++$n; }
        fclose($fh);
    }
    $RESULTS[] = array($phase, $item, $rec['status'], trim($rec['msg'].' '.($rec['where'] ? '@ '.$rec['where'] : '')), $n);
    printf("%-6s %-62s %-9s %4d  %s\n", $phase, substr($item, 0, 62), $rec['status'], $n, substr($RESULTS[count($RESULTS) - 1][3], 0, 200));
}

function pulse_skip($phase, $item, $why)
{
    global $RESULTS;
    $RESULTS[] = array($phase, $item, 'SKIPPED', $why, 0);
    printf("%-6s %-62s %-9s %4d  %s\n", $phase, substr($item, 0, 62), 'SKIPPED', 0, $why);
}

/* ---------------------------------------------------------------- snapshot ---- */

function pulse_mysql_cmd($bin, $extra = '')
{
    return 'MYSQL_PWD='.escapeshellarg(_DB_PASSWD_).' '.$bin.' -h '.escapeshellarg(_DB_SERVER_)
        .' -u '.escapeshellarg(_DB_USER_).' '.$extra.' '.escapeshellarg(_DB_NAME_);
}

/** Row counts of the biggest Pulse tables, to prove the run left nothing behind. */
function pulse_counts()
{
    $out = array();
    $tables = Db::getInstance()->executeS('SELECT TABLE_NAME t FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE "'.pSQL(_DB_PREFIX_).'pulse%"
        ORDER BY TABLE_ROWS DESC LIMIT 10');
    foreach ((array) $tables as $t) {
        $out[$t['t']] = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `'.bqSQL($t['t']).'`');
    }
    return $out;
}

$countsBefore = pulse_counts();
$dump = $opt['dump'];
exec(pulse_mysql_cmd('mysqldump', '--single-transaction --quick --routines --skip-lock-tables').' > '.escapeshellarg($dump).' 2>/dev/null', $o, $rc);
if ($rc !== 0 || !filesize($dump)) { fwrite(STDERR, "mysqldump failed — refusing to run\n"); exit(2); }
printf("snapshot: %s (%.1f MB)\n", $dump, filesize($dump) / 1048576);

$RESTORED = false;
register_shutdown_function(function () use ($dump, $MAIN_PID, $opt, &$RESTORED) {
    if (getmypid() !== $MAIN_PID || $RESTORED || $opt['no-restore']) { return; }
    $RESTORED = true;
    exec(pulse_mysql_cmd('mysql').' < '.escapeshellarg($dump).' 2>/dev/null');
});

/* ------------------------------------------------------------- inventory ---- */
/* Every payload is built here, in the parent, so the children's capture holds only Pulse's own SQL. */

$items = array();

/* -- 1. admin POST handlers -- */
if (in_array('posts', $phases)) {
    foreach (glob(_PS_MODULE_DIR_.'pulse*/controllers/admin/AdminPulse*Controller.php') as $file) {
        $class = basename($file, '.php');
        $module = basename(dirname(dirname(dirname($file))));
        $src = file_get_contents($file);
        preg_match_all('/Tools::isSubmit\(\s*\'([A-Za-z0-9_]+)\'/', $src, $m);
        $submits = array_values(array_unique($m[1]));
        preg_match_all('/function\s+ajaxProcess([A-Za-z0-9_]+)\s*\(/', $src, $m);
        $ajax = array_values(array_unique($m[1]));
        $base = pulse_payload(pulse_fields($src), $submits);
        foreach ($submits as $key) {
            $items[] = array('posts', $class.'::'.$key, array($file, $class, array_merge($base, array($key => 1)), false));
        }
        foreach ($ajax as $key) {
            $items[] = array('posts', $class.'::ajax:'.$key, array($file, $class, array_merge($base, array('action' => $key, 'ajax' => 1)), true));
        }
        if (!$submits && !$ajax) { $items[] = array('posts', $class.'::(no branch)', array($file, $class, $base, false)); }
    }
}

/* -- 2. JSON API resources -- */
if (in_array('api', $phases)) {
    foreach (glob(_PS_MODULE_DIR_.'pulse*/controllers/front/api.php') as $file) {
        $module = basename(dirname(dirname(dirname($file))));
        $src = file_get_contents($file);
        if (!preg_match('/\$resources\s*=\s*array\((.*?)\);/s', $src, $m)) {
            pulse_skip('api', $module.'/api.php', 'no $resources map found');
            continue;
        }
        preg_match_all('/\'([A-Za-z0-9_]+)\'\s*=>\s*\'([A-Za-z0-9_]+)\'/', $m[1], $r, PREG_SET_ORDER);
        if (!preg_match('/class\s+([A-Za-z0-9_]+)\s+extends/', $src, $c)) {
            pulse_skip('api', $module.'/api.php', 'no class declaration');
            continue;
        }
        foreach ($r as $res) {
            $near = '';
            $param = 'id';
            $bodyVar = 'body';
            if (preg_match('/function\s+'.preg_quote($res[2], '/').'\s*\(([^)]*)\)/', $src, $m2, PREG_OFFSET_CAPTURE)) {
                $near = substr($src, $m2[0][1], 2500);
                preg_match_all('/\$([A-Za-z0-9_]+)/', $m2[1][0], $p);
                // The two parameters are the record id and the decoded JSON body; the files name them freely.
                if (isset($p[1][0])) { $param = $p[1][0]; }
                if (isset($p[1][1])) { $bodyVar = $p[1][1]; }
            }
            $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $param));
            $id = $snake === 'id' ? 1 : pulse_val(preg_match('/^id_/', $snake) ? $snake : 'id_'.$snake);
            $body = array();
            preg_match_all('/\$'.preg_quote($bodyVar, '/').'\[\s*\'([A-Za-z0-9_]+)\'/', $near, $b);
            foreach (array_unique($b[1]) as $f) { $body[$f] = pulse_val($f); }
            $get = array_merge(pulse_payload(pulse_fields($near)),
                array('module' => $module, 'controller' => 'api', 'resource' => $res[1], 'id' => $id));
            $items[] = array('api', $module.'/'.$res[1], array($file, $c[1], $res[2], (int) $id, $body, $get));
        }
    }
}

/* -- 3. public front controllers -- */
if (in_array('front', $phases)) {
    foreach (glob(_PS_MODULE_DIR_.'pulse*/controllers/front/*.php') as $file) {
        $base = basename($file, '.php');
        if ($base === 'index' || $base === 'api') { continue; }
        $module = basename(dirname(dirname(dirname($file))));
        $src = file_get_contents($file);
        if (!preg_match('/class\s+([A-Za-z0-9_]+)\s+extends/', $src, $c)) {
            pulse_skip('front', $module.'/'.$base, 'no class declaration');
            continue;
        }
        $get = array_merge(pulse_payload(pulse_fields($src)), array('module' => $module, 'controller' => $base, 'fc' => 'module'));
        $items[] = array('front', $module.'/'.$base, array($file, $c[1], $get));
    }
}

/* -- 4. cron entry points -- */
if (in_array('cron', $phases)) {
    foreach (glob(_PS_MODULE_DIR_.'pulse*/cron/*.php') as $file) {
        if (basename($file) === 'index.php') { continue; }
        $module = basename(dirname(dirname($file)));
        $src = file_get_contents($file);
        preg_match('/Configuration::get\(\s*\'([A-Z_]*CRON_TOKEN)\'/', $src, $m);
        $const = isset($m[1]) ? $m[1] : '';
        if ($const) {
            // The cron dies on a bad token; give it the configured one, minting it when the sandbox has none.
            $tok = Configuration::get($const);
            if (!$tok) { $tok = Tools::passwdGen(32); Configuration::updateValue($const, $tok); }
        } else {
            $tok = '';
        }
        $get = array_merge(pulse_payload(pulse_fields($src)), array('token' => $tok, 'task' => 'all',
            'period' => date('Y-m', strtotime('first day of last month'))));    // depreciation.php refuses an unfinished period
        $items[] = array('cron', $module.'/'.basename($file), array($file, $get));
    }
}

// The hotel access screen is skipped by default: its own handlers grant and revoke hotels, and a
// sweep that revokes the harness employee's access halfway through invalidates everything after it.
// Pass --access=1 to include it.
if (!$opt['access']) {
    $items = array_values(array_filter($items, function ($i) { return stripos($i[1], 'AdminPulseHotelAccess') === false; }));
}
if ($opt['only']) {
    $items = array_values(array_filter($items, function ($i) use ($opt) { return stripos($i[1], $opt['only']) !== false; }));
}

printf("%d item(s) across phase(s): %s\n\n", count($items), implode(',', $phases));
if ($opt['list']) {
    foreach ($items as $i) { echo $i[0]."\t".$i[1]."\n"; }
    exit(0);
}

/* ------------------------------------------------------------------- run ---- */

/**
 * A fresh token carrying every scope any resource asks for. Minted when the api phase starts rather
 * than up front, because the posts phase runs first and its token-management branches revoke tokens.
 */
function pulse_api_token()
{
    $scopes = array();
    foreach (glob(_PS_MODULE_DIR_.'pulse*/controllers/front/api.php') as $f) {
        preg_match_all('/requireScope\(\s*\'([A-Za-z0-9_.:]+)\'/', file_get_contents($f), $s);
        $scopes = array_merge($scopes, $s[1]);
    }
    $token = Tools::passwdGen(64, 'ALPHANUMERIC');
    // Bind the token to the hotel being exercised. A token with no hotel is a group token, and the
    // API rightly refuses one that does not name a property per request — which would make every
    // resource fail here for a reason that has nothing to do with the resource.
    global $opt;
    Db::getInstance()->insert('pulse_api_token', array('label' => 'exercise harness', 'token' => pSQL($token),
        'scopes' => pSQL(implode(',', array_unique($scopes))), 'id_hotel' => (int) $opt['hotel'],
        'active' => 1, 'date_add' => date('Y-m-d H:i:s')));
    return $token;
}

$apiToken = '';

foreach ($items as $it) {
    list($phase, $label, $a) = $it;
    if ($phase === 'api' && !$apiToken) { $apiToken = pulse_api_token(); }

    if ($phase === 'posts') {
        list($file, $class, $payload, $isAjax) = $a;
        pulse_run($phase, $label, function () use ($file, $class, $payload, $isAjax) {
            require_once $file;
            if (!class_exists($class)) { throw new Exception('class '.$class.' not declared by its file'); }
            $name = substr($class, 0, -10);
            $_POST = $payload;
            $_GET = array_merge($payload, array('controller' => $name, 'token' => Tools::getAdminTokenLite($name)));
            $_REQUEST = $_GET;
            $c = new $class();
            $c->id = (int) Tab::getIdFromClassName($name);
            $c->controller_name = $name;
            $c->php_self = $name;
            $c->ajax = $isAjax;
            $c->init();
            $c->initProcess();
            $c->postProcess();
            return $c->errors;
        }, str_replace(_PS_ROOT_DIR_.'/', '', $file));
        continue;
    }

    if ($phase === 'api') {
        // The dispatch PulseApiController::postProcess() performs, minus respond()'s die().
        list($file, $class, $method, $id, $body, $get) = $a;
        $token = $apiToken;
        pulse_run($phase, $label, function () use ($file, $class, $method, $id, $body, $get, $token) {
            require_once $file;
            if (!class_exists($class)) { throw new Exception('class '.$class.' not declared by its file'); }
            $_GET = $_POST = $_REQUEST = $get;
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
            $c = new $class();
            $r = new ReflectionClass($c);
            foreach (array('authenticate', $method) as $call) {
                $mm = $r->getMethod($call);
                $mm->setAccessible(true);
                $mm->invokeArgs($c, $call === 'authenticate' ? array() : array($id, $body));
            }
        });
        continue;
    }

    if ($phase === 'front') {
        list($file, $class, $get) = $a;
        pulse_run($phase, $label, function () use ($file, $class, $get) {
            require_once $file;
            if (!class_exists($class)) { throw new Exception('class '.$class.' not declared by its file'); }
            $_GET = $_POST = $_REQUEST = $get;
            $c = new $class();
            $c->init();
            $c->postProcess();
            $c->initContent();
            return $c->errors;
        }, str_replace(_PS_ROOT_DIR_.'/', '', $file));
        continue;
    }

    if ($phase === 'cron') {
        list($file, $get) = $a;
        pulse_run($phase, $label, function () use ($file, $get) {
            $_GET = $_POST = $_REQUEST = $get;
            include $file;
        });
        continue;
    }
}

/* ---------------------------------------------------------------- restore ---- */

exec(pulse_mysql_cmd('mysql').' < '.escapeshellarg($dump).' 2>&1', $ro, $rrc);
$RESTORED = true;
Db::getInstance()->disconnect();
Db::getInstance()->connect();
$countsAfter = pulse_counts();

/* ----------------------------------------------------------------- report ---- */

echo "\n---- per phase ----\n";
$tot = array();
foreach ($RESULTS as $r) {
    $p = $r[0];
    if (!isset($tot[$p])) { $tot[$p] = array('n' => 0, 'ok' => 0, 'exc' => 0, 'skip' => 0, 'sql' => 0); }
    ++$tot[$p]['n'];
    $tot[$p][$r[2] === 'OK' ? 'ok' : ($r[2] === 'EXCEPTION' ? 'exc' : 'skip')]++;
    $tot[$p]['sql'] += $r[4];
}
printf("%-8s %6s %6s %10s %8s %8s\n", 'phase', 'items', 'ok', 'exception', 'skipped', 'sql');
foreach ($tot as $p => $t) { printf("%-8s %6d %6d %10d %8d %8d\n", $p, $t['n'], $t['ok'], $t['exc'], $t['skip'], $t['sql']); }

echo "\n---- most common exceptions ----\n";
$agg = array();
foreach ($RESULTS as $r) {
    if ($r[2] !== 'EXCEPTION') { continue; }
    $k = $r[3];
    if (!isset($agg[$k])) { $agg[$k] = array('n' => 0, 'first' => $r[1]); }
    ++$agg[$k]['n'];
}
uasort($agg, function ($a, $b) { return $b['n'] - $a['n']; });
$i = 0;
foreach ($agg as $msg => $a) {
    if (++$i > 10) { break; }
    printf("%4d  %s\n            first seen: %s\n", $a['n'], $msg, $a['first']);
}

echo "\n---- data unchanged? (10 largest pulse tables) ----\n";
$drift = 0;
foreach ($countsBefore as $t => $n) {
    $after = isset($countsAfter[$t]) ? $countsAfter[$t] : -1;
    if ($after !== $n) { ++$drift; }
    printf("%-44s %8d -> %8d %s\n", $t, $n, $after, $after === $n ? '' : '  DRIFT');
}
printf("\nrestore exit %d, %d table(s) drifted\n", $rrc, $drift);
if ($SQL_FILE && file_exists($SQL_FILE)) {
    $lines = 0;
    $distinct = array();
    $fh = fopen($SQL_FILE, 'r');
    while (($l = fgets($fh)) !== false) {
        $l = trim($l);
        if ($l === '' || stripos($l, _DB_PREFIX_.'pulse') === false) { continue; }
        ++$lines;
        $distinct[md5($l)] = 1;
    }
    fclose($fh);
    printf("corpus %s: %d statements touching %spulse%%, %d distinct\n", $SQL_FILE, $lines, _DB_PREFIX_, count($distinct));
}
exit(0);
