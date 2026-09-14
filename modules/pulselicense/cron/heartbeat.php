<?php
/**
 * Optional daily cron: keeps server-bound licenses confirmed even if nobody opens the back office.
 *
 * The only Pulse cron that does not work through the properties: one licence covers the whole install,
 * keyed to its domain and fingerprint, and it answers into Configuration and pulse_license_log — both
 * group-level. Running it once per hotel would send the same heartbeat N times and log N identical rows.
 */
require_once dirname(__FILE__).'/../../../config/config.inc.php';
require_once dirname(__FILE__).'/../classes/PulseLicenseService.php';
$r = PulseLicenseService::heartbeat(true);
echo $r ? 'OK '.json_encode($r) : 'no server / unreachable';
