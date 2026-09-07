<?php
/**
 * Upgrade from the 0.1.0 scaffold (5 stub tables, 1 tab) to POS 1.0.0.
 * Runs automatically when PrestaShop sees version 1.0.0 in the module files (Modules → "Update" on Pulse POS).
 * The scaffold tables never held live data; they are replaced with the 1.0.0 schema.
 */
if (!defined('_PS_VERSION_')) { exit; }
function upgrade_module_1_0_0($module)
{
    $D = Db::getInstance();
    // scaffold detection: 0.1.0 outlet table has no `code` column
    $isScaffold = $D->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'pulse_pos_outlet"') && !$D->executeS('SHOW COLUMNS FROM `'._DB_PREFIX_.'pulse_pos_outlet` LIKE "code"');
    if ($isScaffold) { foreach (array('outlet', 'table', 'menu_item', 'bill', 'bill_line') as $t) { $D->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'pulse_pos_'.$t.'`'); } }
    $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, _MYSQL_ENGINE_), Tools::file_get_contents(dirname(__FILE__).'/../sql/install.sql'));
    $sql = preg_replace('/^\s*--.*(?:\r\n|\r|\n|$)/m', '', $sql);
    foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) { $D->execute($q); }
    // tabs
    $parent = (int) Tab::getIdFromClassName('AdminPulseCore'); $i = 20;
    foreach (array('AdminPulsePos' => 'F&B POS', 'AdminPulsePosMenu' => 'POS Menu', 'AdminPulsePosInventory' => 'F&B Inventory', 'AdminPulsePosReports' => 'POS Reports', 'AdminPulsePosSettings' => 'POS Settings') as $c => $n) {
        $id = (int) Tab::getIdFromClassName($c); $t = $id ? new Tab($id) : new Tab(); $t->class_name = $c; $t->module = 'pulsepos'; $t->id_parent = $parent; $t->position = $i++;
        foreach (Language::getLanguages(true) as $l) { $t->name[$l['id_lang']] = $n; } $id ? $t->update() : $t->add();
    }
    foreach (array('displayBackOfficeHeader', 'moduleRoutes', 'actionPulseBeforeCheckOut', 'actionPulseNightAuditClosed', 'actionPulsePosBillSettled', 'actionPulsePosKotFired', 'actionPulsePosItemReady', 'actionPulsePosRoomCharge', 'actionPulsePosStockMove') as $h) { $module->registerHook($h); }
    foreach (array('PULSE_POS_AUTO_LOGOUT_MIN' => 3, 'PULSE_POS_CASH_ROUNDING' => 0, 'PULSE_POS_KDS_LATE_MIN' => 15, 'PULSE_POS_TIP_ON_CARD' => 1, 'PULSE_POS_ROOM_GUEST_CHECK' => 1, 'PULSE_POS_DEFAULT_TENDER' => 'cash', 'PULSE_POS_DRAWER_HOST' => '', 'PULSE_POS_RECEIPT_HOST' => '', 'PULSE_POS_BLIND_CLOSE' => 1) as $k => $v) { if (Configuration::get($k) === false) { Configuration::updateValue($k, $v); } }
    $emp = (int) $D->getValue('SELECT MIN(id_employee) FROM `'._DB_PREFIX_.'employee` WHERE active=1');
    if ($emp && !$D->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'pulse_pos_staff`')) { $D->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'pulse_pos_staff` (id_employee, pin_hash, role, can_void_sent, can_discount, max_discount_pct, can_reopen, can_comp, can_settle) VALUES ('.$emp.', "'.pSQL(PulsePosService::setPin($emp, '1234')).'", "manager", 1, 1, 100, 1, 1, 1)'); }
    return true;
}
