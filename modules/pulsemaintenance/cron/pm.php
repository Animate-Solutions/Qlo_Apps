<?php
/** Daily: generate preventive work orders + escalate overdue. php cron/pm.php <token> */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/autoload.php';
$token = isset($argv[1]) ? $argv[1] : Tools::getValue('token');
if ($token !== Configuration::get('PULSE_MNT_CRON_TOKEN')) { die('Invalid token'); }
Context::getContext()->employee = new Employee((int) Configuration::get('PS_CRON_EMPLOYEE_ID') ?: 1);
$n = PulseMaintenanceService::pmGenerate();
$overdue = Db::getInstance()->executeS('SELECT id_pulse_work_order, wo_no, priority FROM `'._DB_PREFIX_.'pulse_work_order` WHERE due_at<NOW() AND status IN ("open","assigned") AND priority IN ("normal","low")');
foreach ($overdue as $o) { Db::getInstance()->update('pulse_work_order', array('priority' => 'high'), 'id_pulse_work_order='.(int) $o['id_pulse_work_order']); PulseCoreService::audit('pulsemaintenance', 'escalate', array('wo' => $o['wo_no'])); }
echo "PM generated: $n, escalated: ".count($overdue);
