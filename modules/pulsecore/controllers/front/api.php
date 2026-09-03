<?php
/**
 * Front controller: /pulse/api/core/{resource}/{id}
 * Token-authenticated JSON API (see PulseApiController in pulsecore).
 */
require_once _PS_MODULE_DIR_.'pulsecore/classes/PulseApiController.php';

class PulseCoreApiModuleFrontController extends PulseApiController
{
    /** Map resource name => method. Add resources here. */
    protected $resources = array(
        'ping' => 'ping',
    );

    protected function ping()
    {
        return array('module' => 'pulsecore', 'version' => PulseCore::VERSION, 'time' => date('c'));
    }
}
