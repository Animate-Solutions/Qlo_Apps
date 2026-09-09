<?php
/**
 * One channel connector. Implement this to add an OTA.
 *
 * pushAri()          receives normalised ARI rows (see PulseChAri::rowsFor()) and must return
 *                    array('ok' => bool, 'sent' => int, 'error' => string|null, 'raw' => mixed)
 * pullReservations() returns array of raw reservation payloads since $since (Y-m-d H:i:s), newest last
 * ackReservation()   confirms delivery of one channel reference back to the OTA
 * testConnection()   a cheap round trip used by the Settings screen
 * capabilities()     what this connector actually supports, so the UI can hide what it cannot do
 */
interface PulseChAdapterInterface
{
    public function __construct(array $channel, array $credentials);
    public function pushAri(array $rows);
    public function pullReservations($since);
    public function ackReservation($ref);
    public function testConnection();
    public function capabilities();
}
