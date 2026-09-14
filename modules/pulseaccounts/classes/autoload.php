<?php
/* The hotel-scoping layer: every Pulse query goes through PulseDb, so it loads first. */
foreach (array('pulsecore/classes/PulseHotelScope.php', 'pulsecore/classes/PulseDb.php', 'pulsecore/classes/PulseDbHandle.php', 'pulsehotel/classes/PulseHotelContext.php') as $__s) { if (file_exists(_PS_MODULE_DIR_.$__s)) { require_once _PS_MODULE_DIR_.$__s; } }
/** Autoloader for pulseaccounts classes; pulls in the core facade, the API base and Front Desk when present. */
spl_autoload_register(function ($c) { $f = dirname(__FILE__).'/'.$c.'.php'; if (is_file($f)) { require_once $f; } });
if (file_exists(_PS_MODULE_DIR_.'pulsecore/classes/PulseCoreService.php')) { require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseCoreService.php'; }
if (file_exists(_PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php')) { require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php'; }
if (file_exists(_PS_MODULE_DIR_.'pulsefrontdesk/classes/autoload.php')) { require_once _PS_MODULE_DIR_.'pulsefrontdesk/classes/autoload.php'; }
