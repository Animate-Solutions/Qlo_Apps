<?php
spl_autoload_register(function ($c) { $f = dirname(__FILE__).'/'.$c.'.php'; if (is_file($f)) { require_once $f; } });
if (file_exists(_PS_MODULE_DIR_.'pulsecore/classes/PulseCoreService.php')) { require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseCoreService.php'; }
if (file_exists(_PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php')) { require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php'; }
if (file_exists(_PS_MODULE_DIR_.'pulsefrontdesk/classes/autoload.php')) { require_once _PS_MODULE_DIR_.'pulsefrontdesk/classes/autoload.php'; }
if (file_exists(_PS_MODULE_DIR_.'pulsepos/classes/autoload.php')) { require_once _PS_MODULE_DIR_.'pulsepos/classes/autoload.php'; }
if (file_exists(_PS_MODULE_DIR_.'pulselaundry/classes/PulseLaundryService.php')) { require_once _PS_MODULE_DIR_.'pulselaundry/classes/PulseLaundryService.php'; }
