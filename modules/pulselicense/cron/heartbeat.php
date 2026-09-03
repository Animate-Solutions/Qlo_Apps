<?php
/** Optional daily cron: keeps server-bound licenses confirmed even if nobody opens the back office. */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/PulseLicenseService.php';
$r = PulseLicenseService::heartbeat(true);
echo $r ? 'OK '.json_encode($r) : 'no server / unreachable';
