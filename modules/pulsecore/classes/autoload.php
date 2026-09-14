<?php
/* The hotel-scoping layer: every Pulse query goes through PulseDb, so it loads first. */
foreach (array('pulsecore/classes/PulseHotelScope.php', 'pulsecore/classes/PulseDb.php', 'pulsecore/classes/PulseDbHandle.php', 'pulsehotel/classes/PulseHotelContext.php') as $__s) { if (file_exists(_PS_MODULE_DIR_.$__s)) { require_once _PS_MODULE_DIR_.$__s; } }
/** Simple PSR-0-ish autoloader for pulsecore classes. */
spl_autoload_register(function ($class) {
    $file = dirname(__FILE__).'/'.$class.'.php';
    if (is_file($file)) {
        require_once $file;
    }
});
