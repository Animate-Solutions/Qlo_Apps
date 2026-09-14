<?php
foreach (array('pulsecore/classes/PulseCoreService.php', 'pulsecore/classes/PulseApiController.php', 'pulsecore/classes/PulseHotelScope.php', 'pulsecore/classes/PulseDb.php', 'pulsecore/classes/PulseDbHandle.php', 'pulsehotel/classes/PulseHotelContext.php') as $__f) { if (file_exists(_PS_MODULE_DIR_.$__f)) { require_once _PS_MODULE_DIR_.$__f; } }
/** Simple PSR-0-ish autoloader for pulsefrontdesk classes. */
spl_autoload_register(function ($class) {
    $file = dirname(__FILE__).'/'.$class.'.php';
    if (is_file($file)) {
        require_once $file;
    }
});
