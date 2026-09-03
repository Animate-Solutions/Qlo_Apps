<?php
/** Simple PSR-0-ish autoloader for pulsefrontdesk classes. */
spl_autoload_register(function ($class) {
    $file = dirname(__FILE__).'/'.$class.'.php';
    if (is_file($file)) {
        require_once $file;
    }
});
