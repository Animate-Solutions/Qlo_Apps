<?php
/* The hotel-scoping layer: every Pulse query goes through PulseDb, so it loads first. */
foreach (array('pulsecore/classes/PulseHotelScope.php', 'pulsecore/classes/PulseDb.php', 'pulsecore/classes/PulseDbHandle.php', 'pulsehotel/classes/PulseHotelContext.php') as $__s) { if (file_exists(_PS_MODULE_DIR_.$__s)) { require_once _PS_MODULE_DIR_.$__s; } }
spl_autoload_register(function ($c) { $f = dirname(__FILE__).'/'.$c.'.php'; if (is_file($f)) { require_once $f; } });
foreach (array('pulsecore/classes/PulseCoreService.php', 'pulsefrontdesk/classes/autoload.php', 'pulsereports/classes/autoload.php', 'pulsepos/classes/autoload.php', 'pulsemaintenance/classes/autoload.php') as $f) { if (file_exists(_PS_MODULE_DIR_.$f)) { require_once _PS_MODULE_DIR_.$f; } }
