<?php
/**
 * PABX / telephone interface. Two directions:
 *  inbound  — the PABX (or a small bridge on the PABX server) POSTs CDR and dial-code events to /pulse/api/frontdesk/pabx
 *  outbound — Pulse asks the PABX to set a wake-up, enable/bar a room phone at check-in/out
 * Ships with PulsePabxGeneric (HTTP JSON to a bridge URL). Implement this for Panasonic/Grandstream/Yeastar specifics.
 */
interface PulsePabxInterface
{
    public function __construct(array $config);
    public function setWakeUp($extension, DateTime $at);
    public function cancelWakeUp($extension);
    public function setRoomPhone($extension, $enabled, $guestName = '');
    public function test();
}
