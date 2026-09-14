<?php
foreach (array('pulsecore/classes/PulseCoreService.php', 'pulsecore/classes/PulseApiController.php', 'pulsecore/classes/PulseHotelScope.php', 'pulsecore/classes/PulseDb.php', 'pulsecore/classes/PulseDbHandle.php', 'pulsehotel/classes/PulseHotelContext.php') as $__f) { if (file_exists(_PS_MODULE_DIR_.$__f)) { require_once _PS_MODULE_DIR_.$__f; } }
/** Autoloader for pulsehotel classes. */
spl_autoload_register(function ($c) { $f = dirname(__FILE__).'/'.$c.'.php'; if (is_file($f)) { require_once $f; } });
