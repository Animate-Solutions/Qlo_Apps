<?php
/** Upgrade path v1.0.0 → v1.1.0 for sites that installed Front Desk v1.0. Fresh installs get everything from sql/install.sql. */
if (!defined('_PS_VERSION_')) { exit; }
function upgrade_module_1_1_0($module)
{
    $sql = Tools::file_get_contents(dirname(__FILE__).'/../sql/upgrade-1.1.0.sql');
    $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), $sql);
    foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) {
        if (strpos($q, '--') === 0) { continue; }
        try { Db::getInstance()->execute($q); } catch (Exception $e) { /* ALTER on already-upgraded table: ignore */ }
    }
    // new tabs (positions follow the order in the module's $tabs list)
    $parent = (int) Tab::getIdFromClassName('AdminPulseCore');
    $ref = new ReflectionProperty($module, 'tabs'); $ref->setAccessible(true); $tabs = $ref->getValue($module);
    $i = 0;
    foreach ($tabs as $class => $label) {
        $id = (int) Tab::getIdFromClassName($class);
        $t = $id ? new Tab($id) : new Tab();
        $t->class_name = $class; $t->module = $module->name; $t->id_parent = $parent; $t->position = $i++;
        foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $label; }
        $id ? $t->update() : $t->add();
    }
    foreach (array('actionPulseStayChanged', 'actionPulseTicketCreated', 'moduleRoutes') as $h) { $module->registerHook($h); }
    foreach (array('PULSE_FD_LATE_GRACE' => 60, 'PULSE_FD_PRECHECKIN_DAYS' => 2, 'PULSE_FD_TERMS_VERSION' => '1.0', 'PULSE_FD_SMS_CHANNEL' => 'sms', 'PULSE_FD_WALKIN_PAYMENT_MODULE' => 'bankwire') as $k => $v) {
        if (Configuration::get($k) === false) { Configuration::updateValue($k, $v); }
    }
    if (!Configuration::get('PULSE_FD_WALKIN_ORDER_STATE')) { Configuration::updateValue('PULSE_FD_WALKIN_ORDER_STATE', (int) Configuration::get('PS_OS_PAYMENT')); }
    Configuration::updateValue('PULSE_FD_VERSION', '1.1.0');
    return true;
}
