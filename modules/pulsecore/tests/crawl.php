<?php
/**
 * Crawl every Pulse back-office screen as a signed-in employee and report what each one did.
 *
 * Runs the controllers in-process rather than over HTTP, so a fatal is caught here with a stack
 * trace instead of showing up as a blank 500, and so the SQL each screen issues can be captured.
 *
 * Usage:
 *   php modules/pulsecore/tests/crawl.php                    # every Pulse screen, hotel from the employee
 *   php modules/pulsecore/tests/crawl.php --hotel=2          # pretend the session chose hotel 2
 *   php modules/pulsecore/tests/crawl.php --sql=/tmp/c.sql   # also write every statement issued
 *   php modules/pulsecore/tests/crawl.php --only=AdminPulseFolio
 *
 * Output is one line per screen: the controller, the outcome, and the counts that matter.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

$opt = array('hotel' => 0, 'sql' => '', 'only' => '', 'json' => '');
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $a, $m)) { $opt[$m[1]] = $m[2]; }
}

define('_PS_ADMIN_DIR_', dirname(__FILE__).'/../../../adminanimate');
require_once dirname(__FILE__).'/../../../config/config.inc.php';

/* ---- sign in as an employee, the way the admin controllers expect ---- */
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
// The list templates call $link->getAdminLink(); without this the four list screens die in Smarty
// for a reason that has nothing to do with what is being tested.
$context->link = new Link();
// initHeader() is what normally assigns `link` into Smarty, and this harness does not call it.
$context->smarty->assign(array('link' => $context->link, 'token' => '', 'currentIndex' => ''));

// The scoping classes are loaded by the module autoloaders, which have not run yet at this point,
// so load them here — otherwise choosing a hotel silently does nothing and every isolation test
// passes for the wrong reason.
foreach (array('pulsecore/classes/PulseHotelScope.php', 'pulsecore/classes/PulseDb.php',
               'pulsecore/classes/PulseDbHandle.php', 'pulsehotel/classes/PulseHotelContext.php') as $f) {
    if (file_exists(_PS_MODULE_DIR_.$f)) { require_once _PS_MODULE_DIR_.$f; }
}
if ((int) $opt['hotel']) {
    if (!class_exists('PulseHotelContext')) { fwrite(STDERR, "pulsehotel is not installed; --hotel has no effect\n"); exit(2); }
    $context->cookie->{PulseHotelContext::COOKIE} = (int) $opt['hotel'];
    PulseHotelContext::reset();
    if (PulseHotelContext::id() !== (int) $opt['hotel']) {
        fwrite(STDERR, sprintf("employee %d has no access to hotel %d\n", $employee->id, (int) $opt['hotel']));
        exit(2);
    }
    fwrite(STDERR, sprintf("crawling as employee %d in hotel %d (%s)\n", $employee->id, PulseHotelContext::id(), PulseHotelContext::name()));
}

/* ---- capture every statement, if asked ---- */
if ($opt['sql']) { @unlink($opt['sql']); putenv('PULSE_SQL_CAPTURE='.$opt['sql']); }

/* ---- the screens: every tab a Pulse module installed ---- */
$where = "t.module LIKE 'pulse%'";
if ($opt['only']) { $where .= " AND t.class_name = '".pSQL($opt['only'])."'"; }
$tabs = Db::getInstance()->executeS('SELECT t.class_name, t.module FROM `'._DB_PREFIX_.'tab` t WHERE '.$where.' ORDER BY t.module, t.class_name');

$results = array();
$clean = $failed = 0;

foreach ($tabs as $tab) {
    $class = $tab['class_name'].'Controller';
    $file = null;
    foreach (glob(_PS_MODULE_DIR_.$tab['module'].'/controllers/admin/'.$class.'.php') as $f) { $file = $f; }
    if (!$file) {
        $results[] = array($tab['class_name'], $tab['module'], 'NO FILE', '');
        continue;
    }
    require_once $file;
    if (!class_exists($class)) {
        $results[] = array($tab['class_name'], $tab['module'], 'NO CLASS', '');
        ++$failed;
        continue;
    }

    $note = '';
    $status = 'OK';
    ob_start();
    try {
        $_GET['controller'] = $tab['class_name'];
        $_GET['token'] = Tools::getAdminTokenLite($tab['class_name']);
        $c = new $class();
        $c->id = (int) Tab::getIdFromClassName($tab['class_name']);
        $c->controller_name = $tab['class_name'];
        $c->php_self = $tab['class_name'];
        $c->init();
        $c->initProcess();
        $c->initContent();
        $out = ob_get_clean();
        $errs = array();
        if (preg_match('/(Fatal error|Uncaught\s+\w*(Exception|Error))/i', $out)) { $errs[] = 'fatal in output'; }
        if (preg_match('/(SQLSTATE|Duplicate entry|Unknown column|doesn\'t exist)/i', $out)) { $errs[] = 'sql error in output'; }
        if (preg_match('/(Smarty|Undefined (variable|index|property))/i', $out)) { $errs[] = 'template/undefined notice'; }
        if ($errs) { $status = 'WARN'; $note = implode('; ', $errs); ++$failed; } else { ++$clean; }
    } catch (Throwable $e) {
        ob_end_clean();
        $status = 'FATAL';
        $note = get_class($e).': '.$e->getMessage().' @ '.str_replace(_PS_ROOT_DIR_.'/', '', $e->getFile()).':'.$e->getLine();
        ++$failed;
    }
    $results[] = array($tab['class_name'], $tab['module'], $status, $note);
}

foreach ($results as $r) {
    printf("%-34s %-18s %-6s %s\n", $r[0], $r[1], $r[2], $r[3]);
}
printf("\n%d screens: %d clean, %d with something to look at\n", count($results), $clean, $failed);

if ($opt['json']) {
    file_put_contents($opt['json'], json_encode($results, 64 | 128));
}
exit($failed ? 1 : 0);
